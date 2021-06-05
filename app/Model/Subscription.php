<?php

namespace Acelle\Model;

use Carbon\Carbon;
use LogicException;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

use Acelle\Model\Invoice;

class Subscription extends Model
{
    const STATUS_NEW = 'new';
    const STATUS_ACTIVE = 'active';
    const STATUS_ENDED = 'ended';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'trial_ends_at', 'ends_at', 'current_period_ends_at',
        'created_at', 'updated_at', 'started_at', 'last_period_ends_at'
    ];

    /**
     * Indicates if the plan change should be prorated.
     *
     * @var bool
     */
    protected $prorate = true;

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var string|null
     */
    protected $billingCycleAnchor = null;

    /**
     * Bootstrap any application services.
     */
    public static function boot()
    {
        parent::boot();

        // Create uid when creating list.
        static::creating(function ($item) {
            // Create new uid
            $item->uid = uniqid();
        });
    }

    /**
     * Find item by uid.
     *
     * @return object
     */
    public static function findByUid($uid)
    {
        return self::where('uid', '=', $uid)->first();
    }

    /**
     * Associations.
     *
     * @var object | collect
     */
    public function plan()
    {
        // @todo dependency injection
        return $this->belongsTo('\Acelle\Model\Plan', 'plan_id', 'uid');
    }

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        // @todo dependency injection
        return $this->belongsTo('\Acelle\Model\Customer', 'user_id', 'uid');
    }

    /**
     * Get related invoices.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoices()
    {
        $uid = $this->uid;
        $type = self::class;
        return Invoice::whereIn('id', function ($query) use ($uid, $type) {
            $query->select('invoice_id')
            ->from(with(new InvoiceItem)->getTable())
            ->where('item_type', $type)
            ->where('item_id', $uid);
        });
    }

    /**
     * Get last invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function lastInvoice()
    {
        return $this->invoices()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get pending invoices. (NEW|CLAIMED)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pendingInvoices()
    {
        return $this->invoices()
            ->whereIn('status', [
                Invoice::STATUS_NEW,
                Invoice::STATUS_CLAIMED,
            ]);
    }

    public function hasPendingInvoice()
    {
        return $this->pendingInvoice() != null;
    }

    /**
     * Get pending invoice. (last invoice that is NEW/CLAIMED)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pendingInvoice()
    {
        return $this->pendingInvoices()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get new invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function changePlanInvoice()
    {
        return $this->invoices()
            ->orderBy('created_at', 'desc')
            ->where('type', Invoice::TYPE_CHANGE_PLAN)
            ->first();
    }

    /**
     * Get new invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function renewInvoice()
    {
        return $this->invoices()
            ->orderBy('created_at', 'desc')
            ->where('type', Invoice::TYPE_RENEW_SUBSCRIPTION)
            ->first();
    }

    /**
     * Get new invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function newInvoice()
    {
        return $this->invoices()
            ->orderBy('created_at', 'desc')
            ->where('status', Invoice::STATUS_NEW)
            ->first();
    }

    /**
     * Get claimed invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function claimedInvoice()
    {
        return $this->invoices()
            ->orderBy('created_at', 'desc')
            ->where('status', Invoice::STATUS_CLAIMED)
            ->first();
    }

    /**
     * Create init invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createInitInvoice()
    {
        //
        if ($this->pendingInvoice()) {
            throw new \Exception(trans('messages.error.has_waiting_invoices'));
        }

        // create invoice
        $invoice = new Invoice();
        $invoice->status = Invoice::STATUS_NEW;
        $invoice->type = Invoice::TYPE_NEW_SUBSCRIPTION;
        $invoice->title = trans('messages.invoice.init_subscription');
        $invoice->description = trans('messages.invoice.init_subscription.desc', [
            'plan' => $this->plan->name,
            'date' => \Acelle\Library\Tool::formatDate($this->getPeriodEndsAt(\Carbon\Carbon::now())),
        ]);
        $invoice->customer_id = $this->user->id;
        $invoice->currency_id = $this->plan->currency_id;
        $invoice->save();

        // data
        $invoice->updateMetadata([
            'subscription_uid' => $this->uid,
        ]);

        // add item
        $invoiceItem = $invoice->invoiceItems()->create([
            'item_id' => $this->uid,
            'item_type' => get_class($this),
            'amount' => $this->plan->price,
            'title' => $this->plan->name,
            'description' => view('plans._bill_desc', ['plan' => $this->plan]),
        ]);

        return $invoice;
    }

    /**
     * Create renew invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createRenewInvoice()
    {
        //
        if ($this->pendingInvoice()) {
            throw new \Exception(trans('messages.error.has_waiting_invoices'));
        }

        // create invoice
        $invoice = new Invoice();
        $invoice->status = Invoice::STATUS_NEW;
        $invoice->type = Invoice::TYPE_RENEW_SUBSCRIPTION;
        $invoice->title = trans('messages.invoice.renew_subscription');
        $invoice->description = trans('messages.renew_subscription.desc', [
            'plan' => $this->plan->name,
            'date' => \Acelle\Library\Tool::formatDate($this->nextPeriod()),
        ]);
        $invoice->customer_id = $this->user->id;
        $invoice->currency_id = $this->plan->currency_id;
        $invoice->save();

        // data
        $invoice->updateMetadata([
            'subscription_uid' => $this->uid,
        ]);

        // add item
        $invoiceItem = $invoice->invoiceItems()->create([
            'item_id' => $this->uid,
            'item_type' => get_class($this),
            'amount' => $this->plan->price,
            'title' => $this->plan->name,
            'description' => view('plans._bill_desc', ['plan' => $this->plan]),
        ]);

        return $invoice;
    }

    /**
     * Create change plan invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createChangePlanInvoice($newPlan)
    {
        //
        if ($this->pendingInvoice()) {
            $this->pendingInvoice()->delete();
        }

        // calculate change plan amout ends at
        $metadata = $this->calcChangePlan($newPlan);

        // create invoice
        $invoice = new Invoice();
        $invoice->status = Invoice::STATUS_NEW;
        $invoice->type = Invoice::TYPE_CHANGE_PLAN;
        $invoice->title = trans('messages.invoice.change_plan');
        $invoice->description = trans('messages.change_plan.desc', [
            'plan' => $this->plan->name,
            'newPlan' => $newPlan->name,
            'date' => \Acelle\Library\Tool::formatDate(\Carbon\Carbon::parse($metadata['endsAt'])),
        ]);
        $invoice->customer_id = $this->user->id;
        $invoice->currency_id = $this->plan->currency_id;
        $invoice->save();

        // save data
        $invoice->updateMetadata([
            'subscription_uid' => $this->uid,
            'new_plan_uid' => $newPlan->uid,
        ]);

        // add item
        $invoiceItem = $invoice->invoiceItems()->create([
            'item_id' => $this->uid,
            'item_type' => get_class($this),
            'amount' => $metadata['amount'],
            'title' => $this->plan->name,
            'description' => view('plans._bill_desc', ['plan' => $this->plan]),
        ]);

        return $invoice;
    }

    /**
     * Set subscription as ended.
     *
     * @return bool
     */
    public function setEnded()
    {
        // then set the sub end
        $this->status = self::STATUS_ENDED;
        $this->ends_at = \Carbon\Carbon::now();
        $this->save();
    }

    /**
     * Get lastest bill information
     *
     * @return void
     */
    public function getUpcomingBillingInfo()
    {
        if (!$this->pendingInvoice()) {
            return null;
        }

        return $this->pendingInvoice()->getBillingInfo();

        // if ($this->cancelled()) {
        //     return null;
        // }

        // if (!$this->canRenewPlan()) {
        //     return null;
        // }

        // // has wait renew invoice
        // if ($this->waitRenewInvoice()) {
        //     return $this->waitRenewInvoice()->getBillingInfo();
        // } else {
        //     return [
        //         'title' => trans('messages.upcoming_bill.title'),
        //         'description' => trans('messages.upcoming_bill.desc', [
        //             'plan' => $this->plan->name,
        //             'date' => $this->current_period_ends_at,
        //         ]),
        //         'bill' => [
        //             [
        //                 'name' => $this->plan->name,
        //                 'desc' => view('plans._bill_desc', ['plan' => $this->plan]),
        //                 'price' => format_price($this->plan->price, $this->plan->currency->format),
        //                 'tax' => format_price(0, $this->plan->currency->format),
        //                 'discount' => format_price(0, $this->plan->currency->format),
        //             ]
        //         ],
        //         'charge_info' => $this->user->supportsAutoBilling() ? trans('messages.bill.auto_charge', ['date' => $this->nextPeriod()]) : trans('messages.bill.charge_before', ['date' => $this->nextPeriod()]),
        //         'total' => format_price($this->plan->price, $this->plan->currency->format),
        //     ];
        // }
    }

    /**
     * Get period by start date.
     *
     * @param  date  $date
     * @return date
     */
    public function getPeriodEndsAt($startDate)
    {
        // dose not support recurring, update ends at column
        $interval = $this->plan->getBillableInterval();
        $intervalCount = $this->plan->getBillableIntervalCount();

        switch ($interval) {
            case 'month':
                $endsAt = $startDate->addMonthsNoOverflow($intervalCount);
                break;
            case 'day':
                $endsAt = $startDate->addDay($intervalCount);
                // no break
            case 'week':
                $endsAt = $startDate->addWeek($intervalCount);
                break;
            case 'year':
                $endsAt = $startDate->addYearsNoOverflow($intervalCount);
                break;
            default:
                $endsAt = null;
        }

        return $endsAt;
    }

    /**
     * Check subscription status.
     *
     * @param  Int  $subscriptionId
     * @return date
     */
    public function check()
    {
        switch ($this->status) {
            case self::STATUS_NEW:
                // nothing to check
                break;
            case self::STATUS_ACTIVE:
                // check expired
                if ($this->isExpired()) {
                    $this->cancelNow();
                    return;
                }

                // check expiring
                if ($this->isExpiring() && $this->canRenewPlan() && !$this->cancelled()) {
                    $pendingInvoice = $this->pendingInvoice();

                    // create renew invoice if no pending invoice
                    if (!$pendingInvoice) {
                        $pendingInvoice = $this->createRenewInvoice();
                    }

                    // check if invoice is change plan -> do nothing
                    if ($pendingInvoice->isChangePlanInvoice()) {
                        return;
                    }

                    // service auto charge
                    if (
                        $this->user->supportsAutoBilling() &&
                        $this->reachDueDate() // check charge day before
                    ) {
                        // add pending transaction for invoice
                        $pendingInvoice->addTransaction([
                            'status' => \Acelle\Model\Transaction::STATUS_PENDING,
                            'message' => trans('messages.automatically_charge', [
                                'id' => $pendingInvoice->uid,
                                'title' => $pendingInvoice->title,
                            ]),
                        ]);
                        
                        // charge invoice
                        $this->user->getPaymentGateway()->charge($pendingInvoice, true);
                    }
                }
                break;
            case self::STATUS_ENDED:
                // nothing to check
                break;
        }
    }

    /**
     * reach due date.
     */
    public function reachDueDate()
    {
        return \Carbon\Carbon::now()->greaterThanOrEqualTo(
            $this->current_period_ends_at->subDays(\Acelle\Model\Setting::get('recurring_charge_before_days'))
        );
    }
    
    /**
     * Change plan.
     */
    public function changePlan($newPlan)
    {
        // calculate change plan amout ends at
        $metadata = $this->calcChangePlan($newPlan);

        // new plan
        $this->plan_id = $newPlan->uid;

        // new end period
        $this->current_period_ends_at = $metadata['endsAt'];
        
        // update ends at if it exist
        if ($this->ends_at != null) {
            $this->ends_at = $this->current_period_ends_at;
        }

        $this->save();

        // logs
        $this->addLog(SubscriptionLog::TYPE_PLAN_CHANGED, [
            'old_plan' => $this->plan->getBillableName(),
            'plan' => $newPlan->getBillableName(),
        ]);
    }

    /**
     * Check subscription status.
     *
     * @param  Int  $subscriptionId
     * @return date
     */
    public static function checkAll()
    {
        $subscriptions = self::whereNull('ends_at')->orWhere('ends_at', '>=', \Carbon\Carbon::now())->get();
        foreach ($subscriptions as $subscription) {
            $subscription->check();
        }
    }

    /**
     * Associations.
     *
     * @var object | collect
     */
    public function subscriptionLogs()
    {
        // @todo dependency injection
        return $this->hasMany('\Acelle\Model\SubscriptionLog');
    }

    /**
     * Get all transactions from invoices.
     */
    public function transactions()
    {
        $uid = $this->uid;
        return \Acelle\Model\Transaction::whereIn('invoice_id', $this->invoices()->select('id'))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     *
     * @return bool
     */
    public function isRecurring()
    {
        return ! $this->cancelled();
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status == self::STATUS_ACTIVE;
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function isNew()
    {
        return $this->status == self::STATUS_NEW;
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Determine if the subscription is ended.
     *
     * @return bool
     */
    public function isEnded()
    {
        return $this->status == self::STATUS_ENDED;
    }

    /**
     * Determine if the subscription is pending.
     *
     * @return bool
     */
    public function activate()
    {
        if (!$this->isNew()) {
            throw new \Exception("Only new subscription can be activated, double check your code to make sure you only call activate() on a new subscription");
        }

        $this->current_period_ends_at = $this->getPeriodEndsAt(Carbon::now());
        $this->ends_at = null;
        $this->status = self::STATUS_ACTIVE;
        $this->started_at = \Carbon\Carbon::now();
        $this->save();

        // add log
        $this->addLog(SubscriptionLog::TYPE_SUBSCRIBED, [
            'plan' => $this->plan->getBillableName(),
            'price' => $this->plan->getBillableFormattedPrice(),
        ]);
    }

    /**
     * Next one period to subscription.
     *
     * @param  Gateway    $gateway
     * @return Boolean
     */
    public function nextPeriod()
    {
        return $this->getPeriodEndsAt($this->current_period_ends_at);
    }

    /**
     * Next one period to subscription.
     *
     * @param  Gateway    $gateway
     * @return Boolean
     */
    public function periodStartAt()
    {
        $startAt = $this->current_period_ends_at;
        $interval = $this->plan->getBillableInterval();
        $intervalCount = $this->plan->getBillableIntervalCount();

        switch ($interval) {
            case 'month':
                $startAt = $startAt->subMonthsNoOverflow($intervalCount);
                break;
            case 'day':
                $startAt = $startAt->subDay($intervalCount);
                // no break
            case 'week':
                $startAt = $startAt->subWeek($intervalCount);
                break;
            case 'year':
                $startAt = $startAt->subYearsNoOverflow($intervalCount);
                break;
            default:
                $startAt = null;
        }

        return $startAt;
    }

    /**
     * Check if subscription is expired.
     *
     * @param  Int  $subscriptionId
     * @return date
     */
    public function isExpired()
    {
        return isset($this->ends_at) && \Carbon\Carbon::now()->endOfDay() > $this->ends_at;
    }

    /**
     * Subscription transactions.
     *
     * @return array
     */
    public function getLogs()
    {
        return $this->subscriptionLogs()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Subscription transactions.
     *
     * @return array
     */
    public function addLog($type, $data, $transaction_id=null)
    {
        $log = new SubscriptionLog();
        $log->subscription_id = $this->id;
        $log->type = $type;
        $log->transaction_id = $transaction_id;
        $log->save();

        if (isset($data)) {
            $log->updateData($data);
        }

        return $log;
    }

    /**
     * Cancel subscription. Set ends at to the end of period.
     *
     * @return void
     */
    public function cancel()
    {
        $this->ends_at = $this->current_period_ends_at;
        $this->save();

        // delete pending invoice
        if ($this->pendingInvoice()) {
            $this->pendingInvoice()->delete();
        }
    }

    /**
     * Cancel subscription. Set ends at to the end of period.
     *
     * @return void
     */
    public function resume()
    {
        $this->ends_at = null;
        $this->save();
    }

    /**
     * Cancel subscription. Set ends at to the end of period.
     *
     * @return void
     */
    public function cancelNow()
    {
        // set status = ended
        $this->setEnded();

        // cancel all pending invoices (new|claimed)
        $this->pendingInvoices()->delete();

        // add log
        $this->addLog(SubscriptionLog::TYPE_CANCELLED_NOW, [
            'plan' => $this->plan->getBillableName(),
            'price' => $this->plan->getBillableFormattedPrice(),
        ]);
    }

    /**
     * Renew subscription
     *
     * @return void
     */
    public function renew()
    {
        // set new current period
        $this->current_period_ends_at = $this->getPeriodEndsAt($this->current_period_ends_at);
        
        // ends at
        if ($this->ends_at != null) {
            $this->ends_at = $this->current_period_ends_at;
        }

        $this->save();

        // logs
        $this->addLog(SubscriptionLog::TYPE_RENEWED, [
            'plan' => $this->plan->getBillableName(),
            'price' => $this->plan->getBillableFormattedPrice(),
        ]);
    }

    public function isExpiring()
    {
        // check if recurring accur
        if (\Carbon\Carbon::now()->greaterThanOrEqualTo($this->current_period_ends_at->subDays(Setting::get('end_period_last_days')))) {
            return true;
        }

        return false;
    }

    /**
     * Check if can renew free plan. amount > 0 or == 0 && renew_free_plan setting = true
     *
     * @return void
     */
    public function canRenewPlan()
    {
        return ($this->plan->getBillableAmount() > 0 ||
            (Setting::get('renew_free_plan') == 'yes' && $this->plan->getBillableAmount() == 0)
        );
    }

    /**
     * user want to change plan.
     *
     * @return bollean
     */
    public function calcChangePlan($plan)
    {
        if (($this->plan->getBillableInterval() != $plan->getBillableInterval()) ||
            ($this->plan->getBillableIntervalCount() != $plan->getBillableIntervalCount()) ||
            ($this->plan->getBillableCurrency() != $plan->getBillableCurrency())
        ) {
            throw new \Exception(trans('cashier::messages.can_not_change_to_diff_currency_period_plan'));
        }
        
        // new ends at
        $newEndsAt = $this->current_period_ends_at;

        // amout per day of current plan
        $currentAmount = $this->plan->getBillableAmount();
        $periodDays = $this->current_period_ends_at->diffInDays($this->periodStartAt()->startOfDay());
        $remainDays = $this->current_period_ends_at->diffInDays(\Carbon\Carbon::now()->startOfDay());
        $currentPerDayAmount = ($currentAmount/$periodDays);
        $newAmount = ($plan->price/$periodDays)*$remainDays;
        $remainAmount = $currentPerDayAmount*$remainDays;

        $amount = $newAmount - $remainAmount;
        
        // if amount < 0
        if ($amount < 0) {
            $days = (int) ceil(-($amount/$currentPerDayAmount));
            $amount = 0;
            $newEndsAt->addDays($days);
            
            // if free plan
            if ($plan->getBillableAmount() == 0) {
                $newEndsAt = $this->current_period_ends_at;
            }
        }

        return [
            'amount' => round($amount, 2),
            'endsAt' => $newEndsAt,
        ];
    }
}

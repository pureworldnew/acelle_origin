<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;

use Acelle\Model\Subscription;

class Invoice extends Model
{
    // statuses
    const STATUS_NEW = 'new';
    const STATUS_CLAIMED = 'claimed';
    const STATUS_PAID = 'paid';

    // type
    const TYPE_RENEW_SUBSCRIPTION = 'renew_subscription';
    const TYPE_NEW_SUBSCRIPTION = 'new_subscription';
    const TYPE_CHANGE_PLAN = 'change_plan';

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
     * Invoice currency.
     */
    public function currency()
    {
        return $this->belongsTo('Acelle\Model\Currency');
    }

    /**
     * Invoice customer.
     */
    public function customer()
    {
        return $this->belongsTo('Acelle\Model\Customer');
    }

    /**
     * Invoice items.
     */
    public function invoiceItems()
    {
        return $this->hasMany('Acelle\Model\InvoiceItem');
    }

    /**
     * Transactions.
     */
    public function transactions()
    {
        return $this->hasMany('Acelle\Model\Transaction');
    }

    /**
     * Get pending transaction.
     */
    public function pendingTransaction()
    {
        return $this->transactions()
            ->where('status', \Acelle\Model\Transaction::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Last transaction.
     */
    public function lastTransaction()
    {
        return $this->transactions()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Set as pending.
     *
     * @return void
     */
    public function setPending()
    {
        $this->status = self::STATUS_PENDING;
        $this->save();
    }

    /**
     * Set as paid.
     *
     * @return void
     */
    public function setPaid()
    {
        $this->status = self::STATUS_PAID;
        $this->save();
    }

    /**
     * Total items.
     *
     * @return void
     */
    public function total()
    {
        $total = 0;

        foreach ($this->invoiceItems as $item) {
            $total += $item->total();
        }

        return $total;
    }

    /**
     * formatted Total.
     *
     * @return void
     */
    public function formattedTotal()
    {
        return format_price($this->total(), $this->currency->format);
    }

    /**
     * Get metadata.
     *
     * @var object | collect
     */
    public function getMetadata($name=null)
    {
        if (!$this['metadata']) {
            return json_decode('{}', true);
        }

        $data = json_decode($this['metadata'], true);

        if ($name != null) {
            if (isset($data[$name])) {
                return $data[$name];
            } else {
                return null;
            }
        } else {
            return $data;
        }
    }

    /**
     * Get metadata.
     *
     * @var object | collect
     */
    public function updateMetadata($data)
    {
        $metadata = (object) array_merge((array) $this->getMetadata(), $data);
        $this['metadata'] = json_encode($metadata);

        $this->save();
    }

    // /**
    //  * Get type.
    //  *
    //  * @return void
    //  */
    // public function getType()
    // {
    //     return $this->invoiceItems()->first()->item_type;
    // }

    /**
     * Check new.
     *
     * @return void
     */
    public function isNew()
    {
        return $this->status == self::STATUS_NEW;
    }

    /**
     * Check claimed.
     *
     * @return void
     */
    public function isClaimed()
    {
        return $this->status == self::STATUS_CLAIMED;
    }

    /**
     * set status as claimed.
     *
     * @return void
     */
    public function setClaimed()
    {
        $this->status = self::STATUS_CLAIMED;
        $this->save();
    }

    /**
     * set status as new.
     *
     * @return void
     */
    public function setNew()
    {
        $this->status = self::STATUS_NEW;
        $this->save();
    }

    /**
     * Claim invoice.
     *
     * @return void
     */
    public function claim()
    {
        // set status as claimed
        $this->setClaimed();

        // just log
        $this->customer->subscription->addLog(SubscriptionLog::TYPE_CLAIMED, [
            'plan' => $this->customer->subscription->plan->getBillableName(),
            'price' => $this->customer->subscription->amount,
        ]);
    }

    /**
     * Approve invoice.
     *
     * @return void
     */
    public function approve()
    {
        // for only new|claimed invoice
        if (!$this->isClaimed()) {
            throw new \Exception("Trying to approve an invoice that is not 'claimed' (Invoice ID: {$this->id}, status: {$this->status}");
        }

        // fulfill invoice
        $this->fulfill();
    }

    /**
     * Reject invoice.
     *
     * @return void
     */
    public function reject($error, $auto=false)
    {
        // for only new|claimed invoice
        if (!$this->isClaimed()) {
            throw new \Exception("Trying to reject an invoice that is not 'claimed' (Invoice ID: {$this->id}, status: {$this->status}");
        }

        // fulfill invoice
        $this->payFailed($error, $auto);
    }
    
    /**
     * Pay invoice.
     *
     * @return void
     */
    public function fulfill()
    {
        // set status as claimed
        $this->setPaid();

        // set transaction as success
        // Important: according to current design, the rule is: one invoice only has one pending transaction
        $this->pendingTransaction()->setSuccess();
        
        // invoice after pay actions
        $this->process();
    }
    
    /**
     * Pay invoice failed.
     *
     * @return void
     */
    public function payFailed($error, $auto=false)
    {
        // set status as new
        $this->setNew();

        // set transaction as failed
        if ($auto == false) {
            $this->pendingTransaction()->setFailed(trans('messages.payment.cannot_charge', [
                'id' => $this->uid,
                'error' => $error,
                'service' => trans('messages.transaction.method.' . $this->pendingTransaction()->method),
            ]));
        } else {
            $this->pendingTransaction()->setFailed(trans('messages.payment.cannot_auto_charge', [
                'id' => $this->uid,
                'error' => $error,
                'service' => trans('messages.transaction.method.' . $this->pendingTransaction()->method),
            ]));
        }
    }

    /**
     * Process invoice.
     *
     * @return void
     */
    public function process()
    {
        $data = $this->getMetadata();
        switch ($this->type) {
            case self::TYPE_NEW_SUBSCRIPTION:
                $subscription = Subscription::findByUid($data['subscription_uid']);
                $subscription->activate();
                break;
            case self::TYPE_RENEW_SUBSCRIPTION:
                $subscription = Subscription::findByUid($data['subscription_uid']);
                $subscription->renew();
                break;
            case self::TYPE_CHANGE_PLAN:
                $subscription = Subscription::findByUid($data['subscription_uid']);
                $newPlan = \Acelle\Model\Plan::findByUid($data['new_plan_uid']);
                $subscription->changePlan($newPlan);
                break;
            default:
                throw new \Exception('Invoice type is not valid: ' . $this->type);
        }
    }

    /**
     * Check paid.
     *
     * @return void
     */
    public function isPaid()
    {
        return $this->status == self::STATUS_PAID;
    }

    /**
     * Check claimed.
     *
     * @return void
     */
    public function claimed()
    {
        return false;
    }

    /**
     * Check done.
     *
     * @return void
     */
    public function isDone()
    {
        return $this->status == self::STATUS_DONE;
    }

    /**
     * Check rejected.
     *
     * @return void
     */
    public function isRejected()
    {
        return $this->status == self::STATUS_REJECTED;
    }

    /**
     * Get billing info.
     *
     * @return void
     */
    public function getBillingInfo()
    {
        switch ($this->type) {
            case self::TYPE_RENEW_SUBSCRIPTION:
                $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                if (!$this->customer->supportsAutoBilling()) {
                    $chargeInfo = trans('messages.bill.charge_before', [
                        'date' => \Acelle\Library\Tool::formatDate($subscription->current_period_ends_at),
                    ]);
                } else {
                    $chargeInfo = trans('messages.bill.auto_charge', [
                        'date' => \Acelle\Library\Tool::formatDate($subscription->current_period_ends_at),
                    ]);
                }
                break;
            case self::TYPE_NEW_SUBSCRIPTION:
                $chargeInfo = trans('messages.bill.charge_now');
                break;
            case self::TYPE_CHANGE_PLAN:
                $chargeInfo = trans('messages.bill.charge_now');
                break;
            default:
                $chargeInfo = '';
        }
        
        return  [
            'title' => $this->title,
            'description' => $this->description,
            'bill' => $this->invoiceItems()->get()->map(function ($item) {
                return [
                    'title' => $item->title,
                    'description' => $item->description,
                    'price' => format_price($item->amount, $item->invoice->currency->format),
                    'tax' => format_price($item->tax, $item->invoice->currency->format),
                    'discount' => format_price($item->discount, $item->invoice->currency->format),
                ];
            }),
            'charge_info' => $chargeInfo,
            'total' => format_price($this->total(), $this->currency->format),
        ];
    }

    /**
     * Add transactions.
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
     * Check is renew subscription invoice.
     *
     * @return boolean
     */
    public function isRenewSubscriptionInvoice()
    {
        return $this->type == self::TYPE_RENEW_SUBSCRIPTION;
    }

    /**
     * Check is change plan invoice.
     *
     * @return boolean
     */
    public function isChangePlanInvoice()
    {
        return $this->type == self::TYPE_CHANGE_PLAN;
    }

    /**
     * Add transaction.
     *
     * @return array
     */
    public function addTransaction($data=[])
    {
        // @todo: dung transactions()->new....
        $transaction = new \Acelle\Model\Transaction();
        $transaction->invoice_id = $this->id;
        $transaction->amount = $this->formattedTotal();
        $transaction->message = $data['message'];
        $transaction->method = $this->customer->getPaymentMethod() ? $this->customer->getPaymentMethod()['method'] : trans('messages.payment_manual');
        $transaction->error = isset($data['error']) ? $data['error'] : null;
        $transaction->status = isset($data['status']) ? $data['status'] : \Acelle\Model\Transaction::STATUS_PENDING;
        $transaction->save();

        return $transaction;
    }
}

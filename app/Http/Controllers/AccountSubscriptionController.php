<?php

namespace Acelle\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LaravelLog;
use Acelle\Model\Subscription;
use Acelle\Model\Setting;
use Acelle\Model\Plan;
use Acelle\Cashier\Cashier;
use Acelle\Cashier\Services\StripeGatewayService;
use Carbon\Carbon;
use Acelle\Model\SubscriptionLog;

class AccountSubscriptionController extends Controller
{
    /**
     * Customer subscription main page.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     **/
    public function index(Request $request)
    {
        // init
        $customer = $request->user()->customer;
        $subscription = $customer->subscription;
        $gateway = $customer->getPaymentGateway();

        // No active subscription
        if (!$subscription ||
            $subscription->isEnded() ||
            ($subscription->isNew() && !$subscription->pendingInvoice())
        ) {
            $plans = Plan::getAvailablePlans();
            $planCount = count($plans);

            return view('account.subscription.select_plan', [
                'plans' => Plan::getAvailablePlans(),
                'subscription' => $subscription,
            ]);
        }

        // check subscription status. @todo: move to cron
        $subscription->check();

        // 2. IF PLAN NOT ACTIVE
        if (!$subscription->plan->isActive()) {
            return response()->view('errors.general', [ 'message' => __('messages.subscription.error.plan-not-active', [ 'name' => $subscription->plan->name]) ]);
        }
        
        // 3. NEW SUBSCRIPTION
        if ($subscription->isNew()) {
            $invoice = $subscription->lastInvoice();

            // new invoice do not have any transaction
            if ($invoice->isNew() && (!$invoice->lastTransaction() || $invoice->lastTransaction()->isFailed())) {
                return view('account.subscription.payment', [
                    'subscription' => $subscription,
                    'invoice' => $invoice,
                ]);
            }

            // redirect to service checkout
            return redirect()->away($gateway->getCheckoutUrl(
                $invoice,
                action('AccountSubscriptionController@index')
            ));
        }
        
        
        return view('account.subscription.index', [
            'subscription' => $subscription,
            'gateway' => $gateway,
            'plan' => $subscription->plan,
        ]);
    }

    /**
     * Select plan.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     **/
    public function init(Request $request)
    {
        // Get current customer
        $customer = $request->user()->customer;
        $plan = Plan::findByUid($request->plan_uid);

        // create new subscription
        $subscription = $customer->assignPlan($plan);

        // create init invoice
        if (!$subscription->pendingInvoice()) {
            $subscription->createInitInvoice();
        }

        // Check if subscriotion is new
        return redirect()->action('AccountSubscriptionController@index');
    }

    /**
     * Checkout subscription.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     **/
    public function checkout(Request $request)
    {
        // Get current customer
        $subscription = $request->user()->customer->subscription;
        $invoice = $subscription->lastInvoice();
        $gateway = $request->user()->customer->getPaymentGateway();

        // add pending transaction for invoice
        if (!$invoice->isPaid()) {
            $invoice->addTransaction([
                'status' => \Acelle\Model\Transaction::STATUS_PENDING,
                'message' => trans('messages.pay_invoice', [
                    'id' => $invoice->uid,
                    'title' => $invoice->title,
                ]),
            ]);
        }

        // return to subscription
        return redirect()->away($gateway->getCheckoutUrl(
            $invoice,
            action('AccountSubscriptionController@index')
        ));
    }

    /**
     * Invoice payment.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     **/
    public function payment(Request $request)
    {
        // Get current customer
        $customer = $request->user()->customer;
        $subscription = $customer->subscription;
        $gateway = $customer->getPaymentGateway();
        $invoice = $subscription->lastInvoice();

        // check if invoice has pending transaction
        if ($invoice->pendingTransaction()) {
            // return to subscription
            return redirect()->away($gateway->getCheckoutUrl(
                $invoice,
                action('AccountSubscriptionController@index')
            ));
        }

        if ($request->isMethod('post')) {
            // add pending transaction for invoice
            $invoice->addTransaction([
                'status' => \Acelle\Model\Transaction::STATUS_PENDING,
                'message' => trans('messages.pay_invoice', [
                    'id' => $invoice->uid,
                    'title' => $invoice->title,
                ]),
            ]);

            // redirect to service checkout
            return redirect()->away($gateway->getCheckoutUrl(
                $invoice,
                action('AccountSubscriptionController@index')
            ));
        }

        return view('account.subscription.payment', [
            'subscription' => $subscription,
            'invoice' => $invoice,
        ]);
    }
    
    /**
     * Change plan.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     **/
    public function changePlan(Request $request)
    {
        $customer = $request->user()->customer;
        $subscription = $customer->subscription;
        $gateway = $customer->getPaymentGateway();
        $plans = Plan::getAvailablePlans();
        
        // Authorization
        if (!$request->user()->customer->can('changePlan', $subscription)) {
            return $this->notAuthorized();
        }

        //
        if ($request->isMethod('post')) {
            $newPlan = Plan::findByUid($request->plan_uid);

            try {
                // set invoice as pending
                $changePlanInvoice = $subscription->createChangePlanInvoice($newPlan);
            } catch (\Exception $e) {
                $request->session()->flash('alert-error', $e->getMessage());
                return redirect()->action('AccountSubscriptionController@index');
            }

            // return to subscription
            return redirect()->action('AccountSubscriptionController@payment');
        }
        
        return view('account.subscription.change_plan', [
            'subscription' => $subscription,
            'gateway' => $gateway,
            'plans' => $plans,
        ]);
    }
    
    /**
     * Cancel subscription at the end of current period.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function cancel(Request $request)
    {
        $customer = $request->user()->customer;
        $subscription = $customer->subscription;

        if ($request->user()->customer->can('cancel', $subscription)) {
            $subscription->cancel();
        }

        // Redirect to my subscription page
        $request->session()->flash('alert-success', trans('messages.subscription.cancelled'));
        return redirect()->action('AccountSubscriptionController@index');
    }

    /**
     * Cancel subscription at the end of current period.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function cancelInvoice(Request $request, $uid)
    {
        $invoice = \Acelle\Model\Invoice::findByUid($uid);
        $subscription = $request->user()->customer->subscription;

        if (!$request->user()->customer->can('delete', $invoice)) {
            return $this->notAuthorized();
        }

        $invoice->delete();

        // Redirect to my subscription page
        $request->session()->flash('alert-success', trans('messages.invoice.cancelled'));
        return redirect()->action('AccountSubscriptionController@index');
    }

    /**
     * Cancel subscription at the end of current period.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function resume(Request $request)
    {
        $customer = $request->user()->customer;
        $subscription = $customer->subscription;

        if ($request->user()->customer->can('resume', $subscription)) {
            $subscription->resume();
        }

        // Redirect to my subscription page
        $request->session()->flash('alert-success', trans('messages.subscription.resumed'));
        return redirect()->action('AccountSubscriptionController@index');
    }
    
    /**
     * Cancel now subscription at the end of current period.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function cancelNow(Request $request)
    {
        $customer = $request->user()->customer;
        $subscription = $customer->subscription;
        
        if ($request->user()->customer->can('cancelNow', $subscription)) {
            $subscription->cancelNow();
        }

        // Redirect to my subscription page
        $request->session()->flash('alert-success', trans('messages.subscription.cancelled_now'));
        return redirect()->action('AccountSubscriptionController@index');
    }
}

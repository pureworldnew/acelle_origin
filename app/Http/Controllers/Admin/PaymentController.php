<?php

namespace Acelle\Http\Controllers\Admin;

use Illuminate\Http\Request;

use Acelle\Http\Requests;
use Acelle\Http\Controllers\Controller;
use Acelle\Model\Setting;
use Acelle\Model\Plan;
use Illuminate\Support\MessageBag;
use Acelle\Cashier\Cashier;
use Acelle\Model\Subscription;

class PaymentController extends Controller
{
    /**
     * Display all paymentt.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, MessageBag $message_bag)
    {
        if (\Gate::denies('read', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }

        return view('admin.payments.index', [
            'gateways' => Setting::getPayments(),
            'enabledGateways' => Setting::getEnabledPaymentGateways(),
        ]);
    }

    /**
     * Editing payment gateways.
     *
     * @param int $name
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($name)
    {
        $service = \Acelle\Model\Setting::getPaymentGateway($name);
        $gateway = Setting::getPayment($name);

        // authorize
        if (\Gate::denies('update', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }

        try {
            $service->validate();
            $isValid = true;
        } catch (\Exception $ex) {
            $isValid = false;
        }
        
        return view('admin.payments.edit', [
            'gateway' => $gateway,
            'service' => $service,
            'isValid' => $isValid,
        ]);
    }

    /**
     * Update payment gateway.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $name
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, MessageBag $message_bag, $name)
    {
        $errors = new MessageBag;
        $gatewayService = \Acelle\Model\Setting::getPaymentGateway($name, $request->options);

        // authorize
        if (\Gate::denies('update', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }
        
        try {
            $gatewayService->validate();
            Setting::updatePaymentGateway($name, $request->options);

            if ($request->save_and_enable) {
                // enable gateway
                $gateways = json_decode(Setting::get('gateways'), true);
                $gateways = array_merge($gateways, [$name]);
                Setting::set('gateways', json_encode($gateways));
            }

            $request->session()->flash('alert-success', trans('messages.payment_gateway.updated'));
            if ($name == 'paypal_subscription') {
                // auto connect
                if (!$gatewayService->getData()['product_id']) {
                    $gatewayService->initPaypalProduct();
                }
                return redirect()->action('Admin\PaymentController@edit', $name);
            } else {
                return redirect()->action('Admin\PaymentController@index');
            }
        } catch (\Exception $ex) {
            // Add example error messages to the MessageBag instance.
            $errors->add(
                'payment',
                trans('messages.payment_gateway.not_valid', ['message' => $ex->getMessage()])
            );

            return view('admin.payments.edit', [
                'gateway' => Setting::getPayment($name),
                'errors' => $errors,
                'service' => $gatewayService,
                'isValid' => false,
            ]);
        }
    }

    /**
     * Set payment gateway as primary.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $name
     *
     * @return \Illuminate\Http\Response
     */
    public function setPrimary(Request $request, $name)
    {
        $service = \Acelle\Model\Setting::getPaymentGateway($name);

        // authorize
        if (\Gate::denies('update', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }

        try {
            $sc = Subscription::count();

            if ($sc > 0) {
                throw new \Exception(trans('messages.gateway.error.subscription_exist'));
            }
        } catch (\Exception $ex) {
            $request->session()->flash('alert-error', trans('messages.payment_gateway.not_valid', ['message' => $ex->getMessage()]));
            return redirect()->action('Admin\PaymentController@index');
        }

        try {
            $service->validate();

            Setting::set('system.payment_gateway', $name);
            
            // update global configs
            config([
                'cashier.gateway' => $name
            ]);

            // check all plan status
            foreach (Plan::all() as $plan) {
                $plan->checkStatus();
            }

            $request->session()->flash('alert-success', trans('messages.payment_gateway.updated'));
            return redirect()->action('Admin\PaymentController@index');
        } catch (\Exception $ex) {
            $request->session()->flash('alert-error', trans('messages.payment_gateway.not_valid', ['message' => $ex->getMessage()]));
            return redirect()->action('Admin\PaymentController@edit', $name);
        }
    }

    /**
     * Enable payment.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $name
     *
     * @return \Illuminate\Http\Response
     */
    public function enable(Request $request, $name)
    {
        $service = \Acelle\Model\Setting::getPaymentGateway($name);

        // authorize
        if (\Gate::denies('update', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }

        try {
            $service->validate();

            // enable gateway
            $gateways = json_decode(Setting::get('gateways'), true);
            $gateways = array_merge($gateways, [$name]);
            Setting::set('gateways', json_encode($gateways));

            // check all plan status
            foreach (Plan::all() as $plan) {
                $plan->checkStatus();
            }

            $request->session()->flash('alert-success', trans('messages.payment_gateway.updated'));
            return redirect()->action('Admin\PaymentController@index');
        } catch (\Exception $ex) {
            $request->session()->flash('alert-error', trans('messages.payment_gateway.not_valid', ['message' => $ex->getMessage()]));
            return redirect()->action('Admin\PaymentController@edit', $name);
        }
    }

    /**
     * Disable payment.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $name
     *
     * @return \Illuminate\Http\Response
     */
    public function disable(Request $request, $name)
    {
        $service = \Acelle\Model\Setting::getPaymentGateway($name);

        // authorize
        if (\Gate::denies('update', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }

        // enable gateway
        $gateways = json_decode(Setting::get('gateways'), true);
        $gateways = array_diff($gateways, [$name]);
        Setting::set('gateways', json_encode($gateways));

        // check all plan status
        foreach (Plan::all() as $plan) {
            $plan->checkStatus();
        }

        $request->session()->flash('alert-success', trans('messages.payment_gateway.updated'));
        return redirect()->action('Admin\PaymentController@index');
    }
    
    /**
     * Connect paypal plan.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $name
     *
     * @return \Illuminate\Http\Response
     */
    public function paypalSubscriptionConnectPlan(Request $request, $plan_uid)
    {
        $service = \Acelle\Model\Setting::getPaymentGateway('paypal_subscription');
        $plan = Plan::findByUid($plan_uid);

        // authorize
        if (\Gate::denies('update', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }

        try {
            $service->connectPlan($plan);

            // check plan status
            $plan->checkStatus();
        } catch (\Exception $e) {
            $request->session()->flash('alert-error', 'PyPal Subscription service error: ' . $e->getMessage());
            return redirect()->action('Admin\PaymentController@edit', 'paypal_subscription');
        }

        $request->session()->flash('alert-success', trans('messages.payment_gateway.paypal_subscription.plan.connected'));
        return redirect()->action('Admin\PaymentController@edit', 'paypal_subscription');
    }

    /**
     * Disonnect paypal plan.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $name
     *
     * @return \Illuminate\Http\Response
     */
    public function paypalSubscriptionDisconnectPlan(Request $request, $plan_uid)
    {
        $service = \Acelle\Model\Setting::getPaymentGateway('paypal_subscription');
        $plan = Plan::findByUid($plan_uid);

        // authorize
        if (\Gate::denies('update', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }

        try {
            $service->disconnectPlan($plan);

            // check plan status
            $plan->checkStatus();
        } catch (\Exception $e) {
            $request->session()->flash('alert-error', 'PyPal Subscription service error: ' . $e->getMessage());
            return redirect()->action('Admin\PaymentController@edit', 'paypal_subscription');
        }

        $request->session()->flash('alert-success', trans('messages.payment_gateway.paypal_subscription.plan.disconnected'));
        return redirect()->action('Admin\PaymentController@edit', 'paypal_subscription');
    }

    /**
     * Connect paypal.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $name
     *
     * @return \Illuminate\Http\Response
     */
    public function paypalSubscriptionConnect(Request $request)
    {
        $service = \Acelle\Model\Setting::getPaymentGateway('paypal_subscription');

        // authorize
        if (\Gate::denies('update', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }
        
        try {
            $service->initPaypalProduct();
        } catch (\Exception $e) {
            $request->session()->flash('alert-error', 'PyPal Subscription service error: ' . $e->getMessage());
            return redirect()->action('Admin\PaymentController@edit', 'paypal_subscription');
        }

        $request->session()->flash('alert-success', trans('messages.payment_gateway.paypal_subscription.connected'));
        return redirect()->action('Admin\PaymentController@edit', 'paypal_subscription');
    }

    /**
     * Disconnect paypal.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $name
     *
     * @return \Illuminate\Http\Response
     */
    public function paypalSubscriptionDisconnect(Request $request)
    {
        $service = \Acelle\Model\Setting::getPaymentGateway('paypal_subscription');

        // authorize
        if (\Gate::denies('update', new \Acelle\Model\PaymentMethod())) {
            return $this->notAuthorized();
        }

        try {
            $service->removePaypalProduct();

            // check all plan status
            foreach (Plan::all() as $plan) {
                $plan->checkStatus();
            }
        } catch (\Exception $e) {
            $request->session()->flash('alert-error', 'PyPal Subscription service error: ' . $e->getMessage());
            return redirect()->action('Admin\PaymentController@edit', 'paypal_subscription');
        }

        $request->session()->flash('alert-success', trans('messages.payment_gateway.paypal_subscription.disconnected'));
        return redirect()->action('Admin\PaymentController@edit', 'paypal_subscription');
    }
}

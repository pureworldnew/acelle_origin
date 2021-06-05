@extends('layouts.frontend')

@section('title', trans('messages.subscriptions'))

@section('page_script')
    <script type="text/javascript" src="{{ URL::asset('assets/js/plugins/forms/styling/uniform.min.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('js/validate.js') }}"></script>
@endsection

@section('page_header')

    <div class="page-title">
        <ul class="breadcrumb breadcrumb-caret position-right">
            <li><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
            <li class="active">{{ trans('messages.subscription') }}</li>
        </ul>
    </div>
    
@endsection

@section('content')

    <div class="row">
        <div class="col-md-8">
            
                <h2 class="mb-3 mt-0">{{ $invoice->title }}</h2>

                <div class="current_payment">
                    @if (request()->user()->customer->getPaymentMethod() == null)
                        <p>{{ trans('messages.plan.review.do_not_have_payment_yet') }}</p>
                    @else
                        <p class="mb-3">{!! trans('messages.payment.you_currenly_payment', [
                            'method' => trans('messages.transaction.method.' . request()->user()->customer->getPaymentMethod()['method']),
                        ]) !!}</p>
                        <form class="edit-payment" action="{{ action('AccountSubscriptionController@checkout') }}"
                            method="POST">
                            {{ csrf_field() }}

                            <input type="submit" name="new_payment"
                                class="btn btn-primary bg-teal-800 py-3 px-4"
                                value="{{ trans('messages.proceed_to_checkout') }}"
                            >

                            <div class="row">
                                <div class="mt-4 col-md-8">{!! trans('messages.payment.agree_service_intro', ['plan' => $subscription->plan->name]) !!}</div>
                            </div>
                        </form>

                        <div class="mt-4 pt-3 other-payment-click">
                            <a href="javascript:;">{{ trans('messages.or_click_choose_another_method') }}</a>
                        </div>
                    @endif
                </div>
                
                <form class="edit-payment mt-4 pt-4" {!! (request()->user()->customer->getPaymentMethod() == null) ? '' : 'style="display:none"' !!} action="{{ request()->user()->customer->getPaymentMethod() ? action('AccountSubscriptionController@checkout') : action('AccountController@editPaymentMethod') }}"
                    method="POST">
                    {{ csrf_field() }}

                    <p>{{ trans('messages.payment.choose_new_payment_method_to_proceed') }}</p>

                    <input type="hidden" name="return_url" value="{{ action('AccountSubscriptionController@checkout') }}" />

                    <div class="sub-section mb-30 choose-payment-methods">      
                        @foreach(Acelle\Model\Setting::getEnabledPaymentGateways() as $gateway)
                            <div class="d-flex align-items-center choose-payment choose-payment-{{ $gateway }}">
                                <div class="text-right pl-2 pr-2">
                                    <div class="d-flex align-items-center form-group-mb-0">
                                        @include('helpers.form_control', [
                                            'type' => 'radio2',
                                            'name' => 'payment_method',
                                            'value' => request()->user()->customer->getPaymentMethod() ? request()->user()->customer->getPaymentMethod()['method'] : '',
                                            'label' => '',
                                            'help_class' => 'setting',
                                            'rules' => ['payment_method' => 'required'],
                                            'options' => [
                                                ['value' => $gateway, 'text' => ''],
                                            ],
                                        ])
                                        <div class="check"></div>
                                    </div>
                                </div>
                                <div class="mr-auto pr-4">
                                    <h4 class="font-weight-semibold mb-2">{{ trans('messages.frontend_payment.' . $gateway) }}</h4>
                                    <p class="mb-3">{{ trans('messages.frontend_payment.' . $gateway . '.desc') }}</p>
                                </div>                        
                            </div>
                        @endforeach
                    </div>
                    
                    
                    <div class="sub-section">
                        <div class="row">
                            <div class="col-md-4">
                                <input type="submit" name="new_payment"
                                    class="btn btn-primary bg-teal-800 py-3 px-4"
                                    value="{{ trans('messages.save_payment_method_proceed') }}"
                                >
                            </div>
                            <div class="col-md-8">
                                {!! trans('messages.payment.agree_service_intro', ['plan' => $subscription->plan->name]) !!}
                            </div>
                        </div>
                    </div>
                </form>

                <div class="my-4 pt-3">
                    <hr>
                    <a class="" link-method="POST" link-confirm="{{ trans('messages.invoice.cancel.confirm') }}"
                        href="{{ action('AccountSubscriptionController@cancelInvoice', [
                            'invoice_uid' => $subscription->lastInvoice()->uid,
                        ]) }}">
                        {{ trans('messages.subscription.cancel_now_change_other_plan') }}
                    </a>
                </div>
        </div>
        <div class="col-md-4">
            @include('invoices.bill', [
                'bill' => $invoice->getBillingInfo(),
            ])
        </div>
    </div>
    <div class="row">
        
    </div>


    <script>
        $('.edit-payment').on('submit', function(e) {
            if (!$('.choose-payment-methods>div [type=radio]:checked').length) {
                e.preventDefault();

                swal({
                    title: '{{ trans('messages.subscription.no_payment_method_selected') }}',
                    text: "",
                    confirmButtonColor: "#00695C",
                    type: "error",
                    allowOutsideClick: true,
                    confirmButtonText: LANG_OK,
                    customClass: "swl-success",
                    html: true
                });
            }
        });

        $('.choose-payment-methods>div').on('click', function() {
            $(this).find('[type=radio]').prop('checked', true);

            $('.choose-payment-methods>div').removeClass('current');
            $('.choose-payment-methods>div [type=radio]:checked').closest('.choose-payment').addClass('current');

            if ($('.choose-payment-methods>div [type=radio]:checked').val() == '{{ request()->user()->customer->getPaymentMethod() ? request()->user()->customer->getPaymentMethod()['method'] : 'none' }}') {
                $('.edit-payment').attr('action', '{!! action('AccountSubscriptionController@checkout') !!}');
            } else {
                $('.edit-payment').attr('action', '{!! action('AccountController@editPaymentMethod') !!}');
            }
        });

        $('.choose-payment-methods>div').removeClass('current');
        $('.choose-payment-methods>div [type=radio]:checked').closest('.choose-payment').addClass('current');        

        if ($('.choose-payment-methods>div [type=radio]').length == 1) {
            $('.choose-payment-methods>div').first().click();
        }

        $('.other-payment-click').on('click', function() {
            $('.edit-payment').show();
            $('.current_payment').hide();
            $(this).hide();
        });
    </script>

@endsection
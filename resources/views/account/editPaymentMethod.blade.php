@extends('layouts.popup.small')

@section('title')
    {{ trans('messages.edit_payment_method') }}
@endsection

@section('content')
    <h2 class="mb-3 mt-0">{{ trans('messages.payment.choose_payment') }}</h2>
    <div class="mb-4 pb-4">{!! trans('messages.payment.choose_payment.desc') !!}</div>
    <form class="edit-payment" action="{{ action('AccountController@editPaymentMethod') }}"
        method="POST">
        {{ csrf_field() }}

        <input type="hidden" name="return_url" value="{{ $redirect }}" />

        <div class="sub-section mb-30 choose-payment-methods">      
            @foreach(Acelle\Model\Setting::getEnabledPaymentGateways() as $gateway)
                <div class="d-flex align-items-center choose-payment choose-payment-{{ $gateway }}">
                    <div class="text-right pl-2 pr-2">
                        <div class="d-flex align-items-center form-group-mb-0">
                            @include('helpers.form_control', [
                                'type' => 'radio2',
                                'name' => 'payment_method',
                                'value' => '',
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
                    <button link-method="POST"
                        class="btn btn-mc_primary py-3 px-4">
                            {{ trans('messages.save_payment_method') }}
                    </button>
                </div>
                <div class="col-md-8">
                    
                </div>
            </div>
        </div>     
        
    </form>

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
        });

        $('.choose-payment-methods>div').removeClass('current');
        $('.choose-payment-methods>div [type=radio]:checked').closest('.choose-payment').addClass('current');        

        if ($('.choose-payment-methods>div [type=radio]').length == 1) {
            $('.choose-payment-methods>div').first().click();
        }
    </script>
@endsection
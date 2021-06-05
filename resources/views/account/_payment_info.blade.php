@if (request()->user()->customer->getPaymentMethod() !== null)
    <div class="payment-box">
        <div class="header py-4 d-flex align-items-center">
            <div class="d-flex align-items-center">
                <i class="payment-icon bg-{{ request()->user()->customer->getPaymentMethod()['method'] }}"></i>
                <span class="font-weight-semibold">{{ trans('messages.pay_with', [
                    'method' => trans('messages.payments.' . request()->user()->customer->getPaymentMethod()['method'])
                ]) }}</span>
            </div>
            <div class="ml-auto">
                <a href="{{ action('AccountController@removePaymentMethod') }}" class="payment-method-remove">
                    {{ trans('messages.remove') }}
                </a>
            </div>
        </div>
        <div class="body">
            <div class="bill_info">
                @if (isset(request()->user()->customer->getPaymentMethod()['description']))
                    <div class="line d-flex my-2">
                        {{ request()->user()->customer->getPaymentMethod()['description'] }}
                    </div>
                @endif
                @if (isset(request()->user()->customer->getPaymentMethod()['user_id']))
                    <div class="line d-flex my-3">
                        <div class="data mr-auto">
                            {{ trans('messages.bill.user_id') }}
                        </div>
                        <div class="value font-weight-semibold">
                            {{ request()->user()->customer->getPaymentMethod()['user_id'] }}
                        </div>
                    </div>
                @endif
                @if (isset(request()->user()->customer->getPaymentMethod()['card_last4']))
                    <div class="line d-flex my-3">
                        <div class="data mr-auto">
                            {{ trans('messages.bill.last4') }}
                        </div>
                        <div class="value">
                            ***{{ request()->user()->customer->getPaymentMethod()['card_last4'] }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <a href="{{ action('AccountController@editPaymentMethod', [
        'redirect' => isset($redirect) ? $redirect : action('AccountController@billing'),
    ]) }}" class="btn btn-mc_primary payment-method-edit mt-4">
        {{ trans('messages.change_payment_method') }}
    </a>
@else
    <p>{{ trans('messages.have_no_payment_method') }}</p>

    <a href="{{ action('AccountController@editPaymentMethod') }}"
        class="btn btn-mc_primary payment-method-edit">
        {{ trans('messages.add_payment_method') }}
    </a>
@endif

<script>
    var paymentPopup = new Popup();
    
    $('.payment-method-edit').click(function(e) {
        e.preventDefault();
        var url = $(this).attr('href');

        paymentPopup.load(url);
    });
    
    $('.payment-method-remove').click(function(e) {
        e.preventDefault();
        var url = $(this).attr('href');

        var dia = new Dialog('confirm', {
            message: '{{  trans('messages.bill.remove_payment.confirm') }}',
            ok: function() {
                $.ajax({
                url: url,
                method: 'POST',
                data: {
                    _token: CSRF_TOKEN
                },
                success: function (response) {
                    window.location.reload();
                }
            });
            },
        });
    });
</script>
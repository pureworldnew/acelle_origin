@extends('layouts.frontend')

@section('title', trans('messages.contact_information'))

@section('page_script')
    <script type="text/javascript" src="{{ URL::asset('assets/js/plugins/forms/styling/uniform.min.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('js/validate.js') }}"></script>
@endsection

@section('page_header')

    <div class="page-title">
        <ul class="breadcrumb breadcrumb-caret position-right">
            <li><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
            <li class="active">{{ trans('messages.contact_information') }}</li>
        </ul>
        <h1>
            <span class="text-semibold"><i class="icon-address-book3"></i> {{ $customer->displayName() }}</span>
        </h1>
    </div>

@endsection

@section('content')

    @include("account._menu")

    <div class="row">
        <div class="col-md-8">
            <h2>{{ trans('messages.billing_information') }}</h2>
            @if (!$customer->getDefaultBillingAddress())
                <p>{{ trans('messages.has_no_billing_address') }}</p>
                <div>
                    <a href="{{ action('AccountController@editBillingAddress') }}" class="btn btn-mc_primary billing-address-edit">
                        {{ trans('messages.add_billing_address') }}
                    </a>
                </div>
            @else
                @php
                    $billingAddress = $customer->getDefaultBillingAddress();
                @endphp
                <div>
                    <h4 class="mb-1 mt-0">{{ $billingAddress->first_name }} {{ $billingAddress->first_name }}</h4>
                    <div class="">{{ $billingAddress->email }}</div>
                    <div class="">{{ $billingAddress->phone }}</div>

                    <div class="mt-4">{{ $billingAddress->address }}</div>
                    <div class="">{{ $billingAddress->country->name }}</div>
                </div>

                <div>
                    <a href="{{ action('AccountController@editBillingAddress') }}"
                    class="mt-4 pt-4 d-block billing-address-edit">
                        {{ trans('messages.edit_billing_address') }}
                    </a>
                </div>
            @endif
        </div>
        <div class="col-md-4">
            <h2>{{ trans('messages.payment_method') }}</h2>

            @include('account._payment_info')
                
        </div>
    </div>
        

    <script>
        var billingPopup = new Popup();

        $('.billing-address-edit').click(function(e) {
            e.preventDefault();
            var url = $(this).attr('href');

            billingPopup.load(url);
        });
    </script>

@endsection

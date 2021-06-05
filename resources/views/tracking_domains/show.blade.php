@extends('layouts.frontend')

@section('title', $domain->name)

@section('page_script')
    <script type="text/javascript" src="{{ URL::asset('assets/js/plugins/forms/styling/uniform.min.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('assets/js/core/libraries/jquery_ui/interactions.min.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('assets/js/core/libraries/jquery_ui/touch.min.js') }}"></script>

    <script type="text/javascript" src="{{ URL::asset('js/validate.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('js/listing.js') }}"></script>
@endsection

@section('page_header')

    <div class="page-title">
        <ul class="breadcrumb breadcrumb-caret position-right">
            <li><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
            <li><a href="{{ action("TrackingDomainController@index") }}">{{ trans('messages.tracking_domains') }}</a></li>
        </ul>
        <h2>
            <span class="text-semibold"><i class="icon-earth"></i> {{ $domain->getUrl() }} </span>
            <span class="label label-primary bg-{{ $domain->status }}">
                {{ trans('messages.tracking_domain.status.' . $domain->status) }}
            </span>
        </h2>       
    </div>

@endsection

@section('content')
    
    <div class="row sub_section">
        <div class="col-sm-12 col-md-8">
            @if (!$domain->isVerified())
                <form action="{{ action('TrackingDomainController@verify', [ 'uid' => $domain->uid ]) }}" method="GET">
                    <div data-type="admin-notification" class="alert alert-warning" style="display: flex; flex-direction: row; align-items: center; justify-content: space-between;">
                        <div style="display: flex; flex-direction: row; align-items: center;">
                            <div style="margin-right:15px">
                                <i class="lnr lnr-warning"></i>
                            </div>
                            <div style="padding-right: 40px">
                                <h4>{{ trans('messages.tracking_domain.show.pending_title') }}</h4>
                            <p>{{ trans('messages.tracking_domain.show.pending_note') }}</p>
                            </div>
                        </div>
                        <button type="submit" class="btn bg-grey-600">{{ trans('messages.tracking_domain.refresh') }}</button>
                    </div>
                </form>
                <h2>{{ trans('messages.tracking_domain.show.dns_setup.title') }}</h2>
                <p>{{ trans('messages.tracking_domain.show.dns_setup.note1') }}</p>

                <p>{{ trans('messages.tracking_domain.show.dns_setup.note2') }}</p>
                <ul class="dotted-list topborder section section-flex">
                    <li style="font-size:16px">
                        <div class="size1of3">
                            <strong>{{ $domain->getFQDN() }}</strong><br><span style="font-size:14px;">{{ trans('messages.tracking_domain.hostname') }}</span>
                        </div>
                        <div class="unit size1of3">
                            <strong>CNAME</strong><br><span style="font-size:14px;">{{ trans('messages.tracking_domain.record_type') }}</span>
                        </div>
                        <div class="size1of3">
                            <div class="d-flex align-items-center">
                                <div>
                                    <strong>{{ $hostname }}</strong><br><span style="font-size:14px;">{{ trans('messages.tracking_domain.value') }}</span>
                                </div>

                                @if (!$domain->isVerified())
                                    <a href="javascript:;" class="btn btn-mc_primary ml-auto tracking-domain-test">{{ trans('messages.tracking_domain.test') }}</a>
                                    <div class="tracking-domain-test-message" style="display:none">
                                        <div class="popup-container" style="width: 600px">
                                            <div class="popup-header">
                                                <i class="lnr lnr-arrow-left back"></i>
                                                <h2 class="title">{{ $domain->name }}</h2>
                                                <i class="lnr lnr-cross close"></i>
                                            </div>
                                            <div class="popup-body scrollbar-inner">
                                                <div class="popup-content">
                                                    {!! trans('messages.tracking_domain.test.wording', [
                                                        'domain' => $domain->name,
                                                    ]) !!}

                                                    <div class="mt-4 pt-3 text-center">
                                                        <a href="http://{{ $domain->name }}" class="btn btn-mc_primary mr-2" target="_blank">{{ trans('messages.tracking_domain.test.proceed') }}</a>
                                                        <button class="btn btn-link tracking-domain-test-close" target="_blank">{{ trans('messages.close') }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <script>
                                        $('.tracking-domain-test').on('click', function(e) {
                                            e.preventDefault();

                                            var html = $('.tracking-domain-test-message').html();
                                            var testPopup = new Popup();
                                            testPopup.loadHtml(html);
                                            testPopup.show();

                                            $('.tracking-domain-test-close').on('click', function(e) {
                                                e.preventDefault();

                                                testPopup.hide();
                                            });
                                        });
                                        
                                    </script>
                                @endif
                                    
                            </div>
                        </div>
                    </li>
                </ul>
                <p style="margin-top: 20px"><i>{{ trans('messages.tracking_domain.show.dns_setup.note3') }}</i></p>
                <a type="button" style="padding-left:0" class="btn btn-mc_inline" href="{{ action('TrackingDomainController@index') }} ">{{ trans('messages.go_back') }}</a>
            @else
                <div data-type="admin-notification" class="alert alert-success" style="display: flex; flex-direction: row; align-items: center; justify-content: space-between;">
                    <div style="display: flex; flex-direction: row; align-items: center;">
                        <div style="margin-right:15px">
                            <i class="lnr lnr-checkmark-circle"></i>
                        </div>
                        <div style="padding-right: 40px">
                            <h4>{{ trans('messages.tracking_domain.show.verified_title') }}</h4>
                            <p>{{ trans('messages.tracking_domain.show.verified_note') }}</p>
                        </div>
                    </div>
                </div>

                <a type="button" style="padding-left:0" class="btn btn-mc_inline" href="{{ action('TrackingDomainController@index') }} ">{{ trans('messages.go_back') }}</a>
            @endif
        </div>
    </div>

@endsection

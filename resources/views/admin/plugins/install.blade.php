@extends('layouts.backend')

@section('title', trans('messages.install_plugin'))

@section('page_script')
    <script type="text/javascript" src="{{ URL::asset('assets/js/plugins/forms/styling/uniform.min.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('js/validate.js') }}"></script>
@endsection

@section('page_header')

    <div class="page-title">				
        <ul class="breadcrumb breadcrumb-caret position-right">
            <li><a href="{{ action("Admin\HomeController@index") }}">{{ trans('messages.home') }}</a></li>
            <li><a href="{{ action("Admin\PluginController@index") }}">{{ trans('messages.plugins') }}</a></li>
        </ul>
        <h1>
            <span class="text-semibold"><i class="icon-upload"></i> {{ trans('messages.install_plugin') }}</span>
        </h1>				
    </div>

@endsection

@section('content')
    
    <div class="row">
        <div class="col-md-6">
        
            <p class="">
                {!! trans('messages.template_upload_guide', ["link" => 'https://s3.amazonaws.com/acellemail/newsletter-template-green.zip']) !!}
            </p>
        
            <form enctype="multipart/form-data" action="{{ action('Admin\PluginController@install') }}"
                method="POST"
                class="plugin-form form-validate-jquery"
            >
                {{ csrf_field() }}
                
                @include('helpers.form_control', [
                    'required' => true,
                    'type' => 'file',
                    'label' => trans('messages.upload_file'),
                    'name' => 'file',
                ])
                    
                <div class="text-right">
                    <button class="btn btn-mc_primary mr-2">
                        {{ trans('messages.upload') }}
                    </button>
                    <a href="{{ action('Admin\PluginController@index') }}" class="btn btn-mc_default">
                        {{ trans('messages.cancel') }}
                    </a>
                </div>
                
            </form>
        </div>
    </div>

    <script>
        function doInstall(url, data) {
            addMaskLoading(`{!! trans('messages.plugin.installing') !!}`);

            $.ajax({
                url: url, 
                type: 'POST',
                data: data, // The form with the file inputs.
                processData: false,
                contentType: false,                    // Using FormData, no need to process data.
                globalError: false,
            }).done(function(res){
                window.location = res.return;
            }).fail(function(e){
                var error = JSON.parse(e.responseText).message;
                removeMaskLoading();

                if (error.includes('already exists')) {
                    var dialog = new Dialog('confirm', {
                        message: error,
                        ok: function(dialog) {       
                            data.append('overwrite', true);             
                            doInstall(url, data);
                        },
                        cancel: function(dialog) {

                        },
                    });
                    return;
                }

                swal({
                    title: error,
                    text: "",
                    confirmButtonColor: "#00695C",
                    type: "error",
                    allowOutsideClick: true,
                    confirmButtonText: LANG_OK,
                    customClass: "swl-success"
                });
            });
        }
        $('.plugin-form').submit(function(e) {
            e.preventDefault();

            var url = $(this).attr('action');
            var form = $(this);
            var data = new FormData(form[0]);

            if (form.valid()) {
                doInstall(url, data);
            }
        });
    </script>

@endsection

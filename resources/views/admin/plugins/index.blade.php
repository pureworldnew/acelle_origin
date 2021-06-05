@extends('layouts.backend')

@section('title', trans('messages.plugins'))

@section('page_script')
    <script type="text/javascript" src="{{ URL::asset('assets/js/core/libraries/jquery_ui/interactions.min.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('assets/js/core/libraries/jquery_ui/touch.min.js') }}"></script>

    <script type="text/javascript" src="{{ URL::asset('js/listing.js') }}"></script>
@endsection

@section('page_header')

	<div class="page-title">
		<ul class="breadcrumb breadcrumb-caret position-right">
			<li><a href="{{ action("Admin\HomeController@index") }}">{{ trans('messages.home') }}</a></li>
		</ul>
		<h1>
			<span class="text-semibold"><i class="icon-list2"></i> {{ trans('messages.plugins') }}</span>
		</h1>
	</div>

@endsection

@section('content')
	<p>{{ trans('messages.plugin.wording') }}</p>

	<form class="listing-form"
		sort-url="{{ action('Admin\PluginController@sort') }}"
		data-url="{{ action('Admin\PluginController@listing') }}"
		per-page="{{ Acelle\Model\Plugin::$itemsPerPage }}"
	>
		<div class="row top-list-controls">
			<div class="col-md-10">
				@if ($plugins->count() >= 0)
					<div class="filter-box">
						<div class="btn-group list_actions hide">
							<button type="button" class="btn btn-xs btn-grey-600 dropdown-toggle" data-toggle="dropdown">
								{{ trans('messages.actions') }} <span class="caret"></span>
							</button>
							<ul class="dropdown-menu">
								<li><a link-confirm="{{ trans('messages.enable_plugins_confirm') }}" href="{{ action('Admin\PluginController@enable') }}"><i class="icon-checkbox-checked2"></i> {{ trans('messages.enable') }}</a></li>
								<li><a link-confirm="{{ trans('messages.disable_plugins_confirm') }}" href="{{ action('Admin\PluginController@disable') }}"><i class="icon-checkbox-unchecked2"></i> {{ trans('messages.disable') }}</a></li>
								<li><a delete-confirm="{{ trans('messages.delete_plugins_confirm') }}" href="{{ action('Admin\PluginController@delete') }}"><i class="icon-trash"></i> {{ trans('messages.delete') }}</a></li>
							</ul>
						</div>
						<span class="text-nowrap">
							<input name="search_keyword" class="form-control search" placeholder="{{ trans('messages.type_to_search') }}" />
							<i class="icon-search4 keyword_search_button"></i>
						</span>
					</div>
				@endif
			</div>
			<div class="col-md-2 text-right">
				<a href="{{ action("Admin\PluginController@install") }}" type="button" class="btn bg-info-800">
					<i class="icon icon-plus2"></i> {{ trans('messages.install_plugin') }}
				</a>
			</div>
		</div>

		<div class="pml-table-container">
		</div>
	</form>
@endsection

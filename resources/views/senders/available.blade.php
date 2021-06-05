@extends('layouts.frontend')

@section('title', trans('messages.verified_senders'))

@section('page_script')
    <script type="text/javascript" src="{{ URL::asset('assets/js/core/libraries/jquery_ui/interactions.min.js') }}"></script>
    <script type="text/javascript" src="{{ URL::asset('assets/js/core/libraries/jquery_ui/touch.min.js') }}"></script>

    <script type="text/javascript" src="{{ URL::asset('js/listing.js') }}"></script>
@endsection

@section('page_header')
<div class="row mc_section boxing">
	<div class="col-md-{{ (null !== Session::get('orig_customer_id') && Auth::user()->customer) ? '6' : '6' }}">
		<div class="page-title">
			<ul class="breadcrumb breadcrumb-caret position-right">
				<li><a href="{{ action("HomeController@index") }}">{{ trans('messages.home') }}</a></li>
				<li><a href="{{ action("SenderController@index") }}">{{ trans('messages.verified_senders') }}</a></li>
				<li><a href="{{ action("SenderController@index") }}">{{ trans('messages.email_addresses') }}</a></li>
			</ul>
			<h1>
				<span class="text-semibold">{{ trans('messages.verified_senders') }}</span>
			</h1>    
		</div>
		<p>{{ trans('messages.sender.available.intro') }}</p>
		<table class="table table-box table-box-head field-list">
			<thead>
				<tr>
					<td>{{ trans('messages.domain') }}</td>
					<td>{{ trans('messages.status') }}</td>
				</tr>
			</thead>
			<tbody>
				@foreach ($identities as $domain)
					<tr class="odd">
						<td>
							{{ $domain }}
						</td>
						<td>
							<span class="badge badge-success badge-lg">{{ trans('messages.sending_identity.status.active') }}</span>
						</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</div>
	@if ((null !== Session::get('orig_customer_id') && Auth::user()->customer) || Auth::user()->admin)
		<div class="col-md-6">
			@include('quicktip.identity')
		</div>
	@endif
</div>
    
@endsection

@section('content')
@endsection

<h3>{{ trans('messages.payment.options') }}</h3>

<form enctype="multipart/form-data" action="{{ action('Admin\PaymentController@update', $gateway['name']) }}" method="POST" class="form-validate-jquery">
    {{ csrf_field() }}	
	<div class="row">
		<div class="col-md-6">
			@include('helpers.form_control', [
				'type' => 'text',
				'name' => 'options[public_key]',
				'value' => $gateway['fields']['public_key'],
				'label' => trans('messages.payment.paystack.public_key'),
				'help_class' => 'payment',
				'rules' => ['options.public_key' => 'required'],
			])
			
			@include('helpers.form_control', [
				'type' => 'text',
				'name' => 'options[secret_key]',
				'value' => $gateway['fields']['secret_key'],
				'label' => trans('messages.payment.paystack.secret_key'),
				'help_class' => 'payment',
				'rules' => ['options.secret_key' => 'required'],
			])
			
			<hr>
			<div class="text-left">
				@if (!Acelle\Model\Setting::get('payment.' . $gateway['name']))
					<input type="submit" class="btn btn-mc_primary bg-teal  mr-5" name="save_and_enable" value="{{ trans('messages.payment.connect') }}" />
				@else
					<button class="btn btn-mc_primary mr-5">{{ trans('messages.save') }}</button>
				@endif
				<a class="btn btn-mc_default" href="{{ action('Admin\PaymentController@index') }}">{{ trans('messages.cancel') }}</a>
			</div>
		</div>
	</div>

	
</form>
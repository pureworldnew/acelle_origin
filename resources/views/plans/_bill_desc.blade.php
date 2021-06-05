<div class="mt-2">
    @if (isset($endsAt))
        <div>
            {{ trans('messages.ends_on') }}:
            <strong>{{ \Acelle\Library\Tool::formatDate($endsAt) }}</strong>
        </div>
        <hr class="my-3">
    @endif
    <div>
        {{ $plan->displayTotalQuota() }} {{ trans('messages.sending_total_quota_label') }}
    </div>
    <div>
        {{ $plan->displayMaxSubscriber() }} {{ trans('messages.contacts') }}
    </div>
    
</div>
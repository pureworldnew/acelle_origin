<div class="sub-section">
    <div class="row">
        <div class="col-sm-12 col-md-12 col-lg-12">
            <h2 class="text-semibold">{{ trans('messages.invoices_logs') }}</h2>
            <p>{{ trans('messages.subscription.logs.intro') }}</p>

            <ul class="nav nav-tabs">
                <li class="active"><a data-toggle="tab" href="#invoices">{{ trans('messages.invoices') }}</a></li>
                <li><a data-toggle="tab" href="#transactions">{{ trans('messages.transactions') }}</a></li>
                <li><a data-toggle="tab" href="#logs">{{ trans('messages.subscription.logs') }}</a></li>
            </ul>

            <div class="tab-content">
                <div id="logs" class="tab-pane fade">
                    <table class="table table-box pml-table table-log mt-10">
                        <tr>
                            <th width="200px">{{ trans('messages.subscription.log.created_at') }}</th>
                            <th>{{ trans('messages.subscription.log.message') }}</th>
                        </tr>
                        @forelse ($subscription->getLogs() as $key => $log)
                            <tr>
                                <td>
                                    <span class="no-margin kq_search">
                                        {{ Acelle\Library\Tool::formatDateTime($log->created_at) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="no-margin kq_search">
                                        {!! trans('cashier::messages.subscription.log.' . $log->type, $log->getData()) !!}
                                    </span>
                                </td>                                
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center" colspan="5">
                                    {{ trans('messages.subscription.logs.empty') }}
                                </td>
                            </tr>
                        @endforelse
                    </table>
                </div>
                <div id="transactions" class="tab-pane fade">
                    <table class="table table-box pml-table table-log mt-10">
                        <tr>
                            <th width="200px">{{ trans('messages.created_at') }}</th>
                            <th>{{ trans('messages.message') }}</th>
                            <th>{{ trans('messages.transaction.amount') }}</th>
                            <th>{{ trans('messages.transaction.method') }}</th>
                            <th>{{ trans('messages.status') }}</th>
                        </tr>
                        @forelse ($subscription->transactions()->get() as $key => $transaction)
                            <tr>
                                <td>
                                    <span class="no-margin kq_search">
                                        {{ Acelle\Library\Tool::formatDateTime($transaction->created_at) }}
                                    </span>
                                </td> 
                                <td>
                                    <span class="no-margin kq_search">
                                        {!! $transaction->message !!}
                                        {{-- @if ($transaction->error)
                                            <div class="mini text-danger">{{ $transaction->error }}</div>
                                        @endif --}}
                                    </span>
                                </td> 
                                <td>
                                    <span class="no-margin kq_search">
                                        {!! $transaction->amount !!}
                                    </span>
                                </td> 
                                <td>
                                    <span class="no-margin kq_search">
                                        {!! trans('messages.transaction.method.' . $transaction->method) !!}
                                    </span>
                                </td> 
                                <td>
                                    <span class="no-margin kq_search">
                                        <span {!! $transaction->error ? 'title="'.$transaction->error.'"' : '' !!} class="xtooltip label label-success bg-{{ $transaction->status }}" style="white-space: nowrap;">
                                            {{ trans('messages.transaction.' . $transaction->status) }}
                                        </span>
                                    </span>
                                </td>                                
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center" colspan="3">
                                    {{ trans('messages.subscription.logs.empty') }}
                                </td>
                            </tr>
                        @endforelse
                    </table>
                </div>
                <div id="invoices" class="tab-pane fade in active">
                    <table class="table table-box pml-table table-log mt-10">
                        <tr>
                            <th width="130px">{{ trans('messages.invoice.created_at') }}</th>
                            <th>{{ trans('messages.invoice.title') }}</th>
                            <th>{{ trans('messages.invoice.amount') }}</th>
                            <th>{{ trans('messages.invoice.status') }}</th>
                            <th>{{ trans('messages.invoice.action') }}</th>
                        </tr>
                        @forelse ($subscription->invoices()->orderBy('created_at', 'desc')->get() as $key => $invoice)
                            @php
                                $billInfo = $invoice->getBillingInfo();
                            @endphp
                            <tr>
                                <td>
                                    <span class="no-margin kq_search">
                                        {{ Acelle\Library\Tool::formatDate($invoice->created_at) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="no-margin kq_search font-weight-semibold">
                                        {!! $billInfo['title'] !!}
                                    </span>
                                    <div class="text-muted small">
                                        {!! $billInfo['description'] !!}
                                    </div>
                                </td>
                                <td>
                                    <span class="no-margin kq_search">
                                        {{ $billInfo['total'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="no-margin kq_search">
                                        <span class="label label-success bg-{{ $invoice->status }}" style="white-space: nowrap;">
                                            {{ trans('messages.invoice.status.' . $invoice->status) }}
                                        </span>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-mc_default btn-disabled" disabled>
                                        {{ trans('messages.edit') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center" colspan="5">
                                    {{ trans('messages.subscription.logs.empty') }}
                                </td>
                            </te>
                        @endforelse
                    </table>
                </div>
            </div>


            
        </div>
    </div>
</div>
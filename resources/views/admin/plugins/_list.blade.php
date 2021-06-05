@if ($plugins->count() > 0)
    <table class="table table-box pml-table"
        current-page="{{ empty(request()->page) ? 1 : empty(request()->page) }}"
    >
        @foreach ($plugins as $key => $plugin)
            <tr>
                <td class="plugin-title-column plugin-title-{{ $plugin->name }}" >
                    <img class="plugin-icon" src="{{ $plugin->getIconUrl() ? $plugin->getIconUrl() : url('/images/plugin.svg') }}" />
                    <h5 class="no-margin text-bold kq_search">
                        {{ $plugin->title }}
                    </h5>
                    <span class="">
                        {{ $plugin->description }}
                    </span>
                    <br />
                    <span class="text-muted">{{ trans('messages.plugin.version') }}: {{ $plugin->version }}</span>
                </td>
                <td>
                    <span class="text-muted2 list-status pull-left">
                        <span class="label label-flat bg-{{ $plugin->status }}">
                            {{ trans('messages.email_verification_server_status_' . $plugin->status) }}
                        </span>
                    </span>
                </td>
                <td class="text-right text-nowrap">
                    @if (isset($settingUrls[$plugin->name]))
                        <a
                            href="{{ $settingUrls[$plugin->name] }}"
                            class="btn btn-mc_default"
                        >
                            {{ trans('messages.setting') }}
                        </a>
                    @endif

                    @if (Auth::user()->admin->can('enable', $plugin))
                        <a link-confirm="{{ trans('messages.enable_plugins_confirm') }}"
                            href="{{ action('Admin\PluginController@enable', ["uids" => $plugin->uid]) }}"
                            class="btn btn-mc_primary"
                        >
                            {{ trans('messages.enable') }}
                        </a>
                    @endif

                    @if (Auth::user()->admin->can('disable', $plugin))
                        <a link-confirm="{{ trans('messages.disable_plugins_confirm') }}"
                            href="{{ action('Admin\PluginController@disable', ["uids" => $plugin->uid]) }}"
                            class="btn btn-mc_primary"
                        >
                            {{ trans('messages.disable') }}
                        </a>
                    @endif

                    <div class="btn-group">
						<button type="button" class="btn btn-mc_default icon-only dropdown-toggle" data-toggle="dropdown">
                            <span class="caret ml-0"></span>
                        </button>
						<ul class="dropdown-menu dropdown-menu-right">
							<li>
								<a
                                    delete-confirm="{{ trans('messages.delete_plugins_confirm') }}"
                                    href="{{ action('Admin\PluginController@delete', ["uids" => $plugin->uid]) }}"
                                    type="button" class="">
                                    <i class="material-icons-outlined">delete</i> {{ trans('messages.uninstall') }}
                                </a>
							</li>
						</ul>
					</div>
                </td>
            </tr>
        @endforeach
    </table>
    @include('elements/_per_page_select')
    {{ $plugins->links() }}
@elseif (!empty(request()->keyword) || !empty(request()->filters["type"]))
    <div class="empty-list">
        <i class="icon-power-cord"></i>
        <span class="line-1">
            {{ trans('messages.no_search_result') }}
        </span>
    </div>
@else
    <div class="empty-list">
        <i class="icon-power-cord"></i>
        <span class="line-1">
            {{ trans('messages.plugin_empty_line_1') }}
        </span>
    </div>
@endif

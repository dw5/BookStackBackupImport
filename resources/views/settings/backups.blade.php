@extends('layouts.simple')

@section('body')
<div class="container">

    @include('settings.parts.navbar', ['selected' => 'backups'])

    <div class="card content-wrap auto-height pb-xl">
        <h2 class="list-heading">{{ trans('settings.backup_create') }}</h2>
        <div class="grid left-focus gap-xl">
            <div>
                <p class="small text-muted">{{ trans('settings.backup_create_desc') }}</p>
                <p class="small text-muted italic">{{ trans('settings.maint_timeout_command_note') }}</p>
            </div>
            <div>
                <form method="POST" action="{{ url('/settings/backups') }}">
                    {!! csrf_field() !!}
                    <button class="button outline">{{ trans('settings.backup_create_button') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="flex-container-row items-stretch gap-xl wrap">

        <div class="flex-3 min-width-l">
            <div class="card content-wrap auto-height">
                <h2 class="list-heading">{{ trans('settings.backup_list') }}</h2>

                @if(count($files) > 0)
                    <div class="item-list">
                        <div class="item-list-row flex-container-row items-center px-s bold hide-under-l">
                            <div class="flex-3 px-m py-xs">{{ trans('common.name') }}</div>
                            <div class="flex px-m py-xs">{{ trans('settings.backup_size') }}</div>
                            <div class="flex-2 px-m py-xs">{{ trans('settings.backup_date') }}</div>
                            <div class="flex px-m py-xs text-right"></div>
                        </div>

                        @foreach($files as $file)
                            <div class="item-list-row flex-container-row items-center px-s wrap">
                                <div class="flex-3 px-m py-xs min-width-m">
                                    @icon('file') {{ $file['filename'] }}
                                </div>
                                <div class="flex px-m py-xs min-width-xs">
                                    <strong class="hide-over-l">{{ trans('settings.backup_size') }}:<br></strong>
                                    {{ $file['filesize'] }}
                                </div>
                                <div class="flex-2 px-m py-xs min-width-s">
                                    <strong class="hide-over-l">{{ trans('settings.backup_date') }}:<br></strong>
                                    {{ \Carbon\Carbon::createFromTimestamp($file['modified_at'])->format('Y-m-d H:i:s') }}
                                </div>
                                <div class="flex px-m py-xs text-m-right min-width-xs">
                                    <div class="flex-container-row items-center gap-xxs justify-flex-end">
                                        {{-- Download --}}
                                        <a href="{{ url('/settings/backups/download/' . $file['filename']) }}"
                                           class="button outline small icon"
                                           title="{{ trans('common.download') }}">
                                            @icon('download')
                                            <span class="screen-reader-only">{{ trans('common.download') }}</span>
                                        </a>
                                        {{-- Restore (dropdown confirm) --}}
                                        <div component="dropdown" class="dropdown-container">
                                            <button type="button" refs="dropdown@toggle"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    class="button outline small icon text-warn"
                                                    title="{{ trans('settings.backup_restore_button') }}">
                                                @icon('history')
                                                <span class="screen-reader-only">{{ trans('settings.backup_restore_button') }}</span>
                                            </button>
                                            <div refs="dropdown@menu" class="dropdown-menu">
                                                <div class="px-m py-xs">
                                                    <p class="text-warn small mb-xs">{{ trans('settings.backup_restore_warn') }}</p>
                                                    <form action="{{ url('/settings/backups/restore/' . $file['filename']) }}" method="POST">
                                                        {!! csrf_field() !!}
                                                        <button type="submit" class="button outline small text-warn">
                                                            @icon('history') {{ trans('settings.backup_restore_button') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Delete (dropdown confirm) --}}
                                        <div component="dropdown" class="dropdown-container">
                                            <button type="button" refs="dropdown@toggle"
                                                    aria-haspopup="true" aria-expanded="false"
                                                    class="button outline small icon text-neg"
                                                    title="{{ trans('common.delete') }}">
                                                @icon('delete')
                                                <span class="screen-reader-only">{{ trans('common.delete') }}</span>
                                            </button>
                                            <div refs="dropdown@menu" class="dropdown-menu">
                                                <div class="px-m py-xs">
                                                    <p class="text-neg small mb-xs">{{ trans('settings.backup_delete_confirm') }}</p>
                                                    <form action="{{ url('/settings/backups/' . $file['filename']) }}" method="POST">
                                                        {!! csrf_field() !!}
                                                        {!! method_field('DELETE') !!}
                                                        <button type="submit" class="button outline small text-neg">
                                                            @icon('delete') {{ trans('common.delete') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted italic">{{ trans('settings.backup_none') }}</p>
                @endif
            </div>
        </div>

        <div class="flex min-width-m">
            <div class="card content-wrap auto-height sticky-top-m">
                <h2 class="list-heading">{{ trans('settings.backup_upload') }}</h2>
                <p class="small text-muted mb-m">{{ trans('settings.backup_upload_desc') }}</p>
                <form method="POST" action="{{ url('/settings/backups/upload') }}" enctype="multipart/form-data">
                    {!! csrf_field() !!}
                    <div class="mb-m">
                        <label class="button outline" style="cursor: pointer;">
                            @icon('attach') {{ trans('settings.backup_upload_select_file') }}
                            <input type="file" name="file" accept=".zip" required style="display: none;">
                        </label>
                    </div>
                    <p class="small text-muted mb-m">{{ trans('settings.backup_upload_max_size', ['size' => $uploadLimit . ' MB']) }}</p>
                    <button class="button outline">@icon('upload') {{ trans('settings.backup_upload_button') }}</button>
                </form>
            </div>
        </div>

    </div>

</div>
@stop

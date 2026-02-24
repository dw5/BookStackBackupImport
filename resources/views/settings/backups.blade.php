@extends('layouts.simple')

@section('body')
<div class="container small">

    @include('settings.parts.navbar', ['selected' => 'backups'])

    <div class="card content-wrap auto-height pb-xl">
        <h2 class="list-heading">{{ trans('settings.backup_create') }}</h2>
        <div class="grid half gap-xl">
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

    <div class="card content-wrap auto-height">
        <h2 class="list-heading">{{ trans('settings.backup_list') }}</h2>
        @if(count($files) > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ trans('common.name') }}</th>
                        <th>{{ trans('settings.backup_size') }}</th>
                        <th>{{ trans('settings.backup_date') }}</th>
                        <th class="text-right">{{ trans('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($files as $file)
                    <tr>
                        <td>{{ $file['filename'] }}</td>
                        <td>{{ $file['filesize'] }}</td>
                        <td>{{ \Carbon\Carbon::createFromTimestamp($file['modified_at'])->format('Y-m-d H:i:s') }}</td>
                        <td class="text-right text-nowrap">
                            <a href="{{ url('/settings/backups/download/' . $file['filename']) }}"
                               class="button outline small">{{ trans('common.download') }}</a>

                            <form method="POST"
                                  action="{{ url('/settings/backups/restore/' . $file['filename']) }}"
                                  class="inline"
                                  onsubmit="return confirm('{{ trans('settings.backup_restore_confirm') }}');">
                                {!! csrf_field() !!}
                                <button class="button outline small text-warn">{{ trans('settings.backup_restore_button') }}</button>
                            </form>

                            <form method="POST"
                                  action="{{ url('/settings/backups/' . $file['filename']) }}"
                                  class="inline"
                                  onsubmit="return confirm('{{ trans('settings.backup_delete_confirm') }}');">
                                {!! csrf_field() !!}
                                {!! method_field('DELETE') !!}
                                <button class="button outline small text-neg">{{ trans('common.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted italic">{{ trans('settings.backup_none') }}</p>
        @endif
    </div>

    <div class="card content-wrap auto-height">
        <h2 class="list-heading">{{ trans('settings.backup_upload') }}</h2>
        <div class="grid half gap-xl">
            <div>
                <p class="small text-muted">{{ trans('settings.backup_upload_desc') }}</p>
            </div>
            <div>
                <form method="POST" action="{{ url('/settings/backups/upload') }}" enctype="multipart/form-data">
                    {!! csrf_field() !!}
                    <div class="mb-m">
                        <input type="file" name="file" accept=".zip" required>
                    </div>
                    <button class="button outline">{{ trans('settings.backup_upload_button') }}</button>
                </form>
            </div>
        </div>
    </div>

</div>
@stop

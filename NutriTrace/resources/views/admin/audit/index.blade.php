@extends('layouts.layout')

@section('title', __('admin.audit.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('admin.audit.title') }}</h1>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('admin.audit.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($logs->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('admin.audit.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0" data-order="[]">
                        <thead>
                            <tr>
                                <th>{{ __('admin.audit.date') }}</th>
                                <th>{{ __('admin.audit.user') }}</th>
                                <th>{{ __('admin.audit.action') }}</th>
                                <th>{{ __('admin.audit.details') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td data-order="{{ $log->created_at?->timestamp }}">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>{{ $log->user?->name ?? __('admin.audit.system') }}</td>
                                    <td>{{ Lang::has('admin.audit.actions.'.str_replace('.', '_', $log->action)) ? __('admin.audit.actions.'.str_replace('.', '_', $log->action)) : $log->action }}</td>
                                    <td>
                                        {{ $log->description }}
                                        @if (! empty($log->metadata['reason']))
                                            <br><span class="small text-gray-600">{{ $log->metadata['reason'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

@endsection

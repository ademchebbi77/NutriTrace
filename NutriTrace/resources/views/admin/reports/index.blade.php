@extends('layouts.layout')

@section('title', __('reports.admin.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('reports.admin.title') }}</h1>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('reports.admin.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($reports->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('reports.admin.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0" data-order="[]">
                        <thead>
                            <tr>
                                <th>{{ __('reports.fields.date') }}</th>
                                <th>{{ __('reports.fields.concerns') }}</th>
                                <th>{{ __('reports.fields.type') }}</th>
                                <th>{{ __('reports.fields.author') }}</th>
                                <th>{{ __('reports.fields.status') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reports as $report)
                                <tr>
                                    <td data-order="{{ $report->created_at->timestamp }}">{{ $report->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="font-weight-bold">{{ $report->targetLabel() }}</td>
                                    <td>{{ $report->type->label() }}</td>
                                    <td>{{ $report->user->name }}</td>
                                    <td><span class="badge badge-{{ $report->status->color() }}">{{ $report->status->label() }}</span></td>
                                    <td>
                                        <a href="{{ route('admin.reports.show', $report) }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }} {{ $report->targetLabel() }}">
                                            <i class="fas fa-eye"></i></a>
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

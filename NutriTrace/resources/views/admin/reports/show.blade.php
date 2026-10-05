@extends('layouts.layout')

@section('title', __('reports.admin.show_title', ['id' => $report->id]))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ __('reports.admin.show_title', ['id' => $report->id]) }}
            <span class="badge badge-{{ $report->status->color() }} align-middle">{{ $report->status->label() }}</span>
        </h1>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-light shadow-sm">
            <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
    </div>

    <div class="row">

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('reports.admin.report_card') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">{{ __('reports.fields.concerns') }}</dt>
                        <dd class="col-sm-8">{{ $report->targetLabel() }}</dd>

                        <dt class="col-sm-4">{{ __('reports.fields.type') }}</dt>
                        <dd class="col-sm-8">{{ $report->type->label() }}</dd>

                        <dt class="col-sm-4">{{ __('reports.fields.author') }}</dt>
                        <dd class="col-sm-8">{{ $report->user->name }} ({{ $report->user->role->label() }})</dd>

                        <dt class="col-sm-4">{{ __('reports.fields.date') }}</dt>
                        <dd class="col-sm-8">{{ $report->created_at->format('d/m/Y H:i') }}</dd>
                    </dl>
                    <blockquote class="blockquote border-left-warning pl-3 mb-0">
                        <p class="mb-0" style="font-size: 1rem;">{!! nl2br(e($report->description)) !!}</p>
                    </blockquote>

                    @if ($lot)
                        <a href="{{ route('admin.lots.show', $lot) }}" class="btn btn-light btn-sm mt-3">
                            <i class="fas fa-boxes fa-sm mr-1"></i> {{ __('reports.admin.open_lot') }} ({{ $lot->lot_number }})</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('reports.admin.decision_card') }}</h6>
                </div>
                <div class="card-body">
                    @if ($report->reviewed_at)
                        <p class="small text-gray-600">{{ __('reports.admin.reviewed_by', ['name' => $report->reviewer?->name ?? '—', 'date' => $report->reviewed_at->format('d/m/Y H:i')]) }}</p>
                    @endif
                    <form method="POST" action="{{ route('admin.reports.update', $report) }}" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="status">{{ __('reports.admin.decision') }}</label>
                            <select name="status" id="status" class="custom-select @error('status') is-invalid @enderror" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', $report->status->value) === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="admin_response">{{ __('reports.admin.response') }}</label>
                            <textarea name="admin_response" id="admin_response" rows="5" maxlength="2000" aria-describedby="responseHelp"
                                class="form-control @error('admin_response') is-invalid @enderror">{{ old('admin_response', $report->admin_response) }}</textarea>
                            <small id="responseHelp" class="form-text text-muted">{{ __('reports.admin.response_help') }}</small>
                            @error('admin_response')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save fa-sm mr-1"></i> {{ __('reports.admin.save') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

@endsection

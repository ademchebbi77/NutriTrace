@extends('layouts.layout')

@section('title', $certification->name)

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ $certification->name }}
            <span class="badge badge-{{ $certification->status->color() }} align-middle">{{ $certification->status->label() }}</span>
        </h1>
        <div>
            <a href="{{ area_route('certifications.index') }}" class="btn btn-sm btn-light shadow-sm">
                <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
            @can('update', $certification)
                <a href="{{ area_route('certifications.edit', $certification) }}" class="btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-pen fa-sm text-white-50"></i> {{ __('ui.edit') }}</a>
            @endcan
        </div>
    </div>

    @if ($errors->has('rejection_reason'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('rejection_reason') }}</div>
    @endif

    <div class="row">

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('certifications.information') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ __('certifications.fields.covers') }}</dt>
                        <dd class="col-sm-7">@include('certifications._target', ['certification' => $certification])</dd>

                        <dt class="col-sm-5">{{ __('certifications.fields.type') }}</dt>
                        <dd class="col-sm-7">{{ $certification->type->label() }}</dd>

                        <dt class="col-sm-5">{{ __('certifications.fields.issuing_organization') }}</dt>
                        <dd class="col-sm-7">{{ $certification->issuing_organization }}</dd>

                        <dt class="col-sm-5">{{ __('certifications.fields.certificate_number') }}</dt>
                        <dd class="col-sm-7">{{ $certification->certificate_number ?: '—' }}</dd>

                        <dt class="col-sm-5">{{ __('certifications.fields.issue_date') }}</dt>
                        <dd class="col-sm-7">{{ $certification->issue_date->format('d/m/Y') }}</dd>

                        <dt class="col-sm-5">{{ __('certifications.fields.expiration_date') }}</dt>
                        <dd class="col-sm-7">{{ $certification->expiration_date?->format('d/m/Y') ?? __('certifications.fields.no_expiration') }}</dd>

                        <dt class="col-sm-5">{{ __('certifications.fields.owner') }}</dt>
                        <dd class="col-sm-7">{{ $certification->owner->displayName() }}</dd>

                        <dt class="col-sm-5">{{ __('certifications.fields.validity') }}</dt>
                        <dd class="col-sm-7 mb-0">
                            <span class="badge badge-{{ $certification->isValid() ? 'success' : 'secondary' }}">
                                {{ $certification->isValid() ? __('certifications.valid') : __('certifications.not_valid') }}</span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('certifications.proof_card') }}</h6>
                </div>
                <div class="card-body">
                    @if ($certification->document_path)
                        <a href="{{ route('certifications.document', $certification) }}" class="btn btn-light btn-block" target="_blank" rel="noopener">
                            <i class="fas fa-file-alt fa-sm mr-1"></i> {{ __('certifications.view_proof') }}</a>
                    @else
                        <p class="mb-0 text-gray-600">{{ __('certifications.no_proof') }}</p>
                    @endif
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('certifications.review_card') }}</h6>
                </div>
                <div class="card-body">
                    @if ($certification->reviewed_at)
                        <p class="mb-2">{{ __('certifications.fields.reviewed_by') }} {{ $certification->reviewer?->name ?? '—' }}
                            {{ __('certifications.fields.reviewed_at', ['date' => $certification->reviewed_at->format('d/m/Y')]) }}</p>
                    @endif
                    @if ($certification->rejection_reason)
                        <div class="alert alert-danger mb-0">
                            <strong>{{ __('certifications.fields.rejection_reason') }} :</strong> {{ $certification->rejection_reason }}
                        </div>
                    @elseif (! $certification->reviewed_at)
                        <p class="mb-0 text-gray-600">{{ __('certifications.pending_notice') }}</p>
                    @endif

                    @can('review', $certification)
                        <hr>
                        <p class="small text-muted">{{ __('certifications.admin.check_list') }}</p>
                        <form method="POST" action="{{ route('admin.certifications.approve', $certification) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check fa-sm"></i> {{ __('certifications.admin.approve') }}
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectCertificationModal">
                            <i class="fas fa-times fa-sm"></i> {{ __('certifications.admin.reject') }}
                        </button>
                    @endcan
                </div>
            </div>
        </div>

    </div>

    @can('review', $certification)
        <div class="modal fade" id="rejectCertificationModal" tabindex="-1" role="dialog" aria-labelledby="rejectCertificationModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form class="modal-content" method="POST" action="{{ route('admin.certifications.reject', $certification) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectCertificationModalLabel">{{ __('certifications.admin.reject_title') }}</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="{{ __('ui.close') }}">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <label for="rejection_reason">{{ __('certifications.admin.reject_reason') }}</label>
                        <textarea name="rejection_reason" id="rejection_reason" rows="3" class="form-control" required minlength="5" maxlength="500"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">{{ __('ui.cancel') }}</button>
                        <button class="btn btn-danger" type="submit">{{ __('certifications.admin.reject') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

@endsection

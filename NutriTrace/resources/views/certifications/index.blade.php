@extends('layouts.layout')

@section('title', __('certifications.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('certifications.title') }}</h1>
        <a href="{{ area_route('certifications.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> {{ __('certifications.add') }}</a>
    </div>

    <p class="text-gray-600"><i class="fas fa-info-circle mr-1"></i>{{ __('certifications.pending_notice') }}</p>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('certifications.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($certifications->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('certifications.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('certifications.fields.name') }}</th>
                                <th>{{ __('certifications.fields.covers') }}</th>
                                <th>{{ __('certifications.fields.issuing_organization') }}</th>
                                <th>{{ __('certifications.fields.expiration_date') }}</th>
                                <th>{{ __('certifications.fields.status') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($certifications as $certification)
                                <tr>
                                    <td>
                                        <a href="{{ area_route('certifications.show', $certification) }}" class="font-weight-bold">{{ $certification->name }}</a>
                                        <br><span class="small text-gray-600">{{ $certification->type->label() }}</span>
                                    </td>
                                    <td>@include('certifications._target', ['certification' => $certification])</td>
                                    <td>{{ $certification->issuing_organization }}
                                        @if ($certification->certificate_number)
                                            <br><span class="small text-gray-600">{{ $certification->certificate_number }}</span>
                                        @endif
                                    </td>
                                    <td data-order="{{ $certification->expiration_date?->timestamp ?? 9999999999 }}">
                                        {{ $certification->expiration_date?->format('d/m/Y') ?? __('certifications.fields.no_expiration') }}
                                    </td>
                                    <td><span class="badge badge-{{ $certification->status->color() }}">{{ $certification->status->label() }}</span></td>
                                    <td class="text-nowrap">
                                        <a href="{{ area_route('certifications.show', $certification) }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }} {{ $certification->name }}">
                                            <i class="fas fa-eye"></i></a>
                                        @can('update', $certification)
                                            <a href="{{ area_route('certifications.edit', $certification) }}" class="btn btn-primary btn-circle btn-sm"
                                                title="{{ __('ui.edit') }}" aria-label="{{ __('ui.edit') }} {{ $certification->name }}">
                                                <i class="fas fa-pen"></i></a>
                                        @endcan
                                        @can('delete', $certification)
                                            <button type="button" class="btn btn-danger btn-circle btn-sm" data-toggle="modal"
                                                data-target="#confirmDeleteModal"
                                                data-action="{{ area_route('certifications.destroy', $certification) }}"
                                                data-label="{{ $certification->name }}"
                                                title="{{ __('ui.delete') }}" aria-label="{{ __('ui.delete') }} {{ $certification->name }}">
                                                <i class="fas fa-trash"></i></button>
                                        @endcan
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

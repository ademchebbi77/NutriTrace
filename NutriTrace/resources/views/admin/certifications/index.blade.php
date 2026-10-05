@extends('layouts.layout')

@section('title', __('certifications.admin.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('certifications.admin.title') }}</h1>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('certifications.admin.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($certifications->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('certifications.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0" data-order="[]">
                        <thead>
                            <tr>
                                <th>{{ __('certifications.fields.name') }}</th>
                                <th>{{ __('certifications.fields.covers') }}</th>
                                <th>{{ __('certifications.fields.owner') }}</th>
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
                                        <a href="{{ route('admin.certifications.show', $certification) }}" class="font-weight-bold">{{ $certification->name }}</a>
                                        <br><span class="small text-gray-600">{{ $certification->type->label() }}</span>
                                    </td>
                                    <td>@include('certifications._target', ['certification' => $certification])</td>
                                    <td>{{ $certification->owner->displayName() }}</td>
                                    <td>{{ $certification->issuing_organization }}
                                        @if ($certification->certificate_number)
                                            <br><span class="small text-gray-600">{{ $certification->certificate_number }}</span>
                                        @endif
                                    </td>
                                    <td data-order="{{ $certification->expiration_date?->timestamp ?? 9999999999 }}">
                                        {{ $certification->expiration_date?->format('d/m/Y') ?? __('certifications.fields.no_expiration') }}
                                    </td>
                                    <td><span class="badge badge-{{ $certification->status->color() }}">{{ $certification->status->label() }}</span></td>
                                    <td>
                                        <a href="{{ route('admin.certifications.show', $certification) }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }} {{ $certification->name }}">
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

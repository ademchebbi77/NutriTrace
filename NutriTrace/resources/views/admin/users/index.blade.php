@extends('layouts.layout')

@section('title', __('account.admin.users_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('account.admin.users_title') }}</h1>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('account.admin.users_title') }}</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered datatable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>{{ __('account.admin.col_user') }}</th>
                            <th>{{ __('account.admin.col_role') }}</th>
                            <th>{{ __('account.admin.col_organization') }}</th>
                            <th>{{ __('account.admin.col_status') }}</th>
                            <th>{{ __('account.admin.col_active') }}</th>
                            <th>{{ __('account.admin.col_registered') }}</th>
                            <th class="no-sort">{{ __('account.admin.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <span class="font-weight-bold">{{ $user->name }}</span><br>
                                    <span class="small text-gray-600">{{ $user->email }}</span>
                                    @unless ($user->hasVerifiedEmail())
                                        <br><span class="badge badge-warning">{{ __('account.admin.email_unverified') }}</span>
                                    @endunless
                                </td>
                                <td>{{ $user->role->label() }}</td>
                                <td>
                                    @if ($user->organization)
                                        {{ $user->organization->name }}
                                        @if ($user->organization->is_verified)
                                            <i class="fas fa-check-circle text-success" title="{{ __('account.organization.verified') }}"></i>
                                            <span class="sr-only">{{ __('account.organization.verified') }}</span>
                                        @endif
                                        <br><span class="small text-gray-600">{{ $user->organization->city }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $user->account_status->color() }}">{{ $user->account_status->label() }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $user->is_active ? 'success' : 'secondary' }}">
                                        {{ $user->is_active ? __('account.admin.yes') : __('account.admin.no') }}
                                    </span>
                                </td>
                                <td data-order="{{ $user->created_at->timestamp }}">{{ $user->created_at->format('d/m/Y') }}</td>
                                <td class="text-nowrap">
                                    @can('toggleActive', $user)
                                        @if ($user->account_status === \App\Enums\AccountStatus::APPROVED)
                                            <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-outline-secondary' : 'btn-success' }}">
                                                    {{ $user->is_active ? __('account.admin.deactivate') : __('account.admin.activate') }}
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                    @if ($user->organization)
                                        <form method="POST" action="{{ route('admin.users.toggle-verified', $user->organization) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $user->organization->is_verified ? 'btn-outline-warning' : 'btn-info' }}"
                                                title="{{ $user->organization->is_verified ? __('account.admin.unverify_org') : __('account.admin.verify_org') }}"
                                                aria-label="{{ $user->organization->is_verified ? __('account.admin.unverify_org') : __('account.admin.verify_org') }}">
                                                <i class="fas {{ $user->organization->is_verified ? 'fa-times-circle' : 'fa-check-circle' }} fa-sm"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

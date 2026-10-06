@extends('layouts.layout')

@section('title', __('account.admin.approvals_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('account.admin.approvals_title') }}</h1>

    @if ($errors->has('rejection_reason'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('rejection_reason') }}</div>
    @endif

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('account.admin.pending_accounts') }}</h6>
        </div>
        <div class="card-body">
            @if ($users->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('account.admin.no_pending') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('account.admin.col_organization') }}</th>
                                <th>{{ __('account.admin.col_user') }}</th>
                                <th>{{ __('account.admin.col_role') }}</th>
                                <th>{{ __('account.admin.col_city') }}</th>
                                <th>{{ __('account.admin.col_registration') }}</th>
                                <th>{{ __('account.admin.col_requested') }}</th>
                                <th class="no-sort">{{ __('account.admin.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td class="font-weight-bold">{{ $user->organization?->name }}</td>
                                    <td>
                                        {{ $user->name }}<br>
                                        <span class="small text-gray-600">{{ $user->email }}</span>
                                        @unless ($user->hasVerifiedEmail())
                                            <br><span class="badge badge-warning">{{ __('account.admin.email_unverified') }}</span>
                                        @endunless
                                    </td>
                                    <td>{{ $user->role->label() }}</td>
                                    <td>{{ $user->organization?->city }}</td>
                                    <td>{{ $user->organization?->registration_number }}</td>
                                    <td data-order="{{ $user->created_at->timestamp }}">{{ $user->created_at->format('d/m/Y') }}</td>
                                    <td class="text-nowrap">
                                        <form method="POST" action="{{ route('admin.approvals.approve', $user) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-check fa-sm"></i> {{ __('account.admin.approve') }}
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-danger btn-sm" data-toggle="modal"
                                            data-target="#rejectModal"
                                            data-action="{{ route('admin.approvals.reject', $user) }}"
                                            data-label="{{ $user->organization?->name ?? $user->name }}">
                                            <i class="fas fa-times fa-sm"></i> {{ __('account.admin.reject') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Reject modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form class="modal-content" method="POST" action="#">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">{{ __('account.admin.reject_title') }}</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="{{ __('ui.close') }}">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="font-weight-bold js-reject-label"></p>
                    <label for="rejection_reason">{{ __('account.admin.reject_reason') }}</label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="3" class="form-control" required
                        minlength="5" maxlength="500"></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">{{ __('ui.cancel') }}</button>
                    <button class="btn btn-danger" type="submit">{{ __('account.admin.reject') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script type="module">
        $('#rejectModal').on('show.bs.modal', function (event) {
            const trigger = $(event.relatedTarget);
            $(this).find('form').attr('action', trigger.data('action'));
            $(this).find('.js-reject-label').text(trigger.data('label'));
        });
    </script>
@endpush

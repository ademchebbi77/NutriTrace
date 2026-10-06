@extends('layouts.layout')

@section('title', __('account.profile.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('account.profile.title') }}</h1>
    </div>

    <div class="row">

        <div class="col-lg-7">

            <!-- Profile information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('account.profile.information') }}</h6>
                </div>
                <div class="card-body">
                    @if (! $user->hasVerifiedEmail())
                        <div class="alert alert-warning d-flex align-items-center justify-content-between" role="alert">
                            <span>{{ __('account.profile.email_unverified') }}</span>
                            <form method="POST" action="{{ route('verification.send') }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-warning">{{ __('account.verify.resend') }}</button>
                            </form>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        @method('PATCH')

                        <div class="d-flex align-items-center mb-4">
                            <img class="rounded-circle avatar-preview mr-3" alt=""
                                src="{{ $user->avatarUrl() ?? Vite::asset('resources/assets/img/admin/undraw_profile.svg') }}">
                            <div class="flex-grow-1">
                                <label for="avatar">{{ __('account.fields.avatar') }}</label>
                                <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp"
                                    class="form-control-file @error('avatar') is-invalid @enderror"
                                    aria-describedby="avatarHelp">
                                <small id="avatarHelp" class="form-text text-muted">{{ __('account.fields.image_help') }}</small>
                                @error('avatar')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="name">{{ __('account.fields.name') }}</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}"
                                class="form-control @error('name') is-invalid @enderror" required autocomplete="name">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-7">
                                <label for="email">{{ __('account.fields.email') }}</label>
                                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
                                    class="form-control @error('email') is-invalid @enderror" required
                                    autocomplete="username">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-5">
                                <label for="phone">{{ __('account.fields.phone') }}</label>
                                <input type="tel" name="phone" id="phone" value="{{ old('phone', $user->phone) }}"
                                    class="form-control @error('phone') is-invalid @enderror" autocomplete="tel">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save fa-sm mr-1"></i> {{ __('account.profile.save') }}
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <div class="col-lg-5">

            <!-- Account summary -->
            <div class="card border-left-primary shadow mb-4">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">{{ __('account.profile.role') }}</div>
                    <div class="h5 mb-1 font-weight-bold text-gray-800">{{ $user->role->label() }}</div>
                    <span class="badge badge-{{ $user->account_status->color() }}">{{ $user->account_status->label() }}</span>
                    <div class="small text-gray-600 mt-2">
                        {{ __('account.profile.member_since', ['date' => $user->created_at->translatedFormat('j F Y')]) }}
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('account.profile.password_heading') }}</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('password.update') }}" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="current_password">{{ __('account.fields.current_password') }}</label>
                            <input type="password" name="current_password" id="current_password"
                                class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                                autocomplete="current-password" required>
                            @error('current_password', 'updatePassword')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="new_password">{{ __('account.fields.new_password') }}</label>
                            <input type="password" name="password" id="new_password"
                                class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                                autocomplete="new-password" required>
                            @error('password', 'updatePassword')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="new_password_confirmation">{{ __('account.fields.password_confirmation') }}</label>
                            <input type="password" name="password_confirmation" id="new_password_confirmation"
                                class="form-control" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('account.profile.password_save') }}</button>
                    </form>
                </div>
            </div>

            <!-- Delete account -->
            <div class="card shadow mb-4 border-bottom-danger">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">{{ __('account.profile.delete_heading') }}</h6>
                </div>
                <div class="card-body">
                    <p>{{ __('account.profile.delete_warning') }}</p>
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteAccountModal">
                        <i class="fas fa-trash fa-sm mr-1"></i> {{ __('account.profile.delete_button') }}
                    </button>
                </div>
            </div>

        </div>

    </div>

    <!-- Delete account modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" role="dialog"
        aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form class="modal-content" method="POST" action="{{ route('profile.destroy') }}" novalidate>
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAccountModalLabel">{{ __('account.profile.delete_heading') }}</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="{{ __('ui.close') }}">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>{{ __('account.profile.delete_confirm') }}</p>
                    <label class="sr-only" for="delete_password">{{ __('account.fields.password') }}</label>
                    <input type="password" name="password" id="delete_password"
                        class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                        placeholder="{{ __('account.fields.password') }}" autocomplete="current-password" required>
                    @error('password', 'userDeletion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">{{ __('ui.cancel') }}</button>
                    <button class="btn btn-danger" type="submit">{{ __('account.profile.delete_button') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@if ($errors->userDeletion->isNotEmpty())
    @push('scripts')
        <script type="module">
            // Reopen the modal when the password confirmation failed.
            $('#deleteAccountModal').modal('show');
        </script>
    @endpush
@endif

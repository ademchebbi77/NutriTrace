@extends('layouts.auth')

@section('title', __('account.register.title'))
@section('heading', __('account.register.title'))
@section('subheading', __('account.register.sub'))
@section('card-class', 'auth-card-wide')

@section('content')
    <form class="user" method="POST" action="{{ route('register') }}" novalidate>
        @csrf
        <div class="form-group">
            <label for="role">{{ __('account.fields.role') }}</label>
            <select name="role" id="role" class="custom-select custom-select-user @error('role') is-invalid @enderror" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" data-professional="{{ $role->isProfessional() ? '1' : '0' }}"
                        @selected(old('role', 'CONSOMMATEUR') === $role->value)>
                        {{ $role->label() }} - {{ __('account.register.role_help.'.$role->value) }}
                    </option>
                @endforeach
            </select>
            @error('role')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group row">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <label for="name">{{ __('account.fields.name') }}</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}"
                    class="form-control form-control-user @error('name') is-invalid @enderror" required autocomplete="name">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-sm-6">
                <label for="phone">{{ __('account.fields.phone') }}</label>
                <input type="tel" name="phone" id="phone" value="{{ old('phone') }}"
                    class="form-control form-control-user @error('phone') is-invalid @enderror" autocomplete="tel">
                @error('phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="form-group">
            <label for="email">{{ __('account.fields.email') }}</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                class="form-control form-control-user @error('email') is-invalid @enderror" required autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group row">
            <div class="col-sm-6 mb-3 mb-sm-0">
                <label for="password">{{ __('account.fields.password') }}</label>
                <input type="password" name="password" id="password"
                    class="form-control form-control-user @error('password') is-invalid @enderror" required
                    autocomplete="new-password">
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-sm-6">
                <label for="password_confirmation">{{ __('account.fields.password_confirmation') }}</label>
                <input type="password" name="password_confirmation" id="password_confirmation"
                    class="form-control form-control-user" required autocomplete="new-password">
            </div>
        </div>

        <div id="organizationFields" class="d-none">
            <hr>
            <h2 class="h6 text-gray-900 font-weight-bold">{{ __('account.register.organization_heading') }}</h2>
            <p class="small text-gray-700">
                <i class="fas fa-info-circle mr-1"></i>{{ __('account.register.professional_notice') }}
            </p>
            <div class="form-group row">
                <div class="col-sm-7 mb-3 mb-sm-0">
                    <label for="organization_name">{{ __('account.fields.organization_name') }}</label>
                    <input type="text" name="organization_name" id="organization_name" value="{{ old('organization_name') }}"
                        class="form-control form-control-user @error('organization_name') is-invalid @enderror">
                    @error('organization_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-sm-5">
                    <label for="organization_city">{{ __('account.fields.organization_city') }}</label>
                    <input type="text" name="organization_city" id="organization_city" value="{{ old('organization_city') }}"
                        class="form-control form-control-user @error('organization_city') is-invalid @enderror">
                    @error('organization_city')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="form-group row">
                <div class="col-sm-7 mb-3 mb-sm-0">
                    <label for="organization_address">{{ __('account.fields.organization_address') }}</label>
                    <input type="text" name="organization_address" id="organization_address" value="{{ old('organization_address') }}"
                        class="form-control form-control-user @error('organization_address') is-invalid @enderror">
                    @error('organization_address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-sm-5">
                    <label for="registration_number">{{ __('account.fields.registration_number') }}</label>
                    <input type="text" name="registration_number" id="registration_number" value="{{ old('registration_number') }}"
                        class="form-control form-control-user @error('registration_number') is-invalid @enderror">
                    @error('registration_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-user btn-block">
            {{ __('account.register.submit') }}
        </button>
    </form>
@endsection

@section('footer')
    {{ __('account.register.already') }} <a href="{{ route('login') }}">{{ __('account.login.submit') }}</a>
@endsection

@push('scripts')
    <script type="module">
        // Show the organization fields only for professional account types.
        const role = document.getElementById('role');
        const organization = document.getElementById('organizationFields');
        const sync = () => organization.classList.toggle('d-none', role.selectedOptions[0].dataset.professional !== '1');

        role.addEventListener('change', sync);
        sync();
    </script>
@endpush

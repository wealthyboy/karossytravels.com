@extends('layouts.public')

@section('title', 'Create an account')

@section('content')
<section class="auth-page"><div class="container public-container"><div class="auth-card auth-card-wide">
    <div class="auth-heading"><span class="auth-icon"><i class="bi bi-person-plus"></i></span><h1>Create your account</h1><p>Save traveller details, manage bookings and enjoy a faster checkout.</p></div>
    <form method="POST" action="{{ route('register.store') }}" class="auth-form">@csrf
        <div class="row g-3"><div class="col-md-6"><label for="first_name" class="form-label">First name</label><input id="first_name" name="first_name" value="{{ old('first_name') }}" class="form-control @error('first_name') is-invalid @enderror" required>@error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label for="last_name" class="form-label">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name') }}" class="form-control @error('last_name') is-invalid @enderror" required>@error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
        <div><label for="email" class="form-label">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="row g-3"><div class="col-md-7"><label for="phone" class="form-label">Phone number <span>Optional</span></label><input id="phone" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" autocomplete="tel">@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-5"><label for="currency_code" class="form-label">Currency</label><select id="currency_code" name="currency_code" class="form-select">@foreach(['NGN','USD','GBP','EUR','CAD','ZAR','AED'] as $currency)<option @selected(old('currency_code','NGN')===$currency)>{{ $currency }}</option>@endforeach</select></div></div>
        <div class="row g-3"><div class="col-md-6"><label for="password" class="form-label">Password</label><div class="auth-password"><input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required><button type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div><div class="col-md-6"><label for="password_confirmation" class="form-label">Confirm password</label><div class="auth-password"><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required><button type="button" data-toggle-password="password_confirmation" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div></div>
        <small class="text-secondary">Use at least 8 characters containing letters and numbers.</small>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="terms" value="1" @checked(old('terms')) required><span class="form-check-label">I agree to the Terms of Service and Privacy Policy.</span></label>
        @error('terms')<div class="text-danger small">{{ $message }}</div>@enderror
        <button class="btn btn-karossy auth-submit" type="submit">Create account</button>
    </form>
    <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</div></div></section>
@endsection

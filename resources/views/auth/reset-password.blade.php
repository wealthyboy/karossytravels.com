@extends('layouts.public')

@section('title', 'Reset password')

@section('content')
<section class="auth-page"><div class="container public-container"><div class="auth-card">
    <div class="auth-heading"><span class="auth-icon"><i class="bi bi-shield-lock"></i></span><h1>Set a new password</h1><p>Choose a secure password for your Karossy account.</p></div>
    <form method="POST" action="{{ route('password.update') }}" class="auth-form">@csrf<input type="hidden" name="token" value="{{ $token }}">
        <div><label for="email" class="form-label">Email address</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div><label for="password" class="form-label">New password</label><div class="auth-password"><input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required><button type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
        <div><label for="password_confirmation" class="form-label">Confirm new password</label><div class="auth-password"><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required><button type="button" data-toggle-password="password_confirmation" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
        <small class="text-secondary">Use at least 8 characters containing letters and numbers.</small>
        <button class="btn btn-karossy auth-submit" type="submit">Reset password</button>
    </form>
</div></div></section>
@endsection

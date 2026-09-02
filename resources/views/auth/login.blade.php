@extends('layouts.public')

@section('title', 'Sign in')

@section('content')
<section class="auth-page"><div class="container public-container"><div class="auth-card">
    <div class="auth-heading"><span class="auth-icon"><i class="bi bi-person"></i></span><h1>Welcome back</h1><p>Sign in to manage your trips and Karossy account.</p></div>
    <form method="POST" action="{{ route('login.store') }}" class="auth-form" data-loading-form>@csrf
        <div><label for="email" class="form-label">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" autofocus required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div><div class="d-flex justify-content-between"><label for="password" class="form-label">Password</label><a href="#" class="auth-helper-link">Forgot password?</a></div><div class="auth-password"><input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required><button type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="remember" value="1"><span class="form-check-label">Keep me signed in</span></label>
        <button class="btn btn-karossy auth-submit" type="submit" data-submit-loading><span class="spinner-border spinner-border-sm d-none" data-submit-spinner aria-hidden="true"></span><span data-submit-label>Sign in</span></button>
    </form>
    <p class="auth-switch">New to Karossy? <a href="{{ route('register') }}">Create an account</a></p>
</div></div></section>
@endsection

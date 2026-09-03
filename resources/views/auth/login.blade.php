@extends('layouts.public')

@section('title', 'Sign in')

@section('content')
<section class="auth-page">
    <div class="container public-container">
        <div class="auth-card">
            <div class="auth-heading">
                <span class="auth-icon"><i class="bi bi-person"></i></span>
                <h1>Welcome back</h1>
                <p>Sign in to manage your trips and Karossy account.</p>
            </div>

            @error('google')
                <div class="alert alert-danger py-2 px-3 small mb-3" role="alert">
                    <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                </div>
            @enderror

            <a
                href="{{ route('auth.google.redirect') }}"
                class="btn w-100 d-flex align-items-center justify-content-center gap-2 bg-white text-dark fw-semibold border"
                style="min-height:3rem;border-radius:4.65rem;border-color:#d9dde5 !important;box-shadow:0 .35rem 1rem rgba(18,25,38,.05);"
                aria-label="Continue with Google"
            >
                <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                    <path fill="#4285F4" d="M17.64 9.205c0-.638-.057-1.252-.164-1.841H9v3.482h4.844a4.14 4.14 0 0 1-1.797 2.716v2.259h2.909c1.702-1.567 2.684-3.876 2.684-6.616Z"/>
                    <path fill="#34A853" d="M9 18c2.43 0 4.468-.806 5.956-2.179l-2.91-2.259c-.805.54-1.835.859-3.046.859-2.344 0-4.328-1.585-5.037-3.714H.956v2.332A9 9 0 0 0 9 18Z"/>
                    <path fill="#FBBC05" d="M3.963 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.169.281-1.707V4.961H.956A9 9 0 0 0 0 9c0 1.452.347 2.826.956 4.039l3.007-2.332Z"/>
                    <path fill="#EA4335" d="M9 3.579c1.321 0 2.507.454 3.441 1.346l2.582-2.582C13.464.891 11.426 0 9 0A9 9 0 0 0 .956 4.961l3.007 2.332C4.672 5.164 6.656 3.579 9 3.579Z"/>
                </svg>
                <span>Continue with Google</span>
            </a>

            <div class="d-flex align-items-center gap-3 my-3" aria-hidden="true">
                <span class="border-top flex-grow-1"></span>
                <span class="text-secondary small text-nowrap">or continue with email</span>
                <span class="border-top flex-grow-1"></span>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="auth-form" data-loading-form>
                @csrf
                <div>
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" autofocus required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <div class="d-flex justify-content-between">
                        <label for="password" class="form-label">Password</label>
                        <a href="#" class="auth-helper-link">Forgot password?</a>
                    </div>
                    <div class="auth-password">
                        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                        <button type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" value="1">
                    <span class="form-check-label">Keep me signed in</span>
                </label>
                <button class="btn btn-karossy auth-submit" type="submit" data-submit-loading>
                    <span class="spinner-border spinner-border-sm d-none" data-submit-spinner aria-hidden="true"></span>
                    <span data-submit-label>Sign in</span>
                </button>
            </form>

            <p class="auth-switch">New to Karossy? <a href="{{ route('register') }}">Create an account</a></p>
        </div>
    </div>
</section>
@endsection

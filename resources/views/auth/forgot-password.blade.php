@extends('layouts.public')

@section('title', 'Forgot password')

@section('content')
<section class="auth-page"><div class="container public-container"><div class="auth-card">
    <div class="auth-heading"><span class="auth-icon"><i class="bi bi-key"></i></span><h1>Forgot your password?</h1><p>Enter the email address on your Karossy account and we’ll send you a secure reset link.</p></div>
    @if(session('status'))<div class="alert alert-success" role="status"><i class="bi bi-check-circle me-2"></i>{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" class="auth-form">@csrf
        <div><label for="email" class="form-label">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" autofocus required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <button class="btn btn-karossy auth-submit" type="submit">Email reset link</button>
    </form>
    <p class="auth-switch"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Back to sign in</a></p>
</div></div></section>
@endsection

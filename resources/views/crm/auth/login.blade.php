<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in | Karossy CRM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { color-scheme: light; }
        body.crm-login-body { background: #f4f6fa; color: #17233f; min-height: 100vh; }
        .crm-login { align-items: center; display: flex; justify-content: center; min-height: 100vh; padding: 2rem; position: relative; }
        .crm-login::before { background: radial-gradient(circle, rgba(225, 42, 52, .13) 0, rgba(225, 42, 52, 0) 67%); content: ''; height: 34rem; left: -12rem; position: fixed; top: -15rem; width: 34rem; }
        .crm-login__card { background: #fff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 1.25rem 3.5rem rgba(23, 35, 63, .10); max-width: 29rem; padding: 2.4rem; position: relative; width: 100%; }
        .crm-login__brand { align-items: center; display: flex; gap: .8rem; margin-bottom: 2.25rem; }
        .crm-login__mark { align-items: center; background: #fff; border: 1px solid #e0e6ef; border-radius: .8rem; display: flex; height: 3.35rem; justify-content: center; overflow: hidden; padding: .28rem; width: 3.35rem; }
        .crm-login__mark img { display: block; height: 100%; object-fit: contain; width: 100%; }
        .crm-login__eyebrow { color: #8995aa; font-size: .7rem; font-weight: 800; letter-spacing: .12em; margin: 0 0 .15rem; text-transform: uppercase; }
        .crm-login__name { color: #17233f; font-size: 1.1rem; font-weight: 800; margin: 0; }
        .crm-login h1 { font-size: 1.8rem; font-weight: 800; letter-spacing: -.045em; margin: 0; }
        .crm-login__intro { color: #66748a; font-size: .94rem; line-height: 1.6; margin: .6rem 0 1.8rem; }
        .crm-login .form-label { color: #344158; font-size: .82rem; font-weight: 700; margin-bottom: .4rem; }
        .crm-login .form-control { border-color: #d8e0ea; border-radius: .7rem; min-height: 3.1rem; }
        .crm-login .form-control:focus { border-color: #246bfe; box-shadow: 0 0 0 .2rem rgba(36, 107, 254, .12); }
        .crm-login__password { position: relative; }
        .crm-login__password .form-control { padding-right: 3.2rem; }
        .crm-login__password button { background: transparent; border: 0; color: #728096; padding: .55rem; position: absolute; right: .45rem; top: 50%; transform: translateY(-50%); }
        .crm-login__options { align-items: center; display: flex; justify-content: space-between; margin-top: .2rem; }
        .crm-login__options .form-check-label { color: #66748a; font-size: .83rem; }
        .crm-login__options .form-check-input:checked { background-color: #246bfe; border-color: #246bfe; }
        .crm-login__forgot { color: #246bfe; font-size: .83rem; font-weight: 700; text-decoration: none; }
        .crm-login__submit { background: #246bfe; border: 0; border-radius: .7rem; font-weight: 800; margin-top: .45rem; min-height: 3.15rem; width: 100%; }
        .crm-login__submit:hover, .crm-login__submit:focus { background: #1558df; }
        .crm-login__note { color: #8b97a9; font-size: .76rem; line-height: 1.5; margin: 1.45rem 0 0; text-align: center; }
        @media (max-width: 575.98px) { .crm-login { padding: 1rem; } .crm-login__card { padding: 1.65rem; } .crm-login__options { align-items: flex-start; flex-direction: column; gap: .75rem; } }
    </style>
</head>
<body class="crm-login-body">
    <main class="crm-login">
        <section class="crm-login__card" aria-labelledby="crm-login-title">
            <div class="crm-login__brand">
                <span class="crm-login__mark">
                    <img src="{{ asset('karossy-bird-mark.png') }}" alt="Karossy Travels">
                </span>
                <div>
                    <p class="crm-login__eyebrow">Karossy Travels</p>
                    <p class="crm-login__name">CRM Workspace</p>
                </div>
            </div>

            <h1 id="crm-login-title">Welcome back</h1>
            <p class="crm-login__intro">Sign in to manage bookings, customers, sales and operations.</p>

            @if(session('status'))
                <div class="alert alert-success py-2 px-3 small mb-3" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="d-grid gap-3" data-loading-form>
                @csrf
                <div>
                    <label for="email" class="form-label">Work email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" autofocus required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="password" class="form-label">Password</label>
                    <div class="crm-login__password">
                        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                        <button type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="crm-login__options">
                    <label class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="remember" value="1">
                        <span class="form-check-label">Keep me signed in</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="crm-login__forgot">Forgot password?</a>
                </div>
                <button class="btn btn-primary crm-login__submit" type="submit" data-submit-loading>
                    <span class="spinner-border spinner-border-sm d-none" data-submit-spinner aria-hidden="true"></span>
                    <span data-submit-label>Sign in to CRM</span>
                </button>
            </form>

            <p class="crm-login__note">Restricted workspace for authorised Karossy staff.</p>
        </section>
    </main>
</body>
</html>

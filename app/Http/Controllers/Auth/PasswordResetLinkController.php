<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

final class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ])['email']));

        $status = Password::sendResetLink(['email' => $email]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'If an account exists for that email, we have sent a password reset link.')
            : back()->withInput()->withErrors(['email' => 'We could not send a reset link right now. Please try again.']);
    }
}

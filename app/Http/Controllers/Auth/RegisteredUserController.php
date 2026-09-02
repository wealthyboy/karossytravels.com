<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'currency_code' => ['required', Rule::in(['NGN', 'USD', 'GBP', 'EUR', 'CAD', 'ZAR', 'AED'])],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ]);

        [$user, $customer] = DB::transaction(function () use ($data): array {
            $email = strtolower(trim($data['email']));
            $user = User::create([
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $email,
                'account_type' => 'b2c',
                'currency_code' => $data['currency_code'],
                'status' => 'active',
                'password' => $data['password'],
            ]);
            $customer = Customer::create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
            ]);

            return [$user, $customer];
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your Karossy account is ready.',
                'user' => ['name' => $user->name, 'email' => $user->email],
                'csrf_token' => csrf_token(),
            ], 201);
        }

        return redirect()->route('home')->with('success', 'Welcome to Karossy, '.$customer->first_name.'.');
    }
}

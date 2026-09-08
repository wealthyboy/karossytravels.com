<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\MobileAuthToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc'], 'password' => ['required', 'string']]);
        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['The email address or password is incorrect.']]);
        }
        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['email' => ['This account is not active. Please contact Karossy support.']]);
        }

        return $this->authenticated($request, $user);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'currency_code' => ['required', Rule::in(['NGN', 'USD', 'GBP', 'EUR', 'CAD', 'ZAR', 'AED'])],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ]);
        $user = DB::transaction(function () use ($data): User {
            $email = strtolower(trim($data['email']));
            $user = User::create(['name' => trim($data['first_name'].' '.$data['last_name']), 'email' => $email, 'account_type' => 'b2c', 'currency_code' => $data['currency_code'], 'status' => 'active', 'password' => $data['password']]);
            Customer::create(['user_id' => $user->id, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $email, 'phone' => $data['phone'] ?? null, 'status' => 'active']);
            return $user;
        });

        return $this->authenticated($request, $user, 201);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($request, ['user' => $this->userData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('mobile_auth_token')?->delete();
        return ApiResponse::success($request, ['message' => 'You are signed out.']);
    }

    public function bookings(Request $request): JsonResponse
    {
        $user = $request->user();
        $bookings = \App\Models\Booking::query()->with(['order', 'tickets'])
            ->whereHas('order', fn (Builder $query) => $query->where('user_id', $user->id)->orWhereHas('customerProfile', fn (Builder $customer) => $customer->where('user_id', $user->id)))
            ->latest()->limit(50)->get()->map(fn ($booking) => [
                'reference' => $booking->order?->reference,
                'product_type' => $booking->product_type,
                'status' => $booking->status,
                'provider_locator' => $booking->provider_locator,
                'ticket_status' => $booking->tickets->isEmpty() ? null : ($booking->tickets->every(fn ($ticket) => $ticket->status === 'issued') ? 'issued' : 'pending'),
                'price' => ['total_minor' => (int) ($booking->order?->total_minor ?? 0), 'currency' => $booking->order?->currency ?? 'NGN'],
                'booked_at' => $booking->booked_at?->toIso8601String(),
            ])->values();
        return ApiResponse::success($request, ['bookings' => $bookings]);
    }

    private function authenticated(Request $request, User $user, int $status = 200): JsonResponse
    {
        $plain = bin2hex(random_bytes(32));
        MobileAuthToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDays(90)]);
        return ApiResponse::success($request, ['token' => $plain, 'user' => $this->userData($user)], [], $status);
    }

    private function userData(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'currency_code' => $user->currency_code];
    }
}

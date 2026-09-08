<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BookingLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $order = Order::query()
            ->with(['bookings.tickets', 'customerProfile'])
            ->whereRaw('LOWER(reference) = ?', [Str::lower(trim($validated['reference']))])
            ->first();

        $orderEmail = Str::lower(trim((string) ($order?->customerProfile?->email ?: data_get($order?->customer, 'email'))));
        $submittedEmail = Str::lower(trim($validated['email']));
        $booking = $order?->bookings->first();

        if (! $booking || ! hash_equals($orderEmail, $submittedEmail)) {
            throw ValidationException::withMessages([
                'reference' => ['We could not find a booking matching that reference and email address.'],
            ]);
        }

        return ApiResponse::success($request, [
            'reference' => $order->reference,
            'product_type' => $booking->product_type,
            'status' => $booking->status,
            'provider_locator' => $booking->provider_locator,
            'ticket_status' => $booking->tickets->isEmpty()
                ? null
                : ($booking->tickets->every(fn ($ticket): bool => $ticket->status === 'issued') ? 'issued' : 'pending'),
            'price' => [
                'total_minor' => (int) $order->total_minor,
                'currency' => $order->currency,
            ],
            'booked_at' => $booking->booked_at?->toIso8601String(),
        ]);
    }
}

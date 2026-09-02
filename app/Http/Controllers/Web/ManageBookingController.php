<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ManageBookingController extends Controller
{
    public function index(): View
    {
        return view('manage-booking.index');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $order = Order::query()
            ->with(['bookings', 'customerProfile'])
            ->whereRaw('LOWER(reference) = ?', [Str::lower(trim($validated['reference']))])
            ->first();

        $orderEmail = Str::lower(trim((string) ($order?->customerProfile?->email ?: data_get($order?->customer, 'email'))));
        $submittedEmail = Str::lower(trim($validated['email']));
        $booking = $order?->bookings->first();

        if (! $booking || ! hash_equals($orderEmail, $submittedEmail)) {
            throw ValidationException::withMessages([
                'reference' => 'We could not find a booking matching that reference and email address.',
            ]);
        }

        return redirect()->to(URL::temporarySignedRoute(
            'manage-booking.show',
            now()->addMinutes(30),
            ['booking' => $booking],
        ));
    }

    public function show(Booking $booking): View
    {
        return view('account.bookings.show', [
            'booking' => $booking->load(['order', 'tickets', 'addons']),
            'managedAsGuest' => true,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountBookingController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = $this->visible($request)->with(['order', 'tickets'])->latest()->paginate(15);
        return view('account.bookings.index', compact('bookings'));
    }

    public function show(Request $request, Booking $booking): View
    {
        abort_unless($this->visible($request)->whereKey($booking->id)->exists(), 404);
        return view('account.bookings.show', ['booking' => $booking->load(['order', 'tickets', 'addons'])]);
    }

    private function visible(Request $request): Builder
    {
        $user = $request->user();
        return Booking::query()->whereHas('order', fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhereHas('customerProfile', fn (Builder $customer) => $customer->where('user_id', $user->id)));
    }
}

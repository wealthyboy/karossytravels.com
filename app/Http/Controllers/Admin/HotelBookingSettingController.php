<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HotelBookingSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HotelBookingSettingController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.settings.hotel-booking', [
            'settings' => HotelBookingSetting::current(),
            'canEdit' => $this->isOwner($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->isOwner($request), 403, 'Only the owner can update hotel booking card details.');

        $settings = HotelBookingSetting::current();
        $requiresDetails = blank($settings->last_four);
        $requiredWhenReplacing = $requiresDetails ? 'required' : 'required_with:last_four';
        $validated = $request->validate([
            'cardholder_name' => [$requiredWhenReplacing, 'nullable', 'string', 'max:120'],
            'card_brand' => [$requiredWhenReplacing, 'nullable', 'string', 'in:VI,MC,AX,DS,DC,JC'],
            'last_four' => [$requiresDetails ? 'required' : 'required_with:cardholder_name,card_brand,expiry_month,expiry_year', 'nullable', 'digits:4'],
            'expiry_month' => [$requiredWhenReplacing, 'nullable', 'integer', 'between:1,12'],
            'expiry_year' => [$requiredWhenReplacing, 'nullable', 'integer', 'min:'.now()->year, 'max:'.now()->addYears(20)->year],
            'vault_reference' => ['nullable', 'string', 'max:500'],
        ]);

        if (blank($validated['last_four'] ?? null)) {
            return back()->with('success', 'Hotel card settings were left unchanged.');
        }

        $expiry = now()->setDate((int) $validated['expiry_year'], (int) $validated['expiry_month'], 1)->endOfMonth();
        if ($expiry->isPast()) {
            return back()->withErrors(['expiry_month' => 'The card expiry date must be in the future.'])->withInput();
        }

        $settings->update([
            'cardholder_name' => trim((string) $validated['cardholder_name']),
            'card_brand' => $validated['card_brand'],
            'last_four' => $validated['last_four'],
            'expiry_month' => str_pad((string) $validated['expiry_month'], 2, '0', STR_PAD_LEFT),
            'expiry_year' => (string) $validated['expiry_year'],
            'vault_reference' => filled($validated['vault_reference'] ?? null)
                ? trim((string) $validated['vault_reference'])
                : $settings->vault_reference,
        ]);

        return back()->with('success', 'Hotel card settings updated. No full card number or CVV is stored.');
    }

    private function isOwner(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->isOwner();
    }
}

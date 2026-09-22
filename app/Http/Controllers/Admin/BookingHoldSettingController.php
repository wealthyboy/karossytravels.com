<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingHoldSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BookingHoldSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.booking-hold', ['settings' => BookingHoldSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'timeout_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'ngn_bank_name' => ['nullable', 'string', 'max:120'],
            'ngn_account_name' => ['nullable', 'string', 'max:160'],
            'ngn_account_number' => ['nullable', 'string', 'max:80'],
            'ngn_sort_code' => ['nullable', 'string', 'max:80'],
            'usd_bank_name' => ['nullable', 'string', 'max:120'],
            'usd_account_name' => ['nullable', 'string', 'max:160'],
            'usd_account_number' => ['nullable', 'string', 'max:80'],
            'usd_sort_code' => ['nullable', 'string', 'max:80'],
        ]);
        $values['enabled'] = (bool) ($values['enabled'] ?? false);
        $values['bank_name'] = $values['ngn_bank_name'] ?? null;
        $values['account_name'] = $values['ngn_account_name'] ?? null;
        $values['account_number'] = $values['ngn_account_number'] ?? null;
        $values['sort_code'] = $values['ngn_sort_code'] ?? null;
        BookingHoldSetting::current()->update($values);

        return back()->with('success', 'Book on Hold settings updated successfully.');
    }
}

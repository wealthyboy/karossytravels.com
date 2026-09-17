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
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:160'],
            'account_number' => ['nullable', 'string', 'max:80'],
            'sort_code' => ['nullable', 'string', 'max:80'],
        ]);
        $values['enabled'] = (bool) ($values['enabled'] ?? false);
        BookingHoldSetting::current()->update($values);

        return back()->with('success', 'Book on Hold settings updated successfully.');
    }
}

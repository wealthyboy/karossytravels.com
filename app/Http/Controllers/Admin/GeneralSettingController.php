<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGeneralSettingRequest;
use App\Models\GeneralSetting;
use App\Support\AuditLogger;
use App\Support\SocialLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class GeneralSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.general', [
            'settings' => GeneralSetting::current(),
            'platforms' => SocialLinks::platforms(),
            'ready' => Schema::hasTable('general_settings'),
        ]);
    }

    public function update(UpdateGeneralSettingRequest $request, AuditLogger $audit): RedirectResponse
    {
        if (! Schema::hasTable('general_settings')) {
            return back()->with('error', 'Run the General Settings database migration before saving.');
        }

        DB::transaction(function () use ($request, $audit): void {
            $before = GeneralSetting::current()->toArray();
            $values = $request->validated();
            $values['phone_numbers'] ??= [];
            $values['social_links'] = array_filter($values['social_links'] ?? [], fn ($url): bool => filled($url));
            $values['whatsapp_number'] ??= null;
            $settings = GeneralSetting::query()->updateOrCreate(['id' => 1], $values);
            $audit->record('settings.general.updated', 'General settings and public social links updated.', $settings, $before, $settings->fresh()->toArray());
        });

        return redirect()->route('admin.settings.general.edit')->with('success', 'General settings saved. Your social links are now updated in the website footer.');
    }
}

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
            'ready' => Schema::hasTable('general_settings') && Schema::hasColumns('general_settings', ['enquiry_emails', 'enquiry_cc_emails', 'enquiry_bcc_emails']),
        ]);
    }

    public function update(UpdateGeneralSettingRequest $request, AuditLogger $audit): RedirectResponse
    {
        if (! Schema::hasTable('general_settings') || ! Schema::hasColumns('general_settings', ['enquiry_emails', 'enquiry_cc_emails', 'enquiry_bcc_emails'])) {
            return back()->with('error', 'Run the General Settings database migration before saving.');
        }

        DB::transaction(function () use ($request, $audit): void {
            $current = GeneralSetting::current();
            $before = $current->toArray();
            $values = $request->validated();
            $values['phone_numbers'] ??= [];
            $values['social_links'] = array_filter($values['social_links'] ?? [], fn ($url): bool => filled($url));
            $values['whatsapp_number'] ??= null;
            $values['enquiry_emails'] ??= $current->enquiry_emails ?? [];
            $values['enquiry_cc_emails'] ??= $current->enquiry_cc_emails ?? [];
            $values['enquiry_bcc_emails'] ??= $current->enquiry_bcc_emails ?? ['willie.samson@karossytravels.com'];
            $settings = GeneralSetting::query()->updateOrCreate(['id' => 1], $values);
            $audit->record('settings.general.updated', 'General settings and public social links updated.', $settings, $before, $settings->fresh()->toArray());
        });

        return redirect()->route('admin.settings.general.edit')->with('success', 'General settings saved. Website contact links, WhatsApp and enquiry email delivery are updated.');
    }
}

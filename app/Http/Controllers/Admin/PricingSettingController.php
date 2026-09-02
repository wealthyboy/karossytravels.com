<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePricingSettingRequest;
use App\Models\PricingSetting;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PricingSettingController extends Controller
{
    public function edit(string $product): View
    {
        abort_unless(in_array($product, ['airline', 'hotel'], true), 404);
        $setting = PricingSetting::firstOrCreate(['product_type' => $product], ['markup_type' => 'percentage', 'currency' => 'USD', 'enabled' => true]);

        return view('admin.pricing.edit', compact('setting', 'product'));
    }

    public function update(UpdatePricingSettingRequest $request, string $product, AuditLogger $audit): RedirectResponse
    {
        abort_unless(in_array($product, ['airline', 'hotel'], true), 404);
        $setting = PricingSetting::firstOrCreate(['product_type' => $product]);
        $before = $setting->toArray();
        $data = $request->validated();
        $data['enabled'] = $request->boolean('enabled');
        $data['markup_value'] = filled($data['markup_value'] ?? null) ? $data['markup_value'] : null;
        $setting->update($data);
        $audit->record('pricing.updated', ucfirst($product).' default markup updated.', $setting, $before, $setting->fresh()->toArray());

        return back()->with('success', ucfirst($product).' pricing updated successfully.');
    }
}

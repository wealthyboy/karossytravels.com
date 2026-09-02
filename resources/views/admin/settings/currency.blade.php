@extends('layouts.admin')

@section('title', 'Currency Settings')

@section('content')
<header class="mb-4 d-flex flex-wrap align-items-end justify-content-between gap-3"><div><p class="text-danger fw-semibold mb-1">SETTINGS</p><h1 class="h3 fw-bold mb-2">Currency and exchange rates</h1><p class="text-secondary mb-0">Live market rates are supplied by Achu Internal Exchange Rate. Configure a markup or markdown beside each currency to control the final customer conversion rate.</p></div><form method="POST" action="{{ route('admin.settings.currency.refresh') }}" data-loading-form>@csrf<button class="btn btn-outline-dark" type="submit"><i class="bi bi-arrow-clockwise"></i><span data-submit-label>Refresh rates</span><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span></button></form></header>

@if($liveRates->isEmpty())
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Achu Internal Exchange Rate is temporarily unavailable. Existing settings remain active and USD will be used as the safe fallback.</div>
@else
<form method="POST" action="{{ route('admin.settings.currency.update') }}" data-loading-form novalidate>@csrf @method('PUT')
    <div class="card content-card"><div class="card-body p-0">
        <div class="p-3 border-bottom"><div class="input-group currency-rate-search"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" type="search" placeholder="Search country or currency code" data-currency-rate-search></div></div>
        <div class="table-responsive"><table class="table align-middle mb-0 admin-data-table"><thead><tr><th>Country</th><th>Currency</th><th>Internal rate per USD</th><th>Adjustment</th><th>Markup / markdown</th><th>Customer rate</th><th>Enabled</th></tr></thead><tbody>
        @foreach($liveRates as $row)
            @php
                $code = $row['code']; $setting = $row['setting']; $isUsd = $code === 'USD';
                $type = old("currencies.$code.adjustment_type", $setting?->adjustment_type ?? 'none');
                $mode = old("currencies.$code.adjustment_mode", $setting?->adjustment_mode ?? 'percentage');
                $percent = old("currencies.$code.adjustment_percent", $setting?->adjustment_percent);
            @endphp
            <tr data-currency-rate-row data-code="{{ $code }} {{ strtoupper($row['country']) }}" data-live-rate="{{ $row['live_rate'] }}">
                <td><span class="fs-5 me-2" role="img" aria-label="{{ $row['country'] }} flag">{{ $row['flag'] }}</span><span>{{ $row['country'] }}</span></td>
                <td><strong>{{ $code }}</strong></td>
                <td><span class="font-monospace">{{ number_format($row['live_rate'], 6) }}</span></td>
                <td><select class="form-select form-select-sm" name="currencies[{{ $code }}][adjustment_type]" data-rate-direction @disabled($isUsd)><option value="none" @selected($type === 'none')>No adjustment</option><option value="markup" @selected($type === 'markup')>Markup</option><option value="markdown" @selected($type === 'markdown')>Markdown</option></select></td>
                <td><div class="d-flex gap-2"><select class="form-select form-select-sm" name="currencies[{{ $code }}][adjustment_mode]" data-rate-mode @disabled($isUsd)><option value="percentage" @selected($mode === 'percentage')>Percentage</option><option value="fixed" @selected($mode === 'fixed')>Fixed</option></select><div class="input-group input-group-sm"><input class="form-control" type="number" min="0" step="0.0001" name="currencies[{{ $code }}][adjustment_percent]" value="{{ $percent }}" placeholder="0.00" data-rate-value @disabled($isUsd)><span class="input-group-text" data-rate-unit>{{ $mode === 'fixed' ? $code : '%' }}</span></div></div></td>
                <td><strong data-customer-rate>{{ $row['effective_rate'] !== null ? number_format($row['effective_rate'], 6) : '—' }}</strong></td>
                <td><div class="form-check form-switch"><input type="hidden" name="currencies[{{ $code }}][enabled]" value="0"><input class="form-check-input" type="checkbox" name="currencies[{{ $code }}][enabled]" value="1" @checked($isUsd || ($setting?->enabled ?? false)) @disabled($isUsd)></div><input type="hidden" name="currencies[{{ $code }}][manual_rate]" value="{{ $isUsd ? 1 : '' }}">@if($isUsd)<input type="hidden" name="currencies[USD][adjustment_type]" value="none"><input type="hidden" name="currencies[USD][adjustment_mode]" value="percentage"><input type="hidden" name="currencies[USD][enabled]" value="1">@endif</td>
            </tr>
        @endforeach
        </tbody></table></div>
    </div></div>
    <div class="d-flex align-items-center justify-content-between mt-3"><small class="text-secondary">Market rates are synchronized every {{ config('travel.currency.cache_hours') }} hours by Achu Internal Exchange Rate.</small><button class="btn btn-karossy" type="submit"><span data-submit-label>Save rate adjustments</span><span class="spinner-border spinner-border-sm d-none" data-submit-spinner></span></button></div>
</form>
@endif
@endsection

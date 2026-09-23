@extends('layouts.admin')
@section('title', 'Hotel Settings')
@section('content')
<header class="mb-4">
    <p class="text-danger fw-semibold mb-1">SETTINGS</p>
    <h1 class="h3 fw-bold mb-2">Hotel settings</h1>
    <p class="text-secondary mb-0">Manage the masked card identity reserved for future Sabre hotel-booking integration.</p>
</header>

<section class="card content-card">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h5 mb-1">Hotel booking card</h2>
                <p class="text-secondary mb-0">This screen does not store or transmit a full card number or CVV.</p>
            </div>
            @if($settings->last_four)
                <span class="badge rounded-pill text-bg-success px-3 py-2"><i class="bi bi-credit-card me-2"></i>Card ending {{ $settings->last_four }}</span>
            @else
                <span class="badge rounded-pill text-bg-warning px-3 py-2">Not configured</span>
            @endif
        </div>

        @unless($canEdit)
            <div class="alert alert-info"><i class="bi bi-lock-fill me-2"></i>Only the owner can add or replace these hotel card settings.</div>
        @endunless

        <form method="POST" action="{{ route('admin.settings.hotel-booking.update') }}" autocomplete="off">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label">Cardholder name</label>
                    <input class="form-control" name="cardholder_name" value="" placeholder="{{ $settings->last_four ? 'Enter when replacing the card' : 'Name shown on card' }}" @disabled(!$canEdit)>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Card brand</label>
                    <select class="form-select" name="card_brand" @disabled(!$canEdit)><option value="">Select</option><option value="VI">Visa</option><option value="MC">Mastercard</option><option value="AX">American Express</option><option value="DS">Discover</option><option value="DC">Diners Club</option><option value="JC">JCB</option></select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Last four digits</label>
                    <input class="form-control" name="last_four" inputmode="numeric" maxlength="4" value="" placeholder="{{ $settings->last_four ?: '0000' }}" @disabled(!$canEdit)>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label">Expiry month</label>
                    <input class="form-control" type="number" name="expiry_month" min="1" max="12" placeholder="MM" @disabled(!$canEdit)>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label">Expiry year</label>
                    <input class="form-control" type="number" name="expiry_year" min="{{ now()->year }}" max="{{ now()->addYears(20)->year }}" placeholder="YYYY" @disabled(!$canEdit)>
                </div>
                <div class="col-lg-6">
                    <label class="form-label">Vault reference <span class="text-secondary">(optional)</span></label>
                    <input class="form-control" name="vault_reference" value="" placeholder="Add after a secure card vault is connected" @disabled(!$canEdit)>
                </div>
            </div>

            <div class="alert alert-light border mt-4 mb-0"><i class="bi bi-shield-lock-fill me-2"></i>Private metadata and the optional vault reference are encrypted with the application key. Only the final four digits are shown after saving.</div>
            @if($canEdit)<button class="btn btn-karossy mt-4" type="submit"><i class="bi bi-lock-fill me-2"></i>{{ $settings->last_four ? 'Replace card settings' : 'Save card settings' }}</button>@endif
        </form>
    </div>
</section>
@endsection

@extends('layouts.crm')

@section('title', 'Calls')

@section('content')
<div class="crm-page-heading crm-calls-heading">
    <div>
        <p class="crm-eyebrow">Call centre</p>
        <h1>Calls</h1>
        <p>Every customer conversation, in one focused workspace.</p>
    </div>
    <div class="crm-agent-controls">
        <button class="crm-agent-status" type="button"><span></span>Available <i class="bi bi-chevron-down"></i></button>
        <button class="crm-button" type="button"><i class="bi bi-headset"></i>Open softphone</button>
    </div>
</div>

<section class="crm-call-metrics" aria-label="Today’s call statistics">
    <article><span class="crm-call-metric-icon blue"><i class="bi bi-telephone-inbound"></i></span><div><small>Calls today</small><strong>0</strong><em>Start receiving calls</em></div></article>
    <article><span class="crm-call-metric-icon amber"><i class="bi bi-people"></i></span><div><small>Waiting in queue</small><strong>0</strong><em>All queues are clear</em></div></article>
    <article><span class="crm-call-metric-icon green"><i class="bi bi-telephone-forward"></i></span><div><small>Answered rate</small><strong>—</strong><em>Available after first calls</em></div></article>
    <article><span class="crm-call-metric-icon violet"><i class="bi bi-clock-history"></i></span><div><small>Average wait</small><strong>—</strong><em>Live queue measurement</em></div></article>
</section>

<section class="crm-calls-grid">
    <article class="crm-card crm-dial-card">
        <div class="crm-card-heading"><div><span class="crm-card-kicker">Quick call</span><h2>Dial a customer</h2></div><button class="crm-card-icon-button" type="button" title="Recent contacts"><i class="bi bi-clock"></i></button></div>
        <div class="crm-dial-search"><i class="bi bi-search"></i><input data-call-number type="tel" inputmode="tel" placeholder="Name or phone number" aria-label="Name or phone number"><button type="button" data-call-clear aria-label="Clear number"><i class="bi bi-x-lg"></i></button></div>
        <div class="crm-dial-pad" data-call-dial-pad>
            @foreach(['1', '2 ABC', '3 DEF', '4 GHI', '5 JKL', '6 MNO', '7 PQRS', '8 TUV', '9 WXYZ', '*', '0 +', '#'] as $key)
                @php([$number, $letters] = array_pad(explode(' ', $key, 2), 2, ''))
                <button type="button" data-call-key="{{ $number }}"><strong>{{ $number }}</strong>@if($letters)<small>{{ $letters }}</small>@endif</button>
            @endforeach
        </div>
        <button class="crm-dial-call" type="button" data-call-start><i class="bi bi-telephone-fill"></i><span>Call</span></button>
        <p class="crm-dial-hint"><i class="bi bi-shield-check"></i> Calls will connect when your Karossy call service is enabled.</p>
    </article>

    <div class="crm-call-central">
        <article class="crm-card crm-live-call-card">
            <div class="crm-live-call-visual"><div class="crm-live-call-wave"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><span class="crm-live-call-ring"><i class="bi bi-telephone"></i></span></div>
            <div class="crm-live-call-copy"><span class="crm-card-kicker">Live call</span><h2>Your line is ready</h2><p>Set your status to Available to receive calls from your assigned queues.</p><button type="button" class="crm-button crm-button-quiet"><i class="bi bi-headset"></i>Set up calling</button></div>
        </article>
        <article class="crm-card crm-queue-card">
            <div class="crm-card-heading"><div><span class="crm-card-kicker">Queues</span><h2>Live queue activity</h2></div><button type="button" class="crm-text-button">Manage queues <i class="bi bi-arrow-up-right"></i></button></div>
            <div class="crm-queue-list">
                @foreach([['Sales enquiries', 'Sales', 'bi-graph-up-arrow', '0 waiting', 'blue'], ['Booking support', 'Operations', 'bi-calendar2-check', '0 waiting', 'green'], ['Customer care', 'Support', 'bi-heart', '0 waiting', 'violet']] as [$queue, $team, $icon, $status, $tone])
                    <div class="crm-queue-row"><span class="crm-queue-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span><div><strong>{{ $queue }}</strong><small>{{ $team }} queue</small></div><span class="crm-queue-status"><i></i>{{ $status }}</span><button type="button" aria-label="Open {{ $queue }}"><i class="bi bi-chevron-right"></i></button></div>
                @endforeach
            </div>
        </article>
    </div>

    <article class="crm-card crm-agent-card">
        <div class="crm-card-heading"><div><span class="crm-card-kicker">Your shift</span><h2>Agent workspace</h2></div><span class="crm-agent-live"><i></i>Online</span></div>
        <div class="crm-agent-profile"><div class="crm-agent-avatar">{{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}</div><div><strong>{{ auth()->user()?->name ?? 'Agent' }}</strong><small>Extension not assigned</small></div></div>
        <div class="crm-agent-options"><button class="is-selected" type="button"><i class="bi bi-check-circle-fill"></i>Available</button><button type="button"><i class="bi bi-pause-circle"></i>Away</button><button type="button"><i class="bi bi-moon-stars"></i>Do not disturb</button></div>
        <div class="crm-agent-note"><i class="bi bi-info-circle"></i><p>When telephony is connected, your extension and active queues will appear here.</p></div>
    </article>
</section>

<section class="crm-card crm-call-history-card">
    <div class="crm-card-heading"><div><span class="crm-card-kicker">Call log</span><h2>Recent calls</h2></div><div class="crm-history-actions"><button type="button"><i class="bi bi-funnel"></i>Filter</button><a href="#" class="crm-text-button">View all <i class="bi bi-arrow-right"></i></a></div></div>
    <div class="crm-call-empty"><span><i class="bi bi-telephone"></i></span><div><strong>No calls yet</strong><p>Inbound, outbound and missed calls will automatically appear here with their customer details, outcome and follow-up.</p></div></div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-call-dial-pad]').forEach(pad => {
    const input = document.querySelector('[data-call-number]');
    pad.addEventListener('click', event => {
        const key = event.target.closest('[data-call-key]');
        if (!key || !input) return;
        input.value += key.dataset.callKey;
        input.focus();
    });
});
document.querySelector('[data-call-clear]')?.addEventListener('click', () => {
    const input = document.querySelector('[data-call-number]');
    if (input) input.value = '';
});
</script>
@endpush

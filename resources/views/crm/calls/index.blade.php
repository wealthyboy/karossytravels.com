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
        <form method="POST" action="{{ route('crm.calls.availability') }}">@csrf @method('PUT')<label class="visually-hidden" for="agent-status">Agent status</label><select id="agent-status" class="crm-agent-status" name="status" onchange="this.form.submit()">@foreach(['available' => 'Available', 'away' => 'Away', 'dnd' => 'Do not disturb', 'offline' => 'Offline'] as $value => $label)<option value="{{ $value }}" @selected($extension->status === $value)>{{ $label }}</option>@endforeach</select></form>
        <button class="crm-button" type="button"><i class="bi bi-headset"></i>Open softphone</button>
    </div>
</div>

<section class="crm-call-metrics" aria-label="Today’s call statistics">
    <article><span class="crm-call-metric-icon blue"><i class="bi bi-telephone-inbound"></i></span><div><small>Calls today</small><strong>{{ $todayCallCount }}</strong><em>{{ $todayCallCount ? 'Recorded call activity' : 'Start by logging a call' }}</em></div></article>
    <article><span class="crm-call-metric-icon amber"><i class="bi bi-people"></i></span><div><small>Waiting in queue</small><strong>{{ $waitingCount }}</strong><em>{{ $waitingCount ? 'Calls need an agent' : 'All queues are clear' }}</em></div></article>
    <article><span class="crm-call-metric-icon green"><i class="bi bi-telephone-forward"></i></span><div><small>Answered rate</small><strong>{{ $answeredRate === null ? '—' : $answeredRate.'%' }}</strong><em>Completed and answered calls</em></div></article>
    <article><span class="crm-call-metric-icon violet"><i class="bi bi-clock-history"></i></span><div><small>Average duration</small><strong>{{ $averageWait ? gmdate('i:s', $averageWait) : '—' }}</strong><em>Available after call records</em></div></article>
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
                @foreach($queues as $queue)
                    <div class="crm-queue-row"><span class="crm-queue-icon {{ ['sales-enquiries' => 'blue', 'booking-support' => 'green', 'customer-care' => 'violet'][$queue->slug] ?? 'blue' }}"><i class="bi {{ $queue->slug === 'booking-support' ? 'bi-calendar2-check' : ($queue->slug === 'customer-care' ? 'bi-heart' : 'bi-graph-up-arrow') }}"></i></span><div><strong>{{ $queue->name }}</strong><small>{{ $queue->department }} queue</small></div><span class="crm-queue-status"><i></i>{{ $queue->waiting_calls_count }} waiting</span><button type="button" aria-label="Open {{ $queue->name }}"><i class="bi bi-chevron-right"></i></button></div>
                @endforeach
            </div>
        </article>
    </div>

    <article class="crm-card crm-agent-card">
        <div class="crm-card-heading"><div><span class="crm-card-kicker">Your shift</span><h2>Agent workspace</h2></div><span class="crm-agent-live"><i></i>Online</span></div>
        <div class="crm-agent-profile"><div class="crm-agent-avatar">{{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}</div><div><strong>{{ auth()->user()?->name ?? 'Agent' }}</strong><small>Extension {{ $extension->extension }}</small></div></div>
        <div class="crm-agent-options"><button class="is-selected" type="button"><i class="bi bi-check-circle-fill"></i>{{ ucfirst(str_replace('dnd', 'do not disturb', $extension->status)) }}</button></div>
        <div class="crm-agent-note"><i class="bi bi-info-circle"></i><p>Your extension and availability are saved. Calls will connect after Karossy’s telephony service is enabled.</p></div>
    </article>
</section>

<section class="crm-card crm-call-history-card">
    <div class="crm-card-heading"><div><span class="crm-card-kicker">Call log</span><h2>Recent calls</h2></div><div class="crm-history-actions"><button type="button"><i class="bi bi-funnel"></i>Filter</button><a href="#" class="crm-text-button">View all <i class="bi bi-arrow-right"></i></a></div></div>
    @forelse($recentCalls as $call)<div class="crm-call-log-row"><span class="crm-queue-icon {{ $call->direction === 'inbound' ? 'green' : 'blue' }}"><i class="bi {{ $call->direction === 'inbound' ? 'bi-telephone-inbound' : 'bi-telephone-outbound' }}"></i></span><div><strong>{{ $call->customer?->full_name ?: $call->caller_name ?: $call->phone_number }}</strong><small>{{ ucfirst($call->direction) }} · {{ $call->outcome ?: 'No outcome' }} · {{ $call->started_at?->diffForHumans() }}</small></div><span class="crm-status completed">{{ ucfirst(str_replace('_', ' ', $call->outcome ?: $call->status)) }}</span></div>@empty<div class="crm-call-empty"><span><i class="bi bi-telephone"></i></span><div><strong>No calls yet</strong><p>Log a completed call below. Later, inbound, outbound and missed calls will appear automatically when telephony is connected.</p></div></div>@endforelse
</section>

<section class="crm-card crm-call-log-form-card"><div class="crm-card-heading"><div><span class="crm-card-kicker">Call record</span><h2>Log a customer call</h2></div></div><form method="POST" action="{{ route('crm.calls.store') }}" class="crm-call-log-form">@csrf <label>Direction<select name="direction"><option value="outbound">Outbound</option><option value="inbound">Inbound</option></select></label><label>Phone number<input required name="phone_number" type="tel" placeholder="+234…"></label><label>Customer (optional)<select name="customer_id"><option value="">Not linked to a customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->full_name }} · {{ $customer->email }}</option>@endforeach</select></label><label>Outcome<select name="outcome">@foreach(['answered' => 'Answered', 'follow_up' => 'Follow-up needed', 'voicemail' => 'Voicemail', 'no_answer' => 'No answer', 'wrong_number' => 'Wrong number', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><label class="crm-call-log-notes">Notes<textarea name="notes" rows="2" placeholder="What happened in this call?"></textarea></label><button type="submit" class="crm-button"><i class="bi bi-plus-lg"></i> Save call record</button></form></section>
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

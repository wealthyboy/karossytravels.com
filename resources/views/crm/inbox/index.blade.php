@extends('layouts.crm')

@section('title', 'Inbox')
@section('topbar-title', 'Inbox')

@section('content')
<div class="crm-page-heading">
    <div><p class="crm-eyebrow">Communications</p><h1>Central inbox</h1><p>Customer messages from every connected channel, with the booking and staff context beside each conversation.</p></div>
    <button class="crm-button" type="button" disabled title="Connect Zepto or Zoho Mail in CRM settings before sending"><i class="bi bi-send"></i> New message</button>
</div>

<section class="crm-call-metrics" aria-label="Inbox summary">
    <article><span class="crm-call-metric-icon blue"><i class="bi bi-chat-square-text"></i></span><div><small>Open conversations</small><strong>{{ $summary['open'] }}</strong><em>Awaiting a staff response</em></div></article>
    <article><span class="crm-call-metric-icon amber"><i class="bi bi-hourglass-split"></i></span><div><small>Pending follow-up</small><strong>{{ $summary['pending'] }}</strong><em>Being handled by the team</em></div></article>
    <article><span class="crm-call-metric-icon violet"><i class="bi bi-person-exclamation"></i></span><div><small>Needs assignment</small><strong>{{ $summary['unassigned'] }}</strong><em>Route these to an agent</em></div></article>
</section>

<section class="crm-card crm-call-history-card">
    <div class="crm-card-heading"><div><span class="crm-card-kicker">Unified conversations</span><h2>All customer messages</h2></div><span class="crm-agent-note"><i class="bi bi-shield-check"></i> Provider webhooks will enter here</span></div>
    <form method="GET" class="crm-history-actions" style="padding: 0 0 1rem;">
        <label class="visually-hidden" for="inbox-channel">Channel</label><select id="inbox-channel" name="channel"><option value="">All channels</option>@foreach(['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'website' => 'Website', 'in_app' => 'In-app'] as $value => $label)<option value="{{ $value }}" @selected(request('channel') === $value)>{{ $label }}</option>@endforeach</select>
        <label class="visually-hidden" for="inbox-status">Status</label><select id="inbox-status" name="status"><option value="">All statuses</option>@foreach(['open' => 'Open', 'pending' => 'Pending', 'closed' => 'Closed'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <button class="crm-button crm-button-quiet" type="submit">Filter</button>
    </form>
    @forelse($conversations as $conversation)
        @php($message = $conversation->messages->first())
        <div class="crm-call-log-row">
            <span class="crm-queue-icon {{ in_array($conversation->channel, ['email', 'website']) ? 'blue' : ($conversation->channel === 'whatsapp' ? 'green' : 'violet') }}"><i class="bi {{ ['email' => 'bi-envelope', 'sms' => 'bi-chat-text', 'whatsapp' => 'bi-whatsapp', 'website' => 'bi-globe2', 'in_app' => 'bi-app-indicator'][$conversation->channel] ?? 'bi-chat-dots' }}"></i></span>
            <div><strong>{{ $conversation->customer?->full_name ?? 'Unmatched customer' }}</strong><small>{{ $conversation->subject ?: ($message?->body ? \Illuminate\Support\Str::limit($message->body, 100) : 'No message preview yet') }}</small></div>
            <small>{{ $conversation->booking?->order?->reference ? 'Booking '.$conversation->booking->order->reference : 'No booking linked' }}</small>
            <small>{{ $conversation->assignee?->name ?? 'Unassigned' }}</small>
            <span class="crm-status {{ $conversation->status === 'closed' ? 'completed' : 'pending' }}">{{ ucfirst($conversation->status) }}</span>
        </div>
    @empty
        <div class="crm-call-empty"><span><i class="bi bi-inboxes"></i></span><div><strong>Your central inbox is ready</strong><p>No messages have been received yet. Zoho Mail, Zepto, website forms, WhatsApp, and SMS will create customer-linked conversations here once their integrations are connected.</p></div></div>
    @endforelse
    <div style="padding-top: 1rem;">{{ $conversations->links() }}</div>
</section>
@endsection

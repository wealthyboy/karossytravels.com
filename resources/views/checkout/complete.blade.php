@extends('layouts.public')

@section('title', 'Booking confirmed')

@section('content')
@include('checkout._confirmation', ['order' => $order, 'booking' => $booking])
@endsection

@push('scripts')
<script>
document.querySelector('[data-copy-reference]')?.addEventListener('click', async event => {
    const value = document.querySelector('[data-copy-value]')?.textContent.trim();
    if (!value) return;
    await navigator.clipboard.writeText(value);
    event.currentTarget.innerHTML = '<i class="bi bi-check-lg"></i><span>Copied</span>';
});
</script>
@endpush

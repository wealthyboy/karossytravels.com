@extends('layouts.public')
@section('title', 'Payment received')
@section('content')
<section class="booking-page"><div class="container public-container"><div class="booking-heading"><span class="public-eyebrow">Payment received</span><h1>Thank you — your payment is recorded.</h1><p>Reference <strong>{{ $order->reference }}</strong></p></div><div class="booking-card"><div class="alert alert-success">Your payment receipt has been sent to the email address on the invoice.</div><p>Our operations team will now revalidate the fare and complete the supplier booking. We will send your flight confirmation separately after that step is complete.</p></div></div></section>
@endsection

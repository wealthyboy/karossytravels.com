@extends('layouts.public')
@section('content')
<section class="enquiry-hero" aria-labelledby="enquiry-title"><div class="container enquiry-container">
    <span class="public-eyebrow">Karossy Travels &amp; Tours</span>
    <h1 id="enquiry-title">@yield('enquiry_heading')</h1>
    <p class="enquiry-lead">@yield('enquiry_lead')</p>
    <p>@yield('enquiry_intro')</p>
</div></section>
<section class="enquiry-section"><div class="container enquiry-container">@yield('enquiry_form')</div></section>
@endsection

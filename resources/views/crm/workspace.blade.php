@extends('layouts.crm')
@section('title', $module['label'])
@section('content')
<div class="crm-workspace-hero"><div class="crm-workspace-icon"><i class="bi {{ $module['icon'] }}"></i></div><div><p class="crm-eyebrow">{{ $module['eyebrow'] }}</p><h1>{{ $module['label'] }}</h1><p>{{ $module['description'] }}</p></div></div>
<section class="crm-card crm-ready-card"><div class="crm-ready-orbit"><span></span><i class="bi {{ $module['icon'] }}"></i></div><div><span class="crm-card-kicker">CRM module in progress</span><h2>{{ $module['label'] }} is being moved into this workspace.</h2><p>The foundation is ready. The next delivery adds its records, automation rules and API connections without interrupting the existing Karossy operations portal.</p><div class="crm-ready-list"><span><i class="bi bi-check2"></i> Shared team access</span><span><i class="bi bi-check2"></i> Responsive CRM design</span><span><i class="bi bi-check2"></i> Connected travel data</span></div></div></section>
@endsection

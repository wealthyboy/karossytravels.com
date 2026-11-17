<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM') · Karossy Travels</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/css/crm.css', 'resources/css/crm-calls.css', 'resources/css/crm-customer.css', 'resources/css/crm-operations.css', 'resources/js/app.js'])
</head>
<body class="crm-body">
@php
    $route = request()->route()?->getName() ?? '';
    $workspace = request()->route('module');
    $navigation = [
        ['label' => 'Overview', 'icon' => 'bi-grid-1x2', 'route' => 'crm.dashboard'],
        ['label' => 'Bookings', 'icon' => 'bi-calendar2-check', 'route' => 'crm.bookings.index'],
        ['label' => 'Sales', 'icon' => 'bi-kanban', 'module' => 'sales'],
        ['label' => 'Customers', 'icon' => 'bi-people', 'route' => 'crm.customers.index'],
        ['label' => 'Activities', 'icon' => 'bi-check2-square', 'module' => 'activities'],
        ['label' => 'Notifications', 'icon' => 'bi-bell', 'module' => 'notifications'],
        ['label' => 'Promotions', 'icon' => 'bi-megaphone', 'module' => 'promotions'],
        ['label' => 'Calls', 'icon' => 'bi-telephone', 'module' => 'calls'],
        ['label' => 'Analytics', 'icon' => 'bi-graph-up-arrow', 'module' => 'analytics'],
        ['label' => 'HR', 'icon' => 'bi-person-workspace', 'module' => 'hr'],
    ];
@endphp
<div class="crm-shell">
    <aside class="crm-sidebar" id="crmSidebar">
        <a class="crm-brand" href="{{ route('crm.dashboard') }}"><img src="{{ asset('favicon.png') }}" alt="Karossy"><span><strong>Karossy</strong><small>Travel CRM</small></span><i class="bi bi-layout-sidebar-inset crm-sidebar-collapse"></i></a>
        <div class="crm-workspace-pill"><span class="crm-pulse"></span><span><small>Active workspace</small><strong>Karossy Operations</strong></span><i class="bi bi-chevron-down"></i></div>
        <nav class="crm-nav" aria-label="CRM navigation">
            <span class="crm-nav-label">Workspace</span>
            @foreach($navigation as $item)
                @php($active = isset($item['route']) ? $route === $item['route'] : $workspace === $item['module'])
                <a class="{{ $active ? 'is-active' : '' }}" href="{{ isset($item['route']) ? route($item['route']) : route('crm.workspace', $item['module']) }}"><i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>@if($item['label'] === 'Activities')<b>New</b>@endif</a>
            @endforeach
            <span class="crm-nav-label crm-nav-label-bottom">System</span>
            <a class="{{ $workspace === 'settings' ? 'is-active' : '' }}" href="{{ route('crm.workspace', 'settings') }}"><i class="bi bi-sliders"></i><span>Settings</span></a>
        </nav>
        <div class="crm-sidebar-footer"><div class="crm-avatar">{{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}</div><div><strong>{{ auth()->user()?->name ?? 'Administrator' }}</strong><small>{{ auth()->user()?->isOwner() ? 'Owner access' : 'Team member' }}</small></div><form action="{{ route('logout') }}" method="post">@csrf<button title="Sign out" aria-label="Sign out"><i class="bi bi-box-arrow-right"></i></button></form></div>
    </aside>
    <main class="crm-main">
        <header class="crm-topbar"><button class="crm-menu-button" type="button" data-crm-menu aria-label="Toggle menu"><i class="bi bi-list"></i></button><h2 class="crm-topbar-title">@yield('topbar-title', 'Travel CRM')</h2><div class="crm-search"><i class="bi bi-search"></i><input type="search" placeholder="Search here…" aria-label="Search CRM"><kbd>⌘ K</kbd></div><div class="crm-topbar-actions"><button class="crm-topbar-utility" type="button" aria-label="Apps"><i class="bi bi-grid-3x3-gap"></i></button><button class="crm-icon-button" type="button" aria-label="Notifications"><i class="bi bi-bell"></i><span></span></button><button class="crm-topbar-utility" type="button" aria-label="Help"><i class="bi bi-question-circle"></i></button><a class="crm-public-link" href="{{ route('home') }}" title="Open website"><i class="bi bi-box-arrow-up-right"></i><span>Website</span></a></div></header>
        <div class="crm-content">
            @if(session('success'))<div class="crm-alert crm-alert-success"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>@endif
            @if(session('error'))<div class="crm-alert crm-alert-error"><i class="bi bi-exclamation-circle-fill"></i>{{ session('error') }}</div>@endif
            @yield('content')
        </div>
    </main>
</div>
<script>document.querySelector('[data-crm-menu]')?.addEventListener('click',()=>document.body.classList.toggle('crm-menu-open'));</script>
@stack('scripts')
</body>
</html>

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
    $navigationGroups = [
        ['label' => 'Operations', 'icon' => 'bi-calendar2-check', 'items' => [
            ['label' => 'Bookings', 'icon' => 'bi-calendar2-check', 'route' => 'crm.bookings.index'],
        ]],
        ['label' => 'Customer & Sales', 'icon' => 'bi-people', 'items' => [
            ['label' => 'Customers', 'icon' => 'bi-people', 'route' => 'crm.customers.index'],
            ['label' => 'Sales', 'icon' => 'bi-kanban', 'module' => 'sales'],
            ['label' => 'Activities', 'icon' => 'bi-check2-square', 'module' => 'activities'],
        ]],
        ['label' => 'Communications', 'icon' => 'bi-chat-dots', 'items' => [
            ['label' => 'Calls', 'icon' => 'bi-telephone', 'route' => 'crm.calls.index'],
            ['label' => 'Notifications', 'icon' => 'bi-bell', 'module' => 'notifications'],
            ['label' => 'Promotions', 'icon' => 'bi-megaphone', 'module' => 'promotions'],
        ]],
        ['label' => 'Intelligence', 'icon' => 'bi-bar-chart-line', 'items' => [
            ['label' => 'Analytics', 'icon' => 'bi-graph-up-arrow', 'module' => 'analytics'],
        ]],
        ['label' => 'People & System', 'icon' => 'bi-gear', 'items' => [
            ['label' => 'HR', 'icon' => 'bi-person-workspace', 'module' => 'hr'],
            ['label' => 'Settings', 'icon' => 'bi-sliders', 'module' => 'settings'],
        ]],
    ];
@endphp
<div class="crm-shell">
    <aside class="crm-sidebar" id="crmSidebar">
        <a class="crm-brand" href="{{ route('crm.dashboard') }}"><img src="{{ asset('favicon.png') }}" alt="Karossy"><span><strong>Karossy</strong><small>Travel CRM</small></span><i class="bi bi-layout-sidebar-inset crm-sidebar-collapse"></i></a>
        <div class="crm-workspace-pill"><span class="crm-pulse"></span><span><small>Active workspace</small><strong>Karossy Operations</strong></span><i class="bi bi-chevron-down"></i></div>
        <nav class="crm-nav" aria-label="CRM navigation">
            <span class="crm-nav-label">Workspace</span>
            <a class="{{ $route === 'crm.dashboard' ? 'is-active' : '' }}" href="{{ route('crm.dashboard') }}"><i class="bi bi-grid-1x2"></i><span>Overview</span></a>
            @foreach($navigationGroups as $group)
                @php($groupActive = collect($group['items'])->contains(fn (array $item): bool => isset($item['route']) ? $route === $item['route'] : $workspace === $item['module']))
                <section class="crm-nav-group {{ $groupActive ? 'is-open' : '' }}" data-crm-nav-group>
                    <button type="button" class="crm-nav-group-toggle" data-crm-nav-toggle aria-expanded="{{ $groupActive ? 'true' : 'false' }}"><i class="bi {{ $group['icon'] }}"></i><span>{{ $group['label'] }}</span><i class="bi bi-chevron-down crm-nav-chevron"></i></button>
                    <div class="crm-nav-group-items">
                        @foreach($group['items'] as $item)
                            @php($active = isset($item['route']) ? $route === $item['route'] : $workspace === $item['module'])
                            <a class="{{ $active ? 'is-active' : '' }}" href="{{ isset($item['route']) ? route($item['route']) : route('crm.workspace', $item['module']) }}"><i class="bi {{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>@if($item['label'] === 'Activities')<b>New</b>@endif</a>
                        @endforeach
                    </div>
                </section>
            @endforeach
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
<script>document.querySelector('[data-crm-menu]')?.addEventListener('click',()=>document.body.classList.toggle('crm-menu-open'));document.querySelectorAll('[data-crm-nav-toggle]').forEach((toggle)=>toggle.addEventListener('click',()=>{const group=toggle.closest('[data-crm-nav-group]');const open=group.classList.toggle('is-open');toggle.setAttribute('aria-expanded',open?'true':'false')}));</script>
@stack('scripts')
</body>
</html>

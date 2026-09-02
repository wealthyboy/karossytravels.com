<?php

return [
    ['label' => 'Dashboard', 'slug' => 'dashboard', 'icon' => 'bi-speedometer2', 'permission' => 'dashboard.view', 'route' => 'admin.dashboard'],
    ['label' => 'Flights', 'slug' => 'flights', 'icon' => 'bi-airplane-fill', 'permission' => 'bookings.view', 'items' => [
        ['label' => 'Search Flights', 'slug' => 'search', 'route' => 'admin.flights.search', 'active' => 'admin.flights.*'],
        ['label' => 'Homepage Offers', 'slug' => 'homepage-offers', 'route' => 'admin.flight-offers.index', 'active' => 'admin.flight-offers.*', 'permission' => 'offers.manage'],
        ['label' => 'Fare Rules', 'slug' => 'fare-rules', 'route' => 'admin.fair-rules.index', 'active' => 'admin.fair-rules.*', 'permission' => 'bookings.manage'],
        ['label' => 'Add-ons', 'slug' => 'addons', 'route' => 'admin.addons.index', 'active' => 'admin.addons.*', 'route_parameters' => ['type' => 'flight'], 'permission' => 'offers.manage'],
        ['label' => 'Operational Logs', 'slug' => 'logs', 'route' => 'admin.travel-logs.index', 'active' => 'admin.travel-logs.*', 'route_parameters' => ['product' => 'flight'], 'permission' => 'integrations.view'],
    ]],
    ['label' => 'Hotels', 'slug' => 'hotels', 'icon' => 'bi-building-fill', 'permission' => 'bookings.view', 'items' => [
        ['label' => 'Search Hotels', 'slug' => 'search', 'route' => 'admin.hotels.search', 'active' => 'admin.hotels.*'],
        ['label' => 'Add-ons', 'slug' => 'addons', 'route' => 'admin.addons.index', 'active' => 'admin.addons.*', 'route_parameters' => ['type' => 'hotel'], 'permission' => 'offers.manage'],
        ['label' => 'Operational Logs', 'slug' => 'logs', 'route' => 'admin.travel-logs.index', 'active' => 'admin.travel-logs.*', 'route_parameters' => ['product' => 'hotel'], 'permission' => 'integrations.view'],
    ]],
    ['label' => 'Visas', 'slug' => 'visas', 'icon' => 'bi-ticket-perforated-fill', 'permission' => 'services.view', 'items' => [
        ['label' => 'Visa Services', 'slug' => 'services', 'route' => 'admin.visas.index', 'active' => 'admin.visas.*'],
        ['label' => 'Applications', 'slug' => 'applications', 'route' => 'admin.visa-applications.index', 'active' => 'admin.visa-applications.*'],
        ['label' => 'Operational Logs', 'slug' => 'logs', 'route' => 'admin.travel-logs.index', 'route_parameters' => ['product' => 'visa'], 'permission' => 'integrations.view'],
    ]],
    ['label' => 'Car Hire', 'slug' => 'car-hire', 'icon' => 'bi-car-front-fill', 'permission' => 'services.view', 'items' => [
        ['label' => 'Partner enquiries', 'slug' => 'partners', 'route' => 'admin.partner-enquiries.index', 'active' => 'admin.partner-enquiries.*'], ['label' => 'Operational Logs', 'slug' => 'logs', 'route' => 'admin.travel-logs.index', 'route_parameters' => ['product' => 'car'], 'permission' => 'integrations.view'],
    ]],
    ['label' => 'Holiday Packages', 'slug' => 'holidays', 'icon' => 'bi-sun-fill', 'permission' => 'services.manage', 'route' => 'admin.holidays.index', 'active' => 'admin.holidays.*'],
    ['label' => 'Customers', 'slug' => 'customers', 'icon' => 'bi-people-fill', 'permission' => 'customers.view', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*'],
    ['label' => 'Bookings', 'slug' => 'bookings', 'icon' => 'bi-receipt-cutoff', 'permission' => 'bookings.view', 'description' => 'A unified view of every booking.', 'items' => [
        ['label' => 'All Bookings', 'slug' => 'all', 'route' => 'admin.bookings.index'],
        ['label' => 'Flight Bookings', 'slug' => 'flights', 'route' => 'admin.bookings.flights'],
        ['label' => 'Hotel Bookings', 'slug' => 'hotels', 'route' => 'admin.bookings.hotels'],
        ['label' => 'Visa Bookings', 'slug' => 'visas', 'route' => 'admin.bookings.visas'],
        ['label' => 'Source Attribution', 'slug' => 'sources'],
    ]],
    ['label' => 'Analytics', 'slug' => 'analytics', 'icon' => 'bi-graph-up-arrow', 'permission' => 'analytics.view', 'items' => [
        ['label' => 'Executive Overview', 'slug' => 'overview', 'route' => 'admin.analytics.index'],
        ['label' => 'Revenue & Profit', 'slug' => 'revenue-profit'],
        ['label' => 'Booking Performance', 'slug' => 'bookings'],
        ['label' => 'Search & Conversion', 'slug' => 'search-conversion'],
        ['label' => 'Routes & Destinations', 'slug' => 'routes-destinations'],
        ['label' => 'Airline Performance', 'slug' => 'airlines'],
        ['label' => 'Hotel Performance', 'slug' => 'hotels'],
        ['label' => 'Customer Retention', 'slug' => 'customers'],
        ['label' => 'Channel Attribution', 'slug' => 'channels'],
        ['label' => 'API Health', 'slug' => 'api-health'],
        ['label' => 'Ticketing Operations', 'slug' => 'ticketing'],
        ['label' => 'Event Stream', 'slug' => 'events', 'route' => 'admin.analytics.events.index', 'active' => 'admin.analytics.events.*'],
    ]],
    ['label' => 'Providers', 'slug' => 'providers', 'icon' => 'bi-globe2', 'permission' => 'integrations.view', 'items' => [
        ['label' => 'Travel supplier', 'slug' => 'sabre', 'route' => 'admin.providers.sabre', 'active' => 'admin.providers.*'],
        ['label' => 'API Logs', 'slug' => 'api-logs', 'route' => 'admin.travel-logs.index', 'active' => 'admin.travel-logs.*', 'route_parameters' => ['product' => 'all']],
    ]],
    ['label' => 'Pricing', 'slug' => 'pricing', 'icon' => 'bi-cash-coin', 'permission' => 'offers.manage', 'items' => [
        ['label' => 'Airline Markups', 'slug' => 'airline-markups', 'route' => 'admin.pricing.edit', 'route_parameters' => ['product' => 'airline']], ['label' => 'Hotel Markups', 'slug' => 'hotel-markups', 'route' => 'admin.pricing.edit', 'route_parameters' => ['product' => 'hotel']],
    ]],
    ['label' => 'Content', 'slug' => 'content', 'icon' => 'bi-layout-text-window-reverse', 'permission' => 'services.manage', 'items' => [
        ['label' => 'Homepage', 'slug' => 'homepage'], ['label' => 'Destinations', 'slug' => 'destinations'], ['label' => 'Travel Guides', 'slug' => 'travel-guides'], ['label' => 'Blog', 'slug' => 'blog'], ['label' => 'Banners', 'slug' => 'banners'], ['label' => 'FAQs', 'slug' => 'faqs'], ['label' => 'Testimonials', 'slug' => 'testimonials'],
    ]],
    ['label' => 'Marketing', 'slug' => 'marketing', 'icon' => 'bi-megaphone-fill', 'permission' => 'offers.manage', 'items' => [
        ['label' => 'Coupons', 'slug' => 'coupons'], ['label' => 'Push Notifications', 'slug' => 'push-notifications'], ['label' => 'Abandoned Bookings', 'slug' => 'abandoned-bookings'],
    ]],
    ['label' => 'Users', 'slug' => 'users', 'icon' => 'bi-person-badge-fill', 'permission' => 'team.manage', 'items' => [
        ['label' => 'Accounts', 'slug' => 'accounts', 'route' => 'admin.users.index', 'active' => 'admin.users.*'], ['label' => 'Roles', 'slug' => 'roles', 'route' => 'admin.roles.index', 'active' => 'admin.roles.*'], ['label' => 'Permissions', 'slug' => 'permissions', 'route' => 'admin.permissions.index', 'active' => 'admin.permissions.*'], ['label' => 'Audit Logs', 'slug' => 'audit-logs'],
    ]],
    ['label' => 'Settings', 'slug' => 'settings', 'icon' => 'bi-gear-fill', 'permission' => 'settings.manage', 'items' => [
        ['label' => 'Company', 'slug' => 'company'], ['label' => 'General', 'slug' => 'general'], ['label' => 'Payment Gateway', 'slug' => 'payment-gateway'], ['label' => 'Currency', 'slug' => 'currency', 'route' => 'admin.settings.currency.edit'], ['label' => 'Taxes', 'slug' => 'taxes'], ['label' => 'Languages', 'slug' => 'languages'], ['label' => 'Email', 'slug' => 'email'], ['label' => 'SMS', 'slug' => 'sms'], ['label' => 'API Credentials', 'slug' => 'api-credentials'],
    ]],
    // Logs removed per recent decision
];

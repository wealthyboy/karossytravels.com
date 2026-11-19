<?php

return [
    // Leave empty locally to use /crm. Set this in production to serve the
    // workspace from its own hostname, e.g. crm.karossytravels.online.
    'domain' => env('CRM_DOMAIN', env('CRM_HOST')),

    'modules' => [
        'sales' => ['label' => 'Sales', 'eyebrow' => 'Revenue workspace', 'icon' => 'bi-kanban', 'description' => 'Manage leads, deal stages, quotes, invoices, payments and commissions in one pipeline.'],
        'activities' => ['label' => 'Activities', 'eyebrow' => 'Customer timeline', 'icon' => 'bi-check2-square', 'description' => 'Capture notes, tasks, meetings, follow-ups, emails and internal comments against every customer and booking.'],
        'notifications' => ['label' => 'Notifications', 'eyebrow' => 'Communication centre', 'icon' => 'bi-bell', 'description' => 'Coordinate email, SMS, WhatsApp and in-app updates using reusable templates and event triggers.'],
        'promotions' => ['label' => 'Promotions', 'eyebrow' => 'Campaign performance', 'icon' => 'bi-megaphone', 'description' => 'Create campaigns, discount codes, referral programmes and audiences, then measure redemption and ROI.'],
        'calls' => ['label' => 'Calls', 'eyebrow' => 'Conversation log', 'icon' => 'bi-telephone', 'description' => 'Record calls, outcomes, recordings or links, and turn each conversation into a follow-up task.'],
        'analytics' => ['label' => 'Analytics', 'eyebrow' => 'Executive intelligence', 'icon' => 'bi-graph-up-arrow', 'description' => 'Bring together revenue, bookings, lead sources, agent performance, cancellations and campaign performance.'],
        'hr' => ['label' => 'HR', 'eyebrow' => 'People operations', 'icon' => 'bi-people', 'description' => 'Manage employees, departments, leave, attendance, targets, roles and payroll references.'],
        'settings' => ['label' => 'Settings', 'eyebrow' => 'CRM controls', 'icon' => 'bi-sliders', 'description' => 'Manage travel provider APIs, pricing controls, access roles, integrations and audit logs.'],
    ],
];

<?php

namespace App\Enums;

enum AdminPermission: string
{
    case ViewDashboard = 'dashboard.view';
    case ViewBookings = 'bookings.view';
    case ManageBookings = 'bookings.manage';
    case ViewCustomers = 'customers.view';
    case ManageCustomers = 'customers.manage';
    case ViewOffers = 'offers.view';
    case ManageOffers = 'offers.manage';
    case ViewServices = 'services.view';
    case ManageServices = 'services.manage';
    case ViewIntegrations = 'integrations.view';
    case ManageIntegrations = 'integrations.manage';
    case ViewAnalytics = 'analytics.view';
    case ManageSettings = 'settings.manage';
    case ManageTeam = 'team.manage';
}

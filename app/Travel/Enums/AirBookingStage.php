<?php

namespace App\Travel\Enums;

enum AirBookingStage: string
{
    case Search = 'search';
    case OfferSelected = 'offer_selected';
    case Revalidating = 'revalidating';
    case Revalidated = 'revalidated';
    case PassengerDetails = 'passenger_details';
    case BookingCreating = 'booking_creating';
    case BookingCreated = 'booking_created';
    case PaymentPending = 'payment_pending';
    case Paid = 'paid';
    case Ticketing = 'ticketing';
    case Complete = 'complete';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}

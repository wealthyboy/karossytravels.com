<?php

namespace App\Support;

final class EnquiryForms
{
    public const LABELS = [
        'help' => 'Help centre', 'contact' => 'Contact us', 'cancellation' => 'Cancellation support',
        'business_travel' => 'Business travel', 'partnership' => 'Partnership', 'careers' => 'Careers',
        'study_abroad' => 'Study abroad', 'car_hire' => 'Car hire', 'visa_enquiry' => 'Visa enquiry', 'driver' => 'Driver partnership',
    ];

    public static function pages(): array
    {
        return [
            'help' => ['route' => 'help-centre', 'path' => '/help-centre', 'title' => 'Help centre', 'intro' => 'Get help with your travel plans, booking questions and Karossy services.'],
            'contact' => ['route' => 'contact', 'path' => '/contact-us', 'title' => 'Contact us', 'intro' => 'Get in touch with the Karossy team about your travel requirements.'],
            'cancellation' => ['route' => 'cancellation-support', 'path' => '/cancellation-support', 'title' => 'Cancellation support', 'intro' => 'Ask our team about cancellation options and the conditions attached to your booking.'],
            'business_travel' => ['route' => 'business-travel', 'path' => '/business-travel', 'title' => 'Business travel', 'intro' => 'Discuss flight, hotel and travel coordination requirements for your organisation.'],
            'partnership' => ['route' => 'partners', 'path' => '/partner-with-us', 'title' => 'Partner with us', 'intro' => 'Introduce your business and explore working with Karossy Travels.'],
            'careers' => ['route' => 'careers', 'path' => '/careers', 'title' => 'Careers', 'intro' => 'Introduce yourself and share your interest in working with Karossy Travels.'],
            'study_abroad' => ['route' => 'study-program', 'path' => '/student-study-program', 'title' => 'Study abroad', 'intro' => 'Tell our advisers about your study plans.'],
        ];
    }

    public static function fields(string $type): array
    {
        return match ($type) {
            'help' => ['booking_reference' => ['Booking reference', false]],
            'cancellation' => ['booking_reference' => ['Booking reference', true]],
            'business_travel' => ['company_name' => ['Company name', true]],
            'partnership' => ['company_name' => ['Company / organisation', false]],
            'careers' => ['role_interest' => ['Role / area of interest', true], 'cv_url' => ['CV or portfolio URL', false]],
            'study_abroad' => ['destination' => ['Preferred destination', false], 'course' => ['Course / programme', false]],
            default => [],
        };
    }
}

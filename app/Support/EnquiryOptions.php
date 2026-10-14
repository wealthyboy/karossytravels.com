<?php

namespace App\Support;

final class EnquiryOptions
{
    public const HIRE_TYPES = ['Airport Transfer', 'One-Way Transfer', 'Round-Trip Transfer', 'Chauffeur-Driven Car', 'Daily Car Hire', 'Intercity Transfer', 'Corporate Transportation', 'Event Transportation', 'Group Transportation', 'Other'];

    public const RETURN_REQUIRED = ['Round-Trip Transfer', 'Daily Car Hire', 'Chauffeur-Driven Car'];

    public const VEHICLES = ['Sedan', 'SUV', 'Executive/Luxury Vehicle', 'Bus/Minibus', 'Van', 'No Preference – Recommend a suitable vehicle'];

    public const OCCUPATIONS = ['Employed', 'Self-employed', 'Business Owner', 'Student', 'Retired', 'Unemployed', 'Other'];

    public const TIMELINES = ['As soon as possible', 'Within 1 month', '1–3 months', '3–6 months', 'More than 6 months'];

    public const VISA_TYPES = [
        'Tourism/Holiday' => ['Tourist Visa', 'Visitor Visa'],
        'Visiting Family/Friends' => ['Visitor Visa', 'Family/Dependent Visa'],
        'Business' => ['Business Visa', 'Visitor Visa'],
        'Study' => ['Student Visa'], 'Work' => ['Work Visa'],
        'Conference/Event' => ['Business Visa', 'Visitor Visa'], 'Transit' => ['Transit Visa'],
        'Medical' => ['Visitor Visa'], 'Pilgrimage/Religious Travel' => ['Tourist Visa', 'Visitor Visa'],
        'Other' => ['Tourist Visa', 'Business Visa', 'Student Visa', 'Work Visa', 'Visitor Visa', 'Transit Visa', 'Family/Dependent Visa'],
    ];

    public const DOCUMENT_QUESTIONS = [
        'Study' => ['Do you have an admission letter?', 'For example, an offer or acceptance letter from a school or university.'],
        'Work' => ['Do you have a job offer or employment letter?', 'For example, a signed offer or a certificate of sponsorship.'],
        'Visiting Family/Friends' => ['Do you have an invitation letter from your host?', 'For example, a letter from the family member or friend you are visiting.'],
        'Business' => ['Do you have an invitation letter from the host company?', 'For example, a letter of invitation or a signed contract.'],
        'Conference/Event' => ['Do you have an invitation or registration for the event?', 'For example, a conference registration or organiser’s letter.'],
        'Medical' => ['Do you have a letter from the hospital or doctor?', 'For example, an appointment or treatment confirmation.'],
        'Tourism/Holiday' => ['Do you have a hotel booking or travel itinerary?', 'This can be a draft plan. We can advise on what is needed.'],
        'Transit' => ['Do you have an onward ticket to your final destination?', 'For example, a flight booking from the transit country.'],
        'Pilgrimage/Religious Travel' => ['Do you have an invitation or registration from the organiser?', 'For example, a group booking or a letter from a religious body.'],
    ];

    public static function visaTypes(string $purpose): array
    {
        if (! isset(self::VISA_TYPES[$purpose])) {
            return [];
        }

        return [...self::VISA_TYPES[$purpose], 'Not sure – please advise', ...($purpose === 'Other' ? [] : ['Other'])];
    }
}

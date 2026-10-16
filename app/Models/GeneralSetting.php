<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class GeneralSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['phone_numbers' => 'array', 'social_links' => 'array', 'enquiry_emails' => 'array', 'enquiry_cc_emails' => 'array', 'enquiry_bcc_emails' => 'array'];
    }

    public static function current(): self
    {
        $defaults = new self([
            'site_name' => config('app.name', 'Karossy Travels'),
            'contact_email' => config('travel.support.email', 'info@karossytravels.com'),
            'phone_numbers' => array_values(array_filter([config('travel.support.phone')])),
            'whatsapp_number' => config('travel.support.whatsapp'),
            'social_links' => [],
            'enquiry_emails' => null,
            'enquiry_cc_emails' => [],
            // Preserve the previously requested copy recipient until changed in admin.
            'enquiry_bcc_emails' => ['willie.samson@karossytravels.com'],
        ]);

        // Keep public pages available while an additive deployment migration is pending.
        if (! Schema::hasTable('general_settings')) {
            return $defaults;
        }

        return self::query()->find(1) ?? $defaults;
    }

    public function enquiryRecipients(): array
    {
        $clean = fn ($items) => array_values(array_unique(array_map('strtolower', array_filter((array) $items,
            fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)))));
        $to = $clean($this->enquiry_emails ?: [$this->contact_email]);
        $cc = array_values(array_diff($clean($this->enquiry_cc_emails ?? []), $to));
        $bcc = array_values(array_diff($clean($this->enquiry_bcc_emails ?? ['willie.samson@karossytravels.com']), $to, $cc));

        return compact('to', 'cc', 'bcc');
    }

    public function whatsappUrl(): ?string
    {
        $number = preg_replace('/\D+/', '', (string) $this->whatsapp_number);
        if (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        } elseif (str_starts_with($number, '0')) {
            $number = '234'.substr($number, 1);
        }

        return preg_match('/^[1-9][0-9]{6,14}$/', $number)
            ? 'https://wa.me/'.$number.'?text='.rawurlencode('Hello Karossy Travels, I need help planning my trip.')
            : null;
    }
}

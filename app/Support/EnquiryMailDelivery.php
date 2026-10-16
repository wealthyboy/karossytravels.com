<?php

namespace App\Support;

use App\Mail\CarHireRequestConfirmation;
use App\Mail\EnquiryConfirmation;
use App\Mail\EnquiryTeamNotification;
use App\Mail\VisaEnquiryConfirmation;
use App\Models\GeneralSetting;
use App\Models\PartnerEnquiry;
use App\Models\ServiceEnquiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class EnquiryMailDelivery
{
    /** Only failed/unattempted deliveries are retried; accepted emails are never resent. */
    public function deliver(ServiceEnquiry|PartnerEnquiry $enquiry): void
    {
        $recipients = GeneralSetting::current()->enquiryRecipients();
        foreach (['customer', 'team'] as $channel) {
            $claimed = DB::transaction(function () use ($enquiry, $channel): bool {
                $fresh = $enquiry->newQuery()->lockForUpdate()->findOrFail($enquiry->id);
                $status = $fresh->email_delivery ?? [];
                if (in_array(data_get($status, $channel.'.state'), ['accepted', 'sending'], true)) {
                    return false;
                }
                $status[$channel] = ['state' => 'sending', 'attempted_at' => now()->toIso8601String()];
                $fresh->forceFill(['email_delivery' => $status])->save();
                return true;
            });
            if (! $claimed) {
                continue;
            }

            try {
                if ($channel === 'customer') {
                    $mail = match ($enquiry->type) {
                        'car_hire' => new CarHireRequestConfirmation($enquiry),
                        'visa_enquiry' => new VisaEnquiryConfirmation($enquiry),
                        default => new EnquiryConfirmation($enquiry),
                    };
                    Mail::to($enquiry->email, $enquiry->name)->send($mail);
                } else {
                    if ($recipients['to'] === []) {
                        throw new \RuntimeException('No enquiry recipients configured.');
                    }
                    Mail::to($recipients['to'])->cc($recipients['cc'])->bcc($recipients['bcc'])->send(new EnquiryTeamNotification($enquiry));
                }
                $state = 'accepted';
            } catch (Throwable $exception) {
                $state = 'failed';
                Log::warning('Enquiry email delivery failed.', [
                    'reference' => $enquiry->reference ?? $enquiry->id,
                    'channel' => $channel,
                    'exception_type' => $exception::class,
                ]);
            }
            DB::transaction(function () use ($enquiry, $channel, $state): void {
                $fresh = $enquiry->newQuery()->lockForUpdate()->findOrFail($enquiry->id);
                $status = $fresh->email_delivery ?? [];
                $status[$channel]['state'] = $state;
                $status[$channel]['updated_at'] = now()->toIso8601String();
                $fresh->forceFill(['email_delivery' => $status])->save();
            });
        }
    }
}

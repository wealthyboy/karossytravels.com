<?php

namespace App\Travel;

use App\Mail\BookingActionNotification;
use App\Models\Booking;
use App\Models\BookingAction;
use App\Support\AuditLogger;
use App\Support\TravelLogger;
use App\Travel\TravelApi\TravelApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

final class BookingLifecycleService
{
    public function __construct(
        private readonly TravelApiClient $travelApi,
        private readonly AuditLogger $audit,
        private readonly TravelLogger $travelLogger,
    ) {}

    /** @param array{change_type:string,requested_change:string,reason:string,internal_notes?:string|null} $data */
    public function requestModification(Booking $booking, array $data): BookingAction
    {
        $this->assertActionable($booking);

        $action = $booking->actions()->create([
            'user_id' => auth()->id(),
            'type' => 'modify',
            'status' => 'requested',
            'change_type' => $data['change_type'],
            'requested_change' => $data['requested_change'],
            'reason' => $data['reason'],
            'internal_notes' => $data['internal_notes'] ?? null,
        ]);

        $this->audit->record('booking.modification_requested',
            "Requested a modification for booking {$booking->provider_locator}.",
            $booking,
            null,
            ['action_id' => $action->id, 'change_type' => $action->change_type, 'status' => $action->status],
        );
        $this->log($booking, $action);
        $this->notifyCustomer($action);

        return $action;
    }

    public function cancel(Booking $booking, string $reason, ?string $internalNotes = null): BookingAction
    {
        $this->assertActionable($booking);
        if ($booking->tickets()->where(fn ($query) => $query->where('status', 'issued')->orWhereNotNull('issued_at'))->exists()) {
            throw new RuntimeException('This booking has an issued ticket. Void or refund the ticket before cancelling the itinerary.');
        }

        return $this->providerAction($booking, 'cancel', $reason, $internalNotes, function () use ($booking): array {
            if ($this->isFake($booking)) {
                return ['mode' => 'test', 'accepted' => true];
            }

            if (! $this->isNdc($booking)) {
                return ['mode' => 'manual', 'accepted' => false];
            }

            $response = $this->travel_api->cancelTripOrder(['id' => $booking->provider_locator]);

            return ['mode' => 'live_order', 'accepted' => true, 'order_id' => data_get($response, 'order.id', $booking->provider_locator)];
        });
    }

    public function void(Booking $booking, string $reason, ?string $internalNotes = null): BookingAction
    {
        $this->assertActionable($booking);
        if ($booking->product_type !== 'flight') {
            throw new RuntimeException('Ticket voiding is only available for flight bookings.');
        }
        if (! $booking->tickets()->where(fn ($query) => $query->where('status', 'issued')->orWhereNotNull('issued_at'))->exists()) {
            throw new RuntimeException('There is no issued ticket to void on this booking.');
        }

        return $this->providerAction($booking, 'void', $reason, $internalNotes, function () use ($booking): array {
            if ($this->isFake($booking)) {
                return ['mode' => 'test', 'accepted' => true];
            }

            if (! $this->isNdc($booking)) {
                return ['mode' => 'manual', 'accepted' => false];
            }

            $response = $this->travel_api->changeTripOrder([
                'id' => $booking->provider_locator,
                'cancelDocumentAndRetainOrder' => true,
            ]);

            return ['mode' => 'live_order', 'accepted' => true, 'order_id' => data_get($response, 'order.id', $booking->provider_locator)];
        });
    }

    /** @param callable():array<string,mixed> $providerOperation */
    private function providerAction(Booking $booking, string $type, string $reason, ?string $internalNotes, callable $providerOperation): BookingAction
    {
        $action = $booking->actions()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'status' => 'processing',
            'reason' => $reason,
            'internal_notes' => $internalNotes,
        ]);

        try {
            $summary = $providerOperation();
            $completed = (bool) ($summary['accepted'] ?? false);

            DB::transaction(function () use ($booking, $action, $summary, $completed, $type): void {
                $action->update([
                    'status' => $completed ? 'completed' : 'requested',
                    'provider_summary' => $summary,
                    'completed_at' => $completed ? now() : null,
                ]);

                if (! $completed) {
                    return;
                }

                if ($type === 'cancel') {
                    $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                    if ($booking->order && ! $booking->order->bookings()->where('status', '!=', 'cancelled')->exists()) {
                        $booking->order->update(['status' => 'cancelled']);
                    }
                }

                if ($type === 'void') {
                    $booking->tickets()->where(fn ($query) => $query->where('status', 'issued')->orWhereNotNull('issued_at'))
                        ->update(['status' => 'voided', 'voided_at' => now()]);
                }
            });

            $this->audit->record("booking.{$type}_{$action->status}",
                ucfirst($type)." action {$action->status} for booking {$booking->provider_locator}.",
                $booking,
                null,
                ['action_id' => $action->id, 'status' => $action->status],
            );
            $this->log($booking, $action);
            $this->notifyCustomer($action);

            return $action->refresh();
        } catch (\Throwable $exception) {
            $action->update([
                'status' => 'failed',
                'failure_message' => str($exception->getMessage())->limit(1500)->toString(),
                'failed_at' => now(),
            ]);
            $this->audit->record("booking.{$type}_failed", ucfirst($type)." failed for booking {$booking->provider_locator}.", $booking, null, [
                'action_id' => $action->id, 'status' => 'failed',
            ]);
            $this->log($booking, $action, $exception->getMessage());
            $this->notifyCustomer($action);

            throw new RuntimeException('The travel system did not accept this action. The booking was not changed.', previous: $exception);
        }
    }

    private function assertActionable(Booking $booking): void
    {
        if (in_array($booking->status, ['cancelled', 'refunded', 'failed'], true)) {
            throw new RuntimeException('This booking can no longer be changed.');
        }
    }

    private function isFake(Booking $booking): bool
    {
        return strtolower($booking->provider) === 'fake';
    }

    private function isNdc(Booking $booking): bool
    {
        return ! blank(data_get($booking->travelOffer?->fare_summary, 'order_offer_id'));
    }

    private function notifyCustomer(BookingAction $action): void
    {
        $action->loadMissing('booking.order');
        $email = data_get($action->booking->order?->customer, 'email')
            ?: $action->booking->order?->customerProfile?->email;

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($email)->send(new BookingActionNotification($action));
            $action->update(['customer_notified_at' => now()]);
        } catch (\Throwable $exception) {
            Log::error('Failed to send booking lifecycle email.', [
                'booking_action_id' => $action->id,
                'booking_id' => $action->booking_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function log(Booking $booking, BookingAction $action, ?string $error = null): void
    {
        $this->travelLogger->record($booking->product_type, 'booking_'.$action->type, $booking->provider, [
            'booking_id' => $booking->id,
            'provider_locator' => $booking->provider_locator,
            'reason' => $action->reason,
        ], [
            'action_id' => $action->id,
            'status' => $action->status,
            'provider_summary' => $action->provider_summary,
        ], [
            'status' => $action->status === 'failed' ? 'failed' : 'success',
            'order_id' => $booking->order_id,
            'error_message' => $error,
        ]);
    }
}

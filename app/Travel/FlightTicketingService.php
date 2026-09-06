<?php

namespace App\Travel;

use App\Models\Booking;
use App\Models\Ticket;
use App\Support\TravelLogger;
use App\Travel\TravelApi\TravelApiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class FlightTicketingService
{
    public function __construct(
        private readonly TravelApiClient $client,
        private readonly TravelLogger $travelLogger,
    ) {}

    /**
     * Issue (or reconcile) the electronic ticket(s) for a paid, confirmed flight booking.
     *
     * @return Collection<int, Ticket>
     */
    public function issue(Booking $booking): Collection
    {
        $booking->loadMissing(['order.payments', 'tickets', 'travelOffer']);
        $this->assertTicketable($booking);

        $issued = $this->issuedTickets($booking);
        if ($issued->isNotEmpty()) {
            return $issued;
        }

        $lock = Cache::lock('flight-ticketing:'.$booking->getKey(), 60);

        try {
            return $lock->block(8, function () use ($booking): Collection {
                $booking->refresh()->loadMissing(['order.payments', 'tickets', 'travelOffer']);
                $this->assertTicketable($booking);

                $issued = $this->issuedTickets($booking);
                if ($issued->isNotEmpty()) {
                    return $issued;
                }

                if (strtolower((string) $booking->provider) === 'fake') {
                    return $this->issueFakeTickets($booking);
                }

                return $this->issueProviderTickets($booking);
            });
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Automatic post-payment ticketing must never turn a successful customer payment
     * into a second payment attempt. Persist the failure for Operations and continue.
     */
    public function issueAfterPayment(Booking $booking): bool
    {
        if (! (bool) config('services.travel.travel_api.auto_ticket_paid_bookings', true)) {
            return false;
        }

        try {
            return $this->issue($booking)->isNotEmpty();
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Automatic flight ticket issuance failed after payment.', [
                'booking_id' => $booking->id,
                'order_id' => $booking->order_id,
                'provider_locator' => $booking->provider_locator,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function assertTicketable(Booking $booking): void
    {
        if ($booking->product_type !== 'flight') {
            throw new RuntimeException('Ticket issuance is only available for flight bookings.');
        }

        if ($booking->status !== 'confirmed') {
            throw new RuntimeException('Only confirmed flight bookings can be ticketed.');
        }

        if (blank($booking->provider_locator)) {
            throw new RuntimeException('The flight booking does not have a valid PNR / provider locator.');
        }

        $paid = $booking->order?->payments
            ?->contains(fn ($payment): bool => in_array(strtolower((string) $payment->status), ['paid', 'simulated'], true));

        if (! $paid) {
            throw new RuntimeException('A confirmed payment is required before issuing the flight ticket.');
        }
    }

    /** @return Collection<int, Ticket> */
    private function issuedTickets(Booking $booking): Collection
    {
        return $booking->tickets
            ->filter(fn (Ticket $ticket): bool => $ticket->status === 'issued' || $ticket->issued_at !== null)
            ->values();
    }

    /** @return Collection<int, Ticket> */
    private function issueFakeTickets(Booking $booking): Collection
    {
        $travellers = collect($booking->travellers ?? []);
        if ($travellers->isEmpty()) {
            $travellers = collect([[]]);
        }

        $tickets = DB::transaction(function () use ($booking, $travellers): Collection {
            $booking->tickets()->whereNull('ticket_number')->whereIn('status', ['pending', 'failed'])->delete();

            return $travellers->values()->map(function (mixed $traveller, int $index) use ($booking): Ticket {
                $traveller = is_array($traveller) ? $traveller : [];

                return $booking->tickets()->create([
                    'ticket_number' => $this->fakeTicketNumber(),
                    'passenger_reference' => $this->passengerReference($traveller, $index),
                    'status' => 'issued',
                    'issuance_attempts' => 1,
                    'last_error' => null,
                    'issued_at' => now(),
                ]);
            });
        });

        $this->travelLogger->record('flight', 'ticketing', 'fake', [
            'booking_id' => $booking->id,
            'provider_locator' => $booking->provider_locator,
        ], [
            'ticket_count' => $tickets->count(),
            'tickets' => $tickets->map(fn (Ticket $ticket): array => [
                'number' => $this->maskTicketNumber((string) $ticket->ticket_number),
                'status' => $ticket->status,
            ])->all(),
        ], [
            'order_id' => $booking->order_id,
        ]);

        return $tickets;
    }

    /** @return Collection<int, Ticket> */
    private function issueProviderTickets(Booking $booking): Collection
    {
        $startedAt = microtime(true);
        $placeholder = $this->pendingTicket($booking);

        $requestSummary = [
            'booking_id' => $booking->id,
            'provider_locator' => $booking->provider_locator,
            'booking_source' => 'SABRE',
            'form_of_payment' => $this->formOfPayment(),
        ];

        try {
            $getBookingPayload = $this->getBookingPayload($booking);
            $currentBooking = $this->client->getBooking($getBookingPayload);
            $existingDocuments = $this->extractTicketDocuments($currentBooking);

            if ($existingDocuments->isNotEmpty()) {
                $tickets = $this->persistIssuedTickets($booking, $existingDocuments, $placeholder);
                $this->logSuccess($booking, $requestSummary + ['reconciled_existing_ticket' => true], $tickets, $startedAt);

                return $tickets;
            }

            $priceQuoteRecordIds = $this->extractPriceQuoteRecordIds($currentBooking);
            $fulfillmentPayload = $this->fulfillmentPayload($booking, $priceQuoteRecordIds);
            $requestSummary['price_quote_record_ids'] = $priceQuoteRecordIds;

            $response = $this->client->fulfillFlightTickets($fulfillmentPayload);
            $documents = $this->extractTicketDocuments($response);

            // Some supplier responses confirm fulfillment before the full ticket
            // document is returned. Re-read the booking once before declaring failure.
            if ($documents->isEmpty()) {
                $refreshedBooking = $this->client->getBooking($getBookingPayload);
                $documents = $this->extractTicketDocuments($refreshedBooking);
            }

            if ($documents->isEmpty()) {
                throw new RuntimeException('Sabre accepted the ticketing request but did not return an electronic ticket number.');
            }

            $tickets = $this->persistIssuedTickets($booking, $documents, $placeholder);
            $this->logSuccess($booking, $requestSummary, $tickets, $startedAt);

            return $tickets;
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage()) !== ''
                ? $exception->getMessage()
                : 'Sabre ticket issuance failed without a supplier error message.';

            $placeholder->update([
                'status' => 'failed',
                'last_error' => str($message)->limit(3000)->toString(),
            ]);

            $this->travelLogger->record('flight', 'ticketing', $booking->provider, $requestSummary, [], [
                'status' => 'failed',
                'order_id' => $booking->order_id,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => $message,
            ]);

            throw new RuntimeException($message, 0, $exception);
        }
    }

    private function pendingTicket(Booking $booking): Ticket
    {
        return DB::transaction(function () use ($booking): Ticket {
            $ticket = $booking->tickets()
                ->whereNull('ticket_number')
                ->whereIn('status', ['pending', 'failed'])
                ->latest('created_at')
                ->first();

            if (! $ticket) {
                $ticket = $booking->tickets()->create([
                    'ticket_number' => null,
                    'passenger_reference' => null,
                    'status' => 'pending',
                    'issuance_attempts' => 0,
                ]);
            }

            $ticket->update([
                'status' => 'pending',
                'issuance_attempts' => min(255, ((int) $ticket->issuance_attempts) + 1),
                'last_error' => null,
            ]);

            return $ticket->fresh();
        });
    }

    /** @return array<string, mixed> */
    private function getBookingPayload(Booking $booking): array
    {
        $payload = ['confirmationId' => (string) $booking->provider_locator];
        $targetPcc = trim((string) config('services.travel.travel_api.pcc'));

        if ($targetPcc !== '') {
            $payload['targetPcc'] = $targetPcc;
        }

        return $payload;
    }

    /** @param array<int, string> $priceQuoteRecordIds
     *  @return array<string, mixed>
     */
    private function fulfillmentPayload(Booking $booking, array $priceQuoteRecordIds): array
    {
        $fulfillment = [
            'payment' => ['primaryFormOfPayment' => 0],
        ];

        if ($priceQuoteRecordIds !== []) {
            $fulfillment['ticketingQualifiers'] = [
                'priceQuoteRecordIds' => $priceQuoteRecordIds,
            ];
        }

        $payload = [
            'confirmationId' => (string) $booking->provider_locator,
            'bookingSource' => 'SABRE',
            'receivedFrom' => trim((string) config('services.travel.travel_api.ticketing_received_from', 'KAROSSY')) ?: 'KAROSSY',
            'acceptPriceChanges' => false,
            'acceptNegotiatedFare' => false,
            'commitTicketToBookingWaitTime' => max(1000, (int) config('services.travel.travel_api.ticketing_commit_wait_ms', 5000)),
            'errorHandlingPolicy' => [trim((string) config('services.travel.travel_api.ticketing_error_policy', 'HALT_ON_ERROR')) ?: 'HALT_ON_ERROR'],
            'formsOfPayment' => [[
                'type' => $this->formOfPayment(),
            ]],
            'fulfillments' => [$fulfillment],
        ];

        $targetPcc = trim((string) config('services.travel.travel_api.pcc'));
        if ($targetPcc !== '') {
            $payload['targetPcc'] = $targetPcc;
        }

        return $payload;
    }

    private function formOfPayment(): string
    {
        $configured = strtoupper(trim((string) config('services.travel.travel_api.ticketing_form_of_payment', 'CASH')));

        return $configured !== '' ? $configured : 'CASH';
    }

    /** @param array<string, mixed> $response
     *  @return array<int, string>
     */
    private function extractPriceQuoteRecordIds(array $response): array
    {
        $ids = [];
        $this->collectPriceQuoteRecordIds($response, $ids);

        return array_values(array_unique($ids));
    }

    private function collectPriceQuoteRecordIds(mixed $value, array &$ids): void
    {
        if (is_string($value)) {
            $candidate = strtoupper(trim($value));
            if (preg_match('/^PQ\d+$/', $candidate) === 1) {
                $ids[] = $candidate;
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $child) {
            $this->collectPriceQuoteRecordIds($child, $ids);
        }
    }

    /**
     * @param array<string, mixed> $response
     * @return Collection<int, array{number:string,passenger_reference:?string,issued_at:mixed}>
     */
    private function extractTicketDocuments(array $response): Collection
    {
        $documents = collect();

        foreach ((array) data_get($response, 'tickets', []) as $index => $ticket) {
            if (! is_array($ticket)) {
                continue;
            }

            $number = $this->normalizedTicketNumber(data_get($ticket, 'number'));
            if ($number === null) {
                continue;
            }

            $documents->push([
                'number' => $number,
                'passenger_reference' => $this->responsePassengerReference($ticket),
                'issued_at' => data_get($ticket, 'date') ?: data_get($ticket, 'issuedAt'),
                'index' => (int) $index,
            ]);
        }

        $this->collectTicketDocuments($response, $documents);

        return $documents
            ->unique('number')
            ->values()
            ->map(fn (array $document): array => [
                'number' => $document['number'],
                'passenger_reference' => $document['passenger_reference'] ?? null,
                'issued_at' => $document['issued_at'] ?? null,
                'index' => $document['index'] ?? 0,
            ]);
    }

    private function collectTicketDocuments(mixed $value, Collection $documents): void
    {
        if (! is_array($value)) {
            return;
        }

        $keys = array_change_key_case($value, CASE_LOWER);
        $candidate = null;

        foreach (['ticketnumber', 'documentnumber', 'eticketnumber'] as $key) {
            if (array_key_exists($key, $keys)) {
                $candidate = $this->normalizedTicketNumber($keys[$key]);
                if ($candidate !== null) {
                    break;
                }
            }
        }

        // A generic `number` is only trusted inside an object that contains
        // additional ticket-specific fields. This avoids treating flight numbers,
        // phone numbers or other numeric values as e-ticket documents.
        if ($candidate === null && array_key_exists('number', $keys)) {
            $hasTicketCue = collect(['ticketstatuscode', 'ticketstatusname', 'ticketingpcc', 'issuedat', 'couponstatus'])
                ->contains(fn (string $key): bool => array_key_exists($key, $keys));

            if ($hasTicketCue) {
                $candidate = $this->normalizedTicketNumber($keys['number']);
            }
        }

        if ($candidate !== null) {
            $documents->push([
                'number' => $candidate,
                'passenger_reference' => $this->responsePassengerReference($value),
                'issued_at' => $value['date'] ?? $value['issuedAt'] ?? $value['issued_at'] ?? null,
                'index' => $documents->count(),
            ]);
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collectTicketDocuments($child, $documents);
            }
        }
    }

    private function normalizedTicketNumber(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);
        if (! is_string($digits) || preg_match('/^\d{10,14}$/', $digits) !== 1) {
            return null;
        }

        return $digits;
    }

    /** @param array<string, mixed> $ticket */
    private function responsePassengerReference(array $ticket): ?string
    {
        $given = data_get($ticket, 'travelerGivenName')
            ?: data_get($ticket, 'travellerGivenName')
            ?: data_get($ticket, 'traveler.firstName')
            ?: data_get($ticket, 'traveller.firstName');
        $surname = data_get($ticket, 'travelerSurname')
            ?: data_get($ticket, 'travellerSurname')
            ?: data_get($ticket, 'traveler.lastName')
            ?: data_get($ticket, 'traveller.lastName');
        $combined = trim(implode(' ', array_filter([(string) $given, (string) $surname])));

        return $combined !== '' ? $combined : null;
    }

    /**
     * @param Collection<int, array{number:string,passenger_reference:?string,issued_at:mixed,index?:int}> $documents
     * @return Collection<int, Ticket>
     */
    private function persistIssuedTickets(Booking $booking, Collection $documents, Ticket $placeholder): Collection
    {
        $travellers = collect($booking->travellers ?? [])->values();

        return DB::transaction(function () use ($booking, $documents, $placeholder, $travellers): Collection {
            $tickets = $documents->values()->map(function (array $document, int $index) use ($booking, $travellers): Ticket {
                $traveller = $travellers->get($index);
                $traveller = is_array($traveller) ? $traveller : [];
                $passenger = $document['passenger_reference'] ?: $this->passengerReference($traveller, $index);

                $existing = Ticket::query()->where('ticket_number', $document['number'])->first();
                if ($existing && (string) $existing->booking_id !== (string) $booking->id) {
                    throw new RuntimeException('The supplier returned a ticket number that is already attached to another booking.');
                }

                $attributes = [
                    'passenger_reference' => $passenger,
                    'status' => 'issued',
                    'issuance_attempts' => max(1, (int) $placeholder->issuance_attempts),
                    'last_error' => null,
                    'issued_at' => $this->issuedAt($document['issued_at'] ?? null),
                ];

                if ($existing) {
                    $existing->update($attributes);

                    return $existing->fresh();
                }

                return $booking->tickets()->create([
                    'ticket_number' => $document['number'],
                    ...$attributes,
                ]);
            });

            $booking->tickets()
                ->whereNull('ticket_number')
                ->whereIn('status', ['pending', 'failed'])
                ->delete();

            return $tickets;
        });
    }

    private function issuedAt(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return now();
        }
    }

    private function passengerReference(array $traveller, int $index): string
    {
        $name = trim(implode(' ', array_filter([
            (string) ($traveller['first_name'] ?? $traveller['given_name'] ?? ''),
            (string) ($traveller['last_name'] ?? $traveller['surname'] ?? ''),
        ])));

        return $name !== '' ? $name : 'Passenger '.($index + 1);
    }

    private function fakeTicketNumber(): string
    {
        do {
            $number = '999'.str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);
        } while (Ticket::query()->where('ticket_number', $number)->exists());

        return $number;
    }

    private function maskTicketNumber(string $ticketNumber): string
    {
        $digits = preg_replace('/\D+/', '', $ticketNumber) ?: $ticketNumber;

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    /** @param array<string, mixed> $requestSummary @param Collection<int, Ticket> $tickets */
    private function logSuccess(Booking $booking, array $requestSummary, Collection $tickets, float $startedAt): void
    {
        $this->travelLogger->record('flight', 'ticketing', $booking->provider, $requestSummary, [
            'ticket_count' => $tickets->count(),
            'tickets' => $tickets->map(fn (Ticket $ticket): array => [
                'number' => $this->maskTicketNumber((string) $ticket->ticket_number),
                'passenger' => $ticket->passenger_reference,
                'status' => $ticket->status,
            ])->all(),
        ], [
            'order_id' => $booking->order_id,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}

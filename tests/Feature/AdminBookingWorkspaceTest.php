<?php

namespace Tests\Feature;

use App\Mail\BookingActionNotification;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AdminBookingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_workspaces_are_consistent_and_filterable(): void
    {
        $admin = $this->bookingUser('admin');
        $customer = Customer::create([
            'first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'ada@example.test', 'status' => 'active',
        ]);
        $flight = $this->booking('KAR-FLIGHT-001', 'flight', 'confirmed', 'website', 'TRAVEL_API', 'PNR001', $admin, $customer);
        Ticket::create(['booking_id' => $flight->id, 'ticket_number' => '1234567890', 'status' => 'issued', 'issued_at' => now()]);
        $this->booking('KAR-HOTEL-001', 'hotel', 'pending', 'mobile_app', 'TRAVEL_API', 'HTL001', $admin, $customer);

        $this->actingAs($admin)->get('/admin/bookings')
            ->assertOk()
            ->assertSee('KAR-FLIGHT-001')
            ->assertSee('KAR-HOTEL-001')
            ->assertSee('All bookings');

        $this->get('/admin/bookings/flights?'.http_build_query([
            'q' => 'PNR001',
            'status' => 'confirmed',
            'source' => 'website',
            'provider' => 'TRAVEL_API',
            'ticket_status' => 'issued',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'sort' => 'created_at',
            'direction' => 'asc',
        ]))->assertOk()->assertSee('KAR-FLIGHT-001')->assertDontSee('KAR-HOTEL-001');

        $this->get('/admin/bookings/hotels')
            ->assertOk()->assertSee('KAR-HOTEL-001')->assertDontSee('KAR-FLIGHT-001');

        $this->get(route('admin.bookings.show', $flight))
            ->assertOk()->assertSee('PNR001')->assertSee('Ada Okafor')->assertSee('1234567890');
    }

    public function test_b2b_users_only_see_their_own_bookings(): void
    {
        $agent = $this->bookingUser('b2b');
        $other = User::factory()->create(['account_type' => 'b2b']);
        $owned = $this->booking('KAR-OWNED-001', 'flight', 'confirmed', 'b2b_portal', 'TRAVEL_API', 'OWN001', $agent);
        $foreign = $this->booking('KAR-FOREIGN-001', 'flight', 'confirmed', 'b2b_portal', 'TRAVEL_API', 'FOR001', $other);

        $this->actingAs($agent)->get('/admin/bookings/flights')
            ->assertOk()->assertSee('KAR-OWNED-001')->assertDontSee('KAR-FOREIGN-001');

        $this->get(route('admin.bookings.show', $owned))->assertOk();
        $this->get(route('admin.bookings.show', $foreign))->assertNotFound();
    }

    public function test_cancelling_a_booking_is_audited(): void
    {
        Mail::fake();
        $admin = $this->bookingUser('admin');
        $booking = $this->booking('KAR-CANCEL-001', 'flight', 'confirmed', 'admin', 'fake', 'CAN001', $admin);

        $this->actingAs($admin)->post(route('admin.bookings.cancel', $booking), [
            'reason' => 'Customer plans have changed.',
            'internal_notes' => 'Confirmed by telephone.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('booking_actions', ['booking_id' => $booking->id, 'type' => 'cancel', 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.cancel_completed', 'subject_id' => $booking->id]);
        Mail::assertSent(BookingActionNotification::class, fn ($mail) => $mail->hasTo('traveller@example.test'));
    }

    public function test_modification_is_recorded_and_emailed_without_changing_the_booking(): void
    {
        Mail::fake();
        $admin = $this->bookingUser('admin');
        $booking = $this->booking('KAR-MODIFY-001', 'flight', 'confirmed', 'admin', 'TRAVEL_API', 'MOD001', $admin);

        $this->actingAs($admin)->post(route('admin.bookings.modify', $booking), [
            'change_type' => 'dates',
            'requested_change' => 'Move departure to 19 August and retain the same route.',
            'reason' => 'The traveller requested a later departure.',
            'internal_notes' => 'Reprice before touching the PNR.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('booking_actions', [
            'booking_id' => $booking->id, 'type' => 'modify', 'status' => 'requested', 'change_type' => 'dates',
        ]);
        Mail::assertSent(BookingActionNotification::class);
    }

    public function test_voiding_a_fake_issued_ticket_updates_ticket_and_emails_customer(): void
    {
        Mail::fake();
        $admin = $this->bookingUser('admin');
        $booking = $this->booking('KAR-VOID-001', 'flight', 'confirmed', 'admin', 'fake', 'VOID01', $admin);
        $ticket = Ticket::create([
            'booking_id' => $booking->id,
            'ticket_number' => '1234567890123',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.void', $booking), [
            'reason' => 'The ticket was issued against the wrong payment instruction.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'voided']);
        $this->assertNotNull($ticket->fresh()->voided_at);
        Mail::assertSent(BookingActionNotification::class);
    }

    public function test_traditional_travel_api_cancellation_stays_pending_until_provider_confirmation(): void
    {
        Mail::fake();
        $admin = $this->bookingUser('admin');
        $booking = $this->booking('KAR-MANUAL-001', 'flight', 'confirmed', 'admin', 'TRAVEL_API', 'ABC123', $admin);

        $this->actingAs($admin)->post(route('admin.bookings.cancel', $booking), [
            'reason' => 'Customer can no longer travel.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('booking_actions', ['booking_id' => $booking->id, 'type' => 'cancel', 'status' => 'requested']);
        Mail::assertSent(BookingActionNotification::class);
    }

    private function booking(
        string $reference,
        string $product,
        string $status,
        string $source,
        string $provider,
        string $locator,
        User $owner,
        ?Customer $customer = null,
    ): Booking {
        $order = Order::create([
            'reference' => $reference,
            'user_id' => $owner->id,
            'customer_id' => $customer?->id,
            'channel' => $source === 'b2b_portal' ? 'b2b' : $source,
            'status' => $status,
            'currency' => 'NGN',
            'subtotal_minor' => 15000000,
            'fees_minor' => 250000,
            'discount_minor' => 0,
            'total_minor' => 15250000,
            'customer' => ['name' => $customer?->full_name ?? 'Test Traveller', 'email' => $customer?->email ?? 'traveller@example.test'],
        ]);

        return Booking::create([
            'order_id' => $order->id,
            'product_type' => $product,
            'provider' => $provider,
            'provider_locator' => $locator,
            'status' => $status,
            'source' => $source,
            'booked_at' => $status === 'confirmed' ? now() : null,
            'details' => ['itinerary' => [['origin' => 'LOS', 'destination' => 'LHR', 'flight_number' => '101']]],
        ]);
    }

    private function bookingUser(string $accountType): User
    {
        $user = User::factory()->create(['account_type' => $accountType, 'status' => 'active']);
        $role = Role::create(['name' => 'booking-role-'.uniqid(), 'label' => 'Booking operator']);
        $permissions = collect(['bookings.view' => 'View bookings', 'bookings.manage' => 'Manage bookings'])
            ->map(fn (string $label, string $name) => Permission::firstOrCreate(['name' => $name], ['label' => $label]));
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);

        return $user;
    }
}

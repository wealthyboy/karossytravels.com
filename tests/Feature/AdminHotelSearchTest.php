<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmation;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\HotelOffer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class AdminHotelSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_can_search_hotels_from_the_backend(): void
    {
        $user = User::factory()->create(['account_type' => 'admin', 'currency_code' => 'USD']);
        $role = Role::create(['name' => 'hotel-agent', 'label' => 'Hotel Agent']);
        $permissions = collect(['bookings.view', 'bookings.manage', 'offers.manage'])->map(fn (string $name) => Permission::create(['name' => $name, 'label' => str($name)->headline()]));
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/admin/hotels/search')
            ->assertOk()->assertSee('Search hotels')
            ->assertSee('id="nav-hotels"', false)
            ->assertSee('collapse show', false);

        $this->actingAs($user)->get('/admin/hotels/results?'.http_build_query([
            'destination_code' => 'LOS', 'destination_label' => 'Lagos, Nigeria',
            'check_in' => now()->addDays(14)->toDateString(), 'check_out' => now()->addDays(16)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'session_id' => (string) Str::uuid(),
        ]))->assertOk()->assertSee('Karossy Grand Hotel')->assertSee('Hotel markup and USD conversion applied');

        $this->assertDatabaseHas('hotel_searches', ['channel' => 'admin', 'user_id' => $user->id]);
    }

    public function test_admin_can_book_a_hotel_with_addons_and_operator_markup(): void
    {
        Mail::fake();
        $user = User::factory()->create(['account_type' => 'admin', 'currency_code' => 'USD']);
        $role = Role::create(['name' => 'hotel-booker', 'label' => 'Hotel Booker']);
        $permissions = collect(['bookings.view', 'bookings.manage', 'offers.manage'])->map(fn (string $name) => Permission::create(['name' => $name, 'label' => str($name)->headline()]));
        $role->permissions()->attach($permissions); $user->roles()->attach($role);
        $customer = Customer::create(['first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'hotel@example.test', 'status' => 'active']);
        $addon = Addon::create(['type' => 'hotel', 'title' => 'Airport transfer', 'price_cents' => 1000, 'currency' => 'USD', 'active' => true]);

        $this->actingAs($user)->get('/admin/hotels/results?'.http_build_query([
            'destination_code' => 'LOS', 'destination_label' => 'Lagos, Nigeria',
            'check_in' => now()->addDays(14)->toDateString(), 'check_out' => now()->addDays(16)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'session_id' => (string) Str::uuid(),
        ]))->assertOk();
        $offer = HotelOffer::firstOrFail();

        $this->get(route('admin.hotels.orders.create', $offer))->assertOk()
            ->assertSee('Airport transfer')
            ->assertSee('Search by name, email or phone')
            ->assertSee('Add customer')
            ->assertSee('id="addHotelCustomerModal"', false)
            ->assertDontSee('<select id="customer_id"', false)
            ->assertSee('id="nav-hotels"', false)
            ->assertSee('collapse show', false);

        $hotelAddonPage = $this->get(route('admin.addons.edit', $addon))->assertOk();
        $this->assertMatchesRegularExpression('/<div class="collapse show" id="nav-hotels">/', $hotelAddonPage->getContent());
        $this->assertDoesNotMatchRegularExpression('/<div class="collapse show" id="nav-flights">/', $hotelAddonPage->getContent());

        $flightAddon = Addon::create(['type' => 'flight', 'title' => 'Extra baggage', 'price_cents' => 2000, 'currency' => 'USD', 'active' => true]);
        $flightAddonPage = $this->get(route('admin.addons.edit', $flightAddon))->assertOk();
        $this->assertMatchesRegularExpression('/<div class="collapse show" id="nav-flights">/', $flightAddonPage->getContent());
        $this->assertDoesNotMatchRegularExpression('/<div class="collapse show" id="nav-hotels">/', $flightAddonPage->getContent());

        $this->post(route('admin.hotels.orders.store', $offer), [
            'customer_id' => $customer->id, 'addons' => [$addon->id],
            'operator_markup_type' => 'percentage', 'operator_markup_value' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id, 'status' => 'confirmed',
            'operator_markup_minor' => 4300, 'total_minor' => 47300,
        ]);
        $this->assertDatabaseHas('bookings', ['product_type' => 'hotel', 'status' => 'confirmed', 'source' => 'admin']);
        Mail::assertSent(BookingConfirmation::class, fn ($mail) => $mail->hasTo('hotel@example.test'));
    }
}

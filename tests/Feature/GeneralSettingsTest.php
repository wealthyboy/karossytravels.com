<?php

namespace Tests\Feature;

use App\Models\GeneralSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\SocialLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_settings_is_in_the_backend_navigation_and_has_all_fields(): void
    {
        $this->actingAs($this->administrator())->get(route('admin.settings.general.edit'))
            ->assertOk()->assertSee('General Settings')->assertSee('Site name')
            ->assertSee('Contact email')->assertSee('Phone numbers')->assertSee('WhatsApp number')
            ->assertSee('Social media links')->assertSee('Save general settings')
            ->assertSee('name="social_links[instagram]"', false)
            ->assertSee('name="social_links[tiktok]"', false)
            ->assertSee('name="social_links[threads]"', false)
            ->assertSee('id="nav-settings"', false);
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('General Settings');
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_authorized_admin_can_save_site_details_contact_numbers_and_social_links(): void
    {
        $this->actingAs($this->administrator())->put(route('admin.settings.general.update'), $this->values())
            ->assertRedirect(route('admin.settings.general.edit'))->assertSessionHas('success');
        $settings = GeneralSetting::current();
        $this->assertSame(1, $settings->id);
        $this->assertSame('Karossy Travels & Tours Limited', $settings->site_name);
        $this->assertSame('hello@example.test', $settings->contact_email);
        $this->assertSame(['+234 700 123 4567', '+44 20 1234 5678'], $settings->phone_numbers);
        $this->assertSame('+234 700 123 4567', $settings->whatsapp_number);
        $this->assertSame($this->values()['social_links'], $settings->social_links);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.general.updated', 'subject_id' => 1]);
        $this->get(route('admin.settings.general.edit'))->assertOk()->assertSee('hello@example.test')
            ->assertSee('https://www.instagram.com/karossy-example/');
    }

    public function test_saved_social_links_are_clickable_in_public_footers(): void
    {
        $this->actingAs($this->administrator())->put(route('admin.settings.general.update'), $this->values());
        foreach (['home', 'book-flights', 'book-hotels', 'cars.hire', 'visas.enquiry', 'study-program'] as $page) {
            $html = $this->followingRedirects()->get(route($page))->assertOk()->getContent();
            $footer = substr($html, strpos($html, '<footer class="public-footer">'));
            $this->assertStringContainsString('href="https://www.instagram.com/karossy-example/"', $footer);
            $this->assertStringContainsString('aria-label="Karossy on Instagram"', $footer);
            $this->assertStringContainsString('rel="noopener noreferrer"', $footer);
            $this->assertStringNotContainsString('title="YouTube"', $footer);
            // This pass intentionally does not replace existing public contact/name displays.
            $this->assertStringNotContainsString('hello@example.test', $footer);
        }
    }

    public function test_empty_social_settings_do_not_render_dead_placeholder_icons_or_write_a_row(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('class="public-footer-social"', false);
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_clearing_a_saved_link_removes_it_from_the_footer_immediately(): void
    {
        $this->actingAs($this->administrator())->put(route('admin.settings.general.update'), $this->values());
        $this->get(route('home'))->assertSee('https://www.instagram.com/karossy-example/');
        $values = $this->values();
        $values['social_links']['instagram'] = '';
        $this->put(route('admin.settings.general.update'), $values)->assertSessionHas('success');
        $this->get(route('home'))->assertOk()->assertDontSee('https://www.instagram.com/karossy-example/')
            ->assertSee('https://www.facebook.com/karossy-example');
        $this->assertDatabaseCount('general_settings', 1);
    }

    public function test_all_social_links_can_be_removed_and_contact_numbers_can_be_cleared(): void
    {
        $this->actingAs($this->administrator())->put(route('admin.settings.general.update'), $this->values());
        $values = $this->values();
        $values['social_links'] = [];
        $values['phone_numbers'] = '';
        $values['whatsapp_number'] = '';
        $this->put(route('admin.settings.general.update'), $values)->assertSessionHas('success');
        $this->get(route('home'))->assertDontSee('class="public-footer-social"', false);
        $this->assertSame([], GeneralSetting::current()->social_links);
        $this->assertSame([], GeneralSetting::current()->phone_numbers);
        $this->assertNull(GeneralSetting::current()->whatsapp_number);
    }

    public function test_settings_routes_require_the_existing_settings_permission(): void
    {
        $this->get(route('admin.settings.general.edit'))->assertNotFound();
        $this->put(route('admin.settings.general.update'), $this->values())->assertNotFound();
        $staff = User::factory()->create(['account_type' => 'admin']);
        $role = Role::create(['name' => 'test-staff', 'label' => 'Staff']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'dashboard.view'], ['label' => 'Dashboard']));
        $staff->roles()->attach($role);
        $this->actingAs($staff)->get(route('admin.settings.general.edit'))->assertForbidden();
        $this->put(route('admin.settings.general.update'), $this->values())->assertForbidden();
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_invalid_social_urls_and_embedded_credentials_are_rejected(): void
    {
        $this->actingAs($this->administrator());
        foreach (['javascript:alert(1)', 'data:text/html,hello', 'file:///etc/passwd', '@handle-only', 'https://username:password@example.test/profile'] as $url) {
            $values = $this->values();
            $values['social_links']['instagram'] = $url;
            $this->put(route('admin.settings.general.update'), $values)->assertSessionHasErrors('social_links.instagram');
        }
        $this->assertDatabaseCount('general_settings', 0);
    }

    public function test_invalid_contact_details_and_unknown_platforms_do_not_overwrite_saved_settings(): void
    {
        $this->actingAs($this->administrator())->put(route('admin.settings.general.update'), $this->values());
        $values = $this->values();
        $values['site_name'] = '';
        $values['contact_email'] = 'not-an-email';
        $values['phone_numbers'] = 'not a phone';
        $values['social_links']['unknown'] = 'https://example.test';
        $this->put(route('admin.settings.general.update'), $values)
            ->assertSessionHasErrors(['site_name', 'contact_email', 'phone_numbers.0', 'social_links']);
        $this->assertSame('hello@example.test', GeneralSetting::current()->contact_email);
        $this->assertDatabaseCount('general_settings', 1);
    }

    public function test_public_pages_are_available_before_the_settings_migration_is_applied(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::drop('general_settings');
        $this->get(route('home'))->assertOk()->assertDontSee('class="public-footer-social"', false);
    }

    public function test_footer_ignores_unsafe_or_unknown_links_even_if_written_directly_to_storage(): void
    {
        GeneralSetting::query()->create([
            'id' => 1, 'site_name' => 'Karossy', 'contact_email' => 'contact@example.test',
            'social_links' => ['facebook' => 'javascript:alert(1)', 'unknown' => 'https://example.test', 'instagram' => 'https://www.instagram.com/example/'],
        ]);
        $this->get(route('home'))->assertOk()->assertSee('href="https://www.instagram.com/example/"', false)
            ->assertDontSee('javascript:alert(1)')->assertDontSee('href="https://example.test"', false);
        $this->assertCount(11, SocialLinks::platforms());
    }

    private function administrator(): User
    {
        $user = User::factory()->create(['account_type' => 'admin']);
        $role = Role::create(['name' => 'general-settings-admin', 'label' => 'Settings Administrator']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'settings.manage'], ['label' => 'Manage Settings']));
        $user->roles()->attach($role);

        return $user;
    }

    private function values(): array
    {
        return [
            'site_name' => 'Karossy Travels & Tours Limited', 'contact_email' => 'hello@example.test',
            'phone_numbers' => "+234 700 123 4567\n\n+44 20 1234 5678", 'whatsapp_number' => '+234 700 123 4567',
            'social_links' => ['facebook' => 'https://www.facebook.com/karossy-example', 'instagram' => 'https://www.instagram.com/karossy-example/'],
        ];
    }
}

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
            $this->assertStringContainsString('role="img" aria-label="YouTube"', $footer);
            $this->assertStringNotContainsString('aria-label="Karossy on YouTube"', $footer);
            $this->assertStringContainsString('mailto:hello@example.test', $footer);
        }
    }

    public function test_original_social_icons_remain_visible_before_settings_are_saved_without_fake_links_or_database_writes(): void
    {
        $html = $this->get(route('home'))->assertOk()->assertSee('class="public-footer-social"', false)->getContent();
        preg_match('/<nav class="public-footer-social".*?<\/nav>/s', $html, $matches);
        $this->assertArrayHasKey(0, $matches);
        foreach (['Facebook', 'Instagram', 'LinkedIn', 'YouTube'] as $platform) {
            $this->assertStringContainsString('role="img" aria-label="'.$platform.'"', $matches[0]);
        }
        $this->assertSame(4, substr_count($matches[0], '<span '));
        $this->assertStringNotContainsString('<a ', $matches[0]);
        $this->assertStringNotContainsString('href="#"', $matches[0]);
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
            ->assertSee('https://www.facebook.com/karossy-example')
            ->assertSee('role="img" aria-label="Instagram"', false);
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
        $this->get(route('home'))->assertSee('class="public-footer-social"', false)
            ->assertSee('role="img" aria-label="Facebook"', false)
            ->assertSee('role="img" aria-label="Instagram"', false)
            ->assertSee('role="img" aria-label="LinkedIn"', false)
            ->assertSee('role="img" aria-label="YouTube"', false);
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
        $this->get(route('home'))->assertOk()->assertSee('class="public-footer-social"', false)
            ->assertSee('role="img" aria-label="Facebook"', false);
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

    public function test_additional_social_platforms_appear_only_when_linked_and_can_be_removed(): void
    {
        $this->assertSame(['facebook', 'instagram', 'linkedin', 'youtube'], array_column(SocialLinks::forFooter([]), 'key'));
        $values = $this->values();
        $values['social_links']['tiktok'] = 'https://www.tiktok.com/@karossy-example';
        $this->actingAs($this->administrator())->put(route('admin.settings.general.update'), $values)->assertSessionHas('success');
        $this->get(route('home'))->assertOk()->assertSee('href="https://www.tiktok.com/@karossy-example"', false)
            ->assertSee('aria-label="Karossy on TikTok"', false);

        $values['social_links']['tiktok'] = '';
        $this->put(route('admin.settings.general.update'), $values)->assertSessionHas('success');
        $this->get(route('home'))->assertOk()->assertDontSee('https://www.tiktok.com/@karossy-example')
            ->assertDontSee('title="TikTok"', false)
            ->assertSee('role="img" aria-label="YouTube"', false);
    }

    private function administrator(): User
    {
        $user = User::factory()->create(['account_type' => 'admin']);
        $role = Role::create(['name' => 'general-settings-admin', 'label' => 'Settings Administrator']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'settings.manage'], ['label' => 'Manage Settings']));
        $user->roles()->attach($role);

        return $user;
    }

    public function test_notification_recipient_lists_are_validated_and_saved_from_admin(): void
    {
        $this->actingAs($this->administrator())->put(route('admin.settings.general.update'), [...$this->values(),
            'enquiry_emails' => "Requests@example.test\nrequests@example.test; second@example.test",
            'enquiry_cc_emails' => 'copy@example.test', 'enquiry_bcc_emails' => 'private@example.test',
        ])->assertSessionHas('success');
        $settings = GeneralSetting::current();
        $this->assertSame(['requests@example.test', 'second@example.test'], $settings->enquiry_emails);
        $this->assertSame(['copy@example.test'], $settings->enquiry_cc_emails);
        $this->assertSame(['private@example.test'], $settings->enquiry_bcc_emails);
        $this->get(route('admin.settings.general.edit'))->assertOk()->assertSee('Enquiry email delivery')->assertSee('private@example.test');
        $this->get(route('home'))->assertDontSee('private@example.test')->assertDontSee('copy@example.test');
        $this->put(route('admin.settings.general.update'), [...$this->values(), 'enquiry_emails' => 'invalid email', 'enquiry_bcc_emails' => ["bad\r\nBcc: victim@example.test"]])
            ->assertSessionHasErrors(['enquiry_emails.0', 'enquiry_bcc_emails.0']);
    }

    public function test_copy_lists_can_be_cleared_and_recipients_are_deduplicated_across_headers(): void
    {
        $this->actingAs($this->administrator())->put(route('admin.settings.general.update'), [...$this->values(),
            'enquiry_emails' => 'primary@example.test', 'enquiry_cc_emails' => 'primary@example.test; copy@example.test',
            'enquiry_bcc_emails' => 'copy@example.test; private@example.test',
        ])->assertSessionHas('success');
        $this->assertSame(['to' => ['primary@example.test'], 'cc' => ['copy@example.test'], 'bcc' => ['private@example.test']], GeneralSetting::current()->enquiryRecipients());
        $this->put(route('admin.settings.general.update'), [...$this->values(), 'enquiry_emails' => '', 'enquiry_cc_emails' => '', 'enquiry_bcc_emails' => ''])->assertSessionHas('success');
        $this->assertSame(['to' => ['hello@example.test'], 'cc' => [], 'bcc' => []], GeneralSetting::current()->enquiryRecipients());
    }

    public function test_saved_whatsapp_number_overrides_environment_and_clearing_it_hides_the_button(): void
    {
        config(['travel.support.whatsapp' => '+2348000000000']);
        $this->actingAs($this->administrator())->put(route('admin.settings.general.update'), $this->values())->assertSessionHas('success');
        foreach (['home', 'contact', 'cars.hire', 'visas.enquiry', 'study-program'] as $page) {
            $this->get(route($page))->assertOk()->assertSee('class="public-whatsapp-float"', false)
                ->assertSee('https://wa.me/2347001234567', false)->assertDontSee('https://wa.me/2348000000000', false);
        }
        $this->put(route('admin.settings.general.update'), [...$this->values(), 'whatsapp_number' => ''])->assertSessionHas('success');
        $this->get(route('home'))->assertDontSee('class="public-whatsapp-float"', false);
        foreach (['123', '+000000000000000000', 'https://wa.me/2347001234567'] as $invalid) {
            $this->put(route('admin.settings.general.update'), [...$this->values(), 'whatsapp_number' => $invalid])->assertSessionHasErrors('whatsapp_number');
        }
        $this->assertStringContainsString('right: calc(1.25rem + env(safe-area-inset-right', file_get_contents(resource_path('css/app.css')));
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

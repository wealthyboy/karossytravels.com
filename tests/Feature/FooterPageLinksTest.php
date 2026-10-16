<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class FooterPageLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_and_company_footer_links_open_internal_pages_instead_of_email_clients(): void
    {
        $pages = [
            'Help centre' => route('help-centre'),
            'Contact us' => route('contact'),
            'Manage booking' => route('manage-booking.index'),
            'Cancellation support' => route('cancellation-support'),
            'About Karossy' => route('about'),
            'Business travel' => route('business-travel'),
            'Partner with us' => route('partners'),
            'Study abroad' => route('study-program').'#study-enquiry',
            'Careers' => route('careers'),
        ];

        foreach (['home', 'contact', 'about', 'manage-booking.index', 'study-program'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $footer = substr($html, strpos($html, '<footer class="public-footer">'));
            foreach ($pages as $label => $url) {
                $this->assertStringContainsString('href="'.e($url).'">'.$label.'</a>', $footer);
            }
        }
    }

    public function test_new_form_pages_are_available_and_accept_real_post_submissions(): void
    {
        Mail::fake();
        foreach (['help-centre', 'contact', 'cancellation-support', 'business-travel', 'partners', 'careers'] as $route) {
            $html = $this->get(route($route))->assertOk()
                ->assertDontSee('Online form coming soon.')
                ->assertSee('name="robots" content="index,follow"', false)->getContent();
            preg_match('/<form[^>]*data-service-enquiry.*?<\/form>/s', $html, $matches);
            $this->assertArrayHasKey(0, $matches);
            $this->assertStringContainsString('method="POST"', $matches[0]);
            $this->assertStringContainsString('action="'.route($route.'.store').'"', $matches[0]);
            $this->assertStringContainsString('type="submit"', $matches[0]);
            $this->assertStringNotContainsString('<fieldset disabled', $matches[0]);
        }
        Mail::assertNothingSent();
    }

    public function test_about_page_reuses_the_approved_homepage_company_copy(): void
    {
        foreach (['home', 'about'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('Karossy Travels &amp; Tours Limited is a travel destination management company', false)
                ->assertSee('Your Satisfaction, Our Peace.');
        }
    }

    public function test_study_abroad_has_a_matching_form_anchor_and_manage_booking_still_works(): void
    {
        $this->get(route('study-program'))->assertOk()
            ->assertSee('href="#study-enquiry"', false)
            ->assertSee('id="study-enquiry"', false)
            ->assertSee('data-service-enquiry="study_abroad"', false);
        $this->get(route('manage-booking.index'))->assertOk()
            ->assertSee('action="'.route('manage-booking.lookup').'"', false)
            ->assertSee('data-manage-booking-form', false);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeTrustSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_the_four_requested_trust_points(): void
    {
        $this->get(route('home'))->assertOk()->assertSeeInOrder([
            'Why Travelers Trust Karossy',
            'No Hidden Charges',
            'What we quote is what you pay. Transparent pricing on every flight, hotel, and tour package.',
            'Dedicated Travel Support',
            'Real humans, not automated bots. We are available to answer questions and resolve urgent flight changes when you need us.',
            'High Visa Guidance Success',
            'We double-check every document before submission to maximize your approval chances.',
            'End-to-End Care',
            "From airport pickups to hotel check-ins, we oversee every leg of your trip so you don't have to stress.",
        ], false);
    }

    public function test_each_trust_point_has_its_own_inline_svg_and_accessible_heading(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $section = '//section[@id="why-trust-karossy"]';

        $this->assertSame(1, $xpath->query($section.'[@aria-labelledby="home-trust-title"]')->length);
        $this->assertSame(1, $xpath->query($section.'//h2[@id="home-trust-title"]')->length);
        $this->assertSame(4, $xpath->query($section.'//li/h3')->length);
        $this->assertSame(4, $xpath->query($section.'//li//svg[@aria-hidden="true" and @focusable="false"]')->length);
    }

    public function test_trust_section_sits_before_about_without_restoring_the_old_trust_strip(): void
    {
        $this->get(route('home'))->assertOk()->assertSeeInOrder([
            'id="student-study-program"',
            'id="why-trust-karossy"',
            'id="about-karossy"',
        ], false)->assertDontSee('class="public-trust-strip"', false);
    }
}

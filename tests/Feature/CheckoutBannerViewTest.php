<?php

namespace Tests\Feature;

use Tests\TestCase;

final class CheckoutBannerViewTest extends TestCase
{
    public function test_checkout_steps_do_not_render_the_removed_security_banner(): void
    {
        foreach ([1, 2] as $step) {
            $this->assertSame('', trim(view('checkout._progress', ['step' => $step])->render()));
        }
        $this->assertSame('', trim(view('checkout._progress')->render()));
    }

    public function test_completed_bookings_keep_the_confirmation_banner(): void
    {
        $html = view('checkout._progress', ['step' => 3])->render();
        $this->assertStringContainsString('Booking complete', $html);
        $this->assertStringContainsString('Confirmed', $html);
        $this->assertStringNotContainsString('One simple checkout', $html);
    }
}

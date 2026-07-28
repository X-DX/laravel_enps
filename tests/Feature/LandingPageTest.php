<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_landing_page_renders_for_guests(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('National Pension System')
            ->assertSee('Sign in');
    }
}

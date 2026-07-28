<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('National Pension System');
    }

    public function test_root_redirects_guests_to_login(): void
    {
        // Unauthenticated users hitting /dashboard are redirected to the login route.
        $this->get('/dashboard')->assertRedirect('/login');
    }
}

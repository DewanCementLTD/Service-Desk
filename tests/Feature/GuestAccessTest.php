<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Nothing is reachable without signing in. Replaces the framework's stock
 * example test, which asserted that "/" returns 200 — it never has here, since
 * the root redirects into the authenticated area.
 */
class GuestAccessTest extends TestCase
{
    public function test_the_root_sends_a_visitor_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_protected_areas_send_a_visitor_to_sign_in(): void
    {
        foreach (['/dashboard', '/tickets', '/board', '/profile', '/users', '/audit'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_the_sign_in_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in', false);
    }
}

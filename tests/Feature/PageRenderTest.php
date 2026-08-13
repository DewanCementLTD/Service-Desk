<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Read-only smoke coverage: every screen a role can reach must render.
 *
 * This suite deliberately performs GETs only. The models are bound to the Oracle
 * connection, so it runs against whatever SDESK schema the environment points at
 * and must never write to it. Behavioural tests that create data belong on a
 * disposable schema — see the Phase 3 test work.
 */
class PageRenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection('oracle')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Oracle is not reachable from this environment.');
        }
    }

    /** @return array<string, array{0: string, 1: array<int, string>}> */
    public static function roleScreens(): array
    {
        return [
            'employee' => [Role::EMPLOYEE,
                ['/dashboard', '/tickets', '/tickets/new', '/tickets/create', '/development/create', '/guide', '/profile']],
            'it staff' => [Role::IT_STAFF,
                ['/dashboard', '/tickets', '/tickets/new', '/tickets/create', '/guide', '/profile']],
            'it manager' => [Role::IT_MANAGER,
                ['/dashboard', '/tickets', '/tickets/new', '/development', '/reports', '/guide', '/profile', '/board', '/board/backlog']],
            // The compliance register has been removed; the role is kept only for
            // the live accounts still holding it, on a baseline employee screen set.
            'compliance officer' => [Role::COMPLIANCE,
                ['/dashboard', '/tickets', '/guide', '/profile']],
            'developer' => [Role::DEVELOPER,
                ['/dashboard', '/tickets', '/development', '/guide', '/profile']],
            'administrator' => [Role::ADMIN,
                ['/dashboard', '/tickets', '/users', '/admin/departments', '/audit', '/guide', '/profile']],
        ];
    }

    /** @param  array<int, string>  $screens */
    #[DataProvider('roleScreens')]
    public function test_screens_render_for_role(string $roleCode, array $screens): void
    {
        $user = User::active()->whereHas('role', fn ($q) => $q->where('code', $roleCode))->first();

        if ($user === null) {
            $this->markTestSkipped("No active user holds the {$roleCode} role.");
        }

        // A user still carrying the forced-password-change flag is bounced to the
        // password screen from everywhere, which renders fine and would make every
        // assertion below pass without ever reaching the screen under test. The
        // flag is cleared on the in-memory model only — nothing is written.
        $user->must_change_password = false;

        foreach ($screens as $screen) {
            // Redirects are followed because /dashboard hands off to the dashboard
            // for the role; "Sign out" only appears once the app shell renders, so
            // it proves the page is the real screen and not a bounce to sign-in.
            $response = $this->actingAs($user)->followingRedirects()->get($screen);

            $this->assertSame(200, $response->getStatusCode(),
                "{$screen} failed for {$roleCode} with status {$response->getStatusCode()}.");

            $response->assertSee('Sign out', false);
        }
    }

    /**
     * An employee's dashboard is a distinct view from the one department heads
     * and IT staff share — it must actually be the personal-requests summary,
     * not just any 200.
     */
    public function test_employee_dashboard_shows_their_own_request_counts(): void
    {
        $user = User::active()->whereHas('role', fn ($q) => $q->where('code', Role::EMPLOYEE))->first();

        if ($user === null) {
            $this->markTestSkipped('No active employee.');
        }

        $user->must_change_password = false;

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('My tickets', false)
            ->assertSee('Total raised', false);
    }
}

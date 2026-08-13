<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Entity separation — BRD AC-06.
 *
 * One deployment serves twenty companies, so the guarantee that matters most is
 * that a user granted one company cannot reach another's records, including by
 * typing an identifier into the address bar. Read-only throughout.
 */
class ScopeIsolationTest extends TestCase
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

    public function test_a_ticket_outside_the_users_companies_is_refused(): void
    {
        // Someone without the blanket grant, and a ticket their grants exclude.
        $user = User::active()->where('is_all_companies', 0)->whereHas('companies')->first();

        if ($user === null) {
            $this->markTestSkipped('No user with explicit company grants.');
        }

        $user->must_change_password = false;

        $ticket = Ticket::whereNotIn('company_id', $user->accessibleCompanyIds() ?: [0])->first();

        if ($ticket === null) {
            $this->markTestSkipped('No ticket exists outside this user\'s granted companies.');
        }

        $this->actingAs($user)->get("/tickets/{$ticket->id}")->assertForbidden();
    }

    public function test_a_requester_without_view_all_cannot_open_someone_elses_ticket(): void
    {
        $user = User::active()->whereHas('role', fn ($q) => $q->where('code', Role::EMPLOYEE))->first();

        if ($user === null) {
            $this->markTestSkipped('No active employee.');
        }

        $user->must_change_password = false;

        $this->assertFalse($user->hasPermission('tickets.view_all'),
            'An employee must not hold tickets.view_all.');

        $ticket = Ticket::whereIn('company_id', $user->accessibleCompanyIds() ?: [0])
            ->where('requester_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', '!=', $user->id))
            ->first();

        if ($ticket === null) {
            $this->markTestSkipped('No other requester\'s ticket in range.');
        }

        $this->actingAs($user)->get("/tickets/{$ticket->id}")->assertForbidden();
    }

    public function test_an_employee_cannot_reach_administration(): void
    {
        $user = User::active()->whereHas('role', fn ($q) => $q->where('code', Role::EMPLOYEE))->first();

        if ($user === null) {
            $this->markTestSkipped('No active employee.');
        }

        $user->must_change_password = false;

        foreach (['/users', '/admin/roles', '/admin/departments', '/audit', '/reports', '/board'] as $forbidden) {
            $this->actingAs($user)->get($forbidden)->assertForbidden();
        }
    }

    public function test_an_employee_cannot_assign_a_ticket(): void
    {
        $user = User::active()->whereHas('role', fn ($q) => $q->where('code', Role::EMPLOYEE))->first();

        if ($user === null) {
            $this->markTestSkipped('No active employee.');
        }

        $user->must_change_password = false;

        $ticket = Ticket::where('requester_id', $user->id)->first();

        if ($ticket === null) {
            $this->markTestSkipped('That employee has raised no tickets.');
        }

        $this->actingAs($user)
            ->post("/tickets/{$ticket->id}/assign", ['assigned_to' => $user->id])
            ->assertForbidden();
    }
}

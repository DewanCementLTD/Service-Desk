<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A user still flagged must_change_password is gated to the password screen —
 * but an AJAX caller must be told that in a way it can detect, not handed a 200
 * with the password page's HTML in it. fetch() follows redirects transparently,
 * so a plain redirect() here reads as success to the caller even though nothing
 * happened: this is exactly what let board drags look like they worked while
 * quietly doing nothing, for the 343 of 354 active users still on a temporary
 * password.
 */
class ForcedPasswordChangeAjaxTest extends TestCase
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

    public function test_an_ajax_call_is_refused_with_json_not_a_redirect(): void
    {
        $user = User::active()->whereHas('role', fn ($q) => $q->where('code', Role::IT_MANAGER))->first();

        if ($user === null) {
            $this->markTestSkipped('No active IT manager.');
        }

        $user->must_change_password = true;

        $ticket = Ticket::whereIn('company_id', $user->accessibleCompanyIds())
            ->whereHas('status', fn ($q) => $q->where('code', 'OPEN'))
            ->first();

        if ($ticket === null) {
            $this->markTestSkipped('No open ticket in scope to move.');
        }

        $inProgress = TicketStatus::where('code', 'IN_PROGRESS')->first();
        $before     = $ticket->status_id;

        $response = $this->actingAs($user)->postJson('/board/move', [
            'kind'      => 'TICKET',
            'id'        => $ticket->id,
            'status_id' => $inProgress->id,
            'sprint_id' => $ticket->sprint_id,
        ]);

        $response->assertStatus(423);
        $response->assertJsonStructure(['message']);

        $ticket->refresh();
        $this->assertSame($before, $ticket->status_id,
            'A gated user must not be able to move a ticket via the board.');
    }

    public function test_a_normal_page_visit_still_redirects_to_the_password_screen(): void
    {
        $user = User::active()->whereHas('role', fn ($q) => $q->where('code', Role::IT_MANAGER))->first();

        if ($user === null) {
            $this->markTestSkipped('No active IT manager.');
        }

        $user->must_change_password = true;

        $this->actingAs($user)->get('/board')->assertRedirect('/password');
    }
}

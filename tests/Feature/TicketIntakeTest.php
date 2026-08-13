<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The intake rules the business asked for, enforced at the server rather than
 * only in the form: a description is required, the department comes from the
 * requester's record, and neither type nor a retired category can be posted.
 *
 * Every test here submits an invalid payload and asserts it is refused, so the
 * suite creates no tickets and is safe to run against a live schema.
 */
class TicketIntakeTest extends TestCase
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

    private function requester(): User
    {
        $user = User::active()
            ->whereHas('role', fn ($q) => $q->where('code', Role::EMPLOYEE))
            ->whereNotNull('department_id')
            ->first();

        if ($user === null) {
            $this->markTestSkipped('No active employee with a department on file.');
        }

        $user->must_change_password = false;

        return $user;
    }

    public function test_a_ticket_without_a_description_is_refused(): void
    {
        $user = $this->requester();

        $this->actingAs($user)
            ->from('/tickets/create')
            ->post('/tickets', [
                'subject'     => 'Test — should never be created',
                'description' => '',
                'company_id'  => $user->accessibleCompanyIds()[0] ?? null,
                'priority_id' => \App\Models\TicketPriority::first()?->id,
            ])
            ->assertSessionHasErrors('description');
    }

    public function test_a_department_cannot_be_posted(): void
    {
        $user = $this->requester();

        // A department belonging to someone else: the request must ignore it
        // outright rather than validate it, so it never reaches the ticket.
        $other = Department::where('id', '!=', $user->department_id)->first();

        if ($other === null) {
            $this->markTestSkipped('Only one department exists.');
        }

        $request = new \App\Http\Requests\StoreTicketRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertArrayNotHasKey('department_id', $request->rules(),
            'department_id must not be accepted from the form — TicketService takes it from the requester.');
    }

    public function test_type_cannot_be_posted(): void
    {
        $user = $this->requester();

        $request = new \App\Http\Requests\StoreTicketRequest();
        $request->setUserResolver(fn () => $user);

        $this->assertArrayNotHasKey('type_id', $request->rules(),
            'type has been dropped from the user journey and must not be accepted.');
    }

    public function test_a_retired_category_is_refused(): void
    {
        $user    = $this->requester();
        $retired = TicketCategory::where('is_active', 0)->first();

        if ($retired === null) {
            $this->markTestSkipped('No deactivated category to test with.');
        }

        $this->actingAs($user)
            ->from('/tickets/create')
            ->post('/tickets', [
                'subject'     => 'Test — should never be created',
                'description' => 'Body',
                'company_id'  => $user->accessibleCompanyIds()[0] ?? null,
                'priority_id' => \App\Models\TicketPriority::first()?->id,
                'category_id' => $retired->id,
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_only_erp_and_network_are_offered(): void
    {
        $this->assertSame(
            ['ERP', 'NETWORK'],
            TicketCategory::where('is_active', 1)->orderBy('code')->pluck('code')->all()
        );
    }

    public function test_a_ticket_without_a_category_is_refused(): void
    {
        $user = $this->requester();

        $this->actingAs($user)
            ->from('/tickets/create')
            ->post('/tickets', [
                'subject'     => 'Test — should never be created',
                'description' => 'Body',
                'company_id'  => $user->accessibleCompanyIds()[0] ?? null,
                'priority_id' => \App\Models\TicketPriority::first()?->id,
            ])
            ->assertSessionHasErrors('category_id');
    }
}

<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who may be handed a ticket.
 *
 * One definition, used by the create form, the assign box on the ticket page and
 * the validation behind both — so what the dropdown offers and what the server
 * accepts can never drift apart.
 *
 * An agent is an active user in the company's IT department, or one holding an
 * IT role there. Membership of the department is enough on its own: a company
 * that staffs its desk with people on other role codes still gets a usable list.
 */
class AgentDirectory
{
    public function __construct(private ScopeContext $scope)
    {
    }

    /** @return Collection<int, User> */
    public function forCompany(?int $companyId = null): Collection
    {
        $companyId ??= $this->scope->companyId();

        $itDepartments = Department::active()
            ->where('is_it_team', 1)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->pluck('id')
            ->all();

        return User::active()
            ->with('role')
            ->where(function ($q) use ($itDepartments) {
                $q->whereIn('department_id', $itDepartments ?: [0])
                    ->orWhereHas('role', fn ($r) => $r->whereIn('code', [
                        Role::IT_MANAGER, Role::IT_STAFF, Role::DEVELOPER,
                    ]));
            })
            // Someone granted the company, either explicitly or by the blanket flag.
            ->when($companyId, fn ($q) => $q->where(fn ($w) => $w
                ->where('is_all_companies', 1)
                ->orWhereHas('companies', fn ($c) => $c->where('companies.id', $companyId))))
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, int> */
    public function idsForCompany(?int $companyId = null): array
    {
        return $this->forCompany($companyId)->modelKeys();
    }
}

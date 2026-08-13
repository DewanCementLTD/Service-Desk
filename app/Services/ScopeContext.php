<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * Tracks which organisation / company / location the signed-in user is currently
 * working in. Users may have access to many of each, so the active selection is
 * kept in the session and every scoped query filters against it.
 */
class ScopeContext
{
    private const KEY_ORG      = 'scope.organization_id';
    private const KEY_COMPANY  = 'scope.company_id';
    private const KEY_LOCATION = 'scope.location_id';

    /**
     * Ensure the session holds a valid selection, falling back to the user's
     * defaults and then to the first company/location they can access.
     */
    public function initialiseFor(User $user): void
    {
        $companyId = Session::get(self::KEY_COMPANY);

        // A company held in the session can disappear between requests — deleted,
        // or deactivated by an administrator. Access alone is not enough: a user
        // with a "all companies" grant would otherwise keep a dead id in scope and
        // write it into every scoped insert.
        if ($companyId !== null && ! $this->companyIsUsable((int) $companyId)) {
            $this->clear();
            $companyId = null;
        }

        if ($companyId !== null && $user->canAccessCompany((int) $companyId)) {
            $this->syncOrganizationFromCompany((int) $companyId);
            $this->validateLocation($user, (int) $companyId);

            return;
        }

        $company = $this->resolveDefaultCompany($user);

        if ($company === null) {
            $this->clear();

            return;
        }

        $this->setCompany($company->id);
        $this->setOrganization($company->organization_id);

        $location = $this->resolveDefaultLocation($user, $company->id);
        $this->setLocation($location?->id);
    }

    public function organizationId(): ?int
    {
        $value = Session::get(self::KEY_ORG);

        return $value === null ? null : (int) $value;
    }

    public function companyId(): ?int
    {
        $value = Session::get(self::KEY_COMPANY);

        return $value === null ? null : (int) $value;
    }

    public function locationId(): ?int
    {
        $value = Session::get(self::KEY_LOCATION);

        return $value === null ? null : (int) $value;
    }

    public function organization(): ?Organization
    {
        $id = $this->organizationId();

        return $id === null ? null : Organization::find($id);
    }

    public function company(): ?Company
    {
        $id = $this->companyId();

        return $id === null ? null : Company::find($id);
    }

    public function location(): ?Location
    {
        $id = $this->locationId();

        return $id === null ? null : Location::find($id);
    }

    public function setOrganization(?int $organizationId): void
    {
        Session::put(self::KEY_ORG, $organizationId);
    }

    public function setCompany(?int $companyId): void
    {
        Session::put(self::KEY_COMPANY, $companyId);
    }

    /** Null means "all locations in the active company". */
    public function setLocation(?int $locationId): void
    {
        Session::put(self::KEY_LOCATION, $locationId);
    }

    /**
     * Switch the active company, rejecting companies the user cannot access.
     * Returns false when the switch was refused.
     */
    public function switchCompany(User $user, int $companyId): bool
    {
        if (! $user->canAccessCompany($companyId)) {
            return false;
        }

        $company = Company::find($companyId);

        if ($company === null || ! $company->is_active) {
            return false;
        }

        $this->setCompany($company->id);
        $this->setOrganization($company->organization_id);

        // The previous location belongs to the previous company, so re-derive it.
        $this->setLocation($this->resolveDefaultLocation($user, $company->id)?->id);

        return true;
    }

    public function switchLocation(User $user, ?int $locationId): bool
    {
        if ($locationId === null) {
            $this->setLocation(null);

            return true;
        }

        if (! $user->canAccessLocation($locationId)) {
            return false;
        }

        $location = Location::find($locationId);

        if ($location === null || $location->company_id !== $this->companyId()) {
            return false;
        }

        $this->setLocation($location->id);

        return true;
    }

    public function clear(): void
    {
        Session::forget([self::KEY_ORG, self::KEY_COMPANY, self::KEY_LOCATION]);
    }

    private function companyIsUsable(int $companyId): bool
    {
        return Company::where('id', $companyId)->where('is_active', 1)->exists();
    }

    private function resolveDefaultCompany(User $user): ?Company
    {
        if ($user->default_company_id && $user->canAccessCompany((int) $user->default_company_id)) {
            $company = Company::find($user->default_company_id);

            if ($company !== null && $company->is_active) {
                return $company;
            }
        }

        return $user->accessibleCompanies()->first();
    }

    private function resolveDefaultLocation(User $user, int $companyId): ?Location
    {
        if ($user->default_location_id && $user->canAccessLocation((int) $user->default_location_id)) {
            $location = Location::find($user->default_location_id);

            if ($location !== null && $location->company_id === $companyId && $location->is_active) {
                return $location;
            }
        }

        // No default: leave it unset so the user sees the whole company.
        return null;
    }

    private function syncOrganizationFromCompany(int $companyId): void
    {
        if ($this->organizationId() === null) {
            $this->setOrganization(Company::where('id', $companyId)->value('organization_id'));
        }
    }

    private function validateLocation(User $user, int $companyId): void
    {
        $locationId = $this->locationId();

        if ($locationId === null) {
            return;
        }

        $valid = $user->canAccessLocation($locationId)
            && Location::where('id', $locationId)->where('company_id', $companyId)->exists();

        if (! $valid) {
            $this->setLocation(null);
        }
    }
}

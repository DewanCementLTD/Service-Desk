<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Roles and their permission grants. A user's role decides what they may see,
 * create and update - editing a role here changes that for every user holding it.
 */
class RoleController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%' . strtoupper($request->string('q')) . '%';
                    $q->where(fn ($w) => $w->whereRaw('UPPER(CODE) LIKE ?', [$term])
                        ->orWhereRaw('UPPER(NAME) LIKE ?', [$term]));
                })
                ->orderBy('hierarchy_level')->orderBy('sort_order')
                ->paginate(25)->withQueryString(),
            'filters' => $request->only('q'),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', $this->formData(
            new Role(['is_active' => true, 'hierarchy_level' => 50, 'sort_order' => 0])
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $role = Role::create($data + ['is_system' => 0]);
        $role->permissions()->sync($this->permissionIds($request));

        $this->audit->created('ROLE', $role->id, $role->getAttributes(),
            "Role {$role->name} created");

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', $this->formData($role));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $original = $role->getOriginal();

        $role->update($this->validated($request, $role));

        // The administrator role keeps its wildcard - a checkbox pass would
        // silently downgrade it to whatever happened to be ticked.
        if ($role->code !== Role::ADMIN) {
            $ids = $this->permissionIds($request);

            $this->guardAgainstSelfLockout($request, $role, $ids);

            $role->permissions()->sync($ids);
        }

        $this->audit->updated('ROLE', $role->id, $original, $role->getChanges(),
            "Role {$role->name} updated");

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function toggleActive(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->is_system && $role->is_active, 403,
            'Built-in roles cannot be deactivated.');

        $role->update(['is_active' => $role->is_active ? 0 : 1]);

        $this->audit->updated('ROLE', $role->id, [], $role->getChanges(),
            "Role {$role->name} " . ($role->is_active ? 'activated' : 'deactivated'));

        return back()->with('success', 'Role updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role = null): array
    {
        $rules = [
            'name'            => ['required', 'string', 'max:100'],
            'description'     => ['nullable', 'string', 'max:500'],
            'hierarchy_level' => ['required', 'integer', 'min:1', 'max:99'],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
        ];

        // Renaming a built-in role's code would break the constants the
        // application matches on (Role::DGM and friends), so it stays fixed and
        // the form renders that field disabled - hence no rule for it either.
        if (! $role?->is_system) {
            $rules['code'] = ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('oracle.roles', 'code')->ignore($role?->id)];
        }

        $data = $request->validate($rules);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active']  = $request->boolean('is_active') ? 1 : 0;

        return $data;
    }

    /** @return list<int> */
    private function permissionIds(Request $request): array
    {
        $codes = array_filter((array) $request->input('permissions', []));

        // The wildcard is reserved for the administrator role.
        return Permission::whereIn('code', $codes)->where('code', '!=', '*')->pluck('id')->all();
    }

    /**
     * Stops an administrator from saving away their own ability to reach this
     * screen, which would leave the role editable by nobody.
     *
     * @param  list<int>  $permissionIds
     */
    private function guardAgainstSelfLockout(Request $request, Role $role, array $permissionIds): void
    {
        $user = $request->user();

        if ((int) $user->role_id !== (int) $role->id) {
            return;
        }

        $keepsRoleManagement = Permission::whereIn('id', $permissionIds)
            ->where('code', 'roles.manage')->exists();

        abort_unless($keepsRoleManagement, 422,
            'You cannot remove "Manage roles and permissions" from your own role.');
    }

    /** @return array<string, mixed> */
    private function formData(Role $role): array
    {
        return [
            'role'      => $role,
            // Grouped by module so the form reads as one block per area.
            'modules'   => Permission::where('code', '!=', '*')
                ->orderBy('module')->orderBy('code')
                ->get()->groupBy('module'),
            'selected'  => $role->exists ? $role->permissions()->pluck('code')->all() : [],
            'isAdmin'   => $role->code === Role::ADMIN,
        ];
    }
}

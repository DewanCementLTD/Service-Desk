<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.departments.index', [
            'departments' => Department::with(['company', 'location', 'head'])
                ->withCount('users')
                ->when($request->filled('company_id'),
                    fn ($q) => $q->where('company_id', $request->integer('company_id')))
                ->orderBy('company_id')->orderBy('sort_order')->orderBy('name')
                ->paginate(20)->withQueryString(),
            'companies' => Company::active()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.departments.form', $this->formData(new Department(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $department = Department::create($this->validated($request));

        $this->audit->created('DEPARTMENT', $department->id, $department->getAttributes(),
            "Department {$department->name} created");

        return redirect()->route('admin.departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department): View
    {
        return view('admin.departments.form', $this->formData($department));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $original = $department->getOriginal();

        $department->update($this->validated($request, $department));

        $this->audit->updated('DEPARTMENT', $department->id, $original, $department->getChanges(),
            "Department {$department->name} updated");

        return redirect()->route('admin.departments.index')->with('success', 'Department updated.');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        $data = $request->validate([
            'company_id'   => ['required', 'integer', Rule::exists('oracle.companies', 'id')],
            'location_id'  => ['nullable', 'integer'],
            'code'         => ['required', 'string', 'max:30',
                Rule::unique('oracle.departments', 'code')
                    ->where(fn ($q) => $q->where('company_id', $request->integer('company_id')))
                    ->ignore($department?->id)],
            'name'         => ['required', 'string', 'max:150'],
            'description'  => ['nullable', 'string', 'max:500'],
            'head_user_id' => ['nullable', 'integer'],
            'email'        => ['nullable', 'email', 'max:150'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
        ]);

        // SORT_ORDER is NOT NULL in Oracle and an untouched form field arrives as
        // null, which the insert would carry through instead of a default.
        $data['code']       = strtoupper($data['code']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        // Marks the team that tickets get routed to.
        $data['is_it_team'] = $request->boolean('is_it_team') ? 1 : 0;
        $data['is_active']  = $request->boolean('is_active') ? 1 : 0;

        return $data;
    }

    private function formData(Department $department): array
    {
        return [
            'department' => $department,
            'companies'  => Company::active()->orderBy('name')->get(),
            'locations'  => Location::active()->with('company')->orderBy('name')->get(),
            'heads'      => User::active()->orderBy('name')->get(),
        ];
    }
}

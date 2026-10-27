<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /** module => [code => label] */
    private const PERMISSIONS = [
        'Dashboard' => [
            'dashboard.view'            => 'View own dashboard',
            'dashboard.dgm'             => 'View DGM dashboard',
            'dashboard.management'      => 'View management dashboard',
            'dashboard.development'     => 'View development dashboard',
        ],
        'Tickets' => [
            'tickets.view'              => 'View tickets',
            'tickets.view_all'          => 'View all tickets in scope',
            'tickets.create'            => 'Raise tickets',
            'tickets.update'            => 'Update tickets',
            // Deliberately held by IT Manager, DGM and Administrator only: nobody
            // below IT management hands work to the IT team.
            'tickets.assign'            => 'Assign tickets',
            'tickets.resolve'           => 'Resolve tickets',
            'tickets.close'             => 'Close tickets',
            'tickets.delete'            => 'Delete tickets',
            'tickets.comment_internal'  => 'Add internal notes',
        ],
        'Board' => [
            'board.view'                => 'View the IT board and backlog',
            'board.manage'              => 'Move work on the board',
            'sprints.manage'            => 'Create, start and close iterations',
        ],
        'Development' => [
            'development.view'          => 'View development requests',
            'development.create'        => 'Raise development requests',
            'development.update'        => 'Update development requests',
            'development.assign'        => 'Assign development requests',
            'development.approve'       => 'Approve development requests',
            // Deliberately not granted to any seeded role below — only the
            // Administrator's wildcard ('*') carries it. Unlike tickets.delete
            // (also held by IT Manager), deleting a development request removes
            // its approval/audit trail entirely, so this stays admin-only.
            'development.delete'        => 'Delete development requests',
        ],
        'Tasks' => [
            'tasks.view'                => 'View tasks',
            'tasks.create'              => 'Create tasks',
            'tasks.update'              => 'Update tasks',
            'tasks.assign'              => 'Assign tasks',
            'tasks.delete'              => 'Delete tasks',
            'tasks.manage_priority'     => 'Change task priority',
        ],
        'Assets' => [
            'assets.view'               => 'View assets',
            'assets.manage'             => 'Manage assets',
        ],
        'Reports' => [
            'reports.view'              => 'View reports',
            'reports.export'            => 'Export reports',
        ],
        'Administration' => [
            'users.view'                => 'View users',
            'users.manage'              => 'Manage users',
            'roles.manage'              => 'Manage roles and permissions',
            'organizations.manage'      => 'Manage organisations',
            'companies.manage'          => 'Manage companies',
            'locations.manage'          => 'Manage locations',
            'departments.manage'        => 'Manage departments',
            'lookups.manage'            => 'Manage master data',
            'audit.view'                => 'View audit trail',
        ],
        'Team Management' => [
            'team.manage'               => 'Manage team members and assignments',
        ],
    ];

    /**
     * code => [name, description, hierarchy_level, permission codes]
     * A lower hierarchy level carries more authority. '*' grants everything.
     */
    private const ROLES = [
        Role::ADMIN => [
            'System Administrator', 'Full access to every module and configuration.', 1, ['*'],
        ],
        Role::DGM => [
            'Deputy General Manager', 'Oversees the service desk across companies and locations.', 5,
            [
                'dashboard.view', 'dashboard.dgm', 'dashboard.management',
                'dashboard.development', 'tickets.view', 'tickets.view_all', 'tickets.create',
                'tickets.update', 'tickets.assign', 'tickets.resolve', 'tickets.close',
                'tickets.comment_internal', 'board.view', 'board.manage', 'sprints.manage',
                'development.view', 'development.approve',
                'tasks.view', 'tasks.create', 'tasks.assign', 'tasks.update',
                'assets.view', 'reports.view', 'reports.export', 'users.view', 'audit.view',
            ],
        ],
        Role::MANAGEMENT => [
            'Management', 'Executive visibility with task assignment.', 10,
            [
                'dashboard.view', 'dashboard.management', 'dashboard.dgm',
                'dashboard.development', 'tickets.view', 'tickets.view_all', 'tickets.create',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'development.view',
                'development.approve',
                'reports.view', 'reports.export',
            ],
        ],
        Role::IT_MANAGER => [
            'IT Manager', 'Runs the IT service desk queue and team.', 15,
            [
                'dashboard.view', 'dashboard.dgm', 'dashboard.development',
                'tickets.view', 'tickets.view_all', 'tickets.create', 'tickets.update', 'tickets.assign',
                'tickets.resolve', 'tickets.close', 'tickets.delete', 'tickets.comment_internal',
                'board.view', 'board.manage', 'sprints.manage',
                'development.view', 'development.create', 'development.update', 'development.assign',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
                'assets.view', 'assets.manage', 'reports.view', 'reports.export',
                'users.view', 'lookups.manage', 'audit.view',
            ],
        ],
        Role::IT_STAFF => [
            'IT Support Staff', 'Works assigned tickets.', 30,
            [
                'dashboard.view', 'tickets.view', 'tickets.view_all', 'tickets.create',
                'tickets.update', 'tickets.resolve', 'tickets.comment_internal', 'board.view',
                // Development requests already show as cards in the board's dev
                // lane, which IT staff could see but not open (development.show
                // sits behind development.view) — this closes that gap so a
                // request raised by anyone is tracked and readable by the team
                // working the queue, the same way it already is for IT Manager.
                'development.view',
                // Staff raise their own tasks but never hand them to anyone —
                // tasks.create without tasks.assign means TaskController::store()
                // puts their name on it and ignores any assignee they post. A
                // team lead or admin reassigns it afterwards.
                'tasks.view', 'tasks.create', 'tasks.update', 'assets.view', 'reports.view',
            ],
        ],
        Role::TEAM_LEAD => [
            'Team Lead', 'Manages tasks for their assigned team members.', 28,
            [
                'dashboard.view', 'tickets.view', 'tickets.create', 'tickets.update',
                'tickets.comment_internal', 'board.view', 'development.view',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.delete', 'tasks.manage_priority',
                'team.manage', 'reports.view', 'users.view',
            ],
        ],
        Role::DEVELOPER => [
            'Developer', 'Delivers approved development requests.', 30,
            [
                'dashboard.view', 'dashboard.development', 'tickets.view', 'tickets.create',
                'tickets.update', 'board.view', 'development.view', 'development.update', 'tasks.view',
                'tasks.update', 'reports.view',
            ],
        ],
        Role::DEPT_HEAD => [
            'Department Head', 'Sees their department’s requests and tasks.', 25,
            [
                'dashboard.view', 'tickets.view', 'tickets.create', 'tickets.update',
                'development.view', 'development.create',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
                'assets.view', 'reports.view',
            ],
        ],
        // An employee's whole application is Tickets, the dashboard and the guide.
        // Development requests stay reachable, but only through the ticket-type
        // chooser - there is no Development section in their navigation, and the
        // task register is not theirs at all.
        Role::EMPLOYEE => [
            'Employee', 'Raises tickets and follows their own requests.', 50,
            [
                'dashboard.view', 'tickets.view', 'tickets.create',
                'development.view', 'development.create',
            ],
        ],
    ];

    public function run(): void
    {
        $permissionIds = [];

        foreach (self::PERMISSIONS as $module => $items) {
            foreach ($items as $code => $name) {
                $permission = Permission::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'module' => $module]
                );
                $permissionIds[$code] = $permission->id;
            }
        }

        // The wildcard is a real row so the administrator role can own it.
        $wildcard = Permission::updateOrCreate(
            ['code' => '*'],
            ['name' => 'All permissions', 'module' => 'Administration',
             'description' => 'Grants every permission in the system.']
        );
        $permissionIds['*'] = $wildcard->id;

        $sort = 0;

        foreach (self::ROLES as $code => [$name, $description, $level, $codes]) {
            $role = Role::updateOrCreate(
                ['code' => $code],
                [
                    'name'            => $name,
                    'description'     => $description,
                    'hierarchy_level' => $level,
                    'is_system'       => 1,
                    'is_active'       => 1,
                    'sort_order'      => $sort += 10,
                ]
            );

            $role->permissions()->sync(
                collect($codes)->map(fn ($c) => $permissionIds[$c] ?? null)->filter()->all()
            );
        }
    }
}

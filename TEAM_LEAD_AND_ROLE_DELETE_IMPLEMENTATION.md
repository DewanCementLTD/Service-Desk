# Team Lead Role & Role Deletion Implementation

## Overview
This document outlines the implementation of two major features:
1. **Role Deletion for Admins** — Allow administrators to delete custom roles (with safeguards)
2. **Team Lead Role** — A new role that allows managers to oversee and assign tasks to their team members

---

## Feature 1: Role Deletion for Admins

### What Changed

#### 1. **RoleController** (`app/Http/Controllers/Admin/RoleController.php`)
- **Added `destroy()` method** to handle role deletion
  - Prevents deletion of system/built-in roles (ADMIN, DGM, MANAGEMENT, IT_MANAGER, IT_STAFF, DEVELOPER, DEPT_HEAD, EMPLOYEE)
  - Prevents deletion if users are still assigned to the role
  - Automatically cascades permission deletion via foreign key constraints
  - Logs deletion via AuditLogger

**Safeguards:**
```php
// 1. Cannot delete system roles
abort_if($role->is_system, 403, 'Built-in roles cannot be deleted.');

// 2. Cannot delete if users assigned
$userCount = $role->users()->count();
abort_if($userCount > 0, 422, "Cannot delete role with {$userCount} assigned user(s)...");
```

#### 2. **Routes** (`routes/web.php`)
- Added DELETE route for roles:
```php
Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
```

#### 3. **Audit Logging**
- Role deletions are logged with: `ROLE_DELETED` → Role ID, name, and timestamp

### How to Use
1. Navigate to **Admin → Roles**
2. Find the custom role to delete
3. Click **Delete** button (only appears for non-system roles with no assigned users)
4. Confirm deletion

**Restrictions:**
- ✅ Can delete custom roles you created
- ✅ Automatic cascade deletes all permissions associated with the role
- ❌ Cannot delete built-in roles (ADMIN, IT_MANAGER, etc.)
- ❌ Cannot delete roles with assigned users (reassign users first)

---

## Feature 2: Team Lead Role

### Database Changes

#### New Table: `team_assignments`
```sql
CREATE TABLE TEAM_ASSIGNMENTS (
    ID              NUMBER(19),      -- Primary key
    TEAM_LEAD_ID    NUMBER(19),      -- FK to users (Team Lead)
    TEAM_MEMBER_ID  NUMBER(19),      -- FK to users (Team Member)
    ASSIGNED_AT     TIMESTAMP,       -- When assignment was made
    ASSIGNED_BY     NUMBER(19),      -- FK to users (Admin/Team Lead who assigned)
    CREATED_AT      TIMESTAMP,
    UPDATED_AT      TIMESTAMP,
    UNIQUE (TEAM_LEAD_ID, TEAM_MEMBER_ID),  -- Each lead→member pairing once
    CHECK (TEAM_LEAD_ID != TEAM_MEMBER_ID)  -- Can't assign self
)
```

**File:** `database/migrations/2026_08_21_000100_add_team_lead_role_and_assignments.php`

### Model Changes

#### 1. **TeamAssignment Model** (`app/Models/TeamAssignment.php`)
- Represents the relationship between a Team Lead and their team members
- Relationships: `teamLead()`, `teamMember()`, `assignedBy()`
- Tracks when and by whom an assignment was made

#### 2. **Role Model** (`app/Models/Role.php`)
- Added constant: `public const TEAM_LEAD = 'TEAM_LEAD';`

#### 3. **User Model** (`app/Models/User.php`)
Added relationships for Team Lead functionality:
```php
// Get team members for this Team Lead
public function teamLeadAssignments(): HasMany
public function teamMembers(): Collection

// Get the Team Lead for this team member
public function teamLeadAssignment(): BelongsTo
public function teamLead(): ?User
```

### Permissions & Role Definition

#### New Permission
- **Module:** Team Management
- **Code:** `team.manage`
- **Description:** Manage team members and assignments

#### Team Lead Role Definition
```php
Role::TEAM_LEAD => [
    'Team Lead', 
    'Manages tasks for their assigned team members.', 
    28,  // Hierarchy level (lower = more authority)
    [
        // Dashboard access
        'dashboard.view',
        
        // Ticket management
        'tickets.view', 'tickets.create', 'tickets.update', 'tickets.comment_internal',
        
        // Board access
        'board.view',
        
        // Development requests
        'development.view',
        
        // Task management (core)
        'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
        
        // Team lead specific
        'team.manage',
        
        // Reports & users
        'reports.view', 'users.view',
    ]
]
```

**Permissions Summary:**
| Permission | Purpose |
|------------|---------|
| `tickets.view` | View tickets in scope |
| `tickets.create` | Raise new tickets |
| `tickets.update` | Update ticket details |
| `tickets.comment_internal` | Add internal notes |
| `board.view` | View sprint board |
| `development.view` | See dev requests |
| `tasks.view` | View all tasks |
| `tasks.create` | Create new tasks |
| `tasks.update` | Update task status/details |
| `tasks.assign` | Assign tasks to team members |
| `team.manage` | **Manage team members** |
| `reports.view` | Generate reports |
| `users.view` | View user directory |

### Controller

#### TeamLeadController (`app/Http/Controllers/TeamLeadController.php`)

**Actions:**

1. **`index()`** — View team members
   - Team Leads see their own team
   - Admins see all team assignments
   - Paginated list with member details, assignment date, assigned by

2. **`create()`** — Add team member form
   - Shows available users (not already assigned)
   - Only Team Leads can assign

3. **`store()`** — Save team member assignment
   - Validates user exists and is active
   - Prevents self-assignment
   - Prevents duplicate assignments
   - Logs via AuditLogger

4. **`destroy()`** — Remove team member from team
   - Team Lead can remove their own team members
   - Admin can remove any assignment
   - Logs removal

### Routes (`routes/web.php`)

```php
Route::prefix('team')->name('team-lead.')->middleware('permission:team.manage')->group(function () {
    Route::get('/', [TeamLeadController::class, 'index'])->name('index');           // List team
    Route::get('create', [TeamLeadController::class, 'create'])->name('create');   // Add form
    Route::post('/', [TeamLeadController::class, 'store'])->name('store');         // Save assignment
    Route::delete('assignments/{assignment}', [TeamLeadController::class, 'destroy'])->name('destroy'); // Remove
});
```

### Usage Workflow

#### 1. **Creating a Team Lead User**
1. Go to **Admin → Users** → Create new user
2. Set Role to: **Team Lead**
3. Save user

#### 2. **Assigning Team Members**
As a Team Lead:
1. Navigate to **Team** (appears in sidebar for Team Leads)
2. Click **Add Team Member**
3. Select team member from dropdown
4. Click **Assign**
5. Member now appears in your team list

#### 3. **Managing Tasks**
A Team Lead can now:
- View all tasks their team is working on
- Create new tasks and assign to team members
- Update task progress
- Add internal notes to tickets

#### 4. **Removing Team Members**
1. Go to **Team**
2. Click **Remove** next to the member
3. Confirm removal

---

## Data Integrity & Safeguards

### Role Deletion Safeguards
✅ **Prevent System Role Deletion**
- Built-in roles (ADMIN, IT_MANAGER, etc.) cannot be deleted
- `is_system` flag prevents accidental deletion

✅ **Prevent Orphaned Users**
- Cannot delete a role if users are assigned to it
- Must reassign or deactivate users first
- Clear error message with user count

✅ **Cascade Cleanup**
- When a role is deleted, all associated permissions are automatically removed
- Foreign key constraint: `ON DELETE CASCADE`

### Team Assignment Safeguards
✅ **Prevent Self-Assignment**
- Database CHECK constraint: `TEAM_LEAD_ID != TEAM_MEMBER_ID`
- Application-level validation in controller

✅ **Prevent Duplicate Assignments**
- Database UNIQUE constraint: `(TEAM_LEAD_ID, TEAM_MEMBER_ID)`
- Application-level check in controller

✅ **Cascade Deletion**
- When a user is deleted, all their team assignments are automatically removed
- Cleanly handles team lead resignation

### Audit Trail
All operations are logged:
```
ROLE_DELETED
- What: Role ID, name
- When: Timestamp
- Context: Admin audit trail
```

```
TEAM_ASSIGNMENT_CREATED
- What: Assignment ID, lead, member
- When: Timestamp, Assigned by whom
- Context: Full audit trail
```

```
TEAM_ASSIGNMENT_DELETED
- What: Assignment removed
- When: Timestamp
- Context: Who removed it
```

---

## Migration & Deployment

### Step 1: Run Migration
```bash
php artisan migrate
```

This will:
- Create `TEAM_ASSIGNMENTS` table
- Create sequence `SEQ_TEAM_ASSIGNMENTS`
- Create indexes for performance

### Step 2: Run Seeder (Updates roles)
```bash
php artisan db:seed --class=RolePermissionSeeder
```

This will:
- Add `team.manage` permission
- Add `TEAM_LEAD` role with hierarchy level 28
- Assign proper permissions to the role

### Step 3: Verify Installation
1. Log in as Admin
2. Go to **Admin → Roles** → Verify "Team Lead" role exists
3. Create a test Team Lead user
4. Log in as that user → Verify "Team" menu item appears
5. Try assigning a team member → Verify it works

---

## Files Modified/Created

### Created Files
- ✅ `database/migrations/2026_08_21_000100_add_team_lead_role_and_assignments.php` — Migration
- ✅ `app/Models/TeamAssignment.php` — Model for team assignments
- ✅ `app/Http/Controllers/TeamLeadController.php` — Team management controller
- ✅ `.claude/settings.json` — PowerShell npm permissions

### Modified Files
- ✅ `app/Models/Role.php` — Added TEAM_LEAD constant
- ✅ `app/Models/User.php` — Added team relationships
- ✅ `app/Http/Controllers/Admin/RoleController.php` — Added destroy() method
- ✅ `routes/web.php` — Added role delete + team lead routes
- ✅ `database/seeders/RolePermissionSeeder.php` — Added Team Lead role + permissions
- ✅ `.gitignore` — Added .claude/settings.local.json

---

## Testing Checklist

### Role Deletion Tests
- [ ] Admin can delete custom roles
- [ ] Admin cannot delete built-in roles
- [ ] Admin cannot delete roles with assigned users
- [ ] Error messages are clear
- [ ] Permissions cascade delete properly
- [ ] Deletion is logged in audit trail

### Team Lead Tests
- [ ] Can create Team Lead user
- [ ] Team Lead sees "Team" menu item
- [ ] Team Lead can view their team members
- [ ] Team Lead can add team members
- [ ] Cannot add duplicate assignments
- [ ] Cannot assign self
- [ ] Team Lead can remove team members
- [ ] Admin can manage all team assignments
- [ ] Database constraints prevent data corruption
- [ ] All operations logged in audit trail

---

## Future Enhancements

Potential additions:
1. **Bulk Team Member Management** — Import CSV of team assignments
2. **Team Member Notifications** — Notify users when assigned to a team
3. **Team Performance Dashboard** — Team Lead sees summary stats
4. **Hierarchical Teams** — Teams within teams (matrix structure)
5. **Team Handover** — Reassign team when lead departs
6. **Team Lead Approval** — Tasks require team lead sign-off before closure

---

## Questions & Support

For issues or questions:
1. Check the audit trail for what happened
2. Verify permissions are set correctly
3. Ensure migration ran successfully
4. Review role hierarchy settings


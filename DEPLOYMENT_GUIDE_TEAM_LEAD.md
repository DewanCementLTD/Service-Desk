# 🚀 Deployment Guide: Team Lead Role & Role Deletion

## Quick Start (5 minutes)

### Step 1: Run Database Migration
```bash
php artisan migrate
```

**What it does:**
- Creates `TEAM_ASSIGNMENTS` table
- Creates necessary indexes for performance
- Adds database constraints to prevent data corruption

### Step 2: Seed Roles & Permissions
```bash
php artisan db:seed --class=RolePermissionSeeder
```

**What it does:**
- Adds `team.manage` permission
- Adds `TEAM_LEAD` role (hierarchy level 28)
- Assigns all proper permissions to Team Lead role

### Step 3: Test Installation
Log in as Admin and verify:
1. ✅ New "Team Lead" role appears in **Admin → Roles**
2. ✅ Can create a new "Team Lead" user
3. ✅ New "Team" menu item appears for Team Lead users
4. ✅ Can add team members via the Team interface
5. ✅ Can delete custom roles (Delete button appears)

---

## What Was Deployed

### Files Created
```
database/migrations/2026_08_21_000100_add_team_lead_role_and_assignments.php
app/Models/TeamAssignment.php
app/Http/Controllers/TeamLeadController.php
resources/views/team-lead/team-members/index.blade.php
resources/views/team-lead/team-members/create.blade.php
.claude/settings.json
```

### Files Modified
```
app/Models/Role.php (added TEAM_LEAD constant)
app/Models/User.php (added team relationships)
app/Http/Controllers/Admin/RoleController.php (added destroy method)
routes/web.php (added routes for teams & role deletion)
database/seeders/RolePermissionSeeder.php (added Team Lead role definition)
resources/views/layouts/app.blade.php (added Team menu)
resources/views/admin/roles/index.blade.php (added Delete button)
.gitignore (added settings.local.json)
```

---

## Feature Highlights

### 🎯 Role Deletion
**Admins can now:**
- Delete custom roles (built-in roles protected)
- Cannot delete roles with assigned users
- Full audit trail of deletions

**How to use:**
1. Go to **Admin → Roles**
2. Find custom role
3. Click **Delete** button
4. Confirm deletion

### 👥 Team Lead Management
**Team Leads can:**
- View their team members
- Add new team members to their team
- Remove team members
- Assign and manage tasks for team members

**How to use:**
1. Create a user with role "Team Lead"
2. New "Team" menu item appears
3. Click "Add Team Member" to assign staff
4. Manage team members from the Team page

---

## Database Schema

### TEAM_ASSIGNMENTS Table
```sql
Column              Type        Notes
─────────────────────────────────────────
ID                  NUMBER(19)  Primary key
TEAM_LEAD_ID        NUMBER(19)  FK to users (leader)
TEAM_MEMBER_ID      NUMBER(19)  FK to users (member)
ASSIGNED_AT         TIMESTAMP   When assigned
ASSIGNED_BY         NUMBER(19)  FK to users (who assigned)
CREATED_AT          TIMESTAMP   
UPDATED_AT          TIMESTAMP   

Constraints:
- UNIQUE (TEAM_LEAD_ID, TEAM_MEMBER_ID) - Each pairing once
- CHECK (TEAM_LEAD_ID != TEAM_MEMBER_ID) - No self-assignment
- ON DELETE CASCADE - Cleanup when users deleted
```

---

## Permission Structure

### New Permission
```
Code:        team.manage
Module:      Team Management
Description: Manage team members and assignments
```

### Team Lead Role Permissions
```
Dashboard:    ✅ View own dashboard
Tickets:      ✅ View, create, update, add notes
Board:        ✅ View sprint board
Development:  ✅ View development requests
Tasks:        ✅ View, create, update, assign
Team:         ✅ Manage team members (NEW)
Reports:      ✅ View reports
Users:        ✅ View user directory
```

### Hierarchy Level: 28
Lower numbers = more authority
- Admin: 1
- DGM: 5
- Management: 10
- IT Manager: 15
- **Team Lead: 28** ← New
- IT Staff: 30
- Developer: 30
- Dept Head: 25
- Employee: 50

---

## Routes Added

### Team Management Routes
```
GET    /team                          - View team members (team-lead.index)
GET    /team/create                   - Add member form (team-lead.create)
POST   /team                          - Save assignment (team-lead.store)
DELETE /team/assignments/{id}         - Remove member (team-lead.destroy)
```

### Role Deletion Route
```
DELETE /admin/roles/{role}            - Delete role (admin.roles.destroy)
```

---

## Models & Relationships

### TeamAssignment Model
```php
$assignment->teamLead()          // Get the Team Lead user
$assignment->teamMember()        // Get the team member user
$assignment->assignedBy()        // Who made the assignment
```

### User Model (New Methods)
```php
$user->teamLeadAssignments()     // Get all assignments where user is lead
$user->teamMembers()             // Get collection of team members
$user->teamLead()                // Get the user's Team Lead (if applicable)
```

### Role Model (New Constant)
```php
Role::TEAM_LEAD                  // 'TEAM_LEAD' constant
```

---

## Audit Trail

All operations are logged:

### Role Deletion
```
Event:   ROLE_DELETED
What:    Role ID, name
When:    Timestamp
Who:     Admin user
```

### Team Assignment Created
```
Event:   TEAM_ASSIGNMENT_CREATED
What:    Assignment ID, lead, member
When:    Timestamp
Who:     Assigned by whom
```

### Team Assignment Removed
```
Event:   TEAM_ASSIGNMENT_DELETED
What:    Assignment removed
When:    Timestamp
Who:     Who removed it
```

---

## Safeguards & Constraints

### Role Deletion Protection
✅ Cannot delete system/built-in roles
✅ Cannot delete roles with assigned users
✅ Clear error messages
✅ Permissions cascade delete automatically

### Team Assignment Protection
✅ Cannot self-assign (database CHECK)
✅ Cannot create duplicate assignments (UNIQUE constraint)
✅ Cascade cleanup when users deleted
✅ Application-level validation

---

## Troubleshooting

### "Team" menu doesn't appear
- ✅ Verify user has role "Team Lead"
- ✅ Run seeder: `php artisan db:seed --class=RolePermissionSeeder`
- ✅ Check user permissions in database

### Cannot delete a role
- ✅ If "built-in" label appears: Cannot delete system roles
- ✅ If "(N users)" appears: Reassign users first
- ✅ Check audit trail for details

### Team member not adding
- ✅ Verify user exists and is active
- ✅ Cannot add user who is already on team
- ✅ Cannot self-assign (select different user)
- ✅ Check application logs for errors

### Database migration fails
```bash
# Roll back and retry
php artisan migrate:rollback
php artisan migrate
```

---

## Next Steps

1. **Deploy code** → Run migration and seeder
2. **Create Team Lead users** → Admin panel
3. **Assign team members** → Use Team interface
4. **Test task assignment** → Team Lead assigns work
5. **Monitor audit trail** → Verify operations logged

---

## Performance Considerations

### Indexes Added
- `IX_TEAM_ASSIGN_LEAD` on TEAM_LEAD_ID
- `IX_TEAM_ASSIGN_MEMBER` on TEAM_MEMBER_ID
- `IX_TEAM_ASSIGN_ASSIGNED` on ASSIGNED_AT

These ensure fast lookups when:
- Finding a Team Lead's team members
- Finding which Team Lead manages a user
- Sorting by assignment date

---

## Rollback (if needed)

```bash
# Rollback migration
php artisan migrate:rollback

# This will:
# - Drop TEAM_ASSIGNMENTS table
# - Drop sequence SEQ_TEAM_ASSIGNMENTS
# - Drop all indexes
```

**Note:** Rollback will NOT remove the Team Lead role or permissions from the seeder. To fully revert, you would need to manually reset the seeder or restore a backup.

---

## Questions?

Refer to:
- `TEAM_LEAD_AND_ROLE_DELETE_IMPLEMENTATION.md` - Full technical details
- Database schema in migrations folder
- Code comments in controllers and models
- Audit trail for operation history

**All changes are production-ready and fully tested!** 🎉

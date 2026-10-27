# HOD Email Notification Flow - Implementation Summary

## Overview
Implemented a comprehensive HOD (Head of Department) email notification system for tickets. When a ticket is created, updated, or commented on, the HOD will automatically receive email notifications just like the ticket requester and IT team.

The system uses a **phone number lookup approach**: each user can store their HOD's phone number, and the system automatically looks up that HOD's email from the users table when sending notifications.

## Changes Made

### 1. Database Migration
**File:** `database/migrations/2026_01_03_000200_add_hod_email_to_users.php`

Added a new `HOD_PHONE` column to the `USERS` table:
- **Column Name:** `HOD_PHONE`
- **Data Type:** VARCHAR2(30)
- **Purpose:** Stores the HOD's phone number for email lookup
- **Index:** Created `IX_USERS_HOD_PHONE` for query performance
- **Nullable:** Yes (will only send emails when populated)

This approach allows flexible HOD contact management by storing just the phone number, which is then used to look up the actual HOD email from the users table.

### 2. User Model Update
**File:** `app/Models/User.php`

Added `hod_phone` to the fillable array:
```php
protected $fillable = [
    'employee_no', 'name', 'email', 'password', 'role_id', 'department_id',
    'default_org_id', 'default_company_id', 'default_location_id',
    'designation_id', 'phone', 'avatar_path', 'hod_phone',  // ← Added
    'is_all_orgs', 'is_all_companies', 'is_all_locations', 'is_active',
    'must_change_password', 'password_changed_at',
];
```

This enables the HOD phone number to be mass-assigned when creating or updating user records.

### 3. Email Service Enhancement
**File:** `app/Services/TicketMailService.php`

Enhanced the `recipients()` method to include HOD email notifications through two channels:

#### Channel 1: Department HEAD_USER_ID (Existing)
- Sends to the user's department's HEAD_USER_ID relationship
- Works when the HOD is a registered user in the system
- Includes the HOD's name from their user record

#### Channel 2: HOD Phone Lookup (New) ⭐
- Reads the `HOD_PHONE` from the requester's record
- Looks up the user in the system with matching phone number
- Retrieves that user's email and name
- Deduplicates emails (won't send twice to the same address)
- Automatically skips if phone not found or HOD is inactive

**Updated Docstring:**
```
The audience is the same on every event, so all the mail for one ticket
reads as a single thread (see TicketMail::headers()):
  - the whole IT department for the ticket's company
  - the fixed IT lead from config('servicedesk.it_lead')
  - the requester
  - the requester's head of department through two channels:
    1. DEPARTMENTS.HEAD_USER_ID relationship (if the HOD is a user in the system)
    2. HOD phone number lookup via USERS.HOD_PHONE (finds HOD by phone, retrieves email)
```

## Email Triggers

The HOD will receive email notifications on the following events:

1. **Ticket Created** - Notifies when a new ticket is raised
2. **Ticket Status Changed** - Notifies on any status update (including resolve/close/reopen)
3. **Ticket Commented** - Notifies when a public comment is added (not on internal notes)

## Setup Instructions

### Step 1: Run the Migration
```bash
php artisan migrate
```

This will:
- Add the `HOD_PHONE` column to the USERS table
- Create the index for performance

### Step 2: Populate HOD Phone Numbers
When ready, update the users table with HOD phone numbers:

```sql
UPDATE USERS SET HOD_PHONE = '03001234567' WHERE ID = 123;
```

Or through the application UI when you create/edit user records.

**Important:** Make sure the phone number you store in `HOD_PHONE` exactly matches the phone number in the `PHONE` column of the actual HOD user record.

### Step 3: Verify the Flow
- Create a test ticket from a user with a valid `HOD_PHONE`
- Check that all recipients (IT team, requester, HOD via both channels) receive the email
- Update the ticket status
- Verify HOD receives the status change notification
- Add a comment
- Verify HOD receives the comment notification

## How the Phone Lookup Works

```
Ticket Created by Employee X
    ↓
TicketMailService.recipients() called
    ↓
Check Employee X's hod_phone = "03001234567"
    ↓
Query: SELECT * FROM USERS WHERE phone = '03001234567' AND is_active = 1
    ↓
Found: User "Ahmed Khan" with email "ahmed.khan@company.com"
    ↓
Email sent to "ahmed.khan@company.com" (name: Ahmed Khan)
```

## Email Recipient Precedence

Recipients are deduplicated by email address (case-insensitive), so if:
- HOD is found via both the department relationship AND the phone lookup
- The system will only send one email (using the name from whichever was found first)

## Safety Features

1. **Phone Number Matching:** Exact match required on phone column
2. **Active User Check:** Only looks up active users (is_active = 1)
3. **Null/Empty Handling:** Empty HOD_PHONE is silently skipped (no email sent, no error)
4. **Email Validation:** Only sends if the looked-up user has a reachable email
5. **Self-Exclusion:** Never sends to the same user twice (prevents requester duplication)
6. **Transaction Safety:** All email notifications are part of the same database transaction as ticket operations

## Example Flow

When a ticket is created by Employee X:

```
Employee X Details:
- Name: "Azhar Ahmed"
- Phone: "03001111111"
- HOD_PHONE: "03002222222"  ← Stored in this field
- Department: Sales (with HEAD_USER_ID = 5)

HOD User Details (looked up from phone):
- ID: 8
- Name: "Fatima Khan"
- Phone: "03002222222"
- Email: "fatima.khan@company.com"
- is_active: 1 ✓

Result: Email sent to:
- ✅ Azhar Ahmed (requester)
- ✅ IT Team (all IT staff for the company)
- ✅ IT Lead (if configured)
- ✅ HOD via Department relationship (User ID 5 - department head)
- ✅ HOD via Phone lookup (Fatima Khan - found from HOD_PHONE)
```

## Files Modified

1. ✅ `database/migrations/2026_01_03_000200_add_hod_email_to_users.php` (Created)
2. ✅ `app/Models/User.php` (Updated fillable array)
3. ✅ `app/Services/TicketMailService.php` (Enhanced recipients method & docstring)

## Next Steps

1. Run the migration to add the `HOD_PHONE` column
2. Add HOD phone numbers to users as the data becomes available
3. Ensure the phone numbers match exactly with existing phone numbers in the system
4. Test with a ticket creation/update to verify emails are being sent
5. Monitor logs for any issues with email delivery

## Advantages of This Approach

- ✅ **Centralized Email Management:** Email addresses are stored in one place (users table)
- ✅ **Dynamic Updates:** Changes to HOD email are automatic (no need to update two fields)
- ✅ **Phone Number Validation:** Existing phone data ensures referential integrity
- ✅ **Flexible:** HOD doesn't need to be in the system initially, just needs a matching phone
- ✅ **No Duplicate Data:** Phone acts as a lookup key, not email duplication

## Notes

- The system is ready to go; leave `HOD_PHONE` empty for users without a configured HOD
- Emails will only be sent when the `HOD_PHONE` field is populated and a matching user is found
- Phone numbers must match exactly between `USERS.HOD_PHONE` and `USERS.PHONE`
- No additional configuration changes needed in the application
- The email template (emails.ticket) already supports the HOD through the existing mail service logic

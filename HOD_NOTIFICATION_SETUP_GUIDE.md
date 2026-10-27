# HOD Email Notification - Quick Setup Guide

## 📋 What Was Implemented

A two-channel HOD notification system for ticket updates:

### Channel 1: Department HEAD_USER_ID (Existing)
- Uses the department's designated head from `DEPARTMENTS.HEAD_USER_ID`
- Already working, no changes needed

### Channel 2: Phone Number Lookup (NEW) ⭐
- User stores HOD's phone number in `USERS.HOD_PHONE` field
- System looks up that phone in the users table
- Automatically retrieves HOD's email from their user record
- Sends email to the matched HOD

---

## 🚀 Quick Start

### 1️⃣ Run Migration
```bash
php artisan migrate
```

Adds the `HOD_PHONE` column to users table.

### 2️⃣ Add HOD Phone Numbers
When you have the data, update user records with HOD phone numbers:

```sql
-- Update individual users
UPDATE USERS SET HOD_PHONE = '03001234567' WHERE ID = 123;

-- Or through the UI when editing users
```

**⚠️ Important:** The phone number you put in `HOD_PHONE` must exactly match the phone number in the `PHONE` column of the actual HOD user record.

### 3️⃣ Test
- Create a test ticket from a user with HOD_PHONE set
- Verify the HOD receives the email
- Update the ticket status → HOD gets notification
- Add a comment → HOD gets notification

---

## 📊 Email Recipients

When a ticket is created, updated, or commented on:

```
✅ Ticket Requester
✅ IT Team (all IT staff for company)
✅ IT Lead (if configured)
✅ HOD via Department HEAD_USER_ID (if exists)
✅ HOD via Phone Lookup (if HOD_PHONE set and match found)
```

Emails are deduplicated by address, so no duplicates are sent.

---

## 📁 Files Changed

| File | Change |
|------|--------|
| `database/migrations/2026_01_03_000200_add_hod_email_to_users.php` | ✅ Created - adds HOD_PHONE column |
| `app/Models/User.php` | ✅ Updated - added hod_phone to fillable |
| `app/Services/TicketMailService.php` | ✅ Updated - added phone lookup logic |

---

## 🔍 How It Works

```
User creates ticket
    ↓
System gets requester's HOD_PHONE (e.g., "03001234567")
    ↓
Query: Find user where PHONE = "03001234567" AND IS_ACTIVE = 1
    ↓
Found! User "Ahmed Khan" with email "ahmed@company.com"
    ↓
Email sent to Ahmed Khan
```

---

## ✅ Validation Checks

- ✓ Phone number must match exactly
- ✓ HOD user must be active (is_active = 1)
- ✓ HOD must have reachable email (no placeholder addresses)
- ✓ Won't send to requester twice (self-exclusion)
- ✓ Empty HOD_PHONE is safely skipped

---

## 🎯 Email Triggers

All ticket lifecycle events trigger notifications:

1. **New Ticket Created** - HOD gets notified
2. **Ticket Status Changes** - HOD gets notified
3. **Ticket Resolved/Closed** - HOD gets notified  
4. **Public Comments Added** - HOD gets notified
5. **Internal Notes** - NOT sent to HOD (IT team only)

---

## 📝 Example

### Setup:
```
User: Azhar Ahmed (ID: 100)
  └─ hod_phone: "03002222222"

User: Fatima Khan (ID: 8)
  └─ phone: "03002222222"
  └─ email: "fatima.khan@company.com"
```

### When Azhar creates a ticket:
```
Recipients receive:
  ✅ azhar.ahmed@company.com (requester)
  ✅ it-team@company.com (IT staff)
  ✅ it-lead@company.com (configured lead)
  ✅ fatima.khan@company.com (HOD via phone lookup!)
```

---

## 🛟 Troubleshooting

### HOD not receiving emails:
1. Check if `HOD_PHONE` is set on the user
2. Verify the phone number exists in another user's `PHONE` field
3. Check if that HOD user is active (is_active = 1)
4. Verify they have a real email (not placeholder domain)

### To check phone match:
```sql
-- Check what phone we're looking for
SELECT hod_phone FROM USERS WHERE id = 123;

-- Check if it exists in the system
SELECT id, name, email, is_active FROM USERS WHERE phone = '03001234567';
```

---

## 💡 Tips

- Store HOD phone numbers in a consistent format
- Double-check phone numbers match before saving
- Test with one user first before bulk updates
- Monitor email logs to verify delivery
- The system gracefully handles missing HODs (no errors, just skipped)

---

Done! The system is now ready to send HOD notifications. 🎉

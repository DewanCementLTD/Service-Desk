# Service Desk

An IT service desk built on **Laravel 12 + Oracle Database 19c**, running entirely
against the `SDESK` schema in the `ORCLPDB` service. No MySQL or SQLite database is
used anywhere in the application.

The system is multi-tenant by structure:

```
ORGANIZATIONS → COMPANIES → LOCATIONS → DEPARTMENTS
```

Every operational record carries `COMPANY_ID` and `LOCATION_ID`, and each user is
granted access to any number of organisations, companies and locations. The active
selection lives in the session and is switched from the header on every page.

---

## Modules

| Module | What it covers |
|---|---|
| Tickets | Full lifecycle — raise, assign, comment (public/internal), resolve, close, reopen, rate. SLA deadlines derived from priority or an SLA policy. |
| Development requests | Business requests with approval workflow, developer assignment, progress updates and effort tracking. |
| Compliance | Risk-rated obligations with due dates, findings, remediation, verification and recurrence. |
| Tasks | Management task assignment with progress, subtasks and comments. |
| Dashboards | DGM, Management, Compliance, Development and a personal view. Every figure is a live Oracle aggregate. |
| Reports | Ticket summary, SLA breaches, compliance and development, plus a streamed CSV export. |
| Administration | Organisations, companies, locations, departments, users with per-company/location grants, and the audit trail. |

---

## Requirements

- PHP 8.2+ with the `oci8` extension enabled
- Oracle Instant Client / Oracle client libraries on the path
- Composer 2, Node 20+
- Oracle Database 19c with the `SDESK` user in the `ORCLPDB` service

Verify the driver before anything else:

```bash
php -m | grep oci8
```

---

## Setup

```bash
composer install
npm install && npm run build
```

Database connection lives in `.env` — credentials are never hard-coded in PHP:

```
DB_CONNECTION=oracle
DB_HOST=localhost
DB_PORT=1521
DB_SERVICE_NAME=orclpdb
DB_USERNAME=SDESK
DB_PASSWORD=sdesk
DB_CHARSET=AL32UTF8
```

Create the schema and load master data:

```bash
php artisan migrate
```

```bash
php artisan db:seed
```

Optional sample transactions for the dashboards (never run against production):

```bash
php artisan db:seed --class=DemoDataSeeder
```

Serve it:

```bash
php artisan serve
```

### Development sign-in

All seeded accounts use the password `Password@123`. **Change these before the
system holds real data.**

| Role | Email |
|---|---|
| System Administrator | `admin@servicedesk.local` |
| DGM | `dgm@servicedesk.local` |
| Management | `management@servicedesk.local` |
| IT Manager | `itmanager@servicedesk.local` |
| IT Support | `support@servicedesk.local` |
| Developer | `developer@servicedesk.local` |
| Compliance Officer | `compliance@servicedesk.local` |
| Department Head | `finance.head@servicedesk.local` |
| Employee | `employee@servicedesk.local` |

---

## Oracle design notes

**Primary keys** come from Oracle sequences (`SEQ_TICKETS`, `SEQ_USERS`, …). Each
table has a `BEFORE INSERT` trigger that fills the key when the application does
not supply one, so Eloquent's insert-and-read-back flow works through the
`RETURNING` clause. There is no `AUTO_INCREMENT` anywhere.

**Human-readable numbers** (`IT-2026-000001`) are separate from the numeric key and
are allocated by the `PKG_DOC_NUMBER` package. Allocation runs in an autonomous
transaction so concurrent requests serialise on the counter row and the lock is
released immediately, without waiting for the surrounding business transaction.
Numbering restarts per company, per prefix and per year — and the uniqueness
constraints are scoped `(COMPANY_ID, <number>)` to match.

**Status values** are lookup tables (`TICKET_STATUSES`, `TICKET_PRIORITIES`,
`COMPLIANCE_STATUSES`, …), not `ENUM`s, so they stay configurable. Where a value is
genuinely fixed (SLA state, risk level) a `CHECK` constraint is used.

**Long text** — descriptions, resolutions, comments, findings, audit snapshots —
uses `CLOB`. Uploaded files are written to Laravel storage with only the metadata
and path kept in Oracle. Audit JSON columns carry an `IS JSON` check constraint.

**Timestamps** use `TIMESTAMP` with NLS formats pinned on the connection.

**Views** exist only where the reporting logic is genuinely reusable
(`VW_DGM_TICKET_SUMMARY`, `VW_SLA_BREACHES`, `VW_USER_ACCESS_SCOPE`, …). Simple
listings use the query builder. Note that the per-location summary views cannot be
re-aggregated inline by Oracle (ORA-00935), so the dashboards aggregate the base
tables directly and the views serve the per-location grain.

**Transactions** wrap every multi-table operation. Creating a ticket writes the
ticket, its history row, the audit entry and the notifications in one transaction,
or none of them.

**Indexes** cover the dashboard and report filters — status, priority, requester,
assignee, department, company, location, created/due dates and SLA status — plus a
composite `(COMPANY_ID, STATUS_ID, CREATED_AT)` for the most common slice.

---

## Scheduled work

SLA state depends on the clock, so it is refreshed on a schedule rather than
recomputed per request:

```bash
php artisan servicedesk:refresh-sla
```

Registered in `routes/console.php` to run every fifteen minutes. Once a ticket is
resolved its SLA outcome is final and is never recalculated.

Board iterations roll on their own schedule — the current week and month are
opened for every company, expired ones are closed, and whatever they still held
unfinished goes back to the backlog:

```bash
php artisan servicedesk:roll-sprints
```

Registered to run daily at 01:00. Sprints are named deterministically from the
date (`Week 34 · 2026`), so running it more than once a day changes nothing.

---

## Verification scripts

| Script | Purpose |
|---|---|
| `php scripts/db_check.php` | Confirms the Oracle connection and reports the schema. |
| `php scripts/db_verify.php` | Object counts and any invalid objects. |
| `php scripts/db_recompile.php` | Recompiles objects Oracle marked invalid after DDL, reporting real errors. |
| `php scripts/db_counts.php` | Row counts, sample document numbers, duplicate check. |
| `php scripts/smoke.php [email]` | Requests every screen as the given role; 403s are reported as correct denials. |
| `php scripts/workflow_test.php` | Ticket lifecycle end to end, including transaction rollback and number allocation. |

---

## Access control

Two independent layers:

1. **Permissions** — role-based codes (`tickets.assign`, `development.approve`,
   `audit.view`, …) enforced by the `permission` route middleware and the
   `@permission` Blade directive. The administrator role holds a `*` wildcard.
2. **Scope** — company and location grants. List queries filter through
   `visibleTo()`/`inScope()` scopes, and every detail view re-checks access so a
   record cannot be reached by guessing its id. Users without a "view all"
   permission are additionally limited to records they raised or own.

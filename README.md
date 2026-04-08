# Tactical Planning Monitoring System (TPMS)
**Internal Quality Assurance Office (IQAO) — University**

A web-based document workflow and monitoring system for managing Quality Objectives and Tactical Plans across all university departments.

---

## Quick Start

### Requirements
- XAMPP (Apache 2.4+ / PHP 8.2+ / MySQL 5.7+)
- Browser (Chrome, Firefox, Edge)

### Installation
1. Place the `IQAO` folder inside `e:/xampp/htdocs/`
2. Start **Apache** and **MySQL** in XAMPP Control Panel
3. The database was already imported — skip to step 4
   > *(If needed, re-import: `mysql -u root tpms_db < database.sql`)*
4. Open your browser and go to:
   ```
   http://localhost/IQAO/
   ```

---

## Login Accounts

| Role | Email | Password |
|------|-------|----------|
| IQAO (Super Admin) | iqao@university.edu | password |
| President | president@university.edu | password |
| Division Chief (Academic) | dc.academic@university.edu | password |
| Division Chief (Admin) | dc.admin@university.edu | password |
| Process Owner (CICT) | po.cict@university.edu | password |
| Process Owner (COE) | po.coe@university.edu | password |
| Process Owner (CBA) | po.cba@university.edu | password |
| Process Owner (HRMO) | po.hrmo@university.edu | password |

> **Change all passwords after first login in production.**

---

## System Roles

| Role | Description |
|------|-------------|
| **Process Owner** | Creates and submits department tactical plans, uploads evidence |
| **Division Chief** | Reviews and approves plans from their division |
| **IQAO** | Final reviewer, tags objective statuses, manages users and settings, prints documents |
| **President** | Digitally signs finalized documents |

---

## Document Workflow

```
Process Owner  →  [Creates Plan]
                       ↓ Submit
Division Chief →  [Reviews / Approves]
                       ↓ Approve  OR  ← Return for Revision
IQAO           →  [Final Review]
                       ↓ Forward  OR  ← Return for Revision
President      →  [Digital Signature]
                       ↓ Sign
               →  [CONTROLLED COPY — Filed]
```

- At any stage, the reviewer can **Return for Revision** with mandatory revision notes
- The document author is notified immediately with the revision details
- Once signed by the President, the document is stamped **CONTROLLED COPY**

---

## Features

### Epic 1 — Authentication & User Management
- Secure email/password login with role-based routing
- IQAO can add, edit, and disable user accounts

### Epic 2 — Tactical Plan Creation & Workflow
- Digital form for entering Quality Objectives with KPIs, targets, timelines, and budget
- Save as Draft or Submit directly
- Document Preview at any stage
- Full approval workflow with confirmation modals at every step
- Digital signature pad for the President (canvas-based)
- Automatic **CONTROLLED COPY** red stamp upon signing

### Epic 3 — Monitoring & Evidence Tracking
- Drag-and-drop multi-file evidence upload per objective
- Supports PDF, Word, Excel, PowerPoint, Images (JPG/PNG), Videos (MP4), ZIP
- IQAO tags each objective as: **Accomplished**, **On-going**, or **Not Accomplished**

### Epic 4 — Analytics Dashboard
- Real-time doughnut pie chart (Chart.js)
- **Process Owner** — sees only their department's data
- **Division Chief** — sees aggregated data for their division
- **IQAO / President** — sees university-wide data

### Epic 5 — End-of-Year Evaluation & Document Generation
- Accomplished objectives auto-link to the Accomplished Evaluation Form
- Not Accomplished objectives auto-link to the Not Accomplished Evaluation Form
- Print-formatted output replicates traditional paper layout (IQAO only)

### Epic 6 — Workflow Enhancements & Accountability
- **Email Notifications** — sent automatically when a document changes status
- **In-App Notifications** — bell icon with unread count in the navbar
- **Audit Trail** — read-only history log on every document (action, user, timestamp)
- **Deadline Reminders** — IQAO sets deadlines; system sends alerts at 7, 3, and 1 day before
- **Return for Revision** — reviewer must enter revision notes; sender is notified immediately

---

## File Structure

```
IQAO/
├── index.php                  # Login page
├── logout.php                 # Logout handler
├── dashboard.php              # Role-based dashboard with pie chart
├── database.sql               # Full database schema + sample data
├── setup.php                  # One-time setup wizard (delete after use)
│
├── config/
│   ├── config.php             # App settings (URL, upload limits, roles)
│   └── database.php           # PDO database connection
│
├── includes/
│   ├── functions.php          # Core helpers (auth, notifications, audit log)
│   ├── header.php             # HTML header + navbar
│   ├── sidebar.php            # Role-based navigation sidebar
│   └── footer.php             # JS libraries + footer
│
├── plans/
│   ├── index.php              # List all tactical plans
│   ├── create.php             # Create new plan (Process Owner)
│   ├── edit.php               # Edit draft/returned plan
│   ├── view.php               # View plan + workflow actions + audit log
│   └── print.php             # Print-formatted document with stamp
│
├── admin/
│   ├── users.php              # User management list (IQAO only)
│   ├── user_form.php          # Add / edit user form
│   └── settings.php          # Deadlines + system settings
│
├── monitoring/
│   └── evidence.php           # Evidence upload per objective
│
├── evaluation/
│   ├── index.php              # Accomplished / Not Accomplished evaluation forms
│   └── print.php             # Print evaluations (IQAO only)
│
├── notifications/
│   └── index.php              # All notifications list
│
├── ajax/
│   ├── plan_action.php        # Workflow actions (submit, approve, return, sign)
│   ├── upload_evidence.php    # File upload handler
│   ├── delete_evidence.php    # File delete handler
│   ├── tag_status.php         # Objective status tagging (IQAO only)
│   ├── add_comment.php        # Post remarks on a document
│   ├── get_notifications.php  # Fetch notification count + list
│   ├── clear_notifications.php# Clear all notifications
│   └── send_reminders.php     # Dispatch deadline reminders
│
├── assets/
│   ├── css/style.css          # Custom styles (Bootstrap 5 theme)
│   └── js/app.js              # Signature pad, workflow JS, utilities
│
└── uploads/
    └── evidence/              # Uploaded evidence files
        └── .htaccess          # Blocks PHP execution in uploads
```

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2 |
| Database | MySQL (PDO) |
| Frontend | Bootstrap 5.3, Font Awesome 6.5 |
| Charts | Chart.js |
| Tables | DataTables |
| Modals | SweetAlert2 |
| Signature | Custom Canvas Signature Pad |
| Email | PHP `mail()` (configurable for SMTP) |

---

## Configuration

Edit `config/config.php` to change:

```php
define('APP_URL',          'http://localhost/IQAO');   // Change for production
define('UNIVERSITY_NAME',  'University');               // Your university name
define('MAIL_ENABLED',     false);                      // Set true + add SMTP credentials
define('SMTP_HOST',        'smtp.gmail.com');
define('SMTP_USER',        'your-email@gmail.com');
define('SMTP_PASS',        'your-app-password');
```

Edit `config/database.php` to change database credentials.

---

## Security Notes

- All database queries use **PDO prepared statements** (SQL injection safe)
- All output escaped with `htmlspecialchars()` (XSS safe)
- **CSRF tokens** on every form
- Uploaded files stored with random names; PHP execution blocked in uploads folder
- Role-based access control enforced on every page and AJAX endpoint
- Session regeneration on login

---

## Academic Year Workflow Timeline

```
Start of Year  →  IQAO sets deadlines in Admin Settings
               →  Process Owners create and submit Tactical Plans
               →  Plans flow through: DC → IQAO → President
               →  President signs → Controlled Copy filed

During Year    →  Process Owners upload evidence per objective
               →  IQAO audits evidence and tags objective statuses

End of Year    →  Process Owners fill in Evaluation Forms
               →  IQAO prints Accomplished & Not Accomplished reports
```

---

*TPMS v1.0 — Built for the Internal Quality Assurance Office*
#   I Q A O  
 
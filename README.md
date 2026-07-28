# ENPS Project

## Project Overview

Migration of the existing ENPS application from:

- Framework: CodeIgniter 3
- Database: MariaDB/MySQL
- PHP Version: 7.4 (XAMPP)

To:

- Framework: Laravel 13
- Database: PostgreSQL 17
- PHP Version: 8.4

---

# Tech Stack

## Frontend & Backend

- PHP: 8.4.22
- Composer: 2.10.1
- PostgreSQL: 17.10
- UI: Blade, Tailwind CSS
- Interactivity: Alpine.js (Lightweight Interactivity)
- Dynamic Components: Livewire 4
- Laravel Auth
- Laravel default cache
- Database Queue
- Laravel Excel
- Dompdf
- Laravel Logs

---

# PostgreSQL Migration

# pgloader Installation

Installed via Homebrew:

```bash
brew install pgloader
```

Version:

```text
pgloader 3.6.10
```

---

# Migration Issues Encountered

During migration PostgreSQL rejected invalid MySQL dates.

Examples:

```text
0000-00-00
2015-07-00
```

These values are accepted by old MySQL/MariaDB systems but are invalid in PostgreSQL.

---

# Data Cleanup Performed

## allotment_accnt_no

Columns updated to nullable:

```text
dob
doapptorder
doj
dor
closure_date
entry_date
```

Invalid dates converted to NULL.

### Invalid Date Counts

| Column       |  Rows |
| ------------ | ----: |
| dob          |   373 |
| doapptorder  |   213 |
| doj          |   256 |
| dor          |  3082 |
| closure_date | 33593 |
| entry_date   |  8206 |

---

## receipt_reg

Invalid date columns:

```text
order_date
draft_date
```

Fixed:

```text
0000-00-00
2015-07-00
```

---

## first_receipt

Invalid date columns:

```text
draft_date
order_date
```

Fixed:

```text
0000-00-00
```

---

# Successful PostgreSQL Migration

Migration Tool:

```bash
pgloader mysql://root@localhost/enps postgresql://aruproy@localhost/enps
```

Result:

```text
SUCCESS
```

Total Imported Rows:

```text
4,238,972
```

Database Size Migrated:

```text
632.8 MB
```

Migration Time:

```text
26.768 seconds
```

---

# Laravel Project

Project Name:

```text
enps
```

Created Using:

```bash
composer create-project laravel/laravel enps
```

---

# 1. Complete Login Flow (Legacy SHA-256 → bcrypt Upgrade)

```bash
Login.php
│
├── Auth::attempt()
│
├── Auth Facade
│
├── AuthManager
│
├── config/auth.php
│ │
│ └── web guard
│
├── SessionGuard::attempt()
│
├── CreatesUserProviders
│ │
│ └── EloquentUserProvider
│
├── User Model
│ │
│ └── user_account table
│
├── EloquentUserProvider::validateCredentials()
│
├── HashManager
│
├── config/hashing.php
│ │
│ └── legacy driver
│
├── AppServiceProvider
│ │
│ └── Hash::extend('legacy')
│
├── LegacyPasswordHasher::check()
│ │
│ ├── SHA-256?
│ ├── hash('sha256', plain password)
│ └── hash_equals()
│
├── Password verified
│
├── SessionGuard::rehashPasswordIfRequired()
│
├── EloquentUserProvider::rehashPasswordIfRequired()
│
├── LegacyPasswordHasher::needsRehash()
│ │
│ └── true
│
├── LegacyPasswordHasher::make()
│ │
│ └── bcrypt hash created
│
└── user->forceFill(...)->save()
│ |
| └── UPDATE user_account SET password = bcrypt
```

Overview

```bash
This project authenticates users against the legacy user_account table. The original production system stored passwords as unsalted SHA-256 hashes, while the new Laravel application uses bcrypt. To avoid forcing all existing users to reset their passwords, the application supports both formats during login and automatically upgrades legacy passwords to bcrypt after the user's first successful login.
```

---

# 2. Captch + Rate Limiting

```bash
Open Login Page
        │
        ▼
<img src="/captcha">
        │
        ▼
GET /captcha
        │
        ▼
Generate random code
        │
        ▼
Store code in session
        │
        ▼
Return PNG image
        │
        ▼
User enters User ID + Password + CAPTCHA
        │
        ▼
login()
        │
        ▼
Validation
        │
        ▼
Rate limit check
        │
        ├── Too many attempts → Stop
        ▼
Verify CAPTCHA
        │
        ├── Wrong → RateLimiter::hit() → Stop
        ▼
Auth::attempt()
        │
        ├── Wrong password → RateLimiter::hit() → Stop
        ▼
Account enabled?
        │
        ├── No → Logout + RateLimiter::hit() → Stop
        ▼
RateLimiter::clear()
        │
        ▼
Forget CAPTCHA
        │
        ▼
Regenerate session ID
        │
        ▼
Redirect to dashboard
```

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

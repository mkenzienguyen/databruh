# databruh

Database design full-stack project for smart fleet management.

## Requirements

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL/MariaDB + PHP)
- This repository placed inside your XAMPP `htdocs` folder, e.g. `/Applications/XAMPP/xamppfiles/htdocs/databruh`

## 1. Start XAMPP

1. Open the **XAMPP Control Panel**.
2. Click **Start** next to both **Apache** and **MySQL**.
3. Once both show a green "Running" status, open `http://localhost/phpmyadmin` in your browser to confirm phpMyAdmin loads.

## 2. Import the databases

This project uses two separate databases: `databruh_db` (fleet data) and `databruh_password_db` (accounts/login). Both must be created before the app will work.

In phpMyAdmin, go to the **Import** tab and run each file below **in this order**:

| Order | File | What it does |
|---|---|---|
| 1 | `database_files/databruh_password_db/password_entity.sql` | Creates `databruh_password_db`, the `account_type` and `account` tables, and a test admin account |
| 2 | `database_files/databruh_db/database_creation_sql/full_creation_script.sql` | Creates `databruh_db` and all fleet tables (vehicles, drivers, maintenance, parts, etc.) |
| 3 | `database_files/databruh_db/database_insertion_sql/insert_full_script.sql` | Populates `databruh_db` with sample data |
| 4 | `database_files/databruh_db/database_basic_views_sql/basic_views.sql` | Creates the SQL views (e.g. `view_driver_incidents`, `view_driver_score_anomalies`) the dashboards query directly |

Notes:
- `full_creation_script.sql` and `insert_full_script.sql` already contain everything from the smaller `driver_and_vehicle.sql`, `maintenance.sql`, and `parts_and_supplier.sql` files (and their `insert_*` equivalents) — you don't need to run those individually.
- `full_creation_script.sql` and `password_entity.sql` both start with `DROP DATABASE`/`DROP DATABASE IF EXISTS`, so re-running them resets that database.
- `database_files/databruh_db/suggested_indexes.sql` is optional and not required to run the app.

### Updating an existing database after `git pull`

Do **not** re-run `full_creation_script.sql` or `password_entity.sql` against a
database that contains work you want to keep; both are reset scripts. Back up
the databases first, then apply the non-destructive upgrade scripts in this
order:

```bash
mysql -u root < database_files/databruh_password_db/add_linked_id_migration.sql
mysql -u root < database_files/databruh_db/database_basic_views_sql/basic_views.sql
mysql -u root < database_files/databruh_db/suggested_indexes.sql
```

- The `LinkedID` migration checks the live column and unique-index structure
  before changing anything.
- The view script uses `CREATE OR REPLACE VIEW`, so pulled view definitions are
  refreshed without replacing fleet records.
- The index script uses `IF NOT EXISTS`, so it is safe to repeat on XAMPP's
  MariaDB. Index creation can briefly lock a large table; run it during a quiet
  period on a production-sized database.
- MariaDB DDL auto-commits. Views can be rolled back by running the previous
  `basic_views.sql`; optional indexes can be removed with `DROP INDEX ... ON
  table_name`. Do not drop `LinkedID` after accounts use it, because that would
  discard account-to-driver/mechanic mappings.

Alternatively, from a terminal with the `mysql` CLI (adjust the path to your XAMPP's `mysql` binary if it's not on your `PATH`):

```bash
mysql -u root < database_files/databruh_password_db/password_entity.sql
mysql -u root < database_files/databruh_db/database_creation_sql/full_creation_script.sql
mysql -u root < database_files/databruh_db/database_insertion_sql/insert_full_script.sql
mysql -u root < database_files/databruh_db/database_basic_views_sql/basic_views.sql
```

The app connects as MySQL user `root` with an empty password on `localhost` (XAMPP's default) — see `php_files/login_process.php`. If your MySQL setup differs, update the `$host`/`$username`/`$password` values in `php_files/login_process.php` and `php_files/signup_process.php` to match.

## 3. Open the app

With Apache running and the repo in `htdocs/databruh`, open:

```
http://localhost/databruh/php_files/home_page.php
```

From there you can sign up or log in (`login.php`). A test admin account is created by `password_entity.sql`:

- Email: `Admin_Test@gmail.com`
- Password: `12345`

Logging in routes each account type to its own dashboard (admin, fleet manager, workshop manager, mechanic, or driver).

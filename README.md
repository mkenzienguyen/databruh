# databruh

Database design full-stack project for smart fleet management.

## Requirements

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL/MariaDB + PHP)
- This repository placed inside your XAMPP `htdocs` folder, e.g. `/Applications/XAMPP/xamppfiles/htdocs/databruh`
- Python 3.8+ (only needed to generate sample data — see step 3)

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
| 2 | `database_files/databruh_db/database_creation_sql/full_creation_script.sql` | Creates `databruh_db`: all 29 fleet tables, the reference/lookup data, the business-rule triggers, and the scoring stored procedures |
| 3 | `database_files/databruh_db/database_basic_views_sql/basic_views.sql` | Creates the 22 SQL views (e.g. `view_driver_incidents`, `view_driver_score_anomalies`, `view_coaching_required`) that the dashboards query directly |

### What `full_creation_script.sql` includes

It is the single source of truth for the fleet schema. Running it gives you:

- **Schema** — all 29 tables with their keys and foreign-key constraints.
- **Reference data** — the lookup tables the app cannot run without: `vehicle_status`, `vehicle_classification`, `severity_level`, `activity_type`, `activity_certification`, `vehicle_certification_type`, `vehicle_type_certification_requirement`, `depot_location`, `workshop`, `maintenance_schedule_rule`, plus the supplier/parts master data (`partner_company`, `part`, `supplier_product_list`).
- **Business rules** — 8 triggers that block an assignment if the vehicle is unavailable, the driver lacks a required certification, has a safety score of 50 or below, or has an unresolved critical incident.
- **Stored procedures** — `sp_check_assignment_eligibility`, `sp_recalculate_driver_month_score`, and `sp_recalculate_all_monthly_scores`, which compute monthly safety scores from recorded behaviour events.

It does **not** contain operational data (drivers, vehicles, trips, behaviour events, maintenance jobs). That is generated in step 3.

Notes:

- The files in `database_files/databruh_db/database_creation_sql/individual_files/` (`driver_and_vehicle.sql`, `maintenance.sql`, `parts_and_supplier.sql`, `business_rules.sql`) are the modular breakdown of the same schema, kept for readability and for the report. Everything in them is already inside `full_creation_script.sql` — **you do not need to run them individually.**
- `full_creation_script.sql` and `password_entity.sql` both start with `DROP DATABASE`, so re-running them resets that database.
- `individual_files/business_rules.sql` is safe to re-run on its own at any time (triggers and procedures are dropped and recreated). Use it if you only need to pick up a change to the enforcement rules or scoring logic without resetting the rest of the database.
- After loading `behaviour_event` rows any other way (e.g. restoring a partial dump), run `CALL sp_recalculate_all_monthly_scores();` once to backfill `monthly_score_log` from them.

Alternatively, from a terminal with the `mysql` CLI (adjust the path to your XAMPP's `mysql` binary if it's not on your `PATH`):

```bash
mysql -u root < database_files/databruh_password_db/password_entity.sql
mysql -u root < database_files/databruh_db/database_creation_sql/full_creation_script.sql
mysql -u root < database_files/databruh_db/database_basic_views_sql/basic_views.sql
```

The app connects as MySQL user `root` with an empty password on `localhost` (XAMPP's default) — see `php_files/login_process.php`. If your MySQL setup differs, update the `$host`/`$username`/`$password` values in `php_files/login_process.php` and `php_files/signup_process.php` to match.

## 3. Generate sample data

The repository ships reference data only, so the dashboards will be empty until you generate a dataset. `tools/generate_mock_data.py` writes a `mock_data.sql` file that you then import. It is deterministic — the same `--seed` always produces the same data.

```bash
python3 tools/generate_mock_data.py -o mock_data.sql
mysql -u root databruh_db < mock_data.sql
```

On XAMPP for Windows the second line is:

```
C:\xampp\mysql\bin\mysql.exe -u root databruh_db < mock_data.sql
```

Two useful sizes:

| Command | Rows | Use for |
|---|---|---|
| `python3 tools/generate_mock_data.py -o mock_data.sql` | 400 drivers, 300 vehicles, 200,000 behaviour events, 20,000 maintenance jobs, 3 years of history | Index benchmarking (step 4) |
| `python3 tools/generate_mock_data.py --small -o mock_data.sql` | 150 drivers, 200 vehicles, 1,000 behaviour events, 200 maintenance jobs | Quick app testing — loads fast, but far too small for the optimiser to bother using an index |

Other flags: `--drivers`, `--vehicles`, `--mechanics`, `--events`, `--jobs`, `--years`, `--seed`. Any flag you pass explicitly overrides `--small`.

The generated data deliberately exercises the business rules — some drivers have lapsed or soon-to-expire certifications, some vehicles are out of service, and some drivers carry unresolved critical incidents — so the triggers and the coaching/anomaly views have something to report. `mock_data.sql` is listed in `.gitignore`; regenerate it rather than committing it.

## 4. Indexes (optional)

`database_files/databruh_db/index.sql` adds the performance indexes. It is **not** required to run the app — the schema works without it — and it is most meaningful against a full-size generated dataset.

```bash
mysql -u root < database_files/databruh_db/index.sql
```

The statements are plain `CREATE INDEX`, so re-running the file on a database that already has them fails with `ERROR 1061 (Duplicate key name)`. To reset, drop the indexes first:

```sql
DROP INDEX IF EXISTS idx_behaviour_event_driver_timestamp ON behaviour_event;
```

To measure whether a given index helps, compare a query before and after creating it in the same session:

```sql
FLUSH STATUS;
SELECT ... ;                      -- the query under test
SHOW SESSION STATUS LIKE 'Handler_read%';
```

`Handler_read_rnd_next` counts rows read by a full table scan; `Handler_read_next` counts rows read through an index. A useful index moves the count out of the first counter and shrinks the total. `EXPLAIN <query>` shows the same story qualitatively — watch the `type` column move from `ALL` toward `ref`/`range`, and the `rows` estimate fall.

Index creation can briefly lock a large table, so run it during a quiet period on a production-sized database. MariaDB DDL auto-commits; indexes can be removed again with `DROP INDEX ... ON table_name`.

## 5. Open the app

With Apache running and the repo in `htdocs/databruh`, open:

```
http://localhost/databruh/php_files/home_page.php
```

From there you can sign up or log in (`login.php`). A test admin account is created by `password_entity.sql`:

- Email: `Admin_Test@gmail.com`
- Password: `12345`

Logging in routes each account type to its own dashboard (admin, fleet manager, workshop manager, mechanic, or driver).

## Repository layout

```
database_files/
  databruh_db/
    database_creation_sql/
      full_creation_script.sql      # run this — schema + reference data + rules
      individual_files/             # modular breakdown, already merged above
    database_basic_views_sql/
      basic_views.sql               # the 22 dashboard views
    index.sql                       # optional performance indexes
  databruh_password_db/
    password_entity.sql             # accounts / login
php_files/                          # pages and dashboards
css_files/  js_files/  assets/
tools/
  generate_mock_data.py             # sample-data generator
```

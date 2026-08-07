# Performance (Indexes) — Investigation and Findings

Smart Fleet Management Database · `databruh_db`

---

## 1. Candidate identification

Index candidates were derived from the query patterns the application
actually executes — the dashboard queries and the SQL views — rather
than chosen speculatively. Thirteen candidates were proposed in
`database_files/databruh_db/index.sql`.

| # | Index | Table | Query pattern it targets |
|---|---|---|---|
| 1 | `idx_behaviour_event_driver_timestamp (DriverID, Timestamp)` | `behaviour_event` | Driver incident history filtered by date range |
| 2 | `idx_alert_status_timestamp (Status, AlertTimestamp)` | `alert` | Open alerts, newest first (`view_active_alerts`) |
| 3 | `idx_maintenance_job_status (Status)` | `maintenance_job` | Open vs closed job counts (`view_workshop_workload`) |
| 4 | `idx_maintenance_job_startdate (StartDate)` | `maintenance_job` | Job lists sorted newest first |
| 5 | `idx_vda_vehicle_enddate (VehicleID, EndDate)` | `vehicle_driver_assignment` | "Who currently drives this vehicle" |
| 6 | `idx_vda_driver_enddate (DriverID, EndDate)` | `vehicle_driver_assignment` | "What does this driver currently drive" |
| 7 | `idx_monthly_score_log_driver_year_month (DriverID, Year, Month)` | `monthly_score_log` | Latest safety score (`sp_check_assignment_eligibility`) |
| 8 | `idx_coaching_log_driver_outcome (DriverID, Outcome)` | `coaching_log` | Retraining counts (`view_driver_risk_summary`) |
| 9 | `idx_driver_cert_owned_expiry (ExpiryDate)` | `driver_certification_owned` | Expired certifications (`view_expired_certifications`) |
| 10 | `idx_driver_fullname (FullName)` | `driver` | Driver directory sort |
| 11 | `idx_mechanic_worker_fullname (FullName)` | `mechanic_worker` | Mechanic list sort |
| 12 | `idx_part_partname (PartName)` | `part` | Parts catalogue sort |
| 13 | `idx_supplier_product_list_part_partner (PartID, PartnerID)` | `supplier_product_list` | Proposed as a uniqueness constraint |

### Deliberately not indexed

- **Foreign-key columns.** InnoDB creates an index for every foreign key
  automatically, so adding one manually would be redundant. This proved
  significant: several baselines below were already indexed.
- **`MONTH()` / `YEAR(Timestamp)` predicates.** Wrapping a column in a
  function makes the predicate non-sargable and prevents index use
  entirely. The remedy is a sargable range rewrite
  (`>= '2026-05-01' AND < '2026-06-01'`), not an index.
- **`view_parts_below_reorder`'s `QuantityOnHand <= ReorderThreshold`.**
  This compares two columns of the same row, which no B-tree index can
  accelerate.
- **Every remaining column.** Each index must be maintained on every
  insert and update. `behaviour_event` carries the highest write volume
  (the telematics feed), so restraint there was deliberate.

---

## 2. Method

Testing was carried out against a generated dataset of **200,000
behaviour events, 20,000 maintenance jobs, 400 drivers and 300
vehicles**, produced by `tools/generate_mock_data.py`. Bulk data was
necessary: on the small demonstration dataset every table fits within a
few pages, and the optimiser correctly prefers a full scan to an index
lookup, so no index effect would be observable.

Each index was tested individually: dropped, measured, recreated,
measured again. The index list for the table was printed before each
measurement to evidence which state was being observed.

**Metric.** Query cost was measured with MySQL's `Handler_read_*`
session status counters rather than elapsed time:

- `Handler_read_rnd_next` — rows read by sequentially scanning a table
- `Handler_read_next` — rows read by walking an index

Their sum is the number of rows the storage engine actually touched.
Because these are exact counts rather than timings, results are
unaffected by caching, background load or clock resolution.
`EXPLAIN` was captured alongside to confirm which index the optimiser
selected, and `ANALYZE TABLE` was run after every schema change so the
planner worked from current statistics.

### A note on the baseline

Foreign-key columns are indexed automatically by InnoDB, so for several
indexes the "before" state was **already indexed**, and the comparison
is composite-index versus single-column index rather than index versus
full scan. In those cases the improvement appears in
`Handler_read_next` and in `key_len`, not in `Handler_read_rnd_next`
(which stays at zero because no table scan occurred either way).

A related constraint surfaced during testing: several composite indexes
lead with a foreign-key column, and InnoDB had **adopted them as the
constraint's supporting index**. Dropping them returned
`ERROR 1553: Cannot drop index … needed in a foreign key constraint`.
A single-column index had to be created first in each case. This shows
these composites are not purely additive — they *replace* the
auto-created foreign-key index rather than sitting alongside it.

---

## 3. Results

Rows examined = `Handler_read_rnd_next` + `Handler_read_next`.

| # | Index | Rows before | Rows after | Change | Key chosen after | Verdict |
|---|---|---:|---:|---:|---|---|
| 4 | `idx_maintenance_job_startdate` | 20,102 | 101 | **−99.5%** | `idx_maintenance_job_startdate` (index) | **RETAIN** |
| 9 | `idx_driver_cert_owned_expiry` | 2,001 | 40 | **−98%** | `idx_driver_cert_owned_expiry` (range) | **RETAIN** |
| 7 | `idx_monthly_score_log_driver_year_month` | 38 | 2 | **−95%** | `idx_monthly_score_log_…` (range) | **RETAIN** |
| 8 | `idx_coaching_log_driver_outcome` | 218 | 35 | **−84%** | `idx_coaching_log_driver_outcome` (ref) | **RETAIN** |
| 1 | `idx_behaviour_event_driver_timestamp` | 2,194 | 876 | **−60%** | `idx_behaviour_event_driver_timestamp` (range) | **RETAIN** |
| 2 | `idx_alert_status_timestamp` | 10,102 | 4,113 | **−59%** | `idx_alert_status_timestamp` (range) | **RETAIN** |
| 10 | `idx_driver_fullname` | 400 | 50 | −87% | `idx_driver_fullname` (index) | RETAIN (marginal) |
| 3 | `idx_maintenance_job_status` | 20,001 | 16,433 | −18% | `idx_maintenance_job_status` (ref) | **DELETE** |
| 11 | `idx_mechanic_worker_fullname` | 40 | 40 | no change | `idx_mechanic_worker_fullname` (index) | **DELETE** |
| 12 | `idx_part_partname` | 30 | 30 | no change | `idx_part_partname` (index) | **DELETE** |
| 5 | `idx_vda_vehicle_enddate` | 1 | 1 | no change | `idx_fk_v` — **not selected** | **DELETE** |
| 6 | `idx_vda_driver_enddate` | 2 | 2 | no change | `idx_fk_d` — **not selected** | **DELETE** |
| 13 | `idx_supplier_product_list_part_partner` | n/a | n/a | n/a | `PRIMARY` — **not selected** | **DELETE** |

**Outcome: 7 retained, 6 removed.**

---

## 4. Detailed findings

### 4.1 `idx_maintenance_job_startdate` — largest improvement (−99.5%)

| | Before | After |
|---|---|---|
| `type` | `ALL` | `index` |
| `key` | `NULL` | `idx_maintenance_job_startdate` |
| `key_len` | — | 5 |
| `rows` | 19,745 | 100 |
| `Extra` | `Using filesort` | `Using index` |
| Rows examined | 20,102 | 101 |

Sorting 20,000 jobs to return the newest 100 required reading the entire
table and performing an external sort. With the index the rows are
already in `StartDate` order, so the engine reads 100 index entries and
stops. `Using index` indicates a **covering index** — `JobID` is
retrievable from the index itself (InnoDB appends the primary key to
every secondary index), so the base table is never touched.

**Retain.** A 199× reduction in rows examined on a 20,000-row table.

### 4.2 `idx_driver_cert_owned_expiry` (−98%)

Baseline was a full scan of all 2,000 certification rows to find the 40
expired ones (`type: ALL`, `Using where`). With the index the plan
becomes `type: range` reading exactly 40 rows — a 1:1 ratio between rows
examined and rows returned. `key_len: 4` confirms the full `DATE` column
is used.

**Retain.** Directly serves `view_expired_certifications`, which the
fleet manager dashboard queries on every page load.

### 4.3 `idx_monthly_score_log_driver_year_month` (−95%, filesort removed)

The most interesting result. The table already carried
`UNIQUE (DriverID, Month, Year)`, so `DriverID` was indexed — but in the
wrong **column order** for `ORDER BY Year DESC, Month DESC`. The
optimiser used the unique key to find the driver's 36 rows, then sorted
them (`Using filesort`).

Reordering the columns to `(DriverID, Year, Month)` lets the index
satisfy the sort directly: `Handler_read_next` fell from 36 to 0, and
`Using filesort` disappeared.

**Retain.** This query runs inside `sp_check_assignment_eligibility`,
which fires on every driver–vehicle assignment, so the cost is incurred
on a write path. It also demonstrates that column *order* in a composite
index determines which sorts it can serve.

### 4.4 `idx_coaching_log_driver_outcome` (−84%)

| | Before | After |
|---|---|---|
| `key` | `idx_fk_cl` (foreign-key index) | `idx_coaching_log_driver_outcome` |
| `key_len` | 202 | **404** |
| `rows` | 218 | 35 |
| `Extra` | `Using index condition; Using where` | `Using index condition` |

The baseline was already indexed on `DriverID` via the foreign key. It
located the driver's 218 coaching records, then discarded 183 of them
against the `Outcome` predicate. `key_len` doubling from 202 to 404
proves both columns are now used, and `ref: const,const` confirms both
are matched as constants. `Using where` disappearing confirms no
post-filtering remains.

**Retain.**

### 4.5 `idx_behaviour_event_driver_timestamp` (−60%)

| | Before | After |
|---|---|---|
| `key` | `idx_fk_driver` | `idx_behaviour_event_driver_timestamp` |
| `key_len` | 203 | **208** |
| `rows` | 2,194 | 876 |
| `Extra` | `Using index condition; Using where` | `Using index condition` |

The foreign-key index on `DriverID` already prevented a full scan, so
the baseline was not unindexed. It read all 2,194 events for the driver
and discarded 1,318 against the timestamp. The composite index reduces
that to exactly the 876 rows returned.

`key_len` rising 203 → 208 is the decisive evidence: 203 bytes is
`DriverID` alone (`VARCHAR(50)` utf8mb4 = 200 bytes + 2 length prefix +
1 nullable flag); the additional 5 bytes are the `DATETIME`. Had it
remained 203, only the leading column would be in use.

**Retain.** `behaviour_event` is the largest table in the schema at
200,000 rows, and this pattern drives both the driver dashboard and the
fleet manager's incident filter.

### 4.6 `idx_alert_status_timestamp` (−59%)

Baseline scanned all 10,102 alert rows with `Using filesort`. With the
index, rows examined fell to 4,113 and `Using index` appeared,
indicating the query is answered from the index without reading the
table.

`Using filesort` **remains** in the plan. This is expected: the query
matches two `Status` values (`'New'` and `'Escalated'`), so the index
yields two separately-ordered ranges that must still be merged into a
single `AlertTimestamp DESC` order. A single-status query would avoid
the sort entirely.

**Retain** — a 59% reduction, with the residual sort understood and
explained rather than assumed away.

### 4.7 `idx_maintenance_job_status` — DELETE despite being used

| | Before | After |
|---|---|---|
| `type` | `ALL` | `ref` |
| `key` | `NULL` | `idx_maintenance_job_status` |
| `rows` (estimated) | 19,749 | **10,128** |
| Rows examined (actual) | 20,001 | **16,433** |

The optimiser did select the index, but the improvement is only 18%,
and two observations argue against keeping it:

**Poor selectivity.** `Status` holds two or three distinct values.
16,433 of 20,000 rows — 82% — match `'Closed'`. Retrieving 82% of a
table through an index means an index traversal *plus* a row lookup for
almost every row, which in physical I/O terms is typically slower than a
single sequential scan, even though the handler count is marginally
lower.

**The estimate was wrong.** The optimiser predicted 10,128 rows and the
query actually touched 16,433 — a 62% underestimate. The index was
chosen on a statistic that did not hold, which is characteristic of
low-cardinality columns where the planner assumes a more even
distribution than exists.

**Delete.** The index costs storage and slows every insert and update to
`maintenance_job` for a marginal and unreliable read benefit.

### 4.8 `idx_vda_vehicle_enddate` and `idx_vda_driver_enddate` — never selected

Both were created successfully, and `EXPLAIN` lists them in
`possible_keys` — but `key` shows the optimiser chose the **foreign-key
index** (`idx_fk_v` / `idx_fk_d`) in both cases. Rows examined were
identical before and after (1 and 2 respectively).

`vehicle_driver_assignment` holds roughly 230 rows. With so few rows per
vehicle or driver, the extra `EndDate` column offers no discrimination
worth a wider index, and the optimiser correctly preferred the narrower
existing key.

**Delete both.** The index design is sound in principle — at production
scale, where assignments accumulate over years and each vehicle has many
historical rows, `(VehicleID, EndDate)` would filter effectively. But on
the measured dataset they are never used, and an unused index is pure
write overhead.

### 4.9 `idx_mechanic_worker_fullname` and `idx_part_partname` — negligible

Both are selected after creation and both remove `Using filesort`, but
rows examined are unchanged (40 and 30 respectively) because the tables
are read in full either way. Each index is also disproportionately wide:
`key_len` 1022–1023 bytes for `VARCHAR(255)` utf8mb4, on tables of 30–40
rows.

**Delete both.** Sorting a few dozen rows in memory costs nothing
measurable, while each index adds a kilobyte-wide structure to maintain
on every write.

### 4.10 `idx_driver_fullname` — retained, marginally

Same pattern, but `driver` holds 400 rows and the plan improves from
`type: ALL` reading 400 rows with `Using filesort` to `type: index`
reading 50 with `Using index` — a genuine reduction because the `LIMIT
50` can stop early once rows arrive pre-sorted.

**Retain**, with the caveat that the benefit is modest and would only
matter as the driver roster grows.

### 4.11 `idx_supplier_product_list_part_partner` — redundant with the primary key

This index was proposed as a uniqueness constraint, on the assumption
that `supplier_product_list` had no primary key. It does:

```
INDEX_NAME | NON_UNIQUE | cols
PRIMARY    |          0 | PartID, PartnerID
PartnerID  |          1 | PartnerID
```

`PRIMARY KEY (PartID, PartnerID)` covers exactly the same columns, in
the same order, with the same uniqueness. InnoDB maintains the primary
key as the table's clustered index, so the proposed index is an exact
duplicate: it consumes storage, must be updated on every write, and can
never be selected in preference to the primary key. `EXPLAIN` on a
`(PartID, PartnerID)` lookup returns `key: PRIMARY`, `type: const`.

**Delete — redundant with an existing constraint.** No performance
measurement was appropriate, because the index was never a performance
optimisation; the role it was intended to fill is already occupied.

**Secondary observation.** The same output shows an auto-created index
on `PartnerID` but none on `PartID`, even though both are foreign keys.
`PartID` is the leading column of the primary key and is therefore
already indexed; `PartnerID` sits second and required its own index.
This illustrates that a composite index only satisfies constraints on
its **leftmost prefix** — the same principle that explains why
`idx_vda_vehicle_enddate` and `idx_vda_driver_enddate` had to be
proposed as two separate indexes rather than one.

---

## 5. Conclusions

**Retained (7):** `idx_maintenance_job_startdate`,
`idx_driver_cert_owned_expiry`,
`idx_monthly_score_log_driver_year_month`,
`idx_coaching_log_driver_outcome`,
`idx_behaviour_event_driver_timestamp`,
`idx_alert_status_timestamp`, `idx_driver_fullname`.

**Removed (6):** `idx_maintenance_job_status`,
`idx_mechanic_worker_fullname`, `idx_part_partname`,
`idx_vda_vehicle_enddate`, `idx_vda_driver_enddate`,
`idx_supplier_product_list_part_partner`.

`index.sql` has been revised to contain only the seven retained
indexes.

### What the investigation showed

**An index being *used* is not the same as an index being *worth it*.**
`idx_maintenance_job_status` was selected by the optimiser yet returned
only an 18% improvement on a column matching 82% of the table, on an
estimate that proved 62% low. Selectivity, not mere usage, determines
value.

**Composite indexes must be evaluated against the real baseline.** Five
of the thirteen lead with a foreign-key column that InnoDB had already
indexed. Reporting these as "full scan eliminated" would have been
wrong; the actual gain is the second column removing post-filtering,
visible as an increase in `key_len` and the disappearance of
`Using where`.

**Column order matters as much as column choice.**
`monthly_score_log` already had a unique key on `(DriverID, Month,
Year)`. Only reordering to `(DriverID, Year, Month)` allowed the sort to
be satisfied from the index.

**Small tables do not benefit.** Four indexes on tables of 30–400 rows
produced no measurable reduction. The optimiser's preference for a scan
at that size is correct, not a failure of the index.

### Limitations

Two of the removed indexes — `idx_vda_vehicle_enddate` and
`idx_vda_driver_enddate` — were rejected on a dataset where
`vehicle_driver_assignment` holds only ~230 rows. In production, where
assignment history accumulates over years, these would likely become
worthwhile. They are recorded as removed **for the current data profile**
and flagged for re-evaluation as the table grows.

Write cost was not measured directly. Every retained index adds work to
each insert and update; `behaviour_event` receives the highest write
volume, so its index carries the greatest ongoing cost. It was retained
because a 60% reduction in rows examined on the schema's largest table,
serving two dashboards, outweighs that overhead.

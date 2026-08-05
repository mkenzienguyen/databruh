# Extension Feature — Predictive Maintenance Risk Scoring

Design sketch for a fifth extension task alongside the four in the brief
(parts, suppliers, warranty claims, mechanic certification renewal).

---

## 1. Why this fits, and what the label is

The brief already establishes the domain: onboard diagnostics generate
predictive alerts, and workshop staff choose one of four actions —
acknowledge and monitor, schedule an inspection, escalate for urgent
repair, or resolve without action.

The useful insight is that **the training label already exists in the
schema**. The brief states:

> An alert does not always result in a maintenance job. However, when a
> job is created in response to an alert, the alert must be linked to
> that job so the outcome can be tracked.

That link is `maintenance_job.AlertID`. So for every historical alert:

- `AlertID` appears in `maintenance_job.AlertID` → it became real work (positive)
- it doesn't → it was noise (negative)

This is a labelled binary classification problem sitting in the existing
data with no extra annotation. The prediction question becomes:

> **Given a new alert, how likely is it to require actual repair work,
> and which of the four actions should the workshop take?**

That output maps directly onto the four actions the brief already
defines, so the feature produces a recommendation the system can act on
rather than an abstract number.

---

## 2. Schema additions

Three tables. Each is justifiable as database design work on its own,
independent of the prediction logic.

### 2.1 `alert_type` — normalise the alert catalogue

`alert.AlertName` is currently free-text `VARCHAR(100)`, which is exactly
the inconsistency problem the brief complains about ("information is
often duplicated across spreadsheets, resulting in inconsistent vehicle
names, depot names and severity labels"). Normalising it is a
correctness fix that also turns alert type into a usable categorical
feature.

```
alert_type
  AlertTypeID      INT PK
  AlertTypeName    VARCHAR(100) UNIQUE   -- 'Brake Wear Warning', etc.
  Subsystem        VARCHAR(50)           -- Braking, Cooling, Battery, ...
  BaseSeverityWeight TINYINT             -- default risk contribution 0-40
```

`alert` gains `AlertTypeID` (FK), keeping `AlertName` during migration
then dropping it.

Seed the seven alert kinds the brief lists: brake wear, engine
overheating, battery degradation, oil quality, transmission fault,
cooling anomaly, tyre pressure.

### 2.2 `risk_model_version` — makes predictions reproducible

```
risk_model_version
  ModelVersionID   INT PK
  VersionLabel     VARCHAR(50)      -- 'heuristic-v1', 'logreg-v2'
  Method           VARCHAR(30)      -- 'Heuristic' | 'LogisticRegression'
  EffectiveFrom    DATETIME
  EffectiveTo      DATETIME NULL
  Notes            TEXT
```

This one is worth arguing for explicitly in the report. The brief's
Historical Records section requires that records stay available even
when *"predictive alert thresholds are adjusted"*. Without a version
table, re-tuning the model silently invalidates every past prediction —
you can no longer explain why an alert was escalated last March. This
table is what makes that requirement satisfiable.

### 2.3 `alert_risk_assessment` — the prediction record

```
alert_risk_assessment
  AssessmentID     INT PK
  AlertID          INT FK -> alert
  ModelVersionID   INT FK -> risk_model_version
  AssessedAt       DATETIME
  RiskScore        DECIMAL(5,2)     -- 0-100
  RecommendedAction VARCHAR(30)     -- Monitor|Schedule|Escalate|Urgent
  FeatureSnapshot  JSON NULL        -- inputs at scoring time
  UNIQUE (AlertID, ModelVersionID, AssessedAt)
```

`FeatureSnapshot` matters for the same reason: the odometer, service
history and fault counts that drove a score all change over time.
Storing the inputs alongside the output means a past assessment stays
explainable. An assessment is an immutable event, never updated in place
— re-scoring inserts a new row.

---

## 3. Features (all derivable from existing tables)

| Feature | Source |
|---|---|
| Alert subsystem + base weight | `alert_type` |
| Vehicle age | `vehicle.YearOfManufacture` |
| Odometer band | `vehicle.CurrentOdometer` |
| Vehicle classification | `vehicle.ClassificationID` |
| Days since last closed job | `maintenance_job` (Status = 'Closed') |
| Prior job count on vehicle | `maintenance_job` |
| Prior repeat-fault count, same subsystem | `activity_instance.RepeatFault` + `activity_type` |
| Currently overdue for service | `view_vehicles_overdue_for_service` |
| Alert age while unresolved | `alert.AlertTimestamp` vs now |
| Harsh driving events, last 30 days | `behaviour_event` on that vehicle |

That last one is the cross-domain link worth highlighting: harsh braking
and rapid acceleration events cause mechanical wear, so the driver
behaviour half of the database becomes a genuine input to the
maintenance half. It ties the two stakeholder domains together instead
of leaving them as parallel silos.

---

## 4. Two layers — and which one to actually build

### Layer 1 — heuristic scoring in SQL (build this)

A stored procedure `sp_score_alert(AlertID)` computing a weighted sum,
clamped to 0–100:

```
score = alert_type.BaseSeverityWeight            -- 10..40
      + 25 if prior repeat fault on same subsystem
      + 15 if vehicle currently overdue for service
      + odometer band bonus                      -- 0/5/10/15
      + 10 if vehicle age > 5 years
      + min(10, days_alert_unresolved)
      + min(10, 2 * harsh_events_last_30_days)
```

Banded into the brief's own four actions:

| Score | Recommended action |
|---|---|
| 0–30 | Acknowledge and monitor |
| 31–60 | Schedule inspection or service |
| 61–85 | Escalate for urgent repair |
| 86–100 | Urgent — remove from service |

**Recommend building this layer.** It's transparent, defensible under
questioning, needs no external runtime, works with the data volume you
have, and it is assessable as database work (procedures, views,
constraints, history modelling) rather than as machine learning that
happens to sit near a database.

### Layer 2 — trained model (optional, and honest about the caveat)

A logistic regression or shallow decision tree trained in Python on
alert → job outcomes, with the fitted coefficients written back into a
`risk_model_coefficient` table and applied by the *same* stored
procedure. The database stays the system of record; Python is only the
fitting step.

**The blocker is data volume.** The seed currently has 5 alerts. You
cannot train or validate anything meaningful on that — any accuracy
figure would be noise, and claiming otherwise is the kind of thing that
invites a hard question in a demo. Two honest options:

1. **Expand the seed data.** Generate ~200–300 alerts across the 8
   vehicles over ~2 years with realistic conversion rates (brake wear
   converts often, tyre pressure rarely). Then both layers work and you
   can report a real train/test split. This is the stronger submission.
2. **Ship Layer 1 only**, and document Layer 2 as designed-but-not-
   trained, with the schema ready for it. Presented as a rules-based
   expert system with a stated upgrade path, this is perfectly
   respectable and much better than an overfitted model on 5 rows.

Option 1 if you have time; option 2 is not a failure.

---

## 5. Where it surfaces in the app

**Workshop manager dashboard** — the existing alerts table gains a
`Risk` column (score + banded pill), default-sorted by score descending,
so the alert queue becomes a prioritised worklist. The "Create job from
alert" action is pre-suggested for anything scoring above 60.

**Recompute** — a stored procedure `sp_score_all_open_alerts()` mirroring
the existing `sp_recalculate_all_monthly_scores()` pattern, triggered by
an admin button and on new alert insert.

**New view** `view_alert_risk_current` — latest assessment per alert
under the currently effective model version, joined to vehicle and depot,
for the dashboards to read directly. Same pattern as the other 21 views.

---

## 6. Build order

1. `alert_type` + migrate `alert.AlertName` → `AlertTypeID`
2. `risk_model_version`, `alert_risk_assessment`
3. Expand seed alert history (if doing Layer 2)
4. `sp_score_alert` + `sp_score_all_open_alerts`
5. `view_alert_risk_current`
6. Workshop manager dashboard column + recompute action
7. Optional: Python fitting script + `risk_model_coefficient`

Steps 1–6 are self-contained database and PHP work in the same style as
the rest of the project.

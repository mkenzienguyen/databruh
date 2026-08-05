<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/db_connect_fleet.php';
require_once __DIR__ . '/helpers.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function statusSlug(string $status): string
{
    return strtolower(str_replace(' ', '-', trim($status)));
}

// ---- Role gates (instruction #1 + #2) ----
$showFleet    = user_can(ROLES_FLEET);
$showWorkshop = user_can(ROLES_WORKSHOP);
if (!$showFleet && !$showWorkshop) {
    header('Location: ' . roleDashboardPath((string) ($_SESSION['TypeID'] ?? '')));
    exit;
}

// ---- Table data ----
$drivers = get_options($conn, "SELECT d.DriverID, d.FullName, d.LicenseExpiration, d.EmploymentStatus, dl.DepotName
    FROM driver d LEFT JOIN depot_location dl ON d.DepotID = dl.DepotID
    WHERE d.IsDeleted = 0 ORDER BY d.FullName");
$vehicles = get_options($conn, "SELECT v.VehicleID, v.RegistrationNumber, v.StatusID, v.CurrentOdometer, vs.StatusName
    FROM vehicle v LEFT JOIN vehicle_status vs ON v.StatusID = vs.StatusID
    WHERE v.IsDeleted = 0 ORDER BY v.RegistrationNumber");
$jobs = get_options($conn, "SELECT mj.JobID, v.RegistrationNumber, w.WorkshopName, mj.StartDate, mj.Status, mj.ToTalCost
    FROM maintenance_job mj
    JOIN vehicle v ON mj.VehicleID = v.VehicleID
    JOIN workshop w ON mj.WorkshopID = w.WorkshopID
    ORDER BY mj.StartDate DESC");
$parts = get_options($conn, "SELECT p.PartID, p.PartName, pc.PartnerName AS Supplier
    FROM part p JOIN partner_company pc ON p.PrimarySupplierID = pc.PartnerID
    ORDER BY p.PartName");
$recentEvents = get_options($conn, "SELECT EventID, Timestamp, VehicleID, EventType FROM behaviour_event ORDER BY Timestamp DESC LIMIT 50");
// ---- Review comments per event (for the Safety events table) ----
$reviewRows = get_options($conn, "SELECT EventID, ReviewerName, Comment FROM incident_review ORDER BY ReviewID");
$reviewsByEvent = [];
foreach ($reviewRows as $review) {
    $reviewsByEvent[(int) $review['EventID']][] = $review;
}
// ---- Form options ----
$classifications = get_options($conn, "SELECT ClassificationID, ClassificationName FROM vehicle_classification");
$depots          = get_options($conn, "SELECT DepotID, DepotName FROM depot_location");
$workshops       = get_options($conn, "SELECT WorkshopID, WorkshopName FROM workshop");
$activityTypes   = get_options($conn, "SELECT ActivityTypeID, ActivityTypeName FROM activity_type");
$statuses        = get_options($conn, "SELECT StatusID, StatusName FROM vehicle_status");
$severities      = get_options($conn, "SELECT SeverityID, LevelName FROM severity_level");
$suppliers       = get_options($conn, "SELECT PartnerID, PartnerName FROM partner_company");
$alerts          = get_options($conn, "SELECT AlertID, AlertName FROM alert WHERE Status != 'Resolved'");
$conn->close();

$notices = [];
if (isset($_GET['driver_added']))   { $notices[] = 'Driver added successfully' . (isset($_GET['driver_id']) ? ' (' . $_GET['driver_id'] . ')' : '') . '.'; }
if (isset($_GET['vehicle_added']))  { $notices[] = 'Vehicle added successfully' . (isset($_GET['vehicle_id']) ? ' (' . $_GET['vehicle_id'] . ')' : '') . '.'; }
if (isset($_GET['part_added']))     { $notices[] = 'Part added successfully.'; }
if (isset($_GET['job_added']))      { $notices[] = 'Maintenance job #' . ($_GET['job_id'] ?? '') . ' created.'; }
if (isset($_GET['status_updated'])) { $notices[] = 'Vehicle status updated.'; }
if (isset($_GET['event_added']))    { $notices[] = 'Behavior event recorded.'; }
if (isset($_GET['review_added']))   { $notices[] = 'Review comment added.'; }
if (isset($_GET['vehicle_deleted'])){ $notices[] = 'Vehicle removed.'; }
if (isset($_GET['driver_deleted'])) { $notices[] = 'Driver removed.'; }
if (isset($_GET['job_deleted']))    { $notices[] = 'Draft maintenance job deleted.'; }
if (isset($_GET['part_deleted']))   { $notices[] = 'Part deleted.'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Add and manage drivers, vehicles, maintenance jobs, and parts across the Databruh fleet.">
<title>Manage Fleet - Databruh</title>
<link rel="icon" href="../assets/databruh-mark.svg" type="image/svg+xml">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="stylesheet" href="../css_files/design_system.css">
<link rel="stylesheet" href="../css_files/datavs.css">
<link rel="stylesheet" href="../css_files/admin_page.css">
<link rel="stylesheet" href="../css_files/role_dashboards.css">
<link rel="stylesheet" href="../css_files/minimalist_theme.css">
<link rel="stylesheet" href="../css_files/swiss_bento_theme.css">
<script>
function confirmSelectChange(selectElement, label) {
const optionText = selectElement.options[selectElement.selectedIndex].text;
if (confirm(`Set ${label} to "${optionText}"?`)) {
selectElement.form.submit();
} else {
selectElement.value = selectElement.getAttribute('data-original');
}
}
function storeOriginalSelectValue(selectElement) {
selectElement.setAttribute('data-original', selectElement.value);
}
function setNow(inputId) {
const now = new Date();
now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
document.getElementById(inputId).value = now.toISOString().slice(0, 16);
}
</script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to fleet management</a>
<?php renderSiteNavigation('fleet'); ?>
<main id="main-content" class="site-main overflow-x-hidden w-full max-w-full">
<section class="site-hero dashboard-hero" aria-labelledby="fleet-title">
<div class="hero-grid" aria-hidden="true"></div>
<div class="site-hero-content">
<p class="eyebrow" data-hero-item>Fleet operations · Records and removals</p>
<h1 id="fleet-title" class="max-w-6xl" data-hero-item>
Add the record,
<br>keep the history.
</h1>
<p class="hero-copy" data-hero-item>
Drivers, vehicles, workshop jobs, and parts — every change stays
traceable in the system log.
</p>
</div>
</section>
<?php foreach ($notices as $notice): ?>
<div class="section-shell" style="padding-top: 1rem; padding-bottom: 0;">
<div class="system-feedback" role="status"><?php echo escape($notice); ?></div>
</div>
<?php endforeach; ?>

<?php if ($showFleet): ?>
<section class="admin-directory" aria-labelledby="fleet-directory-title">
<div class="section-shell">
<div class="chapter-heading">
<div>
<span class="section-kicker">Drivers &amp; vehicles</span>
<h2 id="fleet-directory-title">Directory, status, and new records.</h2>
</div>
</div>
<div class="admin-table-shell" data-reveal data-stack-card>
<table class="admin-table">
<caption class="sr-only">Driver directory</caption>
<thead>
<tr>
<th scope="col">Driver</th>
<th scope="col">Depot</th>
<th scope="col">Licence expires</th>
<th scope="col">Employment status</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php if ($drivers): ?>
<?php foreach ($drivers as $driver): ?>
<tr>
<td class="cell-strong"><?php echo escape($driver['FullName']); ?>
<div class="description-cell"><?php echo escape($driver['DriverID']); ?></div>
</td>
<td><?php echo escape($driver['DepotName'] ?? '—'); ?></td>
<td><?php echo escape($driver['LicenseExpiration']); ?></td>
<td><?php echo escape($driver['EmploymentStatus']); ?></td>
<td>
<form method="POST" class="inline-form" action="drivers_process.php?action=soft_delete"
onsubmit="return confirm('Remove this driver? Their history will be kept, but they will no longer appear in active lists.');">
<input type="hidden" name="driver_id" value="<?php echo escape($driver['DriverID']); ?>">
<button type="submit" class="btn btn-danger">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="5" class="empty-row">No drivers recorded.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<form method="POST" action="drivers_process.php?action=add" class="directory-toolbar" data-reveal data-stack-card>
<div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
<div class="field-group"><label for="f-name">Full name</label><input id="f-name" type="text" name="full_name" required></div>
<div class="field-group"><label for="f-lic">License number</label><input id="f-lic" type="text" name="license_number" required></div>
<div class="field-group"><label for="f-exp">License expiration</label><input id="f-exp" type="date" name="license_expiration" required></div>
<div class="field-group"><label for="f-depot">Depot</label>
<select id="f-depot" name="depot_id">
<option value="">-- none --</option>
<?php foreach ($depots as $d): ?>
<option value="<?php echo (int) $d['DepotID']; ?>"><?php echo escape($d['DepotName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="f-emp">Employment status</label><input id="f-emp" type="text" name="employment_status" placeholder="Active"></div>
<div class="field-group"><label for="f-contact">Contact info</label><input id="f-contact" type="text" name="contact_info"></div>
<div class="field-group"><label for="f-emergency">Emergency contact</label><input id="f-emergency" type="text" name="emergency_contact"></div>
<button type="submit" class="btn btn-search">Add Driver</button>
</div>
</form>
<div class="admin-table-shell" data-reveal data-stack-card>
<table class="admin-table">
<caption class="sr-only">Vehicle directory</caption>
<thead>
<tr>
<th scope="col">Vehicle</th>
<th scope="col">Odometer</th>
<th scope="col">Status</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php if ($vehicles): ?>
<?php foreach ($vehicles as $vehicle): ?>
<tr>
<td class="cell-strong"><?php echo escape($vehicle['RegistrationNumber']); ?>
<div class="description-cell"><?php echo escape($vehicle['VehicleID']); ?></div>
</td>
<td><?php echo number_format((int) $vehicle['CurrentOdometer']); ?> km</td>
<td>
<form method="POST" class="inline-form role-form" action="vehicles_process.php?action=update_status">
<input type="hidden" name="vehicle_id" value="<?php echo escape($vehicle['VehicleID']); ?>">
<label class="sr-only" for="vs-<?php echo escape($vehicle['VehicleID']); ?>">Status for <?php echo escape($vehicle['RegistrationNumber']); ?></label>
<select id="vs-<?php echo escape($vehicle['VehicleID']); ?>" name="status_id"
onfocus="storeOriginalSelectValue(this)" onchange="confirmSelectChange(this, 'vehicle status')">
<?php foreach ($statuses as $s): ?>
<option value="<?php echo (int) $s['StatusID']; ?>" <?php echo ((int) $vehicle['StatusID'] === (int) $s['StatusID']) ? 'selected' : ''; ?>>
<?php echo escape($s['StatusName']); ?>
</option>
<?php endforeach; ?>
</select>
</form>
</td>
<td>
<form method="POST" class="inline-form" action="vehicles_process.php?action=soft_delete"
onsubmit="return confirm('Remove this vehicle? Its history will be kept, but it will no longer appear in active lists.');">
<input type="hidden" name="vehicle_id" value="<?php echo escape($vehicle['VehicleID']); ?>">
<button type="submit" class="btn btn-danger">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="4" class="empty-row">No vehicles recorded.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<form method="POST" action="vehicles_process.php?action=add" class="directory-toolbar" data-reveal data-stack-card>
<div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
<div class="field-group"><label for="v-reg">Registration number</label><input id="v-reg" type="text" name="registration_number" required></div>
<div class="field-group"><label for="v-man">Manufacturer</label><input id="v-man" type="text" name="manufacturer"></div>
<div class="field-group"><label for="v-model">Model</label><input id="v-model" type="text" name="model"></div>
<div class="field-group"><label for="v-class">Classification</label>
<select id="v-class" name="classification_id">
<option value="">-- none --</option>
<?php foreach ($classifications as $c): ?>
<option value="<?php echo (int) $c['ClassificationID']; ?>"><?php echo escape($c['ClassificationName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="v-year">Year of manufacture</label><input id="v-year" type="number" name="year_of_manufacture" min="1950" max="2100"></div>
<div class="field-group"><label for="v-status">Status</label>
<select id="v-status" name="status_id">
<option value="">-- none --</option>
<?php foreach ($statuses as $s): ?>
<option value="<?php echo (int) $s['StatusID']; ?>"><?php echo escape($s['StatusName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="v-depot">Depot</label>
<select id="v-depot" name="depot_id">
<option value="">-- none --</option>
<?php foreach ($depots as $d): ?>
<option value="<?php echo (int) $d['DepotID']; ?>"><?php echo escape($d['DepotName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="v-odo">Current odometer (km)</label><input id="v-odo" type="number" name="current_odometer" value="0" min="0"></div>
<button type="submit" class="btn btn-search">Add Vehicle</button>
</div>
</form>
</div>
</section>
<section class="admin-directory" aria-labelledby="safety-title">
<div class="section-shell">
<div class="chapter-heading">
<div>
<span class="section-kicker">Safety events</span>
<h2 id="safety-title">Record events and coaching.</h2>
</div>
</div>
<div class="admin-table-shell" data-reveal data-stack-card>
<table class="admin-table">
<caption class="sr-only">Recent behaviour events with review comments</caption>
<thead>
<tr>
<th scope="col">Event</th>
<th scope="col">Vehicle</th>
<th scope="col">Timestamp</th>
<th scope="col">Reviews / coaching notes</th>
</tr>
</thead>
<tbody>
<?php if ($recentEvents): ?>
<?php foreach ($recentEvents as $event): ?>
<tr>
<td class="cell-strong">#<?php echo (int) $event['EventID']; ?> - <?php echo escape($event['EventType']); ?></td>
<td><?php echo escape($event['VehicleID']); ?></td>
<td><?php echo escape($event['Timestamp']); ?></td>
<td>
<?php $eventReviews = $reviewsByEvent[(int) $event['EventID']] ?? []; ?>
<?php if ($eventReviews): ?>
<?php foreach ($eventReviews as $review): ?>
<div class="description-cell" style="margin-bottom:0.35rem;">
<strong><?php echo escape($review['ReviewerName'] !== '' ? $review['ReviewerName'] : 'Reviewer'); ?>:</strong>
<?php echo escape($review['Comment']); ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<span class="empty-row">— no reviews yet —</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="4" class="empty-row">No behaviour events recorded.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<form method="POST" action="incidents_process.php?action=add_event" class="directory-toolbar" data-reveal data-stack-card
onsubmit="this.timestamp.value = this.timestamp.value.replace('T', ' ') + ':00';">
<div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
<div class="field-group"><label for="e-vehicle">Vehicle</label>
<select id="e-vehicle" name="vehicle_id" required>
<?php foreach ($vehicles as $v): ?>
<option value="<?php echo escape($v['VehicleID']); ?>"><?php echo escape($v['RegistrationNumber']); ?> (<?php echo escape($v['VehicleID']); ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="e-driver">Driver (optional)</label>
<select id="e-driver" name="driver_id">
<option value="">-- unknown/none --</option>
<?php foreach ($drivers as $d): ?>
<option value="<?php echo escape($d['DriverID']); ?>"><?php echo escape($d['FullName']); ?> (<?php echo escape($d['DriverID']); ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="e-depot">Depot (optional)</label>
<select id="e-depot" name="depot_id">
<option value="">-- none --</option>
<?php foreach ($depots as $d): ?>
<option value="<?php echo (int) $d['DepotID']; ?>"><?php echo escape($d['DepotName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="event-timestamp">Timestamp</label><input id="event-timestamp" type="datetime-local" name="timestamp" required></div>
<div class="field-group"><button type="button" class="btn btn-secondary" onclick="setNow('event-timestamp')">Use now</button></div>
<div class="field-group"><label for="e-sev">Severity</label>
<select id="e-sev" name="severity_id">
<option value="">-- none --</option>
<?php foreach ($severities as $s): ?>
<option value="<?php echo (int) $s['SeverityID']; ?>"><?php echo escape($s['LevelName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="e-type">Event type</label><input id="e-type" type="text" name="event_type" placeholder="Speeding" required></div>
<div class="field-group"><label for="e-desc">Description</label><input id="e-desc" type="text" name="description"></div>
<button type="submit" class="btn btn-search">Record Event</button>
</div>
</form>
<form method="POST" action="incidents_process.php?action=add_review" class="directory-toolbar" data-reveal data-stack-card>
<div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
<div class="field-group"><label for="r-event">Incident</label>
<select id="r-event" name="event_id" required>
<?php foreach ($recentEvents as $e): ?>
<option value="<?php echo (int) $e['EventID']; ?>">
#<?php echo (int) $e['EventID']; ?> - <?php echo escape($e['EventType']); ?> - <?php echo escape($e['VehicleID']); ?> (<?php echo escape($e['Timestamp']); ?>)
</option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="r-name">Reviewer name</label><input id="r-name" type="text" name="reviewer_name"></div>
<div class="field-group"><label for="r-comment">Comment / coaching recommendation</label><input id="r-comment" type="text" name="comment" required></div>
<button type="submit" class="btn btn-search">Add Comment</button>
</div>
</form>
</div>
</section>
<?php endif; ?>

<?php if ($showWorkshop): ?>
<section class="admin-directory" aria-labelledby="jobs-title">
<div class="section-shell">
<div class="chapter-heading">
<div>
<span class="section-kicker">Maintenance jobs</span>
<h2 id="jobs-title">Track status, downtime, and cost.</h2>
</div>
</div>
<div class="admin-table-shell" data-reveal data-stack-card>
<table class="admin-table">
<caption class="sr-only">Maintenance jobs</caption>
<thead>
<tr>
<th scope="col">Vehicle</th>
<th scope="col">Workshop</th>
<th scope="col">Started</th>
<th scope="col">Status</th>
<th scope="col">Cost (VND)</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php if ($jobs): ?>
<?php foreach ($jobs as $job): ?>
<tr>
<td class="cell-strong"><?php echo escape($job['RegistrationNumber']); ?></td>
<td><?php echo escape($job['WorkshopName']); ?></td>
<td><?php echo escape($job['StartDate']); ?></td>
<td>
<span class="status-pill status-<?php echo statusSlug($job['Status'] ?? ''); ?>">
<?php echo escape($job['Status'] ?? 'Unknown'); ?>
</span>
</td>
<td><?php echo $job['ToTalCost'] !== null ? number_format((int) $job['ToTalCost']) : '—'; ?></td>
<td>
<?php if ($job['Status'] !== 'Closed'): ?>
<form method="POST" class="cell-actions" action="workshop_process.php?action=close_job">
<input type="hidden" name="job_id" value="<?php echo (int) $job['JobID']; ?>">
<div class="inline-form">
<label class="sr-only" for="close-end-<?php echo (int) $job['JobID']; ?>">End date</label>
<input type="datetime-local" id="close-end-<?php echo (int) $job['JobID']; ?>" name="end_date" required>
<label class="sr-only" for="close-cost-<?php echo (int) $job['JobID']; ?>">Total cost</label>
<input type="number" id="close-cost-<?php echo (int) $job['JobID']; ?>" name="total_cost" min="0" placeholder="Cost (VND)" required>
</div>
<button type="submit" class="btn btn-search">Close job</button>
</form>
<form method="POST" class="inline-form" style="margin-top:0.5rem;" action="workshop_process.php?action=delete_job"
onsubmit="return confirm('Permanently delete this draft job and its activities? This cannot be undone.');">
<input type="hidden" name="job_id" value="<?php echo (int) $job['JobID']; ?>">
<button type="submit" class="btn btn-danger">Delete draft</button>
</form>
<?php else: ?>
<span class="empty-row">—</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="6" class="empty-row">No maintenance jobs recorded.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<form method="POST" action="workshop_process.php?action=add_job" class="directory-toolbar" data-reveal data-stack-card
onsubmit="this.start_date.value = this.start_date.value.replace('T', ' ') + ':00';">
<div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
<div class="field-group"><label for="j-vehicle">Vehicle</label>
<select id="j-vehicle" name="vehicle_id" required>
<?php foreach ($vehicles as $v): ?>
<option value="<?php echo escape($v['VehicleID']); ?>"><?php echo escape($v['RegistrationNumber']); ?> (<?php echo escape($v['VehicleID']); ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="j-workshop">Workshop</label>
<select id="j-workshop" name="workshop_id" required>
<?php foreach ($workshops as $w): ?>
<option value="<?php echo (int) $w['WorkshopID']; ?>"><?php echo escape($w['WorkshopName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="job-start-date">Start date/time</label><input id="job-start-date" type="datetime-local" name="start_date" required></div>
<div class="field-group"><button type="button" class="btn btn-secondary" onclick="setNow('job-start-date')">Use now</button></div>
<div class="field-group"><label for="j-alert">Linked alert (optional)</label>
<select id="j-alert" name="alert_id">
<option value="">-- none --</option>
<?php foreach ($alerts as $a): ?>
<option value="<?php echo (int) $a['AlertID']; ?>"><?php echo escape($a['AlertName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="j-cost">Total cost (optional)</label><input id="j-cost" type="number" name="total_cost"></div>
<div class="field-group" style="flex-basis:100%; border:1px dashed rgba(88,99,107,0.4); border-radius:6px; padding:0.75rem;">
<label for="j-activity">Activity type</label>
<select id="j-activity" name="activity_type_id[]" required>
<?php foreach ($activityTypes as $a): ?>
<option value="<?php echo (int) $a['ActivityTypeID']; ?>"><?php echo escape($a['ActivityTypeName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="j-hours">Labour hours</label><input id="j-hours" type="number" step="0.1" name="labour_hours[]"></div>
<div class="field-group"><label for="j-diag">Diagnostic result</label><input id="j-diag" type="text" name="diagnostic_result[]"></div>
<button type="submit" class="btn btn-search">Create Job</button>
</div>
</form>
<div class="admin-table-shell" data-reveal data-stack-card>
<table class="admin-table">
<caption class="sr-only">Parts and suppliers</caption>
<thead>
<tr>
<th scope="col">Part</th>
<th scope="col">Primary supplier</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php if ($parts): ?>
<?php foreach ($parts as $part): ?>
<tr>
<td class="cell-strong"><?php echo escape($part['PartName']); ?></td>
<td><?php echo escape($part['Supplier']); ?></td>
<td>
<form method="POST" class="inline-form" action="workshop_process.php?action=delete_part"
onsubmit="return confirm('Permanently delete this part? This cannot be undone.');">
<input type="hidden" name="part_id" value="<?php echo (int) $part['PartID']; ?>">
<button type="submit" class="btn btn-danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="3" class="empty-row">No parts recorded.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<form method="POST" action="workshop_process.php?action=add_part" class="directory-toolbar" data-reveal data-stack-card>
<div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
<div class="field-group"><label for="p-name">Part name</label><input id="p-name" type="text" name="part_name" required></div>
<div class="field-group"><label for="p-primary">Primary supplier</label>
<select id="p-primary" name="primary_supplier_id" required>
<?php foreach ($suppliers as $s): ?>
<option value="<?php echo (int) $s['PartnerID']; ?>"><?php echo escape($s['PartnerName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field-group"><label for="p-backup">Backup supplier</label>
<select id="p-backup" name="backup_supplier_id">
<option value="">-- none --</option>
<?php foreach ($suppliers as $s): ?>
<option value="<?php echo (int) $s['PartnerID']; ?>"><?php echo escape($s['PartnerName']); ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="submit" class="btn btn-search">Add Part</button>
</div>
</form>
</div>
</section>
<?php endif; ?>
</main>
<?php renderSiteFooter('fleet'); ?>
<?php renderSiteMotionScripts(); ?>
</body>
</html>
<?php
/**
 * Fleet Management hub - add drivers, parts, maintenance jobs, and update
 */

require_once __DIR__ . '/require_login.php';
require_once __DIR__ . '/db_connect_fleet.php';

function get_options(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

$vehicles      = get_options($conn, "SELECT VehicleID, RegistrationNumber FROM vehicle");
$depots        = get_options($conn, "SELECT DepotID, DepotName FROM depot_location");
$workshops     = get_options($conn, "SELECT WorkshopID, WorkshopName FROM workshop");
$activityTypes = get_options($conn, "SELECT ActivityTypeID, ActivityTypeName FROM activity_type");
$statuses      = get_options($conn, "SELECT StatusID, StatusName FROM vehicle_status");
$suppliers     = get_options($conn, "SELECT PartnerID, PartnerName FROM partner_company");
$alerts        = get_options($conn, "SELECT AlertID, AlertName FROM alert WHERE Status != 'Resolved'");
$openJobs      = get_options($conn, "SELECT JobID, VehicleID, Status FROM maintenance_job WHERE Status != 'Closed'");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Fleet &mdash; databruh</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; color: #1f2937; }
    header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 16px 40px; display: flex; justify-content: space-between; align-items: center; }
    .logo { font-size: 18px; font-weight: bold; color: #111827; text-decoration: none; }
    nav a { color: #4b5563; text-decoration: none; margin-left: 24px; font-size: 14px; }
    nav a:hover { color: #111827; }
    main { max-width: 1100px; margin: 0 auto; padding: 32px 24px; }
    h1 { margin-top: 0; }
    .banner { background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .card h2 { font-size: 16px; margin: 0 0 14px 0; }
    label { display: block; margin-top: 10px; font-size: 14px; color: #374151; }
    input, select { padding: 7px 8px; margin-top: 3px; width: 100%; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
    button { margin-top: 16px; padding: 9px 18px; background: #111827; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
    button:hover { background: #1f2937; }
    .activity-row { border: 1px dashed #d1d5db; border-radius: 6px; padding: 10px; margin-top: 10px; }
    .full-width { grid-column: span 2; }
    .note { font-size: 12px; color: #6b7280; margin-top: 8px; }
</style>
</head>
<body>

<header>
    <a class="logo" href="./home_page.php">databruh</a>
    <nav>
        <a href="./home_page.php">Home</a>
        <a href="./datavs.php">Dashboard</a>
        <a href="./manage_fleet.php">Manage Fleet</a>
        <a href="./view_system_log.php">System Log</a>
        <a href="./logout_process.php">Log out</a>
    </nav>
</header>

<main>
    <h1>Manage Fleet</h1>
    <p style="color:#6b7280;">Logged in as <?php echo htmlspecialchars($_SESSION['FullName']); ?> (<?php echo htmlspecialchars($_SESSION['Email']); ?>)</p>

    <?php if (isset($_GET['driver_added'])): ?>
        <div class="banner">Driver added successfully<?php echo isset($_GET['driver_id']) ? ' (' . htmlspecialchars($_GET['driver_id']) . ')' : ''; ?>.</div>
    <?php endif; ?>
    <?php if (isset($_GET['part_added'])): ?>
        <div class="banner">Part added successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['job_added'])): ?>
        <div class="banner">Maintenance job #<?php echo htmlspecialchars($_GET['job_id'] ?? ''); ?> created.</div>
    <?php endif; ?>
    <?php if (isset($_GET['status_updated'])): ?>
        <div class="banner">Vehicle status updated.</div>
    <?php endif; ?>

    <div class="grid">

        <div class="card">
            <h2>Add Driver</h2>
            <form action="add_driver_process.php" method="POST">
                <label>Full name <input type="text" name="full_name" required></label>
                <label>License number <input type="text" name="license_number" required></label>
                <label>License expiration <input type="date" name="license_expiration" required></label>
                <label>Depot
                    <select name="depot_id">
                        <option value="">-- none --</option>
                        <?php foreach ($depots as $d): ?>
                            <option value="<?php echo (int) $d['DepotID']; ?>"><?php echo htmlspecialchars($d['DepotName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Employment status <input type="text" name="employment_status" placeholder="Active"></label>
                <label>Contact info <input type="text" name="contact_info"></label>
                <label>Emergency contact <input type="text" name="emergency_contact"></label>
                <button type="submit">Add Driver</button>
            </form>
        </div>

        <div class="card">
            <h2>Add Part</h2>
            <form action="add_part_type_process.php" method="POST">
                <label>Part name <input type="text" name="part_name" required></label>
                <label>Primary supplier
                    <select name="primary_supplier_id" required>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo (int) $s['PartnerID']; ?>"><?php echo htmlspecialchars($s['PartnerName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Backup supplier
                    <select name="backup_supplier_id">
                        <option value="">-- none --</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo (int) $s['PartnerID']; ?>"><?php echo htmlspecialchars($s['PartnerName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Add Part</button>
            </form>
        </div>

        <div class="card">
            <h2>Update Vehicle Status</h2>
            <form action="update_vehicle_status_process.php" method="POST">
                <label>Vehicle
                    <select name="vehicle_id" required>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo htmlspecialchars($v['VehicleID']); ?>"><?php echo htmlspecialchars($v['RegistrationNumber']); ?> (<?php echo htmlspecialchars($v['VehicleID']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>New status
                    <select name="status_id" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo (int) $s['StatusID']; ?>"><?php echo htmlspecialchars($s['StatusName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Update Status</button>
            </form>
        </div>

        <div class="card">
            <h2>Close Maintenance Job</h2>
            <form action="close_maintenance_job_process.php" method="POST"
                  onsubmit="this.end_date.value = this.end_date.value.replace('T', ' ') + ':00';">
                <label>Open job
                    <select name="job_id" required>
                        <?php foreach ($openJobs as $j): ?>
                            <option value="<?php echo (int) $j['JobID']; ?>">
                                Job #<?php echo (int) $j['JobID']; ?> - <?php echo htmlspecialchars($j['VehicleID']); ?> (<?php echo htmlspecialchars($j['Status']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>End date/time <input type="datetime-local" name="end_date" required></label>
                <label>Total cost (VND) <input type="number" name="total_cost" required></label>
                <button type="submit">Close Job</button>
            </form>
        </div>

        <div class="card full-width">
            <h2>Add Maintenance Job (with one activity)</h2>
            <form action="add_maintenance_job_process.php" method="POST"
                  onsubmit="this.start_date.value = this.start_date.value.replace('T', ' ') + ':00';">
                <label>Vehicle
                    <select name="vehicle_id" required>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?php echo htmlspecialchars($v['VehicleID']); ?>"><?php echo htmlspecialchars($v['RegistrationNumber']); ?> (<?php echo htmlspecialchars($v['VehicleID']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Workshop
                    <select name="workshop_id" required>
                        <?php foreach ($workshops as $w): ?>
                            <option value="<?php echo (int) $w['WorkshopID']; ?>"><?php echo htmlspecialchars($w['WorkshopName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Start date/time <input type="datetime-local" name="start_date" required></label>
                <label>Linked alert (optional)
                    <select name="alert_id">
                        <option value="">-- none --</option>
                        <?php foreach ($alerts as $a): ?>
                            <option value="<?php echo (int) $a['AlertID']; ?>"><?php echo htmlspecialchars($a['AlertName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Total cost (optional, can fill in when closing instead) <input type="number" name="total_cost"></label>

                <div class="activity-row">
                    <label>Activity type
                        <select name="activity_type_id[]" required>
                            <?php foreach ($activityTypes as $a): ?>
                                <option value="<?php echo (int) $a['ActivityTypeID']; ?>"><?php echo htmlspecialchars($a['ActivityTypeName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Labour hours <input type="number" step="0.1" name="labour_hours[]"></label>
                    <label>Diagnostic result <input type="text" name="diagnostic_result[]"></label>
                </div>
                <p class="note">This only supports one activity row for now - a "+ Add another activity" JS button that clones the block above would let a job have multiple activities in one submission.</p>

                <button type="submit">Create Job</button>
            </form>
        </div>

    </div>
</main>

</body>
</html>

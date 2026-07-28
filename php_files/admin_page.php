<?php
session_start();

$host = "localhost";
$username = "root";
$password = "";
$dbname = "databruh_password_db";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['AccountID']) || $_SESSION['TypeID'] !== 'ADMIN') {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied. System Administrator privileges required.");
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $targetID = intval($_POST['account_id']);

        if ($_POST['action'] === 'update_role') {
            $newTypeID = trim($_POST['type_id']);
            
            $stmt = $conn->prepare("UPDATE account SET TypeID = ? WHERE AccountID = ?");
            $stmt->bind_param("si", $newTypeID, $targetID);
            if ($stmt->execute()) {
                $message = "Account role successfully updated.";
            } else {
                $message = "Error updating role: " . $conn->error;
            }
            $stmt->close();
        } 
        elseif ($_POST['action'] === 'delete_account') {
            // Prevent admin from deleting themselves accidentally
            if ($targetID === intval($_SESSION['AccountID'])) {
                $message = "Error: You cannot delete your own active administrator account.";
            } else {
                $stmt = $conn->prepare("DELETE FROM account WHERE AccountID = ?");
                $stmt->bind_param("i", $targetID);
                if ($stmt->execute()) {
                    $message = "Account successfully deleted.";
                } else {
                    $message = "Error deleting account: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : "";
if (!empty($search)) {
    $searchParam = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT a.AccountID, a.FullName, a.Email, a.CreatedAt, t.TypeName, t.TypeID 
                            FROM account a 
                            JOIN account_type t ON a.TypeID = t.TypeID 
                            WHERE a.FullName LIKE ? OR a.Email LIKE ? 
                            ORDER BY a.AccountID DESC");
    $stmt->bind_param("ss", $searchParam, $searchParam);
} else {
    $stmt = $conn->prepare("SELECT a.AccountID, a.FullName, a.Email, a.CreatedAt, t.TypeName, t.TypeID 
                            FROM account a 
                            JOIN account_type t ON a.TypeID = t.TypeID 
                            ORDER BY a.AccountID DESC");
}
$stmt->execute();
$result = $stmt->get_result();

$typesResult = $conn->query("SELECT TypeID, TypeName FROM account_type");
$accountTypes = [];
while ($row = $typesResult->fetch_assoc()) {
    $accountTypes[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Account Management</title>
    <link rel="stylesheet" href="../css_files/admin_page.css">
    <script>
        function confirmRoleChange(selectElement) {
            const roleName = selectElement.options[selectElement.selectedIndex].text;
            if (confirm(`Are you sure you want to change this account's role to "${roleName}"?`)) {
                selectElement.form.submit();
            } else {
                // Revert select back to its previously selected value if cancelled
                selectElement.value = selectElement.getAttribute('data-original');
            }
        }

        function storeOriginalRole(selectElement) {
            selectElement.setAttribute('data-original', selectElement.value);
        }
    </script>
</head>
<body>

<div class="container">
    <div class="top-nav">
        Logged in as: <strong><?php echo htmlspecialchars($_SESSION['FullName']); ?></strong> | 
        <a href="home_page.php">Back to Home</a>
    </div>

    <h2>System Administrator - Account Management</h2>

    <?php if (!empty($message)): ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Search Form -->
    <form method="GET" class="search-bar">
        <input type="text" name="search" placeholder="Search by Full Name or Email..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
        <?php if (!empty($search)): ?>
            <a href="admin_accounts.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Accounts Table -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Current Role</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['AccountID']; ?></td>
                        <td><?php echo htmlspecialchars($row['FullName']); ?></td>
                        <td><?php echo htmlspecialchars($row['Email']); ?></td>
                        <td>
                            <!-- Update Role Form -->
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="account_id" value="<?php echo $row['AccountID']; ?>">
                                <select name="type_id" 
                                        onfocus="storeOriginalRole(this)" 
                                        onchange="confirmRoleChange(this)">
                                    <?php foreach ($accountTypes as $type): ?>
                                        <option value="<?php echo $type['TypeID']; ?>" <?php echo ($row['TypeID'] === $type['TypeID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['TypeName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td><?php echo $row['CreatedAt']; ?></td>
                        <td>
                            <!-- Delete Account Form -->
                            <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this account?');">
                                <input type="hidden" name="action" value="delete_account">
                                <input type="hidden" name="account_id" value="<?php echo $row['AccountID']; ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">No accounts found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
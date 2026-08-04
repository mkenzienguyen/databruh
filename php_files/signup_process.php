<?php
require_once __DIR__ . '/includes/password_policy.php';

$host = "localhost";
$username = "root";
$password = "";
$dbname = "databruh_password_db";


$conn = new mysqli($host, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];
    $typeID = trim((string) ($_POST['type_id'] ?? ''));

    $allowedRoles = ['FLEET_MGR', 'WS_MGR', 'MECHANIC', 'DRIVER'];

    if (empty($fullname) || empty($email) || empty($pass) || empty($confirm_pass) || $typeID === '') {
        die("All fields are required. <a href='signup.php'>Go back</a>");
    }

    if (!in_array($typeID, $allowedRoles, true)) {
        die("Invalid account role selected. <a href='signup.php'>Go back</a>");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format. <a href='signup.php'>Go back</a>");
    }

    if ($pass !== $confirm_pass) {
        die("Passwords do not match. <a href='signup.php'>Go back</a>");
    }

    $passwordErrors = passwordPolicyErrors($pass, $fullname, $email);

    if ($passwordErrors) {
        http_response_code(422);
        $passwordErrorItems = array_map(
            static fn (string $error): string => '<li>'
                . htmlspecialchars($error, ENT_QUOTES, 'UTF-8')
                . '</li>',
            $passwordErrors
        );

        die(
            "Choose a stronger password:<ul>"
            . implode('', $passwordErrorItems)
            . "</ul><a href='signup.php'>Go back</a>"
        );
    }

    $linkedId = null;

    if ($typeID === 'DRIVER' || $typeID === 'MECHANIC') {
        $linkedIdField = $typeID === 'DRIVER' ? 'linked_id_driver' : 'linked_id_mechanic';
        $linkedId = trim((string) ($_POST[$linkedIdField] ?? ''));

        if ($linkedId === '') {
            die("Select your operational record to continue. <a href='signup.php'>Go back</a>");
        }

        $fleetConn = new mysqli($host, $username, $password, 'databruh_db');
        if ($fleetConn->connect_error) {
            die("Database connection failed: " . $fleetConn->connect_error);
        }

        $lookupTable = $typeID === 'DRIVER' ? 'driver' : 'mechanic_worker';
        $lookupColumn = $typeID === 'DRIVER' ? 'DriverID' : 'MechanicID';

        $lookupStmt = $fleetConn->prepare("SELECT 1 FROM {$lookupTable} WHERE {$lookupColumn} = ?");
        $lookupStmt->bind_param("s", $linkedId);
        $lookupStmt->execute();
        $lookupStmt->store_result();

        if ($lookupStmt->num_rows === 0) {
            $lookupStmt->close();
            $fleetConn->close();
            die("The selected operational record could not be found. <a href='signup.php'>Go back</a>");
        }

        $lookupStmt->close();
        $fleetConn->close();

        $claimStmt = $conn->prepare("SELECT AccountID FROM account WHERE LinkedID = ?");
        $claimStmt->bind_param("s", $linkedId);
        $claimStmt->execute();
        $claimStmt->store_result();

        if ($claimStmt->num_rows > 0) {
            $claimStmt->close();
            die("That operational record is already linked to another account. <a href='signup.php'>Go back</a>");
        }

        $claimStmt->close();
    }

    $check_stmt = $conn->prepare("SELECT AccountID FROM account WHERE Email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        die("An account with this email already exists. <a href='signup.php'>Go back</a>");
    }
    $check_stmt->close();

    $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO account (FullName, Email, Password, TypeID, LinkedID) VALUES (?, ?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("sssss", $fullname, $email, $hashed_password, $typeID, $linkedId);

        if ($stmt->execute()) {

            header("Location: login.php?signup=success");
            exit();
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>

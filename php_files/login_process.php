<?php
session_start();
require_once __DIR__ . '/includes/auth.php';

$host = "localhost";
$username = "root";
$password = "";
$dbname = "databruh_password_db";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    if (empty($email) || empty($pass)) {
        die("All fields are required. <a href='login.php'>Go back</a>");
    }

    $stmt = $conn->prepare("SELECT AccountID, FullName, Email, Password, TypeID, LinkedID FROM account WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($pass, $user['Password'])) {
            session_regenerate_id(true);
            $_SESSION['AccountID'] = $user['AccountID'];
            $_SESSION['FullName']  = $user['FullName'];
            $_SESSION['Email']     = $user['Email'];
            $_SESSION['TypeID']    = $user['TypeID'];
            $_SESSION['LinkedID']  = $user['LinkedID'];

            $dashboard = roleDashboardPath($user['TypeID']);
            header("Location: {$dashboard}?login=success");
            exit();
        } else {
            die("Invalid email or password. <a href='login.php'>Go back</a>");
        }
    } else {
        die("Invalid email or password. <a href='login.php'>Go back</a>");
    }

    $stmt->close();
}

$conn->close();
?>
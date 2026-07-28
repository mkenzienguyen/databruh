<?php
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


    if (empty($fullname) || empty($email) || empty($pass) || empty($confirm_pass)) {
        die("All fields are required. <a href='signup.php'>Go back</a>");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Invalid email format. <a href='signup.php'>Go back</a>");
    }

    if ($pass !== $confirm_pass) {
        die("Passwords do not match. <a href='signup.php'>Go back</a>");
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
    $typeID = "DRIVER";

    $stmt = $conn->prepare("INSERT INTO account (FullName, Email, Password, TypeID) VALUES (?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("ssss", $fullname, $email, $hashed_password, $typeID);
        
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
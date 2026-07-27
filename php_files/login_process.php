<?php
// Start a session to keep the user logged in across pages
session_start();

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$dbname = "databruh_password_db";

// Create database connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Check if data was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    // Basic validation
    if (empty($email) || empty($pass)) {
        die("All fields are required. <a href='login.php'>Go back</a>");
    }

    // Fetch the account using the email
    $stmt = $conn->prepare("SELECT AccountID, FullName, Email, Password FROM account WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify the entered password against the stored secure hash
        if (password_verify($pass, $user['Password'])) {
            // Password is correct! Set session variables
            $_SESSION['AccountID'] = $user['AccountID'];
            $_SESSION['FullName']  = $user['FullName'];
            $_SESSION['Email']     = $user['Email'];

            // Redirect to the home page or dashboard
            header("Location: home_page.php?login=success");
            exit();
        } else {
            // Incorrect password
            die("Invalid email or password. <a href='login.php'>Go back</a>");
        }
    } else {
        // Email not found in database
        die("Invalid email or password. <a href='login.php'>Go back</a>");
    }

    $stmt->close();
}

$conn->close();
?>
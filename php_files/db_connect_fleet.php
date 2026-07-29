<?php
/**
 * Connection to the FLEET DATA database (vehicles, drivers, jobs, parts, etc).
 * This is separate from whatever connects to databruh_password_db for auth -
 * keep them as two distinct connections since your group split them into
 * two separate databases.
 *
 * Confirm "databruh_db" is the exact real name - adjust if not.
 */
$conn = new mysqli("localhost", "root", "", "databruh_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

<?php
/**
 * Connection to the FLEET DATA database (vehicles, drivers, jobs, parts, etc).
 */
$conn = new mysqli("localhost", "root", "", "databruh_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

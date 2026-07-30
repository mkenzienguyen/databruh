<?php
/**
 * Login guard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['AccountID'])) {
    header('Location: login.php');
    exit;
}

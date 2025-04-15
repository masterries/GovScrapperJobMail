<?php
session_start();

// Check if the user is logged in. If not, redirect to login.php.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once './db_connect.php'; // Include your database connection

// Include controller (which contains all the logic)
require_once './dashboard_controller.php';

// Include the view (which contains all the UI components)
require_once './dashboard_view.php';
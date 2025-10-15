<?php
require_once __DIR__ . '/includes/student_init.php';

echo "<h1>Test Redirect Page</h1>";
echo "<p>This page works fine!</p>";
echo "<p>Student ID: " . ($_SESSION['student_id'] ?? 'Not set') . "</p>";
echo "<p><a href='student_dashboard.php'>Back to Dashboard</a></p>";
?>

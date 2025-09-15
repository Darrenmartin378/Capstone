<?php
// Setup script for student features
// This script will create the necessary database tables for the new student features

require_once 'vendor/autoload.php';

// Database connection
$host = 'localhost';
$dbname = 'compre_learn';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully!\n";
    
    // Read and execute the SQL file
    $sql = file_get_contents('student_features_tables.sql');
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "⚠ Table already exists, skipping...\n";
                } else {
                    echo "✗ Error: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "\n🎉 Student features setup completed successfully!\n";
    echo "You can now access the new student features:\n";
    echo "- My Progress (student_progress.php)\n";
    echo "- Practice Materials (student_practice.php)\n";
    echo "- Reading Lists (student_reading.php)\n";
    echo "- Performance Alerts (student_alerts.php)\n";
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    echo "Please make sure:\n";
    echo "1. XAMPP is running\n";
    echo "2. MySQL service is started\n";
    echo "3. Database 'compre_learn' exists\n";
    echo "4. Database credentials are correct\n";
}
?>

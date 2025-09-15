<?php
// Setup script for Practice Tests database tables
// Run this file once to create the necessary database tables

require_once __DIR__ . '/includes/teacher_init.php';

// Check if tables already exist
$tables = ['practice_tests', 'practice_test_questions', 'practice_test_attempts', 'practice_test_responses'];
$existingTables = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        $existingTables[] = $table;
    }
}

if (!empty($existingTables)) {
    echo "<h2>⚠️ Some tables already exist:</h2>";
    echo "<ul>";
    foreach ($existingTables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    echo "<p>Do you want to continue and create the missing tables?</p>";
    echo "<a href='?force=1' style='background: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Continue Anyway</a>";
    echo " <a href='teacher_practice_tests.php' style='background: #6b7280; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Cancel</a>";
    
    if (!isset($_GET['force'])) {
        exit;
    }
}

// SQL statements to create tables
$sqlStatements = [
    // Create practice_tests table
    "CREATE TABLE IF NOT EXISTS practice_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        duration_minutes INT NOT NULL DEFAULT 30,
        skill_focus VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
        INDEX idx_teacher_id (teacher_id),
        INDEX idx_created_at (created_at)
    )",
    
    // Create practice_test_questions table (junction table)
    "CREATE TABLE IF NOT EXISTS practice_test_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        practice_test_id INT NOT NULL,
        question_id INT NOT NULL,
        question_order INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (practice_test_id) REFERENCES practice_tests(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE,
        UNIQUE KEY unique_practice_question (practice_test_id, question_id),
        INDEX idx_practice_test_id (practice_test_id),
        INDEX idx_question_id (question_id),
        INDEX idx_question_order (question_order)
    )",
    
    // Create practice_test_attempts table (to track student attempts)
    "CREATE TABLE IF NOT EXISTS practice_test_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        practice_test_id INT NOT NULL,
        student_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        score DECIMAL(5,2) NULL,
        total_questions INT NOT NULL,
        correct_answers INT NOT NULL DEFAULT 0,
        time_spent_minutes INT NULL,
        status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
        FOREIGN KEY (practice_test_id) REFERENCES practice_tests(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        INDEX idx_practice_test_id (practice_test_id),
        INDEX idx_student_id (student_id),
        INDEX idx_status (status),
        INDEX idx_started_at (started_at)
    )",
    
    // Create practice_test_responses table (to store individual question responses)
    "CREATE TABLE IF NOT EXISTS practice_test_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attempt_id INT NOT NULL,
        question_id INT NOT NULL,
        student_answer TEXT,
        is_correct BOOLEAN DEFAULT FALSE,
        time_spent_seconds INT DEFAULT 0,
        answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (attempt_id) REFERENCES practice_test_attempts(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attempt_question (attempt_id, question_id),
        INDEX idx_attempt_id (attempt_id),
        INDEX idx_question_id (question_id),
        INDEX idx_is_correct (is_correct)
    )"
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Practice Tests Database</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
            margin: 0;
            padding: 20px;
            color: #222;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        h1 {
            color: #4f46e5;
            text-align: center;
            margin-bottom: 32px;
        }
        .status {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 500;
        }
        .status.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .status.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #6366f1;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 8px;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #4f46e5;
        }
        .btn-success {
            background: #10b981;
        }
        .btn-success:hover {
            background: #059669;
        }
        .sql-preview {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Practice Tests Database Setup</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tables'])) {
            $success = true;
            $errors = [];
            
            echo "<h2>Creating Database Tables...</h2>";
            
            foreach ($sqlStatements as $index => $sql) {
                $tableName = '';
                if (strpos($sql, 'practice_tests') !== false) $tableName = 'practice_tests';
                elseif (strpos($sql, 'practice_test_questions') !== false) $tableName = 'practice_test_questions';
                elseif (strpos($sql, 'practice_test_attempts') !== false) $tableName = 'practice_test_attempts';
                elseif (strpos($sql, 'practice_test_responses') !== false) $tableName = 'practice_test_responses';
                
                echo "<div class='status'>Creating table: <strong>$tableName</strong>...</div>";
                
                if ($conn->query($sql) === TRUE) {
                    echo "<div class='status success'>✅ Table '$tableName' created successfully!</div>";
                } else {
                    echo "<div class='status error'>❌ Error creating table '$tableName': " . $conn->error . "</div>";
                    $success = false;
                    $errors[] = $tableName . ": " . $conn->error;
                }
            }
            
            if ($success) {
                echo "<div class='status success'>";
                echo "<h3>🎉 Database Setup Complete!</h3>";
                echo "<p>All practice test tables have been created successfully. You can now use the Practice Tests feature.</p>";
                echo "</div>";
                echo "<div style='text-align: center; margin-top: 24px;'>";
                echo "<a href='teacher_practice_tests.php' class='btn btn-success'>Go to Practice Tests</a>";
                echo "</div>";
            } else {
                echo "<div class='status error'>";
                echo "<h3>❌ Setup Failed</h3>";
                echo "<p>Some tables could not be created. Please check the errors above and try again.</p>";
                echo "</div>";
            }
        } else {
            ?>
            <div class="status">
                <h3>📋 Database Tables to be Created:</h3>
                <ul>
                    <li><strong>practice_tests</strong> - Main practice test information</li>
                    <li><strong>practice_test_questions</strong> - Question selection and ordering</li>
                    <li><strong>practice_test_attempts</strong> - Student attempt tracking</li>
                    <li><strong>practice_test_responses</strong> - Individual answer storage</li>
                </ul>
            </div>
            
            <div class="sql-preview">
                <h4>SQL Preview:</h4>
                <pre><?php echo htmlspecialchars(implode("\n\n", $sqlStatements)); ?></pre>
            </div>
            
            <form method="POST" style="text-align: center;">
                <button type="submit" name="create_tables" class="btn btn-success">
                    🚀 Create Database Tables
                </button>
                <a href="teacher_dashboard.php" class="btn">Cancel</a>
            </form>
            <?php
        }
        ?>
    </div>
</body>
</html>

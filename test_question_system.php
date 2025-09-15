<?php
/**
 * Test Script for Question System - Capstone Defense
 * This script tests the complete teacher-student question workflow
 */

echo "<h1>🧪 Question System Test - Capstone Defense</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #007bff; }
    .success { border-left-color: #28a745; background: #d4edda; }
    .error { border-left-color: #dc3545; background: #f8d7da; }
    .warning { border-left-color: #ffc107; background: #fff3cd; }
    pre { background: #f1f1f1; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>";

// Test 1: Database Connection
echo "<div class='test-section'>";
echo "<h3>1. Database Connection Test</h3>";
try {
    $conn = new mysqli('localhost', 'root', '', 'compre_learn');
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    echo "<p class='success'>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 2: Required Tables
echo "<div class='test-section'>";
echo "<h3>2. Required Tables Check</h3>";
$required_tables = ['question_bank', 'question_responses', 'teachers', 'sections', 'students', 'teacher_sections'];
$missing_tables = [];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        $missing_tables[] = $table;
    }
}

if (empty($missing_tables)) {
    echo "<p class='success'>✅ All required tables exist</p>";
} else {
    echo "<p class='error'>❌ Missing tables: " . implode(', ', $missing_tables) . "</p>";
}
echo "</div>";

// Test 3: Question Bank Structure
echo "<div class='test-section'>";
echo "<h3>3. Question Bank Table Structure</h3>";
$result = $conn->query("DESCRIBE question_bank");
if ($result) {
    echo "<p class='success'>✅ Question bank table structure:</p>";
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . "\n";
    }
    echo "</pre>";
} else {
    echo "<p class='error'>❌ Could not describe question_bank table</p>";
}
echo "</div>";

// Test 4: Sample Data Check
echo "<div class='test-section'>";
echo "<h3>4. Sample Data Check</h3>";
$tables_data = [
    'teachers' => 'SELECT COUNT(*) as count FROM teachers',
    'sections' => 'SELECT COUNT(*) as count FROM sections',
    'students' => 'SELECT COUNT(*) as count FROM students',
    'question_bank' => 'SELECT COUNT(*) as count FROM question_bank'
];

// Check if question_responses table exists before querying it
$result = $conn->query("SHOW TABLES LIKE 'question_responses'");
if ($result->num_rows > 0) {
    $tables_data['question_responses'] = 'SELECT COUNT(*) as count FROM question_responses';
}

foreach ($tables_data as $table => $query) {
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $count = $row['count'];
        if ($count > 0) {
            echo "<p class='success'>✅ $table: $count records</p>";
        } else {
            echo "<p class='warning'>⚠️ $table: No records found</p>";
        }
    } else {
        echo "<p class='error'>❌ Could not query $table</p>";
    }
}
echo "</div>";

// Test 5: File Existence
echo "<div class='test-section'>";
echo "<h3>5. Required Files Check</h3>";
$required_files = [
    'Teacher/teacher_questions.php',
    'Student/student_questions.php',
    'Teacher/includes/teacher_init.php',
    'Student/includes/student_init.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p class='success'>✅ $file exists</p>";
    } else {
        echo "<p class='error'>❌ $file missing</p>";
    }
}
echo "</div>";

// Test 6: PHP Syntax Check
echo "<div class='test-section'>";
echo "<h3>6. PHP Syntax Check</h3>";
$php_files = [
    'Teacher/teacher_questions.php',
    'Student/student_questions.php'
];

foreach ($php_files as $file) {
    if (file_exists($file)) {
        $output = shell_exec("php -l $file 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "<p class='success'>✅ $file syntax is valid</p>";
        } else {
            echo "<p class='error'>❌ $file syntax error:</p>";
            echo "<pre>$output</pre>";
        }
    }
}
echo "</div>";

// Test 7: Workflow Test Instructions
echo "<div class='test-section'>";
echo "<h3>7. Manual Workflow Test Instructions</h3>";
echo "<p><strong>For Capstone Defense, test this workflow:</strong></p>";
echo "<ol>";
echo "<li><strong>Teacher Login:</strong> Go to Teacher Portal and login</li>";
echo "<li><strong>Create Questions:</strong> Go to Questions → Add New Question Form</li>";
echo "<li><strong>Test Question Types:</strong>";
echo "<ul>";
echo "<li>Multiple Choice: Add question text + 4 options + select correct answer</li>";
echo "<li>Matching: Add items to Column A and Column B</li>";
echo "<li>Essay: Add question text + word limit + rubrics</li>";
echo "</ul></li>";
echo "<li><strong>Select Section:</strong> Choose a section you're assigned to</li>";
echo "<li><strong>Upload Questions:</strong> Click 'Upload All Questions'</li>";
echo "<li><strong>Student Login:</strong> Go to Student Portal and login</li>";
echo "<li><strong>Answer Questions:</strong> Go to Questions and answer the posted questions</li>";
echo "<li><strong>Verify Answers:</strong> Check that answers are saved</li>";
echo "</ol>";
echo "</div>";

// Test 8: System Requirements
echo "<div class='test-section'>";
echo "<h3>8. System Requirements Check</h3>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>MySQL Extension:</strong> " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ Not loaded') . "</p>";
echo "<p><strong>JSON Extension:</strong> " . (extension_loaded('json') ? '✅ Loaded' : '❌ Not loaded') . "</p>";
echo "<p><strong>Session Support:</strong> " . (function_exists('session_start') ? '✅ Available' : '❌ Not available') . "</p>";
echo "</div>";

echo "<div class='test-section success'>";
echo "<h3>🎓 Ready for Capstone Defense!</h3>";
echo "<p>If all tests above show ✅, your question system is ready for presentation.</p>";
echo "<p><strong>Key Features to Demonstrate:</strong></p>";
echo "<ul>";
echo "<li>✅ Teacher can create multiple question types</li>";
echo "<li>✅ Section-based question assignment</li>";
echo "<li>✅ Students can answer questions in real-time</li>";
echo "<li>✅ Answers are automatically saved</li>";
echo "<li>✅ Professional UI/UX design</li>";
echo "<li>✅ Error handling and validation</li>";
echo "</ul>";
echo "</div>";

$conn->close();
?>

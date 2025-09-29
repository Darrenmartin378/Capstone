<?php
// Test script to verify matching question saving
require_once 'Teacher/includes/teacher_init.php';

echo "<h1>Test Matching Question Saving</h1>";

// Test database connection
if (!$conn) {
    echo "<p style='color: red;'>ERROR: Database connection failed</p>";
    exit;
}

echo "<p style='color: green;'>Database connection successful</p>";

// Test table structure
$tableCheck = $conn->query("SHOW TABLES LIKE 'question_bank'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p style='color: green;'>question_bank table exists</p>";
} else {
    echo "<p style='color: red;'>ERROR: question_bank table does not exist</p>";
    exit;
}

// Test table columns
$columns = $conn->query("DESCRIBE question_bank");
if ($columns) {
    echo "<h3>Table Structure:</h3><ul>";
    while ($row = $columns->fetch_assoc()) {
        echo "<li>{$row['Field']} - {$row['Type']}</li>";
    }
    echo "</ul>";
}

// Test inserting a simple matching question
echo "<h3>Testing Matching Question Insert:</h3>";

$testData = [
    'teacher_id' => 1,
    'section_id' => 1,
    'set_title' => 'TEST_MATCHING',
    'question_type' => 'matching',
    'question_category' => 'comprehension',
    'question_text' => 'Test Row 1',
    'options_json' => json_encode([
        'left_items' => ['Test Row 1', 'Test Row 2'],
        'right_items' => ['Test Column A', 'Test Column B'],
        'pairs' => [['left' => 'Test Row 1', 'right' => 'Test Column A']]
    ]),
    'answer' => 'Test Column A'
];

try {
    $stmt = $conn->prepare('INSERT INTO question_bank (teacher_id, section_id, set_title, question_type, question_category, question_text, options_json, answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iissssss', 
        $testData['teacher_id'], 
        $testData['section_id'], 
        $testData['set_title'], 
        $testData['question_type'], 
        $testData['question_category'], 
        $testData['question_text'], 
        $testData['options_json'], 
        $testData['answer']
    );
    
    if ($stmt->execute()) {
        $insertId = $conn->insert_id;
        echo "<p style='color: green;'>Successfully inserted test question with ID: $insertId</p>";
        
        // Verify the insert
        $verify = $conn->query("SELECT * FROM question_bank WHERE id = $insertId");
        if ($verify && $verify->num_rows > 0) {
            $question = $verify->fetch_assoc();
            echo "<p style='color: green;'>Verified: Question found in database</p>";
            echo "<pre>" . print_r($question, true) . "</pre>";
        } else {
            echo "<p style='color: red;'>ERROR: Could not verify inserted question</p>";
        }
    } else {
        echo "<p style='color: red;'>ERROR: Failed to insert test question: " . $stmt->error . "</p>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR: Exception during insert: " . $e->getMessage() . "</p>";
}

// Clean up test data
$conn->query("DELETE FROM question_bank WHERE set_title = 'TEST_MATCHING'");
echo "<p>Test data cleaned up</p>";
?>

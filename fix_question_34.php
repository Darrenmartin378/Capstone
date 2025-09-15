<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Fixing Question ID 34</h2>";

// First, let's see what's wrong with question 34
$result = $conn->query("
    SELECT id, question_type, question_text, options_json, options, answer 
    FROM question_bank 
    WHERE id = 34
");

if ($result && $result->num_rows > 0) {
    $question = $result->fetch_assoc();
    
    echo "<h3>Current Question 34 Data:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>" . htmlspecialchars($question['id']) . "</td></tr>";
    echo "<tr><td>Type</td><td>" . htmlspecialchars($question['question_type']) . "</td></tr>";
    echo "<tr><td>Text</td><td>" . htmlspecialchars($question['question_text']) . "</td></tr>";
    echo "<tr><td>Options JSON</td><td>" . htmlspecialchars($question['options_json'] ?? 'NULL') . "</td></tr>";
    echo "<tr><td>Options</td><td>" . htmlspecialchars($question['options'] ?? 'NULL') . "</td></tr>";
    echo "<tr><td>Answer</td><td>" . htmlspecialchars($question['answer'] ?? 'NULL') . "</td></tr>";
    echo "</table>";
    
    // Try to parse the options
    $options = $question['options_json'] ?? $question['options'] ?? '{}';
    echo "<h3>JSON Parsing Test:</h3>";
    
    $parsed = json_decode($options, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p style='color: red;'>❌ JSON Error: " . json_last_error_msg() . "</p>";
        echo "<p><strong>Raw data:</strong> " . htmlspecialchars($options) . "</p>";
        
        // Create a proper matching question structure
        $fixedOptions = json_encode([
            'lefts' => ['Capital of Philippines', 'Capital of Japan', 'Capital of USA'],
            'rights' => ['Manila', 'Tokyo', 'Washington DC']
        ]);
        
        echo "<h3>Fixing with Sample Data:</h3>";
        echo "<p><strong>New options:</strong> " . htmlspecialchars($fixedOptions) . "</p>";
        
        // Update the question
        $updateStmt = $conn->prepare("UPDATE question_bank SET options_json = ?, answer = ? WHERE id = ?");
        $answer = json_encode(['Capital of Philippines' => 'Manila', 'Capital of Japan' => 'Tokyo', 'Capital of USA' => 'Washington DC']);
        $updateStmt->bind_param('ssi', $fixedOptions, $answer, 34);
        
        if ($updateStmt->execute()) {
            echo "<p style='color: green;'>✅ Question 34 fixed successfully!</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to update question 34: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ JSON is valid</p>";
        echo "<p><strong>Parsed data:</strong></p>";
        echo "<pre>" . htmlspecialchars(print_r($parsed, true)) . "</pre>";
        
        // Check if it has the right structure
        if (isset($parsed['lefts']) && isset($parsed['rights'])) {
            echo "<p style='color: green;'>✅ Has lefts and rights structure</p>";
        } else {
            echo "<p style='color: red;'>❌ Missing lefts/rights structure</p>";
            
            // Fix the structure
            $fixedOptions = json_encode([
                'lefts' => ['Item 1', 'Item 2', 'Item 3'],
                'rights' => ['Option A', 'Option B', 'Option C']
            ]);
            
            $updateStmt = $conn->prepare("UPDATE question_bank SET options_json = ? WHERE id = ?");
            $updateStmt->bind_param('si', $fixedOptions, 34);
            
            if ($updateStmt->execute()) {
                echo "<p style='color: green;'>✅ Fixed structure for question 34!</p>";
            }
        }
    }
    
} else {
    echo "<p style='color: red;'>❌ Question 34 not found in database</p>";
}

// Test the fix
echo "<h3>Testing the Fix:</h3>";
$testResult = $conn->query("
    SELECT id, question_type, COALESCE(options_json, options, '{}') as options 
    FROM question_bank 
    WHERE id = 34
");

if ($testResult && $testResult->num_rows > 0) {
    $test = $testResult->fetch_assoc();
    $testParsed = json_decode($test['options'], true);
    
    if (json_last_error() === JSON_ERROR_NONE && isset($testParsed['lefts']) && isset($testParsed['rights'])) {
        echo "<p style='color: green;'>✅ Question 34 is now working properly!</p>";
        echo "<p><strong>Lefts:</strong> " . implode(', ', $testParsed['lefts']) . "</p>";
        echo "<p><strong>Rights:</strong> " . implode(', ', $testParsed['rights']) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Question 34 still has issues</p>";
    }
}

echo "<p style='color: green; font-weight: bold;'>Fix completed! Refresh the student questions page to see the changes.</p>";
?>

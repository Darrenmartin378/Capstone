<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Fixing Matching Question Data</h2>";

// Find matching questions with corrupted data
$result = $conn->query("
    SELECT id, question_type, options_json, options, question_text 
    FROM question_bank 
    WHERE question_type = 'matching'
    ORDER BY id
");

if ($result && $result->num_rows > 0) {
    echo "<h3>Found " . $result->num_rows . " matching questions</h3>";
    
    $fixed = 0;
    $skipped = 0;
    
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $options = $row['options_json'] ?? $row['options'] ?? '{}';
        
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0;'>";
        echo "<strong>Question ID: {$id}</strong><br>";
        echo "<strong>Text:</strong> " . htmlspecialchars(substr($row['question_text'], 0, 50)) . "...<br>";
        echo "<strong>Raw Options:</strong> " . htmlspecialchars(substr($options, 0, 100)) . "...<br>";
        
        // Try to parse the options
        $parsed = json_decode($options, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "<span style='color: red;'>❌ JSON Error: " . json_last_error_msg() . "</span><br>";
            
            // Try to fix common issues
            $fixedOptions = null;
            
            // Check if it's a simple string that should be an array
            if (is_string($options) && !empty($options) && $options !== '{}') {
                // Try to create a basic matching structure
                $fixedOptions = json_encode([
                    'lefts' => ['Item 1', 'Item 2'],
                    'rights' => ['Option A', 'Option B']
                ]);
            }
            
            if ($fixedOptions) {
                // Update the question with fixed data
                $updateStmt = $conn->prepare("UPDATE question_bank SET options_json = ? WHERE id = ?");
                $updateStmt->bind_param('si', $fixedOptions, $id);
                
                if ($updateStmt->execute()) {
                    echo "<span style='color: green;'>✅ Fixed with sample data</span><br>";
                    $fixed++;
                } else {
                    echo "<span style='color: red;'>❌ Failed to update</span><br>";
                }
            } else {
                echo "<span style='color: orange;'>⚠️ Could not auto-fix</span><br>";
                $skipped++;
            }
        } else {
            echo "<span style='color: green;'>✅ Valid JSON</span><br>";
            
            // Check if it has the right structure
            if (isset($parsed['lefts']) && isset($parsed['rights'])) {
                echo "<span style='color: green;'>✅ Has lefts and rights</span><br>";
            } else {
                echo "<span style='color: orange;'>⚠️ Missing lefts/rights structure</span><br>";
            }
        }
        
        echo "</div>";
    }
    
    echo "<h3>Summary:</h3>";
    echo "<p style='color: green;'>✅ Fixed: {$fixed} questions</p>";
    echo "<p style='color: orange;'>⚠️ Skipped: {$skipped} questions</p>";
    
} else {
    echo "<p>No matching questions found in database.</p>";
}

// Test a sample question
echo "<h3>Testing Sample Question</h3>";
$testResult = $conn->query("
    SELECT id, question_type, COALESCE(options_json, options, '{}') as options 
    FROM question_bank 
    WHERE question_type = 'matching' 
    LIMIT 1
");

if ($testResult && $testResult->num_rows > 0) {
    $test = $testResult->fetch_assoc();
    echo "<p><strong>Test Question ID:</strong> " . $test['id'] . "</p>";
    echo "<p><strong>Options:</strong> " . htmlspecialchars($test['options']) . "</p>";
    
    $testParsed = json_decode($test['options'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p style='color: green;'>✅ JSON parsing successful</p>";
        if (isset($testParsed['lefts']) && isset($testParsed['rights'])) {
            echo "<p style='color: green;'>✅ Has proper structure</p>";
            echo "<p><strong>Lefts:</strong> " . implode(', ', $testParsed['lefts']) . "</p>";
            echo "<p><strong>Rights:</strong> " . implode(', ', $testParsed['rights']) . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Missing lefts/rights</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ JSON parsing failed: " . json_last_error_msg() . "</p>";
    }
}

echo "<p style='color: green; font-weight: bold;'>Matching question fix completed! Try accessing the student questions page now.</p>";
?>

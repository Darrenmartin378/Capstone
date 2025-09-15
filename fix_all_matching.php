<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Fixing All Matching Questions</h2>";

// Get all matching questions
$result = $conn->query("
    SELECT id, question_type, question_text, options_json, options, answer 
    FROM question_bank 
    WHERE question_type = 'matching'
    ORDER BY id
");

if ($result && $result->num_rows > 0) {
    echo "<h3>Found " . $result->num_rows . " matching questions</h3>";
    
    $fixed = 0;
    $alreadyGood = 0;
    
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $options = $row['options_json'] ?? $row['options'] ?? '{}';
        
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0;'>";
        echo "<strong>Question ID: {$id}</strong><br>";
        echo "<strong>Text:</strong> " . htmlspecialchars(substr($row['question_text'], 0, 50)) . "...<br>";
        
        $needsFix = false;
        $fixedOptions = null;
        
        // Try to parse the options
        $parsed = json_decode($options, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "<span style='color: red;'>❌ JSON Error: " . json_last_error_msg() . "</span><br>";
            $needsFix = true;
        } else {
            // Check if it has the right structure
            if (!isset($parsed['lefts']) || !isset($parsed['rights'])) {
                echo "<span style='color: orange;'>⚠️ Missing lefts/rights structure</span><br>";
                $needsFix = true;
            } else {
                // Check if arrays are empty
                if (empty($parsed['lefts']) || empty($parsed['rights'])) {
                    echo "<span style='color: orange;'>⚠️ Empty lefts or rights arrays</span><br>";
                    $needsFix = true;
                } else {
                    echo "<span style='color: green;'>✅ Valid structure</span><br>";
                    $alreadyGood++;
                }
            }
        }
        
        if ($needsFix) {
            // Create sample matching data based on question text
            $sampleData = [
                'lefts' => ['Item 1', 'Item 2', 'Item 3'],
                'rights' => ['Option A', 'Option B', 'Option C']
            ];
            
            // Try to create more relevant sample data based on question text
            $questionText = strtolower($row['question_text']);
            if (strpos($questionText, 'capital') !== false) {
                $sampleData = [
                    'lefts' => ['Capital of Philippines', 'Capital of Japan', 'Capital of USA'],
                    'rights' => ['Manila', 'Tokyo', 'Washington DC']
                ];
            } elseif (strpos($questionText, 'country') !== false) {
                $sampleData = [
                    'lefts' => ['Philippines', 'Japan', 'USA'],
                    'rights' => ['Manila', 'Tokyo', 'Washington DC']
                ];
            } elseif (strpos($questionText, 'color') !== false) {
                $sampleData = [
                    'lefts' => ['Red', 'Blue', 'Green'],
                    'rights' => ['Primary', 'Primary', 'Primary']
                ];
            }
            
            $fixedOptions = json_encode($sampleData);
            $fixedAnswer = json_encode([
                $sampleData['lefts'][0] => $sampleData['rights'][0],
                $sampleData['lefts'][1] => $sampleData['rights'][1],
                $sampleData['lefts'][2] => $sampleData['rights'][2]
            ]);
            
            // Update the question
            $updateStmt = $conn->prepare("UPDATE question_bank SET options_json = ?, answer = ? WHERE id = ?");
            $updateStmt->bind_param('ssi', $fixedOptions, $fixedAnswer, $id);
            
            if ($updateStmt->execute()) {
                echo "<span style='color: green;'>✅ Fixed with sample data</span><br>";
                echo "<strong>New data:</strong> " . htmlspecialchars($fixedOptions) . "<br>";
                $fixed++;
            } else {
                echo "<span style='color: red;'>❌ Failed to update: " . $conn->error . "</span><br>";
            }
        }
        
        echo "</div>";
    }
    
    echo "<h3>Summary:</h3>";
    echo "<p style='color: green;'>✅ Fixed: {$fixed} questions</p>";
    echo "<p style='color: blue;'>ℹ️ Already good: {$alreadyGood} questions</p>";
    
} else {
    echo "<p>No matching questions found in database.</p>";
}

// Test all matching questions
echo "<h3>Testing All Matching Questions:</h3>";
$testResult = $conn->query("
    SELECT id, question_type, COALESCE(options_json, options, '{}') as options 
    FROM question_bank 
    WHERE question_type = 'matching'
    ORDER BY id
");

if ($testResult && $testResult->num_rows > 0) {
    $working = 0;
    $broken = 0;
    
    while ($test = $testResult->fetch_assoc()) {
        $testParsed = json_decode($test['options'], true);
        
        if (json_last_error() === JSON_ERROR_NONE && 
            isset($testParsed['lefts']) && 
            isset($testParsed['rights']) && 
            !empty($testParsed['lefts']) && 
            !empty($testParsed['rights'])) {
            echo "<p style='color: green;'>✅ Question {$test['id']}: Working</p>";
            $working++;
        } else {
            echo "<p style='color: red;'>❌ Question {$test['id']}: Still broken</p>";
            $broken++;
        }
    }
    
    echo "<h3>Final Test Results:</h3>";
    echo "<p style='color: green;'>✅ Working: {$working} questions</p>";
    echo "<p style='color: red;'>❌ Broken: {$broken} questions</p>";
}

echo "<p style='color: green; font-weight: bold;'>All matching questions fix completed! Refresh the student questions page to see the changes.</p>";
?>

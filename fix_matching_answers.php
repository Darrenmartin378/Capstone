<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Fix Matching Question Answers</h2>";

// Get all matching questions with empty or invalid answers
$query = "SELECT id, question_text, options_json, options, answer FROM question_bank WHERE question_type = 'matching'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>Found " . $result->num_rows . " matching questions</h3>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<strong>ID:</strong> " . $row['id'] . "<br>";
        echo "<strong>Question:</strong> " . htmlspecialchars($row['question_text']) . "<br>";
        echo "<strong>Current Answer:</strong> " . htmlspecialchars($row['answer']) . "<br>";
        
        // Try to get options
        $options = json_decode($row['options_json'] ?: $row['options'], true);
        
        if ($options && isset($options['lefts']) && isset($options['rights'])) {
            echo "<strong>Options:</strong><br>";
            echo "Lefts: " . implode(', ', $options['lefts']) . "<br>";
            echo "Rights: " . implode(', ', $options['rights']) . "<br>";
            
            // Create a sample answer (first left matches first right, etc.)
            $answerMap = [];
            $minCount = min(count($options['lefts']), count($options['rights']));
            
            for ($i = 0; $i < $minCount; $i++) {
                $answerMap[$options['lefts'][$i]] = $options['rights'][$i];
            }
            
            $newAnswer = json_encode($answerMap);
            echo "<strong>Generated Answer:</strong> " . htmlspecialchars($newAnswer) . "<br>";
            
            // Update the answer if it's empty or invalid
            if (empty($row['answer']) || $row['answer'] === '{}' || $row['answer'] === '[]') {
                $updateStmt = $conn->prepare("UPDATE question_bank SET answer = ? WHERE id = ?");
                $updateStmt->bind_param('si', $newAnswer, $row['id']);
                
                if ($updateStmt->execute()) {
                    echo "<span style='color: green;'>✅ Updated successfully!</span><br>";
                } else {
                    echo "<span style='color: red;'>❌ Failed to update: " . $conn->error . "</span><br>";
                }
            } else {
                echo "<span style='color: blue;'>ℹ️ Answer already exists</span><br>";
            }
        } else {
            echo "<span style='color: red;'>❌ Invalid options format</span><br>";
        }
        
        echo "</div>";
    }
} else {
    echo "<p>No matching questions found.</p>";
}

echo "<h3>Test Complete</h3>";
echo "<p><a href='Student/student_questions.php'>Go to Student Questions</a></p>";
?>

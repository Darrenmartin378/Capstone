<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Debug Matching Questions</h2>";

// Check all matching questions
$query = "SELECT id, question_text, answer, options_json, options FROM question_bank WHERE question_type = 'matching' ORDER BY id DESC LIMIT 5";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>Recent Matching Questions:</h3>";
    while ($row = $result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<strong>ID:</strong> " . $row['id'] . "<br>";
        echo "<strong>Question:</strong> " . htmlspecialchars($row['question_text']) . "<br>";
        echo "<strong>Answer (raw):</strong> " . htmlspecialchars($row['answer']) . "<br>";
        echo "<strong>Options JSON:</strong> " . htmlspecialchars($row['options_json']) . "<br>";
        echo "<strong>Options (old):</strong> " . htmlspecialchars($row['options']) . "<br>";
        
        // Try to decode the answer
        $answerDecoded = json_decode($row['answer'], true);
        echo "<strong>Answer (decoded):</strong> ";
        if ($answerDecoded) {
            echo "<pre>" . print_r($answerDecoded, true) . "</pre>";
        } else {
            echo "Failed to decode JSON: " . json_last_error_msg();
        }
        
        // Try to decode options
        $optionsDecoded = json_decode($row['options_json'] ?: $row['options'], true);
        echo "<strong>Options (decoded):</strong> ";
        if ($optionsDecoded) {
            echo "<pre>" . print_r($optionsDecoded, true) . "</pre>";
        } else {
            echo "Failed to decode JSON: " . json_last_error_msg();
        }
        
        echo "</div>";
    }
} else {
    echo "<p>No matching questions found.</p>";
}

// Check if there are any questions with empty answers
$query2 = "SELECT id, question_text, answer FROM question_bank WHERE question_type = 'matching' AND (answer = '' OR answer = '{}' OR answer IS NULL)";
$result2 = $conn->query($query2);

if ($result2 && $result2->num_rows > 0) {
    echo "<h3>Matching Questions with Empty/Invalid Answers:</h3>";
    while ($row = $result2->fetch_assoc()) {
        echo "<div style='border: 1px solid red; margin: 10px; padding: 10px; background: #ffe6e6;'>";
        echo "<strong>ID:</strong> " . $row['id'] . "<br>";
        echo "<strong>Question:</strong> " . htmlspecialchars($row['question_text']) . "<br>";
        echo "<strong>Answer:</strong> '" . htmlspecialchars($row['answer']) . "'<br>";
        echo "</div>";
    }
} else {
    echo "<p>No matching questions with empty answers found.</p>";
}
?>

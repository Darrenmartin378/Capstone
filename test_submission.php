<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Testing Answer Submission</h2>";

// Check if we can find questions for Quiz2
$setTitle = 'Quiz2';
echo "<h3>Looking for questions in set: $setTitle</h3>";

$stmt = $conn->prepare("
    SELECT qb.* FROM question_bank qb 
    WHERE qb.section_id = ? AND qb.set_title = ?
    AND qb.question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder') 
    AND qb.question_text != '' 
    AND qb.question_text IS NOT NULL
");
$stmt->bind_param('is', $studentSectionId, $setTitle);
$stmt->execute();
$questions = $stmt->get_result();

if ($questions && $questions->num_rows > 0) {
    echo "<p style='color: green;'>✅ Found " . $questions->num_rows . " questions for Quiz2</p>";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Type</th><th>Text</th><th>Answer</th><th>Options</th></tr>";
    
    while ($question = $questions->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($question['id']) . "</td>";
        echo "<td>" . htmlspecialchars($question['question_type']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($question['question_text'], 0, 30)) . "...</td>";
        echo "<td>" . htmlspecialchars(substr($question['answer'] ?? 'NULL', 0, 20)) . "...</td>";
        echo "<td>" . htmlspecialchars(substr($question['options'] ?? 'NULL', 0, 30)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No questions found for Quiz2</p>";
}

// Check student section
echo "<h3>Student Information</h3>";
echo "<p><strong>Student ID:</strong> $studentId</p>";
echo "<p><strong>Student Section ID:</strong> $studentSectionId</p>";

// Check sections table
echo "<h3>Available Sections</h3>";
$sections = $conn->query("SELECT * FROM sections");
if ($sections && $sections->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th></tr>";
    while ($section = $sections->fetch_assoc()) {
        $highlight = ($section['id'] == $studentSectionId) ? 'style="background: yellow;"' : '';
        echo "<tr $highlight>";
        echo "<td>" . htmlspecialchars($section['id']) . "</td>";
        echo "<td>" . htmlspecialchars($section['name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No sections found</p>";
}

// Test a sample submission
echo "<h3>Testing Sample Submission</h3>";
$sampleAnswers = [
    '34' => '{"qwe": "dsa", "ewq": "gfdg", "ewt": "kjjk"}', // Matching question
    '35' => 'C', // Multiple choice
    '36' => 'This is my essay answer' // Essay
];

echo "<p><strong>Sample answers:</strong></p>";
echo "<pre>" . htmlspecialchars(print_r($sampleAnswers, true)) . "</pre>";

// Test JSON encoding
$jsonAnswers = json_encode($sampleAnswers);
echo "<p><strong>JSON encoded:</strong> " . htmlspecialchars($jsonAnswers) . "</p>";

// Test JSON decoding
$decodedAnswers = json_decode($jsonAnswers, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<p style='color: green;'>✅ JSON encoding/decoding works</p>";
} else {
    echo "<p style='color: red;'>❌ JSON error: " . json_last_error_msg() . "</p>";
}

echo "<p style='color: green; font-weight: bold;'>Test completed! Check the results above.</p>";
?>

<?php
require_once 'Teacher/includes/teacher_init.php';

echo "<h2>Debug Question Sets</h2>";

// Check all question sets for this teacher
$query = "SELECT set_title, section_id, COUNT(*) as question_count, MIN(created_at) as created_at 
          FROM question_bank 
          WHERE teacher_id = $teacherId 
          AND set_title IS NOT NULL 
          AND set_title != ''
          GROUP BY set_title, section_id
          ORDER BY created_at DESC";

$result = $conn->query($query);

echo "<h3>All Question Sets for Teacher ID: $teacherId</h3>";
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Set Title</th><th>Section ID</th><th>Question Count</th><th>Created At</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['set_title']) . "</td>";
        echo "<td>" . $row['section_id'] . "</td>";
        echo "<td>" . $row['question_count'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No question sets found.</p>";
}

// Check with the same query as in teacher_grading.php
echo "<h3>Question Sets with Filter (same as teacher_grading.php)</h3>";
$filtered_query = "
    SELECT DISTINCT set_title, section_id, MIN(created_at) as created_at
    FROM question_bank 
    WHERE teacher_id = $teacherId 
    AND set_title IS NOT NULL 
    AND set_title != ''
    AND question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder') 
    AND question_text != '' 
    AND question_text IS NOT NULL
    GROUP BY set_title, section_id
    ORDER BY created_at DESC
";

$filtered_result = $conn->query($filtered_query);

if ($filtered_result && $filtered_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Set Title</th><th>Section ID</th><th>Created At</th></tr>";
    while ($row = $filtered_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['set_title']) . "</td>";
        echo "<td>" . $row['section_id'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No filtered question sets found.</p>";
}

// Check individual questions
echo "<h3>Individual Questions</h3>";
$questions_query = "SELECT id, set_title, question_text, question_type, created_at 
                    FROM question_bank 
                    WHERE teacher_id = $teacherId 
                    ORDER BY set_title, id";

$questions_result = $conn->query($questions_query);

if ($questions_result && $questions_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Set Title</th><th>Question Text</th><th>Type</th><th>Created</th></tr>";
    while ($row = $questions_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['set_title']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['question_text'], 0, 50)) . "...</td>";
        echo "<td>" . $row['question_type'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No questions found.</p>";
}

echo "<p><a href='Teacher/teacher_grading.php'>Go to Teacher Grading</a></p>";
?>

<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Debug: Question Bank Data</h2>";

// Check what's in the question_bank table
$result = $conn->query("
    SELECT 
        id, 
        teacher_id, 
        section_id, 
        set_title, 
        question_type, 
        question_text, 
        options_json, 
        options, 
        answer, 
        created_at
    FROM question_bank 
    ORDER BY created_at DESC
");

if ($result && $result->num_rows > 0) {
    echo "<h3>All Questions in Database:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Teacher</th><th>Section</th><th>Set Title</th><th>Type</th><th>Text</th><th>Options JSON</th><th>Options</th><th>Answer</th><th>Created</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . h($row['id']) . "</td>";
        echo "<td>" . h($row['teacher_id']) . "</td>";
        echo "<td>" . h($row['section_id']) . "</td>";
        echo "<td>" . h($row['set_title']) . "</td>";
        echo "<td>" . h($row['question_type']) . "</td>";
        echo "<td>" . h(substr($row['question_text'], 0, 50)) . "...</td>";
        echo "<td>" . h(substr($row['options_json'] ?? 'NULL', 0, 50)) . "...</td>";
        echo "<td>" . h(substr($row['options'] ?? 'NULL', 0, 50)) . "...</td>";
        echo "<td>" . h(substr($row['answer'] ?? 'NULL', 0, 30)) . "...</td>";
        echo "<td>" . h($row['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No questions found in database.</p>";
}

// Check question sets
echo "<h3>Question Sets:</h3>";
$sets = $conn->query("
    SELECT 
        set_title,
        COUNT(id) as question_count,
        MIN(created_at) as set_created_at,
        teacher_id,
        section_id
    FROM question_bank 
    WHERE set_title IS NOT NULL 
    AND set_title != ''
    GROUP BY set_title, teacher_id, section_id
    ORDER BY set_created_at DESC
");

if ($sets && $sets->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Set Title</th><th>Question Count</th><th>Teacher ID</th><th>Section ID</th><th>Created</th></tr>";
    
    while ($set = $sets->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . h($set['set_title']) . "</td>";
        echo "<td>" . h($set['question_count']) . "</td>";
        echo "<td>" . h($set['teacher_id']) . "</td>";
        echo "<td>" . h($set['section_id']) . "</td>";
        echo "<td>" . h($set['set_created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No question sets found.</p>";
}

// Check sections
echo "<h3>Sections:</h3>";
$sections = $conn->query("SELECT * FROM sections");
if ($sections && $sections->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th></tr>";
    
    while ($section = $sections->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . h($section['id']) . "</td>";
        echo "<td>" . h($section['name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No sections found.</p>";
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>

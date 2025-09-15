<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Fixing Database Schema</h2>";

// Add options_json column if it doesn't exist
$colCheck = $conn->query("SHOW COLUMNS FROM question_bank LIKE 'options_json'");
if ($colCheck && $colCheck->num_rows === 0) {
    echo "<p>Adding options_json column...</p>";
    $result = $conn->query("ALTER TABLE question_bank ADD COLUMN options_json JSON NULL AFTER question_text");
    if ($result) {
        echo "<p style='color: green;'>✅ options_json column added successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add options_json column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ options_json column already exists</p>";
}

// Migrate existing options data to options_json if needed
echo "<p>Migrating existing options data...</p>";
$result = $conn->query("SELECT id, options FROM question_bank WHERE options IS NOT NULL AND options != '' AND (options_json IS NULL OR options_json = '')");
if ($result && $result->num_rows > 0) {
    $migrated = 0;
    while ($row = $result->fetch_assoc()) {
        $updateStmt = $conn->prepare("UPDATE question_bank SET options_json = ? WHERE id = ?");
        $updateStmt->bind_param('si', $row['options'], $row['id']);
        if ($updateStmt->execute()) {
            $migrated++;
        }
    }
    echo "<p style='color: green;'>✅ Migrated {$migrated} questions to options_json</p>";
} else {
    echo "<p style='color: blue;'>ℹ️ No data migration needed</p>";
}

// Check current table structure
echo "<h3>Current question_bank table structure:</h3>";
$result = $conn->query("DESCRIBE question_bank");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check sample data
echo "<h3>Sample questions:</h3>";
$result = $conn->query("SELECT id, question_type, set_title, options_json, options FROM question_bank LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Type</th><th>Set Title</th><th>Options JSON</th><th>Options</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['question_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['set_title']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['options_json'] ?? 'NULL', 0, 50)) . "...</td>";
        echo "<td>" . htmlspecialchars(substr($row['options'] ?? 'NULL', 0, 50)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No questions found in database.</p>";
}

echo "<p style='color: green; font-weight: bold;'>Database fix completed! You can now try accessing the student questions page.</p>";
?>

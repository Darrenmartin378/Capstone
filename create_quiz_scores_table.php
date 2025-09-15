<?php
require_once 'Student/includes/student_init.php';

// Create quiz_scores table if it doesn't exist
$createTable = "
CREATE TABLE IF NOT EXISTS quiz_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    set_title VARCHAR(255) NOT NULL,
    section_id INT NOT NULL,
    teacher_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    total_points INT NOT NULL,
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_quiz_per_student (student_id, set_title, section_id, teacher_id)
)";

if ($conn->query($createTable)) {
    echo "✅ Quiz scores table created successfully!<br>";
} else {
    echo "❌ Error creating table: " . $conn->error . "<br>";
}

// Create quiz_responses table to store individual question responses
$createResponsesTable = "
CREATE TABLE IF NOT EXISTS quiz_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    question_id INT NOT NULL,
    set_title VARCHAR(255) NOT NULL,
    section_id INT NOT NULL,
    teacher_id INT NOT NULL,
    student_answer TEXT,
    is_correct BOOLEAN,
    partial_score INT DEFAULT 0,
    total_matches INT DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
)";

if ($conn->query($createResponsesTable)) {
    echo "✅ Quiz responses table created successfully!<br>";
} else {
    echo "❌ Error creating responses table: " . $conn->error . "<br>";
}

echo "<h3>Database setup complete!</h3>";
echo "<p><a href='Student/student_questions.php'>Go to Student Questions</a></p>";
?>

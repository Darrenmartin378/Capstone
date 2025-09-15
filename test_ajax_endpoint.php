<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Test AJAX Endpoint</h2>";

// Test the get_set_questions endpoint
$setTitle = 'Quiz5'; // or whatever set you're trying to load

echo "<h3>Testing get_set_questions for: $setTitle</h3>";

// Simulate the AJAX request
$_GET['action'] = 'get_set_questions';
$_GET['set_title'] = $setTitle;

// Capture output
ob_start();

// Include the relevant part of student_questions.php
try {
    // Check if quiz_scores table exists, if not create it
    try {
        $conn->query("SELECT 1 FROM quiz_scores LIMIT 1");
    } catch (Exception $e) {
        // Table doesn't exist, create it
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
        $conn->query($createTable);
    }

    // Get questions for this set
    $stmt = $conn->prepare("
        SELECT qb.*, COALESCE(qb.options_json, qb.options, '{}') as options
        FROM question_bank qb 
        WHERE qb.section_id = ? AND qb.set_title = ?
        AND qb.question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder') 
        AND qb.question_text != '' 
        AND qb.question_text IS NOT NULL
        ORDER BY qb.id
    ");
    $stmt->bind_param('is', $studentSectionId, $setTitle);
    $stmt->execute();
    $questions = $stmt->get_result();
    
    echo "<h4>Query executed. Found " . ($questions ? $questions->num_rows : 0) . " questions.</h4>";
    
    $questionList = [];
    if ($questions && $questions->num_rows > 0) {
        while ($q = $questions->fetch_assoc()) {
            echo "<p>Processing question ID: " . $q['id'] . " - " . htmlspecialchars($q['question_text']) . "</p>";
            $questionList[] = $q;
        }
    }
    
    echo "<h4>Final question list count: " . count($questionList) . "</h4>";
    
    // Test JSON encoding
    $response = ['questions' => $questionList];
    $json_output = json_encode($response);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p style='color: red;'>JSON encoding error: " . json_last_error_msg() . "</p>";
    } else {
        echo "<p style='color: green;'>JSON encoding successful!</p>";
        echo "<pre>" . htmlspecialchars($json_output) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$output = ob_get_clean();
echo $output;

echo "<p><a href='Student/student_questions.php'>Go to Student Questions</a></p>";
?>

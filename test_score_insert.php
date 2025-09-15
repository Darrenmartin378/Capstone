<?php
require_once 'Student/includes/student_init.php';

echo "<h2>Test Score Insert</h2>";

// Get a sample question set
$query = "SELECT set_title, teacher_id FROM question_bank WHERE section_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $studentSectionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $sample = $result->fetch_assoc();
    $setTitle = $sample['set_title'];
    $teacherId = $sample['teacher_id'];
    
    echo "Testing with Set: " . htmlspecialchars($setTitle) . "<br>";
    echo "Teacher ID: " . $teacherId . "<br>";
    
    // Try to insert a test score
    try {
        $insertStmt = $conn->prepare("
            INSERT INTO quiz_scores (student_id, set_title, section_id, teacher_id, score, total_points, total_questions, correct_answers) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            score = VALUES(score), 
            total_points = VALUES(total_points), 
            total_questions = VALUES(total_questions), 
            correct_answers = VALUES(correct_answers),
            submitted_at = CURRENT_TIMESTAMP
        ");
        
        $testScore = 85.5;
        $totalPoints = 10;
        $totalQuestions = 3;
        $correctAnswers = 8;
        
        $insertStmt->bind_param('isiiidii', $studentId, $setTitle, $studentSectionId, $teacherId, $testScore, $totalPoints, $totalQuestions, $correctAnswers);
        
        if ($insertStmt->execute()) {
            echo "✅ Test score inserted successfully!<br>";
            
            // Now check if it shows up in the query
            $checkStmt = $conn->prepare("
                SELECT qb.set_title, qs.score, qs.submitted_at as completed_at
                FROM question_bank qb 
                LEFT JOIN quiz_scores qs ON qb.set_title = qs.set_title 
                    AND qb.section_id = qs.section_id 
                    AND qb.teacher_id = qs.teacher_id 
                    AND qs.student_id = ?
                WHERE qb.section_id = ? AND qb.set_title = ?
                GROUP BY qb.set_title, qb.teacher_id, qb.section_id
            ");
            $checkStmt->bind_param('iis', $studentId, $studentSectionId, $setTitle);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $row = $checkResult->fetch_assoc();
                echo "✅ Score found in query: " . $row['score'] . "%<br>";
                echo "Completed at: " . $row['completed_at'] . "<br>";
            } else {
                echo "❌ Score not found in query<br>";
            }
            
        } else {
            echo "❌ Failed to insert test score: " . $insertStmt->error . "<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
    
} else {
    echo "No question sets found to test with.<br>";
}

echo "<p><a href='Student/student_questions.php'>Go to Student Questions</a></p>";
echo "<p><a href='debug_quiz_scores.php'>Debug Quiz Scores</a></p>";
?>

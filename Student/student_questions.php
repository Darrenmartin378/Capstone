<?php
// Handle AJAX requests FIRST, before any output
if (isset($_GET['action']) && $_GET['action'] === 'get_set_questions') {
    // Suppress warnings/notices for AJAX requests
    error_reporting(E_ERROR | E_PARSE);
    
    require_once 'includes/student_init.php';
    
    // Clear ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    $setTitle = trim($_GET['set_title'] ?? '');
    
    // Debug logging
    error_log("AJAX get_set_questions called with set_title: " . $setTitle);
    
    if (empty($setTitle)) {
        error_log("AJAX get_set_questions error: Empty set title");
        echo json_encode(['error' => 'Set title required']);
        exit;
    }
    
    // Simple test response first
    if ($setTitle === 'test') {
        echo json_encode(['test' => 'success', 'message' => 'Test response working']);
        exit;
    }
    
    $stmt = $conn->prepare("
        SELECT qb.*, t.name as teacher_name, s.name as section_name,
               COALESCE(qb.options_json, qb.options, '{}') as options
        FROM question_bank qb 
        JOIN teachers t ON qb.teacher_id = t.id 
        JOIN sections s ON qb.section_id = s.id
        WHERE qb.section_id = ? 
        AND qb.set_title = ?
        AND qb.question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder') 
        AND qb.question_text != '' 
        AND qb.question_text IS NOT NULL
        ORDER BY qb.id
    ");
    
    error_log("AJAX query - Section ID: $studentSectionId, Set Title: $setTitle");
    
    $stmt->bind_param('is', $studentSectionId, $setTitle);
    $stmt->execute();
    $questions = $stmt->get_result();
    
    error_log("AJAX query executed, found " . ($questions ? $questions->num_rows : 0) . " questions");
    
    $questionList = [];
    if ($questions && $questions->num_rows > 0) {
        while ($q = $questions->fetch_assoc()) {
            try {
                // Debug matching questions
                if ($q['question_type'] === 'matching') {
                    error_log("Matching question data for ID {$q['id']}: " . json_encode([
                        'question_text' => $q['question_text'],
                        'options' => $q['options'],
                        'answer' => $q['answer']
                    ]));
                }
                
                // Ensure all required fields exist
                if (!isset($q['id']) || !isset($q['question_text']) || !isset($q['question_type'])) {
                    error_log("Missing required fields for question: " . json_encode($q));
                    continue;
                }
                
                $questionList[] = $q;
            } catch (Exception $e) {
                error_log("Error processing question: " . $e->getMessage());
                continue;
            }
        }
    }
    
    // Ensure we have valid JSON output
    try {
        if (empty($questionList)) {
            error_log("No questions found for set: " . $setTitle);
            $response = ['error' => 'No questions found for this set'];
        } else {
            $response = ['questions' => $questionList];
        }
        
        $json_output = json_encode($response);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON encoding error: " . json_last_error_msg());
            $json_output = json_encode(['error' => 'Failed to encode questions data']);
        }
        
        echo $json_output;
        
    } catch (Exception $e) {
        error_log("Exception in AJAX response: " . $e->getMessage());
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Start output buffering to prevent accidental output
ob_start();

// Suppress warnings/notices for AJAX requests
if (isset($_POST['action']) || isset($_GET['action'])) {
    error_reporting(E_ERROR | E_PARSE);
}

require_once 'includes/student_init.php';

$pageTitle = 'Questions';

// Create question_responses table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS question_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    student_id INT NOT NULL,
    answer TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_question_student (question_id, student_id),
    INDEX idx_question_id (question_id),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle student answers to teacher-posted questions (AJAX upsert)
if (isset($_POST['action']) && $_POST['action'] === 'answer_question') {
    ob_clean();
    header('Content-Type: application/json');
    $questionId = (int)($_POST['question_id'] ?? 0);
    $answer = trim($_POST['answer'] ?? '');
    
    if ($questionId <= 0 || $studentId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
        ob_end_clean();
        exit();
    }
    
    try {
        // Check if response already exists
        $check = $conn->prepare('SELECT id FROM question_responses WHERE question_id = ? AND student_id = ?');
        $check->bind_param('ii', $questionId, $studentId);
        $check->execute();
        $rs = $check->get_result();
        
        if ($rs && $row = $rs->fetch_assoc()) {
            // Update existing response
            $rid = (int)$row['id'];
            $upd = $conn->prepare('UPDATE question_responses SET answer = ?, updated_at = NOW() WHERE id = ?');
            $upd->bind_param('si', $answer, $rid);
            $ok = $upd->execute();
            echo json_encode(['ok' => $ok, 'mode' => 'update']);
        } else {
            // Insert new response
            $ins = $conn->prepare('INSERT INTO question_responses (question_id, student_id, answer) VALUES (?, ?, ?)');
            $ins->bind_param('iis', $questionId, $studentId, $answer);
            $ok = $ins->execute();
            echo json_encode(['ok' => $ok, 'mode' => 'insert']);
        }
    } catch (Exception $e) {
        error_log("Error saving question response: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Database error']);
    }
    ob_end_clean();
    exit();
}

// Clean up questions with invalid/placeholder content (only run occasionally, not on every page load)
// This should be moved to a separate cleanup script or run less frequently
if (rand(1, 100) <= 5) { // Only run 5% of the time to avoid performance issues
    $conn->query("DELETE FROM question_bank WHERE 
        question_text IN ('dsad', 'dsadasdasdasd', 'placeholder', 'dasdasdsad', '3213213') 
        OR question_text = '' 
        OR question_text IS NULL
        OR question_text REGEXP '^[0-9]+$'
        OR LENGTH(TRIM(question_text)) < 3
        OR question_text = 'What is the capital of the Philippines?'
        OR question_text = 'Explain the importance of education in society.'
        OR question_text = 'Match the following countries with their capitals:'
    ");
}

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

// Fetch question sets (grouped by set_title) for students to answer (only from their section)
$stmt = $conn->prepare("
    SELECT 
        qb.set_title,
        COUNT(qb.id) as question_count,
        MIN(qb.created_at) as set_created_at,
        t.name as teacher_name,
        s.name as section_name,
        GROUP_CONCAT(qb.id ORDER BY qb.id) as question_ids,
        qs.score,
        qs.submitted_at as completed_at
    FROM question_bank qb 
    JOIN teachers t ON qb.teacher_id = t.id 
    JOIN sections s ON qb.section_id = s.id
    LEFT JOIN quiz_scores qs ON qb.set_title = qs.set_title 
        AND qb.section_id = qs.section_id 
        AND qb.teacher_id = qs.teacher_id 
        AND qs.student_id = ?
    WHERE qb.section_id = ?
    AND qb.set_title IS NOT NULL 
    AND qb.set_title != ''
    AND qb.question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder') 
    AND qb.question_text != '' 
    AND qb.question_text IS NOT NULL
    GROUP BY qb.set_title, qb.teacher_id, qb.section_id
    ORDER BY set_created_at DESC
");
$stmt->bind_param('ii', $studentId, $studentSectionId);
$stmt->execute();
$question_sets = $stmt->get_result();

// Debug: Log the query results
error_log("Debug: Fetched " . ($question_sets ? $question_sets->num_rows : 0) . " question sets for student $studentId");

// Simple test endpoint
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'message' => 'AJAX is working']);
    ob_end_clean();
    exit;
}


// Handle AJAX request to submit answers and get score
if (isset($_POST['action']) && $_POST['action'] === 'submit_answers') {
    // Suppress warnings/notices for AJAX requests
    error_reporting(E_ERROR | E_PARSE);
    
    // Clear ALL output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    $setTitle = trim($_POST['set_title'] ?? '');
    $answers = $_POST['answers'] ?? [];
    
    // Debug logging
    error_log("Submit answers request - Set title: " . $setTitle);
    error_log("Submit answers request - Answers: " . print_r($answers, true));
    
    if (empty($setTitle) || empty($answers)) {
        error_log("Submit answers error - Missing set title or answers");
        echo json_encode(['error' => 'Set title and answers required']);
        exit;
    }
    
    // Parse answers if it's a JSON string
    if (is_string($answers)) {
        $answers = json_decode($answers, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Submit answers error - Invalid JSON: " . json_last_error_msg());
            echo json_encode(['error' => 'Invalid answers format']);
            exit;
        }
    }
    
    // Debug: Log the received answers
    error_log("Received answers: " . json_encode($answers));
    
    try {
        // Get questions for this set
        $stmt = $conn->prepare("
            SELECT qb.*, qb.teacher_id FROM question_bank qb 
            WHERE qb.section_id = ? AND qb.set_title = ?
            AND qb.question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder') 
            AND qb.question_text != '' 
            AND qb.question_text IS NOT NULL
        ");
        $stmt->bind_param('is', $studentSectionId, $setTitle);
        $stmt->execute();
        $questions = $stmt->get_result();
        
        $totalQuestions = 0;
        $correctAnswers = 0;
        $totalPossiblePoints = 0; // Track total possible points
        $results = [];
        $teacherId = null; // Will be set from first question
        
        if (!$questions || $questions->num_rows === 0) {
            error_log("Submit answers error - No questions found for set: " . $setTitle);
            echo json_encode(['error' => 'No questions found for this set']);
            exit;
        }
        
        while ($question = $questions->fetch_assoc()) {
            // Set teacher_id from first question
            if ($teacherId === null) {
                $teacherId = $question['teacher_id'];
            }
            $totalQuestions++;
            $questionId = $question['id'];
            $studentAnswer = $answers[$questionId] ?? '';
            $correctAnswer = $question['answer'] ?? '';
            $questionType = $question['question_type'];
            
            // Debug: Log essay answer processing
            if ($questionType === 'essay') {
                error_log("Essay question $questionId - Student answer: " . json_encode($studentAnswer));
            }
            
            $isCorrect = false;
            $partialScore = 0;
            $totalMatches = 0;
            
            if ($questionType === 'multiple_choice') {
                $isCorrect = (strtolower(trim($studentAnswer)) === strtolower(trim($correctAnswer)));
                $totalPossiblePoints += 1; // Each MC question is worth 1 point
                if ($isCorrect) {
                    $correctAnswers++;
                }
            } elseif ($questionType === 'matching') {
                // For matching, each correct match gets 1 point
                $studentMatches = json_decode($studentAnswer, true);
                $correctMatches = json_decode($correctAnswer, true);
                
                if (is_array($studentMatches) && is_array($correctMatches)) {
                    $correctCount = 0;
                    $totalMatches = count($correctMatches);
                    $totalPossiblePoints += $totalMatches; // Each match is worth 1 point
                    
                    foreach ($correctMatches as $leftItem => $correctRight) {
                        if (isset($studentMatches[$leftItem]) && 
                            trim($studentMatches[$leftItem]) === trim($correctRight)) {
                            $correctCount++;
                        }
                    }
                    
                    // Each correct match counts as 1 point
                    $correctAnswers += $correctCount;
                    $isCorrect = ($correctCount === $totalMatches); // true only if all matches are correct
                    $partialScore = $correctCount; // Store partial score for display
                } else {
                    $isCorrect = false;
                    $partialScore = 0;
                }
            } elseif ($questionType === 'essay') {
                // Essay questions are not auto-scored
                $isCorrect = null; // null means needs teacher review
                $partialScore = 0; // No partial score for essays
                $totalMatches = 0; // No matches for essays
                // Don't add to total possible points for essays (teacher graded)
            }
            
            $resultData = [
                'question_id' => $questionId,
                'question_text' => $question['question_text'],
                'question_type' => $questionType,
                'student_answer' => $studentAnswer,
                'correct_answer' => $correctAnswer,
                'is_correct' => $isCorrect
            ];
            
            // Add partial score for matching questions
            if ($questionType === 'matching' && isset($partialScore)) {
                $resultData['partial_score'] = $partialScore;
                $resultData['total_matches'] = $totalMatches;
            }
            
            // Debug logging for matching questions
            if ($questionType === 'matching') {
                error_log("Matching question debug - ID: $questionId");
                error_log("Student answer: " . $studentAnswer);
                error_log("Correct answer: " . $correctAnswer);
                error_log("Partial score: " . ($partialScore ?? 'not set'));
            }
            
            $results[] = $resultData;
            
            // Save student response to quiz_responses table
            try {
                // Use direct SQL for now to avoid parameter binding issues
                $partialScore = $partialScore ?? 0;
                $totalMatches = $totalMatches ?? 0;
                $isCorrectValue = $isCorrect === null ? 0 : ($isCorrect ? 1 : 0);
                
                // Escape the student answer for SQL
                $escapedAnswer = $conn->real_escape_string($studentAnswer);
                $escapedSetTitle = $conn->real_escape_string($setTitle);
                
                $sql = "INSERT INTO quiz_responses (student_id, question_id, set_title, section_id, teacher_id, student_answer, is_correct, partial_score, total_matches, submitted_at) 
                        VALUES ($studentId, $questionId, '$escapedSetTitle', $studentSectionId, $teacherId, '$escapedAnswer', $isCorrectValue, $partialScore, $totalMatches, NOW())
                        ON DUPLICATE KEY UPDATE 
                        student_answer = VALUES(student_answer), 
                        is_correct = VALUES(is_correct),
                        partial_score = VALUES(partial_score),
                        total_matches = VALUES(total_matches),
                        submitted_at = NOW()";
                
                error_log("SQL for question $questionId: " . $sql);
                
                if (!$conn->query($sql)) {
                    throw new Exception("Failed to execute response insert: " . $conn->error);
                }
                
                error_log("Successfully saved response for question $questionId");
            } catch (Exception $e) {
                error_log("Error saving response for question $questionId: " . $e->getMessage());
                // Continue processing other questions even if one fails
            }
        }
        
        $score = $totalPossiblePoints > 0 ? round(($correctAnswers / $totalPossiblePoints) * 100, 2) : 0;
        
        // Check if quiz_responses table exists, if not create it
        try {
            $conn->query("SELECT 1 FROM quiz_responses LIMIT 1");
        } catch (Exception $e) {
            // Table doesn't exist, create it
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
            $conn->query($createResponsesTable);
        }

        // Save quiz score to database
        try {
            error_log("Attempting to save quiz score - Student: $studentId, Set: $setTitle, Teacher: $teacherId, Score: $score");
            
            $scoreStmt = $conn->prepare("
                INSERT INTO quiz_scores (student_id, set_title, section_id, teacher_id, score, total_points, total_questions, correct_answers) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                score = VALUES(score), 
                total_points = VALUES(total_points), 
                total_questions = VALUES(total_questions), 
                correct_answers = VALUES(correct_answers),
                submitted_at = CURRENT_TIMESTAMP
            ");
            $scoreStmt->bind_param('isiiidii', $studentId, $setTitle, $studentSectionId, $teacherId, $score, $totalPossiblePoints, $totalQuestions, $correctAnswers);
            
            if ($scoreStmt->execute()) {
                error_log("✅ Quiz score saved successfully - Student: $studentId, Set: $setTitle, Score: $score%");
            } else {
                error_log("❌ Failed to save quiz score: " . $scoreStmt->error);
            }
        } catch (Exception $e) {
            error_log("❌ Error saving quiz score: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
        
        error_log("Submit answers success - Score: $score, Correct: $correctAnswers, Total Possible: $totalPossiblePoints, Questions: $totalQuestions");
        
        $response = [
            'success' => true,
            'score' => $score,
            'correct' => $correctAnswers,
            'total' => $totalPossiblePoints,
            'total_questions' => $totalQuestions,
            'results' => $results
        ];
        
        $json_output = json_encode($response);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON encoding error in submit_answers: " . json_last_error_msg());
            $json_output = json_encode(['error' => 'Failed to encode response data']);
        }
        
        echo $json_output;
        
    } catch (Exception $e) {
        error_log("Error submitting answers: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        $error_response = json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON encoding error in catch block: " . json_last_error_msg());
            $error_response = json_encode(['error' => 'Server error occurred']);
        }
        
        echo $error_response;
    }
    
    // Clean output buffer and exit
    ob_end_clean();
    exit;
}
?>
<style>
    .card {
        background: var(--card);
        border: 2px solid #d9f2ff;
        border-radius: 18px;
        box-shadow: 0 10px 20px rgba(43,144,217,.15);
        margin: 18px 0;
        overflow: hidden;
    }
    .card-header {
        padding: 14px 16px;
        background: linear-gradient(90deg,#e8f7ff,#f0fff6);
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 700;
        color: #17415e;
    }
    .card-body {
        padding: 16px;
    }
    .muted {
        color: var(--muted);
        font-size: 13px;
    }
    input[type=text], textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #d7ecff;
        border-radius: 14px;
        background: #fff;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
    }
    textarea {
        min-height: 120px;
    }
    label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }
    .matching-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .matching-widget select {
        width: 100%;
        padding: 8px;
        border: 2px solid #d7ecff;
        border-radius: 8px;
        background: #fff;
    }
</style>

<div class="card">
    <div class="card-header">
        <strong>Comprehension Questions</strong> 
        <span aria-hidden="true">📚</span>
    </div>
    <div class="card-body">
        <?php if ($question_sets && $question_sets->num_rows > 0): ?>
            <div id="question-sets-list">
                <?php while ($set = $question_sets->fetch_assoc()): ?>
                    <?php $isCompleted = !is_null($set['score']); ?>
                    <div class="card" style="margin:10px 0; border-color:<?php echo $isCompleted ? '#d4edda' : '#d7ecff'; ?>; <?php echo $isCompleted ? '' : 'cursor: pointer;'; ?>" 
                         <?php if (!$isCompleted): ?>onclick="loadQuestionSet('<?php echo htmlspecialchars($set['set_title'], ENT_QUOTES); ?>')"<?php endif; ?>>
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo h($set['set_title']); ?></strong>
                                <span style="font-size: 0.9em; color: #666; margin-left: 10px;">
                                    (<?php echo (int)$set['question_count']; ?> questions)
                                </span>
                                <div style="font-size: 0.8em; color: #666; margin-top: 4px;">
                                    <em>Posted by: <?php echo h($set['teacher_name']); ?> | Section: <?php echo h($set['section_name']); ?></em>
                                    <br>
                                    <em>
                                        <?php if ($isCompleted): ?>
                                            Completed: <?php echo date('M j, Y g:i A', strtotime($set['completed_at'])); ?>
                                        <?php else: ?>
                                            Created: <?php echo date('M j, Y g:i A', strtotime($set['set_created_at'])); ?>
                                        <?php endif; ?>
                                    </em>
                                </div>
                            </div>
                            <div style="text-align: center;">
                                <?php if ($isCompleted): ?>
                                    <?php
                                    // Get detailed score breakdown
                                    $scoreDetails = $conn->prepare("
                                        SELECT 
                                            qs.correct_answers as automated_score,
                                            qs.total_points as total_possible,
                                            COALESCE(SUM(CASE WHEN qb.question_type = 'essay' THEN qr.partial_score ELSE 0 END), 0) as essay_score,
                                            COUNT(CASE WHEN qb.question_type = 'essay' THEN 1 END) as essay_count
                                        FROM quiz_scores qs
                                        LEFT JOIN quiz_responses qr ON qs.student_id = qr.student_id AND qs.set_title = qr.set_title AND qs.section_id = qr.section_id
                                        LEFT JOIN question_bank qb ON qr.question_id = qb.id
                                        WHERE qs.student_id = ? AND qs.set_title = ? AND qs.section_id = ?
                                        GROUP BY qs.id
                                    ");
                                    $scoreDetails->bind_param('isi', $studentId, $set['set_title'], $studentSectionId);
                                    $scoreDetails->execute();
                                    $details = $scoreDetails->get_result()->fetch_assoc();
                                    
                                    $automatedScore = $details['automated_score'] ?? 0;
                                    $essayScore = $details['essay_score'] ?? 0;
                                    $totalPossible = $details['total_possible'] ?? 0;
                                    $essayCount = $details['essay_count'] ?? 0;
                                    $maxEssayScore = $essayCount * 10;
                                    $totalEarned = $automatedScore + $essayScore;
                                    $totalMaxPossible = $totalPossible + $maxEssayScore;
                                    ?>
                                    <div style="background: #d4edda; padding: 12px; border-radius: 8px; min-width: 100px;">
                                        <div style="font-size: 1.4em; font-weight: bold; color: #155724;">
                                            <?php echo number_format($set['score'], 1); ?>%
                                        </div>
                                        <div style="font-size: 0.75em; color: #155724; margin: 2px 0;">
                                            <?php echo $totalEarned; ?>/<?php echo $totalMaxPossible; ?> pts
                                        </div>
                                        <div style="font-size: 0.7em; color: #6b7280; margin: 2px 0;">
                                            <?php if ($essayCount > 0): ?>
                                                Auto: <?php echo $automatedScore; ?>/<?php echo $totalPossible; ?> | 
                                                Essay: <?php echo $essayScore; ?>/<?php echo $maxEssayScore; ?>
                                            <?php else: ?>
                                                Auto-scored
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.8em; color: #155724; font-weight: 500;">
                                            Completed
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <i class="fas fa-chevron-right" style="color: #666; font-size: 1.2em;"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Question Set Content (hidden initially) -->
            <div id="question-set-content" style="display: none;">
                <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px; padding: 16px; background: #f8fafc; border-radius: 8px;">
                    <button onclick="showQuestionSets()" class="btn btn-secondary" style="padding: 8px 16px; border: none; border-radius: 6px; background: #e0e7ff; color: #3730a3; cursor: pointer;">
                        ← Back to Sets
                    </button>
                    <h3 id="current-set-title" style="margin: 0; color: #1e40af;"></h3>
                </div>
                
                <div id="questions-container">
                    <!-- Questions will be loaded here -->
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button id="submit-answers-btn" onclick="submitAnswers()" class="btn btn-primary" 
                            style="padding: 12px 24px; border: none; border-radius: 8px; background: #6366f1; color: white; font-size: 1.1em; cursor: pointer; display: none;">
                        Submit Answers
                    </button>
                </div>
            </div>
            
            <!-- Results Modal -->
            <div id="results-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; padding: 24px; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto;">
                    <h2 style="margin: 0 0 16px 0; color: #1e40af;">Quiz Results</h2>
                    <div id="results-content">
                        <!-- Results will be displayed here -->
                    </div>
                    <div style="text-align: right; margin-top: 20px;">
                        <button onclick="closeResults()" class="btn btn-secondary" style="padding: 8px 16px; border: none; border-radius: 6px; background: #e0e7ff; color: #3730a3; cursor: pointer;">
                            Close
                        </button>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 40px; background: linear-gradient(135deg, #f8fafc, #e2e8f0);">
                <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                <h3 style="color: #1e40af; margin-bottom: 12px;">No Question Sets Available</h3>
                <p class="muted" style="font-size: 1.1em; margin-bottom: 8px;">No comprehension question sets have been posted for your section yet.</p>
                <p class="muted" style="font-size: 0.9em;">
                    Your teachers can create question sets for your section through the <strong>Teacher Portal → Question Management</strong>.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let currentSetTitle = '';
let currentQuestions = [];

function loadQuestionSet(setTitle) {
    currentSetTitle = setTitle;
    
    // Show loading
    document.getElementById('question-sets-list').style.display = 'none';
    document.getElementById('question-set-content').style.display = 'block';
    document.getElementById('current-set-title').textContent = setTitle;
    document.getElementById('questions-container').innerHTML = '<p>Loading questions...</p>';
    document.getElementById('submit-answers-btn').style.display = 'none';
    
    // Fetch questions for this set
    fetch(`?action=get_set_questions&set_title=${encodeURIComponent(setTitle)}`)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text(); // Get as text first
        })
        .then(text => {
            console.log('Raw response:', text);
            
            try {
                const data = JSON.parse(text);
                if (data.error) {
                    document.getElementById('questions-container').innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                    return;
                }
                
                currentQuestions = data.questions || [];
                renderQuestions(currentQuestions);
                document.getElementById('submit-answers-btn').style.display = 'block';
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                document.getElementById('questions-container').innerHTML = '<p style="color: red;">Error parsing response.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading questions:', error);
            document.getElementById('questions-container').innerHTML = '<p style="color: red;">Error loading questions.</p>';
        });
}

function renderQuestions(questions) {
    const container = document.getElementById('questions-container');
    
    if (questions.length === 0) {
        container.innerHTML = '<p>No questions found in this set.</p>';
        return;
    }
    
    let html = '';
    questions.forEach((question, index) => {
        html += `
            <div class="card" style="margin:10px 0; border-color:#d7ecff;">
                <div class="card-header">
                    <div>
                        ${question.question_type === 'matching' ? 
                            escapeHtml(question.question_text) : 
                            `<strong>Q${index + 1}.</strong> ${escapeHtml(question.question_text)}`
                        }
                    </div>
                    <div style="font-size: 0.8em; color: #666; margin-top: 4px;">
                        <em>Type: ${question.question_type.replace('_', ' ').toUpperCase()}</em>
                    </div>
                </div>
                <div class="card-body">
                    ${renderQuestionInput(question, index)}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Initialize matching widgets with their question index
    document.querySelectorAll('.matching-widget').forEach((widget, index) => {
        const questionCard = widget.closest('.card');
        const questionIndex = Array.from(questionCard.parentElement.children).indexOf(questionCard);
        buildMatchingUI(widget, questionIndex);
    });
}

function renderQuestionInput(question, index) {
    const questionId = question.id;
    const questionType = question.question_type;
    
    if (questionType === 'multiple_choice') {
        let options;
        try {
            options = JSON.parse(question.options || '{}');
        } catch (e) {
            console.error('Error parsing MC options for question', questionId, ':', e);
            return '<div class="muted">Multiple choice options are corrupted.</div>';
        }
        
        let html = '';
        Object.entries(options).forEach(([letter, label]) => {
            if (label && label.trim()) {
                html += `
                    <label>
                        <input type="radio" name="q_${questionId}" value="${letter}" data-question-id="${questionId}">
                        <span><strong>${letter}.</strong> ${escapeHtml(label)}</span>
                    </label>
                `;
            }
        });
        return html;
    } else if (questionType === 'matching') {
        let options;
        try {
            // Try to parse the options
            const optionsStr = question.options || '{}';
            console.log('Raw options for matching question', questionId, ':', optionsStr);
            
            // Clean up the options string if it's malformed
            let cleanOptionsStr = optionsStr;
            if (typeof cleanOptionsStr === 'string') {
                // Remove any extra characters or fix common JSON issues
                cleanOptionsStr = cleanOptionsStr.trim();
                if (!cleanOptionsStr.startsWith('{')) {
                    cleanOptionsStr = '{}';
                }
            }
            
            options = JSON.parse(cleanOptionsStr);
        } catch (e) {
            console.error('Error parsing matching options for question', questionId, ':', e);
            console.error('Raw options data:', question.options);
            
            // Try to create a fallback matching question
            return `
                <div class="matching-widget" 
                     data-qid="${questionId}"
                     data-lefts='["Item 1", "Item 2"]'
                     data-rights='["Option A", "Option B"]'>
                    <div class="muted" style="margin-bottom:6px;">Match the right item to each left item.</div>
                    <div class="matching-grid">
                        <!-- JS will render rows here -->
                    </div>
                    <div style="color: #f59e0b; font-size: 0.9em; margin-top: 8px;">
                        ⚠️ Original data corrupted, showing sample matching question
                    </div>
                </div>
            `;
        }
        
        console.log('Parsed options for matching question', questionId, ':', options);
        
        const lefts = options.lefts || [];
        const rights = options.rights || [];
        
        console.log('Lefts:', lefts, 'Rights:', rights);
        
        if (lefts.length === 0 || rights.length === 0) {
            return '<div class="muted">Matching question items not properly configured. Lefts: ' + lefts.length + ', Rights: ' + rights.length + '</div>';
        }
        
        return `
            <div class="matching-widget" 
                 data-qid="${questionId}"
                 data-lefts='${JSON.stringify(lefts)}'
                 data-rights='${JSON.stringify(rights)}'>
                <div class="muted" style="margin-bottom:6px;">Match the right item to each left item.</div>
                <div class="matching-grid">
                    <!-- JS will render rows here -->
                </div>
            </div>
        `;
    } else if (questionType === 'essay') {
        return `<textarea placeholder="Write your answer..." data-question-id="${questionId}" style="width: 100%; padding: 12px; border: 2px solid #d7ecff; border-radius: 14px; background: #fff; min-height: 120px;"></textarea>`;
    }
    
    return '<div class="muted">Unknown question type: ' + questionType + '</div>';
}

function submitAnswers() {
    const answers = {};
    let allAnswered = true;
    
    // Collect answers
    currentQuestions.forEach(question => {
        const questionId = question.id;
        const questionType = question.question_type;
        
        if (questionType === 'multiple_choice') {
            const selected = document.querySelector(`input[name="q_${questionId}"]:checked`);
            if (selected) {
                answers[questionId] = selected.value;
            } else {
                allAnswered = false;
            }
        } else if (questionType === 'matching') {
            const widget = document.querySelector(`[data-qid="${questionId}"]`);
            if (widget) {
                const answerMap = {};
                const selects = widget.querySelectorAll('select[data-left-item]');
                let hasAnswers = false;
                
                selects.forEach(select => {
                    if (select.value && select.value.trim() !== '') {
                        const leftItem = select.getAttribute('data-left-item');
                        if (leftItem) {
                            answerMap[leftItem] = select.value.trim();
                            hasAnswers = true;
                        }
                    }
                });
                
                if (hasAnswers) {
                    answers[questionId] = JSON.stringify(answerMap);
                } else {
                    allAnswered = false;
                }
            } else {
                allAnswered = false;
            }
        } else if (questionType === 'essay') {
            const textarea = document.querySelector(`textarea[data-question-id="${questionId}"]`);
            if (textarea && textarea.value.trim()) {
                answers[questionId] = textarea.value.trim();
            } else {
                allAnswered = false;
            }
        }
    });
    
    if (!allAnswered) {
        alert('Please answer all questions before submitting.');
        return;
    }
    
    // Submit answers
    const formData = new FormData();
    formData.append('action', 'submit_answers');
    formData.append('set_title', currentSetTitle);
    formData.append('answers', JSON.stringify(answers));
    
    fetch('student_questions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showResults(data);
            // After showing results, redirect back to quiz list after 3 seconds
            setTimeout(() => {
                showQuestionSets();
                // Reload the page to show updated scores
                location.reload();
            }, 3000);
        } else {
            console.error('Server error:', data);
            alert('Error submitting answers: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error submitting answers:', error);
        alert('Error submitting answers. Please try again.');
    });
}

function showResults(data) {
    const modal = document.getElementById('results-modal');
    const content = document.getElementById('results-content');
    
    let html = `
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="font-size: 48px; margin-bottom: 10px;">${getScoreEmoji(data.score)}</div>
            <h3 style="color: #1e40af; margin: 0;">Score: ${data.score}%</h3>
            <p style="color: #666; margin: 5px 0;">${data.correct} out of ${data.total} points earned</p>
            <p style="color: #999; margin: 2px 0; font-size: 0.9em;">(${data.total_questions} questions total)</p>
        </div>
        
        <div style="border-top: 1px solid #e2e8f0; padding-top: 20px;">
            <h4 style="margin: 0 0 15px 0; color: #374151;">Question Review:</h4>
    `;
    
    data.results.forEach((result, index) => {
        let statusIcon, statusText, statusColor;
        
        if (result.question_type === 'matching' && result.partial_score !== undefined) {
            // Partial scoring for matching questions
            const score = result.partial_score;
            const total = result.total_matches;
            if (score === total) {
                statusIcon = '✅';
                statusText = 'Correct';
                statusColor = '#10b981';
            } else if (score > 0) {
                statusIcon = '⚠️';
                statusText = `Partial (${score}/${total})`;
                statusColor = '#f59e0b';
            } else {
                statusIcon = '❌';
                statusText = 'Incorrect';
                statusColor = '#ef4444';
            }
        } else {
            // Regular scoring for other question types
            statusIcon = result.is_correct === true ? '✅' : result.is_correct === false ? '❌' : '📝';
            statusText = result.is_correct === true ? 'Correct' : result.is_correct === false ? 'Incorrect' : 'Needs Review';
            statusColor = result.is_correct === true ? '#10b981' : result.is_correct === false ? '#ef4444' : '#f59e0b';
        }
        
        html += `
            <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span style="font-size: 1.2em;">${statusIcon}</span>
                    <strong>Q${index + 1}:</strong>
                    <span style="color: ${statusColor}; font-weight: 500;">${statusText}</span>
                </div>
                <div style="margin-bottom: 8px;">
                    <strong>Question:</strong> ${escapeHtml(result.question_text)}
                </div>
                <div style="margin-bottom: 8px;">
                    <strong>Your Answer:</strong> ${formatMatchingAnswer(result.student_answer, result.question_type, index)}
                </div>
                ${result.is_correct !== null ? `
                    <div>
                        <strong>Correct Answer:</strong> ${formatMatchingAnswer(result.correct_answer, result.question_type, index)}
                    </div>
                ` : `
                    <div style="color: #f59e0b; font-style: italic;">
                        This essay question will be reviewed by your teacher.
                    </div>
                `}
            </div>
        `;
    });
    
    html += '</div>';
    content.innerHTML = html;
    modal.style.display = 'block';
}

function getScoreEmoji(score) {
    if (score >= 90) return '🎉';
    if (score >= 80) return '😊';
    if (score >= 70) return '👍';
    if (score >= 60) return '😐';
    return '😔';
}

function closeResults() {
    document.getElementById('results-modal').style.display = 'none';
}

function showQuestionSets() {
    document.getElementById('question-sets-list').style.display = 'block';
    document.getElementById('question-set-content').style.display = 'none';
    currentSetTitle = '';
    currentQuestions = [];
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatMatchingAnswer(answer, questionType, questionIndex = 0) {
    if (questionType === 'matching') {
        try {
            const matches = JSON.parse(answer || '{}');
            if (Object.keys(matches).length === 0) {
                return 'No answer provided';
            }
            
            let formatted = '';
            let index = 1;
            for (const [left, right] of Object.entries(matches)) {
                formatted += `Q${questionIndex + 1 + index - 1} → ${escapeHtml(right)}<br>`;
                index++;
            }
            return formatted;
        } catch (e) {
            return escapeHtml(answer || 'Invalid answer format');
        }
    } else {
        return escapeHtml(answer || 'No answer provided');
    }
}

// Matching question functionality
function shuffleArray(a) {
    if (!Array.isArray(a)) return [];
    for(let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

function buildMatchingUI(el, questionIndex = 0) {
    try {
        const qid = el.dataset.qid;
        const container = el.querySelector('.matching-grid');
        
        if (!container || !qid) {
            console.error('Missing container or question ID for matching widget');
            return;
        }

        // Parse the data with better error handling
        let lefts, rights;
        try {
            const leftsStr = el.dataset.lefts || '[]';
            const rightsStr = el.dataset.rights || '[]';
            
            console.log('Parsing lefts:', leftsStr);
            console.log('Parsing rights:', rightsStr);
            
            lefts = JSON.parse(leftsStr);
            rights = JSON.parse(rightsStr);
        } catch (parseError) {
            console.error('Error parsing matching question data for question', qid, ':', parseError);
            console.error('Lefts data:', el.dataset.lefts);
            console.error('Rights data:', el.dataset.rights);
            
            // Try to create a fallback with sample data
            container.innerHTML = `
                <div style="color: #f59e0b; padding: 10px; border: 1px solid #f59e0b; border-radius: 6px; background: #fef3c7;">
                    <strong>⚠️ Data Error:</strong> Matching question data is corrupted.<br>
                    <small>Question ID: ${qid}</small><br>
                    <small>This question will be marked as "Needs Review" for teacher grading.</small>
                </div>
            `;
            return;
        }

        // Validate data structure
        if (!Array.isArray(lefts) || !Array.isArray(rights)) {
            console.error('Invalid data structure for matching question', qid, ':', { lefts, rights });
            container.innerHTML = '<div style="color: #ef4444; padding: 10px;">Invalid matching question data structure</div>';
            return;
        }

        if (lefts.length === 0 || rights.length === 0) {
            console.error('Empty arrays for matching question', qid, ':', { lefts, rights });
            container.innerHTML = '<div style="color: #ef4444; padding: 10px;">No matching items found</div>';
            return;
        }

        // Filter out invalid items
        const validLefts = lefts.filter(item => item && typeof item === 'string' && item.trim().length > 0);
        const validRights = rights.filter(item => item && typeof item === 'string' && item.trim().length > 0);

        if (validLefts.length === 0 || validRights.length === 0) {
            console.error('No valid items after filtering for matching question', qid, ':', { validLefts, validRights });
            container.innerHTML = '<div style="color: #ef4444; padding: 10px;">No valid matching items found</div>';
            return;
        }

        const shuffled = shuffleArray(validRights.slice());
        container.innerHTML = '';

        validLefts.forEach((L, idx) => {
            const row = document.createElement('div');
            row.style.display = 'contents';
            row.setAttribute('data-row-index', idx);

            const leftDiv = document.createElement('div');
            // Use the question index to determine the starting number for matching items
            leftDiv.textContent = `Q${questionIndex + 1 + idx}`;
            leftDiv.style.padding = '8px';
            leftDiv.style.alignSelf = 'center';
            leftDiv.style.fontWeight = '500';
            leftDiv.setAttribute('data-left-item', L.trim());

            const rightDiv = document.createElement('div');
            const sel = document.createElement('select');
            sel.style.width = '100%';
            sel.style.padding = '8px';
            sel.style.border = '2px solid #d7ecff';
            sel.style.borderRadius = '8px';
            sel.style.background = '#fff';
            sel.innerHTML = '<option value="">— choose —</option>';
            sel.setAttribute('data-left-item', L.trim());
            
            shuffled.forEach(opt => {
                const o = document.createElement('option');
                o.value = opt.trim();
                o.textContent = opt.trim();
                sel.appendChild(o);
            });

            rightDiv.appendChild(sel);
            row.appendChild(leftDiv);
            row.appendChild(rightDiv);
            container.appendChild(row);
        });

        console.log('Successfully built matching UI for question', qid, 'with', validLefts.length, 'left items and', validRights.length, 'right items');
    } catch (error) {
        console.error('Error building matching UI:', error);
        const container = el.querySelector('.matching-grid');
        if (container) {
            container.innerHTML = '<div style="color: #ef4444; padding: 10px;">Error loading matching question</div>';
        }
    }
}
</script>
<?php
$content = ob_get_clean();
require_once 'includes/student_layout.php';
?>

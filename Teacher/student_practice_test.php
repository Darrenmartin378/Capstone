<?php
require_once __DIR__ . '/includes/teacher_init.php';

// This file will be used by students to take practice tests
// It should be moved to the student module later

// Get practice test ID from URL
$practiceTestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($practiceTestId <= 0) {
    die('Invalid practice test ID');
}

// Get practice test details
$stmt = $conn->prepare("SELECT pt.*, t.name as teacher_name 
                       FROM practice_tests pt 
                       JOIN teachers t ON pt.teacher_id = t.id 
                       WHERE pt.id = ?");
$stmt->bind_param("i", $practiceTestId);
$stmt->execute();
$practiceTest = $stmt->get_result()->fetch_assoc();

if (!$practiceTest) {
    die('Practice test not found');
}

// Get questions for this practice test
$questionsQuery = "SELECT qb.*, ptq.question_order 
                  FROM practice_test_questions ptq 
                  JOIN question_bank qb ON ptq.question_id = qb.id 
                  WHERE ptq.practice_test_id = ? 
                  ORDER BY ptq.question_order";
$stmt = $conn->prepare($questionsQuery);
$stmt->bind_param("i", $practiceTestId);
$stmt->execute();
$questions = $stmt->get_result();

if ($questions->num_rows === 0) {
    die('No questions found for this practice test');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_practice_test') {
    $studentId = $_SESSION['student_id'] ?? 1; // TODO: Get from student session
    
    // Create practice test attempt
    $stmt = $conn->prepare("INSERT INTO practice_test_attempts (practice_test_id, student_id, total_questions, status) VALUES (?, ?, ?, 'completed')");
    $totalQuestions = $questions->num_rows;
    $stmt->bind_param("iii", $practiceTestId, $studentId, $totalQuestions);
    $stmt->execute();
    $attemptId = $conn->insert_id;
    
    $correctAnswers = 0;
    
    // Process each question response
    foreach ($_POST['answers'] as $questionId => $studentAnswer) {
        // Get correct answer for this question
        $stmt = $conn->prepare("SELECT correct_answer FROM question_bank WHERE id = ?");
        $stmt->bind_param("i", $questionId);
        $stmt->execute();
        $correctAnswer = $stmt->get_result()->fetch_assoc()['correct_answer'];
        
        $isCorrect = (strtolower(trim($studentAnswer)) === strtolower(trim($correctAnswer)));
        if ($isCorrect) $correctAnswers++;
        
        // Save response
        $stmt = $conn->prepare("INSERT INTO practice_test_responses (attempt_id, question_id, student_answer, is_correct) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $attemptId, $questionId, $studentAnswer, $isCorrect);
        $stmt->execute();
    }
    
    // Update attempt with score
    $score = ($correctAnswers / $totalQuestions) * 100;
    $stmt = $conn->prepare("UPDATE practice_test_attempts SET score = ?, correct_answers = ?, completed_at = NOW() WHERE id = ?");
    $stmt->bind_param("dii", $score, $correctAnswers, $attemptId);
    $stmt->execute();
    
    // Redirect to results page
    header("Location: practice_test_results.php?attempt_id=" . $attemptId);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($practiceTest['title']); ?> - Practice Test</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
            margin: 0;
            color: #222;
            min-height: 100vh;
        }
        
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .test-header {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .test-title {
            font-size: 2rem;
            font-weight: 700;
            color: #4f46e5;
            margin-bottom: 8px;
        }
        
        .test-meta {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .meta-icon {
            color: #6366f1;
        }
        
        .timer {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .questions-container {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .question-item {
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .question-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .question-number {
            background: #6366f1;
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .question-text {
            font-size: 1.1rem;
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 16px;
            line-height: 1.6;
        }
        
        .answer-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        .answer-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        
        .submit-section {
            margin-top: 32px;
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 16px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16,185,129,0.3);
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16,185,129,0.4);
        }
        
        .submit-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .progress-bar {
            background: #e5e7eb;
            height: 8px;
            border-radius: 4px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }
        
        .skill-focus {
            background: linear-gradient(135deg, #e0e7ff, #f0f9ff);
            color: #3730a3;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 16px;
        }
        
        @media (max-width: 768px) {
            .test-container {
                padding: 16px;
            }
            
            .test-title {
                font-size: 1.6rem;
            }
            
            .test-meta {
                flex-direction: column;
                gap: 12px;
            }
            
            .questions-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1 class="test-title"><?php echo htmlspecialchars($practiceTest['title']); ?></h1>
            <?php if (!empty($practiceTest['description'])): ?>
                <p style="color: #6b7280; margin-bottom: 16px;"><?php echo htmlspecialchars($practiceTest['description']); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($practiceTest['skill_focus'])): ?>
                <span class="skill-focus">
                    <i class="fas fa-target"></i> <?php echo htmlspecialchars($practiceTest['skill_focus']); ?>
                </span>
            <?php endif; ?>
            
            <div class="test-meta">
                <div class="meta-item">
                    <i class="fas fa-clock meta-icon"></i>
                    <span>Duration: <?php echo $practiceTest['duration_minutes']; ?> minutes</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-question-circle meta-icon"></i>
                    <span><?php echo $questions->num_rows; ?> questions</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-user meta-icon"></i>
                    <span>By: <?php echo htmlspecialchars($practiceTest['teacher_name']); ?></span>
                </div>
                <div class="timer" id="timer">
                    <i class="fas fa-stopwatch"></i>
                    <span id="timeDisplay"><?php echo $practiceTest['duration_minutes']; ?>:00</span>
                </div>
            </div>
        </div>
        
        <div class="questions-container">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%"></div>
            </div>
            
            <form id="practiceTestForm" method="POST">
                <input type="hidden" name="action" value="submit_practice_test">
                
                <?php 
                $questionNumber = 1;
                $questions->data_seek(0);
                while ($question = $questions->fetch_assoc()): 
                ?>
                    <div class="question-item">
                        <div class="question-number"><?php echo $questionNumber; ?></div>
                        <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                        <input type="text" 
                               name="answers[<?php echo $question['id']; ?>]" 
                               class="answer-input" 
                               placeholder="Enter your answer here..."
                               onchange="updateProgress()">
                    </div>
                <?php 
                $questionNumber++;
                endwhile; 
                ?>
                
                <div class="submit-section">
                    <button type="submit" class="submit-btn" id="submitBtn" disabled>
                        <i class="fas fa-check-circle"></i> Submit Practice Test
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Timer functionality
        let timeLeft = <?php echo $practiceTest['duration_minutes'] * 60; ?>; // Convert to seconds
        const timerElement = document.getElementById('timeDisplay');
        const submitBtn = document.getElementById('submitBtn');
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                // Time's up - auto submit
                document.getElementById('practiceTestForm').submit();
                return;
            }
            
            timeLeft--;
        }
        
        // Update timer every second
        setInterval(updateTimer, 1000);
        
        // Progress tracking
        function updateProgress() {
            const inputs = document.querySelectorAll('.answer-input');
            const filledInputs = document.querySelectorAll('.answer-input:not([value=""])');
            const progress = (filledInputs.length / inputs.length) * 100;
            
            document.getElementById('progressFill').style.width = progress + '%';
            
            // Enable submit button when all questions are answered
            if (filledInputs.length === inputs.length) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }
        
        // Add event listeners to all inputs
        document.querySelectorAll('.answer-input').forEach(input => {
            input.addEventListener('input', updateProgress);
        });
        
        // Form submission confirmation
        document.getElementById('practiceTestForm').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to submit your practice test? You cannot change your answers after submission.')) {
                e.preventDefault();
            }
        });
        
        // Initialize progress
        updateProgress();
    </script>
</body>
</html>

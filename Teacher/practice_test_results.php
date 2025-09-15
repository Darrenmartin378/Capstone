<?php
require_once __DIR__ . '/includes/teacher_init.php';

// Get attempt ID from URL
$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

if ($attemptId <= 0) {
    die('Invalid attempt ID');
}

// Get attempt details with practice test info
$stmt = $conn->prepare("SELECT pta.*, pt.title, pt.description, pt.skill_focus, t.name as teacher_name
                       FROM practice_test_attempts pta
                       JOIN practice_tests pt ON pta.practice_test_id = pt.id
                       JOIN teachers t ON pt.teacher_id = t.id
                       WHERE pta.id = ?");
$stmt->bind_param("i", $attemptId);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();

if (!$attempt) {
    die('Attempt not found');
}

// Get detailed results
$stmt = $conn->prepare("SELECT ptr.*, qb.question_text, qb.correct_answer, qb.question_type, qb.difficulty_level
                       FROM practice_test_responses ptr
                       JOIN question_bank qb ON ptr.question_id = qb.id
                       WHERE ptr.attempt_id = ?
                       ORDER BY ptr.id");
$stmt->bind_param("i", $attemptId);
$stmt->execute();
$responses = $stmt->get_result();

// Calculate statistics
$totalQuestions = $attempt['total_questions'];
$correctAnswers = $attempt['correct_answers'];
$score = $attempt['score'];
$percentage = round($score, 1);

// Determine performance level
$performanceLevel = '';
$performanceColor = '';
if ($percentage >= 90) {
    $performanceLevel = 'Excellent';
    $performanceColor = '#10b981';
} elseif ($percentage >= 80) {
    $performanceLevel = 'Very Good';
    $performanceColor = '#3b82f6';
} elseif ($percentage >= 70) {
    $performanceLevel = 'Good';
    $performanceColor = '#f59e0b';
} elseif ($percentage >= 60) {
    $performanceLevel = 'Satisfactory';
    $performanceColor = '#ef4444';
} else {
    $performanceLevel = 'Needs Improvement';
    $performanceColor = '#dc2626';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practice Test Results - <?php echo htmlspecialchars($attempt['title']); ?></title>
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
        
        .results-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .results-header {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .results-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: #4f46e5;
            margin-bottom: 8px;
        }
        
        .test-name {
            font-size: 1.2rem;
            color: #6b7280;
            margin-bottom: 24px;
        }
        
        .score-display {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 24px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            position: relative;
            background: conic-gradient(<?php echo $performanceColor; ?> <?php echo $percentage * 3.6; ?>deg, #e5e7eb 0deg);
        }
        
        .score-circle::before {
            content: '';
            position: absolute;
            width: 80px;
            height: 80px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .score-text {
            position: relative;
            z-index: 1;
        }
        
        .performance-level {
            background: <?php echo $performanceColor; ?>;
            color: #fff;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .correct-icon { color: #10b981; }
        .incorrect-icon { color: #ef4444; }
        .time-icon { color: #3b82f6; }
        .questions-icon { color: #8b5cf6; }
        
        .detailed-results {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .question-result {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
        }
        
        .question-result:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .question-result.correct {
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }
        
        .question-result.incorrect {
            border-left: 4px solid #ef4444;
            background: #fef2f2;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .question-number {
            background: #6366f1;
            color: #fff;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .result-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .result-badge.correct {
            background: #d1fae5;
            color: #065f46;
        }
        
        .result-badge.incorrect {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .question-text {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        
        .answer-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 12px;
        }
        
        .answer-box {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .answer-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 6px;
        }
        
        .answer-text {
            color: #1f2937;
            font-weight: 500;
        }
        
        .correct-answer {
            background: #f0fdf4;
            border-color: #10b981;
        }
        
        .student-answer {
            background: #fef2f2;
            border-color: #ef4444;
        }
        
        .actions {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            margin: 0 8px;
        }
        
        .btn-primary {
            background: #6366f1;
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #4f46e5;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        @media (max-width: 768px) {
            .results-container {
                padding: 16px;
            }
            
            .results-title {
                font-size: 1.8rem;
            }
            
            .score-display {
                flex-direction: column;
                gap: 16px;
            }
            
            .answer-section {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="results-container">
        <div class="results-header">
            <h1 class="results-title">Practice Test Results</h1>
            <p class="test-name"><?php echo htmlspecialchars($attempt['title']); ?></p>
            
            <div class="score-display">
                <div class="score-circle">
                    <div class="score-text"><?php echo $percentage; ?>%</div>
                </div>
                <div class="performance-level"><?php echo $performanceLevel; ?></div>
            </div>
            
            <?php if (!empty($attempt['skill_focus'])): ?>
                <div style="background: linear-gradient(135deg, #e0e7ff, #f0f9ff); color: #3730a3; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; display: inline-block;">
                    <i class="fas fa-target"></i> <?php echo htmlspecialchars($attempt['skill_focus']); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon correct-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $correctAnswers; ?></div>
                <div class="stat-label">Correct Answers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon incorrect-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value"><?php echo $totalQuestions - $correctAnswers; ?></div>
                <div class="stat-label">Incorrect Answers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon questions-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="stat-value"><?php echo $totalQuestions; ?></div>
                <div class="stat-label">Total Questions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon time-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo date('M j, Y', strtotime($attempt['completed_at'])); ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        
        <div class="detailed-results">
            <h2 class="section-title">
                <i class="fas fa-list-alt"></i>
                Detailed Results
            </h2>
            
            <?php 
            $questionNumber = 1;
            while ($response = $responses->fetch_assoc()): 
                $isCorrect = $response['is_correct'];
            ?>
                <div class="question-result <?php echo $isCorrect ? 'correct' : 'incorrect'; ?>">
                    <div class="question-header">
                        <div class="question-number"><?php echo $questionNumber; ?></div>
                        <div class="result-badge <?php echo $isCorrect ? 'correct' : 'incorrect'; ?>">
                            <i class="fas fa-<?php echo $isCorrect ? 'check' : 'times'; ?>"></i>
                            <?php echo $isCorrect ? 'Correct' : 'Incorrect'; ?>
                        </div>
                    </div>
                    
                    <div class="question-text"><?php echo htmlspecialchars($response['question_text']); ?></div>
                    
                    <div class="answer-section">
                        <div class="answer-box student-answer">
                            <div class="answer-label">Your Answer:</div>
                            <div class="answer-text"><?php echo htmlspecialchars($response['student_answer']); ?></div>
                        </div>
                        
                        <div class="answer-box correct-answer">
                            <div class="answer-label">Correct Answer:</div>
                            <div class="answer-text"><?php echo htmlspecialchars($response['correct_answer']); ?></div>
                        </div>
                    </div>
                </div>
            <?php 
            $questionNumber++;
            endwhile; 
            ?>
        </div>
        
        <div class="actions">
            <a href="student_practice_test.php?id=<?php echo $attempt['practice_test_id']; ?>" class="btn btn-primary">
                <i class="fas fa-redo"></i> Retake Test
            </a>
            <a href="../Student/student_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>

<?php
require_once 'Includes/student_init.php';
require_once 'includes/NewResponseHandler.php';

$responseHandler = new NewResponseHandler($conn);
$studentId = (int)($_SESSION['student_id'] ?? 0);
$sectionId = (int)($_SESSION['section_id'] ?? 0);
$testId = (int)($_GET['test_id'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'submit_practice_test') {
            $testId = (int)($_POST['test_id'] ?? 0);
            $responses = json_decode($_POST['responses'] ?? '{}', true) ?? [];
            $showResults = isset($_POST['show_results']) && $_POST['show_results'] === '1';
            
            if ($testId <= 0) {
                throw new Exception('Invalid test ID');
            }
            
            // Calculate score
            $totalScore = 0;
            $maxPoints = 0;
            $answeredQuestions = 0;
            $totalQuestions = 0;
            
            // Get all questions for this test
            $stmt = $conn->prepare("SELECT * FROM practice_test_questions WHERE practice_test_id = ?");
            $stmt->bind_param('i', $testId);
            $stmt->execute();
            $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $totalQuestions = count($questions);
            
            foreach ($questions as $question) {
                $maxPoints += (int)$question['points'];
                
                if (isset($responses[$question['id']])) {
                    $answeredQuestions++;
                    $userAnswer = $responses[$question['id']];
                    
                    // Check if answer is correct based on question type
                    if ($question['question_type'] === 'mcq') {
                        if ($userAnswer === $question['correct_answer']) {
                            $totalScore += (int)$question['points'];
                        }
                    } elseif ($question['question_type'] === 'matching') {
                        // For matching questions, check each match
                        $correctMatches = json_decode($question['correct_matches'] ?? '{}', true);
                        $userMatches = is_array($userAnswer) ? $userAnswer : [];
                        $correctCount = 0;
                        $totalMatches = count($correctMatches);
                        
                        foreach ($userMatches as $index => $userMatch) {
                            if (isset($correctMatches[$index]) && $userMatch === $correctMatches[$index]) {
                                $correctCount++;
                            }
                        }
                        
                        if ($totalMatches > 0) {
                            $matchScore = ($correctCount / $totalMatches) * (int)$question['points'];
                            $totalScore += $matchScore;
                        }
                    } elseif ($question['question_type'] === 'essay') {
                        // For essay questions, give full points (manual grading would be needed for actual scoring)
                        $totalScore += (int)$question['points'];
                    }
                }
            }
            
            $percentage = $maxPoints > 0 ? round(($totalScore / $maxPoints) * 100, 1) : 0;
            
            // Store the submission in the database
            try {
                // Check if submission already exists
                $checkStmt = $conn->prepare("SELECT id FROM practice_test_submissions WHERE practice_test_id = ? AND student_id = ?");
                $checkStmt->bind_param('ii', $testId, $_SESSION['student_id']);
                $checkStmt->execute();
                $existingSubmission = $checkStmt->get_result()->fetch_assoc();
                
                if ($existingSubmission) {
                    // Update existing submission
                    $updateStmt = $conn->prepare("UPDATE practice_test_submissions SET score = ?, percentage = ?, answered_questions = ?, total_questions = ?, submitted_at = NOW() WHERE practice_test_id = ? AND student_id = ?");
                    $updateStmt->bind_param('ddiii', $totalScore, $percentage, $answeredQuestions, $totalQuestions, $testId, $_SESSION['student_id']);
                    $updateStmt->execute();
                } else {
                    // Insert new submission
                    $insertStmt = $conn->prepare("INSERT INTO practice_test_submissions (practice_test_id, student_id, score, percentage, answered_questions, total_questions, submitted_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $insertStmt->bind_param('iiddii', $testId, $_SESSION['student_id'], $totalScore, $percentage, $answeredQuestions, $totalQuestions);
                    $insertStmt->execute();
                }
            } catch (Exception $e) {
                // Log error but don't fail the response
                error_log("Error storing practice test submission: " . $e->getMessage());
            }
            
            // Handle case where no questions were answered
            if ($answeredQuestions === 0) {
                echo json_encode([
                    'success' => true,
                    'total_score' => 0,
                    'max_points' => $maxPoints,
                    'percentage' => 0,
                    'answered_questions' => 0,
                    'total_questions' => $totalQuestions,
                    'message' => 'Test submitted with no answers'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'total_score' => round($totalScore, 1),
                    'max_points' => $maxPoints,
                    'percentage' => $percentage,
                    'answered_questions' => $answeredQuestions,
                    'total_questions' => $totalQuestions,
                    'message' => 'Test submitted successfully'
                ]);
            }
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

if ($testId <= 0) {
    header('Location: student_practice.php');
    exit();
}

// Fetch practice test details
$stmt = $conn->prepare("SELECT * FROM practice_tests WHERE id = ? AND section_id = ?");
$stmt->bind_param('ii', $testId, $sectionId);
$stmt->execute();
$test = $stmt->get_result()->fetch_assoc();

if (!$test) {
    header('Location: student_practice.php');
    exit();
}

// Fetch practice test questions
$stmt = $conn->prepare("SELECT * FROM practice_test_questions WHERE practice_test_id = ? ORDER BY id");
$stmt->bind_param('i', $testId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

ob_start();
?>
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Galaxy background overlay ready for your live background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
                        radial-gradient(ellipse at bottom right, rgba(34, 211, 238, 0.1) 0%, transparent 50%),
                        radial-gradient(ellipse at bottom left, rgba(168, 85, 247, 0.08) 0%, transparent 50%);
            z-index: -1;
            pointer-events: none;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        /* Main centered page shell */
        .page-shell { max-width: 1240px; margin: 0; padding: 0 16px; }
        .content-header.sticky { position: sticky; top: 0; z-index: 5; }
        .main-content { padding: 20px; min-height: 100vh; }
        
        .header {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .question-sets {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 24px;
            justify-content: start;
            justify-items: stretch;
        }
        @media (max-width: 640px) {
            .page-shell { padding: 0 12px; }
            .content-header { margin-bottom: 12px; }
            .question-sets { grid-template-columns: 1fr; }
        }
        
        .set-card {
            position: relative;
            background: #ffffff;
            padding: 22px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        /* Cosmic glow effect */
        .set-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #2563eb, transparent);
            pointer-events: none;
        }
        
        .set-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #2563eb;
        }
        
        .set-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 12px;
            color: #1e293b;
            letter-spacing: .2px;
        }
        
        .set-stats {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 15px;
        }
        /* Inline meta with icons */
        .set-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; color: #64748b; font-size: 14px; margin-bottom: 14px; }
        .set-meta .meta { display: inline-flex; align-items: center; gap:6px; }
        .set-meta .dot { opacity:.5; }
        
        .btn {
            background: #2563eb;
            color: white;
            border: 1px solid #1d4ed8;
            padding: 12px 22px;
            border-radius: 9999px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex; align-items:center; gap:8px;
            transition: transform .12s ease, filter .2s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        .btn:hover {
            filter: brightness(1.1); 
            transform: translateY(-2px); 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .question-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .question-item {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .question-number {
            background: #4285f4;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .question-points {
            background: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .question-text {
            color: Black;
            font-size: 16px;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .question-options {
            margin-bottom: 15px;
        }
        
        .option {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 14px 16px;
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s, border-color .2s ease;
            user-select: none;
        }
        
        .option:hover {
            background: #f8f9fa;
        }
        
        .option input[type="radio"] {
            margin-right: 12px;
            width: 20px; height: 20px;
            accent-color: #3b82f6;
            flex-shrink: 0;
        }
        .option label { color: Black; flex:1; cursor:pointer; }
        
        .matching-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .matching-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .matching-item label {
            margin-right: 10px;
            font-weight: 500;
        }
        
        .matching-item select {
            flex: 1;
            padding: 8px;
            border: 1px solid #e1e5e9;
            border-radius: 4px;
        }
        
        .essay-textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            border: 1px solid #e1e5e9;
            border-radius: 4px;
            font-family: inherit;
            resize: vertical;
        }
        
        .submit-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            background: #218838;
        }
        
        .score-display {
            background: #e8f5e8;
            border: 1px solid #28a745;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .score-display h3 {
            color: #28a745;
            margin-bottom: 5px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e1e5e9;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s;
        }
        
        /* Matching Question Styles - Drag and Drop */
        .matching-container {
            margin: 20px 0;
        }
        
        .matching-instructions {
            color: Black;
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        
        .drag-drop-container {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .draggable-items, .drop-zones, .answer-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .draggable-items h4, .drop-zones h4, .answer-items h4 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
            font-weight: 600;
        }
        
        .draggable-item {
            background: #000;
            color: white;
            padding: 12px 15px;
            border-radius: 6px;
            cursor: grab;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            user-select: none;
        }
        
        .draggable-item:hover {
            background: #333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .draggable-item:active {
            cursor: grabbing;
            transform: scale(0.95);
        }
        
        .draggable-item.dragging {
            opacity: 0.5;
            transform: rotate(5deg);
        }
        
        .drag-number {
            background: #fff;
            color: #000;
            padding: 4px 8px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 12px;
            min-width: 20px;
            text-align: center;
        }
        
        .drag-text {
            font-weight: 500;
        }
        
        .drop-zone {
            background: #f8f9fa;
            border: 2px dashed #ced4da;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
        }
        .clear-btn { position: absolute; top: 6px; right: 6px; background: #dc3545; color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; line-height: 22px; text-align: center; cursor: pointer; font-weight: 700; display: none; }
        .drop-zone.has-answer .clear-btn { display: block; }
        
        .drop-zone.drag-over {
            background: #e3f2fd;
            border-color: #2196f3;
            border-style: solid;
        }
        
        .drop-zone.correct {
            background: #d4edda;
            border-color: #28a745;
            border-style: solid;
        }
        
        .drop-zone.incorrect {
            background: #d4edda;
            border-color: #28a745;
            border-style: solid;
        }
        
        .drop-placeholder {
            color: #6c757d;
            font-weight: 500;
            font-size: 14px;
        }
        
        .dropped-item {
            background: #007bff;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .dropped-item .drag-number {
            background: white;
            color: #007bff;
        }
        
        .answer-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 12px 15px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .answer-number {
            background: #6c757d;
            color: white;
            padding: 4px 8px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 12px;
            min-width: 20px;
            text-align: center;
        }
        
        .answer-text {
            font-weight: 500;
            color: #333;
        }
        
        .matching-score {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        
        .score-text {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: block;
        }
        
        .content-header {
            background: none;
            padding: 0;
            border-radius: 0;
            box-shadow: none;
            margin-bottom: 12px;
        }
        .content-header h1{ 
            font-weight:900; 
            color: #1e293b;
            text-shadow: none;
        }

        /* Centered quiz container that appears below the title */
        /* Quiz modal overlay */
        .quiz-shell {
            display: none; /* flex when open */
            position: static; 
            background: #ffffff;
            padding: 30px; 
            width: 100%;
            justify-content: center;
            align-items: flex-start;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        .quiz-shell .question-form { width: 100%; max-width: none; margin: 0; border-radius: 0; min-height: calc(100vh - 80px); }
        .quiz-close {
            position: absolute; top: 10px; right: 12px; border: none; background: #ef4444; color: #fff;
            border-radius: 9999px; width: 28px; height: 28px; cursor: pointer; font-weight: 700;
        }

        /* Progress bar at the top of the quiz */
        .quiz-progress {
            display: none;
            margin: 0 0 16px 0;
        }
        .quiz-progress .progress-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        .quiz-progress .bar {
            width: 100%;
            height: 10px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
        }
        .quiz-progress .bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg,#6366f1,#22c55e);
            transition: width .35s ease;
        }

        /* Question card polish */
        .question-item.card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0,0,0,.08);
            animation: slideIn .35s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Option interactions */
        .option {
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 10px;
            transition: background .15s ease, transform .08s ease, border-color .15s ease;
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(8px);
        }
        .option:hover { 
            background: rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.5);
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
        }
        .option input[type="radio"] { accent-color: #8b5cf6; }
        .option.selected { 
            border-color: rgba(139, 92, 246, 0.6); 
            background: rgba(139, 92, 246, 0.2);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2), 0 0 20px rgba(139, 92, 246, 0.3);
        }
        .option:active { transform: scale(.98); }

        /* Bottom actions */
        .nav-actions { display:flex; gap:12px; justify-content:flex-end; margin-top: 12px; }
        .btn-ghost { background:#eef2ff; color:#3730a3; }
        .btn-disabled { opacity:.6; cursor:not-allowed; }

        /* Loading overlay between questions */
        .loading-overlay {
            position: absolute; inset: 0; background: rgba(255,255,255,.75);
            display: none; align-items: center; justify-content: center; border-radius: 8px;
        }
        .spinner {
            width: 32px; height: 32px; border-radius: 50%;
            border: 3px solid #e5e7eb; border-top-color: #3b82f6; animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Encouragement message */
        .encourage { display:none; text-align:center; color:#111827; font-weight:600; margin: 6px 0 12px; }
        .encourage small { color:#6b7280; font-weight:500; }

        /* Timer & badges */
        .timer-display {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            display: none;
            font-weight: 800;
            color: #111827;
            background: #fde68a;
            border: 2px solid #fcd34d;
            padding: 8px 12px;
            border-radius: 9999px;
            box-shadow: 0 6px 12px rgba(0,0,0,.08);
        }
        .badge { display:inline-block; padding:6px 10px; border-radius:9999px; font-size:12px; background:#eef2ff; color:#3730a3; margin-left:8px; font-weight:700; }
        .badge.timer { background:#cffafe; color:#075985; }
        .badge.open { background:#fde68a; color:#92400e; }
        .badge.diff-easy{background:#dcfce7;color:#065f46}
        .badge.diff-medium{background:#fef9c3;color:#92400e}
        .badge.diff-hard{background:#fee2e2;color:#9f1239}
        .locked-info{display:flex;align-items:center;gap:8px;margin-top:10px;color:#6b7280;font-size:12px;background:#f1f5f9;border:1px dashed #e2e8f0;padding:6px 10px;border-radius:9999px;width:max-content}
        .badge.starts { background:#e0e7ff; color:#1e40af; }
    </style>

<div class="page-shell" id="pageShell" style="width: 100%; margin: 0; padding: 16px;">
    <div class="content-header" style="width:100%;">
        <h1><i class="fas fa-question-circle"></i> <?php echo htmlspecialchars($test['title']); ?></h1>
    </div>
    
    <div id="quizShell" class="quiz-shell" tabindex="-1" aria-modal="true" role="dialog" style="display: block;">
        <div id="questionForm" class="question-form" style="display: block; position: relative;">
            <button type="button" class="quiz-close" title="Close" onclick="closeQuiz()">×</button>
            <h2 id="formTitle" style="color: Black;"><?php echo htmlspecialchars($test['title']); ?></h2>
            <div class="quiz-progress" id="quizProgress">
                <div class="progress-meta">
                    <span id="progressLabel">Question 1</span>
                    <span id="progressCount">0 / <?php echo count($questions); ?></span>
                </div>
                <div class="bar"><div class="bar-fill" id="progressFill"></div></div>
            </div>
            <div class="timer-display" id="timerDisplay" aria-live="polite" style="display: inline-block;">
                Time Remaining: <span id="time-display"><?php echo $test['timer_minutes']; ?>:00</span>
            </div>
            <div class="encourage" id="encourageMsg">Great job! <small>Next one 💪</small></div>
            
            <form id="practiceTestForm" method="POST" action="">
                <input type="hidden" name="test_id" value="<?php echo $testId; ?>">
                <input type="hidden" name="action" value="submit_practice_test">
                
                <div id="questionsContainer">
                    <?php foreach ($questions as $index => $question): ?>
                    <div class="question-item card">
                        <div class="question-header">
                            <div class="question-number">Q<?php echo $index + 1; ?></div>
                            <div class="question-points"><?php echo $question['points']; ?> pts</div>
                        </div>
                        <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                
                        <?php if ($question['question_type'] === 'mcq'): ?>
                            <div class="question-options">
                                <?php 
                                $options = json_decode($question['mcq_options'] ?? '[]', true);
                                $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                                
                                // Ensure options is an array and not empty
                                if (is_array($options) && !empty($options)) {
                                    foreach ($options as $i => $option): 
                                        // Ensure we have a valid letter and option
                                        if (isset($letters[$i]) && is_string($option) && !empty(trim($option))):
                                ?>
                                    <div class="option">
                                        <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="<?php echo $letters[$i]; ?>" id="q<?php echo $question['id']; ?>_<?php echo $letters[$i]; ?>">
                                        <label for="q<?php echo $question['id']; ?>_<?php echo $letters[$i]; ?>"><?php echo htmlspecialchars($option); ?></label>
                                    </div>
                                <?php 
                                        endif;
                                    endforeach; 
                                } else {
                                    echo '<p>No options available for this question.</p>';
                                }
                                ?>
                            </div>
                    
                        <?php elseif ($question['question_type'] === 'matching'): ?>
                            <div class="matching-grid">
                                <div class="matching-item">
                                    <label>Left Items:</label>
                                    <div>
                                        <?php 
                                        $leftItems = json_decode($question['matching_left_items'] ?? '[]', true);
                                        if (is_array($leftItems) && !empty($leftItems)) {
                                            foreach ($leftItems as $i => $item): 
                                                if (is_string($item) && !empty(trim($item))):
                                        ?>
                                            <div style="margin: 5px 0; padding: 8px; background: #f8f9fa; border-radius: 4px;"><?php echo htmlspecialchars($item); ?></div>
                                        <?php 
                                                endif;
                                            endforeach; 
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="matching-item">
                                    <label>Right Items:</label>
                                    <div>
                                        <?php 
                                        $rightItems = json_decode($question['matching_right_items'] ?? '[]', true);
                                        if (is_array($rightItems) && !empty($rightItems)) {
                                            foreach ($rightItems as $i => $item): 
                                                if (is_string($item) && !empty(trim($item))):
                                        ?>
                                            <div style="margin: 5px 0; padding: 8px; background: #f8f9fa; border-radius: 4px;"><?php echo htmlspecialchars($item); ?></div>
                                        <?php 
                                                endif;
                                            endforeach; 
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="matching-matches">
                                <h4>Match the items:</h4>
                                <?php 
                                if (is_array($leftItems) && is_array($rightItems) && !empty($leftItems) && !empty($rightItems)) {
                                    foreach ($leftItems as $i => $leftItem): 
                                        if (is_string($leftItem) && !empty(trim($leftItem))):
                                ?>
                                    <div style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                                        <span style="color: #333; min-width: 200px;"><?php echo htmlspecialchars($leftItem); ?></span>
                                        <select name="matches[<?php echo $question['id']; ?>][<?php echo $i; ?>]" class="matching-select">
                                            <option value="">Select match</option>
                                            <?php foreach ($rightItems as $j => $rightItem): 
                                                if (is_string($rightItem) && !empty(trim($rightItem))):
                                            ?>
                                                <option value="<?php echo $j; ?>"><?php echo htmlspecialchars($rightItem); ?></option>
                                            <?php 
                                                endif;
                                            endforeach; ?>
                                        </select>
                                    </div>
                                <?php 
                                        endif;
                                    endforeach; 
                                } else {
                                    echo '<p>No matching items available.</p>';
                                }
                                ?>
                            </div>
                            
                        <?php elseif ($question['question_type'] === 'essay'): ?>
                            <textarea name="answers[<?php echo $question['id']; ?>]" class="essay-textarea" placeholder="Enter your answer here..."></textarea>
                            <?php if (!empty($question['essay_rubric'])): ?>
                                <div style="margin-top: 10px; padding: 12px; background: #e3f2fd; border-radius: 8px; border: 1px solid #2196f3;">
                                    <strong style="color: #333;">Rubric:</strong>
                                    <div style="color: #666; margin-top: 5px;"><?php echo nl2br(htmlspecialchars($question['essay_rubric'])); ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="nav-actions">
                    <button id="nextBtn" class="btn btn-success btn-disabled" disabled>Next</button>
                </div>
                <div class="loading-overlay" id="loadingOverlay"><div class="spinner"></div></div>
            </form>
        </div>
    </div>
        
        <div style="text-align: center;">
            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Submit Practice Test
            </button>
        </div>
    </form>
</div>

<script>
// Cache busting - force reload of updated JavaScript
console.log('Practice test script loaded - version 3.0 - ' + new Date().getTime());
console.log('=== SCRIPT LOADED - NO VALIDATION VERSION ===');
        let currentQuestionSetId = <?php echo $testId; ?>;
        let currentQuestions = <?php echo json_encode($questions); ?>;
        let currentIndex = 0;
        const studentResponses = {};
        const encourage = [
            'Great job! Next one 💪',
            'Nice progress! Keep it up ✨',
            'You got this! 🚀',
            'Awesome! Continue 👏',
            'Steady pace! Onward ➡️'
        ];
        
        // Timer functionality
        let timeLeft = <?php echo $test['timer_minutes'] * 60; ?>; // Convert minutes to seconds
        const timerDisplay = document.getElementById('time-display');
        let timerStarted = false;
        let countdownInterval = null;

        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                alert('Time is up! Your test will be submitted automatically.');
                document.getElementById('practiceTestForm').submit();
                return;
            }
            
            timeLeft--;
        }

        function startCountdown(seconds) {
            const td = document.getElementById('timerDisplay');
            if (!td) return;
            if (countdownInterval) { clearInterval(countdownInterval); }
            td.style.display = 'inline-block';
            function render() {
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                td.textContent = `Time Remaining: ${m}:${s.toString().padStart(2,'0')}`;
            }
            render();
            countdownInterval = setInterval(() => {
                seconds--;
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    td.textContent = 'Time is up! Submitting...';
                    // Automatically submit with results display
                    setTimeout(() => {
                        submitResponses(true); // Show results automatically
                    }, 1000);
                } else {
                    render();
                }
            }, 1000);
            timerStarted = true;
        }

        function stopCountdown() {
            if (countdownInterval) clearInterval(countdownInterval);
            const td = document.getElementById('timerDisplay');
            if (td) td.style.display = 'none';
            timerStarted = false;
        }

        function updateProgress() {
            const total = currentQuestions.length;
            const idx = Math.min(currentIndex + 1, total);
            document.getElementById('progressLabel').textContent = `Question ${idx}`;
            document.getElementById('progressCount').textContent = `${idx - 1}/${total}`;
            const fill = document.getElementById('progressFill');
            const pct = total > 0 ? ((idx - 1) / total) * 100 : 0;
            fill.style.width = pct + '%';
        }

        function renderCurrentQuestion() {
            const container = document.getElementById('questionsContainer');
            container.innerHTML = '';
            document.getElementById('encourageMsg').style.display = 'none';
            const nextBtn = document.getElementById('nextBtn');
            nextBtn.disabled = true; nextBtn.classList.add('btn-disabled');

            const total = currentQuestions.length;
            if (currentIndex >= total) { submitResponses(); return; }

            const question = currentQuestions[currentIndex];
            const qDiv = document.createElement('div');
            qDiv.className = 'question-item card';
            qDiv.innerHTML = `
                <div class="question-header">
                    <div class="question-number">Q${currentIndex + 1}</div>
                    <div class="question-points">${question.points} pts</div>
                </div>
                <div class="question-text">${question.question_text}</div>
                ${renderQuestionContent(question, currentIndex)}
            `;
            container.appendChild(qDiv);

            // Enable interactions
            if (question.question_type === 'mcq') {
                qDiv.querySelectorAll('.option input[type="radio"]').forEach(r => {
                    r.addEventListener('change', (e) => {
                        qDiv.querySelectorAll('.option').forEach(op => op.classList.remove('selected'));
                        e.target.closest('.option').classList.add('selected');
                        nextBtn.disabled = false; nextBtn.classList.remove('btn-disabled');
                    });
                });
            }

            if (question.question_type === 'matching') {
                const selects = qDiv.querySelectorAll('select');
                selects.forEach(select => {
                    select.addEventListener('change', () => {
                        const allSelected = Array.from(selects).every(s => s.value !== '');
                        if (allSelected) { nextBtn.disabled = false; nextBtn.classList.remove('btn-disabled'); }
                    });
                });
            }

            if (question.question_type === 'essay') {
                const ta = qDiv.querySelector('textarea');
                if (ta) ta.addEventListener('input', () => {
                    nextBtn.disabled = ta.value.trim().length === 0; 
                    nextBtn.classList.toggle('btn-disabled', nextBtn.disabled);
                });
            }

            // Update Next button label
            nextBtn.textContent = (currentIndex === total - 1) ? 'Submit' : 'Next';
            updateProgress();
        }
        
        function renderQuestionContent(question, index) {
            switch (question.question_type) {
                case 'mcq':
                    const options = JSON.parse(question.mcq_options || '[]');
                    return `
                        <div class="question-options">
                            ${options.map((option, optIndex) => `
                                <div class="option">
                                    <input type="radio" name="answers[${question.id}]" value="${(['A','B','C','D'][optIndex] || String.fromCharCode(65 + optIndex))}" id="q${question.id}_${optIndex}">
                                    <label for="q${question.id}_${optIndex}">${option}</label>
                                </div>
                            `).join('')}
                        </div>
                    `;
                    
                case 'matching':
                    const leftItems = JSON.parse(question.matching_left_items || '[]');
                    const rightItems = JSON.parse(question.matching_right_items || '[]');
                    
                    return `
                        <div class="matching-grid">
                            <div class="matching-item">
                                <label>Left Items:</label>
                                <div>
                                    ${leftItems.map(item => `<div style="margin: 5px 0; padding: 8px; background: #f8f9fa; border-radius: 4px;">${item}</div>`).join('')}
                                </div>
                            </div>
                            <div class="matching-item">
                                <label>Right Items:</label>
                                <div>
                                    ${rightItems.map(item => `<div style="margin: 5px 0; padding: 8px; background: #f8f9fa; border-radius: 4px;">${item}</div>`).join('')}
                                </div>
                            </div>
                        </div>
                        <div class="matching-matches">
                            <h4>Match the items:</h4>
                            ${leftItems.map((leftItem, i) => `
                                <div style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                                    <span style="color: #333; min-width: 200px;">${leftItem}</span>
                                    <select name="matches[${question.id}][${i}]" class="matching-select">
                                        <option value="">Select match</option>
                                        ${rightItems.map((rightItem, j) => `<option value="${j}">${rightItem}</option>`).join('')}
                                    </select>
                                </div>
                            `).join('')}
                        </div>
                    `;
                    
                case 'essay':
                    return `
                        <textarea class="essay-textarea" name="answers[${question.id}]" placeholder="Enter your answer here..."></textarea>
                        ${question.essay_rubric ? `
                            <div style="margin-top: 10px; padding: 12px; background: #e3f2fd; border-radius: 8px; border: 1px solid #2196f3;">
                                <strong style="color: #333;">Rubric:</strong>
                                <div style="color: #666; margin-top: 5px;">${question.essay_rubric}</div>
                            </div>
                        ` : ''}
                    `;
                    
                default:
                    return '<p>Unknown question type</p>';
            }
        }

        function collectCurrentAnswer() {
            const question = currentQuestions[currentIndex];
            if (!question) return;
            
            if (question.question_type === 'matching') {
                const matchingResponses = {};
                const selects = document.querySelectorAll(`select[name^="matches[${question.id}]"]`);
                selects.forEach(select => {
                    const matchIndex = select.name.match(/\[(\d+)\]$/)[1];
                    matchingResponses[matchIndex] = select.value;
                });
                studentResponses[question.id] = matchingResponses;
            } else {
                const response = document.querySelector(`input[name="answers[${question.id}]"]:checked, textarea[name="answers[${question.id}]"]`);
                if (response) studentResponses[question.id] = response.value;
            }
        }

        function submitResponses(showResults = false) {
            // FORCE OVERRIDE: Remove any validation that might block submission
            console.log('=== SUBMIT RESPONSES CALLED ===');
            console.log('Show results:', showResults);
            
            const responses = { ...studentResponses };
            
            // FORCE: Allow submission even with no answers (timer expired or student chose to submit)
            // NO VALIDATION REQUIRED - just submit whatever answers exist
            console.log('Submitting responses:', responses);
            console.log('Number of responses:', Object.keys(responses).length);
            
            // Override any potential validation
            if (Object.keys(responses).length === 0) {
                console.log('No answers provided - submitting empty test');
            }
            
            // FORCE SUBMISSION - NO VALIDATION CHECKS
            console.log('Proceeding with submission regardless of answer count...');
            
            // DEBUG: Show alert to confirm function is called
            if (Object.keys(responses).length === 0) {
                alert('DEBUG: Submitting with NO ANSWERS - this should work now!');
            }
            
            const formData = new FormData();
            formData.append('action', 'submit_practice_test');
            formData.append('test_id', currentQuestionSetId);
            formData.append('responses', JSON.stringify(responses));
            formData.append('show_results', showResults ? '1' : '0');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Server response:', data);
                if (data.success) {
                    if (showResults) {
                        // Show detailed results modal
                        showScoringResults(data);
                    } else {
                        alert('Practice test submitted successfully!');
                        window.location.href = 'student_practice.php';
                    }
                } else {
                    console.error('Server error:', data.error);
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        function showScoringResults(data) {
            // Store score data for later use
            window.lastScoreData = data;
            
            const modal = document.createElement('div');
            modal.className = 'scoring-modal';
            modal.innerHTML = `
                <div class="scoring-content">
                    <div class="scoring-header">
                        <h2><i class="fas fa-trophy"></i> Test Results</h2>
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" class="close-btn">&times;</button>
                    </div>
                    <div class="scoring-body">
                        <div class="score-summary">
                            <div class="score-circle">
                                <span class="score-number">${data.total_score || 0}</span>
                                <span class="score-total">/ ${data.max_points || 0}</span>
                            </div>
                            <div class="score-details">
                                <h3>Your Score: ${data.total_score || 0} out of ${data.max_points || 0} points</h3>
                                <p>Percentage: ${data.percentage || 0}%</p>
                                <p>Questions Answered: ${data.answered_questions || 0} out of ${data.total_questions || 0}</p>
                                ${(data.answered_questions || 0) === 0 ? '<p style="color: #ff6b6b; font-weight: 600;">No questions were answered</p>' : ''}
                            </div>
                        </div>
                        
                        <div class="scoring-actions">
                            <button onclick="goBackToPracticeTests()" class="btn">
                                <i class="fas fa-home"></i> Back to Practice Tests
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal styles
            const style = document.createElement('style');
            style.textContent = `
                .scoring-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.8);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 1000;
                }
                .scoring-content {
                    background: white;
                    border-radius: 16px;
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                }
                .scoring-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px;
                    border-bottom: 1px solid #eee;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 16px 16px 0 0;
                }
                .scoring-header h2 {
                    margin: 0;
                    color: white;
                }
                .close-btn {
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: white;
                }
                .scoring-body {
                    padding: 20px;
                }
                .score-summary {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                    margin-bottom: 20px;
                }
                .score-circle {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #4CAF50, #45a049);
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    color: white;
                    font-weight: bold;
                }
                .score-number {
                    font-size: 24px;
                }
                .score-total {
                    font-size: 14px;
                    opacity: 0.8;
                }
                .score-details h3 {
                    margin: 0 0 10px 0;
                    color: #333;
                }
                .score-details p {
                    margin: 5px 0;
                    color: #666;
                }
                .scoring-actions {
                    text-align: center;
                    margin-top: 20px;
                }
                .btn {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 16px;
                    font-weight: 600;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(modal);
        }
        
        // Function to go back to practice tests with score update
        function goBackToPracticeTests() {
            console.log('=== GOING BACK TO PRACTICE TESTS ===');
            console.log('Current test ID:', currentQuestionSetId);
            console.log('Last score data:', window.lastScoreData);
            
            // Redirect with a parameter to trigger refresh
            window.location.href = 'student_practice.php?completed=1';
        }

        function closeQuiz() {
            if (confirm('Are you sure you want to close? Your progress will be lost.')) {
                window.location.href = 'student_practice.php';
            }
        }

        // Next/Submit flow handling
        document.addEventListener('DOMContentLoaded', () => {
            const nextBtn = document.getElementById('nextBtn');
            nextBtn.addEventListener('click', () => {
                collectCurrentAnswer();
                const overlay = document.getElementById('loadingOverlay');
                overlay.style.display = 'flex';
                setTimeout(() => {
                    overlay.style.display = 'none';
                    currentIndex++;
                    const total = currentQuestions.length;
                    if (currentIndex < total) {
                        // encouragement
                        const msg = document.getElementById('encourageMsg');
                        msg.textContent = encourage[Math.floor(Math.random()*encourage.length)];
                        msg.style.display = 'block';
                        setTimeout(()=> (msg.style.display='none'), 1200);
                        renderCurrentQuestion();
                    } else {
                        // Final submit
                        submitResponses(true); // Show results for manual submit too
                    }
                }, 600);
            });
            
            // Start timer
            const minutes = <?php echo $test['timer_minutes']; ?>;
            if (minutes > 0) {
                startCountdown(minutes * 60);
            }
            
            // Show progress
            const qp = document.getElementById('quizProgress');
            qp.style.display = 'block';
            updateProgress();
            renderCurrentQuestion();
        });
</script>

<?php
$content = ob_get_clean();
require_once 'Includes/student_layout.php';
?>

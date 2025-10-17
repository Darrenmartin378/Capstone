<?php
require_once 'includes/student_init.php';
require_once 'includes/NewResponseHandler.php';

$responseHandler = new NewResponseHandler($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Suppress all output except JSON
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // Start output buffering to catch any HTML output
    ob_start();
    
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_questions':
                $questionSetId = (int)($_POST['question_set_id']);
                // Enforce open_at schedule using DB NOW() to avoid PHP/MySQL timezone mismatch
                try {
                    $chkOpen = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
                    if ($chkOpen && $chkOpen->num_rows > 0) {
                        $stO = $conn->prepare("SELECT open_at, CASE WHEN open_at IS NOT NULL AND open_at > NOW() THEN 1 ELSE 0 END AS locked FROM question_sets WHERE id = ? LIMIT 1");
                        $stO->bind_param('i', $questionSetId);
                        $stO->execute();
                        $resO = $stO->get_result();
                        if ($resO && $rowO = $resO->fetch_assoc()) {
                            $oa = $rowO['open_at'] ?? null;
                            $locked = (int)($rowO['locked'] ?? 0);
                            if (!empty($oa) && $locked === 1) {
                                $ts = strtotime($oa);
                                echo json_encode(['success' => false, 'error' => 'This quiz opens on ' . date('M j, Y g:ia', $ts)]);
                                exit;
                            }
                        }
                    }
                } catch (Exception $e) { /* ignore */ }
                
                try {
                    $questions = [];
                    
                    // Get MCQ questions
                    $stmt = $conn->prepare("
                        SELECT question_id as id, 'mcq' as type, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, points, order_index
                        FROM mcq_questions WHERE set_id = ? ORDER BY order_index
                    ");
                    $stmt->bind_param('i', $questionSetId);
                    $stmt->execute();
                    $mcqQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    // Get matching questions
                    $stmt = $conn->prepare("
                        SELECT question_id as id, 'matching' as type, question_text, left_items, right_items, correct_pairs, points, order_index
                        FROM matching_questions WHERE set_id = ? ORDER BY order_index
                    ");
                    $stmt->bind_param('i', $questionSetId);
                    $stmt->execute();
                    $matchingQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    // Get essay questions
                    $stmt = $conn->prepare("
                        SELECT question_id as id, 'essay' as type, question_text, points, order_index
                        FROM essay_questions WHERE set_id = ? ORDER BY order_index
                    ");
                    $stmt->bind_param('i', $questionSetId);
                    $stmt->execute();
                    $essayQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    // Combine all questions
                    $questions = array_merge($mcqQuestions, $matchingQuestions, $essayQuestions);
                    
                    // Sort by order_index
                    usort($questions, function($a, $b) {
                        return $a['order_index'] - $b['order_index'];
                    });
                    
                    echo json_encode(['success' => true, 'questions' => $questions]);
                    exit;
                } catch (Exception $e) {
                    error_log('Error loading questions: ' . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => 'Failed to load questions']);
                    exit;
                }
                
            case 'submit_responses':
                $questionSetId = (int)($_POST['question_set_id']);
                $responses = json_decode($_POST['responses'] ?? '{}', true) ?? [];
                
                // Debug logging
                error_log('Received responses: ' . print_r($responses, true));
                
                $result = $responseHandler->submitResponses($_SESSION['student_id'], $questionSetId, $responses);
                
                if ($result) {
                    // Get detailed scoring information
                    $totalScore = $responseHandler->calculateTotalScore($_SESSION['student_id'], $questionSetId);
                    $maxPoints = $responseHandler->getMaxPointsForSet($questionSetId);
                    $breakdown = $responseHandler->getScoringBreakdown($_SESSION['student_id'], $questionSetId);
                    
                    $percentage = $maxPoints > 0 ? round(($totalScore['total_score'] / $maxPoints) * 100, 1) : 0;
                    
                    echo json_encode([
                        'success' => true,
                        'total_score' => $totalScore['total_score'],
                        'max_points' => $maxPoints,
                        'correct_answers' => $totalScore['correct_answers'],
                        'total_questions' => $totalScore['total_questions'],
                        'percentage' => $percentage,
                        'breakdown' => $breakdown
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'You already submitted this quiz']);
                }
                exit;
        }
    } catch (Exception $e) {
        // Clear any output buffer
        ob_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } catch (Error $e) {
        // Clear any output buffer
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
        exit;
    }
    
    // Clean output buffer and send JSON
    ob_end_clean();
}

// Force update section_id from database if not set
if (!isset($_SESSION['section_id']) || $_SESSION['section_id'] <= 0) {
    $studentId = (int)($_SESSION['student_id'] ?? 0);
    if ($studentId > 0) {
        $studentRes = $conn->query("SELECT section_id FROM students WHERE id = $studentId");
        if ($studentRes && $studentRes->num_rows > 0) {
            $student = $studentRes->fetch_assoc();
            $_SESSION['section_id'] = (int)($student['section_id'] ?? 0);
        }
    }
}

// Debug: Log student section and check what question sets exist
error_log('Student section_id: ' . ($_SESSION['section_id'] ?? 'NOT SET'));
error_log('Student session data: ' . print_r($_SESSION, true));

// Determine if optional columns exist (timer/open_at) to avoid SQL errors
$hasTimerCol = false; $hasOpenCol = false;
try {
    $chk = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'timer_minutes'");
    $hasTimerCol = $chk && $chk->num_rows > 0;
} catch (Exception $e) {}
try {
    $chk2 = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
    $hasOpenCol = $chk2 && $chk2->num_rows > 0;
} catch (Exception $e) {}

$hasDiffMcq = false; $hasDiffMatching = false; $hasDiffEssay = false;
try { $chk3 = $conn->query("SHOW COLUMNS FROM mcq_questions LIKE 'difficulty'"); $hasDiffMcq = $chk3 && $chk3->num_rows > 0; } catch (Exception $e) {}
try { $chk4 = $conn->query("SHOW COLUMNS FROM matching_questions LIKE 'difficulty'"); $hasDiffMatching = $chk4 && $chk4->num_rows > 0; } catch (Exception $e) {}
try { $chk5 = $conn->query("SHOW COLUMNS FROM essay_questions LIKE 'difficulty'"); $hasDiffEssay = $chk5 && $chk5->num_rows > 0; } catch (Exception $e) {}

// Get available question sets for the student's section only
if (isset($_SESSION['section_id']) && $_SESSION['section_id'] > 0) {
    $extraSelect = ($hasTimerCol ? "COALESCE(qs.timer_minutes, 0) as timer_minutes," : "0 as timer_minutes,") .
                   ($hasOpenCol ? "qs.open_at," : "NULL as open_at,");
    $sql = "SELECT qs.*, s.name as section_name,
               (SELECT COUNT(*) FROM mcq_questions WHERE set_id = qs.id) +
               (SELECT COUNT(*) FROM matching_questions WHERE set_id = qs.id) +
               (SELECT COUNT(*) FROM essay_questions WHERE set_id = qs.id) as question_count,
               (SELECT COALESCE(SUM(points), 0) FROM mcq_questions WHERE set_id = qs.id) +
               (SELECT COALESCE(SUM(points), 0) FROM matching_questions WHERE set_id = qs.id) +
               (SELECT COALESCE(SUM(points), 0) FROM essay_questions WHERE set_id = qs.id) as total_points,
               " . $extraSelect . "
               1 as _dummy
        FROM question_sets qs
        JOIN sections s ON qs.section_id = s.id
        WHERE qs.section_id = ?
        ORDER BY qs.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $_SESSION['section_id']);
    $stmt->execute();
    $questionSets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // Enrich with student's submission status and score
    $studentId = (int)($_SESSION['student_id'] ?? 0);
    if ($studentId > 0 && !empty($questionSets)) {
        foreach ($questionSets as &$qs) {
            $qs['already_submitted'] = false;
            try {
                $scoreInfo = $responseHandler->calculateTotalScore($studentId, (int)$qs['id']);
                $maxPts = $responseHandler->getMaxPointsForSet((int)$qs['id']);
                if (is_array($scoreInfo) && ($scoreInfo['total_questions'] ?? 0) > 0) {
                    $qs['already_submitted'] = true;
                    $qs['student_score'] = (float)($scoreInfo['total_score'] ?? 0);
                    $qs['max_points'] = (float)$maxPts;
                }
            } catch (Exception $e) { /* ignore */ }
        }
        unset($qs);
    }
    // Compute difficulty label per set if difficulty columns exist
    if (!empty($questionSets) && ($hasDiffMcq || $hasDiffMatching || $hasDiffEssay)) {
        foreach ($questionSets as $idx => $qs) {
            $diffVals = [];
            if ($hasDiffMcq) {
                $st = $conn->prepare("SELECT DISTINCT difficulty FROM mcq_questions WHERE set_id = ? AND COALESCE(difficulty,'')<>''");
                $st->bind_param('i', $qs['id']);
                if ($st->execute()) {
                    $res = $st->get_result();
                    while ($row = $res->fetch_assoc()) { $diffVals[] = strtolower(trim($row['difficulty'])); }
                }
            }
            if ($hasDiffMatching) {
                $st = $conn->prepare("SELECT DISTINCT difficulty FROM matching_questions WHERE set_id = ? AND COALESCE(difficulty,'')<>''");
                $st->bind_param('i', $qs['id']);
                if ($st->execute()) {
                    $res = $st->get_result();
                    while ($row = $res->fetch_assoc()) { $diffVals[] = strtolower(trim($row['difficulty'])); }
                }
            }
            if ($hasDiffEssay) {
                $st = $conn->prepare("SELECT DISTINCT difficulty FROM essay_questions WHERE set_id = ? AND COALESCE(difficulty,'')<>''");
                $st->bind_param('i', $qs['id']);
                if ($st->execute()) {
                    $res = $st->get_result();
                    while ($row = $res->fetch_assoc()) { $diffVals[] = strtolower(trim($row['difficulty'])); }
                }
            }
            $diffVals = array_values(array_unique(array_filter($diffVals)));
            if (count($diffVals) === 1) {
                $questionSets[$idx]['difficulty_label'] = $diffVals[0];
            } elseif (count($diffVals) > 1) {
                $questionSets[$idx]['difficulty_label'] = 'mixed';
} else {
                $questionSets[$idx]['difficulty_label'] = '';
            }
        }
    }
} else {
    // If no section assigned, show no question sets
    $questionSets = [];
}

// Debug: Log the results
error_log('Question sets found: ' . print_r($questionSets, true));

// Also check all question sets regardless of section for debugging
$extraSelectAll = ($hasTimerCol ? "COALESCE(qs.timer_minutes, 0) as timer_minutes," : "0 as timer_minutes,") .
                   ($hasOpenCol ? "qs.open_at," : "NULL as open_at,");
$allSql = "SELECT qs.*, s.name as section_name,
           (SELECT COUNT(*) FROM mcq_questions WHERE set_id = qs.id) +
           (SELECT COUNT(*) FROM matching_questions WHERE set_id = qs.id) +
           (SELECT COUNT(*) FROM essay_questions WHERE set_id = qs.id) as question_count,
           (SELECT COALESCE(SUM(points), 0) FROM mcq_questions WHERE set_id = qs.id) +
           (SELECT COALESCE(SUM(points), 0) FROM matching_questions WHERE set_id = qs.id) +
           (SELECT COALESCE(SUM(points), 0) FROM essay_questions WHERE set_id = qs.id) as total_points,
           " . $extraSelectAll . "
           1 as _dummy
    FROM question_sets qs
    JOIN sections s ON qs.section_id = s.id
    ORDER BY qs.created_at DESC";
$allSetsStmt = $conn->prepare($allSql);
$allSetsStmt->execute();
$allQuestionSets = $allSetsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
error_log('All question sets in database: ' . print_r($allQuestionSets, true));

// Start output buffering to capture content
ob_start();
?>

<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0f;
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
            background: #2563eb;
            border-bottom: 1px solid #1d4ed8;
            padding: 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            height: 70px;

        }
        
        .question-sets {
            margin-bottom: 24px;
        }
        
        .question-sets-table {
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .question-sets-table table {
            width: 100%;
            border-collapse: collapse;
            color: #1e293b;
        }
        
        .question-sets-table th {
            background: #f8fafc;
            color: #1e293b;
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .question-sets-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        
        .question-sets-table tr:hover {
            background: #f8fafc;
        }
        
        .question-sets-table tr:last-child td {
            border-bottom: none;
        }
        
        .table-title {
            font-weight: 700;
            font-size: 16px;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .table-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 12px;
            color: #64748b;
        }
        
        .table-meta .meta-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .table-stats {
            color: #16a34a;
            font-weight: 600;
            font-size: 14px;
        }
        
        .table-actions {
            text-align: center;
        }
        
        .table-actions .btn {
            min-width: 100px;
        }
        
        /* Improve table row hover effects */
        .question-sets-table tr:hover {
            background: rgba(139, 92, 246, 0.1);
            transform: translateX(2px);
            transition: all 0.2s ease;
        }
        
        /* Better spacing for table cells */
        .question-sets-table td:first-child {
            width: 40%;
        }
        
        .question-sets-table td:nth-child(2) {
            width: 20%;
        }
        
        .question-sets-table td:nth-child(3) {
            width: 20%;
        }
        
        .question-sets-table td:last-child {
            width: 20%;
        }
        
        @media (max-width: 768px) {
            .page-shell { padding: 0 12px; }
            .content-header { margin-bottom: 12px; }
            
            .question-sets-table {
                overflow-x: auto;
                border-radius: 12px;
            }
            
            .question-sets-table table {
                min-width: 600px;
            }
            
            .question-sets-table th,
            .question-sets-table td {
                padding: 12px 8px;
                font-size: 13px;
            }
            
            .table-title {
                font-size: 14px;
            }
            
            .table-meta {
                font-size: 11px;
            }
            
            .table-actions .btn {
                min-width: 80px;
                padding: 8px 12px;
                font-size: 12px;
            }
            
            /* Stack meta items vertically on very small screens */
            .table-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
        }
        
        @media (max-width: 480px) {
            .question-sets-table table {
                min-width: 500px;
            }
            
            .question-sets-table th,
            .question-sets-table td {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .table-title {
                font-size: 13px;
            }
            
            .table-meta {
                font-size: 10px;
            }
        }
        
        
        .btn {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.9), rgba(168, 85, 247, 0.8));
            color: white;
            border: 1px solid rgba(139, 92, 246, 0.5);
            padding: 12px 22px;
            border-radius: 9999px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex; align-items:center; gap:8px;
            transition: transform .12s ease, filter .2s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
        }
        
        .btn:hover {
            filter: brightness(1.1); 
            transform: translateY(-2px); 
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.5);
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
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: background .15s ease, transform .08s ease, border-color .15s ease;
            background: #ffffff;
        }
        .option:hover { 
            background: #f0f9ff;
            border-color: #2563eb;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        .option input[type="radio"] { accent-color: #2563eb; }
        .option.selected { 
            border-color: #2563eb; 
            background: #f0f9ff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
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
        .locked-info{display:flex;align-items:center;gap:8px;margin-top:10px;color:#64748b;font-size:12px;background:#f8fafc;border:1px dashed #e2e8f0;padding:6px 10px;border-radius:9999px;width:max-content}
        .badge.starts { background:#e0e7ff; color:#1e40af; }
    </style>
</head>
<body>
    <script>window.serverNowTs = <?php echo time(); ?>; window.pageLoadedAt = Date.now();</script>
    <div class="page-shell" id="pageShell" style="width: 100%; margin: 0; padding: 16px;">
        <div class="content-header" style="width:100%;">
            <h1><i class="fas fa-question-circle"></i> Available Question Sets</h1>
            <p style="margin-top:6px;color:#64748b">Select a question set to start answering</p>
        </div>
        <div class="question-sets">
            <?php if (empty($questionSets)): ?>
                <div class="question-sets-table">
                    <div style="text-align: center; padding: 40px; color: #1e293b;">
                        <h3>No Question Sets Available</h3>
                    </div>
                </div>
            <?php else: ?>
                <div class="question-sets-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Question Set</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questionSets as $set): ?>
                            <?php 
                                $openAt = $set['open_at'] ?? null; 
                                $isLocked = false; 
                                if (!empty($openAt)) { $isLocked = (strtotime($openAt) > time()); }
                                $timer = (int)($set['timer_minutes'] ?? 0);
                            ?>
                            <tr id="set-<?php echo (int)$set['id']; ?>" data-set-id="<?php echo (int)$set['id']; ?>" data-open-at="<?php echo htmlspecialchars($openAt ?? ''); ?>" data-open-ts="<?php echo $openAt ? (int)@strtotime($openAt) : 0; ?>" data-duration="<?php echo max(0,(int)$timer*60); ?>">
                                <td>
                                    <div class="table-title"><?php echo htmlspecialchars($set['set_title']); ?></div>
                                    <div class="table-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-list-ol"></i> <?php echo $set['question_count']; ?> questions
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-star"></i> <?php echo $set['total_points']; ?> points
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($set['section_name']); ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px;">
                                        <?php if($timer>0): ?><span class="badge timer"><?php echo $timer; ?> mins</span><?php endif; ?>
                                        <?php if($timer>0 && !empty($openAt) && strtotime($openAt) <= time()): ?>
                                            <span class="badge timer" data-time-left>Time left —</span>
                                        <?php endif; ?>
                                        <?php if(!empty($openAt)): ?>
                                            <span class="badge open">
                                                <?php if (!empty($set['already_submitted'])): ?>
                                                    Uploaded: <?php echo date('M j, Y g:ia'); ?>
                                                <?php else: ?>
                                                    Opens: <?php echo date('M j, Y g:ia', strtotime($openAt)); ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php 
                                            // Prefer set-level difficulty if present; fallback to legacy computed label
                                            $diff = strtolower(trim($set['difficulty'] ?? ($set['difficulty_label'] ?? '')));
                                            if ($diff) {
                                                $cls = ($diff==='easy')?'diff-easy':(($diff==='hard')?'diff-hard':(($diff==='medium')?'diff-medium':'diff-medium'));
                                                $label = $diff==='mixed' ? 'Mixed' : ucfirst($diff);
                                                echo '<span class="badge '.$cls.'">'.$label.'</span>';
                                            }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($set['already_submitted'])): ?>
                                        <div class="table-stats">
                                            Your Score: <?php echo (float)($set['student_score'] ?? 0); ?> / <?php echo (float)($set['max_points'] ?? 0); ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="color: #64748b; font-size: 14px;">
                                            Ready to start
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($set['already_submitted'])): ?>
                                        <span style="color: #16a34a; font-weight: 600;">Submitted</span>
                                    <?php elseif($isLocked): ?>
                                        <span style="color: #f59e0b; font-weight: 600;">Locked</span>
                                        <div class="locked-info" data-open-at="<?php echo htmlspecialchars($openAt); ?>" style="margin-top: 4px;">
                                            <i class="fas fa-hourglass-half"></i>
                                            <span class="unlock-countdown">Opens in —</span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #10b981; font-weight: 600;">Available</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <?php if (!empty($set['already_submitted'])): ?>
                                        <button class="btn" disabled>
                                            Submitted
                                        </button>
                                    <?php elseif($isLocked): ?>
                                        <button class="btn" disabled title="Opens on <?php echo date('M j, Y g:ia', strtotime($openAt)); ?>" data-set-id="<?php echo (int)$set['id']; ?>" data-title="<?php echo htmlspecialchars($set['set_title']); ?>" data-timer="<?php echo (int)$timer; ?>" data-duration="<?php echo max(0,(int)$timer*60); ?>" data-open-ts="<?php echo $openAt ? (int)@strtotime($openAt) : 0; ?>">
                                            <i class="fas fa-lock"></i> Locked
                                        </button>
                                    <?php else: ?>
                                        <button class="btn" data-set-id="<?php echo (int)$set['id']; ?>" data-timer="<?php echo (int)$timer; ?>" data-open-at="<?php echo htmlspecialchars($openAt ?? ''); ?>" data-open-ts="<?php echo $openAt ? (int)@strtotime($openAt) : 0; ?>" data-duration="<?php echo max(0,(int)$timer*60); ?>" onclick="guardAndStart(this, <?php echo $set['id']; ?>, '<?php echo htmlspecialchars($set['set_title']); ?>')">
                                            <i class="fas fa-play"></i> Start 
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        </div>
        
    <div id="quizShell" class="quiz-shell" tabindex="-1" aria-modal="true" role="dialog">
            <div id="questionForm" class="question-form" style="display: none; position: relative;">
                <button type="button" class="quiz-close" title="Close" onclick="closeQuiz()">×</button>
            <h2 id="formTitle" style="color: Black;"></h2>
                <div class="quiz-progress" id="quizProgress">
                    <div class="progress-meta">
                        <span id="progressLabel"></span>
                        <span id="progressCount">0 / 0</span>
                    </div>
                    <div class="bar"><div class="bar-fill" id="progressFill"></div></div>
                </div>
                <div class="timer-display" id="timerDisplay" aria-live="polite"></div>
                <div class="encourage" id="encourageMsg">Great job! <small>Next one 💪</small></div>
            <div id="questionsContainer">
                    <!-- Single question will be rendered here -->
            </div>
                <div class="nav-actions">
                    <button id="nextBtn" class="btn btn-success btn-disabled" disabled>Next</button>
                </div>
                <div class="loading-overlay" id="loadingOverlay"><div class="spinner"></div></div>
            </div>
        </div>
    </div>

    <script>
        // If URL hash points to a specific set (e.g., #set-12), scroll to and accent it
        (function(){
            function highlightFromHash(){
                if (!location.hash) return;
                var id = location.hash.replace(/^#/, '');
                var el = document.getElementById(id);
                if (!el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.style.boxShadow = '0 0 0 3px rgba(139,92,246,.9), 0 12px 30px rgba(0,0,0,.5)';
                setTimeout(function(){ el.style.boxShadow=''; }, 2000);
            }
            window.addEventListener('hashchange', highlightFromHash);
            document.addEventListener('DOMContentLoaded', highlightFromHash);
        })();
        let currentQuestionSetId = null;
        let currentQuestions = [];
        let currentIndex = 0;
        const studentResponses = {};
        const encourage = [
            'Great job! Next one 💪',
            'Nice progress! Keep it up ✨',
            'You got this! 🚀',
            'Awesome! Continue 👏',
            'Steady pace! Onward ➡️'
        ];
        
        function startQuestionSet(setId, setTitle, timerMinutes, openAt) {
            currentQuestionSetId = setId;
            document.getElementById('formTitle').textContent = setTitle;
            const shell = document.getElementById('quizShell');
            shell.style.display = 'block';
            document.body.style.overflow = '';
            const form = document.getElementById('questionForm');
            form.style.display = 'block';
            const td = document.getElementById('timerDisplay');
            if (td) td.style.display = 'inline-block';
            // Hide header and table for distraction-free quiz
            const header = document.querySelector('.content-header');
            const sets = document.querySelector('.question-sets');
            if (header) header.style.display = 'none';
            if (sets) sets.style.display = 'none';
            // Scroll and focus the container smoothly near the top
            setTimeout(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                shell.focus({ preventScroll: true });
            }, 50);
            
            // Load questions for this set
            loadQuestions(setId);
            // Apply timer immediately if passed
            const minutes = parseInt(timerMinutes || '0');
            if (!isNaN(minutes) && minutes > 0) {
                startCountdown(minutes * 60);
            }
        }

        function guardAndStart(btn, setId, setTitle){
            const openAt = btn ? (btn.getAttribute('data-open-at') || '') : '';
            const openTs = parseInt(btn ? (btn.getAttribute('data-open-ts') || '0') : '0');
            // Use server time to avoid client clock skew
            const nowTs = (window.serverNowTs || Math.floor(Date.now()/1000)) + Math.floor((Date.now() - (window.pageLoadedAt||Date.now()))/1000);
            if (openTs && nowTs < openTs) {
                const openDate = new Date(openTs * 1000);
                alert('This quiz opens on ' + openDate.toLocaleString());
                return;
            }
            if (openAt) {
                const openDate = new Date(openAt.replace(' ', 'T'));
                if (!isNaN(openDate.getTime()) && new Date() < openDate) {
                    alert('This quiz opens on ' + openDate.toLocaleString());
                    return;
                }
            }
            const t = btn ? (btn.getAttribute('data-timer') || '0') : '0';
            startQuestionSet(setId, setTitle, t, openAt);
        }
        
        function loadQuestions(setId) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_questions&question_set_id=' + setId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentQuestions = data.questions || [];
                    currentIndex = 0;
                    // Initialize progress
                    const qp = document.getElementById('quizProgress');
                    qp.style.display = 'block';
                    updateProgress();
                    renderCurrentQuestion();
                    // Timer visibility/start
                    const td = document.getElementById('timerDisplay');
                    if (!timerStarted) {
                        const selectedCard = document.querySelector('.set-card .set-title .badge.timer');
                        let minutes = 0;
                        if (selectedCard) {
                            const m = parseInt(selectedCard.textContent);
                            minutes = isNaN(m) ? 0 : m;
                        }
                        if (minutes > 0) {
                            startCountdown(minutes * 60);
                        }
                    }
                    if (td) td.style.display = timerStarted ? 'inline-block' : td.style.display;
                } else {
                    alert('Error loading questions: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
        
        function updateProgress() {
            const total = currentQuestions.length;
            const idx = Math.min(currentIndex + 1, total);
            document.getElementById('progressLabel').textContent = '';
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
            // Build question number label (range for matching pairs)
            let qLabel = `Q${currentIndex + 1}`;
            if (question.type === 'matching') {
                try {
                    const li = JSON.parse(question.left_items || '[]');
                    const count = Array.isArray(li) ? li.length : 0;
                    if (count > 1) {
                        const start = currentIndex + 1;
                        const end = start + count - 1;
                        qLabel = `Q${start}–Q${end}`;
                    }
                } catch(e) {}
            }
            qDiv.innerHTML = `
                    <div class="question-header">
                    <div class="question-number">${qLabel}</div>
                        <div class="question-points">${question.points} pts</div>
                    </div>
                    <div class="question-text">${question.question_text}</div>
                ${renderQuestionContent(question, currentIndex)}
            `;
            container.appendChild(qDiv);

            // Enable interactions
            if (question.type === 'mcq') {
                qDiv.querySelectorAll('.option input[type="radio"]').forEach(r => {
                    r.addEventListener('change', (e) => {
                        qDiv.querySelectorAll('.option').forEach(op => op.classList.remove('selected'));
                        e.target.closest('.option').classList.add('selected');
                        nextBtn.disabled = false; nextBtn.classList.remove('btn-disabled');
                    });
                });
            }

                if (question.type === 'matching') {
                const draggableItems = qDiv.querySelectorAll('.draggable-item');
                    draggableItems.forEach(item => {
                        item.addEventListener('dragstart', drag);
                        item.addEventListener('dragend', dragEnd);
                    });
                const observerFn = () => {
                    // Enable Next when all drop zones have answers
                    const dz = qDiv.querySelectorAll('.drop-zone');
                    const ready = Array.from(dz).every(z => (z.dataset.answer || '').trim() !== '');
                    if (ready) { nextBtn.disabled = false; nextBtn.classList.remove('btn-disabled'); }
                };
                qDiv.addEventListener('drop', () => setTimeout(observerFn, 50));
                qDiv.addEventListener('click', (e)=>{ if(e.target.classList.contains('clear-btn')) setTimeout(observerFn, 50);});
            }

            if (question.type === 'essay') {
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
            switch (question.type) {
                case 'mcq':
                    // For new schema, choices are stored as separate columns
                    const options = [
                        question.choice_a,
                        question.choice_b, 
                        question.choice_c,
                        question.choice_d
                    ].filter(choice => choice && choice.trim() !== '');
                    
                    return `
                        <div class="question-options">
                            ${options.map((option, optIndex) => `
                                <div class="option">
                                    <input type="radio" name="question_${question.id}" value="${(['A','B','C','D'][optIndex] || String.fromCharCode(65 + optIndex))}" id="q${question.id}_${optIndex}">
                                    <label for="q${question.id}_${optIndex}">${option}</label>
                                </div>
                            `).join('')}
                        </div>
                    `;
                    
                case 'matching':
                    // For new schema, matching data is stored as JSON
                    let leftItems, rightItems, correctPairs;
                    
                    try {
                        leftItems = JSON.parse(question.left_items || '[]');
                        rightItems = JSON.parse(question.right_items || '[]');
                        correctPairs = JSON.parse(question.correct_pairs || '[]');
                        
                        // Debug logging
                        console.log('Matching question data:', {
                            leftItems: leftItems,
                            rightItems: rightItems,
                            correctPairs: correctPairs
                        });
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        leftItems = [];
                        rightItems = [];
                        correctPairs = [];
                    }
                    
                    return `
                        <div class="matching-container">
                            <div class="matching-instructions">
                                <p><strong>Instructions:</strong> Drag the correct answer into each drop zone.</p>
                            </div>
                            <div class="drag-drop-container">
                                <div class="draggable-items">
                                    <h4>Answer Options:</h4>
                                    ${rightItems.map((rightItem, itemIndex) => `
                                        <div class="draggable-item" 
                                             draggable="true" 
                                             data-answer-index="${itemIndex}"
                                             data-question-id="${question.id}"
                                             id="drag_${question.id}_${itemIndex}">
                                            <span class="drag-number">${itemIndex + 1}</span>
                                            <span class="drag-text">${rightItem}</span>
                                        </div>
                                    `).join('')}
                                </div>
                                
                                <div class="drop-zones">
                                    <h4>Drop Zones:</h4>
                                    ${leftItems.map((item, itemIndex) => `
                                        <div class="drop-zone" 
                                             data-pair-index="${itemIndex}"
                                             data-correct-index="${(correctPairs && correctPairs[itemIndex]) ? correctPairs[itemIndex] : ''}"
                                             data-correct-text="${(correctPairs && correctPairs[itemIndex] !== undefined && rightItems[correctPairs[itemIndex]]) ? rightItems[correctPairs[itemIndex]] : ''}"
                                             data-question-id="${question.id}"
                                             id="drop_${question.id}_${itemIndex}"
                                             ondrop="drop(event)" 
                                             ondragover="allowDrop(event)">
                                            <button type="button" class="clear-btn" title="Remove" onclick="clearDropZone(event)">&times;</button>
                                            <div class="drop-placeholder">${item}</div>
                                            <div class="dropped-item" id="dropped_${question.id}_${itemIndex}" style="display: none;"></div>
                                        </div>
                                    `).join('')}
                                </div>
                                
                                <div class="answer-items">
                                    <h4>Left Items:</h4>
                                    ${leftItems.map((item, itemIndex) => `
                                        <div class="answer-item">
                                            <span class="answer-number">${itemIndex + 1}</span>
                                            <span class="answer-text">${item}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            
                            <div class="matching-score" id="matching_score_${question.id}" style="display:none;">
                                <span class="score-text">Score: 0/${leftItems.length}</span>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                case 'essay':
                    return `
                        <textarea class="essay-textarea" name="question_${question.id}" placeholder="Enter your answer here..."></textarea>
                    `;
                    
                default:
                    return '<p>Unknown question type</p>';
            }
        }
        
        // Drag and Drop Functions
        function allowDrop(ev) {
            ev.preventDefault();
            ev.currentTarget.classList.add('drag-over');
        }
        
        function drag(ev) {
            ev.dataTransfer.setData("text", ev.target.id);
            ev.target.classList.add('dragging');
        }
        
        function drop(ev) {
            ev.preventDefault();
            ev.currentTarget.classList.remove('drag-over');
            
            const data = ev.dataTransfer.getData("text");
            const draggedElement = document.getElementById(data);
            const dropZone = ev.currentTarget;
            
            // Get question ID and pair index
            const questionId = dropZone.dataset.questionId;
            const pairIndex = dropZone.dataset.pairIndex;
            const correctIndex = dropZone.dataset.correctIndex;
            const correctText = dropZone.dataset.correctText;
            
            // Get the dragged item's data
            const draggedText = draggedElement.querySelector('.drag-text').textContent;
            const draggedNumber = draggedElement.querySelector('.drag-number').textContent;
            const draggedIndex = draggedElement.dataset.answerIndex;
            
            // Check if this is the correct match (compare indices)
            const isCorrect = draggedIndex === correctIndex;
            
            // Debug logging
            console.log('Drop comparison:', {
                draggedText: draggedText,
                draggedIndex: draggedIndex,
                correctIndex: correctIndex,
                correctText: correctText,
                isCorrect: isCorrect,
                pairIndex: pairIndex
            });
            
            // Update drop zone
            const placeholder = dropZone.querySelector('.drop-placeholder');
            const droppedItem = dropZone.querySelector('.dropped-item');
            
            placeholder.style.display = 'none';
            droppedItem.style.display = 'flex';
            droppedItem.innerHTML = `
                <span class="drag-number">${draggedNumber}</span>
                <span class="drag-text">${draggedText}</span>
            `;
            
            // If this drop zone already had an answer, un-hide the previous draggable
            if (dropZone.dataset.dragId) {
                const prevDrag = document.getElementById(dropZone.dataset.dragId);
                if (prevDrag) prevDrag.style.display = '';
            }

            // Store the answer index and reference to original draggable element
            dropZone.dataset.answer = draggedIndex;
            dropZone.dataset.dragId = data;
            dropZone.classList.add('has-answer');
            
            // Visual feedback
            dropZone.classList.remove('correct', 'incorrect');
            if (isCorrect) {
                dropZone.classList.add('correct');
            } else {
                dropZone.classList.add('incorrect');
            }
            
            // Hide the dragged element
            draggedElement.style.display = 'none';
            
            // Update score
            updateDragDropScore(questionId);
        }

        function clearDropZone(ev) {
            ev.stopPropagation();
            const dropZone = ev.currentTarget.closest('.drop-zone');
            const droppedItem = dropZone.querySelector('.dropped-item');
            const placeholder = dropZone.querySelector('.drop-placeholder');
            // Reset
            // Unhide the original draggable item in the left column
            if (dropZone.dataset.dragId) {
                const dragEl = document.getElementById(dropZone.dataset.dragId);
                if (dragEl) dragEl.style.display = '';
            }
            dropZone.dataset.answer = '';
            dropZone.dataset.dragId = '';
            dropZone.classList.remove('correct', 'incorrect', 'has-answer');
            droppedItem.style.display = 'none';
            placeholder.style.display = 'block';
            // Recompute score
            const questionId = dropZone.dataset.questionId;
            updateDragDropScore(questionId);
        }
        
        function dragEnd(ev) {
            ev.target.classList.remove('dragging');
        }
        
        function updateDragDropScore(questionId) {
            const dropZones = document.querySelectorAll(`[data-question-id="${questionId}"].drop-zone`);
            let correctCount = 0;
            let totalCount = dropZones.length;
            
            dropZones.forEach(zone => {
                const answer = zone.dataset.answer;
                const correct = zone.dataset.correctIndex;
                if (answer && answer === correct) {
                    correctCount++;
                }
            });
            
            const scoreElement = document.getElementById(`matching_score_${questionId}`);
            const scoreText = scoreElement.querySelector('.score-text');
            const progressFill = scoreElement.querySelector('.progress-fill');
            
            scoreText.textContent = `Score: ${correctCount}/${totalCount}`;
            const percentage = (correctCount / totalCount) * 100;
            progressFill.style.width = `${percentage}%`;
            
            // Change progress bar color based on score
            if (percentage === 100) {
                progressFill.style.background = '#28a745'; // Green for perfect
            } else if (percentage >= 50) {
                progressFill.style.background = '#ffc107'; // Yellow for partial
            } else {
                progressFill.style.background = '#dc3545'; // Red for low score
            }
        }
        
        function updateMatchingScore(questionId) {
            const selects = document.querySelectorAll(`select[name^="question_${questionId}_"]`);
            let correctCount = 0;
            let totalCount = selects.length;
            
            selects.forEach(select => {
                if (select.value === select.dataset.correct) {
                    correctCount++;
                }
            });
            
            const scoreElement = document.getElementById(`matching_score_${questionId}`);
            const scoreText = scoreElement.querySelector('.score-text');
            const progressFill = scoreElement.querySelector('.progress-fill');
            
            scoreText.textContent = `Score: ${correctCount}/${totalCount}`;
            const percentage = (correctCount / totalCount) * 100;
            progressFill.style.width = `${percentage}%`;
            
            // Change progress bar color based on score
            if (percentage === 100) {
                progressFill.style.background = '#28a745'; // Green for perfect
            } else if (percentage >= 50) {
                progressFill.style.background = '#ffc107'; // Yellow for partial
            } else {
                progressFill.style.background = '#dc3545'; // Red for low score
            }
        }
        
        function collectCurrentAnswer() {
            const question = currentQuestions[currentIndex];
            if (!question) return;
                if (question.type === 'matching') {
                    const matchingResponses = [];
                    const dropZones = document.querySelectorAll(`[data-question-id="${question.id}"].drop-zone`);
                    
                    // Sort drop zones by pair index to ensure correct order
                    const sortedZones = Array.from(dropZones).sort((a, b) => {
                        return parseInt(a.dataset.pairIndex) - parseInt(b.dataset.pairIndex);
                    });
                    
                    sortedZones.forEach(zone => {
                        // Get the index of the dropped item, not the text
                        const droppedItem = zone.querySelector('.dropped-item');
                        let answerIndex = '';
                        if (droppedItem && droppedItem.style.display !== 'none') {
                            // Find the original draggable element to get its index
                            const dragId = zone.dataset.dragId;
                            if (dragId) {
                                const dragElement = document.getElementById(dragId);
                                if (dragElement) {
                                    answerIndex = dragElement.dataset.answerIndex || '';
                                }
                            }
                        }
                        matchingResponses.push(answerIndex);
                    });
                    
                    // Debug logging
                    console.log('Collecting matching responses for question', question.id, ':', matchingResponses);
                studentResponses[question.id] = matchingResponses;
                } else {
                    const response = document.querySelector(`input[name="question_${question.id}"]:checked, textarea[name="question_${question.id}"]`);
                if (response) studentResponses[question.id] = response.value;
                    }
                }

        function submitResponses() {
            // build from collected studentResponses (already gathered step-by-step)
            const responses = { ...studentResponses };
            
            // Debug logging
            console.log('Submitting responses:', responses);
            
            if (Object.keys(responses).length === 0) {
                alert('Please answer at least one question!');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'submit_responses');
            formData.append('question_set_id', currentQuestionSetId);
            formData.append('responses', JSON.stringify(responses));
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // cache last score for card update
                    window.lastScoreData = data;
                    // Show detailed scoring results
                    showScoringResults(data);
                    stopCountdown();
                    // Do not close quiz immediately; allow user to read results and go back
                    // Disable re-submission after success
                    const submitBtn = document.querySelector('.submit-btn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Submitted';
                    }
                } else {
                    if (data.error && data.error.includes('already')) {
                        alert('You have already submitted this quiz.');
                    } else {
                        alert('Error: ' + data.error);
                    }
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        // Simple countdown timer helpers
        let countdownInterval = null;
        let timerStarted = false;
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
                    submitResponses();
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

        function closeQuiz() {
            const shell = document.getElementById('quizShell');
            const form = document.getElementById('questionForm');
            shell.style.display = 'none';
            if (form) form.style.display = 'none';
            document.body.style.overflow = '';
            // Restore header and table when quiz closes
            const header = document.querySelector('.content-header');
            const sets = document.querySelector('.question-sets');
            if (header) header.style.display = '';
            if (sets) sets.style.display = '';
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
                        submitResponses();
                    }
                }, 600);
            });
            // Initialize lock countdowns on cards and enforce client locks
            initLockCountdowns();
            applyClientLocks();
            bindStartButtons();
            refreshWhenOpen();
            initHeaderTimers();
        });

        function initLockCountdowns(){
            const els = document.querySelectorAll('.locked-info');
            els.forEach(el => {
                const openAtStr = el.getAttribute('data-open-at');
                if (!openAtStr) return;
                const target = new Date(openAtStr.replace(' ', 'T'));
                const label = el.querySelector('.unlock-countdown');
                function render(){
                    const now = new Date();
                    let diff = Math.max(0, Math.floor((target - now)/1000));
                    const d = Math.floor(diff / 86400); diff %= 86400;
                    const h = Math.floor(diff / 3600); diff %= 3600;
                    const m = Math.floor(diff / 60); const s = diff % 60;
                    if (d>0){ label.textContent = `Opens in ${d}d ${h}h ${m}m`; }
                    else if (h>0){ label.textContent = `Opens in ${h}h ${m}m ${s}s`; }
                    else if (m>0){ label.textContent = `Opens in ${m}m ${s}s`; }
                    else { label.textContent = `Opens in ${s}s`; }
                }
                render();
                const itv = setInterval(() => {
                    const now = new Date();
                    if (now >= target) {
                        clearInterval(itv);
                        // Swap locked button to Start Quiz
                        const row = el.closest('tr');
                        if (row) {
                            const lockedBtn = row.querySelector('button[disabled][data-set-id]');
                            if (lockedBtn) {
                                const setId = parseInt(lockedBtn.getAttribute('data-set-id'));
                                const setTitle = lockedBtn.getAttribute('data-title') || 'Question Set';
                                const timer = parseInt(lockedBtn.getAttribute('data-timer') || '0');
                                const startBtn = document.createElement('button');
                                startBtn.className = 'btn';
                                startBtn.innerHTML = '<i class="fas fa-play"></i> Start Quiz';
                                startBtn.addEventListener('click', () => startQuestionSet(setId, setTitle, timer));
                                lockedBtn.replaceWith(startBtn);
                            }
                        }
                        el.remove();
                        return;
                    }
                    render();
                }, 1000);
            });
        }

        function applyClientLocks(){
            const rows = document.querySelectorAll('tr[data-set-id]');
            rows.forEach(row => {
                const openAtStr = row.getAttribute('data-open-at') || '';
                if (!openAtStr) return;
                const target = new Date(openAtStr.replace(' ', 'T'));
                if (isNaN(target.getTime())) return;
                if (new Date() < target){
                    // Find Start button and replace with Locked if not already
                    const btn = row.querySelector('.btn[data-open-at]');
                    if (btn && !btn.disabled){
                        const locked = document.createElement('button');
                        locked.className = 'btn';
                        locked.disabled = true;
                        locked.title = 'Opens on ' + target.toLocaleString();
                        locked.innerHTML = '<i class="fas fa-lock"></i> Locked';
                        btn.replaceWith(locked);
                        // Add countdown chip if not present
                        if (!row.querySelector('.locked-info')){
                            const chip = document.createElement('div');
                            chip.className = 'locked-info';
                            chip.setAttribute('data-open-at', openAtStr);
                            chip.innerHTML = '<i class="fas fa-hourglass-half"></i> <span class="unlock-countdown">Opens in —</span>';
                            // Add to the status cell
                            const statusCell = row.querySelector('td:nth-child(3)');
                            if (statusCell) {
                                statusCell.appendChild(chip);
                            }
                        }
                    }
                }
            });
            // Start/update countdowns for chips we may have added
            initLockCountdowns();
        }

        function bindStartButtons(){
            const starts = document.querySelectorAll('tr[data-set-id] .btn[data-open-at]');
            starts.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const openAt = btn.getAttribute('data-open-at') || '';
                    const timer = btn.getAttribute('data-timer') || '0';
                    const row = btn.closest('tr[data-set-id]');
                    const title = (row && row.querySelector('.table-title')) ? row.querySelector('.table-title').textContent.trim() : 'Question Set';
                    const idMatch = btn.getAttribute('onclick');
                    // If onclick was already replaced, we still route through guard
                    guardAndStart(btn, parseInt(btn.getAttribute('data-set-id') || '0') || extractId(idMatch), title);
                }, { passive: false });
            });
        }
        function extractId(str){
            if (!str) return 0;
            const m = str.match(/\((\d+)/);
            return m ? parseInt(m[1]) : 0;
        }

        function refreshWhenOpen(){
            // Auto refresh card state every second to flip Locked -> Start when open
            setInterval(() => {
                applyClientLocks();
            }, 1000);
        }

        // Header timers: continue counting down even before quiz starts
        function initHeaderTimers(){
            const rows = document.querySelectorAll('tr[data-set-id]');
            rows.forEach(row => {
                const openTs = parseInt(row.getAttribute('data-open-ts') || '0');
                const duration = parseInt(row.getAttribute('data-duration') || '0');
                const badge = row.querySelector('[data-time-left]');
                if (!badge || !duration) return;
                const tick = () => {
                    const now = Math.floor(Date.now()/1000);
                    const startTs = openTs && now < openTs ? openTs : Math.min(openTs || now, now); // if not open use openTs
                    let timeLeft = duration - Math.max(0, now - startTs);
                    if (timeLeft < 0) timeLeft = 0;
                    const m = Math.floor(timeLeft / 60); const s = timeLeft % 60;
                    badge.textContent = `Time left ${m}:${String(s).padStart(2,'0')}`;
                };
                tick();
                setInterval(tick, 1000);
            });
        }
        
        function showScoringResults(data) {
            const modal = document.createElement('div');
            modal.className = 'scoring-modal';
            modal.innerHTML = `
                <div class="scoring-content">
                    <div class="scoring-header">
                        <h2><i class="fas fa-trophy"></i> Quiz Results</h2>
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
                            </div>
                        </div>
                        
                        
                        <div class="scoring-actions">
                            <button onclick="handleBackToSets()" class="btn">
                                <i class="fas fa-home"></i> Back to Question Sets
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
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 1000;
                }
                .scoring-content {
                    background: white;
                    border-radius: 10px;
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                }
                .scoring-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px;
                    border-bottom: 1px solid #eee;
                }
                .scoring-header h2 {
                    margin: 0;
                    color: #333;
                }
                .close-btn {
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #999;
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
                .score-breakdown {
                    margin: 20px 0;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 5px;
                }
                .score-breakdown h4 {
                    margin: 0 0 15px 0;
                    color: #333;
                }
                .breakdown-item {
                    display: flex;
                    justify-content: space-between;
                    margin: 8px 0;
                    padding: 5px 0;
                    border-bottom: 1px solid #eee;
                }
                .scoring-actions {
                    text-align: center;
                    margin-top: 20px;
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(modal);
        }
        function handleBackToSets(){
            // Close modal
            const modal = document.querySelector('.scoring-modal');
            if (modal) modal.remove();
            // Restore list
            const header = document.querySelector('.content-header');
            const sets = document.querySelector('.question-sets');
            if (header) header.style.display = '';
            if (sets) sets.style.display = '';
            // Update table row with score
            if (window.currentQuestionSetId && window.lastScoreData){
                const row = document.querySelector(`tr[data-set-id="${window.currentQuestionSetId}"]`);
                if (row){
                    // Update the details cell with score
                    const detailsCell = row.querySelector('td:nth-child(2)');
                    if (detailsCell) {
                        detailsCell.innerHTML = `<div class="table-stats">Your Score: ${window.lastScoreData.total_score || 0} / ${window.lastScoreData.max_points || 0}</div>`;
                    }
                    // Update status
                    const statusCell = row.querySelector('td:nth-child(3)');
                    if (statusCell) {
                        statusCell.innerHTML = '<span style="color: #16a34a; font-weight: 600;">Submitted</span>';
                    }
                    // Update action button
                    const actionCell = row.querySelector('td:nth-child(4)');
                    if (actionCell) {
                        const btn = actionCell.querySelector('button');
                        if (btn) {
                            btn.disabled = true;
                            btn.textContent = 'Submitted';
                        }
                    }
                }
            }
            // Hide quiz form
            closeQuiz();
            window.scrollTo({top:0, behavior:'smooth'});
        }
    </script>
    </div>
</body>
</html>
<?php
$content = ob_get_clean();
require_once 'includes/student_layout.php';
?>

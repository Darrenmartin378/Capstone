<?php
require_once 'includes/teacher_init.php';
require_once 'includes/QuestionHandler.php';

try {
    $questionHandler = new QuestionHandler($conn);
} catch (Exception $e) {
    error_log('Failed to create QuestionHandler: ' . $e->getMessage());
    die('Failed to initialize question handler');
}
$flash = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Disable error display for AJAX requests to prevent HTML output
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // Start output buffering to prevent stray HTML before JSON
    if (!ob_get_level()) { ob_start(); }
    header('Content-Type: application/json');
    // Clear any buffered output from includes before emitting JSON
    if (ob_get_length()) { ob_clean(); }
    
    try {
        switch ($_POST['action']) {
            case 'create_question':
                error_log('Create question request received');
                error_log('POST data: ' . print_r($_POST, true));
                
                $sectionId = (int)($_POST['section_id'] ?? 0);
                if ($sectionId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Please select a section']);
                    exit;
                }
                
                if (!isset($_SESSION['teacher_id']) || $_SESSION['teacher_id'] <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Teacher session not found']);
                    exit;
                }
                
                // Create new question set (supports multi-section via section_ids[])
                $setTitle = $_POST['set_title'] ?? '';
                if (empty($setTitle)) {
                    echo json_encode(['success' => false, 'error' => 'Please enter a question set title']);
                    exit;
                }
                
                // Resolve sections
                $sectionIds = [];
                if (!empty($_POST['section_ids']) && is_array($_POST['section_ids'])) {
                    foreach ($_POST['section_ids'] as $sid) { $sid = (int)$sid; if ($sid>0) $sectionIds[] = $sid; }
                    $sectionIds = array_values(array_unique($sectionIds));
                }
                if (empty($sectionIds)) { $one = (int)($_POST['section_id'] ?? 0); if ($one>0) $sectionIds = [$one]; }
                if (empty($sectionIds)) {
                    echo json_encode(['success' => false, 'error' => 'Please select at least one section']);
                    exit;
                }
                $sectionId = $sectionIds[0];

                // Get or create question set
                $setId = $questionHandler->getOrCreateQuestionSet($_SESSION['teacher_id'], $sectionId, $setTitle);
                if (!$setId) {
                    echo json_encode(['success' => false, 'error' => 'Failed to create question set']);
                    exit;
                }
                
                error_log('Creating question with sectionId: ' . $sectionId . ', setId: ' . $setId);
                
                // Optionally persist set timer/open_at if columns exist
                $timerMinutes = isset($_POST['set_timer']) ? (int)$_POST['set_timer'] : 0;
                $openAtRaw = trim($_POST['set_open_at'] ?? '');
                $openAt = $openAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($openAtRaw)) : null;
                try {
                    // Check columns
                    $hasTimerCol = false; $hasOpenCol = false; $hasDiffCol = false;
                    $r1 = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'timer_minutes'");
                    $hasTimerCol = $r1 && $r1->num_rows > 0;
                    $r2 = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
                    $hasOpenCol = $r2 && $r2->num_rows > 0;
                    $r3 = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'difficulty'");
                    $hasDiffCol = $r3 && $r3->num_rows > 0;
                    if ($hasTimerCol || $hasOpenCol || $hasDiffCol) {
                        $sqlU = "UPDATE question_sets SET ";
                        $fields = [];
                        $types = '';
                        $vals = [];
                        if ($hasTimerCol) { $fields[] = "timer_minutes = ?"; $types .= 'i'; $vals[] = $timerMinutes; }
                        if ($hasOpenCol) { $fields[] = "open_at = ?"; $types .= 's'; $vals[] = $openAt; }
                        if ($hasDiffCol) { $fields[] = "difficulty = ?"; $types .= 's'; $vals[] = (string)($_POST['set_difficulty'] ?? ''); }
                        $sqlU .= implode(', ', $fields) . " WHERE id = ?";
                        $types .= 'i';
                        $vals[] = $setId;
                        $stmtU = $conn->prepare($sqlU);
                        if ($stmtU) {
                            $stmtU->bind_param($types, ...$vals);
                            $stmtU->execute();
                        }
                    }
                } catch (Exception $e) { /* ignore */ }

                // Handle multiple questions
                $questions = [];
                $questionIndex = 0;
                
                // Collect all questions from the form
                while (isset($_POST["questions"][$questionIndex])) {
                    $questionData = $_POST["questions"][$questionIndex];
                    $questions[] = $questionData;
                    $questionIndex++;
                }
                
                // If no questions found in array format, try single question format
                if (empty($questions)) {
                    $questionData = [
                        'type' => $_POST['type'] ?? '',
                        'question_text' => $_POST['question_text'] ?? '',
                        'points' => (int)($_POST['points'] ?? 1)
                    ];
                    
                    // Add question type specific data
                    if ($questionData['type'] === 'mcq') {
                        $questionData['choice_a'] = $_POST['choice_a'] ?? '';
                        $questionData['choice_b'] = $_POST['choice_b'] ?? '';
                        $questionData['choice_c'] = $_POST['choice_c'] ?? '';
                        $questionData['choice_d'] = $_POST['choice_d'] ?? '';
                        $questionData['correct_answer'] = $_POST['correct_answer'] ?? '';
                    } elseif ($questionData['type'] === 'matching') {
                        $questionData['left_items'] = json_decode($_POST['left_items'] ?? '[]', true);
                        $questionData['right_items'] = json_decode($_POST['right_items'] ?? '[]', true);
                        $questionData['correct_pairs'] = json_decode($_POST['correct_pairs'] ?? '[]', true);
                    }
                    
                    if (!empty($questionData['type']) && !empty($questionData['question_text'])) {
                        $questions[] = $questionData;
                    }
                }
                
                if (empty($questions)) {
                    echo json_encode(['success' => false, 'error' => 'Please add at least one question']);
                    exit;
                }
                
                $successCount = 0;
                $errorCount = 0;
                $results = [];
                
                foreach ($questions as $questionData) {
                    $result = $questionHandler->createQuestion(
                        $_SESSION['teacher_id'],
                        $sectionId,
                        $setId,
                        $questionData
                    );
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                    
                    $results[] = $result;
                }
                
                if ($successCount > 0) {
                    // Duplicate to other selected sections
                    if (count($sectionIds) > 1) {
                        foreach (array_slice($sectionIds, 1) as $sid) {
                            $dupSetId = $questionHandler->getOrCreateQuestionSet($_SESSION['teacher_id'], $sid, $setTitle);
                            if ($dupSetId) {
                                // Copy set-level fields
                                try {
                                    $hasTimerCol = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'timer_minutes'");
                                    $hasOpenCol  = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
                                    $hasDiffCol  = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'difficulty'");
                                    $fields = []; $types = ''; $vals = [];
                                    if ($hasTimerCol && $hasTimerCol->num_rows>0) { $fields[] = 'timer_minutes = ?'; $types.='i'; $vals[] = $timerMinutes; }
                                    if ($hasOpenCol && $hasOpenCol->num_rows>0) { $fields[] = 'open_at = ?'; $types.='s'; $vals[] = $openAt; }
                                    if ($hasDiffCol && $hasDiffCol->num_rows>0) { $fields[] = 'difficulty = ?'; $types.='s'; $vals[] = (string)($_POST['set_difficulty'] ?? ''); }
                                    if (!empty($fields)) {
                                        $sql = 'UPDATE question_sets SET '.implode(', ', $fields).' WHERE id = ?';
                                        $types.='i'; $vals[] = $dupSetId;
                                        $st = $conn->prepare($sql); if ($st) { $st->bind_param($types, ...$vals); $st->execute(); }
                                    }
                                } catch (Exception $e) { /* ignore */ }
                                foreach ($questions as $qd) {
                                    $r = $questionHandler->createQuestion($_SESSION['teacher_id'], $sid, $dupSetId, $qd);
                                    if ($r['success']) { $successCount++; } else { $errorCount++; }
                                    $results[] = $r;
                                }
                            }
                        }
                    }
                    echo json_encode([
                        'success' => true, 
                        'message' => "Successfully created {$successCount} question(s)",
                        'success_count' => $successCount,
                        'error_count' => $errorCount
                    ]);
                } else {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Failed to create any questions'
                    ]);
                }
                exit;
                
            case 'get_questions':
                $setId = (int)($_POST['set_id'] ?? 0);
                if ($setId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid set']);
                    exit;
                }
                $questions = $questionHandler->getQuestionsForSet($setId);
                echo json_encode(['success' => true, 'questions' => $questions]);
                exit;
            case 'get_set_questions':
                try {
                    $setId = (int)$_POST['set_id'];
                    if ($setId <= 0) {
                        echo json_encode(['success' => false, 'error' => 'Invalid set ID']);
                        exit;
                    }
                    
                    $questions = $questionHandler->getQuestionsForSet($setId);
                    $sectionId = method_exists($questionHandler, 'getSetSectionId') ? $questionHandler->getSetSectionId($setId) : null;
                    $setTitle = method_exists($questionHandler, 'getSetTitle') ? $questionHandler->getSetTitle($setId) : '';
                    // Optional timer/open_at fetch (columns may not exist)
                    $timerMinutes = null; $openAt = null; $setDifficulty = '';
                    try {
                        $hasTimer = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'timer_minutes'");
                        $hasOpen = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
                        $hasDiff  = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'difficulty'");
                        $need = ($hasTimer && $hasTimer->num_rows > 0) || ($hasOpen && $hasOpen->num_rows > 0) || ($hasDiff && $hasDiff->num_rows>0);
                        if ($need) {
                            $stmt = $conn->prepare("SELECT ".(($hasTimer && $hasTimer->num_rows>0)?"timer_minutes":"NULL")." AS timer_minutes, ".(($hasOpen && $hasOpen->num_rows>0)?"open_at":"NULL")." AS open_at, ".(($hasDiff && $hasDiff->num_rows>0)?"difficulty":"NULL")." AS difficulty FROM question_sets WHERE id = ? LIMIT 1");
                            $stmt->bind_param('i', $setId);
                            $stmt->execute();
                            $row = $stmt->get_result()->fetch_assoc();
                            if ($row) { $timerMinutes = $row['timer_minutes']; $openAt = $row['open_at']; $setDifficulty = $row['difficulty'] ?? ''; }
                        }
                    } catch (Exception $e) { /* ignore */ }
                    
                    echo json_encode([
                        'success' => true,
                        'questions' => $questions,
                        'set_title' => $setTitle,
                        'section_id' => $sectionId,
                        'timer_minutes' => $timerMinutes,
                        'open_at' => $openAt,
                        'difficulty' => $setDifficulty
                    ]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
            case 'update_question_set':
                // Handle updates: delete specified questions, update existing, add new
                $setId = (int)$_POST['set_id'];
                $newTitle = $_POST['set_title'];
                $questions = $_POST['questions']; // array of questions with id for existing, no id for new, delete flag for deletion
                // Save timer/open_at if columns exist
                try {
                    $hasTimer = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'timer_minutes'");
                    $hasOpen = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
                    $timer = isset($_POST['set_timer']) ? (int)$_POST['set_timer'] : null;
                    $openRaw = trim($_POST['set_open_at'] ?? '');
                    $openAt = $openRaw !== '' ? date('Y-m-d H:i:s', strtotime($openRaw)) : null;
                    if (($hasTimer && $hasTimer->num_rows>0) || ($hasOpen && $hasOpen->num_rows>0)) {
                        $fields = []; $types = ''; $vals = [];
                        if ($hasTimer && $hasTimer->num_rows>0) { $fields[] = 'timer_minutes = ?'; $types .= 'i'; $vals[] = $timer; }
                        if ($hasOpen && $hasOpen->num_rows>0) { $fields[] = 'open_at = ?'; $types .= 's'; $vals[] = $openAt; }
                        if (!empty($fields)) {
                            $sql = 'UPDATE question_sets SET '.implode(', ', $fields).' WHERE id = ?';
                            $types .= 'i'; $vals[] = $setId;
                            $st = $conn->prepare($sql);
                            if ($st) { $st->bind_param($types, ...$vals); $st->execute(); }
                        }
                    }
                } catch (Exception $e) { /* ignore */ }
                $result = $questionHandler->updateQuestionSet($setId, $newTitle, $questions);
                echo json_encode($result);
                exit;
            case 'delete_question_set':
                $setId = (int)$_POST['set_id'];
                $result = $questionHandler->deleteQuestionSet($setId);
                echo json_encode($result);
                exit;
            case 'check_set_title':
                $sectionId = (int)($_POST['section_id'] ?? 0);
                $setTitle = trim($_POST['set_title'] ?? '');
                $excludeId = (int)($_POST['exclude_set_id'] ?? 0);
                if ($sectionId <= 0 || $setTitle === '') {
                    echo json_encode(['success' => true, 'exists' => false]);
                    exit;
                }
                // Check if title already exists for this teacher+section (excluding current set when editing)
                $sql = "SELECT id FROM question_sets WHERE teacher_id = ? AND section_id = ? AND set_title = ?";
                if ($excludeId > 0) { $sql .= " AND id <> ?"; }
                $stmt = $conn->prepare($sql);
                if ($excludeId > 0) {
                    $stmt->bind_param('iisi', $_SESSION['teacher_id'], $sectionId, $setTitle, $excludeId);
                } else {
                    $stmt->bind_param('iis', $_SESSION['teacher_id'], $sectionId, $setTitle);
                }
                $stmt->execute();
                $res = $stmt->get_result();
                $exists = $res && $res->num_rows > 0;
                echo json_encode(['success' => true, 'exists' => $exists]);
                exit;
        }
    } catch (Exception $e) {
        // Ensure only JSON is emitted; clear any prior buffer content
        if (ob_get_length()) { ob_clean(); }
        error_log('Question creation error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An error occurred while creating the question']);
        exit;
    } catch (Error $e) {
        if (ob_get_length()) { ob_clean(); }
        error_log('Question creation fatal error: ' . $e->getMessage());
        error_log('Fatal error file: ' . $e->getFile() . ' line: ' . $e->getLine());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
        exit;
    }
}

// Get question sets for all teacher sections
$questionSets = [];
foreach ($teacherSections as $section) {
    $sets = $questionHandler->getQuestionSets($_SESSION['teacher_id'], $section['id']);
    foreach ($sets as $set) {
        $set['section_name'] = $section['section_name'] ?: $section['name'];
        $questionSets[] = $set;
    }
}

// Include the teacher layout
require_once 'includes/teacher_layout.php';
render_teacher_header('clean_question_creator.php', $teacherName, 'Question Creator');
?>

<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .question-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #4285f4;
        }
        
        .question-type-section {
            display: none !important;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .question-type-section.active {
            display: block !important;
        }
        .invalid {
            border-color: #ef4444 !important;
            background: #fff7f7;
        }
        .error-text {
            color: #ef4444;
            font-size: 12px;
            margin-top: 4px;
        }
        
        .option-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .option-group input {
            flex: 1;
            margin-right: 10px;
        }
        
        .option-group button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .add-option {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .add-option:hover {
            background: #218838;
        }
        
        .input-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .input-group label {
            min-width: 80px;
            margin: 0;
        }
        
        .input-group input {
            flex: 1;
            margin: 0;
        }
        
        .remove-option {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            min-width: 35px;
        }
        
        .remove-option:hover {
            background: #c82333;
        }
        
        .btn {
            background: #4285f4;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #3367d6;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-primary {
            background: #28a745;
            font-weight: bold;
        }
        
        .btn-primary:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .btn-primary:focus {
            outline: 2px solid #28a745;
            outline-offset: 2px;
        }
        
        /* Ensure button is clickable */
        button[type="submit"] {
            pointer-events: auto !important;
            z-index: 10;
            position: relative;
        }
        
        .question-sets {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .question-bank-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .question-bank-header h2 {
            margin: 0;
            color: #1f2937;
            font-size: 24px;
            font-weight: 600;
        }
        
        .section-filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-filter label {
            font-weight: 500;
            color: #374151;
            font-size: 14px;
            margin: 0;
        }
        
        .section-filter select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            font-size: 14px;
            color: #374151;
            cursor: pointer;
            transition: border-color 0.2s ease;
            min-width: 150px;
        }
        
        .section-filter select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .section-filter select:hover {
            border-color: #9ca3af;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        .question-bank-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .question-bank-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .question-bank-table th {
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .question-bank-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s ease;
        }
        
        .question-bank-table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .question-bank-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .question-bank-table td {
            padding: 16px 12px;
            vertical-align: middle;
        }
        
        .set-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 15px;
        }
        
        .section-name {
            color: #6b7280;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            min-width: 24px;
            text-align: center;
        }
        
        .points-badge {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            min-width: 24px;
            text-align: center;
        }
        
        .created-date {
            text-align: center;
        }
        
        .date-text {
            display: block;
            color: #374151;
            font-size: 13px;
            font-weight: 500;
        }
        
        .time-text {
            display: block;
            color: #9ca3af;
            font-size: 11px;
            margin-top: 2px;
        }
        
        .actions {
            text-align: center;
        }
        
        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: center;
        }
        
        .action-buttons .btn {
            padding: 8px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
        }
        
        .action-buttons .btn-view {
            background: #3b82f6;
            color: white;
        }
        
        .action-buttons .btn-view:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .action-buttons .btn-edit {
            background: #f59e0b;
            color: white;
        }
        
        .action-buttons .btn-edit:hover {
            background: #d97706;
            transform: translateY(-1px);
        }
        
        .action-buttons .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .action-buttons .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .question-bank-table {
                font-size: 12px;
            }
            
            .question-bank-table th,
            .question-bank-table td {
                padding: 8px 6px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
            
            .action-buttons .btn {
                min-width: 28px;
                height: 28px;
                font-size: 10px;
            }
        }
        
        .matching-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .matching-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .matching-item select {
            margin-left: 10px;
            flex: 1;
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Layout integration */
        .main-content {
            padding: 20px;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .content-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .question-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f9f9f9;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .question-header h3 {
            margin: 0;
            color: #333;
        }
        
        .remove-question {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .remove-question:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="main-content">
            <div class="content-header">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <h1 id="pageTitle"><i class="fas fa-plus-circle"></i> Create Questions</h1>
                <button id="cancelEditBtn" type="button" class="btn btn-secondary" style="display:none;" onclick="cancelEdit()">Cancel Edit</button>
            </div>
            <p>Clean, modular question creation system</p>
                <div style="margin-top:8px; display:flex; gap:8px;">
                    <button type="button" class="btn btn-primary" onclick="openImportModal()"><i class="fas fa-file-import"></i> Import from Question Bank</button>
                </div>
        </div>
        
        <!-- Question Creation Form -->
        <div class="question-form">
            <h2 id="formTitleHeading">Create Questions</h2>
            <form id="questionForm">
                <div class="form-group" style="position:relative;">
                    <label>Sections:</label>
                    <div id="sectionMulti" class="multi-select" tabindex="0" style="display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; cursor:pointer; min-width:260px;">
                        <span id="sectionSummary" class="multi-select-label" style="color:#6b7280;">Select sections</span>
                        <i class="fas fa-chevron-down" style="font-size:12px; color:#6b7280;"></i>
                    </div>
                    <div id="sectionPanel" class="multi-select-panel" style="position:absolute; top:100%; left:0; width:100%; background:#fff; border:1px solid #e5e7eb; border-radius:10px; margin-top:6px; box-shadow:0 10px 20px rgba(0,0,0,.08); padding:8px; display:none; max-height:260px; overflow:auto; z-index:1000;">
                        <label style="display:grid; grid-template-columns: 1fr auto; align-items:center; column-gap:10px; padding:8px 10px; border-radius:8px; background:#f8fafc; border:2px solid #16a34a; margin-bottom:6px;">
                            <span style="color:#374151; font-weight:600;">Select all</span>
                            <input type="checkbox" id="section_all" style="margin:0; justify-self:end;">
                        </label>
                        <?php foreach ($teacherSections as $section): $label = htmlspecialchars($section['section_name'] ?: $section['name']); ?>
                        <label style="display:grid; grid-template-columns: 1fr auto; align-items:center; column-gap:10px; padding:8px 10px; border-radius:8px; border:2px solid #16a34a; margin-bottom:6px; background:#fff;">
                            <span style="color:#111827;">&nbsp;<?php echo $label; ?></span>
                            <input type="checkbox" class="sec-box" value="<?php echo (int)$section['id']; ?>" data-label="<?php echo $label; ?>" style="margin:0; justify-self:end;">
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div id="sectionHiddenInputs"></div>
                    <small style="color:#6b7280; display:block; margin-top:6px;">Choose one or more sections.</small>
                </div>
                
                
                <div id="new-set-fields">
                    <div class="form-group">
                        <label for="set_title">Question Set Title:</label>
                        <input type="text" id="set_title" name="set_title">
                    </div>
                    <div class="form-group">
                        <label for="set_timer">Timer (minutes):</label>
                        <input type="number" id="set_timer" name="set_timer" min="0" placeholder="e.g., 30">
                    </div>
                    <div class="form-group">
                        <label for="set_open_at">Open Date/Time:</label>
                        <input type="datetime-local" id="set_open_at" name="set_open_at">
                    </div>
                    <div class="form-group">
                        <label for="set_difficulty">Difficulty:</label>
                        <select id="set_difficulty" name="set_difficulty">
                            <option value="">Select difficulty</option>
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                </div>
                
                
                <!-- Questions Container -->
                <div id="questions-container"></div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="addNewQuestion()" style="margin-right: 10px;">
                        <i class="fas fa-plus"></i> Add New Question
                    </button>
                    <button type="submit" class="btn btn-primary" style="font-size: 18px; padding: 15px 30px; margin-top: 20px;">
                        <i class="fas fa-save"></i> Create Questions
                    </button>
                </div>
            </form>
        </div>

        <!-- Import Modal -->
        <div id="importModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:2000; align-items:flex-start; justify-content:center; padding:80px 16px 24px;">
            <div style="background:#fff; width:min(980px,95vw); border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,.2); overflow:hidden;">
                <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e5e7eb;">
                    <strong><i class="fas fa-database"></i> Import Questions</strong>
                    <button onclick="closeImportModal()" class="btn btn-secondary">Close</button>
                </div>
                <div style="padding:12px;">
                    <div style="margin-bottom:10px; display:flex; gap:8px; align-items:center;">
                        <label>From Set:</label>
                        <select id="importSet" style="padding:6px 8px; border:1px solid #e5e7eb; border-radius:8px;">
                            <?php foreach ($questionSets as $set): ?>
                                <option value="<?php echo (int)$set['id']; ?>"><?php echo htmlspecialchars($set['set_title']); ?> (<?php echo htmlspecialchars($set['section_name']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-secondary" onclick="loadBankQuestions()">Load</button>
                    </div>
                    <div id="bankList" style="max-height:60vh; overflow:auto; border:1px solid #e5e7eb; border-radius:8px; padding:8px; background:#fff;">
                        <div style="color:#6b7280;">Select a set and click Load.</div>
                    </div>
                    <div style="text-align:right; margin-top:10px;">
                        <button class="btn btn-primary" onclick="importSelected()"><i class="fas fa-plus"></i> Import Selected</button>
                    </div>
                </div>
            </div>
        </div>
        
        
        <!-- Question Sets List -->
        <div class="question-sets">
            <div class="question-bank-header">
                <h2>Question Bank</h2>
                <div class="section-filter">
                    <label for="sectionFilter">Filter by Section:</label>
                    <select id="sectionFilter" onchange="filterBySection()">
                        <?php 
                        // Get all sections that the teacher handles
                        foreach ($teacherSections as $section): 
                            $sectionName = $section['section_name'] ?: $section['name'];
                            $isSelected = ($sectionName === 'Rizal') ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($sectionName); ?>" <?php echo $isSelected; ?>>
                                <?php echo htmlspecialchars($sectionName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="table-container">
                <table class="question-bank-table">
                    <thead>
                        <tr>
                            <th>Question Set</th>
                            <th>Section</th>
                            <th>Points</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questionSets as $set): ?>
                        <tr>
                            <td class="set-title">
                                <strong><?php echo htmlspecialchars($set['set_title']); ?></strong>
                            </td>
                            <td class="section-name">
                                <?php echo htmlspecialchars($set['section_name']); ?>
                            </td>
                            <td class="total-points">
                                <span class="points-badge"><?php echo $set['total_points']; ?></span>
                            </td>
                            <td class="created-date">
                                <span class="date-text">
                                    <?php echo date('M j, Y', strtotime($set['created_at'])); ?>
                                </span>
                                <small class="time-text">
                                    <?php echo date('g:i A', strtotime($set['created_at'])); ?>
                                </small>
                            </td>
                            <td class="actions">
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-view" onclick="viewQuestions('<?php echo $set['id']; ?>', '<?php echo htmlspecialchars($set['set_title']); ?>')" title="View Questions">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-edit" onclick="editSet(<?php echo $set['id']; ?>)" title="Edit Set">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-delete" onclick="deleteSet(<?php echo $set['id']; ?>)" title="Delete Set">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function openImportModal(){ document.getElementById('importModal').style.display='flex'; }
        function closeImportModal(){ document.getElementById('importModal').style.display='none'; }
        function loadBankQuestions(){
            const setId = document.getElementById('importSet').value;
            const body = new URLSearchParams({ action: 'get_set_questions', set_id: setId });
            fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                .then(r=>r.json()).then(data=>{
                    const container = document.getElementById('bankList');
                    if(!data || !data.success || !Array.isArray(data.questions)){ container.innerHTML = '<div style="color:#ef4444">Failed to load.</div>'; return; }
                    if(data.questions.length===0){ container.innerHTML = '<div style="color:#6b7280">No questions in this set.</div>'; return; }
                    container.innerHTML = data.questions.map((q,i)=>{
                        const title = (q.type||'').toUpperCase()+ (q.points?` • ${q.points} pts`:'' );
                        return `<label style="display:block; padding:8px; border-bottom:1px solid #e5e7eb;">
                            <input type="checkbox" class="impChk" data-type="${q.type}" data-raw='${encodeURIComponent(JSON.stringify(q))}'>
                            <strong>${title}</strong><br>
                            <span style="color:#374151">${(q.question_text||'').substring(0,120)}</span>
                        </label>`;
                    }).join('');
                }).catch(()=>{
                    document.getElementById('bankList').innerHTML = '<div style="color:#ef4444">Failed to load.</div>';
                });
        }
        function importSelected(){
            const checks = document.querySelectorAll('.impChk:checked');
            if(!checks.length){ closeImportModal(); return; }
            checks.forEach(ch=>{
                try{
                    const raw = JSON.parse(decodeURIComponent(ch.getAttribute('data-raw')));
                    const t = (raw.type||'').toLowerCase();
                    if(t==='mcq'){
                        const idx = document.querySelectorAll('.question-item').length; addNewQuestion();
                        const i = idx; document.getElementById(`type_${i}`).value = 'mcq'; showQuestionTypeSection(i);
                        document.getElementById(`question_text_${i}`).value = raw.question_text||'';
                        document.getElementById(`points_${i}`).value = raw.points||1;
                        document.getElementById(`choice_a_${i}`).value = raw.choice_a||'';
                        document.getElementById(`choice_b_${i}`).value = raw.choice_b||'';
                        document.getElementById(`choice_c_${i}`).value = raw.choice_c||'';
                        document.getElementById(`choice_d_${i}`).value = raw.choice_d||'';
                        const ca = (raw.correct_answer||'').toLowerCase(); const r = document.getElementById(`correct_${ca}_${i}`); if(r) r.checked = true;
                    } else if(t==='matching'){
                        const idx = document.querySelectorAll('.question-item').length; addNewQuestion(); const i = idx;
                        document.getElementById(`type_${i}`).value = 'matching'; showQuestionTypeSection(i);
                        document.getElementById(`question_text_${i}`).value = raw.question_text||'';
                        try{
                            const left = Array.isArray(raw.left_items)?raw.left_items:JSON.parse(raw.left_items||'[]');
                            const right = Array.isArray(raw.right_items)?raw.right_items:JSON.parse(raw.right_items||'[]');
                            // Add rows/cols to fit
                            for(let n=2;n<left.length;n++) addMatchingRow(i);
                            const li = document.querySelectorAll(`input[name="questions[${i}][left_items][]"]`);
                            const ri = document.querySelectorAll(`input[name="questions[${i}][right_items][]"]`);
                            left.forEach((v,k)=>{ if(li[k]) li[k].value = v; });
                            right.forEach((v,k)=>{ if(ri[k]) ri[k].value = v; });

                            // Build normalized matches from various shapes
                            const normalize = (rawM)=>{
                                let out = [];
                                if(!rawM) return out;
                                const r = (typeof rawM === 'string') ? (function(){ try { return JSON.parse(rawM); } catch(e){ return rawM; } })() : rawM;
                                if(Array.isArray(r)){
                                    r.forEach(item=>{
                                        if(typeof item === 'string') out.push(item);
                                        else if(typeof item === 'number') out.push(right[item] ?? '');
                                        else if(item && typeof item === 'object') out.push(item.value ?? item.answer ?? item.right ?? item.right_item ?? '');
                                    });
                                } else if(r && typeof r === 'object'){
                                    Object.keys(r).forEach(k=> out.push(r[k]));
                                } else if(typeof r === 'number'){
                                    out.push(right[r] ?? '');
                                }
                                return out;
                            };
                            let matches = normalize(raw.matches);
                            if(matches.length === 0) matches = normalize(raw.correct_pairs);

                            // Populate selects after options are built
                            updateMatchingMatches(i);
                            setTimeout(()=>{
                                const selects = document.querySelectorAll(`#matching-matches_${i} select`);
                                const apply = () => {
                                    const ready = Array.from(selects).every(s=>s.options.length>1);
                                    if(!ready){ setTimeout(apply, 80); return; }
                                    matches.forEach((targetVal, idxSel)=>{
                                        const sel = selects[idxSel]; if(!sel) return;
                                        const target = (targetVal ?? '').toString().trim().toLowerCase();
                                        let chosen = false;
                                        Array.from(sel.options).forEach(opt=>{
                                            const ov = (opt.value||'').toString().trim().toLowerCase();
                                            const ot = (opt.textContent||'').toString().trim().toLowerCase();
                                            if(ov===target || ot===target){ opt.selected = true; chosen = true; }
                                        });
                                        if(!chosen && target){
                                            Array.from(sel.options).forEach(opt=>{
                                                const ov = (opt.value||'').toString().trim().toLowerCase();
                                                const ot = (opt.textContent||'').toString().trim().toLowerCase();
                                                if(ov.includes(target) || target.includes(ov) || ot.includes(target) || target.includes(ot)){
                                                    opt.selected = true; chosen = true;
                                                }
                                            });
                                        }
                                        if(!chosen && target){ sel.value = targetVal; }
                                        sel.dispatchEvent(new Event('change'));
                                    });
                                };
                                apply();
                            },100);
                        }catch(e){}
                        document.getElementById(`points_${i}`).value = raw.points||2;
                    } else if(t==='essay'){
                        const idx = document.querySelectorAll('.question-item').length; addNewQuestion(); const i = idx;
                        document.getElementById(`type_${i}`).value = 'essay'; showQuestionTypeSection(i);
                        document.getElementById(`question_text_${i}`).value = raw.question_text||'';
                        document.getElementById(`points_${i}`).value = raw.points||1;
                    }
                }catch(e){ console.error(e); }
            });
            closeImportModal();
        }
        // Start with zero questions; teacher will click "Add New Question"
        let questionIndex = 0;
        window.isEditMode = false;
        window.currentEditSetId = null;
        
        // Dropdown with checkboxes (multi-select sections)
        (function multiSelectSections(){
            const trigger = document.getElementById('sectionMulti');
            const panel = document.getElementById('sectionPanel');
            const all = document.getElementById('section_all');
            const boxes = Array.from(panel ? panel.querySelectorAll('.sec-box') : []);
            const summary = document.getElementById('sectionSummary');
            const hiddenWrap = document.getElementById('sectionHiddenInputs');
            if(!trigger || !panel || !summary) return;
            const open = ()=>{ panel.style.display = 'block'; };
            const close = ()=>{ panel.style.display = 'none'; };
            trigger.addEventListener('click', (e)=>{ e.stopPropagation(); panel.style.display = (panel.style.display==='block'?'none':'block'); });
            document.addEventListener('click', (e)=>{ if(!panel.contains(e.target) && e.target!==trigger) close(); });
            const syncAll = ()=>{
                const total = boxes.length;
                const checked = boxes.filter(b=>b.checked).length;
                if(all){ all.indeterminate = checked>0 && checked<total; all.checked = checked===total; }
                const labels = boxes.filter(b=>b.checked).map(b=>b.getAttribute('data-label'));
                summary.textContent = labels.length ? labels.join(', ') : 'Select sections';
                // Sync hidden inputs
                hiddenWrap.innerHTML = '';
                boxes.filter(b=>b.checked).forEach(b=>{
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'section_ids[]';
                    input.value = b.value;
                    hiddenWrap.appendChild(input);
                });
            };
            if(all){ all.addEventListener('change', ()=>{ boxes.forEach(b=> b.checked = all.checked); syncAll(); }); }
            boxes.forEach(b=> b.addEventListener('change', syncAll));
            syncAll();
        })();
        
        // Global functions that need to be accessible from HTML
        function showQuestionTypeSection(questionIndex = 0) {
            // Try to get the type from the current question or the first question
            const typeElement = document.getElementById(`type_${questionIndex}`) || document.getElementById('type');
            const type = typeElement ? typeElement.value : '';
            
            // Try to get question text from the current question or the first question
            const questionText = document.getElementById(`question_text_${questionIndex}`) || document.getElementById('question_text');
            const helpText = document.getElementById(`question_text_help_${questionIndex}`) || document.getElementById('question_text_help_0');
            
            if (!questionText) {
                console.error('Question text element not found for question index:', questionIndex);
                return;
            }
            
            // Hide all sections for this specific question only
            const questionItem = document.querySelector(`[data-question-index="${questionIndex}"]`);
            if (questionItem) {
                questionItem.querySelectorAll('.question-type-section').forEach(section => {
                    section.classList.remove('active');
                });
            }
            
            // Remove required attributes from question type fields for this specific question only
            if (questionItem) {
                questionItem.querySelectorAll('.question-type-section input, .question-type-section select, .question-type-section textarea').forEach(field => {
                    field.removeAttribute('required');
                });
            }
            
            // Show selected section
            if (type) {
                // Try to find section with question index first, then fallback to general section
                const section = document.getElementById(`${type}-section_${questionIndex}`) || document.getElementById(`${type}-section`);
                
                if (section) {
                    section.classList.add('active');
                    
                    // Add required attributes only to the active section
                    section.querySelectorAll('input, select, textarea').forEach(field => {
                        if (field.type !== 'radio' || field.name.includes('correct_answer')) {
                            field.setAttribute('required', 'required');
                        }
                    });
                    
                    // Special handling for essay rubric
                    if (type === 'essay') {
                        const rubricField = document.getElementById(`essay_rubric_${questionIndex}`);
                        if (rubricField) {
                            rubricField.setAttribute('required', 'required');
                        }
                    }
                    
                    // If it's a matching question, update the matches display
                    if (type === 'matching') {
                        // Small delay to ensure DOM is ready
                        setTimeout(() => {
                            updateMatchingMatches(questionIndex);
                        }, 100);
                    }
                }
                
                // Auto-populate/reset defaults based on type
                const defaultMatching = 'Match the following items with their correct answers:';
                if (type === 'matching') {
                    // Only auto-insert default text if not in edit mode or field empty
                    if (!window.isEditMode || (questionText && questionText.value.trim() === '')) {
                        questionText.value = defaultMatching;
                    }
                    helpText.style.display = 'block';
                    // Add event listeners to existing inputs and update matches
                    setTimeout(() => {
                        addInputListeners(questionIndex);
                        updateMatchingMatches(questionIndex);
                        // Set default points to 2 (since there are 2 default rows)
                        const pointsField = document.getElementById(`points_${questionIndex}`) || document.getElementById('points');
                        if (pointsField) {
                            pointsField.value = 2;
                        }
                    }, 100);
                } else {
                    // Reset to neutral defaults for MCQ/essay when switching from matching
                    helpText.style.display = 'none';
                    const pointsField = document.getElementById(`points_${questionIndex}`) || document.getElementById('points');
                    if (pointsField) pointsField.value = 1; // default 1 point
                    // Clear only the matching default text, do not erase real content loaded in edit mode
                    if (questionText && questionText.value.trim() === defaultMatching) questionText.value = '';
                }
                
                // Auto-update points for matching questions
                if (type === 'matching') {
                    updateMatchingMatches(questionIndex);
                }
            }
        }
        
        function addNewQuestion() {
            const container = document.getElementById('questions-container');
            // Ensure sequential indexes even after deletions
            const nextIndex = container ? container.querySelectorAll('.question-item').length : (questionIndex + 1);
            questionIndex = nextIndex - 1; // sync global
            questionIndex++;
            console.log(`Adding new question with index: ${questionIndex}`);
            const newQuestion = createQuestionHTML(questionIndex);
            container.insertAdjacentHTML('beforeend', newQuestion);
            
            // Show remove buttons for all questions
            document.querySelectorAll('.remove-question').forEach(btn => {
                btn.style.display = 'inline-block';
            });
        }
        
        function addAnotherQuestion() {
            addNewQuestion();
        }
        
        function removeQuestion(button) {
            const questionItem = button.closest('.question-item');
            questionItem.remove();
            
            // Hide remove buttons if only one question left
            const remainingQuestions = document.querySelectorAll('.question-item');
            if (remainingQuestions.length <= 1) {
                document.querySelectorAll('.remove-question').forEach(btn => {
                    btn.style.display = 'none';
                });
            }

            // Renumber remaining questions sequentially (titles, indexes, and element IDs)
            const container = document.getElementById('questions-container');
            const items = Array.from(container.querySelectorAll('.question-item'));
            items.forEach((item, newIdx) => {
                const oldIdx = parseInt(item.getAttribute('data-question-index') || '0', 10);
                if (oldIdx === newIdx) return; // already aligned

                // Update data index
                item.setAttribute('data-question-index', String(newIdx));

                // Update title
                const title = item.querySelector('.q-title') || item.querySelector('.question-header h3');
                if (title) title.textContent = `Question ${newIdx + 1}`;

                // Update common field IDs/names
                const remap = (selector, attr, pattern, replaceWith) => {
                    item.querySelectorAll(selector).forEach(el => {
                        const val = el.getAttribute(attr);
                        if (val && val.includes(pattern)) {
                            el.setAttribute(attr, val.replace(pattern, replaceWith));
                        }
                    });
                };

                // id attributes
                remap('[id]','id', `_${oldIdx}`, `_${newIdx}`);
                // for attributes (labels)
                remap('label[for]','for', `_${oldIdx}`, `_${newIdx}`);
                // name attributes
                remap('[name]','name', `[${oldIdx}]`, `[${newIdx}]`);

                // Update inline handlers that depend on index (e.g., type selector change)
                const typeSel = item.querySelector(`#type_${newIdx}`) || item.querySelector('select[id^="type_"]');
                if (typeSel) {
                    typeSel.setAttribute('onchange', `showQuestionTypeSection(${newIdx})`);
                }
            });
            // Sync global index with current count
            questionIndex = (document.querySelectorAll('.question-item').length || 1) - 1;
        }
        
        function getQuestionHeaderLabel(q) {
            // q: may be index (number) or question DOM stub with inputs
            if (typeof q === 'number') { return `Question ${q + 1}`; }
            try {
                const container = q;
                const rows = container ? container.querySelectorAll('.question-type-section#matching-section_' + container.dataset.questionIndex + ' input[name^="questions"][name$="[left_items][]"]') : [];
                if (rows && rows.length > 1) {
                    const idx = parseInt(container.dataset.questionIndex || '0', 10) + 1;
                    return `Question ${idx}–${idx + rows.length - 1}`;
                }
            } catch(e) {}
            const idx = parseInt(q.dataset?.questionIndex || q || 0, 10);
            return `Question ${isNaN(idx)? 1 : (idx + 1)}`;
        }

        function createQuestionHTML(index) {
            return `
                <div class="question-item" data-question-index="${index}">
                    <div class="question-header">
                        <h3 class="q-title">Question ${index + 1}</h3>
                        <button type="button" class="btn btn-danger btn-sm remove-question" onclick="removeQuestion(this)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label for="type_${index}">Question Type:</label>
                        <select id="type_${index}" name="questions[${index}][type]" required onchange="showQuestionTypeSection(${index})">
                            <option value="">Select Question Type</option>
                            <option value="mcq">Multiple Choice</option>
                            <option value="matching">Matching</option>
                            <option value="essay">Essay</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="question_text_${index}">Question Text:</label>
                        <textarea id="question_text_${index}" name="questions[${index}][question_text]" rows="3" required></textarea>
                        <small id="question_text_help_${index}" class="form-text text-muted" style="display: none;">
                            For matching questions, this will be used as the main instruction above all matching pairs.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="points_${index}">Points:</label>
                        <input type="number" id="points_${index}" name="questions[${index}][points]" value="1" min="1" required>
                    </div>
                    
                    <!-- MCQ Section -->
                    <div id="mcq-section_${index}" class="question-type-section">
                        <h3>Multiple Choice Options</h3>
                        <div id="mcq-options_${index}">
                            <div class="option-group">
                                <label for="choice_a_${index}">Option A:</label>
                                <input type="text" id="choice_a_${index}" name="questions[${index}][choice_a]" placeholder="Option A" required>
                                <label for="correct_a_${index}">Correct Answer:</label>
                                <input type="radio" id="correct_a_${index}" name="questions[${index}][correct_answer]" value="A" required>
                                <button type="button" onclick="removeOption(this)">×</button>
                            </div>
                            <div class="option-group">
                                <label for="choice_b_${index}">Option B:</label>
                                <input type="text" id="choice_b_${index}" name="questions[${index}][choice_b]" placeholder="Option B" required>
                                <label for="correct_b_${index}">Correct Answer:</label>
                                <input type="radio" id="correct_b_${index}" name="questions[${index}][correct_answer]" value="B" required>
                                <button type="button" onclick="removeOption(this)">×</button>
                            </div>
                            <div class="option-group">
                                <label for="choice_c_${index}">Option C:</label>
                                <input type="text" id="choice_c_${index}" name="questions[${index}][choice_c]" placeholder="Option C" required>
                                <label for="correct_c_${index}">Correct Answer:</label>
                                <input type="radio" id="correct_c_${index}" name="questions[${index}][correct_answer]" value="C" required>
                                <button type="button" onclick="removeOption(this)">×</button>
                            </div>
                            <div class="option-group">
                                <label for="choice_d_${index}">Option D:</label>
                                <input type="text" id="choice_d_${index}" name="questions[${index}][choice_d]" placeholder="Option D" required>
                                <label for="correct_d_${index}">Correct Answer:</label>
                                <input type="radio" id="correct_d_${index}" name="questions[${index}][correct_answer]" value="D" required>
                                <button type="button" onclick="removeOption(this)">×</button>
                            </div>
                        </div>
                        <button type="button" class="add-option" onclick="addMCQOption(${index})">
                            <i class="fas fa-plus"></i> Add Option
                        </button>
                    </div>
                    
                    <!-- Matching Section -->
                    <div id="matching-section_${index}" class="question-type-section">
                        <h3>Matching Pairs</h3>
                        <div class="form-group">
                            <label>Left Items (Rows):</label>
                            <div id="matching-rows_${index}">
                                <div class="input-group">
                                    <label for="left_item_1_${index}">Row 1:</label>
                                    <input type="text" id="left_item_1_${index}" name="questions[${index}][left_items][]" placeholder="Row 1" required>
                                    <button type="button" class="remove-option" onclick="removeMatchingRow(this, ${index})">×</button>
                                </div>
                                <div class="input-group">
                                    <label for="left_item_2_${index}">Row 2:</label>
                                    <input type="text" id="left_item_2_${index}" name="questions[${index}][left_items][]" placeholder="Row 2" required>
                                    <button type="button" class="remove-option" onclick="removeMatchingRow(this, ${index})">×</button>
                                </div>
                            </div>
                            <button type="button" class="add-option" onclick="addMatchingRow(${index})">
                                <i class="fas fa-plus"></i> Add Row
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label>Right Items (Columns):</label>
                            <div id="matching-columns_${index}">
                                <div class="input-group">
                                    <label for="right_item_1_${index}">Column 1:</label>
                                    <input type="text" id="right_item_1_${index}" name="questions[${index}][right_items][]" placeholder="Column 1" required>
                                    <button type="button" class="remove-option" onclick="removeMatchingColumn(this, ${index})">×</button>
                                </div>
                                <div class="input-group">
                                    <label for="right_item_2_${index}">Column 2:</label>
                                    <input type="text" id="right_item_2_${index}" name="questions[${index}][right_items][]" placeholder="Column 2" required>
                                    <button type="button" class="remove-option" onclick="removeMatchingColumn(this, ${index})">×</button>
                                </div>
                            </div>
                            <button type="button" class="add-option" onclick="addMatchingColumn(${index})">
                                <i class="fas fa-plus"></i> Add Column
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label>Correct Matches:</label>
                            <div id="matching-matches_${index}">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Essay Section -->
                    <div id="essay-section_${index}" class="question-type-section">
                        <h3>Essay Question</h3>
                        <p>Essay questions will be manually graded by the teacher.</p>
                        <div class="form-group">
                            <label for="essay_rubric_${index}">Rubric (required)</label>
                            <textarea id="essay_rubric_${index}" name="questions[${index}][rubric]" rows="4" placeholder="e.g., Thesis (2), Evidence (3), Organization (2), Grammar (3)"></textarea>
                            <small class="form-text text-muted">Describe scoring criteria or paste a rubric. Students will see this rubric.</small>
                        </div>
                    </div>
                </div>
            `;
        }
        
        
        
        
        
        function addMCQOption(questionIndex = 0) {
            const container = document.getElementById(`mcq-options_${questionIndex}`);
            const optionCount = container.children.length;
            const optionGroup = document.createElement('div');
            optionGroup.className = 'option-group';
            const optionLetter = String.fromCharCode(65 + optionCount);
            const optionId = `choice_${optionLetter.toLowerCase()}_${questionIndex}_${optionCount}`;
            const radioId = `correct_${optionLetter.toLowerCase()}_${questionIndex}_${optionCount}`;
            optionGroup.innerHTML = `
                <label for="${optionId}">Option ${optionLetter}:</label>
                <input type="text" id="${optionId}" name="questions[${questionIndex}][choice_${optionLetter.toLowerCase()}]" placeholder="Option ${optionLetter}" required>
                <label for="${radioId}">Correct Answer:</label>
                <input type="radio" id="${radioId}" name="questions[${questionIndex}][correct_answer]" value="${optionLetter}" required>
                <button type="button" onclick="removeOption(this)">×</button>
            `;
            container.appendChild(optionGroup);
        }
        
        function addMatchingRow(questionIndex = 0) {
            const container = document.getElementById(`matching-rows_${questionIndex}`);
            // Count only input elements to get the correct row number
            const existingInputs = container.querySelectorAll('input[type="text"]');
            const rowNumber = existingInputs.length + 1;
            const inputId = `left_item_${rowNumber}_${questionIndex}`;
            
            const label = document.createElement('label');
            label.setAttribute('for', inputId);
            label.textContent = `Row ${rowNumber}:`;
            
            const input = document.createElement('input');
            input.type = 'text';
            input.id = inputId;
            input.name = `questions[${questionIndex}][left_items][]`;
            input.placeholder = `Row ${rowNumber}`;
            input.required = true;
            input.addEventListener('input', () => {
                updateMatchingMatches(questionIndex);
                setTimeout(() => validateMatching(questionIndex), 10);
            });
            input.setAttribute('data-listener-added', 'true');
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-option';
            removeBtn.textContent = '×';
            removeBtn.onclick = () => removeMatchingRow(removeBtn, questionIndex);
            
            const inputGroup = document.createElement('div');
            inputGroup.className = 'input-group';
            inputGroup.appendChild(label);
            inputGroup.appendChild(input);
            inputGroup.appendChild(removeBtn);
            
            container.appendChild(inputGroup);
            
            // Automatically add a corresponding column
            addMatchingColumn(questionIndex);
            
            updateMatchingMatches(questionIndex); // This will update points automatically
            // Update header label to range for matching
            const qi = document.querySelector(`[data-question-index="${questionIndex}"]`);
            const title = qi ? qi.querySelector('.q-title') : null;
            if (title) {
                const count = container.querySelectorAll('input[type="text"]').length;
                if (count > 1) {
                    const start = questionIndex + 1;
                    title.textContent = `Question ${start}–${start + count - 1}`;
                } else {
                    title.textContent = `Question ${questionIndex + 1}`;
                }
            }
        }
        
        function addMatchingColumn(questionIndex = 0) {
            const container = document.getElementById(`matching-columns_${questionIndex}`);
            // Count only input elements to get the correct column number
            const existingInputs = container.querySelectorAll('input[type="text"]');
            const columnNumber = existingInputs.length + 1;
            const inputId = `right_item_${columnNumber}_${questionIndex}`;
            
            const label = document.createElement('label');
            label.setAttribute('for', inputId);
            label.textContent = `Column ${columnNumber}:`;
            
            const input = document.createElement('input');
            input.type = 'text';
            input.id = inputId;
            input.name = `questions[${questionIndex}][right_items][]`;
            input.placeholder = `Column ${columnNumber}`;
            input.required = true;
            input.addEventListener('input', () => {
                updateMatchingMatches(questionIndex);
                setTimeout(() => validateMatching(questionIndex), 10);
            });
            input.setAttribute('data-listener-added', 'true');
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-option';
            removeBtn.textContent = '×';
            removeBtn.onclick = () => removeMatchingColumn(removeBtn, questionIndex);
            
            const inputGroup = document.createElement('div');
            inputGroup.className = 'input-group';
            inputGroup.appendChild(label);
            inputGroup.appendChild(input);
            inputGroup.appendChild(removeBtn);
            
            container.appendChild(inputGroup);
            updateMatchingMatches(questionIndex); // This will update points automatically
        }
        
        function updateMatchingMatches(questionIndex = 0) {
            const rows = document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`);
            const columns = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
            const container = document.getElementById(`matching-matches_${questionIndex}`);
            const pointsField = document.getElementById(`points_${questionIndex}`);
            
            container.innerHTML = '';
            
            // Count valid rows (non-empty)
            const validRows = Array.from(rows).filter(row => row.value.trim());
            const validColumns = Array.from(columns).filter(col => col.value.trim());
            
            // Calculate points based on number of all rows (not just valid ones)
            const calculatedPoints = Math.max(rows.length, 1); // At least 1 point
            if (pointsField) {
                pointsField.value = calculatedPoints;
                console.log(`Updated points to ${calculatedPoints} for question ${questionIndex} (${rows.length} rows)`);
                
                // Force update the display
                pointsField.dispatchEvent(new Event('input'));
                pointsField.dispatchEvent(new Event('change'));
            } else {
                console.error(`Points field not found for question ${questionIndex}`);
            }
            
            rows.forEach((row, index) => {
                if (row.value.trim()) {
                    const matchItem = document.createElement('div');
                    matchItem.className = 'matching-item';
                    matchItem.innerHTML = `
                        <label>${row.value}:</label>
                        <select name="questions[${questionIndex}][matches][${index}]" required>
                            <option value="">Select match</option>
                            ${Array.from(columns).map(col => 
                                `<option value="${col.value}">${col.value}</option>`
                            ).join('')}
                        </select>
                    `;
                    container.appendChild(matchItem);
                }
            });
        }
        
        function removeMatchingRow(button, questionIndex) {
            const rowContainer = document.getElementById(`matching-rows_${questionIndex}`);
            const columnContainer = document.getElementById(`matching-columns_${questionIndex}`);
            
            // Get the row index (position in the rows container)
            const rowGroups = Array.from(rowContainer.querySelectorAll('.input-group'));
            const rowIndex = rowGroups.indexOf(button.parentElement);
            
            // Remove the row
            button.parentElement.remove();
            
            // Remove the corresponding column (same index)
            const columnGroups = Array.from(columnContainer.querySelectorAll('.input-group'));
            if (columnGroups[rowIndex]) {
                columnGroups[rowIndex].remove();
            }
            
            // Renumber remaining rows and columns
            renumberMatchingItems(questionIndex);
            
            // Update matching matches
            updateMatchingMatches(questionIndex);
            // Update header label
            const qi = document.querySelector(`[data-question-index="${questionIndex}"]`);
            const title = qi ? qi.querySelector('.q-title') : null;
            if (title) {
                const count = rowContainer.querySelectorAll('input[type="text"]').length;
                if (count > 1) {
                    const start = questionIndex + 1;
                    title.textContent = `Question ${start}–${start + count - 1}`;
                } else {
                    title.textContent = `Question ${questionIndex + 1}`;
                }
            }
        }
        
        function removeMatchingColumn(button, questionIndex) {
            const rowContainer = document.getElementById(`matching-rows_${questionIndex}`);
            const columnContainer = document.getElementById(`matching-columns_${questionIndex}`);
            
            // Get the column index (position in the columns container)
            const columnGroups = Array.from(columnContainer.querySelectorAll('.input-group'));
            const columnIndex = columnGroups.indexOf(button.parentElement);
            
            // Remove the column
            button.parentElement.remove();
            
            // Remove the corresponding row (same index)
            const rowGroups = Array.from(rowContainer.querySelectorAll('.input-group'));
            if (rowGroups[columnIndex]) {
                rowGroups[columnIndex].remove();
            }
            
            // Renumber remaining rows and columns
            renumberMatchingItems(questionIndex);
            
            // Update matching matches
            updateMatchingMatches(questionIndex);
            // Update header label
            const qi = document.querySelector(`[data-question-index="${questionIndex}"]`);
            const title = qi ? qi.querySelector('.q-title') : null;
            if (title) {
                const count = rowContainer.querySelectorAll('input[type="text"]').length;
                if (count > 1) {
                    const start = questionIndex + 1;
                    title.textContent = `Question ${start}–${start + count - 1}`;
                } else {
                    title.textContent = `Question ${questionIndex + 1}`;
                }
            }
        }
        
        function renumberMatchingItems(questionIndex) {
            const rowContainer = document.getElementById(`matching-rows_${questionIndex}`);
            const columnContainer = document.getElementById(`matching-columns_${questionIndex}`);
            
            // Renumber rows
            const rowGroups = Array.from(rowContainer.querySelectorAll('.input-group'));
            rowGroups.forEach((group, index) => {
                const newNumber = index + 1;
                const label = group.querySelector('label');
                const input = group.querySelector('input');
                
                label.textContent = `Row ${newNumber}:`;
                label.setAttribute('for', `left_item_${newNumber}_${questionIndex}`);
                input.id = `left_item_${newNumber}_${questionIndex}`;
                input.placeholder = `Row ${newNumber}`;
            });
            
            // Renumber columns
            const columnGroups = Array.from(columnContainer.querySelectorAll('.input-group'));
            columnGroups.forEach((group, index) => {
                const newNumber = index + 1;
                const label = group.querySelector('label');
                const input = group.querySelector('input');
                
                label.textContent = `Column ${newNumber}:`;
                label.setAttribute('for', `right_item_${newNumber}_${questionIndex}`);
                input.id = `right_item_${newNumber}_${questionIndex}`;
                input.placeholder = `Column ${newNumber}`;
            });
        }
        
        function removeOption(button) {
            const container = button.parentElement.parentElement;
            button.parentElement.remove();
            
            // Renumber remaining items
            const questionIndex = container.id.split('_').pop();
            const isRow = container.id.includes('rows');
            
            // Get all remaining input elements
            const remainingInputs = container.querySelectorAll('input[type="text"]');
            
            // Renumber labels and inputs
            remainingInputs.forEach((input, index) => {
                const newNumber = index + 1;
                const label = input.previousElementSibling;
                
                if (isRow) {
                    label.textContent = `Row ${newNumber}:`;
                    input.placeholder = `Row ${newNumber}`;
                } else {
                    label.textContent = `Column ${newNumber}:`;
                    input.placeholder = `Column ${newNumber}`;
                }
            });
            
            // Update matching matches if this is a row/column removal
            if (isRow) {
                updateMatchingMatches(questionIndex);
            }
        }
        
        function addInputListeners(questionIndex = 0) {
            // Add event listeners to existing row inputs
            document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`).forEach(input => {
                if (!input.hasAttribute('data-listener-added')) {
                    input.addEventListener('input', () => { 
                        updateMatchingMatches(questionIndex); 
                        // Delay validation to ensure value is set
                        setTimeout(() => validateMatching(questionIndex), 100);
                    });
                    input.addEventListener('blur', () => {
                        setTimeout(() => validateMatching(questionIndex), 50);
                    });
                    input.setAttribute('data-listener-added', 'true');
                }
            });
            
            // Add event listeners to existing column inputs
            document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`).forEach(input => {
                if (!input.hasAttribute('data-listener-added')) {
                    input.addEventListener('input', () => { 
                        updateMatchingMatches(questionIndex); 
                        // Clear all errors first, then validate
                        clearAllMatchingErrors(questionIndex);
                        setTimeout(() => validateMatching(questionIndex), 100);
                    });
                    input.addEventListener('blur', () => {
                        clearAllMatchingErrors(questionIndex);
                        setTimeout(() => validateMatching(questionIndex), 50);
                    });
                    input.setAttribute('data-listener-added', 'true');
                }
            });
            // Add listener for type/points/text
            const typeSel = document.getElementById(`type_${questionIndex}`);
            const textEl = document.getElementById(`question_text_${questionIndex}`);
            const ptsEl = document.getElementById(`points_${questionIndex}`);
            if (typeSel && !typeSel.hasAttribute('data-rtv')) { typeSel.addEventListener('change', () => validateQuestion(questionIndex)); typeSel.setAttribute('data-rtv','1'); }
            if (textEl && !textEl.hasAttribute('data-rtv')) { textEl.addEventListener('input', () => validateQuestion(questionIndex)); textEl.setAttribute('data-rtv','1'); }
            if (ptsEl && !ptsEl.hasAttribute('data-rtv')) { ptsEl.addEventListener('input', () => validateQuestion(questionIndex)); ptsEl.setAttribute('data-rtv','1'); }
            // If MCQ, add listeners
            ['a','b','c','d'].forEach(k => {
                const opt = document.getElementById(`choice_${k}_${questionIndex}`);
                if (opt && !opt.hasAttribute('data-rtv')) { opt.addEventListener('input', () => validateMCQ(questionIndex)); opt.setAttribute('data-rtv','1'); }
                const ra = document.getElementById(`correct_${k}_${questionIndex}`);
                if (ra && !ra.hasAttribute('data-rtv')) { ra.addEventListener('change', () => validateMCQ(questionIndex)); ra.setAttribute('data-rtv','1'); }
            });
        }

        function showError(el, msgId, message) {
            if (!el) return;
            el.classList.add('invalid');
            let m = document.getElementById(msgId);
            if (!m) {
                m = document.createElement('div');
                m.id = msgId;
                m.className = 'error-text';
                el.parentElement.appendChild(m);
            }
            m.textContent = message;
        }
        function clearError(el, msgId) {
            if (!el) return;
            el.classList.remove('invalid');
            
            // Try to find and remove the error message by ID
            const m = document.getElementById(msgId);
            if (m) {
                console.log(`Clearing error for ${msgId}`);
                m.remove();
            }
            
            // Also try to find and remove any error text in the parent container
            const parent = el.parentElement;
            if (parent) {
                const errorTexts = parent.querySelectorAll('.error-text');
                errorTexts.forEach(error => {
                    if (error.textContent === 'Required') {
                        console.log(`Removing Required error text from parent`);
                        error.remove();
                    }
                });
            }
        }
        
        // Function to force clear all matching errors for a question
        function clearAllMatchingErrors(questionIndex) {
            console.log(`Force clearing all matching errors for question ${questionIndex}`);
            
            // Clear all left input errors
            const leftInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`);
            leftInputs.forEach((el, idx) => {
                clearError(el, `err_l_${questionIndex}_${idx}`);
            });
            
            // Clear all right input errors
            const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
            rightInputs.forEach((el, idx) => {
                clearError(el, `err_r_${questionIndex}_${idx}`);
            });
            
            // Clear all select errors
            const selects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
            selects.forEach((sel, idx) => {
                clearError(sel, `err_sel_${questionIndex}_${idx}`);
            });
        }
        function validateQuestion(i) {
            const textEl = document.getElementById(`question_text_${i}`);
            const typeSel = document.getElementById(`type_${i}`);
            const ptsEl = document.getElementById(`points_${i}`);
            if (textEl && !textEl.value.trim()) showError(textEl, `err_text_${i}`, 'Question text is required'); else clearError(textEl, `err_text_${i}`);
            if (typeSel && !typeSel.value) showError(typeSel, `err_type_${i}`, 'Please select a question type'); else clearError(typeSel, `err_type_${i}`);
            if (ptsEl && (Number(ptsEl.value) < 1 || isNaN(Number(ptsEl.value)))) showError(ptsEl, `err_pts_${i}`, 'Points must be 1 or higher'); else clearError(ptsEl, `err_pts_${i}`);
            // Essay rubric required when essay is selected
            if (typeSel && typeSel.value === 'essay') {
                const rubric = document.getElementById(`essay_rubric_${i}`);
                if (rubric && !rubric.value.trim()) showError(rubric, `err_rub_${i}`, 'Rubric is required for essay questions'); else clearError(rubric, `err_rub_${i}`);
            }
        }
        function validateMCQ(i) {
            const a = document.getElementById(`choice_a_${i}`), b = document.getElementById(`choice_b_${i}`), c = document.getElementById(`choice_c_${i}`), d = document.getElementById(`choice_d_${i}`);
            const correct = document.querySelector(`input[name="questions[${i}][correct_answer]"]:checked`);
            [a,b,c,d].forEach((el, idx) => {
                if (el) {
                    if (!el.value.trim()) showError(el, `err_m_${i}_${idx}`, 'Required'); else clearError(el, `err_m_${i}_${idx}`);
                }
            });
            const typeSel = document.getElementById(`type_${i}`);
            if (typeSel && typeSel.value === 'mcq') {
                if (!correct) showError(typeSel, `err_mcq_${i}`, 'Select a correct answer'); else clearError(typeSel, `err_mcq_${i}`);
            }
        }
        function validateMatching(i) {
            // Add a small delay to ensure DOM is fully updated
            setTimeout(() => {
                const leftInputs = document.querySelectorAll(`input[name="questions[${i}][left_items][]"]`);
                const rightInputs = document.querySelectorAll(`input[name="questions[${i}][right_items][]"]`);
                
                console.log(`Validating matching question ${i}:`);
                console.log('Left inputs:', leftInputs.length);
                console.log('Right inputs:', rightInputs.length);
                
                // Validate left inputs (rows)
                leftInputs.forEach((el, idx) => { 
                    const hasValue = el.value && el.value.trim().length > 0;
                    console.log(`Left input ${idx}: value="${el.value}", hasValue=${hasValue}`);
                    
                    if (!hasValue) {
                        showError(el, `err_l_${i}_${idx}`, 'Required');
                    } else {
                        clearError(el, `err_l_${i}_${idx}`);
                    }
                });
                
                // Validate right inputs (columns)
                rightInputs.forEach((el, idx) => { 
                    const hasValue = el.value && el.value.trim().length > 0;
                    console.log(`Right input ${idx}: value="${el.value}", hasValue=${hasValue}`);
                    
                    if (!hasValue) {
                        showError(el, `err_r_${i}_${idx}`, 'Required');
                    } else {
                        clearError(el, `err_r_${i}_${idx}`);
                        // Remove any stray 'Required' message that might be attached to parent wrappers
                        const parent = el.parentElement;
                        if (parent) {
                            const stray = parent.querySelectorAll('.error-text');
                            stray.forEach(n => { if (n.textContent === 'Required') n.remove(); });
                        }
                    }
                });
                
                // Matches dropdowns exist after updateMatchingMatches
                setTimeout(() => {
                    const selects = document.querySelectorAll(`#matching-matches_${i} select`);
                    selects.forEach((sel, idx) => { 
                        if (!sel.value) {
                            showError(sel, `err_sel_${i}_${idx}`, 'Select match'); 
                        } else {
                            clearError(sel, `err_sel_${i}_${idx}`);
                        }
                    });
                }, 100);
            }, 50);
        }
        
        
            
            // Form submission
            document.getElementById('questionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get the first question data (since we're creating a new set)
            const typeElement = document.getElementById('type_0') || document.getElementById('type');
            const questionTextElement = document.getElementById('question_text_0') || document.getElementById('question_text');
            const setTitleElement = document.getElementById('set_title');
            // Multi-select sections (checkboxes in dropdown)
            const sectionBoxes = document.querySelectorAll('#sectionHiddenInputs input[name="section_ids[]"]');
            const selectedSectionIds = Array.from(sectionBoxes).map(i => i.value);
            
            if (!typeElement || !questionTextElement || !setTitleElement) {
                alert('Form elements not found. Please refresh the page and try again.');
                return;
            }
            
            const type = typeElement.value;
            const questionText = questionTextElement.value;
            const setTitle = setTitleElement.value;
            
            // Basic validation
            if (selectedSectionIds.length === 0 || !questionText || !type) {
                alert('Please fill in all required fields!');
                return;
            }
            
            // Validate question set title
            if (!setTitle) {
                alert('Please enter a question set title!');
                return;
            }
            
            // Validate all questions (realtime + submit)
            const questionItems = document.querySelectorAll('.question-item');
            let allValid = true;
            
            questionItems.forEach((questionItem, index) => {
                const questionType = questionItem.querySelector(`#type_${index}`)?.value || questionItem.querySelector('#type')?.value;
                const questionText = questionItem.querySelector(`#question_text_${index}`)?.value || questionItem.querySelector('#question_text')?.value;
                
                if (!questionType || !questionText.trim()) { validateQuestion(index); allValid = false; }
                
                // Validate question type specific fields
                const activeSection = questionItem.querySelector(`#${questionType}-section_${index}`) || questionItem.querySelector(`#${questionType}-section`);
                if (activeSection) {
                    if (questionType === 'mcq') validateMCQ(index);
                    if (questionType === 'matching') validateMatching(index);
                }
            });
            
            if (!allValid) {
                alert('Please fix the highlighted fields before submitting.');
                return;
            }
            
            const formData = new FormData(this);
            // Normalize section selection for backend: always send section_ids[]; also include first as section_id for compatibility
            try {
                // Remove any accidental single field
                formData.delete('section_id');
            } catch(e) {}
            // Append chosen sections
            selectedSectionIds.forEach(id => formData.append('section_ids[]', id));
            if (selectedSectionIds.length > 0) {
                formData.append('section_id', selectedSectionIds[0]);
            }
            if (window.isEditMode) {
                formData.append('action', 'update_question_set');
                formData.append('set_id', window.currentEditSetId || '');
            } else {
                formData.append('action', 'create_question');
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = window.isEditMode ? '<i class="fas fa-spinner fa-spin"></i> Saving Changes...' : '<i class="fas fa-spinner fa-spin"></i> Creating Questions...';
            submitBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.text().then(text => {
                    try { return JSON.parse(text); }
                    catch(e){
                        console.error('Create response not JSON:', text);
                        throw new Error('Server returned invalid JSON. ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    alert('Question created successfully!');
                    this.reset();
                    document.querySelectorAll('.question-type-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        function viewQuestions(setId, setTitle) {
            // Helper to render the modal from data.questions
            const renderModal = (data) => {
                if (!data || !data.questions || data.questions.length === 0) {
                    alert('No questions found in this set.');
                    return;
                }

                // Styles
                const containerStyle = `position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center;`;
                const cardStyle = `background:#fff; width:min(920px,90vw); max-height:85vh; overflow:auto; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.2);`;
                const headerStyle = `display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #e5e7eb; position:sticky; top:0; background:#fff; z-index:1;`;
                const bodyStyle = `padding:16px 20px;`;
                const qCardStyle = `border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin:12px 0; background:#fafafa;`;
                const badgeStyle = `display:inline-block; padding:4px 8px; border-radius:12px; font-size:12px; background:#eef2ff; color:#4338ca; margin-left:8px;`;
                const btnStyle = `padding:8px 14px; border:none; border-radius:8px; cursor:pointer;`;

                let html = `<div style="${headerStyle}">` +
                           `<div style="font-size:18px; font-weight:600;">Questions in "${setTitle}"</div>` +
                           `<div>` +
                                `<button style="${btnStyle}; background:#6b7280; color:#fff;" onclick="this.closest('.view-qs-card').parentElement.remove()">Cancel</button>` +
                           `</div>` +
                           `</div>`;

                html += `<div style="${bodyStyle}">`;

                data.questions.forEach((q, idx) => {
                    html += `<div style="${qCardStyle}">`;
                    html += `<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">` +
                            `<div style="font-weight:600;">Question ${idx + 1} <span style="${badgeStyle}">${(q.type || '').toUpperCase()}</span></div>` +
                            `<div style="font-size:12px; color:#6b7280;">Points: ${q.points ?? 0}</div>` +
                            `</div>`;

                    html += `<div style="margin-bottom:10px; color:#111827;">${q.question_text || ''}</div>`;

                    if (q.type === 'mcq') {
                        const opts = [
                            {k:'A', v:q.choice_a},
                            {k:'B', v:q.choice_b},
                            {k:'C', v:q.choice_c},
                            {k:'D', v:q.choice_d}
                        ].filter(o=>o.v !== undefined && o.v !== null);
                        html += '<ul style="list-style:none; padding:0; margin:0;">';
                        opts.forEach(o => {
                            const isCorrect = (q.correct_answer || '').toString().toUpperCase() === o.k;
                            html += `<li style="display:flex; align-items:center; gap:8px; padding:8px 10px; margin:6px 0; border:1px solid #e5e7eb; border-radius:8px; background:${isCorrect ? '#ecfdf5' : '#fff'};">` +
                                    `<span style="width:20px; font-weight:700; color:#374151;">${o.k}.</span>` +
                                    `<span style="flex:1; color:#111827;">${o.v ?? ''}</span>` +
                                    (isCorrect ? `<span style="color:#047857; font-size:12px; font-weight:600;">Correct</span>` : '') +
                                    `</li>`;
                        });
                        html += '</ul>';
                    } else if (q.type === 'matching') {
                        let left = []; let right = []; let matches = [];
                        try { left = Array.isArray(q.left_items) ? q.left_items : JSON.parse(q.left_items || '[]'); } catch(e){}
                        try { right = Array.isArray(q.right_items) ? q.right_items : JSON.parse(q.right_items || '[]'); } catch(e){}
                        try { matches = Array.isArray(q.matches) ? q.matches : JSON.parse(q.correct_pairs || '[]'); } catch(e){}

                        html += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">';
                        html += '<div><div style="font-weight:600; margin-bottom:6px;">Left Items</div>';
                        html += '<ol style="margin:0; padding-left:20px;">' + left.map(it=>`<li style=\"margin:4px 0;\">${it}</li>`).join('') + '</ol></div>';
                        html += '<div><div style="font-weight:600; margin-bottom:6px;">Correct Matches</div>';
                        html += '<ol style="margin:0; padding-left:20px;">';
                        left.forEach((_, i) => {
                            const m = (matches && matches[i]) ? matches[i] : '';
                            html += `<li style="margin:4px 0; color:#065f46;">${m || '<span style=\"color:#9ca3af\">(none)</span>'}</li>`;
                        });
                        html += '</ol></div>';
                        html += '</div>';
                    }

                    html += `</div>`; // q card
                });

                html += `</div>`; // body

                const modal = document.createElement('div');
                modal.setAttribute('style', containerStyle);
                const card = document.createElement('div');
                card.className = 'view-qs-card';
                card.setAttribute('style', cardStyle);
                card.innerHTML = html;
                modal.appendChild(card);
                document.body.appendChild(modal);
            };

            // Primary request
            const formData = new FormData();
            formData.append('action', 'get_questions');
            formData.append('set_id', setId);
            fetch('', { method: 'POST', body: formData })
                .then(res => res.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data && data.success && Array.isArray(data.questions) && data.questions.length > 0) {
                            renderModal(data);
                        } else {
                            // Fallback: use get_set_questions endpoint
                            const params = new URLSearchParams({ action: 'get_set_questions', set_id: setId });
                            return fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
                                .then(r => r.text())
                                .then(tt => {
                                    try { renderModal(JSON.parse(tt)); }
                                    catch(e){ throw new Error('No questions found in this set.'); }
                                });
                        }
                    } catch (e) {
                        throw new Error('Error parsing server response.');
                    }
                })
                .catch(err => alert('Error loading questions: ' + err.message));
        }

        function editSet(setId) {
            const params = new URLSearchParams({action: 'get_set_questions', set_id: setId});
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response is not valid JSON:', text);
                        throw new Error('Server returned invalid JSON. Response: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                if (!data.success) {
                    alert('Failed to load set for editing: ' + (data.error || 'Unknown error'));
                    return;
                }
                // Switch to edit mode
                window.isEditMode = true;
                window.currentEditSetId = setId;
                const submitBtn = document.querySelector('#questionForm button[type="submit"]');
                if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                // Update headings to "Edit Questions"
                const pageTitle = document.getElementById('pageTitle');
                const formHeading = document.getElementById('formTitleHeading');
                if (pageTitle) pageTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Questions';
                if (formHeading) formHeading.textContent = 'Edit Questions';
                const cancelBtn = document.getElementById('cancelEditBtn');
                if (cancelBtn) cancelBtn.style.display = 'inline-block';
                // Set timer/open_at into header fields when provided
                if (data.timer_minutes !== undefined && data.timer_minutes !== null) {
                    const tm = document.getElementById('set_timer');
                    if (tm) tm.value = String(data.timer_minutes || 0);
                }
                if (data.open_at) {
                    const oa = document.getElementById('set_open_at');
                    if (oa) {
                        // convert to local datetime-local format
                        const dt = new Date((data.open_at || '').replace(' ', 'T'));
                        if (!isNaN(dt.getTime())) {
                            const pad = n => String(n).padStart(2,'0');
                            const v = `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                            oa.value = v;
                        }
                    }
                }
                if (data.difficulty !== undefined) {
                    const diffSel = document.getElementById('set_difficulty');
                    if (diffSel) diffSel.value = (data.difficulty || '').toLowerCase();
                }

                // Populate header
                const titleEl = document.getElementById('set_title');
                if (titleEl) titleEl.value = data.set_title || '';
                // Preselect the section if available from server (augment payload if needed)
                if (data.section_id) {
                    const sectionSel = document.getElementById('section_id');
                    if (sectionSel) {
                        let matched = false;
                        Array.from(sectionSel.options).forEach(opt => {
                            if (Number(opt.value) === Number(data.section_id)) {
                                opt.selected = true;
                                matched = true;
                            }
                        });
                        if (!matched) sectionSel.value = String(data.section_id);
                    }
                }

                // Build questions
                const container = document.getElementById('questions-container');
                container.innerHTML = '';
                questionIndex = -1;

                const qs = Array.isArray(data.questions) ? data.questions : [];
                // Sort questions by their original order (if available) or by ID
                qs.sort((a, b) => {
                    if (a.question_order !== undefined && b.question_order !== undefined) {
                        return a.question_order - b.question_order;
                    }
                    return a.id - b.id; // Fallback to ID order
                });
                
                qs.forEach((q, index) => {
                    questionIndex++;
                    console.log(`Loading question ${questionIndex}: ${q.type} (ID: ${q.id}, Order: ${q.question_order || 'N/A'})`);
                    container.insertAdjacentHTML('beforeend', createQuestionHTML(questionIndex));

                    // Hidden id for updates
                    const qi = document.querySelector(`[data-question-index="${questionIndex}"]`);
                    const hiddenId = document.createElement('input');
                    hiddenId.type = 'hidden';
                    hiddenId.name = `questions[${questionIndex}][id]`;
                    hiddenId.value = q.id;
                    qi.appendChild(hiddenId);

                    // Keep only the trash button; remove old checkbox delete toggle

                    // Common fields
                    document.getElementById(`question_text_${questionIndex}`).value = q.question_text || '';
                    document.getElementById(`points_${questionIndex}`).value = q.points || 1;

                    const typeSel = document.getElementById(`type_${questionIndex}`);
                    typeSel.value = q.type;
                    showQuestionTypeSection(questionIndex);

                    if (q.type === 'mcq') {
                        document.getElementById(`choice_a_${questionIndex}`).value = q.choice_a || '';
                        document.getElementById(`choice_b_${questionIndex}`).value = q.choice_b || '';
                        document.getElementById(`choice_c_${questionIndex}`).value = q.choice_c || '';
                        document.getElementById(`choice_d_${questionIndex}`).value = q.choice_d || '';
                        const ca = (q.correct_answer || '').toLowerCase();
                        const r = document.getElementById(`correct_${ca}_${questionIndex}`);
                        if (r) r.checked = true;
                    } else if (q.type === 'matching') {
                        const left = Array.isArray(q.left_items) ? q.left_items : [];
                        const right = Array.isArray(q.right_items) ? q.right_items : [];
                        // Update header label to show range based on pair count
                        const titleEl = qi ? qi.querySelector('.q-title') : null;
                        if (titleEl && left.length > 1) {
                            const start = questionIndex + 1;
                            titleEl.textContent = `Question ${start}–${start + left.length - 1}`;
                        }
                        // Add extra rows only if there are more than 2 items
                        // addMatchingRow will automatically add corresponding columns
                        for (let i = 2; i < left.length; i++) addMatchingRow(questionIndex);
                        const leftInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`);
                        const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                        left.forEach((v,i)=>{ if (leftInputs[i]) leftInputs[i].value = v; });
                        right.forEach((v,i)=>{ if (rightInputs[i]) rightInputs[i].value = v; });
                        
                        // Build a robust normalized matches array from various DB shapes
                        const normalize = (raw) => {
                            let out = [];
                            if (!raw) return out;
                            const r = (typeof raw === 'string') ? (function(){ try { return JSON.parse(raw); } catch(e){ return raw; } })() : raw;
                            if (Array.isArray(r)) {
                                r.forEach(item => {
                                    if (typeof item === 'string') out.push(item);
                                    else if (typeof item === 'number') out.push(right[item] ?? '');
                                    else if (item && typeof item === 'object') {
                                        out.push(item.value ?? item.answer ?? item.right ?? item.right_item ?? '');
                                    }
                                });
                            } else if (r && typeof r === 'object') {
                                Object.keys(r).forEach(k => out.push(r[k]));
                            } else if (typeof r === 'number') {
                                out.push(right[r] ?? '');
                            }
                            return out;
                        };
                        let matches = normalize(q.matches);
                        if (matches.length === 0) matches = normalize(q.correct_pairs);
                        
                        console.log('Matching question data:', {
                            questionId: q.id,
                            leftItems: left,
                            rightItems: right,
                            matches: matches,
                            rawMatches: q.matches,
                            rawCorrectPairs: q.correct_pairs
                        });
                        
                        // Update matching matches after populating the inputs
                        setTimeout(() => {
                            updateMatchingMatches(questionIndex);
                            
                            // Wait for updateMatchingMatches to complete and options to be populated
                            setTimeout(() => {
                                const selects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                                
                                // Ensure all selects have options before proceeding
                                const allSelectsReady = Array.from(selects).every(sel => sel.options.length > 1);
                                if (!allSelectsReady) {
                                    console.log('Not all selects are ready, waiting...');
                                    setTimeout(() => {
                                        // Retry after a short delay
                                        const retrySelects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                                        setMatchesInSelects(retrySelects, matches, questionIndex);
                                    }, 100);
                                    return;
                                }
                                
                                setMatchesInSelects(selects, matches, questionIndex);
                            }, 150);
                        }, 50);
                        
                        function setMatchesInSelects(selects, matches, questionIndex) {
                            // Set the correct matches by finding the right item that matches
                            matches.forEach((val, i) => {
                                const sel = selects[i];
                                if (!sel) return;
                                const target = (val ?? '').toString().trim();
                                
                                console.log(`Setting match ${i}: "${target}"`);
                                
                                // Find the matching right item
                                let selected = false;
                                Array.from(sel.options).forEach(opt => {
                                    if (opt.value.trim().toLowerCase() === target.toLowerCase() || 
                                        opt.textContent.trim().toLowerCase() === target.toLowerCase()) {
                                        opt.selected = true;
                                        selected = true;
                                        console.log(`Selected match: ${target} for select ${i}`);
                                    } else {
                                        opt.selected = false;
                                    }
                                });
                                
                                // If no exact match found, try to find the closest match
                                if (!selected && target) {
                                    Array.from(sel.options).forEach(opt => {
                                        if (opt.value.includes(target) || target.includes(opt.value) ||
                                            opt.textContent.includes(target) || target.includes(opt.textContent)) {
                                            opt.selected = true;
                                            selected = true;
                                            console.log(`Selected closest match: ${opt.value} for target ${target}`);
                                        }
                                    });
                                }
                                
                                // Final fallback - set the value directly
                                if (!selected && target) {
                                    sel.value = target;
                                    console.log(`Fallback selection: ${target} for select ${i}`);
                                }
                                
                                sel.dispatchEvent(new Event('change'));
                            });
                        }
                        
                        // Final points update after everything is loaded
                        setTimeout(() => {
                            const pointsField = document.getElementById(`points_${questionIndex}`);
                            if (pointsField) {
                                const rowCount = left.length;
                                pointsField.value = Math.max(rowCount, 1);
                                console.log(`Final points update: ${rowCount} for edit mode question ${questionIndex}`);
                            }
                        }, 200);
                    }
                });

                if (qs.length === 0) {
                    questionIndex = 0;
                    container.insertAdjacentHTML('beforeend', createQuestionHTML(questionIndex));
                }

                window.scrollTo({top: 0, behavior: 'smooth'});
            })
            .catch(err => {
                console.error('Edit set error:', err);
                alert('Error loading set for editing: ' + err.message);
            });
        }

        function cancelEdit() {
            // Reset edit state
            window.isEditMode = false;
            window.currentEditSetId = null;
            // Reset titles
            const pageTitle = document.getElementById('pageTitle');
            const formHeading = document.getElementById('formTitleHeading');
            if (pageTitle) pageTitle.innerHTML = '<i class="fas fa-plus-circle"></i> Create Questions';
            if (formHeading) formHeading.textContent = 'Create Questions';
            const cancelBtn = document.getElementById('cancelEditBtn');
            if (cancelBtn) cancelBtn.style.display = 'none';
            // Clear questions and add a fresh one
            const container = document.getElementById('questions-container');
            container.innerHTML = '';
            questionIndex = 0;
            container.insertAdjacentHTML('beforeend', createQuestionHTML(0));
            // Reset header fields
            const setTitle = document.getElementById('set_title');
            if (setTitle) setTitle.value = '';
            const sectionSel = document.getElementById('section_id');
            if (sectionSel) sectionSel.value = '';
            // Reset submit button text
            const submitBtn = document.querySelector('#questionForm button[type="submit"]');
            if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save"></i> Create Questions';
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        // Realtime: unique title per section (and teacher)
        (function initRealtimeSetTitleValidation(){
            const titleEl = document.getElementById('set_title');
            const sectionSel = document.getElementById('section_id');
            if (!titleEl || !sectionSel) return;
            let debounce;
            const check = () => {
                const title = (titleEl.value || '').trim();
                const sectionId = sectionSel.value || '';
                clearError(titleEl, 'err_set_title');
                if (!title || !sectionId) return; // wait until both available
                clearTimeout(debounce);
                debounce = setTimeout(() => {
                    const params = new URLSearchParams({
                        action: 'check_set_title',
                        set_title: title,
                        section_id: sectionId
                    });
                    // If editing, include current set to exclude
                    if (window.isEditMode && window.currentEditSetId) {
                        params.append('exclude_set_id', window.currentEditSetId);
                    }
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params.toString()
                    }).then(r => r.json()).then(resp => {
                        if (resp && resp.exists) {
                            showError(titleEl, 'err_set_title', 'A set with this title already exists in the selected section.');
                        } else {
                            clearError(titleEl, 'err_set_title');
                        }
                    }).catch(()=>{});
                }, 350);
            };
            titleEl.addEventListener('input', check);
            sectionSel.addEventListener('change', check);
        })();

        function deleteSet(setId) {
            if (confirm('Delete this set?')) {
                fetch('', {
                    method: 'POST',
                    body: new URLSearchParams({action: 'delete_question_set', set_id: setId})
                }).then(res => res.json()).then(data => {
                    if (data.success) location.reload();
                });
            }
        }
        
        function filterBySection() {
            const selectedSection = document.getElementById('sectionFilter').value;
            const tableRows = document.querySelectorAll('.question-bank-table tbody tr');
            
            tableRows.forEach(row => {
                // Each set row is followed by an optional details row. Hide/show both together.
                const sectionCell = row.querySelector('.section-name');
                if (!sectionCell) return;
                const sectionName = sectionCell.textContent.trim();
                const match = sectionName === selectedSection;
                row.style.display = match ? '' : 'none';
                const next = row.nextElementSibling;
                if (next && !next.querySelector('.set-title')) {
                    next.style.display = match ? '' : 'none';
                }
            });
        }

        // Apply initial filter on load so default selection (e.g., Rizal) is enforced immediately
        document.addEventListener('DOMContentLoaded', function(){
            if (document.getElementById('sectionFilter')) {
                filterBySection();
            }
        });
    </script>
    </div>
</body>
</html>
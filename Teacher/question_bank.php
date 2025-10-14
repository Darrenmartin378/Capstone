<?php
require_once 'includes/teacher_init.php';
require_once 'includes/QuestionHandler.php';

try {
    $questionHandler = new QuestionHandler($conn);
} catch (Exception $e) {
    error_log('Failed to create QuestionHandler: ' . $e->getMessage());
    die('Failed to initialize question handler');
}

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
            case 'get_set_questions':
                $setId = (int)($_POST['set_id'] ?? 0);
                if ($setId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid set']);
                    exit;
                }
                $questions = $questionHandler->getQuestionsForSet($setId);
                echo json_encode(['success' => true, 'questions' => $questions]);
                exit;

            case 'archive_question_set':
                $setId = (int)($_POST['set_id'] ?? 0);
                if ($setId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid set ID']);
                    exit;
                }
                
                // Check if teacher owns this set
                $stmt = $conn->prepare("SELECT teacher_id FROM question_sets WHERE id = ?");
                $stmt->bind_param("i", $setId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    echo json_encode(['success' => false, 'error' => 'Set not found']);
                    exit;
                }
                $set = $result->fetch_assoc();
                if ($set['teacher_id'] != $_SESSION['teacher_id']) {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    exit;
                }
                
                // Archive the set
                $stmt = $conn->prepare("UPDATE question_sets SET is_archived = 1 WHERE id = ?");
                $stmt->bind_param("i", $setId);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Set archived successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to archive set']);
                }
                exit;

            case 'unarchive_question_set':
                $setId = (int)($_POST['set_id'] ?? 0);
                if ($setId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid set ID']);
                    exit;
                }
                
                // Check if teacher owns this set
                $stmt = $conn->prepare("SELECT teacher_id FROM question_sets WHERE id = ?");
                $stmt->bind_param("i", $setId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    echo json_encode(['success' => false, 'error' => 'Set not found']);
                    exit;
                }
                $set = $result->fetch_assoc();
                if ($set['teacher_id'] != $_SESSION['teacher_id']) {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    exit;
                }
                
                // Unarchive the set
                $stmt = $conn->prepare("UPDATE question_sets SET is_archived = 0 WHERE id = ?");
                $stmt->bind_param("i", $setId);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Set unarchived successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to unarchive set']);
                }
                exit;

            case 'bulk_archive_sets':
                $setIds = $_POST['set_ids'] ?? [];
                if (!is_array($setIds) || empty($setIds)) {
                    echo json_encode(['success' => false, 'error' => 'No sets selected']);
                    exit;
                }
                
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($setIds as $setId) {
                    $setId = (int)$setId;
                    if ($setId <= 0) continue;
                    
                    // Check if teacher owns this set
                    $stmt = $conn->prepare("SELECT teacher_id FROM question_sets WHERE id = ?");
                    $stmt->bind_param("i", $setId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows === 0) {
                        $errorCount++;
                        continue;
                    }
                    $set = $result->fetch_assoc();
                    if ($set['teacher_id'] != $_SESSION['teacher_id']) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Archive the set
                    $stmt = $conn->prepare("UPDATE question_sets SET is_archived = 1 WHERE id = ?");
                    $stmt->bind_param("i", $setId);
                    if ($stmt->execute()) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Successfully archived {$successCount} set(s)",
                    'success_count' => $successCount,
                    'error_count' => $errorCount
                ]);
                exit;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                exit;
        }
    } catch (Throwable $e) {
        if (ob_get_length()) { ob_clean(); }
        error_log('Question bank fatal error: ' . $e->getMessage());
        error_log('Fatal error file: ' . $e->getFile() . ' line: ' . $e->getLine());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
        exit;
    }
}

// Get question sets for all teacher sections (exclude archived)
$questionSets = [];
$hasArchiveCol = false;
try {
    $chkArch = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'is_archived'");
    $hasArchiveCol = $chkArch && $chkArch->num_rows > 0;
} catch (Throwable $e) { /* ignore */ }

// Normalize legacy "[ARCHIVED] " prefixes: move to is_archived flag and strip prefix
try {
    if (!$hasArchiveCol) {
        $conn->query("ALTER TABLE question_sets ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
        $hasArchiveCol = true;
    }
    // Set flag for any prefixed titles
    $conn->query("UPDATE question_sets SET is_archived = 1 WHERE set_title LIKE '[ARCHIVED] %'");
    // Strip the prefix from titles
    $conn->query("UPDATE question_sets SET set_title = TRIM(LEADING '[ARCHIVED] ' FROM set_title) WHERE set_title LIKE '[ARCHIVED] %'");
} catch (Throwable $e) { /* ignore normalization errors */ }

foreach ($teacherSections as $section) {
    $sets = $questionHandler->getQuestionSets($_SESSION['teacher_id'], $section['id']);
    foreach ($sets as $set) {
        // Skip archived sets
        $isArchived = false;
        if ($hasArchiveCol) {
            $isArchived = (int)($set['is_archived'] ?? 0) === 1;
        } else {
            $title = (string)($set['set_title'] ?? '');
            $isArchived = strpos($title, '[ARCHIVED] ') === 0;
        }
        if ($isArchived) { continue; }

        $set['section_name'] = $section['section_name'] ?: $section['name'];
        $questionSets[] = $set;
    }
}

// Include the teacher layout
require_once 'includes/teacher_layout.php';
render_teacher_header('question_bank.php', $teacherName, 'Question Bank');
?>

<style>
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
        
        .filters-container {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .search-filter, .section-filter, .type-filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-filter label, .section-filter label, .type-filter label {
            font-weight: 500;
            color: #374151;
            font-size: 14px;
            margin: 0;
        }
        
        .search-filter input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            font-size: 14px;
            color: #374151;
            transition: border-color 0.2s ease;
            min-width: 350px;
        }
        
        .search-filter input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .search-filter input:hover {
            border-color: #9ca3af;
        }
        
        .section-filter select, .type-filter select {
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
        
        .section-filter select:focus, .type-filter select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .section-filter select:hover, .type-filter select:hover {
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
        }

        .section-name {
            color: #6b7280;
            font-size: 14px;
        }

        .total-points {
            text-align: center;
        }

        .points-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .created-date {
            text-align: center;
        }

        .date-text {
            display: block;
            font-size: 14px;
            color: #374151;
            font-weight: 500;
        }

        .time-text {
            display: block;
            font-size: 12px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .actions {
            text-align: center;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }

        .btn-view {
            background: #3b82f6;
            color: white;
        }

        .btn-view:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .btn-responses {
            background: #8b5cf6;
            color: white;
        }

        .btn-responses:hover {
            background: #7c3aed;
            transform: translateY(-1px);
        }

        .btn-archive {
            background: #f59e0b;
            color: white;
        }

        .btn-archive:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        .btn-edit {
            background: #10b981;
            color: white;
        }

        .btn-edit:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
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
            
            .btn {
                padding: 6px 8px;
                font-size: 11px;
            }
            
            .filters-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .search-filter, .section-filter, .type-filter {
                width: 100%;
            }
            
            .search-filter input {
                min-width: 120px;
                width: 100%;
            }
            
            .section-filter select, .type-filter select {
                min-width: 120px;
                width: 100%;
            }
        }
</style>

<div class="container">
    <!-- Question Sets List -->
    <div class="question-sets">
        <div class="question-bank-header">
            <h2>Question Bank</h2>
            <div class="filters-container">
                <div class="search-filter">
                    <label for="searchInput">Search:</label>
                    <input type="text" id="searchInput" placeholder="Search question title..." onkeyup="applyFilters()" onchange="applyFilters()">
                </div>
                <div class="section-filter">
                    <label for="sectionFilter">Filter by Section:</label>
                    <select id="sectionFilter" onchange="applyFilters()">
                        <option value="">All Sections</option>
                        <?php 
                        // Get all sections that the teacher handles
                        foreach ($teacherSections as $section): 
                            $sectionName = $section['section_name'] ?: $section['name'];
                        ?>
                            <option value="<?php echo htmlspecialchars($sectionName); ?>">
                                <?php echo htmlspecialchars($sectionName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="type-filter">
                    <label for="typeFilter">Filter by Type:</label>
                    <select id="typeFilter" onchange="applyFilters()">
                        <option value="">All Types</option>
                        <option value="Assessment Question">Assessment Question</option>
                        <option value="Comprehension Questions">Comprehension Questions</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div style="display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-top:16px;">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='teacher_practice_tests.php'" style="background: #10b981; color: white;">
                <i class="fas fa-clipboard-list"></i> Practice Tests
            </button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='archived_question_sets.php'" style="background: #6b7280; color: white;">
                <i class="fas fa-archive"></i> View Archived Sets
            </button>
            <button type="button" id="bulkArchiveBtn" class="btn btn-archive" style="opacity:.6; pointer-events:none;">
                <i class="fas fa-box-archive"></i> Archive Selected
            </button>
        </div>
        
        <div class="table-container">
            <table class="question-bank-table">
                <thead>
                    <tr>
                        <th style="width:44px; text-align:center;">
                            <input type="checkbox" id="selectAllSets" />
                        </th>
                        <th>Question Set</th>
                        <th>Section</th>
                        <th>Points</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($questionSets)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                            <h3 style="margin: 0 0 8px 0; color: #374151;">No Question Sets Found</h3>
                            <p style="margin: 0; font-size: 14px;">Create your first question set to get started.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($questionSets as $set): ?>
                        <tr>
                            <td style="text-align:center;">
                                <input type="checkbox" class="select-set" value="<?php echo $set['id']; ?>" />
                            </td>
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
                                    <button type="button" class="btn btn-responses" onclick="viewStudentResponses(<?php echo $set['id']; ?>, '<?php echo htmlspecialchars($set['set_title']); ?>')" title="View Student Responses">
                                        <i class="fas fa-users"></i>
                                    </button>
                                    <button type="button" class="btn btn-archive" onclick="archiveSet(<?php echo $set['id']; ?>, true)" title="Archive Set">
                                        <i class="fas fa-box-archive"></i>
                                    </button>
                                    <button type="button" class="btn btn-edit" onclick="editSet(<?php echo $set['id']; ?>)" title="Edit Set">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
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
                const headerStyle = `padding:20px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;`;
                const bodyStyle = `padding:20px; max-height:60vh; overflow-y:auto;`;
                const footerStyle = `padding:16px 20px; border-top:1px solid #e5e7eb; text-align:right;`;
                
                // Question item styles
                const questionStyle = `margin-bottom:20px; padding:16px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb;`;
                const questionTitleStyle = `font-weight:600; color:#1f2937; margin-bottom:8px; font-size:16px;`;
                const questionTextStyle = `color:#374151; margin-bottom:12px; line-height:1.5;`;
                const choicesStyle = `margin-left:16px; color:#6b7280;`;
                const choiceStyle = `margin-bottom:4px;`;
                const correctStyle = `color:#059669; font-weight:600;`;
                const pointsStyle = `display:inline-block; background:#10b981; color:white; padding:2px 8px; border-radius:12px; font-size:12px; margin-left:8px;`;
                
                let html = `<div style="${containerStyle}">
                    <div style="${cardStyle}">
                        <div style="${headerStyle}">
                            <h3 style="margin:0; color:#1f2937;">${setTitle} - Questions</h3>
                            <button onclick="this.closest('.modal-container').remove()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#6b7280;">&times;</button>
                        </div>
                        <div style="${bodyStyle}">`;
                
                data.questions.forEach((q, idx) => {
                    const qText = (q.question_text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    const points = Number.isFinite(q.points) ? q.points : 1;
                    const type = (q.type || 'mcq').toUpperCase();
                    
                    html += `<div style="${questionStyle}">
                        <div style="${questionTitleStyle}">Q${idx + 1}. [${type}] ${points} pt(s)</div>
                        <div style="${questionTextStyle}">${qText}</div>`;
                    
                    if (q.type === 'mcq') {
                        const choices = ['A', 'B', 'C', 'D'];
                        html += `<div style="${choicesStyle}">`;
                        choices.forEach(choice => {
                            const choiceText = (q[`choice_${choice.toLowerCase()}`] || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            const isCorrect = q.correct_answer === choice;
                            const style = isCorrect ? `${choiceStyle} ${correctStyle}` : choiceStyle;
                            html += `<div style="${style}">${choice}. ${choiceText} ${isCorrect ? '✓' : ''}</div>`;
                        });
                        html += `</div>`;
                    } else if (q.type === 'matching') {
                        // Debug: Log the matching question data structure
                        console.log('Matching question data:', q);
                        
                        // Parse left and right items
                        let leftItems = [];
                        let rightItems = [];
                        let correctPairs = [];
                        
                        // Handle different data formats for left_items
                        if (Array.isArray(q.left_items)) {
                            leftItems = q.left_items;
                        } else if (typeof q.left_items === 'string') {
                            try {
                                leftItems = JSON.parse(q.left_items);
                            } catch (e) {
                                leftItems = [q.left_items];
                            }
                        }
                        
                        // Handle different data formats for right_items
                        if (Array.isArray(q.right_items)) {
                            rightItems = q.right_items;
                        } else if (typeof q.right_items === 'string') {
                            try {
                                rightItems = JSON.parse(q.right_items);
                            } catch (e) {
                                rightItems = [q.right_items];
                            }
                        }
                        
                        // Parse correct pairs from matches array (from QuestionHandler)
                        if (q.matches && Array.isArray(q.matches)) {
                            // q.matches contains the indices of correct pairs
                            q.matches.forEach((rightIndex, leftIndex) => {
                                if (leftItems[leftIndex] && rightItems[rightIndex]) {
                                    correctPairs.push({
                                        left: leftItems[leftIndex],
                                        right: rightItems[rightIndex]
                                    });
                                }
                            });
                        }
                        // Fallback: try to parse from answer_key if matches is not available
                        else if (q.answer_key) {
                            try {
                                let answerKey = q.answer_key;
                                if (typeof answerKey === 'string') {
                                    // Remove brackets if present
                                    answerKey = answerKey.replace(/[\[\]]/g, '');
                                    const indices = answerKey.split(',').map(i => parseInt(i.trim()));
                                    
                                    // Create pairs based on indices
                                    indices.forEach((rightIndex, leftIndex) => {
                                        if (leftItems[leftIndex] && rightItems[rightIndex]) {
                                            correctPairs.push({
                                                left: leftItems[leftIndex],
                                                right: rightItems[rightIndex]
                                            });
                                        }
                                    });
                                }
                            } catch (e) {
                                console.error('Error parsing answer_key:', e);
                            }
                        }
                        
                        html += `<div style="${choicesStyle}">
                            <div style="margin-bottom: 8px;">
                                <strong>Left Items:</strong> 
                                <span style="color: #1f2937; font-weight: 500;">${leftItems.join(', ')}</span>
                            </div>
                            <div style="margin-bottom: 8px;">
                                <strong>Right Items:</strong> 
                                <span style="color: #1f2937; font-weight: 500;">${rightItems.join(', ')}</span>
                            </div>
                            <div style="margin-bottom: 8px;">
                                <strong>Correct Pairs:</strong>
                                <div style="margin-top: 4px; padding: 8px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px;">
                                    ${correctPairs.length > 0 ? 
                                        correctPairs.map(p => `<span style="display: inline-block; margin: 2px 4px; padding: 4px 8px; background: #10b981; color: white; border-radius: 4px; font-size: 12px;">${p.left} → ${p.right}</span>`).join('') : 
                                        '<span style="color: #6b7280; font-style: italic;">No pairs defined</span>'
                                    }
                                </div>
                            </div>
                        </div>`;
                    } else if (q.type === 'essay') {
                        const rubric = (q.rubric || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        html += `<div style="${choicesStyle}">
                            <div><strong>Rubric:</strong> ${rubric}</div>
                        </div>`;
                    }
                    
                    html += `</div>`;
                });
                
                html += `</div>
                    <div style="${footerStyle}">
                        <button onclick="this.closest('.modal-container').remove()" style="background:#6b7280; color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer;">Close</button>
                    </div>
                </div>`;
                
                // Add modal container class for easy removal
                const modal = document.createElement('div');
                modal.className = 'modal-container';
                modal.innerHTML = html;
                document.body.appendChild(modal);
            };
            
            // Fetch questions for the set
            const params = new URLSearchParams({ action: 'get_set_questions', set_id: setId });
            fetch('', { method: 'POST', body: params })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderModal(data);
                    } else {
                        alert('Error loading questions: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Network error loading questions');
                });
        }

        function viewStudentResponses(setId, setTitle) {
            window.location.href = `view_student_responses.php?set_id=${setId}&title=${encodeURIComponent(setTitle)}`;
        }

        function editSet(setId) {
            // Check if this is a comprehension question set
            const row = document.querySelector(`input[value="${setId}"]`).closest('tr');
            const titleCell = row.querySelector('.set-title');
            const titleText = titleCell.textContent.trim();
            
            if (titleText.includes('Comprehension Questions')) {
                // Redirect to teacher_content.php for comprehension questions
                window.location.href = `teacher_content.php?edit_comprehension=${setId}`;
            } else {
                // Redirect to clean_question_creator.php for other question types
                window.location.href = `clean_question_creator.php?edit_set=${setId}`;
            }
        }

        function archiveSet(setId, doArchive) {
            const verb = doArchive ? 'Archive' : 'Unarchive';
            if (confirm(`${verb} this set?`)) {
                const params = new URLSearchParams({action: doArchive ? 'archive_question_set' : 'unarchive_question_set', set_id: setId});
                fetch('', { method: 'POST', body: params })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) location.reload();
                        else alert('Error: ' + (data.error || `Failed to ${verb.toLowerCase()}`));
                    })
                    .catch(err => alert('Network error: ' + err.message));
            }
        }

        function applyFilters() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const selectedSection = document.getElementById('sectionFilter').value;
            const selectedType = document.getElementById('typeFilter').value;
            const tableRows = document.querySelectorAll('.question-bank-table tbody tr');
            
            tableRows.forEach(row => {
                const sectionCell = row.querySelector('.section-name');
                const titleCell = row.querySelector('.set-title');
                
                if (!sectionCell || !titleCell) return;
                
                const sectionName = sectionCell.textContent.trim();
                const titleText = titleCell.textContent.trim();
                
                // Check search filter
                const searchMatch = searchTerm === '' || titleText.toLowerCase().includes(searchTerm);
                
                // Check section filter
                const sectionMatch = selectedSection === '' || sectionName === selectedSection;
                
                // Check type filter
                let typeMatch = true;
                if (selectedType !== '') {
                    if (selectedType === 'Assessment Question') {
                        // Show items that don't contain "Comprehension Questions"
                        typeMatch = !titleText.includes('Comprehension Questions');
                    } else if (selectedType === 'Comprehension Questions') {
                        // Show items that contain "Comprehension Questions"
                        typeMatch = titleText.includes('Comprehension Questions');
                    }
                }
                
                // Show row only if all filters match
                const shouldShow = searchMatch && sectionMatch && typeMatch;
                row.style.display = shouldShow ? '' : 'none';
            });
        }

        // Bulk selection functionality
        (function bulkSelection(){
            const selectAll = document.getElementById('selectAllSets');
            const bulkArchiveBtn = document.getElementById('bulkArchiveBtn');
            const table = document.querySelector('.question-bank-table');
            if (!selectAll || !table) return;

            const updateState = () => {
                const checkboxes = table.querySelectorAll('.select-set:not([disabled])');
                const checked = table.querySelectorAll('.select-set:checked');
                const count = checked.length;
                
                selectAll.checked = count > 0 && count === checkboxes.length;
                selectAll.indeterminate = count > 0 && count < checkboxes.length;
                
                bulkArchiveBtn.style.opacity = count > 0 ? '1' : '.6';
                bulkArchiveBtn.style.pointerEvents = count > 0 ? 'auto' : 'none';
            };

            selectAll.addEventListener('change', (e) => {
                const checkboxes = table.querySelectorAll('.select-set:not([disabled])');
                checkboxes.forEach(cb => cb.checked = e.target.checked);
                updateState();
            });

            table.addEventListener('change', (e) => {
                if (e.target.classList.contains('select-set')) {
                    updateState();
                }
            });

            bulkArchiveBtn.addEventListener('click', () => {
                const checked = table.querySelectorAll('.select-set:checked');
                if (checked.length === 0) return;
                
                const setIds = Array.from(checked).map(cb => cb.value);
                if (confirm(`Archive ${setIds.length} selected set(s)?`)) {
                    const params = new URLSearchParams();
                    params.append('action', 'bulk_archive_sets');
                    setIds.forEach(id => params.append('set_ids[]', id));
                    
                    fetch('', { method: 'POST', body: params })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + (data.error || 'Failed to archive sets'));
                            }
                        })
                        .catch(err => alert('Network error: ' + err.message));
                }
            });

            updateState();
        })();
</script>

<?php
render_teacher_footer();
?>

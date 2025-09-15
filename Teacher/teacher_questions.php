<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';
require_once __DIR__ . '/includes/notification_helper.php';

$flash = '';

// Handle success message from redirect
if (isset($_GET['success'])) {
    $flash = $_GET['success'];
}

// Handle AJAX requests first (both GET and POST)
if (isset($_GET['action']) || (isset($_POST['action']))) {
    $action = $_GET['action'] ?? $_POST['action'];
    
    if ($action === 'get_question_data') {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id > 0) {
    try {
        $stmt = $conn->prepare('SELECT *, COALESCE(options_json, options, "{}") as options FROM question_bank WHERE id = ? AND teacher_id = ?');
        $stmt->bind_param('ii', $id, $teacherId);
        $stmt->execute();
        $result = $stmt->get_result();
    } catch (Exception $e) {
        // Fallback if options_json column doesn't exist
        $stmt = $conn->prepare('SELECT *, COALESCE(options, "{}") as options FROM question_bank WHERE id = ? AND teacher_id = ?');
        $stmt->bind_param('ii', $id, $teacherId);
        $stmt->execute();
        $result = $stmt->get_result();
    }
            
            if ($question = $result->fetch_assoc()) {
                header('Content-Type: application/json');
                echo json_encode($question);
                exit;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Question not found']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_question') {
        $questions = $_POST['questions'] ?? [];
        $set_title = trim($_POST['set_title'] ?? '');
        $section_id = (int)($_POST['section_id'] ?? 0);
        
        if ($set_title === '' || empty($questions) || $section_id <= 0) {
            $flash = 'Set title, target section, and at least one question required.';
        } else {
            // Verify that the teacher handles this section
            $section_check = $conn->prepare("SELECT id FROM teacher_sections WHERE teacher_id = ? AND section_id = ?");
            $section_check->bind_param('ii', $teacherId, $section_id);
            $section_check->execute();
            $section_result = $section_check->get_result();
            
            if ($section_result->num_rows === 0) {
                $flash = 'You are not assigned to the selected section.';
            } else {
                $validQuestions = 0;
                
            foreach ($questions as $q) {
                $type = $q['type'] ?? '';
                $text = trim($q['text'] ?? '');
                    
                    // Skip validation for matching type since Column A serves as the question
                    if ($type !== 'matching' && strlen($text) < 5) {
                        continue;
                    }
                    
                    $options = null;
                    $answer = '';
                    
                    if ($type === 'multiple_choice') {
                        $mcOptions = $q['options'] ?? [];
                        $validOptions = 0;
                        $processedOptions = [];
                        
                        foreach ($mcOptions as $key => $value) {
                            $cleanValue = trim($value);
                            if (strlen($cleanValue) > 0) {
                                $optionKey = is_numeric($key) ? chr(65 + $key) : $key;
                                $processedOptions[$optionKey] = $cleanValue;
                                $validOptions++;
                            }
                        }
                        
                        if ($validOptions >= 2) {
                            $options = json_encode($processedOptions);
                            $answer = $q['answer'] ?? '';
                        } else {
                            continue;
                        }
                        
                    } elseif ($type === 'matching') {
                        $colA = $q['options']['A'] ?? $q['options']['lefts'] ?? [];
                        $colB = $q['options']['B'] ?? $q['options']['rights'] ?? [];
                        
                        $validLefts = [];
                        $validRights = [];
                        
                        foreach ($colA as $item) {
                            $cleanItem = trim($item);
                            if (strlen($cleanItem) > 0) {
                                $validLefts[] = $cleanItem;
                            }
                        }
                        
                        foreach ($colB as $item) {
                            $cleanItem = trim($item);
                            if (strlen($cleanItem) > 0) {
                                $validRights[] = $cleanItem;
                            }
                        }
                        
                        if (count($validLefts) >= 2 && count($validRights) >= 2) {
                            $text = 'Match the items in Column A with the corresponding items in Column B.';
                            
                            $options = json_encode([
                                'lefts' => $validLefts,
                                'rights' => $validRights
                            ]);
                            
                            // Create proper answer format for matching questions
                            $answerMap = [];
                            if (isset($q['answer']) && is_array($q['answer'])) {
                                foreach ($q['answer'] as $index => $rightIndex) {
                                    if (isset($validLefts[$index]) && isset($validRights[$rightIndex])) {
                                        $answerMap[$validLefts[$index]] = $validRights[$rightIndex];
                                    }
                                }
                            }
                            $answer = json_encode($answerMap);
                            
                            // Debug logging
                            error_log("Matching question debug:");
                            error_log("Valid lefts: " . print_r($validLefts, true));
                            error_log("Valid rights: " . print_r($validRights, true));
                            error_log("Answer array: " . print_r($q['answer'] ?? 'not set', true));
                            error_log("Final answer: " . $answer);
                        } else {
                            continue;
                        }
                        
                    } elseif ($type === 'essay') {
                        $wordLimit = (int)($q['word_limit'] ?? 0);
                        $rubrics = trim($q['rubrics'] ?? '');
                        
                        if ($wordLimit < 10 || strlen($rubrics) < 5) {
                            continue;
                        }
                        
                        $options = json_encode([
                            'word_limit' => $wordLimit,
                            'rubrics' => $rubrics
                        ]);
                        $answer = $q['answer'] ?? '';
                    }
                    
                    if ($options !== null) {
                        try {
                            $stmt = $conn->prepare('INSERT INTO question_bank (teacher_id, section_id, set_title, question_type, question_category, question_text, options_json, answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                            $questionCategory = 'comprehension';
                            $stmt->bind_param('iissssss', $teacherId, $section_id, $set_title, $type, $questionCategory, $text, $options, $answer);
                            if ($stmt->execute()) {
                                $validQuestions++;
                            }
                        } catch (Exception $e) {
                            // Fallback if options_json column doesn't exist
                            $stmt = $conn->prepare('INSERT INTO question_bank (teacher_id, section_id, set_title, question_type, question_category, question_text, options, answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                            $questionCategory = 'comprehension';
                            $stmt->bind_param('iissssss', $teacherId, $section_id, $set_title, $type, $questionCategory, $text, $options, $answer);
                            if ($stmt->execute()) {
                                $validQuestions++;
                            }
                        }
                    }
                }
                
                if ($validQuestions > 0) {
                    $flash = "Successfully uploaded {$validQuestions} question(s) to Question Bank.";
                    
                    // Create notification for students in the section
                    if ($section_id) {
                        createNotificationForSection(
                            $conn, 
                            $teacherId, 
                            $section_id, 
                            'comprehension', 
                            'New Comprehension Questions Available', 
                            "Your teacher has created new comprehension questions for \"$set_title\". Check the Questions section to take the quiz.",
                            null
                        );
                    }
                } else {
                    $totalQuestions = count($questions);
                    $flash = "No valid questions were uploaded. Please check your question format and try again. (Total questions submitted: {$totalQuestions})";
                }
            }
        }
        
        // Redirect to prevent form resubmission on refresh
        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=' . urlencode($flash));
        exit();
    }

    if ($action === 'delete_question') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM question_bank WHERE id = ? AND teacher_id = ?');
            $stmt->bind_param('ii', $id, $teacherId);
            if ($stmt->execute()) {
                $flash = 'Question deleted successfully.';
            } else {
                $flash = 'Error deleting question.';
            }
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    if ($action === 'delete_set') {
        $set_title = trim($_POST['set_title'] ?? '');
        $section_id = (int)($_POST['section_id'] ?? 0);
        
        if ($set_title && $section_id > 0) {
            $stmt = $conn->prepare('DELETE FROM question_bank WHERE set_title = ? AND section_id = ? AND teacher_id = ?');
            $stmt->bind_param('sii', $set_title, $section_id, $teacherId);
            if ($stmt->execute()) {
                $deleted_count = $stmt->affected_rows;
                $flash = "Set '{$set_title}' and {$deleted_count} question(s) deleted successfully.";
            } else {
                $flash = 'Error deleting set.';
            }
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    if ($action === 'bulk_delete') {
        $question_ids = $_POST['question_ids'] ?? [];
        $deleted_count = 0;
        
        if (!empty($question_ids)) {
            foreach ($question_ids as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $stmt = $conn->prepare('DELETE FROM question_bank WHERE id = ? AND teacher_id = ?');
                    $stmt->bind_param('ii', $id, $teacherId);
                    if ($stmt->execute()) {
                        $deleted_count++;
                    }
                }
            }
        }
        
        if ($deleted_count > 0) {
            $flash = "{$deleted_count} question(s) deleted successfully.";
        } else {
            $flash = 'No questions were deleted.';
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    if ($action === 'update_question') {
        $id = (int)($_POST['id'] ?? 0);
        $question_text = trim($_POST['question_text'] ?? '');
        $options = $_POST['options'] ?? '';
        $answer = $_POST['answer'] ?? '';
        
        if ($id > 0 && !empty($question_text)) {
            $stmt = $conn->prepare('UPDATE question_bank SET question_text = ?, options_json = ?, answer = ? WHERE id = ? AND teacher_id = ?');
            $stmt->bind_param('sssii', $question_text, $options, $answer, $id, $teacherId);
            if ($stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Question updated successfully']);
                exit;
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to update question']);
        exit;
    }
}

// Fetch all questions for this teacher
try {
    $questions = $conn->query("
        SELECT qb.*, s.name as section_name,
               COALESCE(qb.options_json, qb.options, '{}') as options
        FROM question_bank qb 
        LEFT JOIN sections s ON qb.section_id = s.id 
        WHERE qb.teacher_id = $teacherId 
        AND (qb.question_category = 'comprehension' OR qb.question_category IS NULL)
        ORDER BY qb.created_at DESC
    ");
} catch (Exception $e) {
    // Fallback if options_json column doesn't exist
    $questions = $conn->query("
        SELECT qb.*, s.name as section_name,
               COALESCE(qb.options, '{}') as options
        FROM question_bank qb 
        LEFT JOIN sections s ON qb.section_id = s.id 
        WHERE qb.teacher_id = $teacherId 
        AND (qb.question_category = 'comprehension' OR qb.question_category IS NULL)
        ORDER BY qb.created_at DESC
    ");
}

$grouped = [];
if ($questions && $questions->num_rows > 0) {
    while ($q = $questions->fetch_assoc()) {
        // First level: Section + Date
        $sectionDateKey = ($q['section_name'] ?? 'No Section') . '_' . date('Y-m-d', strtotime($q['created_at']));
        
        // Second level: Set Title (if exists, otherwise use "Default Set")
        $setTitle = !empty($q['set_title']) ? $q['set_title'] : 'Default Set';
        
        if (!isset($grouped[$sectionDateKey])) {
            $grouped[$sectionDateKey] = [];
        }
        if (!isset($grouped[$sectionDateKey][$setTitle])) {
            $grouped[$sectionDateKey][$setTitle] = [];
        }
        
        $grouped[$sectionDateKey][$setTitle][] = $q;
    }
}
?>
<?php render_teacher_header('questions', $teacherName, 'Question Management'); ?>
<style>
body {
    background: #f6f8fc;
}
.container {
    max-width: 900px;
    margin: 32px auto;
    padding: 0 16px;
}
.flash {
    background: #fde68a;
    color: #b45309;
    border-radius: 8px;
    padding: 12px 18px;
    margin-bottom: 18px;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(251,191,36,0.08);
    position: relative;
}
.flash-close {
    position: absolute;
    right: 14px;
    top: 10px;
    background: none;
    border: none;
    font-size: 1.3rem;
    color: #b45309;
    cursor: pointer;
    line-height: 1;
}
.flash-close:hover {
    color: #a16207;
}
.card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    margin-bottom: 32px;
    padding: 0;
}
.card-header {
    background: #eef2ff;
    padding: 18px 24px;
    border-radius: 12px 12px 0 0;
    font-size: 1.15rem;
    font-weight: 600;
    color: #3730a3;
    border-bottom: 1px solid #e0e7ff;
}
.card-body {
    padding: 24px;
}
.btn {
    border: none;
    border-radius: 6px;
    padding: 7px 18px;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-primary {
    background: #6366f1;
    color: #fff;
}
.btn-primary:hover {
    background: #4338ca;
}
.btn-secondary {
    background: #e0e7ff;
    color: #3730a3;
}
.btn-secondary:hover {
    background: #c7d2fe;
}
.btn-danger {
    background: #f87171;
    color: #fff;
}
.btn-danger:hover {
    background: #dc2626;
}
input[type="text"], select, textarea {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 7px 10px;
    font-size: 1rem;
    margin-top: 4px;
    margin-bottom: 12px;
    width: 100%;
    box-sizing: border-box;
    background: #f9fafb;
}
label {
    font-weight: 500;
    color: #3730a3;
    margin-bottom: 2px;
    display: block;
}
.question-block {
    border: 1px solid #e0e0e0;
    background: #f3f4f6;
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
}
.question-block > button {
    margin-top: 8px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 18px;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
}
th, td {
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}
th {
    background: #eef2ff;
    color: #3730a3;
    font-weight: 600;
}
tr:last-child td {
    border-bottom: none;
}
.muted {
    color: #6b7280;
    font-size: 1.1rem;
    margin-bottom: 8px;
}
.set-group {
    margin-bottom: 24px;
}
.set-toggle {
    background: #eef2ff;
    color: #4338ca;
    border: none;
    border-radius: 8px;
    padding: 12px 18px;
    font-size: 1.08em;
    font-weight: 600;
    width: 100%;
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s;
    margin-bottom: 2px;
}
.set-toggle:hover {
    background: #c7d2fe;
}
.set-toggle .chevron {
    margin-left: auto;
    transition: transform 0.2s;
}
.set-toggle.active .chevron {
    transform: rotate(180deg);
}
.set-content {
    animation: fadeIn 0.3s;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@media (max-width: 700px) {
    .container { max-width: 100%; }
    .card-body { padding: 12px; }
    th, td { padding: 7px 6px; }
}
</style>
<div class="container">
    <?php if (!empty($flash)): ?>
        <div class="flash" id="flash-message">
            <?php echo h($flash); ?>
            <button type="button" class="flash-close" onclick="document.getElementById('flash-message').style.display='none';">&times;</button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><strong><i class="fas fa-question-circle"></i> Upload Comprehension Questions</strong></div>
        <div class="card-body">
            <form method="POST" id="questionForm">
                <input type="hidden" name="action" value="add_question">
                <div>
                    <label>Question Set Title</label>
                    <input type="text" name="set_title" required placeholder="e.g. Unit 1 Quiz">
                </div>
                <div>
                    <label>Target Section <span style="color:red;">*</span></label>
                    <select name="section_id" required>
                        <option value="">Select a section</option>
                        <?php
                        $teacher_sections = $conn->query("
                            SELECT s.id, s.name 
                            FROM sections s 
                            JOIN teacher_sections ts ON s.id = ts.section_id 
                            WHERE ts.teacher_id = $teacherId 
                            ORDER BY s.name
                        ");
                        if ($teacher_sections && $teacher_sections->num_rows > 0) {
                            while ($section = $teacher_sections->fetch_assoc()) {
                                echo '<option value="' . $section['id'] . '">' . h($section['name']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div id="questions-list"></div>
                <div style="margin-top:10px;">
                    <button type="button" class="btn btn-secondary" id="addQuestionBtn"><i class="fas fa-plus"></i> Add New Question Form</button>
                </div>
                <div style="text-align:right; margin-top:10px;">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-upload"></i> Upload All Questions</button>
                </div>
            </form>

            <h3 class="muted" style="margin-top:18px;"><i class="fas fa-database"></i> Question Bank</h3>
            
            <?php if (!empty($grouped)): ?>
                <?php foreach ($grouped as $sectionDateKey => $setGroups): ?>
                    <div class="set-group">
                        <button class="set-toggle active" onclick="toggleSet(this)">
                            <i class="fas fa-folder"></i>
                            <span><?php echo htmlspecialchars($sectionDateKey); ?></span>
                            <i class="fas fa-chevron-down chevron"></i>
                        </button>
                        <div class="set-content" style="display:block;">
                            <?php foreach ($setGroups as $setTitle => $setQuestions): ?>
                                <div class="set-subgroup" style="margin: 10px 0 20px 20px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <button class="set-toggle active" onclick="toggleSet(this)" style="background: #f0f4ff; color: #4338ca; padding: 8px 14px; font-size: 0.95em; flex: 1;">
                                            <i class="fas fa-folder-open"></i>
                                            <span><?php echo htmlspecialchars($setTitle); ?></span>
                                            <i class="fas fa-chevron-down chevron"></i>
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_set">
                                            <input type="hidden" name="set_title" value="<?php echo htmlspecialchars($setTitle); ?>">
                                            <input type="hidden" name="section_id" value="<?php echo (int)$setQuestions[0]['section_id']; ?>">
                                            <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Are you sure you want to delete the entire set \'<?php echo htmlspecialchars($setTitle); ?>\' and all its questions?')" style="padding: 4px 8px; font-size: 0.8em;">
                                                <i class="fas fa-trash-alt"></i> Delete Set
                                            </button>
                                        </form>
                                    </div>
                                    <div class="set-content" style="display:block; margin-top: 10px;">
                                        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 15px; margin-left: 10px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb;">
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <input type="checkbox" id="selectAll_<?php echo md5($setTitle); ?>" onchange="toggleSelectAll(this, '<?php echo md5($setTitle); ?>')" style="margin-right: 5px;">
                                                    <label for="selectAll_<?php echo md5($setTitle); ?>" style="font-weight: 600; color: #374151; margin: 0;">Select All</label>
                                                </div>
                                                <button class="btn btn-danger btn-sm" onclick="bulkDelete('<?php echo md5($setTitle); ?>')" style="padding: 4px 8px; font-size: 0.8em;">
                                                    <i class="fas fa-trash"></i> Delete Selected
                                                </button>
                                            </div>
                                <?php foreach ($setQuestions as $q): ?>
                                                <div style="display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #f3f4f6;">
                                                    <input type="checkbox" name="question_ids[]" value="<?php echo (int)$q['id']; ?>" class="question-checkbox" data-set="<?php echo md5($setTitle); ?>" style="margin-right: 10px;">
                                                    <div style="flex: 1;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <?php
                                                            if ($q['question_type'] === 'multiple_choice') echo '<i class="fas fa-list-ul" style="color:#6366f1;"></i><span style="color:#6366f1;font-weight:600;font-size:0.9em;">Multiple Choice</span>';
                                                            elseif ($q['question_type'] === 'matching') echo '<i class="fas fa-link" style="color:#f59e42;"></i><span style="color:#f59e42;font-weight:600;font-size:0.9em;">Matching</span>';
                                                            elseif ($q['question_type'] === 'essay') echo '<i class="fas fa-edit" style="color:#10b981;"></i><span style="color:#10b981;font-weight:600;font-size:0.9em;">Essay</span>';
                                                            else echo '<i class="fas fa-question-circle"></i><span>' . htmlspecialchars($q['question_type']) . '</span>';
                                                            ?>
                                                        </div>
                                                        <div style="margin-top: 4px; color: #374151; font-size: 0.9em;">
                                                            <?php echo htmlspecialchars($q['question_text']); ?>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; gap: 5px;">
                                                        <button class="btn btn-primary btn-sm" onclick="editQuestion(<?php echo (int)$q['id']; ?>, '<?php echo htmlspecialchars($q['question_type']); ?>')" style="padding: 4px 8px; font-size: 0.8em;">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_question">
                                                <input type="hidden" name="id" value="<?php echo (int)$q['id']; ?>">
                                                            <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Are you sure you want to delete this question?')" style="padding: 4px 8px; font-size: 0.8em;">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                            </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="muted">No questions yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
let questionBlockCount = 0;
document.getElementById('addQuestionBtn').onclick = function() {
    questionBlockCount++;
    const list = document.getElementById('questions-list');
    const block = document.createElement('div');
    block.className = 'question-block';

    block.innerHTML = `
        <div>
            <label>Type</label>
            <select name="questions[${questionBlockCount}][type]" onchange="renderOptions(this, ${questionBlockCount})" required>
                <option value="">Select type</option>
                <option value="multiple_choice">Multiple Choice</option>
                <option value="matching">Matching</option>
                <option value="essay">Essay</option>
            </select>
        </div>
        <div id="question-field-${questionBlockCount}">
            <label>Question</label>
            <input type="text" name="questions[${questionBlockCount}][text]" id="question-input-${questionBlockCount}">
        </div>
        <div id="options-block-${questionBlockCount}"></div>
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()"><i class="fas fa-times"></i> Remove</button>
    `;
    list.appendChild(block);

    const typeSelect = block.querySelector('select');
    const questionInput = block.querySelector(`#question-input-${questionBlockCount}`);
    
    typeSelect.addEventListener('change', function() {
        const questionField = block.querySelector(`#question-field-${questionBlockCount}`);
        if (this.value === 'matching') {
            questionField.style.display = 'none';
            questionInput.removeAttribute('required');
        } else {
            questionField.style.display = 'block';
            questionInput.setAttribute('required', 'required');
        }
    });
};

function renderOptions(sel, idx) {
    const val = sel.value;
    const optionsBlock = document.getElementById(`options-block-${idx}`);
    optionsBlock.innerHTML = '';
    if (val === 'multiple_choice') {
        let html = `<label>Options <span style="color:red;">*</span></label>`;
        for (let i = 0; i < 4; i++) {
            const letter = String.fromCharCode(65 + i);
            html += `<input type="text" name="questions[${idx}][options][${letter}]" placeholder="Option ${letter}" required>`;
        }
        html += `<label>Answer Key <span style="color:red;">*</span></label>
            <select name="questions[${idx}][answer]" required>
                <option value="">Select correct answer</option>
                <option value="A">Option A</option>
                <option value="B">Option B</option>
                <option value="C">Option C</option>
                <option value="D">Option D</option>
            </select>`;
        optionsBlock.innerHTML = html;
    } else if (val === 'matching') {
        let html = `
            <label>Column A (Left Items) <span style="color:red;">*</span></label>
            <div id="matching-colA-${idx}"></div>
            <button type="button" class="btn btn-secondary" onclick="addMatchingItem(${idx}, 'A')"><i class="fas fa-plus"></i> Add Item to Column A</button>
            <label>Column B (Right Items) <span style="color:red;">*</span></label>
            <div id="matching-colB-${idx}"></div>
            <button type="button" class="btn btn-secondary" onclick="addMatchingItem(${idx}, 'B')"><i class="fas fa-plus"></i> Add Item to Column B</button>
            <div id="matching-answers-${idx}" style="margin-top:16px;"></div>
        `;
        optionsBlock.innerHTML = html;
        addMatchingItem(idx, 'A');
        addMatchingItem(idx, 'B');
        updateMatchingAnswers(idx);
    } else if (val === 'essay') {
        optionsBlock.innerHTML = `
            <label>Word Limit <span style="color:red;">*</span></label>
            <input type="number" name="questions[${idx}][word_limit]" placeholder="Minimum 50 words" min="50" required>
            <label>Rubrics <span style="color:red;">*</span></label>
            <textarea name="questions[${idx}][rubrics]" placeholder="Define grading criteria (minimum 10 characters)" required></textarea>
            <label>Sample Answer <span style="font-size:0.95em;color:#6b7280;">(optional)</span></label>
            <textarea name="questions[${idx}][answer]" placeholder="Sample answer for reference"></textarea>`;
    }
}

function addMatchingItem(idx, col) {
    const colBlock = document.getElementById(`matching-col${col}-${idx}`);
    const itemIdx = colBlock ? colBlock.children.length : 0;
    const div = document.createElement('div');
    div.style.display = 'flex';
    div.style.gap = '8px';
    div.style.marginBottom = '6px';
    div.innerHTML = `
        <input type="text" name="questions[${idx}][options][${col}][${itemIdx}]" placeholder="Item ${col === 'A' ? itemIdx + 1 : String.fromCharCode(97 + itemIdx)}" required>
        <button type="button" class="btn btn-danger" style="padding:2px 8px;" onclick="this.parentElement.remove(); updateMatchingAnswers(${idx});"><i class="fas fa-times"></i></button>
    `;
    colBlock.appendChild(div);
    updateMatchingAnswers(idx);
}

function updateMatchingAnswers(idx) {
    const colA = document.getElementById(`matching-colA-${idx}`);
    const colB = document.getElementById(`matching-colB-${idx}`);
    const answersBlock = document.getElementById(`matching-answers-${idx}`);
    if (!colA || !colB || !answersBlock) return;

    let bOptions = [];
    for (let i = 0; i < colB.children.length; i++) {
        const input = colB.children[i].querySelector('input');
        bOptions.push({ idx: i, label: input ? input.value || String.fromCharCode(97 + i) : String.fromCharCode(97 + i) });
    }

    let html = `<label>Answer Key (Select correct match for each item in Column A)</label>`;
    for (let i = 0; i < colA.children.length; i++) {
        const inputA = colA.children[i].querySelector('input');
        html += `<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <span style="min-width:80px;">${inputA ? (inputA.value || 'Item ' + (i+1)) : 'Item ' + (i+1)}</span>
            <select name="questions[${idx}][answer][${i}]" required>
                <option value="">Select match</option>`;
        bOptions.forEach(opt => {
            html += `<option value="${opt.idx}">${opt.label}</option>`;
        });
        html += `</select>
        </div>`;
    }
    answersBlock.innerHTML = html;
}

document.addEventListener('input', function(e) {
    if (e.target.closest('[id^="matching-colB-"]')) {
        const idx = e.target.closest('[id^="matching-colB-"]').id.split('-')[2];
        updateMatchingAnswers(idx);
    }
});

function toggleSet(btn) {
    // Find the content div by looking in the parent container
    const parent = btn.closest('.set-group, .set-subgroup');
    const content = parent ? parent.querySelector('.set-content') : null;
    
    if (content) {
        // Toggle the display
        if (content.style.display === 'none') {
        content.style.display = 'block';
            btn.classList.add('active');
    } else {
        content.style.display = 'none';
            btn.classList.remove('active');
        }
    }
}

function toggleSelectAll(selectAllCheckbox, setHash) {
    const checkboxes = document.querySelectorAll(`input.question-checkbox[data-set="${setHash}"]`);
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function bulkDelete(setHash) {
    const checkboxes = document.querySelectorAll(`input.question-checkbox[data-set="${setHash}"]:checked`);
    
    if (checkboxes.length === 0) {
        alert('Please select at least one question to delete.');
        return;
    }
    
    const questionIds = Array.from(checkboxes).map(cb => cb.value);
    const confirmMessage = `Are you sure you want to delete ${questionIds.length} selected question(s)?`;
    
    if (confirm(confirmMessage)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_delete';
        form.appendChild(actionInput);
        
        questionIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'question_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}

function editQuestion(questionId, questionType) {
    // Create modal
    const editModal = document.createElement('div');
    editModal.className = 'modal';
    editModal.style.cssText = `
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
    `;
    
    editModal.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 10px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;" data-question-type="${questionType}">
            <h3 style="margin-top: 0; color: #1e40af;">Edit Question</h3>
            <p><strong>Question Type:</strong> ${questionType}</p>
            <p><strong>Question ID:</strong> ${questionId}</p>
            
            <div style="margin: 20px 0;">
                <label><strong>Question Text:</strong></label>
                <textarea id="editQuestionText" style="width: 100%; height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin-top: 5px;"></textarea>
            </div>
            
            <div id="editOptionsContainer" style="margin: 20px 0;">
                <!-- Options will be loaded here based on question type -->
            </div>
            
            <div style="text-align: right; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                <button onclick="closeEditModal()" style="background: #6b7280; color: white; border: none; padding: 12px 24px; border-radius: 6px; margin-right: 10px; cursor: pointer; font-weight: 500; transition: background 0.2s;">Cancel</button>
                <button onclick="saveEditedQuestion(${questionId})" style="background: #10b981; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 500; transition: background 0.2s;">Save Changes</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(editModal);
    
    // Load question data
    loadQuestionData(questionId, questionType);
}

function closeEditModal() {
    const modal = document.querySelector('.modal');
    if (modal) {
        modal.remove();
    }
}

function loadQuestionData(questionId, questionType) {
    const container = document.getElementById('editOptionsContainer');
    container.innerHTML = '<p>Loading question data...</p>';
    
    fetch(`?action=get_question_data&id=${questionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                return;
            }
            
            // Populate question text
            document.getElementById('editQuestionText').value = data.question_text || '';
            
            // Parse options
            let options = {};
            try {
                if (data.options) {
                    options = JSON.parse(data.options);
                }
            } catch (e) {
                console.error('Error parsing options:', e);
                options = {};
            }
            
            const actualQuestionType = data.question_type || questionType;
            
            if (actualQuestionType === 'multiple_choice') {
                container.innerHTML = `
                    <label><strong>Options:</strong></label>
                    <div style="margin: 10px 0;">
                        <input type="text" id="editOptionA" placeholder="Option A" value="${options.A || ''}" style="width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">
                        <input type="text" id="editOptionB" placeholder="Option B" value="${options.B || ''}" style="width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">
                        <input type="text" id="editOptionC" placeholder="Option C" value="${options.C || ''}" style="width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">
                        <input type="text" id="editOptionD" placeholder="Option D" value="${options.D || ''}" style="width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">
                    </div>
                    <div>
                        <label><strong>Correct Answer:</strong></label>
                        <select id="editCorrectAnswer" style="padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">
                            <option value="A" ${data.answer === 'A' ? 'selected' : ''}>A</option>
                            <option value="B" ${data.answer === 'B' ? 'selected' : ''}>B</option>
                            <option value="C" ${data.answer === 'C' ? 'selected' : ''}>C</option>
                            <option value="D" ${data.answer === 'D' ? 'selected' : ''}>D</option>
                        </select>
                    </div>
                `;
            } else if (actualQuestionType === 'matching') {
                const lefts = options.lefts || [];
                const rights = options.rights || [];
                
                let leftInputs = '';
                let rightInputs = '';
                
                lefts.forEach((item, index) => {
                    leftInputs += `<input type="text" id="editLeft${index}" value="${item}" style="width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">`;
                });
                
                rights.forEach((item, index) => {
                    rightInputs += `<input type="text" id="editRight${index}" value="${item}" style="width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">`;
                });
                
                container.innerHTML = `
                    <label><strong>Column A (Left Items):</strong></label>
                    <div style="margin: 10px 0;">
                        ${leftInputs || '<input type="text" id="editLeft0" placeholder="Item 1" style="width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">'}
                    </div>
                    <label><strong>Column B (Right Items):</strong></label>
                    <div style="margin: 10px 0;">
                        ${rightInputs || '<input type="text" id="editRight0" placeholder="Item 1" style="width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">'}
                    </div>
                `;
            } else if (actualQuestionType === 'essay') {
                container.innerHTML = `
                    <div style="margin: 10px 0;">
                        <label><strong>Word Limit:</strong></label>
                        <input type="number" id="editWordLimit" value="${options.word_limit || 50}" style="width: 100px; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">
                    </div>
                    <div style="margin: 10px 0;">
                        <label><strong>Rubrics:</strong></label>
                        <textarea id="editRubrics" style="width: 100%; height: 80px; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px;">${options.rubrics || ''}</textarea>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading question data:', error);
            container.innerHTML = '<p style="color: red;">Error loading question data. Please refresh the page and try again.</p>';
        });
}

function saveEditedQuestion(questionId) {
    const questionText = document.getElementById('editQuestionText').value.trim();
    
    const modalDiv = document.querySelector('.modal div[data-question-type]');
    const questionType = modalDiv ? modalDiv.getAttribute('data-question-type') : '';
    
    if (!questionText || questionText.length < 5) {
        alert('Question text must be at least 5 characters long.');
        return;
    }
    
    let options = {};
    let answer = '';
    
    if (questionType === 'multiple_choice') {
        options = {
            A: document.getElementById('editOptionA')?.value?.trim() || '',
            B: document.getElementById('editOptionB')?.value?.trim() || '',
            C: document.getElementById('editOptionC')?.value?.trim() || '',
            D: document.getElementById('editOptionD')?.value?.trim() || ''
        };
        answer = document.getElementById('editCorrectAnswer')?.value || '';
        
        const validOptions = Object.values(options).filter(opt => opt.length > 0);
        if (validOptions.length < 2) {
            alert('Please provide at least 2 valid options for multiple choice questions.');
            return;
        }
        if (!answer) {
            alert('Please select a correct answer.');
            return;
        }
    } else if (questionType === 'matching') {
        const leftInputs = document.querySelectorAll('input[id^="editLeft"]');
        const rightInputs = document.querySelectorAll('input[id^="editRight"]');
        
        const lefts = Array.from(leftInputs).map(input => input.value.trim()).filter(val => val.length > 0);
        const rights = Array.from(rightInputs).map(input => input.value.trim()).filter(val => val.length > 0);
        
        if (lefts.length < 2 || rights.length < 2) {
            alert('Please provide at least 2 items in each column for matching questions.');
            return;
        }
        
        options = { lefts, rights };
        answer = JSON.stringify({});
    } else if (questionType === 'essay') {
        const wordLimit = parseInt(document.getElementById('editWordLimit')?.value || 50);
        const rubrics = document.getElementById('editRubrics')?.value?.trim() || '';
        
        if (wordLimit < 10) {
            alert('Word limit must be at least 10 words.');
            return;
        }
        if (rubrics.length < 5) {
            alert('Rubrics must be at least 5 characters long.');
            return;
        }
        
        options = { word_limit: wordLimit, rubrics };
        answer = '';
    }
    
    // Show loading state
    const saveBtn = document.querySelector('button[onclick*="saveEditedQuestion"]');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;
    
    // Send update request
    const formData = new FormData();
    formData.append('action', 'update_question');
    formData.append('id', questionId);
    formData.append('question_text', questionText);
    formData.append('options', JSON.stringify(options));
    formData.append('answer', answer);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Question updated successfully!');
            closeEditModal();
            window.location.reload();
        } else {
            alert('Error updating question: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating question. Please try again.');
    })
    .finally(() => {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
}
</script>
<?php render_teacher_footer(); ?>
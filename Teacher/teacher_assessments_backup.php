<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';

$flash = '';

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_question_bank') {
    $sectionId = (int)($_GET['section_id'] ?? 0);
    
    $whereClause = "WHERE qb.teacher_id = $teacherId";
    if ($sectionId > 0) {
        $whereClause .= " AND qb.section_id = $sectionId";
    }
    
    $questions = $conn->query("
        SELECT qb.*, s.name as section_name,
               COALESCE(qb.options_json, qb.options, '{}') as options
        FROM question_bank qb 
        LEFT JOIN sections s ON qb.section_id = s.id 
        $whereClause 
        ORDER BY qb.created_at DESC
    ");
    
    $questionList = [];
    if ($questions && $questions->num_rows > 0) {
        while ($q = $questions->fetch_assoc()) {
            $questionList[] = $q;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['questions' => $questionList]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_assessment') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $theme = isset($_POST['theme']) ? json_encode($_POST['theme']) : null;
        if ($title === '') {
            $flash = 'Title is required.';
        } else {
            $stmt = $conn->prepare('INSERT INTO assessments (teacher_id, title, description, theme_settings) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isss', $teacherId, $title, $description, $theme);
            $stmt->execute();
            $assessmentId = $stmt->insert_id;

            // Handle questions - each question can be from bank or new
            if (!empty($_POST['questions']) && is_array($_POST['questions'])) {
                foreach ($_POST['questions'] as $q) {
                    $source = $q['source'] ?? 'new';
                    $type = $q['type'] ?? 'multiple_choice';
                    $text = trim($q['text'] ?? '');
                    $optionsJson = !empty($q['options']) ? json_encode($q['options']) : null;
                    $answer = $q['answer'] ?? null;
                    
                    if ($source === 'from_bank' && !empty($q['question_id'])) {
                        // Question from question bank
                        $questionId = (int)$q['question_id'];
                        if ($questionId > 0) {
                            // Get question from question bank
                            $stmtQ = $conn->prepare('SELECT * FROM question_bank WHERE id = ? AND teacher_id = ?');
                            $stmtQ->bind_param('ii', $questionId, $teacherId);
                            $stmtQ->execute();
                            $result = $stmtQ->get_result();
                            
                        if ($question = $result->fetch_assoc()) {
                            // Insert into assessment_questions
                            $options = $question['options_json'] ?? $question['options'] ?? '{}';
                            $stmtA = $conn->prepare('INSERT INTO assessment_questions (assessment_id, question_type, question_text, options, answer) VALUES (?, ?, ?, ?, ?)');
                            $stmtA->bind_param('issss', $assessmentId, $question['question_type'], $question['question_text'], $options, $question['answer']);
                            $stmtA->execute();
                        }
                        }
                    } elseif ($source === 'new' && $text !== '') {
                        // New question
                        $stmtQ = $conn->prepare('INSERT INTO assessment_questions (assessment_id, question_type, question_text, options, answer) VALUES (?, ?, ?, ?, ?)');
                        $stmtQ->bind_param('issss', $assessmentId, $type, $text, $optionsJson, $answer);
                        $stmtQ->execute();
                    }
                }
            }
            // Redirect to prevent form resubmission
            header('Location: teacher_assessments.php?saved=1');
            exit;
        }
    }

    if ($_POST['action'] === 'delete_assessment') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM assessments WHERE id = ? AND teacher_id = ?');
            $stmt->bind_param('ii', $id, $teacherId);
            $stmt->execute();
            // Redirect to prevent form resubmission
            header('Location: teacher_assessments.php?deleted=1');
            exit;
        }
    }
}

$assessments = $conn->query("SELECT * FROM assessments WHERE teacher_id = $teacherId ORDER BY created_at DESC");

render_teacher_header('assessments', $teacherName, 'Assessments');
?>
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
    transition: all 0.3s ease;
}
.flash-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}
.flash-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}
.flash-close {
    position: absolute;
    right: 14px;
    top: 10px;
    background: none;
    border: none;
    font-size: 1.3rem;
    color: inherit;
    cursor: pointer;
    line-height: 1;
    opacity: 0.7;
    transition: opacity 0.2s;
}
.flash-close:hover {
    opacity: 1;
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
.grid {
    display: grid;
    gap: 18px;
}
.grid-3 {
    grid-template-columns: repeat(3, 1fr);
}
.question-block {
    border: 1px solid #e0e0e0;
    background: #f3f4f6;
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    position: relative;
}
.question-block .remove-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #f87171;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    font-size: 1.1rem;
    cursor: pointer;
}
.question-block .remove-btn:hover {
    background: #dc2626;
}
.muted {
    color: #6b7280;
    font-size: 1.1rem;
    margin-bottom: 8px;
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
@media (max-width: 700px) {
    .container { max-width: 100%; }
    .card-body { padding: 12px; }
    th, td { padding: 7px 6px; }
    .grid-3 { grid-template-columns: 1fr; }
}
</style>
<div class="container">
    <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
        <div class="flash flash-success" id="success-message">
            ✅ Assessment saved successfully!
            <button type="button" class="flash-close" onclick="closeFlashMessage('success-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
        <div class="flash flash-success" id="deleted-message">
            ✅ Assessment deleted successfully!
            <button type="button" class="flash-close" onclick="closeFlashMessage('deleted-message')">&times;</button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><strong><i class="fas fa-clipboard-list"></i> Build Custom Tests</strong></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_assessment">
                <label>Title</label>
                <input type="text" name="title" required>
                <label>Description</label>
                <textarea name="description" placeholder="Instructions for students..."></textarea>
                
                <!-- Questions Section -->
                <div style="margin: 20px 0;">
                    <h4 style="margin: 0 0 16px 0; color: #374151;">Questions</h4>
                    <div id="questions-container"></div>
                    <div style="margin: 16px 0;">
                        <button type="button" class="btn btn-secondary" id="addQuestionBtn"><i class="fas fa-plus"></i> Add New Question Form</button>
                    </div>
                </div>
                
                <div style="text-align:right;">
                    <button class="btn btn-primary" type="submit" onclick="prepareFormSubmission()"><i class="fas fa-save"></i> Save Assessment</button>
                </div>
            </form>

            <h3 class="muted" style="margin-top:18px;"><i class="fas fa-archive"></i> Your Assessments</h3>
            <table>
                <thead><tr><th>Title</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ($assessments && $assessments->num_rows > 0): while ($a = $assessments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo h($a['title']); ?></td>
                        <td><?php echo h($a['created_at']); ?></td>
                        <td>
                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this assessment?');">
                                <input type="hidden" name="action" value="delete_assessment">
                                <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                                <button class="btn btn-danger" type="submit"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="3">No assessments yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
let questionBlockCount = 0;

document.getElementById('addQuestionBtn').onclick = function() {
    questionBlockCount++;
    const list = document.getElementById('questions-container');
    const block = document.createElement('div');
    block.className = 'question-block';

    block.innerHTML = `
        <div>
            <label>Question Source</label>
            <select name="questions[${questionBlockCount}][source]" onchange="toggleQuestionSource(this, ${questionBlockCount})" required>
                <option value="">Select source</option>
                <option value="new">Create New Question</option>
                <option value="from_bank">Select from Question Bank</option>
            </select>
        </div>
        <div id="question-type-field-${questionBlockCount}" style="display: none;">
            <label>Type</label>
            <select name="questions[${questionBlockCount}][type]" onchange="renderOptions(this, ${questionBlockCount})">
                <option value="">Select type</option>
                <option value="multiple_choice">Multiple Choice</option>
                <option value="matching">Matching</option>
                <option value="essay">Essay</option>
            </select>
        </div>
        <div id="question-field-${questionBlockCount}" style="display: none;">
            <label>Question</label>
            <input type="text" name="questions[${questionBlockCount}][text]" id="question-input-${questionBlockCount}">
        </div>
        <div id="options-block-${questionBlockCount}"></div>
        <div id="question-bank-selection-${questionBlockCount}" style="display: none;"></div>
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()"><i class="fas fa-times"></i> Remove</button>
    `;
    list.appendChild(block);
};

function toggleQuestionSource(select, idx) {
    const source = select.value;
    const typeField = document.getElementById(`question-type-field-${idx}`);
    const questionField = document.getElementById(`question-field-${idx}`);
    const optionsBlock = document.getElementById(`options-block-${idx}`);
    const bankSelection = document.getElementById(`question-bank-selection-${idx}`);
    const typeSelect = typeField.querySelector('select');
    const questionInput = questionField.querySelector('input');
    
    if (source === 'new') {
        typeField.style.display = 'block';
        questionField.style.display = 'block';
        optionsBlock.style.display = 'block';
        bankSelection.style.display = 'none';
        // Add required attributes when fields become visible
        if (typeSelect) typeSelect.setAttribute('required', 'required');
    } else if (source === 'from_bank') {
        typeField.style.display = 'none';
        questionField.style.display = 'none';
        optionsBlock.style.display = 'none';
        bankSelection.style.display = 'block';
        // Remove required attributes when fields are hidden
        if (typeSelect) typeSelect.removeAttribute('required');
        if (questionInput) questionInput.removeAttribute('required');
        loadQuestionBankForSelection(idx);
    } else {
        typeField.style.display = 'none';
        questionField.style.display = 'none';
        optionsBlock.style.display = 'none';
        bankSelection.style.display = 'none';
        // Remove required attributes when fields are hidden
        if (typeSelect) typeSelect.removeAttribute('required');
        if (questionInput) questionInput.removeAttribute('required');
    }
}

function loadQuestionBankForSelection(questionIndex) {
    const container = document.getElementById(`question-bank-selection-${questionIndex}`);
    container.innerHTML = '<p>Loading questions...</p>';
    
    // Fetch questions from question bank
    fetch(`?action=get_question_bank&section_id=`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                return;
            }
            
            if (data.questions && data.questions.length > 0) {
                let html = '<h5>Select ONE Question from Bank:</h5>';
                html += '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; background: #fff;">';
                
                data.questions.forEach((question, index) => {
                    html += `
                        <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin: 8px 0; cursor: pointer; transition: all 0.2s; display: flex; align-items: flex-start; gap: 12px;"
                             onclick="selectQuestionFromBank(${questionIndex}, ${question.id}, '${question.question_type}', '${question.question_text.replace(/'/g, "\\'")}', '${(question.options_json || question.options || '{}').replace(/'/g, "\\'")}', '${(question.answer || '').replace(/'/g, "\\'")}')"
                             onmouseover="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#3b82f6';"
                             onmouseout="this.style.backgroundColor='#fff'; this.style.borderColor='#e2e8f0';">
                            <input type="radio" name="question_selection_${questionIndex}" value="${question.id}" style="margin-top: 2px; cursor: pointer;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span style="font-size: 0.8em; padding: 2px 6px; border-radius: 4px; background: #e0e7ff; color: #3730a3;">
                                        ${question.question_type.replace('_', ' ').toUpperCase()}
                                    </span>
                                </div>
                                <div style="font-size: 0.9em; color: #374151;">
                                    ${question.question_text}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                html += '<p style="margin-top: 8px; font-size: 0.9em; color: #6b7280; font-style: italic;">💡 Click on any question card to select it</p>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p>No questions found in the question bank.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading questions:', error);
            container.innerHTML = '<p style="color: red;">Error loading questions from question bank.</p>';
        });
}

function selectQuestionFromBank(questionIndex, questionId, questionType, questionText, options, answer) {
    // Check the radio button for this question
    const radioButton = document.querySelector(`input[name="question_selection_${questionIndex}"][value="${questionId}"]`);
    if (radioButton) {
        radioButton.checked = true;
    }
    
    const container = document.getElementById(`question-bank-selection-${questionIndex}`);
    container.innerHTML = `
        <div style="padding: 12px; background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 6px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span style="font-size: 0.8em; padding: 2px 6px; border-radius: 4px; background: #0ea5e9; color: white;">
                    ${questionType.replace('_', ' ').toUpperCase()} (FROM BANK)
                </span>
                <span style="font-size: 0.8em; padding: 2px 6px; border-radius: 4px; background: #10b981; color: white;">
                    ✓ SELECTED
                </span>
            </div>
            <div style="font-weight: 500; color: #374151; margin-bottom: 8px;">
                ${questionText}
            </div>
            <button type="button" class="btn btn-secondary" onclick="loadQuestionBankForSelection(${questionIndex})" style="font-size: 0.8em; padding: 4px 8px;">
                <i class="fas fa-exchange-alt"></i> Change Selection
            </button>
        </div>
        <input type="hidden" name="questions[${questionIndex}][question_id]" value="${questionId}">
        <input type="hidden" name="questions[${questionIndex}][type]" value="${questionType}">
        <input type="hidden" name="questions[${questionIndex}][text]" value="${questionText}">
        <input type="hidden" name="questions[${questionIndex}][options]" value="${options}">
        <input type="hidden" name="questions[${questionIndex}][answer]" value="${answer}">
    `;
}

function renderOptions(sel, idx) {
    const val = sel.value;
    const optionsBlock = document.getElementById(`options-block-${idx}`);
    const questionInput = document.getElementById(`question-input-${idx}`);
    
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
        if (questionInput) questionInput.setAttribute('required', 'required');
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
        if (questionInput) questionInput.removeAttribute('required');
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
        if (questionInput) questionInput.setAttribute('required', 'required');
    } else {
        // Clear any required attributes if no type is selected
        if (questionInput) questionInput.removeAttribute('required');
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

function prepareFormSubmission() {
    // Validate that at least one question is added
    const questionsContainer = document.getElementById('questions-container');
    if (questionsContainer.children.length === 0) {
        alert('Please add at least one question to the assessment.');
        return false;
    }
    
    // Validate each question block
    const questionBlocks = questionsContainer.querySelectorAll('.question-block');
    for (let block of questionBlocks) {
        const sourceSelect = block.querySelector('select[name*="[source]"]');
        if (!sourceSelect || !sourceSelect.value) {
            alert('Please select a question source for all questions.');
            return false;
        }
        
        if (sourceSelect.value === 'new') {
            const typeSelect = block.querySelector('select[name*="[type]"]');
            if (!typeSelect || !typeSelect.value) {
                alert('Please select a question type for all new questions.');
                return false;
            }
            
            const questionInput = block.querySelector('input[name*="[text]"]');
            if (typeSelect.value !== 'matching' && (!questionInput || !questionInput.value.trim())) {
                alert('Please provide question text for all questions.');
                return false;
            }
        } else if (sourceSelect.value === 'from_bank') {
            const hiddenQuestionId = block.querySelector('input[name*="[question_id]"]');
            if (!hiddenQuestionId || !hiddenQuestionId.value) {
                alert('Please select a question from the question bank.');
                return false;
            }
        }
    }
    
    return true;
}
</script>
<?php render_teacher_footer(); ?>



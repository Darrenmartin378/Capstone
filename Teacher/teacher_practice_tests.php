<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/teacher_layout.php';
require_once __DIR__ . '/includes/notification_helper.php';

$flash = '';

render_teacher_header('teacher_practice_tests.php', $_SESSION['teacher_name'] ?? 'Teacher', 'Practice Sets');

// Get teacher ID from session
$teacherId = $_SESSION['teacher_id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_practice_questions':
                $setTitle = trim($_POST['set_title'] ?? '');
                $sectionId = (int)($_POST['section_id'] ?? 0);
                $questions = $_POST['questions'] ?? [];
                
                if ($setTitle && $sectionId > 0 && !empty($questions)) {
                    $validQuestions = 0;
                    $errors = [];
                    
                    foreach ($questions as $index => $q) {
                        $type = $q['type'] ?? '';
                        $text = trim($q['text'] ?? '');
                        $options = null;
                        $answer = '';
                        
                        if (empty($text) || empty($type)) {
                            $errors[] = "Question " . ($index + 1) . " is missing required fields";
                            continue;
                        }
                        
                        if ($type === 'multiple_choice') {
                            $optionsArray = [];
                            for ($i = 1; $i <= 4; $i++) {
                                $option = trim($q["option_$i"] ?? '');
                                if ($option) {
                                    $optionsArray["option_$i"] = $option;
                                }
                            }
                            $options = json_encode($optionsArray);
                            $answer = $q['correct_answer'] ?? '';
                            
                        } elseif ($type === 'matching') {
                            $leftItems = [];
                            $rightItems = [];
                            $matches = [];
                            
                            for ($i = 1; $i <= 4; $i++) {
                                $leftItem = trim($q["left_item_$i"] ?? '');
                                $rightItem = trim($q["right_item_$i"] ?? '');
                                if ($leftItem && $rightItem) {
                                    $leftItems[] = $leftItem;
                                    $rightItems[] = $rightItem;
                                    $matches[$leftItem] = $rightItem;
                                }
                            }
                            $options = json_encode([
                                'left_items' => $leftItems,
                                'right_items' => $rightItems
                            ]);
                            $answer = json_encode($matches);
                            
                        } elseif ($type === 'essay') {
                            $rubrics = trim($q['rubrics'] ?? '');
                            $wordLimit = (int)($q['word_limit'] ?? 0);
                            
                            $options = json_encode([
                                'word_limit' => $wordLimit,
                                'rubrics' => $rubrics
                            ]);
                            $answer = $rubrics;
                        }
                        
                        try {
                            $stmt = $conn->prepare('INSERT INTO question_bank (teacher_id, section_id, set_title, question_type, question_category, question_text, options_json, answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                            $questionCategory = 'practice';
                            $stmt->bind_param('iissssss', $teacherId, $sectionId, $setTitle, $type, $questionCategory, $text, $options, $answer);
                            
                            if ($stmt->execute()) {
                                $validQuestions++;
                            }
                        } catch (Exception $e) {
                            // Fallback if options_json column doesn't exist
                            $stmt = $conn->prepare('INSERT INTO question_bank (teacher_id, section_id, set_title, question_type, question_category, question_text, options, answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                            $questionCategory = 'practice';
                            $stmt->bind_param('iissssss', $teacherId, $sectionId, $setTitle, $type, $questionCategory, $text, $options, $answer);
                            
                            if ($stmt->execute()) {
                                $validQuestions++;
                            }
                        }
                    }
                    
                    if ($validQuestions > 0) {
                        $flash = "Successfully uploaded {$validQuestions} practice question(s) to '$setTitle'!";
                        
                        // Create notification for students in the section
                        if ($sectionId) {
                            createNotificationForSection(
                                $conn, 
                                $teacherId, 
                                $sectionId, 
                                'comprehension', 
                                'New Practice Questions Available', 
                                "Your teacher has added new practice questions to \"$setTitle\". Check the Practice section to try them.",
                                null
                            );
                        }
                    } else {
                        $flash = "Failed to upload questions. Please check your input.";
                    }
                } else {
                    $flash = "Please fill in all required fields and add at least one question.";
                }
                break;
                
                
            case 'delete_practice_test':
                $setTitle = trim($_POST['set_title']);
                if (!empty($setTitle)) {
                    $stmt = $conn->prepare("DELETE FROM question_bank WHERE set_title = ? AND teacher_id = ? AND question_category = 'practice'");
                    $stmt->bind_param("si", $setTitle, $teacherId);
                    if ($stmt->execute()) {
                        $flash = "Practice set '{$setTitle}' deleted successfully!";
                    } else {
                        $flash = "Failed to delete practice set.";
                    }
                } else {
                    $flash = "Invalid practice set.";
                }
                break;
        }
    }
}

// Get all practice sets grouped by set_title (only practice questions)
$practiceTestsQuery = "SELECT 
    set_title,
    section_id,
    COUNT(*) as question_count,
    MIN(created_at) as created_at,
    GROUP_CONCAT(
        CONCAT(question_type, ': ', SUBSTRING(question_text, 1, 50), '...') 
        ORDER BY id SEPARATOR '|'
    ) as questions_preview
FROM question_bank 
WHERE teacher_id = ? 
AND question_category = 'practice'
AND set_title IS NOT NULL 
AND set_title != ''
AND question_text NOT IN ('dsad', 'dsadasdasdasd', 'placeholder') 
AND question_text != '' 
AND question_text IS NOT NULL
GROUP BY set_title, section_id
ORDER BY created_at DESC";
$stmt = $conn->prepare($practiceTestsQuery);
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$practiceTests = $stmt->get_result();


// Get teacher's sections through the teacher_sections junction table
$sectionsQuery = "SELECT s.id, s.name FROM sections s 
                  JOIN teacher_sections ts ON s.id = ts.section_id 
                  WHERE ts.teacher_id = ? 
                  ORDER BY s.name";
$stmt = $conn->prepare($sectionsQuery);
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$teacherSections = $stmt->get_result();
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-body {
    padding: 24px;
}
.btn {
    background: #6366f1;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    text-decoration: none;
    display: inline-block;
    font-size: 0.95rem;
}
.btn:hover {
    background: #4f46e5;
}
.btn-danger {
    background: #ef4444;
}
.btn-danger:hover {
    background: #dc2626;
}
.btn-secondary {
    background: #6b7280;
}
.btn-secondary:hover {
    background: #4b5563;
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
.question-type-options {
    margin-top: 15px;
    padding: 15px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}
.practice-test-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    transition: all 0.2s ease;
}
.practice-test-item:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.practice-test-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}
.practice-test-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}
.practice-test-actions {
    display: flex;
    gap: 8px;
}
.action-btn {
    padding: 4px 8px;
    border: none;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
}
.edit-btn {
    background: #3b82f6;
    color: #fff;
}
.edit-btn:hover {
    background: #2563eb;
}
.delete-btn {
    background: #ef4444;
    color: #fff;
}
.delete-btn:hover {
    background: #dc2626;
}
.practice-test-meta {
    display: flex;
    gap: 16px;
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 8px;
}
.meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
}
.practice-test-description {
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 8px;
}
.questions-count {
    background: #e0e7ff;
    color: #3730a3;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
}
.modal-content {
    background-color: #fff;
    margin: 2% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.modal-header {
    background: #eef2ff;
    color: #3730a3;
    padding: 18px 24px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e0e7ff;
}
.modal-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0;
}
.close {
    color: #3730a3;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    transition: opacity 0.2s;
}
.close:hover {
    opacity: 0.7;
}
.modal-body {
    padding: 24px;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
    font-size: 0.9rem;
}
.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}
.form-group textarea {
    resize: vertical;
    min-height: 80px;
}
.questions-section {
    margin-top: 20px;
}
.questions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.questions-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 12px;
    background: #f9fafb;
}
.question-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 8px;
    background: #fff;
    transition: all 0.2s ease;
}
.question-item:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.question-item:last-child {
    margin-bottom: 0;
}
.question-checkbox {
    margin-top: 2px;
}
.question-content {
    flex: 1;
}
.question-text {
    font-weight: 500;
    color: #1f2937;
    margin-bottom: 4px;
    line-height: 1.4;
    font-size: 0.9rem;
}
.question-meta {
    display: flex;
    gap: 8px;
    font-size: 0.75rem;
    color: #6b7280;
}
.question-type {
    background: #f3f4f6;
    padding: 2px 6px;
    border-radius: 10px;
}
.question-date {
    background: #e0e7ff;
    color: #3730a3;
    padding: 2px 6px;
    border-radius: 10px;
}
.modal-footer {
    padding: 18px 24px;
    background: #f9fafb;
    border-radius: 0 0 12px 12px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e5e7eb;
}
.no-practice-tests {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}
.no-practice-tests i {
    font-size: 3rem;
    color: #d1d5db;
    margin-bottom: 12px;
}
.no-practice-tests h3 {
    font-size: 1.3rem;
    margin-bottom: 6px;
    color: #374151;
}
.no-practice-tests p {
    font-size: 0.95rem;
    margin-bottom: 20px;
}
@media (max-width: 768px) {
    .container {
        padding: 0 12px;
    }
    .practice-test-header {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }
    .practice-test-actions {
        align-self: flex-end;
    }
    .practice-test-meta {
        flex-direction: column;
        gap: 4px;
    }
    .modal-content {
        width: 95%;
        margin: 5% auto;
    }
    .modal-footer {
        flex-direction: column;
    }
    .btn {
        width: 100%;
    }
}
</style>

<div class="container">
    <?php if ($flash): ?>
        <div class="flash">
            <?php echo htmlspecialchars($flash); ?>
            <button class="flash-close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Create Practice Questions Form -->
    <div class="card">
        <div class="card-header">
            <span>📝 Create Practice Questions</span>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="questionForm">
                <input type="hidden" name="action" value="add_practice_questions">
                
                <div class="form-group">
                    <label>Practice Set Title *</label>
                    <input type="text" name="set_title" required placeholder="e.g., Reading Comprehension Practice">
                </div>
                
                <div class="form-group">
                    <label>Target Section *</label>
                    <select name="section_id" required>
                        <option value="">Select a section</option>
                        <?php 
                        $teacherSections->data_seek(0);
                        while ($section = $teacherSections->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div id="questions-list"></div>
                
                <div style="margin-top: 10px;">
                    <button type="button" class="btn btn-secondary" id="addQuestionBtn">
                        <i class="fas fa-plus"></i> Add New Question Form
                    </button>
                </div>
                
                <div style="text-align: right; margin-top: 10px;">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-upload"></i> Upload All Questions
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>🔥 Practice Sets</span>
        </div>
        <div class="card-body">
            <?php if ($practiceTests->num_rows > 0): ?>
                <?php while ($test = $practiceTests->fetch_assoc()): ?>
                    <?php
                    // Get section name
                    $sectionQuery = $conn->prepare("SELECT name FROM sections WHERE id = ?");
                    $sectionQuery->bind_param("i", $test['section_id']);
                    $sectionQuery->execute();
                    $sectionResult = $sectionQuery->get_result();
                    $sectionName = $sectionResult->num_rows > 0 ? $sectionResult->fetch_assoc()['name'] : 'Unknown Section';
                    ?>
                    <div class="practice-test-item">
                        <div class="practice-test-header">
                            <h3 class="practice-test-title"><?php echo htmlspecialchars($test['set_title']); ?></h3>
                            <div class="practice-test-actions">
                                <button class="action-btn edit-btn" onclick="editPracticeTest('<?php echo htmlspecialchars($test['set_title']); ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="action-btn delete-btn" onclick="deletePracticeTest('<?php echo htmlspecialchars($test['set_title']); ?>', '<?php echo htmlspecialchars($test['set_title']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        
                        <div class="practice-test-meta">
                            <div class="meta-item">
                                <i class="fas fa-question-circle"></i>
                                <span><?php echo $test['question_count']; ?> questions</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo htmlspecialchars($sectionName); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('M j, Y', strtotime($test['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($test['questions_preview'])): ?>
                            <div class="questions-count">
                                <?php 
                                $questions = explode('|', $test['questions_preview']);
                                $preview = implode(' • ', array_slice($questions, 0, 2));
                                if (count($questions) > 2) $preview .= ' • ...';
                                echo htmlspecialchars($preview);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-practice-tests">
                    <i class="fas fa-fire"></i>
                    <h3>No Practice Sets Yet</h3>
                    <p>Create your first practice set to help students warm up and prepare for learning.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<script>
// Dynamic Question Forms (like Comprehension Questions)
let questionCount = 0;

function addQuestionForm() {
    questionCount++;
    const questionsList = document.getElementById('questions-list');
    
    const questionDiv = document.createElement('div');
    questionDiv.className = 'question-block';
    questionDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h4 style="margin: 0; color: #3730a3;">Question ${questionCount}</h4>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestionForm(this)" style="padding: 6px 12px; font-size: 0.85em; background: #ef4444; border: none; border-radius: 4px; color: white; cursor: pointer;">
                <i class="fas fa-times"></i> Remove
            </button>
        </div>
        
        <div class="form-group">
            <label>Question Type *</label>
            <div style="display: flex; gap: 15px; margin-top: 8px;">
                <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                    <input type="radio" name="questions[${questionCount}][type]" value="multiple_choice" onchange="showQuestionTypeFields(${questionCount})" required>
                    <span>Multiple Choice</span>
                </label>
                <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                    <input type="radio" name="questions[${questionCount}][type]" value="matching" onchange="showQuestionTypeFields(${questionCount})" required>
                    <span>Matching Type</span>
                </label>
                <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                    <input type="radio" name="questions[${questionCount}][type]" value="essay" onchange="showQuestionTypeFields(${questionCount})" required>
                    <span>Essay</span>
                </label>
            </div>
        </div>
        
        <div class="form-group">
            <label>Question Text *</label>
            <textarea name="questions[${questionCount}][text]" required placeholder="Enter your question here..." rows="3"></textarea>
        </div>
        
        <!-- Multiple Choice Options -->
        <div id="multiple_choice_options_${questionCount}" class="question-type-options" style="display: none;">
            <h5>Add Options</h5>
            ${[1,2,3,4].map(i => `
                <div class="form-group">
                    <label>Option ${String.fromCharCode(64 + i)} *</label>
                    <input type="text" name="questions[${questionCount}][option_${i}]" placeholder="Enter option ${String.fromCharCode(64 + i)}">
                </div>
            `).join('')}
            
            <div class="form-group">
                <label>Correct Answer *</label>
                <select name="questions[${questionCount}][correct_answer]" required>
                    <option value="">Select correct answer</option>
                    <option value="option_1">Option A</option>
                    <option value="option_2">Option B</option>
                    <option value="option_3">Option C</option>
                    <option value="option_4">Option D</option>
                </select>
            </div>
        </div>
        
        <!-- Matching Options -->
        <div id="matching_options_${questionCount}" class="question-type-options" style="display: none;">
            <h5>Add Matching Items</h5>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <h6>Column A (Left Items)</h6>
                    ${[1,2,3,4].map(i => `
                        <div class="form-group">
                            <label>Item ${i} *</label>
                            <input type="text" name="questions[${questionCount}][left_item_${i}]" placeholder="Enter left item ${i}">
                        </div>
                    `).join('')}
                </div>
                <div>
                    <h6>Column B (Right Items)</h6>
                    ${[1,2,3,4].map(i => `
                        <div class="form-group">
                            <label>Item ${i} *</label>
                            <input type="text" name="questions[${questionCount}][right_item_${i}]" placeholder="Enter right item ${i}">
                        </div>
                    `).join('')}
                </div>
            </div>
            <p style="color: #6b7280; font-size: 0.9rem; margin-top: 10px;">
                <strong>Answer Key:</strong> The system will automatically create matches based on the order of items.
            </p>
        </div>
        
        <!-- Essay Options -->
        <div id="essay_options_${questionCount}" class="question-type-options" style="display: none;">
            <h5>Add Rubrics</h5>
            <div class="form-group">
                <label>Grading Rubrics *</label>
                <textarea name="questions[${questionCount}][rubrics]" placeholder="Enter the grading criteria and rubrics for this essay question..." rows="4"></textarea>
            </div>
            
            <div class="form-group">
                <label>Word Limit</label>
                <input type="number" name="questions[${questionCount}][word_limit]" min="0" placeholder="e.g., 200 (0 for no limit)">
            </div>
            
            <p style="color: #6b7280; font-size: 0.9rem; margin-top: 10px;">
                <strong>Answer Key:</strong> The rubrics will serve as the answer key for grading this essay question.
            </p>
        </div>
    `;
    
    questionsList.appendChild(questionDiv);
}

function removeQuestionForm(button) {
    const questionBlocks = document.querySelectorAll('.question-block');
    
    // Don't allow removing the last question
    if (questionBlocks.length <= 1) {
        alert('You must have at least one question in the practice set.');
        return;
    }
    
    button.closest('.question-block').remove();
    updateQuestionNumbers();
}

function updateQuestionNumbers() {
    const questionBlocks = document.querySelectorAll('.question-block');
    questionBlocks.forEach((block, index) => {
        const title = block.querySelector('h4');
        if (title) {
            title.textContent = `Question ${index + 1}`;
        }
        
        // Update all input names to match the new index
        const inputs = block.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.name) {
                // Extract the field name from the current name
                const match = input.name.match(/questions\[\d+\]\[(.+)\]/);
                if (match) {
                    input.name = `questions[${index + 1}][${match[1]}]`;
                }
            }
        });
        
        // Update IDs for question type options
        const optionDivs = block.querySelectorAll('.question-type-options');
        optionDivs.forEach(div => {
            const oldId = div.id;
            if (oldId) {
                const match = oldId.match(/^(.+)_\d+$/);
                if (match) {
                    div.id = `${match[1]}_${index + 1}`;
                }
            }
        });
        
        // Update onchange attributes
        const radioButtons = block.querySelectorAll('input[type="radio"]');
        radioButtons.forEach(radio => {
            if (radio.onchange) {
                radio.setAttribute('onchange', `showQuestionTypeFields(${index + 1})`);
            }
        });
    });
}

function showQuestionTypeFields(questionIndex) {
    // Hide all question type options for this question
    const questionBlock = document.querySelector(`input[name="questions[${questionIndex}][type]"]`).closest('.question-block');
    const allOptions = questionBlock.querySelectorAll('.question-type-options');
    allOptions.forEach(option => option.style.display = 'none');
    
    // Show the selected question type options
    const selectedType = questionBlock.querySelector('input[name="questions[' + questionIndex + '][type]"]:checked');
    if (selectedType) {
        const typeOptions = questionBlock.querySelector('#' + selectedType.value + '_options_' + questionIndex);
        if (typeOptions) {
            typeOptions.style.display = 'block';
        }
    }
}

// Initialize the form
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('addQuestionBtn').addEventListener('click', addQuestionForm);
    
    // Add first question form automatically
    questionCount = 0; // Reset counter
    addQuestionForm();
    updateQuestionNumbers(); // Ensure proper numbering
});



function deletePracticeTest(setTitle, testTitle) {
    if (confirm(`Are you sure you want to delete the practice test "${testTitle}"?\n\nThis will delete all questions in this set and cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_practice_test">
            <input type="hidden" name="set_title" value="${setTitle}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function editPracticeTest(testId) {
    // TODO: Implement edit functionality
    alert('Edit functionality will be implemented in the next version.');
}


</script>

<?php
render_teacher_footer();
?>


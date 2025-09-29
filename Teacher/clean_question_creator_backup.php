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
    
    header('Content-Type: application/json');
    
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
                
                // Create new question set
                $setTitle = $_POST['set_title'] ?? '';
                if (empty($setTitle)) {
                    echo json_encode(['success' => false, 'error' => 'Please enter a question set title']);
                    exit;
                }
                
                // Get or create question set
                $setId = $questionHandler->getOrCreateQuestionSet($_SESSION['teacher_id'], $sectionId, $setTitle);
                if (!$setId) {
                    echo json_encode(['success' => false, 'error' => 'Failed to create question set']);
                    exit;
                }
                
                error_log('Creating question with sectionId: ' . $sectionId . ', setId: ' . $setId);
                
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
                    
                    echo json_encode([
                        'success' => true,
                        'questions' => $questions,
                        'set_title' => $setTitle,
                        'section_id' => $sectionId
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
        error_log('Question creation error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An error occurred while creating the question']);
        exit;
    } catch (Error $e) {
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
        
        .set-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        .set-info h4 {
            margin-bottom: 5px;
            color: #333;
        }
        
        .set-stats {
            color: #666;
            font-size: 14px;
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
        </div>
        
        <!-- Question Creation Form -->
        <div class="question-form">
            <h2 id="formTitleHeading">Create Questions</h2>
            <form id="questionForm">
                <div class="form-group">
                    <label for="section_id">Section:</label>
                    <select id="section_id" name="section_id" required>
                        <option value="">Select a section</option>
                        <?php foreach ($teacherSections as $section): ?>
                            <option value="<?php echo $section['id']; ?>"><?php echo htmlspecialchars($section['section_name'] ?: $section['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                
                <div id="new-set-fields">
                    <div class="form-group">
                        <label for="set_title">Question Set Title:</label>
                        <input type="text" id="set_title" name="set_title">
                    </div>
                </div>
                
                
                <!-- Questions Container -->
                <div id="questions-container">
                    <!-- Question 1 (Default) -->
                    <div class="question-item" data-question-index="0">
                        <div class="question-header">
                            <h3>Question 1</h3>
                            <button type="button" class="btn btn-danger btn-sm remove-question" onclick="removeQuestion(this)" style="display: none;">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label for="type_0">Question Type:</label>
                            <select id="type_0" name="questions[0][type]" required onchange="showQuestionTypeSection(0);">
                                <option value="">Select Question Type</option>
                                <option value="mcq">Multiple Choice</option>
                                <option value="matching">Matching</option>
                                <option value="essay">Essay</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="question_text_0">Question Text:</label>
                            <textarea id="question_text_0" name="questions[0][question_text]" rows="3" required></textarea>
                            <small id="question_text_help_0" class="form-text text-muted" style="display: none;">
                                For matching questions, this will be used as the main instruction above all matching pairs.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="points_0">Points:</label>
                            <input type="number" id="points_0" name="questions[0][points]" value="1" min="1" required>
                        </div>
                        
                        <!-- MCQ Section -->
                        <div id="mcq-section_0" class="question-type-section">
                            <h3>Multiple Choice Options</h3>
                            <div id="mcq-options_0">
                                <div class="option-group">
                                    <label for="choice_a_0">Option A:</label>
                                    <input type="text" id="choice_a_0" name="questions[0][choice_a]" placeholder="Option A" required>
                                    <label for="correct_a_0">Correct Answer:</label>
                                    <input type="radio" id="correct_a_0" name="questions[0][correct_answer]" value="A" required>
                                    <button type="button" onclick="removeOption(this)">×</button>
                                </div>
                                <div class="option-group">
                                    <label for="choice_b_0">Option B:</label>
                                    <input type="text" id="choice_b_0" name="questions[0][choice_b]" placeholder="Option B" required>
                                    <label for="correct_b_0">Correct Answer:</label>
                                    <input type="radio" id="correct_b_0" name="questions[0][correct_answer]" value="B" required>
                                    <button type="button" onclick="removeOption(this)">×</button>
                                </div>
                                <div class="option-group">
                                    <label for="choice_c_0">Option C:</label>
                                    <input type="text" id="choice_c_0" name="questions[0][choice_c]" placeholder="Option C" required>
                                    <label for="correct_c_0">Correct Answer:</label>
                                    <input type="radio" id="correct_c_0" name="questions[0][correct_answer]" value="C" required>
                                    <button type="button" onclick="removeOption(this)">×</button>
                                </div>
                                <div class="option-group">
                                    <label for="choice_d_0">Option D:</label>
                                    <input type="text" id="choice_d_0" name="questions[0][choice_d]" placeholder="Option D" required>
                                    <label for="correct_d_0">Correct Answer:</label>
                                    <input type="radio" id="correct_d_0" name="questions[0][correct_answer]" value="D" required>
                                    <button type="button" onclick="removeOption(this)">×</button>
                                </div>
                            </div>
                            <button type="button" class="add-option" onclick="addMCQOption(0)">
                                <i class="fas fa-plus"></i> Add Option
                            </button>
                        </div>
                
                        <!-- Matching Section -->
                        <div id="matching-section_0" class="question-type-section">
                            <h3>Matching Pairs</h3>
                            
                            <div class="form-group">
                                <label>Left Items (Rows):</label>
                                <div id="matching-rows_0">
                                    <div class="input-group">
                                        <label for="left_item_1_0">Row 1:</label>
                                        <input type="text" id="left_item_1_0" name="questions[0][left_items][]" placeholder="Row 1" required>
                                        <button type="button" class="remove-option" onclick="removeMatchingRow(this, 0)">×</button>
                                    </div>
                                    <div class="input-group">
                                        <label for="left_item_2_0">Row 2:</label>
                                        <input type="text" id="left_item_2_0" name="questions[0][left_items][]" placeholder="Row 2" required>
                                        <button type="button" class="remove-option" onclick="removeMatchingRow(this, 0)">×</button>
                                    </div>
                                </div>
                                <button type="button" class="add-option" onclick="addMatchingRow(0)">
                                    <i class="fas fa-plus"></i> Add Row
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label>Right Items (Columns):</label>
                                <div id="matching-columns_0">
                                    <div class="input-group">
                                        <label for="right_item_1_0">Column 1:</label>
                                        <input type="text" id="right_item_1_0" name="questions[0][right_items][]" placeholder="Column 1" required>
                                        <button type="button" class="remove-option" onclick="removeMatchingColumn(this, 0)">×</button>
                                    </div>
                                    <div class="input-group">
                                        <label for="right_item_2_0">Column 2:</label>
                                        <input type="text" id="right_item_2_0" name="questions[0][right_items][]" placeholder="Column 2" required>
                                        <button type="button" class="remove-option" onclick="removeMatchingColumn(this, 0)">×</button>
                                    </div>
                                </div>
                                <button type="button" class="add-option" onclick="addMatchingColumn(0)">
                                    <i class="fas fa-plus"></i> Add Column
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label>Correct Matches:</label>
                                <div id="matching-matches_0">
                                    <!-- Will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                
                        <!-- Essay Section -->
                        <div id="essay-section_0" class="question-type-section">
                            <h3>Essay Question</h3>
                            <p>Essay questions will be manually graded by the teacher.</p>
                            <div class="form-group">
                                <label for="essay_rubric_0">Rubric (required)</label>
                                <textarea id="essay_rubric_0" name="questions[0][rubric]" rows="4" placeholder="e.g., Thesis (2), Evidence (3), Organization (2), Grammar (3)"></textarea>
                                <small class="form-text text-muted">Describe scoring criteria or paste a rubric. Students will see this rubric.</small>
                            </div>
                        </div>
                    </div>
                </div>
                
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
        
        
        <!-- Question Sets List -->
        <div class="question-sets">
            <h2>Question Bank</h2>
            <div id="questionSetsList">
                <?php foreach ($questionSets as $set): ?>
                <div class="set-item">
                    <div class="set-info">
                        <h4><?php echo htmlspecialchars($set['set_title']); ?></h4>
                        <div class="set-stats">
                            <?php echo $set['question_count']; ?> questions, 
                            <?php echo $set['total_points']; ?> total points
                            (<?php echo htmlspecialchars($set['section_name']); ?>)
                        </div>
                    </div>
                    <div class="set-actions">
                        <button type="button" class="btn btn-view" onclick="viewQuestions('<?php echo $set['id']; ?>', '<?php echo htmlspecialchars($set['set_title']); ?>')">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button type="button" class="btn btn-edit" onclick="editSet(<?php echo $set['id']; ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button type="button" class="btn btn-delete" onclick="deleteSet(<?php echo $set['id']; ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        let questionIndex = 0;
        window.isEditMode = false;
        window.currentEditSetId = null;
        
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
                
                // Auto-populate question text based on type
                if (type === 'matching') {
                    questionText.value = 'Match the following items with their correct answers:';
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
                } else if (type !== 'matching') {
                    helpText.style.display = 'none';
                }
                
                // Auto-update points for matching questions
                if (type === 'matching') {
                    updateMatchingMatches(questionIndex);
                }
            }
        }
        
function addNewQuestion() {
    questionIndex++;
    console.log(`Adding new question with index: ${questionIndex}`);
    const container = document.getElementById('questions-container');
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
}

function createQuestionHTML(index) {
    return `
        <div class="question-item" data-question-index="${index}">
            <div class="question-header">
                <h3>Question ${index + 1}</h3>
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
            input.addEventListener('input', () => updateMatchingMatches(questionIndex));
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
            input.addEventListener('input', () => updateMatchingMatches(questionIndex));
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
                    input.addEventListener('input', () => { updateMatchingMatches(questionIndex); validateMatching(questionIndex); });
                    input.setAttribute('data-listener-added', 'true');
                }
            });
            
            // Add event listeners to existing column inputs
            document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`).forEach(input => {
                if (!input.hasAttribute('data-listener-added')) {
                    input.addEventListener('input', () => { updateMatchingMatches(questionIndex); validateMatching(questionIndex); });
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
            const m = document.getElementById(msgId);
            if (m) m.remove();
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
            const leftInputs = document.querySelectorAll(`input[name="questions[${i}][left_items][]"]`);
            const rightInputs = document.querySelectorAll(`input[name="questions[${i}][right_items][]"]`);
            leftInputs.forEach((el, idx) => { if (!el.value.trim()) showError(el, `err_l_${i}_${idx}`, 'Required'); else clearError(el, `err_l_${i}_${idx}`); });
            rightInputs.forEach((el, idx) => { if (!el.value.trim()) showError(el, `err_r_${i}_${idx}`, 'Required'); else clearError(el, `err_r_${i}_${idx}`); });
            // Matches dropdowns exist after updateMatchingMatches
            setTimeout(()=>{
                const selects = document.querySelectorAll(`#matching-matches_${i} select`);
                selects.forEach((sel, idx) => { if (!sel.value) showError(sel, `err_sel_${i}_${idx}`, 'Select match'); else clearError(sel, `err_sel_${i}_${idx}`); });
            },0);
        }
        
        
            
            // Form submission
            document.getElementById('questionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get the first question data (since we're creating a new set)
            const typeElement = document.getElementById('type_0') || document.getElementById('type');
            const questionTextElement = document.getElementById('question_text_0') || document.getElementById('question_text');
            const sectionIdElement = document.getElementById('section_id');
            const setTitleElement = document.getElementById('set_title');
            
            if (!typeElement || !questionTextElement || !sectionIdElement || !setTitleElement) {
                alert('Form elements not found. Please refresh the page and try again.');
                return;
            }
            
            const type = typeElement.value;
            const questionText = questionTextElement.value;
            const sectionId = sectionIdElement.value;
            const setTitle = setTitleElement.value;
            
            // Basic validation
            if (!sectionId || !questionText || !type) {
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
            .then(response => response.json())
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
    // Load and display questions for the selected set
    const formData = new FormData();
    formData.append('action', 'get_questions');
    formData.append('set_id', setId);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success || !data.questions || data.questions.length === 0) {
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
            })
            .catch(error => {
                alert('Error loading questions: ' + error.message);
            });
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

                    // Add delete toggle in edit mode
                    const delWrap = document.createElement('div');
                    delWrap.style.cssText = 'margin:6px 0;';
                    delWrap.innerHTML = `<label style="font-size:12px;color:#666"><input type="checkbox" name="questions[${questionIndex}][delete]" value="1"> Remove this question</label>`;
                    qi.querySelector('.question-header').appendChild(delWrap);

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
                        // Add extra rows only if there are more than 2 items
                        // addMatchingRow will automatically add corresponding columns
                        for (let i = 2; i < left.length; i++) addMatchingRow(questionIndex);
                        const leftInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`);
                        const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                        left.forEach((v,i)=>{ if (leftInputs[i]) leftInputs[i].value = v; });
                        right.forEach((v,i)=>{ if (rightInputs[i]) rightInputs[i].value = v; });
                        
                        // Update matching matches after populating the inputs
                        setTimeout(() => {
                            updateMatchingMatches(questionIndex);
                            
                            // Wait for updateMatchingMatches to complete, then set selections
                            setTimeout(() => {
                            const selects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
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
                            
                            // Additional fallback: try to extract matches from other possible fields
                            if (matches.length === 0) {
                                console.log('No matches found in matches or correct_pairs, trying other fields...');
                                if (q.answer) matches = normalize(q.answer);
                                if (matches.length === 0 && q.correct_answer) matches = normalize(q.correct_answer);
                                if (matches.length === 0 && q.right_items && Array.isArray(q.right_items)) {
                                    // If no matches found, try to use right_items as fallback
                                    matches = q.right_items.slice(0, left.length);
                                }
                            }
                            
                            console.log('Matching question data:', {
                                questionId: q.id,
                                leftItems: left,
                                rightItems: right,
                                matches: matches,
                                rawMatches: q.matches,
                                rawCorrectPairs: q.correct_pairs
                            });

                            console.log(`Found ${selects.length} select elements, trying to set ${matches.length} matches`);
                            
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
                        }, 200);
                    }
                });
                
                function setMatchesInSelects(selects, matches, questionIndex) {
                    matches.forEach((val, i) => {
                        const sel = selects[i];
                        if (!sel) {
                            console.log(`No select element found for index ${i}`);
                            return;
                        }
                        const target = (val ?? '').toString().trim();
                        console.log(`Setting match ${i}: "${target}"`);
                        
                        // First, ensure the option exists
                        let exists = false;
                        Array.from(sel.options).forEach(opt => {
                            if (opt.value === target || opt.textContent.trim() === target) exists = true;
                        });
                        if (!exists && target) {
                            const opt = document.createElement('option');
                            opt.value = target;
                            opt.textContent = target;
                            sel.appendChild(opt);
                            console.log(`Added option: ${target}`);
                        }
                        
                        // Try multiple selection methods
                        let selected = false;
                        
                        // Method 1: Select by exact value match
                        Array.from(sel.options).forEach(opt => {
                            if (opt.value === target) {
                                opt.selected = true;
                                selected = true;
                                console.log(`Selected by exact value: ${target}`);
                            }
                        });
                        
                        // Method 2: Select by text content match
                        if (!selected) {
                            Array.from(sel.options).forEach(opt => {
                                if (opt.textContent.trim() === target) {
                                    opt.selected = true;
                                    selected = true;
                                    console.log(`Selected by text content: ${target}`);
                                }
                            });
                        }
                        
                        // Method 3: Case insensitive match
                        if (!selected) {
                            Array.from(sel.options).forEach(opt => {
                                if (opt.value.trim().toLowerCase() === target.toLowerCase() || 
                                    opt.textContent.trim().toLowerCase() === target.toLowerCase()) {
                                    opt.selected = true;
                                    selected = true;
                                    console.log(`Selected by case insensitive match: ${target}`);
                                }
                            });
                        }
                        
                        // Method 4: Direct value assignment
                        if (!selected && target) {
                            sel.value = target;
                            selected = true;
                            console.log(`Selected by direct value assignment: ${target}`);
                        }
                        
                        if (!selected) {
                            console.log(`Failed to select: ${target} for select ${i}`);
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
                }, 50);
            }, 100);
        }, 50);
        
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
    </script>
    </div>
</body>
</html>
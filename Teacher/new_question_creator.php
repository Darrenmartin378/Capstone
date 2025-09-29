<?php
require_once 'includes/teacher_init.php';
require_once 'includes/NewQuestionHandler.php';

$newQuestionHandler = new NewQuestionHandler($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_question':
                $sectionId = (int)($_POST['section_id'] ?? 0);
                if ($sectionId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Please select a section']);
                    exit;
                }
                
                if (!isset($_SESSION['teacher_id']) || $_SESSION['teacher_id'] <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Teacher session not found']);
                    exit;
                }
                
                $questionType = $_POST['type'] ?? '';
                $setTitle = $_POST['set_title'] ?? '';
                $questionText = $_POST['question_text'] ?? '';
                $points = (int)($_POST['points'] ?? 1);
                
                // Create or get question set
                $setResult = $newQuestionHandler->createQuestionSet(
                    $_SESSION['teacher_id'], 
                    $sectionId, 
                    $setTitle, 
                    'Created via new question creator'
                );
                
                if (!$setResult['success']) {
                    echo json_encode($setResult);
                    exit;
                }
                
                $setId = $setResult['set_id'];
                
                // Create question based on type
                switch ($questionType) {
                    case 'mcq':
                        $result = $newQuestionHandler->createMCQQuestion(
                            $setId,
                            $questionText,
                            $_POST['choice_a'] ?? '',
                            $_POST['choice_b'] ?? '',
                            $_POST['choice_c'] ?? '',
                            $_POST['choice_d'] ?? '',
                            $_POST['correct_answer'] ?? 'A',
                            $points
                        );
                        break;
                        
                    case 'matching':
                        $leftItems = $_POST['rows'] ?? [];
                        $rightItems = $_POST['columns'] ?? [];
                        $correctPairs = [];
                        
                        // Build correct pairs from form data
                        foreach ($_POST['matches'] ?? [] as $index => $match) {
                            $correctPairs[$index] = $match;
                        }
                        
                        $result = $newQuestionHandler->createMatchingQuestion(
                            $setId,
                            $questionText,
                            $leftItems,
                            $rightItems,
                            $correctPairs,
                            $points
                        );
                        break;
                        
                    case 'essay':
                        $result = $newQuestionHandler->createEssayQuestion(
                            $setId,
                            $questionText,
                            $points
                        );
                        break;
                        
                    default:
                        $result = ['success' => false, 'error' => 'Invalid question type'];
                }
                
                echo json_encode($result);
                exit;
                
            case 'get_questions':
                $sectionId = (int)($_POST['section_id'] ?? 0);
                $setTitle = $_POST['set_title'] ?? '';
                
                if ($sectionId <= 0 || empty($setTitle)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                    exit;
                }
                
                // Find the question set
                $stmt = $conn->prepare("
                    SELECT id FROM question_sets 
                    WHERE teacher_id = ? AND section_id = ? AND set_title = ?
                ");
                $stmt->bind_param('iis', $_SESSION['teacher_id'], $sectionId, $setTitle);
                $stmt->execute();
                $setResult = $stmt->get_result()->fetch_assoc();
                
                if (!$setResult) {
                    echo json_encode(['success' => false, 'error' => 'Question set not found']);
                    exit;
                }
                
                $questions = $newQuestionHandler->getQuestionsForSet($setResult['id']);
                echo json_encode(['success' => true, 'questions' => $questions]);
                exit;
        }
    } catch (Exception $e) {
        error_log('Question creation error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An error occurred while creating the question']);
        exit;
    } catch (Error $e) {
        error_log('Question creation fatal error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
        exit;
    }
}

// Get available sections
$sections = $newQuestionHandler->getSections();

// Get question sets for the teacher
$questionSets = $newQuestionHandler->getQuestionSets($_SESSION['teacher_id']);

// Include the teacher layout
require_once 'includes/teacher_layout.php';
render_teacher_header('new_question_creator.php', $teacherName, 'New Question Creator');
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
    
    .form-container {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .question-type-section {
        display: none;
        margin-top: 20px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 6px;
    }
    
    .question-type-section.active {
        display: block;
    }
    
    .mcq-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-top: 15px;
    }
    
    .matching-pairs {
        margin-top: 15px;
    }
    
    .matching-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .matching-row input {
        flex: 1;
    }
    
    .matching-row select {
        width: 200px;
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
</style>

<body>
    <div class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-plus-circle"></i> Create Questions (New Schema)</h1>
            <p>Create questions using the new separate table schema</p>
        </div>
        
        <div class="form-container">
            <form id="questionForm">
                <div class="form-group">
                    <label>Question Set Title:</label>
                    <input type="text" name="set_title" required>
                </div>
                
                <div class="form-group">
                    <label>Section:</label>
                    <select name="section_id" required>
                        <option value="">Select a section</option>
                        <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>">
                            <?php echo htmlspecialchars($section['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Question Text:</label>
                    <textarea name="question_text" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Question Type:</label>
                    <select name="type" id="type" onchange="showQuestionTypeSection()">
                        <option value="">Select Question Type</option>
                        <option value="mcq">Multiple Choice</option>
                        <option value="matching">Matching</option>
                        <option value="essay">Essay</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Points:</label>
                    <input type="number" name="points" value="1" min="1" required>
                </div>
                
                <!-- MCQ Section -->
                <div id="mcq-section" class="question-type-section">
                    <h3>Multiple Choice Options</h3>
                    <div class="mcq-options">
                        <div class="form-group">
                            <label>Choice A:</label>
                            <input type="text" name="choice_a" placeholder="Option A">
                        </div>
                        <div class="form-group">
                            <label>Choice B:</label>
                            <input type="text" name="choice_b" placeholder="Option B">
                        </div>
                        <div class="form-group">
                            <label>Choice C:</label>
                            <input type="text" name="choice_c" placeholder="Option C">
                        </div>
                        <div class="form-group">
                            <label>Choice D:</label>
                            <input type="text" name="choice_d" placeholder="Option D">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Correct Answer:</label>
                        <select name="correct_answer">
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>
                
                <!-- Matching Section -->
                <div id="matching-section" class="question-type-section">
                    <h3>Matching Pairs</h3>
                    <div class="form-group">
                        <label>Left Items (Rows):</label>
                        <div id="matching-rows">
                            <input type="text" name="rows[]" placeholder="Row 1" required>
                            <input type="text" name="rows[]" placeholder="Row 2" required>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addMatchingRow()">
                            <i class="fas fa-plus"></i> Add Row
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label>Right Items (Columns):</label>
                        <div id="matching-columns">
                            <input type="text" name="columns[]" placeholder="Column 1" required>
                            <input type="text" name="columns[]" placeholder="Column 2" required>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addMatchingColumn()">
                            <i class="fas fa-plus"></i> Add Column
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label>Correct Matches:</label>
                        <div id="matching-matches">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <!-- Essay Section -->
                <div id="essay-section" class="question-type-section">
                    <h3>Essay Question</h3>
                    <p>Essay questions will be manually graded by the teacher.</p>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Create Question
                </button>
            </form>
        </div>
        
        <!-- Question Sets List -->
        <div class="question-sets">
            <h2>Existing Question Sets</h2>
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
                    <div>
                        <button class="btn btn-secondary" onclick="viewQuestions('<?php echo $set['set_title']; ?>', <?php echo $set['section_id']; ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function showQuestionTypeSection() {
            const type = document.getElementById('type').value;
            
            // Hide all sections
            document.querySelectorAll('.question-type-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            if (type) {
                document.getElementById(type + '-section').classList.add('active');
            }
            
            // Update matching matches when matching is selected
            if (type === 'matching') {
                updateMatchingMatches();
            }
        }
        
        function addMatchingRow() {
            const container = document.getElementById('matching-rows');
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'rows[]';
            input.placeholder = 'Row ' + (container.children.length + 1);
            input.required = true;
            container.appendChild(input);
            updateMatchingMatches();
        }
        
        function addMatchingColumn() {
            const container = document.getElementById('matching-columns');
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'columns[]';
            input.placeholder = 'Column ' + (container.children.length + 1);
            input.required = true;
            container.appendChild(input);
            updateMatchingMatches();
        }
        
        function updateMatchingMatches() {
            const rows = document.querySelectorAll('input[name="rows[]"]');
            const columns = document.querySelectorAll('input[name="columns[]"]');
            const container = document.getElementById('matching-matches');
            
            container.innerHTML = '';
            
            rows.forEach((row, index) => {
                if (row.value.trim()) {
                    const div = document.createElement('div');
                    div.className = 'matching-row';
                    div.innerHTML = `
                        <span>${row.value}:</span>
                        <select name="matches[${index}]" required>
                            <option value="">Select match...</option>
                            ${Array.from(columns).map(col => 
                                `<option value="${col.value}">${col.value}</option>`
                            ).join('')}
                        </select>
                    `;
                    container.appendChild(div);
                }
            });
        }
        
        // Add event listeners to existing inputs
        document.addEventListener('DOMContentLoaded', function() {
            const rowInputs = document.querySelectorAll('input[name="rows[]"]');
            const colInputs = document.querySelectorAll('input[name="columns[]"]');
            
            rowInputs.forEach(input => {
                input.addEventListener('input', updateMatchingMatches);
            });
            
            colInputs.forEach(input => {
                input.addEventListener('input', updateMatchingMatches);
            });
        });
        
        // Form submission
        document.getElementById('questionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_question');
            
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
            });
        });
        
        function viewQuestions(setTitle, sectionId) {
            const formData = new FormData();
            formData.append('action', 'get_questions');
            formData.append('set_title', setTitle);
            formData.append('section_id', sectionId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.questions.length > 0) {
                    let questionsHtml = '<h3>Questions in "' + setTitle + '"</h3>';
                    questionsHtml += '<div style="max-height: 400px; overflow-y: auto;">';
                    
                    data.questions.forEach((question, index) => {
                        questionsHtml += '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">';
                        questionsHtml += '<h4>Question ' + (index + 1) + ' (' + question.type.toUpperCase() + ')</h4>';
                        questionsHtml += '<p><strong>Text:</strong> ' + question.question_text + '</p>';
                        questionsHtml += '<p><strong>Points:</strong> ' + question.points + '</p>';
                        
                        if (question.type === 'mcq') {
                            questionsHtml += '<p><strong>Choices:</strong> A) ' + question.choice_a + ', B) ' + question.choice_b + ', C) ' + question.choice_c + ', D) ' + question.choice_d + '</p>';
                            questionsHtml += '<p><strong>Answer:</strong> ' + question.correct_answer + '</p>';
                        } else if (question.type === 'matching') {
                            const leftItems = JSON.parse(question.left_items || '[]');
                            const rightItems = JSON.parse(question.right_items || '[]');
                            questionsHtml += '<p><strong>Left Items:</strong> ' + leftItems.join(', ') + '</p>';
                            questionsHtml += '<p><strong>Right Items:</strong> ' + rightItems.join(', ') + '</p>';
                        }
                        
                        questionsHtml += '</div>';
                    });
                    
                    questionsHtml += '</div>';
                    
                    // Create modal
                    const modal = document.createElement('div');
                    modal.style.cssText = `
                        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                        background: rgba(0,0,0,0.5); z-index: 1000; display: flex; 
                        align-items: center; justify-content: center;
                    `;
                    
                    const modalContent = document.createElement('div');
                    modalContent.style.cssText = `
                        background: white; padding: 20px; border-radius: 8px; 
                        max-width: 80%; max-height: 80%; overflow-y: auto;
                    `;
                    modalContent.innerHTML = questionsHtml + '<button onclick="this.closest(\'div\').remove()" style="margin-top: 15px; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Close</button>';
                    
                    modal.appendChild(modalContent);
                    document.body.appendChild(modal);
                } else {
                    alert('No questions found in this set.');
                }
            })
            .catch(error => {
                alert('Error loading questions: ' + error.message);
            });
        }
    </script>
</body>
</html>

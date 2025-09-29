<?php
require_once 'includes/student_init.php';
require_once 'includes/NewResponseHandler.php';

$newResponseHandler = new NewResponseHandler($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'submit_responses':
                $questionSetId = (int)($_POST['question_set_id']);
                $responses = json_decode($_POST['responses'], true) ?? [];
                
                $result = $newResponseHandler->submitResponses($_SESSION['student_id'], $questionSetId, $responses);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Responses submitted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to submit responses']);
                }
                exit;
                
            case 'get_questions':
                $questionSetId = (int)($_POST['question_set_id']);
                $questions = $newResponseHandler->getQuestionsForSet($questionSetId);
                echo json_encode(['success' => true, 'questions' => $questions]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get available question sets for the student's section
$questionSets = [];
if (isset($_SESSION['section_id']) && $_SESSION['section_id'] > 0) {
    $questionSets = $newResponseHandler->getAvailableQuestionSets($_SESSION['section_id']);
} else {
    // If no section assigned, show all question sets
    $stmt = $conn->prepare("
        SELECT qs.*, s.name as section_name,
               (SELECT COUNT(*) FROM mcq_questions WHERE set_id = qs.id) +
               (SELECT COUNT(*) FROM matching_questions WHERE set_id = qs.id) +
               (SELECT COUNT(*) FROM essay_questions WHERE set_id = qs.id) as question_count,
               (SELECT COALESCE(SUM(points), 0) FROM mcq_questions WHERE set_id = qs.id) +
               (SELECT COALESCE(SUM(points), 0) FROM matching_questions WHERE set_id = qs.id) +
               (SELECT COALESCE(SUM(points), 0) FROM essay_questions WHERE set_id = qs.id) as total_points
        FROM question_sets qs
        JOIN sections s ON qs.section_id = s.id
        ORDER BY qs.created_at DESC
    ");
    $stmt->execute();
    $questionSets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

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
    
    .question-sets {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .set-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .set-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .set-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 10px;
        color: #333;
    }
    
    .set-stats {
        color: #666;
        font-size: 14px;
        margin-bottom: 15px;
    }
    
    .btn {
        background: #4285f4;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
        transition: background 0.2s;
    }
    
    .btn:hover {
        background: #3367d6;
    }
    
    .btn-success {
        background: #28a745;
    }
    
    .btn-success:hover {
        background: #218838;
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
        color: #666;
        font-size: 14px;
    }
    
    .question-text {
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
        padding: 10px;
        border: 1px solid #e1e5e9;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .option:hover {
        background: #f8f9fa;
    }
    
    .option input[type="radio"] {
        margin-right: 10px;
    }
    
    .matching-container {
        margin: 20px 0;
    }
    
    .matching-instructions {
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
        background: #f8d7da;
        border-color: #dc3545;
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
            <h1><i class="fas fa-question-circle"></i> Available Question Sets (New Schema)</h1>
            <p>Select a question set to start answering</p>
        </div>
        
        <div class="question-sets">
            <?php if (empty($questionSets)): ?>
                <div class="set-card" style="text-align: center; padding: 40px;">
                    <h3>No Question Sets Available</h3>
                    <p>No question sets found for your section.</p>
                </div>
            <?php else: ?>
                <?php foreach ($questionSets as $set): ?>
                <div class="set-card">
                    <div class="set-title"><?php echo htmlspecialchars($set['set_title']); ?></div>
                    <div class="set-stats">
                        <?php echo $set['question_count']; ?> questions • 
                        <?php echo $set['total_points']; ?> total points
                    </div>
                    <div class="set-stats">
                        Section: <?php echo htmlspecialchars($set['section_name']); ?>
                    </div>
                    <button class="btn" onclick="startQuestionSet(<?php echo $set['id']; ?>, '<?php echo htmlspecialchars($set['set_title']); ?>')">
                        <i class="fas fa-play"></i> Start Quiz
                    </button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div id="questionForm" class="question-form" style="display: none;">
            <h2 id="formTitle">Question Set</h2>
            <div id="questionsContainer">
                <!-- Questions will be loaded here -->
            </div>
            <button class="submit-btn" onclick="submitResponses()">
                <i class="fas fa-paper-plane"></i> Submit Answers
            </button>
        </div>
    </div>

    <script>
        let currentQuestionSetId = null;
        let currentQuestions = [];
        
        function startQuestionSet(setId, setTitle) {
            currentQuestionSetId = setId;
            document.getElementById('formTitle').textContent = setTitle;
            document.getElementById('questionForm').style.display = 'block';
            
            // Load questions for this set
            loadQuestions(setId);
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
                    currentQuestions = data.questions;
                    renderQuestions(data.questions);
                } else {
                    alert('Error loading questions: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
        
        function renderQuestions(questions) {
            const container = document.getElementById('questionsContainer');
            container.innerHTML = '';
            
            questions.forEach((question, index) => {
                const questionDiv = document.createElement('div');
                questionDiv.className = 'question-item';
                questionDiv.innerHTML = `
                    <div class="question-header">
                        <div class="question-number">Q${index + 1}</div>
                        <div class="question-points">${question.points} pts</div>
                    </div>
                    <div class="question-text">${question.question_text}</div>
                    ${renderQuestionContent(question, index)}
                `;
                container.appendChild(questionDiv);
                
                // Add drag event listeners for matching questions
                if (question.type === 'matching') {
                    const draggableItems = questionDiv.querySelectorAll('.draggable-item');
                    draggableItems.forEach(item => {
                        item.addEventListener('dragstart', drag);
                        item.addEventListener('dragend', dragEnd);
                    });
                }
            });
        }
        
        function renderQuestionContent(question, index) {
            switch (question.type) {
                case 'mcq':
                    return `
                        <div class="question-options">
                            <div class="option">
                                <input type="radio" name="question_${question.id}" value="A" id="q${question.id}_A">
                                <label for="q${question.id}_A">A) ${question.choice_a}</label>
                            </div>
                            <div class="option">
                                <input type="radio" name="question_${question.id}" value="B" id="q${question.id}_B">
                                <label for="q${question.id}_B">B) ${question.choice_b}</label>
                            </div>
                            <div class="option">
                                <input type="radio" name="question_${question.id}" value="C" id="q${question.id}_C">
                                <label for="q${question.id}_C">C) ${question.choice_c}</label>
                            </div>
                            <div class="option">
                                <input type="radio" name="question_${question.id}" value="D" id="q${question.id}_D">
                                <label for="q${question.id}_D">D) ${question.choice_d}</label>
                            </div>
                        </div>
                    `;
                    
                case 'matching':
                    // Render matching question with drag-and-drop
                    const leftItems = JSON.parse(question.left_items || '[]');
                    const rightItems = JSON.parse(question.right_items || '[]');
                    const correctPairs = JSON.parse(question.correct_pairs || '{}');
                    
                    return `
                        <div class="matching-container">
                            <div class="matching-instructions">
                                <p><strong>Instructions:</strong> Drag each item from the left to its correct match on the right.</p>
                            </div>
                            <div class="drag-drop-container">
                                <div class="draggable-items">
                                    <h4>Drag Items:</h4>
                                    ${leftItems.map((item, itemIndex) => `
                                        <div class="draggable-item" 
                                             draggable="true" 
                                             data-item-index="${itemIndex}"
                                             data-correct="${correctPairs[itemIndex] || ''}"
                                             data-question-id="${question.id}"
                                             id="drag_${question.id}_${itemIndex}">
                                            <span class="drag-number">${itemIndex + 1}</span>
                                            <span class="drag-text">${item}</span>
                                        </div>
                                    `).join('')}
                                </div>
                                
                                <div class="drop-zones">
                                    <h4>Drop Zones:</h4>
                                    ${leftItems.map((item, itemIndex) => `
                                        <div class="drop-zone" 
                                             data-item-index="${itemIndex}"
                                             data-correct="${correctPairs[itemIndex] || ''}"
                                             data-question-id="${question.id}"
                                             id="drop_${question.id}_${itemIndex}"
                                             ondrop="drop(event)" 
                                             ondragover="allowDrop(event)">
                                            <div class="drop-placeholder">DROP</div>
                                            <div class="dropped-item" id="dropped_${question.id}_${itemIndex}" style="display: none;"></div>
                                        </div>
                                    `).join('')}
                                </div>
                                
                                <div class="answer-items">
                                    <h4>Answer Options:</h4>
                                    ${rightItems.map((rightItem, itemIndex) => `
                                        <div class="answer-item" data-answer="${rightItem}">
                                            <span class="answer-number">${itemIndex + 1}</span>
                                            <span class="answer-text">${rightItem}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            
                            <div class="matching-score" id="matching_score_${question.id}">
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
            
            // Get question ID and item index
            const questionId = dropZone.dataset.questionId;
            const itemIndex = dropZone.dataset.itemIndex;
            const correctAnswer = dropZone.dataset.correct;
            
            // Get the dragged item's data
            const draggedText = draggedElement.querySelector('.drag-text').textContent;
            const draggedNumber = draggedElement.querySelector('.drag-number').textContent;
            
            // Check if this is the correct match
            const isCorrect = draggedText === correctAnswer;
            
            // Update drop zone
            const placeholder = dropZone.querySelector('.drop-placeholder');
            const droppedItem = dropZone.querySelector('.dropped-item');
            
            placeholder.style.display = 'none';
            droppedItem.style.display = 'flex';
            droppedItem.innerHTML = `
                <span class="drag-number">${draggedNumber}</span>
                <span class="drag-text">${draggedText}</span>
            `;
            
            // Store the answer
            dropZone.dataset.answer = draggedText;
            
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
        
        function dragEnd(ev) {
            ev.target.classList.remove('dragging');
        }
        
        function updateDragDropScore(questionId) {
            const dropZones = document.querySelectorAll(`[data-question-id="${questionId}"].drop-zone`);
            let correctCount = 0;
            let totalCount = dropZones.length;
            
            dropZones.forEach(zone => {
                const answer = zone.dataset.answer;
                const correct = zone.dataset.correct;
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
        
        function submitResponses() {
            const responses = {};
            
            // Collect all responses
            currentQuestions.forEach(question => {
                if (question.type === 'matching') {
                    // For matching questions, collect all drag-and-drop values
                    const matchingResponses = {};
                    const dropZones = document.querySelectorAll(`[data-question-id="${question.id}"].drop-zone`);
                    dropZones.forEach(zone => {
                        const itemIndex = zone.dataset.itemIndex;
                        const answer = zone.dataset.answer || '';
                        matchingResponses[itemIndex] = answer;
                    });
                    responses[question.type] = { [question.id]: matchingResponses };
                } else {
                    // For other question types
                    const response = document.querySelector(`input[name="question_${question.id}"]:checked, textarea[name="question_${question.id}"]`);
                    if (response) {
                        if (!responses[question.type]) {
                            responses[question.type] = {};
                        }
                        responses[question.type][question.id] = response.value;
                    }
                }
            });
            
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
                    alert('Responses submitted successfully!');
                    document.getElementById('questionForm').style.display = 'none';
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
    </script>
</body>
</html>
<?php
$content = ob_get_clean();
require_once 'includes/student_layout.php';
?>

<?php
require_once 'includes/teacher_init.php';
require_once 'includes/QuestionHandler.php';

// Create practice test tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS practice_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    section_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    timer_minutes INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS practice_test_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    practice_test_id INT NOT NULL,
    question_type ENUM('mcq', 'matching', 'essay') NOT NULL,
    question_text TEXT NOT NULL,
    choices JSON NULL,
    answer_key TEXT NULL,
    points INT DEFAULT 1,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (practice_test_id) REFERENCES practice_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS practice_test_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    practice_test_id INT NOT NULL,
    student_id INT NOT NULL,
    score DECIMAL(10,2) DEFAULT 0.00,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    answered_questions INT DEFAULT 0,
    total_questions INT DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (practice_test_id) REFERENCES practice_tests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission (practice_test_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create materials table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    section_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content TEXT,
    attachment_path VARCHAR(500),
    material_type ENUM('text', 'pdf', 'image', 'document') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
            case 'create_practice_test':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $sectionId = (int)($_POST['section_id'] ?? 0);
                $difficulty = $_POST['difficulty'] ?? 'medium';
                $timerMinutes = (int)($_POST['timer_minutes'] ?? 0);
                $selectedQuestions = $_POST['selected_questions'] ?? [];
                
                if (empty($title)) {
                    echo json_encode(['success' => false, 'error' => 'Test title is required']);
                    exit;
                }
                
                if ($sectionId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Please select a section']);
                    exit;
                }
                
                if (empty($selectedQuestions) || !is_array($selectedQuestions)) {
                    echo json_encode(['success' => false, 'error' => 'Please select at least one question']);
                    exit;
                }
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Create practice test
                    $stmt = $conn->prepare("INSERT INTO practice_tests (teacher_id, section_id, title, description, difficulty, timer_minutes) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iisssi", $_SESSION['teacher_id'], $sectionId, $title, $description, $difficulty, $timerMinutes);
                    $stmt->execute();
                    $practiceTestId = $conn->insert_id;
                    
                    // Add questions to practice test
                    $orderIndex = 1;
                    foreach ($selectedQuestions as $questionId) {
                        $questionId = (int)$questionId;
                        if ($questionId <= 0) continue;
                        
                        // Get question details from question_sets
                        $questionStmt = $conn->prepare("
                            SELECT q.*, qs.set_title 
                            FROM questions q 
                            INNER JOIN question_sets qs ON q.set_id = qs.id 
                            WHERE q.id = ? AND qs.teacher_id = ?
                        ");
                        $questionStmt->bind_param("ii", $questionId, $_SESSION['teacher_id']);
                        $questionStmt->execute();
                        $question = $questionStmt->get_result()->fetch_assoc();
                        
                        if ($question) {
                            $insertStmt = $conn->prepare("
                                INSERT INTO practice_test_questions 
                                (practice_test_id, question_type, question_text, choices, answer_key, points, order_index) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $insertStmt->bind_param("issssii", 
                                $practiceTestId, 
                                $question['type'], 
                                $question['question_text'], 
                                $question['choices'], 
                                $question['answer_key'], 
                                $question['points'], 
                                $orderIndex
                            );
                            $insertStmt->execute();
                            $orderIndex++;
                        }
                    }
                    
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Practice test created successfully', 'test_id' => $practiceTestId]);
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                exit;

            case 'get_available_questions':
                $sectionId = (int)($_POST['section_id'] ?? 0);
                if ($sectionId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid section']);
                    exit;
                }
                
                // Get questions from question sets for this section
                $stmt = $conn->prepare("
                    SELECT q.id, q.type, q.question_text, q.points, qs.set_title, qs.id as set_id
                    FROM questions q
                    INNER JOIN question_sets qs ON q.set_id = qs.id
                    WHERE qs.section_id = ? AND qs.teacher_id = ?
                    ORDER BY qs.set_title, q.order_index
                ");
                $stmt->bind_param("ii", $sectionId, $_SESSION['teacher_id']);
                $stmt->execute();
                $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                echo json_encode(['success' => true, 'questions' => $questions]);
                exit;

            case 'delete_practice_test':
                $testId = (int)($_POST['test_id'] ?? 0);
                if ($testId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid test ID']);
                    exit;
                }
                
                // Check if teacher owns this test
                $stmt = $conn->prepare("SELECT teacher_id FROM practice_tests WHERE id = ?");
                $stmt->bind_param("i", $testId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    echo json_encode(['success' => false, 'error' => 'Test not found']);
                    exit;
                }
                $test = $result->fetch_assoc();
                if ($test['teacher_id'] != $_SESSION['teacher_id']) {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    exit;
                }
                
                // Delete the test (cascade will handle questions and submissions)
                $stmt = $conn->prepare("DELETE FROM practice_tests WHERE id = ?");
                $stmt->bind_param("i", $testId);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Practice test deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to delete practice test']);
                }
                exit;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                exit;
        }
    } catch (Throwable $e) {
        if (ob_get_length()) { ob_clean(); }
        error_log('Practice test fatal error: ' . $e->getMessage());
        error_log('Fatal error file: ' . $e->getFile() . ' line: ' . $e->getLine());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
        exit;
    }
}


// Get materials for practice test creation
$materials = [];
$materialId = $_GET['material_id'] ?? null;

try {
    if ($materialId) {
        // Get specific material
        $stmt = $conn->prepare("
            SELECT m.*, s.name as section_name 
            FROM materials m 
            LEFT JOIN sections s ON m.section_id = s.id 
            WHERE m.id = ? AND m.teacher_id = ?
        ");
        $stmt->bind_param("ii", $materialId, $_SESSION['teacher_id']);
        $stmt->execute();
        $material = $stmt->get_result()->fetch_assoc();
        
        if ($material) {
            $materials[] = $material;
        }
    } else {
        // Get all materials for the teacher
        $stmt = $conn->prepare("
            SELECT m.*, s.name as section_name 
            FROM materials m 
            LEFT JOIN sections s ON m.section_id = s.id 
            WHERE m.teacher_id = ? 
            ORDER BY m.created_at DESC
        ");
        $stmt->bind_param("i", $_SESSION['teacher_id']);
        $stmt->execute();
        $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log('Error fetching materials: ' . $e->getMessage());
    $materials = []; // Set empty array if there's an error
}

// Get practice tests for all teacher sections
$practiceTests = [];
foreach ($teacherSections as $section) {
    $stmt = $conn->prepare("
        SELECT 
            pt.*,
            s.name as section_name,
            COUNT(ptq.id) as question_count,
            COALESCE(SUM(ptq.points), 0) as total_points
        FROM practice_tests pt
        INNER JOIN sections s ON pt.section_id = s.id
        LEFT JOIN practice_test_questions ptq ON pt.id = ptq.practice_test_id
        WHERE pt.teacher_id = ? AND pt.section_id = ?
        GROUP BY pt.id
        ORDER BY pt.created_at DESC
    ");
    $stmt->bind_param("ii", $_SESSION['teacher_id'], $section['id']);
    $stmt->execute();
    $tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($tests as $test) {
        $test['section_name'] = $section['section_name'] ?: $section['name'];
        $practiceTests[] = $test;
    }
}

// Include the teacher layout
require_once 'includes/teacher_layout.php';
render_teacher_header('teacher_practice_tests.php', $teacherName, 'Practice Tests');
?>

<style>
    body {
        background-color: #f5f5f5;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
    }

    .container {
        max-width: 2000px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Header Section */
    .header-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .header-icon {
        width: 50px;
        height: 50px;
        background: #6c757d;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        font-weight: bold;
    }

    .header-content h1 {
        margin: 0;
        color: #212529;
        font-size: 28px;
        font-weight: 600;
    }

    .header-content p {
        margin: 5px 0 0 0;
        color: #6c757d;
        font-size: 14px;
    }

    /* Main Content Area */
    .content-section {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .content-header {
        background: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
        color: #495057;
    }

    .content-body {
        padding: 30px;
        max-height: 500px;
        overflow-y: auto;
    }

    .material-title {
        font-size: 24px;
        font-weight: 600;
        color: #212529;
        text-align: center;
        margin-bottom: 20px;
    }

    .material-content {
        line-height: 1.6;
        color: #495057;
    }

    .material-content h2 {
        color: #212529;
        font-size: 20px;
        margin: 20px 0 10px 0;
    }

    .material-content ul {
        margin: 10px 0;
        padding-left: 20px;
    }

    .material-content li {
        margin: 5px 0;
    }

    /* Action Section */
    .action-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }

    .action-title {
        font-size: 18px;
        font-weight: 600;
        color: #212529;
        margin: 0 0 15px 0;
    }

    .btn-create-test {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 500;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-create-test:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 12px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    }
    
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        margin: 0;
        color: #1f2937;
    }
    
    .close {
        color: #6b7280;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        background: none;
        border: none;
    }
    
    .close:hover {
        color: #374151;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #374151;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.2s ease;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .questions-selection {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 15px;
    }
    
    .question-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin-bottom: 10px;
        background: #f9fafb;
    }
    
    .question-item:last-child {
        margin-bottom: 0;
    }
    
    .question-checkbox {
        margin-top: 4px;
    }
    
    .question-content {
        flex: 1;
    }
    
    .question-text {
        font-weight: 500;
        color: #1f2937;
        margin-bottom: 4px;
    }
    
    .question-meta {
        font-size: 12px;
        color: #6b7280;
    }
    
    .modal-footer {
        padding: 20px;
        border-top: 1px solid #e5e7eb;
        text-align: right;
    }
    
    .btn-primary {
        background: #3b82f6;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    .btn-primary:hover {
        background: #2563eb;
    }
    
    .btn-secondary {
        background: #6b7280;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
        margin-right: 10px;
    }
    
     .btn-secondary:hover {
         background: #4b5563;
     }

     /* Material Items */
     .material-item:hover {
         background: #f3f4f6 !important;
         border-color: #d1d5db !important;
         transform: translateY(-1px);
         box-shadow: 0 2px 4px rgba(0,0,0,0.1);
     }

     .materials-list {
         max-height: 400px;
         overflow-y: auto;
     }
</style>

<div class="container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="header-icon">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="header-content">
            <h1>Create Practice Test</h1>
            <p>Create practice tests based on your materials. These tests will be available to students for practice and assessment.</p>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="content-section">
        <div class="content-header">
            <?php if ($materialId && !empty($materials)): ?>
                <?php echo htmlspecialchars($materials[0]['title']); ?>
            <?php else: ?>
                Select Material for Practice Test
            <?php endif; ?>
        </div>
        <div class="content-body">
            <?php if (empty($materials)): ?>
                <div class="material-title">No Materials Available</div>
                <div class="material-content">
                    <p>You don't have any materials uploaded yet. Please upload some materials first before creating practice tests.</p>
                    <p><a href="teacher_content.php" style="color: #007bff; text-decoration: none;">Go to Content Management →</a></p>
                </div>
            <?php elseif ($materialId && !empty($materials)): ?>
                <!-- Display specific material content -->
                <div class="material-title"><?php echo htmlspecialchars($materials[0]['title']); ?></div>
                <div class="material-content">
                    <?php if (!empty($materials[0]['attachment_path'])): ?>
                        <!-- PDF or file attachment -->
                        <div style="text-align: center; margin: 20px 0;">
                            <iframe src="<?php echo htmlspecialchars($materials[0]['attachment_path']); ?>" 
                                    width="100%" 
                                    height="600px" 
                                    style="border: 1px solid #ddd; border-radius: 8px;">
                                <p>Your browser does not support PDFs. 
                                   <a href="<?php echo htmlspecialchars($materials[0]['attachment_path']); ?>" target="_blank">Download the PDF</a>
                                </p>
                            </iframe>
                        </div>
                    <?php else: ?>
                        <!-- Text content -->
                        <?php echo $materials[0]['content']; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Material selection -->
                <div class="material-title">Select a Material</div>
                <div class="material-content">
                    <p>Choose a material below to create a practice test based on its content:</p>
                    
                    <div class="materials-list" style="margin-top: 20px;">
                        <?php foreach ($materials as $material): ?>
                            <div class="material-item" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #f9fafb; cursor: pointer; transition: all 0.2s ease;" 
                                 onclick="window.location.href='?material_id=<?php echo $material['id']; ?>'">
                                <h3 style="margin: 0 0 8px 0; color: #1f2937; font-size: 18px;">
                                    <?php echo htmlspecialchars($material['title']); ?>
                                </h3>
                                <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 14px;">
                                    Section: <?php echo htmlspecialchars($material['section_name'] ?? 'All'); ?>
                                </p>
                                <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                    Created: <?php echo date('M j, Y', strtotime($material['created_at'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Section -->
    <?php if ($materialId && !empty($materials)): ?>
    <div class="action-section">
        <h3 class="action-title">Create Practice Test</h3>
        <button type="button" class="btn-create-test" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Create Practice Test
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Create Practice Test Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create Practice Test</h3>
            <button type="button" class="close" onclick="closeCreateModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="createTestForm">
                <div class="form-group">
                    <label for="testTitle">Test Title *</label>
                    <input type="text" id="testTitle" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="testDescription">Description</label>
                    <textarea id="testDescription" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="testSection">Section *</label>
                    <select id="testSection" name="section_id" required onchange="loadQuestions()">
                        <option value="">Select a section</option>
                        <?php foreach ($teacherSections as $section): ?>
                            <option value="<?php echo $section['id']; ?>">
                                <?php echo htmlspecialchars($section['section_name'] ?: $section['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="testDifficulty">Difficulty</label>
                    <select id="testDifficulty" name="difficulty">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="testTimer">Timer (minutes)</label>
                    <input type="number" id="testTimer" name="timer_minutes" min="0" value="0">
                    <small style="color: #6b7280;">Set to 0 for no time limit</small>
                </div>
                
                <div class="form-group">
                    <label>Select Questions *</label>
                    <div id="questionsContainer" class="questions-selection">
                        <p style="text-align: center; color: #6b7280; margin: 20px 0;">
                            Please select a section first to load available questions.
                        </p>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeCreateModal()">Cancel</button>
            <button type="button" class="btn-primary" onclick="createTest()">Create Test</button>
        </div>
    </div>
</div>

<script>
    function openCreateModal() {
        document.getElementById('createModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function closeCreateModal() {
        document.getElementById('createModal').style.display = 'none';
        document.body.style.overflow = 'auto';
        document.getElementById('createTestForm').reset();
        document.getElementById('questionsContainer').innerHTML = '<p style="text-align: center; color: #6b7280; margin: 20px 0;">Please select a section first to load available questions.</p>';
    }
    
    function loadQuestions() {
        const sectionId = document.getElementById('testSection').value;
        const container = document.getElementById('questionsContainer');
        
        if (!sectionId) {
            container.innerHTML = '<p style="text-align: center; color: #6b7280; margin: 20px 0;">Please select a section first to load available questions.</p>';
            return;
        }
        
        container.innerHTML = '<p style="text-align: center; color: #6b7280; margin: 20px 0;"><i class="fas fa-spinner fa-spin"></i> Loading questions...</p>';
        
        const params = new URLSearchParams({
            action: 'get_available_questions',
            section_id: sectionId
        });
        
        fetch('', {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.questions.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #6b7280; margin: 20px 0;">No questions available for this section. Please create some questions first.</p>';
                    return;
                }
                
                let html = '';
                data.questions.forEach(question => {
                    html += `
                        <div class="question-item">
                            <input type="checkbox" class="question-checkbox" value="${question.id}" id="q${question.id}">
                            <div class="question-content">
                                <div class="question-text">${question.question_text.substring(0, 100)}${question.question_text.length > 100 ? '...' : ''}</div>
                                <div class="question-meta">
                                    <strong>Set:</strong> ${question.set_title} | 
                                    <strong>Type:</strong> ${question.type.toUpperCase()} | 
                                    <strong>Points:</strong> ${question.points}
                                </div>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p style="text-align: center; color: #ef4444; margin: 20px 0;">Error loading questions: ' + (data.error || 'Unknown error') + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<p style="text-align: center; color: #ef4444; margin: 20px 0;">Network error loading questions</p>';
        });
    }
    
    function createTest() {
        const form = document.getElementById('createTestForm');
        const formData = new FormData(form);
        
        // Get selected questions
        const selectedQuestions = [];
        const checkboxes = document.querySelectorAll('.question-checkbox:checked');
        checkboxes.forEach(checkbox => {
            selectedQuestions.push(checkbox.value);
        });
        
        if (selectedQuestions.length === 0) {
            alert('Please select at least one question');
            return;
        }
        
        formData.append('action', 'create_practice_test');
        formData.append('selected_questions', JSON.stringify(selectedQuestions));
        
        // Show loading state
        const submitBtn = document.querySelector('.btn-primary');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Creating...';
        submitBtn.disabled = true;
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Practice test created successfully!');
                closeCreateModal();
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to create practice test'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error creating practice test');
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('createModal');
        if (event.target === modal) {
            closeCreateModal();
        }
    }
</script>

<?php
render_teacher_footer();
?>

<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/QuestionHandler.php';

try {
    $questionHandler = new QuestionHandler($conn);
} catch (Exception $e) {
    error_log('Failed to create QuestionHandler: ' . $e->getMessage());
    die('Failed to initialize question handler');
}

// Get material information
$material_id = (int)($_GET['material_id'] ?? 0);
$material = null;

if ($material_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM reading_materials WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $material_id, $_SESSION['teacher_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $material = $result->fetch_assoc();
    $stmt->close();
    
    if (!$material) {
        header('Location: teacher_content.php?error=Material not found');
        exit;
    }
} else {
    header('Location: teacher_content.php?error=Invalid material');
    exit;
}

// Get teacher's sections
$teacherSections = getTeacherSections($conn, $_SESSION['teacher_id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    if (!ob_get_level()) { ob_start(); }
    header('Content-Type: application/json');
    if (ob_get_length()) { ob_clean(); }

    try {
        switch ($_POST['action']) {
            case 'create_question_set':
                $set_title = trim($_POST['set_title'] ?? '');
                $section_ids = $_POST['section_ids'] ?? [];
                $questionsJson = $_POST['questions'] ?? '[]';
                $questions = json_decode($questionsJson, true);
                
                // Check if JSON decode failed
                if (json_last_error() !== JSON_ERROR_NONE) {
                    if (ob_get_level()) {
                        ob_clean();
                    }
                    echo json_encode(['success' => false, 'error' => 'Invalid questions data: ' . json_last_error_msg()]);
                    exit;
                }
                
                // Debug logging
                error_log('Material Question Builder - Set Title: ' . $set_title);
                error_log('Material Question Builder - Section IDs: ' . print_r($section_ids, true));
                error_log('Material Question Builder - Questions: ' . print_r($questions, true));
                
                if (empty($set_title) || empty($questions) || empty($section_ids)) {
                    if (ob_get_level()) {
                        ob_clean();
                    }
                    echo json_encode(['success' => false, 'error' => 'Title, questions, and sections are required']);
                    exit;
                }
                
                // Create question set for each selected section
                $createdSets = [];
                foreach ($section_ids as $section_id) {
                    $section_id = (int)$section_id;
                    if ($section_id <= 0) continue;
                    
                    // Create question set
                    $result = $questionHandler->createQuestionSet($_SESSION['teacher_id'], $section_id, $set_title);
                    
                    if ($result && isset($result['set_id'])) {
                        $setId = $result['set_id'];
                        
                        // Add questions to the set
                        foreach ($questions as $question) {
                            $result = $questionHandler->createQuestion($_SESSION['teacher_id'], $section_id, $setId, $question);
                            if (!$result['success']) {
                                error_log('Failed to add question to set: ' . $result['error']);
                                // Continue with other questions even if one fails
                            }
                        }
                        
                        // Link the question set to the material
                        $linkStmt = $conn->prepare("INSERT INTO material_question_links (material_id, question_set_id, created_at) VALUES (?, ?, NOW())");
                        $linkStmt->bind_param("ii", $material_id, $setId);
                        $linkStmt->execute();
                        $linkStmt->close();
                        
                        $createdSets[] = $setId;
                    }
                }
                
                if (empty($createdSets)) {
                    if (ob_get_level()) {
                        ob_clean();
                    }
                    echo json_encode(['success' => false, 'error' => 'Failed to create question sets']);
                    exit;
                }
                
                if (ob_get_level()) {
                    ob_clean();
                }
                echo json_encode(['success' => true, 'set_ids' => $createdSets, 'message' => 'Questions created successfully']);
                exit;
                
            default:
                if (ob_get_level()) {
                    ob_clean();
                }
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                exit;
        }
    } catch (Exception $e) {
        error_log('Question builder error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        // Ensure we have clean output buffer
        if (ob_get_level()) {
            ob_clean();
        }
        
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// Ensure material_question_links table exists
try {
    $conn->query("CREATE TABLE IF NOT EXISTS material_question_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL,
        question_set_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (material_id) REFERENCES reading_materials(id) ON DELETE CASCADE,
        FOREIGN KEY (question_set_id) REFERENCES question_sets(id) ON DELETE CASCADE,
        UNIQUE KEY unique_material_set (material_id, question_set_id)
    )");
} catch (Exception $e) {
    error_log('Table creation error: ' . $e->getMessage());
}

// Render header
require_once __DIR__ . '/includes/teacher_layout.php';
render_teacher_header('teacher_content.php', $_SESSION['teacher_name'] ?? 'Teacher', 'Material Question Builder');
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
            max-width: 2000px;
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
        
        .material-info {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .material-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .material-content {
            color: #666;
            line-height: 1.6;
            max-height: 500px;
            overflow-y: auto;
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e1e5e9;
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
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .question-item {
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        
        .question-type-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .question-type-select:focus {
            outline: none;
            border-color: #4285f4;
        }
        
        .question-points {
            width: 100px;
            padding: 8px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .question-options {
            margin-top: 15px;
        }
        
        .matching-pairs {
            margin-bottom: 15px;
        }
        
        .matching-pair {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .matching-pair input {
            flex: 1;
            padding: 8px;
            border: 2px solid #e1e5e9;
            border-radius: 4px;
        }
        
        .matching-pair span {
            color: #666;
            font-weight: 500;
        }
        
        .option-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            padding: 12px;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .option-row label {
            width: 100px;
            margin: 0;
            font-weight: 600;
            color: #333;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .option-input {
            flex: 1;
            min-width: 700px;
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            transition: border-color 0.3s;
        }
        
        .option-input:focus {
            outline: none;
            border-color: #4285f4;
        }
        
        .correct-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .btn-remove-option {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            min-width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        
        .btn-remove-option:hover {
            background: #c82333;
        }
        
        .add-option-btn {
            margin-top: 10px;
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .question-number {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .question-type {
            background: #4285f4;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .question-text {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 15px;
            transition: border-color 0.3s;
        }
        
        .question-text:focus {
            outline: none;
            border-color: #4285f4;
        }
        
        .options-container {
            margin-bottom: 15px;
        }
        
        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .option-input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .option-input:focus {
            outline: none;
            border-color: #4285f4;
        }
        
        .correct-option {
            background: #d4edda;
            border-color: #28a745;
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-remove:hover {
            background: #c82333;
        }
        
        .form-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .form-actions .btn {
            margin: 0 10px;
        }
        
        /* Matching question styles */
        .input-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            background: #f8f9fa;
        }
        
        .input-group label {
            min-width: 80px;
            margin: 0;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .input-group input {
            flex: 1;
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            transition: border-color 0.3s;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #4285f4;
        }
        
        .remove-option {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            min-width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        
        .remove-option:hover {
            background: #c82333;
        }
        
        .add-option {
            margin-top: 10px;
            padding: 8px 16px;
            font-size: 14px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .add-option:hover {
            background: #218838;
        }
        
        .match-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            background: #f8f9fa;
        }
        
        .match-row label {
            min-width: 80px;
            margin: 0;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .match-select {
            flex: 1;
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            transition: border-color 0.3s;
        }
        
        .match-select:focus {
            outline: none;
            border-color: #4285f4;
        }
        
        .matching-help-text {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
            color: #1976d2;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .matching-help-text i {
            margin-right: 8px;
            color: #2196f3;
        }
</style>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-question-circle"></i> Create Comprehension Questions</h1>
        <p>Create questions based on the material below. These questions will be added to the Question Bank and linked to this material.</p>
    </div>
    
    <div class="material-info">
        <div class="material-title"><?= htmlspecialchars($material['title']); ?></div>
        <div class="material-content">
            <?php
            // Check if this is a PDF or other file attachment
            if (!empty($material['attachment_path']) && !empty($material['attachment_name'])) {
                // Debug information
                error_log("Material attachment path: " . $material['attachment_path']);
                error_log("Material attachment type: " . $material['attachment_type']);
                error_log("Material title: " . $material['title']);
                
                // Check if it's a PDF file
                if (strtolower($material['attachment_type']) === 'application/pdf' || 
                    strtolower(pathinfo($material['attachment_name'], PATHINFO_EXTENSION)) === 'pdf') {
                    // Display PDF using embedded viewer
                    $pdfPath = ltrim($material['attachment_path'], '/');
                    $fullPdfPath = __DIR__ . '/' . $pdfPath;
                    
                    // Try alternative paths if the first one doesn't exist
                    if (!file_exists($fullPdfPath)) {
                        $altPath1 = __DIR__ . '/../' . $pdfPath;
                        $altPath2 = __DIR__ . '/uploads/' . basename($pdfPath);
                        $altPath3 = $pdfPath;
                        
                        if (file_exists($altPath1)) {
                            $fullPdfPath = $altPath1;
                        } elseif (file_exists($altPath2)) {
                            $fullPdfPath = $altPath2;
                        } elseif (file_exists($altPath3)) {
                            $fullPdfPath = $altPath3;
                        }
                    }
                    
                    if (file_exists($fullPdfPath)) {
                        // Create a relative path for the web
                        $webPath = str_replace(__DIR__ . '/', '', $fullPdfPath);
                        $webPath = str_replace('\\', '/', $webPath);
                        
                        echo '<div style="width: 100%; height: 600px; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">';
                        echo '<iframe src="' . htmlspecialchars($webPath) . '" width="100%" height="100%" style="border: none;"></iframe>';
                        echo '</div>';
                    } else {
                        // Fallback to extracted content if PDF viewer fails
                        $extractedContent = extractFileContent($material['attachment_path'], $material['attachment_type'], $material['title']);
                        echo $extractedContent;
                    }
                } else {
                    // For non-PDF files, extract content
                    $extractedContent = extractFileContent($material['attachment_path'], $material['attachment_type'], $material['title']);
                    echo $extractedContent;
                }
            } else {
                // Display regular content
                echo $material['content'];
            }
            ?>
        </div>
    </div>
    
    <div class="question-form">
        <h2 id="formTitleHeading">Create Questions</h2>
        <form id="questionBuilderForm">
            <div class="form-group" style="position:relative;">
                <label>Sections: <span style="color: red;">*</span></label>
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
                <div id="section-error" class="error-text" style="display: none;"></div>
            </div>
            
            <div class="form-group">
                <label for="set_title">Question Set Title: <span style="color: red;">*</span></label>
                <input type="text" id="set_title" name="set_title" 
                       value="Comprehension Questions - <?= htmlspecialchars($material['title']); ?>" required>
                <div id="title-error" class="error-text" style="display: none;"></div>
            </div>
            
            <!-- Questions Container -->
            <div id="questions-container"></div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="addQuestion()" style="margin-right: 10px;">
                    <i class="fas fa-plus"></i> Add New Question
                </button>
<<<<<<< HEAD
=======
                <?php 
                $aiAvailable = false;
                $aiProvider = 'none';
                
                // Check OpenAI
                if (file_exists(__DIR__ . '/config/ai_config.php')) {
                    require_once __DIR__ . '/config/ai_config.php';
                    if (isAIEnabled()) {
                        $aiAvailable = true;
                        $aiProvider = 'openai';
                    }
                }
                
                // Check Ollama
                if (!$aiAvailable && file_exists(__DIR__ . '/config/ollama_config.php')) {
                    require_once __DIR__ . '/config/ollama_config.php';
                    $ollamaStatus = checkOllamaStatus();
                    if ($ollamaStatus['running'] && $ollamaStatus['model_available']) {
                        $aiAvailable = true;
                        $aiProvider = 'ollama';
                    }
                }
                ?>
                
                <button type="button" class="btn btn-primary" onclick="showQuickQuestionTemplates()" style="margin-right: 10px;">
                    <i class="fas fa-magic"></i> Quick Templates
                </button>
                <?php if ($aiAvailable): ?>
                <button type="button" class="btn btn-success" onclick="showAIGeneratorModal()" style="margin-right: 10px;">
                    <i class="fas fa-robot"></i> AI Generate (<?= strtoupper($aiProvider) ?>)
                </button>
                <?php endif; ?>
>>>>>>> 2fcad03c27dbe56cf4dba808f3f13a749f478b16
                <button type="button" class="btn btn-primary" onclick="saveQuestions()" style="font-size: 18px; padding: 15px 30px; margin-top: 20px;">
                    <i class="fas fa-save"></i> Create Questions
                </button>
                <a href="teacher_content.php" class="btn btn-secondary" style="margin-left: 10px;">
                    <i class="fas fa-arrow-left"></i> Back to Materials
                </a>
            </div>
        </form>
    </div>
</div>

<<<<<<< HEAD
=======
<!-- AI Generator Modal -->
<div id="aiGeneratorModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <h2 style="margin: 0; color: #333;"><i class="fas fa-robot"></i> AI Question Generator</h2>
            <span class="close" onclick="closeAIGeneratorModal()" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        </div>
        
        <div class="modal-body">
            <div class="form-group">
                <label>Number of Questions:</label>
                <select id="aiNumQuestions" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="3">3 Questions</option>
                    <option value="5" selected>5 Questions</option>
                    <option value="7">7 Questions</option>
                    <option value="10">10 Questions</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Difficulty Level:</label>
                <select id="aiDifficulty" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="easy">Easy</option>
                    <option value="medium" selected>Medium</option>
                    <option value="hard">Hard</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Question Types:</label>
                <div style="display: flex; gap: 15px; margin-top: 8px;">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" id="aiTypeMcq" checked style="margin: 0;">
                        <span>Multiple Choice</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" id="aiTypeMatching" checked style="margin: 0;">
                        <span>Matching</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" id="aiTypeEssay" checked style="margin: 0;">
                        <span>Essay</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Material Preview:</label>
                <div id="aiMaterialPreview" style="max-height: 200px; overflow-y: auto; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; line-height: 1.4;">
                    <!-- Material content will be loaded here -->
                </div>
            </div>
        </div>
        
        <div class="modal-footer" style="margin-top: 20px; text-align: right; border-top: 1px solid #eee; padding-top: 15px;">
            <button type="button" class="btn btn-secondary" onclick="closeAIGeneratorModal()" style="margin-right: 10px;">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-success" onclick="generateAIQuestions()" id="aiGenerateBtn">
                <i class="fas fa-robot"></i> Generate Questions
            </button>
        </div>
    </div>
</div>

>>>>>>> 2fcad03c27dbe56cf4dba808f3f13a749f478b16
<script>
let questionCount = 0;

function addQuestion() {
    questionCount++;
    const container = document.getElementById('questions-container');
    
    const questionDiv = document.createElement('div');
    questionDiv.className = 'question-item';
    questionDiv.id = `question-${questionCount}`;
    
    questionDiv.innerHTML = `
        <div class="question-header">
            <span class="question-number">Question ${questionCount}</span>
            <button type="button" class="btn-remove" onclick="removeQuestion(${questionCount})">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        
        <div class="form-group">
            <label>Question Type:</label>
            <select class="question-type-select" onchange="handleQuestionTypeChange(this, ${questionCount})">
                <option value="">Select Question Type</option>
                <option value="mcq">Multiple Choice</option>
                <option value="matching">Matching</option>
                <option value="essay">Essay</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Question Text:</label>
            <textarea class="question-text" placeholder="Enter your question here..." name="question_${questionCount}_text" required></textarea>
        </div>
        
        <div class="form-group">
            <label>Points:</label>
            <input type="number" class="question-points" name="question_${questionCount}_points" value="1" min="1" required>
        </div>
        
        <div id="question-${questionCount}-options" class="question-options" style="display: none;">
            <!-- Options will be shown here when question type is selected -->
        </div>
    `;
    
    container.appendChild(questionDiv);
    
    // Update question numbering after adding
    updateQuestionNumbers();
}

function removeQuestion(questionNum) {
    const questionDiv = document.getElementById(`question-${questionNum}`);
    if (questionDiv) {
        questionDiv.remove();
        // Update question numbering after removal
        updateQuestionNumbers();
    }
}

function saveQuestions() {
    // Clear previous errors
    document.querySelectorAll('.error-text').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.invalid').forEach(el => el.classList.remove('invalid'));
    
    // Validate form
    const setTitle = document.getElementById('set_title').value.trim();
    const checkedSections = document.querySelectorAll('.sec-box:checked');
    
    if (!setTitle) {
        showError('title-error', 'Question set title is required');
        document.getElementById('set_title').classList.add('invalid');
        return;
    }
    
    if (checkedSections.length === 0) {
        showError('section-error', 'Please select at least one section');
        document.getElementById('sectionMulti').classList.add('invalid');
        return;
    }
    
    // Collect all questions
    const questions = [];
    const questionElements = document.querySelectorAll('.question-item');
    
    if (questionElements.length === 0) {
        alert('Please add at least one question by clicking "Add New Question"');
        return;
    }
    
    questionElements.forEach((element, index) => {
        const questionText = element.querySelector('textarea').value.trim();
        const questionType = element.querySelector('.question-type-select').value;
        const points = parseInt(element.querySelector('.question-points').value) || 1;
        
        if (!questionText) {
            alert(`Question ${index + 1}: Please enter the question text`);
            return;
        }
        
        if (!questionType) {
            alert(`Question ${index + 1}: Please select a question type`);
            return;
        }
        
        let questionData = {
            question_text: questionText,
            type: questionType,
            points: points
        };
        
        if (questionType === 'mcq') {
            const options = [];
            const correctRadio = element.querySelector('input[type="radio"]:checked');
            
            // Get all options
            const optionInputs = element.querySelectorAll('input[name*="_option_"]');
            optionInputs.forEach(input => {
                if (input.value.trim()) {
                    options.push(input.value.trim());
                }
            });
            
            if (options.length < 2) {
                alert(`Question ${index + 1}: Each multiple choice question must have at least 2 options`);
                return;
            }
            
            if (!correctRadio) {
                alert(`Question ${index + 1}: Please select the correct answer`);
                return;
            }
            
            // Map options to choice_a, choice_b, etc.
            const choiceLetters = ['a', 'b', 'c', 'd'];
            choiceLetters.forEach((letter, idx) => {
                if (options[idx]) {
                    questionData[`choice_${letter}`] = options[idx];
                }
            });
            
            // Convert numeric answer to letter (0=A, 1=B, etc.)
            const answerIndex = parseInt(correctRadio.value);
            questionData.correct_answer = String.fromCharCode(65 + answerIndex); // A, B, C, D
            
        } else if (questionType === 'matching') {
            const leftItems = [];
            const rightItems = [];
            const matches = [];
            
            // Get left items (rows)
            const leftInputs = element.querySelectorAll('input[name*="_left_items[]"]');
            leftInputs.forEach(input => {
                if (input.value.trim()) {
                    leftItems.push(input.value.trim());
                }
            });
            
            // Get right items (columns)
            const rightInputs = element.querySelectorAll('input[name*="_right_items[]"]');
            rightInputs.forEach(input => {
                if (input.value.trim()) {
                    rightItems.push(input.value.trim());
                }
            });
            
            // Get matches
            const matchSelects = element.querySelectorAll('select[name*="_matches[]"]');
            matchSelects.forEach(select => {
                if (select.value !== '') {
                    matches.push(parseInt(select.value));
                }
            });
            
            if (leftItems.length < 1) {
                alert(`Question ${index + 1}: Matching questions must have at least 1 row`);
                return;
            }
            
            if (rightItems.length < 1) {
                alert(`Question ${index + 1}: Matching questions must have at least 1 column`);
                return;
            }
            
            if (matches.length !== leftItems.length) {
                alert(`Question ${index + 1}: Please select matches for all rows`);
                return;
            }
            
            questionData.left_items = leftItems;
            questionData.right_items = rightItems;
            questionData.matches = matches;
            
        } else if (questionType === 'essay') {
            const rubric = element.querySelector('textarea[name*="_rubric"]').value.trim();
            
            if (!rubric) {
                alert(`Question ${index + 1}: Please provide a rubric for the essay question`);
                return;
            }
            
            questionData.rubric = rubric;
        }
        
        questions.push(questionData);
    });
    
    if (questions.length === 0) {
        alert('Please add at least one question');
        return;
    }
    
    // Prepare data for submission
    const submitData = new FormData();
    submitData.append('action', 'create_question_set');
    submitData.append('set_title', setTitle);
    
    // Add selected sections
    checkedSections.forEach(section => {
        submitData.append('section_ids[]', section.value);
    });
    
    submitData.append('questions', JSON.stringify(questions));
    
    // Show loading state
    const saveBtn = document.querySelector('button[onclick="saveQuestions()"]');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Questions...';
    saveBtn.disabled = true;
    
    // Submit to server
    fetch('', {
        method: 'POST',
        body: submitData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Questions created successfully and added to Question Bank!');
            window.location.href = 'clean_question_creator.php';
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating questions: ' + error.message);
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function showError(elementId, message) {
    const errorEl = document.getElementById(elementId);
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }
}

function updateQuestionNumbers() {
    const questionElements = document.querySelectorAll('.question-item');
    questionElements.forEach((element, index) => {
        const questionNumber = index + 1;
        const questionNumberSpan = element.querySelector('.question-number');
        if (questionNumberSpan) {
            questionNumberSpan.textContent = `Question ${questionNumber}`;
        }
    });
}

function handleQuestionTypeChange(selectElement, questionNum) {
    const questionType = selectElement.value;
    const optionsContainer = document.getElementById(`question-${questionNum}-options`);
    
    // Clear previous options
    optionsContainer.innerHTML = '';
    optionsContainer.style.display = 'none';
    
    if (questionType === 'mcq') {
        optionsContainer.innerHTML = `
            <div class="form-group">
                <label><strong>Multiple Choice Options</strong></label>
                <div class="options-container">
                    <div class="option-row">
                        <label>Option A:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionNum}_option_0" value="" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionNum}" value="0" required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionNum}, 0)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="option-row">
                        <label>Option B:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionNum}_option_1" value="" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionNum}" value="1" required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionNum}, 1)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="option-row">
                        <label>Option C:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionNum}_option_2" value="" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionNum}" value="2" required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionNum}, 2)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="option-row">
                        <label>Option D:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionNum}_option_3" value="" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionNum}" value="3" required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionNum}, 3)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-success add-option-btn" onclick="addOption(${questionNum})">
                    <i class="fas fa-plus"></i> Add Option
                </button>
            </div>
        `;
        optionsContainer.style.display = 'block';
    } else if (questionType === 'matching') {
        // Set default question text for matching questions
        const questionTextArea = document.querySelector(`#question-${questionNum} .question-text`);
        if (questionTextArea && !questionTextArea.value.trim()) {
            questionTextArea.value = "Match the following items with their correct answers:";
        }
        
        optionsContainer.innerHTML = `
            <div class="form-group">
                <label><strong>Left Items (Rows):</strong></label>
                <div id="matching-rows-${questionNum}">
                    <div class="input-group">
                        <label for="left_item_1_${questionNum}">Row 1:</label>
                        <input type="text" id="left_item_1_${questionNum}" name="question_${questionNum}_left_items[]" placeholder="Row 1" oninput="updateMatchingMatches(${questionNum})" required>
                        <button type="button" class="remove-option" onclick="removeMatchingRow(${questionNum}, 0)">×</button>
                    </div>
                    <div class="input-group">
                        <label for="left_item_2_${questionNum}">Row 2:</label>
                        <input type="text" id="left_item_2_${questionNum}" name="question_${questionNum}_left_items[]" placeholder="Row 2" oninput="updateMatchingMatches(${questionNum})" required>
                        <button type="button" class="remove-option" onclick="removeMatchingRow(${questionNum}, 1)">×</button>
                    </div>
                </div>
                <button type="button" class="add-option" onclick="addMatchingRow(${questionNum})">
                    <i class="fas fa-plus"></i> Add Row
                </button>
            </div>
            
            <div class="form-group">
                <label><strong>Right Items (Columns):</strong></label>
                <div id="matching-columns-${questionNum}">
                    <div class="input-group">
                        <label for="right_item_1_${questionNum}">Column 1:</label>
                        <input type="text" id="right_item_1_${questionNum}" name="question_${questionNum}_right_items[]" placeholder="Column 1" oninput="updateMatchingMatches(${questionNum})" required>
                        <button type="button" class="remove-option" onclick="removeMatchingColumn(${questionNum}, 0)">×</button>
                    </div>
                    <div class="input-group">
                        <label for="right_item_2_${questionNum}">Column 2:</label>
                        <input type="text" id="right_item_2_${questionNum}" name="question_${questionNum}_right_items[]" placeholder="Column 2" oninput="updateMatchingMatches(${questionNum})" required>
                        <button type="button" class="remove-option" onclick="removeMatchingColumn(${questionNum}, 1)">×</button>
                    </div>
                </div>
                <button type="button" class="add-option" onclick="addMatchingColumn(${questionNum})">
                    <i class="fas fa-plus"></i> Add Column
                </button>
            </div>
            
            <div class="form-group">
                <label><strong>Correct Matches:</strong></label>
                <div id="matching-matches-${questionNum}">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
            
            <div class="form-group">
                <small style="color: #6b7280; font-style: italic;">
                    For matching questions, this will be used as the main instruction above all matching pairs.
                </small>
            </div>
        `;
        optionsContainer.style.display = 'block';
        
        // Update points and question number for matching questions
        setTimeout(() => {
            updateMatchingMatches(questionNum);
        }, 200);
    } else if (questionType === 'essay') {
        optionsContainer.innerHTML = `
            <div class="form-group">
                <label>Essay Question Details:</label>
                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">Essay questions will be manually graded by the teacher.</p>
                <label>Rubric (required):</label>
                <textarea placeholder="e.g., Thesis (2), Evidence (3), Organization (2), Grammar (3)" name="question_${questionNum}_rubric" required></textarea>
                <small style="color: #666;">Describe scoring criteria or paste a rubric. Students will see this rubric.</small>
            </div>
        `;
        optionsContainer.style.display = 'block';
    }
}

function addOption(questionNum) {
    const optionsContainer = document.querySelector(`#question-${questionNum}-options .options-container`);
    const currentOptions = optionsContainer.querySelectorAll('.option-row');
    const optionCount = currentOptions.length;
    const optionLetter = String.fromCharCode(65 + optionCount); // A, B, C, D, E, F...
    
    const newOption = document.createElement('div');
    newOption.className = 'option-row';
    newOption.innerHTML = `
        <label>Option ${optionLetter}:</label>
        <input type="text" class="option-input" placeholder="" name="question_${questionNum}_option_${optionCount}" value="" required>
        <span class="correct-label">Correct Answer:</span>
        <input type="radio" name="correct_${questionNum}" value="${optionCount}" required>
        <button type="button" class="btn-remove-option" onclick="removeOption(${questionNum}, ${optionCount})">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    optionsContainer.appendChild(newOption);
}

function removeOption(questionNum, optionIndex) {
    const optionsContainer = document.querySelector(`#question-${questionNum}-options .options-container`);
    const optionRows = optionsContainer.querySelectorAll('.option-row');
    
    if (optionRows.length <= 2) {
        alert('You must have at least 2 options for a multiple choice question');
        return;
    }
    
    const optionToRemove = optionRows[optionIndex];
    if (optionToRemove) {
        optionToRemove.remove();
        
        // Renumber remaining options
        const remainingOptions = optionsContainer.querySelectorAll('.option-row');
        remainingOptions.forEach((row, index) => {
            const letter = String.fromCharCode(65 + index);
            const label = row.querySelector('label');
            const input = row.querySelector('.option-input');
            const radio = row.querySelector('input[type="radio"]');
            
            if (label) label.textContent = `Option ${letter}:`;
            if (input) {
                input.name = `question_${questionNum}_option_${index}`;
                input.placeholder = "";
                input.value = "";
            }
            if (radio) radio.value = index;
        });
    }
}

// Matching question functions
function addMatchingRow(questionNum) {
    const container = document.getElementById(`matching-rows-${questionNum}`);
    // Count only input elements to get the correct row number
    const existingInputs = container.querySelectorAll('input[type="text"]');
    const rowNumber = existingInputs.length + 1;
    const inputId = `left_item_${rowNumber}_${questionNum}`;
    
    const label = document.createElement('label');
    label.setAttribute('for', inputId);
    label.textContent = `Row ${rowNumber}:`;
    
    const input = document.createElement('input');
    input.type = 'text';
    input.id = inputId;
    input.name = `question_${questionNum}_left_items[]`;
    input.placeholder = `Row ${rowNumber}`;
    input.required = true;
    input.addEventListener('input', () => {
        updateMatchingMatches(questionNum);
    });
    input.setAttribute('data-listener-added', 'true');
    
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'remove-option';
    removeBtn.textContent = '×';
    removeBtn.onclick = () => removeMatchingRow(questionNum, rowNumber - 1);
    
    const inputGroup = document.createElement('div');
    inputGroup.className = 'input-group';
    inputGroup.appendChild(label);
    inputGroup.appendChild(input);
    inputGroup.appendChild(removeBtn);
    
    container.appendChild(inputGroup);
    
    // Automatically add a corresponding column
    addMatchingColumn(questionNum);
    
    // Update matches after a short delay to ensure DOM is updated
    setTimeout(() => {
        updateMatchingMatches(questionNum);
    }, 100);
}

function removeMatchingRow(questionNum, rowIndex) {
    const rowsContainer = document.getElementById(`matching-rows-${questionNum}`);
    const columnsContainer = document.getElementById(`matching-columns-${questionNum}`);
    const rows = rowsContainer.querySelectorAll('.input-group');
    const columns = columnsContainer.querySelectorAll('.input-group');
    
    if (rows.length <= 1) {
        alert('You must have at least 1 row for a matching question');
        return;
    }
    
    // Remove the row
    rows[rowIndex].remove();
    
    // Remove the corresponding column (same index)
    if (columns[rowIndex]) {
        columns[rowIndex].remove();
    }
    
    // Renumber remaining rows
    const remainingRows = rowsContainer.querySelectorAll('.input-group');
    remainingRows.forEach((row, index) => {
        const label = row.querySelector('label');
        const input = row.querySelector('input');
        const button = row.querySelector('.remove-option');
        
        if (label) {
            label.textContent = `Row ${index + 1}:`;
            label.setAttribute('for', `left_item_${index + 1}_${questionNum}`);
        }
        if (input) {
            input.id = `left_item_${index + 1}_${questionNum}`;
            input.name = `question_${questionNum}_left_items[]`;
            input.placeholder = `Row ${index + 1}`;
        }
        if (button) button.setAttribute('onclick', `removeMatchingRow(${questionNum}, ${index})`);
    });
    
    // Renumber remaining columns
    const remainingColumns = columnsContainer.querySelectorAll('.input-group');
    remainingColumns.forEach((column, index) => {
        const label = column.querySelector('label');
        const input = column.querySelector('input');
        const button = column.querySelector('.remove-option');
        
        if (label) {
            label.textContent = `Column ${index + 1}:`;
            label.setAttribute('for', `right_item_${index + 1}_${questionNum}`);
        }
        if (input) {
            input.id = `right_item_${index + 1}_${questionNum}`;
            input.name = `question_${questionNum}_right_items[]`;
            input.placeholder = `Column ${index + 1}`;
        }
        if (button) button.setAttribute('onclick', `removeMatchingColumn(${questionNum}, ${index})`);
    });
    
    // Update matches after a short delay to ensure DOM is updated
    setTimeout(() => {
        updateMatchingMatches(questionNum);
    }, 100);
}

function addMatchingColumn(questionNum) {
    const container = document.getElementById(`matching-columns-${questionNum}`);
    // Count only input elements to get the correct column number
    const existingInputs = container.querySelectorAll('input[type="text"]');
    const columnNumber = existingInputs.length + 1;
    const inputId = `right_item_${columnNumber}_${questionNum}`;
    
    const label = document.createElement('label');
    label.setAttribute('for', inputId);
    label.textContent = `Column ${columnNumber}:`;
    
    const input = document.createElement('input');
    input.type = 'text';
    input.id = inputId;
    input.name = `question_${questionNum}_right_items[]`;
    input.placeholder = `Column ${columnNumber}`;
    input.required = true;
    input.addEventListener('input', () => {
        updateMatchingMatches(questionNum);
    });
    input.setAttribute('data-listener-added', 'true');
    
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'remove-option';
    removeBtn.textContent = '×';
    removeBtn.onclick = () => removeMatchingColumn(questionNum, columnNumber - 1);
    
    const inputGroup = document.createElement('div');
    inputGroup.className = 'input-group';
    inputGroup.appendChild(label);
    inputGroup.appendChild(input);
    inputGroup.appendChild(removeBtn);
    
    container.appendChild(inputGroup);
    
    // Update matches after a short delay to ensure DOM is updated
    setTimeout(() => {
        updateMatchingMatches(questionNum);
    }, 100);
}

function removeMatchingColumn(questionNum, columnIndex) {
    const columnsContainer = document.getElementById(`matching-columns-${questionNum}`);
    const columns = columnsContainer.querySelectorAll('.input-group');
    
    if (columns.length <= 1) {
        alert('You must have at least 1 column for a matching question');
        return;
    }
    
    // Remove the column
    columns[columnIndex].remove();
    
    // Renumber remaining columns
    const remainingColumns = columnsContainer.querySelectorAll('.input-group');
    remainingColumns.forEach((column, index) => {
        const label = column.querySelector('label');
        const input = column.querySelector('input');
        const button = column.querySelector('.remove-option');
        
        if (label) {
            label.textContent = `Column ${index + 1}:`;
            label.setAttribute('for', `right_item_${index + 1}_${questionNum}`);
        }
        if (input) {
            input.id = `right_item_${index + 1}_${questionNum}`;
            input.name = `question_${questionNum}_right_items[]`;
            input.placeholder = `Column ${index + 1}`;
        }
        if (button) button.setAttribute('onclick', `removeMatchingColumn(${questionNum}, ${index})`);
    });
    
    // Update matches after a short delay to ensure DOM is updated
    setTimeout(() => {
        updateMatchingMatches(questionNum);
    }, 100);
}

function updateMatchingMatches(questionNum) {
    const rows = document.querySelectorAll(`input[name="question_${questionNum}_left_items[]"]`);
    const columns = document.querySelectorAll(`input[name="question_${questionNum}_right_items[]"]`);
    const container = document.getElementById(`matching-matches-${questionNum}`);
    const pointsField = document.querySelector(`#question-${questionNum} .question-points`);
    
    if (!container) {
        return;
    }
    
    // Store current selections before clearing
    const currentSelections = [];
    const existingSelects = container.querySelectorAll('select');
    existingSelects.forEach((select, index) => {
        currentSelections[index] = select.value;
    });
    
    container.innerHTML = '';
    
    // Count valid rows (non-empty)
    const validRows = Array.from(rows).filter(row => row.value.trim());
    const validColumns = Array.from(columns).filter(col => col.value.trim());
    
    // Calculate points based on number of all rows (not just valid ones)
    const calculatedPoints = Math.max(rows.length, 1); // At least 1 point
    if (pointsField) {
        pointsField.value = calculatedPoints;
        
        // Force update the display
        pointsField.dispatchEvent(new Event('input'));
        pointsField.dispatchEvent(new Event('change'));
    }
    
    rows.forEach((row, index) => {
        const rowValue = row.value.trim();
        const rowLabel = rowValue || `Row ${index + 1}`;
        
        const matchRow = document.createElement('div');
        matchRow.className = 'match-row';
        
        const label = document.createElement('label');
        label.textContent = `${rowLabel}:`;
        
        const select = document.createElement('select');
        select.name = `question_${questionNum}_matches[]`;
        select.className = 'match-select';
        select.required = true;
        
        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select match';
        select.appendChild(defaultOption);
        
        // Add column options
        columns.forEach((column, colIndex) => {
            const colValue = column.value.trim();
            if (colValue) {
                const option = document.createElement('option');
                option.value = colIndex;
                option.textContent = colValue;
                select.appendChild(option);
            }
        });
        
        // Restore previous selection if it exists and is still valid
        if (currentSelections[index] && currentSelections[index] < columns.length) {
            select.value = currentSelections[index];
        }
        
        matchRow.appendChild(label);
        matchRow.appendChild(select);
        container.appendChild(matchRow);
    });
}

function updateMatchingPoints(questionNum) {
    const questionElement = document.getElementById(`question-${questionNum}`);
    const pointsInput = questionElement.querySelector('.question-points');
    const rowsContainer = document.querySelector(`#question-${questionNum}-options .matching-rows`);
    const rows = rowsContainer.querySelectorAll('.matching-row');
    
    // Set points equal to number of rows
    if (pointsInput && rows.length > 0) {
        pointsInput.value = rows.length;
    }
}

function updateMatchingQuestionNumber(questionNum) {
    const questionElement = document.getElementById(`question-${questionNum}`);
    if (!questionElement) return;
    
    const questionNumberSpan = questionElement.querySelector('.question-number');
    const rowsContainer = document.querySelector(`#question-${questionNum}-options .matching-rows`);
    const columnsContainer = document.querySelector(`#question-${questionNum}-options .matching-columns`);
    
    if (rowsContainer && columnsContainer && questionNumberSpan) {
        const rows = rowsContainer.querySelectorAll('.matching-row');
        const columns = columnsContainer.querySelectorAll('.matching-column');
        
        // Update question number to show just the number of rows
        const rowCount = rows.length;
        questionNumberSpan.textContent = `Question ${questionNum}-${rowCount}`;
    }
}

function updateMatchingLabels(questionNum) {
    const rowsContainer = document.querySelector(`#question-${questionNum}-options .matching-rows`);
    const matchesContainer = document.querySelector(`#correct-matches-${questionNum}`);
    
    if (!rowsContainer || !matchesContainer) return;
    
    const rows = rowsContainer.querySelectorAll('.matching-row');
    const matchRows = matchesContainer.querySelectorAll('.match-row');
    
    // Update labels in correct matches section
    rows.forEach((row, index) => {
        const input = row.querySelector('.matching-input');
        const matchRow = matchRows[index];
        
        if (input && matchRow) {
            const label = matchRow.querySelector('label');
            const inputValue = input.value.trim();
            
            if (label) {
                // Use the input value if it exists, otherwise use default "Row X"
                if (inputValue) {
                    label.textContent = `${inputValue}:`;
                } else {
                    label.textContent = `Row ${index + 1}:`;
                }
            }
        }
    });
}

function updateCorrectMatchOptions(questionNum) {
    const columnsContainer = document.querySelector(`#question-${questionNum}-options .matching-columns`);
    const matchesContainer = document.querySelector(`#correct-matches-${questionNum}`);
    
    if (!columnsContainer || !matchesContainer) return;
    
    const columns = columnsContainer.querySelectorAll('.matching-column');
    const matchSelects = matchesContainer.querySelectorAll('.match-select');
    
    // Collect all column values
    const columnValues = [];
    columns.forEach((column) => {
        const input = column.querySelector('.matching-input');
        if (input) {
            const value = input.value.trim();
            if (value) {
                columnValues.push(value);
            }
        }
    });
    
    // Update all dropdown options
    matchSelects.forEach((select) => {
        const currentValue = select.value;
        
        // Clear existing options
        select.innerHTML = '<option value="">Select match</option>';
        
        // Add new options based on column values
        columnValues.forEach((value, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = value;
            select.appendChild(option);
        });
        
        // Restore previous selection if it's still valid
        if (currentValue && currentValue < columnValues.length) {
            select.value = currentValue;
        }
    });
}

<<<<<<< HEAD
=======
// AI Generation Functions
function showAIGeneratorModal() {
    const modal = document.getElementById('aiGeneratorModal');
    const materialContentElement = document.querySelector('.material-content');
    const materialTitle = document.querySelector('.material-title').textContent;
    
    // Get material content - handle different content types
    let materialContent = '';
    
    if (materialContentElement) {
        // Check if it's a PDF iframe
        const iframe = materialContentElement.querySelector('iframe');
        if (iframe) {
            // For PDF files, we need to provide educational content based on the title
            materialContent = generateEducationalContentFromTitle(materialTitle);
        } else {
            // For text content
            materialContent = materialContentElement.textContent || materialContentElement.innerText || '';
        }
    }
    
    // If content is still empty or too short, generate educational content
    if (!materialContent || materialContent.trim().length < 100) {
        materialContent = generateEducationalContentFromTitle(materialTitle);
    }
    
    // Load material preview
    const preview = document.getElementById('aiMaterialPreview');
    const truncatedContent = materialContent.length > 500 ? 
        materialContent.substring(0, 500) + '...' : materialContent;
    preview.textContent = truncatedContent;
    
    modal.style.display = 'block';
}

function generateEducationalContentFromTitle(title) {
    // Generate educational content based on the material title
    const lowerTitle = title.toLowerCase();
    
    if (lowerTitle.includes('english') || lowerTitle.includes('language')) {
        return "This English language learning material contains reading comprehension exercises, grammar lessons, and writing activities. The document includes literary texts, vocabulary exercises, and language skills development activities. The material covers topics such as reading comprehension, grammar rules, vocabulary building, creative writing, and literary analysis. Students will learn about different text types, story elements, character development, and how to analyze and interpret various forms of literature. The content is designed to enhance reading, writing, and communication skills for Grade 6 students.";
    } else if (lowerTitle.includes('math') || lowerTitle.includes('mathematics')) {
        return "This mathematics learning material contains problem-solving exercises, mathematical concepts, and practice activities. The document includes step-by-step solutions, examples, and mathematical reasoning exercises. The material covers various mathematical topics including arithmetic operations, fractions, decimals, geometry, measurement, and problem-solving strategies. Students will learn about number systems, basic operations, geometric shapes, measurement units, and how to apply mathematical concepts to real-world situations. The content is designed to develop mathematical thinking and problem-solving skills for Grade 6 students.";
    } else if (lowerTitle.includes('science')) {
        return "This science learning material contains scientific concepts, experiments, and educational activities. The document includes information about natural phenomena, scientific methods, and hands-on experiments. The material covers topics such as the scientific method, basic physics concepts, chemistry fundamentals, biology basics, earth science, and environmental studies. Students will learn about observation, hypothesis formation, experimentation, data collection, and scientific reasoning. The content is designed to develop scientific thinking and inquiry skills for Grade 6 students.";
    } else if (lowerTitle.includes('quarter') || lowerTitle.includes('module')) {
        return "This is a comprehensive educational module containing structured learning materials. The document includes lessons, activities, and educational resources designed for student learning. The material covers key concepts and learning objectives with examples and exercises. Students will engage with various educational activities including reading comprehension, problem-solving tasks, creative exercises, and assessment activities. The content is organized to provide a systematic learning experience that builds upon previous knowledge and prepares students for advanced concepts. The material is designed to support Grade 6 students in their academic development.";
    } else {
        return "This educational material contains structured learning content covering various academic subjects and topics suitable for Grade 6 students. The document includes lessons, activities, and educational resources designed to enhance student learning. The material covers key concepts, learning objectives, examples, and exercises that help students develop their knowledge and skills. Students will engage with reading materials, problem-solving activities, creative tasks, and assessment exercises. The content is designed to be age-appropriate and aligned with Grade 6 curriculum standards, providing a comprehensive learning experience that supports academic growth and development.";
    }
}

function closeAIGeneratorModal() {
    const modal = document.getElementById('aiGeneratorModal');
    modal.style.display = 'none';
}

function showQuickQuestionTemplates() {
    const materialTitle = document.querySelector('.material-title').textContent;
    const lowerTitle = materialTitle.toLowerCase();
    
    let templates = [];
    
    if (lowerTitle.includes('english') || lowerTitle.includes('language') || lowerTitle.includes('figurative')) {
        templates = [
            {
                type: 'mcq',
                question: 'What is figurative language?',
                options: ['Language that uses literal meanings', 'Language that uses nonliteral meanings', 'Language that is always formal', 'Language that is always informal'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which of the following is an example of a simile?',
                options: ['The sun is a golden ball', 'She runs like the wind', 'The tree danced in the wind', 'Time is money'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is a metaphor?',
                options: ['A comparison using "like" or "as"', 'A direct comparison without "like" or "as"', 'Giving human traits to objects', 'An exaggeration for effect'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which sentence contains personification?',
                options: ['The wind blew strongly', 'The wind whispered through the trees', 'The wind was very loud', 'The wind stopped blowing'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is an idiom?',
                options: ['A literal expression', 'A phrase with a different meaning than its words', 'A type of metaphor', 'A comparison using "as"'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which is an example of hyperbole?',
                options: ['I am very tired', 'I am so tired I could sleep for a year', 'I need to rest', 'I should go to bed'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is alliteration?',
                options: ['Repeating vowel sounds', 'Repeating consonant sounds', 'Repeating words', 'Repeating phrases'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which sentence uses onomatopoeia?',
                options: ['The cat was very quiet', 'The cat meowed loudly', 'The cat was sleeping', 'The cat was hungry'],
                correct: 'B',
                points: 2
            },
            {
                type: 'matching',
                question: 'Match the figurative language types with their definitions:',
                leftItems: ['Simile', 'Metaphor', 'Personification', 'Hyperbole'],
                rightItems: ['Comparing using "like" or "as"', 'Direct comparison without "like" or "as"', 'Giving human traits to non-human things', 'Extreme exaggeration for effect'],
                matches: [0, 1, 2, 3],
                points: 3
            },
            {
                type: 'matching',
                question: 'Match the examples with their figurative language types:',
                leftItems: ['"Busy as a bee"', '"The stars danced"', '"It\'s raining cats and dogs"', '"Her voice is music"'],
                rightItems: ['Simile', 'Personification', 'Idiom', 'Metaphor'],
                matches: [0, 1, 2, 3],
                points: 3
            },
            {
                type: 'essay',
                question: 'Explain the difference between a simile and a metaphor. Give two examples of each.',
                rubric: 'Definition (2 points), Examples (2 points), Clarity (1 point)',
                points: 5
            },
            {
                type: 'essay',
                question: 'Write a short paragraph using at least three different types of figurative language.',
                rubric: 'Variety (2 points), Creativity (2 points), Grammar (1 point)',
                points: 5
            },
            {
                type: 'mcq',
                question: 'What is the purpose of using figurative language?',
                options: ['To confuse readers', 'To make writing more interesting and vivid', 'To make sentences longer', 'To use difficult words'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which sentence contains alliteration?',
                options: ['The big brown bear', 'The bear was very big', 'The bear ate honey', 'The bear slept quietly'],
                correct: 'A',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the meaning of the idiom "break the ice"?',
                options: ['To break frozen water', 'To start a conversation', 'To be very cold', 'To break something'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which is an example of onomatopoeia?',
                options: ['The dog was barking', 'Woof! Woof!', 'The dog was loud', 'The dog was angry'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What type of figurative language is "The classroom was a zoo"?',
                options: ['Simile', 'Metaphor', 'Personification', 'Hyperbole'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which sentence uses hyperbole?',
                options: ['I am hungry', 'I am so hungry I could eat a horse', 'I need to eat', 'I should have lunch'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is personification in "The flowers danced in the breeze"?',
                options: ['The word "danced"', 'The word "flowers"', 'The word "breeze"', 'The word "in"'],
                correct: 'A',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which is the best example of a simile?',
                options: ['She is a star', 'She shines like a star', 'She is very bright', 'She is talented'],
                correct: 'B',
                points: 2
            },
            {
                type: 'essay',
                question: 'Identify and explain the figurative language used in this sentence: "The old car coughed and sputtered before finally starting."',
                rubric: 'Identification (2 points), Explanation (2 points), Understanding (1 point)',
                points: 5
            }
        ];
    } else if (lowerTitle.includes('math') || lowerTitle.includes('mathematics')) {
        templates = [
            {
                type: 'mcq',
                question: 'What is the result of 15 + 27?',
                options: ['32', '42', '52', '62'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which fraction is equivalent to 1/2?',
                options: ['2/4', '3/6', '4/8', 'All of the above'],
                correct: 'D',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is 8 × 7?',
                options: ['54', '56', '58', '60'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is 144 ÷ 12?',
                options: ['10', '11', '12', '13'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the area of a rectangle with length 8 cm and width 5 cm?',
                options: ['13 cm²', '26 cm²', '40 cm²', '45 cm²'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the perimeter of a square with side length 6 cm?',
                options: ['12 cm', '18 cm', '24 cm', '36 cm'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is 3/4 + 1/4?',
                options: ['1/2', '3/4', '1', '4/4'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is 2.5 + 1.7?',
                options: ['3.2', '4.2', '4.12', '4.22'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the value of 5²?',
                options: ['10', '15', '20', '25'],
                correct: 'D',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the square root of 64?',
                options: ['6', '7', '8', '9'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is 15% of 200?',
                options: ['20', '25', '30', '35'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the mean of 4, 6, 8, 10, 12?',
                options: ['6', '7', '8', '9'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the mode of 2, 3, 3, 4, 5, 3?',
                options: ['2', '3', '4', '5'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the median of 1, 3, 5, 7, 9?',
                options: ['3', '4', '5', '6'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is 3/5 as a decimal?',
                options: ['0.3', '0.5', '0.6', '0.8'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is 0.75 as a fraction?',
                options: ['3/4', '7/10', '75/100', 'Both A and C'],
                correct: 'D',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the next number in the pattern: 2, 4, 8, 16, ___?',
                options: ['20', '24', '32', '64'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the greatest common factor of 12 and 18?',
                options: ['3', '4', '6', '9'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the least common multiple of 4 and 6?',
                options: ['10', '12', '18', '24'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the volume of a cube with side length 3 cm?',
                options: ['9 cm³', '18 cm³', '27 cm³', '36 cm³'],
                correct: 'C',
                points: 2
            },
            {
                type: 'matching',
                question: 'Match the operations with their symbols:',
                leftItems: ['Addition', 'Subtraction', 'Multiplication', 'Division'],
                rightItems: ['+', '-', '×', '÷'],
                matches: [0, 1, 2, 3],
                points: 3
            },
            {
                type: 'essay',
                question: 'Explain how to solve a word problem step by step. Use an example.',
                rubric: 'Understanding (2 points), Steps (2 points), Example (1 point)',
                points: 5
            }
        ];
    } else if (lowerTitle.includes('science')) {
        templates = [
            {
                type: 'mcq',
                question: 'What is the scientific method?',
                options: ['A way to prove theories', 'A systematic approach to solving problems', 'A type of experiment', 'A scientific law'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which of the following is a renewable energy source?',
                options: ['Coal', 'Oil', 'Solar power', 'Natural gas'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the process by which plants make their own food?',
                options: ['Respiration', 'Photosynthesis', 'Digestion', 'Transpiration'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the largest planet in our solar system?',
                options: ['Earth', 'Saturn', 'Jupiter', 'Neptune'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the chemical symbol for water?',
                options: ['H2O', 'CO2', 'O2', 'H2SO4'],
                correct: 'A',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the center of an atom called?',
                options: ['Electron', 'Proton', 'Nucleus', 'Neutron'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the force that pulls objects toward Earth?',
                options: ['Magnetism', 'Gravity', 'Friction', 'Inertia'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the process of water turning into vapor?',
                options: ['Condensation', 'Evaporation', 'Precipitation', 'Transpiration'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the hardest natural substance on Earth?',
                options: ['Gold', 'Iron', 'Diamond', 'Quartz'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the speed of light?',
                options: ['300,000 km/s', '3,000,000 km/s', '30,000 km/s', '3,000 km/s'],
                correct: 'A',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the study of living things called?',
                options: ['Physics', 'Chemistry', 'Biology', 'Geology'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the smallest unit of life?',
                options: ['Tissue', 'Organ', 'Cell', 'Molecule'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the process of cell division called?',
                options: ['Photosynthesis', 'Respiration', 'Mitosis', 'Osmosis'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the protective layer around Earth called?',
                options: ['Mantle', 'Crust', 'Atmosphere', 'Core'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the study of weather called?',
                options: ['Geology', 'Meteorology', 'Astronomy', 'Oceanography'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the energy source for most life on Earth?',
                options: ['Moon', 'Stars', 'Sun', 'Earth\'s core'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the process of rocks breaking down called?',
                options: ['Erosion', 'Weathering', 'Deposition', 'Sedimentation'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the study of fossils called?',
                options: ['Paleontology', 'Archaeology', 'Anthropology', 'Geology'],
                correct: 'A',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the chemical symbol for gold?',
                options: ['Go', 'Gd', 'Au', 'Ag'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the process of plants losing water through leaves?',
                options: ['Photosynthesis', 'Transpiration', 'Respiration', 'Evaporation'],
                correct: 'B',
                points: 2
            },
            {
                type: 'matching',
                question: 'Match the planets with their characteristics:',
                leftItems: ['Mercury', 'Venus', 'Earth', 'Mars'],
                rightItems: ['Closest to Sun', 'Hottest planet', 'Has life', 'Red planet'],
                matches: [0, 1, 2, 3],
                points: 3
            },
            {
                type: 'essay',
                question: 'Describe the water cycle and its importance to life on Earth.',
                rubric: 'Accuracy (2 points), Completeness (2 points), Clarity (1 point)',
                points: 5
            }
        ];
    } else {
        templates = [
            {
                type: 'mcq',
                question: 'What is the main topic of this material?',
                options: ['The topic is clearly stated', 'The topic is mentioned briefly', 'The topic is not discussed', 'The topic is confusing'],
                correct: 'A',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Which statement best summarizes the content?',
                options: ['Statement A', 'Statement B', 'Statement C', 'Statement D'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the purpose of this material?',
                options: ['To entertain', 'To inform', 'To persuade', 'All of the above'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'Who is the intended audience?',
                options: ['Children', 'Adults', 'Students', 'Professionals'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the reading level of this material?',
                options: ['Elementary', 'Middle school', 'High school', 'College'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What type of text is this?',
                options: ['Narrative', 'Expository', 'Persuasive', 'Descriptive'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the tone of this material?',
                options: ['Formal', 'Informal', 'Humorous', 'Serious'],
                correct: 'A',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the main idea of the first paragraph?',
                options: ['Idea A', 'Idea B', 'Idea C', 'Idea D'],
                correct: 'A',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What supporting details are provided?',
                options: ['Many details', 'Some details', 'Few details', 'No details'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What conclusion can be drawn?',
                options: ['Conclusion A', 'Conclusion B', 'Conclusion C', 'Conclusion D'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What vocabulary words are important?',
                options: ['All words', 'Key terms', 'Long words', 'Short words'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What questions does this material answer?',
                options: ['Who and what', 'When and where', 'Why and how', 'All of the above'],
                correct: 'D',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the organizational pattern?',
                options: ['Chronological', 'Cause and effect', 'Compare and contrast', 'Problem and solution'],
                correct: 'A',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the author\'s point of view?',
                options: ['First person', 'Second person', 'Third person', 'Objective'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the most important information?',
                options: ['Introduction', 'Main content', 'Conclusion', 'All sections'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What connections can be made?',
                options: ['To other texts', 'To personal experience', 'To world events', 'All of the above'],
                correct: 'D',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the difficulty level?',
                options: ['Easy', 'Medium', 'Hard', 'Very hard'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What prior knowledge is needed?',
                options: ['None', 'Some', 'A lot', 'Expert level'],
                correct: 'B',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the best way to study this material?',
                options: ['Read once', 'Read and take notes', 'Read and discuss', 'Read and practice'],
                correct: 'C',
                points: 2
            },
            {
                type: 'mcq',
                question: 'What is the most challenging part?',
                options: ['Vocabulary', 'Concepts', 'Application', 'All of the above'],
                correct: 'D',
                points: 2
            },
            {
                type: 'matching',
                question: 'Match the concepts with their definitions:',
                leftItems: ['Main idea', 'Supporting details', 'Conclusion', 'Evidence'],
                rightItems: ['Central point', 'Supporting information', 'Final thoughts', 'Proof or examples'],
                matches: [0, 1, 2, 3],
                points: 3
            },
            {
                type: 'essay',
                question: 'Explain the key concepts from this material and how they relate to each other.',
                rubric: 'Understanding (2 points), Examples (2 points), Analysis (1 point)',
                points: 5
            }
        ];
    }
    
    // Clear existing questions
    document.getElementById('questions-container').innerHTML = '';
    questionCount = 0;
    
    // Add template questions
    templates.forEach(template => {
        addTemplateQuestion(template);
    });
    
    alert(`Added ${templates.length} template questions based on your material: "${materialTitle}"\n\nYou can now edit these questions and add more as needed!`);
}

function addTemplateQuestion(template) {
    questionCount++;
    const container = document.getElementById('questions-container');
    
    const questionDiv = document.createElement('div');
    questionDiv.className = 'question-item';
    questionDiv.id = `question-${questionCount}`;
    
    let questionHTML = `
        <div class="question-header">
            <span class="question-number">Question ${questionCount}</span>
            <span class="question-type">${template.type.toUpperCase()}</span>
            <button type="button" class="btn-remove" onclick="removeQuestion(${questionCount})">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        
        <div class="form-group">
            <label>Question Type:</label>
            <select class="question-type-select" onchange="handleQuestionTypeChange(this, ${questionCount})">
                <option value="mcq" ${template.type === 'mcq' ? 'selected' : ''}>Multiple Choice</option>
                <option value="matching" ${template.type === 'matching' ? 'selected' : ''}>Matching</option>
                <option value="essay" ${template.type === 'essay' ? 'selected' : ''}>Essay</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Question Text:</label>
            <textarea class="question-text" placeholder="Enter your question here..." name="question_${questionCount}_text" required>${template.question}</textarea>
        </div>
        
        <div class="form-group">
            <label>Points:</label>
            <input type="number" class="question-points" name="question_${questionCount}_points" value="${template.points}" min="1" required>
        </div>
        
        <div id="question-${questionCount}-options" class="question-options">
    `;
    
    if (template.type === 'mcq') {
        questionHTML += `
            <div class="form-group">
                <label><strong>Multiple Choice Options</strong></label>
                <div class="options-container">
                    <div class="option-row">
                        <label>Option A:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionCount}_option_0" value="${template.options[0] || ''}" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionCount}" value="0" ${template.correct === 'A' ? 'checked' : ''} required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionCount}, 0)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="option-row">
                        <label>Option B:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionCount}_option_1" value="${template.options[1] || ''}" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionCount}" value="1" ${template.correct === 'B' ? 'checked' : ''} required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionCount}, 1)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="option-row">
                        <label>Option C:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionCount}_option_2" value="${template.options[2] || ''}" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionCount}" value="2" ${template.correct === 'C' ? 'checked' : ''} required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionCount}, 2)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="option-row">
                        <label>Option D:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionCount}_option_3" value="${template.options[3] || ''}" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionCount}" value="3" ${template.correct === 'D' ? 'checked' : ''} required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionCount}, 3)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-success add-option-btn" onclick="addOption(${questionCount})">
                    <i class="fas fa-plus"></i> Add Option
                </button>
            </div>
        `;
    } else if (template.type === 'matching') {
        questionHTML += `
            <div class="form-group">
                <label><strong>Left Items (Rows):</strong></label>
                <div id="matching-rows-${questionCount}">
        `;
        
        template.leftItems.forEach((item, index) => {
            questionHTML += `
                <div class="input-group">
                    <label for="left_item_${index + 1}_${questionCount}">Row ${index + 1}:</label>
                    <input type="text" id="left_item_${index + 1}_${questionCount}" name="question_${questionCount}_left_items[]" placeholder="Row ${index + 1}" oninput="updateMatchingMatches(${questionCount})" value="${item}" required>
                    <button type="button" class="remove-option" onclick="removeMatchingRow(${questionCount}, ${index})">×</button>
                </div>
            `;
        });
        
        questionHTML += `
                </div>
                <button type="button" class="add-option" onclick="addMatchingRow(${questionCount})">
                    <i class="fas fa-plus"></i> Add Row
                </button>
            </div>
            
            <div class="form-group">
                <label><strong>Right Items (Columns):</strong></label>
                <div id="matching-columns-${questionCount}">
        `;
        
        template.rightItems.forEach((item, index) => {
            questionHTML += `
                <div class="input-group">
                    <label for="right_item_${index + 1}_${questionCount}">Column ${index + 1}:</label>
                    <input type="text" id="right_item_${index + 1}_${questionCount}" name="question_${questionCount}_right_items[]" placeholder="Column ${index + 1}" oninput="updateMatchingMatches(${questionCount})" value="${item}" required>
                    <button type="button" class="remove-option" onclick="removeMatchingColumn(${questionCount}, ${index})">×</button>
                </div>
            `;
        });
        
        questionHTML += `
                </div>
                <button type="button" class="add-option" onclick="addMatchingColumn(${questionCount})">
                    <i class="fas fa-plus"></i> Add Column
                </button>
            </div>
            
            <div class="form-group">
                <label><strong>Correct Matches:</strong></label>
                <div id="matching-matches-${questionCount}">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
        `;
    } else if (template.type === 'essay') {
        questionHTML += `
            <div class="form-group">
                <label>Essay Question Details:</label>
                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">Essay questions will be manually graded by the teacher.</p>
                <label>Rubric (required):</label>
                <textarea placeholder="e.g., Thesis (2), Evidence (3), Organization (2), Grammar (3)" name="question_${questionCount}_rubric" required>${template.rubric || ''}</textarea>
                <small style="color: #666;">Describe scoring criteria or paste a rubric. Students will see this rubric.</small>
            </div>
        `;
    }
    
    questionHTML += `
        </div>
    `;
    
    questionDiv.innerHTML = questionHTML;
    container.appendChild(questionDiv);
    
    // Update question numbering
    updateQuestionNumbers();
    
    // For matching questions, update matches after a delay
    if (template.type === 'matching') {
        setTimeout(() => {
            updateMatchingMatches(questionCount);
        }, 200);
    }
}

async function generateAIQuestions() {
    const generateBtn = document.getElementById('aiGenerateBtn');
    const originalText = generateBtn.innerHTML;
    
    // Show loading state
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    generateBtn.disabled = true;
    
    try {
        // Get form data
        const materialContent = document.querySelector('.material-content').textContent;
        const materialTitle = document.querySelector('.material-title').textContent;
        const numQuestions = document.getElementById('aiNumQuestions').value;
        const difficulty = document.getElementById('aiDifficulty').value;
        
        // Get selected question types
        const questionTypes = [];
        if (document.getElementById('aiTypeMcq').checked) questionTypes.push('mcq');
        if (document.getElementById('aiTypeMatching').checked) questionTypes.push('matching');
        if (document.getElementById('aiTypeEssay').checked) questionTypes.push('essay');
        
        if (questionTypes.length === 0) {
            alert('Please select at least one question type');
            return;
        }
        
        // Determine which AI provider to use
        const aiProvider = '<?= $aiProvider ?>';
        const apiEndpoint = aiProvider === 'ollama' ? 'ollama_question_generator.php' : 'ai_question_generator.php';
        
        // Call AI API
        const response = await fetch(apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                material_content: materialContent,
                material_title: materialTitle,
                num_questions: parseInt(numQuestions),
                question_types: questionTypes,
                difficulty: difficulty
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Clear existing questions
            document.getElementById('questions-container').innerHTML = '';
            questionCount = 0;
            
            // Add generated questions
            data.questions.forEach(question => {
                addGeneratedQuestion(question);
            });
            
            // Close modal
            closeAIGeneratorModal();
            
            // Show success message
            alert(`Successfully generated ${data.questions.length} questions! You can now review and edit them before saving.`);
            
        } else {
            alert('Error generating questions: ' + (data.error || 'Unknown error'));
        }
        
    } catch (error) {
        console.error('Error:', error);
        alert('Error generating questions: ' + error.message);
    } finally {
        generateBtn.innerHTML = originalText;
        generateBtn.disabled = false;
    }
}

function addGeneratedQuestion(questionData) {
    questionCount++;
    const container = document.getElementById('questions-container');
    
    const questionDiv = document.createElement('div');
    questionDiv.className = 'question-item';
    questionDiv.id = `question-${questionCount}`;
    
    // Create question HTML based on type
    let questionHTML = `
        <div class="question-header">
            <span class="question-number">Question ${questionCount}</span>
            <span class="question-type">${questionData.type.toUpperCase()}</span>
            <button type="button" class="btn-remove" onclick="removeQuestion(${questionCount})">
                <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        
        <div class="form-group">
            <label>Question Type:</label>
            <select class="question-type-select" onchange="handleQuestionTypeChange(this, ${questionCount})">
                <option value="mcq" ${questionData.type === 'mcq' ? 'selected' : ''}>Multiple Choice</option>
                <option value="matching" ${questionData.type === 'matching' ? 'selected' : ''}>Matching</option>
                <option value="essay" ${questionData.type === 'essay' ? 'selected' : ''}>Essay</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Question Text:</label>
            <textarea class="question-text" placeholder="Enter your question here..." name="question_${questionCount}_text" required>${questionData.question_text}</textarea>
        </div>
        
        <div class="form-group">
            <label>Points:</label>
            <input type="number" class="question-points" name="question_${questionCount}_points" value="${questionData.points}" min="1" required>
        </div>
        
        <div id="question-${questionCount}-options" class="question-options">
    `;
    
    // Add type-specific options
    if (questionData.type === 'mcq') {
        questionHTML += `
            <div class="form-group">
                <label><strong>Multiple Choice Options</strong></label>
                <div class="options-container">
                    <div class="option-row">
                        <label>Option A:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionCount}_option_0" value="${questionData.choice_a || ''}" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionCount}" value="0" ${questionData.correct_answer === 'A' ? 'checked' : ''} required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionCount}, 0)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="option-row">
                        <label>Option B:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionCount}_option_1" value="${questionData.choice_b || ''}" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionCount}" value="1" ${questionData.correct_answer === 'B' ? 'checked' : ''} required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionCount}, 1)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="option-row">
                        <label>Option C:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionCount}_option_2" value="${questionData.choice_c || ''}" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionCount}" value="2" ${questionData.correct_answer === 'C' ? 'checked' : ''} required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionCount}, 2)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="option-row">
                        <label>Option D:</label>
                        <input type="text" class="option-input" placeholder="" name="question_${questionCount}_option_3" value="${questionData.choice_d || ''}" required>
                        <span class="correct-label">Correct Answer:</span>
                        <input type="radio" name="correct_${questionCount}" value="3" ${questionData.correct_answer === 'D' ? 'checked' : ''} required>
                        <button type="button" class="btn-remove-option" onclick="removeOption(${questionCount}, 3)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-success add-option-btn" onclick="addOption(${questionCount})">
                    <i class="fas fa-plus"></i> Add Option
                </button>
            </div>
        `;
    } else if (questionData.type === 'matching') {
        questionHTML += `
            <div class="form-group">
                <label><strong>Left Items (Rows):</strong></label>
                <div id="matching-rows-${questionCount}">
        `;
        
        questionData.left_items.forEach((item, index) => {
            questionHTML += `
                <div class="input-group">
                    <label for="left_item_${index + 1}_${questionCount}">Row ${index + 1}:</label>
                    <input type="text" id="left_item_${index + 1}_${questionCount}" name="question_${questionCount}_left_items[]" placeholder="Row ${index + 1}" oninput="updateMatchingMatches(${questionCount})" value="${item}" required>
                    <button type="button" class="remove-option" onclick="removeMatchingRow(${questionCount}, ${index})">×</button>
                </div>
            `;
        });
        
        questionHTML += `
                </div>
                <button type="button" class="add-option" onclick="addMatchingRow(${questionCount})">
                    <i class="fas fa-plus"></i> Add Row
                </button>
            </div>
            
            <div class="form-group">
                <label><strong>Right Items (Columns):</strong></label>
                <div id="matching-columns-${questionCount}">
        `;
        
        questionData.right_items.forEach((item, index) => {
            questionHTML += `
                <div class="input-group">
                    <label for="right_item_${index + 1}_${questionCount}">Column ${index + 1}:</label>
                    <input type="text" id="right_item_${index + 1}_${questionCount}" name="question_${questionCount}_right_items[]" placeholder="Column ${index + 1}" oninput="updateMatchingMatches(${questionCount})" value="${item}" required>
                    <button type="button" class="remove-option" onclick="removeMatchingColumn(${questionCount}, ${index})">×</button>
                </div>
            `;
        });
        
        questionHTML += `
                </div>
                <button type="button" class="add-option" onclick="addMatchingColumn(${questionCount})">
                    <i class="fas fa-plus"></i> Add Column
                </button>
            </div>
            
            <div class="form-group">
                <label><strong>Correct Matches:</strong></label>
                <div id="matching-matches-${questionCount}">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
        `;
    } else if (questionData.type === 'essay') {
        questionHTML += `
            <div class="form-group">
                <label>Essay Question Details:</label>
                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">Essay questions will be manually graded by the teacher.</p>
                <label>Rubric (required):</label>
                <textarea placeholder="e.g., Thesis (2), Evidence (3), Organization (2), Grammar (3)" name="question_${questionCount}_rubric" required>${questionData.rubric || ''}</textarea>
                <small style="color: #666;">Describe scoring criteria or paste a rubric. Students will see this rubric.</small>
            </div>
        `;
    }
    
    questionHTML += `
        </div>
    `;
    
    questionDiv.innerHTML = questionHTML;
    container.appendChild(questionDiv);
    
    // Update question numbering
    updateQuestionNumbers();
    
    // For matching questions, update matches after a delay
    if (questionData.type === 'matching') {
        setTimeout(() => {
            updateMatchingMatches(questionCount);
        }, 200);
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('aiGeneratorModal');
    if (event.target === modal) {
        closeAIGeneratorModal();
    }
}

>>>>>>> 2fcad03c27dbe56cf4dba808f3f13a749f478b16
// Multi-select functionality for sections
document.addEventListener('DOMContentLoaded', function() {
    // Don't add any questions initially - wait for user to click "Add New Question"
    
    // Section multi-select functionality
    const sectionMulti = document.getElementById('sectionMulti');
    const sectionPanel = document.getElementById('sectionPanel');
    const sectionSummary = document.getElementById('sectionSummary');
    const sectionAll = document.getElementById('section_all');
    const sectionBoxes = document.querySelectorAll('.sec-box');
    const sectionHiddenInputs = document.getElementById('sectionHiddenInputs');
    
    // Toggle panel
    sectionMulti.addEventListener('click', function(e) {
        e.stopPropagation();
        sectionPanel.style.display = sectionPanel.style.display === 'none' ? 'block' : 'none';
    });
    
    // Close panel when clicking outside
    document.addEventListener('click', function(e) {
        if (!sectionMulti.contains(e.target) && !sectionPanel.contains(e.target)) {
            sectionPanel.style.display = 'none';
        }
    });
    
    // Handle "Select all" checkbox
    sectionAll.addEventListener('change', function() {
        sectionBoxes.forEach(box => {
            box.checked = this.checked;
        });
        updateSectionSummary();
    });
    
    // Handle individual section checkboxes
    sectionBoxes.forEach(box => {
        box.addEventListener('change', function() {
            updateSectionSummary();
            updateSelectAllState();
        });
    });
    
    function updateSectionSummary() {
        const checkedBoxes = Array.from(sectionBoxes).filter(box => box.checked);
        const labels = checkedBoxes.map(box => box.dataset.label);
        
        if (labels.length === 0) {
            sectionSummary.textContent = 'Select sections';
        } else if (labels.length === 1) {
            sectionSummary.textContent = labels[0];
        } else {
            sectionSummary.textContent = `${labels.length} sections selected`;
        }
        
        // Update hidden inputs
        sectionHiddenInputs.innerHTML = '';
        checkedBoxes.forEach(box => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'section_ids[]';
            hiddenInput.value = box.value;
            sectionHiddenInputs.appendChild(hiddenInput);
        });
    }
    
    function updateSelectAllState() {
        const checkedBoxes = Array.from(sectionBoxes).filter(box => box.checked);
        sectionAll.checked = checkedBoxes.length === sectionBoxes.length;
        sectionAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < sectionBoxes.length;
    }
});
</script>

<?php
render_teacher_footer();
?>

<?php
// File content extraction method for uploaded files
function extractFileContent($attachmentPath, $attachmentType, $title) {
    $content = '';
    
    try {
        // Ensure the file path is correct (remove any leading slashes)
        $filePath = ltrim($attachmentPath, '/');
        $fullPath = __DIR__ . '/' . $filePath;
        
        // Check if file exists
        if (!file_exists($fullPath)) {
            error_log("File not found: $fullPath");
            // Try alternative paths
            $altPath1 = __DIR__ . '/../' . $filePath;
            $altPath2 = __DIR__ . '/uploads/' . basename($filePath);
            $altPath3 = $filePath; // Try direct path
            
            if (file_exists($altPath1)) {
                $fullPath = $altPath1;
            } elseif (file_exists($altPath2)) {
                $fullPath = $altPath2;
            } elseif (file_exists($altPath3)) {
                $fullPath = $altPath3;
            } else {
                error_log("File not found in any path: $fullPath, $altPath1, $altPath2, $altPath3");
                return "This uploaded file contains educational material. The file content could not be extracted.";
            }
        }
        
        // Extract content based on file type
        switch (strtolower($attachmentType)) {
            case 'application/pdf':
                $content = extractPDFContent($fullPath, $title);
                break;
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                $content = extractWordContent($fullPath, $title);
                break;
            case 'application/vnd.ms-powerpoint':
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                $content = extractPowerPointContent($fullPath, $title);
                break;
            case 'text/plain':
                $content = extractTextContent($fullPath, $title);
                break;
            default:
                $content = "This uploaded file contains educational material. The file type (" . $attachmentType . ") is not supported for content extraction.";
        }
        
        // If content extraction failed, provide fallback
        if (empty($content) || strlen($content) < 50) {
            // Provide educational content based on file title instead of just metadata
            if (stripos($title, 'math') !== false) {
                $content = "This material covers mathematical concepts including arithmetic, geometry, and problem-solving skills suitable for Grade 6 students.";
            } elseif (stripos($title, 'science') !== false) {
                $content = "This material covers scientific concepts including natural phenomena, experiments, and scientific methods appropriate for Grade 6 students.";
            } elseif (stripos($title, 'english') !== false || stripos($title, 'language') !== false) {
                $content = "This material covers language arts including reading comprehension, grammar, writing skills, and literary concepts for Grade 6 students.";
            } elseif (stripos($title, 'history') !== false || stripos($title, 'social') !== false) {
                $content = "This material covers historical events, social studies concepts, and cultural topics appropriate for Grade 6 students.";
            } else {
                $content = "This material contains educational content covering various academic subjects and topics suitable for Grade 6 students.";
            }
        }
        
    } catch (Exception $e) {
        error_log("File content extraction error: " . $e->getMessage());
        $content = "This uploaded file contains educational material. The file content could not be extracted due to an error.";
    }
    
    return $content;
}

// Extract content from PDF files
function extractPDFContent($filePath, $title) {
    $content = '';
    
    try {
        // Try to extract text from PDF using a simple method
        // First, try to use pdftotext if available (common on Linux/Mac)
        if (function_exists('shell_exec') && !empty(shell_exec('which pdftotext'))) {
            $output = shell_exec("pdftotext -layout '$filePath' -");
            if (!empty($output)) {
                $content = trim($output);
            }
        }
        
        // If pdftotext didn't work, try using PHP's built-in methods
        if (empty($content)) {
            // Try to read the PDF as binary and extract text using simple regex
            $pdfContent = file_get_contents($filePath);
            if ($pdfContent !== false) {
                // Simple text extraction from PDF binary content
                // This is a basic method that works for simple PDFs
                $text = '';
                
                // Extract text between BT and ET markers (PDF text objects)
                if (preg_match_all('/BT\s*(.*?)\s*ET/s', $pdfContent, $matches)) {
                    foreach ($matches[1] as $match) {
                        // Extract text from PDF commands
                        if (preg_match_all('/\((.*?)\)\s*Tj/s', $match, $textMatches)) {
                            foreach ($textMatches[1] as $textMatch) {
                                $text .= $textMatch . ' ';
                            }
                        }
                    }
                }
                
                // Clean up the extracted text
                $text = preg_replace('/\s+/', ' ', $text);
                $text = trim($text);
                
                if (strlen($text) > 50) {
                    $content = $text;
                }
            }
        }
        
        // If we still don't have content, try a different approach
        if (empty($content)) {
            // Try using exec with pdftotext if available
            $command = "pdftotext -layout '$filePath' - 2>/dev/null";
            $output = @shell_exec($command);
            if (!empty($output)) {
                $content = trim($output);
            }
        }
        
        // Try alternative PDF extraction methods
        if (empty($content)) {
            // Try using a simple text extraction method
            $pdfContent = file_get_contents($filePath);
            if ($pdfContent !== false) {
                // Look for text streams in PDF
                if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $pdfContent, $streamMatches)) {
                    foreach ($streamMatches[1] as $stream) {
                        // Try to extract readable text
                        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $stream);
                        $text = preg_replace('/\s+/', ' ', $text);
                        $text = trim($text);
                        
                        // Check if this looks like meaningful text (not garbled)
                        if (strlen($text) > 100 && 
                            preg_match('/[a-zA-Z]{3,}/', $text) && 
                            !preg_match('/[~!@#$%^&*()+={}\[\]|\\:";\'<>?,.\/]{10,}/', $text) && // Not too many special chars
                            preg_match('/\b(the|and|or|but|in|on|at|to|for|of|with|by)\b/i', $text)) { // Contains common words
                            $content = $text;
                            break;
                        }
                    }
                }
            }
        }
        
        // If all methods failed, provide a fallback based on file analysis
        if (empty($content) || strlen($content) < 50) {
            // Analyze the file size and provide educational content
            $fileSize = filesize($filePath);
            
            // Provide more specific content based on the title
            if (stripos($title, 'quarter') !== false || stripos($title, 'module') !== false) {
                $content = "This is a comprehensive educational module containing structured learning materials. ";
                $content .= "The document includes lessons, activities, and educational resources designed for student learning. ";
                $content .= "The material covers key concepts and learning objectives with examples and exercises.";
            } elseif (stripos($title, 'english') !== false || stripos($title, 'language') !== false) {
                $content = "This English language learning material contains reading comprehension exercises, grammar lessons, and writing activities. ";
                $content .= "The document includes literary texts, vocabulary exercises, and language skills development activities. ";
                $content .= "The material is designed to enhance reading, writing, and communication skills.";
            } elseif (stripos($title, 'math') !== false || stripos($title, 'mathematics') !== false) {
                $content = "This mathematics learning material contains problem-solving exercises, mathematical concepts, and practice activities. ";
                $content .= "The document includes step-by-step solutions, examples, and mathematical reasoning exercises. ";
                $content .= "The material covers various mathematical topics and skills development.";
            } elseif ($fileSize > 100000) { // Large file
                $content = "This is a comprehensive educational PDF document containing detailed learning materials. ";
                $content .= "The document includes structured content, examples, and educational resources suitable for Grade 6 students. ";
                $content .= "The material covers various academic topics and provides in-depth information for student learning.";
            } else {
                $content = "This is an educational PDF document containing learning materials. ";
                $content .= "The content includes educational resources and information suitable for Grade 6 students. ";
                $content .= "The material provides structured learning content for academic development.";
            }
        }
        
        // Clean up the content
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        // Early check for garbled content - if it looks like binary/encoded data, reject it immediately
        if (preg_match('/[a-zA-Z]{1,2}[^a-zA-Z\s]{5,}/', $content) || 
            preg_match('/[~!@#$%^&*()+={}\[\]|\\:";\'<>?,.\/]{15,}/', $content) ||
            preg_match('/[0-9]{8,}/', $content)) {
            $content = ''; // Force fallback
        }
        
        // Check if content is garbled or not meaningful
        if (strlen($content) < 100 || 
            preg_match('/[~!@#$%^&*()+={}\[\]|\\:";\'<>?,.\/]{10,}/', $content) || // Too many special chars
            preg_match('/[a-zA-Z]{1,2}[^a-zA-Z\s]{3,}/', $content) || // Short letters followed by many non-letters
            preg_match('/[0-9]{5,}/', $content) || // Too many consecutive numbers
            !preg_match('/\b(the|and|or|but|in|on|at|to|for|of|with|by|is|are|was|were|have|has|had|this|that|these|those|a|an)\b/i', $content)) { // No common words
            // Use fallback content instead of garbled text
            $fileSize = filesize($filePath);
            if (stripos($title, 'quarter') !== false || stripos($title, 'module') !== false) {
                $content = "This is a comprehensive educational module containing structured learning materials. ";
                $content .= "The document includes lessons, activities, and educational resources designed for student learning. ";
                $content .= "The material covers key concepts and learning objectives with examples and exercises.";
            } elseif (stripos($title, 'english') !== false || stripos($title, 'language') !== false) {
                $content = "This English language learning material contains reading comprehension exercises, grammar lessons, and writing activities. ";
                $content .= "The document includes literary texts, vocabulary exercises, and language skills development activities. ";
                $content .= "The material is designed to enhance reading, writing, and communication skills.";
            } elseif (stripos($title, 'math') !== false || stripos($title, 'mathematics') !== false) {
                $content = "This mathematics learning material contains problem-solving exercises, mathematical concepts, and practice activities. ";
                $content .= "The document includes step-by-step solutions, examples, and mathematical reasoning exercises. ";
                $content .= "The material covers various mathematical topics and skills development.";
            } elseif ($fileSize > 100000) { // Large file
                $content = "This is a comprehensive educational PDF document containing detailed learning materials. ";
                $content .= "The document includes structured content, examples, and educational resources suitable for Grade 6 students. ";
                $content .= "The material covers various academic topics and provides in-depth information for student learning.";
            } else {
                $content = "This is an educational PDF document containing learning materials. ";
                $content .= "The content includes educational resources and information suitable for Grade 6 students. ";
                $content .= "The material provides structured learning content for academic development.";
            }
        }
        
    } catch (Exception $e) {
        error_log("PDF extraction error: " . $e->getMessage());
        $content = "This educational PDF document contains learning materials covering various academic topics suitable for Grade 6 students.";
    }
    
    return $content;
}

// Extract content from Word documents
function extractWordContent($filePath, $title) {
    $content = '';
    
    try {
        // Try to extract text from Word documents
        // For .docx files, we can try to read the XML content
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'docx') {
            // Try to extract text from docx (it's a ZIP file with XML)
            $zip = new ZipArchive();
            if ($zip->open($filePath) === TRUE) {
                // Read the main document XML
                $documentXml = $zip->getFromName('word/document.xml');
                if ($documentXml !== false) {
                    // Extract text from XML
                    $text = strip_tags($documentXml);
                    $text = preg_replace('/\s+/', ' ', $text);
                    $text = trim($text);
                    
                    if (strlen($text) > 50) {
                        $content = $text;
                    }
                }
                $zip->close();
            }
        }
        
        // If we couldn't extract from docx, try reading as plain text
        if (empty($content)) {
            $text = file_get_contents($filePath);
            if ($text !== false) {
                // Clean up the text
                $text = preg_replace('/[^\x20-\x7E]/', ' ', $text);
                $text = preg_replace('/\s+/', ' ', $text);
                $text = trim($text);
                
                if (strlen($text) > 50) {
                    $content = $text;
                }
            }
        }
        
        // If all methods failed, provide educational content
        if (empty($content) || strlen($content) < 50) {
            $content = "This is a Word document containing educational material. ";
            $content .= "The document includes formatted text, headings, and structured content suitable for Grade 6 students. ";
            $content .= "The material covers key concepts and learning objectives appropriate for Grade 6 students.";
        }
        
    } catch (Exception $e) {
        error_log("Word extraction error: " . $e->getMessage());
        $content = "This is a Word document containing educational material suitable for Grade 6 students.";
    }
    
    return $content;
}

// Extract content from PowerPoint presentations
function extractPowerPointContent($filePath, $title) {
    $content = '';
    
    try {
        // Try to extract text from PowerPoint files
        // For .pptx files, we can try to read the XML content
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pptx') {
            // Try to extract text from pptx (it's a ZIP file with XML)
            $zip = new ZipArchive();
            if ($zip->open($filePath) === TRUE) {
                // Read slide XML files
                for ($i = 1; $i <= 100; $i++) { // Check up to 100 slides
                    $slideXml = $zip->getFromName("ppt/slides/slide$i.xml");
                    if ($slideXml !== false) {
                        // Extract text from XML
                        $text = strip_tags($slideXml);
                        $text = preg_replace('/\s+/', ' ', $text);
                        $text = trim($text);
                        
                        if (strlen($text) > 10) {
                            $content .= $text . ' ';
                        }
                    } else {
                        break; // No more slides
                    }
                }
                $zip->close();
            }
        }
        
        // If all methods failed, provide educational content
        if (empty($content) || strlen($content) < 50) {
            $content = "This is a PowerPoint presentation containing educational material. ";
            $content .= "The presentation includes slides with text, images, and structured content suitable for Grade 6 students. ";
            $content .= "The material covers key concepts and learning objectives appropriate for Grade 6 students.";
        }
        
    } catch (Exception $e) {
        error_log("PowerPoint extraction error: " . $e->getMessage());
        $content = "This is a PowerPoint presentation containing educational material suitable for Grade 6 students.";
    }
    
    return $content;
}

// Extract content from text files
function extractTextContent($filePath, $title) {
    $content = '';
    
    try {
        $text = file_get_contents($filePath);
        if ($text !== false) {
            // Clean up the text
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            
            if (strlen($text) > 10) {
                $content = $text;
            }
        }
        
        // If extraction failed, provide educational content
        if (empty($content) || strlen($content) < 50) {
            $content = "This is a text file containing educational material. ";
            $content .= "The file includes structured text content suitable for Grade 6 students. ";
            $content .= "The material covers key concepts and learning objectives appropriate for Grade 6 students.";
        }
        
    } catch (Exception $e) {
        error_log("Text extraction error: " . $e->getMessage());
        $content = "This is a text file containing educational material suitable for Grade 6 students.";
    }
    
    return $content;
}
?>

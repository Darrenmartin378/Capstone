<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/notification_helper.php';

$edit_mode = false;
$edit_material = null;
$edit_comprehension_mode = false;
$edit_comprehension_set = null;

// Initialize teacher sections list
$teacherSectionsList = [];
try {
    $stf = $conn->prepare("SELECT s.id, s.name FROM teacher_sections ts JOIN sections s ON s.id = ts.section_id WHERE ts.teacher_id = ?");
    $stf->bind_param('i', $_SESSION['teacher_id']);
    $stf->execute();
    $rf = $stf->get_result();
    while ($row = $rf->fetch_assoc()) { $teacherSectionsList[] = $row; }
    $stf->close();
} catch (Throwable $e) { /* ignore */ }

// Ensure reading_materials has section_id column (migration-safe)
try {
    $chk = $conn->query("SHOW COLUMNS FROM reading_materials LIKE 'section_id'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE reading_materials ADD COLUMN section_id INT NULL AFTER teacher_id");
    }
} catch (Throwable $e) { /* ignore */ }

// Ensure reading_materials has attachment columns (migration-safe)
try {
    $needPath = $conn->query("SHOW COLUMNS FROM reading_materials LIKE 'attachment_path'");
    if ($needPath && $needPath->num_rows === 0) {
        $conn->query("ALTER TABLE reading_materials ADD COLUMN attachment_path VARCHAR(255) NULL AFTER content");
    }
    $needName = $conn->query("SHOW COLUMNS FROM reading_materials LIKE 'attachment_name'");
    if ($needName && $needName->num_rows === 0) {
        $conn->query("ALTER TABLE reading_materials ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path");
    }
    $needType = $conn->query("SHOW COLUMNS FROM reading_materials LIKE 'attachment_type'");
    if ($needType && $needType->num_rows === 0) {
        $conn->query("ALTER TABLE reading_materials ADD COLUMN attachment_type VARCHAR(128) NULL AFTER attachment_name");
    }
    $needSize = $conn->query("SHOW COLUMNS FROM reading_materials LIKE 'attachment_size'");
    if ($needSize && $needSize->num_rows === 0) {
        $conn->query("ALTER TABLE reading_materials ADD COLUMN attachment_size INT NULL AFTER attachment_type");
    }
} catch (Throwable $e) { /* ignore */ }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
    $teacher_id = $_SESSION['teacher_id'] ?? 0;

        // Debug: Log the POST data
        error_log('POST data: ' . print_r($_POST, true));
    
    if (isset($_POST['action']) && $_POST['action'] === 'add_material') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $theme_settings = json_encode(['bg_color' => '#ffffff']);
        $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : null;

        if (!$conn) { throw new Exception('Database connection failed'); }

        // Handle file upload first so we can derive a title if needed
        $attPath = null; $attName = null; $attType = null; $attSize = null; $hasFile = false;
        if (!empty($_FILES)) { error_log('FILES: ' . print_r($_FILES, true)); }
        if (!empty($_FILES['attachment']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../uploads/materials';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
            $orig = $_FILES['attachment']['name'];
            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]+/', '_', $orig);
            $ext = pathinfo($safeName, PATHINFO_EXTENSION);
            $fileName = uniqid('mat_', true) . ($ext? ('.' . $ext) : '');
            $destAbs = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destAbs)) {
                $attPath = 'uploads/materials/' . $fileName; // relative to Teacher/
                $attName = $orig;
                $attType = $_FILES['attachment']['type'] ?? null;
                $attSize = (int)($_FILES['attachment']['size'] ?? 0);
                $hasFile = true;
            }
        }

        // If a file is provided but title/content are empty, derive sensible defaults
        if ($hasFile) {
            if (!$title) {
                $title = $attName ? pathinfo($attName, PATHINFO_FILENAME) : 'Uploaded Material';
            }
            if (!$content || trim(strip_tags($content)) === '') {
                $content = '<p></p>';
            }
        }

        // Validate: allow either (title AND content) OR (file attached)
        if ($teacher_id && (($title && $content) || $hasFile)) {
            $stmt = $conn->prepare("INSERT INTO reading_materials (teacher_id, section_id, title, content, attachment_path, attachment_name, attachment_type, attachment_size, theme_settings, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }
            $stmt->bind_param("iisssssis", $teacher_id, $section_id, $title, $content, $attPath, $attName, $attType, $attSize, $theme_settings);
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute statement: ' . $stmt->error);
            }

            $materialId = $conn->insert_id;
            $stmt->close();

            // Create notification for all students in teacher's sections (best-effort)
            try {
                createNotificationForAllStudents(
                    $conn,
                    $teacher_id,
                    'material',
                    'New Reading Material Available',
                    "Your teacher has uploaded a new reading material: \"$title\". Check the Materials section to read it.",
                    $materialId
                );
            } catch (Exception $e) {
                error_log('Notification creation failed: ' . $e->getMessage());
            }

            // Redirect to prevent form resubmission
            header('Location: teacher_content.php?uploaded=1&success=1');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            exit;
        } else {
            header('Location: teacher_content.php?error=1');
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit_material') {
        $id = (int)($_POST['id'] ?? 0);
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $theme_settings = json_encode(['bg_color' => '#ffffff']);
        $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : null;

        if ($id && $title && $content && $teacher_id) {
            // Handle new upload (optional)
            $attPath = null; $attName = null; $attType = null; $attSize = null; $hasNew = false;
            if (!empty($_FILES['attachment']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
                $uploadDir = __DIR__ . '/../uploads/materials';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
                $orig = $_FILES['attachment']['name'];
                $safeName = preg_replace('/[^a-zA-Z0-9_\.-]+/', '_', $orig);
                $ext = pathinfo($safeName, PATHINFO_EXTENSION);
                $fileName = uniqid('mat_', true) . ($ext? ('.' . $ext) : '');
                $destAbs = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destAbs)) {
                    $attPath = 'uploads/materials/' . $fileName;
                    $attName = $orig; $attType = $_FILES['attachment']['type'] ?? null; $attSize = (int)($_FILES['attachment']['size'] ?? 0);
                    $hasNew = true;
                }
            }

            if ($hasNew) {
                $stmt = $conn->prepare("UPDATE reading_materials SET section_id = ?, title = ?, content = ?, attachment_path = ?, attachment_name = ?, attachment_type = ?, attachment_size = ?, theme_settings = ?, updated_at = NOW() WHERE id = ? AND teacher_id = ?");
                $stmt->bind_param("isssssisii", $section_id, $title, $content, $attPath, $attName, $attType, $attSize, $theme_settings, $id, $teacher_id);
            } else {
                $stmt = $conn->prepare("UPDATE reading_materials SET section_id = ?, title = ?, content = ?, theme_settings = ?, updated_at = NOW() WHERE id = ? AND teacher_id = ?");
                $stmt->bind_param("isssii", $section_id, $title, $content, $theme_settings, $id, $teacher_id);
            }
            $stmt->execute();
            $stmt->close();
            // Redirect to prevent form resubmission
            header('Location: teacher_content.php?updated=1&success=1');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            exit;
        } else {
            // Redirect with error
            header('Location: teacher_content.php?error=1');
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_material') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM reading_materials WHERE id = ? AND teacher_id = ?");
            $stmt->bind_param("ii", $id, $teacher_id);
            $stmt->execute();
            $stmt->close();
            // Redirect to prevent form resubmission
            header('Location: teacher_content.php?deleted=1&success=1');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            exit;
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_comprehension_questions') {
        $set_id = (int)($_POST['set_id'] ?? 0);
        $set_title = $_POST['set_title'] ?? '';
        $section_ids = $_POST['section_ids'] ?? [];
        
        if ($set_id && $set_title && $teacher_id && !empty($section_ids)) {
            // For now, use the first selected section (can be enhanced later for multiple sections)
            $section_id = (int)$section_ids[0];
            
            // Update the question set title and section
            $stmt = $conn->prepare("UPDATE question_sets SET set_title = ?, section_id = ?, updated_at = NOW() WHERE id = ? AND teacher_id = ?");
            $stmt->bind_param("siii", $set_title, $section_id, $set_id, $teacher_id);
            $stmt->execute();
            $stmt->close();
            
            // Update individual questions
            if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                foreach ($_POST['questions'] as $questionData) {
                    $question_id = (int)($questionData['id'] ?? 0);
                    $question_text = $questionData['question_text'] ?? '';
                    $points = (int)($questionData['points'] ?? 1);
                    $order_index = (int)($questionData['order_index'] ?? 0);
                    // Determine question type based on data structure
                    if (isset($questionData['type']) && !empty($questionData['type'])) {
                        $question_type = $questionData['type'];
                    } elseif (isset($questionData['left_items']) && isset($questionData['right_items'])) {
                        $question_type = 'matching';
                    } elseif (isset($questionData['choices']) && is_array($questionData['choices'])) {
                        $question_type = 'mcq';
                    } else {
                        $question_type = 'essay';
                    }
                    
                    if ($question_text) {
                        if ($question_id > 0) {
                            // Update existing question based on type
                            if ($question_type === 'mcq') {
                                $choice_a = $questionData['choices'][0] ?? '';
                                $choice_b = $questionData['choices'][1] ?? '';
                                $choice_c = $questionData['choices'][2] ?? '';
                                $choice_d = $questionData['choices'][3] ?? '';
                                $correct_answer = $questionData['answer_key'] ?? '';
                                
                                $stmt = $conn->prepare("UPDATE mcq_questions SET question_text = ?, choice_a = ?, choice_b = ?, choice_c = ?, choice_d = ?, correct_answer = ?, points = ?, order_index = ? WHERE question_id = ? AND set_id = ?");
                                $stmt->bind_param("ssssssiii", $question_text, $choice_a, $choice_b, $choice_c, $choice_d, $correct_answer, $points, $order_index, $question_id, $set_id);
                                $stmt->execute();
                                $stmt->close();
                            } elseif ($question_type === 'matching') {
                                $left_items = json_encode($questionData['left_items'] ?? []);
                                $right_items = json_encode($questionData['right_items'] ?? []);
                                $correct_pairs = $questionData['answer_key'] ?? '';
                                
                                $stmt = $conn->prepare("UPDATE matching_questions SET question_text = ?, left_items = ?, right_items = ?, correct_pairs = ?, points = ?, order_index = ? WHERE question_id = ? AND set_id = ?");
                                $stmt->bind_param("ssssiiii", $question_text, $left_items, $right_items, $correct_pairs, $points, $order_index, $question_id, $set_id);
                                $stmt->execute();
                                $stmt->close();
                            } elseif ($question_type === 'essay') {
                                $stmt = $conn->prepare("UPDATE essay_questions SET question_text = ?, points = ?, order_index = ? WHERE question_id = ? AND set_id = ?");
                                $stmt->bind_param("siii", $question_text, $points, $order_index, $question_id, $set_id);
                                $stmt->execute();
                                $stmt->close();
                            }
                        } else {
                            // Insert new question based on type
                            if ($question_type === 'mcq') {
                                $choice_a = $questionData['choices'][0] ?? '';
                                $choice_b = $questionData['choices'][1] ?? '';
                                $choice_c = $questionData['choices'][2] ?? '';
                                $choice_d = $questionData['choices'][3] ?? '';
                                $correct_answer = $questionData['answer_key'] ?? '';
                                
                                $stmt = $conn->prepare("INSERT INTO mcq_questions (set_id, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, points, order_index) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param("issssssii", $set_id, $question_text, $choice_a, $choice_b, $choice_c, $choice_d, $correct_answer, $points, $order_index);
                                $stmt->execute();
                                $stmt->close();
                            } elseif ($question_type === 'matching') {
                                $left_items = json_encode($questionData['left_items'] ?? []);
                                $right_items = json_encode($questionData['right_items'] ?? []);
                                $correct_pairs = $questionData['answer_key'] ?? '';
                                
                                $stmt = $conn->prepare("INSERT INTO matching_questions (set_id, question_text, left_items, right_items, correct_pairs, points, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param("issssii", $set_id, $question_text, $left_items, $right_items, $correct_pairs, $points, $order_index);
                                $stmt->execute();
                                $stmt->close();
                            } elseif ($question_type === 'essay') {
                                $stmt = $conn->prepare("INSERT INTO essay_questions (set_id, question_text, points, order_index) VALUES (?, ?, ?, ?)");
                                $stmt->bind_param("isii", $set_id, $question_text, $points, $order_index);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                    }
                }
            }
            
            // Redirect to prevent form resubmission
            header('Location: teacher_content.php?comprehension_updated=1&success=1');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            exit;
        } else {
            // Redirect with error
            header('Location: teacher_content.php?error=1');
            exit;
        }
    } else {
        // No valid action found
        error_log('No valid action found in POST data');
        header('Location: teacher_content.php?error=1');
        exit;
    }
    } catch (Exception $e) {
        error_log('Form processing error: ' . $e->getMessage());
        header('Location: teacher_content.php?error=1');
        exit;
    }
}

// Handle success messages
$success_message = '';
$error_message = '';

// Check for success parameter first
if (isset($_GET['success']) && $_GET['success'] == '1') {
    if (isset($_GET['uploaded']) && $_GET['uploaded'] == '1') {
        $success_message = 'Content uploaded successfully!';
    } elseif (isset($_GET['updated']) && $_GET['updated'] == '1') {
        $success_message = 'Content updated successfully!';
    } elseif (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
        $success_message = 'Content deleted successfully!';
    } elseif (isset($_GET['comprehension_updated']) && $_GET['comprehension_updated'] == '1') {
        $success_message = 'Comprehension questions updated successfully!';
    }
} elseif (isset($_GET['error']) && $_GET['error'] == '1') {
    $error_message = 'An error occurred. Please try again.';
}

// Fallback for direct parameter checks (backward compatibility)
if (empty($success_message) && empty($error_message)) {
    if (isset($_GET['uploaded']) && $_GET['uploaded'] == '1') {
        $success_message = 'Content uploaded successfully!';
    } elseif (isset($_GET['updated']) && $_GET['updated'] == '1') {
        $success_message = 'Content updated successfully!';
    } elseif (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
        $success_message = 'Content deleted successfully!';
    }
}

// Handle edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM reading_materials WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $edit_id, $_SESSION['teacher_id']);
    $stmt->execute();
    $result = $stmt->get_result();
        $edit_material = $result->fetch_assoc();
    $stmt->close();
    
    if ($edit_material) {
        $edit_mode = true;
    }
}

// Handle comprehension question edit mode
if (isset($_GET['edit_comprehension']) && is_numeric($_GET['edit_comprehension'])) {
    $edit_set_id = (int)$_GET['edit_comprehension'];
    
    // Get the question set details
    $stmt = $conn->prepare("SELECT * FROM question_sets WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $edit_set_id, $_SESSION['teacher_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_comprehension_set = $result->fetch_assoc();
    $stmt->close();
    
    if ($edit_comprehension_set) {
        $edit_comprehension_mode = true;
        
        // Get all questions for this set from separate tables
        $edit_comprehension_set['questions'] = [];
        
        // Get MCQ questions
        $stmt = $conn->prepare("SELECT question_id as id, 'mcq' as type, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, points, order_index FROM mcq_questions WHERE set_id = ? ORDER BY order_index, question_id");
        $stmt->bind_param("i", $edit_set_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Convert MCQ format to match the edit form structure
            $choices = [
                $row['choice_a'],
                $row['choice_b'], 
                $row['choice_c'],
                $row['choice_d']
            ];
            $row['choices'] = json_encode($choices);
            $row['answer_key'] = $row['correct_answer'];
            unset($row['choice_a'], $row['choice_b'], $row['choice_c'], $row['choice_d'], $row['correct_answer']);
            $edit_comprehension_set['questions'][] = $row;
        }
        $stmt->close();
        
        // Get Matching questions
        $stmt = $conn->prepare("SELECT question_id as id, 'matching' as type, question_text, left_items, right_items, correct_pairs, points, order_index FROM matching_questions WHERE set_id = ? ORDER BY order_index, question_id");
        $stmt->bind_param("i", $edit_set_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Convert matching format to match the edit form structure
            $row['choices'] = $row['left_items']; // Store left items in choices field
            $row['right_items'] = $row['right_items']; // Keep right items separate
            $row['answer_key'] = $row['correct_pairs']; // Store correct pairs in answer_key field
            unset($row['left_items'], $row['correct_pairs']);
            $edit_comprehension_set['questions'][] = $row;
        }
        $stmt->close();
        
        // Get Essay questions
        $stmt = $conn->prepare("SELECT question_id as id, 'essay' as type, question_text, points, order_index FROM essay_questions WHERE set_id = ? ORDER BY order_index, question_id");
        $stmt->bind_param("i", $edit_set_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $edit_comprehension_set['questions'][] = $row;
        }
        $stmt->close();
    }
}

// Get all materials for this teacher (with optional section filter)
$materials = [];
$filterSecId = (int)($_GET['sec'] ?? 0);
$filterType = isset($_GET['mtype']) ? strtolower(trim($_GET['mtype'])) : 'all';
if (!in_array($filterType, ['all','created','uploaded'], true)) { $filterType = 'all'; }
$sql = "SELECT rm.*, s.name AS section_name FROM reading_materials rm LEFT JOIN sections s ON s.id = rm.section_id WHERE rm.teacher_id = ?";
if ($filterSecId > 0) { $sql .= " AND rm.section_id = ?"; }
if ($filterType === 'uploaded') {
    $sql .= " AND (rm.attachment_path IS NOT NULL AND rm.attachment_path <> '')";
} elseif ($filterType === 'created') {
    $sql .= " AND (rm.attachment_path IS NULL OR rm.attachment_path = '')";
}
$sql .= " ORDER BY rm.created_at DESC";
$stmt = $conn->prepare($sql);
if ($filterSecId > 0) { $stmt->bind_param("ii", $_SESSION['teacher_id'], $filterSecId); }
else { $stmt->bind_param("i", $_SESSION['teacher_id']); }
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $materials[] = $row; }
$stmt->close();

// Render header after all POST handling is complete
require_once __DIR__ . '/includes/teacher_layout.php';
render_teacher_header('teacher_content.php', $_SESSION['teacher_name'] ?? 'Teacher');
?>

<div class="main-content">
    <div class="content-header">
        <?php if ($edit_comprehension_mode): ?>
            <h1>Edit Comprehension Questions</h1>
            <p>Modify your comprehension question set</p>
        <?php else: ?>
        <h1>Content Management</h1>
        <p>Upload and manage reading materials for your students</p>
        <?php endif; ?>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="flash flash-success" id="success-message">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
            <button onclick="closeFlashMessage('success-message')" class="close-btn">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Debug information (remove in production) -->
    <?php if (isset($_GET['debug'])): ?>
        <div class="flash flash-info" style="background: #e3f2fd; color: #1976d2; border: 1px solid #90caf9;">
            <strong>Debug Info:</strong><br>
            Success Message: <?php echo !empty($success_message) ? htmlspecialchars($success_message) : 'None'; ?><br>
            Error Message: <?php echo !empty($error_message) ? htmlspecialchars($error_message) : 'None'; ?><br>
            GET Parameters: <?php echo htmlspecialchars(print_r($_GET, true)); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="flash flash-error" id="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
            <button onclick="closeFlashMessage('error-message')" class="close-btn">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($edit_comprehension_mode && $edit_comprehension_set): ?>
        <!-- Comprehension Question Edit Form -->
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-edit"></i> Edit Comprehension Questions</h1>
                <p>Modify your comprehension question set</p>
            </div>
            
            <form id="comprehensionEditForm" method="POST" class="question-form">
                <input type="hidden" name="action" value="update_comprehension_questions">
                <input type="hidden" name="set_id" value="<?php echo $edit_comprehension_set['id']; ?>">
                
                <div class="form-group">
                    <label for="set_title">Question Set Title: <span style="color: red;">*</span></label>
                    <input type="text" id="set_title" name="set_title" value="<?php echo htmlspecialchars($edit_comprehension_set['set_title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Section: <span style="color: red;">*</span></label>
                    <div id="sectionMulti" style="border: 2px solid #e1e5e9; border-radius: 6px; padding: 15px; background: #f8f9fa;">
                        <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #555;">
                            Select Section(s):
                        </label>
                        <?php foreach ($teacherSections as $section): $label = htmlspecialchars($section['section_name'] ?: $section['name']); ?>
                        <label style="display:grid; grid-template-columns: 1fr auto; align-items:center; column-gap:10px; padding:8px 10px; border-radius:8px; border:2px solid #16a34a; margin-bottom:6px; background:#fff;">
                            <span style="color:#111827;">&nbsp;<?php echo $label; ?></span>
                            <input type="checkbox" class="sec-box" value="<?php echo (int)$section['id']; ?>" data-label="<?php echo $label; ?>" 
                                   <?php echo ($edit_comprehension_set['section_id'] == $section['id']) ? 'checked' : ''; ?> style="margin:0; justify-self:end;">
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div id="sectionHiddenInputs"></div>
                    <small style="color:#6b7280; display:block; margin-top:6px;">Choose one or more sections.</small>
                    <div id="section-error" class="error-text" style="display: none;"></div>
                </div>
                
                <!-- Debug Information (remove in production) -->
                <?php if (isset($_GET['debug'])): ?>
                    <div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 8px; font-family: monospace; font-size: 12px;">
                        <strong>Debug Info:</strong><br>
                        Questions Count: <?php echo count($edit_comprehension_set['questions']); ?><br>
                        Questions Data: <?php echo htmlspecialchars(print_r($edit_comprehension_set['questions'], true)); ?>
                        <br><br>
                        <?php foreach ($edit_comprehension_set['questions'] as $debugIndex => $debugQuestion): ?>
                            <strong>Question <?php echo $debugIndex + 1; ?> (<?php echo $debugQuestion['type']; ?>):</strong><br>
                            Choices: <?php echo htmlspecialchars($debugQuestion['choices'] ?? 'NULL'); ?><br>
                            Right Items: <?php echo htmlspecialchars($debugQuestion['right_items'] ?? 'NULL'); ?><br>
                            Answer Key: <?php echo htmlspecialchars($debugQuestion['answer_key'] ?? 'NULL'); ?><br><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Cache Busting -->
                <div style="display: none;">Cache: <?php echo time(); ?></div>
                
                <!-- Questions Container -->
                <div id="questions-container">
                    <?php if (empty($edit_comprehension_set['questions'])): ?>
                        <div class="no-questions" style="text-align: center; padding: 20px; color: #666; background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
                            <p>No questions found in this set. Click "Add New Question" to create questions.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($edit_comprehension_set['questions'] as $index => $question): ?>
                        <div class="question-item" data-question-id="<?php echo $question['id']; ?>">
                            <div class="question-header">
                                <span class="question-number">Question <?php echo $index + 1; ?></span>
                                <button type="button" class="btn-remove" onclick="removeQuestion(<?php echo $index + 1; ?>)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label>Question Type:</label>
                                <select class="question-type-select" onchange="handleQuestionTypeChange(this, <?php echo $index + 1; ?>)">
                                    <option value="">Select Question Type</option>
                                    <option value="mcq" <?php echo ($question['type'] === 'mcq') ? 'selected' : ''; ?>>Multiple Choice</option>
                                    <option value="matching" <?php echo ($question['type'] === 'matching') ? 'selected' : ''; ?>>Matching</option>
                                    <option value="essay" <?php echo ($question['type'] === 'essay') ? 'selected' : ''; ?>>Essay</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Question Text:</label>
                                <textarea class="question-text" placeholder="Enter your question here..." name="questions[<?php echo $index; ?>][question_text]" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Points:</label>
                                <input type="number" class="question-points" name="questions[<?php echo $index; ?>][points]" value="<?php echo $question['points']; ?>" min="1" required>
                            </div>
                            
                            <input type="hidden" name="questions[<?php echo $index; ?>][id]" value="<?php echo $question['id']; ?>">
                            <input type="hidden" name="questions[<?php echo $index; ?>][order_index]" value="<?php echo $question['order_index']; ?>">
                            <?php if ($question['type'] === 'matching'): ?>
                            <input type="hidden" name="questions[<?php echo $index; ?>][answer_key]" value="<?php echo htmlspecialchars($question['answer_key'] ?? ''); ?>" id="matches-data-<?php echo $index + 1; ?>">
                            <?php endif; ?>
                            
                            <div id="question-<?php echo $index + 1; ?>-options" class="question-options" style="display: block;">
                                <?php if ($question['type'] === 'mcq' && !empty($question['choices'])): ?>
                                    <?php 
                                    $choices = json_decode($question['choices'], true);
                                    if (is_array($choices)):
                                    ?>
                                    <div class="options-container">
                                        <label>Answer Choices:</label>
                                        <?php 
                                        $choiceLetters = ['a', 'b', 'c', 'd'];
                                        foreach ($choiceLetters as $idx => $choice): 
                                            $choiceValue = $choices[$idx] ?? '';
                                        ?>
                                        <div class="option-item">
                                            <input type="text" class="option-input" name="questions[<?php echo $index; ?>][choices][<?php echo $idx; ?>]" 
                                                   value="<?php echo htmlspecialchars($choiceValue); ?>" placeholder="Option <?php echo strtoupper($choice); ?>">
                                            <input type="radio" name="questions[<?php echo $index; ?>][answer_key]" value="<?php echo $idx; ?>" 
                                                   <?php echo ($question['answer_key'] == $idx) ? 'checked' : ''; ?>>
                                            <label>Correct</label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                <?php elseif ($question['type'] === 'matching'): ?>
                                    <div class="form-group">
                                        <label><strong>Left Items (Rows):</strong></label>
                                        <div id="matching-rows-<?php echo $index + 1; ?>">
                                            <?php 
                                            $left_items = json_decode($question['choices'] ?? '[]', true);
                                            if (is_array($left_items) && !empty($left_items)) {
                                                foreach ($left_items as $leftIndex => $leftItem) {
                                                    echo '<div class="input-group">';
                                                    echo '<label for="left_item_' . ($leftIndex + 1) . '_' . ($index + 1) . '">Row ' . ($leftIndex + 1) . ':</label>';
                                                    echo '<input type="text" id="left_item_' . ($leftIndex + 1) . '_' . ($index + 1) . '" name="questions[' . $index . '][left_items][]" value="' . htmlspecialchars($leftItem) . '" placeholder="Row ' . ($leftIndex + 1) . '" oninput="updateMatchingMatches(' . ($index + 1) . ')" required>';
                                                    echo '<button type="button" class="remove-option" onclick="removeMatchingRow(' . ($index + 1) . ', ' . $leftIndex . ')">×</button>';
                                                    echo '</div>';
                                                }
                                            } else {
                                                // Show at least one empty input if no data
                                                echo '<div class="input-group">';
                                                echo '<label for="left_item_1_' . ($index + 1) . '">Row 1:</label>';
                                                echo '<input type="text" id="left_item_1_' . ($index + 1) . '" name="questions[' . $index . '][left_items][]" placeholder="Row 1" oninput="updateMatchingMatches(' . ($index + 1) . ')" required>';
                                                echo '<button type="button" class="remove-option" onclick="removeMatchingRow(' . ($index + 1) . ', 0)">×</button>';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                        <button type="button" class="add-option" onclick="addMatchingRow(<?php echo $index + 1; ?>)">
                                            <i class="fas fa-plus"></i> Add Row
                                        </button>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><strong>Right Items (Columns):</strong></label>
                                        <div id="matching-columns-<?php echo $index + 1; ?>">
                                            <?php 
                                            $right_items = json_decode($question['right_items'] ?? '[]', true);
                                            if (is_array($right_items) && !empty($right_items)) {
                                                foreach ($right_items as $rightIndex => $rightItem) {
                                                    echo '<div class="input-group">';
                                                    echo '<label for="right_item_' . ($rightIndex + 1) . '_' . ($index + 1) . '">Column ' . ($rightIndex + 1) . ':</label>';
                                                    echo '<input type="text" id="right_item_' . ($rightIndex + 1) . '_' . ($index + 1) . '" name="questions[' . $index . '][right_items][]" value="' . htmlspecialchars($rightItem) . '" placeholder="Column ' . ($rightIndex + 1) . '" oninput="updateMatchingMatches(' . ($index + 1) . ')" required>';
                                                    echo '<button type="button" class="remove-option" onclick="removeMatchingColumn(' . ($index + 1) . ', ' . $rightIndex . ')">×</button>';
                                                    echo '</div>';
                                                }
                                            } else {
                                                // Show at least one empty input if no data
                                                echo '<div class="input-group">';
                                                echo '<label for="right_item_1_' . ($index + 1) . '">Column 1:</label>';
                                                echo '<input type="text" id="right_item_1_' . ($index + 1) . '" name="questions[' . $index . '][right_items][]" placeholder="Column 1" oninput="updateMatchingMatches(' . ($index + 1) . ')" required>';
                                                echo '<button type="button" class="remove-option" onclick="removeMatchingColumn(' . ($index + 1) . ', 0)">×</button>';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                        <button type="button" class="add-option" onclick="addMatchingColumn(<?php echo $index + 1; ?>)">
                                            <i class="fas fa-plus"></i> Add Column
                                        </button>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><strong>Correct Matches:</strong></label>
                                        <div id="matching-matches-<?php echo $index + 1; ?>">
                                            <!-- Will be populated by JavaScript -->
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <small style="color: #6b7280; font-style: italic;">
                                            For matching questions, this will be used as the main instruction above all matching pairs.
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="addQuestion()">
                        <i class="fas fa-plus"></i> Add New Question
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="question_bank.php" class="btn btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-arrow-left"></i> Back to Question Bank
                    </a>
                </div>
            </form>
        </div>
    <?php else: ?>
    <!-- Google Docs-Style Editor -->
    <div class="docs-editor-container">
        <div class="docs-header">
        <div class="docs-title-bar">
                <div class="docs-title-input" style="display:flex; gap:12px; align-items:center;">
                    <input type="text" id="docs-title" placeholder="Untitled document" value="<?php echo $edit_mode ? htmlspecialchars($edit_material['title']) : ''; ?>" required>
                    <?php
                    // Fetch sections for this teacher
                    $teacherSections = [];
                    try {
                        $st = $conn->prepare("SELECT s.id, s.name FROM teacher_sections ts JOIN sections s ON s.id = ts.section_id WHERE ts.teacher_id = ?");
                        $st->bind_param('i', $_SESSION['teacher_id']);
                        $st->execute();
                        $res = $st->get_result();
                        while ($row = $res->fetch_assoc()) { $teacherSections[] = $row; }
                        $st->close();
                    } catch (Throwable $e) { /* ignore */ }
                    $selectedSection = $edit_mode ? (int)($edit_material['section_id'] ?? 0) : 0;
                    ?>
                    <select id="material-section" name="section_id" style="border:1px solid #dadce0; border-radius:6px; padding:8px; background:#fff;">
                        <option value="">All Sections</option>
                        <?php foreach ($teacherSections as $sec): ?>
                            <option value="<?= (int)$sec['id']; ?>" <?= $selectedSection == (int)$sec['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sec['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="docs-btn docs-btn-outline" style="cursor:pointer; display:inline-flex; align-items:center; gap:8px;" title="Upload file to materials list">
                        <i class="fas fa-upload"></i>
                        <span>Upload file</span>
                        <input type="file" id="attachmentInput" name="attachment" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.rtf,.odt,.odp,.xls,.xlsx,.csv,image/*" style="display:none;">
                    </label>
        </div>
                <div class="docs-actions">
                    <div class="collaboration-status" id="collaborationStatus">
                        <span class="status-indicator" id="statusIndicator">
                            <i class="fas fa-circle"></i> Ready
                        </span>
                        <span class="auto-save-status" id="autoSaveStatus">
                            <i class="fas fa-save"></i> All changes saved
                        </span>
        </div>
                    <button type="button" class="docs-btn docs-btn-secondary" onclick="saveDocument()">
                <i class="fas fa-save"></i>
                        <?php echo $edit_mode ? 'Update' : 'Save'; ?>
            </button>
                    <?php if ($edit_mode): ?>
                        <a href="teacher_content.php" class="docs-btn docs-btn-outline">Cancel</a>
                    <?php endif; ?>
        </div>
    </div>

            <!-- TinyMCE will provide its own toolbar -->
    
        <!-- Google Docs-Style Editor Area -->
        <div class="docs-editor-wrapper">
            <textarea id="docs-editor" name="content"><?php echo $edit_mode ? htmlspecialchars($edit_material['content']) : ''; ?></textarea>
                </div>
                
        <!-- Hidden form for submission -->
        <form id="materialForm" method="POST" enctype="multipart/form-data" style="display: none;">
            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit_material' : 'add_material'; ?>">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo $edit_material['id']; ?>">
            <?php endif; ?>
            <input type="hidden" name="title" id="hidden-title">
            <input type="hidden" name="content" id="hidden-content">
            
            <input type="hidden" name="section_id" id="hidden-section">
        </form>
                </div>
                
    <?php endif; ?>
    
    <div class="content-section">
        <div class="section-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <h2>Your Materials</h2>
            <form method="get" style="display:flex;align-items:center;gap:8px;">
                <label for="sec" style="color:#5f6368;font-size:13px;">Filter by section:</label>
                <select name="sec" id="sec" onchange="this.form.submit()" style="border:1px solid #dadce0;border-radius:6px;padding:6px 8px;background:#fff;">
                    <option value="0">All</option>
                    <?php foreach ($teacherSectionsList as $sec): ?>
                        <option value="<?= (int)$sec['id']; ?>" <?= $filterSecId==(int)$sec['id']?'selected':'' ?>><?= htmlspecialchars($sec['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="mtype" style="color:#5f6368;font-size:13px;">Type:</label>
                <select name="mtype" id="mtype" onchange="this.form.submit()" style="border:1px solid #dadce0;border-radius:6px;padding:6px 8px;background:#fff;">
                    <option value="all" <?= $filterType==='all'?'selected':''; ?>>All</option>
                    <option value="created" <?= $filterType==='created'?'selected':''; ?>>Created Materials</option>
                    <option value="uploaded" <?= $filterType==='uploaded'?'selected':''; ?>>Uploaded Files</option>
                </select>
            </form>
        </div>

        <?php if (empty($materials)): ?>
            <div class="no-materials" style="text-align:center;padding:24px;color:#5f6368;">No materials uploaded yet.</div>
        <?php else: ?>
            <table class="mat-table" style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                <thead style="background:#f8f9fa;">
                    <tr>
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e5e7eb;">Title</th>
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e5e7eb;">Section</th>
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e5e7eb;">Created</th>
                        <th style="text-align:left;padding:12px;border-bottom:1px solid #e5e7eb;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $material): ?>
                        <tr>
                            <td style="padding:10px 12px;"><?= htmlspecialchars($material['title']); ?></td>
                            <td style="padding:10px 12px;"><span class="section-badge"><?= htmlspecialchars($material['section_name'] ?? 'All'); ?></span></td>
                            <td style="padding:10px 12px;"><span class="date-chip"><?= date('M j, Y', strtotime($material['created_at'])); ?></span></td>
                            <td style="padding:10px 12px;">
                                <div class="action-group">
                                    <button type="button" class="icon-btn secondary toggle-content-btn" title="View" onclick="toggleContent(<?= $material['id']; ?>, this)"><i class="fas fa-eye"></i></button>
                                    <a href="material_question_builder.php?material_id=<?= $material['id']; ?>" class="icon-btn success" title="Create Comprehension Questions"><i class="fas fa-question-circle"></i></a>
                                    <a href="teacher_practice_tests.php" class="icon-btn practice-test" title="Create Practice Test"><i class="fas fa-clipboard-list"></i></a>
                                    <?php if (empty($material['attachment_path'])): ?>
                                        <a href="?edit=<?= $material['id']; ?>" class="icon-btn primary" title="Edit"><i class="fas fa-pen"></i></a>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this material?')">
                                        <input type="hidden" name="action" value="delete_material">
                                        <input type="hidden" name="id" value="<?= $material['id']; ?>">
                                        <button type="submit" class="icon-btn danger" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="row-content-<?= $material['id']; ?>" style="display:none;">
                            <td colspan="4" style="padding:14px 12px;background:#fafafa;border-top:1px solid #f0f0f0;">
                                <div class="material-content" id="content-<?= $material['id']; ?>"><?php echo $material['content']; ?></div>
                                <?php if (!empty($material['attachment_path'])): ?>
                                    <div style="margin-top:10px;">
                                        <?php if (strpos($material['attachment_path'], '.pdf') !== false): ?>
                                            <!-- PDF Viewer -->
                                            <div id="pdf-viewer-<?= $material['id']; ?>" style="display:none;">
                                                <iframe src="../<?= htmlspecialchars($material['attachment_path']); ?>#toolbar=1&navpanes=1&scrollbar=1" 
                                                        style="width:100%;height:600px;border:1px solid #e0e0e0;border-radius:8px;"
                                                        frameborder="0">
                                                </iframe>
                                            </div>
                                            <!-- Download Link (hidden by default) -->
                                            <div id="download-link-<?= $material['id']; ?>">
                                                <a href="../<?= htmlspecialchars($material['attachment_path']); ?>" download class="docs-btn docs-btn-secondary" style="display:inline-flex;align-items:center;gap:8px;">
                                                    <i class="fas fa-download"></i>
                                                    Download attachment (<?= htmlspecialchars($material['attachment_name'] ?: 'file'); ?>)
                                                </a>
                                                <?php if (!empty($material['attachment_type']) && !empty($material['attachment_size'])): ?>
                                                    <small style="margin-left:8px;color:#6b7280;">(<?= htmlspecialchars($material['attachment_type']); ?>, <?= number_format((int)$material['attachment_size']/1024, 1); ?> KB)</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <!-- Non-PDF files show download link -->
                                            <a href="../<?= htmlspecialchars($material['attachment_path']); ?>" download class="docs-btn docs-btn-secondary" style="display:inline-flex;align-items:center;gap:8px;">
                                                <i class="fas fa-download"></i>
                                                Download attachment (<?= htmlspecialchars($material['attachment_name'] ?: 'file'); ?>)
                                            </a>
                                            <?php if (!empty($material['attachment_type']) && !empty($material['attachment_size'])): ?>
                                                <small style="margin-left:8px;color:#6b7280;">(<?= htmlspecialchars($material['attachment_type']); ?>, <?= number_format((int)$material['attachment_size']/1024, 1); ?> KB)</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* Google Docs-Style Editor */
.content {
    background: #f8f9fa;
    min-height: calc(100vh - 64px);
    padding: 0;
}

.main-content {
    max-width: 100%;
    margin: 0;
}

.content-header {
    margin-bottom: 0;
    padding: 24px;
    background: white;
    border-bottom: 1px solid #e0e0e0;
}

.content-header h1 {
    font-size: 32px;
    font-weight: 400;
    color: #202124;
    margin: 0 0 8px 0;
    line-height: 1.2;
}

.content-header p {
    color: #5f6368;
    font-size: 14px;
    margin: 0;
}

/* Google Docs Editor Container */
.docs-editor-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    margin: 24px;
    overflow: hidden;
}

.docs-header {
    background: white;
    border-bottom: 1px solid #e0e0e0;
}

.docs-title-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid #e0e0e0;
}

.docs-title-input {
    flex: 1;
    margin-right: 24px;
}

.docs-title-input input {
    width: 100%;
    border: none;
    outline: none;
    font-size: 18px;
    font-weight: 400;
    color: #202124;
    background: transparent;
    padding: 8px 0;
}

.docs-title-input input::placeholder {
    color: #9aa0a6;
}

.docs-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* Ensure Save area is always clickable and above overlays */
.docs-actions { position: relative; z-index: 10; }
.docs-actions .docs-btn { pointer-events: auto; }

.collaboration-status {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-right: 16px;
}

.status-indicator {
    font-size: 12px;
    color: #5f6368;
    display: flex;
    align-items: center;
    gap: 4px;
}

.status-indicator i {
    color: #34a853;
    font-size: 8px;
}

.status-indicator.saving i {
    color: #fbbc04;
    animation: pulse 1s infinite;
}

.status-indicator.error i {
    color: #ea4335;
}

.auto-save-status {
    font-size: 11px;
    color: #5f6368;
    display: flex;
    align-items: center;
    gap: 4px;
}

.auto-save-status.saving {
    color: #fbbc04;
}

.auto-save-status.error {
    color: #ea4335;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Version History Modal */
.version-history-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    background: rgba(0, 0, 0, 0.5);
}

.modal-overlay {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.modal-header h3 {
    margin: 0;
    color: #202124;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #5f6368;
}

.version-list {
    max-height: 400px;
    overflow-y: auto;
    padding: 16px;
}

.version-item {
    padding: 12px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.version-item:hover {
    background: #f8f9fa;
    border-color: #1a73e8;
}

.version-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.version-date {
    font-size: 12px;
    color: #5f6368;
}

.version-author {
    font-size: 12px;
    color: #5f6368;
}

/* Comment Markers */
.comment-marker {
    background: #e8f0fe;
    color: #1a73e8;
    padding: 2px 4px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
    margin: 0 2px;
}

.comment-marker:hover {
    background: #d2e3fc;
}

/* Page Break */
.page-break {
    margin: 20px 0;
    text-align: center;
    color: #5f6368;
    font-size: 12px;
}

/* Old image context menu CSS removed - using TinyMCE native features */

/* Draggable Images */
.docs-editor img {
    cursor: move;
    transition: all 0.2s ease;
    user-select: none;
}

.docs-editor img:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.docs-editor img.dragging {
    opacity: 0.7;
    transform: scale(1.05);
    z-index: 1000;
    position: relative;
}

.docs-editor img.drag-preview {
    border: 2px dashed #4285f4;
    background: rgba(66, 133, 244, 0.1);
}

/* Image Style Classes */
.rounded {
    border-radius: 8px !important;
}

.circle {
    border-radius: 50% !important;
}

.shadow {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

.border {
    border: 2px solid #dadce0 !important;
}

/* TinyMCE Custom Styles */
.tox-tinymce {
    border-radius: 8px !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
}

.tox-toolbar__primary {
    background: #f8f9fa !important;
    border-bottom: 1px solid #dadce0 !important;
}

.tox-edit-area__iframe {
    border-radius: 0 0 8px 8px !important;
}

/* Word Count Display */
.tox-statusbar__wordcount {
    color: #5f6368 !important;
    font-size: 12px !important;
}

/* Custom Button Styles */
.tox-tbtn--enabled {
    background: #1a73e8 !important;
    color: white !important;
}

.tox-tbtn:hover {
    background: #1557b0 !important;
}

/* Advanced Editor Features */
.tox-menubar {
    background: #f8f9fa !important;
    border-bottom: 1px solid #dadce0 !important;
}

.tox-menu {
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

.tox-collection__item {
    padding: 8px 16px !important;
}

.tox-collection__item:hover {
    background: #f8f9fa !important;
}

.docs-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    line-height: 1;
}

.docs-btn-secondary {
    background: #1a73e8;
    color: white;
}

.docs-btn-secondary:hover {
    background: #1557b0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
}

.docs-btn-outline {
    background: transparent;
    color: #5f6368;
    border: 1px solid #dadce0;
}

.docs-btn-outline:hover {
    background: #f8f9fa;
    border-color: #5f6368;
}

/* Google Docs Toolbar */
.docs-toolbar {
    display: flex;
    align-items: center;
    padding: 8px 16px;
    background: white;
    border-bottom: 1px solid #e0e0e0;
    overflow-x: auto;
    gap: 4px;
}

.toolbar-group {
    display: flex;
    align-items: center;
    gap: 2px;
}

.toolbar-separator {
    width: 1px;
    height: 24px;
    background: #dadce0;
    margin: 0 8px;
}

.toolbar-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #5f6368;
    transition: all 0.2s ease;
}

.toolbar-btn:hover {
    background: #f1f3f4;
    color: #202124;
}

.toolbar-btn.active {
    background: #e8f0fe;
    color: #1a73e8;
}

.toolbar-select {
    height: 32px;
    border: 1px solid #dadce0;
    border-radius: 4px;
    background: white;
    color: #5f6368;
    font-size: 13px;
    padding: 0 8px;
    cursor: pointer;
    outline: none;
}

.toolbar-select:focus {
    border-color: #1a73e8;
    box-shadow: 0 0 0 1px #1a73e8;
}

.toolbar-color {
    width: 32px;
    height: 32px;
    border: 1px solid #dadce0;
    border-radius: 4px;
    cursor: pointer;
    background: none;
    padding: 0;
}

.toolbar-color::-webkit-color-swatch-wrapper {
    padding: 0;
}

.toolbar-color::-webkit-color-swatch {
    border: none;
    border-radius: 3px;
}

/* Google Docs Editor Area */
.docs-editor-wrapper {
    background: white;
    min-height: 600px;
    position: relative;
}

.docs-editor {
    min-height: 600px;
    padding: 48px 72px;
    font-family: 'Google Sans', 'Roboto', Arial, sans-serif;
    font-size: 14px;
    line-height: 1.6;
    color: #202124;
    outline: none;
    background: white;
    position: relative;
}

.docs-editor:empty:before {
    content: attr(data-placeholder);
    color: #9aa0a6;
    font-style: italic;
    position: absolute;
    top: 48px;
    left: 72px;
    pointer-events: none;
}

.docs-editor:focus:before {
    display: none;
}

/* Content Styling */
.content-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    margin: 24px;
    overflow: hidden;
}

.section-header {
    padding: 24px 24px 16px 24px;
    border-bottom: 1px solid #e0e0e0;
}

.section-header h2 {
    font-size: 20px;
    font-weight: 500;
    color: #202124;
    margin: 0;
}

.materials-grid {
    padding: 24px;
}

.no-materials {
    text-align: center;
    padding: 48px 24px;
    color: #5f6368;
}

.material-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 16px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.material-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}

.material-header {
    padding: 20px 24px 16px 24px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.material-header h3 {
    font-size: 18px;
    font-weight: 500;
    color: #202124;
    margin: 0;
    flex: 1;
}

.material-actions {
    display: flex;
    gap: 8px;
    margin-left: 16px;
    align-items: center;
}

.toggle-content-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease;
    padding: 8px 12px;
    border: 1px solid #dadce0;
    border-radius: 4px;
    background: white;
    color: #5f6368;
    cursor: pointer;
    font-size: 13px;
}

/* Inline actions (View/Edit/Delete) */
.action-group { display: inline-flex; align-items: center; gap: 8px; }
.action-group form { margin: 0; display: inline; }
.icon-btn { display: inline-flex; align-items: center; justify-content: center; height: 32px; min-width: 36px; padding: 0 10px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; color: #111827; cursor: pointer; text-decoration: none; font-weight: 600; }
.icon-btn i { pointer-events: none; }
.icon-btn.secondary { background: #f8fafc; }
.icon-btn.primary { background: #1a73e8; border-color: #1a73e8; color: #fff; }
.icon-btn.danger { background: #e11d48; border-color: #e11d48; color: #fff; }
.icon-btn.success { background: #10b981; border-color: #10b981; color: #fff; }
.icon-btn.practice-test { background: #8b5cf6; border-color: #8b5cf6; color: #fff; }
.icon-btn:hover { filter: brightness(1.05); }

.toggle-content-btn:hover {
    background: #f8f9fa;
    border-color: #5f6368;
}

.toggle-icon {
    transition: transform 0.2s ease;
    font-size: 12px;
}

.toggle-content-btn.expanded .toggle-icon {
    transform: rotate(180deg);
}

.material-content {
    padding: 20px 24px;
    color: #202124;
    line-height: 1.6;
    border-top: 1px solid #e0e0e0;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.material-content.collapsed {
    display: none;
}

.material-footer {
    padding: 16px 24px;
    background: #f8f9fa;
    border-top: 1px solid #e0e0e0;
}

.material-footer small {
    color: #5f6368;
    font-size: 12px;
}

/* Enhanced table colors */
.mat-table { border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #ffffff; box-shadow: 0 4px 14px rgba(16,24,40,0.06); }
.mat-table thead { background: linear-gradient(90deg, #4f46e5 0%, #1d4ed8 100%) !important; }
.mat-table thead th { color: #ffffff !important; border-bottom: 1px solid rgba(255,255,255,0.18) !important; letter-spacing: .2px; font-weight: 700; }
.mat-table tbody tr:nth-child(even) { background: #f7f9ff; }
.mat-table tbody tr:hover { background: #eef2ff; transition: background-color .2s ease; }
.mat-table td { border-bottom: 1px solid #eef2ff; }
.mat-table tr:last-child td { border-bottom: none; }

/* Badges */
.section-badge { display:inline-block; padding:4px 10px; border-radius:9999px; background: linear-gradient(90deg,#9333ea,#3b82f6); color:#fff; font-weight:700; font-size:12px; box-shadow: 0 2px 6px rgba(59,130,246,0.25); }
.date-chip { display:inline-block; padding:4px 10px; border-radius:8px; background:#eef2ff; color:#1d4ed8; font-weight:600; border:1px solid #dbeafe; }

/* Flash Messages */
.flash {
    background: #fde68a;
    color: #b45309;
    border-radius: 8px;
    padding: 12px 18px;
    margin: 24px;
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

.close-btn {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.close-btn:hover {
    opacity: 1;
}

/* Image Controls Styling */
.image-controls {
    background: #f8f9fa;
    border: 1px solid #dadce0;
    border-radius: 4px;
    padding: 4px;
    margin: 0 8px;
}

.image-controls .toolbar-btn {
    background: white;
    border: 1px solid #dadce0;
    margin: 0 2px;
}

.image-controls .toolbar-btn:hover {
    background: #e8f0fe;
    border-color: #1a73e8;
}

.image-controls .toolbar-btn.active {
    background: #1a73e8;
    color: white;
    border-color: #1a73e8;
}

/* Enhanced Image Styling */
.docs-editor img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
}

.docs-editor img:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: scale(1.02);
}

.docs-editor img.selected {
    outline: 2px solid #1a73e8;
    outline-offset: 2px;
    box-shadow: 0 0 0 4px rgba(26, 115, 232, 0.2);
}

/* Image Wrap Styles */
.docs-editor img.inline {
    display: inline-block;
    vertical-align: middle;
    margin: 0 8px;
}

.docs-editor img.wrap {
    float: left;
    margin: 0 16px 16px 0;
    max-width: 300px;
}

.docs-editor img.break {
    float: left;
    margin: 0 16px 16px 0;
    max-width: 300px;
    clear: both;
}

.docs-editor img.behind {
    position: absolute;
    z-index: -1;
    opacity: 0.3;
}

.docs-editor img.front {
    position: absolute;
    z-index: 10;
}

/* Image Resize Handles */
.docs-editor img.resizable {
    position: relative;
}

.docs-editor img.resizable::after {
    content: '';
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    background: #1a73e8;
    border: 2px solid white;
    border-radius: 2px;
    cursor: se-resize;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.docs-editor img.resizable:hover::after {
    opacity: 1;
}

/* Old image context menu CSS removed - using TinyMCE native features */

/* Responsive Design */
@media (max-width: 768px) {
    .content {
        padding: 0;
    }
    
    .docs-editor-container {
        margin: 16px;
    }
    
    .docs-editor {
        padding: 24px 16px;
    }
    
    .docs-title-bar {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .docs-title-input {
        margin-right: 0;
    }
    
    .docs-toolbar {
        padding: 8px;
        gap: 2px;
        overflow-x: auto;
    }
    
    .toolbar-separator {
        margin: 0 4px;
    }
    
    .image-controls {
        margin: 0 4px;
    }
    
    .content-section {
        margin: 16px;
    }
    
    .material-header {
        flex-direction: column;
        gap: 12px;
    }
    
    .material-actions {
        margin-left: 0;
        align-self: flex-start;
    }
}

/* Image Editor Modal Styles */
.image-editor-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
}

.image-editor-overlay {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 20px;
}

.image-editor-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 1200px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.image-editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
}

.image-editor-header h3 {
    margin: 0;
    color: #202124;
    font-size: 18px;
    font-weight: 500;
}

.image-editor-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.image-editor-toolbar {
    display: flex;
    align-items: center;
    padding: 12px 24px;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
        flex-wrap: wrap;
    gap: 16px;
}

.image-editor-toolbar .toolbar-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.image-editor-toolbar .toolbar-label {
    font-size: 12px;
    color: #5f6368;
    font-weight: 500;
    white-space: nowrap;
}

.image-editor-toolbar .toolbar-slider {
    width: 100px;
    height: 4px;
    background: #e0e0e0;
    border-radius: 2px;
    outline: none;
    -webkit-appearance: none;
}

.image-editor-toolbar .toolbar-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    background: #1a73e8;
    border-radius: 50%;
    cursor: pointer;
}

.image-editor-canvas-container {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: #f8f9fa;
    overflow: auto;
}

#imageEditorCanvas {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Responsive Image Editor */
@media (max-width: 768px) {
    .image-editor-container {
        width: 95%;
        max-height: 95vh;
    }
    
    .image-editor-toolbar {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .image-editor-actions {
        flex-wrap: wrap;
        gap: 4px;
    }
}

/* Comprehension Question Edit Form Styles - Matching Material Question Builder */
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

.header h1 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 24px;
    font-weight: 600;
}

.header p {
    margin: 0;
    color: #666;
    font-size: 14px;
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

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
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

.question-item {
    background: #f8f9fa;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
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
    padding-top: 20px;
    border-top: 1px solid #e1e5e9;
}

.btn {
    background: #4285f4;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 5px;
    transition: background-color 0.3s;
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

.btn-primary {
    background: #28a745;
}

.btn-primary:hover {
    background: #218838;
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

.matching-container {
    margin-top: 15px;
}

.matching-items {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 10px;
}

.matching-left,
.matching-right {
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    padding: 15px;
    background: #f8f9fa;
}

.matching-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.matching-row label {
    min-width: 60px;
    font-weight: 500;
    color: #374151;
}

.matching-input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
}

.matching-input:focus {
    outline: none;
    border-color: #16a34a;
    box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.1);
}

.btn-remove-matching {
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 4px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 12px;
}

.btn-remove-matching:hover {
    background: #b91c1c;
}

.add-matching-btn {
    background: #16a34a;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 10px;
}

.add-matching-btn:hover {
    background: #15803d;
}

.matching-left label,
.matching-right label {
    font-weight: 600;
    color: #555;
    margin-bottom: 10px;
    display: block;
}

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        padding: 10px;
    }
    
    .question-form {
        padding: 20px;
    }
    
    .matching-items {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        text-align: center;
    }
    
    .btn {
        display: block;
        width: 100%;
        margin: 5px 0;
    }
}

/* Matching question styles for edit form */
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

/* Remove option button styling */
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
    flex-shrink: 0;
}

.remove-option:hover {
    background: #c82333;
}

/* Add option button styling */
.add-option {
    margin-top: 10px;
    padding: 8px 16px;
    font-size: 14px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.add-option:hover {
    background: #218838;
}

/* Input group styling to match create form */
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
    margin: 0;
}

.input-group input:focus {
    outline: none;
    border-color: #4285f4;
}
</style>

<!-- jQuery, Bootstrap JS, and TinyMCE -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.7.2/tinymce.min.js"></script>
<script>
// TinyMCE offline-first loader: try local copies, fallback to CDN
(function(){
    function load(src, cb){
        var s = document.createElement('script');
        s.src = src;
        s.onload = cb || function(){};
        document.head.appendChild(s);
    }
    if (typeof window.tinymce === 'undefined') {
        // Try common local paths first
        load('../assets/vendor/tinymce/js/tinymce/tinymce.min.js', function(){
            if (typeof window.tinymce === 'undefined') {
                load('../assets/vendor/tinymce/tinymce.min.js', function(){
                    // Last resort: CDN
                    if (typeof window.tinymce === 'undefined') {
                        load('https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.7.2/tinymce.min.js');
                    }
                });
            }
        });
    }
})();
</script>

<script>
// TinyMCE Google Docs-like Editor Implementation
let autoSaveTimeout;
let documentVersion = 1;
let isCollaborating = false;
let collaborators = [];
let documentHistory = [];
let comments = [];
let editorInstance = null;

// Initialize Complete TinyMCE Editor
function initializeTinyMCE() {
    tinymce.init({
        license_key: 'gpl',
        selector: '#docs-editor',
        height: 600,
        menubar: true,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount',
            'codesample', 'pagebreak', 'nonbreaking',
            'directionality', 'visualchars', 'autosave'
        ],
        toolbar: [
            'undo redo | blocks fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | outdent indent | numlist bullist | removeformat | help',
            'searchreplace | insertdatetime | charmap | link image media table | pagebreak | code preview fullscreen | save'
        ],
        toolbar_mode: 'sliding',
        menubar: 'file edit view insert format tools table help',
        menu: {
            file: { title: 'File', items: 'newdocument restoredraft | preview | print | deleteallconversations' },
            edit: { title: 'Edit', items: 'undo redo | cut copy paste pastetext | selectall | searchreplace | removeformat' },
            view: { title: 'View', items: 'code | visualaid visualchars visualblocks | preview fullscreen' },
            insert: { title: 'Insert', items: 'image link media codesample inserttable | charmap | pagebreak nonbreaking anchor | insertdatetime' },
            format: { title: 'Format', items: 'bold italic underline strikethrough superscript subscript codeformat | blocks align | forecolor backcolor | fontsize | removeformat' },
            tools: { title: 'Tools', items: 'code wordcount' },
            table: { title: 'Table', items: 'inserttable | cell row column | tableprops deletetable' },
            help: { title: 'Help', items: 'help' }
        },
        content_style: `
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
                font-size: 14px; 
                line-height: 1.6; 
                color: #202124; 
                margin: 0;
                padding: 20px;
            }
            h1, h2, h3, h4, h5, h6 { 
                color: #202124; 
                margin-top: 24px; 
                margin-bottom: 16px; 
                font-weight: 600; 
            }
            p { 
                margin-bottom: 16px; 
            }
            img { 
                max-width: 100%; 
                height: auto; 
                border-radius: 4px; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
            }
            table { 
                border-collapse: collapse; 
                width: 100%; 
                margin: 16px 0; 
            }
            table td, table th { 
                border: 1px solid #dadce0; 
                padding: 8px 12px; 
                text-align: left; 
            }
            table th { 
                background-color: #f8f9fa; 
                font-weight: 600; 
            }
            blockquote { 
                border-left: 4px solid #1a73e8; 
                margin: 16px 0; 
                padding: 0 16px; 
                color: #5f6368; 
            }
            code { 
                background-color: #f1f3f4; 
                padding: 2px 4px; 
                border-radius: 3px; 
                font-family: 'Courier New', monospace; 
            }
            pre { 
                background-color: #f1f3f4; 
                padding: 16px; 
                border-radius: 4px; 
                overflow-x: auto; 
            }
        `,
        branding: false,
        promotion: false,
        resize: true,
        statusbar: true,
        elementpath: true,
        contextmenu: 'link image table configurepermanentpen',
        // Remove API key requirements - not needed for self-hosted version
        // Advanced features
        paste_data_images: true,
        paste_auto_cleanup_on_paste: false,
        paste_remove_styles_if_webkit: false,
        paste_merge_formats: true,
        // Support more image file types
        file_picker_types: 'image',
        file_picker_callback: function (callback, value, meta) {
            if (meta.filetype === 'image') {
                const input = document.createElement('input');
                input.setAttribute('type', 'file');
                input.setAttribute('accept', 'image/*,.jpg,.jpeg,.png,.gif,.webp,.bmp,.svg,.tiff,.ico');
                input.click();
                
                input.onchange = function () {
                    const file = this.files[0];
                    if (file) {
                        // Validate file type
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml', 'image/tiff', 'image/x-icon'];
                        if (!validTypes.includes(file.type) && !file.type.startsWith('image/')) {
                            alert('Please select a valid image file (JPG, PNG, GIF, WebP, BMP, SVG, TIFF, ICO)');
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function () {
                            callback(reader.result, {
                                title: file.name
                            });
                        };
                        reader.onerror = function() {
                            alert('Failed to read image file');
                        };
                        reader.readAsDataURL(file);
                    }
                };
            }
        },
        // Disable automatic uploads to prevent server calls
        automatic_uploads: false,
        // Auto-save configuration
        autosave_ask_before_unload: true,
        autosave_interval: '30s',
        autosave_retention: '2m',
        autosave_restore_when_empty: false,
        // Font size configuration
        fontsize_formats: '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 22pt 24pt 26pt 28pt 36pt 48pt 72pt',
        font_formats: 'Andale Mono=andale mono,times; Arial=arial,helvetica,sans-serif; Arial Black=arial black,avant garde; Book Antiqua=book antiqua,palatino; Comic Sans MS=comic sans ms,sans-serif; Courier New=courier new,courier; Georgia=georgia,palatino; Helvetica=helvetica; Impact=impact,chicago; Symbol=symbol; Tahoma=tahoma,arial,helvetica,sans-serif; Terminal=terminal,monaco; Times New Roman=times new roman,times; Trebuchet MS=trebuchet ms,geneva; Verdana=verdana,geneva; Webdings=webdings; Wingdings=wingdings,zapf dingbats',
        // Image editing features
        image_advtab: true,
        image_caption: true,
        image_title: true,
        image_description: true,
        image_uploadtab: true,
        image_class_list: [
            {title: 'None', value: ''},
            {title: 'Rounded', value: 'rounded'},
            {title: 'Circle', value: 'circle'},
            {title: 'Shadow', value: 'shadow'},
            {title: 'Border', value: 'border'}
        ],
        // Advanced text features
        font_formats: 'Andale Mono=andale mono,times; Arial=arial,helvetica,sans-serif; Arial Black=arial black,avant garde; Book Antiqua=book antiqua,palatino; Comic Sans MS=comic sans ms,sans-serif; Courier New=courier new,courier; Georgia=georgia,palatino; Helvetica=helvetica; Impact=impact,chicago; Symbol=symbol; Tahoma=tahoma,arial,helvetica,sans-serif; Terminal=terminal,monaco; Times New Roman=times new roman,times; Trebuchet MS=trebuchet ms,geneva; Verdana=verdana,geneva; Webdings=webdings; Wingdings=wingdings,zapf dingbats',
        fontsize_formats: '8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 22pt 24pt 26pt 28pt 36pt 48pt 72pt',
        // Auto-save
        autosave_ask_before_unload: true,
        autosave_interval: '30s',
        autosave_retention: '2m',
        autosave_restore_when_empty: false,
        // Spell checker
        browser_spellcheck: true,
        contextmenu_never_use_native: true,
        // Advanced editing
        wordcount: {
            show_word_count: true,
            show_char_count: true,
            show_paragraphs: true,
            show_reading_time: true
        },
        // Comments and collaboration
        comments: {
            add_comment: 'Add comment',
            delete_comment: 'Delete comment',
            edit_comment: 'Edit comment',
            resolve_comment: 'Resolve comment'
        },
        // Advanced table features
        table_default_attributes: {
            border: '1'
        },
        table_default_styles: {
            'border-collapse': 'collapse',
            'width': '100%'
        },
        table_class_list: [
            {title: 'None', value: ''},
            {title: 'Striped', value: 'table-striped'},
            {title: 'Bordered', value: 'table-bordered'},
            {title: 'Hover', value: 'table-hover'}
        ],
        // Advanced link features
        link_context_toolbar: true,
        link_default_protocol: 'https',
        // Media features
        media_live_embeds: true,
        // Undo/Redo configuration - prevent automatic clearing
        undo_redo_levels: 50,
        undo_redo_separator: '|',
        undo_redo_shortcuts: true,
        // Enhanced undo manager settings to prevent timeout
        undo_manager: {
            levels: 50,
            timeout: 0, // No timeout for undo operations
            ignore_shortcuts: false,
            auto_clear: false, // Prevent automatic clearing of undo history
            max_levels: 50
        },
        // Additional settings to maintain undo history
        save_onsavecallback: function() {
            // Prevent save from clearing undo history
            return true;
        },
        // Enhanced paste configuration for better undo support (TinyMCE 6.0 compatible)
        paste_merge_formats: true,
        paste_auto_cleanup_on_paste: false,
        paste_remove_styles_if_webkit: false,
        // Ensure undo history is maintained during operations
        save_enablewhendirty: true,
        media_url_resolver: function (data, resolve) {
            if (data.url.indexOf('youtube.com') !== -1 || data.url.indexOf('youtu.be') !== -1) {
                // Convert YouTube URL to proper embed format
                let embedUrl = data.url;
                if (data.url.indexOf('youtube.com/watch?v=') !== -1) {
                    const videoId = data.url.split('v=')[1].split('&')[0];
                    embedUrl = 'https://www.youtube.com/embed/' + videoId;
                } else if (data.url.indexOf('youtu.be/') !== -1) {
                    const videoId = data.url.split('youtu.be/')[1].split('?')[0];
                    embedUrl = 'https://www.youtube.com/embed/' + videoId;
                }
                resolve({html: '<iframe src="' + embedUrl + '" width="560" height="315" frameborder="0" allowfullscreen></iframe>'});
            } else {
                resolve({html: '<a href="' + data.url + '">' + data.url + '</a>'});
            }
        },
        setup: function (editor) {
            editorInstance = editor;
            
            // Initialize undo manager and ensure it's working properly
            editor.on('init', function() {
                // Ensure undo manager is properly initialized
                if (editor.undoManager) {
                    console.log('TinyMCE undo manager initialized successfully');
                    
                    // Test undo/redo functionality
                    console.log('Undo manager has undo method:', typeof editor.undoManager.undo === 'function');
                    console.log('Undo manager has redo method:', typeof editor.undoManager.redo === 'function');
                    
                    // Prevent undo manager from being cleared automatically
                    const originalClear = editor.undoManager.clear;
                    editor.undoManager.clear = function() {
                        console.log('Undo manager clear called - preventing automatic clearing');
                        // Don't clear the undo history automatically
                        return false;
                    };
                    
                    // Override the undo manager's add method to ensure it works properly
                    const originalAdd = editor.undoManager.add;
                    editor.undoManager.add = function() {
                        console.log('Adding to undo history');
                        return originalAdd.call(this);
                    };
                } else {
                    console.warn('TinyMCE undo manager not available');
                }
            });
            
            // Auto-save functionality
            editor.on('input', function () {
                autoSave();
            });
            
            // Handle drag and drop with undo support
            editor.on('drop', function(e) {
                e.preventDefault();
                const files = e.dataTransfer.files;
                if (files && files.length > 0) {
                    const file = files[0];
                    if (file.type.startsWith('image/')) {
                        // Insert image - TinyMCE will handle undo history automatically
                        const reader = new FileReader();
                        reader.onload = function() {
                            editor.insertContent('<img src="' + reader.result + '" style="max-width: 100%; height: auto;" />');
                            console.log('Image dropped - TinyMCE managing undo history');
                        };
                        reader.readAsDataURL(file);
                    } else {
                        alert('Please drop an image file (JPG, PNG, GIF, WebP, etc.)');
                    }
                }
            });
            
            // Handle paste with proper undo manager support
            editor.on('paste', function(e) {
                const items = e.clipboardData.items;
                let hasImage = false;
                
                // Check for images first
                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    if (item.type.startsWith('image/')) {
                        hasImage = true;
                        e.preventDefault();
                        const file = item.getAsFile();
                        const reader = new FileReader();
                        reader.onload = function() {
                            // Insert image - TinyMCE will handle undo history automatically
                            editor.insertContent('<img src="' + reader.result + '" style="max-width: 100%; height: auto;" />');
                            console.log('Image pasted - TinyMCE managing undo history');
                        };
                        reader.readAsDataURL(file);
                        break;
                    }
                }
                
                // For text paste operations, let TinyMCE handle undo naturally
                if (!hasImage) {
                    // TinyMCE automatically handles undo history for text paste
                    console.log('Text paste - TinyMCE managing undo history');
                }
            });
            
            // Handle paste preprocessing to ensure proper undo support
            editor.on('BeforePaste', function(e) {
                console.log('Before paste event - ensuring undo history support');
                // This event fires before paste, ensuring undo manager is ready
            });
            
            // Handle paste post-processing
            editor.on('PastePostProcess', function(e) {
                console.log('Paste post-process - content has been pasted');
                // TinyMCE automatically handles undo history after paste
            });
            
            // Handle content changes - let TinyMCE handle undo naturally
            editor.on('change', function() {
                // TinyMCE automatically handles undo history on change events
                console.log('Content changed - TinyMCE managing undo history');
            });
            
            // Handle node change events (minimal intervention)
            editor.on('NodeChange', function(e) {
                // Let TinyMCE handle undo management naturally
                console.log('Node change detected');
            });
            
            // Handle exec command events (minimal intervention)
            editor.on('ExecCommand', function(e) {
                console.log('Command executed:', e.command);
                // Only log, let TinyMCE handle undo management
            });
            
            // Handle image resize
            editor.on('ObjectResized', function(e) {
                if (e.target.nodeName === 'IMG') {
                    console.log('Image resized:', e.target);
                    // Auto-save after resize
                    autoSave();
                }
            });
            
            // Handle successful save events
            editor.on('SaveContent', function() {
                editor.setDirty(false);
                console.log('TinyMCE content saved, dirty state reset');
                // Don't clear undo history on save
            });
            
            // Prevent undo history from being cleared on various events
            editor.on('BeforeSetContent', function() {
                console.log('BeforeSetContent - preserving undo history');
            });
            
            editor.on('SetContent', function() {
                console.log('SetContent - preserving undo history');
            });
            
            // Monitor undo manager state
            editor.on('Undo', function() {
                console.log('Undo executed successfully');
            });
            
            editor.on('Redo', function() {
                console.log('Redo executed successfully');
            });
            
            // Add custom image editing functionality
            editor.on('init', function() {
                // Add custom image resize handles
                editor.on('ObjectResized', function(e) {
                    if (e.target.nodeName === 'IMG') {
                        console.log('Image resized:', e.target);
                        autoSave();
                    }
                });
                
                // Add custom image context menu
                editor.on('contextmenu', function(e) {
                    if (e.target.nodeName === 'IMG') {
                        e.preventDefault();
                        showCustomImageMenu(e, e.target);
                    }
                });
            });
            
            // Update status
            editor.on('change', function () {
                updateStatus('saving');
            });
            
            // Initialize collaboration
            editor.on('init', function () {
                updateStatus('ready');
            });
            
            // Custom buttons removed - using core TinyMCE features only
            
            // Custom menu items removed - using core TinyMCE features only
            
            // Advanced image editing
            editor.on('init', function () {
                // Add image editing capabilities
                editor.on('ObjectResized', function (e) {
                    if (e.target.nodeName === 'IMG') {
                        console.log('Image resized:', e.target);
                    }
                });
                
                // Add image context menu
                // TinyMCE handles image context menu natively
            });
            
            // Advanced text editing features
            editor.on('keydown', function (e) {
                // Auto-save on Ctrl+S
                if (e.ctrlKey && e.keyCode === 83) {
                    e.preventDefault();
                    saveDocument();
                }
                
                // Auto-save on Ctrl+Enter
                if (e.ctrlKey && e.keyCode === 13) {
                    e.preventDefault();
                    saveDocument();
                }
            });
            
            // Word count display
            editor.on('keyup', function () {
                const wordCount = editor.plugins.wordcount;
                if (wordCount) {
                    const count = wordCount.body.getWordCount();
                    console.log('Word count:', count);
                }
                });
            },
        images_upload_handler: function (blobInfo, success, failure) {
            try {
                // Validate file type
                const file = blobInfo.blob();
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml', 'image/tiff', 'image/x-icon'];
                
                if (!validTypes.includes(file.type) && !file.type.startsWith('image/')) {
                    if (typeof failure === 'function') {
                        failure('Unsupported file type. Please use JPG, PNG, GIF, WebP, BMP, SVG, TIFF, or ICO images.');
                    }
                    return;
                }
                
                // Create a simple data URL for immediate preview
                const reader = new FileReader();
                reader.onload = function() {
                    if (typeof success === 'function') {
                        success(reader.result);
                    }
                };
                reader.onerror = function() {
                    if (typeof failure === 'function') {
                        failure('Failed to read image file');
                    }
                };
                reader.readAsDataURL(file);
            } catch (error) {
                console.error('Image upload error:', error);
                if (typeof failure === 'function') {
                    failure('Image upload failed: ' + error.message);
                }
            }
        },
    });
}

// Enhanced auto-save functionality
function autoSave() {
    try {
        clearTimeout(autoSaveTimeout);
        updateStatus('saving');
        
        autoSaveTimeout = setTimeout(() => {
            saveDocumentVersion();
            updateStatus('saved');
            
            // Reset TinyMCE's dirty state after auto-save
            safeTinyMCEReset();
        }, 2000);
    } catch (error) {
        console.error('Auto-save error:', error);
        updateStatus('error');
    }
}

// Update collaboration status
function updateStatus(status) {
    const statusIndicator = document.getElementById('statusIndicator');
    const autoSaveStatus = document.getElementById('autoSaveStatus');
    
    if (statusIndicator && autoSaveStatus) {
        statusIndicator.className = `status-indicator ${status}`;
        autoSaveStatus.className = `auto-save-status ${status}`;
        
        switch(status) {
            case 'saving':
                statusIndicator.innerHTML = '<i class="fas fa-circle"></i> Saving...';
                autoSaveStatus.innerHTML = '<i class="fas fa-save"></i> Saving changes';
                break;
            case 'saved':
                statusIndicator.innerHTML = '<i class="fas fa-circle"></i> Ready';
                autoSaveStatus.innerHTML = '<i class="fas fa-save"></i> All changes saved';
                break;
            case 'error':
                statusIndicator.innerHTML = '<i class="fas fa-circle"></i> Error';
                autoSaveStatus.innerHTML = '<i class="fas fa-save"></i> Save failed';
                break;
            case 'collaborating':
                statusIndicator.innerHTML = '<i class="fas fa-circle"></i> Collaborating';
                autoSaveStatus.innerHTML = `<i class="fas fa-users"></i> ${collaborators.length} people editing`;
                break;
        }
    }
}

// Save document version for history
function saveDocumentVersion() {
    const titleInput = document.getElementById('docs-title');
    const title = titleInput ? titleInput.value : '';
    const content = editorInstance ? editorInstance.getContent() : '';
    
    const version = {
        id: documentVersion++,
        title: title,
        content: content,
        timestamp: new Date().toISOString(),
        author: '<?php echo $_SESSION["teacher_name"] ?? "Unknown"; ?>'
    };
    
    documentHistory.push(version);
    
    // Keep only last 50 versions
    if (documentHistory.length > 50) {
        documentHistory.shift();
    }
    
    console.log('Document version saved:', version.id);
}

// Show version history
function showVersionHistory() {
    const modal = document.createElement('div');
    modal.className = 'version-history-modal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeVersionHistory()">
            <div class="modal-content" onclick="event.stopPropagation()">
                <div class="modal-header">
                    <h3>Version History</h3>
                    <button onclick="closeVersionHistory()" class="close-btn">&times;</button>
                </div>
                <div class="version-list">
                    ${documentHistory.map(version => `
                        <div class="version-item" onclick="restoreVersion(${version.id})">
                            <div class="version-info">
                                <strong>Version ${version.id}</strong>
                                <span class="version-date">${new Date(version.timestamp).toLocaleString()}</span>
                            </div>
                            <div class="version-author">by ${version.author}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

// Close version history
function closeVersionHistory() {
    const modal = document.querySelector('.version-history-modal');
    if (modal) {
        modal.remove();
    }
}

// Restore version
function restoreVersion(versionId) {
    const version = documentHistory.find(v => v.id === versionId);
    if (version) {
        const titleInput = document.getElementById('docs-title');
        if (titleInput) {
            titleInput.value = version.title;
        }
        if (editorInstance) {
            editorInstance.setContent(version.content);
        }
        closeVersionHistory();
        updateStatus('saved');
    }
}

// Toggle collaboration
function toggleCollaboration() {
    isCollaborating = !isCollaborating;
    
    if (isCollaborating) {
        startCollaboration();
    } else {
        stopCollaboration();
    }
}

// Start collaboration
function startCollaboration() {
    // Simulate collaboration (in real app, would use WebSocket)
    collaborators = [
        { name: '<?php echo $_SESSION["teacher_name"] ?? "You"; ?>', color: '#1a73e8' },
        { name: 'Student 1', color: '#34a853' },
        { name: 'Student 2', color: '#fbbc04' }
    ];
    
    updateStatus('collaborating');
    console.log('Collaboration started with', collaborators.length, 'people');
}

// Stop collaboration
function stopCollaboration() {
    collaborators = [];
    updateStatus('saved');
    console.log('Collaboration stopped');
}

// Insert comment
function insertComment() {
    if (editorInstance) {
        const selection = editorInstance.selection.getContent();
        if (selection.trim()) {
            const comment = {
                id: Date.now(),
                text: selection,
                author: '<?php echo $_SESSION["teacher_name"] ?? "You"; ?>',
                timestamp: new Date().toISOString()
            };
            
            comments.push(comment);
            
            // Add comment marker
            const commentSpan = `<span class="comment-marker" data-comment-id="${comment.id}" title="Comment by ${comment.author}">💬</span>`;
            editorInstance.selection.setContent(commentSpan);
            
            console.log('Comment added:', comment);
        } else {
            alert('Please select text to comment on');
        }
    }
}

// Advanced image editing functions
// Old context menu function removed - using TinyMCE native features

// Old editImage function removed - using TinyMCE native features

// Old resizeImage function removed - using TinyMCE native features

// Old image editing functions removed - using TinyMCE native features

// Insert page break
function insertPageBreak() {
    const pageBreak = document.createElement('div');
    pageBreak.className = 'page-break';
    pageBreak.innerHTML = '<hr style="border: 2px dashed #ccc; margin: 20px 0;">';
    
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        range.insertNode(pageBreak);
    } else {
        document.getElementById('docs-editor').appendChild(pageBreak);
    }
}

// Export document
function exportDocument() {
    const titleInput = document.getElementById('docs-title');
    const title = titleInput ? titleInput.value : 'Untitled Document';
    const content = editorInstance ? editorInstance.getContent() : '';
    
    // Create downloadable HTML file
    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
                .page-break { page-break-before: always; }
            </style>
        </head>
        <body>
            <h1>${title}</h1>
            ${content}
        </body>
        </html>
    `;
    
    const blob = new Blob([htmlContent], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = `${title}.html`;
    a.click();
    
    URL.revokeObjectURL(url);
    console.log('Document exported:', title);
}

// Initialize TinyMCE when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('docs-title');
    
    // Only initialize TinyMCE if we're not in comprehension edit mode
    if (!document.getElementById('comprehensionEditForm')) {
        // Ensure Save button is always clickable and triggers saveDocument()
        try {
            var explicitSaveBtn = document.querySelector('.docs-actions .docs-btn.docs-btn-secondary');
            if (explicitSaveBtn) {
                if (!explicitSaveBtn.id) { explicitSaveBtn.id = 'docs-save-btn'; }
                explicitSaveBtn.style.pointerEvents = 'auto';
                explicitSaveBtn.disabled = false;
                explicitSaveBtn.addEventListener('click', function(ev){
                    ev.preventDefault();
                    ev.stopPropagation();
                    if (typeof saveDocument === 'function') { saveDocument(); }
                });
            }
        } catch (e) { /* ignore */ }
        // Capture-phase fallback: trigger save for clicks inside the Save button
        document.addEventListener('click', function(e){
            var target = e.target;
            if (!target) return;
            var inSave = target.closest && (target.closest('#docs-save-btn') || target.closest('.docs-actions .docs-btn.docs-btn-secondary'));
            if (inSave) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof saveDocument === 'function') { saveDocument(); }
            }
        }, true);
        // Initialize TinyMCE (wait for offline fallback if needed)
        (function startEditorWhenReady(){
            if (window.tinymce && typeof window.tinymce.init === 'function') {
                initializeTinyMCE();
            } else {
                let tries = 0;
                const timer = setInterval(function(){
                    if (window.tinymce && typeof window.tinymce.init === 'function') {
                        clearInterval(timer);
                        initializeTinyMCE();
                    } else if (++tries > 50) { // ~5s timeout
                        clearInterval(timer);
                        console.warn('TinyMCE not loaded. Check offline path at ../assets/vendor/tinymce/');
                    }
                }, 100);
            }
        })();
    }
    
    // Handle successful form submission
    const urlParams = new URLSearchParams(window.location.search);
    const hasSuccess = urlParams.get('success') === '1';
    const hasUploaded = urlParams.get('uploaded') === '1';
    const hasUpdated = urlParams.get('updated') === '1';
    const hasDeleted = urlParams.get('deleted') === '1';
    
    if (hasSuccess || hasUploaded || hasUpdated || hasDeleted) {
        handleSaveSuccess();
        
        // Show success message if not already displayed
        if (!document.querySelector('.flash-success')) {
            let message = 'Content saved successfully!';
            if (hasUploaded) message = 'Content uploaded successfully!';
            else if (hasUpdated) message = 'Content updated successfully!';
            else if (hasDeleted) message = 'Content deleted successfully!';
            
            showSuccessMessage(message);
        }
    }
    
    // Only run these if we're not in comprehension edit mode
    if (!document.getElementById('comprehensionEditForm')) {
        // Auto-save on title change
        if (titleInput) {
            titleInput.addEventListener('input', autoSave);
        }
        
        // Prevent form resubmission
        const form = document.getElementById('materialForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Always move the visible file input into the form so the file is submitted reliably
                const src = document.getElementById('attachmentInput');
                if (src && src.files && src.files.length) {
                    try { form.appendChild(src); } catch(e){}
                }
                // Check if form is already being submitted
                if (this.dataset.submitting === 'true') {
                    e.preventDefault();
                    console.log('Preventing duplicate form submission');
                    return false;
                }
                
                // Mark as submitting
                this.dataset.submitting = 'true';
                
                // Reset flag after a delay
                setTimeout(() => {
                    this.dataset.submitting = 'false';
                }, 3000);
            });
        }
    }
    
    // Alternative success detection - check for any success indicators
    setTimeout(() => {
        if (!document.querySelector('.flash-success') && !document.querySelector('.flash-error')) {
            // Check if we're on a success page but no message is showing
            const currentUrl = window.location.href;
            if (currentUrl.includes('uploaded=1') || currentUrl.includes('updated=1') || currentUrl.includes('deleted=1')) {
                showSuccessMessage('Content saved successfully!');
            }
        }
    }, 1000);
    
    // Prevent browser back button from causing form resubmission
    window.addEventListener('beforeunload', function(e) {
        // Only show warning if there are unsaved changes
        if (typeof editorInstance !== 'undefined' && editorInstance && typeof editorInstance.isDirty === 'function' && editorInstance.isDirty()) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });
    
    // Handle page visibility change to prevent resubmission
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            // Reset form submission flag when page becomes visible
            const form = document.getElementById('materialForm');
            if (form) {
                form.dataset.submitting = 'false';
            }
        }
    });
    
    // Upload-only flow: when a file is selected, submit immediately to store as material attachment
    // Only run this if we're not in comprehension edit mode
    if (!document.getElementById('comprehensionEditForm')) {
        const uploadBtn = document.getElementById('attachmentInput');
        if (uploadBtn) {
            uploadBtn.addEventListener('change', function(){
                if (!this.files || !this.files.length) return;
                const titleInput = document.getElementById('docs-title');
                const title = titleInput ? titleInput.value : (this.files[0].name || 'Untitled upload');
                let content = editorInstance ? editorInstance.getContent() : '';
                if (!content || !content.trim()) { content = '<p></p>'; }
                // Set hidden fields
                const hiddenTitle = document.getElementById('hidden-title');
                const hiddenContent = document.getElementById('hidden-content');
                const hiddenSection = document.getElementById('hidden-section');
                if (hiddenTitle) hiddenTitle.value = title;
                if (hiddenContent) hiddenContent.value = content || '<p></p>';
                const secSel = document.getElementById('material-section');
                if (hiddenSection) hiddenSection.value = secSel ? (secSel.value || '') : '';
                // Submit via the hidden form (attachment is synced in submit listener)
                const form = document.getElementById('materialForm');
                if (form) {
                    if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
                }
            });
        }
    }
});

// Format text functions
function formatText(command, value = null) {
    document.execCommand(command, false, value);
    updateToolbarState();
}

// Clear formatting function for TinyMCE
function clearFormatting() {
    if (editorInstance && typeof editorInstance.execCommand === 'function') {
        try {
            editorInstance.execCommand('RemoveFormat');
            console.log('Clear formatting executed');
        } catch (error) {
            console.error('Error executing clear formatting:', error);
        }
    }
}

// Update toolbar button states
function updateToolbarState() {
    const buttons = document.querySelectorAll('.toolbar-btn');
    buttons.forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Check for active formatting
    if (document.queryCommandState('bold')) {
        document.querySelector('[onclick*="bold"]').classList.add('active');
    }
    if (document.queryCommandState('italic')) {
        document.querySelector('[onclick*="italic"]').classList.add('active');
    }
    if (document.queryCommandState('underline')) {
        document.querySelector('[onclick*="underline"]').classList.add('active');
    }
}

// Insert image function
function insertImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            uploadImage(file);
        }
    };
    input.click();
}

// Upload image function
function uploadImage(file) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
        // Insert image into editor
        const editor = document.getElementById('docs-editor');
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        img.style.display = 'block';
        img.style.margin = '16px auto';
        img.style.borderRadius = '4px';
        img.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
        img.style.cursor = 'pointer';
        
        // Make image draggable
        makeImageDraggable(img);
        
        // Insert at cursor position
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            range.deleteContents();
            range.insertNode(img);
            range.setStartAfter(img);
            range.setEndAfter(img);
            selection.removeAllRanges();
            selection.addRange(range);
        } else {
            editor.appendChild(img);
        }
        
        // Auto-save after image upload
        if (typeof autoSave === 'function') {
            autoSave();
        }
    };
    
    reader.onerror = function() {
        alert('Failed to read image file. Please try again.');
    };
    
    reader.readAsDataURL(file);
}

// Enhanced image handling with Google Docs-style features
let selectedImage = null;

function makeImageDraggable(img) {
    img.style.cursor = 'pointer';
    img.classList.add('resizable');
    
    // Click to select image
    img.addEventListener('click', function(e) {
        e.stopPropagation();
        selectImage(this);
    });
    
    // TinyMCE handles image context menu natively
    
    // Double-click to edit
    img.addEventListener('dblclick', function(e) {
        e.stopPropagation();
        showImageEditDialog(this);
    });
    
    // Hover effects
    img.addEventListener('mouseenter', function() {
        if (!this.classList.contains('selected')) {
            this.style.transform = 'scale(1.02)';
        }
    });
    
    img.addEventListener('mouseleave', function() {
        if (!this.classList.contains('selected')) {
            this.style.transform = 'scale(1)';
        }
    });
}

// Select image function
function selectImage(img) {
    // Remove previous selection
    document.querySelectorAll('.docs-editor img').forEach(i => {
        i.classList.remove('selected');
    });
    
    // Select current image
    img.classList.add('selected');
    selectedImage = img;
    
    // Show image controls
    showImageControls();
    
    // Update toolbar state
    updateImageToolbarState(img);
}

// Show image controls
function showImageControls() {
    const imageControls = document.getElementById('imageControls');
    if (imageControls) {
        imageControls.style.display = 'flex';
    }
}

// Hide image controls
function hideImageControls() {
    const imageControls = document.getElementById('imageControls');
    if (imageControls) {
        imageControls.style.display = 'none';
    }
}

// Update image toolbar state
function updateImageToolbarState(img) {
    const wrapType = img.dataset.wrap || 'inline';
    document.querySelectorAll('.image-controls .toolbar-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const activeBtn = document.querySelector(`[onclick*="${wrapType}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
}

// Draggable Images Functionality
function makeImageDraggable(img) {
    let isDragging = false;
    let startX, startY, initialX, initialY;
    
    // Add drag handle
    img.style.cursor = 'move';
    img.draggable = true;
    
    // Mouse down - start drag
    img.addEventListener('mousedown', function(e) {
        if (e.button === 0) { // Left mouse button
            isDragging = true;
            img.classList.add('dragging');
            
            startX = e.clientX;
            startY = e.clientY;
            
            // Get current position
            const rect = img.getBoundingClientRect();
            initialX = rect.left;
            initialY = rect.top;
            
            e.preventDefault();
        }
    });
    
    // Mouse move - drag
    document.addEventListener('mousemove', function(e) {
        if (isDragging) {
            const deltaX = e.clientX - startX;
            const deltaY = e.clientY - startY;
            
            img.style.position = 'relative';
            img.style.left = deltaX + 'px';
            img.style.top = deltaY + 'px';
            img.style.zIndex = '1000';
        }
    });
    
    // Mouse up - end drag
    document.addEventListener('mouseup', function(e) {
        if (isDragging) {
            isDragging = false;
            img.classList.remove('dragging');
            
            // Reset position styles
            img.style.position = '';
            img.style.left = '';
            img.style.top = '';
            img.style.zIndex = '';
            
            // Auto-save after drag
            if (typeof autoSave === 'function') {
                autoSave();
            }
        }
    });
    
    // Touch events for mobile
    img.addEventListener('touchstart', function(e) {
        if (e.touches.length === 1) {
            isDragging = true;
            img.classList.add('dragging');
            
            const touch = e.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
            
            const rect = img.getBoundingClientRect();
            initialX = rect.left;
            initialY = rect.top;
            
            e.preventDefault();
        }
    });
    
    document.addEventListener('touchmove', function(e) {
        if (isDragging && e.touches.length === 1) {
            const touch = e.touches[0];
            const deltaX = touch.clientX - startX;
            const deltaY = touch.clientY - startY;
            
            img.style.position = 'relative';
            img.style.left = deltaX + 'px';
            img.style.top = deltaY + 'px';
            img.style.zIndex = '1000';
            
            e.preventDefault();
        }
    });
    
    document.addEventListener('touchend', function(e) {
        if (isDragging) {
            isDragging = false;
            img.classList.remove('dragging');
            
            img.style.position = '';
            img.style.left = '';
            img.style.top = '';
            img.style.zIndex = '';
            
            if (typeof autoSave === 'function') {
                autoSave();
            }
        }
    });
}

// Old image editing functions removed - using TinyMCE native features

// Show image context menu
// Old context menu functions removed - using TinyMCE native features

// Show image edit dialog
function showImageEditDialog(img) {
    const currentWidth = img.offsetWidth;
    const currentHeight = img.offsetHeight;
    
    const newWidth = prompt('Enter new width in pixels:', currentWidth);
    if (newWidth && !isNaN(newWidth) && newWidth > 0) {
        img.style.width = newWidth + 'px';
        img.style.height = 'auto';
    }
}

// Click outside to deselect
document.addEventListener('click', function(e) {
    // Only handle image deselection if we're not in comprehension edit mode
    if (!document.getElementById('comprehensionEditForm')) {
        if (!e.target.closest('.docs-editor img') && !e.target.closest('.image-controls')) {
            if (selectedImage) {
                selectedImage.classList.remove('selected');
                selectedImage = null;
                hideImageControls();
            }
        }
    }
});

// Save document function
function saveDocument() {
    const titleInput = document.getElementById('docs-title');
    const title = titleInput ? titleInput.value : '';
    
    // Get content from TinyMCE editor instance
    let content = '';
    if (typeof editorInstance !== 'undefined' && editorInstance) {
        content = editorInstance.getContent();
        console.log('Content from TinyMCE:', content);
    } else {
        // Fallback: read from the textarea value (not innerHTML)
        var fallbackEl = document.getElementById('docs-editor');
        content = fallbackEl ? (fallbackEl.value || '') : '';
        console.log('Content from fallback (textarea value):', content);
    }
    
    if (!title.trim()) {
        alert('Please enter a title for your document.');
        return;
    }
    
    // Check if content is empty (remove HTML tags for validation)
    const textContent = content.replace(/<[^>]*>/g, '').trim();
    const hasImages = content.includes('<img');
    const hasTables = content.includes('<table');
    const hasIframes = content.includes('<iframe');
    const hasMedia = content.includes('<video') || content.includes('<audio');
    const hasLists = content.includes('<ul') || content.includes('<ol');
    const hasHeadings = content.includes('<h1') || content.includes('<h2') || content.includes('<h3');
    
    // Check if content has any meaningful content
    if (!textContent && !hasImages && !hasTables && !hasIframes && !hasMedia && !hasLists && !hasHeadings) {
        alert('Please add some content to your document.');
        return;
    }
    
    // Update hidden form fields
    document.getElementById('hidden-title').value = title;
    document.getElementById('hidden-content').value = content;
    var sec = document.getElementById('material-section');
    document.getElementById('hidden-section').value = sec ? (sec.value || '') : '';
    
    // Debug: Log form data before submission
    console.log('Form data being submitted:');
    console.log('Title:', title);
    console.log('Content length:', content.length);
    console.log('Form action:', document.getElementById('materialForm').action);
    
    // Reset TinyMCE's dirty state before submission
    safeTinyMCEReset();
    
    // Submit form and prevent resubmission
    const form = document.getElementById('materialForm');
    
    // Let the submit handler manage the duplicate submission guard
    
    // Ensure the file input is part of the form before submit
    try {
        var fileInput = document.getElementById('attachmentInput');
        if (fileInput && fileInput.files && fileInput.files.length && fileInput.form !== form) {
            form.appendChild(fileInput);
        }
    } catch (e) { /* ignore */ }
    
    // Submit form
    if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
}

// Helper function for safe TinyMCE operations
function safeTinyMCEReset() {
    if (typeof editorInstance !== 'undefined' && editorInstance) {
        try {
            // Check if setDirty method exists
            if (typeof editorInstance.setDirty === 'function') {
                editorInstance.setDirty(false);
            }
            
            // DON'T clear undo manager - this was causing the timeout issue
            // The undo history should persist even after auto-save
            console.log('TinyMCE dirty state reset, undo history preserved');
            return true;
        } catch (error) {
            console.error('Error resetting TinyMCE state:', error);
            return false;
        }
    }
    return false;
}

// Handle successful save response
function handleSaveSuccess() {
    safeTinyMCEReset();
    updateStatus('saved');
}

// Show success message
function showSuccessMessage(message) {
    // Remove existing success messages
    const existingMessages = document.querySelectorAll('.flash-success');
    existingMessages.forEach(msg => msg.remove());
    
    // Create success message
    const successDiv = document.createElement('div');
    successDiv.className = 'flash flash-success';
    successDiv.id = 'dynamic-success-message';
    successDiv.innerHTML = `
        <i class="fas fa-check-circle"></i>
        <span>${message}</span>
        <button onclick="closeFlashMessage('dynamic-success-message')" class="close-btn">&times;</button>
    `;
    
    // Insert at the top of main content
    const mainContent = document.querySelector('.main-content');
    const contentHeader = document.querySelector('.content-header');
    if (contentHeader && contentHeader.nextSibling) {
        mainContent.insertBefore(successDiv, contentHeader.nextSibling);
    } else {
        mainContent.insertBefore(successDiv, mainContent.firstChild);
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (successDiv.parentNode) {
            closeFlashMessage('dynamic-success-message');
        }
    }, 5000);
}

// Custom image editing menu
function showCustomImageMenu(e, img) {
    // Remove existing menu
    const existingMenu = document.querySelector('.custom-image-menu');
    if (existingMenu) {
        existingMenu.remove();
    }
    
    // Create custom menu
    const menu = document.createElement('div');
    menu.className = 'custom-image-menu';
    menu.style.cssText = `
        position: fixed;
        top: ${e.clientY}px;
        left: ${e.clientX}px;
        background: white;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 10000;
        padding: 5px 0;
        min-width: 150px;
    `;
    
    // Add menu items
    const items = [
        { text: 'Resize Image', action: () => resizeImage(img) },
        { text: 'Align Left', action: () => alignImage(img, 'left') },
        { text: 'Align Center', action: () => alignImage(img, 'center') },
        { text: 'Align Right', action: () => alignImage(img, 'right') },
        { text: 'Remove Image', action: () => removeImage(img) }
    ];
    
    items.forEach(item => {
        const menuItem = document.createElement('div');
        menuItem.textContent = item.text;
        menuItem.style.cssText = `
            padding: 8px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        `;
        menuItem.addEventListener('click', () => {
            item.action();
            menu.remove();
        });
        menuItem.addEventListener('mouseenter', () => {
            menuItem.style.backgroundColor = '#f5f5f5';
        });
        menuItem.addEventListener('mouseleave', () => {
            menuItem.style.backgroundColor = 'white';
        });
        menu.appendChild(menuItem);
    });
    
    document.body.appendChild(menu);
    
    // Close menu when clicking outside
    setTimeout(() => {
        document.addEventListener('click', function closeMenu() {
            menu.remove();
            document.removeEventListener('click', closeMenu);
        });
    }, 100);
}

// Image editing functions
function resizeImage(img) {
    const newWidth = prompt('Enter new width (in pixels):', img.width);
    if (newWidth && !isNaN(newWidth)) {
        img.style.width = newWidth + 'px';
        img.style.height = 'auto';
        autoSave();
    }
}

function alignImage(img, alignment) {
    img.style.display = 'block';
    img.style.margin = '0 auto';
    if (alignment === 'left') {
        img.style.float = 'left';
        img.style.margin = '0 10px 10px 0';
    } else if (alignment === 'right') {
        img.style.float = 'right';
        img.style.margin = '0 0 10px 10px';
    } else {
        img.style.float = 'none';
        img.style.margin = '0 auto';
    }
    autoSave();
}

function removeImage(img) {
    if (confirm('Are you sure you want to remove this image?')) {
        img.remove();
        autoSave();
    }
}

// Flash message functions
function closeFlashMessage(messageId) {
    const message = document.getElementById(messageId);
    if (message) {
        message.style.opacity = '0';
        message.style.transform = 'translateX(100%)';
        setTimeout(() => {
            message.remove();
            // Clean up URL parameters
            const url = new URL(window.location);
            url.searchParams.delete('uploaded');
            url.searchParams.delete('updated');
            url.searchParams.delete('deleted');
            url.searchParams.delete('error');
            window.history.replaceState({}, '', url);
        }, 300);
    }
}

// Auto-dismiss flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('.flash');
    flashMessages.forEach(message => {
        setTimeout(() => {
            if (message && message.parentNode) {
                closeFlashMessage(message.id);
            }
        }, 5000);
    });
    
    // Initialize comprehension edit if we're in edit mode
    if (typeof initializeComprehensionEdit === 'function') {
        initializeComprehensionEdit();
    }
});

// Toggle content visibility
function toggleContent(materialId, el) {
    const content = document.getElementById('content-' + materialId);
    const row = document.getElementById('row-content-' + materialId);
    const button = (el && el.closest) ? el.closest('.toggle-content-btn') : (event && event.target && event.target.closest ? event.target.closest('.toggle-content-btn') : null);
    // Support both text button and icon-only button
    const toggleText = button.querySelector('.toggle-text');
    const toggleIcon = button.querySelector('.toggle-icon');
    
    const isHidden = row ? (row.style.display === 'none' || row.style.display === '') : (content.style.display === 'none' || content.style.display === '');
    if (isHidden) {
        // Show content
        if (row) { row.style.display = 'table-row'; } else { content.style.display = 'block'; }
        if (toggleText) toggleText.textContent = 'Hide Content';
        button.classList.add('expanded');
        
        // Check if this is a PDF file and toggle between viewer and download link
        const pdfViewer = document.getElementById('pdf-viewer-' + materialId);
        const downloadLink = document.getElementById('download-link-' + materialId);
        
        if (pdfViewer && downloadLink) {
            // This is a PDF file - show viewer, hide download link
            pdfViewer.style.display = 'block';
            downloadLink.style.display = 'none';
        }
        
        // Smooth slide down animation
        content.style.opacity = '0';
        content.style.transform = 'translateY(-10px)';
        content.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        
        setTimeout(() => {
            content.style.opacity = '1';
            content.style.transform = 'translateY(0)';
        }, 10);
    } else {
        // Hide content
        content.style.opacity = '0';
        content.style.transform = 'translateY(-10px)';
        
        // For PDF files, hide viewer and show download link
        const pdfViewer = document.getElementById('pdf-viewer-' + materialId);
        const downloadLink = document.getElementById('download-link-' + materialId);
        
        if (pdfViewer && downloadLink) {
            pdfViewer.style.display = 'none';
            downloadLink.style.display = 'block';
        }
        
        setTimeout(() => {
            if (row) { row.style.display = 'none'; } else { content.style.display = 'none'; }
            if (toggleText) toggleText.textContent = 'View Content';
            button.classList.remove('expanded');
        }, 300);
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Only apply shortcuts if we're not in comprehension edit mode
    if (!document.getElementById('comprehensionEditForm')) {
        // Ctrl+S to save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            saveDocument();
        }
        
        // Ctrl+Z for undo
        if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
            e.preventDefault();
            if (editorInstance && editorInstance.undoManager && typeof editorInstance.undoManager.undo === 'function') {
                try {
                    editorInstance.undoManager.undo();
                    console.log('Undo executed via keyboard shortcut');
                } catch (error) {
                    console.error('Error executing undo:', error);
                }
            }
        }
        
        // Ctrl+Y or Ctrl+Shift+Z for redo
        if ((e.ctrlKey && e.key === 'y') || (e.ctrlKey && e.key === 'z' && e.shiftKey)) {
            e.preventDefault();
            if (editorInstance && editorInstance.undoManager && typeof editorInstance.undoManager.redo === 'function') {
                try {
                    editorInstance.undoManager.redo();
                    console.log('Redo executed via keyboard shortcut');
                } catch (error) {
                    console.error('Error executing redo:', error);
                }
            }
        }
    }
    
    // Ctrl+B for bold
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        formatText('bold');
    }
    
    // Ctrl+I for italic
    if (e.ctrlKey && e.key === 'i') {
        e.preventDefault();
        formatText('italic');
    }
    
    // Ctrl+U for underline
    if (e.ctrlKey && e.key === 'u') {
        e.preventDefault();
        formatText('underline');
    }
    
    // Ctrl+Shift+N for clear formatting
    if (e.ctrlKey && e.shiftKey && e.key === 'N') {
        e.preventDefault();
        if (editorInstance && typeof editorInstance.execCommand === 'function') {
            try {
                editorInstance.execCommand('RemoveFormat');
                console.log('Clear formatting executed via keyboard shortcut');
            } catch (error) {
                console.error('Error executing clear formatting:', error);
            }
        }
    }
});

// Update toolbar state on selection change
document.addEventListener('selectionchange', function() {
    // Only update toolbar if we're not in comprehension edit mode
    if (!document.getElementById('comprehensionEditForm')) {
        updateToolbarState();
    }
});

// Image Editor functionality (TinyMCE integrated)
let currentEditingImage = null;

// Image Editor functionality (TinyMCE integrated)
function openImageEditor(imgElement) {
    // TinyMCE handles image editing natively
    console.log('Image editing handled by TinyMCE');
}

// TinyMCE handles image loading natively

// TinyMCE handles toolbar updates natively

// TinyMCE handles all image editing natively

// Enhanced image handling with Google Docs-style features

function makeImageDraggable(img) {
    // Add click listener for selection
    img.addEventListener('click', function(e) {
        e.stopPropagation();
        selectImage(this);
    });
    
    // TinyMCE handles image context menu natively
    
    // Add double-click for editor
    img.addEventListener('dblclick', function(e) {
        e.preventDefault();
        openImageEditor(this);
    });
    
    // Add hover effects
    img.addEventListener('mouseenter', function() {
        if (!this.classList.contains('selected')) {
            this.style.transform = 'scale(1.02)';
        }
    });
    
    img.addEventListener('mouseleave', function() {
        if (!this.classList.contains('selected')) {
            this.style.transform = 'scale(1)';
        }
    });
}

function selectImage(img) {
    // Remove previous selection
    document.querySelectorAll('.docs-editor img.selected').forEach(selected => {
        selected.classList.remove('selected');
    });
    
    // Select current image
    img.classList.add('selected');
    selectedImage = img;
    
    // Show image controls
    showImageControls();
    updateImageToolbarState(img);
}

function showImageControls() {
    const imageControls = document.querySelector('.image-controls');
    if (imageControls) {
        imageControls.style.display = 'flex';
    }
}

function hideImageControls() {
    const imageControls = document.querySelector('.image-controls');
    if (imageControls) {
        imageControls.style.display = 'none';
    }
}

function updateImageToolbarState(img) {
    const wrapType = img.dataset.wrap || 'inline';
    document.querySelectorAll('.image-controls .toolbar-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const activeBtn = document.querySelector(`[onclick*="${wrapType}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
}

// Old image editing functions removed - using TinyMCE native features

// Old context menu functions removed - using TinyMCE native features

// Global click listener to deselect images
document.addEventListener('click', function(e) {
    // Only handle image deselection if we're not in comprehension edit mode
    if (!document.getElementById('comprehensionEditForm')) {
        if (!e.target.closest('.docs-editor img') && !e.target.closest('.image-context-menu')) {
            document.querySelectorAll('.docs-editor img.selected').forEach(img => {
                img.classList.remove('selected');
            });
            selectedImage = null;
            hideImageControls();
            // hideContextMenu function removed - using TinyMCE native features
        }
    }
});

// Initialize image editor when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize image editor if we're not in comprehension edit mode
    if (!document.getElementById('comprehensionEditForm')) {
        // TinyMCE handles image editing natively
        
        // Make all existing images draggable
    const existingImages = document.querySelectorAll('.docs-editor img');
    existingImages.forEach(img => {
        makeImageDraggable(img);
    });
    
    // Watch for new images added to the editor
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    if (node.tagName === 'IMG') {
                        makeImageDraggable(node);
                    }
                    // Check for images in added nodes
                    const images = node.querySelectorAll && node.querySelectorAll('img');
                    if (images) {
                        images.forEach(img => makeImageDraggable(img));
                    }
                }
            });
        });
    });
    
        const editor = document.querySelector('.docs-editor');
        if (editor) {
            observer.observe(editor, {
                childList: true,
                subtree: true
            });
        }
    }
    
    // Initialize comprehension question edit functionality only if in comprehension edit mode
    if (document.getElementById('comprehensionEditForm')) {
        initializeComprehensionEdit();
    }
});

// Comprehension Question Edit Functions
let questionCount = 0;

function initializeComprehensionEdit() {
    // Set initial question count based on existing questions
    const existingQuestions = document.querySelectorAll('.question-item');
    questionCount = existingQuestions.length;
    
    // Show options for existing questions
    existingQuestions.forEach((questionItem, index) => {
        const questionTypeSelect = questionItem.querySelector('.question-type-select');
        if (questionTypeSelect && questionTypeSelect.value) {
            // Trigger the change event to show options for existing questions
            handleQuestionTypeChange(questionTypeSelect, index + 1);
            
            // For matching questions, initialize the matches section
            if (questionTypeSelect.value === 'matching') {
                setTimeout(() => {
                    updateMatchingMatches(index + 1);
                }, 100);
            }
        }
    });
    
    // Initialize section handling
    initializeSectionHandling();
    
    // Initialize matching matches for existing matching questions
    setTimeout(() => {
        const existingMatchingQuestions = document.querySelectorAll('.question-item');
        existingMatchingQuestions.forEach((questionItem, index) => {
            const questionTypeSelect = questionItem.querySelector('.question-type-select');
            if (questionTypeSelect && questionTypeSelect.value === 'matching') {
                updateMatchingMatches(index + 1);
            }
        });
    }, 500);
}

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
            <textarea class="question-text" placeholder="Enter your question here..." name="questions[${questionCount - 1}][question_text]" required></textarea>
        </div>
        
        <div class="form-group">
            <label>Points:</label>
            <input type="number" class="question-points" name="questions[${questionCount - 1}][points]" value="1" min="1" required>
        </div>
        
        <input type="hidden" name="questions[${questionCount - 1}][id]" value="0">
        <input type="hidden" name="questions[${questionCount - 1}][order_index]" value="${questionCount}">
        
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

function handleQuestionTypeChange(selectElement, questionNum) {
    const questionType = selectElement.value;
    const optionsContainer = document.getElementById(`question-${questionNum}-options`);
    
    // Check if this is an existing question with data (don't overwrite existing content)
    const hasExistingContent = optionsContainer.innerHTML.trim() !== '';
    
    if (questionType === 'mcq') {
        if (!hasExistingContent) {
            optionsContainer.innerHTML = `
                <div class="options-container">
                    <label>Answer Choices:</label>
                    <div class="option-item">
                        <input type="text" class="option-input" name="questions[${questionNum - 1}][choices][0]" placeholder="Option A">
                        <input type="radio" name="questions[${questionNum - 1}][answer_key]" value="0">
                        <label>Correct</label>
                    </div>
                    <div class="option-item">
                        <input type="text" class="option-input" name="questions[${questionNum - 1}][choices][1]" placeholder="Option B">
                        <input type="radio" name="questions[${questionNum - 1}][answer_key]" value="1">
                        <label>Correct</label>
                    </div>
                    <div class="option-item">
                        <input type="text" class="option-input" name="questions[${questionNum - 1}][choices][2]" placeholder="Option C">
                        <input type="radio" name="questions[${questionNum - 1}][answer_key]" value="2">
                        <label>Correct</label>
                    </div>
                    <div class="option-item">
                        <input type="text" class="option-input" name="questions[${questionNum - 1}][choices][3]" placeholder="Option D">
                        <input type="radio" name="questions[${questionNum - 1}][answer_key]" value="3">
                        <label>Correct</label>
                    </div>
                </div>
            `;
        }
        optionsContainer.style.display = 'block';
    } else if (questionType === 'matching') {
        if (!hasExistingContent) {
            optionsContainer.innerHTML = `
                <div class="form-group">
                    <label><strong>Left Items (Rows):</strong></label>
                    <div id="matching-rows-${questionNum}">
                        <div class="input-group">
                            <label for="left_item_1_${questionNum}">Row 1:</label>
                            <input type="text" id="left_item_1_${questionNum}" name="questions[${questionNum - 1}][left_items][]" placeholder="Row 1" oninput="updateMatchingMatches(${questionNum})" required>
                            <button type="button" class="remove-option" onclick="removeMatchingRow(${questionNum}, 0)">×</button>
                        </div>
                        <div class="input-group">
                            <label for="left_item_2_${questionNum}">Row 2:</label>
                            <input type="text" id="left_item_2_${questionNum}" name="questions[${questionNum - 1}][left_items][]" placeholder="Row 2" oninput="updateMatchingMatches(${questionNum})" required>
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
                            <input type="text" id="right_item_1_${questionNum}" name="questions[${questionNum - 1}][right_items][]" placeholder="Column 1" oninput="updateMatchingMatches(${questionNum})" required>
                            <button type="button" class="remove-option" onclick="removeMatchingColumn(${questionNum}, 0)">×</button>
                        </div>
                        <div class="input-group">
                            <label for="right_item_2_${questionNum}">Column 2:</label>
                            <input type="text" id="right_item_2_${questionNum}" name="questions[${questionNum - 1}][right_items][]" placeholder="Column 2" oninput="updateMatchingMatches(${questionNum})" required>
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
        }
        optionsContainer.style.display = 'block';
        
        // Update matches after a short delay to ensure DOM is updated
        setTimeout(() => {
            updateMatchingMatches(questionNum);
        }, 200);
    } else if (questionType === 'essay') {
        if (!hasExistingContent) {
            optionsContainer.innerHTML = `
                <div class="form-group">
                    <label>Essay Question:</label>
                    <p style="color: #666; font-style: italic;">No additional options needed for essay questions.</p>
                </div>
            `;
        }
        optionsContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
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
    input.name = `questions[${questionNum - 1}][left_items][]`;
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
            input.name = `questions[${questionNum - 1}][left_items][]`;
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
            input.name = `questions[${questionNum - 1}][right_items][]`;
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
    input.name = `questions[${questionNum - 1}][right_items][]`;
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
            input.name = `questions[${questionNum - 1}][right_items][]`;
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
    const rows = document.querySelectorAll(`input[name="questions[${questionNum - 1}][left_items][]"]`);
    const columns = document.querySelectorAll(`input[name="questions[${questionNum - 1}][right_items][]"]`);
    const container = document.getElementById(`matching-matches-${questionNum}`);
    const pointsField = document.querySelector(`#question-${questionNum} .question-points`);
    
    if (!container) {
        return;
    }
    
    // Get the existing correct matches from the hidden input or data attribute
    const questionElement = document.getElementById(`question-${questionNum}`);
    let existingMatches = [];
    
    // Try to get matches from the hidden input
    const matchesInput = document.getElementById(`matches-data-${questionNum}`);
    if (matchesInput && matchesInput.value) {
        try {
            existingMatches = JSON.parse(matchesInput.value);
        } catch (e) {
            // If not JSON, try to parse as comma-separated values
            existingMatches = matchesInput.value.split(',').map(m => parseInt(m.trim())).filter(m => !isNaN(m));
        }
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
        select.name = `questions[${questionNum - 1}][matches][]`;
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
        
        // Restore selection: first try existing matches, then current selections
        let selectedValue = '';
        if (existingMatches[index] !== undefined && existingMatches[index] < columns.length) {
            selectedValue = existingMatches[index];
        } else if (currentSelections[index] && currentSelections[index] < columns.length) {
            selectedValue = currentSelections[index];
        }
        
        if (selectedValue !== '') {
            select.value = selectedValue;
        }
        
        matchRow.appendChild(label);
        matchRow.appendChild(select);
        container.appendChild(matchRow);
    });
}

function updateQuestionNumbers() {
    const questionItems = document.querySelectorAll('.question-item');
    questionItems.forEach((item, index) => {
        const numberSpan = item.querySelector('.question-number');
        if (numberSpan) {
            numberSpan.textContent = `Question ${index + 1}`;
        }
    });
}

function initializeSectionHandling() {
    const sectionCheckboxes = document.querySelectorAll('.sec-box');
    const hiddenInputsContainer = document.getElementById('sectionHiddenInputs');
    
    sectionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSectionHiddenInputs);
    });
    
    // Initialize hidden inputs
    updateSectionHiddenInputs();
}

function updateSectionHiddenInputs() {
    const checkedBoxes = document.querySelectorAll('.sec-box:checked');
    const hiddenInputsContainer = document.getElementById('sectionHiddenInputs');
    
    // Check if the container exists before trying to modify it
    if (!hiddenInputsContainer) {
        return;
    }
    
    // Clear existing hidden inputs
    hiddenInputsContainer.innerHTML = '';
    
    // Add hidden inputs for checked sections
    checkedBoxes.forEach(checkbox => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'section_ids[]';
        hiddenInput.value = checkbox.value;
        hiddenInputsContainer.appendChild(hiddenInput);
    });
}

function showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}
</script>

<!-- TinyMCE handles image editing natively -->

<?php
render_teacher_footer();
?>

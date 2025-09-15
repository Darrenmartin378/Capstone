<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/includes/notification_helper.php';

$edit_mode = false;
$edit_material = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = $_SESSION['teacher_id'] ?? 0;

    if ($_POST['action'] === 'add_material') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $theme_settings = json_encode(['bg_color' => '#ffffff']);

        if ($title && $content && $teacher_id) {
            $stmt = $conn->prepare("INSERT INTO reading_materials (teacher_id, title, content, theme_settings, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $teacher_id, $title, $content, $theme_settings);
            $stmt->execute();
            $materialId = $conn->insert_id;
            $stmt->close();
            
            // Create notification for all students in teacher's sections
            createNotificationForAllStudents(
                $conn, 
                $teacher_id, 
                'material', 
                'New Reading Material Available', 
                "Your teacher has uploaded a new reading material: \"$title\". Check the Materials section to read it.",
                $materialId
            );
            
            // Redirect to prevent form resubmission
            header('Location: teacher_content.php?uploaded=1');
            exit;
        } else {
            // Redirect with error
            header('Location: teacher_content.php?error=1');
            exit;
        }
    } elseif ($_POST['action'] === 'edit_material') {
        $id = (int)($_POST['id'] ?? 0);
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $theme_settings = json_encode(['bg_color' => '#ffffff']);

        if ($id && $title && $content && $teacher_id) {
            $stmt = $conn->prepare("UPDATE reading_materials SET title = ?, content = ?, theme_settings = ?, updated_at = NOW() WHERE id = ? AND teacher_id = ?");
            $stmt->bind_param("sssii", $title, $content, $theme_settings, $id, $teacher_id);
            $stmt->execute();
            $stmt->close();
            // Redirect to prevent form resubmission
            header('Location: teacher_content.php?updated=1');
            exit;
        } else {
            // Redirect with error
            header('Location: teacher_content.php?error=1');
            exit;
        }
    } elseif ($_POST['action'] === 'delete_material') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM reading_materials WHERE id = ? AND teacher_id = ?");
            $stmt->bind_param("ii", $id, $teacher_id);
            $stmt->execute();
            $stmt->close();
            // Redirect to prevent form resubmission
            header('Location: teacher_content.php?deleted=1');
            exit;
        }
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

// Get all materials for this teacher
$materials = [];
$stmt = $conn->prepare("SELECT * FROM reading_materials WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['teacher_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $materials[] = $row;
}
$stmt->close();

// Render header after all POST handling is complete
require_once __DIR__ . '/includes/teacher_layout.php';
render_teacher_header('teacher_content.php', $_SESSION['teacher_name'] ?? 'Teacher');
?>

<div class="main-content">
    <div class="content-header">
        <h1>Content Management</h1>
        <p>Upload and manage reading materials for your students</p>
    </div>

    <?php if (isset($_GET['uploaded']) && $_GET['uploaded'] === '1'): ?>
        <div class="flash flash-success" id="uploaded-message">
            ✅ Material uploaded successfully!
            <button type="button" class="flash-close" onclick="closeFlashMessage('uploaded-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
        <div class="flash flash-success" id="updated-message">
            ✅ Material updated successfully!
            <button type="button" class="flash-close" onclick="closeFlashMessage('updated-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
        <div class="flash flash-success" id="deleted-message">
            ✅ Material deleted successfully!
            <button type="button" class="flash-close" onclick="closeFlashMessage('deleted-message')">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] === '1'): ?>
        <div class="flash flash-error" id="error-message">
            ❌ Please fill in all fields.
            <button type="button" class="flash-close" onclick="closeFlashMessage('error-message')">&times;</button>
        </div>
    <?php endif; ?>

    <div class="content-section">
        <div class="section-header">
            <h2><?php echo $edit_mode ? 'Edit Material' : 'Upload New Material'; ?></h2>
        </div>

        <form id="materialForm" method="POST" class="material-form">
            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit_material' : 'add_material'; ?>">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo $edit_material['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?php echo $edit_mode ? htmlspecialchars($edit_material['title']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="content">Content</label>
                <textarea id="summernote-editor" name="content"><?php echo $edit_mode ? htmlspecialchars($edit_material['content']) : ''; ?></textarea>
            </div>


            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_mode ? 'Update Material' : 'Upload Material'; ?>
                </button>
                <?php if ($edit_mode): ?>
                    <a href="teacher_content.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="content-section">
        <div class="section-header">
            <h2>Your Materials</h2>
        </div>

        <div class="materials-grid">
            <?php if (empty($materials)): ?>
                <div class="no-materials">
                    <p>No materials uploaded yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($materials as $material): ?>
                    <div class="material-card">
                        <div class="material-header">
                            <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                            <div class="material-actions">
                                <button type="button" class="btn btn-sm btn-secondary toggle-content-btn" onclick="toggleContent(<?php echo $material['id']; ?>)">
                                    <span class="toggle-text">View Content</span>
                                    <span class="toggle-icon">▼</span>
                                </button>
                                <a href="?edit=<?php echo $material['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this material?')">
                                    <input type="hidden" name="action" value="delete_material">
                                    <input type="hidden" name="id" value="<?php echo $material['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                        <div class="material-content" id="content-<?php echo $material['id']; ?>" style="display: none;">
                            <?php echo $material['content']; ?>
                        </div>
                        <div class="material-footer">
                            <small>Created: <?php echo date('M j, Y', strtotime($material['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* Gmail-style Content Styling */
.content {
    background: var(--bg-secondary);
    min-height: calc(100vh - 64px);
    padding: 24px;
}

.main-content {
    max-width: 1200px;
    margin: 0 auto;
}

.content-header {
    margin-bottom: 32px;
}

.content-header h1 {
    font-size: 32px;
    font-weight: 400;
    color: var(--text-primary);
    margin: 0 0 8px 0;
    line-height: 1.2;
}

.content-header p {
    color: var(--text-secondary);
    font-size: 14px;
    margin: 0;
}

.content-section {
    background: var(--bg-primary);
    border-radius: 8px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    overflow: hidden;
}

.section-header {
    padding: 24px 24px 16px 24px;
    border-bottom: 1px solid var(--border);
}

.section-header h2 {
    font-size: 20px;
    font-weight: 500;
    color: var(--text-primary);
    margin: 0;
}

.material-form {
    padding: 24px;
}

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 8px;
    font-size: 14px;
}

.form-group input[type="text"],
.form-group input[type="color"] {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    background: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.2s ease;
}

.form-group input[type="text"]:focus,
.form-group input[type="color"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.1);
}

.form-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
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

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    box-shadow: var(--shadow-md);
}

.btn-secondary {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--bg-tertiary);
    box-shadow: var(--shadow-sm);
}

.btn-danger {
    background: var(--error);
    color: white;
}

.btn-danger:hover {
    background: #d33b2c;
    box-shadow: var(--shadow-md);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
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

.materials-grid {
    padding: 24px;
}

.no-materials {
    text-align: center;
    padding: 48px 24px;
    color: var(--text-secondary);
}

.material-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 16px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.material-card:hover {
    box-shadow: var(--shadow-md);
}

.material-header {
    padding: 20px 24px 16px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.material-header h3 {
    font-size: 18px;
    font-weight: 500;
    color: var(--text-primary);
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
}

.toggle-content-btn:hover {
    transform: translateY(-1px);
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
    color: var(--text-primary);
    line-height: 1.6;
    border-top: 1px solid var(--border);
    background: var(--bg-secondary);
    transition: all 0.3s ease;
}

.material-content.collapsed {
    display: none;
}

.material-footer {
    padding: 16px 24px;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border);
}

.material-footer small {
    color: var(--text-muted);
    font-size: 12px;
}

/* Summernote Customization */
.note-editor {
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    overflow: hidden;
}

.note-toolbar {
    background: var(--bg-primary) !important;
    border-bottom: 1px solid var(--border) !important;
    padding: 8px 12px !important;
}

.note-editing-area {
    background: var(--bg-primary) !important;
}

.note-editable {
    background: var(--bg-primary) !important;
    color: var(--text-primary) !important;
    padding: 16px !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    line-height: 1.6 !important;
}

.note-editable:focus {
    outline: none !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    .content {
        padding: 16px;
    }
    
    .content-header h1 {
        font-size: 24px;
    }
    
    .material-form {
        padding: 16px;
    }
    
    .materials-grid {
        padding: 16px;
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
</style>

<!-- jQuery, jQuery UI, Bootstrap JS, and Summernote JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.js"></script>

<script>
$(document).ready(function() {
    $('#summernote-editor').summernote({
        height: 400,
        placeholder: 'Write your content here...',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        styleTags: ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
        fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
        fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '36', '48'],
        onImageUpload: function(files) {
            uploadImage(files[0]);
        },
        callbacks: {
            onInit: function() {
                // Make images draggable after editor initialization
                makeImagesDraggable();
            },
            onChange: function(contents, $editable) {
                // Make new images draggable when content changes
                setTimeout(function() {
                    makeImagesDraggable();
                }, 100);
            }
        }
    });
});

function makeImagesDraggable() {
    // Remove existing draggable functionality to avoid conflicts
    $('.note-editable img').off('mousedown.drag');
    
    // Add draggable functionality to all images
    $('.note-editable img').each(function() {
        const $img = $(this);
        
        // Add cursor style
        $img.css('cursor', 'move');
        
        // Make image draggable
        $img.draggable({
            containment: '.note-editable',
            helper: 'clone',
            opacity: 0.7,
            start: function(event, ui) {
                // Store original position
                $img.data('original-position', {
                    top: $img.css('top'),
                    left: $img.css('left'),
                    position: $img.css('position')
                });
            },
            stop: function(event, ui) {
                // Update image position
                const newTop = ui.position.top;
                const newLeft = ui.position.left;
                
                $img.css({
                    'position': 'absolute',
                    'top': newTop + 'px',
                    'left': newLeft + 'px',
                    'z-index': '10'
                });
                
                // Remove draggable to prevent conflicts
                $img.draggable('destroy');
                
                // Re-add draggable functionality
                setTimeout(function() {
                    makeImagesDraggable();
                }, 100);
            }
        });
    });
}

function uploadImage(file) {
    var formData = new FormData();
    formData.append('file', file);
    
    $.ajax({
        url: 'upload_image.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            $('#summernote-editor').summernote('insertImage', response);
        },
        error: function() {
            alert('Error uploading image');
        }
    });
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
});

// Toggle content visibility
function toggleContent(materialId) {
    const content = document.getElementById('content-' + materialId);
    const button = event.target.closest('.toggle-content-btn');
    const toggleText = button.querySelector('.toggle-text');
    const toggleIcon = button.querySelector('.toggle-icon');
    
    if (content.style.display === 'none') {
        // Show content
        content.style.display = 'block';
        toggleText.textContent = 'Hide Content';
        button.classList.add('expanded');
        
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
        
        setTimeout(() => {
            content.style.display = 'none';
            toggleText.textContent = 'View Content';
            button.classList.remove('expanded');
        }, 300);
    }
}

// Summernote form submission handler
document.getElementById('materialForm').addEventListener('submit', function(e) {
    // Get content from Summernote
    var content = $('#summernote-editor').summernote('code');
    
    // Update the textarea with Summernote content
    var textarea = document.createElement('textarea');
    textarea.name = 'content';
    textarea.style.display = 'none';
    textarea.value = content;
    
    // Remove any existing content textarea
    var existingContent = document.querySelector('textarea[name="content"]');
    if (existingContent) {
        existingContent.remove();
    }
    
    // Add the new content textarea
    this.appendChild(textarea);
});
</script>

<?php
render_teacher_footer();
?>

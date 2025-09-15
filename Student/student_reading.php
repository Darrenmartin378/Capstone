<?php
require_once 'includes/student_init.php';

$pageTitle = 'Reading Lists';

// Get reading materials as reading lists
$readingLists = [];
$listsQuery = $conn->query("
    SELECT rm.id, rm.title, rm.content, rm.created_at,
           t.name as teacher_name
    FROM reading_materials rm
    JOIN teachers t ON rm.teacher_id = t.id
    ORDER BY rm.created_at DESC
");

if ($listsQuery && $listsQuery->num_rows > 0) {
    while ($row = $listsQuery->fetch_assoc()) {
        $readingLists[] = $row;
    }
}

// Get student's reading progress (simulated)
$readingProgress = [];
foreach ($readingLists as $list) {
    $readingProgress[$list['id']] = [
        'books_read' => 0,
        'total_books' => 1,
        'completion_percentage' => 0
    ];
}

$content = '
<div class="reading-container">
    <div class="reading-header">
        <h2>📚 Reading Lists</h2>
        <p>Discover amazing books and track your reading journey!</p>
    </div>

    <div class="reading-lists">';

if (!empty($readingLists)) {
    foreach ($readingLists as $list) {
        $progress = isset($readingProgress[$list['id']]) ? $readingProgress[$list['id']] : null;
        $booksRead = $progress ? $progress['books_read'] : 0;
        $totalBooks = $progress ? $progress['total_books'] : 1;
        $completionPercentage = $progress ? $progress['completion_percentage'] : 0;
        
        $content .= '
        <div class="reading-list-card">
            <div class="list-header">
                <div class="list-icon">📖</div>
                <div class="list-info">
                    <h3>' . h($list['title']) . '</h3>
                    <p class="list-description">' . h(substr($list['content'], 0, 200)) . '...</p>
                    <div class="list-meta">
                        <span class="list-category">Reading Material</span>
                        <span class="list-grade">By ' . h($list['teacher_name']) . '</span>
                    </div>
                </div>
            </div>
            
            <div class="reading-progress">
                <div class="progress-header">
                    <span class="progress-label">Reading Progress</span>
                    <span class="progress-percentage">' . $completionPercentage . '%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ' . $completionPercentage . '%"></div>
                </div>
                <div class="progress-stats">
                    <span>' . $booksRead . ' of ' . $totalBooks . ' materials read</span>
                </div>
            </div>
            
            <div class="list-actions">
                <a href="student_materials.php?id=' . $list['id'] . '" class="btn-view-list">
                    📋 Read Material
                </a>
                <a href="student_materials.php?id=' . $list['id'] . '" class="btn-track-reading">
                    📝 Start Reading
                </a>
            </div>
        </div>';
    }
} else {
    $content .= '
    <div class="no-reading-lists">
        <div class="no-lists-icon">📚</div>
        <h4>No reading materials available</h4>
        <p>Check back later for reading materials!</p>
    </div>';
}

$content .= '
    </div>
</div>

<style>
.reading-container {
    max-width: 1200px;
    margin: 0 auto;
}

.reading-header {
    text-align: center;
    margin-bottom: 2rem;
}

.reading-header h2 {
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.reading-lists {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.reading-list-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary);
}

.reading-list-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.list-header {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.list-icon {
    font-size: 3rem;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 16px;
    flex-shrink: 0;
}

.list-info {
    flex: 1;
}

.list-info h3 {
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 1.5rem;
}

.list-description {
    color: var(--text-secondary);
    margin-bottom: 1rem;
    line-height: 1.6;
}

.list-meta {
    display: flex;
    gap: 1rem;
}

.list-category {
    background: var(--accent);
    color: white;
    padding: 6px 16px;
    border-radius: 16px;
    font-size: 0.9rem;
    font-weight: 600;
}

.list-grade {
    background: var(--teal);
    color: white;
    padding: 6px 16px;
    border-radius: 16px;
    font-size: 0.9rem;
    font-weight: 600;
}

.reading-progress {
    margin-bottom: 1.5rem;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.progress-label {
    font-weight: 600;
    color: var(--text-primary);
}

.progress-percentage {
    font-weight: 700;
    color: var(--primary);
    font-size: 1.1rem;
}

.progress-bar {
    width: 100%;
    height: 12px;
    background: #E0E0E0;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 6px;
    transition: width 0.3s ease;
}

.progress-stats {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.list-actions {
    display: flex;
    gap: 1rem;
}

.btn-view-list, .btn-track-reading {
    flex: 1;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s ease;
}

.btn-view-list {
    background: var(--primary);
    color: white;
}

.btn-view-list:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.btn-track-reading {
    background: var(--secondary);
    color: white;
}

.btn-track-reading:hover {
    background: #e68900;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.no-reading-lists {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.no-lists-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .list-header {
        flex-direction: column;
        text-align: center;
    }
    
    .list-icon {
        align-self: center;
    }
    
    .list-meta {
        justify-content: center;
    }
    
    .list-actions {
        flex-direction: column;
    }
}
</style>';

include 'includes/student_layout.php';
?>

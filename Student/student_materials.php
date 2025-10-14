<?php
require_once 'includes/student_init.php';

$pageTitle = 'Materials';
$materials = $conn->query("SELECT * FROM reading_materials ORDER BY created_at DESC");

ob_start();
?>
<style>
    /* Header similar to questions page */
    .materials-header { margin-bottom: 12px; }
    .materials-header h1 { color:#f1f5f9; font-weight:900; margin:0 0 6px 0; }
    .materials-header p { margin:0; color: rgba(241,245,249,.85); }

    /* Grid like question cards */
    .materials-grid { 
        display:grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap:24px; 
        justify-content:start; 
        justify-items:stretch; 
        margin-bottom:24px; 
    }
    @media (max-width: 900px) { .materials-grid { grid-template-columns: 1fr; } }

    .material-card {
        position: relative;
        background: rgba(15, 23, 42, 0.85);
        padding: 22px;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(139, 92, 246, 0.2);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        border: 1px solid rgba(139, 92, 246, 0.3);
        overflow: hidden;
        backdrop-filter: blur(12px);
    }
    .material-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(139, 92, 246, 0.4), 0 0 20px rgba(139, 92, 246, 0.2);
        border-color: rgba(139, 92, 246, 0.5);
    }
    .material-title { font-size: 20px; font-weight: 800; color:#f1f5f9; margin:0 0 8px 0; }
    .material-meta { font-size:13px; color:#9aa4b2; margin-bottom:12px; }
    .material-actions { margin-top: 14px; }

    .muted { color: rgba(241, 245, 249, 0.6); font-size: 13px; }
    .btn {
        border: 1px solid rgba(139, 92, 246, 0.5);
        padding: 10px 14px;
        border-radius: 12px;
        cursor: pointer;
        color: #fff;
        font-weight: 700;
        box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
        text-decoration: none;
        backdrop-filter: blur(10px);
        display: inline-block;
    }
    .btn-primary { background: linear-gradient(180deg, var(--primary), #5fb4ff); }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(30,144,255,.25); }
</style>

<div class="materials-header">
    <h1><i class="fas fa-book"></i> Available Materials</h1>
    <p>Browse instructional materials and resources</p>
    </div>
<div class="materials-grid">
    <?php if ($materials && $materials->num_rows > 0): while ($m = $materials->fetch_assoc()): ?>
        <div class="material-card" data-material-id="<?php echo (int)$m['id']; ?>">
            <div class="material-title"><?php echo h($m['title']); ?></div>
            <div class="material-meta">Uploaded: <?php echo h(date('M j, Y g:ia', strtotime($m['created_at'] ?? $m['updated_at']))); ?></div>
            <div class="material-actions">
                <?php
                // Build a web-accessible path for the attachment if present
                $webAttachment = '';
                $attachmentType = trim((string)($m['attachment_type'] ?? ''));
                $attachmentName = trim((string)($m['attachment_name'] ?? ''));
                $attachmentPath = trim((string)($m['attachment_path'] ?? ''));
                if ($attachmentPath !== '') {
                    $relativePath = ltrim($attachmentPath, '/\\');
                    // From Student/ to project root, use ../
                    $candidate1 = '../' . str_replace('\\', '/', $relativePath);
                    // Alternative: if only basename is accessible under uploads
                    $candidate2 = '../uploads/' . basename($relativePath);

                    // Resolve to an existing file on disk to avoid broken links
                    $projectRoot = dirname(__DIR__);
                    $abs1 = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                    $abs2 = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($relativePath);
                    if (file_exists($abs1)) {
                        $webAttachment = $candidate1;
                    } elseif (file_exists($abs2)) {
                        $webAttachment = $candidate2;
                    } else {
                        // Fallback to provided path (browser may still resolve it)
                        $webAttachment = $candidate1;
                    }
                }
                ?>
                <button class="btn btn-primary view-material-btn" 
                        data-title="<?php echo htmlspecialchars($m['title']); ?>"
                        data-content="<?php echo htmlspecialchars($m['content']); ?>"
                        data-theme="<?php echo htmlspecialchars($m['theme_settings']); ?>"
                        data-attachment="<?php echo htmlspecialchars($webAttachment); ?>"
                        data-attachment-type="<?php echo htmlspecialchars($attachmentType); ?>"
                        data-attachment-name="<?php echo htmlspecialchars($attachmentName); ?>">
                    View Full Material
                </button>
                <?php
                // Fetch related comprehension question sets linked to this material
                try {
                    $relSets = [];
                    // Ensure link table exists before querying
                    $chk = $conn->query("SHOW TABLES LIKE 'material_question_links'");
                    if ($chk && $chk->num_rows > 0) {
                        $mid = (int)$m['id'];
                        $studentSectionId = (int)($_SESSION['section_id'] ?? 0);
                        
                        // First try to get the question set for the student's current section
                        if ($studentSectionId > 0) {
                            $st = $conn->prepare("SELECT qs.id, qs.set_title, qs.created_at FROM material_question_links mql JOIN question_sets qs ON qs.id = mql.question_set_id WHERE mql.material_id = ? AND qs.section_id = ? ORDER BY qs.created_at DESC LIMIT 1");
                            if ($st) {
                                $st->bind_param('ii', $mid, $studentSectionId);
                                $st->execute();
                                $relSets = $st->get_result()->fetch_all(MYSQLI_ASSOC);
                            }
                        }
                        
                        // If no set found for student's section, get any set for this material (fallback)
                        if (empty($relSets)) {
                            $st = $conn->prepare("SELECT qs.id, qs.set_title, qs.created_at FROM material_question_links mql JOIN question_sets qs ON qs.id = mql.question_set_id WHERE mql.material_id = ? ORDER BY qs.created_at DESC LIMIT 1");
                            if ($st) {
                                $st->bind_param('i', $mid);
                                $st->execute();
                                $relSets = $st->get_result()->fetch_all(MYSQLI_ASSOC);
                            }
                        }
                    }
                } catch (Throwable $e) { $relSets = []; }
                if (!empty($relSets)):
                ?>
                    <div class="muted" style="margin-top:10px;">Related Comprehension Questions</div>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:6px;">
                        <?php foreach ($relSets as $rs): ?>
                            <a class="btn" style="background: linear-gradient(135deg, #7c3aed, #2563eb); border-color: rgba(139,92,246,.4);" href="clean_question_viewer.php#set-<?php echo (int)$rs['id']; ?>">
                                <i class="fas fa-play"></i> <?php echo htmlspecialchars($rs['set_title']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; else: ?>
        <div class="material-card" style="text-align:center; padding: 40px;">
            <h3 style="margin:0; color:#f1f5f9;">No Materials Available</h3>
        </div>
    <?php endif; ?>
</div>

<!-- PDF Viewer Modal -->
<div id="pdfViewerBackdrop" class="pdf-viewer-backdrop" role="dialog" aria-hidden="true" aria-labelledby="pdfViewerTitle">
    <div class="pdf-viewer" role="document">
        <div class="pdf-toolbar">
            <div class="left">
                <strong id="pdfViewerTitle">Material</strong>
                <span class="pdf-meta" id="pdfViewerMeta" style="margin-left:10px;"></span>
            </div>
            <div class="right">
                <label style="display:flex; align-items:center; gap:6px;">
                    Zoom
                    <select id="pdfZoom" onchange="setPdfZoom(this.value)">
                        <option value="0.6">60%</option>
                        <option value="0.8">80%</option>
                        <option value="1" selected>100%</option>
                        <option value="1.25">125%</option>
                        <option value="1.5">150%</option>
                    </select>
                </label>
                <button type="button" onclick="downloadPdfView()">Download / Print</button>
                <button type="button" onclick="closePdfViewer()">Close</button>
            </div>
        </div>

        <div class="pdf-page-frame">
            <div id="pdfPage" class="pdf-page" style="transform: scale(1);">
                <!-- content injected here -->
            </div>
        </div>
    </div>
</div>

<style>
/* PDF-like viewer */
.pdf-viewer-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(20,24,30,.55);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1200;
    padding: 24px;
}
.pdf-viewer-backdrop.active { display: flex; }

.pdf-viewer {
    background: transparent;
    width: 96vw;              /* make modal nearly full width */
    max-width: 1400px;        /* cap on very wide screens */
    max-height: calc(100% - 48px);
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: stretch;
}

/* toolbar */
.pdf-toolbar {
    display:flex;
    gap:8px;
    align-items:center;
    justify-content:space-between;
}
.pdf-toolbar .left { display:flex; gap:8px; align-items:center; }
.pdf-toolbar .right { display:flex; gap:8px; align-items:center; }

/* "page" container (A4-ish) */
.pdf-page-frame {
    /* Unified reading container */
    background: #ffffff; 
    padding: 24px; 
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,.18);
    display:flex;
    justify-content:center;
    overflow: auto;
    max-height: calc(100vh - 80px);
    scrollbar-width: thin;
    scrollbar-color: #999 #f5f5f5;
}

/* Custom scrollbar for webkit browsers */
.pdf-page-frame::-webkit-scrollbar {
    width: 16px;
}

.pdf-page-frame::-webkit-scrollbar-track {
    background: #ddd;
    border-radius: 8px;
}

.pdf-page-frame::-webkit-scrollbar-thumb {
    background: #999;
    border-radius: 8px;
    border: 2px solid #ddd;
}

.pdf-page-frame::-webkit-scrollbar-thumb:hover {
    background: #666;
}

.pdf-page-frame::-webkit-scrollbar-button {
    display: block;
    height: 16px;
    width: 16px;
    background: #999;
    border: 1px solid #ddd;
}

.pdf-page-frame::-webkit-scrollbar-button:vertical:start:decrement {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23fff' d='M8 4l-4 4h8l-4-4z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 12px;
}

.pdf-page-frame::-webkit-scrollbar-button:vertical:end:increment {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%23fff' d='M8 12l4-4H4l4 4z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 12px;
}
.pdf-page {
    width: 100%;
    min-height: 50vh; 
    background: transparent; /* let frame provide the unified white */
    padding: 0;
    box-sizing: border-box;
    overflow: visible;
    transform-origin: top left;
}

/* responsive: fall back to screen width on small devices */
@media (max-width:900px){
    .pdf-page { width: 100%; padding: 18px; min-height: 400px; }
    .pdf-toolbar { flex-wrap:wrap; gap:6px; }
}

/* small ui buttons */
.pdf-toolbar button, .pdf-toolbar select {
    background:#fff;
    border:1px solid #d7dbe6;
    padding:6px 10px;
    border-radius:8px;
    cursor:pointer;
}
.pdf-meta { font-size:13px; color:#444; }
/* Sizing for embedded PDF viewer */
.pdf-embed { width: 100%; height: calc(100vh - 140px); border: 0; }
/* Remove per-paragraph white boxes coming from source markup */
.pdf-page p,
.pdf-page div,
.pdf-page section,
.pdf-page article {
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
}
/* Pleasant readable paragraph spacing */
.pdf-page p { margin: 0 0 14px 0; }
</style>

<script>
function viewMaterial(title, content, theme, attachmentUrl, attachmentType) {
    const backdrop = document.getElementById('pdfViewerBackdrop');
    const page = document.getElementById('pdfPage');
    const tl = document.getElementById('pdfViewerTitle');
    const meta = document.getElementById('pdfViewerMeta');

    tl.textContent = title || 'Material';
    meta.textContent = theme || '';

    // Apply theme preview if provided
    page.style.backgroundColor = '#ffffff';
    page.style.color = '#000000';

    // Prefer showing the uploaded attachment (e.g., PDF) if available
    if (attachmentUrl) {
        const safeSrc = String(attachmentUrl);
        const iframe = document.createElement('iframe');
        iframe.src = safeSrc;
        iframe.className = 'pdf-embed';
        iframe.loading = 'lazy';
        // Make container fluid for iframe content
        page.style.width = '100%';
        page.style.padding = '0';
        page.style.minHeight = '70vh';
        page.style.background = 'transparent';
        page.innerHTML = '';
        page.appendChild(iframe);
        meta.textContent = attachmentType || 'Attachment';
    } else {
        // Insert content - handle both HTML and plain text
        if (content && typeof content === 'string') {
            const looksLikeFullHtml = /<\s*html|<\s*body|<!doctype|<\s*style|<\s*link[^>]*stylesheet/i.test(content);
            if (looksLikeFullHtml) {
                // Render full HTML page inside an isolated iframe to preserve its own CSS
                const iframe = document.createElement('iframe');
                iframe.className = 'pdf-embed';
                iframe.loading = 'lazy';
                // Keep styles, strip scripts
                const safe = sanitizeViewerHtml(content);
                const htmlShell = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>
                    html,body{margin:0;padding:0;background:#0b0f17;color:#000;font-family:Arial,Helvetica,sans-serif}
                    .reader{max-width:1100px;margin:24px auto;background:#fff;padding:24px;border-radius:10px;line-height:1.7}
                    .reader p, .reader div, .reader section, .reader article{background:transparent !important;box-shadow:none !important;border:none !important}
                    .reader img, .reader video, .reader iframe{max-width:100%;height:auto}
                </style></head><body><div class="reader">${safe}</div></body></html>`;
                iframe.srcdoc = htmlShell;
                // Make container fluid for iframe content
                page.style.width = '100%';
                page.style.padding = '0';
                page.style.minHeight = '70vh';
                page.style.background = 'transparent';
                page.innerHTML = '';
                page.appendChild(iframe);
                meta.textContent = 'HTML Material';
            } else if (content.includes('<') && content.includes('>')) {
                // Inline HTML fragment: render directly inside pdf-page area; pdf-page-frame now provides white background
                page.style.width = '100%';
                page.style.padding = '24px';
                page.style.background = 'transparent';
                page.innerHTML = sanitizeViewerHtml(content);
            } else {
                page.innerHTML = '<p>' + escapeHtml(content) + '</p>';
            }
        } else {
            page.innerHTML = '<p>No content available.</p>';
        }
    }

    // reset zoom
    document.getElementById('pdfZoom').value = '1';
    page.style.transform = 'scale(1)';

    backdrop.classList.add('active');
    backdrop.setAttribute('aria-hidden','false');
}

function sanitizeViewerHtml(raw) {
    if (!raw) return '<p></p>';
    
    // If the content is already HTML, use it directly
    if (typeof raw === 'string' && raw.includes('<')) {
        try {
            // Create a temporary div to parse and sanitize the HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = raw;
            
            // Remove any script tags and dangerous attributes
            const scripts = tempDiv.querySelectorAll('script');
            scripts.forEach(script => script.remove());
            
            const elements = tempDiv.querySelectorAll('*');
            elements.forEach(element => {
                // Remove event handlers
                Array.from(element.attributes).forEach(attr => {
                    if (attr.name.startsWith('on')) {
                        element.removeAttribute(attr.name);
                    }
                });
            });
            
            return tempDiv.innerHTML;
        } catch(e) {
            console.warn('Error sanitizing HTML:', e);
            return '<div>' + escapeHtml(String(raw)) + '</div>';
        }
    }
    
    // If it's plain text, escape it
    return '<div>' + escapeHtml(String(raw)) + '</div>';
}

function escapeHtml(s){ 
    return String(s).replace(/[&<>"']/g, function(m){ 
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; 
    }); 
}

function closePdfViewer(){
    const backdrop = document.getElementById('pdfViewerBackdrop');
    backdrop.classList.remove('active');
    backdrop.setAttribute('aria-hidden','true');
    document.getElementById('pdfPage').innerHTML = '';
}

function setPdfZoom(v){
    const page = document.getElementById('pdfPage');
    page.style.transform = 'scale(' + Number(v) + ')';
}

function downloadPdfView(){
    const title = document.getElementById('pdfViewerTitle').textContent || 'material';
    const page = document.getElementById('pdfPage');
    // If viewing via iframe, try to open the source (attachment or srcdoc) in a new tab
    const iframe = page.querySelector('iframe');
    if (iframe) {
        if (iframe.src) {
            try { window.open(iframe.src, '_blank'); return; } catch (e) {}
        }
        if (iframe.srcdoc) {
            const w = window.open('', '_blank');
            if (w) {
                w.document.open();
                w.document.write(iframe.srcdoc);
                w.document.close();
                return;
            }
        }
    }
    const content = page.innerHTML;
    const bg = page.style.backgroundColor || '#ffffff';
    const color = page.style.color || '#000000';

    const printHtml = `
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>${escapeHtml(title)}</title>
            <style>
                @page { margin: 12mm; size: A4; }
                body { margin:0; background: #ddd; display:flex; align-items:center; justify-content:center; }
                .page { width:210mm; min-height:297mm; background:${bg}; color:${color}; padding:24mm; box-sizing:border-box; font-family: Arial, Helvetica, sans-serif; }
                img { max-width:100%; height:auto; }
            </style>
        </head>
        <body>
            <div class="page">${content}</div>
        </body>
        </html>
    `;

    const printFrame = document.createElement('iframe');
    printFrame.style.position = 'fixed';
    printFrame.style.right = '0';
    printFrame.style.bottom = '0';
    printFrame.style.width = '0';
    printFrame.style.height = '0';
    printFrame.style.border = '0';
    document.body.appendChild(printFrame);

    const doc = printFrame.contentWindow.document;
    doc.open();
    doc.write(printHtml);
    doc.close();

    const doPrint = () => {
        try {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
        } catch (e) {
            console.warn('print failed', e);
            alert('Unable to open Print dialog in this browser.');
        } finally {
            setTimeout(()=>{ printFrame.remove(); }, 800);
        }
    };

    const imgs = doc.querySelectorAll('img');
    if (imgs.length === 0) {
        setTimeout(doPrint, 300);
    } else {
        let loaded = 0;
        imgs.forEach(img=>{
            img.onload = img.onerror = ()=>{ loaded++; if (loaded === imgs.length) doPrint(); };
        });
        setTimeout(doPrint, 2000);
    }
}

// Add event listeners for view material buttons
document.addEventListener('DOMContentLoaded', function() {
    // Handle view material button clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-material-btn')) {
            const title = e.target.getAttribute('data-title');
            const content = e.target.getAttribute('data-content');
            const theme = e.target.getAttribute('data-theme');
            const attachmentUrl = e.target.getAttribute('data-attachment');
            const attachmentType = e.target.getAttribute('data-attachment-type');
            viewMaterial(title, content, theme, attachmentUrl, attachmentType);
        }
    });
    
    // Close on backdrop click
    const pdfBackdropEl = document.getElementById('pdfViewerBackdrop');
    if (pdfBackdropEl) {
        pdfBackdropEl.addEventListener('click', function(e){
            if (e.target === this) closePdfViewer();
        });
    }
    
    // Let browser handle natural scrolling - no custom scroll handling
});
</script>
<?php
$content = ob_get_clean();
require_once 'includes/student_layout.php';
?>

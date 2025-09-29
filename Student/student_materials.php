<?php
require_once 'includes/student_init.php';

$pageTitle = 'Materials';
$materials = $conn->query("SELECT * FROM reading_materials ORDER BY created_at DESC");

ob_start();
?>
<style>
    .card {
        background: var(--card);
        border: 2px solid #d9f2ff;
        border-radius: 18px;
        box-shadow: 0 10px 20px rgba(43,144,217,.15);
        margin: 18px 0;
        overflow: hidden;
    }
    .card-header {
        padding: 14px 16px;
        background: linear-gradient(90deg,#e8f7ff,#f0fff6);
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 700;
        color: #17415e;
    }
    .card-body {
        padding: 16px;
    }
    .grid {
        display: grid;
        gap: 12px;
    }
    .grid-2 {
        grid-template-columns: 1fr 1fr;
    }
    .muted {
        color: var(--muted);
        font-size: 13px;
    }
    .btn {
        border: none;
        padding: 10px 14px;
        border-radius: 12px;
        cursor: pointer;
        color: #fff;
        font-weight: 700;
        box-shadow: 0 6px 14px rgba(30,144,255,.18);
        text-decoration: none;
        display: inline-block;
    }
    .btn-primary {
        background: linear-gradient(180deg, var(--primary), #5fb4ff);
    }
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(30,144,255,.25);
    }
    @media (max-width: 900px) {
        .grid-2 {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="card">
    <div class="card-header">
        <strong>Instructional Material Repository</strong> 
        <span class="emoji" aria-hidden="true">📗✨</span>
    </div>
    <div class="card-body">
        <div class="grid grid-2">
            <?php if ($materials && $materials->num_rows > 0): while ($m = $materials->fetch_assoc()): ?>
                <div class="card" style="margin:0; border-color:#d7ecff; box-shadow:0 6px 12px rgba(43,144,217,.12);">
                    <div class="card-header" style="justify-content:space-between;">
                        <span><?php echo h($m['title']); ?> 📘</span>
                        <span class="muted">Updated: <?php echo h(date('M j, Y', strtotime($m['updated_at']))); ?></span>
                    </div>
                    <div class="card-body">
                        
                        <div style="margin-top:8px; padding:12px; background:#f8f9fa; border-radius:8px; text-align:center; color:#6c757d;">
                            <p>📄 Click "View Full Material" to see the content</p>
                        </div>
                        <div style="margin-top: 12px;">
                            <button class="btn btn-primary view-material-btn" 
                                    data-title="<?php echo htmlspecialchars($m['title']); ?>"
                                    data-content="<?php echo htmlspecialchars($m['content']); ?>"
                                    data-theme="<?php echo htmlspecialchars($m['theme_settings']); ?>">
                                View Full Material
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; else: ?>
                <div class="card" style="text-align: center; padding: 40px;">
                    <p class="muted">No materials available yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
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
    max-width: calc(100% - 48px);
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
    background: #ddd; /* surrounding background */
    padding: 18px;
    display:flex;
    justify-content:center;
    overflow: auto;
    max-height: calc(100vh - 120px);
    scrollbar-width: thin;
    scrollbar-color: #999 #ddd;
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
    width: 400mm; /* A4 width */
    min-height: 297mm; /* A4 height */
    background: white;
    box-shadow: 0 6px 20px rgba(0,0,0,.18);
    padding: 24mm;
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
</style>

<script>
function viewMaterial(title, content, theme) {
    const backdrop = document.getElementById('pdfViewerBackdrop');
    const page = document.getElementById('pdfPage');
    const tl = document.getElementById('pdfViewerTitle');
    const meta = document.getElementById('pdfViewerMeta');

    tl.textContent = title || 'Material';
    meta.textContent = theme || '';

    // Apply theme preview if provided
    page.style.backgroundColor = '#ffffff';
    page.style.color = '#000000';

    // Insert content - handle both HTML and plain text
    if (content && typeof content === 'string') {
        // If content contains HTML tags, render it as HTML
        if (content.includes('<') && content.includes('>')) {
            page.innerHTML = sanitizeViewerHtml(content);
        } else {
            // If it's plain text, wrap it in a paragraph
            page.innerHTML = '<p>' + escapeHtml(content) + '</p>';
        }
    } else {
        page.innerHTML = '<p>No content available.</p>';
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

    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(printHtml);
    doc.close();

    const doPrint = () => {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {
            console.warn('print failed', e);
            alert('Unable to open Print dialog in this browser.');
        } finally {
            setTimeout(()=>{ iframe.remove(); }, 800);
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
            viewMaterial(title, content, theme);
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

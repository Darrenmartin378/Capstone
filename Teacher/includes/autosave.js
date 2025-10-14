/**
 * Auto-save functionality for teacher features
 * Prevents data loss on accidental refresh/close
 */

class AutoSave {
    constructor(options = {}) {
        this.prefix = options.prefix || 'teacher_autosave_';
        this.interval = options.interval || 30000; // 30 seconds
        this.storage = options.storage || 'localStorage';
        this.formSelector = options.formSelector || 'form';
        this.excludeFields = options.excludeFields || ['password', 'confirm_password'];
        this.onSave = options.onSave || null;
        this.onRestore = options.onRestore || null;
        this.isEnabled = false; // Disabled by user request
        this.timer = null;
        this.lastSaved = null;
        
        this.init();
    }
    
    init() {
        if (typeof Storage === 'undefined') {
            console.warn('AutoSave: localStorage not supported');
            return;
        }
        
        this.setupEventListeners();
        this.restoreData();
        this.startAutoSave();
        this.setupBeforeUnload();
    }
    
    setupEventListeners() {
        const form = document.querySelector(this.formSelector);
        if (!form) return;
        
        // Save on input change
        form.addEventListener('input', (e) => {
            if (this.shouldSaveField(e.target)) {
                this.debouncedSave();
            }
        });
        
        // Save on form change
        form.addEventListener('change', (e) => {
            if (this.shouldSaveField(e.target)) {
                this.debouncedSave();
            }
        });
        
        // Save on textarea/input blur
        form.addEventListener('blur', (e) => {
            if (this.shouldSaveField(e.target)) {
                this.saveData();
            }
        }, true);
    }
    
    shouldSaveField(field) {
        if (!field || !field.name) return false;
        if (this.excludeFields.includes(field.name)) return false;
        if (field.type === 'submit' || field.type === 'button') return false;
        return true;
    }
    
    debouncedSave() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        this.debounceTimer = setTimeout(() => {
            this.saveData();
        }, 2000); // 2 second delay
    }
    
    startAutoSave() {
        if (this.timer) {
            clearInterval(this.timer);
        }
        
        this.timer = setInterval(() => {
            this.saveData();
        }, this.interval);
    }
    
    saveData() {
        if (!this.isEnabled) return;
        
        const form = document.querySelector(this.formSelector);
        if (!form) return;
        
        const formData = new FormData(form);
        const data = {};
        
        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            if (this.excludeFields.includes(key)) continue;
            
            if (data[key]) {
                // Handle multiple values (like checkboxes)
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        
        // Add metadata
        data._autosave_metadata = {
            timestamp: Date.now(),
            url: window.location.href,
            formId: form.id || 'default'
        };

        // Page-specific capture: question builder
        if (form.id === 'questionForm') {
            try {
                data._custom = this.captureQuestionBuilderDraft();
            } catch (e) { console.warn('AutoSave: capture error', e); }
        }
        
        try {
            const key = this.prefix + this.getPageKey();
            const serialized = JSON.stringify(data);
            this.storage === 'sessionStorage' 
                ? sessionStorage.setItem(key, serialized)
                : localStorage.setItem(key, serialized);
            
            this.lastSaved = new Date();
            this.justSavedAt = Date.now();
            this.lastSnapshot = serialized; // cache latest snapshot to reduce leave prompts
            this.updateSaveIndicator('saved');
            
            if (this.onSave) {
                this.onSave(data);
            }
        } catch (e) {
            console.warn('AutoSave: Failed to save data', e);
        }
    }
    
    restoreData() {
        if (!this.isEnabled) return;
        
        try {
            const key = this.prefix + this.getPageKey();
            const saved = this.storage === 'sessionStorage' 
                ? sessionStorage.getItem(key)
                : localStorage.getItem(key);
            
            if (!saved) return;
            
            const data = JSON.parse(saved);
            this.lastSnapshot = saved; // seed baseline snapshot
            if (!data || !data._autosave_metadata) return;
            
            // Check if data is recent (within 24 hours)
            const age = Date.now() - data._autosave_metadata.timestamp;
            if (age > 24 * 60 * 60 * 1000) {
                this.clearData();
                return;
            }
            
            this.populateForm(data);

            // Page-specific restore
            if ((data._custom) && document.querySelector('#questionForm')) {
                try {
                    this.restoreQuestionBuilderDraft(data._custom);
                } catch (e) { console.warn('AutoSave: restore error', e); }
            }
            this.updateSaveIndicator('restored');
            
            if (this.onRestore) {
                this.onRestore(data);
            }
        } catch (e) {
            console.warn('AutoSave: Failed to restore data', e);
        }
    }
    
    populateForm(data) {
        const form = document.querySelector(this.formSelector);
        if (!form) return;
        
        Object.keys(data).forEach(key => {
            if (key.startsWith('_autosave_')) return;
            
            const elements = form.querySelectorAll(`[name="${key}"]`);
            elements.forEach(element => {
                if (element.type === 'checkbox' || element.type === 'radio') {
                    const values = Array.isArray(data[key]) ? data[key] : [data[key]];
                    element.checked = values.includes(element.value);
                } else if (element.type === 'select-multiple') {
                    const values = Array.isArray(data[key]) ? data[key] : [data[key]];
                    Array.from(element.options).forEach(option => {
                        option.selected = values.includes(option.value);
                    });
                } else {
                    element.value = Array.isArray(data[key]) ? data[key][0] : data[key];
                }
            });
        });
    }

    // -------- Question Builder (clean_question_creator.php) helpers ---------
    captureQuestionBuilderDraft() {
        const draft = { sections: [], questions: [] };
        // Sections from the multi-select panel
        document.querySelectorAll('#sectionPanel .sec-box:checked').forEach(cb => {
            draft.sections.push({ id: cb.value, label: cb.getAttribute('data-label') || '' });
        });

        // Questions
        const items = document.querySelectorAll('#questions-container .question-item');
        items.forEach((item) => {
            const index = item.getAttribute('data-question-index') || '0';
            const typeEl = item.querySelector(`#type_${index}`);
            const q = {
                index,
                type: typeEl ? typeEl.value : '',
                text: (item.querySelector(`#question_text_${index}`)?.value || ''),
                points: (item.querySelector(`#points_${index}`)?.value || '1')
            };
            if (q.type === 'mcq') {
                q.mcq = {
                    A: item.querySelector(`#mcq_option_A_${index}`)?.value || '',
                    B: item.querySelector(`#mcq_option_B_${index}`)?.value || '',
                    C: item.querySelector(`#mcq_option_C_${index}`)?.value || '',
                    D: item.querySelector(`#mcq_option_D_${index}`)?.value || '',
                    correct: (item.querySelector(`input[name="questions[${index}][correct_answer]"]:checked`)?.value || '')
                };
            } else if (q.type === 'matching') {
                const left = Array.from(item.querySelectorAll(`input[name="questions[${index}][left_items][]"]`)).map(i => i.value);
                const right = Array.from(item.querySelectorAll(`input[name="questions[${index}][right_items][]"]`)).map(i => i.value);
                const matches = Array.from(item.querySelectorAll(`select[name="questions[${index}][correct_matches][]"]`)).map(s => s.value);
                q.matching = { left, right, matches };
            } else if (q.type === 'essay') {
                q.essay = { rubric: item.querySelector(`#essay_rubric_${index}`)?.value || '' };
            }
            draft.questions.push(q);
        });
        return draft;
    }

    restoreQuestionBuilderDraft(draft) {
        // Restore sections: check panel boxes and sync
        if (Array.isArray(draft.sections)) {
            const boxes = document.querySelectorAll('#sectionPanel .sec-box');
            boxes.forEach(cb => { cb.checked = false; });
            draft.sections.forEach(s => {
                const el = document.querySelector(`#sectionPanel .sec-box[value="${s.id}"]`);
                if (el) el.checked = true;
            });
            // Trigger sync
            const all = document.getElementById('section_all');
            if (all) { all.dispatchEvent(new Event('change')); }
            else { boxes.forEach(b => b.dispatchEvent(new Event('change'))); }
        }

        // Restore questions
        if (Array.isArray(draft.questions) && draft.questions.length) {
            const container = document.getElementById('questions-container');
            if (container) container.innerHTML = '';
            draft.questions.forEach((q, idx) => {
                if (typeof window.addNewQuestion === 'function') {
                    window.addNewQuestion();
                    const index = document.querySelectorAll('#questions-container .question-item').length - 1;
                    const typeEl = document.getElementById(`type_${index}`);
                    if (typeEl) {
                        typeEl.value = q.type || '';
                        if (typeof window.showQuestionTypeSection === 'function') {
                            window.showQuestionTypeSection(index);
                        }
                    }
                    const textEl = document.getElementById(`question_text_${index}`);
                    if (textEl) textEl.value = q.text || '';
                    const ptsEl = document.getElementById(`points_${index}`);
                    if (ptsEl) ptsEl.value = q.points || '1';
                    if (q.type === 'mcq' && q.mcq) {
                        const map = q.mcq;
                        const setIf = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
                        setIf(`mcq_option_A_${index}`, map.A);
                        setIf(`mcq_option_B_${index}`, map.B);
                        setIf(`mcq_option_C_${index}`, map.C);
                        setIf(`mcq_option_D_${index}`, map.D);
                        if (map.correct) {
                            const radio = document.querySelector(`input[name="questions[${index}][correct_answer]"][value="${map.correct}"]`);
                            if (radio) radio.checked = true;
                        }
                    } else if (q.type === 'matching' && q.matching) {
                        const { left = [], right = [], matches = [] } = q.matching;
                        // Assume there are at least two rows; add rows if needed
                        const ensureRows = (count) => {
                            const leftInputs = document.querySelectorAll(`input[name="questions[${index}][left_items][]"]`);
                            while (leftInputs.length < count && typeof window.addMatchingRow === 'function') {
                                window.addMatchingRow(index);
                            }
                        };
                        ensureRows(Math.max(left.length, right.length));
                        const leftInputs = document.querySelectorAll(`input[name="questions[${index}][left_items][]"]`);
                        const rightInputs = document.querySelectorAll(`input[name="questions[${index}][right_items][]"]`);
                        const matchSelects = document.querySelectorAll(`select[name="questions[${index}][correct_matches][]"]`);
                        left.forEach((v, i) => { if (leftInputs[i]) leftInputs[i].value = v || ''; });
                        right.forEach((v, i) => { if (rightInputs[i]) rightInputs[i].value = v || ''; });
                        matches.forEach((v, i) => { if (matchSelects[i]) { matchSelects[i].value = v || ''; matchSelects[i].dispatchEvent(new Event('change')); } });
                    } else if (q.type === 'essay' && q.essay) {
                        const rb = document.getElementById(`essay_rubric_${index}`);
                        if (rb) rb.value = q.essay.rubric || '';
                    }
                }
            });
        }
    }
    
    clearData() {
        try {
            const key = this.prefix + this.getPageKey();
            this.storage === 'sessionStorage' 
                ? sessionStorage.removeItem(key)
                : localStorage.removeItem(key);
        } catch (e) {
            console.warn('AutoSave: Failed to clear data', e);
        }
    }
    
    getPageKey() {
        // Create unique key based on page and form
        const path = window.location.pathname;
        const form = document.querySelector(this.formSelector);
        const formId = form ? (form.id || 'default') : 'default';
        return btoa(path + '_' + formId).replace(/[^a-zA-Z0-9]/g, '');
    }
    
    updateSaveIndicator(status) {
        // Create or update save indicator
        let indicator = document.getElementById('autosave-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'autosave-indicator';
            indicator.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 8px 16px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                z-index: 9999;
                transition: all 0.3s ease;
                opacity: 0;
            `;
            document.body.appendChild(indicator);
        }
        
        const messages = {
            saved: { text: 'Draft saved', color: '#10b981', bg: '#d1fae5' },
            restored: { text: 'Draft restored', color: '#3b82f6', bg: '#dbeafe' },
            saving: { text: 'Saving...', color: '#f59e0b', bg: '#fef3c7' }
        };
        
        const msg = messages[status] || messages.saved;
        indicator.textContent = msg.text;
        indicator.style.color = msg.color;
        indicator.style.backgroundColor = msg.bg;
        indicator.style.opacity = '1';
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            indicator.style.opacity = '0';
        }, 3000);
    }
    
    setupBeforeUnload() {
        window.addEventListener('beforeunload', (e) => {
            // Only show warning if there are actual unsaved changes
            if (this.hasUnsavedChanges()) {
                // If we literally just saved in the last 5 seconds, don't warn
                if (this.justSavedAt && (Date.now() - this.justSavedAt) < 5000) {
                    return;
                }
                // Check if we have recent auto-save data
                const key = this.prefix + this.getPageKey();
                const saved = this.storage === 'sessionStorage' 
                    ? sessionStorage.getItem(key)
                    : localStorage.getItem(key);
                
                if (saved) {
                    const data = JSON.parse(saved);
                    const age = Date.now() - (data._autosave_metadata?.timestamp || 0);
                    // If auto-saved within last 2 minutes, don't show warning
                    if (age < 2 * 60 * 1000) {
                        return;
                    }
                }
                
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    }
    
    hasUnsavedChanges() {
        const form = document.querySelector(this.formSelector);
        if (!form) return false;
        
        // Build a comparable snapshot of current form + custom data
        const current = this.buildSnapshot();
        const currentStr = JSON.stringify(current);
        
        // If we have a lastSnapshot from storage/save, compare
        if (this.lastSnapshot && currentStr === this.lastSnapshot) {
            return false; // nothing new since last save
        }
        
        // More conservative check: only warn if there's substantial content
        const inputs = form.querySelectorAll('input, textarea, select');
        let hasSubstantialContent = false;
        
        Array.from(inputs).forEach(input => {
            if (input.type === 'submit' || input.type === 'button') return;
            const value = input.value && input.value.trim();
            // Only consider it "unsaved" if there's meaningful content
            if (value && value.length > 3) {
                hasSubstantialContent = true;
            }
        });
        
        return hasSubstantialContent;
    }

    buildSnapshot() {
        const form = document.querySelector(this.formSelector);
        const formData = new FormData(form);
        const plain = {};
        for (let [key, value] of formData.entries()) {
            if (this.excludeFields.includes(key)) continue;
            if (plain[key]) {
                Array.isArray(plain[key]) ? plain[key].push(value) : (plain[key] = [plain[key], value]);
            } else {
                plain[key] = value;
            }
        }
        if (form.id === 'questionForm') {
            try { plain._custom = this.captureQuestionBuilderDraft(); } catch(e) {}
        }
        return plain;
    }
    
    // Public methods
    enable() {
        this.isEnabled = true;
        this.startAutoSave();
    }
    
    disable() {
        this.isEnabled = false;
        if (this.timer) {
            clearInterval(this.timer);
        }
    }
    
    forceSave() {
        this.saveData();
    }
    
    forceRestore() {
        this.restoreData();
    }
    
    clear() {
        this.clearData();
        this.updateSaveIndicator('cleared');
    }
    
    destroy() {
        this.disable();
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
    }
}

// Auto-initialize for common teacher forms
document.addEventListener('DOMContentLoaded', function() {
    // Initialize for question creation form
    if (document.querySelector('#questionForm')) {
        window.questionAutoSave = new AutoSave({
            prefix: 'question_form_',
            formSelector: '#questionForm',
            interval: 30000,
            onSave: (data) => {
                console.log('Question form auto-saved');
            },
            onRestore: (data) => {
                console.log('Question form restored from draft');
                // Show notification to user
                if (confirm('A draft was found. Would you like to restore it?')) {
                    // Data is already restored, just confirm
                } else {
                    window.questionAutoSave.clear();
                }
            }
        });
    }
    
    // Initialize for practice test form
    if (document.querySelector('#practiceTestForm')) {
        window.practiceAutoSave = new AutoSave({
            prefix: 'practice_form_',
            formSelector: '#practiceTestForm',
            interval: 30000,
            onSave: (data) => {
                console.log('Practice test form auto-saved');
            },
            onRestore: (data) => {
                console.log('Practice test form restored from draft');
                if (confirm('A draft was found. Would you like to restore it?')) {
                    // Data is already restored
                } else {
                    window.practiceAutoSave.clear();
                }
            }
        });
    }
    
    // Initialize for any other forms
    const forms = document.querySelectorAll('form:not(#questionForm):not(#practiceTestForm)');
    forms.forEach((form, index) => {
        if (form.id) {
            window[`formAutoSave_${index}`] = new AutoSave({
                prefix: `form_${form.id}_`,
                formSelector: `#${form.id}`,
                interval: 30000
            });
        }
    });
});

// Export for manual initialization
window.AutoSave = AutoSave;

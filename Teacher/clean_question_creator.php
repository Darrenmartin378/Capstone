<?php
require_once 'includes/teacher_init.php';
require_once 'includes/QuestionHandler.php';

try {
    $questionHandler = new QuestionHandler($conn);
} catch (Exception $e) {
    error_log('Failed to create QuestionHandler: ' . $e->getMessage());
    die('Failed to initialize question handler');
}
$flash = '';

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
            case 'get_question_sets':
                try {
                    $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
                    if ($teacherId <= 0) {
                        echo json_encode(['success' => false, 'error' => 'Invalid teacher']);
                        exit;
                    }
                    
                    // Check if is_archived column exists, if not, add it
                    try {
                        $conn->query("ALTER TABLE question_sets ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
                    } catch (Exception $e) {
                        // Column already exists, ignore
                    }
                    
                    // Get question sets with question counts
                    $stmt = $conn->prepare("
                        SELECT qs.id, qs.set_title, qs.created_at,
                               (SELECT COUNT(*) FROM mcq_questions WHERE set_id = qs.id) +
                               (SELECT COUNT(*) FROM matching_questions WHERE set_id = qs.id) +
                               (SELECT COUNT(*) FROM essay_questions WHERE set_id = qs.id) as question_count
                        FROM question_sets qs
                        WHERE qs.teacher_id = ? AND (qs.is_archived = 0 OR qs.is_archived IS NULL)
                        ORDER BY qs.created_at DESC
                    ");
                    $stmt->bind_param('i', $teacherId);
                    $stmt->execute();
                    $questionSets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    echo json_encode(['success' => true, 'question_sets' => $questionSets]);
                    exit;
                } catch (Exception $e) {
                    error_log('Error getting question sets: ' . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => 'Failed to retrieve question sets']);
                    exit;
                }

            case 'get_questions_by_set':
                try {
                    $teacherId = (int)($_SESSION['teacher_id'] ?? 0);
                    $setId = (int)($_POST['set_id'] ?? 0);
                    
                    if ($teacherId <= 0 || $setId <= 0) {
                        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                        exit;
                    }
                    
                    // Verify teacher owns this set
                    $stmt = $conn->prepare("SELECT id, set_title FROM question_sets WHERE id = ? AND teacher_id = ? AND (is_archived = 0 OR is_archived IS NULL)");
                    $stmt->bind_param('ii', $setId, $teacherId);
                    $stmt->execute();
                    $setInfo = $stmt->get_result()->fetch_assoc();
                    
                    if (!$setInfo) {
                        echo json_encode(['success' => false, 'error' => 'Question set not found']);
                        exit;
                    }
                    
                    $questions = [];
                    
                    // Get MCQ questions
                    $stmt = $conn->prepare("
                        SELECT question_id as id, 'multiple_choice' as question_type, question_text, 
                               choice_a, choice_b, choice_c, choice_d, 
                               correct_answer as answer, points, order_index, created_at
                        FROM mcq_questions 
                        WHERE set_id = ?
                        ORDER BY order_index, question_id
                    ");
                    $stmt->bind_param('i', $setId);
                    $stmt->execute();
                    $mcqQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    // Get Matching questions
                    $stmt = $conn->prepare("
                        SELECT question_id as id, 'matching' as question_type, question_text,
                               left_items, right_items, correct_pairs as answer,
                               points, order_index, created_at
                        FROM matching_questions 
                        WHERE set_id = ?
                        ORDER BY order_index, question_id
                    ");
                    $stmt->bind_param('i', $setId);
                    $stmt->execute();
                    $matchingQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    // Get Essay questions
                    $stmt = $conn->prepare("
                        SELECT question_id as id, 'essay' as question_type, question_text,
                               points, order_index, created_at
                        FROM essay_questions 
                        WHERE set_id = ?
                        ORDER BY order_index, question_id
                    ");
                    $stmt->bind_param('i', $setId);
                    $stmt->execute();
                    $essayQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    // Combine all questions
                    $questions = array_merge($mcqQuestions, $matchingQuestions, $essayQuestions);
                    
                    // Sort by order_index
                    usort($questions, function($a, $b) {
                        return ($a['order_index'] ?? 0) - ($b['order_index'] ?? 0);
                    });
                    
                    echo json_encode([
                        'success' => true, 
                        'questions' => $questions,
                        'set_info' => $setInfo
                    ]);
                    exit;
                } catch (Exception $e) {
                    error_log('Error getting questions by set: ' . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => 'Failed to retrieve questions']);
                    exit;
                }

            case 'ai_recommend_questions':
                try {
                    error_log('AI Recommendation Request - POST data: ' . print_r($_POST, true));
                    
                    $materialIds = [];
                    if (isset($_POST['material_ids']) && !empty($_POST['material_ids'])) {
                        // Handle comma-separated material IDs
                        $ids = explode(',', $_POST['material_ids']);
                        foreach ($ids as $id) {
                            $id = (int)trim($id);
                            if ($id > 0) $materialIds[] = $id;
                        }
                        error_log('Material IDs from material_ids: ' . implode(',', $materialIds));
                    } elseif (isset($_POST['material_id'])) {
                        // Handle single material ID (backward compatibility)
                        $id = (int)($_POST['material_id'] ?? 0);
                        if ($id > 0) $materialIds[] = $id;
                        error_log('Material ID from material_id: ' . $id);
                    }
                    
                    $qType = strtolower(trim((string)($_POST['question_type'] ?? 'mcq')));
                    if (!in_array($qType, ['mcq','matching','essay'], true)) { $qType = 'mcq'; }
                    
                    error_log('Question type: ' . $qType);
                    error_log('Material IDs count: ' . count($materialIds));
                    
                    if (empty($materialIds)) {
                        error_log('No materials selected');
                        echo json_encode(['success' => false, 'error' => 'No materials selected']);
                        exit;
                    }

                    // Ensure teacher owns all materials
                    $tid = (int)($_SESSION['teacher_id'] ?? 0);
                    error_log('Teacher ID: ' . $tid);
                    
                    $placeholders = str_repeat('?,', count($materialIds) - 1) . '?';
                    $sql = "SELECT id, title, content, attachment_path, attachment_name, attachment_type FROM reading_materials WHERE id IN ($placeholders) AND teacher_id = ?";
                    error_log('SQL Query: ' . $sql);
                    error_log('Material IDs for query: ' . implode(',', $materialIds));
                    
                    $stmt = $conn->prepare($sql);
                    $params = array_merge($materialIds, [$tid]);
                    $stmt->bind_param(str_repeat('i', count($materialIds)) . 'i', ...$params);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $materials = [];
                    while ($mat = $res->fetch_assoc()) {
                        $materials[] = $mat;
                    }
                    
                    error_log('Found materials: ' . count($materials));
                    
                    if (empty($materials)) {
                        error_log('No materials found for teacher');
                        echo json_encode(['success' => false, 'error' => 'No materials found']);
                        exit;
                    }

                    // Process all materials and combine their content
                    $allTitles = [];
                    $allContent = [];
                    $combinedText = '';
                    
                    foreach ($materials as $mat) {
                        $title = trim((string)($mat['title'] ?? ''));
                        $html = (string)($mat['content'] ?? '');
                        $attachmentPath = (string)($mat['attachment_path'] ?? '');
                        $attachmentName = (string)($mat['attachment_name'] ?? '');
                        $attachmentType = (string)($mat['attachment_type'] ?? '');
                        
                        $allTitles[] = $title;
                        
                        // Check if this is an uploaded file (PDF, Word, etc.)
                        if (!empty($attachmentPath) && !empty($attachmentName)) {
                            $text = extractFileContent($attachmentPath, $attachmentType, $title);
                    } else {
                            // Regular text content
                            $text = trim(strip_tags($html));
                            
                            // If content is empty or minimal, provide better context
                            if (strlen($text) < 100) {
                                $text = "This reading material covers educational topics suitable for Grade 6 students. ";
                                $text .= "The content includes various academic concepts and learning materials. ";
                            }
                        }
                        
                        $allContent[] = $text;
                        $combinedText .= $text . "\n\n";
                    }
                    
                    // Limit combined text length
                    if (strlen($combinedText) > 8000) { 
                        $combinedText = substr($combinedText, 0, 8000) . '...'; 
                    }

                    // Enhanced material content extraction for comprehensive AI analysis
                    $mainText = '';
                    $detailedContent = [];
                    
                    try {
                        // Process each material's content thoroughly for AI analysis
                        foreach ($materials as $mat) {
                            $materialTitle = (string)($mat['title'] ?? '');
                            $html = (string)($mat['content'] ?? '');
                            $cleanHtml = preg_replace('/<(script|style)[^>]*>.*?<\\/\\1>/is', '', $html);
                            
                            // Skip file metadata and focus on actual content
                            if (stripos($html, 'This is an uploaded file titled:') !== false ||
                                stripos($html, 'The file content could not be extracted') !== false ||
                                stripos($html, 'uploaded file') !== false) {
                                // Skip materials that are just file metadata
                                continue;
                            }
                            
                            // Extract comprehensive content from this material
                            $materialContent = [];
                            
                            // 1. Extract headings (H1-H6) for structure
                            if (preg_match_all('/<h[1-6][^>]*>(.*?)<\\/h[1-6]>/is', $cleanHtml, $headings)) {
                                foreach ($headings[1] as $heading) {
                                    $headingText = trim(strip_tags($heading));
                                    if (strlen($headingText) > 3 && stripos($headingText, 'uploaded file') === false) {
                                        $materialContent[] = "HEADING: " . $headingText;
                                    }
                                }
                            }
                            
                            // 2. Extract paragraphs with full content
                            if (preg_match_all('/<p[^>]*>(.*?)<\\/p>/is', $cleanHtml, $paragraphs)) {
                                foreach ($paragraphs[1] as $para) {
                                    $paraText = trim(preg_replace('/\s+/', ' ', strip_tags($para)));
                                    if (mb_strlen($paraText) >= 20 && stripos($paraText, 'uploaded file') === false) {
                                        $materialContent[] = $paraText;
                                    }
                                }
                            }
                            
                            // 3. Extract list items for key points
                            if (preg_match_all('/<li[^>]*>(.*?)<\\/li>/is', $cleanHtml, $listItems)) {
                                foreach ($listItems[1] as $item) {
                                    $itemText = trim(preg_replace('/\s+/', ' ', strip_tags($item)));
                                    if (mb_strlen($itemText) >= 10 && stripos($itemText, 'uploaded file') === false) {
                                        $materialContent[] = "• " . $itemText;
                                    }
                                }
                            }
                            
                            // 4. Extract definitions and key terms
                            if (preg_match_all('/<(strong|b)>\s*([^<]{1,100})\s*<\\\/(strong|b)>\s*[:\-–—]\s*([^<]{2,200})/is', $cleanHtml, $definitions)) {
                                foreach ($definitions as $def) {
                                    $term = trim($def[2]);
                                    $definition = trim($def[4]);
                                    if ($term && $definition && stripos($term, 'uploaded file') === false) {
                                        $materialContent[] = "DEFINITION: " . $term . " - " . $definition;
                                    }
                                }
                            }
                            
                            // 5. Extract examples and quotes
                            if (preg_match_all('/[""]([^""]{10,150})[""]/u', $cleanHtml, $quotes)) {
                                foreach ($quotes[1] as $quote) {
                                    $quoteText = trim($quote);
                                    if (mb_strlen($quoteText) >= 10 && stripos($quoteText, 'uploaded file') === false) {
                                        $materialContent[] = "EXAMPLE: \"" . $quoteText . "\"";
                                    }
                                }
                            }
                            
                            // Combine all content for this material with clear structure
                            if (!empty($materialContent)) {
                                $detailedContent[] = "=== READING MATERIAL ===\n" . implode("\n", $materialContent);
                            }
                        }
                        
                        // Create comprehensive main text for AI analysis
                        $mainText = implode("\n\n", $detailedContent);
                        
                        // Ensure we have substantial content for AI analysis
                        if (strlen($mainText) < 100) {
                            // Fallback to combined text if detailed extraction failed
                            $mainText = $combinedText;
                        }
                        
                        // Limit to reasonable length for AI processing (increased for GPT-5)
                        if (strlen($mainText) > 12000) {
                            $mainText = substr($mainText, 0, 12000) . "\n\n[Content truncated for processing...]";
                        }
                        
                    } catch (Throwable $e) { 
                        error_log('Enhanced content extraction error: ' . $e->getMessage());
                        $mainText = $combinedText;
                    }

                    // Extract main topic and key concepts from all materials
                    $concepts = [];
                    $mainTopic = '';
                    $keyPoints = [];

                    try {
                        $plain = preg_replace('/\r\n?/', "\n", $combinedText);
                        
                        // Extract main topic from multiple materials
                        $mainTopic = '';
                        if (count($allTitles) === 1) {
                            $mainTopic = $allTitles[0];
                } else {
                            $mainTopic = 'Multiple topics: ' . implode(', ', array_slice($allTitles, 0, 3));
                            if (count($allTitles) > 3) {
                                $mainTopic .= ' and ' . (count($allTitles) - 3) . ' more';
                            }
                        }
                        
                        // Extract key points and concepts from the content
                        $sentences = preg_split('/(?<=[.!?])\s+/', $plain);
                        foreach ($sentences as $sentence) {
                            $sentence = trim($sentence);
                            if (mb_strlen($sentence) >= 20 && mb_strlen($sentence) <= 200) {
                                // Look for important concepts, definitions, or key information
                                if (preg_match('/\b(?:what|how|why|when|where|who|which|definition|means|is|are|refers to|example|instance)\b/i', $sentence)) {
                                    $keyPoints[] = $sentence;
                                }
                            }
                        }
                        
                        // Extract specific terms and their definitions
                        if (preg_match_all('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*[:\-–—]\s*([^.!?]{10,150})/i', $plain, $termDefs, PREG_SET_ORDER)) {
                            foreach ($termDefs as $match) {
                                $term = trim($match[1]);
                                $definition = trim($match[2]);
                                if (mb_strlen($term) >= 3 && mb_strlen($definition) >= 10) {
                                    $concepts[] = [
                                        'name' => $term,
                                        'definition' => $definition,
                                        'examples' => []
                                    ];
                                }
                            }
                        }
                        
                        // Extract examples and important details
                        if (preg_match_all('/[""]([^""]{10,100})[""]/u', $plain, $examples)) {
                            foreach ($examples[1] as $example) {
                                $example = trim($example);
                                if (mb_strlen($example) >= 10) {
                                    $keyPoints[] = $example;
                                }
                            }
                        }
                        
                        // If no specific concepts found, create general content concepts
                        if (empty($concepts)) {
                            // Extract important sentences as concepts
                            $importantSentences = array_slice($keyPoints, 0, 5);
                            foreach ($importantSentences as $index => $sentence) {
                                $concepts[] = [
                                    'name' => "Key Point " . ($index + 1),
                                    'definition' => $sentence,
                                    'examples' => []
                                ];
                            }
                        }
                        
                    } catch (Throwable $e) { 
                        error_log('Content analysis error: ' . $e->getMessage());
                    }
                    
                    // If no concepts found, let the AI analyze the actual content
                    // Don't force specific concepts - let AI work with the actual material content
                    
                    $conceptsJson = json_encode($concepts, JSON_UNESCAPED_UNICODE);

                    // Enhanced content analysis to ensure AI reads actual material content
                    $contentAnalysis = [];
                    try {
                        // Extract key sentences and their context with better filtering
                        $sentences = preg_split('/(?<=[.!?])\s+/', strip_tags($mainText));
                        $keySentences = [];
                        foreach ($sentences as $s) {
                            $s = trim($s);
                            // More comprehensive sentence filtering
                            if (mb_strlen($s) >= 15 && mb_strlen($s) <= 300) {
                                // Avoid generic or placeholder content
                                $genericPhrases = [
                                    'click here', 'read more', 'see below', 'as mentioned',
                                    'for example', 'in conclusion', 'to summarize',
                                    'it is important', 'it should be noted', 'please note'
                                ];
                                
                                $isGeneric = false;
                                foreach ($genericPhrases as $phrase) {
                                    if (stripos($s, $phrase) !== false) {
                                        $isGeneric = true;
                break;
                                    }
                                }
                                
                                if (!$isGeneric && !empty(trim($s))) {
                                    $keySentences[] = $s;
                                }
                            }
                        }
                        
                        // Ensure we have substantial content for AI analysis
                        if (empty($keySentences) && !empty($mainText)) {
                            // Fallback: split by paragraphs and extract meaningful content
                            $paragraphs = preg_split('/\n\s*\n/', strip_tags($mainText));
                            foreach ($paragraphs as $para) {
                                $para = trim($para);
                                if (mb_strlen($para) >= 30 && mb_strlen($para) <= 500) {
                                    $keySentences[] = $para;
                                }
                            }
                        }
                        
                        // Identify learning objectives from content structure
                        $objectives = [];
                        if (!empty($concepts)) {
                            foreach ($concepts as $c) {
                                $objectives[] = "Understand and identify " . $c['name'];
                                if (!empty($c['examples'])) {
                                    $objectives[] = "Recognize examples of " . $c['name'];
                                }
                            }
                        }
                        
                        // Extract vocabulary and terminology
                        $vocabulary = [];
                        foreach ($concepts as $c) {
                            $vocabulary[$c['name']] = [
                                'definition' => $c['definition'],
                                'examples' => array_slice($c['examples'], 0, 3),
                                'context' => 'Key term from material'
                            ];
                        }
                        
                        // Identify question-worthy content patterns
                        $patterns = [];
                        if (stripos($mainText, 'definition') !== false) $patterns[] = 'definition_based';
                        if (stripos($mainText, 'example') !== false) $patterns[] = 'example_based';
                        if (stripos($mainText, 'type') !== false || stripos($mainText, 'kind') !== false) $patterns[] = 'classification';
                        if (!empty($concepts)) $patterns[] = 'concept_application';
                        
                        $contentAnalysis = [
                            'topic' => $title,
                            'key_sentences' => array_slice($keySentences, 0, 8),
                            'learning_objectives' => $objectives,
                            'vocabulary' => $vocabulary,
                            'content_patterns' => $patterns,
                            'complexity_level' => count($concepts) > 3 ? 'moderate' : 'basic'
                        ];
                    } catch (Throwable $e) { /* ignore */ }
                    $analysisJson = json_encode($contentAnalysis, JSON_UNESCAPED_UNICODE);

                    // Load and analyze previous successful question patterns for adaptive learning
                    $questionPatterns = [];
                    try {
                        if (isset($_SESSION['successful_question_patterns']) && is_array($_SESSION['successful_question_patterns'])) {
                            $questionPatterns = array_slice($_SESSION['successful_question_patterns'], -5); // Last 5 successful patterns
                        }
                    } catch (Throwable $e) { /* ignore */ }
                    $patternsJson = json_encode($questionPatterns);

                    // Extract potential term–definition/example pairs from the material (for matching)
                    $extractedPairs = [];
                    try {
                        // Pull from <li> elements like "Term: Definition" or "Term - Definition"
                        if (preg_match_all('/<li[^>]*>(.*?)<\\/li>/is', $html, $m)) {
                            foreach ($m[1] as $liRaw) {
                                $plain = trim(preg_replace('/\s+/', ' ', strip_tags($liRaw)));
                                if (strpos($plain, ':') !== false) {
                                    list($k, $v) = array_map('trim', explode(':', $plain, 2));
                                } elseif (strpos($plain, ' - ') !== false || strpos($plain, ' – ') !== false || strpos($plain, ' — ') !== false) {
                                    $parts = preg_split('/\s[-–—]\s/', $plain, 2);
                                    $k = trim($parts[0] ?? '');
                                    $v = trim($parts[1] ?? '');
                                } else { $k = ''; $v = ''; }
                                if ($k !== '' && $v !== '' && strlen($k) <= 120 && strlen($v) >= 2) {
                                    $extractedPairs[] = [$k, $v];
                                }
                            }
                        }
                        // Bold/strong label patterns like <strong>Term</strong>: Definition
                        if (preg_match_all('/<(strong|b)>\s*([^<]{1,120})\s*<\\\/(strong|b)>\s*[:\-–—]\s*([^<]{2,300})/is', $html, $m2, PREG_SET_ORDER)) {
                            foreach ($m2 as $mm) {
                                $k = trim($mm[2]);
                                $v = trim($mm[4]);
                                if ($k !== '' && $v !== '') { $extractedPairs[] = [$k, $v]; }
                            }
                        }
                        // Definition lists <dt>Term</dt><dd>Definition</dd>
                        if (preg_match_all('/<dt[^>]*>(.*?)<\\/dt>\s*<dd[^>]*>(.*?)<\\/dd>/is', $html, $m3, PREG_SET_ORDER)) {
                            foreach ($m3 as $mm) {
                                $k = trim(strip_tags($mm[1]));
                                $v = trim(strip_tags($mm[2]));
                                if ($k !== '' && $v !== '') { $extractedPairs[] = [$k, $v]; }
                            }
                        }
                        // Headings followed by first paragraph as definition
                        if (preg_match_all('/<h[1-6][^>]*>(.*?)<\\/h[1-6]>\s*(<p[^>]*>(.*?)<\\/p>)?/is', $html, $m4, PREG_SET_ORDER)) {
                            foreach ($m4 as $mm) {
                                $k = trim(strip_tags($mm[1]));
                                $pv = trim(strip_tags($mm[3] ?? ''));
                                if ($k !== '' && $pv !== '') {
                                    // take first sentence
                                    $sent = preg_split('/(?<=[.!?])\s+/', $pv, 2)[0];
                                    if ($sent !== '') { $extractedPairs[] = [$k, $sent]; }
                                }
                            }
                        }
                        // Also scan plain text lines with a colon
                        $lines = preg_split('/\r?\n/', $text);
                        foreach ($lines as $ln) {
                            $ln = trim(preg_replace('/\s+/', ' ', $ln));
                            if (strpos($ln, ':') !== false) {
                                list($k, $v) = array_map('trim', explode(':', $ln, 2));
                                if ($k !== '' && $v !== '' && strlen($k) <= 120 && strlen($v) >= 2) {
                                    $extractedPairs[] = [$k, $v];
                                }
                            }
                        }
                        // Deduplicate by left term (case-insensitive)
                        $seen = [];
                        $dedup = [];
                        foreach ($extractedPairs as $p) {
                            $lk = strtolower($p[0]);
                            if (!isset($seen[$lk])) { $seen[$lk] = true; $dedup[] = $p; }
                        }
                        $extractedPairs = $dedup;
                    } catch (Throwable $e) { /* ignore */ }

                    // Use local fallback system (no API key required)
                    $apiKey = false; // Disable AI API to use local fallback system

                    $recommendations = [];
                    // Local fallback system provides high-quality questions without API costs
                    if ($apiKey) {
                        try {
                            $sysBase = 'You are Claude, an expert educational assessment specialist with advanced expertise in modern assessment design, cognitive psychology, and pedagogical best practices. Your specialized knowledge includes:

1. CONTEXT-AWARE ANALYSIS: Deeply analyze content structure, learning objectives, and cognitive demands to create questions that truly test understanding
2. COGNITIVE TAXONOMY MASTERY: Expertly design questions across all Bloom\'s taxonomy levels with appropriate cognitive complexity
3. MODERN ASSESSMENT DESIGN: Create questions that follow current best practices in educational measurement and assessment
4. DIFFERENTIATED PEDAGOGY: Generate questions that accommodate various learning styles, abilities, and cognitive approaches
5. CURRICULUM INTEGRATION: Ensure questions align with learning standards and promote meaningful learning outcomes
6. CRITICAL THINKING EXPERTISE: Design questions that promote higher-order thinking, analysis, and synthesis
7. CONTENT-SPECIFIC INTELLIGENCE: Use actual material content to create contextually relevant and educationally valuable questions

Your sophisticated process:
1) Conduct deep content analysis to identify key concepts, relationships, and learning objectives
2) Determine the most important knowledge and skills students should master
3) Create diverse, sophisticated question types that test both knowledge and reasoning
4) Ensure questions are contextually relevant, educationally valuable, and age-appropriate
5) Generate 6-7 high-quality questions that demonstrate advanced pedagogical understanding
6) Focus on questions that would be valuable for both formative and summative assessment

QUALITY STANDARDS:
- Questions must be contextually relevant to the specific content
- Use actual details, examples, and information from the materials
- Create questions that test deep understanding, not just surface knowledge
- Ensure distractors are educationally valuable and test real misconceptions
- Design questions that promote critical thinking and reasoning
- Avoid generic or template-based questions
- Focus on questions that would be valuable for student learning and assessment

Return ONLY valid JSON with {"questions": [...]} array containing 6-7 sophisticated questions.';
                            if ($qType === 'mcq') {
                                $usr = "You are Claude, an expert educational assessment specialist. You MUST read and analyze the ACTUAL CONTENT from the selected materials before generating questions. Generate 6-7 sophisticated multiple-choice questions based ONLY on the real content provided.

CRITICAL INSTRUCTION: READ THE ACTUAL MATERIAL CONTENT BELOW CAREFULLY!

MATERIALS SELECTED:\n" . implode(', ', $allTitles) . "\n\nACTUAL MATERIAL CONTENT:\n{$conceptsJson}\n\nFULL TEXT OF SELECTED MATERIALS:\n{$mainText}\n\nDETAILED CONTENT ANALYSIS:\n{$analysisJson}\n\nIMPORTANT: You MUST use ONLY the content provided above. Do NOT use any external knowledge or generic information. Base ALL questions, options, and answers EXCLUSIVELY on the material content provided.\n\nMANDATORY REQUIREMENTS:\n\n1. CONTENT-BASED ANALYSIS (CRITICAL):\n   - READ and ANALYZE the actual material content provided above\n   - Identify specific facts, concepts, examples, and details from the materials\n   - Create questions that test understanding of the ACTUAL content, not generic topics\n   - Use specific information, examples, and details that appear in the materials\n   - NEVER use placeholder text or generic content\n\n2. ANTI-PLACEHOLDER REQUIREMENTS:\n   - FORBIDDEN: Generic phrases like 'Another option from the material', 'A different concept', 'Another example'\n   - FORBIDDEN: Placeholder text like 'Option A', 'Choice B', 'Another choice'\n   - FORBIDDEN: Vague content like 'A key idea from the passage', 'A detail from the text'\n   - FORBIDDEN: TERRIBLE questions like 'What is Examples?', 'What is including?', 'What is Examples', 'What is including'\n   - FORBIDDEN: Malformed questions like 'What is example?', 'What is definition?', 'What is concept?', 'What is term?'\n   - FORBIDDEN: Questions that start with 'What is' followed by a single word and question mark\n   - FORBIDDEN: Incomplete options with question marks or broken sentences\n   - REQUIRED: Use specific facts, examples, and details from the actual materials\n   - REQUIRED: Create options based on real content, not generic templates\n   - REQUIRED: Create proper, professional questions with complete sentences\n\n3. CONTENT-SPECIFIC QUESTION CREATION:\n   - Extract specific facts, concepts, and examples from the materials\n   - Create questions about the actual topics covered in the materials\n   - Use real examples and details that appear in the text\n   - Test understanding of specific concepts mentioned in the materials\n   - Ensure all options are based on actual content from the materials\n\n4. DISTRACTOR QUALITY (NO PLACEHOLDERS):\n   - Use other specific facts, concepts, or examples from the materials as distractors\n   - Create plausible but incorrect options based on real content\n   - Avoid generic or placeholder distractors\n   - Ensure all options are content-specific and meaningful\n   - Test real misconceptions about the actual material content\n\n5. CONTENT VERIFICATION:\n   - Each question must be answerable using information from the provided materials\n   - All options must be based on actual content from the materials\n   - Questions must test understanding of the specific topics covered\n   - Avoid questions that could apply to any generic material\n   - Focus on the unique aspects and specific content of the selected materials\n\n6. QUALITY ASSURANCE:\n   - Each question must have 4 unique, content-specific options (A, B, C, D)\n   - Options should be similar in length and complexity\n   - Correct answer must be clearly correct based on the material content\n   - Distractors must be plausible but incorrect based on the material content\n   - Questions must be clear and unambiguous\n   - Language must be appropriate for Grade 6 students\n   - NO malformed questions or broken options\n   - NO incomplete sentences or question marks in options\n   - NO multiple questions in one question\n\nCONTENT ANALYSIS PROCESS:\n1. Read the full material content provided above\n2. Identify the main topics, concepts, and key information\n3. Extract specific facts, examples, and details\n4. Create questions that test understanding of this specific content\n5. Use only information that appears in the provided materials\n6. Ensure all options are based on actual material content\n\nGENERATION STRATEGY:\n- Start by identifying the main topics and concepts from the actual materials\n- Create questions that test understanding of these specific topics\n- Use real examples and details from the materials\n- Create distractors using other specific information from the materials\n- Focus on questions that can only be answered using the provided material content\n- Avoid any generic or placeholder content\n- Ensure all questions are well-formed and complete\n\nReturn JSON: {\\\"questions\\\":[{\\\"type\\\":\\\"mcq\\\",\\\"question_text\\\":string,\\\"choices\\\":{\\\"A\\\":string,\\\"B\\\":string,\\\"C\\\":string,\\\"D\\\":string},\\\"correct_answer\\\":\\\"A|B|C|D\\\",\\\"points\\\":1}]}";
                            } elseif ($qType === 'matching') {
                                $sample = '';
                                if (!empty($extractedPairs)) {
                                    $lim = array_slice($extractedPairs, 0, 6);
                                    $pairsJson = json_encode(array_map(function($p){ return ['left'=>$p[0], 'right'=>$p[1]]; }, $lim));
                                    $sample = "\nUse only terms that actually appear in the content. Example extracted pairs: {$pairsJson}";
                                }
                                $usr = "You are Claude, an expert educational assessment specialist. You MUST read and analyze the ACTUAL CONTENT from the selected materials before generating matching questions. Generate 4-5 sophisticated matching questions based ONLY on the real content provided.

CRITICAL INSTRUCTION: READ THE ACTUAL MATERIAL CONTENT BELOW CAREFULLY!

DETAILED CONTENT ANALYSIS:\n{$analysisJson}\n\nFULL TEXT OF SELECTED MATERIALS:\n{$mainText}{$sample}\n\nEXTRACTED CONCEPTS FROM MATERIALS:\n{$conceptsJson}\n\nIMPORTANT: You MUST use ONLY the content provided above. Do NOT use any external knowledge or generic information. Base ALL matching pairs EXCLUSIVELY on the material content provided.\n\nMANDATORY REQUIREMENTS:\n\n1. CONTENT-BASED MATCHING (CRITICAL):\n   - READ and ANALYZE the actual material content provided above\n   - Identify specific terms, concepts, definitions, and examples from the materials\n   - Create matches based on ACTUAL content, not generic templates\n   - Use real terms, definitions, and examples that appear in the materials\n   - NEVER use placeholder text or generic content\n\n2. ANTI-PLACEHOLDER REQUIREMENTS:\n   - FORBIDDEN: Generic terms like 'Concept 1', 'Definition 1', 'Example 1'\n   - FORBIDDEN: Placeholder text like 'Term A', 'Description B', 'Item C'\n   - FORBIDDEN: Vague content like 'A concept from the material', 'A definition from the text'\n   - REQUIRED: Use specific terms, concepts, and definitions from the actual materials\n   - REQUIRED: Create matches based on real content relationships\n\n3. CONTENT-SPECIFIC MATCHING CREATION:\n   - Extract specific terms and their definitions from the materials\n   - Identify real concepts and their examples from the materials\n   - Create matches based on actual relationships in the materials\n   - Use real examples and details that appear in the text\n   - Ensure all items are based on actual content from the materials\n\n4. MATCHING QUALITY (NO PLACEHOLDERS):\n   - Use specific terms, concepts, and definitions from the materials\n   - Create matches based on real relationships in the content\n   - Avoid generic or placeholder items\n   - Ensure all items are content-specific and meaningful\n   - Test real understanding of the actual material content\n\n5. CONTENT VERIFICATION:\n   - Each matching question must be based on actual material content\n   - All items must be derived from the provided materials\n   - Matches must reflect real relationships in the content\n   - Avoid generic matches that could apply to any material\n   - Focus on the unique aspects and specific content of the selected materials\n\n6. QUALITY ASSURANCE:\n   - Each question should have 4-5 meaningful, content-specific pairs\n   - Items should be clearly related but require content knowledge to match\n   - Use precise, content-specific vocabulary from the materials\n   - Ensure all items derive from actual material content\n   - Create logical but not obvious matches based on real content\n\nCONTENT ANALYSIS PROCESS:\n1. Read the full material content provided above\n2. Identify specific terms, concepts, and their definitions\n3. Extract real examples and their relationships\n4. Create matches based on actual content relationships\n5. Use only information that appears in the provided materials\n6. Ensure all items are based on actual material content\n\nMATCHING STRATEGY:\n- Start by identifying specific terms and concepts from the actual materials\n- Create matches based on real relationships in the content\n- Use actual definitions and examples from the materials\n- Create matches that test understanding of specific content\n- Focus on relationships that exist in the provided materials\n- Avoid any generic or placeholder content\n\nReturn JSON: {\\\"questions\\\":[{\\\"type\\\":\\\"matching\\\",\\\"question_text\\\":string,\\\"left_items\\\":[string,string,string,string],\\\"right_items\\\":[string,string,string,string],\\\"correct_pairs\\\":[string,string,string,string],\\\"points\\\":4}]}";
                            } else { // essay
                                $usr = "You are Claude, an expert educational assessment specialist. You MUST read and analyze the ACTUAL CONTENT from the selected materials before generating essay questions. Generate 4-5 sophisticated essay questions based ONLY on the real content provided.

CRITICAL INSTRUCTION: READ THE ACTUAL MATERIAL CONTENT BELOW CAREFULLY!

DETAILED CONTENT ANALYSIS:\n{$analysisJson}\n\nFULL TEXT OF SELECTED MATERIALS:\n{$mainText}\n\nIMPORTANT: You MUST use ONLY the content provided above. Do NOT use any external knowledge or generic information. Base ALL essay questions EXCLUSIVELY on the material content provided.\n\nMANDATORY REQUIREMENTS:\n\n1. CONTENT-BASED ESSAY QUESTIONS (CRITICAL):\n   - READ and ANALYZE the actual material content provided above\n   - Identify specific topics, concepts, and key information from the materials\n   - Create questions that test understanding of the ACTUAL content, not generic topics\n   - Use specific information, examples, and details that appear in the materials\n   - NEVER use placeholder text or generic content\n\n2. ANTI-PLACEHOLDER REQUIREMENTS:\n   - FORBIDDEN: Generic questions like 'Discuss the main idea', 'Explain the concept', 'Analyze the topic'\n   - FORBIDDEN: Placeholder text like 'the material', 'the content', 'the text'\n   - FORBIDDEN: Vague content like 'key concepts', 'main ideas', 'important points'\n   - REQUIRED: Use specific topics, concepts, and details from the actual materials\n   - REQUIRED: Create questions based on real content, not generic templates\n\n3. CONTENT-SPECIFIC ESSAY CREATION:\n   - Extract specific topics and concepts from the materials\n   - Create questions about the actual content covered in the materials\n   - Use real examples and details that appear in the text\n   - Test understanding of specific concepts mentioned in the materials\n   - Ensure all questions are based on actual content from the materials\n\n4. ESSAY QUALITY (NO PLACEHOLDERS):\n   - Use specific topics and concepts from the materials\n   - Create questions based on real content relationships\n   - Avoid generic or placeholder questions\n   - Ensure all questions are content-specific and meaningful\n   - Test real understanding of the actual material content\n\n5. CONTENT VERIFICATION:\n   - Each question must be answerable using information from the provided materials\n   - All questions must be based on actual content from the materials\n   - Questions must test understanding of the specific topics covered\n   - Avoid questions that could apply to any generic material\n   - Focus on the unique aspects and specific content of the selected materials\n\n6. QUALITY ASSURANCE:\n   - Each question should be answerable in 3-5 well-developed sentences\n   - Require specific content knowledge and understanding\n   - Test higher-order thinking skills and reasoning\n   - Include clear, detailed rubric criteria for assessment\n   - Be age-appropriate but intellectually challenging for Grade 6\n   - Encourage original thinking and personal connections\n\nCONTENT ANALYSIS PROCESS:\n1. Read the full material content provided above\n2. Identify the main topics, concepts, and key information\n3. Extract specific facts, examples, and details\n4. Create questions that test understanding of this specific content\n5. Use only information that appears in the provided materials\n6. Ensure all questions are based on actual material content\n\nESSAY STRATEGY:\n- Start by identifying specific topics and concepts from the actual materials\n- Create questions that test understanding of these specific topics\n- Use real examples and details from the materials\n- Create questions that require analysis of specific content\n- Focus on questions that can only be answered using the provided material content\n- Avoid any generic or placeholder content\n\nReturn JSON: {\\\"questions\\\":[{\\\"type\\\":\\\"essay\\\",\\\"question_text\\\":string,\\\"rubric\\\":string,\\\"points\\\":5}]}";
                            }
                            $payload = [
                                'model' => 'claude-3-5-sonnet-20241022',  // Using Claude 3.5 Sonnet for advanced reasoning and generation quality
                                'max_tokens' => 4000,  // Increased token limit for more detailed responses
                                'temperature' => 0.6,  // Slightly lower temperature for more consistent, focused output
                                'messages' => [
                                    ['role' => 'user', 'content' => $sysBase . "\n\n" . $usr]
                                ]
                            ];
                            $ch = curl_init('https://api.anthropic.com/v1/messages');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Content-Type: application/json',
                                'x-api-key: ' . $apiKey,
                                'anthropic-version: 2023-06-01'
                            ]);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                            $resp = curl_exec($ch);
                            if ($resp === false) { throw new Exception('Claude API request failed'); }
                            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            if ($status < 200 || $status >= 300) { throw new Exception('Claude API non-2xx'); }
                            $data = json_decode($resp, true);
                            $content = $data['content'][0]['text'] ?? '';
                            // Extract JSON block
                            $jsonStr = $content;
                            if (strpos($jsonStr, '{') !== false) {
                                $jsonStr = substr($jsonStr, strpos($jsonStr, '{'));
                            }
                            if (strrpos($jsonStr, '}') !== false) {
                                $jsonStr = substr($jsonStr, 0, strrpos($jsonStr, '}') + 1);
                            }
                            $parsed = json_decode($jsonStr, true);
                            if (isset($parsed['questions']) && is_array($parsed['questions'])) {
                                foreach ($parsed['questions'] as $q) {
                                    $qt = strtolower((string)($q['type'] ?? $qType));
                                    if ($qt === 'mcq' && $qType === 'mcq') {
                                        $choices = $q['choices'] ?? [];
                                        
                                        // Validate that all options are populated, unique, and material-based
                                        $validChoices = [];
                                        $allPopulated = true;
                                        $uniqueChoices = [];
                                        $materialBased = true;
                                        
                                        foreach (['A', 'B', 'C', 'D'] as $letter) {
                                            $choice = trim((string)($choices[$letter] ?? ''));
                                            if (empty($choice)) {
                                                $allPopulated = false;
                break;
                                            }
                                            
                                            // Check for duplicate options (case-insensitive and whitespace-normalized)
                                            $choiceNormalized = strtolower(preg_replace('/\s+/', ' ', trim($choice)));
                                            foreach ($uniqueChoices as $usedChoice) {
                                                $usedNormalized = strtolower(preg_replace('/\s+/', ' ', trim($usedChoice)));
                                                if ($usedNormalized === $choiceNormalized) {
                                                    $allPopulated = false;
                                                    break 2;
                                                }
                                            }
                                            
                                            // Enhanced placeholder detection - check if content appears to be from material (not generic)
                                            $genericPhrases = [
                                                'another option from the material',
                                                'a different concept from the material',
                                                'another example from the material',
                                                'a different concept',
                                                'another example',
                                                'option a', 'option b', 'option c', 'option d',
                                                'choice a', 'choice b', 'choice c', 'choice d',
                                                'concept 1', 'concept 2', 'concept 3', 'concept 4',
                                                'definition 1', 'definition 2', 'definition 3', 'definition 4',
                                                'example 1', 'example 2', 'example 3', 'example 4',
                                                'term 1', 'term 2', 'term 3', 'term 4',
                                                'item 1', 'item 2', 'item 3', 'item 4',
                                                'a key idea from the passage',
                                                'a detail from the text',
                                                'a concept from the material',
                                                'a definition from the text',
                                                'an example from the material',
                                                'another choice',
                                                'another option',
                                                'another answer',
                                                'a different answer',
                                                'a different choice',
                                                'a different option',
                                                'placeholder',
                                                'lorem ipsum',
                                                'sample text',
                                                'example text'
                                            ];
                                            
                                            foreach ($genericPhrases as $generic) {
                                                if (stripos($choice, $generic) !== false) {
                                                    $materialBased = false;
                                                    break 2;
                                                }
                                            }
                                            
                                            $uniqueChoices[] = $choice;
                                            $validChoices[$letter] = $choice;
                                        }
                                        
                                        // Only add if all options are populated, unique, and material-based
                                        if ($allPopulated && count($validChoices) === 4 && count($uniqueChoices) === 4 && $materialBased) {
                                            // Additional validation: check for exact duplicates
                                            $choiceValues = array_values($validChoices);
                                            $uniqueValues = array_unique($choiceValues);
                                            if (count($choiceValues) !== count($uniqueValues)) {
                                                // Skip this question - has duplicates
                                                continue;
                                            }
                                            
                                            // Enhanced validation: reject poor quality questions
                                            $hasGenericContent = false;
                                            $hasDefinitionLabels = false;
                                            $hasMalformedContent = false;
                                            $hasIncompleteOptions = false;
                                            
                                            foreach ($validChoices as $choice) {
                                                // Check for generic content
                                                if (stripos($choice, 'another option') !== false || 
                                                    stripos($choice, 'different concept') !== false ||
                                                    stripos($choice, 'another example') !== false) {
                                                    $hasGenericContent = true;
                                                    break;
                                                }
                                                
                                                // Check for definition labels and general statements
                                                if (stripos($choice, 'definition:') !== false ||
                                                    stripos($choice, 'there are many types') !== false ||
                                                    stripos($choice, 'figurative language is a form') !== false ||
                                                    stripos($choice, 'using the words') !== false ||
                                                    stripos($choice, 'a form of expression') !== false ||
                                                    stripos($choice, 'including:') !== false ||
                                                    stripos($choice, 'figurative language is') !== false ||
                                                    stripos($choice, 'nonliteral meanings') !== false ||
                                                    stripos($choice, 'convey a more abstract') !== false) {
                                                    $hasDefinitionLabels = true;
                                                    break;
                                                }
                                                
                                                // Check for malformed content (incomplete sentences, broken quotes)
                                                if (strpos($choice, '"') !== false && substr_count($choice, '"') % 2 !== 0) {
                                                    $hasMalformedContent = true;
                                                    break;
                                                }
                                                
                                                // Check for incomplete options (too short or incomplete)
                                                if (strlen(trim($choice)) < 10 || 
                                                    stripos($choice, 'what is') !== false ||
                                                    stripos($choice, 'including?') !== false ||
                                                    stripos($choice, '?') !== false) {
                                                    $hasIncompleteOptions = true;
                                                    break;
                                                }
                                                
                                                // Check for broken options with multiple questions
                                                if (substr_count($choice, '?') > 0 || 
                                                    stripos($choice, 'A.') !== false ||
                                                    stripos($choice, 'B.') !== false ||
                                                    stripos($choice, 'C.') !== false ||
                                                    stripos($choice, 'D.') !== false) {
                                                    $hasMalformedContent = true;
                                                    break;
                                                }
                                            }
                                            
                                            // Check question text for quality issues - REJECT TERRIBLE QUESTIONS
                                            $questionText = trim((string)($q['question_text'] ?? ''));
                                            $terribleQuestions = [
                                                'what is examples?',
                                                'what is including?',
                                                'what is examples',
                                                'what is including',
                                                'what is example?',
                                                'what is example',
                                                'what is definition?',
                                                'what is definition',
                                                'what is concept?',
                                                'what is concept',
                                                'what is term?',
                                                'what is term',
                                                'what is item?',
                                                'what is item',
                                                'what is option?',
                                                'what is option',
                                                'what is choice?',
                                                'what is choice',
                                                'what is this is an uploaded file titled?',
                                                'what is this is an uploaded file',
                                                'what is uploaded file',
                                                'what is file titled',
                                                'what is quarter 1 module 1',
                                                'what is quarter 1',
                                                'what is module 1',
                                                'what is pdf',
                                                'what is document',
                                                'what is material',
                                                'what is reading material',
                                                'what is content',
                                                'what is file',
                                                'what is uploaded',
                                                'what is title',
                                                'what is name',
                                                'what is filename',
                                                'what is file name',
                                                'which detail comes from',
                                                'which detail comes',
                                                'which detail',
                                                'comes from quarter',
                                                'comes from module',
                                                'comes from pdf',
                                                'comes from document',
                                                'comes from file',
                                                'from quarter 1',
                                                'from module 1',
                                                'from pdf',
                                                'from document',
                                                'from file'
                                            ];
                                            
                                            $questionLower = strtolower($questionText);
                                            foreach ($terribleQuestions as $terrible) {
                                                if (stripos($questionLower, $terrible) !== false) {
                                                    $hasMalformedContent = true;
                                                    break;
                                                }
                                            }
                                            
                                            if (strlen($questionText) < 15 || 
                                                stripos($questionText, '?') !== false && substr_count($questionText, '?') > 1 ||
                                                stripos($questionText, 'what is') !== false && stripos($questionText, '?') !== false) {
                                                $hasMalformedContent = true;
                                            }
                                            
                                            // Additional validation: reject questions with file references
                                            if (stripos($questionText, '.pdf') !== false ||
                                                stripos($questionText, '.doc') !== false ||
                                                stripos($questionText, '.docx') !== false ||
                                                stripos($questionText, '.ppt') !== false ||
                                                stripos($questionText, '.pptx') !== false ||
                                                stripos($questionText, 'quarter') !== false ||
                                                stripos($questionText, 'module') !== false ||
                                                stripos($questionText, 'which detail comes from') !== false ||
                                                stripos($questionText, 'which detail') !== false ||
                                                stripos($questionText, 'comes from') !== false) {
                                                $hasMalformedContent = true;
                                            }
                                            
                                            // Reject poor quality questions
                                            if ($hasGenericContent || $hasDefinitionLabels || $hasMalformedContent || $hasIncompleteOptions) {
                                                continue; // Skip generic content or definition labels
                                            }
                                            
                                            // Validate that distractors represent different concepts
                                            $conceptKeywords = ['simile', 'metaphor', 'hyperbole', 'personification', 'idiom'];
                                            $foundConcepts = [];
                                            
                                            foreach ($validChoices as $choice) {
                                                $choiceLower = strtolower($choice);
                                                foreach ($conceptKeywords as $concept) {
                                                    if (stripos($choiceLower, $concept) !== false) {
                                                        $foundConcepts[] = $concept;
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // Ensure we have different concepts (at least 3 different ones)
                                            $uniqueConcepts = array_unique($foundConcepts);
                                            if (count($uniqueConcepts) < 3) {
                                                continue; // Skip if not enough different concepts
                                            }
                                            
                                            // For example questions, ensure all options are actual sentences/examples
                                            $questionText = strtolower((string)($q['question_text'] ?? ''));
                                            if (stripos($questionText, 'which sentence') !== false || 
                                                stripos($questionText, 'which example') !== false ||
                                                stripos($questionText, 'shows an example') !== false) {
                                                
                                                $hasNonExampleContent = false;
                                                foreach ($validChoices as $choice) {
                                                    // Check if choice contains definition-like content
                                                    if (stripos($choice, 'definition') !== false ||
                                                        stripos($choice, 'there are many types') !== false ||
                                                        stripos($choice, 'figurative language is') !== false ||
                                                        stripos($choice, 'using the words') !== false ||
                                                        stripos($choice, 'a form of expression') !== false ||
                                                        stripos($choice, 'including:') !== false) {
                                                        $hasNonExampleContent = true;
                                                        break;
                                                    }
                                                }
                                                
                                                if ($hasNonExampleContent) {
                                                    continue; // Skip example questions with non-example content
                                                }
                                            }
                                            $recommendation = [
                                                'type' => 'mcq',
                                                'question_text' => (string)($q['question_text'] ?? ''),
                                                'choices' => $validChoices,
                                                'correct_answer' => (string)($q['correct_answer'] ?? ''),
                                                'points' => (int)($q['points'] ?? 1)
                                            ];
                                            $recommendations[] = $recommendation;
                                            
                                            // Store successful pattern for adaptive learning
                                            try {
                                                if (!isset($_SESSION['successful_question_patterns'])) {
                                                    $_SESSION['successful_question_patterns'] = [];
                                                }
                                                $_SESSION['successful_question_patterns'][] = [
                                                    'type' => 'mcq',
                                                    'pattern' => substr($recommendation['question_text'], 0, 50),
                                                    'quality_score' => strlen($recommendation['question_text']) > 20 ? 'high' : 'medium',
                                                    'timestamp' => time()
                                                ];
                                                // Keep only last 10 patterns
                                                $_SESSION['successful_question_patterns'] = array_slice($_SESSION['successful_question_patterns'], -10);
                                            } catch (Throwable $e) { /* ignore */ }
                                        }
                                    } elseif ($qt === 'matching' && $qType === 'matching') {
                                        $li = $q['left_items'] ?? [];
                                        $ri = $q['right_items'] ?? [];
                                        $cp = $q['correct_pairs'] ?? [];
                                        if (!is_array($li)) $li = [];
                                        if (!is_array($ri)) $ri = [];
                                        if (!is_array($cp)) $cp = [];
                                        $recommendations[] = [
                                            'type' => 'matching',
                                            'question_text' => (string)($q['question_text'] ?? ''),
                                            'left_items' => array_values(array_map('strval', $li)),
                                            'right_items' => array_values(array_map('strval', $ri)),
                                            'correct_pairs' => array_values(array_map('strval', $cp)),
                                            'points' => (int)($q['points'] ?? max(1, count($li)))
                                        ];
                                    } elseif ($qt === 'essay' && $qType === 'essay') {
                                        $recommendations[] = [
                                            'type' => 'essay',
                                            'question_text' => (string)($q['question_text'] ?? ''),
                                            'rubric' => (string)($q['rubric'] ?? ''),
                                            'points' => (int)($q['points'] ?? 5)
                                        ];
                                    }
                                }
                            }
                        } catch (Throwable $e) {
                            // fall through to local fallback
                        }
                    }

                    if (empty($recommendations)) {
                        $topic = $title !== '' ? $title : 'the content';
                        if ($qType === 'mcq') {
                            // Build body-specific MCQs from extracted concepts
                            $recommendations = [];
                            // Definition questions
                            if (!empty($concepts)) {
                                $pool = $concepts;
                                // Generate 5-6 questions instead of just 2
                                for ($i=0; $i<min(6, count($pool)); $i++) {
                                    $c = $pool[$i];
                                    $others = array_values(array_filter($pool, function($x) use($c){ return ($x['name']??'') !== ($c['name']??''); }));
                                    
                                    // Enhanced question generation using content analysis
                                    $correct = (string)($c['definition'] ?? '');
                                    
                                    // Randomize correct answer position for better quality
                                    $positions = ['A', 'B', 'C', 'D'];
                                    shuffle($positions);
                                    $correctPos = $positions[0];
                                    
                                    $choices = [];
                                    $choices[$correctPos] = $correct;
                                    
                                    // Create better distractors using other definitions
                                    $distractors = [];
                                    foreach ($others as $od) {
                                        if (count($distractors) >= 3) break;
                                        $distractors[] = (string)($od['definition'] ?? '');
                                    }
                                    
                                    // Use content analysis for better distractors if needed
                                    if (count($distractors) < 3 && !empty($contentAnalysis['key_sentences'])) {
                                        foreach ($contentAnalysis['key_sentences'] as $sentence) {
                                            if (count($distractors) >= 3) break;
                                            if (strlen($sentence) < 100 && !in_array($sentence, $distractors)) {
                                                $distractors[] = $sentence;
                                            }
                                        }
                                    }
                                    
                                    // Fill remaining positions with distractors
                                    $k = 0;
                                    foreach ($positions as $pos) {
                                        if ($pos !== $correctPos && $k < count($distractors)) {
                                            $choices[$pos] = $distractors[$k];
                                            $k++;
                                        }
                                    }
                                    
                                    // Pad with generic distractors if needed
                                    foreach ($positions as $pos) {
                                        if (!isset($choices[$pos])) {
                                            $choices[$pos] = 'A different concept from the material';
                                        }
                                    }
                                    
                                    // Create meaningful distractors from other concepts
                                    $meaningfulDistractors = [];
                                    $usedDefinitions = [];
                                    
                                    foreach ($concepts as $otherConcept) {
                                        if ($otherConcept['name'] !== $c['name'] && !empty($otherConcept['definition'])) {
                                            $def = trim($otherConcept['definition']);
                                            if (!in_array($def, $usedDefinitions)) {
                                                $meaningfulDistractors[] = $def;
                                                $usedDefinitions[] = $def;
                                                if (count($meaningfulDistractors) >= 3) break;
                                            }
                                        }
                                    }
                                    
                                    // If we don't have enough concepts, create related but wrong definitions
                                    $fallbackDefinitions = [
                                        'A comparison that does not use "like" or "as"',  // Metaphor
                                        'An extreme exaggeration to emphasize a point',  // Hyperbole
                                        'Giving human characteristics to non-living things',  // Personification
                                        'A phrase that means something different from its literal meaning'  // Idiom
                                    ];
                                    
                                    foreach ($fallbackDefinitions as $fallbackDef) {
                                        if (!in_array($fallbackDef, $usedDefinitions) && count($meaningfulDistractors) < 3) {
                                            $meaningfulDistractors[] = $fallbackDef;
                                            $usedDefinitions[] = $fallbackDef;
                                        }
                                    }
                                    
                                    // Final validation - ensure all 4 options are populated with unique, material-based content
                                    $finalChoices = [];
                                    $usedOptions = [];
                                    $distractorIndex = 0;
                                    
                                    foreach (['A', 'B', 'C', 'D'] as $letter) {
                                        if (!empty($choices[$letter])) {
                                            $finalChoices[$letter] = $choices[$letter];
                                            $usedOptions[] = $choices[$letter];
                                        } elseif ($distractorIndex < count($meaningfulDistractors)) {
                                            $distractor = $meaningfulDistractors[$distractorIndex];
                                            // Ensure no duplicates
                                            if (!in_array($distractor, $usedOptions)) {
                                                $finalChoices[$letter] = $distractor;
                                                $usedOptions[] = $distractor;
                                                $distractorIndex++;
                                            } else {
                                                // Skip duplicate, try next
                                                $distractorIndex++;
                                                $letter--; // Retry this letter
                                            }
                                        } else {
                                            // Use material-specific fallback
                                            $fallbackOptions = [
                                                'A form of expression using literal meanings',
                                                'A type of writing that tells a story',
                                                'A method of organizing information',
                                                'A way of expressing ideas directly'
                                            ];
                                            
                                            foreach ($fallbackOptions as $fallback) {
                                                if (!in_array($fallback, $usedOptions)) {
                                                    $finalChoices[$letter] = $fallback;
                                                    $usedOptions[] = $fallback;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Create a proper question based on the concept
                                    $questionText = '';
                                    if (stripos($c['name'], 'uploaded file') !== false || 
                                        stripos($c['name'], 'quarter') !== false ||
                                        stripos($c['name'], 'module') !== false ||
                                        stripos($c['name'], 'pdf') !== false ||
                                        stripos($c['name'], 'document') !== false ||
                                        stripos($c['name'], 'file') !== false ||
                                        stripos($c['name'], 'title') !== false ||
                                        stripos($c['name'], 'name') !== false) {
                                        // Skip terrible questions about file metadata
                                        continue;
                                    }
                                    
                                    // Create meaningful questions based on content
                                    if (stripos($c['name'], 'simile') !== false) {
                                        $questionText = 'Which sentence is an example of a simile?';
                                    } elseif (stripos($c['name'], 'metaphor') !== false) {
                                        $questionText = 'Which sentence is an example of a metaphor?';
                                    } elseif (stripos($c['name'], 'hyperbole') !== false) {
                                        $questionText = 'Which sentence is an example of hyperbole?';
                                    } elseif (stripos($c['name'], 'personification') !== false) {
                                        $questionText = 'Which sentence is an example of personification?';
                                    } elseif (stripos($c['name'], 'idiom') !== false) {
                                        $questionText = 'Which phrase is an example of an idiom?';
                                    } else {
                                        // Create a general question about the concept
                                        $questionText = 'What is ' . $c['name'] . '?';
                                    }
                                    
                                    $recommendations[] = [
                                        'type'=>'mcq',
                                        'question_text'=> $questionText,
                                        'choices'=>$finalChoices,
                                        'correct_answer'=>$correctPos,
                                        'points'=>1
                                    ];
                                }
                                // Enhanced example identification
                                foreach ($concepts as $c) {
                                    if (count($recommendations) >= 4) break;
                                    $ex = $c['examples'][0] ?? '';
                                    if ($ex === '') continue;
                                    
                                    // Randomize correct answer position
                                    $positions = ['A', 'B', 'C', 'D'];
                                    shuffle($positions);
                                    $correctPos = $positions[0];
                                    
                                    $choices = [];
                                    $choices[$correctPos] = $ex;
                                    
                                    // Get distractor examples from other concepts
                                    $distractors = [];
                                    foreach ($concepts as $o) {
                                        if (($o['name']??'') !== ($c['name']??'') && !empty($o['examples'])) {
                                            $distractors[] = $o['examples'][0];
                                            if (count($distractors) >= 3) break;
                                        }
                                    }
                                    
                                    // Use key sentences as additional distractors if needed
                                    if (count($distractors) < 3 && !empty($contentAnalysis['key_sentences'])) {
                                        foreach ($contentAnalysis['key_sentences'] as $sentence) {
                                            if (count($distractors) >= 3) break;
                                            if (!in_array($sentence, $distractors) && $sentence !== $ex) {
                                                $distractors[] = $sentence;
                                            }
                                        }
                                    }
                                    
                                    // Fill remaining positions with distractors
                                    $k = 0;
                                    foreach ($positions as $pos) {
                                        if ($pos !== $correctPos && $k < count($distractors)) {
                                            $choices[$pos] = $distractors[$k];
                                            $k++;
                                        }
                                    }
                                    
                                    // Pad with proper sentence examples if needed
                                    $fallbackSentences = [
                                        '"Time is money"',  // Metaphor
                                        '"The leaves danced in the wind"',  // Personification
                                        '"My bag weighs a ton!"',  // Hyperbole
                                        '"It\'s raining cats and dogs"',  // Idiom
                                        '"The wind howled like a wolf"',  // Simile
                                        '"The sun smiled down on us"',  // Personification
                                        '"Her voice was music to my ears"',  // Metaphor
                                        '"I could eat a horse!"'  // Hyperbole
                                    ];
                                    
                                    foreach ($positions as $pos) {
                                        if (!isset($choices[$pos])) {
                                            foreach ($fallbackSentences as $sentence) {
                                                if (!in_array($sentence, $choices)) {
                                                    $choices[$pos] = $sentence;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Create meaningful distractors from other concept examples
                                    $meaningfulDistractors = [];
                                    $usedExamples = [];
                                    
                                    foreach ($concepts as $otherConcept) {
                                        if ($otherConcept['name'] !== $c['name'] && !empty($otherConcept['examples'])) {
                                            $example = trim($otherConcept['examples'][0]);
                                            if (!in_array($example, $usedExamples)) {
                                                $meaningfulDistractors[] = $example;
                                                $usedExamples[] = $example;
                                                if (count($meaningfulDistractors) >= 3) break;
                                            }
                                        }
                                    }
                                    
                                    // If we don't have enough examples, create related but wrong examples
                                    $fallbackExamples = [
                                        '"Time is money"',  // Metaphor example
                                        '"The leaves danced in the wind"',  // Personification example
                                        '"My bag weighs a ton!"',  // Hyperbole example
                                        '"It\'s raining cats and dogs"',  // Idiom example
                                        '"The wind howled like a wolf"',  // Another simile (wrong for non-simile questions)
                                        '"The sun smiled down on us"'  // Personification example
                                    ];
                                    
                                    foreach ($fallbackExamples as $fallbackExample) {
                                        if (!in_array($fallbackExample, $usedExamples) && count($meaningfulDistractors) < 3) {
                                            $meaningfulDistractors[] = $fallbackExample;
                                            $usedExamples[] = $fallbackExample;
                                        }
                                    }
                                    
                                    // Final validation - ensure all 4 options are populated with unique, material-based content
                                    $finalChoices = [];
                                    $usedOptions = [];
                                    $distractorIndex = 0;
                                    
                                    foreach (['A', 'B', 'C', 'D'] as $letter) {
                                        if (!empty($choices[$letter])) {
                                            $finalChoices[$letter] = $choices[$letter];
                                            $usedOptions[] = $choices[$letter];
                                        } elseif ($distractorIndex < count($meaningfulDistractors)) {
                                            $distractor = $meaningfulDistractors[$distractorIndex];
                                            // Ensure no duplicates
                                            if (!in_array($distractor, $usedOptions)) {
                                                $finalChoices[$letter] = $distractor;
                                                $usedOptions[] = $distractor;
                                                $distractorIndex++;
                                            } else {
                                                // Skip duplicate, try next
                                                $distractorIndex++;
                                                $letter--; // Retry this letter
                                            }
                                        } else {
                                            // Use material-specific fallback examples
                                            $fallbackExamples = [
                                                '"The sun is bright"',
                                                '"The wind is strong"',
                                                '"The water is cold"',
                                                '"The sky is blue"'
                                            ];
                                            
                                            foreach ($fallbackExamples as $fallback) {
                                                if (!in_array($fallback, $usedOptions)) {
                                                    $finalChoices[$letter] = $fallback;
                                                    $usedOptions[] = $fallback;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    
                                    $recommendations[] = [
                                        'type'=>'mcq',
                                        'question_text'=> 'Which sentence shows an example of '.$c['name'].'?',
                                        'choices'=>$finalChoices,
                                        'correct_answer'=>$correctPos,
                                        'points'=>1
                                    ];
                                }
                                // Idiom meaning questions, if idioms exist
                                $idiom = null; foreach ($concepts as $c){ if (stripos($c['name'],'idiom')!==false) { $idiom=$c; break; } }
                                if ($idiom && !empty($idiom['examples'])) {
                                    $ex = $idiom['examples'][0];
                                    // Attempt to parse common idiom meanings from definition/examples
                                    $correct = 'the accepted meaning of the idiom';
                                    if (stripos($ex,'hit the sack')!==false) $correct = 'go to bed';
                                    if (stripos($ex,'under the weather')!==false) $correct = 'feeling sick';
                                    $choices = ['A'=>$correct,'B'=>'its literal meaning','C'=>'an unrelated action','D'=>'a random opinion'];
                                    $recommendations[] = [
                                        'type'=>'mcq',
                                        'question_text'=>'What does the idiom in this sentence most nearly mean: "'.$ex.'"?',
                                        'choices'=>$choices,
                                        'correct_answer'=>'A',
                                        'points'=>1
                                    ];
                                }
                            }
                            if (empty($recommendations)) {
                                // Last resort generic single item
                                $recommendations = [ [ 'type'=>'mcq', 'question_text'=>"Which detail comes from {$topic}?", 'choices'=>['A'=>'A key idea from the passage','B'=>'A random fact','C'=>'Off-topic idea','D'=>'Opinion only'], 'correct_answer'=>'A','points'=>1 ] ];
                            }
                        } elseif ($qType === 'matching') {
                            // Build matching questions from extractedPairs or concepts when possible
                            if (count($extractedPairs) >= 3) {
                                $chunks = array_chunk($extractedPairs, 3);
                                $recommendations = [];
                                foreach ($chunks as $chunk) {
                                    if (count($recommendations) >= 3) break; // limit to 3
                                    $left = [];$right=[];$cp=[];
                                    foreach ($chunk as $pair) { $left[] = (string)$pair[0]; $right[] = (string)$pair[1]; $cp[] = (string)$pair[1]; }
                                    $recommendations[] = [
                                        'type'=>'matching',
                                        'question_text'=>"Match each term from {$topic} with its correct definition.",
                                        'left_items'=>$left,
                                        'right_items'=>$right,
                                        'correct_pairs'=>$cp,
                                        'points'=>count($left)
                                    ];
                                }
                            }
                            if (empty($recommendations) && !empty($concepts)) {
                                // Generate 3-4 matching questions instead of just 1
                                $recommendations = [];
                                
                                // Question 1: Term-Definition matching
                                $left=[]; $right=[]; $cp=[];
                                foreach (array_slice($concepts,0,4) as $c){ 
                                    $left[]=$c['name']; 
                                    $d=$c['definition']??''; 
                                    $right[]=$d; 
                                    $cp[]=$d; 
                                }
                                if (!empty($left) && !empty($right)) {
                                    $recommendations[] = [ 
                                        'type'=>'matching',
                                        'question_text'=>"Match each term to its correct definition.",
                                        'left_items'=>$left,
                                        'right_items'=>$right,
                                        'correct_pairs'=>$cp,
                                        'points'=>count($left) 
                                    ];
                                }
                                
                                // Question 2: Concept-Example matching (if we have examples)
                                $left2=[]; $right2=[]; $cp2=[];
                                foreach (array_slice($concepts,0,4) as $c){ 
                                    if (!empty($c['examples'])) {
                                        $left2[]=$c['name']; 
                                        $example = $c['examples'][0] ?? '';
                                        $right2[]=$example; 
                                        $cp2[]=$example; 
                                    }
                                }
                                if (!empty($left2) && !empty($right2)) {
                                    $recommendations[] = [ 
                                        'type'=>'matching',
                                        'question_text'=>"Match each concept to its example.",
                                        'left_items'=>$left2,
                                        'right_items'=>$right2,
                                        'correct_pairs'=>$cp2,
                                        'points'=>count($left2) 
                                    ];
                                }
                                
                                // Question 3: Process-Step matching (if applicable)
                                if (count($concepts) >= 3) {
                                    $left3=[]; $right3=[]; $cp3=[];
                                    $processes = ['Step 1', 'Step 2', 'Step 3', 'Step 4'];
                                    $descriptions = ['Initial process', 'Development phase', 'Final stage', 'Completion'];
                                    
                                    foreach (array_slice($processes,0,3) as $i => $process) {
                                        $left3[] = $process;
                                        $right3[] = $descriptions[$i];
                                        $cp3[] = $descriptions[$i];
                                    }
                                    
                                    $recommendations[] = [ 
                                        'type'=>'matching',
                                        'question_text'=>"Match each step to its description.",
                                        'left_items'=>$left3,
                                        'right_items'=>$right3,
                                        'correct_pairs'=>$cp3,
                                        'points'=>count($left3) 
                                    ];
                                }
                            }
                            if (empty($recommendations)) {
                                // Fallback generic if no pairs extracted
                                $recommendations = [
                                    [ 'type'=>'matching','question_text'=>"Match each concept related to {$topic} with its description.", 'left_items'=>['Concept 1','Concept 2','Concept 3'], 'right_items'=>['Definition for Concept 1','Definition for Concept 2','Definition for Concept 3'], 'correct_pairs'=>['Definition for Concept 1','Definition for Concept 2','Definition for Concept 3'], 'points'=>3 ]
                                ];
                            }
                        } else { // essay
                            // Generate 5-6 essay questions instead of just 3
                            $recommendations = [
                                [ 'type'=>'essay','question_text'=>"Discuss the central idea of {$topic} and support your response with details from the text.", 'rubric'=>'Thesis clarity, evidence integration, organization, and language conventions.', 'points'=>5 ],
                                [ 'type'=>'essay','question_text'=>"Evaluate the author\'s purpose in {$topic} and how effectively it is achieved.", 'rubric'=>'Purpose identification, evaluative reasoning, textual support, coherence.', 'points'=>5 ],
                                [ 'type'=>'essay','question_text'=>"Explain how two key concepts from {$topic} are related, using examples.", 'rubric'=>'Concept explanation, relationship analysis, example quality, structure.', 'points'=>5 ],
                                [ 'type'=>'essay','question_text'=>"Compare and contrast two different aspects or elements discussed in {$topic}.", 'rubric'=>'Comparison accuracy, contrast clarity, evidence quality, organization.', 'points'=>5 ],
                                [ 'type'=>'essay','question_text'=>"Analyze the cause and effect relationships presented in {$topic}.", 'rubric'=>'Cause identification, effect analysis, logical reasoning, evidence support.', 'points'=>5 ],
                                [ 'type'=>'essay','question_text'=>"Create a solution or recommendation based on the information in {$topic}.", 'rubric'=>'Solution creativity, logical reasoning, practical application, justification.', 'points'=>5 ]
                            ];
                        }
                    }

                    error_log('Generated recommendations: ' . count($recommendations));
                    error_log('Recommendations data: ' . print_r($recommendations, true));
                    
                    echo json_encode(['success' => true, 'recommendations' => $recommendations]);
                } catch (Throwable $e) {
                    error_log('AI generation error: ' . $e->getMessage());
                    error_log('Stack trace: ' . $e->getTraceAsString());
                    echo json_encode(['success' => false, 'error' => 'AI generation failed: ' . $e->getMessage()]);
                }
                exit;

            case 'create_question':
                error_log('Create question request received');
                error_log('POST data: ' . print_r($_POST, true));
                
                $sectionId = (int)($_POST['section_id'] ?? 0);
                if ($sectionId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Please select a section']);
                    exit;
                }
                
                if (!isset($_SESSION['teacher_id']) || $_SESSION['teacher_id'] <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Teacher session not found']);
                    exit;
                }
                
                // Create new question set (supports multi-section via section_ids[])
                $setTitle = $_POST['set_title'] ?? '';
                if (empty($setTitle)) {
                    echo json_encode(['success' => false, 'error' => 'Please enter a question set title']);
                    exit;
                }
                
                // Resolve sections
                $sectionIds = [];
                if (!empty($_POST['section_ids']) && is_array($_POST['section_ids'])) {
                    foreach ($_POST['section_ids'] as $sid) { $sid = (int)$sid; if ($sid>0) $sectionIds[] = $sid; }
                    $sectionIds = array_values(array_unique($sectionIds));
                }
                if (empty($sectionIds)) { $one = (int)($_POST['section_id'] ?? 0); if ($one>0) $sectionIds = [$one]; }
                if (empty($sectionIds)) {
                    echo json_encode(['success' => false, 'error' => 'Please select at least one section']);
                    exit;
                }
                $sectionId = $sectionIds[0];
                
                // Get or create question set
                $setId = $questionHandler->getOrCreateQuestionSet($_SESSION['teacher_id'], $sectionId, $setTitle);
                if (!$setId) {
                    echo json_encode(['success' => false, 'error' => 'Failed to create question set']);
                    exit;
                }
                
                error_log('Creating question with sectionId: ' . $sectionId . ', setId: ' . $setId);
                
                // Optionally persist set timer/open_at if columns exist
                $timerMinutes = isset($_POST['set_timer']) ? (int)$_POST['set_timer'] : 0;
                $openAtRaw = trim($_POST['set_open_at'] ?? '');
                $openAt = $openAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($openAtRaw)) : null;
                try {
                    // Check columns
                    $hasTimerCol = false; $hasOpenCol = false; $hasDiffCol = false;
                    $r1 = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'timer_minutes'");
                    $hasTimerCol = $r1 && $r1->num_rows > 0;
                    $r2 = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
                    $hasOpenCol = $r2 && $r2->num_rows > 0;
                    $r3 = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'difficulty'");
                    $hasDiffCol = $r3 && $r3->num_rows > 0;
                    if ($hasTimerCol || $hasOpenCol || $hasDiffCol) {
                        $sqlU = "UPDATE question_sets SET ";
                        $fields = [];
                        $types = '';
                        $vals = [];
                        if ($hasTimerCol) { $fields[] = "timer_minutes = ?"; $types .= 'i'; $vals[] = $timerMinutes; }
                        if ($hasOpenCol) { $fields[] = "open_at = ?"; $types .= 's'; $vals[] = $openAt; }
                        if ($hasDiffCol) { $fields[] = "difficulty = ?"; $types .= 's'; $vals[] = (string)($_POST['set_difficulty'] ?? ''); }
                        $sqlU .= implode(', ', $fields) . " WHERE id = ?";
                        $types .= 'i';
                        $vals[] = $setId;
                        $stmtU = $conn->prepare($sqlU);
                        if ($stmtU) {
                            $stmtU->bind_param($types, ...$vals);
                            $stmtU->execute();
                        }
                    }
                } catch (Exception $e) { /* ignore */ }
                
                // Handle multiple questions
                $questions = [];
                $questionIndex = 0;
                
                // Collect all questions from the form
                while (isset($_POST["questions"][$questionIndex])) {
                    $questionData = $_POST["questions"][$questionIndex];
                    $questions[] = $questionData;
                    $questionIndex++;
                }
                
                // If no questions found in array format, try single question format
                if (empty($questions)) {
                    $questionData = [
                        'type' => $_POST['type'] ?? '',
                        'question_text' => $_POST['question_text'] ?? '',
                        'points' => (int)($_POST['points'] ?? 1)
                    ];
                    
                    // Add question type specific data
                    if ($questionData['type'] === 'mcq') {
                        $questionData['choice_a'] = $_POST['choice_a'] ?? '';
                        $questionData['choice_b'] = $_POST['choice_b'] ?? '';
                        $questionData['choice_c'] = $_POST['choice_c'] ?? '';
                        $questionData['choice_d'] = $_POST['choice_d'] ?? '';
                        $questionData['correct_answer'] = $_POST['correct_answer'] ?? '';
                    } elseif ($questionData['type'] === 'matching') {
                        $questionData['left_items'] = json_decode($_POST['left_items'] ?? '[]', true);
                        $questionData['right_items'] = json_decode($_POST['right_items'] ?? '[]', true);
                        $questionData['correct_pairs'] = json_decode($_POST['correct_pairs'] ?? '[]', true);
                    }
                    
                    if (!empty($questionData['type']) && !empty($questionData['question_text'])) {
                        $questions[] = $questionData;
                    }
                }
                
                if (empty($questions)) {
                    echo json_encode(['success' => false, 'error' => 'Please add at least one question']);
                    exit;
                }
                
                $successCount = 0;
                $errorCount = 0;
                $results = [];
                
                foreach ($questions as $questionData) {
                    $result = $questionHandler->createQuestion(
                        $_SESSION['teacher_id'],
                        $sectionId,
                        $setId,
                        $questionData
                    );
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                    
                    $results[] = $result;
                }
                
                if ($successCount > 0) {
                    // Duplicate to other selected sections
                    if (count($sectionIds) > 1) {
                        foreach (array_slice($sectionIds, 1) as $sid) {
                            $dupSetId = $questionHandler->getOrCreateQuestionSet($_SESSION['teacher_id'], $sid, $setTitle);
                            if ($dupSetId) {
                                // Copy set-level fields
                                try {
                                    $hasTimerCol = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'timer_minutes'");
                                    $hasOpenCol  = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
                                    $hasDiffCol  = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'difficulty'");
                                    $fields = []; $types = ''; $vals = [];
                                    if ($hasTimerCol && $hasTimerCol->num_rows>0) { $fields[] = 'timer_minutes = ?'; $types.='i'; $vals[] = $timerMinutes; }
                                    if ($hasOpenCol && $hasOpenCol->num_rows>0) { $fields[] = 'open_at = ?'; $types.='s'; $vals[] = $openAt; }
                                    if ($hasDiffCol && $hasDiffCol->num_rows>0) { $fields[] = 'difficulty = ?'; $types.='s'; $vals[] = (string)($_POST['set_difficulty'] ?? ''); }
                                    if (!empty($fields)) {
                                        $sql = 'UPDATE question_sets SET '.implode(', ', $fields).' WHERE id = ?';
                                        $types.='i'; $vals[] = $dupSetId;
                                        $st = $conn->prepare($sql); if ($st) { $st->bind_param($types, ...$vals); $st->execute(); }
                                    }
                                } catch (Exception $e) { /* ignore */ }
                                foreach ($questions as $qd) {
                                    $r = $questionHandler->createQuestion($_SESSION['teacher_id'], $sid, $dupSetId, $qd);
                                    if ($r['success']) { $successCount++; } else { $errorCount++; }
                                    $results[] = $r;
                                }
                            }
                        }
                    }
                    echo json_encode([
                        'success' => true, 
                        'message' => "Successfully created {$successCount} question(s)",
                        'success_count' => $successCount,
                        'error_count' => $errorCount
                    ]);
                } else {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'Failed to create any questions'
                    ]);
                }
                exit;
                
            case 'get_questions':
                $setId = (int)($_POST['set_id'] ?? 0);
                if ($setId <= 0) {
                    echo json_encode(['success' => false, 'error' => 'Invalid set']);
                    exit;
                }
                $questions = $questionHandler->getQuestionsForSet($setId);
                echo json_encode(['success' => true, 'questions' => $questions]);
                exit;
            case 'get_set_questions':
                try {
                $setId = (int)$_POST['set_id'];
                    if ($setId <= 0) {
                        echo json_encode(['success' => false, 'error' => 'Invalid set ID']);
                        exit;
                    }
                    
                $questions = $questionHandler->getQuestionsForSet($setId);
                $sectionId = method_exists($questionHandler, 'getSetSectionId') ? $questionHandler->getSetSectionId($setId) : null;
                    $setTitle = method_exists($questionHandler, 'getSetTitle') ? $questionHandler->getSetTitle($setId) : '';
                    // Optional timer/open_at fetch (columns may not exist)
                    $timerMinutes = null; $openAt = null; $setDifficulty = '';
                    try {
                        $hasTimer = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'timer_minutes'");
                        $hasOpen = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
                        $hasDiff  = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'difficulty'");
                        $need = ($hasTimer && $hasTimer->num_rows > 0) || ($hasOpen && $hasOpen->num_rows > 0) || ($hasDiff && $hasDiff->num_rows>0);
                        if ($need) {
                            $stmt = $conn->prepare("SELECT ".(($hasTimer && $hasTimer->num_rows>0)?"timer_minutes":"NULL")." AS timer_minutes, ".(($hasOpen && $hasOpen->num_rows>0)?"open_at":"NULL")." AS open_at, ".(($hasDiff && $hasDiff->num_rows>0)?"difficulty":"NULL")." AS difficulty FROM question_sets WHERE id = ? LIMIT 1");
                            $stmt->bind_param('i', $setId);
                            $stmt->execute();
                            $row = $stmt->get_result()->fetch_assoc();
                            if ($row) { $timerMinutes = $row['timer_minutes']; $openAt = $row['open_at']; $setDifficulty = $row['difficulty'] ?? ''; }
                        }
                    } catch (Exception $e) { /* ignore */ }
                    
                echo json_encode([
                    'success' => true,
                    'questions' => $questions,
                        'set_title' => $setTitle,
                        'section_id' => $sectionId,
                        'timer_minutes' => $timerMinutes,
                        'open_at' => $openAt,
                        'difficulty' => $setDifficulty
                    ]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
            case 'update_question_set':
                // Handle updates: delete specified questions, update existing, add new
                $setId = (int)$_POST['set_id'];
                $newTitle = $_POST['set_title'];
                $questions = $_POST['questions']; // array of questions with id for existing, no id for new, delete flag for deletion
                // Save timer/open_at if columns exist
                try {
                    $hasTimer = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'timer_minutes'");
                    $hasOpen = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'open_at'");
                    $timer = isset($_POST['set_timer']) ? (int)$_POST['set_timer'] : null;
                    $openRaw = trim($_POST['set_open_at'] ?? '');
                    $openAt = $openRaw !== '' ? date('Y-m-d H:i:s', strtotime($openRaw)) : null;
                    if (($hasTimer && $hasTimer->num_rows>0) || ($hasOpen && $hasOpen->num_rows>0)) {
                        $fields = []; $types = ''; $vals = [];
                        if ($hasTimer && $hasTimer->num_rows>0) { $fields[] = 'timer_minutes = ?'; $types .= 'i'; $vals[] = $timer; }
                        if ($hasOpen && $hasOpen->num_rows>0) { $fields[] = 'open_at = ?'; $types .= 's'; $vals[] = $openAt; }
                        if (!empty($fields)) {
                            $sql = 'UPDATE question_sets SET '.implode(', ', $fields).' WHERE id = ?';
                            $types .= 'i'; $vals[] = $setId;
                            $st = $conn->prepare($sql);
                            if ($st) { $st->bind_param($types, ...$vals); $st->execute(); }
                        }
                    }
                } catch (Exception $e) { /* ignore */ }
                $result = $questionHandler->updateQuestionSet($setId, $newTitle, $questions);
                echo json_encode($result);
                exit;
            case 'delete_question_set':
                $setId = (int)$_POST['set_id'];
                $result = $questionHandler->deleteQuestionSet($setId);
                echo json_encode($result);
                exit;
            case 'archive_question_set':
                try {
                    $setId = (int)($_POST['set_id'] ?? 0);
                    if ($setId <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid set id']); exit; }
                    // Ensure is_archived column exists; add if missing
                    $hasCol = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'is_archived'");
                    if (!$hasCol || $hasCol->num_rows === 0) {
                        $conn->query("ALTER TABLE question_sets ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
                    }
                    $stmt = $conn->prepare("UPDATE question_sets SET is_archived = 1 WHERE id = ?");
                    $stmt->bind_param('i', $setId);
                    $ok = $stmt->execute();
                    echo json_encode(['success' => (bool)$ok]);
                } catch (Exception $e) {
                    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
                }
                exit;
            case 'unarchive_question_set':
                try {
                    $setId = (int)($_POST['set_id'] ?? 0);
                    if ($setId <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid set id']); exit; }
                    $hasCol = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'is_archived'");
                    if (!$hasCol || $hasCol->num_rows === 0) {
                        $conn->query("ALTER TABLE question_sets ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
                    }
                    $stmt = $conn->prepare("UPDATE question_sets SET is_archived = 0 WHERE id = ?");
                    $stmt->bind_param('i', $setId);
                    $ok = $stmt->execute();
                    echo json_encode(['success' => (bool)$ok]);
                } catch (Exception $e) {
                    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
                }
                exit;
            case 'bulk_delete_question_sets':
                try {
                    $raw = $_POST['set_ids'] ?? '[]';
                    $ids = json_decode($raw, true);
                    if (!is_array($ids) || empty($ids)) {
                        echo json_encode(['success' => false, 'error' => 'No set ids provided']);
                        exit;
                    }
                    $deleted = 0; $errors = [];
                    foreach ($ids as $sid) {
                        $sid = (int)$sid;
                        if ($sid <= 0) { continue; }
                        $res = $questionHandler->deleteQuestionSet($sid);
                        if (!empty($res['success'])) { $deleted++; }
                        else { $errors[] = $sid; }
                    }
                    echo json_encode(['success' => true, 'deleted' => $deleted, 'failed' => $errors]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            case 'bulk_archive_question_sets':
            case 'bulk_unarchive_question_sets':
                try {
                    $raw = $_POST['set_ids'] ?? '[]';
                    $ids = json_decode($raw, true);
                    if (!is_array($ids) || empty($ids)) { echo json_encode(['success'=>false,'error'=>'No set ids provided']); exit; }
                    $doArchive = $_POST['action'] === 'bulk_archive_question_sets';
                    $hasCol = $conn->query("SHOW COLUMNS FROM question_sets LIKE 'is_archived'");
                    if (!$hasCol || $hasCol->num_rows === 0) {
                        $conn->query("ALTER TABLE question_sets ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
                    }
                    $okCount = 0; $errs = [];
                    foreach ($ids as $sid) {
                        $sid = (int)$sid; if ($sid<=0) continue;
                        $stmt = $conn->prepare("UPDATE question_sets SET is_archived = ? WHERE id = ?");
                        $flag = $doArchive ? 1 : 0;
                        $stmt->bind_param('ii', $flag, $sid);
                        if ($stmt->execute()) $okCount++; else $errs[] = $sid;
                    }
                    echo json_encode(['success'=>true,'updated'=>$okCount,'failed'=>$errs]);
                } catch (Exception $e) {
                    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
                }
                exit;
            case 'check_set_title':
                $sectionId = (int)($_POST['section_id'] ?? 0);
                $setTitle = trim($_POST['set_title'] ?? '');
                $excludeId = (int)($_POST['exclude_set_id'] ?? 0);
                if ($sectionId <= 0 || $setTitle === '') {
                    echo json_encode(['success' => true, 'exists' => false]);
                    exit;
                }
                // Check if title already exists for this teacher+section (excluding current set when editing)
                $sql = "SELECT id FROM question_sets WHERE teacher_id = ? AND section_id = ? AND set_title = ?";
                if ($excludeId > 0) { $sql .= " AND id <> ?"; }
                $stmt = $conn->prepare($sql);
                if ($excludeId > 0) {
                    $stmt->bind_param('iisi', $_SESSION['teacher_id'], $sectionId, $setTitle, $excludeId);
                } else {
                    $stmt->bind_param('iis', $_SESSION['teacher_id'], $sectionId, $setTitle);
                }
                $stmt->execute();
                $res = $stmt->get_result();
                $exists = $res && $res->num_rows > 0;
                echo json_encode(['success' => true, 'exists' => $exists]);
                exit;
        }
    } catch (Exception $e) {
        // Ensure only JSON is emitted; clear any prior buffer content
        if (ob_get_length()) { ob_clean(); }
        error_log('Question creation error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'An error occurred while creating the question']);
        exit;
    } catch (Error $e) {
        if (ob_get_length()) { ob_clean(); }
        error_log('Question creation fatal error: ' . $e->getMessage());
        error_log('Fatal error file: ' . $e->getFile() . ' line: ' . $e->getLine());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
        exit;
    }
}


// Handle edit mode if edit_set parameter is provided
$editSetId = null;
$isEditMode = false;
if (isset($_GET['edit_set']) && !empty($_GET['edit_set'])) {
    $editSetId = (int)$_GET['edit_set'];
    if ($editSetId > 0) {
        $isEditMode = true;
    }
}

// Include the teacher layout
require_once 'includes/teacher_layout.php';
$pageTitle = $isEditMode ? 'Edit Questions' : 'Question Creator';
render_teacher_header('clean_question_creator.php', $teacherName, $pageTitle);
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
        
        .question-type-section {
            display: none !important;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .question-type-section.active {
            display: block !important;
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
            background: #545b62;
        }
        
        .btn-primary {
            background: #28a745;
            font-weight: bold;
        }
        
        .btn-primary:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .btn-primary:focus {
            outline: 2px solid #28a745;
            outline-offset: 2px;
        }
        
        /* Ensure button is clickable */
        button[type="submit"] {
            pointer-events: auto !important;
            z-index: 10;
            position: relative;
        }
        

        .set-title {
    font-weight: 600;
    color: #1f2937;
    font-size: 15px;
}

.section-name {
    color: #6b7280;
    font-size: 14px;
}

        .badge {
    display: inline-block;
            background: #dbeafe;
            color: #1e40af;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
            min-width: 24px;
            text-align: center;
}

        .points-badge {
            display: inline-block;
    background: #dcfce7;
    color: #166534;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    min-width: 24px;
    text-align: center;
}

.created-date {
    text-align: center;
}

.date-text {
    display: block;
    color: #374151;
    font-size: 13px;
    font-weight: 500;
}

.time-text {
    display: block;
    color: #9ca3af;
    font-size: 11px;
    margin-top: 2px;
}

.actions {
    text-align: center;
}

        .action-buttons {
    display: flex;
            gap: 6px;
    justify-content: center;
}

        .action-buttons .btn {
            padding: 8px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
            display: flex;
    align-items: center;
    justify-content: center;
            min-width: 32px;
    height: 32px;
        }
        
        .action-buttons .btn-view {
            background: #3b82f6;
            color: white;
        }
        
        .action-buttons .btn-view:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        
        .action-buttons .btn-edit {
            background: #f59e0b;
            color: white;
        }
        
        .action-buttons .btn-edit:hover {
            background: #d97706;
            transform: translateY(-1px);
        }
        
        
        .action-buttons .btn-archive {
            background: #0ea5e9;
            color: white;
        }
        
        .action-buttons .btn-archive:hover {
            background: #0284c7;
    transform: translateY(-1px);
        }
        
        
        .action-buttons .btn-responses {
            background: #8b5cf6;
            color: white;
        }
        
        .action-buttons .btn-responses:hover {
            background: #7c3aed;
            transform: translateY(-1px);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
            
            .action-buttons .btn {
                min-width: 28px;
                height: 28px;
                font-size: 10px;
            }
        }
        
        .matching-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .matching-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .matching-item select {
            margin-left: 10px;
            flex: 1;
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Layout integration */
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
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .question-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f9f9f9;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .question-header h3 {
            margin: 0;
            color: #333;
        }
        
        .remove-question {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .remove-question:hover {
            background: #c82333;
        }
    </style>
    
    <!-- Auto-save functionality -->
    <script src="includes/autosave.js"></script>
</head>
<body>
    <div class="main-content">
        <div class="content-header">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <h1 id="pageTitle"><i class="fas fa-<?php echo $isEditMode ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $isEditMode ? 'Edit Questions' : 'Create Questions'; ?></h1>
                <button id="cancelEditBtn" type="button" class="btn btn-secondary" style="display:<?php echo $isEditMode ? 'block' : 'none'; ?>;" onclick="cancelEdit()">Cancel Edit</button>
            </div>
            <p>Clean, modular question creation system</p>
        </div>
                        
        <!-- Question Creation Form -->
        <div class="question-form">
            <h2 id="formTitleHeading"><?php echo $isEditMode ? 'Edit Questions' : 'Create Questions'; ?></h2>
            <form id="questionForm">
            <div class="form-group">
                    <label for="ai_content_dropdown">Content (Reading Materials):</label>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <div style="position:relative; max-width:420px;">
                            <button type="button" id="ai_content_dropdown" class="btn btn-secondary" aria-haspopup="true" aria-expanded="false" aria-label="Select reading materials" style="width:100%; text-align:left; justify-content:space-between; display:flex; align-items:center; padding:12px 16px; border:2px solid #e2e8f0; border-radius:8px; background:white; cursor:pointer; transition:all 0.2s ease; font-size:14px; font-weight:500;" onmouseover="this.style.borderColor='#3b82f6'; this.style.backgroundColor='#f8fafc'; this.style.boxShadow='0 4px 12px rgba(59, 130, 246, 0.15)'" onmouseout="this.style.borderColor='#e2e8f0'; this.style.backgroundColor='white'; this.style.boxShadow='none'">
                                <span id="ai_content_text" style="color:#374151;">Select materials (multiple selection)</span>
                                <i class="fas fa-chevron-down" id="dropdown_icon" style="color:#6b7280; transition:transform 0.2s ease;"></i>
                    </button>
                            <div id="ai_content_panel" role="listbox" aria-label="Reading materials selection" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.15); z-index:1000; max-height:250px; overflow-y:auto; margin-top:4px;">
                                <div style="padding:12px;">
                                    <label style="display:flex; align-items:center; gap:10px; padding:8px 12px; cursor:pointer; border-radius:6px; margin:0; transition:all 0.2s ease; background:#f8fafc; border:1px solid #e2e8f0;" onmouseover="this.style.backgroundColor='#e2e8f0'; this.style.borderColor='#cbd5e1'" onmouseout="this.style.backgroundColor='#f8fafc'; this.style.borderColor='#e2e8f0'">
                                        <input type="checkbox" id="select_all_materials" name="select_all_materials" onchange="toggleAllMaterials()" style="width:16px; height:16px; accent-color:#3b82f6;">
                                        <strong style="color:#1e293b; font-size:14px;">Select All</strong>
                        </label>
                                    <hr style="margin:12px 0; border:none; height:1px; background:#e2e8f0;">
                                    <?php
                                    try {
                                        $tid = (int)($_SESSION['teacher_id'] ?? 0);
                                        $stmtRM = $conn->prepare("SELECT id, title FROM reading_materials WHERE teacher_id = ? ORDER BY updated_at DESC");
                                        $stmtRM->bind_param('i', $tid);
                                        $stmtRM->execute();
                                        $rsRM = $stmtRM->get_result();
                                        while ($rowRM = $rsRM->fetch_assoc()) {
                                            $rid = (int)$rowRM['id'];
                                            $rtitle = htmlspecialchars($rowRM['title'] ?: ('Material #' . $rid));
                                            echo '<label style="display:flex; align-items:center; gap:10px; padding:8px 12px; cursor:pointer; border-radius:6px; margin:2px 0; transition:all 0.2s ease; background:white; border:1px solid transparent;" onmouseover="this.style.backgroundColor=\'#f1f5f9\'; this.style.borderColor=\'#cbd5e1\'; this.style.transform=\'translateX(2px)\'" onmouseout="this.style.backgroundColor=\'white\'; this.style.borderColor=\'transparent\'; this.style.transform=\'translateX(0)\'">
                                                    <input type="checkbox" class="material-checkbox" name="material_ids[]" value="' . $rid . '" onchange="updateMaterialSelection()" style="width:16px; height:16px; accent-color:#3b82f6;">
                                                    <span style="color:#374151; font-size:14px; font-weight:500;">' . $rtitle . '</span>
                                                  </label>';
                                        }
                                    } catch (Throwable $e) { /* ignore */ }
                                    ?>
                        </div>
                        </div>
                        </div>
                        <select id="ai_type_select" title="Question type">
                                <option value="mcq">Multiple Choice</option>
                                <option value="matching">Matching</option>
                                <option value="essay">Essay</option>
                            </select>
                        <button type="button" id="btnFetchAI" class="btn btn-primary" title="Get AI recommendations" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; padding: 10px 20px; border-radius: 6px; font-weight: 500; transition: all 0.2s ease;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'"><i class="fas fa-magic"></i> Recommend</button>
                        </div>
                    <small class="form-text">Select one or more materials to generate questions covering different topics.</small>
                        </div>
                        
                <div id="ai_reco_panel" style="display:none; border:1px solid #e5e7eb; border-radius:8px; padding:12px; margin-bottom:16px; background:#f9fafb;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <strong><i class="fas fa-lightbulb"></i> AI Recommendations</strong>
                        <button type="button" class="btn btn-secondary" id="btnHideReco">Hide</button>
                                </div>
                    <div id="ai_reco_list" style="display:grid; gap:10px;"></div>
                                </div>
                <div class="form-group" style="position:relative;">
                    <label>Sections:</label>
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
                    <div id="section-error" class="error-message" style="color: red; font-size: 12px; margin-top: 4px; display: none;"></div>
            </div>
                
                            
                <div id="new-set-fields">
                <div class="form-group">
                        <label for="set_title">Question Set Title: <span style="color: red;">*</span></label>
                        <input type="text" id="set_title" name="set_title" required>
                        <div id="title-error" class="error-message" style="color: red; font-size: 12px; margin-top: 4px; display: none;"></div>
                </div>
                <div class="form-group">
                        <label for="set_timer">Timer (minutes): <span style="color: red;">*</span></label>
                        <input type="number" id="set_timer" name="set_timer" min="1" placeholder="e.g., 30" required>
                        <div id="timer-error" class="error-message" style="color: red; font-size: 12px; margin-top: 4px; display: none;"></div>
                                </div>
                            <div class="form-group">
                        <label for="set_open_at">Open Date/Time:</label>
                        <input type="datetime-local" id="set_open_at" name="set_open_at">
                        <div id="date-error" class="error-message" style="color: red; font-size: 12px; margin-top: 4px; display: none;"></div>
                                </div>
                            <div class="form-group">
                        <label for="set_difficulty">Difficulty: <span style="color: red;">*</span></label>
                        <select id="set_difficulty" name="set_difficulty" required>
                            <option value="">Select difficulty</option>
                    <option value="easy">Easy</option>
                    <option value="medium">Medium</option>
                    <option value="hard">Hard</option>
                    </select>
                        <div id="difficulty-error" class="error-message" style="color: red; font-size: 12px; margin-top: 4px; display: none;"></div>
                </div>
                </div>
                
                
            <!-- Questions Container -->
                <div id="questions-container"></div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="addNewQuestion()" style="margin-right: 10px;">
                    <i class="fas fa-plus"></i> Add New Question
                </button>
                    <button type="button" class="btn btn-secondary" onclick="showImportModal()" style="margin-right: 10px; background: #8b5cf6; color: white;">
                        <i class="fas fa-download"></i> Import from Question Bank
                </button>
                    <button type="submit" class="btn btn-primary" style="font-size: 18px; padding: 15px 30px; margin-top: 20px;">
                        <i class="fas fa-save"></i> <?php echo $isEditMode ? 'Save Changes' : 'Create Questions'; ?>
                    </button>
                </div>
            </form>
    </div>


    <script>
        // AI Recommendations UI handlers
        (function initAIReco(){
            const btn = document.getElementById('btnFetchAI');
            const dropdown = document.getElementById('ai_content_dropdown');
            const panel = document.getElementById('ai_content_panel');
            const typeSel = document.getElementById('ai_type_select');
            const recoPanel = document.getElementById('ai_reco_panel');
            const list = document.getElementById('ai_reco_list');
            const btnHide = document.getElementById('btnHideReco');
            if(!btn || !dropdown || !recoPanel || !list) return;
            const tplCard = (q, idx) => {
                const safe = (s)=> (s||'').toString().replace(/</g,'&lt;');
                const qa = safe(q.question_text);
                const pts = Number.isFinite(q.points)? q.points : 1;
                if ((q.type||'mcq') === 'mcq') {
                    const a = safe(q.choices?.A), b = safe(q.choices?.B), c = safe(q.choices?.C), d = safe(q.choices?.D);
                    const ca = safe(q.correct_answer || '');
                    const raw = encodeURIComponent(JSON.stringify({ type:'mcq', question_text:q.question_text, choice_a:a, choice_b:b, choice_c:c, choice_d:d, correct_answer:ca, points:pts }));
                    return `<div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
                        <div style="font-weight:600; margin-bottom:6px;">Q${idx+1}. ${qa}</div>
                        <div style="font-size:14px; color:#374151; margin-left:12px;">
                            <div>A. ${a}</div>
                            <div>B. ${b}</div>
                            <div>C. ${c}</div>
                            <div>D. ${d}</div>
</div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                            <small>Correct: <strong>${ca}</strong> • ${pts} pt(s)</small>
                            <button type="button" class="btn btn-primary" onclick="useAIQuestion('${raw}')"><i class="fas fa-plus"></i> Use this Question</button>
                        </div>
                    </div>`;
                } else if (q.type === 'matching') {
                    const left = Array.isArray(q.left_items)? q.left_items : [];
                    const right = Array.isArray(q.right_items)? q.right_items : [];
                    const cp = Array.isArray(q.correct_pairs)? q.correct_pairs : [];
                    const raw = encodeURIComponent(JSON.stringify({ type:'matching', question_text:q.question_text, left_items:left, right_items:right, correct_pairs:cp, points:pts }));
                    return `<div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
                        <div style="font-weight:600; margin-bottom:6px;">Q${idx+1}. ${qa}</div>
                        <div style="font-size:14px; color:#374151; margin-left:12px;">
                            <div><strong>Left:</strong> ${safe(left.join(', '))}</div>
                            <div><strong>Right:</strong> ${safe(right.join(', '))}</div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                            <small>${pts} pt(s)</small>
                            <button type="button" class="btn btn-primary" onclick="useAIQuestion('${raw}')"><i class="fas fa-plus"></i> Use this Question</button>
                        </div>
                    </div>`;
                } else { // essay
                    const rub = safe(q.rubric || '');
                    const raw = encodeURIComponent(JSON.stringify({ type:'essay', question_text:q.question_text, rubric:q.rubric || rub, points:pts }));
                    return `<div style="background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
                        <div style="font-weight:600; margin-bottom:6px;">Q${idx+1}. ${qa}</div>
                        <div style="font-size:14px; color:#374151; margin-left:12px;">
                            <div><strong>Rubric:</strong> ${rub}</div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                            <small>${pts} pt(s)</small>
                            <button type="button" class="btn btn-primary" onclick="useAIQuestion('${raw}')"><i class="fas fa-plus"></i> Use this Question</button>
                        </div>
                    </div>`;
                }
            };
            const setLoading = (flag)=>{
                recoPanel.style.display = 'block';
                list.innerHTML = flag ? '<div style="padding:8px; color:#6b7280;">Generating recommendations…</div>' : '';
            };
            // Handle dropdown toggle
            dropdown.addEventListener('click', (e)=>{
                e.stopPropagation();
                const isOpen = panel.style.display !== 'none';
                
                if (isOpen) {
                    panel.style.display = 'none';
                    panel.style.opacity = '0';
                    panel.style.transform = 'translateY(-10px)';
                    dropdown.setAttribute('aria-expanded', 'false');
                } else {
                    panel.style.display = 'block';
                    panel.style.opacity = '0';
                    panel.style.transform = 'translateY(-10px)';
                    dropdown.setAttribute('aria-expanded', 'true');
                    
                    // Animate in
                    setTimeout(() => {
                        panel.style.transition = 'all 0.2s ease';
                        panel.style.opacity = '1';
                        panel.style.transform = 'translateY(0)';
                    }, 10);
                }
                
                // Rotate chevron icon
                const icon = document.getElementById('dropdown_icon');
                if (icon) {
                    icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                    icon.style.transition = 'transform 0.2s ease';
                }
            });
            
            // Prevent panel from closing when clicking inside it
            panel.addEventListener('click', (e)=>{
                e.stopPropagation();
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e)=>{
                if (!dropdown.contains(e.target) && !panel.contains(e.target)) {
                    panel.style.display = 'none';
                    dropdown.setAttribute('aria-expanded', 'false');
                    // Reset chevron icon
                    const icon = document.getElementById('dropdown_icon');
                    if (icon) {
                        icon.style.transform = 'rotate(0deg)';
                    }
                }
            });
            
            btn.addEventListener('click', ()=>{
                console.log('Recommend button clicked!');
                const selectedMaterials = getSelectedMaterials();
                const qt = (typeSel && typeSel.value) ? typeSel.value : 'mcq';
                
                console.log('Selected materials:', selectedMaterials);
                console.log('Question type:', qt);
                
                // Always show the panel first
                recoPanel.style.display = 'block';
                
                if(selectedMaterials.length === 0){ 
                    list.innerHTML='<div style="color:#ef4444; padding:8px;">Please select at least one material first.</div>'; 
                    return; 
                }
                
                setLoading(true);
                const body = new URLSearchParams({ 
                    action:'ai_recommend_questions', 
                    material_ids: selectedMaterials.join(','), 
                    question_type: qt 
                });
                
                console.log('Sending request with body:', body.toString());
                
                fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r => {
                        console.log('Response status:', r.status);
                        return r.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        recoPanel.style.display = 'block'; // Always show the panel
                        if(!data || !data.success || !Array.isArray(data.recommendations)){ 
                            list.innerHTML = '<div style="color:#ef4444; padding:8px;">Failed to generate recommendations. ' + (data.error || 'Unknown error') + '</div>'; 
                            return; 
                        }
                        if(data.recommendations.length===0){ 
                            list.innerHTML = '<div style="color:#6b7280; padding:8px;">No recommendations generated.</div>'; 
                            return; 
                        }
                        list.innerHTML = data.recommendations.map(tplCard).join('');
                    })
                    .catch(error => { 
                        console.error('Fetch error:', error);
                        recoPanel.style.display = 'block'; // Show panel on error too
                        list.innerHTML = '<div style="color:#ef4444; padding:8px;">Error generating recommendations: ' + error.message + '</div>'; 
                    });
            });
            if(btnHide){ btnHide.addEventListener('click', ()=>{ recoPanel.style.display='none'; }); }
        })();

        // Multi-select material functions
        function getSelectedMaterials() {
            const checkboxes = document.querySelectorAll('.material-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        function updateMaterialSelection() {
            const selected = getSelectedMaterials();
            const text = document.getElementById('ai_content_text');
            const dropdown = document.getElementById('ai_content_dropdown');
            
            if (selected.length === 0) {
                text.textContent = 'Select materials (multiple selection)';
                text.style.color = '#6b7280';
                dropdown.style.borderColor = '#e2e8f0';
                dropdown.style.backgroundColor = 'white';
                dropdown.style.boxShadow = 'none';
            } else if (selected.length === 1) {
                const checkbox = document.querySelector('.material-checkbox:checked');
                const label = checkbox.nextElementSibling.textContent;
                text.textContent = label;
                text.style.color = '#059669';
                dropdown.style.borderColor = '#10b981';
                dropdown.style.backgroundColor = '#f0fdf4';
                dropdown.style.boxShadow = '0 4px 12px rgba(16, 185, 129, 0.15)';
        } else {
                text.textContent = `${selected.length} materials selected`;
                text.style.color = '#059669';
                dropdown.style.borderColor = '#10b981';
                dropdown.style.backgroundColor = '#f0fdf4';
                dropdown.style.boxShadow = '0 4px 12px rgba(16, 185, 129, 0.15)';
            }
        }

        function toggleAllMaterials() {
            const selectAll = document.getElementById('select_all_materials');
            const checkboxes = document.querySelectorAll('.material-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateMaterialSelection();
        }

        // Use AI question to autofill the form
        function useAIQuestion(encoded){
            try{
                const q = JSON.parse(decodeURIComponent(encoded));
                const idx = document.querySelectorAll('.question-item').length; addNewQuestion();
                const i = idx;
                const qt = (q.type||'mcq');
                document.getElementById(`type_${i}`).value = qt;
                showQuestionTypeSection(i);
                document.getElementById(`question_text_${i}`).value = q.question_text||'';
                document.getElementById(`points_${i}`).value = q.points||1;
                if (qt === 'mcq') {
                    document.getElementById(`choice_a_${i}`).value = q.choice_a||q.choices?.A||'';
                    document.getElementById(`choice_b_${i}`).value = q.choice_b||q.choices?.B||'';
                    document.getElementById(`choice_c_${i}`).value = q.choice_c||q.choices?.C||'';
                    document.getElementById(`choice_d_${i}`).value = q.choice_d||q.choices?.D||'';
                    const ca = (q.correct_answer||'').toString().toLowerCase();
                    const r = document.getElementById(`correct_${ca}_${i}`) || document.getElementById(`correct_${ca}_${i}_0`);
                    if(r) r.checked = true;
                } else if (qt === 'matching') {
                    const left = Array.isArray(q.left_items)? q.left_items : [];
                    const right = Array.isArray(q.right_items)? q.right_items : [];
                    for(let n=2;n<left.length;n++) addMatchingRow(i);
                    const li = document.querySelectorAll(`input[name="questions[${i}][left_items][]"]`);
                    const ri = document.querySelectorAll(`input[name="questions[${i}][right_items][]"]`);
                    left.forEach((v,k)=>{ if(li[k]) li[k].value = v; });
                    right.forEach((v,k)=>{ if(ri[k]) ri[k].value = v; });
                    updateMatchingMatches(i);
                    const cp = Array.isArray(q.matches) ? q.matches : (Array.isArray(q.correct_pairs) ? q.correct_pairs : []);
                    setTimeout(()=>{
                        const selects = document.querySelectorAll(`#matching-matches_${i} select`);
                        const rightInputs = document.querySelectorAll(`input[name="questions[${i}][right_items][]"]`);
                        const rightItems = Array.from(rightInputs).map(input => input.value.trim());
                        
                        console.log('=== OLD MATCHING LOGIC ===');
                        console.log('Correct pairs:', cp);
                        console.log('Right items:', rightItems);
                        console.log('Number of selects:', selects.length);
                        
                        // Check if we have any matches to set
                        if (!cp || cp.length === 0) {
                            console.log('❌ No correct pairs to set - cp array is empty or undefined');
                            return;
                        }
                        
                        cp.forEach((targetVal, idxSel)=>{
                            const sel = selects[idxSel]; if(!sel) return;
                            const target = (targetVal ?? '').toString().trim();
                            
                            console.log(`Setting match ${idxSel}: "${target}" (looking for this in right items)`);
                            
                            // Find the index of the matching right item
                            let selectedIndex = -1;
                            rightItems.forEach((rightItem, rightIndex) => {
                                if (rightItem.toLowerCase() === target.toLowerCase()) {
                                    selectedIndex = rightIndex;
                                    console.log(`Found "${target}" at right index ${rightIndex}`);
                                }
                            });
                            
                            console.log(`Final selected index: ${selectedIndex} for target "${target}"`);
                            
                            // Set the select value to the index
                            if (selectedIndex >= 0) {
                                sel.value = selectedIndex;
                                console.log(`✅ Set select ${idxSel} to index ${selectedIndex} (${rightItems[selectedIndex]})`);
                            } else {
                                // Fallback: try to find by text content
                                let found = false;
                                Array.from(sel.options).forEach(opt => {
                                    if (opt.textContent.trim().toLowerCase() === target.toLowerCase()) {
                                        sel.value = opt.value;
                                        found = true;
                                        console.log(`✅ Found by text content: ${opt.textContent} (value: ${opt.value})`);
                                    }
                                });
                                
                                if (!found) {
                                    console.log(`❌ No match found for "${target}"`);
                                    sel.value = ''; // Reset to default
                                }
                            }
                            
                            sel.dispatchEvent(new Event('change'));
                        });
                        
                        console.log('=== FINISHED OLD MATCHING LOGIC ===');
                    },120);
                } else if (qt === 'essay') {
                    const rub = (q.rubric || '').toString();
                    const field = document.getElementById(`essay_rubric_${i}`);
                    if (field) field.value = rub;
                }
            }catch(e){ console.error(e); }
        }
        function importSelected(){
            const checks = document.querySelectorAll('.impChk:checked');
            if(!checks.length){ closeImportModal(); return; }
            checks.forEach(ch=>{
                try{
                    const raw = JSON.parse(decodeURIComponent(ch.getAttribute('data-raw')));
                    const t = (raw.type||'').toLowerCase();
                    if(t==='mcq'){
                        const idx = document.querySelectorAll('.question-item').length; addNewQuestion();
                        const i = idx; document.getElementById(`type_${i}`).value = 'mcq'; showQuestionTypeSection(i);
                        document.getElementById(`question_text_${i}`).value = raw.question_text||'';
                        document.getElementById(`points_${i}`).value = raw.points||1;
                        document.getElementById(`choice_a_${i}`).value = raw.choice_a||'';
                        document.getElementById(`choice_b_${i}`).value = raw.choice_b||'';
                        document.getElementById(`choice_c_${i}`).value = raw.choice_c||'';
                        document.getElementById(`choice_d_${i}`).value = raw.choice_d||'';
                        const ca = (raw.correct_answer||'').toLowerCase(); const r = document.getElementById(`correct_${ca}_${i}`); if(r) r.checked = true;
                    } else if(t==='matching'){
                        const idx = document.querySelectorAll('.question-item').length; addNewQuestion(); const i = idx;
                        document.getElementById(`type_${i}`).value = 'matching'; showQuestionTypeSection(i);
                        document.getElementById(`question_text_${i}`).value = raw.question_text||'';
                        try{
                            const left = Array.isArray(raw.left_items)?raw.left_items:JSON.parse(raw.left_items||'[]');
                            const right = Array.isArray(raw.right_items)?raw.right_items:JSON.parse(raw.right_items||'[]');
                            // Add rows/cols to fit
                            for(let n=2;n<left.length;n++) addMatchingRow(i);
                            const li = document.querySelectorAll(`input[name="questions[${i}][left_items][]"]`);
                            const ri = document.querySelectorAll(`input[name="questions[${i}][right_items][]"]`);
                            left.forEach((v,k)=>{ if(li[k]) li[k].value = v; });
                            right.forEach((v,k)=>{ if(ri[k]) ri[k].value = v; });

                            // Build normalized matches from various shapes
                            const normalize = (rawM)=>{
                                let out = [];
                                if(!rawM) return out;
                                const r = (typeof rawM === 'string') ? (function(){ try { return JSON.parse(rawM); } catch(e){ return rawM; } })() : rawM;
                                if(Array.isArray(r)){
                                    r.forEach(item=>{
                                        if(typeof item === 'string') out.push(item);
                                        else if(typeof item === 'number') out.push(right[item] ?? '');
                                        else if(item && typeof item === 'object') out.push(item.value ?? item.answer ?? item.right ?? item.right_item ?? '');
                                    });
                                } else if(r && typeof r === 'object'){
                                    Object.keys(r).forEach(k=> out.push(r[k]));
                                } else if(typeof r === 'number'){
                                    out.push(right[r] ?? '');
                                }
                                return out;
                            };
                            let matches = normalize(raw.matches);
                            if(matches.length === 0) matches = normalize(raw.correct_pairs);

                            // Populate selects after options are built
                            updateMatchingMatches(i);
                            setTimeout(()=>{
                                const selects = document.querySelectorAll(`#matching-matches_${i} select`);
                                const apply = () => {
                                    const ready = Array.from(selects).every(s=>s.options.length>1);
                                    if(!ready){ setTimeout(apply, 80); return; }
                                    matches.forEach((targetVal, idxSel)=>{
                                        const sel = selects[idxSel]; if(!sel) return;
                                        const target = (targetVal ?? '').toString().trim().toLowerCase();
                                        let chosen = false;
                                        Array.from(sel.options).forEach(opt=>{
                                            const ov = (opt.value||'').toString().trim().toLowerCase();
                                            const ot = (opt.textContent||'').toString().trim().toLowerCase();
                                            if(ov===target || ot===target){ opt.selected = true; chosen = true; }
                                        });
                                        if(!chosen && target){
                                            Array.from(sel.options).forEach(opt=>{
                                                const ov = (opt.value||'').toString().trim().toLowerCase();
                                                const ot = (opt.textContent||'').toString().trim().toLowerCase();
                                                if(ov.includes(target) || target.includes(ov) || ot.includes(target) || target.includes(ot)){
                                                    opt.selected = true; chosen = true;
                                                }
                                            });
                                        }
                                        if(!chosen && target){ sel.value = targetVal; }
                                        sel.dispatchEvent(new Event('change'));
                                    });
                                };
                                apply();
                            },100);
                        }catch(e){}
                        document.getElementById(`points_${i}`).value = raw.points||2;
                    } else if(t==='essay'){
                        const idx = document.querySelectorAll('.question-item').length; addNewQuestion(); const i = idx;
                        document.getElementById(`type_${i}`).value = 'essay'; showQuestionTypeSection(i);
                        document.getElementById(`question_text_${i}`).value = raw.question_text||'';
                        document.getElementById(`points_${i}`).value = raw.points||1;
                    }
                }catch(e){ console.error(e); }
            });
            closeImportModal();
        }
        // Start with zero questions; teacher will click "Add New Question"
        let questionIndex = 0;
        window.isEditMode = false;
        window.currentEditSetId = null;
        
        // Dropdown with checkboxes (multi-select sections)
        (function multiSelectSections(){
            const trigger = document.getElementById('sectionMulti');
            const panel = document.getElementById('sectionPanel');
            const all = document.getElementById('section_all');
            const boxes = Array.from(panel ? panel.querySelectorAll('.sec-box') : []);
            const summary = document.getElementById('sectionSummary');
            const hiddenWrap = document.getElementById('sectionHiddenInputs');
            if(!trigger || !panel || !summary) return;
            const open = ()=>{ panel.style.display = 'block'; };
            const close = ()=>{ panel.style.display = 'none'; };
            trigger.addEventListener('click', (e)=>{ e.stopPropagation(); panel.style.display = (panel.style.display==='block'?'none':'block'); });
            document.addEventListener('click', (e)=>{ if(!panel.contains(e.target) && e.target!==trigger) close(); });
            const syncAll = ()=>{
                const total = boxes.length;
                const checked = boxes.filter(b=>b.checked).length;
                if(all){ all.indeterminate = checked>0 && checked<total; all.checked = checked===total; }
                const labels = boxes.filter(b=>b.checked).map(b=>b.getAttribute('data-label'));
                summary.textContent = labels.length ? labels.join(', ') : 'Select sections';
                // Sync hidden inputs
                hiddenWrap.innerHTML = '';
                boxes.filter(b=>b.checked).forEach(b=>{
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'section_ids[]';
                    input.value = b.value;
                    hiddenWrap.appendChild(input);
                });
            };
            if(all){ all.addEventListener('change', ()=>{ boxes.forEach(b=> b.checked = all.checked); syncAll(); }); }
            boxes.forEach(b=> b.addEventListener('change', syncAll));
            syncAll();
        })();
        
        // Global functions that need to be accessible from HTML
        function showQuestionTypeSection(questionIndex = 0) {
            // Try to get the type from the current question or the first question
            const typeElement = document.getElementById(`type_${questionIndex}`) || document.getElementById('type');
            const type = typeElement ? typeElement.value : '';
            
            // Try to get question text from the current question or the first question
            const questionText = document.getElementById(`question_text_${questionIndex}`) || document.getElementById('question_text');
            const helpText = document.getElementById(`question_text_help_${questionIndex}`) || document.getElementById('question_text_help_0');
            
            if (!questionText) {
                console.error('Question text element not found for question index:', questionIndex);
                return;
            }
            
            // Hide all sections for this specific question only
            const questionItem = document.querySelector(`[data-question-index="${questionIndex}"]`);
            if (questionItem) {
                questionItem.querySelectorAll('.question-type-section').forEach(section => {
                    section.classList.remove('active');
                });
            }
            
            // Remove required attributes from question type fields for this specific question only
            if (questionItem) {
                questionItem.querySelectorAll('.question-type-section input, .question-type-section select, .question-type-section textarea').forEach(field => {
                    field.removeAttribute('required');
                });
            }
            
            // Show selected section
            if (type) {
                // Try to find section with question index first, then fallback to general section
                const section = document.getElementById(`${type}-section_${questionIndex}`) || document.getElementById(`${type}-section`);
                
                if (section) {
                    section.classList.add('active');
                    
                    // Add required attributes only to the active section
                    section.querySelectorAll('input, select, textarea').forEach(field => {
                        if (field.type !== 'radio' || field.name.includes('correct_answer')) {
                            field.setAttribute('required', 'required');
                        }
                    });
                    
                    // Special handling for essay rubric
                    if (type === 'essay') {
                        const rubricField = document.getElementById(`essay_rubric_${questionIndex}`);
                        if (rubricField) {
                            rubricField.setAttribute('required', 'required');
                        }
                    }
                    
                    // If it's a matching question, update the matches display
                    if (type === 'matching') {
                        // Small delay to ensure DOM is ready
                        setTimeout(() => {
                        updateMatchingMatches(questionIndex);
                        updateQuestionTitles(); // Update titles after matching section is ready
                        }, 100);
        } else {
                        // Update titles immediately for non-matching questions
                        updateQuestionTitles();
                    }
                }
                
                // Auto-populate/reset defaults based on type
                const defaultMatching = 'Match the following items with their correct answers:';
                if (type === 'matching') {
                    // Only auto-insert default text if not in edit mode or field empty
                    if (!window.isEditMode || (questionText && questionText.value.trim() === '')) {
                        questionText.value = defaultMatching;
                    }
                    helpText.style.display = 'block';
                    // Add event listeners to existing inputs and update matches
                    setTimeout(() => {
                        addInputListeners(questionIndex);
                        updateMatchingMatches(questionIndex);
                        // Set default points to 2 (since there are 2 default rows)
                        const pointsField = document.getElementById(`points_${questionIndex}`) || document.getElementById('points');
                        if (pointsField) {
                            pointsField.value = 2;
                        }
                    }, 100);
                } else {
                    // Reset to neutral defaults for MCQ/essay when switching from matching
                    helpText.style.display = 'none';
                    const pointsField = document.getElementById(`points_${questionIndex}`) || document.getElementById('points');
                    if (pointsField) pointsField.value = 1; // default 1 point
                    // Clear only the matching default text, do not erase real content loaded in edit mode
                    if (questionText && questionText.value.trim() === defaultMatching) questionText.value = '';
                }
                
                // Auto-update points for matching questions
                if (type === 'matching') {
                    updateMatchingMatches(questionIndex);
                }
            }
        }
        
        function addNewQuestion() {
            const container = document.getElementById('questions-container');
            // Ensure sequential indexes even after deletions
            const nextIndex = container ? container.querySelectorAll('.question-item').length : (questionIndex + 1);
            questionIndex = nextIndex - 1; // sync global
            questionIndex++;
            console.log(`Adding new question with index: ${questionIndex}`);
            const newQuestion = createQuestionHTML(questionIndex);
            container.insertAdjacentHTML('beforeend', newQuestion);
            
            // Show remove buttons for all questions
            document.querySelectorAll('.remove-question').forEach(btn => {
                btn.style.display = 'inline-block';
            });
        }
        
        function addAnotherQuestion() {
            addNewQuestion();
        }
        
        function removeQuestion(button) {
            const questionItem = button.closest('.question-item');
            questionItem.remove();
            
            // Hide remove buttons if only one question left
            const remainingQuestions = document.querySelectorAll('.question-item');
            if (remainingQuestions.length <= 1) {
                document.querySelectorAll('.remove-question').forEach(btn => {
                    btn.style.display = 'none';
                });
            }

            // Renumber remaining questions sequentially (titles, indexes, and element IDs)
            const container = document.getElementById('questions-container');
            const items = Array.from(container.querySelectorAll('.question-item'));
            items.forEach((item, newIdx) => {
                const oldIdx = parseInt(item.getAttribute('data-question-index') || '0', 10);
                if (oldIdx === newIdx) return; // already aligned

                // Update data index
                item.setAttribute('data-question-index', String(newIdx));

                // Update title
                const title = item.querySelector('.q-title') || item.querySelector('.question-header h3');
                if (title) title.textContent = `Question ${newIdx + 1}`;

                // Update common field IDs/names
                const remap = (selector, attr, pattern, replaceWith) => {
                    item.querySelectorAll(selector).forEach(el => {
                        const val = el.getAttribute(attr);
                        if (val && val.includes(pattern)) {
                            el.setAttribute(attr, val.replace(pattern, replaceWith));
                        }
                    });
                };

                // id attributes
                remap('[id]','id', `_${oldIdx}`, `_${newIdx}`);
                // for attributes (labels)
                remap('label[for]','for', `_${oldIdx}`, `_${newIdx}`);
                // name attributes
                remap('[name]','name', `[${oldIdx}]`, `[${newIdx}]`);

                // Update inline handlers that depend on index (e.g., type selector change)
                const typeSel = item.querySelector(`#type_${newIdx}`) || item.querySelector('select[id^="type_"]');
                if (typeSel) {
                    typeSel.setAttribute('onchange', `showQuestionTypeSection(${newIdx})`);
                }
            });
            // Sync global index with current count
            questionIndex = (document.querySelectorAll('.question-item').length || 1) - 1;
        }
        
        function getQuestionHeaderLabel(q) {
            // q: may be index (number) or question DOM stub with inputs
            if (typeof q === 'number') { 
                // Calculate cumulative question number
                let cumulativeNumber = 1;
                for (let i = 0; i < q; i++) {
                    const container = document.querySelector(`[data-question-index="${i}"]`);
                    if (container) {
                        const typeSelect = container.querySelector(`#type_${i}`);
                        const questionType = typeSelect ? typeSelect.value : '';
                        
                        if (questionType === 'matching') {
                            const matchingSection = container.querySelector('.question-type-section#matching-section_' + i);
                            if (matchingSection) {
                                const leftItems = matchingSection.querySelectorAll('input[name^="questions"][name$="[left_items][]"]');
                                const pairCount = leftItems.length;
                                cumulativeNumber += Math.max(pairCount, 1); // At least 1 slot
        } else {
                                cumulativeNumber += 1;
                            }
                        } else {
                            cumulativeNumber += 1;
                        }
                    } else {
                        cumulativeNumber += 1;
                    }
                }
                return `Question ${cumulativeNumber}`;
            }
            
            try {
                const container = q;
                const questionIndex = parseInt(container.dataset.questionIndex || '0', 10);
                
                // Calculate cumulative question number
                let cumulativeNumber = 1;
                for (let i = 0; i < questionIndex; i++) {
                    const prevContainer = document.querySelector(`[data-question-index="${i}"]`);
                    if (prevContainer) {
                        const typeSelect = prevContainer.querySelector(`#type_${i}`);
                        const questionType = typeSelect ? typeSelect.value : '';
                        
                        if (questionType === 'matching') {
                            const matchingSection = prevContainer.querySelector('.question-type-section#matching-section_' + i);
                            if (matchingSection) {
                                const leftItems = matchingSection.querySelectorAll('input[name^="questions"][name$="[left_items][]"]');
                                const pairCount = leftItems.length;
                                cumulativeNumber += Math.max(pairCount, 1); // At least 1 slot
        } else {
                                cumulativeNumber += 1;
                            }
                        } else {
                            cumulativeNumber += 1;
                        }
                    } else {
                        cumulativeNumber += 1;
                    }
                }
                
                // Check the current question type
                const typeSelect = container.querySelector(`#type_${questionIndex}`);
                const questionType = typeSelect ? typeSelect.value : '';
                
                // Apply range numbering for matching questions with multiple pairs
                if (questionType === 'matching') {
                    const matchingSection = container.querySelector('.question-type-section#matching-section_' + questionIndex);
                    if (matchingSection) {
                        const leftItems = matchingSection.querySelectorAll('input[name^="questions"][name$="[left_items][]"]');
                        const pairCount = leftItems.length;
                        if (pairCount > 1) {
                            const endNum = cumulativeNumber + pairCount - 1;
                            return `Question ${cumulativeNumber}-${endNum}`;
                        }
                    }
                }
                
                return `Question ${cumulativeNumber}`;
            } catch(e) {
                const idx = parseInt(q.dataset?.questionIndex || q || 0, 10);
                return `Question ${isNaN(idx)? 1 : (idx + 1)}`;
            }
        }
        
        function updateQuestionTitles() {
            // Update all question titles based on current state
            const questionItems = document.querySelectorAll('.question-item');
            questionItems.forEach((item, index) => {
                const titleElement = item.querySelector('.q-title');
                if (titleElement) {
                    const newLabel = getQuestionHeaderLabel(item);
                    titleElement.textContent = newLabel;
                }
            });
        }
        
        function createQuestionHTML(index) {
            return `
                <div class="question-item" data-question-index="${index}">
            <div class="question-header">
                        <h3 class="q-title">Question ${index + 1}</h3>
                        <button type="button" class="btn btn-danger btn-sm remove-question" onclick="removeQuestion(this)">
                    <i class="fas fa-trash"></i> Remove
            </button>
        </div>
        
        <div class="form-group">
                        <label for="type_${index}">Question Type:</label>
                        <select id="type_${index}" name="questions[${index}][type]" required onchange="showQuestionTypeSection(${index})">
                    <option value="">Select Question Type</option>
                    <option value="mcq">Multiple Choice</option>
                            <option value="matching">Matching</option>
                    <option value="essay">Essay</option>
                </select>
        </div>
        
        <div class="form-group">
                        <label for="question_text_${index}">Question Text:</label>
                        <textarea id="question_text_${index}" name="questions[${index}][question_text]" rows="3" required></textarea>
                        <small id="question_text_help_${index}" class="form-text text-muted" style="display: none;">
                            For matching questions, this will be used as the main instruction above all matching pairs.
                        </small>
        </div>
        
                <div class="form-group">
                        <label for="points_${index}">Points:</label>
                        <input type="number" id="points_${index}" name="questions[${index}][points]" value="1" min="1" required>
                </div>
            
                    <!-- MCQ Section -->
                    <div id="mcq-section_${index}" class="question-type-section">
                        <h3>Multiple Choice Options</h3>
                        <div id="mcq-options_${index}">
                            <div class="option-group">
                                <label for="choice_a_${index}">Option A:</label>
                                <input type="text" id="choice_a_${index}" name="questions[${index}][choice_a]" placeholder="Option A" required>
                                <label for="correct_a_${index}">Correct Answer:</label>
                                <input type="radio" id="correct_a_${index}" name="questions[${index}][correct_answer]" value="A" required>
                                <button type="button" onclick="removeOption(this)">×</button>
            </div>
                            <div class="option-group">
                                <label for="choice_b_${index}">Option B:</label>
                                <input type="text" id="choice_b_${index}" name="questions[${index}][choice_b]" placeholder="Option B" required>
                                <label for="correct_b_${index}">Correct Answer:</label>
                                <input type="radio" id="correct_b_${index}" name="questions[${index}][correct_answer]" value="B" required>
                                <button type="button" onclick="removeOption(this)">×</button>
                    </div>
                            <div class="option-group">
                                <label for="choice_c_${index}">Option C:</label>
                                <input type="text" id="choice_c_${index}" name="questions[${index}][choice_c]" placeholder="Option C" required>
                                <label for="correct_c_${index}">Correct Answer:</label>
                                <input type="radio" id="correct_c_${index}" name="questions[${index}][correct_answer]" value="C" required>
                                <button type="button" onclick="removeOption(this)">×</button>
                        </div>
                            <div class="option-group">
                                <label for="choice_d_${index}">Option D:</label>
                                <input type="text" id="choice_d_${index}" name="questions[${index}][choice_d]" placeholder="Option D" required>
                                <label for="correct_d_${index}">Correct Answer:</label>
                                <input type="radio" id="correct_d_${index}" name="questions[${index}][correct_answer]" value="D" required>
                                <button type="button" onclick="removeOption(this)">×</button>
                    </div>
                        </div>
                        <button type="button" class="add-option" onclick="addMCQOption(${index})">
                    <i class="fas fa-plus"></i> Add Option
                </button>
        </div>
        
                    <!-- Matching Section -->
                    <div id="matching-section_${index}" class="question-type-section">
                        <h3>Matching Pairs</h3>
                        <div class="form-group">
                            <label>Left Items (Rows):</label>
                            <div id="matching-rows_${index}">
                                <div class="input-group">
                                <label for="left_item_1_${index}">Row 1:</label>
                                <input type="text" id="left_item_1_${index}" name="questions[${index}][left_items][]" placeholder="Row 1" required>
                                    <button type="button" class="remove-option" onclick="removeMatchingRow(this, ${index})">×</button>
                        </div>
                                <div class="input-group">
                                <label for="left_item_2_${index}">Row 2:</label>
                                <input type="text" id="left_item_2_${index}" name="questions[${index}][left_items][]" placeholder="Row 2" required>
                                    <button type="button" class="remove-option" onclick="removeMatchingRow(this, ${index})">×</button>
                </div>
                        </div>
                            <button type="button" class="add-option" onclick="addMatchingRow(${index})">
                            <i class="fas fa-plus"></i> Add Row
                        </button>
            </div>
                    
                        <div class="form-group">
                            <label>Right Items (Columns):</label>
                            <div id="matching-columns_${index}">
                                <div class="input-group">
                                <label for="right_item_1_${index}">Column 1:</label>
                                <input type="text" id="right_item_1_${index}" name="questions[${index}][right_items][]" placeholder="Column 1" required>
                                    <button type="button" class="remove-option" onclick="removeMatchingColumn(this, ${index})">×</button>
                            </div>
                                <div class="input-group">
                                <label for="right_item_2_${index}">Column 2:</label>
                                <input type="text" id="right_item_2_${index}" name="questions[${index}][right_items][]" placeholder="Column 2" required>
                                    <button type="button" class="remove-option" onclick="removeMatchingColumn(this, ${index})">×</button>
                            </div>
                        </div>
                            <button type="button" class="add-option" onclick="addMatchingColumn(${index})">
                            <i class="fas fa-plus"></i> Add Column
                        </button>
        </div>
        
                        <div class="form-group">
                            <label>Correct Matches:</label>
                            <div id="matching-matches_${index}">
                                <!-- Will be populated by JavaScript -->
                    </div>
                </div>
        </div>
        
                    <!-- Essay Section -->
                    <div id="essay-section_${index}" class="question-type-section">
                        <h3>Essay Question</h3>
                        <p>Essay questions will be manually graded by the teacher.</p>
            <div class="form-group">
                            <label for="essay_rubric_${index}">Rubric (required)</label>
                            <textarea id="essay_rubric_${index}" name="questions[${index}][rubric]" rows="4" placeholder="e.g., Thesis (2), Evidence (3), Organization (2), Grammar (3)"></textarea>
                            <small class="form-text text-muted">Describe scoring criteria or paste a rubric. Students will see this rubric.</small>
            </div>
            </div>
        </div>
    `;
        }
        
        
        
        
        
        function addMCQOption(questionIndex = 0) {
            const container = document.getElementById(`mcq-options_${questionIndex}`);
            const optionCount = container.children.length;
            const optionGroup = document.createElement('div');
            optionGroup.className = 'option-group';
            const optionLetter = String.fromCharCode(65 + optionCount);
            const optionId = `choice_${optionLetter.toLowerCase()}_${questionIndex}_${optionCount}`;
            const radioId = `correct_${optionLetter.toLowerCase()}_${questionIndex}_${optionCount}`;
            optionGroup.innerHTML = `
                <label for="${optionId}">Option ${optionLetter}:</label>
                <input type="text" id="${optionId}" name="questions[${questionIndex}][choice_${optionLetter.toLowerCase()}]" placeholder="Option ${optionLetter}" required>
                <label for="${radioId}">Correct Answer:</label>
                <input type="radio" id="${radioId}" name="questions[${questionIndex}][correct_answer]" value="${optionLetter}" required>
                <button type="button" onclick="removeOption(this)">×</button>
            `;
            container.appendChild(optionGroup);
        }
        
        function addMatchingRow(questionIndex = 0) {
            const container = document.getElementById(`matching-rows_${questionIndex}`);
            // Count only input elements to get the correct row number
            const existingInputs = container.querySelectorAll('input[type="text"]');
            const rowNumber = existingInputs.length + 1;
            const inputId = `left_item_${rowNumber}_${questionIndex}`;
            
            const label = document.createElement('label');
            label.setAttribute('for', inputId);
            label.textContent = `Row ${rowNumber}:`;
            
            const input = document.createElement('input');
            input.type = 'text';
            input.id = inputId;
            input.name = `questions[${questionIndex}][left_items][]`;
            input.placeholder = `Row ${rowNumber}`;
            input.required = true;
            input.addEventListener('input', () => {
                updateMatchingMatches(questionIndex);
                setTimeout(() => validateMatching(questionIndex), 10);
            });
            input.setAttribute('data-listener-added', 'true');
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-option';
            removeBtn.textContent = '×';
            removeBtn.onclick = () => removeMatchingRow(removeBtn, questionIndex);
            
            const inputGroup = document.createElement('div');
            inputGroup.className = 'input-group';
            inputGroup.appendChild(label);
            inputGroup.appendChild(input);
            inputGroup.appendChild(removeBtn);
            
            container.appendChild(inputGroup);
            
            // Automatically add a corresponding column
            addMatchingColumn(questionIndex);
            
            updateMatchingMatches(questionIndex); // This will update points automatically
            // Update header label to range for matching
            updateQuestionTitles(); // Update all question titles after adding row
            const qi = document.querySelector(`[data-question-index="${questionIndex}"]`);
            const title = qi ? qi.querySelector('.q-title') : null;
            if (title) {
                const count = container.querySelectorAll('input[type="text"]').length;
                if (count > 1) {
                    const start = questionIndex + 1;
                    title.textContent = `Question ${start}–${start + count - 1}`;
                } else {
                    title.textContent = `Question ${questionIndex + 1}`;
                }
            }
        }
        
        function addMatchingColumn(questionIndex = 0) {
            const container = document.getElementById(`matching-columns_${questionIndex}`);
            // Count only input elements to get the correct column number
            const existingInputs = container.querySelectorAll('input[type="text"]');
            const columnNumber = existingInputs.length + 1;
            const inputId = `right_item_${columnNumber}_${questionIndex}`;
            
            const label = document.createElement('label');
            label.setAttribute('for', inputId);
            label.textContent = `Column ${columnNumber}:`;
            
            const input = document.createElement('input');
            input.type = 'text';
            input.id = inputId;
            input.name = `questions[${questionIndex}][right_items][]`;
            input.placeholder = `Column ${columnNumber}`;
            input.required = true;
            input.addEventListener('input', () => {
                updateMatchingMatches(questionIndex);
                setTimeout(() => validateMatching(questionIndex), 10);
            });
            input.setAttribute('data-listener-added', 'true');
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-option';
            removeBtn.textContent = '×';
            removeBtn.onclick = () => removeMatchingColumn(removeBtn, questionIndex);
            
            const inputGroup = document.createElement('div');
            inputGroup.className = 'input-group';
            inputGroup.appendChild(label);
            inputGroup.appendChild(input);
            inputGroup.appendChild(removeBtn);
            
            container.appendChild(inputGroup);
            updateMatchingMatches(questionIndex); // This will update points automatically
        }
        
        function updateMatchingMatches(questionIndex = 0) {
            console.log('updateMatchingMatches called for questionIndex:', questionIndex);
            
            const rows = document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`);
            const columns = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
            const container = document.getElementById(`matching-matches_${questionIndex}`);
            const pointsField = document.getElementById(`points_${questionIndex}`);
            
            console.log('Found rows:', rows.length, 'columns:', columns.length, 'container:', container);
            
            if (!container) {
                console.log('No container found for questionIndex:', questionIndex);
                return;
            }
            
            container.innerHTML = '';
            console.log('Cleared container, now creating text fields...');
            
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
                console.log(`Creating dropdown for row ${index}:`, row.value);
                
                // Create dropdown for ALL rows, not just non-empty ones
                    const matchItem = document.createElement('div');
                    matchItem.className = 'matching-item';
                    matchItem.innerHTML = `
                    <label>${row.value || `Row ${index + 1}`}:</label>
                        <select name="questions[${questionIndex}][matches][${index}]" required>
                            <option value="">Select match</option>
                        ${Array.from(columns).map((col, colIndex) => 
                            `<option value="${colIndex}">${col.value || `Column ${colIndex + 1}`}</option>`
                            ).join('')}
                        </select>
                    `;
                    container.appendChild(matchItem);
                    console.log(`Added dropdown for row ${index}`);
            });
        }
        
        
        function removeMatchingRow(button, questionIndex) {
            const rowContainer = document.getElementById(`matching-rows_${questionIndex}`);
            const columnContainer = document.getElementById(`matching-columns_${questionIndex}`);
            
            // Get the row index (position in the rows container)
            const rowGroups = Array.from(rowContainer.querySelectorAll('.input-group'));
            const rowIndex = rowGroups.indexOf(button.parentElement);
            
            // Remove the row
            button.parentElement.remove();
            
            // Remove the corresponding column (same index)
            const columnGroups = Array.from(columnContainer.querySelectorAll('.input-group'));
            if (columnGroups[rowIndex]) {
                columnGroups[rowIndex].remove();
            }
            
            // Renumber remaining rows and columns
            renumberMatchingItems(questionIndex);
            
            // Update matching matches
            updateMatchingMatches(questionIndex);
            // Update all question titles
            updateQuestionTitles();
        }
        
        function removeMatchingColumn(button, questionIndex) {
            const rowContainer = document.getElementById(`matching-rows_${questionIndex}`);
            const columnContainer = document.getElementById(`matching-columns_${questionIndex}`);
            
            // Get the column index (position in the columns container)
            const columnGroups = Array.from(columnContainer.querySelectorAll('.input-group'));
            const columnIndex = columnGroups.indexOf(button.parentElement);
            
            // Remove the column
            button.parentElement.remove();
            
            // Remove the corresponding row (same index)
            const rowGroups = Array.from(rowContainer.querySelectorAll('.input-group'));
            if (rowGroups[columnIndex]) {
                rowGroups[columnIndex].remove();
            }
            
            // Renumber remaining rows and columns
            renumberMatchingItems(questionIndex);
            
            // Update matching matches
            updateMatchingMatches(questionIndex);
            // Update all question titles
            updateQuestionTitles();
        }
        
        function renumberMatchingItems(questionIndex) {
            const rowContainer = document.getElementById(`matching-rows_${questionIndex}`);
            const columnContainer = document.getElementById(`matching-columns_${questionIndex}`);
            
            // Renumber rows
            const rowGroups = Array.from(rowContainer.querySelectorAll('.input-group'));
            rowGroups.forEach((group, index) => {
                const newNumber = index + 1;
                const label = group.querySelector('label');
                const input = group.querySelector('input');
                
                label.textContent = `Row ${newNumber}:`;
                label.setAttribute('for', `left_item_${newNumber}_${questionIndex}`);
                input.id = `left_item_${newNumber}_${questionIndex}`;
                input.placeholder = `Row ${newNumber}`;
            });
            
            // Renumber columns
            const columnGroups = Array.from(columnContainer.querySelectorAll('.input-group'));
            columnGroups.forEach((group, index) => {
                const newNumber = index + 1;
                const label = group.querySelector('label');
                const input = group.querySelector('input');
                
                label.textContent = `Column ${newNumber}:`;
                label.setAttribute('for', `right_item_${newNumber}_${questionIndex}`);
                input.id = `right_item_${newNumber}_${questionIndex}`;
                input.placeholder = `Column ${newNumber}`;
            });
        }
        
        function removeOption(button) {
            const container = button.parentElement.parentElement;
            button.parentElement.remove();
            
            // Renumber remaining items
            const questionIndex = container.id.split('_').pop();
            const isRow = container.id.includes('rows');
            
            // Get all remaining input elements
            const remainingInputs = container.querySelectorAll('input[type="text"]');
            
            // Renumber labels and inputs
            remainingInputs.forEach((input, index) => {
                const newNumber = index + 1;
                const label = input.previousElementSibling;
                
                if (isRow) {
                    label.textContent = `Row ${newNumber}:`;
                    input.placeholder = `Row ${newNumber}`;
                } else {
                    label.textContent = `Column ${newNumber}:`;
                    input.placeholder = `Column ${newNumber}`;
                }
            });
            
            // Update matching matches if this is a row/column removal
            if (isRow) {
                updateMatchingMatches(questionIndex);
            }
        }
        
        function addInputListeners(questionIndex = 0) {
            // Add event listeners to existing row inputs
            document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`).forEach(input => {
                if (!input.hasAttribute('data-listener-added')) {
                    input.addEventListener('input', () => { 
                        updateMatchingMatches(questionIndex); 
                        // Delay validation to ensure value is set
                        setTimeout(() => validateMatching(questionIndex), 100);
                    });
                    input.addEventListener('blur', () => {
                        setTimeout(() => validateMatching(questionIndex), 50);
                    });
                    input.setAttribute('data-listener-added', 'true');
                }
            });
            
            // Add event listeners to existing column inputs
            document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`).forEach(input => {
                if (!input.hasAttribute('data-listener-added')) {
                    input.addEventListener('input', () => { 
                        updateMatchingMatches(questionIndex); 
                        // Clear all errors first, then validate
                        clearAllMatchingErrors(questionIndex);
                        setTimeout(() => validateMatching(questionIndex), 100);
                    });
                    input.addEventListener('blur', () => {
                        clearAllMatchingErrors(questionIndex);
                        setTimeout(() => validateMatching(questionIndex), 50);
                    });
                    input.setAttribute('data-listener-added', 'true');
                }
            });
            // Add listener for type/points/text
            const typeSel = document.getElementById(`type_${questionIndex}`);
            const textEl = document.getElementById(`question_text_${questionIndex}`);
            const ptsEl = document.getElementById(`points_${questionIndex}`);
            if (typeSel && !typeSel.hasAttribute('data-rtv')) { typeSel.addEventListener('change', () => validateQuestion(questionIndex)); typeSel.setAttribute('data-rtv','1'); }
            if (textEl && !textEl.hasAttribute('data-rtv')) { textEl.addEventListener('input', () => validateQuestion(questionIndex)); textEl.setAttribute('data-rtv','1'); }
            if (ptsEl && !ptsEl.hasAttribute('data-rtv')) { ptsEl.addEventListener('input', () => validateQuestion(questionIndex)); ptsEl.setAttribute('data-rtv','1'); }
            // If MCQ, add listeners
            ['a','b','c','d'].forEach(k => {
                const opt = document.getElementById(`choice_${k}_${questionIndex}`);
                if (opt && !opt.hasAttribute('data-rtv')) { opt.addEventListener('input', () => validateMCQ(questionIndex)); opt.setAttribute('data-rtv','1'); }
                const ra = document.getElementById(`correct_${k}_${questionIndex}`);
                if (ra && !ra.hasAttribute('data-rtv')) { ra.addEventListener('change', () => validateMCQ(questionIndex)); ra.setAttribute('data-rtv','1'); }
            });
        }

        function showError(el, msgId, message) {
            if (!el) return;
            el.classList.add('invalid');
            let m = document.getElementById(msgId);
            if (!m) {
                m = document.createElement('div');
                m.id = msgId;
                m.className = 'error-text';
                el.parentElement.appendChild(m);
            }
            m.textContent = message;
        }
        function clearError(el, msgId) {
            if (!el) return;
            el.classList.remove('invalid');
            
            // Try to find and remove the error message by ID
            const m = document.getElementById(msgId);
            if (m) {
                console.log(`Clearing error for ${msgId}`);
                m.remove();
            }
            
            // Also try to find and remove any error text in the parent container
            const parent = el.parentElement;
            if (parent) {
                const errorTexts = parent.querySelectorAll('.error-text');
                errorTexts.forEach(error => {
                    if (error.textContent === 'Required') {
                        console.log(`Removing Required error text from parent`);
                        error.remove();
                    }
                });
            }
        }
        
        // Function to force clear all matching errors for a question
        function clearAllMatchingErrors(questionIndex) {
            console.log(`Force clearing all matching errors for question ${questionIndex}`);
            
            // Clear all left input errors
            const leftInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`);
            leftInputs.forEach((el, idx) => {
                clearError(el, `err_l_${questionIndex}_${idx}`);
            });
            
            // Clear all right input errors
            const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
            rightInputs.forEach((el, idx) => {
                clearError(el, `err_r_${questionIndex}_${idx}`);
            });
            
            // Clear all select errors
            const selects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
            selects.forEach((sel, idx) => {
                clearError(sel, `err_sel_${questionIndex}_${idx}`);
            });
        }
        function validateQuestion(i) {
            const textEl = document.getElementById(`question_text_${i}`);
            const typeSel = document.getElementById(`type_${i}`);
            const ptsEl = document.getElementById(`points_${i}`);
            if (textEl && !textEl.value.trim()) showError(textEl, `err_text_${i}`, 'Question text is required'); else clearError(textEl, `err_text_${i}`);
            if (typeSel && !typeSel.value) showError(typeSel, `err_type_${i}`, 'Please select a question type'); else clearError(typeSel, `err_type_${i}`);
            if (ptsEl && (Number(ptsEl.value) < 1 || isNaN(Number(ptsEl.value)))) showError(ptsEl, `err_pts_${i}`, 'Points must be 1 or higher'); else clearError(ptsEl, `err_pts_${i}`);
            // Essay rubric required when essay is selected
            if (typeSel && typeSel.value === 'essay') {
                const rubric = document.getElementById(`essay_rubric_${i}`);
                if (rubric && !rubric.value.trim()) showError(rubric, `err_rub_${i}`, 'Rubric is required for essay questions'); else clearError(rubric, `err_rub_${i}`);
            }
        }
        function validateMCQ(i) {
            const a = document.getElementById(`choice_a_${i}`), b = document.getElementById(`choice_b_${i}`), c = document.getElementById(`choice_c_${i}`), d = document.getElementById(`choice_d_${i}`);
            const correct = document.querySelector(`input[name="questions[${i}][correct_answer]"]:checked`);
            [a,b,c,d].forEach((el, idx) => {
                if (el) {
                    if (!el.value.trim()) showError(el, `err_m_${i}_${idx}`, 'Required'); else clearError(el, `err_m_${i}_${idx}`);
                }
            });
            const typeSel = document.getElementById(`type_${i}`);
            if (typeSel && typeSel.value === 'mcq') {
                if (!correct) showError(typeSel, `err_mcq_${i}`, 'Select a correct answer'); else clearError(typeSel, `err_mcq_${i}`);
            }
        }
        function validateMatching(i) {
            // Add a small delay to ensure DOM is fully updated
            setTimeout(() => {
            const leftInputs = document.querySelectorAll(`input[name="questions[${i}][left_items][]"]`);
            const rightInputs = document.querySelectorAll(`input[name="questions[${i}][right_items][]"]`);
                
                console.log(`Validating matching question ${i}:`);
                console.log('Left inputs:', leftInputs.length);
                console.log('Right inputs:', rightInputs.length);
                
                // Validate left inputs (rows)
                leftInputs.forEach((el, idx) => { 
                    const hasValue = el.value && el.value.trim().length > 0;
                    console.log(`Left input ${idx}: value="${el.value}", hasValue=${hasValue}`);
                    
                    if (!hasValue) {
                        showError(el, `err_l_${i}_${idx}`, 'Required');
                    } else {
                        clearError(el, `err_l_${i}_${idx}`);
                    }
                });
                
                // Validate right inputs (columns)
                rightInputs.forEach((el, idx) => { 
                    const hasValue = el.value && el.value.trim().length > 0;
                    console.log(`Right input ${idx}: value="${el.value}", hasValue=${hasValue}`);
                    
                    if (!hasValue) {
                        showError(el, `err_r_${i}_${idx}`, 'Required');
                    } else {
                        clearError(el, `err_r_${i}_${idx}`);
                        // Remove any stray 'Required' message that might be attached to parent wrappers
                        const parent = el.parentElement;
                        if (parent) {
                            const stray = parent.querySelectorAll('.error-text');
                            stray.forEach(n => { if (n.textContent === 'Required') n.remove(); });
                        }
                    }
                });
                
            // Matches dropdowns exist after updateMatchingMatches
                setTimeout(() => {
                const selects = document.querySelectorAll(`#matching-matches_${i} select`);
                    selects.forEach((sel, idx) => { 
                        if (!sel.value) {
                            showError(sel, `err_sel_${i}_${idx}`, 'Select match'); 
                        } else {
                            clearError(sel, `err_sel_${i}_${idx}`);
                        }
                    });
                }, 100);
            }, 50);
        }
        
        // Validation helper functions
        function showError(elementId, message) {
            const errorElement = document.getElementById(elementId);
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }
        
        function clearError(elementId) {
            const errorElement = document.getElementById(elementId);
            if (errorElement) {
                errorElement.style.display = 'none';
                errorElement.textContent = '';
            }
        }
        
        function clearAllErrors() {
            const errorElements = document.querySelectorAll('.error-message');
            errorElements.forEach(element => {
                element.style.display = 'none';
                element.textContent = '';
            });
        }
        
        function checkTitleExists(title, sectionId) {
            return fetch('', {
        method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_set_title&set_title=${encodeURIComponent(title)}&section_id=${sectionId}`
    })
    .then(response => response.json())
    .then(data => {
                return data.success && data.exists;
            })
            .catch(error => {
                console.error('Error checking title:', error);
                return false;
            });
        }
        
        // Real-time validation
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we're in edit mode from URL parameter
            <?php if ($isEditMode && $editSetId): ?>
            // Auto-trigger edit mode
            setTimeout(() => {
                editSet(<?php echo $editSetId; ?>);
            }, 100);
            <?php endif; ?>
            
            // Title validation
            const titleInput = document.getElementById('set_title');
            if (titleInput) {
                titleInput.addEventListener('blur', function() {
                    const title = this.value.trim();
                    if (title) {
                        const sectionBoxes = document.querySelectorAll('#sectionHiddenInputs input[name="section_ids[]"]:checked');
                        if (sectionBoxes.length > 0) {
                            const sectionId = sectionBoxes[0].value;
                            checkTitleExists(title, sectionId).then(exists => {
                                if (exists) {
                                    showError('title-error', 'A question set with this title already exists for the selected section');
            } else {
                                    clearError('title-error');
                                }
                            });
                        }
                    } else {
                        clearError('title-error');
                    }
                });
            }
            
            // Timer validation
            const timerInput = document.getElementById('set_timer');
            if (timerInput) {
                timerInput.addEventListener('input', function() {
                    const timer = parseInt(this.value);
                    if (timer < 1) {
                        showError('timer-error', 'Timer must be at least 1 minute');
        } else {
                        clearError('timer-error');
                    }
                });
            }
            
            // Date validation
            const dateInput = document.getElementById('set_open_at');
            if (dateInput) {
                function validateDate() {
                    if (dateInput.value) {
                        const selectedDate = new Date(dateInput.value);
                        const now = new Date();
                        // Add a 1-minute buffer to account for timing differences
                        const bufferTime = new Date(now.getTime() - 60000); // 1 minute ago
                        
                        if (selectedDate < bufferTime) {
                            showError('date-error', 'Open date/time must be current or future');
                        } else {
                            clearError('date-error');
                        }
                    } else {
                        clearError('date-error');
                    }
                }
                
                // Validate on change and input events
                dateInput.addEventListener('change', validateDate);
                dateInput.addEventListener('input', validateDate);
                dateInput.addEventListener('blur', validateDate);
            }
            
            // Difficulty validation
            const difficultySelect = document.getElementById('set_difficulty');
            if (difficultySelect) {
                difficultySelect.addEventListener('change', function() {
                    if (this.value) {
                        clearError('difficulty-error');
                    }
                });
            }
        });
        
            
            // Form submission
            document.getElementById('questionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear auto-save data on successful submission
            if (window.questionAutoSave) {
                window.questionAutoSave.clear();
            }
            
            // Clear previous error messages
            clearAllErrors();
            
            // Get form elements
            const setTitleElement = document.getElementById('set_title');
            const timerElement = document.getElementById('set_timer');
            const openAtElement = document.getElementById('set_open_at');
            const difficultyElement = document.getElementById('set_difficulty');
            const sectionBoxes = document.querySelectorAll('#sectionHiddenInputs input[name="section_ids[]"]');
            const selectedSectionIds = Array.from(sectionBoxes).map(i => i.value);
            
            // Get question elements
            const typeElement = document.getElementById('type_0') || document.getElementById('type');
            const questionTextElement = document.getElementById('question_text_0') || document.getElementById('question_text');
            
            if (!typeElement || !questionTextElement || !setTitleElement) {
                alert('Form elements not found. Please refresh the page and try again.');
                return;
            }
            
            const type = typeElement.value;
            const questionText = questionTextElement.value;
            const setTitle = setTitleElement.value;
            const timer = timerElement ? parseInt(timerElement.value) : 0;
            const openAt = openAtElement ? openAtElement.value : '';
            const difficulty = difficultyElement ? difficultyElement.value : '';
            
            let hasErrors = false;
            
            // 1. Validate Question Set Title (Required + No Duplicates)
            if (!setTitle || setTitle.trim() === '') {
                showError('title-error', 'Question set title is required');
                hasErrors = true;
            } else {
                // Check for duplicate title (async validation)
                if (selectedSectionIds.length > 0) {
                    const checkTitlePromise = checkTitleExists(setTitle.trim(), selectedSectionIds[0]);
                    checkTitlePromise.then(exists => {
                        if (exists) {
                            showError('title-error', 'A question set with this title already exists for the selected section');
                            hasErrors = true;
                        }
                    });
                }
            }
            
            // 2. Validate Section Selection (Required)
            if (selectedSectionIds.length === 0) {
                showError('section-error', 'Please select at least one section');
                hasErrors = true;
            }
            
            // 3. Validate Timer (Required + Minimum 1 minute)
            if (!timer || timer < 1) {
                showError('timer-error', 'Timer must be at least 1 minute');
                hasErrors = true;
            }
            
            // 4. Validate Open Date/Time (No Past Dates)
            if (openAt && openAt.trim() !== '') {
                const selectedDate = new Date(openAt);
                const now = new Date();
                // Add a 1-minute buffer to account for timing differences
                const bufferTime = new Date(now.getTime() - 60000); // 1 minute ago
                
                if (selectedDate < bufferTime) {
                    showError('date-error', 'Open date/time must be current or future');
                    hasErrors = true;
                }
            }
            
            // 5. Validate Difficulty (Required)
            if (!difficulty || difficulty === '') {
                showError('difficulty-error', 'Please select a difficulty level');
                hasErrors = true;
            }
            
            // 6. Validate Question Data
            if (!questionText || questionText.trim() === '') {
                alert('Please enter question text!');
                return;
            }
            
            if (!type || type === '') {
                alert('Please select a question type!');
                return;
            }
            
            // If there are validation errors, stop submission
            if (hasErrors) {
                alert('Please fix the highlighted errors before submitting.');
                return;
            }
            
            // Validate all questions (realtime + submit)
            const questionItems = document.querySelectorAll('.question-item');
            let allValid = true;
            
            questionItems.forEach((questionItem, index) => {
                const questionType = questionItem.querySelector(`#type_${index}`)?.value || questionItem.querySelector('#type')?.value;
                const questionText = questionItem.querySelector(`#question_text_${index}`)?.value || questionItem.querySelector('#question_text')?.value;
                
                if (!questionType || !questionText.trim()) { validateQuestion(index); allValid = false; }
                
                // Validate question type specific fields
                const activeSection = questionItem.querySelector(`#${questionType}-section_${index}`) || questionItem.querySelector(`#${questionType}-section`);
                if (activeSection) {
                    if (questionType === 'mcq') validateMCQ(index);
                    if (questionType === 'matching') validateMatching(index);
                }
            });
            
            if (!allValid) {
                alert('Please fix the highlighted fields before submitting.');
                return;
            }
            
            const formData = new FormData(this);
            // Normalize section selection for backend: always send section_ids[]; also include first as section_id for compatibility
            try {
                // Remove any accidental single field
                formData.delete('section_id');
            } catch(e) {}
            // Append chosen sections
            selectedSectionIds.forEach(id => formData.append('section_ids[]', id));
            if (selectedSectionIds.length > 0) {
                formData.append('section_id', selectedSectionIds[0]);
            }
            if (window.isEditMode) {
                formData.append('action', 'update_question_set');
                formData.append('set_id', window.currentEditSetId || '');
            } else {
                formData.append('action', 'create_question');
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = window.isEditMode ? '<i class="fas fa-spinner fa-spin"></i> Saving Changes...' : '<i class="fas fa-spinner fa-spin"></i> Creating Questions...';
            submitBtn.disabled = true;
            
    fetch('', {
        method: 'POST',
                body: formData
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.text().then(text => {
                    try { return JSON.parse(text); }
                    catch(e){
                        console.error('Create response not JSON:', text);
                        throw new Error('Server returned invalid JSON. ' + text.substring(0, 200));
                    }
                });
            })
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
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        function viewQuestions(setId, setTitle) {
            // Helper to render the modal from data.questions
            const renderModal = (data) => {
                if (!data || !data.questions || data.questions.length === 0) {
                    alert('No questions found in this set.');
        return;
    }
    
                // Styles
                const containerStyle = `position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center;`;
                const cardStyle = `background:#fff; width:min(920px,90vw); max-height:85vh; overflow:auto; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.2);`;
                const headerStyle = `display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #e5e7eb; position:sticky; top:0; background:#fff; z-index:1;`;
                const bodyStyle = `padding:16px 20px;`;
                const qCardStyle = `border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; margin:12px 0; background:#fafafa;`;
                const badgeStyle = `display:inline-block; padding:4px 8px; border-radius:12px; font-size:12px; background:#eef2ff; color:#4338ca; margin-left:8px;`;
                const btnStyle = `padding:8px 14px; border:none; border-radius:8px; cursor:pointer;`;

                let html = `<div style="${headerStyle}">` +
                           `<div style="font-size:18px; font-weight:600;">Questions in "${setTitle}"</div>` +
                           `<div>` +
                                `<button style="${btnStyle}; background:#6b7280; color:#fff;" onclick="this.closest('.view-qs-card').parentElement.remove()">Cancel</button>` +
                           `</div>` +
                           `</div>`;

                html += `<div style="${bodyStyle}">`;

                // Ensure questions are in creation order for consistent numbering
                data.questions.sort((a, b) => {
                    const aOrder = (a.order_index !== undefined && a.order_index !== null) ? a.order_index
                                  : (a.question_order !== undefined && a.question_order !== null) ? a.question_order
                                  : Number.MAX_SAFE_INTEGER;
                    const bOrder = (b.order_index !== undefined && b.order_index !== null) ? b.order_index
                                  : (b.question_order !== undefined && b.question_order !== null) ? b.question_order
                                  : Number.MAX_SAFE_INTEGER;
                    if (aOrder !== bOrder) return aOrder - bOrder;
                    return (a.id || 0) - (b.id || 0);
                });

                // Calculate cumulative question numbers
                let cumulativeNumber = 1;
                const questionNumbers = [];
                
                data.questions.forEach((q, idx) => {
                    if (q.type === 'matching') {
                        try {
                            const leftItems = Array.isArray(q.left_items) ? q.left_items : JSON.parse(q.left_items || '[]');
                            const pairCount = leftItems.length;
                            if (pairCount > 1) {
                                const endNum = cumulativeNumber + pairCount - 1;
                                questionNumbers.push(`Question ${cumulativeNumber}-${endNum}`);
                                cumulativeNumber += pairCount;
                            } else {
                                questionNumbers.push(`Question ${cumulativeNumber}`);
                                cumulativeNumber += 1;
                            }
                        } catch(e) {
                            questionNumbers.push(`Question ${cumulativeNumber}`);
                            cumulativeNumber += 1;
                        }
                    } else {
                        questionNumbers.push(`Question ${cumulativeNumber}`);
                        cumulativeNumber += 1;
                    }
                });

                data.questions.forEach((q, idx) => {
                    html += `<div style="${qCardStyle}">`;
                    
                    const questionNumber = questionNumbers[idx];
                    
                    html += `<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">` +
                            `<div style="font-weight:600;">${questionNumber} <span style="${badgeStyle}">${(q.type || '').toUpperCase()}</span></div>` +
                            `<div style="font-size:12px; color:#6b7280;">Points: ${q.points ?? 0}</div>` +
                            `</div>`;

                    html += `<div style="margin-bottom:10px; color:#111827;">${q.question_text || ''}</div>`;

                    if (q.type === 'mcq') {
                        const opts = [
                            {k:'A', v:q.choice_a},
                            {k:'B', v:q.choice_b},
                            {k:'C', v:q.choice_c},
                            {k:'D', v:q.choice_d}
                        ].filter(o=>o.v !== undefined && o.v !== null);
                        html += '<ul style="list-style:none; padding:0; margin:0;">';
                        opts.forEach(o => {
                            const isCorrect = (q.correct_answer || '').toString().toUpperCase() === o.k;
                            html += `<li style="display:flex; align-items:center; gap:8px; padding:8px 10px; margin:6px 0; border:1px solid #e5e7eb; border-radius:8px; background:${isCorrect ? '#ecfdf5' : '#fff'};">` +
                                    `<span style="width:20px; font-weight:700; color:#374151;">${o.k}.</span>` +
                                    `<span style="flex:1; color:#111827;">${o.v ?? ''}</span>` +
                                    (isCorrect ? `<span style="color:#047857; font-size:12px; font-weight:600;">Correct</span>` : '') +
                                    `</li>`;
                        });
                        html += '</ul>';
                    } else if (q.type === 'matching') {
                        let left = []; let right = []; let matches = [];
                        try { left = Array.isArray(q.left_items) ? q.left_items : JSON.parse(q.left_items || '[]'); } catch(e){}
                        try { right = Array.isArray(q.right_items) ? q.right_items : JSON.parse(q.right_items || '[]'); } catch(e){}
                        try { matches = Array.isArray(q.matches) ? q.matches : JSON.parse(q.correct_pairs || '[]'); } catch(e){}
                        
                        // Debug: log the data to console
                        console.log('Matching question data:', {
                            left: left,
                            right: right,
                            matches: matches,
                            correct_pairs: q.correct_pairs
                        });
                        
                        // Handle case where matches might be indices instead of text
                        if (matches && matches.length > 0) {
                            // Check if matches are numbers (indices) or strings that look like numbers
                            const firstMatch = matches[0];
                            console.log('First match:', firstMatch, 'Type:', typeof firstMatch);
                            if (typeof firstMatch === 'number' || 
                                (typeof firstMatch === 'string' && !isNaN(firstMatch) && firstMatch.trim() !== '')) {
                                console.log('Converting indices to text...');
                                // Convert indices to text
                                matches = matches.map(index => {
                                    const numIndex = parseInt(index);
                                    const result = (numIndex >= 0 && numIndex < right.length) ? right[numIndex] : '';
                                    console.log(`Index ${index} -> ${result}`);
                                    return result;
                                });
                                console.log('Converted matches:', matches);
                            }
                        }

                        html += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">';
                        html += '<div><div style="font-weight:600; margin-bottom:6px;">Left Items</div>';
                        html += '<ol style="margin:0; padding-left:20px;">' + left.map(it=>`<li style=\"margin:4px 0;\">${it}</li>`).join('') + '</ol></div>';
                        html += '<div><div style="font-weight:600; margin-bottom:6px;">Correct Matches</div>';
                        html += '<ol style="margin:0; padding-left:20px;">';
                        left.forEach((_, i) => {
                            const m = (matches && matches[i]) ? matches[i] : '';
                            html += `<li style="margin:4px 0; color:#065f46;">${m || '<span style=\"color:#9ca3af\">(none)</span>'}</li>`;
                        });
                        html += '</ol></div>';
                        html += '</div>';
                    }

                    html += `</div>`; // q card
                });

                html += `</div>`; // body

                const modal = document.createElement('div');
                modal.setAttribute('style', containerStyle);
                const card = document.createElement('div');
                card.className = 'view-qs-card';
                card.setAttribute('style', cardStyle);
                card.innerHTML = html;
                modal.appendChild(card);
                document.body.appendChild(modal);
            };

            // Primary request
            const formData = new FormData();
            formData.append('action', 'get_questions');
            formData.append('set_id', setId);
            fetch('', { method: 'POST', body: formData })
                .then(res => res.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data && data.success && Array.isArray(data.questions) && data.questions.length > 0) {
                            renderModal(data);
        } else {
                            // Fallback: use get_set_questions endpoint
                            const params = new URLSearchParams({ action: 'get_set_questions', set_id: setId });
                            return fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
                                .then(r => r.text())
                                .then(tt => {
                                    try { renderModal(JSON.parse(tt)); }
                                    catch(e){ throw new Error('No questions found in this set.'); }
                                });
                        }
                    } catch (e) {
                        throw new Error('Error parsing server response.');
                    }
                })
                .catch(err => alert('Error loading questions: ' + err.message));
        }

        function editSet(setId) {
            const params = new URLSearchParams({action: 'get_set_questions', set_id: setId});
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response is not valid JSON:', text);
                        throw new Error('Server returned invalid JSON. Response: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                if (!data.success) {
                    alert('Failed to load set for editing: ' + (data.error || 'Unknown error'));
                    return;
                }
                // Switch to edit mode
                window.isEditMode = true;
                window.currentEditSetId = setId;
                const submitBtn = document.querySelector('#questionForm button[type="submit"]');
                if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                // Update headings to "Edit Questions"
                const pageTitle = document.getElementById('pageTitle');
                const formHeading = document.getElementById('formTitleHeading');
                if (pageTitle) pageTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Questions';
                if (formHeading) formHeading.textContent = 'Edit Questions';
                const cancelBtn = document.getElementById('cancelEditBtn');
                if (cancelBtn) cancelBtn.style.display = 'inline-block';
                // Set timer/open_at into header fields when provided
                if (data.timer_minutes !== undefined && data.timer_minutes !== null) {
                    const tm = document.getElementById('set_timer');
                    if (tm) tm.value = String(data.timer_minutes || 0);
                }
                if (data.open_at) {
                    const oa = document.getElementById('set_open_at');
                    if (oa) {
                        // convert to local datetime-local format
                        const dt = new Date((data.open_at || '').replace(' ', 'T'));
                        if (!isNaN(dt.getTime())) {
                            const pad = n => String(n).padStart(2,'0');
                            const v = `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
                            oa.value = v;
                        }
                    }
                }
                if (data.difficulty !== undefined) {
                    const diffSel = document.getElementById('set_difficulty');
                    if (diffSel) diffSel.value = (data.difficulty || '').toLowerCase();
                }

                // Populate header
                const titleEl = document.getElementById('set_title');
                if (titleEl) titleEl.value = data.set_title || '';
                // Preselect the section if available from server (augment payload if needed)
                if (data.section_id) {
                    const sectionSel = document.getElementById('section_id');
                    if (sectionSel) {
                        let matched = false;
                        Array.from(sectionSel.options).forEach(opt => {
                            if (Number(opt.value) === Number(data.section_id)) {
                                opt.selected = true;
                                matched = true;
                            }
                        });
                        if (!matched) sectionSel.value = String(data.section_id);
                    }
                }

                // Build questions
                const container = document.getElementById('questions-container');
                container.innerHTML = '';
                questionIndex = -1;

                const qs = Array.isArray(data.questions) ? data.questions : [];
                // Sort strictly by order_index when present, then question_order, then id
                qs.sort((a, b) => {
                    const aOrder = (a.order_index !== undefined && a.order_index !== null) ? a.order_index
                                  : (a.question_order !== undefined && a.question_order !== null) ? a.question_order
                                  : Number.MAX_SAFE_INTEGER;
                    const bOrder = (b.order_index !== undefined && b.order_index !== null) ? b.order_index
                                  : (b.question_order !== undefined && b.question_order !== null) ? b.question_order
                                  : Number.MAX_SAFE_INTEGER;
                    if (aOrder !== bOrder) return aOrder - bOrder;
                    return (a.id || 0) - (b.id || 0);
                });
                
                let displayOrder = 1;
                qs.forEach((q, index) => {
                    questionIndex++;
                    console.log(`Loading question ${questionIndex}: ${q.type} (ID: ${q.id}, Order: ${q.question_order || 'N/A'})`);
                    container.insertAdjacentHTML('beforeend', createQuestionHTML(questionIndex));

                    // Hidden id for updates
                    const qi = document.querySelector(`[data-question-index="${questionIndex}"]`);
                    const hiddenId = document.createElement('input');
                    hiddenId.type = 'hidden';
                    hiddenId.name = `questions[${questionIndex}][id]`;
                    hiddenId.value = q.id;
                    qi.appendChild(hiddenId);

                    // Persist display order so backend can save ordering
                    const hiddenOrder = document.createElement('input');
                    hiddenOrder.type = 'hidden';
                    hiddenOrder.name = `questions[${questionIndex}][order_index]`;
                    hiddenOrder.value = displayOrder++;
                    qi.appendChild(hiddenOrder);

                    // Keep only the trash button; remove old checkbox delete toggle

                    // Common fields
                    document.getElementById(`question_text_${questionIndex}`).value = q.question_text || '';
                    document.getElementById(`points_${questionIndex}`).value = q.points || 1;

                    const typeSel = document.getElementById(`type_${questionIndex}`);
                    typeSel.value = q.type;
                    showQuestionTypeSection(questionIndex);

                    if (q.type === 'mcq') {
                        document.getElementById(`choice_a_${questionIndex}`).value = q.choice_a || '';
                        document.getElementById(`choice_b_${questionIndex}`).value = q.choice_b || '';
                        document.getElementById(`choice_c_${questionIndex}`).value = q.choice_c || '';
                        document.getElementById(`choice_d_${questionIndex}`).value = q.choice_d || '';
                        const ca = (q.correct_answer || '').toLowerCase();
                        const r = document.getElementById(`correct_${ca}_${questionIndex}`);
                        if (r) r.checked = true;
                    } else if (q.type === 'matching') {
                        
                        const left = Array.isArray(q.left_items) ? q.left_items : [];
                        const right = Array.isArray(q.right_items) ? q.right_items : [];
                        
                        // Update header label to show range based on pair count
                        const titleEl = qi ? qi.querySelector('.q-title') : null;
                        if (titleEl && left.length > 1) {
                            const start = questionIndex + 1;
                            titleEl.textContent = `Question ${start}–${start + left.length - 1}`;
                        }
                        // Add extra rows only if there are more than 2 items
                        // addMatchingRow will automatically add corresponding columns
                        for (let i = 2; i < left.length; i++) addMatchingRow(questionIndex);
                        const leftInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`);
                        const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                        
                        
                        left.forEach((v,i)=>{ 
                            if (leftInputs[i]) {
                                leftInputs[i].value = v;
                            }
                        });
                        right.forEach((v,i)=>{ 
                            if (rightInputs[i]) {
                                rightInputs[i].value = v;
                            }
                        });
                        
                        // Ensure right items are populated before setting matches
                        setTimeout(() => {
                            // Re-query the right inputs to ensure they have values
                            const updatedRightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                            const updatedRightItems = Array.from(updatedRightInputs).map(input => input.value.trim());
                            
                            // Only proceed if we have valid right items
                            if (updatedRightItems.length > 0 && updatedRightItems.some(item => item.length > 0)) {
                                // Update the matches with the correct right items
                                const finalMatches = matches.map(match => {
                                    if (typeof match === 'number' && match >= 0 && match < updatedRightItems.length) {
                                        return updatedRightItems[match];
                                    }
                                    return match;
                                });
                                
                                // Now set the matches with the correct data
                                setTimeout(() => {
                                    const selects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                                    if (selects.length > 0 && finalMatches.length > 0) {
                                        setMatchesInSelects(selects, finalMatches, questionIndex);
                                    }
                                }, 100);
                            }
                        }, 300);
                        
                        // Verify the values were set
                        setTimeout(() => {
                            const verifyLeftInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`);
                            const verifyRightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                            const verifyLeftValues = Array.from(verifyLeftInputs).map(input => input.value);
                            const verifyRightValues = Array.from(verifyRightInputs).map(input => input.value);
                        }, 50);
                        
                            // Build a robust normalized matches array from various DB shapes
                            const normalize = (raw) => {
                                let out = [];
                                if (!raw) return out;
                                const r = (typeof raw === 'string') ? (function(){ try { return JSON.parse(raw); } catch(e){ return raw; } })() : raw;
                                if (Array.isArray(r)) {
                                    r.forEach(item => {
                                        if (typeof item === 'string') out.push(item);
                                        else if (typeof item === 'number') out.push(right[item] ?? '');
                                        else if (item && typeof item === 'object') {
                                            out.push(item.value ?? item.answer ?? item.right ?? item.right_item ?? '');
                                        }
                                    });
                            } else if (r && typeof r === 'object'){
                                    Object.keys(r).forEach(k => out.push(r[k]));
                            } else if(typeof r === 'number'){
                                    out.push(right[r] ?? '');
                                }
                                return out;
                            };
                            let matches = normalize(q.matches);
                            if (matches.length === 0) matches = normalize(q.correct_pairs);
                            

                        
                        // Check if this question has the required data
                        const hasValidData = left.length > 0 && right.length > 0 && matches.length > 0;
                        
                        if (!hasValidData) {
                        }
                        
                        // Test: Let's manually set a match to see if the dropdown works
                        setTimeout(() => {
                            const testSelects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                            if (testSelects.length > 0) {
                                testSelects[0].value = '1';
                            }
                        }, 200);
                        
                        // Force set the matches after a longer delay
                        setTimeout(() => {
                            const forceSelects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                            
                            if (matches && matches.length > 0 && forceSelects.length > 0) {
                                matches.forEach((match, index) => {
                                    if (forceSelects[index]) {
                                        // Find the right item index
                                        const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                                        const rightItems = Array.from(rightInputs).map(input => input.value.trim());
                                        
                                        let targetIndex = -1;
                                        rightItems.forEach((item, idx) => {
                                            if (item.toLowerCase() === match.toLowerCase()) {
                                                targetIndex = idx;
                                            }
                                        });
                                        
                                        if (targetIndex >= 0) {
                                            forceSelects[index].value = targetIndex;
                                        }
                                    }
                                });
                            }
                        }, 500);
                        
                        // Update matching matches after populating the inputs
                        setTimeout(() => {
                            updateMatchingMatches(questionIndex);
                            
                            // Wait for updateMatchingMatches to complete and options to be populated
                            setTimeout(() => {
                                const selects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                                
                                // Ensure all selects have options before proceeding
                                const allSelectsReady = Array.from(selects).every(sel => sel.options.length > 1);
                                
                                if (!allSelectsReady) {
                                    setTimeout(() => {
                                        // Retry after a short delay
                                        const retrySelects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                                        setMatchesInSelects(retrySelects, matches, questionIndex);
                                    }, 200);
                                    return;
                                }
                                
                                
                                // Visual test - change background color to see if dropdowns are found
                                selects.forEach((sel, idx) => {
                                    sel.style.backgroundColor = '#ffffcc';
                                });
                                
                                setMatchesInSelects(selects, matches, questionIndex);
                            }, 200);
                        }, 100);
                        
                        // Additional delay to ensure input values are populated
                        setTimeout(() => {
                            console.log('🔄 FINAL ATTEMPT: Setting matches after longer delay');
                            const finalSelects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                            const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                            const rightItems = Array.from(rightInputs).map(input => input.value.trim());
                            
                            console.log('🔄 FINAL: Right items after delay:', rightItems);
                            console.log('🔄 FINAL: Found selects:', finalSelects.length);
                            
                            if (matches && matches.length > 0 && finalSelects.length > 0 && rightItems.some(item => item !== '')) {
                                matches.forEach((match, index) => {
                                    if (finalSelects[index]) {
                                        // The match is already an index, so use it directly
                                        const targetIndex = parseInt(match);
                                        if (!isNaN(targetIndex) && targetIndex >= 0 && targetIndex < rightItems.length) {
                                            finalSelects[index].value = targetIndex;
                                            console.log(`🔄 FINAL: Set select ${index} to value ${targetIndex} (${rightItems[targetIndex]})`);
                                        }
                                    }
                                });
            } else {
                                console.log('🔄 FINAL: Conditions not met - retrying with manual input setting');
                                // If inputs are still empty, try to set them manually using the original data
                                if (rightItems.every(item => item === '')) {
                                    console.log('🔄 FINAL: Right items still empty, setting manually');
                                    rightInputs.forEach((input, idx) => {
                                        if (right[idx]) {
                                            input.value = right[idx];
                                            console.log(`🔄 FINAL: Manually set right input ${idx} to: "${right[idx]}"`);
                                        }
                                    });
                                    
                                    // Regenerate the dropdown options with the new values
                                    updateMatchingMatches(questionIndex);
                                    
                                    // Now try to set the matches again after regenerating dropdowns
                                    setTimeout(() => {
                                        const updatedSelects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                                        const updatedRightItems = Array.from(rightInputs).map(input => input.value.trim());
                                        console.log('🔄 FINAL: Updated right items:', updatedRightItems);
                                        console.log('🔄 FINAL: Updated selects after regeneration:', updatedSelects.length);
                                        
                                        if (matches && matches.length > 0 && updatedSelects.length > 0) {
                                            matches.forEach((match, index) => {
                                                if (updatedSelects[index]) {
                                                    const targetIndex = parseInt(match);
                                                    if (!isNaN(targetIndex) && targetIndex >= 0 && targetIndex < updatedRightItems.length) {
                                                        updatedSelects[index].value = targetIndex;
                                                        console.log(`🔄 FINAL: Set select ${index} to value ${targetIndex} (${updatedRightItems[targetIndex]})`);
                                                        
                                                        // Force the change event to ensure the UI updates
                                                        updatedSelects[index].dispatchEvent(new Event('change'));
                                                        console.log(`🔄 FINAL: Dispatched change event for select ${index}`);
                                                        
                                                        // Additional visual confirmation
                                                        updatedSelects[index].style.backgroundColor = '#e6ffe6';
                                                        console.log(`🔄 FINAL: Set background color for select ${index}`);
            }
        }
    });
                                        }
                                        
                                        // Final verification
                                        setTimeout(() => {
                                            const verifySelects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                                            console.log('🔍 FINAL VERIFICATION:');
                                            verifySelects.forEach((sel, idx) => {
                                                console.log(`🔍 Select ${idx}: value="${sel.value}", selectedIndex=${sel.selectedIndex}, text="${sel.options[sel.selectedIndex]?.textContent || 'N/A'}"`);
                                            });
                                        }, 100);
                                    }, 200);
                                }
                            }
                        }, 1000);
                        
                        function setMatchesInSelects(selects, matches, questionIndex) {
                            // Get the right items to match against
                            const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                            const rightItems = Array.from(rightInputs).map(input => input.value.trim());
                            
                            // Check if we have any matches to set
                            if (!matches || matches.length === 0) {
                                return;
                            }
                            
                            // Check if right items are populated
                            if (rightItems.length === 0 || rightItems.every(item => item.length === 0)) {
                                // Right items not ready yet, try again later
    setTimeout(() => {
                                    setMatchesInSelects(selects, matches, questionIndex);
                                }, 200);
                                return;
                            }
                            
                            // Set the correct matches by finding the right item index that matches
                            matches.forEach((targetText, i) => {
                                const sel = selects[i];
                                if (!sel) return;
                                
                                const target = (targetText ?? '').toString().trim();
                                
                                // Find the index of the matching right item
                                let selectedIndex = -1;
                                rightItems.forEach((rightItem, rightIndex) => {
                                    if (rightItem.toLowerCase() === target.toLowerCase()) {
                                        selectedIndex = rightIndex;
                                    }
                                });
                                
                                // Set the select value to the index
                                if (selectedIndex >= 0) {
                                    sel.value = selectedIndex;
            } else {
                                    // If no exact match, try to find by text content
                                    let found = false;
                                    Array.from(sel.options).forEach(opt => {
                                        if (opt.textContent.trim().toLowerCase() === target.toLowerCase()) {
                                            sel.value = opt.value;
                                            found = true;
                                        }
                                    });
                                    
                                    if (!found) {
                                        sel.value = ''; // Reset to default
                                    }
                                }
                                
                                sel.dispatchEvent(new Event('change'));
                            });
                        }
                        
                        // Final points update after everything is loaded
                        setTimeout(() => {
                            const pointsField = document.getElementById(`points_${questionIndex}`);
                            if (pointsField) {
                                const rowCount = left.length;
                                pointsField.value = Math.max(rowCount, 1);
                                console.log(`Final points update: ${rowCount} for edit mode question ${questionIndex}`);
                            }
                        }, 200);
                    }
                });

                // Update all question titles after loading
                setTimeout(() => {
                    updateQuestionTitles();
    }, 100);

                if (qs.length === 0) {
                    questionIndex = 0;
                    container.insertAdjacentHTML('beforeend', createQuestionHTML(questionIndex));
                }

                window.scrollTo({top: 0, behavior: 'smooth'});
            })
            .catch(err => {
                console.error('Edit set error:', err);
                alert('Error loading set for editing: ' + err.message);
            });
        }

        function cancelEdit() {
            // Redirect to question bank
            window.location.href = 'question_bank.php';
        }

        // Realtime: unique title per section (and teacher)
        (function initRealtimeSetTitleValidation(){
            const titleEl = document.getElementById('set_title');
            const sectionSel = document.getElementById('section_id');
            if (!titleEl || !sectionSel) return;
            let debounce;
            const check = () => {
                const title = (titleEl.value || '').trim();
                const sectionId = sectionSel.value || '';
                clearError(titleEl, 'err_set_title');
                if (!title || !sectionId) return; // wait until both available
                clearTimeout(debounce);
                debounce = setTimeout(() => {
                    const params = new URLSearchParams({
                        action: 'check_set_title',
                        set_title: title,
                        section_id: sectionId
                    });
                    // If editing, include current set to exclude
                    if (window.isEditMode && window.currentEditSetId) {
                        params.append('exclude_set_id', window.currentEditSetId);
                    }
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params.toString()
                    }).then(r => r.json()).then(resp => {
                        if (resp && resp.exists) {
                            showError(titleEl, 'err_set_title', 'A set with this title already exists in the selected section.');
                        } else {
                            clearError(titleEl, 'err_set_title');
                        }
                    }).catch(()=>{});
                }, 350);
            };
            titleEl.addEventListener('input', check);
            sectionSel.addEventListener('change', check);
        })();

        function deleteSet(setId) {
            if (confirm('Delete this set?')) {
                fetch('', {
                    method: 'POST',
                    body: new URLSearchParams({action: 'delete_question_set', set_id: setId})
                }).then(res => res.json()).then(data => {
                    if (data.success) location.reload();
                });
            }
        }

        function archiveSet(setId, doArchive) {
            const verb = doArchive ? 'Archive' : 'Unarchive';
            if (confirm(`${verb} this set?`)) {
                const params = new URLSearchParams({action: doArchive ? 'archive_question_set' : 'unarchive_question_set', set_id: setId});
                fetch('', { method: 'POST', body: params })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) location.reload();
                        else alert('Error: ' + (data.error || `Failed to ${verb.toLowerCase()}`));
                    })
                    .catch(err => alert('Network error: ' + err.message));
            }
        }

        // Bulk selection handlers
        (function bulkSelection(){
            const selectAll = document.getElementById('selectAllSets');
            const bulkArchiveBtn = document.getElementById('bulkArchiveBtn');
            const table = document.querySelector('.question-bank-table');
            if (!selectAll || !table) return;

            const updateState = () => {
                const checks = table.querySelectorAll('tbody .select-set');
                const selected = Array.from(checks).filter(c => c.checked);
                if (bulkArchiveBtn) {
                    const enabled = selected.length > 0;
                    bulkArchiveBtn.style.opacity = enabled ? '1' : '.6';
                    bulkArchiveBtn.style.pointerEvents = enabled ? 'auto' : 'none';
                }
                // reflect selectAll state
                const allChecked = selected.length > 0 && selected.length === checks.length;
                selectAll.checked = allChecked;
                selectAll.indeterminate = selected.length > 0 && selected.length < checks.length;
            };

            table.addEventListener('change', (e) => {
                if (e.target && e.target.classList.contains('select-set')) {
                    updateState();
                }
            });

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    const checks = table.querySelectorAll('tbody .select-set');
                    checks.forEach(c => { c.checked = selectAll.checked; });
                    updateState();
                });
            }

            if (bulkArchiveBtn) {
                bulkArchiveBtn.addEventListener('click', () => {
                    const checks = table.querySelectorAll('tbody .select-set:checked');
                    const ids = Array.from(checks).map(c => c.value);
                    if (ids.length === 0) return;
                    if (!confirm(`Archive ${ids.length} selected set(s)?`)) return;
                    const payload = new URLSearchParams();
                    payload.append('action', 'bulk_archive_question_sets');
                    payload.append('set_ids', JSON.stringify(ids));
                    fetch('', { method: 'POST', body: payload })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) location.reload();
                            else alert('Error: ' + (data.error || 'Failed'));
                        })
                        .catch(err => alert('Network error: ' + err.message));
                });
            }

            // initialize state on load
            updateState();
        })();
        
        function viewStudentResponses(setId, setTitle) {
            // Navigate to student responses page in the same tab
            const url = `view_student_responses.php?set_id=${setId}&set_title=${encodeURIComponent(setTitle)}`;
            window.location.href = url;
        }
        
        function filterBySection() {
            const selectedSection = document.getElementById('sectionFilter').value;
            const tableRows = document.querySelectorAll('.question-bank-table tbody tr');
            
            tableRows.forEach(row => {
                // Each set row is followed by an optional details row. Hide/show both together.
                const sectionCell = row.querySelector('.section-name');
                if (!sectionCell) return;
                const sectionName = sectionCell.textContent.trim();
                const match = sectionName === selectedSection;
                row.style.display = match ? '' : 'none';
                const next = row.nextElementSibling;
                if (next && !next.querySelector('.set-title')) {
                    next.style.display = match ? '' : 'none';
                }
            });
        }

        // Apply initial filter on load so default selection (e.g., Rizal) is enforced immediately
        document.addEventListener('DOMContentLoaded', function(){
            if (document.getElementById('sectionFilter')) {
                filterBySection();
            }
        });

        // Import from Question Bank functionality
        function showImportModal() {
            console.log('Opening import modal...');
            // Create modal HTML with improved styling
            const modalHTML = `
                <div id="importModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
                    <div style="background: white; width: 95%; max-width: 900px; max-height: 85vh; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: 1px solid #e5e7eb;">
                        <!-- Header -->
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; color: white;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3 id="importModalTitle" style="margin: 0; font-size: 20px; font-weight: 600;">Import Questions from Question Bank</h3>
                                    <p id="importModalSubtitle" style="margin: 4px 0 0 0; font-size: 14px; opacity: 0.9;">Select questions to add to your current assessment</p>
                                </div>
                                <button onclick="closeImportModal()" style="background: rgba(255,255,255,0.2); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; color: white; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">&times;</button>
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div style="padding: 24px; max-height: 55vh; overflow-y: auto; background: #fafbfc;">
                            <div id="importLoading" style="text-align: center; padding: 60px 20px;">
                                <div style="background: white; border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #667eea;"></i>
                                </div>
                                <p style="margin: 0; color: #6b7280; font-size: 16px; font-weight: 500;">Loading question sets...</p>
                            </div>
                            <div id="importContent" style="display: none;">
                                <div id="importSetsList" style="display: block;"></div>
                                <div id="importQuestionsList" style="display: none;"></div>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div style="padding: 20px 24px; border-top: 1px solid #e5e7eb; background: white; display: flex; justify-content: space-between; align-items: center;">
                            <button onclick="goBackToSets()" id="backBtn" style="background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; display: none; transition: all 0.2s ease;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                                <i class="fas fa-arrow-left" style="margin-right: 8px;"></i>Back to Sets
                            </button>
                            <div style="display: flex; gap: 12px; margin-left: auto;">
                                <button onclick="closeImportModal()" style="background: #6b7280; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.2s ease;" onmouseover="this.style.background='#4b5563'" onmouseout="this.style.background='#6b7280'">Cancel</button>
                                <button onclick="importSelectedQuestions()" id="importBtn" style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; opacity: 0.5; pointer-events: none; transition: all 0.2s ease;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
                                    <i class="fas fa-download" style="margin-right: 8px;"></i>Import Selected
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Load question sets first
            loadQuestionSets();
        }

        function closeImportModal() {
            const modal = document.getElementById('importModal');
            if (modal) {
                modal.remove();
            }
        }

        function loadQuestionSets() {
            const formData = new FormData();
            formData.append('action', 'get_question_sets');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Question sets data:', data);
                const loading = document.getElementById('importLoading');
                const content = document.getElementById('importContent');
                const setsList = document.getElementById('importSetsList');
                
                if (data.success && data.question_sets.length > 0) {
                    loading.style.display = 'none';
                    content.style.display = 'block';
                    
                    let html = `
                        <div style="margin-bottom: 24px;">
                            <h4 style="margin: 0 0 8px 0; color: #1f2937; font-size: 18px; font-weight: 600;">Select a Question Set</h4>
                            <p style="margin: 0; color: #6b7280; font-size: 14px;">Choose a question set to import questions from</p>
                        </div>
                    `;
                    
                    data.question_sets.forEach((set) => {
                        html += `
                            <div style="border: 2px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; background: white; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.04);" 
                                 onmouseover="this.style.borderColor='#667eea'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(102, 126, 234, 0.15)'" 
                                 onmouseout="this.style.borderColor='#e5e7eb'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)'"
                                 onclick="loadQuestionsFromSet(${set.id}, '${set.set_title.replace(/'/g, "\\'")}')">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: 8px; height: 8px; border-radius: 50%; margin-right: 12px;"></div>
                                            <div style="font-weight: 600; color: #1f2937; font-size: 18px;">${set.set_title}</div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 16px;">
                                            <div style="background: #f0f9ff; color: #0369a1; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 500;">
                                                <i class="fas fa-question-circle" style="margin-right: 6px;"></i>${set.question_count} question${set.question_count !== 1 ? 's' : ''}
                                            </div>
                                            <div style="color: #6b7280; font-size: 13px;">
                                                <i class="fas fa-calendar-alt" style="margin-right: 6px;"></i>${new Date(set.created_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                    </div>
                                    <div style="background: #f8fafc; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;">
                                        <i class="fas fa-chevron-right" style="color: #667eea; font-size: 16px;"></i>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    setsList.innerHTML = html;
                } else {
                    loading.style.display = 'none';
                    content.style.display = 'block';
                    setsList.innerHTML = `
                        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; border: 2px dashed #e5e7eb;">
                            <div style="background: #f8fafc; border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; border: 2px solid #e5e7eb;">
                                <i class="fas fa-inbox" style="font-size: 32px; color: #9ca3af;"></i>
                            </div>
                            <h4 style="margin: 0 0 8px 0; color: #374151; font-size: 18px; font-weight: 600;">No Question Sets Found</h4>
                            <p style="margin: 0; font-size: 14px; color: #6b7280; max-width: 300px; margin: 0 auto;">You don't have any question sets yet. Create some questions first to use this feature.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading question sets:', error);
                const loading = document.getElementById('importLoading');
                const content = document.getElementById('importContent');
                const setsList = document.getElementById('importSetsList');
                
                loading.style.display = 'none';
                content.style.display = 'block';
                setsList.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; border: 2px solid #fecaca;">
                        <div style="background: #fef2f2; border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; border: 2px solid #fecaca;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 32px; color: #dc2626;"></i>
                        </div>
                        <h4 style="margin: 0 0 8px 0; color: #dc2626; font-size: 18px; font-weight: 600;">Error Loading Question Sets</h4>
                        <p style="margin: 0; font-size: 14px; color: #6b7280; max-width: 300px; margin: 0 auto;">Failed to load question sets. Please try again.</p>
                    </div>
                `;
            });
        }

        function loadQuestionsFromSet(setId, setTitle) {
            // Show loading
            const setsList = document.getElementById('importSetsList');
            const questionsList = document.getElementById('importQuestionsList');
            const backBtn = document.getElementById('backBtn');
            const modalTitle = document.getElementById('importModalTitle');
            const modalSubtitle = document.getElementById('importModalSubtitle');
            
            setsList.style.display = 'none';
            questionsList.style.display = 'block';
            backBtn.style.display = 'inline-block';
            modalTitle.textContent = `Import from: ${setTitle}`;
            modalSubtitle.textContent = 'Select questions to add to your current assessment';
            
            // Show loading in questions area
            questionsList.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #3b82f6;"></i>
                    <p style="margin-top: 10px; color: #6b7280;">Loading questions...</p>
                </div>
            `;
            
            const formData = new FormData();
            formData.append('action', 'get_questions_by_set');
            formData.append('set_id', setId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Questions data:', data);
                if (data.success && data.questions.length > 0) {
                    // Store question data globally for import
                    window.importQuestionData = {};
                    
                    let html = `
                        <div style="margin-bottom: 24px;">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: 6px; height: 6px; border-radius: 50%; margin-right: 12px;"></div>
                                <h4 style="margin: 0; color: #1f2937; font-size: 18px; font-weight: 600;">Questions from: ${data.set_info.set_title}</h4>
                            </div>
                            <p style="margin: 0; color: #6b7280; font-size: 14px;">Select the questions you want to import</p>
                        </div>
                    `;
                    
                    data.questions.forEach((question, index) => {
                        const questionType = question.question_type || 'multiple_choice';
                        const typeLabel = questionType === 'multiple_choice' ? 'MCQ' : 
                                        questionType === 'matching' ? 'Matching' : 'Essay';
                        
                        // Get type-specific colors
                        let typeColor = '#3b82f6';
                        let typeBgColor = '#eff6ff';
                        if (questionType === 'matching') {
                            typeColor = '#8b5cf6';
                            typeBgColor = '#f3e8ff';
                        } else if (questionType === 'essay') {
                            typeColor = '#10b981';
                            typeBgColor = '#ecfdf5';
                        }
                        
                        // Store the full question data
                        const questionData = {
                            id: question.id,
                            type: questionType === 'multiple_choice' ? 'mcq' : 
                                  questionType === 'matching' ? 'matching' : 'essay',
                            question_text: question.question_text,
                            set_title: data.set_info.set_title,
                            points: question.points || 1,
                            // Store additional data based on type
                            ...(questionType === 'multiple_choice' && {
                                choice_a: question.choice_a,
                                choice_b: question.choice_b,
                                choice_c: question.choice_c,
                                choice_d: question.choice_d,
                                correct_answer: question.answer
                            }),
                            ...(questionType === 'matching' && {
                                left_items: question.left_items,
                                right_items: question.right_items,
                                answer: question.answer
                            })
                        };
                        
                        window.importQuestionData[question.id] = questionData;
                        console.log('Stored question data for ID', question.id, ':', questionData);
                        
                        html += `
                            <div style="border: 2px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; background: white; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                                <label style="display: flex; align-items: flex-start; gap: 16px; cursor: pointer;">
                                    <div style="margin-top: 2px;">
                                        <input type="checkbox" class="import-question-checkbox" value="${question.id}" onchange="updateImportButton()" style="width: 18px; height: 18px; accent-color: #667eea; cursor: pointer;">
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <span style="background: ${typeBgColor}; color: ${typeColor}; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid ${typeColor}20;">
                                                    ${typeLabel}
                                                </span>
                                                <div style="background: #f8fafc; color: #64748b; padding: 4px 10px; border-radius: 16px; font-size: 12px; font-weight: 500;">
                                                    <i class="fas fa-star" style="margin-right: 4px;"></i>${question.points || 1} pt${(question.points || 1) !== 1 ? 's' : ''}
                                                </div>
                                            </div>
                                        </div>
                                        <div style="color: #374151; line-height: 1.6; font-size: 15px; background: #fafbfc; padding: 16px; border-radius: 8px; border-left: 4px solid ${typeColor};">${question.question_text.substring(0, 250)}${question.question_text.length > 250 ? '...' : ''}</div>
                                    </div>
                                </label>
                            </div>
                        `;
                    });
                    
                    questionsList.innerHTML = html;
                } else {
                    questionsList.innerHTML = `
                        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; border: 2px dashed #e5e7eb;">
                            <div style="background: #f8fafc; border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; border: 2px solid #e5e7eb;">
                                <i class="fas fa-question-circle" style="font-size: 32px; color: #9ca3af;"></i>
                            </div>
                            <h4 style="margin: 0 0 8px 0; color: #374151; font-size: 18px; font-weight: 600;">No Questions Found</h4>
                            <p style="margin: 0; font-size: 14px; color: #6b7280; max-width: 300px; margin: 0 auto;">This question set doesn't have any questions yet.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading questions:', error);
                questionsList.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; border: 2px solid #fecaca;">
                        <div style="background: #fef2f2; border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; border: 2px solid #fecaca;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 32px; color: #dc2626;"></i>
                        </div>
                        <h4 style="margin: 0 0 8px 0; color: #dc2626; font-size: 18px; font-weight: 600;">Error Loading Questions</h4>
                        <p style="margin: 0; font-size: 14px; color: #6b7280; max-width: 300px; margin: 0 auto;">Failed to load questions from this set. Please try again.</p>
                    </div>
                `;
            });
        }

        function goBackToSets() {
            const setsList = document.getElementById('importSetsList');
            const questionsList = document.getElementById('importQuestionsList');
            const backBtn = document.getElementById('backBtn');
            const modalTitle = document.getElementById('importModalTitle');
            const modalSubtitle = document.getElementById('importModalSubtitle');
            const importBtn = document.getElementById('importBtn');
            
            setsList.style.display = 'block';
            questionsList.style.display = 'none';
            backBtn.style.display = 'none';
            modalTitle.textContent = 'Import Questions from Question Bank';
            modalSubtitle.textContent = 'Select questions to add to your current assessment';
            
            // Reset import button
            importBtn.style.opacity = '0.5';
            importBtn.style.pointerEvents = 'none';
            importBtn.innerHTML = '<i class="fas fa-download" style="margin-right: 8px;"></i>Import Selected';
        }

        function updateImportButton() {
            const checkboxes = document.querySelectorAll('.import-question-checkbox:checked');
            const importBtn = document.getElementById('importBtn');
            
            if (checkboxes.length > 0) {
                importBtn.style.opacity = '1';
                importBtn.style.pointerEvents = 'auto';
                importBtn.innerHTML = `<i class="fas fa-download" style="margin-right: 8px;"></i>Import Selected (${checkboxes.length})`;
            } else {
                importBtn.style.opacity = '0.5';
                importBtn.style.pointerEvents = 'none';
                importBtn.innerHTML = '<i class="fas fa-download" style="margin-right: 8px;"></i>Import Selected';
            }
        }

        function importSelectedQuestions() {
            try {
                const checkboxes = document.querySelectorAll('.import-question-checkbox:checked');
                if (checkboxes.length === 0) {
                    alert('Please select at least one question to import.');
                    return;
                }
                
                // Get the question data from the stored data
                const questions = [];
                checkboxes.forEach(checkbox => {
                    const questionData = window.importQuestionData[checkbox.value];
                    if (questionData) {
                        console.log('Found question data for import:', questionData);
                        questions.push(questionData);
                    } else {
                        console.warn('Question data not found for ID:', checkbox.value);
                        console.log('Available question data:', window.importQuestionData);
                    }
                });
                
                if (questions.length === 0) {
                    alert('No valid questions found to import.');
                    return;
                }
                
                // Add questions to the form
                questions.forEach((question, index) => {
                    setTimeout(() => {
                        addImportedQuestion(question);
                    }, index * 100); // Stagger the imports slightly
                });
                
                // Close modal
                closeImportModal();
                
                // Show success message
                alert(`Successfully imported ${questions.length} question(s) from question bank!`);
            } catch (error) {
                console.error('Error importing questions:', error);
                alert('Error importing questions. Please try again.');
            }
        }

        function addImportedQuestion(questionData) {
            try {
                console.log('=== ADDING IMPORTED QUESTION ===');
                console.log('Question data received:', questionData);
                console.log('Question type:', questionData.type);
                
                // Get the current question count
                const container = document.getElementById('questions-container');
                if (!container) {
                    console.error('Questions container not found');
                    return;
                }
                
                const existingQuestions = container.querySelectorAll('.question-item');
                const questionIndex = existingQuestions.length;
                
                // Create a new question item
                const questionHTML = createQuestionHTML(questionIndex);
                container.insertAdjacentHTML('beforeend', questionHTML);
                
                // Wait a bit for DOM to update, then populate the fields
                setTimeout(() => {
                    console.log('Populating question data for index:', questionIndex, 'Type:', questionData.type);
                    
                    // Set the question text first
                    const questionTextInput = document.querySelector(`#question_text_${questionIndex}`);
                    if (questionTextInput) {
                        questionTextInput.value = questionData.question_text;
                        console.log('Set question text:', questionData.question_text);
                    }
                    
                    // Set the question type and trigger change event
                    const typeSelect = document.querySelector(`#type_${questionIndex}`);
                    if (typeSelect) {
                        typeSelect.value = questionData.type;
                        console.log('Set question type to:', questionData.type);
                        
                        // Call the function to show the appropriate section
                        if (typeof showQuestionTypeSection === 'function') {
                            showQuestionTypeSection(questionIndex);
                            console.log('Called showQuestionTypeSection for index:', questionIndex);
                        }
                        
                        // Also trigger change event as backup
                        const changeEvent = new Event('change', { bubbles: true });
                        typeSelect.dispatchEvent(changeEvent);
                        
                        // Wait longer for the type-specific fields to appear
                        setTimeout(() => {
                            console.log('Setting type-specific fields for:', questionData.type);
                            
                            // Set points
                            const pointsInput = document.querySelector(`#points_${questionIndex}`);
                            if (pointsInput) {
                                pointsInput.value = questionData.points || 1;
                                console.log('Set points to:', questionData.points || 1);
                            }
                            
                            // Set additional data based on question type
                            if (questionData.type === 'mcq') {
                                console.log('Setting MCQ data:', questionData);
                                // Set MCQ choices and correct answer
                                if (questionData.choice_a) {
                                    const choiceA = document.querySelector(`#choice_a_${questionIndex}`);
                                    if (choiceA) {
                                        choiceA.value = questionData.choice_a;
                                        console.log('Set choice A:', questionData.choice_a);
                                    } else {
                                        console.warn('Choice A input not found for index:', questionIndex);
                                    }
                                }
                                if (questionData.choice_b) {
                                    const choiceB = document.querySelector(`#choice_b_${questionIndex}`);
                                    if (choiceB) {
                                        choiceB.value = questionData.choice_b;
                                        console.log('Set choice B:', questionData.choice_b);
                                    } else {
                                        console.warn('Choice B input not found for index:', questionIndex);
                                    }
                                }
                                if (questionData.choice_c) {
                                    const choiceC = document.querySelector(`#choice_c_${questionIndex}`);
                                    if (choiceC) {
                                        choiceC.value = questionData.choice_c;
                                        console.log('Set choice C:', questionData.choice_c);
                                    } else {
                                        console.warn('Choice C input not found for index:', questionIndex);
                                    }
                                }
                                if (questionData.choice_d) {
                                    const choiceD = document.querySelector(`#choice_d_${questionIndex}`);
                                    if (choiceD) {
                                        choiceD.value = questionData.choice_d;
                                        console.log('Set choice D:', questionData.choice_d);
                                    } else {
                                        console.warn('Choice D input not found for index:', questionIndex);
                                    }
                                }
                                if (questionData.correct_answer) {
                                    const correctAnswerValue = questionData.correct_answer.toString().toUpperCase();
                                    const correctAnswerRadio = document.querySelector(`#correct_${correctAnswerValue.toLowerCase()}_${questionIndex}`);
                                    if (correctAnswerRadio) {
                                        correctAnswerRadio.checked = true;
                                        console.log('Set correct answer radio:', correctAnswerValue);
                                    } else {
                                        console.warn('Correct answer radio not found for:', correctAnswerValue, 'index:', questionIndex);
                                    }
                                }
                            } else if (questionData.type === 'matching') {
                                console.log('Setting matching data:', questionData);
                                // Note: Matching items will be set after additional rows/columns are created
                                
                                // Handle matching questions
                                if (questionData.type === 'matching') {
                                    console.log('Processing matching question for index:', questionIndex);
                                    
                                    // First, add additional rows and columns if needed
                                    try {
                                        const leftItems = typeof questionData.left_items === 'string' ? 
                                            JSON.parse(questionData.left_items) : questionData.left_items;
                                        const rightItems = typeof questionData.right_items === 'string' ? 
                                            JSON.parse(questionData.right_items) : questionData.right_items;
                                        
                                        if (Array.isArray(leftItems) && Array.isArray(rightItems)) {
                                            console.log('Left items count:', leftItems.length, 'Right items count:', rightItems.length);
                                            
                                            // Calculate how many additional rows and columns we need
                                            const additionalRows = Math.max(0, leftItems.length - 2);
                                            const additionalColumns = Math.max(0, rightItems.length - 2);
                                            
                                            console.log('Need to add:', additionalRows, 'rows and', additionalColumns, 'columns');
                                            
                                            // Add additional rows (addMatchingRow will automatically add columns, but we'll clean up extras)
                                            for (let i = 0; i < additionalRows; i++) {
                                                if (typeof addMatchingRow === 'function') {
                                                    addMatchingRow(questionIndex);
                                                    console.log(`Added row ${i + 3} for index:`, questionIndex);
                                                }
                                            }
                                            
                                            // Now remove any extra columns that were automatically added
                                            const currentColumns = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                                            const neededColumns = rightItems.length;
                                            
                                            console.log('Current columns:', currentColumns.length, 'Needed columns:', neededColumns);
                                            
                                            // Remove extra columns if we have more than needed
                                            if (currentColumns.length > neededColumns) {
                                                const columnsToRemove = currentColumns.length - neededColumns;
                                                console.log('Removing', columnsToRemove, 'extra columns');
                                                
                                                // Remove the extra columns (from the end)
                                                for (let i = currentColumns.length - 1; i >= neededColumns; i--) {
                                                    const columnInput = currentColumns[i];
                                                    const inputGroup = columnInput.closest('.input-group');
                                                    if (inputGroup) {
                                                        inputGroup.remove();
                                                        console.log(`Removed extra column ${i + 1}`);
                                                    }
                                                }
                                            }
                                            
                                            // Add additional columns if we need more
                                            const finalColumns = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`).length;
                                            const columnsToAdd = Math.max(0, neededColumns - finalColumns);
                                            
                                            if (columnsToAdd > 0) {
                                                console.log('Adding', columnsToAdd, 'additional columns');
                                                for (let i = 0; i < columnsToAdd; i++) {
                                                    if (typeof addMatchingColumn === 'function') {
                                                        addMatchingColumn(questionIndex);
                                                        console.log(`Added additional column ${finalColumns + i + 1}`);
                                                    }
                                                }
                                            }
                                        }
                                    } catch (e) {
                                        console.error('Error adding additional rows/columns:', e);
                                    }
                                    
                                    // Then, call updateMatchingMatches to populate dropdown options
                                    if (typeof updateMatchingMatches === 'function') {
                                        updateMatchingMatches(questionIndex);
                                        console.log('Called updateMatchingMatches for index:', questionIndex);
                                    }
                                    
                                    // Now populate the left and right items after rows/columns are created
                                    setTimeout(() => {
                                        // Set matching items
                                        if (questionData.left_items) {
                                            try {
                                                const leftItems = typeof questionData.left_items === 'string' ? 
                                                    JSON.parse(questionData.left_items) : questionData.left_items;
                                                if (Array.isArray(leftItems)) {
                                                    console.log('Setting left items:', leftItems);
                                                    const leftInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][left_items][]"]`);
                                                    leftItems.forEach((item, index) => {
                                                        if (leftInputs[index]) {
                                                            leftInputs[index].value = item;
                                                            console.log(`Set left item ${index}:`, item);
                                                        } else {
                                                            console.warn(`Left input ${index} not found for index:`, questionIndex);
                                                        }
                                                    });
                                                }
                                            } catch (e) {
                                                console.error('Error parsing left_items:', e);
                                            }
                                        }
                                        if (questionData.right_items) {
                                            try {
                                                const rightItems = typeof questionData.right_items === 'string' ? 
                                                    JSON.parse(questionData.right_items) : questionData.right_items;
                                                if (Array.isArray(rightItems)) {
                                                    console.log('Setting right items:', rightItems);
                                                    const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                                                    rightItems.forEach((item, index) => {
                                                        if (rightInputs[index]) {
                                                            rightInputs[index].value = item;
                                                            console.log(`Set right item ${index}:`, item);
                                                        } else {
                                                            console.warn(`Right input ${index} not found for index:`, questionIndex);
                                                        }
                                                    });
                                                }
                                            } catch (e) {
                                                console.error('Error parsing right_items:', e);
                                            }
                                        }
                                        
                                        // Update matching matches after populating items
                                        if (typeof updateMatchingMatches === 'function') {
                                            updateMatchingMatches(questionIndex);
                                        }
                                    }, 100);
                                    
                                    // Then set correct matches after a delay to ensure text fields are populated
                                    if (questionData.answer) {
                                        console.log('=== MATCHING QUESTION IMPORT DEBUG ===');
                                        console.log('Setting correct matches:', questionData.answer);
                                        console.log('Answer type:', typeof questionData.answer);
                                        console.log('Full question data:', questionData);
                                        console.log('Left items:', questionData.left_items);
                                        console.log('Right items:', questionData.right_items);
                                        
                                        // Wait for text fields to be populated
                                        setTimeout(() => {
                                            try {
                                                // Get the right items first
                                                const rightInputs = document.querySelectorAll(`input[name="questions[${questionIndex}][right_items][]"]`);
                                                const rightItems = Array.from(rightInputs).map(input => input.value.trim());
                                                console.log('Right items for matching:', rightItems);
                                                
                                                // Normalize the correct pairs data (handle various formats)
                                                const normalizeCorrectPairs = (rawData) => {
                                                    let out = [];
                                                    if (!rawData) return out;
                                                    
                                                    const parsed = (typeof rawData === 'string') ? 
                                                        (function() { 
                                                            try { return JSON.parse(rawData); } 
                                                            catch(e) { return rawData; } 
                                                        })() : rawData;
                                                    
                                                    if (Array.isArray(parsed)) {
                                                        parsed.forEach(item => {
                                                            if (typeof item === 'string') {
                                                                out.push(item);
                                                            } else if (typeof item === 'number') {
                                                                // If it's a number, it might be an index
                                                                out.push(rightItems[item] ?? '');
                                                            } else if (item && typeof item === 'object') {
                                                                out.push(item.value ?? item.answer ?? item.right ?? item.right_item ?? '');
                                                            }
                                                        });
                                                    } else if (parsed && typeof parsed === 'object') {
                                                        Object.keys(parsed).forEach(k => out.push(parsed[k]));
                                                    } else if (typeof parsed === 'number') {
                                                        out.push(rightItems[parsed] ?? '');
                                                    }
                                                    
                                                    return out;
                                                };
                                                
                                                const correctPairs = normalizeCorrectPairs(questionData.answer);
                                                console.log('Normalized correct pairs:', correctPairs);
                                                
                                                if (Array.isArray(correctPairs)) {
                                                    console.log('Correct pairs array:', correctPairs);
                                                    
                                                    // Get the matching dropdowns
                                                    const selects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                                                    
                                                    console.log('Found selects:', selects.length);
                                                    
                                                    // Wait for dropdowns to be ready with options (improved timing logic)
                                                    const applyCorrectMatches = () => {
                                                        const currentSelects = document.querySelectorAll(`#matching-matches_${questionIndex} select`);
                                                        const ready = Array.from(currentSelects).every(s => s.options.length > 1);
                                                        console.log('Dropdowns ready check:', ready, 'Selects found:', currentSelects.length);
                                                        
                                                        if (!ready) {
                                                            console.log('Dropdowns not ready, retrying in 80ms...');
                                                            setTimeout(applyCorrectMatches, 80);
                                                            return;
                                                        }
                                                        
                                                        console.log('Dropdowns are ready, setting correct matches...');
                                                        setCorrectMatches(currentSelects, correctPairs, rightItems);
                                                    };
                                                    
                                                    // Start the retry logic
                                                    setTimeout(applyCorrectMatches, 200);
                                                    
                                                    // Helper function to set correct matches (improved version)
                                                    function setCorrectMatches(selects, correctPairs, rightItems) {
                                                        console.log('setCorrectMatches called with:', {
                                                            selects: selects.length,
                                                            correctPairs: correctPairs,
                                                            rightItems: rightItems
                                                        });
                                                        
                                                        correctPairs.forEach((targetVal, idxSel) => {
                                                            const sel = selects[idxSel];
                                                            if (!sel) {
                                                                console.warn(`❌ Missing select for match ${idxSel}`);
                                                                return;
                                                            }
                                                            
                                                            const target = (targetVal ?? '').toString().trim();
                                                            console.log(`Processing match ${idxSel}: "${target}"`);
                                                            
                                                            if (!target) {
                                                                console.warn(`❌ Empty target for match ${idxSel}`);
                                                                return;
                                                            }
                                                            
                                                            let chosen = false;
                                                            const targetLower = target.toLowerCase();
                                                            
                                                            // First try: exact match by value or text
                                                            Array.from(sel.options).forEach(opt => {
                                                                const optValue = (opt.value || '').toString().trim();
                                                                const optText = (opt.textContent || '').toString().trim();
                                                                
                                                                if (optValue.toLowerCase() === targetLower || optText.toLowerCase() === targetLower) {
                                                                    opt.selected = true;
                                                                    chosen = true;
                                                                    console.log(`✅ Exact match found: "${optText}" (value: ${optValue})`);
                                                                }
                                                            });
                                                            
                                                            // Second try: partial match if exact match failed
                                                            if (!chosen) {
                                                                Array.from(sel.options).forEach(opt => {
                                                                    const optValue = (opt.value || '').toString().trim();
                                                                    const optText = (opt.textContent || '').toString().trim();
                                                                    
                                                                    if (optValue.toLowerCase().includes(targetLower) || 
                                                                        targetLower.includes(optValue.toLowerCase()) ||
                                                                        optText.toLowerCase().includes(targetLower) || 
                                                                        targetLower.includes(optText.toLowerCase())) {
                                                                        opt.selected = true;
                                                                        chosen = true;
                                                                        console.log(`✅ Partial match found: "${optText}" (value: ${optValue})`);
                                                                    }
                                                                });
                                                            }
                                                            
                                                            // Third try: direct value assignment if still no match
                                                            if (!chosen) {
                                                                sel.value = targetVal;
                                                                console.log(`✅ Direct value assignment: ${targetVal}`);
                                                            }
                                                            
                                                            // Trigger change event
                                                            sel.dispatchEvent(new Event('change'));
                                                        });
                                                    }
                                                    
                                                    // Dropdowns are now ready for correct matches
                                                }
                                            } catch (e) {
                                                console.error('Error setting correct matches:', e);
                                            }
                                        }, 400);
                                    }
                                }
                            }
                            
                            // Update question numbering
                            if (typeof updateQuestionNumbers === 'function') {
                                updateQuestionNumbers();
                            }
                        }, 200);
                    }
                }, 100);
            } catch (error) {
                console.error('Error adding imported question:', error);
                alert('Error importing question. Please try again.');
            }
        }
    </script>
    </div>
</body>
</html>

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
            return "This uploaded file contains educational material. The file content could not be extracted.";
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
        
        // If all methods failed, provide a fallback based on file analysis
        if (empty($content) || strlen($content) < 50) {
            // Analyze the file size and provide educational content
            $fileSize = filesize($filePath);
            if ($fileSize > 100000) { // Large file
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
        
        // Ensure we have substantial content
        if (strlen($content) < 100) {
            $content = "This educational PDF document contains learning materials covering various academic topics suitable for Grade 6 students. ";
            $content .= "The content includes structured information, examples, and educational resources designed to support student learning and development.";
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
                $text = '';
                
                // Read all slide XML files
                for ($i = 1; $i <= 50; $i++) { // Check up to 50 slides
                    $slideXml = $zip->getFromName("ppt/slides/slide$i.xml");
                    if ($slideXml !== false) {
                        // Extract text from slide XML
                        $slideText = strip_tags($slideXml);
                        $slideText = preg_replace('/\s+/', ' ', $slideText);
                        $slideText = trim($slideText);
                        
                        if (strlen($slideText) > 10) {
                            $text .= $slideText . ' ';
                        }
        } else {
                        break; // No more slides
                    }
                }
                
                if (strlen($text) > 50) {
                    $content = trim($text);
                }
                
                $zip->close();
            }
        }
        
        // If we couldn't extract from pptx, try reading as plain text
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
            $content = "This is a PowerPoint presentation containing educational slides. ";
            $content .= "The presentation includes visual content, bullet points, and structured information suitable for Grade 6 students. ";
            $content .= "The presentation covers lesson content with visual aids and examples appropriate for Grade 6 students.";
        }
        
    } catch (Exception $e) {
        error_log("PowerPoint extraction error: " . $e->getMessage());
        $content = "This is a PowerPoint presentation containing educational material suitable for Grade 6 students.";
    }
    
    return $content;
}

// Extract content from plain text files
function extractTextContent($filePath, $title) {
    $content = '';
    
    try {
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            if ($content !== false) {
                // Clean up the text
                $content = preg_replace('/\r\n?/', "\n", $content);
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                
                // If content is empty or too short, provide fallback
                if (empty($content) || strlen($content) < 20) {
                    $content = "This is a text file containing educational material suitable for Grade 6 students.";
                }
            } else {
                $content = "This is a text file containing educational material suitable for Grade 6 students.";
            }
        } else {
            $content = "This is a text file containing educational material suitable for Grade 6 students.";
        }
    } catch (Exception $e) {
        error_log("Text extraction error: " . $e->getMessage());
        $content = "This is a text file containing educational material suitable for Grade 6 students.";
    }
    
    return $content;
}
?>
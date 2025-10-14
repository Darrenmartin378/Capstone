<?php
/**
 * Question Handler Class
 * Clean, modular system for question management
 */

class QuestionHandler {
    private $conn;
    
    public function __construct($databaseConnection) {
        $this->conn = $databaseConnection;
    }
    
    /**
     * Create a new question set
     */
    public function createQuestionSet($teacherId, $sectionId, $setTitle, $description = '') {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO question_sets (teacher_id, section_id, set_title, description) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param('iiss', $teacherId, $sectionId, $setTitle, $description);
            
            if ($stmt->execute()) {
                return ['success' => true, 'set_id' => $this->conn->insert_id];
            } else {
                return ['success' => false, 'error' => 'Failed to create question set'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create a new question
     */
    public function createQuestion($teacherId, $sectionId, $setId, $questionData) {
        try {
            // Debug logging
            error_log('createQuestion called with: teacherId=' . $teacherId . ', sectionId=' . $sectionId . ', setId=' . $setId);
            error_log('questionData: ' . print_r($questionData, true));
            
            $type = $questionData['type'];
            $questionText = trim($questionData['question_text']);
            $points = (int)($questionData['points'] ?? 1);
            $difficulty = isset($questionData['difficulty']) ? trim(strtolower($questionData['difficulty'])) : '';
            
            // Validate required fields
            if (empty($questionText)) {
                return ['success' => false, 'error' => 'Question text is required'];
            }
            
            // Use the provided setId
            if (!$setId || $setId <= 0) {
                return ['success' => false, 'error' => 'Invalid question set ID'];
            }
            
            // Prepare choices and answer_key based on type
            $choices = null;
            $answerKey = null;
            
            switch ($type) {
                case 'mcq':
                    $result = $this->prepareMCQData($questionData);
                    if (!$result['success']) {
                        return $result;
                    }
                    $choices = $result['choices'];
                    $answerKey = $result['answer_key'];
                    break;
                    
                case 'matching':
                    $result = $this->prepareMatchingData($questionData);
                    if (!$result['success']) {
                        return $result;
                    }
                    $choices = $result['choices'];
                    $answerKey = $result['answer_key'];
                    break;
                    
                case 'essay':
                    // Essay questions don't need choices or answer_key
                    break;
                    
                default:
                    return ['success' => false, 'error' => 'Invalid question type'];
            }
            
            // Insert question into appropriate table based on type
            $questionId = null;
            
            switch ($type) {
                case 'mcq':
                    // Validate correct answer exists and is one of A-D
                    $providedAnswer = strtoupper(trim($questionData['correct_answer'] ?? ''));
                    if (!in_array($providedAnswer, ['A', 'B', 'C', 'D'], true)) {
                        return [
                            'success' => false,
                            'error' => 'MCQ requires a correct answer (A, B, C, or D).'
                        ];
                    }
                    $questionData['correct_answer'] = $providedAnswer;
                    $questionId = $this->insertMCQQuestion($setId, $questionText, $questionData, $points, $difficulty);
                    break;
                case 'matching':
                    $questionId = $this->insertMatchingQuestion($setId, $questionText, $questionData, $points, $difficulty);
                    break;
                case 'essay':
                    $questionId = $this->insertEssayQuestion($setId, $questionText, $points, $difficulty);
                    break;
            }
            
            if (!$questionId) {
                return ['success' => false, 'error' => 'Failed to create question'];
            }
            
            error_log('Question created successfully with ID: ' . $questionId);
            
            // Handle matching pairs if it's a matching question
            if ($type === 'matching' && isset($questionData['left_items']) && isset($questionData['right_items'])) {
                $this->createMatchingPairs($questionId, $questionData);
            }
            
            return ['success' => true, 'question_id' => $questionId];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get or create a question set
     */
    public function getOrCreateQuestionSet($teacherId, $sectionId, $setTitle) {
        try {
            // First, try to get existing set
            $stmt = $this->conn->prepare("
                SELECT id FROM question_sets 
                WHERE teacher_id = ? AND section_id = ? AND set_title = ?
            ");
            if (!$stmt) {
                error_log('Failed to prepare statement: ' . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param('iis', $teacherId, $sectionId, $setTitle);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                error_log('Found existing question set: ' . $row['id']);
                return $row['id'];
            }
            
            // Create new set if it doesn't exist
            error_log('Creating new question set...');
            $result = $this->createQuestionSet($teacherId, $sectionId, $setTitle);
            return $result['success'] ? $result['set_id'] : false;
        } catch (Exception $e) {
            error_log('Error in getOrCreateQuestionSet: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Prepare MCQ data
     */
    private function prepareMCQData($questionData) {
        // Get the first 4 choices only (since that's what the database schema supports)
        $choiceLetters = ['a', 'b', 'c', 'd'];
        $choices = [];

        foreach ($choiceLetters as $letter) {
            $choiceKey = "choice_$letter";
            if (isset($questionData[$choiceKey]) && !empty(trim($questionData[$choiceKey]))) {
                $choices[] = trim($questionData[$choiceKey]);
            }
        }

        $correctAnswer = strtoupper(trim($questionData['correct_answer'] ?? ''));

        if (count($choices) < 2) {
            return ['success' => false, 'error' => 'MCQ requires at least two choices'];
        }

        if (empty($correctAnswer)) {
            return ['success' => false, 'error' => 'MCQ requires a correct answer'];
        }

        // Validate that correct answer exists in choices
        $choiceLettersUpper = array_map('strtoupper', $choiceLetters);
        if (!in_array($correctAnswer, $choiceLettersUpper, true)) {
            return ['success' => false, 'error' => 'Correct answer must be one of the available options (A, B, C, D)'];
        }

        $choicesJson = json_encode($choices);
        return ['success' => true, 'choices' => $choicesJson, 'answer_key' => $correctAnswer];
    }
    
    /**
     * Prepare matching data
     */
    private function prepareMatchingData($questionData) {
        $leftItems = $questionData['left_items'] ?? [];
        $rightItems = $questionData['right_items'] ?? [];
        $matches = $questionData['matches'] ?? [];
        
        if (empty($leftItems) || empty($rightItems) || empty($matches)) {
            return ['success' => false, 'error' => 'Matching questions require left items, right items, and matches'];
        }
        
        // Create choices array with all options
        $allOptions = array_merge($leftItems, $rightItems);
        $choices = json_encode($allOptions);
        
        // Create answer key with matches
        $answerKey = json_encode($matches);
        
        return ['success' => true, 'choices' => $choices, 'answer_key' => $answerKey];
    }
    
    /**
     * Create matching pairs
     */
    private function createMatchingPairs($questionId, $questionData) {
        try {
            $leftItems = $questionData['left_items'] ?? [];
            $matches = $questionData['matches'] ?? [];
            
            error_log('Creating matching pairs for question ID: ' . $questionId);
            error_log('Left items: ' . print_r($leftItems, true));
            error_log('Matches: ' . print_r($matches, true));
            
            foreach ($leftItems as $index => $leftItem) {
                if (isset($matches[$index])) {
                    $stmt = $this->conn->prepare("
                        INSERT INTO matching_pairs (question_id, left_item, right_item, correct_answer, pair_order) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    if (!$stmt) {
                        error_log('Failed to prepare matching pairs statement: ' . $this->conn->error);
                        continue;
                    }
                    
                    $rightItem = $matches[$index];
                    
                    // Ensure all variables are properly defined
                    $questionId = (int)$questionId;
                    $leftItem = (string)$leftItem;
                    $rightItem = (string)$rightItem;
                    $pairOrder = (int)($index + 1);
                    
                    $stmt->bind_param('isssi', $questionId, $leftItem, $rightItem, $rightItem, $pairOrder);
                    
                    if ($stmt->execute()) {
                        error_log('Matching pair created: ' . $leftItem . ' -> ' . $rightItem);
                    } else {
                        error_log('Failed to create matching pair: ' . $stmt->error);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error in createMatchingPairs: ' . $e->getMessage());
        }
    }
    
    /**
     * Get questions for a specific set
     */
    public function getQuestionsForSet($setId) {
        try {
            $questions = [];
            
            // Get MCQ with order_index
            $stmt = $this->conn->prepare("SELECT question_id as id, 'mcq' as type, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, points, order_index FROM mcq_questions WHERE set_id = ? ORDER BY order_index, question_id");
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $questions = array_merge($questions, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
            
            // Get Matching with order_index
            $stmt = $this->conn->prepare("SELECT question_id as id, 'matching' as type, question_text, left_items, right_items, correct_pairs, points, order_index FROM matching_questions WHERE set_id = ? ORDER BY order_index, question_id");
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $matching = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($matching as &$q) {
                $q['left_items'] = json_decode($q['left_items'], true);
                $q['right_items'] = json_decode($q['right_items'], true);
                $q['matches'] = json_decode($q['correct_pairs'], true);
            }
            $questions = array_merge($questions, $matching);
            
            // Get Essay with order_index
            $stmt = $this->conn->prepare("SELECT question_id as id, 'essay' as type, question_text, points, order_index FROM essay_questions WHERE set_id = ? ORDER BY order_index, question_id");
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $questions = array_merge($questions, $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
            
            // Sort all questions by order_index, then by id
            usort($questions, function($a, $b) {
                $orderA = $a['order_index'] ?? 999;
                $orderB = $b['order_index'] ?? 999;
                if ($orderA == $orderB) {
                    return $a['id'] - $b['id'];
                }
                return $orderA - $orderB;
            });
            
            return $questions;
        } catch (Exception $e) {
            return [];
        }
    }

    public function getSetTitle($setId) {
        $stmt = $this->conn->prepare("SELECT set_title FROM question_sets WHERE id = ?");
        $stmt->bind_param('i', $setId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['set_title'] ?? '';
    }
    
    /**
     * Get the next order index for a question set
     */
    private function getNextOrderIndex($setId) {
        try {
            // Get the maximum order_index from all question tables for this set
            $maxOrder = 0;
            
            // Check MCQ questions
            $stmt = $this->conn->prepare("SELECT MAX(order_index) as max_order FROM mcq_questions WHERE set_id = ?");
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && $result['max_order'] !== null) {
                $maxOrder = max($maxOrder, $result['max_order']);
            }
            
            // Check Matching questions
            $stmt = $this->conn->prepare("SELECT MAX(order_index) as max_order FROM matching_questions WHERE set_id = ?");
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && $result['max_order'] !== null) {
                $maxOrder = max($maxOrder, $result['max_order']);
            }
            
            // Check Essay questions
            $stmt = $this->conn->prepare("SELECT MAX(order_index) as max_order FROM essay_questions WHERE set_id = ?");
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result && $result['max_order'] !== null) {
                $maxOrder = max($maxOrder, $result['max_order']);
            }
            
            return $maxOrder + 1;
        } catch (Exception $e) {
            error_log('Error getting next order index: ' . $e->getMessage());
            return 1; // Fallback to 1 if there's an error
        }
    }

    public function updateQuestionSet($setId, $newTitle, $questions) {
        try {
            $this->conn->begin_transaction();

            // Update title
            $stmt = $this->conn->prepare("UPDATE question_sets SET set_title = ? WHERE id = ?");
            $stmt->bind_param('si', $newTitle, $setId);
            $stmt->execute();

            foreach ($questions as $q) {
                if (isset($q['delete']) && $q['delete']) {
                    $this->deleteQuestion($q['id'], $q['type']);
                } elseif (isset($q['id'])) {
                    $this->updateQuestion($q['id'], $q['type'], $q);
                } else {
                    $this->createQuestion($this->getTeacherIdFromSet($setId), $this->getSectionIdFromSet($setId), $setId, $q);
                }
            }

            $this->conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function deleteQuestion($id, $type) {
        $table = $type . '_questions';
        $stmt = $this->conn->prepare("DELETE FROM $table WHERE question_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }

    private function updateQuestion($id, $type, $data) {
        switch ($type) {
            case 'mcq':
                $orderIndex = isset($data['order_index']) ? (int)$data['order_index'] : null;
                if ($orderIndex !== null) {
                    $stmt = $this->conn->prepare("UPDATE mcq_questions SET question_text = ?, choice_a = ?, choice_b = ?, choice_c = ?, choice_d = ?, correct_answer = ?, points = ?, order_index = ? WHERE question_id = ?");
                    $stmt->bind_param('ssssssiii', $data['question_text'], $data['choice_a'], $data['choice_b'], $data['choice_c'], $data['choice_d'], $data['correct_answer'], $data['points'], $orderIndex, $id);
                } else {
                    $stmt = $this->conn->prepare("UPDATE mcq_questions SET question_text = ?, choice_a = ?, choice_b = ?, choice_c = ?, choice_d = ?, correct_answer = ?, points = ? WHERE question_id = ?");
                    $stmt->bind_param('ssssssii', $data['question_text'], $data['choice_a'], $data['choice_b'], $data['choice_c'], $data['choice_d'], $data['correct_answer'], $data['points'], $id);
                }
                $ok = $stmt->execute();
                if ($ok && isset($data['difficulty']) && trim($data['difficulty']) !== '') { $this->saveQuestionDifficulty('mcq', $id, trim(strtolower($data['difficulty']))); }
                return $ok;
            case 'matching':
                $leftItems = json_encode($data['left_items']);
                $rightItems = json_encode($data['right_items']);
                $correctPairs = json_encode($data['matches']);
                $orderIndex = isset($data['order_index']) ? (int)$data['order_index'] : null;
                if ($orderIndex !== null) {
                    $stmt = $this->conn->prepare("UPDATE matching_questions SET question_text = ?, left_items = ?, right_items = ?, correct_pairs = ?, points = ?, order_index = ? WHERE question_id = ?");
                    $stmt->bind_param('ssssiii', $data['question_text'], $leftItems, $rightItems, $correctPairs, $data['points'], $orderIndex, $id);
                } else {
                    $stmt = $this->conn->prepare("UPDATE matching_questions SET question_text = ?, left_items = ?, right_items = ?, correct_pairs = ?, points = ? WHERE question_id = ?");
                    $stmt->bind_param('ssssii', $data['question_text'], $leftItems, $rightItems, $correctPairs, $data['points'], $id);
                }
                $ok = $stmt->execute();
                if ($ok && isset($data['difficulty']) && trim($data['difficulty']) !== '') { $this->saveQuestionDifficulty('matching', $id, trim(strtolower($data['difficulty']))); }
                return $ok;
            case 'essay':
                $orderIndex = isset($data['order_index']) ? (int)$data['order_index'] : null;
                if ($orderIndex !== null) {
                    $stmt = $this->conn->prepare("UPDATE essay_questions SET question_text = ?, points = ?, order_index = ? WHERE question_id = ?");
                    $stmt->bind_param('siii', $data['question_text'], $data['points'], $orderIndex, $id);
                } else {
                    $stmt = $this->conn->prepare("UPDATE essay_questions SET question_text = ?, points = ? WHERE question_id = ?");
                    $stmt->bind_param('sii', $data['question_text'], $data['points'], $id);
                }
                $ok = $stmt->execute();
                if ($ok && isset($data['difficulty']) && trim($data['difficulty']) !== '') { $this->saveQuestionDifficulty('essay', $id, trim(strtolower($data['difficulty']))); }
                return $ok;
            default:
                return false;
        }
    }

    private function saveQuestionDifficulty(string $type, int $questionId, string $difficulty): void {
        try {
            $table = ($type === 'mcq') ? 'mcq_questions' : (($type === 'matching') ? 'matching_questions' : (($type === 'essay') ? 'essay_questions' : ''));
            if ($table === '') { return; }
            // Check column exists to stay backward compatible
            $hasCol = false;
            try {
                $chk = $this->conn->query("SHOW COLUMNS FROM {$table} LIKE 'difficulty'");
                $hasCol = $chk && $chk->num_rows > 0;
            } catch (Exception $e) { $hasCol = false; }
            if (!$hasCol) { return; }
            $stmt = $this->conn->prepare("UPDATE {$table} SET difficulty = ? WHERE question_id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $difficulty, $questionId);
                $stmt->execute();
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    private function getTeacherIdFromSet($setId) {
        $stmt = $this->conn->prepare("SELECT teacher_id FROM question_sets WHERE id = ?");
        $stmt->bind_param('i', $setId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['teacher_id'] ?? 0;
    }

    private function getSectionIdFromSet($setId) {
        $stmt = $this->conn->prepare("SELECT section_id FROM question_sets WHERE id = ?");
        $stmt->bind_param('i', $setId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['section_id'] ?? 0;
    }

    // Public accessor for section id of a set
    public function getSetSectionId($setId) {
        return $this->getSectionIdFromSet($setId);
    }
    
    /**
     * Get all question sets for a teacher
     */
    public function getQuestionSets($teacherId, $sectionId = null) {
        try {
            if ($sectionId) {
                $stmt = $this->conn->prepare("
                    SELECT qs.*, s.name as section_name,
                           (SELECT COUNT(*) FROM mcq_questions WHERE set_id = qs.id) +
                           (SELECT COUNT(*) FROM matching_questions WHERE set_id = qs.id) +
                           (SELECT COUNT(*) FROM essay_questions WHERE set_id = qs.id) as question_count,
                           (SELECT COALESCE(SUM(points), 0) FROM mcq_questions WHERE set_id = qs.id) +
                           (SELECT COALESCE(SUM(points), 0) FROM matching_questions WHERE set_id = qs.id) +
                           (SELECT COALESCE(SUM(points), 0) FROM essay_questions WHERE set_id = qs.id) as total_points
                    FROM question_sets qs
                    LEFT JOIN sections s ON qs.section_id = s.id
                    WHERE qs.teacher_id = ? AND qs.section_id = ?
                    ORDER BY qs.created_at DESC
                ");
                $stmt->bind_param('ii', $teacherId, $sectionId);
            } else {
                $stmt = $this->conn->prepare("
                    SELECT qs.*, s.name as section_name,
                           (SELECT COUNT(*) FROM mcq_questions WHERE set_id = qs.id) +
                           (SELECT COUNT(*) FROM matching_questions WHERE set_id = qs.id) +
                           (SELECT COUNT(*) FROM essay_questions WHERE set_id = qs.id) as question_count,
                           (SELECT COALESCE(SUM(points), 0) FROM mcq_questions WHERE set_id = qs.id) +
                           (SELECT COALESCE(SUM(points), 0) FROM matching_questions WHERE set_id = qs.id) +
                           (SELECT COALESCE(SUM(points), 0) FROM essay_questions WHERE set_id = qs.id) as total_points
                    FROM question_sets qs
                    LEFT JOIN sections s ON qs.section_id = s.id
                    WHERE qs.teacher_id = ?
                    ORDER BY qs.created_at DESC
                ");
                $stmt->bind_param('i', $teacherId);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $sets = [];
            while ($row = $result->fetch_assoc()) {
                $sets[] = $row;
            }
            
            return $sets;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Delete entire question set (and all questions in it)
     */
    public function deleteQuestionSet(int $setId): array {
        try {
            $this->conn->begin_transaction();
            // Delete child questions
            $stmt = $this->conn->prepare("DELETE FROM mcq_questions WHERE set_id = ?");
            $stmt->bind_param('i', $setId);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM matching_questions WHERE set_id = ?");
            $stmt->bind_param('i', $setId);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM essay_questions WHERE set_id = ?");
            $stmt->bind_param('i', $setId);
            $stmt->execute();

            // Delete the set itself
            $stmt = $this->conn->prepare("DELETE FROM question_sets WHERE id = ?");
            $stmt->bind_param('i', $setId);
            $stmt->execute();

            $this->conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Insert MCQ question (with optional difficulty)
     */
    private function insertMCQQuestion($setId, $questionText, $questionData, $points, $difficulty = '') {
        try {
            // Choose SQL based on column existence
            $hasDiff = false;
            try { $chk = $this->conn->query("SHOW COLUMNS FROM mcq_questions LIKE 'difficulty'"); $hasDiff = $chk && $chk->num_rows > 0; } catch (Exception $e) {}
            if ($hasDiff) {
                $stmt = $this->conn->prepare("
                    INSERT INTO mcq_questions (set_id, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, points, order_index, difficulty)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO mcq_questions (set_id, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, points, order_index)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
            }

            // Get all available choices and use only the first 4
            $choiceLetters = ['a', 'b', 'c', 'd'];
            $choices = [];
            foreach ($choiceLetters as $letter) {
                $choiceKey = "choice_$letter";
                $choices[$letter] = $questionData[$choiceKey] ?? '';
            }
            $correctAnswer = strtoupper(trim($questionData['correct_answer'] ?? ''));
            // Get the next order index for this set
            $orderIndex = $this->getNextOrderIndex($setId);

            if ($hasDiff) {
                // Types: i (set), s (text), ssss (A-D), s (correct), ii (points, order), s (difficulty)
                $stmt->bind_param('issssssiis', $setId, $questionText, $choices['a'], $choices['b'], $choices['c'], $choices['d'], $correctAnswer, $points, $orderIndex, $difficulty);
            } else {
                $stmt->bind_param('issssssii', $setId, $questionText, $choices['a'], $choices['b'], $choices['c'], $choices['d'], $correctAnswer, $points, $orderIndex);
            }

            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
            return false;
        } catch (Exception $e) {
            error_log('Error inserting MCQ question: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert Matching question (with optional difficulty)
     */
    private function insertMatchingQuestion($setId, $questionText, $questionData, $points, $difficulty = '') {
        try {
            $hasDiff = false; try { $chk = $this->conn->query("SHOW COLUMNS FROM matching_questions LIKE 'difficulty'"); $hasDiff = $chk && $chk->num_rows > 0; } catch (Exception $e) {}
            if ($hasDiff) {
                $stmt = $this->conn->prepare("
                    INSERT INTO matching_questions (set_id, question_text, left_items, right_items, correct_pairs, points, order_index, difficulty) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO matching_questions (set_id, question_text, left_items, right_items, correct_pairs, points, order_index) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
            }
            
            $leftItems = json_encode($questionData['left_items'] ?? []);
            $rightItems = json_encode($questionData['right_items'] ?? []);
            $correctPairs = json_encode($questionData['matches'] ?? []);
            // Get the next order index for this set
            $orderIndex = $this->getNextOrderIndex($setId);
            
            if ($hasDiff) {
                // Types: i (set), s (text), s (left), s (right), s (pairs), i (points), i (order), s (difficulty)
                $stmt->bind_param('issssiis', $setId, $questionText, $leftItems, $rightItems, $correctPairs, $points, $orderIndex, $difficulty);
            } else {
                $stmt->bind_param('issssii', $setId, $questionText, $leftItems, $rightItems, $correctPairs, $points, $orderIndex);
            }
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
            return false;
        } catch (Exception $e) {
            error_log('Error inserting matching question: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert Essay question (with optional difficulty)
     */
    private function insertEssayQuestion($setId, $questionText, $points, $difficulty = '') {
        try {
            $hasDiff = false; try { $chk = $this->conn->query("SHOW COLUMNS FROM essay_questions LIKE 'difficulty'"); $hasDiff = $chk && $chk->num_rows > 0; } catch (Exception $e) {}
            if ($hasDiff) {
                $stmt = $this->conn->prepare("
                    INSERT INTO essay_questions (set_id, question_text, points, order_index, difficulty) 
                    VALUES (?, ?, ?, ?, ?)
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO essay_questions (set_id, question_text, points, order_index) 
                    VALUES (?, ?, ?, ?)
                ");
            }
            
            // Get the next order index for this set
            $orderIndex = $this->getNextOrderIndex($setId);
            if ($hasDiff) {
                $stmt->bind_param('isiis', $setId, $questionText, $points, $orderIndex, $difficulty);
            } else {
                $stmt->bind_param('isii', $setId, $questionText, $points, $orderIndex);
            }
            
            if ($stmt->execute()) {
                return $this->conn->insert_id;
            }
            return false;
        } catch (Exception $e) {
            error_log('Error inserting essay question: ' . $e->getMessage());
            return false;
        }
    }
}
?>

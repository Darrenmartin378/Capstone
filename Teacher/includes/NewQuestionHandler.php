<?php
/**
 * New Question Handler for Separate Question Type Tables
 * Handles MCQ, Matching, and Essay questions in separate tables
 */

class NewQuestionHandler {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
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
     * Create an MCQ question
     */
    public function createMCQQuestion($setId, $questionText, $choiceA, $choiceB, $choiceC, $choiceD, $correctAnswer, $points = 1) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO mcq_questions (set_id, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, points) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('issssssi', $setId, $questionText, $choiceA, $choiceB, $choiceC, $choiceD, $correctAnswer, $points);
            
            if ($stmt->execute()) {
                return ['success' => true, 'question_id' => $this->conn->insert_id];
            } else {
                return ['success' => false, 'error' => 'Failed to create MCQ question'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create a matching question
     */
    public function createMatchingQuestion($setId, $questionText, $leftItems, $rightItems, $correctPairs, $points = 1) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO matching_questions (set_id, question_text, left_items, right_items, correct_pairs, points) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $leftItemsJson = json_encode($leftItems);
            $rightItemsJson = json_encode($rightItems);
            $correctPairsJson = json_encode($correctPairs);
            
            $stmt->bind_param('issssi', $setId, $questionText, $leftItemsJson, $rightItemsJson, $correctPairsJson, $points);
            
            if ($stmt->execute()) {
                return ['success' => true, 'question_id' => $this->conn->insert_id];
            } else {
                return ['success' => false, 'error' => 'Failed to create matching question'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Create an essay question
     */
    public function createEssayQuestion($setId, $questionText, $points = 1) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO essay_questions (set_id, question_text, points) 
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param('isi', $setId, $questionText, $points);
            
            if ($stmt->execute()) {
                return ['success' => true, 'question_id' => $this->conn->insert_id];
            } else {
                return ['success' => false, 'error' => 'Failed to create essay question'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get all question sets for a teacher
     */
    public function getQuestionSets($teacherId, $sectionId = null) {
        try {
            $sql = "
                SELECT qs.*, s.name as section_name, 
                       (SELECT COUNT(*) FROM mcq_questions WHERE set_id = qs.id) +
                       (SELECT COUNT(*) FROM matching_questions WHERE set_id = qs.id) +
                       (SELECT COUNT(*) FROM essay_questions WHERE set_id = qs.id) as question_count,
                       (SELECT COALESCE(SUM(points), 0) FROM mcq_questions WHERE set_id = qs.id) +
                       (SELECT COALESCE(SUM(points), 0) FROM matching_questions WHERE set_id = qs.id) +
                       (SELECT COALESCE(SUM(points), 0) FROM essay_questions WHERE set_id = qs.id) as total_points
                FROM question_sets qs
                JOIN sections s ON qs.section_id = s.id
                WHERE qs.teacher_id = ?
            ";
            
            if ($sectionId) {
                $sql .= " AND qs.section_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param('ii', $teacherId, $sectionId);
            } else {
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param('i', $teacherId);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get questions for a specific set
     */
    public function getQuestionsForSet($setId) {
        try {
            $questions = [];
            
            // Get MCQ questions
            $stmt = $this->conn->prepare("
                SELECT question_id as id, 'mcq' as type, question_text, choice_a, choice_b, choice_c, choice_d, correct_answer, points, order_index
                FROM mcq_questions WHERE set_id = ? ORDER BY order_index
            ");
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $mcqQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get matching questions
            $stmt = $this->conn->prepare("
                SELECT question_id as id, 'matching' as type, question_text, left_items, right_items, correct_pairs, points, order_index
                FROM matching_questions WHERE set_id = ? ORDER BY order_index
            ");
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $matchingQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get essay questions
            $stmt = $this->conn->prepare("
                SELECT question_id as id, 'essay' as type, question_text, points, order_index
                FROM essay_questions WHERE set_id = ? ORDER BY order_index
            ");
            $stmt->bind_param('i', $setId);
            $stmt->execute();
            $essayQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Combine all questions
            $questions = array_merge($mcqQuestions, $matchingQuestions, $essayQuestions);
            
            // Sort by order_index
            usort($questions, function($a, $b) {
                return $a['order_index'] - $b['order_index'];
            });
            
            return $questions;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get available sections
     */
    public function getSections() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM sections ORDER BY name");
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
?>

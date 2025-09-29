<?php
/**
 * New Response Handler for Separate Question Type Tables
 * Handles student responses for MCQ, Matching, and Essay questions
 */

class NewResponseHandler {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Submit student responses
     */
    public function submitResponses($studentId, $questionSetId, $responses) {
        try {
            // Prevent multiple submissions: if there are any responses for this student and set, block re-submission
            $check = $this->conn->prepare("SELECT 1 FROM student_responses WHERE student_id = ? AND question_set_id = ? LIMIT 1");
            $check->bind_param('ii', $studentId, $questionSetId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                return false; // Already submitted
            }

            $this->conn->begin_transaction();
            
            // Handle the format: {questionId: answer}
            foreach ($responses as $questionId => $answer) {
                // Determine question type by checking which table has this question
                $questionType = $this->getQuestionType($questionId);
                error_log("Question ID: $questionId, Type: " . ($questionType ?: 'NOT FOUND'));
                if ($questionType) {
                    $this->saveResponse($studentId, $questionSetId, $questionType, $questionId, $answer);
                } else {
                    error_log("Question type not found for question ID: $questionId");
                }
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error in submitResponses: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get question type by question ID
     */
    private function getQuestionType($questionId) {
        // Check MCQ questions
        $stmt = $this->conn->prepare("SELECT 'mcq' as type FROM mcq_questions WHERE question_id = ?");
        $stmt->bind_param('i', $questionId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return 'mcq';
        }
        
        // Check Matching questions
        $stmt = $this->conn->prepare("SELECT 'matching' as type FROM matching_questions WHERE question_id = ?");
        $stmt->bind_param('i', $questionId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return 'matching';
        }
        
        // Check Essay questions
        $stmt = $this->conn->prepare("SELECT 'essay' as type FROM essay_questions WHERE question_id = ?");
        $stmt->bind_param('i', $questionId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return 'essay';
        }
        
        return null;
    }
    
    /**
     * Save individual response
     */
    private function saveResponse($studentId, $questionSetId, $questionType, $questionId, $answer) {
        $isCorrect = null;
        $score = null;
        
        // Auto-grade MCQ and matching questions
        if ($questionType === 'mcq') {
            $result = $this->gradeMCQ($questionId, $answer);
            $isCorrect = $result['is_correct'];
            $score = $result['score'];
        } elseif ($questionType === 'matching') {
            $result = $this->gradeMatching($questionId, $answer);
            $isCorrect = $result['is_correct'];
            $score = $result['score'];
        }
        // Essay questions are not auto-graded
        
        $stmt = $this->conn->prepare("
            INSERT INTO student_responses (student_id, question_set_id, question_type, question_id, answer, is_correct, score) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            answer = VALUES(answer), 
            is_correct = VALUES(is_correct), 
            score = VALUES(score),
            submitted_at = CURRENT_TIMESTAMP
        ");
        
        $answerJson = is_array($answer) ? json_encode($answer) : $answer;
        $stmt->bind_param('iisisid', $studentId, $questionSetId, $questionType, $questionId, $answerJson, $isCorrect, $score);
        
        if (!$stmt->execute()) {
            error_log("Error saving response: " . $stmt->error);
            throw new Exception("Failed to save response: " . $stmt->error);
        }
    }
    
    /**
     * Grade MCQ question
     */
    private function gradeMCQ($questionId, $answer) {
        $stmt = $this->conn->prepare("
            SELECT correct_answer, points FROM mcq_questions WHERE question_id = ?
        ");
        $stmt->bind_param('i', $questionId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $isCorrect = ($answer === $result['correct_answer']);
            $score = $isCorrect ? $result['points'] : 0;
            return ['is_correct' => $isCorrect, 'score' => $score];
        }
        
        return ['is_correct' => false, 'score' => 0];
    }
    
    /**
     * Grade matching question
     */
    private function gradeMatching($questionId, $answer) {
        $stmt = $this->conn->prepare("
            SELECT correct_pairs, points FROM matching_questions WHERE question_id = ?
        ");
        $stmt->bind_param('i', $questionId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result) {
            $correctPairs = json_decode($result['correct_pairs'], true);
            $studentPairs = is_array($answer) ? $answer : json_decode($answer, true);
            
            $correctCount = 0;
            $totalCount = count($correctPairs);
            
            foreach ($correctPairs as $index => $correctAnswer) {
                if (isset($studentPairs[$index]) && $studentPairs[$index] === $correctAnswer) {
                    $correctCount++;
                }
            }
            
            $isCorrect = ($correctCount === $totalCount);
            $score = ($correctCount / $totalCount) * $result['points'];
            
            return ['is_correct' => $isCorrect, 'score' => $score];
        }
        
        return ['is_correct' => false, 'score' => 0];
    }
    
    /**
     * Get student responses for a question set
     */
    public function getStudentResponses($studentId, $questionSetId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM student_responses 
                WHERE student_id = ? AND question_set_id = ?
                ORDER BY question_type, question_id
            ");
            $stmt->bind_param('ii', $studentId, $questionSetId);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get question sets available to student
     */
    public function getAvailableQuestionSets($sectionId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT qs.*, s.name as section_name,
                       (SELECT COUNT(*) FROM mcq_questions WHERE set_id = qs.id) +
                       (SELECT COUNT(*) FROM matching_questions WHERE set_id = qs.id) +
                       (SELECT COUNT(*) FROM essay_questions WHERE set_id = qs.id) as question_count,
                       (SELECT COALESCE(SUM(points), 0) FROM mcq_questions WHERE set_id = qs.id) +
                       (SELECT COALESCE(SUM(points), 0) FROM matching_questions WHERE set_id = qs.id) +
                       (SELECT COALESCE(SUM(points), 0) FROM essay_questions WHERE set_id = qs.id) as total_points,
                       (SELECT COALESCE(SUM(score), 0) FROM student_responses sr WHERE sr.question_set_id = qs.id AND sr.student_id = ?) as student_score,
                       (SELECT COALESCE(SUM(points), 0) FROM mcq_questions WHERE set_id = qs.id) +
                       (SELECT COALESCE(SUM(points), 0) FROM matching_questions WHERE set_id = qs.id) +
                       (SELECT COALESCE(SUM(points), 0) FROM essay_questions WHERE set_id = qs.id) as max_points,
                       EXISTS(SELECT 1 FROM student_responses sr2 WHERE sr2.question_set_id = qs.id AND sr2.student_id = ?) as already_submitted
                FROM question_sets qs
                JOIN sections s ON qs.section_id = s.id
                WHERE qs.section_id = ?
                ORDER BY qs.created_at DESC
            ");
            $stmt->bind_param('iii', $_SESSION['student_id'], $_SESSION['student_id'], $sectionId);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get questions for a specific set (for students)
     */
    public function getQuestionsForSet($setId) {
        try {
            $questions = [];
            
            // Get MCQ questions
            $stmt = $this->conn->prepare("
                SELECT question_id as id, 'mcq' as type, question_text, choice_a, choice_b, choice_c, choice_d, points, order_index
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
     * Calculate total score for a question set
     */
    public function calculateTotalScore($studentId, $questionSetId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    SUM(score) as total_score,
                    COUNT(*) as total_questions,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
                FROM student_responses 
                WHERE student_id = ? AND question_set_id = ?
            ");
            $stmt->bind_param('ii', $studentId, $questionSetId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            return [
                'total_score' => (float)($result['total_score'] ?? 0),
                'total_questions' => (int)($result['total_questions'] ?? 0),
                'correct_answers' => (int)($result['correct_answers'] ?? 0)
            ];
        } catch (Exception $e) {
            return ['total_score' => 0, 'total_questions' => 0, 'correct_answers' => 0];
        }
    }
    
    /**
     * Get maximum possible points for a question set
     */
    public function getMaxPointsForSet($questionSetId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    (SELECT COALESCE(SUM(points), 0) FROM mcq_questions WHERE set_id = ?) +
                    (SELECT COALESCE(SUM(points), 0) FROM matching_questions WHERE set_id = ?) +
                    (SELECT COALESCE(SUM(points), 0) FROM essay_questions WHERE set_id = ?) as max_points
            ");
            $stmt->bind_param('iii', $questionSetId, $questionSetId, $questionSetId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            return (float)($result['max_points'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get detailed scoring breakdown
     */
    public function getScoringBreakdown($studentId, $questionSetId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    question_type,
                    question_id,
                    score,
                    is_correct,
                    answer
                FROM student_responses 
                WHERE student_id = ? AND question_set_id = ?
                ORDER BY question_type, question_id
            ");
            $stmt->bind_param('ii', $studentId, $questionSetId);
            $stmt->execute();
            $responses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $breakdown = [
                'mcq' => ['score' => 0, 'max_score' => 0, 'questions' => []],
                'matching' => ['score' => 0, 'max_score' => 0, 'questions' => []],
                'essay' => ['score' => 0, 'max_score' => 0, 'questions' => []]
            ];
            
            foreach ($responses as $response) {
                $type = $response['question_type'];
                $breakdown[$type]['score'] += (float)$response['score'];
                $breakdown[$type]['questions'][] = $response;
            }
            
            return $breakdown;
        } catch (Exception $e) {
            return [];
        }
    }
}
?>

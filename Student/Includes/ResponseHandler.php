<?php
/**
 * Response Handler Class
 * Clean, modular system for handling student responses and scoring
 */

class ResponseHandler {
    private $conn;
    
    public function __construct($databaseConnection) {
        $this->conn = $databaseConnection;
    }
    
    /**
     * Submit a student response
     */
    public function submitResponse($questionId, $studentId, $answer) {
        try {
            // Check if response already exists
            $stmt = $this->conn->prepare("
                SELECT id FROM responses 
                WHERE question_id = ? AND student_id = ?
            ");
            $stmt->bind_param('ii', $questionId, $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Update existing response
                $stmt = $this->conn->prepare("
                    UPDATE responses 
                    SET answer = ?, submitted_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->bind_param('si', $answer, $row['id']);
                return $stmt->execute();
            } else {
                // Create new response
                $stmt = $this->conn->prepare("
                    INSERT INTO responses (question_id, student_id, answer) 
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param('iis', $questionId, $studentId, $answer);
                return $stmt->execute();
            }
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Submit multiple responses for a question set
     */
    public function submitResponses($questionSetId, $studentId, $responses) {
        try {
            $this->conn->begin_transaction();
            
            foreach ($responses as $questionId => $answer) {
                $this->submitResponse($questionId, $studentId, $answer);
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
    
    /**
     * Score a response
     */
    public function scoreResponse($responseId, $score, $isCorrect = null) {
        try {
            if ($isCorrect === null) {
                $isCorrect = $score > 0;
            }
            
            $stmt = $this->conn->prepare("
                UPDATE responses 
                SET score = ?, is_correct = ?, graded_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param('dii', $score, $isCorrect, $responseId);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Auto-score MCQ response
     */
    public function autoScoreMCQ($questionId, $studentId, $answer) {
        try {
            // Get the correct answer
            $stmt = $this->conn->prepare("
                SELECT answer_key FROM questions WHERE id = ?
            ");
            $stmt->bind_param('i', $questionId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $correctAnswer = $row['answer_key'];
                $isCorrect = ($answer === $correctAnswer);
                $score = $isCorrect ? 1 : 0;
                
                // Update the response
                $stmt = $this->conn->prepare("
                    UPDATE responses 
                    SET score = ?, is_correct = ?, graded_at = CURRENT_TIMESTAMP
                    WHERE question_id = ? AND student_id = ?
                ");
                $stmt->bind_param('diii', $score, $isCorrect, $questionId, $studentId);
                return $stmt->execute();
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Auto-score matching response
     */
    public function autoScoreMatching($questionId, $studentId, $answers) {
        try {
            // Get matching pairs
            $stmt = $this->conn->prepare("
                SELECT left_item, correct_answer FROM matching_pairs 
                WHERE question_id = ? ORDER BY pair_order
            ");
            $stmt->bind_param('i', $questionId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $correctPairs = [];
            while ($row = $result->fetch_assoc()) {
                $correctPairs[$row['left_item']] = $row['correct_answer'];
            }
            
            $score = 0;
            $totalPairs = count($correctPairs);
            
            foreach ($answers as $leftItem => $studentAnswer) {
                if (isset($correctPairs[$leftItem]) && $studentAnswer === $correctPairs[$leftItem]) {
                    $score++;
                }
            }
            
            $finalScore = $totalPairs > 0 ? ($score / $totalPairs) : 0;
            $isCorrect = ($score === $totalPairs);
            
            // Update the response
            $stmt = $this->conn->prepare("
                UPDATE responses 
                SET score = ?, is_correct = ?, graded_at = CURRENT_TIMESTAMP
                WHERE question_id = ? AND student_id = ?
            ");
            $stmt->bind_param('diii', $finalScore, $isCorrect, $questionId, $studentId);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get student responses for a question set
     */
    public function getStudentResponses($questionSetId, $studentId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT r.*, q.type, q.points, q.question_text
                FROM responses r
                JOIN questions q ON r.question_id = q.id
                JOIN question_sets qs ON q.set_id = qs.id
                WHERE qs.id = ? AND r.student_id = ?
                ORDER BY q.order_index, q.id
            ");
            $stmt->bind_param('ii', $questionSetId, $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $responses = [];
            while ($row = $result->fetch_assoc()) {
                $responses[] = $row;
            }
            
            return $responses;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get all responses for a question (for grading)
     */
    public function getQuestionResponses($questionId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT r.*, s.name as student_name, s.student_id as student_number
                FROM responses r
                JOIN students s ON r.student_id = s.id
                WHERE r.question_id = ?
                ORDER BY r.submitted_at DESC
            ");
            $stmt->bind_param('i', $questionId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $responses = [];
            while ($row = $result->fetch_assoc()) {
                $responses[] = $row;
            }
            
            return $responses;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get student's total score for a question set
     */
    public function getStudentScore($questionSetId, $studentId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    SUM(r.score) as total_score,
                    SUM(q.points) as max_score,
                    COUNT(r.id) as answered_questions,
                    COUNT(q.id) as total_questions
                FROM questions q
                LEFT JOIN responses r ON q.id = r.question_id AND r.student_id = ?
                WHERE q.set_id = ?
            ");
            $stmt->bind_param('ii', $studentId, $questionSetId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                return [
                    'total_score' => (float)$row['total_score'],
                    'max_score' => (float)$row['max_score'],
                    'answered_questions' => (int)$row['answered_questions'],
                    'total_questions' => (int)$row['total_questions'],
                    'percentage' => $row['max_score'] > 0 ? ($row['total_score'] / $row['max_score']) * 100 : 0
                ];
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get all students' scores for a question set
     */
    public function getAllScores($questionSetId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    s.id as student_id,
                    s.name as student_name,
                    s.student_id as student_number,
                    SUM(r.score) as total_score,
                    SUM(q.points) as max_score,
                    COUNT(r.id) as answered_questions,
                    COUNT(q.id) as total_questions
                FROM students s
                CROSS JOIN questions q
                LEFT JOIN responses r ON q.id = r.question_id AND r.student_id = s.id
                WHERE q.set_id = ?
                GROUP BY s.id, s.name, s.student_id
                ORDER BY total_score DESC, s.name
            ");
            $stmt->bind_param('i', $questionSetId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $scores = [];
            while ($row = $result->fetch_assoc()) {
                $scores[] = [
                    'student_id' => $row['student_id'],
                    'student_name' => $row['student_name'],
                    'student_number' => $row['student_number'],
                    'total_score' => (float)$row['total_score'],
                    'max_score' => (float)$row['max_score'],
                    'answered_questions' => (int)$row['answered_questions'],
                    'total_questions' => (int)$row['total_questions'],
                    'percentage' => $row['max_score'] > 0 ? ($row['total_score'] / $row['max_score']) * 100 : 0
                ];
            }
            
            return $scores;
        } catch (Exception $e) {
            return [];
        }
    }
}
?>
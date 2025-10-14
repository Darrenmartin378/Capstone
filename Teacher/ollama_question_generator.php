<?php
set_time_limit(120); // Set max execution time to 120 seconds (2 minutes)
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/config/ai_config.php';

// Ollama Configuration
define('OLLAMA_BASE_URL', 'http://localhost:11434');
define('OLLAMA_MODEL', 'llama3.2:3b'); // Lightweight model, good for question generation
define('OLLAMA_TIMEOUT', 60);

// Set content type to JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $materialContent = $input['material_content'] ?? '';
        $materialTitle = $input['material_title'] ?? '';
        $numQuestions = (int)($input['num_questions'] ?? AI_DEFAULT_QUESTIONS);
        $questionTypes = $input['question_types'] ?? ['mcq', 'matching', 'essay'];
        $difficulty = $input['difficulty'] ?? 'medium';
        
        // Validate input
        if (!validateMaterialContent($materialContent)) {
            throw new Exception(AI_ERROR_INVALID_CONTENT);
        }
        
        if ($numQuestions < AI_MIN_QUESTIONS || $numQuestions > AI_MAX_QUESTIONS) {
            throw new Exception("Number of questions must be between " . AI_MIN_QUESTIONS . " and " . AI_MAX_QUESTIONS);
        }
        
        // Truncate content if needed - make it even smaller for faster processing
        $materialContent = substr($materialContent, 0, 1500) . '...';
        
        // Create AI prompt
        $prompt = createQuestionGenerationPrompt($materialContent, $materialTitle, $numQuestions, $questionTypes, $difficulty);
        
        // Call Ollama API
        $generatedQuestions = callOllamaAPI($prompt);
        
        // Log AI usage
        logAIUsage('ollama_question_generation', [
            'material_title' => $materialTitle,
            'num_questions' => $numQuestions,
            'question_types' => $questionTypes,
            'difficulty' => $difficulty
        ]);
        
        if (!$generatedQuestions) {
            throw new Exception('Failed to generate questions from Ollama');
        }
        
        // Return structured questions
        echo json_encode([
            'success' => true,
            'questions' => $generatedQuestions,
            'message' => 'Questions generated successfully using Ollama',
            'model' => OLLAMA_MODEL
        ]);
        
    } catch (Exception $e) {
        error_log('Ollama Question Generator Error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Only POST requests are allowed'
    ]);
}

function createQuestionGenerationPrompt($content, $title, $numQuestions, $questionTypes, $difficulty) {
    $difficultyText = match($difficulty) {
        'easy' => 'simple and straightforward',
        'medium' => 'moderately challenging',
        'hard' => 'challenging and analytical',
        default => 'moderately challenging'
    };
    
    $questionTypesText = implode(', ', $questionTypes);
    
    return "Create {$numQuestions} Grade 6 questions about: {$title}

Content: " . substr($content, 0, 1000) . "

Types: {$questionTypesText}
Difficulty: {$difficultyText}

Return JSON only:
{
  \"questions\": [
    {
      \"type\": \"mcq\",
      \"question_text\": \"What is the main idea?\",
      \"points\": 2,
      \"choice_a\": \"Option A\",
      \"choice_b\": \"Option B\", 
      \"choice_c\": \"Option C\",
      \"choice_d\": \"Option D\",
      \"correct_answer\": \"A\"
    }
  ]
}";
}

function callOllamaAPI($prompt) {
    $data = [
        'model' => OLLAMA_MODEL,
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'temperature' => 0.7,
            'top_p' => 0.9,
            'max_tokens' => 4000
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OLLAMA_BASE_URL . '/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, OLLAMA_TIMEOUT);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('CURL Error: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('Ollama API Error: HTTP ' . $httpCode . ' - ' . $response);
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['response'])) {
        throw new Exception('Invalid response from Ollama API');
    }
    
    $content = $result['response'];
    
    // Clean the response - remove any markdown formatting and extra text
    $content = preg_replace('/```json\s*/', '', $content);
    $content = preg_replace('/```\s*$/', '', $content);
    $content = preg_replace('/^[^{]*/', '', $content); // Remove text before first {
    $content = preg_replace('/[^}]*$/', '', $content); // Remove text after last }
    $content = trim($content);
    
    // Try to extract JSON from the response
    if (preg_match('/\{.*\}/s', $content, $matches)) {
        $content = $matches[0];
    }
    
    // Parse the JSON response
    $questions = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON Parse Error: ' . json_last_error_msg());
        error_log('Raw Ollama Response: ' . $content);
        
        // If JSON parsing fails, create fallback questions
        $questions = createFallbackQuestions($content);
    }
    
    if (!isset($questions['questions']) || !is_array($questions['questions'])) {
        throw new Exception('Invalid question format from Ollama');
    }
    
    return $questions['questions'];
}

/**
 * Check if Ollama is running and available
 * 
 * @return bool
 */
function isOllamaAvailable() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OLLAMA_BASE_URL . '/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

/**
 * Get available Ollama models
 * 
 * @return array
 */
function getOllamaModels() {
    if (!isOllamaAvailable()) {
        return [];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OLLAMA_BASE_URL . '/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['models'])) {
        return array_map(function($model) {
            return $model['name'];
        }, $result['models']);
    }
    
    return [];
}

/**
 * Create fallback questions when JSON parsing fails
 */
function createFallbackQuestions($rawContent) {
    // Create simple fallback questions based on the content
    $fallbackQuestions = [
        [
            'type' => 'mcq',
            'question_text' => 'What is the main topic discussed in this material?',
            'points' => 2,
            'choice_a' => 'The main topic is clearly explained',
            'choice_b' => 'The main topic is briefly mentioned',
            'choice_c' => 'The main topic is not discussed',
            'choice_d' => 'The main topic is confusing',
            'correct_answer' => 'A'
        ],
        [
            'type' => 'mcq',
            'question_text' => 'Which statement best describes the content?',
            'points' => 2,
            'choice_a' => 'The content is educational and informative',
            'choice_b' => 'The content is entertaining and fun',
            'choice_c' => 'The content is difficult to understand',
            'choice_d' => 'The content is not relevant',
            'correct_answer' => 'A'
        ],
        [
            'type' => 'essay',
            'question_text' => 'Explain the key concepts from this material in your own words.',
            'points' => 5,
            'rubric' => 'Understanding (2 points), Examples (2 points), Clarity (1 point)'
        ]
    ];
    
    return ['questions' => $fallbackQuestions];
}
?>

<?php
require_once __DIR__ . '/includes/teacher_init.php';
require_once __DIR__ . '/config/ai_config.php';

// Check if AI is enabled
if (!isAIEnabled()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'AI features are not configured. Please contact your administrator.'
    ]);
    exit;
}

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
        
        // Truncate content if needed
        $materialContent = truncateContentForAI($materialContent);
        
        // Create AI prompt
        $prompt = createQuestionGenerationPrompt($materialContent, $materialTitle, $numQuestions, $questionTypes, $difficulty);
        
        // Call OpenAI API
        $generatedQuestions = callOpenAIAPI($prompt);
        
        // Log AI usage
        logAIUsage('question_generation', [
            'material_title' => $materialTitle,
            'num_questions' => $numQuestions,
            'question_types' => $questionTypes,
            'difficulty' => $difficulty
        ]);
        
        if (!$generatedQuestions) {
            throw new Exception('Failed to generate questions from AI');
        }
        
        // Return structured questions
        echo json_encode([
            'success' => true,
            'questions' => $generatedQuestions,
            'message' => 'Questions generated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log('AI Question Generator Error: ' . $e->getMessage());
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
    
    return "You are an expert Grade 6 educator creating comprehension questions. Generate {$numQuestions} questions of types: {$questionTypesText}. Make them {$difficultyText} for Grade 6 students.

MATERIAL TITLE: {$title}

MATERIAL CONTENT:
" . substr($content, 0, 8000) . " // Limit content to avoid token limits

INSTRUCTIONS:
1. Create questions that test comprehension, analysis, and critical thinking
2. Ensure questions are directly related to the material content
3. Use age-appropriate language for Grade 6 students
4. For MCQ: Provide 4 options with only one correct answer
5. For Matching: Create 3-4 pairs that logically connect
6. For Essay: Create questions that require thoughtful responses

Return ONLY valid JSON in this exact format:
{
  \"questions\": [
    {
      \"type\": \"mcq\",
      \"question_text\": \"What is the main idea of the passage?\",
      \"points\": 2,
      \"choice_a\": \"Option A text\",
      \"choice_b\": \"Option B text\", 
      \"choice_c\": \"Option C text\",
      \"choice_d\": \"Option D text\",
      \"correct_answer\": \"A\"
    },
    {
      \"type\": \"matching\",
      \"question_text\": \"Match the following items with their correct descriptions:\",
      \"points\": 3,
      \"left_items\": [\"Item 1\", \"Item 2\", \"Item 3\"],
      \"right_items\": [\"Description A\", \"Description B\", \"Description C\"],
      \"matches\": [0, 1, 2]
    },
    {
      \"type\": \"essay\",
      \"question_text\": \"Explain the importance of...\",
      \"points\": 5,
      \"rubric\": \"Thesis (2 points), Evidence (2 points), Organization (1 point)\"
    }
  ]
}

IMPORTANT: Return ONLY the JSON object, no additional text or explanations.";
}

function callOpenAIAPI($prompt) {
    $apiKey = getOpenAIAPIKey();
    $config = getAIModelConfig();
    
    $data = [
        'model' => $config['model'],
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert Grade 6 educator. Always respond with valid JSON only, no additional text.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => $config['max_tokens'],
        'temperature' => $config['temperature'],
        'top_p' => $config['top_p'],
        'frequency_penalty' => $config['frequency_penalty'],
        'presence_penalty' => $config['presence_penalty']
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('CURL Error: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception(AI_ERROR_API_FAILED . ': HTTP ' . $httpCode . ' - ' . $response);
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Invalid response from OpenAI API');
    }
    
    $content = $result['choices'][0]['message']['content'];
    
    // Clean the response - remove any markdown formatting
    $content = preg_replace('/```json\s*/', '', $content);
    $content = preg_replace('/```\s*$/', '', $content);
    $content = trim($content);
    
    // Parse the JSON response
    $questions = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON Parse Error: ' . json_last_error_msg());
        error_log('Raw AI Response: ' . $content);
        throw new Exception(AI_ERROR_INVALID_RESPONSE . ': ' . json_last_error_msg());
    }
    
    if (!isset($questions['questions']) || !is_array($questions['questions'])) {
        throw new Exception('Invalid question format from AI');
    }
    
    return $questions['questions'];
}
?>

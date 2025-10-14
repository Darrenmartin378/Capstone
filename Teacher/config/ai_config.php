<?php
/**
 * AI Configuration File
 * 
 * This file contains configuration settings for AI question generation.
 * Make sure to keep your API keys secure and never commit them to version control.
 */

// OpenAI API Configuration
define('OPENAI_API_KEY', ''); // Disabled - using Ollama instead
define('OPENAI_MODEL', 'gpt-3.5-turbo'); // Options: 'gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'
define('OPENAI_MAX_TOKENS', 4000);
define('OPENAI_TEMPERATURE', 0.7);

// AI Generation Settings
define('AI_MAX_QUESTIONS', 10);
define('AI_MIN_QUESTIONS', 1);
define('AI_DEFAULT_QUESTIONS', 5);

// Content Processing Settings
define('AI_MAX_CONTENT_LENGTH', 8000); // Maximum characters to send to AI
define('AI_MIN_CONTENT_LENGTH', 100);  // Minimum content length required

// Error Messages
define('AI_ERROR_NO_API_KEY', 'OpenAI API key is not configured');
define('AI_ERROR_INVALID_CONTENT', 'Material content is too short or invalid');
define('AI_ERROR_API_FAILED', 'Failed to connect to OpenAI API');
define('AI_ERROR_INVALID_RESPONSE', 'Invalid response from AI service');

// Rate Limiting (optional)
define('AI_RATE_LIMIT_ENABLED', false);
define('AI_RATE_LIMIT_REQUESTS', 10); // Max requests per hour per user
define('AI_RATE_LIMIT_WINDOW', 3600); // Time window in seconds

/**
 * Get OpenAI API Key
 * 
 * @return string
 */
function getOpenAIAPIKey() {
    $apiKey = OPENAI_API_KEY;
    
    // Check if API key is set
    if (empty($apiKey) || $apiKey === 'your-openai-api-key-here') {
        throw new Exception(AI_ERROR_NO_API_KEY);
    }
    
    return $apiKey;
}

/**
 * Validate material content for AI processing
 * 
 * @param string $content
 * @return bool
 */
function validateMaterialContent($content) {
    if (empty($content) || strlen(trim($content)) < AI_MIN_CONTENT_LENGTH) {
        return false;
    }
    
    return true;
}

/**
 * Truncate content to fit AI token limits
 * 
 * @param string $content
 * @return string
 */
function truncateContentForAI($content) {
    if (strlen($content) > AI_MAX_CONTENT_LENGTH) {
        return substr($content, 0, AI_MAX_CONTENT_LENGTH) . '...';
    }
    
    return $content;
}

/**
 * Get AI model configuration
 * 
 * @return array
 */
function getAIModelConfig() {
    return [
        'model' => OPENAI_MODEL,
        'max_tokens' => OPENAI_MAX_TOKENS,
        'temperature' => OPENAI_TEMPERATURE,
        'top_p' => 1,
        'frequency_penalty' => 0,
        'presence_penalty' => 0
    ];
}

/**
 * Log AI usage for monitoring
 * 
 * @param string $action
 * @param array $data
 * @return void
 */
function logAIUsage($action, $data = []) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'user_id' => $_SESSION['teacher_id'] ?? 'unknown',
        'data' => $data
    ];
    
    $logFile = __DIR__ . '/../logs/ai_usage.log';
    $logEntry = json_encode($logData) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Check if AI features are enabled
 * 
 * @return bool
 */
function isAIEnabled() {
    try {
        getOpenAIAPIKey();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

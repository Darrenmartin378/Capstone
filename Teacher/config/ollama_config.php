<?php
/**
 * Ollama Configuration File
 * 
 * This file contains configuration settings for Ollama local AI integration.
 * Ollama runs AI models locally on your computer, completely free!
 */

// Ollama Server Configuration
define('OLLAMA_BASE_URL', 'http://127.0.0.1:11434');
define('OLLAMA_MODEL', 'llama3.2:1b'); // Much lighter and faster model
define('OLLAMA_TIMEOUT', 90);

// Alternative models you can use (uncomment to switch)
// define('OLLAMA_MODEL', 'llama3.2:1b'); // Even lighter, faster
// define('OLLAMA_MODEL', 'llama3.2:8b'); // Better quality, needs more RAM
// define('OLLAMA_MODEL', 'mistral:7b'); // Alternative model
// define('OLLAMA_MODEL', 'codellama:7b'); // Good for educational content

// Model Settings
define('OLLAMA_TEMPERATURE', 0.7);
define('OLLAMA_TOP_P', 0.9);
define('OLLAMA_MAX_TOKENS', 4000);

// Content Processing Settings (same as OpenAI)
define('OLLAMA_MAX_CONTENT_LENGTH', 2000); // Much smaller for faster processing
define('OLLAMA_MIN_CONTENT_LENGTH', 50);

// Error Messages
define('OLLAMA_ERROR_NOT_RUNNING', 'Ollama is not running. Please start Ollama service.');
define('OLLAMA_ERROR_MODEL_NOT_FOUND', 'Ollama model not found. Please install the model.');
define('OLLAMA_ERROR_CONNECTION_FAILED', 'Failed to connect to Ollama service.');

/**
 * Check if Ollama is running and available
 * 
 * @return bool
 */
function isOllamaRunning() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, OLLAMA_BASE_URL . '/api/tags');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log('Ollama connection error: ' . $curlError);
        return false;
    }
    
    return $httpCode === 200;
}

/**
 * Get available Ollama models
 * 
 * @return array
 */
function getOllamaModels() {
    if (!isOllamaRunning()) {
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
 * Check if the configured model is available
 * 
 * @return bool
 */
function isOllamaModelAvailable() {
    $models = getOllamaModels();
    return in_array(OLLAMA_MODEL, $models);
}

/**
 * Get Ollama model configuration
 * 
 * @return array
 */
function getOllamaModelConfig() {
    return [
        'model' => OLLAMA_MODEL,
        'temperature' => OLLAMA_TEMPERATURE,
        'top_p' => OLLAMA_TOP_P,
        'max_tokens' => OLLAMA_MAX_TOKENS
    ];
}

/**
 * Check if Ollama is properly configured and ready
 * 
 * @return array
 */
function checkOllamaStatus() {
    $status = [
        'running' => false,
        'model_available' => false,
        'models' => [],
        'error' => null
    ];
    
    try {
        $status['running'] = isOllamaRunning();
        
        if ($status['running']) {
            $status['models'] = getOllamaModels();
            $status['model_available'] = isOllamaModelAvailable();
            
            if (!$status['model_available']) {
                $status['error'] = 'Configured model "' . OLLAMA_MODEL . '" is not installed. Available models: ' . implode(', ', $status['models']);
            }
        } else {
            $status['error'] = 'Ollama service is not running on ' . OLLAMA_BASE_URL;
        }
    } catch (Exception $e) {
        $status['error'] = $e->getMessage();
    }
    
    return $status;
}

/**
 * Log Ollama usage for monitoring
 * 
 * @param string $action
 * @param array $data
 * @return void
 */
function logOllamaUsage($action, $data = []) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'user_id' => $_SESSION['teacher_id'] ?? 'unknown',
        'model' => OLLAMA_MODEL,
        'data' => $data
    ];
    
    $logFile = __DIR__ . '/../logs/ollama_usage.log';
    $logEntry = json_encode($logData) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>

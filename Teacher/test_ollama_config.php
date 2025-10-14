<?php
/**
 * Ollama Configuration Test File
 * 
 * This file helps you test if your Ollama configuration is working correctly.
 * Access this file in your browser to check the configuration.
 */

require_once __DIR__ . '/config/ollama_config.php';

echo "<h2>Ollama Configuration Test</h2>";

// Test 1: Check if Ollama is running
echo "<h3>1. Ollama Service Status</h3>";
$status = checkOllamaStatus();

if ($status['running']) {
    echo "✅ <strong>Ollama is running</strong><br>";
} else {
    echo "❌ <strong>Ollama is not running</strong><br>";
    echo "Error: " . ($status['error'] ?? 'Unknown error') . "<br>";
    echo "<br><strong>To fix this:</strong><br>";
    echo "1. Install Ollama from <a href='https://ollama.ai' target='_blank'>https://ollama.ai</a><br>";
    echo "2. Start Ollama service: <code>ollama serve</code><br>";
    echo "3. Install a model: <code>ollama pull llama3.2:3b</code><br>";
}

// Test 2: Check available models
echo "<h3>2. Available Models</h3>";
if ($status['running']) {
    if (!empty($status['models'])) {
        echo "✅ <strong>Available models:</strong><br>";
        foreach ($status['models'] as $model) {
            $isCurrent = ($model === OLLAMA_MODEL) ? " (Current)" : "";
            echo "• " . $model . $isCurrent . "<br>";
        }
    } else {
        echo "❌ <strong>No models installed</strong><br>";
        echo "<br><strong>To fix this:</strong><br>";
        echo "Run: <code>ollama pull llama3.2:3b</code><br>";
    }
} else {
    echo "❌ <strong>Cannot check models - Ollama not running</strong><br>";
}

// Test 3: Check configured model
echo "<h3>3. Configured Model</h3>";
echo "<strong>Current model:</strong> " . OLLAMA_MODEL . "<br>";

if ($status['model_available']) {
    echo "✅ <strong>Configured model is available</strong><br>";
} else {
    echo "❌ <strong>Configured model is not available</strong><br>";
    if ($status['running']) {
        echo "<br><strong>To fix this:</strong><br>";
        echo "Run: <code>ollama pull " . OLLAMA_MODEL . "</code><br>";
    }
}

// Test 4: Test API connection
echo "<h3>4. API Connection Test</h3>";
if ($status['running'] && $status['model_available']) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, OLLAMA_BASE_URL . '/api/generate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => OLLAMA_MODEL,
            'prompt' => 'Hello, are you working?',
            'stream' => false
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['response'])) {
                echo "✅ <strong>API connection successful</strong><br>";
                echo "Test response: " . htmlspecialchars(substr($result['response'], 0, 100)) . "...<br>";
            } else {
                echo "❌ <strong>Invalid API response</strong><br>";
            }
        } else {
            echo "❌ <strong>API connection failed</strong> (HTTP $httpCode)<br>";
        }
    } catch (Exception $e) {
        echo "❌ <strong>API test error:</strong> " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ <strong>Cannot test API - Ollama not ready</strong><br>";
}

// Test 5: System Resources
echo "<h3>5. System Resources</h3>";
$ramGB = round(memory_get_usage(true) / 1024 / 1024 / 1024, 2);
echo "<strong>PHP Memory Usage:</strong> {$ramGB}GB<br>";

// Check if we can detect system RAM (approximate)
if (function_exists('shell_exec')) {
    $ramInfo = shell_exec('wmic computersystem get TotalPhysicalMemory 2>nul');
    if ($ramInfo) {
        $ramBytes = (int)filter_var($ramInfo, FILTER_SANITIZE_NUMBER_INT);
        $ramGB = round($ramBytes / 1024 / 1024 / 1024, 1);
        echo "<strong>System RAM:</strong> {$ramGB}GB<br>";
        
        if ($ramGB >= 8) {
            echo "✅ <strong>Sufficient RAM for Ollama</strong><br>";
        } else {
            echo "⚠️ <strong>Low RAM - consider using llama3.2:1b model</strong><br>";
        }
    }
}

echo "<hr>";

// Summary
echo "<h3>Summary</h3>";
if ($status['running'] && $status['model_available']) {
    echo "🎉 <strong>Ollama is ready to use!</strong><br>";
    echo "You can now generate questions using AI in your material question builder.<br>";
} else {
    echo "❌ <strong>Ollama needs setup</strong><br>";
    echo "Please follow the setup instructions in OLLAMA_SETUP_GUIDE.md<br>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If you see any ❌ errors above, please fix them</li>";
echo "<li>Make sure Ollama is running: <code>ollama serve</code></li>";
echo "<li>Install a model: <code>ollama pull llama3.2:3b</code></li>";
echo "<li>Test the AI generation in the material question builder</li>";
echo "<li>Delete this test file after verification</li>";
echo "</ol>";

echo "<p><strong>Note:</strong> Ollama is completely FREE and runs locally on your computer!</p>";
?>

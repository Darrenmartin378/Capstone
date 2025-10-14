<?php
/**
 * AI Configuration Test File
 * 
 * This file helps you test if your AI configuration is working correctly.
 * Access this file in your browser to check the configuration.
 */

require_once __DIR__ . '/config/ai_config.php';

echo "<h2>AI Configuration Test</h2>";

// Test 1: Check if AI is enabled
echo "<h3>1. AI Status Check</h3>";
try {
    if (isAIEnabled()) {
        echo "✅ <strong>AI is enabled</strong><br>";
    } else {
        echo "❌ <strong>AI is disabled</strong><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage() . "<br>";
}

// Test 2: Check API Key
echo "<h3>2. API Key Check</h3>";
try {
    $apiKey = getOpenAIAPIKey();
    $maskedKey = substr($apiKey, 0, 8) . "..." . substr($apiKey, -4);
    echo "✅ <strong>API Key is configured:</strong> " . $maskedKey . "<br>";
} catch (Exception $e) {
    echo "❌ <strong>API Key Error:</strong> " . $e->getMessage() . "<br>";
}

// Test 3: Check Model Configuration
echo "<h3>3. Model Configuration</h3>";
try {
    $config = getAIModelConfig();
    echo "✅ <strong>Model:</strong> " . $config['model'] . "<br>";
    echo "✅ <strong>Max Tokens:</strong> " . $config['max_tokens'] . "<br>";
    echo "✅ <strong>Temperature:</strong> " . $config['temperature'] . "<br>";
} catch (Exception $e) {
    echo "❌ <strong>Configuration Error:</strong> " . $e->getMessage() . "<br>";
}

// Test 4: Check Content Validation
echo "<h3>4. Content Validation Test</h3>";
$testContent = "This is a test material content for Grade 6 students. It contains educational information about various subjects including mathematics, science, and language arts.";
if (validateMaterialContent($testContent)) {
    echo "✅ <strong>Content validation working</strong><br>";
} else {
    echo "❌ <strong>Content validation failed</strong><br>";
}

// Test 5: Check Logs Directory
echo "<h3>5. Logs Directory Check</h3>";
$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir) && is_writable($logsDir)) {
    echo "✅ <strong>Logs directory exists and is writable</strong><br>";
} else {
    echo "❌ <strong>Logs directory issue:</strong> " . $logsDir . "<br>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If you see any ❌ errors above, please fix them</li>";
echo "<li>Make sure your OpenAI API key is valid and has credits</li>";
echo "<li>Test the AI generation in the material question builder</li>";
echo "<li>Delete this test file after verification</li>";
echo "</ol>";

echo "<p><strong>Note:</strong> Remember to replace the placeholder API key in <code>config/ai_config.php</code> with your actual OpenAI API key!</p>";
?>

<?php
/**
 * Debug Console for CompreLearn System
 * Check errors in both teacher and student systems
 */

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>CompreLearn Debug Console</h1>";

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'compre_learn';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>1. Database Connection Status</h2>";
echo "✅ Database connected successfully<br>";

echo "<h2>2. Check Question Sets</h2>";
$result = $conn->query("SELECT * FROM question_sets ORDER BY id DESC LIMIT 5");
if ($result) {
    echo "<table border='1'><tr><th>ID</th><th>Title</th><th>Teacher ID</th><th>Section ID</th><th>Created</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['set_title']}</td><td>{$row['teacher_id']}</td><td>{$row['section_id']}</td><td>{$row['created_at']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "❌ Error: " . $conn->error;
}

echo "<h2>3. Check MCQ Questions</h2>";
$result = $conn->query("SELECT * FROM mcq_questions ORDER BY question_id DESC LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'><tr><th>ID</th><th>Set ID</th><th>Question</th><th>Points</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['question_id']}</td><td>{$row['set_id']}</td><td>{$row['question_text']}</td><td>{$row['points']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No MCQ questions found<br>";
    }
} else {
    echo "❌ Error: " . $conn->error;
}

echo "<h2>4. Check Matching Questions</h2>";
$result = $conn->query("SELECT * FROM matching_questions ORDER BY question_id DESC LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'><tr><th>ID</th><th>Set ID</th><th>Question</th><th>Points</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['question_id']}</td><td>{$row['set_id']}</td><td>{$row['question_text']}</td><td>{$row['points']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No matching questions found<br>";
    }
} else {
    echo "❌ Error: " . $conn->error;
}

echo "<h2>5. Check Essay Questions</h2>";
$result = $conn->query("SELECT * FROM essay_questions ORDER BY question_id DESC LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'><tr><th>ID</th><th>Set ID</th><th>Question</th><th>Points</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['question_id']}</td><td>{$row['set_id']}</td><td>{$row['question_text']}</td><td>{$row['points']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No essay questions found<br>";
    }
} else {
    echo "❌ Error: " . $conn->error;
}

echo "<h2>6. Check Old Questions Table</h2>";
$result = $conn->query("SELECT * FROM questions ORDER BY id DESC LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'><tr><th>ID</th><th>Set ID</th><th>Type</th><th>Question</th><th>Points</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['set_id']}</td><td>{$row['type']}</td><td>{$row['question_text']}</td><td>{$row['points']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No questions in old table<br>";
    }
} else {
    echo "❌ Error: " . $conn->error;
}

echo "<h2>7. Check Student Responses</h2>";
$result = $conn->query("SELECT * FROM student_responses ORDER BY response_id DESC LIMIT 5");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'><tr><th>ID</th><th>Student ID</th><th>Set ID</th><th>Type</th><th>Answer</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['response_id']}</td><td>{$row['student_id']}</td><td>{$row['question_set_id']}</td><td>{$row['question_type']}</td><td>{$row['answer']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No student responses found<br>";
    }
} else {
    echo "❌ Error: " . $conn->error;
}

echo "<h2>8. Test QuestionHandler</h2>";
try {
    require_once 'Teacher/includes/QuestionHandler.php';
    $questionHandler = new QuestionHandler($conn);
    
    // Test data
    $testData = [
        'question_text' => 'Test question',
        'type' => 'mcq',
        'points' => 1,
        'choice_a' => 'Option A',
        'choice_b' => 'Option B',
        'choice_c' => 'Option C',
        'choice_d' => 'Option D',
        'correct_answer' => 'A'
    ];
    
    echo "Testing MCQ question creation...<br>";
    $result = $questionHandler->createQuestion(4, 1, 999, $testData);
    echo "Result: " . json_encode($result) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Error testing QuestionHandler: " . $e->getMessage() . "<br>";
}

echo "<h2>9. Test NewResponseHandler</h2>";
try {
    require_once 'Student/includes/NewResponseHandler.php';
    $responseHandler = new NewResponseHandler($conn);
    
    // Test data
    $testResponses = [
        1 => 'A'  // Question ID 1, answer A
    ];
    
    echo "Testing response submission...<br>";
    $result = $responseHandler->submitResponses(5, 1, $testResponses);
    echo "Result: " . ($result ? 'Success' : 'Failed') . "<br>";
    
} catch (Exception $e) {
    echo "❌ Error testing NewResponseHandler: " . $e->getMessage() . "<br>";
}

echo "<h2>10. Check Error Logs</h2>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $logs = file_get_contents($errorLog);
    $recentLogs = array_slice(explode("\n", $logs), -20);
    echo "<pre>" . implode("\n", $recentLogs) . "</pre>";
} else {
    echo "No error log found or accessible<br>";
}

$conn->close();
?>

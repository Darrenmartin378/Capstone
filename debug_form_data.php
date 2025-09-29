<?php
// Debug script to show form data structure
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h1>Form Data Debug</h1>";
    echo "<h2>Raw POST Data:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h2>Questions Array:</h2>";
    if (isset($_POST['questions'])) {
        echo "<pre>";
        print_r($_POST['questions']);
        echo "</pre>";
        
        $questionCount = 0;
        foreach ($_POST['questions'] as $index => $q) {
            $questionCount++;
            echo "<h3>Question $questionCount (Index: $index)</h3>";
            echo "<p><strong>Type:</strong> " . ($q['type'] ?? 'not set') . "</p>";
            
            if (($q['type'] ?? '') === 'matching') {
                echo "<p><strong>Rows:</strong></p>";
                echo "<ul>";
                if (isset($q['rows']) && is_array($q['rows'])) {
                    foreach ($q['rows'] as $rowIndex => $rowText) {
                        echo "<li>Row $rowIndex: '$rowText'</li>";
                    }
                } else {
                    echo "<li>No rows found or not an array</li>";
                }
                echo "</ul>";
                
                echo "<p><strong>Columns:</strong></p>";
                echo "<ul>";
                if (isset($q['columns']) && is_array($q['columns'])) {
                    foreach ($q['columns'] as $colIndex => $colText) {
                        echo "<li>Column $colIndex: '$colText'</li>";
                    }
                } else {
                    echo "<li>No columns found or not an array</li>";
                }
                echo "</ul>";
                
                echo "<p><strong>Answer Key:</strong></p>";
                echo "<ul>";
                if (isset($q['answer_key']) && is_array($q['answer_key'])) {
                    foreach ($q['answer_key'] as $rowIndex => $colIndex) {
                        echo "<li>Row $rowIndex -> Column $colIndex</li>";
                    }
                } else {
                    echo "<li>No answer key found or not an array</li>";
                }
                echo "</ul>";
            }
        }
        
        echo "<h2>Total Questions Found: $questionCount</h2>";
    } else {
        echo "<p style='color: red;'>No questions array found in POST data!</p>";
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Form Data Debug</title>
</head>
<body>
    <h1>Form Data Debug Tool</h1>
    <p>This tool will show you exactly what form data is being sent.</p>
    
    <form method="POST">
        <h2>Test Form</h2>
        
        <h3>Question 1: Multiple Choice</h3>
        <input type="hidden" name="questions[1][type]" value="multiple_choice">
        <input type="text" name="questions[1][text]" value="What is 2+2?" placeholder="Question text">
        <br><br>
        <input type="text" name="questions[1][options][A]" value="3" placeholder="Option A">
        <input type="text" name="questions[1][options][B]" value="4" placeholder="Option B">
        <input type="text" name="questions[1][answer]" value="B" placeholder="Correct answer">
        
        <h3>Question 2: Matching</h3>
        <input type="hidden" name="questions[2][type]" value="matching">
        <h4>Rows:</h4>
        <input type="text" name="questions[2][rows][0]" value="Capital of France" placeholder="Row 1">
        <input type="text" name="questions[2][rows][1]" value="Capital of Germany" placeholder="Row 2">
        <h4>Columns:</h4>
        <input type="text" name="questions[2][columns][0]" value="Paris" placeholder="Column A">
        <input type="text" name="questions[2][columns][1]" value="Berlin" placeholder="Column B">
        <h4>Answer Key:</h4>
        <input type="hidden" name="questions[2][answer_key][0]" value="0">
        <input type="hidden" name="questions[2][answer_key][1]" value="1">
        
        <h3>Question 3: Essay</h3>
        <input type="hidden" name="questions[3][type]" value="essay">
        <input type="text" name="questions[3][text]" value="Explain photosynthesis" placeholder="Question text">
        <input type="number" name="questions[3][word_limit]" value="100" placeholder="Word limit">
        <input type="text" name="questions[3][rubrics]" value="Clear explanation required" placeholder="Rubrics">
        
        <br><br>
        <button type="submit">Debug Form Data</button>
    </form>
</body>
</html>

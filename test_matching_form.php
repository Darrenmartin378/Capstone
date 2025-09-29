<?php
// Simple test to check form data structure
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Form Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    if (isset($_POST['questions'])) {
        echo "<h2>Questions Array:</h2>";
        echo "<pre>";
        print_r($_POST['questions']);
        echo "</pre>";
        
        foreach ($_POST['questions'] as $index => $q) {
            if (($q['question_type'] ?? '') === 'matching') {
                echo "<h3>Matching Question $index:</h3>";
                echo "<p>Rows: " . print_r($q['rows'] ?? [], true) . "</p>";
                echo "<p>Columns: " . print_r($q['columns'] ?? [], true) . "</p>";
                echo "<p>Answer Key: " . print_r($q['answer_key'] ?? [], true) . "</p>";
                
                $rows = $q['rows'] ?? [];
                $nonEmptyRows = 0;
                foreach ($rows as $rowText) {
                    if (!empty(trim($rowText))) {
                        $nonEmptyRows++;
                    }
                }
                echo "<p>Non-empty rows: $nonEmptyRows</p>";
            }
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Matching Form</title>
</head>
<body>
    <h1>Test Matching Question Form</h1>
    <form method="POST">
        <h2>Question 1: Multiple Choice</h2>
        <input type="hidden" name="questions[1][type]" value="multiple_choice">
        <input type="text" name="questions[1][text]" value="What is 2+2?" placeholder="Question text">
        <br><br>
        <input type="text" name="questions[1][options][A]" value="3" placeholder="Option A">
        <input type="text" name="questions[1][options][B]" value="4" placeholder="Option B">
        <input type="text" name="questions[1][answer]" value="B" placeholder="Correct answer">
        
        <h2>Question 2: Matching</h2>
        <input type="hidden" name="questions[2][type]" value="matching">
        <h3>Rows:</h3>
        <input type="text" name="questions[2][rows][0]" value="Capital of France" placeholder="Row 1">
        <input type="text" name="questions[2][rows][1]" value="Capital of Germany" placeholder="Row 2">
        <h3>Columns:</h3>
        <input type="text" name="questions[2][columns][0]" value="Paris" placeholder="Column A">
        <input type="text" name="questions[2][columns][1]" value="Berlin" placeholder="Column B">
        <h3>Answer Key:</h3>
        <input type="hidden" name="questions[2][answer_key][0]" value="0">
        <input type="hidden" name="questions[2][answer_key][1]" value="1">
        
        <h2>Question 3: Essay</h2>
        <input type="hidden" name="questions[3][type]" value="essay">
        <input type="text" name="questions[3][text]" value="Explain photosynthesis" placeholder="Question text">
        <input type="number" name="questions[3][word_limit]" value="100" placeholder="Word limit">
        <input type="text" name="questions[3][rubrics]" value="Clear explanation required" placeholder="Rubrics">
        
        <br><br>
        <button type="submit">Test Form Submission</button>
    </form>
</body>
</html>

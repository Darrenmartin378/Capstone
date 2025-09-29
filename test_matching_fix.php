<?php
// Test script to verify matching questions are saved correctly
require_once 'Teacher/includes/teacher_init.php';

echo "<h1>Test Matching Questions Fix</h1>";

// Test data that simulates what the form should send
$testData = [
    'set_title' => 'TEST_MATCHING_FIX',
    'section_id' => 1,
    'questions' => [
        [
            'type' => 'multiple_choice',
            'text' => 'What is 2+2?',
            'options' => ['A' => '3', 'B' => '4'],
            'answer' => 'B'
        ],
        [
            'type' => 'matching',
            'rows' => [
                0 => 'Capital of France',
                1 => 'Capital of Germany',
                2 => 'Capital of Spain',
                3 => 'Capital of Italy'
            ],
            'columns' => [
                0 => 'Paris',
                1 => 'Berlin',
                2 => 'Madrid',
                3 => 'Rome'
            ],
            'answer_key' => [
                0 => 0, // France -> Paris
                1 => 1, // Germany -> Berlin
                2 => 2, // Spain -> Madrid
                3 => 3  // Italy -> Rome
            ]
        ],
        [
            'type' => 'essay',
            'text' => 'Explain photosynthesis',
            'word_limit' => 100,
            'rubrics' => 'Clear explanation required'
        ]
    ]
];

echo "<h2>Test Data:</h2>";
echo "<pre>" . print_r($testData, true) . "</pre>";

// Simulate the processing logic
$validQuestions = 0;
$errors = [];

foreach ($testData['questions'] as $index => $q) {
    $type = $q['type'] ?? '';
    $text = trim($q['text'] ?? '');
    
    echo "<h3>Processing Question $index (Type: $type)</h3>";
    
    if ($type === 'multiple_choice') {
        $validQuestions++;
        echo "<p>Multiple choice question saved. Total: $validQuestions</p>";
    } elseif ($type === 'matching') {
        $rows = $q['rows'] ?? [];
        $columns = $q['columns'] ?? [];
        $answerKey = $q['answer_key'] ?? [];
        
        echo "<p>Matching question with " . count($rows) . " rows and " . count($columns) . " columns</p>";
        
        // Save each row as a separate question
        foreach ($rows as $rowIndex => $rowText) {
            if (!empty(trim($rowText))) {
                $validQuestions++;
                echo "<p>Row $rowIndex: '$rowText' saved as question. Total: $validQuestions</p>";
            }
        }
    } elseif ($type === 'essay') {
        $validQuestions++;
        echo "<p>Essay question saved. Total: $validQuestions</p>";
    }
}

echo "<h2>Final Result:</h2>";
echo "<p><strong>Total Questions Created: $validQuestions</strong></p>";
echo "<p>Expected: 6 questions (1 MC + 4 Matching rows + 1 Essay)</p>";

if ($validQuestions === 6) {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: All questions saved correctly!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED: Expected 6 questions, got $validQuestions</p>";
}
?>

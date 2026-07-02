<?php
define('AJAX_SCRIPT', true);
require_once('../../config.php');

// 1. Safely initialize the Moodle session.
// Passing (null, false) allows guests to view the catalog without being violently redirected to the login page.
require_login(null, false);

// 2. Get the requested course ID securely
$courseid = required_param('courseid', PARAM_INT);

// 3. Set up the JSON response headers
header('Content-Type: application/json');

// 4. Wrap everything in a try-catch to guarantee we always return JSON, never an HTML crash page
try {
    global $DB;

    // The SQL Query: Find all answers to the "How would you rate this course?" question
    $sql = "SELECT fv.id, fv.value
            FROM {feedback} f
            JOIN {feedback_item} fi ON fi.feedback = f.id
            JOIN {feedback_value} fv ON fv.item = fi.id
            WHERE f.course = :courseid 
            AND fi.typ = 'multichoice'
            AND fi.name LIKE '%rate this course%'"; 

    $responses = $DB->get_records_sql($sql, ['courseid' => $courseid]);

    if (empty($responses)) {
        echo json_encode(['rating' => 0, 'count' => 0]);
        exit;
    }

    $total_stars = 0;
    $valid_responses = 0;

    // Calculate the Average
    foreach ($responses as $res) {
        $answer_index = (int)$res->value;
        
        // In Moodle, 0 usually means "Not selected". 
        // Index 1 = (0)5=Excellent (5 stars)
        // Index 2 = (0)4=Very Good (4 stars)
        // Index 3 = (0)3=Average (3 stars)
        // Index 4 = (0)2=Poor (2 stars)
        // Index 5 = (0)1=Very Poor (1 star)
        
        if ($answer_index > 0 && $answer_index <= 5) {
            $stars = 6 - $answer_index; // Reverses the index into a 1-5 star scale
            $total_stars += $stars;
            $valid_responses++;
        }
    }

    if ($valid_responses > 0) {
        $average = round($total_stars / $valid_responses, 1);
        echo json_encode(['rating' => $average, 'count' => $valid_responses]);
    } else {
        echo json_encode(['rating' => 0, 'count' => 0]);
    }

} catch (Exception $e) {
    // If the database query fails (e.g., table missing or syntax error), output a clean JSON error
    http_response_code(500);
    echo json_encode([
        'error' => 'Database exception', 
        'message' => $e->getMessage()
    ]);
}

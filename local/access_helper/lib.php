<?php
defined('MOODLE_INTERNAL') || die();

function get_course_average_rating($courseid) {
    global $DB;
    $feedback = $DB->get_record('feedback', array('course' => $courseid));
    if (!$feedback) return "N/A";
    
    $item = $DB->get_record('feedback_item', array('feedback' => $feedback->id, 'typ' => 'multichoicerated'));
    if (!$item) return "0.0";

    $sql = "SELECT AVG(value) FROM {feedback_value} WHERE item = :itemid AND value IS NOT NULL";
    $avg = $DB->get_field_sql($sql, array('itemid' => $item->id));
    
    return $avg ? number_format((float)$avg, 1) : "0.0";
}

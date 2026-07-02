<?php
namespace theme_access_custom\output\course;

defined('MOODLE_INTERNAL') || die();

class renderer extends \core_course\output\course_renderer {
    
    // We override the function that prints individual course boxes
    public function course_box($course, $style = null) {
        // 1. Get our data using the helper we created earlier
        $avg = get_course_average_rating($course->id);
        
        // 2. Capture the default output
        $output = parent::course_box($course, $style);
        
        // 3. Inject our custom data attributes for the JavaScript to read
        $search = '<div class="coursebox';
        $replace = '<div class="course-list-item" data-rating="'.$avg.'" data-learn="Learn more about '.$course->fullname.'" class="coursebox';
        
        return str_replace($search, $replace, $output);
    }
}

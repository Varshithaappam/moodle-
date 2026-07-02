<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class local_aitutor_external extends external_api {

    public static function mobile_get_content_parameters() {
        return new external_function_parameters([]);
    }

    public static function mobile_get_content() {

        global $CFG;

        return [
            'html' => '
                <iframe
                    src="'.$CFG->wwwroot.'/local/aitutor/index.php"
                    width="100%"
                    height="100%"
                    style="border:none;">
                </iframe>
            '
        ];
    }

    public static function mobile_get_content_returns() {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'HTML content')
        ]);
    }
}

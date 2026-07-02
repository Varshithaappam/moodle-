<?php

namespace local_aitutor\output;

defined('MOODLE_INTERNAL') || die();

class mobile {

    public static function mobile_view() {

        global $CFG;

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '
                        <iframe
                            src="'.$CFG->wwwroot.'/local/aitutor/index.php"
                            width="100%"
                            height="100%"
                            style="border:none;">
                        </iframe>
                    '
                ]
            ]
        ];
    }
}

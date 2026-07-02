<?php

defined('MOODLE_INTERNAL') || die();

function local_aitutor_mobile_get_content($args) {

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
        ],
        'javascript' => '',
        'otherdata' => ''
    ];
}

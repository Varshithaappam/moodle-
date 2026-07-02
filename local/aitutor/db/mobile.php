<?php

defined('MOODLE_INTERNAL') || die();

$addons = [
    'local_aitutor' => [
        'handlers' => [
            'aitutor' => [
                'delegate' => 'CoreMainMenuDelegate',
                'method' => 'mobile_view'
            ]
        ],
        'lang' => [
            ['pluginname', 'local_aitutor']
        ]
    ]
];

return $addons;

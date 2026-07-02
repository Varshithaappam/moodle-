<?php

$functions = [
    'local_aitutor_mobile_get_content' => [
        'classname'   => 'local_aitutor_external',
        'methodname'  => 'mobile_get_content',
        'classpath'   => 'local/aitutor/externallib.php',
        'description' => 'Get AI Tutor mobile content',
        'type'        => 'read',
        'ajax'        => true,
    ],
];

$services = [
    'AI Tutor mobile service' => [
        'functions' => [
            'local_aitutor_mobile_get_content'
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
    ]
];

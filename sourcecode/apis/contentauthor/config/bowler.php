<?php

return [

    'rabbitmq' => [
        'secure' => trim(env('RABBITMQ_SECURE', false)),
        'host' => trim(env('RABBITMQ_HOST', 'localhost')),
        'port' => trim(env('RABBITMQ_PORT', '5672')),
        'username' => trim(env('RABBITMQ_USERNAME', 'guest')),
        'password' => trim(env('RABBITMQ_PASSWORD', 'guest')),
        'connection_timeout' => trim(env('RABBITMQ_CONNECTION_TIMEOUT', 60)),
        'read_write_timeout' => trim(env('RABBITMQ_RW_TIMEOUT', 60)),
        'heartbeat' => trim(env('RABBITMQ_HEARTBEAT', 30)),
        'vhost' => trim(env('RABBITMQ_VHOST', '/')),
    ],

    'lifecycle_hooks' => [
        'fail_on_error' => false,
    ],

    'log' => [
        'message' => [
            'truncate_length' => 16000,
        ],
    ],

];
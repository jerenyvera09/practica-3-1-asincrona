<?php

return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'queue' => env('RABBITMQ_QUEUE', 'notificaciones_citas'),
    'error_queue' => env('RABBITMQ_ERROR_QUEUE', 'notificaciones_citas_error'),
    'confirm_timeout' => (float) env('RABBITMQ_CONFIRM_TIMEOUT', 5),
];

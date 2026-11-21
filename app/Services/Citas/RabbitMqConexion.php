<?php

namespace App\Services\Citas;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMqConexion
{
    public function abrir(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost'),
        );
    }

    public function declararColas($canal): void
    {
        $canal->queue_declare(config('rabbitmq.error_queue'), false, true, false, false);
        $canal->queue_declare(config('rabbitmq.queue'), false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => config('rabbitmq.error_queue'),
        ]));
    }
}

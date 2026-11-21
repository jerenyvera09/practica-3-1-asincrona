<?php

namespace App\Services\Citas;

use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

class RabbitMqPublicador implements PublicadorNotificaciones
{
    public function __construct(private RabbitMqConexion $conexion) {}

    public function publicar(array $mensaje): void
    {
        $conexion = $this->conexion->abrir();
        $canal = $conexion->channel();

        try {
            $this->conexion->declararColas($canal);
            $canal->confirm_select();
            $canal->set_nack_handler(function (): void {
                throw new RuntimeException('RabbitMQ rechazó la publicación');
            });
            $canal->basic_publish(new AMQPMessage(json_encode($mensaje, JSON_THROW_ON_ERROR), [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $mensaje['message_id'],
                'correlation_id' => $mensaje['correlation_id'],
            ]), '', config('rabbitmq.queue'));
            $canal->wait_for_pending_acks_returns(config('rabbitmq.confirm_timeout'));
        } finally {
            $canal->close();
            $conexion->close();
        }
    }
}

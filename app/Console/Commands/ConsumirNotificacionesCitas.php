<?php

namespace App\Console\Commands;

use App\Services\Citas\ProcesadorNotificaciones;
use App\Services\Citas\RabbitMqConexion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use Throwable;

#[Signature('app:consumir-notificaciones-citas {--max-messages=0 : Salir tras procesar esta cantidad; 0 mantiene el consumidor activo}')]
#[Description('Consume notificaciones de citas desde RabbitMQ con ack manual')]
class ConsumirNotificacionesCitas extends Command
{
    public function handle(RabbitMqConexion $rabbitMq, ProcesadorNotificaciones $procesador): int
    {
        $detener = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use (&$detener): void {
                $detener = true;
            });
            pcntl_signal(SIGTERM, function () use (&$detener): void {
                $detener = true;
            });
        }
        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function (int $evento) use (&$detener): bool {
                $detener = true;

                return true;
            });
        }
        $conexion = $rabbitMq->abrir();
        $canal = $conexion->channel();
        $procesados = 0;

        try {
            $rabbitMq->declararColas($canal);
            $canal->basic_qos(0, 1, false);
            $canal->basic_consume(config('rabbitmq.queue'), '', false, false, false, false,
                function ($entrega) use ($procesador, &$procesados, &$detener): void {
                    try {
                        $mensaje = json_decode($entrega->getBody(), true, 512, JSON_THROW_ON_ERROR);
                        if (! is_array($mensaje)) {
                            throw new InvalidArgumentException('El mensaje debe ser un objeto JSON');
                        }
                        $resultado = $procesador->procesar($mensaje);
                        $entrega->ack();
                        $this->line($resultado.' message_id='.$mensaje['message_id'].' correlation_id='.$mensaje['correlation_id']);
                    } catch (InvalidArgumentException|\JsonException $excepcion) {
                        $this->registrarError($mensaje ?? null, 'error_mensaje');
                        Log::warning('Notificación inválida o duplicada inconsistente', ['error' => $excepcion->getMessage(),
                            'message_id' => $mensaje['message_id'] ?? null, 'correlation_id' => $mensaje['correlation_id'] ?? null]);
                        $entrega->nack(false, false);
                        $this->warn('Mensaje enviado a cola de errores: '.$excepcion->getMessage());
                    } catch (Throwable $excepcion) {
                        $this->registrarError($mensaje ?? null, 'error_procesamiento');
                        Log::error('Error al procesar notificación', ['error' => $excepcion->getMessage(),
                            'message_id' => $mensaje['message_id'] ?? null, 'correlation_id' => $mensaje['correlation_id'] ?? null]);
                        $entrega->nack(false, false);
                        $this->error('Procesamiento fallido; mensaje enviado a cola de errores');
                    }
                    $procesados++;
                    $maximo = (int) $this->option('max-messages');
                    if ($maximo > 0 && $procesados >= $maximo) {
                        $detener = true;
                    }
                });
            $this->info('Consumidor activo. Ctrl+C para salir.');
            while (! $detener && $canal->is_consuming()) {
                try {
                    $canal->wait(null, false, 1);
                } catch (AMQPTimeoutException) {
                    continue;
                }
            }
        } finally {
            $canal->close();
            $conexion->close();
        }

        return self::SUCCESS;
    }

    private function registrarError(mixed $mensaje, string $estado): void
    {
        if (! is_array($mensaje) || ! isset($mensaje['message_id'], $mensaje['correlation_id'])
            || ! Str::isUuid($mensaje['message_id']) || ! Str::isUuid($mensaje['correlation_id'])) {
            return;
        }

        try {
            DB::table('notificaciones_citas')->where('message_id', $mensaje['message_id'])
                ->where('correlation_id', $mensaje['correlation_id'])->where('estado', 'pendiente')
                ->update(['estado' => $estado, 'detalle' => 'Mensaje rechazado; revisar cola de errores', 'updated_at' => now()]);
        } catch (Throwable $excepcion) {
            Log::error('No se pudo registrar el rechazo en SQLite', ['error' => $excepcion->getMessage(),
                'message_id' => $mensaje['message_id']]);
        }
    }
}

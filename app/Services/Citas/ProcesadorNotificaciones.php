<?php

namespace App\Services\Citas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcesadorNotificaciones
{
    public function procesar(array $mensaje): string
    {
        if (! isset($mensaje['message_id'], $mensaje['correlation_id'], $mensaje['tipo'], $mensaje['payload']['solicitud_id'], $mensaje['payload']['estado_solicitud'])
            || ! Str::isUuid($mensaje['message_id']) || ! Str::isUuid($mensaje['correlation_id'])
            || $mensaje['tipo'] !== 'solicitud_cita'
            || filter_var($mensaje['payload']['solicitud_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            || ! in_array($mensaje['payload']['estado_solicitud'], ['solicitada', 'confirmada', 'cancelada'], true)) {
            throw new InvalidArgumentException('Mensaje de cita inválido');
        }

        return DB::transaction(function () use ($mensaje): string {
            $registro = DB::table('notificaciones_citas')->where('message_id', $mensaje['message_id'])->first();
            if (! $registro || $registro->correlation_id !== $mensaje['correlation_id']
                || (int) $registro->solicitud_id !== (int) $mensaje['payload']['solicitud_id']
                || $registro->tipo_evento !== $mensaje['tipo']) {
                throw new InvalidArgumentException('Mensaje desconocido o no coincide con el registro');
            }
            if ($registro->estado === 'procesada') {
                return 'duplicado';
            }
            if ($registro->estado !== 'pendiente') {
                throw new InvalidArgumentException('Estado del mensaje no procesable');
            }

            $inicio = hrtime(true);
            DB::table('notificaciones_citas')->where('id', $registro->id)->update([
                'estado' => 'procesada',
                'detalle' => 'Solicitud '.$mensaje['payload']['estado_solicitud'].'; notificación registrada',
                'procesado_en' => now(),
                'tiempo_procesamiento_ms' => (hrtime(true) - $inicio) / 1_000_000,
                'updated_at' => now(),
            ]);

            return 'procesada';
        });
    }
}

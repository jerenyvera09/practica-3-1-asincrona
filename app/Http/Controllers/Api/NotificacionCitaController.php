<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Citas\ProcesadorNotificaciones;
use App\Services\Citas\PublicadorNotificaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class NotificacionCitaController extends Controller
{
    public function publicar(Request $request, PublicadorNotificaciones $publicador): JsonResponse
    {
        $inicio = hrtime(true);
        $datos = $this->validar($request);
        $mensaje = $this->mensaje($datos);
        $this->registrar($mensaje, 'asincrono');

        try {
            $publicador->publicar($mensaje);
        } catch (Throwable $excepcion) {
            Log::error('Falló la publicación de notificación de cita', [
                'message_id' => $mensaje['message_id'], 'correlation_id' => $mensaje['correlation_id'],
                'error' => $excepcion->getMessage(),
            ]);
            DB::table('notificaciones_citas')->where('message_id', $mensaje['message_id'])->update([
                'estado' => 'error_publicacion', 'detalle' => 'No se confirmó la publicación', 'updated_at' => now(),
            ]);

            return response()->json(['estado' => 'error_publicacion', 'message_id' => $mensaje['message_id'],
                'correlation_id' => $mensaje['correlation_id'], 'error' => 'RabbitMQ no confirmó la publicación'], 503);
        }

        $tiempo = $this->medir($inicio);
        DB::table('notificaciones_citas')->where('message_id', $mensaje['message_id'])->update(['tiempo_respuesta_ms' => $tiempo]);

        return response()->json(['estado' => 'pendiente', 'message_id' => $mensaje['message_id'],
            'correlation_id' => $mensaje['correlation_id'], 'tiempo_respuesta_ms' => $tiempo], 202);
    }

    public function sincrono(Request $request, ProcesadorNotificaciones $procesador): JsonResponse
    {
        $inicio = hrtime(true);
        $mensaje = $this->mensaje($this->validar($request));
        $this->registrar($mensaje, 'sincrono');
        $procesador->procesar($mensaje);
        $tiempo = $this->medir($inicio);
        DB::table('notificaciones_citas')->where('message_id', $mensaje['message_id'])->update(['tiempo_respuesta_ms' => $tiempo]);
        $registro = DB::table('notificaciones_citas')->where('message_id', $mensaje['message_id'])->first();

        return response()->json(['estado' => $registro->estado, 'message_id' => $mensaje['message_id'],
            'correlation_id' => $mensaje['correlation_id'], 'tiempo_respuesta_ms' => $tiempo,
            'tiempo_procesamiento_ms' => (float) $registro->tiempo_procesamiento_ms]);
    }

    public function consultar(string $correlationId): JsonResponse
    {
        if (! Str::isUuid($correlationId)) {
            return response()->json(['error' => 'correlation_id debe ser UUID'], 422);
        }
        $registros = DB::table('notificaciones_citas')->where('correlation_id', $correlationId)
            ->orderBy('id')->get(['message_id', 'correlation_id', 'solicitud_id', 'tipo_evento', 'modo',
                'estado', 'detalle', 'tiempo_respuesta_ms', 'tiempo_procesamiento_ms', 'created_at', 'procesado_en']);
        if ($registros->isEmpty()) {
            return response()->json(['error' => 'No se encontró la correlación'], 404);
        }

        return response()->json(['correlation_id' => $correlationId, 'notificaciones' => $registros]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'solicitud_id' => ['required', 'integer', 'min:1'],
            'estado_solicitud' => ['required', Rule::in(['solicitada', 'confirmada', 'cancelada'])],
            'correlation_id' => ['sometimes', 'uuid'],
        ]);
    }

    private function mensaje(array $datos): array
    {
        return ['message_id' => (string) Str::uuid(),
            'correlation_id' => $datos['correlation_id'] ?? (string) Str::uuid(),
            'tipo' => 'solicitud_cita', 'ocurrido_en' => now()->toIso8601String(),
            'payload' => ['solicitud_id' => (int) $datos['solicitud_id'], 'estado_solicitud' => $datos['estado_solicitud']]];
    }

    private function registrar(array $mensaje, string $modo): void
    {
        DB::table('notificaciones_citas')->insert([
            'message_id' => $mensaje['message_id'], 'correlation_id' => $mensaje['correlation_id'],
            'solicitud_id' => $mensaje['payload']['solicitud_id'], 'tipo_evento' => $mensaje['tipo'],
            'modo' => $modo, 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function medir(int $inicio): float
    {
        return round((hrtime(true) - $inicio) / 1_000_000, 3);
    }
}

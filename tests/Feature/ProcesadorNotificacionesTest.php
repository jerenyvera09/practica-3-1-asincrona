<?php

namespace Tests\Feature;

use App\Services\Citas\ProcesadorNotificaciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ProcesadorNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_procesa_una_vez_y_reconoce_entrega_duplicada(): void
    {
        $mensaje = $this->mensaje();
        $this->registrar($mensaje);
        $procesador = app(ProcesadorNotificaciones::class);

        $this->assertSame('procesada', $procesador->procesar($mensaje));
        $this->assertSame('duplicado', $procesador->procesar($mensaje));
        $this->assertDatabaseHas('notificaciones_citas', ['message_id' => $mensaje['message_id'], 'estado' => 'procesada']);
        $this->assertNotNull(DB::table('notificaciones_citas')->where('message_id', $mensaje['message_id'])->value('procesado_en'));
    }

    public function test_rechaza_mensaje_desconocido_sin_crear_registro(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProcesadorNotificaciones::class)->procesar($this->mensaje());
    }

    public function test_rechaza_correlacion_que_no_coincide(): void
    {
        $mensaje = $this->mensaje();
        $this->registrar($mensaje);
        $mensaje['correlation_id'] = (string) Str::uuid();

        $this->expectException(InvalidArgumentException::class);
        app(ProcesadorNotificaciones::class)->procesar($mensaje);
    }

    public function test_rechaza_identificadores_que_no_son_cadenas(): void
    {
        $mensaje = $this->mensaje();
        $mensaje['message_id'] = ['invalido'];

        $this->expectException(InvalidArgumentException::class);
        app(ProcesadorNotificaciones::class)->procesar($mensaje);
    }

    private function mensaje(): array
    {
        return ['message_id' => (string) Str::uuid(), 'correlation_id' => (string) Str::uuid(),
            'tipo' => 'solicitud_cita', 'ocurrido_en' => now()->toIso8601String(),
            'payload' => ['solicitud_id' => 10, 'estado_solicitud' => 'solicitada']];
    }

    private function registrar(array $mensaje): void
    {
        DB::table('notificaciones_citas')->insert([
            'message_id' => $mensaje['message_id'], 'correlation_id' => $mensaje['correlation_id'],
            'solicitud_id' => 10, 'tipo_evento' => 'solicitud_cita', 'modo' => 'asincrono',
            'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

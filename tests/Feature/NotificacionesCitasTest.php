<?php

namespace Tests\Feature;

use App\Services\Citas\PublicadorNotificaciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class NotificacionesCitasTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicacion_confirmada_devuelve_202_y_permite_consultar_correlacion(): void
    {
        $publicador = new class implements PublicadorNotificaciones
        {
            public array $mensaje = [];

            public function publicar(array $mensaje): void
            {
                $this->mensaje = $mensaje;
            }
        };
        $this->app->instance(PublicadorNotificaciones::class, $publicador);
        $correlationId = (string) Str::uuid();

        $respuesta = $this->postJson('/api/notificaciones-citas', [
            'solicitud_id' => 123,
            'estado_solicitud' => 'solicitada',
            'correlation_id' => $correlationId,
        ]);

        $respuesta->assertStatus(202)->assertJsonPath('correlation_id', $correlationId)
            ->assertJsonPath('estado', 'pendiente');
        $this->assertNotSame($correlationId, $publicador->mensaje['message_id']);
        $this->assertSame(123, $publicador->mensaje['payload']['solicitud_id']);
        $this->assertDatabaseHas('notificaciones_citas', ['message_id' => $publicador->mensaje['message_id'], 'estado' => 'pendiente']);
        $this->getJson('/api/notificaciones-citas/'.$correlationId)
            ->assertOk()->assertJsonPath('notificaciones.0.message_id', $publicador->mensaje['message_id']);
    }

    public function test_datos_invalidos_devuelven_422_sin_publicar(): void
    {
        $this->postJson('/api/notificaciones-citas', ['solicitud_id' => 0, 'estado_solicitud' => 'desconocida'])
            ->assertStatus(422)->assertJsonValidationErrors(['solicitud_id', 'estado_solicitud']);
        $this->assertDatabaseCount('notificaciones_citas', 0);
    }

    public function test_fallo_de_publicacion_no_afirma_procesamiento(): void
    {
        $this->app->instance(PublicadorNotificaciones::class, new class implements PublicadorNotificaciones
        {
            public function publicar(array $mensaje): void
            {
                throw new RuntimeException('broker no disponible');
            }
        });

        $this->postJson('/api/notificaciones-citas', ['solicitud_id' => 9, 'estado_solicitud' => 'solicitada'])
            ->assertStatus(503)->assertJsonPath('estado', 'error_publicacion');
        $this->assertDatabaseHas('notificaciones_citas', ['solicitud_id' => 9, 'estado' => 'error_publicacion']);
    }

    public function test_sincrono_procesa_y_registra_tiempos(): void
    {
        $this->postJson('/api/notificaciones-citas/sincrono', ['solicitud_id' => 8, 'estado_solicitud' => 'confirmada'])
            ->assertOk()->assertJsonPath('estado', 'procesada')->assertJsonStructure(['message_id', 'correlation_id', 'tiempo_respuesta_ms', 'tiempo_procesamiento_ms']);
        $this->assertDatabaseHas('notificaciones_citas', ['solicitud_id' => 8, 'modo' => 'sincrono', 'estado' => 'procesada']);
    }
}

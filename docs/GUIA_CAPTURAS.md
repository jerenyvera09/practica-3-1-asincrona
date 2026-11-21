# Guía de capturas para la práctica 3.1

Ejecute los comandos desde PowerShell en esta carpeta. Capture la salida real con fecha visible si el docente la solicita; no edite estados ni tiempos. Use las instrucciones completas del [README](../README.md).

1. **Infraestructura:** `docker ps --filter name=rabbitmq-practica31` y, si corresponde, interfaz `http://127.0.0.1:15672` con las colas `notificaciones_citas` y `notificaciones_citas_error`.
2. **API y rutas:** terminal con `php artisan serve --host=127.0.0.1 --port=8000` y otra con `php artisan route:list --path=api`.
3. **Tres mensajes válidos:** detenga consumidor con `Ctrl+C`, ejecute el bloque `1..3` del README y capture las tres respuestas **202** con sus `message_id` y `correlation_id`.
4. **Cola acumulada:** antes de reiniciar el consumidor, ejecute `docker exec rabbitmq-practica31 rabbitmqctl list_queues name messages_ready messages_unacknowledged` y capture el conteo observado. Consulte GET para un ID y capture `pendiente`.
5. **Reanudación y correlación:** ejecute `php artisan app:consumir-notificaciones-citas --max-messages=3`; capture las líneas procesadas y vuelva a consultar GET con el mismo `correlation_id`, ahora con estado y `procesado_en` reales. Capture el nuevo conteo de cola.
6. **Validación:** ejecute el bloque 422 del README y capture el código y, si PowerShell lo muestra, el cuerpo de errores.
7. **Comparación:** ejecute ambos POST del README con el mismo payload ficticio; consulte el `correlation_id` asíncrono después de procesarlo. Anote los tiempos devueltos en la tabla inferior.
8. **Pruebas:** `php artisan migrate`, `php artisan route:list --path=api` y `php artisan test`; capture la salida auténtica.

| Modalidad | `solicitud_id` ficticio | `message_id` | `correlation_id` | Respuesta HTTP | `tiempo_respuesta_ms` | `tiempo_procesamiento_ms` | Observación |
|---|---:|---|---|---:|---:|---:|---|
| Síncrona |  |  |  |  |  |  |  |
| Asíncrona |  |  |  |  |  |  |  |

Los tiempos son del servidor, medidos con `hrtime()`. El tiempo de respuesta asíncrono incluye la confirmación de RabbitMQ; el de procesamiento aparece después de ejecutar el consumidor. No se usa latencia simulada.

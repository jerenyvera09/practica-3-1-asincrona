# Práctica individual 3.1: notificaciones de citas médicas

Laravel 13, SQLite y RabbitMQ. El cliente registra una solicitud ficticia; la API guarda `pendiente`, publica un evento persistente en la cola durable `notificaciones_citas` y responde **202** únicamente tras el `publisher confirm`. El consumidor Artisan independiente registra el resultado y hace `ack` manual tras confirmar la escritura en SQLite. No se envían datos personales.

## Preparación (PowerShell, desde la raíz de este proyecto)

```powershell
Copy-Item .env.example .env              # Solo si aún no existe .env
composer install
php artisan key:generate                  # Solo si APP_KEY está vacío
php artisan migrate
docker ps --filter name=rabbitmq-practica31
```

Configure solo `RABBITMQ_*` en `.env` si sus credenciales/puertos difieren de `.env.example`. El usuario `guest` del ejemplo funciona únicamente para acceso local. No copie `.env` al repositorio. La cola principal y `notificaciones_citas_error` se declaran al publicar/consumir; ambas son durables. La principal tiene dead letter hacia la cola de errores.

## Ejecutar

En una terminal:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

En otra terminal, inicie el consumidor y deténgalo con `Ctrl+C`:

```powershell
php artisan app:consumir-notificaciones-citas
```

Para una demostración que termine después de tres entregas:

```powershell
php artisan app:consumir-notificaciones-citas --max-messages=3
```

Con el consumidor detenido, envíe tres solicitudes desde una tercera terminal:

```powershell
$base = 'http://127.0.0.1:8000/api/notificaciones-citas'
1..3 | ForEach-Object { Invoke-RestMethod -Method Post -Uri $base -ContentType 'application/json' -Body (@{ solicitud_id = $_; estado_solicitud = 'solicitada' } | ConvertTo-Json) }
```

Guarde el `correlation_id` de una respuesta y consulte su estado antes y después de arrancar el consumidor:

```powershell
$id = '<correlation_id de la respuesta>'
Invoke-RestMethod -Uri "$base/$id"
```

Prueba de validación 422:

```powershell
try { Invoke-RestMethod -Method Post -Uri $base -ContentType 'application/json' -Body '{"solicitud_id":0,"estado_solicitud":"invalida"}' } catch { $_.Exception.Response.StatusCode.value__ }
```

Colas pendientes (solo inspección; no detiene ni altera contenedores):

```powershell
docker exec rabbitmq-practica31 rabbitmqctl list_queues name messages_ready messages_unacknowledged
```

También puede abrir la interfaz de administración de RabbitMQ en `http://127.0.0.1:15672` y consultar **Queues and Streams**.

## Comparación síncrona y asíncrona

```powershell
$cuerpo = @{ solicitud_id = 101; estado_solicitud = 'confirmada' } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "$base/sincrono" -ContentType 'application/json' -Body $cuerpo
Invoke-RestMethod -Method Post -Uri $base -ContentType 'application/json' -Body $cuerpo
```

Ambos caminos hacen la misma validación y el mismo registro final. El camino síncrono ejecuta el procesamiento antes de responder; el asíncrono espera confirmación de RabbitMQ y el consumidor procesa después. `tiempo_respuesta_ms` mide tiempo del servidor desde el inicio del controlador hasta justo antes de construir la respuesta; `tiempo_procesamiento_ms` mide la operación de actualización en el procesador. No incluyen red del cliente ni esperas simuladas. En este trabajo pequeño, la publicación AMQP puede tardar más que el procesamiento directo: compare resultados medidos, sin prometer una mejora de latencia.

## Fallos y trazabilidad

- `message_id` identifica una entrega y es único en SQLite; `correlation_id` relaciona solicitud, respuesta, mensaje y consulta. Un mismo `correlation_id` puede agrupar varias entregas; GET devuelve la lista.
- Si no se confirma la publicación, API devuelve **503** y el registro queda `error_publicacion`. Un fallo de conexión tras publicar puede dejar confirmación incierta; consulte RabbitMQ y SQLite por `message_id` antes de reintentar manualmente.
- Un mensaje inválido, desconocido o un fallo del procesador se rechaza sin reencolar y queda en `notificaciones_citas_error`. Si se puede identificar un registro pendiente, su estado pasa a `error_mensaje` o `error_procesamiento`. No hay reintentos infinitos. Las entregas duplicadas ya procesadas se reconocen y se confirman sin segunda escritura.
- Si SQLite no puede registrar el rechazo, el mensaje permanece inspeccionable en la cola de errores y el incidente se registra en `storage/logs/laravel.log`.

## Verificación

```powershell
php artisan migrate
php artisan route:list --path=api
php artisan test
vendor\bin\pint --format agent
```

Vea [flujo y arquitectura](docs/flujo-asincrono.md) y [guía de capturas](docs/GUIA_CAPTURAS.md).

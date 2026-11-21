<?php

namespace App\Services\Citas;

interface PublicadorNotificaciones
{
    public function publicar(array $mensaje): void;
}

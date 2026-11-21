<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notificaciones_citas', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->uuid('correlation_id')->index();
            $table->unsignedBigInteger('solicitud_id');
            $table->string('tipo_evento');
            $table->string('modo', 16);
            $table->string('estado', 32);
            $table->text('detalle')->nullable();
            $table->decimal('tiempo_respuesta_ms', 12, 3)->nullable();
            $table->decimal('tiempo_procesamiento_ms', 12, 3)->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones_citas');
    }
};

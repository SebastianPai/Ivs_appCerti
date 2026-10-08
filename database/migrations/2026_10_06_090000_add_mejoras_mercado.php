<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mejoras de octubre 2026: 2FA, auditoría, vigencia de certificados y sincronización sin conexión.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 2FA con app (Google Authenticator, Authy…). Se guardan cifrados (ver casts en User).
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });

        // Registro de auditoría: quién hizo qué, cuándo y desde dónde.
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Sin cascada: si se borra la solicitud, su historial se conserva
            $table->unsignedBigInteger('solicitud_id')->nullable()->index();
            $table->nullableMorphs('auditable');
            $table->string('evento', 40)->index();
            $table->text('descripcion');
            $table->json('cambios')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // Vigencia del certificado y control del aviso de vencimiento
        Schema::table('solicituds', function (Blueprint $table) {
            $table->date('vence_el')->nullable()->index()->after('fecha_aprobacion');
            $table->timestamp('aviso_vencimiento_at')->nullable()->after('vence_el');
        });

        // Los certificados ya emitidos quedan con un año de vigencia desde su aprobación
        DB::table('solicituds')
            ->whereNotNull('fecha_aprobacion')
            ->orderBy('id')
            ->each(function ($s) {
                DB::table('solicituds')->where('id', $s->id)->update([
                    'vence_el' => \Illuminate\Support\Carbon::parse($s->fecha_aprobacion)->addYear()->toDateString(),
                ]);
            });

        // Inspecciones hechas en modo sin conexión
        Schema::table('solicitud_verificaciones', function (Blueprint $table) {
            $table->timestamp('sincronizada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_verificaciones', function (Blueprint $table) {
            $table->dropColumn('sincronizada_en');
        });

        Schema::table('solicituds', function (Blueprint $table) {
            $table->dropIndex(['vence_el']);
            $table->dropColumn(['vence_el', 'aviso_vencimiento_at']);
        });

        Schema::dropIfExists('actividades');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};

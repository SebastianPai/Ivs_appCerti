<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicituds', function (Blueprint $table) {
            // Número de certificado, se asigna al aprobar
            $table->string('codigo')->nullable()->unique()->after('id');
            $table->text('observaciones_revisor')->nullable()->after('observacion_devolucion');
            $table->foreignId('revisor_id')->nullable()->after('observaciones_revisor')->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_aprobacion')->nullable()->after('revisor_id');
            $table->index('estado');
        });

        // id_chip se creó como string sin llave foránea; los valores guardados son ids numéricos de chips.
        DB::table('solicitud_verificaciones')
            ->whereNotNull('id_chip')
            ->whereNotIn('id_chip', DB::table('chips')->select(DB::raw('CAST(id AS CHAR)')))
            ->update(['id_chip' => null]);

        Schema::table('solicitud_verificaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('id_chip')->nullable()->change();
            $table->foreign('id_chip')->references('id')->on('chips')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_verificaciones', function (Blueprint $table) {
            $table->dropForeign(['id_chip']);
            $table->string('id_chip')->nullable()->change();
        });

        Schema::table('solicituds', function (Blueprint $table) {
            $table->dropIndex(['estado']);
            $table->dropConstrainedForeignId('revisor_id');
            $table->dropColumn(['codigo', 'observaciones_revisor', 'fecha_aprobacion']);
        });
    }
};

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Tareas programadas. En el servidor basta UNA tarea cron cada minuto:
 *   * * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
 */

// Procesa la cola (correos) sin necesitar un proceso permanente: sirve en hosting compartido.
// Si el servidor ya corre `php artisan queue:work` con supervisor, esta línea no estorba.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Avisos de certificados próximos a vencer
Schedule::command('ivs:avisar-vencimientos')->dailyAt('07:00')->withoutOverlapping();

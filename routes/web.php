<?php

use App\Http\Controllers\CampoController;
use App\Http\Controllers\CertificadoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// La app vive en el panel de Filament (/ivs). La raíz lleva directo allá.
Route::redirect('/', '/ivs');

// Usado por las pantallas de acceso restringido
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/ivs/login');
})->name('logout');

Route::view('/acceso-restringido', 'errors.restricted')->name('access.restricted');
Route::view('/acceso-restringido-pc', 'errors.restrict_pc')->name('access.restricted_pc');

// Certificado PDF: requiere sesión, permiso sobre la solicitud y estado aprobado (ver CertificadoController)
Route::get('/solicitud/{solicitud}/certificado', CertificadoController::class)
    ->middleware('auth')
    ->name('solicitud.certificado');

// Inspección sin conexión del evaluador (ver CampoController)
Route::middleware(['auth', 'restrict.access'])->prefix('campo')->name('campo.')->group(function () {
    Route::get('/', [CampoController::class, 'index'])->name('index');
    Route::get('/datos', [CampoController::class, 'datos'])->name('datos');
    Route::post('/sincronizar/{solicitud}', [CampoController::class, 'sincronizar'])->whereNumber('solicitud')->name('sincronizar');
});

// Service worker del modo sin conexión: guarda la pantalla /campo para abrirla sin señal.
// Va sin sesión para que el navegador pueda actualizarlo aunque la sesión haya vencido.
Route::get('/campo-sw.js', fn () => response()
    ->view('campo.sw')
    ->header('Content-Type', 'application/javascript; charset=utf-8')
    ->header('Service-Worker-Allowed', '/campo')
    ->header('Cache-Control', 'no-cache'))
    ->name('campo.sw');

// Archivos privados (documentos, fotos). Solo con sesión y con el enlace firmado y temporal
// que genera la app para quien tiene permiso de ver la solicitud (ver App\Support\Archivo).
Route::get('/privado/{path}', function (string $path) {
    $disco = Illuminate\Support\Facades\Storage::disk('local');

    try {
        abort_unless($disco->exists($path), 404);
    } catch (League\Flysystem\PathTraversalDetected) {
        abort(404);
    }

    return $disco->response($path, headers: [
        'Cache-Control' => 'private, max-age=600',
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('path', '.*')->middleware(['auth', 'signed'])->name('archivos.privado');

// Documento de cámara de comercio (solo admin). Los archivos viejos quedaron en el disco privado.
Route::get('/usuarios/{user}/camara-comercio', function (Request $request, App\Models\User $user) {
    abort_unless($request->user()?->hasRole('admin'), 403);
    abort_unless($user->camara_comercio, 404);

    foreach (['public', 'local'] as $disco) {
        if (Illuminate\Support\Facades\Storage::disk($disco)->exists($user->camara_comercio)) {
            return Illuminate\Support\Facades\Storage::disk($disco)->response($user->camara_comercio);
        }
    }

    abort(404);
})->middleware('auth')->name('usuarios.camara');

<?php

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

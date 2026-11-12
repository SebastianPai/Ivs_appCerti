<?php

namespace App\Http\Controllers;

use App\Support\Correo;
use App\Support\TextosCorreo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Vista previa de los correos para el admin (Configuración → Textos de los correos → «Ver ejemplos»).
 * Usa datos de ejemplo y los textos guardados en Configuración. No envía nada.
 */
class VistaPreviaCorreosController extends Controller
{
    /** Vista de cada tipo de correo del flujo y los datos de ejemplo con que se muestra. */
    private const FLUJO = [
        'solicitud_creada' => ['emails.solicitud_iniciada', 'Solicitud iniciada - Placa: {placa}'],
        'documentos' => ['emails.confirmacion_carga', '✅ Documentación recibida - Placa: {placa}'],
        'devolucion' => ['emails.solicitud_devuelta', '⚠️ Solicitud devuelta - Placa: {placa}'],
        'revision' => ['emails.revision_pendiente', '📋 Nueva revisión pendiente - Placa: {placa}'],
        'aprobacion' => ['emails.certificado', 'Vehículo Certificado - Placa: {placa}'],
        'inspector' => ['emails.inspector', 'Notificación Nuevo Inspector'],
        'vencimiento' => ['emails.notificacion', 'Su certificado está por vencer - Placa: {placa}'],
    ];

    /** Qué hace cada correo y a quién le llega (vista previa y correos de prueba). */
    public const DESCRIPCIONES = [
        'cuenta_bienvenida' => 'Al crear un usuario: le da la bienvenida y le pide confirmar el correo para poder entrar.',
        'cuenta_password' => 'Cuando alguien pulsa «¿Olvidaste tu contraseña?»: trae el enlace para crear una nueva.',
        'cuenta_cambio_correo' => 'Al cambiar el correo de una cuenta: llega al correo nuevo para confirmarlo.',
        'cuenta_aviso_cambio' => 'Al cambiar el correo de una cuenta: llega al correo anterior con un botón para bloquear el cambio.',
        'solicitud_creada' => 'El taller crea una solicitud: al taller le confirma y le pide cargar documentos (los evaluadores reciben «Nueva asignación»).',
        'documentos' => 'El taller carga o corrige los documentos: le confirma al taller (el evaluador recibe «Documentación lista»).',
        'devolucion' => 'El evaluador devuelve la solicitud al taller con el motivo, para que la corrija.',
        'revision' => 'El evaluador termina la inspección: avisa al revisor que tiene una evaluación por aprobar.',
        'aprobacion' => 'El revisor aprueba: llega al taller y al propietario con el certificado PDF adjunto.',
        'inspector' => 'El admin asigna o quita un evaluador a un taller: le avisa al taller.',
        'vencimiento' => 'El certificado está por vencer: avisa al taller y al propietario.',
    ];

    /** Correos extra del flujo que usan otra plantilla (los recibe el evaluador). */
    public const EXTRA = [
        'evaluador_nueva' => ['Nueva solicitud (al evaluador)', 'El taller crea una solicitud: avisa a sus evaluadores asignados.'],
        'evaluador_documentos' => ['Documentos listos (al evaluador)', 'El taller cargó o corrigió documentos: avisa al evaluador que la tiene.'],
        'evaluador_correccion' => ['Corrección técnica (al evaluador)', 'El revisor devuelve la evaluación al evaluador con una observación.'],
    ];

    /** @return array<string, array{0: string, 1: string}> tipo => [nombre, descripción], en el orden del flujo */
    public static function catalogo(): array
    {
        $nombres = [...collect(TextosCorreo::CUENTA)->map(fn ($t) => $t['nombre'])->all(), ...Correo::TIPOS];

        return [
            ...collect($nombres)->map(fn ($nombre, $tipo) => [$nombre, self::DESCRIPCIONES[$tipo] ?? ''])->all(),
            ...self::EXTRA,
        ];
    }

    public function __invoke(Request $request)
    {
        abort_unless((bool) Auth::user()?->hasRole('admin'), 403);

        $tipo = (string) $request->query('tipo', '');

        if ($tipo === '') {
            return response()->view('emails.vista-previa', [
                'tipos' => collect(self::catalogo())->map(fn ($c) => $c[0])->all(),
                'descripciones' => collect(self::catalogo())->map(fn ($c) => $c[1])->all(),
            ]);
        }

        return response(self::html($tipo));
    }

    public static function html(string $tipo): string
    {
        $ejemplo = [
            'placa' => 'ABC123', 'detalle' => 'Revisión periódica', 'saludo' => 'Taller Ejemplo', 'user_name' => 'Taller Ejemplo',
            'inspector' => 'Evaluador Ejemplo', 'asignado' => true, 'evaluador_name' => 'Evaluador Ejemplo', 'cliente_name' => 'Taller Ejemplo',
            'url_gestion' => url('/ivs'), 'url_solicitud' => url('/ivs'), 'url_adjuntos' => url('/ivs'), 'boton_url' => url('/ivs'),
            'observacion' => 'Falta la foto del cilindro con la placa visible.', 'titulo' => 'Su certificado está por vencer',
            'cuerpo' => 'El certificado del vehículo vence pronto. Programe la revisión periódica a tiempo.', 'boton_texto' => 'Ir a la plataforma',
        ];

        if (isset(TextosCorreo::CUENTA[$tipo])) {
            $usuario = new \App\Models\User(['name' => 'Usuario Ejemplo', 'email' => 'usuario@ejemplo.com']);
            $aviso = match ($tipo) {
                'cuenta_bienvenida' => tap(new \App\Notifications\Cuenta\BienvenidaConfirmarCorreo, fn ($n) => $n->url = url('/ivs')),
                'cuenta_password' => tap(new \App\Notifications\Cuenta\RestablecerContrasena('token'), fn ($n) => $n->url = url('/ivs')),
                'cuenta_cambio_correo' => tap(new \App\Notifications\Cuenta\ConfirmarCambioCorreo, fn ($n) => $n->url = url('/ivs')),
                'cuenta_aviso_cambio' => new \App\Notifications\Cuenta\AvisoCambioCorreo('nuevo@ejemplo.com', url('/ivs')),
            };

            return (string) $aviso->toMail($usuario)->render();
        }

        $extra = [
            'evaluador_nueva' => ['emails.nueva_solicitud_evaluador', 'solicitud_creada'],
            'evaluador_documentos' => ['emails.documentos_cargados_evaluador', 'documentos'],
            'evaluador_correccion' => ['emails.notificacion', 'devolucion'],
        ];

        if (isset($extra[$tipo])) {
            [$vista, $tipoTextos] = $extra[$tipo];
            $ejemplo = [...$ejemplo, 'saludo' => 'Evaluador Ejemplo', 'titulo' => 'Corrección técnica solicitada',
                'cuerpo' => 'El revisor devolvió la evaluación con la siguiente observación:', 'detalle' => 'Revise la medición del cilindro.',
                'boton_texto' => 'Corregir evaluación'];
            $ejemplo['mensaje_admin'] = TextosCorreo::mensaje($tipoTextos, $ejemplo);
            $ejemplo['pie_correo'] = TextosCorreo::pie();

            return view($vista, $ejemplo)->render();
        }

        abort_unless(isset(self::FLUJO[$tipo]), 404);

        [$vista] = self::FLUJO[$tipo];
        $ejemplo['mensaje_admin'] = TextosCorreo::mensaje($tipo, $ejemplo);
        $ejemplo['pie_correo'] = TextosCorreo::pie();

        return view($vista, $ejemplo)->render();
    }

    /**
     * Envía un correo de prueba de cada tipo a $email (de inmediato, sin cola ni límite), con el número,
     * el nombre y lo que hace en el asunto. Devuelve cuántos salieron.
     */
    public static function enviarPruebas(string $email): int
    {
        $n = 0;

        foreach (self::catalogo() as $tipo => [$nombre, $descripcion]) {
            $n++;
            $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:16px auto 0;padding:12px 16px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#431407;font-size:13px;">'
                .'<b>Correo de prueba '.$n.' de '.count(self::catalogo()).' · '.e($nombre).'</b><br>'.e($descripcion)
                .'<br><span style="color:#9a3412;">Asunto real: '.e(self::asunto($tipo)).'</span></div>'
                .self::html($tipo);

            \Illuminate\Support\Facades\Mail::html($html, fn ($m) => $m->to($email)->subject(sprintf('[Prueba IVS %02d] %s', $n, $nombre)));
        }

        return $n;
    }

    /** Asunto con el que saldría (para mostrarlo sobre la vista previa). */
    public static function asunto(string $tipo): string
    {
        $datos = ['placa' => 'ABC123', 'nombre' => 'Usuario Ejemplo'];

        if (isset(self::EXTRA[$tipo])) {
            return self::EXTRA[$tipo][0];
        }

        return isset(TextosCorreo::CUENTA[$tipo])
            ? TextosCorreo::asunto($tipo, TextosCorreo::CUENTA[$tipo]['asunto'], $datos)
            : TextosCorreo::asunto($tipo, str_replace('{placa}', 'ABC123', self::FLUJO[$tipo][1] ?? $tipo), $datos);
    }
}

# IVS Certificaciones

Plataforma para certificar conversiones de vehículos a gas (GNV/GLP): el taller registra el vehículo y sus equipos, un evaluador inspecciona en sitio (con GPS y lectura del chip), un revisor audita y aprueba, y el sistema emite el certificado en PDF.

Stack: **Laravel 12 + Filament 4** (un único panel en `/ivs`), MySQL, Spatie Permission para roles, DomPDF para el certificado.

## Roles y flujo

| Rol | Qué hace | Menú |
|---|---|---|
| `cliente` (taller) | Crea solicitudes, carga documentos, corrige lo devuelto, descarga el certificado | Taller → Mis solicitudes |
| `evaluador` | Inspecciona en el taller: filtro de seguridad → chip y fotos → checklist NTC | Evaluación → Solicitudes por evaluar |
| `revisor` | Audita el expediente y aprueba o devuelve al evaluador | Revisión → Auditoría final |
| `admin` | Usuarios, asignaciones, catálogos y configuración | Administración |

Estados de una solicitud (`app/Enums/EstadoSolicitud.php`):

```
pendiente ──(evaluador devuelve)──► devuelta_taller ──(taller corrige)──► subsanada
    │                                                                         │
    └──────────────(evaluador envía checklist)◄───────────────────────────────┘
                                │
                            evaluada ──(revisor devuelve)──► correccion_tecnica ──► evaluada
                                │
                     (revisor aprueba) ──► aprobada  → certificado IVS-AAAA-000123
```

Pasos del evaluador (cada uno exige el anterior, no se pueden saltar por URL):

1. **Filtro de seguridad** (`VerificacionPrevia`): declaración de no conflicto de interés + captura GPS.
2. **Chip y fotos** (`Evaluador\...\ViewSolicitud`): lectura del chip, revisión de documentos del taller, 4 fotos obligatorias. Desde aquí también se puede devolver al taller.
3. **Checklist técnico** (`EvaluacionChecklist`): preguntas definidas en `app/Support/ChecklistTecnico.php` (la misma fuente la usa el revisor).

### Asignaciones (Administración → Usuarios → menú ⋮ de cada fila)

- **Asignar evaluadores** a un taller: el evaluador solo ve las solicitudes de sus talleres.
- **Asignar revisores** a un evaluador: el revisor ve lo de sus evaluadores y lo de evaluadores que no tienen revisor asignado.

## Lectura del chip

- **Celular Android (Chrome):** botón «Leer con NFC del celular». Requiere que el sitio abra con **https://**. Lee el registro de texto del chip; si no tiene, usa el número de serie (UID).
- **PC con lector USB/Bluetooth tipo teclado:** botón «Usar lector USB» y acercar el chip; el lector escribe el código en el campo.
- **iPhone:** Safari no soporta Web NFC; usar lector externo o digitar el código.

El código debe existir en la tabla `chips` y estar activo. Un chip no puede usarse en dos vehículos distintos. Que sea obligatorio se configura en Administración → Configuración.

## Configuración (Administración → Configuración)

- Chip obligatorio sí/no.
- Acceso por rol: celular y PC / solo celular / sin acceso. La detección de celular se basa en el User-Agent (política de uso, no seguridad fuerte). Los admin nunca se restringen.

## Instalación local

```bash
composer install
npm install && npm run build      # compila el tema de Filament (obligatorio)
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan db:seed               # roles, catálogos, configuración y usuarios de prueba
php artisan storage:link
php artisan serve                 # http://localhost:8000/ivs
```

Usuarios de prueba (solo fuera de producción, contraseña `password`): `admin@admin.com`, `cliente@cliente.com`, `evaluador@ivs.com`, `revisor@ivs.com`.

El catálogo de marcas/modelos de la API de NHTSA es opcional y muy lento: `php artisan db:seed --class=VehicleCatalogSeeder`. Si falta una línea de vehículo, el taller la puede crear desde el formulario (botón +).

## Rendimiento

- PHP debe tener **OPcache** activo, también para consola si se usa `php artisan serve`: en `php.ini` → `zend_extension=opcache`, `opcache.enable=1`, `opcache.enable_cli=1`. Sin esto cada página tarda varios segundos.
- `php artisan filament:optimize` cachea íconos y componentes de Filament (en Windows ahorra ~3 s por petición). Si se agregan recursos o páginas nuevas de Filament y no aparecen, correr `php artisan filament:optimize-clear` y volver a optimizar.

## Producción (checklist)

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con **https** (necesario para GPS y NFC).
- Crear el admin con `php artisan make:filament-user` y asignarle el rol `admin` (el seeder de usuarios no corre en producción).
- Configurar SMTP (`MAIL_*`). Si un correo falla, queda registrado en `storage/logs/laravel.log` y el flujo continúa.
- `npm run build` y `php artisan optimize`.
- **No subir** `.env` ni `storage/app/key/*.json` (credenciales).

## Pruebas

```bash
php artisan test
```

Cubren permisos por rol, acceso por URL a registros ajenos, protección del certificado, validaciones del formulario, el chip y el flujo completo taller → evaluador → revisor → certificado.

## Mapa del código

```
app/Enums/EstadoSolicitud.php          estados, etiquetas y colores
app/Support/ChecklistTecnico.php       preguntas NTC del checklist
app/Services/ChipValidationService.php validación del chip
app/Services/ColombiaGeo.php           departamentos/ciudades (api-colombia.com, cacheado)
app/Filament/Resources/Solicituds/     módulo del taller
app/Filament/Resources/Evaluador/      módulo del evaluador
app/Filament/Resources/Revisor/        módulo del revisor
app/Filament/Pages/Admin/SystemSettings.php  configuración
app/Http/Controllers/CertificadoController.php  PDF (resources/views/pdf/certificado.blade.php)
resources/css/filament/admin/theme.css estilos propios (recompilar con npm run build)
```

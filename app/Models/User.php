<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\Auditable;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Models\Solicitud;

/**
 * @method \Illuminate\Database\Eloquent\Relations\BelongsToMany clientesAsignados()
 * @method \Illuminate\Database\Eloquent\Relations\BelongsToMany evaluadores()
 */

/**
 * @method bool hasRole(string|array $roles)
 * @method bool hasAnyRole(string|array $roles)
 * @method bool hasAllRoles(string|array $roles)
 * @method bool can(string $permission)
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $department
 * @property string|null $city
 * @property int $is_taller
 * @property string|null $camara_comercio
 * @property string|null $fecha_radicado
 * @property string|null $fecha_vencimiento
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $clientesAsignados
 * @property-read int|null $clientes_asignados_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $evaluadores
 * @property-read int|null $evaluadores_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $revisores
 * @property-read int|null $revisores_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SolicitudVerificacion> $verificacionesComoEvaluador
 * @property-read int|null $verificaciones_como_evaluador_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCamaraComercio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDepartment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFechaRadicado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFechaVencimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsTaller($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Auditable, HasFactory, Notifiable, HasRoles;

    protected function describirAuditoria(string $evento, array $cambios): ?string
    {
        return match ($evento) {
            'creado' => "Usuario {$this->name} ({$this->email}) creado",
            'eliminado' => "Usuario {$this->name} ({$this->email}) eliminado",
            default => "Usuario {$this->name} modificado: ".implode(', ', array_keys($cambios)),
        };
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department',
        'city',
        'direccion',
        'is_taller',
        'nit',
        'camara_comercio', 
        'fecha_radicado', 
        'fecha_vencimiento',
        'activo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected static function booted(): void
    {
        // El registro de Industria y Comercio también queda copiado en Firebase
        static::saved(fn (self $user) => $user->wasChanged("camara_comercio") ? \App\Support\Nube::copiarDespues($user->camara_comercio) : null);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fecha_radicado' => 'date',
            'fecha_vencimiento' => 'date',
            'activo' => 'boolean',
            'debe_cambiar_password' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    // -------- Verificación en dos pasos (app autenticadora) --------

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function tiene2fa(): bool
    {
        return filled($this->app_authentication_secret);
    }

    /**
     * Requerido por Filament en producción: sin esto, nadie puede entrar al panel
     * cuando APP_ENV != local. Solo usuarios con algún rol del sistema.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Una cuenta «sin acceso» conserva su rol y su historial, pero no entra
        return $this->activo !== false && $this->hasAnyRole(['admin', 'cliente', 'evaluador', 'revisor', 'externo']);
    }

    /** Nombre de cada rol como aparece en la plataforma anterior. */
    public const ROLES = [
        'admin' => 'Admin',
        'cliente' => 'Cliente',
        'evaluador' => 'Inspector',
        'revisor' => 'Revisor',
        'externo' => 'Externo Exportado',
    ];

    // -------- Servicios que el taller puede pedir --------

    public function servicios()
    {
        return $this->belongsToMany(ServiceType::class, 'taller_servicio', 'user_id', 'service_type_id')->withTimestamps();
    }

    /**
     * Tipos de servicio que puede pedir este taller: los activos que el admin le asignó
     * («Asignación de servicios»). Con la regla apagada en Configuración, todos los activos.
     */
    public function serviciosDisponibles()
    {
        return ServiceType::query()
            ->where('activo', true)
            ->when(
                SystemSetting::enabled('servicios_por_taller'),
                fn ($q) => $q->whereIn('id', $this->servicios()->select('service_types.id')),
            )
            // Mismo orden del catálogo de la plataforma anterior: INICIAL, REVISION ANUAL, CAMBIO DE CHIP…
            ->orderBy('id');
    }

    // -------- Registro de Industria y Comercio del taller --------

    /** Todo cliente es un taller: no hay clientes de otro tipo (se confirmó con la plataforma anterior). */
    public function esTaller(): bool
    {
        return $this->hasRole('cliente');
    }

    /** Correo de confirmación con el enlace del panel. Lo reenvía el admin desde Administrar Usuarios. */
    public function enviarConfirmacionDeCorreo(): void
    {
        $aviso = app(\Filament\Auth\Notifications\VerifyEmail::class);
        $aviso->url = \Filament\Facades\Filament::getVerifyEmailUrl($this);

        $this->notify($aviso);
    }

    /** Vigente = con fecha de vencimiento que no ha pasado. Sin fecha cuenta como vencido. */
    public function registroVigente(): bool
    {
        return $this->fecha_vencimiento !== null && ! $this->fecha_vencimiento->endOfDay()->isPast();
    }

    /** Como en la plataforma anterior: con el registro vencido el taller no entra (se puede apagar en Configuración). */
    public function debeActualizarRegistro(): bool
    {
        return $this->esTaller() && ! $this->registroVigente() && SystemSetting::enabled('registro_taller_required');
    }

    /** Talleres que hoy no pueden entrar por su registro: sin fecha o vencido. */
    public function scopeConRegistroPendiente($query)
    {
        return $query
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'cliente'))
            ->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '<', now()->toDateString()));
    }

    // Evaluador → clientes (talleres)
    public function clientesAsignados()
    {
        return $this->belongsToMany(
            User::class,
            'evaluador_cliente',
            'evaluador_id',
            'cliente_id'
        )->withTimestamps();
    }

    // Cliente → evaluadores
    public function evaluadores()
    {
        return $this->belongsToMany(
            User::class,
            'evaluador_cliente',
            'cliente_id',
            'evaluador_id'
        )->withTimestamps();
    }

    public function inventario()
    {
        return $this->hasOne(InventarioUsuario::class);
    }

    /**
     * Talleres cuyos datos puede exportar un Externo Exportado, como en la plataforma anterior.
     * Usa la misma tabla que la asignación de talleres del evaluador.
     */
    public function talleresExportables()
    {
        return $this->clientesAsignados();
    }

    // Evaluador → revisores que auditan su trabajo
    /** Usuarios que pueden recibir avisos: los que no están desactivados (activo en null cuenta como activo). */
    public function scopeActivos($query)
    {
        return $query->where(fn ($q) => $q->where('activo', true)->orWhereNull('activo'));
    }

    public function revisores()
    {
        return $this->belongsToMany(User::class, 'evaluador_revisor', 'evaluador_id', 'revisor_id')->withTimestamps();
    }

    // Revisor → evaluadores que supervisa
    public function evaluadoresSupervisados()
    {
        return $this->belongsToMany(User::class, 'evaluador_revisor', 'revisor_id', 'evaluador_id')->withTimestamps();
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class);
    }

    public function verificacionesComoEvaluador()
    {
        return $this->hasMany(SolicitudVerificacion::class, 'evaluador_id');
    }
}

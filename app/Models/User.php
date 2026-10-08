<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
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
        'is_taller', 
        'camara_comercio', 
        'fecha_radicado', 
        'fecha_vencimiento',
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
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fecha_radicado' => 'date',
            'fecha_vencimiento' => 'date',
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
        return $this->hasAnyRole(['admin', 'cliente', 'evaluador', 'revisor']);
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

    // Evaluador → revisores que auditan su trabajo
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

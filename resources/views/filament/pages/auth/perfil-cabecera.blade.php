{{-- Cabecera del perfil: avatar, nombre, correo y rol del usuario que entró. --}}
@php
    $usuario = auth()->user();
    $roles = [
        'admin' => 'Administrador', 'cliente' => 'Taller', 'evaluador' => 'Evaluador',
        'revisor' => 'Revisor', 'externo' => 'Externo',
    ];
    $rol = $usuario?->getRoleNames()->map(fn ($r) => $roles[$r] ?? ucfirst($r))->implode(' · ');
@endphp
<div class="flex items-center gap-4 rounded-2xl bg-primary-50 p-4 ring-1 ring-primary-100 dark:bg-primary-500/10 dark:ring-primary-500/20">
    <x-filament-panels::avatar.user :user="$usuario" class="h-14 w-14 shrink-0" />
    <div class="min-w-0">
        <p class="truncate text-lg font-semibold text-gray-900 dark:text-white">{{ $usuario?->name }}</p>
        <p class="truncate text-sm text-gray-600 dark:text-gray-300">{{ $usuario?->email }}</p>
        @if (filled($rol))
            <span class="mt-1 inline-block rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-primary-200 dark:bg-white/5 dark:text-primary-300 dark:ring-primary-500/30">{{ $rol }}</span>
        @endif
    </div>
</div>

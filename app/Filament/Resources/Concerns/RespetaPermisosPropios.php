<?php

namespace App\Filament\Resources\Concerns;

use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Las pantallas de Filament (lista, crear, ver, editar) autorizan con las «respuestas de
 * autorización», que por defecto solo miran las policies. Este proyecto escribe sus permisos en
 * los métodos canViewAny(), canEdit(), etc. de cada recurso; sin este trait una versión nueva de
 * Filament dejaba abrir por URL pantallas que esos métodos prohíben.
 *
 * Si el recurso no redefine un método can*, se conserva el comportamiento de Filament.
 */
trait RespetaPermisosPropios
{
    private static function propio(string $metodo): bool
    {
        return (new \ReflectionMethod(static::class, $metodo))->getDeclaringClass()->getName() !== Resource::class;
    }

    private static function respuesta(bool $permitido): Response
    {
        return $permitido ? Response::allow() : Response::deny();
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return static::propio('canViewAny') ? static::respuesta(static::canViewAny()) : parent::getViewAnyAuthorizationResponse();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::propio('canCreate') ? static::respuesta(static::canCreate()) : parent::getCreateAuthorizationResponse();
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return static::propio('canView') ? static::respuesta(static::canView($record)) : parent::getViewAuthorizationResponse($record);
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::propio('canEdit') ? static::respuesta(static::canEdit($record)) : parent::getEditAuthorizationResponse($record);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::propio('canDelete') ? static::respuesta(static::canDelete($record)) : parent::getDeleteAuthorizationResponse($record);
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return static::propio('canDeleteAny') ? static::respuesta(static::canDeleteAny()) : parent::getDeleteAnyAuthorizationResponse();
    }
}

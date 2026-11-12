<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar con las iniciales del usuario, generado aquí mismo (SVG en línea).
 * Reemplaza al de Filament, que pedía la imagen a ui-avatars.com enviándole el nombre del usuario.
 */
class Iniciales implements AvatarProvider
{
    public function get(Model $record): string
    {
        $nombre = trim((string) Filament::getNameForDefaultAvatar($record));
        $iniciales = collect(preg_split('/\s+/u', $nombre, -1, PREG_SPLIT_NO_EMPTY))
            ->take(2)
            ->map(fn (string $palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
            ->implode('') ?: '?';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#a5484e"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff" font-family="Segoe UI,Roboto,Arial,sans-serif" font-size="26" font-weight="600">'
            .htmlspecialchars($iniciales, ENT_QUOTES | ENT_XML1)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

{{-- Galería de documentos/fotos. $items: colección de ['titulo' => string, 'ruta' => ?string] --}}
@if ($items->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay archivos.</p>
@else
    <div class="ivs-galeria">
        @foreach ($items as $item)
            @php
                $url = \App\Filament\Resources\Revisor\Schemas\SolicitudRevisorInfolist::url($item['ruta']);
                $ext = strtolower(pathinfo((string) $item['ruta'], PATHINFO_EXTENSION));
                $esImagen = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
            @endphp
            <figure class="ivs-galeria__item">
                @if (! $url)
                    <div class="ivs-galeria__vacio">
                        <x-filament::icon icon="heroicon-o-x-circle" class="h-8 w-8 text-danger-500" />
                        <span>Sin archivo</span>
                    </div>
                @elseif ($esImagen)
                    <a href="{{ $url }}" target="_blank" rel="noopener">
                        <img src="{{ $url }}" alt="{{ $item['titulo'] }}" loading="lazy" class="ivs-galeria__img">
                    </a>
                @else
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="ivs-galeria__vacio">
                        <x-filament::icon icon="heroicon-o-document-text" class="h-8 w-8 text-primary-500" />
                        <span>Abrir .{{ $ext }}</span>
                    </a>
                @endif
                <figcaption class="ivs-galeria__titulo">{{ $item['titulo'] }}</figcaption>
            </figure>
        @endforeach
    </div>
@endif

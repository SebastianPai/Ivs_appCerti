<?php

namespace App\Filament\Resources\Solicituds\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class AttachmentForm
{
    /**
     * Documentos que todo taller debe cargar. El nombre es la clave guardada en
     * solicitud_adjuntos.nombre_adjunto (se conserva el formato histórico con " *").
     */
    public const DOCUMENTOS = [
        'Adjunto Preconversión *' => true,
        'Adjunto Preconversión Opcional' => false,
        'Adjunto Posconversión *' => true,
        'Adjunto Posconversión Opcional' => false,
        'Adjunto Certificado conformidad *' => true,
        'Adjunto Acta desmonte *' => true,
        'Adjunto Prueba hidrostática *' => true,
    ];

    /** Nombre del campo del documento fijo número $i (doc_0, doc_1, ...). */
    public static function campo(int $i): string
    {
        return "doc_{$i}";
    }

    public static function etiqueta(string $nombre): string
    {
        return trim(str_replace(['Adjunto ', ' *', ' Opcional'], ['', '', ' (opcional)'], $nombre));
    }

    /** Configuración común para subir archivos desde PC o celular. */
    public static function archivo(string $nombre, string $directorio): FileUpload
    {
        return FileUpload::make($nombre)
            ->disk('public')
            ->directory($directorio)
            ->visibility('public')
            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic'])
            ->maxSize(10240) // 10 MB
            // Fotos del celular: se reducen antes de subir (ahorra datos en campo)
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth('1920')
            ->imageResizeTargetHeight('1920')
            ->imageResizeUpscale(false)
            ->openable()
            ->downloadable()
            ->helperText('PDF o foto (JPG/PNG), máximo 10 MB.');
    }

    public static function components(int $solicitudId): array
    {
        $directorio = "solicitudes/{$solicitudId}/adjuntos";

        $fijos = [];
        foreach (array_keys(self::DOCUMENTOS) as $i => $nombre) {
            // Ojo: el nombre no puede llevar punto ("fijos.0"): Filament valida tipo/tamaño con ese
            // nombre y un punto lo vuelve una ruta anidada, con lo que la validación no se aplicaba.
            $fijos[] = self::archivo(self::campo($i), $directorio)
                ->label(self::etiqueta($nombre))
                ->required(self::DOCUMENTOS[$nombre]);
        }

        return [
            Section::make('Documentos requeridos')
                ->description('Los marcados con * son obligatorios.')
                ->icon('heroicon-o-document-check')
                ->schema([Grid::make(['default' => 1, 'md' => 2])->schema($fijos)])
                ->columnSpanFull(),

            Section::make('Documentos adicionales')
                ->description('Opcional: cualquier otro soporte que quiera adjuntar.')
                ->icon('heroicon-o-paper-clip')
                ->collapsible()
                ->schema([
                    Repeater::make('adicionales')
                        ->hiddenLabel()
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('nombre_adjunto')
                                    ->label('Nombre del documento')
                                    ->required()
                                    ->maxLength(120)
                                    ->notIn(array_keys(self::DOCUMENTOS))
                                    ->distinct(),
                                self::archivo('ruta_archivo', $directorio)
                                    ->label('Archivo')
                                    ->required(),
                            ]),
                        ])
                        ->itemLabel(fn (array $state) => $state['nombre_adjunto'] ?? 'Nuevo documento')
                        ->addActionLabel('Agregar documento')
                        ->defaultItems(0)
                        ->collapsible(),
                ])
                ->columnSpanFull(),
        ];
    }
}

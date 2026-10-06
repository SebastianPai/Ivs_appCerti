<?php

namespace App\Support;

/**
 * Única fuente de verdad del checklist técnico (NTC 4821 / NTC 5212-1 / Res. 0957).
 * La usan el evaluador (para diligenciar) y el revisor (para auditar), así nunca
 * se desincronizan las preguntas entre ambos.
 *
 * Cada ítem: id (clave en datos_checklist), numeral, texto, medicion (pide valor en cm), minimo (cm).
 */
class ChecklistTecnico
{
    public static function secciones(): array
    {
        return [
            'documentacion' => [
                'titulo' => 'Documentación',
                'descripcion' => 'Trazabilidad',
                'icono' => 'heroicon-o-document-magnifying-glass',
                'columna' => 'Normativa / Documento',
                'items' => [
                    ['id' => 'doc_0957', 'numeral' => 'Resolución 0957 Anexo 1', 'texto' => 'Formato de preconversión y postconversión diligenciados.'],
                    ['id' => 'doc_acta', 'numeral' => 'Reglamento Interno', 'texto' => 'Acta de monte y desmonte de cilindros (según aplique).'],
                    ['id' => 'doc_ensayo', 'numeral' => 'Reglamento Interno', 'texto' => 'Registro de ensayo de cilindros (según aplique).'],
                ],
            ],
            'tuberias' => [
                'titulo' => 'Tuberías',
                'descripcion' => 'Verificación a 200 bar con solución jabonosa.',
                'icono' => 'heroicon-o-wrench-screwdriver',
                'columna' => 'Numeral NTC / Requisito',
                'items' => [
                    ['id' => 'code_4_5', 'numeral' => 'NTC 5212-1 Numeral 4.5', 'texto' => 'Distancia mínima al sistema de escape (o uso de pantallas de protección).', 'medicion' => true, 'minimo' => 10],
                    ['id' => 'code_5_2_7', 'numeral' => 'NTC 4821 Numeral 5.2.7', 'texto' => 'Distancia mínima de líneas de suministro a terminales de batería.', 'medicion' => true, 'minimo' => 20],
                    ['id' => 'code_4_1_2_5', 'numeral' => 'NTC 5212-1 Numeral 4.1.2.5', 'texto' => 'Instalación en chasis: sin daños por vibración e intervalos de fijación máx. 1 m.', 'medicion' => true],
                    ['id' => 'code_5_2_11', 'numeral' => 'NTC 4821 Numeral 5.2.11', 'texto' => 'Integridad de líneas: rígidas/flexibles dañadas deben reemplazarse (no reparar).'],
                ],
            ],
            'regulador' => [
                'titulo' => 'Regulador',
                'descripcion' => 'Montaje',
                'icono' => 'heroicon-o-cog',
                'columna' => 'Numeral NTC / Ubicación',
                'items' => [
                    ['id' => 'ntc_5_3_10', 'numeral' => 'NTC 4821 Numeral 5.3.10', 'texto' => 'El regulador de última etapa debe estar cerca al mezclador (mangueras lo más cortas posible).'],
                ],
            ],
            'valvulas' => [
                'titulo' => 'Válvulas',
                'descripcion' => 'Cierre y llenado',
                'icono' => 'heroicon-o-adjustments-vertical',
                'columna' => 'Numeral NTC / Componente',
                'items' => [
                    ['id' => 'ntc_5_4_3', 'numeral' => 'NTC 4821 Numeral 5.4.3', 'texto' => 'Válvula manual de cierre para aislar cilindros.'],
                    ['id' => 'ntc_5_4_4', 'numeral' => 'NTC 4821 Numeral 5.4.4', 'texto' => '(Bicombustible) Electroválvulas de corte para cada combustible, firmemente acopladas.'],
                    ['id' => 'ntc_5_4_5', 'numeral' => 'NTC 4821 Numeral 5.4.5', 'texto' => '(Carburador) Electroválvula de gasolina entre bomba y carburador.'],
                    ['id' => 'ntc_4_1_2_1', 'numeral' => 'NTC 5212-1 Numeral 4.1.2.1', 'texto' => 'El receptáculo debe tener tapa contra polvo y fluidos.'],
                    ['id' => 'ntc_4_2_2', 'numeral' => 'NTC 5212-1 Numeral 4.2.2', 'texto' => 'Receptáculo en lugar de fácil acceso (preferiblemente lateral).'],
                ],
            ],
        ];
    }

    public const UBICACIONES_CILINDRO = [
        'chasis_ejes' => 'Debajo del chasis entre ejes',
        'chasis_prolongacion' => 'Delante/atrás de los ejes',
        'exterior' => 'Exterior (Platón/Estacas)',
        'baul' => 'Compartimiento de carga/maletero',
        'pasajeros' => 'Compartimiento de pasajero',
        'techo' => 'Techo del vehículo',
    ];

    /** Ítems marcados como "No cumple" (para alertar al evaluador y al revisor). */
    public static function noConformidades(array $datos): array
    {
        $nc = [];

        foreach (self::secciones() as $seccion) {
            foreach ($seccion['items'] as $item) {
                if (($datos[$item['id']] ?? null) === 'nc') {
                    $nc[] = $item['numeral'].' — '.$item['texto'];
                }
            }
        }

        return $nc;
    }
}

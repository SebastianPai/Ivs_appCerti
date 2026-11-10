<?php

namespace Database\Seeders;

use App\Models\CylinderBrand;
use Illuminate\Database\Seeder;

/** Marcas de cilindro de la plataforma anterior con su código SICOM (sin los registros de prueba). */
class CylinderBrandSeeder extends Seeder
{
    private const MARCAS = [
        'CPI' => '393', 'BONGAS' => '3', 'BTIC' => '4', 'CIDEGAS' => '5', 'CILBRAS' => '6', 'CNG' => '7', 'DALMINE' => '8',
        'EKC' => '9', 'ENK' => '10', 'EUROCIL' => '11', 'FABER' => '12', 'HEBEI' => '13', 'IMPROCIL' => '14', 'INFLEX' => '15',
        'JIANGSU' => '16', 'JINDUN' => '17', 'KIOSHI' => '18', 'LINCOLN' => '19', 'LUXFER' => '20', 'MAT' => '21', 'NKCO' => '22',
        'SHENYANG' => '23', 'WENZHOU' => '24', 'WORTHINGTON' => '25', 'ANHUI' => '73', 'ARGENTOIL' => '74', 'HITECH' => '75',
        'IMZ' => '76', 'NORRIS' => '77', 'QIQI' => '78', 'QUANTUM' => '79', 'RAMA' => '80', 'SINOMA' => '81', 'SYSTEM' => '82',
        'TECNOMASH' => '83', 'TW' => '84', 'VITCOVICE' => '85', 'XPERION' => '86', '4G-GYM' => '87', 'LD CMV' => '88',
        'ANHUI LD' => '233', 'JINHUA' => '253', 'VITKOVICE MILMET' => '333', 'BANNER' => '2', 'AGILITY CYLINDERS LLC' => '453',
        // JINHUA y JINHUA 4GYM comparten el 253 en SICOM
        'JINHUA 4GYM' => '253', 'LD' => '514', 'YONGAN 4G GYM' => '556', 'YA' => '578', 'SanJiang HangTian' => '616',
        'HUBEI DONGJUN EST' => '596', 'WGAS' => '697',
    ];

    public function run(): void
    {
        foreach (self::MARCAS as $nombre => $codigo) {
            $marca = CylinderBrand::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first() ?? new CylinderBrand;
            $marca->fill(['nombre' => $nombre, 'codigo_sicom' => $codigo])->save();
        }
    }
}

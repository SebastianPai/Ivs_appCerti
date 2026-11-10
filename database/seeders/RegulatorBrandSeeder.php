<?php

namespace Database\Seeders;

use App\Models\RegulatorBrand;
use Illuminate\Database\Seeder;

/** Marcas de regulador de la plataforma anterior con su código SICOM. */
class RegulatorBrandSeeder extends Seeder
{
    private const MARCAS = [
        'ABAGAS' => '26', 'ARGENCHIP' => '27', 'AUTOGAS' => '28', 'AUTRONIC' => '29', 'AVANTI' => '30', 'BEDINI' => '31',
        'BIGAS' => '32', 'BLUEMEC' => '33', 'BONGAS' => '34', 'BRC' => '35', 'BUGATTI' => '36', 'DA-VINCI' => '37',
        'DINAMOTOR' => '38', 'EDG' => '39', 'EMMEGAS' => '40', 'EUROGAS' => '41', 'FAETON' => '42', 'GALILEO' => '43',
        'GASPETRO' => '44', 'GN-GROUP' => '45', 'GNVMOTORINATURAL' => '46', 'HEDEF' => '47', 'IMPCO' => '48', 'IMPULSOGAS' => '49',
        'ITALKAR' => '50', 'ITTCONOFLOW' => '51', 'LAE' => '52', 'LANDI' => '53', 'LANDI-RENZO' => '54', 'LOVATO' => '55',
        'MECO' => '56', 'METATRON' => '57', 'NGV' => '58', 'NGV_MOTORI' => '59', 'NOLFFS' => '60', 'OMVL' => '61', 'OYRSA' => '62',
        'PELMAG' => '63', 'SALUSTRI' => '64', 'TARTARINI' => '65', 'TECGAS' => '66', 'TES' => '67', 'TESCOM' => '68', 'TL' => '69',
        'TOMASETTO-ACHILLE' => '70', 'TOMASETTO-LOVATO' => '71', 'ZAVOLI' => '72', 'AFS' => '89', 'AIR-GNC' => '90', 'AINY' => '91',
        'AXIS GNC' => '92', 'CADEX' => '93', 'CHERASCO' => '94', 'EMER' => '96', 'EXONGAS' => '97', 'FOX' => '98',
        'GNC-SALUSTRI' => '99', 'HOFFMAN' => '100', 'ITT' => '101', 'IZAWA' => '102', 'LOGAS' => '103', 'MILLENIUM' => '105',
        'NIKKI' => '106', 'PRESSOR' => '107', 'RODAGAS' => '108', 'ALDESA' => '109', 'BCO' => '110', 'BMD' => '112',
        'COREA-GAS-SYSTEM' => '113', 'COGAS' => '114', 'EASY' => '115', 'LOVATO_DE_ITALIA' => '116', 'POCKET' => '117', 'PVR' => '118',
        'ROMANO' => '119', 'VERSUS' => '120', 'WOODWARD' => '121', 'TELEFLEX' => '122', 'VOLTRAN' => '123', 'VANAZ' => '124',
        'PROMOVIL' => '300', 'AUTOGAS ITALIA SRL' => '213', 'GENERICO' => '293',
        // Códigos repetidos en SICOM: 334 (las dos PARKER) y 413 (las dos HENGDA)
        'PARKER HANNIFIN CORPORATION VERIFLO DIVISION' => '334', 'HANA' => '273', 'IGT MOTORS' => '373', 'HENGDA 3G-GYM' => '413',
        'HENGDA / 5G-GYM' => '413', 'IGT' => '353', 'TECNOMASH' => '0', 'PARKER' => '334', 'San Jiang Hang' => '577',
        'E XON GNC' => '516', 'DongJun' => '636',
    ];

    public function run(): void
    {
        foreach (self::MARCAS as $nombre => $codigo) {
            $marca = RegulatorBrand::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first() ?? new RegulatorBrand;
            $marca->fill(['nombre' => $nombre, 'codigo_sicom' => $codigo])->save();
        }
    }
}

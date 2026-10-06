<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    public function __invoke(Request $request, Solicitud $solicitud)
    {
        abort_unless($solicitud->esVisiblePara($request->user()), 403, 'No tiene acceso a este certificado.');
        abort_unless($solicitud->estaAprobada(), 403, 'El certificado aún no ha sido aprobado por el revisor.');

        $solicitud->load([
            'user',
            'serviceType',
            'combustionSystem',
            'technology',
            'vehicle.brand',
            'vehicle.model',
            'vehicle.type',
            'regulators.brand',
            'cilindros.brand',
            'verificacion.evaluador',
            'verificacion.chip',
            'revisor',
        ]);

        return Pdf::loadView('pdf.certificado', ['record' => $solicitud])
            ->setPaper('letter')
            ->stream("certificado-{$solicitud->codigo}.pdf");
    }
}

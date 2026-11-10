<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    public function __invoke(Request $request, Solicitud $solicitud)
    {
        abort_unless($solicitud->esVisiblePara($request->user()), 403, 'No tiene acceso a este certificado.');
        abort_unless($solicitud->tieneCertificado(), 403, 'Esta solicitud no tiene certificado.');

        return $solicitud->pdfCertificado()->stream("certificado-{$solicitud->codigo}.pdf");
    }
}

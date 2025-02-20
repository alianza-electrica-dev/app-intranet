<?php
/*
Solo es una prueba

*/
namespace App\Http\Controllers;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Response;


class PdfController extends Controller
{
    public function generarPDF()
    {
        $dompdf = new Dompdf();
        $html = '<h1>Mi PDF generado</h1><p>Este es un contenido de prueba.</p>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return Response::make($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="archivo.pdf"',
        ]);
    }
}



?>
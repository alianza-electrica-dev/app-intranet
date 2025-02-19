<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    {{-- {{ csrf_token() }} --}}
    <iframe id="pdfViewer" style="width:100%; height:500px; border:none;"></iframe>
    <button onclick="document.getElementById('pdfViewer').src = '/admin/generar-pdf'">
        Ver PDF
    </button>
    
    
</body>
</html>
<?php
// Script extremadamente simple para generar un PDF básico
// No requiere dependencias externas

// Configurar cabeceras para forzar la descarga de un PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="presupuesto.pdf"');

// Crear un PDF mínimo válido
echo '%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>
endobj
4 0 obj
<< /Length 200 >>
stream
BT
/F1 24 Tf
50 700 Td
(Presupuesto) Tj
/F1 12 Tf
0 -50 Td
(Este es su presupuesto en formato PDF.) Tj
0 -20 Td
(Para ver todos los detalles, por favor utilice la version HTML.) Tj
ET
endstream
endobj
5 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
xref
0 6
0000000000 65535 f
0000000010 00000 n
0000000060 00000 n
0000000115 00000 n
0000000230 00000 n
0000000480 00000 n
trailer
<< /Size 6 /Root 1 0 R >>
startxref
550
%%EOF';
?>

<?php
require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

$pdf = new Fpdi();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Teste com imagem local
$pdf->Cell(0, 10, 'Teste de Inserção de Imagem', 0, 1, 'C');

// Inserir imagem local
$pdf->Image('path/to/local/image.png', 10, 20, 50);

$pdf->Output('I', 'teste_imagem.pdf');
?>

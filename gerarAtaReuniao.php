<?php
ob_start(); // Inicia o buffer de saída para evitar enviar dados antes do PDF

require_once('path/to/fpdf/fpdf.php');
include 'db.php';

$eventoId = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT e.*, u.Nome as UsuarioNome FROM eventos e LEFT JOIN usuarios u ON e.usuario_id = u.Id WHERE e.id = :id");
$stmt->bindParam(':id', $eventoId, PDO::PARAM_INT);

if (!$stmt->execute()) {
    die("Erro na consulta SQL");
}

$evento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evento) {
    die("Evento não encontrado.");
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);

// Inclua a logomarca
$logoPath = 'path/to/fpdf/img/logo.jpg';
$pdf->Image($logoPath, 10, 10, 33);
$pdf->Ln(20); // Espaço após o logo

// Cabeçalho
$pdf->SetFont('Arial', 'B', 17);
$pdf->Cell(0, 10, utf8_decode('ATA DE REUNIÃO'), 0, 1, 'C');
$pdf->Ln(10);

// Informações da reunião
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(216, 216, 216); // Cor de preenchimento cinza
$pdf->Cell(95, 10, 'DATA: ' . date('d/m/Y', strtotime($evento['data_inicio'])), 1, 0, 'C', true);
$pdf->Cell(95, 10, 'CRIADO POR: ' . utf8_decode($evento['UsuarioNome']), 1, 1, 'C', true);
$pdf->Ln(10);

// Título da ata
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(216, 216, 216);
$pdf->Cell(0, 10, utf8_decode('TÍTULO'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 12);
$pdf->SetFillColor(255, 255, 255); // Branco
$pdf->Cell(0, 10, utf8_decode($evento['titulo']), 1, 1, 'C', true);
$pdf->Ln(10);

// Descrição da ata
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(216, 216, 216);
$pdf->Cell(0, 10, utf8_decode('DESCRIÇÃO'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 12);
$pdf->SetFillColor(255, 255, 255); // Branco

// Respeitar as quebras de linha originais da descrição
$descricao = utf8_decode($evento['descricao']); // Decodifica para UTF-8

// Usa MultiCell para imprimir a descrição respeitando as quebras de linha
if ($pdf->GetY() > 250) { // Verifica se está próximo ao final da página
    $pdf->AddPage(); // Adiciona uma nova página se estiver no fim
}
$pdf->MultiCell(0, 6, $descricao, 1, 'L', true); // Ajusta a altura da célula para 6

// Saída do PDF
$pdf->Output('I', 'Ata_da_Reuniao_' . preg_replace('/\s+/', '_', utf8_decode($evento['titulo'])) . '.pdf');

ob_end_flush(); // Envia o buffer de saída e desliga o buffer
?>

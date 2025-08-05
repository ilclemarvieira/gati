<?php
require_once('path/to/fpdf/fpdf.php'); // Substitua pelo caminho correto até o FPDF
include 'db.php';

$id_backlog = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT b.*, u.Nome as NomeResponsavel FROM backlog b LEFT JOIN usuarios u ON b.Responsavel = u.Id WHERE b.Id = :id");
$stmt->bindParam(':id', $id_backlog, PDO::PARAM_INT);

if (!$stmt->execute()) {
    die("Erro na consulta SQL");
}

$detalhesBacklog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$detalhesBacklog) {
    die("Item de backlog não encontrado.");
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$leftMargin = 10;
$rightMargin = 10;
$pdf->SetMargins($leftMargin, 10, $rightMargin);

// Logomarca
$logoPath = 'path/to/fpdf/img/logo.jpg'; // Ajuste o caminho para sua logomarca
$pdf->Image($logoPath, 10, 10, 33);
$pdf->Ln(20);

// Cabeçalho
$pdf->SetFont('Arial', 'B', 17);
$pdf->Cell(0, 10, utf8_decode('REGISTRO DE BACKLOG'), 0, 1, 'C');
$pdf->Ln(10);

// Número do Backlog, Data de Criação e Prioridade na mesma linha
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(216, 216, 216);
$pdf->Cell(47, 10, 'Data: ' . date('d/m/Y', strtotime($detalhesBacklog['Dt_criacao'])), 1, 0, 'C', true);

// Definindo a cor de preenchimento para a célula da prioridade
$pdf->SetFillColor(169, 169, 169); // Cor cinza
$pdf->Cell(143, 10, utf8_decode('Prioridade: ' . $detalhesBacklog['Prioridade']), 1, 1, 'C', true);
$pdf->Ln(10);


// Nome do Projeto
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169);
$pdf->Cell(0, 10, utf8_decode('NOME DO PROJETO'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 12);
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(0, 10, utf8_decode($detalhesBacklog['Projeto']), 1, 1, 'L', true);
$pdf->Ln(10);

// Descrição
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169);
$pdf->Cell(0, 10, utf8_decode('DESCRIÇÃO'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);
$pdf->SetFillColor(255, 255, 255);
$descricao = utf8_decode($detalhesBacklog['Descricao']);
$pdf->MultiCell(0, 6, $descricao, 1, 'L', true);
$pdf->Ln(10);

// Responsável
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169);
$pdf->Cell(0, 10, utf8_decode('RESPONSÁVEL'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 12);
$pdf->SetFillColor(255, 255, 255);
$pdf->Cell(0, 10, utf8_decode($detalhesBacklog['NomeResponsavel']), 1, 1, 'L', true);
$pdf->Ln(10);

// Finaliza o PDF e o envia para download ou para o navegador
$pdf->Output('I', 'Backlog_'.$id_backlog.'.pdf');
?>

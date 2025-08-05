<?php
require_once('path/to/fpdf/fpdf.php');
include 'db.php';

$osId = $_GET['osId'];
$stmt = $pdo->prepare("SELECT o.*, u.Nome as NomeResponsavel, c.Nome as NomeContratada FROM bi o LEFT JOIN usuarios u ON o.Responsavel = u.Id LEFT JOIN contratadas c ON o.Id_contratada = c.Id WHERE o.Id = :osId");
$stmt->bindParam(':osId', $osId, PDO::PARAM_INT);

if (!$stmt->execute()) {
    die("Erro na consulta SQL");
}

$osData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$osData) {
    die("Dados do projeto BI não encontrados");
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$leftMargin = 10;
$rightMargin = 10;
$pdf->SetMargins($leftMargin, 10, $rightMargin);

// Após obter os dados do banco de dados
$osData['Descricao'] = preg_replace("/\n\s+/", "\n", $osData['Descricao']); // Remove espaços em branco após quebras de linha
$osData['Descricao'] = str_replace("\r\n", "\n", $osData['Descricao']); // Normaliza quebras de linha do Windows
$osData['Descricao'] = str_replace("\r", "\n", $osData['Descricao']); // Normaliza quebras de linha do Mac
$osData['Descricao'] = str_replace(["“", "”", "–"], ['"', '"', '-'], $osData['Descricao']); // Corrige caracteres especiais
$description = utf8_decode($osData['Descricao']); // Converte a codificação

// Inclua a logomarca
$logoPath = 'path/to/fpdf/img/logo.jpg';
$pdf->Image($logoPath, 10, 10, 33);
$pdf->Ln(20); // Espaço após o logo

// Cabeçalho
$pdf->SetFont('Arial', 'B', 17);
$pdf->Cell(0, 10, utf8_decode('REGISTRO DO PROJETO BI'), 0, 1, 'C');
$pdf->Ln(10);

// Número e Data da OS
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(216, 216, 216);
$pdf->Cell(95, 10, $osData['N_os'], 1, 0, 'C', true);
$pdf->Cell(95, 10, 'DATA DE ABERTURA: ' . date('d/m/Y', strtotime($osData['Dt_inicial'])), 1, 1, 'C', true);
$pdf->Ln(10); // Pula uma linha

// Prioridade
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169); // Cor de preenchimento cinza
$pdf->Cell(150, 10, 'PRIORIDADE', 1, 0, 'C', true);
$pdf->SetFillColor(216, 216, 216);

// Utilize utf8_decode apenas se o texto estiver em UTF-8
$prioridade = utf8_decode($osData['Prioridade']);
// Se a prioridade é média, substitua o texto diretamente
$prioridade = str_replace('MÃ©DIA', 'MÉDIA', $prioridade);
$pdf->Cell(40, 10, $prioridade, 1, 1, 'C', true);
$pdf->Ln(10); // Pula uma linha

// Nome da OS
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169); 
$pdf->Cell(0, 10, utf8_decode('NOME DO PROJETO'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 12);
$pdf->SetFillColor(255, 255, 255); // Cor de preenchimento branco
$pdf->Cell(0, 10, utf8_decode($osData['Nome_os']), 1, 1, 'L', true);
$pdf->Ln(10); // Pula uma linha


// Descrição da OS
$description = utf8_decode($osData['Descricao']);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169);
$pdf->Cell(0, 10, utf8_decode('DESCRIÇÃO DO PROJETO'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);
$pdf->SetFillColor(255, 255, 255);

// Calcule a largura disponível para a descrição
$descriptionWidth = $pdf->GetPageWidth() - $leftMargin - $rightMargin - 0; // -2 para pequeno espaço em cada lado

// Desenhe a descrição com MultiCell com alinhamento justificado
$pdf->MultiCell($descriptionWidth, 7, $description, 1, 'J', true);

$pdf->Output('I', '' . $osData['N_os'] . '.pdf');

?>
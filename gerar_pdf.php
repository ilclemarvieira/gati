<?php
require_once('path/to/fpdf/fpdf.php'); // Substitua pelo caminho correto até o FPDF
include 'db.php';

$id_tarefa = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT s.*, u.Nome as NomeResponsavel FROM suporte s LEFT JOIN usuarios u ON s.Solicitado_por = u.Id WHERE s.Id = :id");
$stmt->bindParam(':id', $id_tarefa, PDO::PARAM_INT);

if (!$stmt->execute()) {
    die("Erro na consulta SQL");
}

$detalhesTarefa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$detalhesTarefa) {
    die("Dados da tarefa não encontrados");
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$leftMargin = 10;
$rightMargin = 10;
$pdf->SetMargins($leftMargin, 10, $rightMargin);

// Logomarca
$logoPath = 'path/to/fpdf/img/logo.jpg'; // Corrija o caminho para o logo
$pdf->Image($logoPath, 10, 10, 33);
$pdf->Ln(20); // Espaço após o logo

// Cabeçalho
$pdf->SetFont('Arial', 'B', 17);
$pdf->Cell(0, 10, utf8_decode('REGISTRO DE SUPORTE'), 0, 1, 'C');
$pdf->Ln(10);

// Número da Tarefa, Data de Criação e Prioridade na mesma linha
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(216, 216, 216);
$pdf->Cell(47, 10, utf8_decode('Código: ' . $detalhesTarefa['Id']), 1, 0, 'C', true);
$pdf->Cell(47, 10, utf8_decode('Data: ' . date('d/m/Y', strtotime($detalhesTarefa['Dt_criacao']))), 1, 0, 'C', true);

// Prioridade com fundo de cor diferente para destaque
$pdf->SetFillColor(169, 169, 169); // Ajuste conforme a necessidade
$pdf->Cell(96, 10, utf8_decode('Prioridade: ' . $detalhesTarefa['Prioridade']), 1, 1, 'C', true);
$pdf->Ln(10);

// Nome da Tarefa
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169); // Cor cinza para cabeçalho
$pdf->Cell(0, 10, utf8_decode('NOME DA TAREFA'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 12);
$pdf->SetFillColor(255, 255, 255); // Cor branca para o conteúdo
$pdf->Cell(0, 10, utf8_decode($detalhesTarefa['Tarefa']), 1, 1, 'L', true);
$pdf->Ln(10); // Pula uma linha

// Descrição da Tarefa
$descricao = utf8_decode($detalhesTarefa['Observacao']);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169); // Cor cinza para cabeçalho
$pdf->Cell(0, 10, utf8_decode('DESCRIÇÃO DA TAREFA'), 1, 1, 'C', true);

// Prepara para escrever o corpo da descrição
$pdf->SetFont('Arial', '', 10);
$pdf->SetFillColor(255, 255, 255); // Cor branca para o conteúdo

// Calcula a altura necessária para a descrição
$start_y = $pdf->GetY();
$start_page = $pdf->PageNo();

// Divide a descrição em parágrafos e escreve cada um
$paragrafos = explode("\n", trim($descricao));
foreach ($paragrafos as $paragrafo) {
    // Adiciona um espaço extra entre parágrafos
    if (trim($paragrafo) == '') {
        $pdf->Ln(1); // Espaçamento adicional para a quebra de parágrafo
        continue;
    }
    $pdf->MultiCell(0, 6, $paragrafo, 0, 'J');
    $pdf->Ln(1); // Espaçamento entre as linhas
}

$end_y = $pdf->GetY();
$end_page = $pdf->PageNo();

// Adiciona a borda ao redor da descrição
if ($end_page == $start_page) {
// Descrição está na mesma página
$pdf->Rect($leftMargin, $start_y, $pdf->GetPageWidth() - $leftMargin - $rightMargin, $end_y - $start_y, 'D');
} else {
// Descrição atravessou para a próxima página
$pdf->Rect($leftMargin, $start_y, $pdf->GetPageWidth() - $leftMargin - $rightMargin, $pdf->GetPageHeight() - $start_y, 'D'); // Primeira página
$pdf->Rect($leftMargin, 0, $pdf->GetPageWidth() - $leftMargin - $rightMargin, $end_y, 'D'); // Segunda página
}



// Informações Adicionais
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169); // Cor cinza para cabeçalho
$pdf->Cell(95, 10, 'SOLICITADO POR', 1, 0, 'C', true);

// Verifica se há uma data de prazo previsto. Se não, mostra "Sem previsão".
$prazoPrevistoTexto = $detalhesTarefa['Prazo_previsto'] ? date('d/m/Y', strtotime($detalhesTarefa['Prazo_previsto'])) : 'Sem previsão';

$pdf->Cell(95, 10, 'PRAZO PREVISTO', 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 12);
$pdf->SetFillColor(255, 255, 255); // Cor branca para o conteúdo
$pdf->Cell(95, 10, utf8_decode($detalhesTarefa['NomeResponsavel']), 1, 0, 'C', true);
$pdf->Cell(95, 10, utf8_decode($prazoPrevistoTexto), 1, 1, 'C', true);
$pdf->Ln(10);


// Finaliza o PDF e o envia para download ou para o navegador
$pdf->Output('I', 'Tarefa_suporte_'.$id_tarefa.'.pdf');
?>

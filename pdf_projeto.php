<?php
/**
 * pdf_projeto.php
 * Relatório em PDF com layout moderno, tratamento de subtarefas, progresso e 
 * supressão de avisos de conversão de caracteres.
 */

// -----------------------------------------------------
// CONFIGURAÇÕES INICIAIS
// -----------------------------------------------------
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ob_start();
date_default_timezone_set('America/Sao_Paulo');

// Inclui a biblioteca FPDF e a conexão com o banco (garanta que os caminhos estejam corretos)
require_once __DIR__ . '/path/to/fpdf/fpdf.php';
require_once 'db.php';

// -----------------------------------------------------
// FUNÇÕES AUXILIARES
// -----------------------------------------------------
/**
 * Converte string UTF-8 para ISO-8859-1 ignorando caracteres ilegais.
 */
function toLatin1($str) {
    return @iconv('UTF-8', 'ISO-8859-1//IGNORE', $str);
}

/**
 * Formata data/hora "YYYY-mm-dd HH:ii:ss" em "dd/mm/YYYY HH:ii:ss".
 */
function formatarDataHora($dataStr) {
    if (empty($dataStr) || strpos($dataStr, '0000-00-00') === 0) {
        return '';
    }
    $timestamp = strtotime($dataStr);
    return $timestamp ? date('d/m/Y H:i:s', $timestamp) : $dataStr;
}

// -----------------------------------------------------
// CLASSE PDF PERSONALIZADA
// -----------------------------------------------------
class PDFProjeto extends FPDF {
    public $nomeProjeto = '';

    // Cabeçalho do PDF
    function Header() {
        // Fundo branco para o cabeçalho e aumento da altura para melhor acomodar os elementos
        $this->SetFillColor(255, 255, 255);
        $this->Rect(0, 0, $this->GetPageWidth(), 35, 'F');

        // Exibe o logotipo no canto superior esquerdo
        $this->Image('img/logo.png', 10, 5, 30);

        // Título centralizado
        $this->SetXY(0, 7);
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(0, 0, 102); // Azul escuro para destaque
        $this->Cell(0, 10, toLatin1("Relatório de Projeto"), 0, 1, 'C');

        // Subtítulo com o nome do projeto, se disponível
        if (!empty($this->nomeProjeto)) {
            $this->SetFont('Arial', 'I', 12);
            $this->SetTextColor(80, 80, 80); // Cinza para o subtítulo
            $this->Cell(0, 6, toLatin1($this->nomeProjeto), 0, 1, 'C');
        }

        // Linha divisória elegante abaixo do cabeçalho
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.5);
        $this->Line(10, 35, $this->GetPageWidth() - 10, 35);

        // Define a posição inicial para o conteúdo do documento
        $this->SetY(40);
    }

    // Rodapé do PDF
    function Footer() {
        $this->SetFillColor(245, 245, 245);
        $this->Rect(0, $this->GetPageHeight() - 15, $this->GetPageWidth(), 15, 'F');

        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 4, toLatin1("Página " . $this->PageNo() . " de {nb}"), 0, 0, 'L');

        $dataImpressao = date('d/m/Y H:i:s');
        $this->Cell(0, 4, toLatin1("Impresso em: {$dataImpressao}"), 0, 0, 'R');
    }

    /**
     * Desenha uma barra de progresso.
     *
     * @param float $x Posição X
     * @param float $y Posição Y
     * @param float $width Largura total da barra
     * @param float $height Altura da barra
     * @param float $percent Percentual de preenchimento (0 a 100)
     */
    function DrawProgressBar($x, $y, $width, $height, $percent) {
        $this->SetDrawColor(120, 120, 120);
        $this->Rect($x, $y, $width, $height);
        $fillWidth = ($width * $percent) / 100.0;
        $this->SetFillColor(67, 160, 71);
        $this->Rect($x, $y, $fillWidth, $height, 'F');
    }
}

// -----------------------------------------------------
// CAPTURA DO ID DO PROJETO E CONSULTAS NO BANCO
// -----------------------------------------------------
$projetoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$projetoId || $projetoId <= 0) {
    ob_end_clean();
    die("Projeto inválido.");
}

try {
    // Consulta principal do projeto
    $stmtProj = $pdo->prepare("
        SELECT p.*, u.Nome AS Responsavel
          FROM projetos p
     LEFT JOIN usuarios u ON p.ResponsavelId = u.Id
         WHERE p.Id = :id
    ");
    $stmtProj->execute([':id' => $projetoId]);
    $projeto = $stmtProj->fetch(PDO::FETCH_ASSOC);
    if (!$projeto) {
        ob_end_clean();
        die("Projeto não encontrado.");
    }

    // Consulta de subtarefas
    $stmtSubs = $pdo->prepare("
        SELECT s.*, uc.Nome AS nomeConcluidor, cr.Nome AS nomeCriador
          FROM subtarefas_projetos s
     LEFT JOIN usuarios uc ON s.concluido_por = uc.Id
     LEFT JOIN usuarios cr ON s.criador_id = cr.Id
         WHERE s.projeto_id = :id
      ORDER BY s.id
    ");
    $stmtSubs->execute([':id' => $projetoId]);
    $subtarefas = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);
    $totalSubs  = count($subtarefas);
    $concluidas = 0;
    foreach ($subtarefas as $st) {
        if ($st['concluida']) {
            $concluidas++;
        }
    }
    $percConcluidas = ($totalSubs > 0) ? round(($concluidas / $totalSubs) * 100, 1) : 0;

    // Consulta de anexos
    $stmtAnexos = $pdo->prepare("
        SELECT a.*, u.Nome AS nomeUsuario
          FROM anexos_projetos a
     LEFT JOIN usuarios u ON a.usuario_id = u.Id
         WHERE a.projeto_id = :id
      ORDER BY a.id
    ");
    $stmtAnexos->execute([':id' => $projetoId]);
    $anexos = $stmtAnexos->fetchAll(PDO::FETCH_ASSOC);

    // Consulta de atividades/comentários
    $stmtAtiv = $pdo->prepare("
        SELECT a.*, u.Nome AS nomeUsuario
          FROM atividades_projetos a
     LEFT JOIN usuarios u ON a.usuario_id = u.Id
         WHERE a.projeto_id = :id
      ORDER BY a.id ASC
    ");
    $stmtAtiv->execute([':id' => $projetoId]);
    $atividades = $stmtAtiv->fetchAll(PDO::FETCH_ASSOC);

    // Consulta do relatório de finalização, se existir
    $stmtRel = $pdo->prepare("
        SELECT *
          FROM okr_relatorios
         WHERE projeto_id = :id
    ");
    $stmtRel->execute([':id' => $projetoId]);
    $relatorio = $stmtRel->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    ob_end_clean();
    die("Erro no banco de dados: " . $e->getMessage());
}

// -----------------------------------------------------
// GERAÇÃO DO PDF
// -----------------------------------------------------
$pdf = new PDFProjeto('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->nomeProjeto = $projeto['NomeProjeto'];
$pdf->AddPage();

// 1) Título Centralizado
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(33, 33, 33);
$pdf->Cell(0, 10, toLatin1("Projeto: {$projeto['NomeProjeto']}"), 0, 1, 'C');
$pdf->Ln(2);
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.4);
$pdf->Line(10, $pdf->GetY(), $pdf->GetPageWidth() - 10, $pdf->GetY());
$pdf->Ln(5);

// 2) Informações Gerais
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, toLatin1("1) Informações Gerais"), 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, toLatin1("Responsável: {$projeto['Responsavel']}"), 0, 1);
$pdf->Cell(0, 7, toLatin1("Data de Criação: " . formatarDataHora($projeto['DataCriacao'])), 0, 1);
$pdf->Cell(0, 7, toLatin1("Status atual: {$projeto['Status']}"), 0, 1);
$pdf->Ln(3);

if ($relatorio) {
    $pdf->Cell(0, 7, toLatin1("Data de Finalização: " . formatarDataHora($relatorio['data_finalizacao'])), 0, 1);
    $pdf->Cell(0, 7, toLatin1("Tempo de Execução: {$relatorio['tempo_execucao']} dia(s)"), 0, 1);
    $pdf->Ln(3);
    if (!empty($relatorio['licoes_aprendidas'])) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, toLatin1("Lições Aprendidas:"), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, toLatin1($relatorio['licoes_aprendidas']));
        $pdf->Ln(3);
    }
}

// 3) Progresso das Subtarefas
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, toLatin1("Progresso das Subtarefas"), 0, 1);
if ($totalSubs > 0) {
    $posX = $pdf->GetX();
    $posY = $pdf->GetY();
    $barW = 100;
    $barH = 6;
    $pdf->DrawProgressBar($posX, $posY, $barW, $barH, $percConcluidas);
    $pdf->Ln($barH + 2);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, toLatin1("Concluídas: {$concluidas}/{$totalSubs} ({$percConcluidas}%)"), 0, 1);
    $pdf->Ln(3);
} else {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, toLatin1("Nenhuma subtarefa cadastrada."), 0, 1);
    $pdf->Ln(3);
}

// 4) Listagem de Subtarefas
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, toLatin1("2) Subtarefas"), 0, 1);
$pdf->SetFont('Arial', '', 10);
if ($totalSubs > 0) {
    foreach ($subtarefas as $st) {
        $nomeSub   = $st['nome_subtarefa'];
        $status    = $st['concluida'] ? "Concluída" : "Em andamento";
        $criador   = $st['nomeCriador'] ?: "Desconhecido";
        $dtCriacao = formatarDataHora($st['data_cadastro']);
        $conclusorInfo = '';
        if ($st['concluida']) {
            $nomeConcluidor = $st['nomeConcluidor'] ?: "N/D";
            $dtConclusao    = formatarDataHora($st['data_conclusao']);
            $conclusorInfo  = "\n   - Concluída por: {$nomeConcluidor} em {$dtConclusao}";
        }
        $texto = "• {$nomeSub} ({$status})\n   - Criada por: {$criador} em {$dtCriacao}" . $conclusorInfo;
        $pdf->MultiCell(0, 6, toLatin1($texto));
        $pdf->Ln(2);
    }
} else {
    $pdf->Cell(0, 8, toLatin1("Nenhuma subtarefa cadastrada."), 0, 1);
}
$pdf->Ln(3);

// 5) Anexos
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, toLatin1("3) Anexos"), 0, 1);
$pdf->SetFont('Arial', '', 10);
if (!empty($anexos)) {
    foreach ($anexos as $a) {
        $nomeArq  = $a['nome_arquivo'];
        $nomeUser = $a['nomeUsuario'] ?: "Desconhecido";
        $dtUpload = formatarDataHora($a['data_upload']);
        $texto    = "• {$nomeArq}\n   - Enviado por: {$nomeUser} em {$dtUpload}";
        $pdf->MultiCell(0, 6, toLatin1($texto));
        $pdf->Ln(2);
    }
} else {
    $pdf->Cell(0, 8, toLatin1("Nenhum anexo disponível."), 0, 1);
}
$pdf->Ln(3);

// 6) Atividades / Comentários
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, toLatin1("4) Atividades / Comentários"), 0, 1);
$pdf->SetFont('Arial', '', 10);
if (!empty($atividades)) {
    foreach ($atividades as $com) {
        $user   = $com['nomeUsuario'] ?: "Desconhecido";
        $dtCom  = formatarDataHora($com['data_hora']);
        $txtCom = $com['comentario'];
        $linha  = "• {$user} em {$dtCom}:\n   {$txtCom}";
        $pdf->MultiCell(0, 6, toLatin1($linha));
        $pdf->Ln(2);
    }
} else {
    $pdf->Cell(0, 8, toLatin1("Nenhum comentário ou atividade registrada."), 0, 1);
}
$pdf->Ln(5);

// -----------------------------------------------------
// ENVIO DO PDF COM CABEÇALHOS HTTP CORRETOS
// -----------------------------------------------------
// Limpa todos os buffers de saída para evitar conteúdo extra
while (ob_get_level()) {
    ob_end_clean();
}

// Define os cabeçalhos adequados para PDF
header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=\"Relatorio_Projeto_{$projetoId}.pdf\"");
header("Cache-Control: private, max-age=0, must-revalidate");
header("Pragma: public");

// Envia o PDF para o navegador
$pdf->Output('I', "Relatorio_Projeto_{$projetoId}.pdf");
exit;
?>

<?php
require_once __DIR__ . '/vendor/autoload.php'; // FPDF + FPDI via Composer
require __DIR__ . '/db.php';

// IMPORTAÇÃO APENAS UMA VEZ
use setasign\Fpdi\Fpdi;

date_default_timezone_set('America/Sao_Paulo');
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

function formataData($data) {
    if (!$data || $data == '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    return $dt ? $dt->format('d/m/Y') : $data;
}
function formataDataHora($datahora) {
    if (!$datahora) return '';
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datahora);
    if ($dt) return $dt->format('d/m/Y H:i:s');
    $dt = DateTime::createFromFormat('Y-m-d H:i', $datahora);
    if ($dt) return $dt->format('d/m/Y H:i');
    return $datahora;
}

// === DADOS DO PROJETO ===
$projeto_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$projeto_id) die('ID de projeto inválido.');

$stmt = $pdo->prepare("SELECT * FROM projetos WHERE Id = :id");
$stmt->execute(['id' => $projeto_id]);
$projeto = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$projeto) die('Projeto não encontrado.');

// Buscar nome do responsável pelo projeto (ResponsavelId)
$responsavel_principal = '';
if (!empty($projeto['ResponsavelId'])) {
    $stmt = $pdo->prepare("SELECT Nome FROM usuarios WHERE Id = ?");
    $stmt->execute([$projeto['ResponsavelId']]);
    $responsavel_principal = $stmt->fetchColumn() ?: '';
}

// Responsáveis (nomes)
$responsaveis_nomes = '';
$gestor_nome = '';
if (!empty($projeto['UsuariosEnvolvidos'])) {
    $ids = array_filter(array_map('trim', explode(',', $projeto['UsuariosEnvolvidos'])));
    if (count($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT Nome, PerfilAcesso, SetorId FROM usuarios WHERE Id IN ($in)");
        $stmt->execute($ids);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $nomes = [];
        foreach ($usuarios as $u) $nomes[] = $u['Nome'];
        $responsaveis_nomes = implode(', ', $nomes);
    }
}

// Gestor do setor
$gestor_nome = '';
if (!empty($projeto['SetorRelacionadoId'])) {
    $stmt = $pdo->prepare("SELECT u.Nome FROM usuarios u WHERE u.SetorId = ? AND u.PerfilAcesso IN (2,3) LIMIT 1");
    $stmt->execute([$projeto['SetorRelacionadoId']]);
    $gestor_nome = $stmt->fetchColumn() ?: '';
}

// Subtarefas
$stmt = $pdo->prepare("SELECT * FROM subtarefas_projetos WHERE projeto_id = :id ORDER BY ordem ASC");
$stmt->execute(['id' => $projeto_id]);
$subtarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Comentários
$stmt = $pdo->prepare("
    SELECT a.comentario, a.data_hora, u.Nome AS usuario 
    FROM atividades_projetos a 
    JOIN usuarios u ON a.usuario_id = u.Id 
    WHERE a.projeto_id = :id 
    ORDER BY a.data_hora ASC
");
$stmt->execute(['id' => $projeto_id]);
$comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Anexos
$stmt = $pdo->prepare("SELECT nome_arquivo, data_upload FROM anexos_projetos WHERE projeto_id = :id");
$stmt->execute(['id' => $projeto_id]);
$anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Registro de Andamento (justificativas_andamento)
$stmt = $pdo->prepare("
    SELECT j.justificativa, j.data_justificativa, u.Nome AS usuario
    FROM justificativas_andamento j
    JOIN usuarios u ON j.usuario_id = u.Id
    WHERE j.projeto_id = :id
    ORDER BY j.data_justificativa ASC
");
$stmt->execute(['id' => $projeto_id]);
$andamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lições Aprendidas/Observações Finais
$licoes_aprendidas = '';
if (!empty($projeto['licoes_aprendidas'])) $licoes_aprendidas = $projeto['licoes_aprendidas'];
elseif (!empty($projeto['qualificacao'])) $licoes_aprendidas = $projeto['qualificacao'];

// Funções e Classes auxiliares
function buscarProjetosVinculados($pdo, $ids) {
    if (empty($ids)) return [];
    $projetos = [];
    foreach ($ids as $id) {
        if (is_numeric($id)) {
            $stmt = $pdo->prepare("SELECT p.Id, p.NomeProjeto, p.Status, p.DataCriacao, p.Prioridade, p.UsuariosEnvolvidos FROM projetos p WHERE p.Id = ?");
            $stmt->execute([$id]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $resp = '';
                if (!empty($row['UsuariosEnvolvidos'])) {
                    $rids = array_filter(array_map('trim', explode(',', $row['UsuariosEnvolvidos'])));
                    if (count($rids)) {
                        $in = implode(',', array_fill(0, count($rids), '?'));
                        $s = $pdo->prepare("SELECT Nome FROM usuarios WHERE Id IN ($in)");
                        $s->execute($rids);
                        $nomes = $s->fetchAll(PDO::FETCH_COLUMN);
                        $resp = implode(', ', $nomes);
                    }
                }
                $row['Responsaveis'] = $resp;
                $projetos[] = $row;
            }
        }
    }
    return $projetos;
}
$projetos_vinculados = [];
if (!empty($projeto['DependenciasProjetos'])) {
    $ids = array_filter(array_map('trim', explode(',', $projeto['DependenciasProjetos'])));
    $projetos_vinculados = buscarProjetosVinculados($pdo, $ids);
}

// Andamento
$total_sub = count($subtarefas);
$completed = 0;
foreach ($subtarefas as $t) if ($t['concluida']) $completed++;
$percent = $total_sub > 0 ? round(($completed / $total_sub) * 100) : 0;

// =============== CLASSE PDF FINAL HERDA FPDI =================
class MyPDF extends Fpdi {
    public $primaryColor = [23, 78, 155];
    public $darkColor = [0, 71, 160];

    function Header() {
        $logoPath = __DIR__ . '/img/logo.png';
        $this->SetFillColor(255,255,255);
        $this->Rect(0, 0, 210, 38, 'F');
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 95, 10, 20);
        }
        $this->SetY(30);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(23, 78, 155);
        $this->Cell(0, 10, utf8_decode('Relatório Detalhado do Projeto'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(80,80,80);
        $this->Cell(0, 7, utf8_decode('Gestão Ágil em TI - Gati'), 0, 1, 'C');
        $this->Ln(2);
        $this->SetDrawColor(23, 78, 155);
        $this->SetLineWidth(0.6);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->Ln(3);
    }

    function Footer() {
        $this->SetY(-18);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 8, utf8_decode('Emitido em: ') . date('d/m/Y H:i'), 0, 0, 'L');
        $this->Cell(0, 8, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
        $this->Ln(1);
        $this->SetDrawColor(220,220,220);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
    }

    function Divider() {
        $this->Ln(2);
        $this->SetDrawColor(200,220,255);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(4);
    }

    function CellFit($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
        $str_width = $this->GetStringWidth($txt);
        if ($str_width > $w) {
            $txt = $this->truncateText($txt, $w);
        }
        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
    }

    function truncateText($text, $maxWidth) {
        $suffix = '...';
        $width = $this->GetStringWidth($suffix);
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $sub = mb_substr($text, 0, $i + 1, 'UTF-8');
            if ($this->GetStringWidth($sub) + $width > $maxWidth) {
                return mb_substr($text, 0, $i, 'UTF-8') . $suffix;
            }
        }
        return $text;
    }

    // ====== EXTENSÃO PARA RECTÂNGULO ARREDONDADO ======
    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));

        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));

        $this->_Arc($xc+$r*$MyArc, $yc-$r, $xc+$r, $yc-$r*$MyArc, $xc+$r, $yc);

        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc+$r, $yc+$r*$MyArc, $xc+$r*$MyArc, $yc+$r, $xc, $yc+$r);

        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc-$r*$MyArc, $yc+$r, $xc-$r, $yc+$r*$MyArc, $xc-$r, $yc);

        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $x*$k,($hp-$yc)*$k ));
        $this->_Arc($xc-$r, $yc-$r*$MyArc, $xc-$r*$MyArc, $yc-$r, $xc, $yc-$r);

        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k,
            $x3*$this->k, ($h-$y3)*$this->k));
    }
} // FECHA CLASSE

// ========== GERAÇÃO DO PDF ============
$pdf = new MyPDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 25);



// ====== VISÃO GERAL ======
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(23,78,155);
$pdf->Cell(0,10,utf8_decode('Visão Geral do Projeto'),0,1,'L');

$pdf->SetFont('Arial','',11);
$pdf->SetTextColor(40,40,40);

$pdf->Cell(45,8,utf8_decode('Nome do Projeto:'),0,0);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,utf8_decode($projeto['NomeProjeto']),0,1);
$pdf->SetFont('Arial','',11);

$pdf->Cell(45,8,utf8_decode('Descrição:'),0,0);
$pdf->MultiCell(0,8,utf8_decode($projeto['descricao_projeto']),0,1);

$pdf->Cell(45,8,utf8_decode('Data de Criação:'),0,0);
$pdf->Cell(50,8,formataData($projeto['DataCriacao']),0,0);

$pdf->Cell(25,8,utf8_decode('Prazo:'),0,0);
$pdf->Cell(0,8,utf8_decode($projeto['Prazo']),0,1);

$pdf->Cell(45,8,utf8_decode('Prioridade:'),0,0);
$pdf->Cell(50,8,utf8_decode($projeto['Prioridade']),0,0);

$pdf->Cell(25,8,utf8_decode('Status:'),0,0);
$pdf->Cell(0,8,utf8_decode(ucfirst($projeto['Status'])),0,1);

$pdf->Cell(45,8,utf8_decode('Responsáveis:'),0,0);
$pdf->MultiCell(0,8,utf8_decode($responsaveis_nomes),0,1);

$pdf->Divider();

// ====== PROJETOS VINCULADOS (TABELA PREMIUM ORGANIZADA) ======
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(23,78,155);
$pdf->Cell(0,8,utf8_decode('Projetos Vinculados'),0,1);
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(35,35,35);

if (count($projetos_vinculados)) {
    // Defina larguras das colunas
    $w_nome = 65; $w_status = 21; $w_prio = 21; $w_data = 26; $w_resp = 52;
    $widths = [$w_nome, $w_status, $w_prio, $w_data, $w_resp];

    // Cabeçalho premium
    $pdf->SetFillColor(0, 71, 160);
    $pdf->SetTextColor(255,255,255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($w_nome, 9, utf8_decode('Nome'), 'LTBR', 0, 'L', 1);
    $pdf->Cell($w_status, 9, utf8_decode('Status'), 'TBR', 0, 'C', 1);
    $pdf->Cell($w_prio, 9, utf8_decode('Prioridade'), 'TBR', 0, 'C', 1);
    $pdf->Cell($w_data, 9, utf8_decode('Criação'), 'TBR', 0, 'C', 1);
    $pdf->Cell($w_resp, 9, utf8_decode('Responsáveis'), 'TBR', 1, 'L', 1);

    // Corpo da tabela
    $pdf->SetFont('Arial','',10);
    $pdf->SetTextColor(35,35,35);

    $fill = false;
    foreach ($projetos_vinculados as $pv) {
        $col_data = [
            $pv['NomeProjeto'],
            ucfirst($pv['Status']),
            $pv['Prioridade'],
            formataData($pv['DataCriacao']),
            $pv['Responsaveis']
        ];

        // Calcule altura máxima desta linha (com base nos textos das células)
        $line_heights = [];
        foreach ($col_data as $k => $text) {
            $n_lines = ceil($pdf->GetStringWidth(utf8_decode($text)) / ($widths[$k]-2));
            $line_heights[$k] = max(1, $n_lines) * 7;
        }
        $row_height = max($line_heights);

        // Cores alternadas de fundo
        if ($fill) $pdf->SetFillColor(242,247,255); else $pdf->SetFillColor(255,255,255);

        // Nome (permite MultiCell, para nunca quebrar a linha feio)
        $x = $pdf->GetX(); $y = $pdf->GetY();
        $pdf->MultiCell($w_nome, 7, utf8_decode($col_data[0]), 0, 'L', $fill);
        $maxY = $pdf->GetY();
        $linhaUsada = $maxY - $y;

        // Outras células alinhadas verticalmente ao topo da linha
        $pdf->SetXY($x+$w_nome, $y);
        $pdf->Cell($w_status, $linhaUsada, utf8_decode($col_data[1]), 0, 0, 'C', $fill);
        $pdf->Cell($w_prio, $linhaUsada, utf8_decode($col_data[2]), 0, 0, 'C', $fill);
        $pdf->Cell($w_data, $linhaUsada, utf8_decode($col_data[3]), 0, 0, 'C', $fill);
        $pdf->Cell($w_resp, $linhaUsada, utf8_decode($col_data[4]), 0, 1, 'L', $fill);

        // Bordas apenas ao redor da linha inteira
        $pdf->SetDrawColor(210,220,240);
        $pdf->Rect($x, $y, array_sum($widths), $linhaUsada);

        $fill = !$fill;
    }
} else {
    $pdf->Cell(0,7,utf8_decode('Nenhum projeto vinculado.'),0,1);
}
$pdf->Divider();


// ====== ANDAMENTO ======
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(23,78,155);
$pdf->Cell(0,9,utf8_decode('Andamento do Projeto'),0,1,'L');
$pdf->SetFont('Arial','',11);
$pdf->SetTextColor(40, 40, 40);
$pdf->Cell(0,7,utf8_decode("Tarefas concluídas: $completed de $total_sub ($percent%)"),0,1);
$pdf->Ln(1);

$barWidth = 130;
$barHeight = 8;
$x = $pdf->GetX();
$y = $pdf->GetY();
$pdf->SetDrawColor(200,220,255);
$pdf->Rect($x, $y, $barWidth, $barHeight);
$pdf->SetFillColor(30,144,255);
$pdf->Rect($x, $y, ($percent/100)*$barWidth, $barHeight, 'F');
$pdf->Ln($barHeight + 3);

$pdf->Divider();

// ====== REGISTRO DE ANDAMENTO ======
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(23,78,155);
$pdf->Cell(0,8,utf8_decode('Registro de Andamento (Atualizações dos Usuários)'),0,1);
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(50,50,50);

if (!empty($andamentos)) {
    foreach ($andamentos as $a) {
        $pdf->SetFont('Arial','I',9);
        $pdf->SetTextColor(30,144,255);
        $pdf->Cell(0,6,utf8_decode($a['usuario'].' ['.formataDataHora($a['data_justificativa']).']:'),0,1);
        $pdf->SetFont('Arial','',10);
        $pdf->SetTextColor(50,50,50);
        $pdf->MultiCell(0,6,utf8_decode($a['justificativa']),0,1);
        $pdf->Ln(1);
    }
} else {
    $pdf->Cell(0,7,utf8_decode('Nenhum registro de andamento cadastrado.'),0,1);
}
$pdf->Divider();

// ====== TAREFAS ======
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(23,78,155);
$pdf->Cell(0,8,utf8_decode('Tarefas do Projeto'),0,1);

$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(35,35,35);
if ($total_sub > 0) {
    $pdf->SetFillColor(30,144,255);
    $pdf->SetTextColor(255,255,255);
    $pdf->Cell(12,7,'#',1,0,'C',1);
    $pdf->Cell(85,7,utf8_decode('Tarefa'),1,0,'L',1);
    $pdf->Cell(25,7,utf8_decode('Concluída'),1,0,'C',1);
    $pdf->Cell(35,7,utf8_decode('Cadastro'),1,1,'C',1);

    $pdf->SetFont('Arial','',10);
    $pdf->SetTextColor(35,35,35);
    $pdf->SetFillColor(240, 248, 255);
    $fill = false;
    foreach ($subtarefas as $t) {
        $pdf->Cell(12,7,$t['ordem'],1,0,'C',$fill);
        $pdf->Cell(85,7,utf8_decode($t['nome_subtarefa']),1,0,'L',$fill);
        $pdf->Cell(25,7, $t['concluida'] ? utf8_decode('Sim') : utf8_decode('Não'),1,0,'C',$fill);
        $pdf->Cell(35,7, formataData($t['data_cadastro']),1,1,'C',$fill);
        $fill = !$fill;
    }
} else {
    $pdf->Cell(0,7,utf8_decode('Nenhuma tarefa cadastrada.'),0,1);
}
$pdf->Divider();

// ====== ANEXOS ======
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(23,78,155);
$pdf->Cell(0,8,utf8_decode('Anexos'),0,1);
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(35,35,35);

if (!empty($anexos)) {
    foreach ($anexos as $an) {
        $pdf->Cell(0,7,utf8_decode($an['nome_arquivo'].' ('.formataDataHora($an['data_upload']).')'),0,1);
    }
} else {
    $pdf->Cell(0,7,utf8_decode('Sem anexos.'),0,1);
}
$pdf->Divider();

// ====== COMENTÁRIOS ======
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(23,78,155);
$pdf->Cell(0,8,utf8_decode('Comentários'),0,1);
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(50,50,50);

if (!empty($comentarios)) {
    foreach ($comentarios as $c) {
        $pdf->SetFont('Arial','I',9);
        $pdf->SetTextColor(30,144,255);
        $pdf->Cell(0,6,utf8_decode($c['usuario'].' ['.formataDataHora($c['data_hora']).']:'),0,1);
        $pdf->SetFont('Arial','',10);
        $pdf->SetTextColor(50,50,50);
        $pdf->MultiCell(0,6,utf8_decode($c['comentario']),0,1);
        $pdf->Ln(1);
    }
} else {
    $pdf->Cell(0,7,utf8_decode('Sem comentários.'),0,1);
}
$pdf->Divider();

// ====== LIÇÕES APRENDIDAS OU OBSERVAÇÕES ======
if ($licoes_aprendidas) {
    $pdf->SetFont('Arial','B',13);
    $pdf->SetTextColor(23,78,155);
    $pdf->Cell(0,8,utf8_decode('Lições Aprendidas / Observações Finais'),0,1);
    $pdf->SetFont('Arial','',10);
    $pdf->SetTextColor(50,50,50);
    $pdf->MultiCell(0,7,utf8_decode($licoes_aprendidas),0,1);
    $pdf->Divider();
}


// ===== PÁGINA DE FECHAMENTO =====
$pdf->AddPage();

// Título da página
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetTextColor($pdf->primaryColor[0], $pdf->primaryColor[1], $pdf->primaryColor[2]);
$pdf->Cell(0, 15, utf8_decode('Resumo Executivo'), 0, 1, 'C');
$pdf->Ln(10);

// Box de resumo
$pdf->SetFillColor(248, 249, 252);
$pdf->RoundedRect(15, $pdf->GetY(), 180, 80, 5, 'F');

$pdf->SetXY(25, $pdf->GetY() + 10);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(60, 60, 60);

$statusText = [
    'andamento' => 'em andamento',
    'bloqueado' => 'bloqueado',
    'concluido' => 'concluído'
];

$resumo = sprintf(
    "O projeto \"%s\" está atualmente %s, com %d%% de progresso alcançado. " .
    "Sob a responsabilidade de %s, o projeto conta com %d tarefas cadastradas, " .
    "das quais %d já foram concluídas.\n\n" .
    "Este relatório consolida todas as informações relevantes do projeto, " .
    "incluindo equipe envolvida, indicadores de impacto, tarefas e atualizações " .
    "realizadas até a presente data.",
    $projeto['NomeProjeto'],
    $statusText[$projeto['Status']] ?? $projeto['Status'],
    $percent,
    $responsavel_principal,
    $total_sub,
    $completed
);

$pdf->MultiCell(160, 7, utf8_decode($resumo), 0, 'J');
$pdf->Ln(20);

// Assinaturas
$pdf->SetY(-80);

// Linha para assinatura do gestor
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(40, $pdf->GetY(), 90, $pdf->GetY());

// Linha para assinatura do responsável
$pdf->Line(120, $pdf->GetY(), 170, $pdf->GetY());

$pdf->Ln(8);

// Nomes
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor($pdf->darkColor[0], $pdf->darkColor[1], $pdf->darkColor[2]);

$pdf->SetX(40);
$pdf->Cell(50, 7, utf8_decode($gestor_nome), 0, 0, 'C');

$pdf->SetX(120);
$pdf->Cell(50, 7, utf8_decode($responsavel_principal), 0, 1, 'C');

// Cargos
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(120, 120, 120);

$pdf->SetX(40);
$pdf->Cell(50, 5, utf8_decode('Gestor do Setor'), 0, 0, 'C');

$pdf->SetX(120);
$pdf->Cell(50, 5, utf8_decode('Responsável do Projeto'), 0, 1, 'C');


$pdf->Ln(10);

$base_anexo = __DIR__ . '/uploads/';
foreach ($anexos as $anexo) {
    $arquivo = $anexo['nome_arquivo'];
    $arquivo_limpo = trim(str_replace(['\\', '//'], '/', $arquivo));
    $caminho = '';
    // Busca arquivos que terminam com o nome (pode ter hash no início)
    $files = glob($base_anexo . '*' . $arquivo_limpo);
    if (count($files) > 0 && file_exists($files[0])) {
        $caminho = $files[0];
    } else {
        continue;
    }

    $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',14);
        $pdf->Cell(0,10,utf8_decode('Anexo: ' . $arquivo_limpo),0,1,'C');
        // Y mínimo para nunca encostar no cabeçalho
        $yMin = 40;
        $yTitulo = $pdf->GetY() + 10;
        $y = max($yMin, $yTitulo);

        list($largura, $altura) = getimagesize($caminho);
        $maxWidth = 180;
        $maxHeight = 270 - $y;
        $ratio = min($maxWidth / $largura, $maxHeight / $altura, 1);
        $newWidth = $largura * $ratio;
        $newHeight = $altura * $ratio;
        $x = (210 - $newWidth) / 2;
        $pdf->Image($caminho, $x, $y, $newWidth, $newHeight, strtoupper($ext));
    }

    if ($ext === 'pdf') {
        $pageCount = $pdf->setSourceFile($caminho);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pdf->AddPage();
            if ($pageNo === 1) {
                // Na primeira página, coloca o título do anexo
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->SetY(50); // Espaço após cabeçalho
                $pdf->Cell(0, 10, utf8_decode('Anexo PDF: ' . $arquivo_limpo), 0, 1, 'C');
                $y = $pdf->GetY() + 15; // Leve espaçamento abaixo do título
                $tplIdx = $pdf->importPage($pageNo);
                // Importa o conteúdo do PDF um pouco mais para baixo
                $pdf->useTemplate($tplIdx, 0, $y, 210);
            } else {
                // Demais páginas do anexo normalmente
                $tplIdx = $pdf->importPage($pageNo);
                $pdf->useTemplate($tplIdx, 0, 0, 210);
            }
        }
    }
    
    
}

$pdf->Output('I', 'projeto_'.$projeto_id.'.pdf');
exit;


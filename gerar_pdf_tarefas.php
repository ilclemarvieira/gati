<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Configura o fuso horário para Brasília
date_default_timezone_set('America/Sao_Paulo');

$id_usuario_logado = $_SESSION['usuario_id'] ?? null;

require_once('path/to/fpdf/fpdf.php');
include 'db.php';


// Buscar o nome do usuário logado
$id_usuario_logado = $_SESSION['usuario_id'];
$nome_usuario = ''; // Inicializa a variável para armazenar o nome do usuário

// Prepara a consulta SQL para buscar o nome do usuário
$stmt = $pdo->prepare('SELECT Nome FROM usuarios WHERE Id = :id_usuario');
$stmt->execute(['id_usuario' => $id_usuario_logado]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nome_usuario = $row['Nome']; // Atribui o nome do usuário
}

class PDF extends FPDF {
    public $nomeUsuario; // Propriedade existente
    public $totalTarefas; // Nova propriedade para o total de tarefas
    public $totalImportantes; // Nova propriedade para o total de tarefas importantes
    public $totalNormais; // Nova propriedade para o total de tarefas normais

    // Construtor que aceita o nome do usuário
    function __construct($nomeUsuario = '', $totalTarefas = 0, $totalImportantes = 0, $totalNormais = 0) {
        parent::__construct(); // Chama o construtor da classe pai FPDF
        $this->nomeUsuario = $nomeUsuario;
        $this->totalTarefas = $totalTarefas;
        $this->totalImportantes = $totalImportantes;
        $this->totalNormais = $totalNormais;
    }

    // Implement the NbLines method
    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    // Método para verificar se é necessário adicionar uma nova página antes de inserir uma nova linha
    function CheckPageBreak($h) {
        if($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }


   function Header() {
    // Seu logotipo - ajuste o tamanho conforme necessário
    $this->Image('path/to/fpdf/img/logo.jpg', 10, 8, 20);
    
    // Move para a direita - centro do título
    $this->Cell(80);
    
    // Título
    $this->SetFont('Arial', 'B', 15);
    $titulo = 'Tarefas de ' . $this->nomeUsuario;
    $this->Cell(30, 10, utf8_decode($titulo), 0, 1, 'C');
    
    // Linha abaixo do título
    $this->SetDrawColor(100, 100, 100); // Cor da linha
    $this->SetLineWidth(0.2); // Espessura da linha
    $this->Line(20, 28, 210-20, 28);
    
    // Quebra de linha
    $this->Ln(10);
    
    // Data e hora - alinhado à direita
    $this->SetTextColor(100, 100, 100); // Cor cinza para a data e hora
    $this->SetFont('Arial', '', 10);
    $dataHora = date('d/m/Y H:i:s');
    $this->Cell(0, 10, utf8_decode($dataHora), 0, 1, 'R');   


    // Após definir a data e hora, vamos incluir a contagem de tarefas
    $this->Ln(5); // Pode precisar ajustar
    $this->SetFont('Arial', 'I', 10);
    $infoTarefas = "Total de Tarefas: $this->totalTarefas (Importantes: $this->totalImportantes, Normais: $this->totalNormais)";
    $this->Cell(0, 10, utf8_decode($infoTarefas), 0, 1, 'C');

    // Quebra de linha após a informação das tarefas
    $this->Ln(5);
}



    // Sobrescrevendo o método Footer para centralizar o número da página
     function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        
        // Defina o alias para o número total de páginas antes de chamar Footer()
        $this->AliasNbPages('{nb}');

        // Converta a palavra 'Página' para ISO-8859-1
        $this->Cell(0, 10, utf8_decode('Página ').$this->PageNo().'/{nb}', 0, 0, 'C');
    }


     // Método FancyRow ajustado para destacar tarefas importantes
  function FancyRow($data, $dataHeight, $is_important, $is_complete) {
        // Cores de preenchimento para células importantes e normais
        $fillColorImportant = [255, 215, 0]; // Por exemplo, amarelo ouro
        $fillColorRegular = [255, 255, 255]; // Branco

        // Cores de texto para tarefas concluídas e não concluídas
        $textColorComplete = [128, 128, 128]; // Cinza
        $textColorRegular = [0, 0, 0]; // Preto

        // Aplicar cor de preenchimento
        $this->SetFillColor($is_important ? $fillColorImportant[0] : $fillColorRegular[0], $is_important ? $fillColorImportant[1] : $fillColorRegular[1], $is_important ? $fillColorImportant[2] : $fillColorRegular[2]);
        
        // Aplicar cor de texto
        $this->SetTextColor($is_complete ? $textColorComplete[0] : $textColorRegular[0], $is_complete ? $textColorComplete[1] : $textColorRegular[1], $is_complete ? $textColorComplete[2] : $textColorRegular[2]);

        // Definir a fonte para negrito se for importante
        if ($is_important) {
            $this->SetFont('', 'B');
        }

        // Definir padding
        $cellPadding = 2;
        $this->setCellPaddings($cellPadding, $cellPadding, $cellPadding, $cellPadding);

        // Desenhar as células com altura baseada no número de linhas necessárias
        $this->Cell(50, $dataHeight, $data[0], 1, 0, 'L', true);
        $this->Cell(105, $dataHeight, $data[1], 1, 0, 'L', true);
        $this->Cell(35, $dataHeight, $data[2], 1, 0, 'C', true);
        
        // Resetar fonte e cor de texto para outras linhas
        $this->SetFont('');
        $this->SetTextColor($textColorRegular[0], $textColorRegular[1], $textColorRegular[2]);

        $this->Ln();

    // If the task is completed, draw a strike-through line
    if ($is_complete) {
        $x = $this->GetX();
        $y = $this->GetY() - $dataHeight / 2;
        $this->SetDrawColor(0, 0, 0); // Black line for strikethrough
        $this->Line($x, $y, $x + 190, $y);
    }
}




    // Método adicional para verificar se a linha é importante e destacá-la
    function CheckImportant($is_important) {
        if ($is_important) {
            // Definindo a cor de fundo para amarelo claro
            $this->SetFillColor(255, 255, 224);
            return true;
        } else {
            $this->SetFillColor(255, 255, 255); // Branco, sem destaque
            return false;
        }
    }
}

// Conta apenas as tarefas pendentes que são importantes
$stmtImportant = $pdo->prepare('SELECT COUNT(*) AS total_importantes FROM tarefas WHERE id_usuario = :id_usuario AND is_important = 1 AND is_complete = 0');
$stmtImportant->execute(['id_usuario' => $id_usuario_logado]);
$rowImportant = $stmtImportant->fetch(PDO::FETCH_ASSOC);
$total_importantes = $rowImportant['total_importantes'];

// Conta apenas as tarefas pendentes que não são consideradas importantes
$stmtNormal = $pdo->prepare('SELECT COUNT(*) AS total_normais FROM tarefas WHERE id_usuario = :id_usuario AND is_important = 0 AND is_complete = 0');
$stmtNormal->execute(['id_usuario' => $id_usuario_logado]);
$rowNormal = $stmtNormal->fetch(PDO::FETCH_ASSOC);
$total_normais = $rowNormal['total_normais'];

// Soma dos totais de tarefas importantes e normais que estão pendentes
$total_tarefas = $total_importantes + $total_normais;




// Criando o PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('Arial', '', 10);


// Criando o PDF com o nome do usuário
$pdf = new PDF(utf8_decode($nome_usuario), $total_tarefas, $total_importantes, $total_normais);
$pdf->AliasNbPages('{nb}');


// Adicione páginas, conteúdo e, em seguida, gere o PDF
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(190, 8, utf8_decode('Descrição'), 1, 1, 'C', true);

if ($id_usuario_logado) {
    $stmt = $pdo->prepare('SELECT * FROM tarefas WHERE id_usuario = :id_usuario AND is_complete = 0 ORDER BY is_important DESC, id DESC');
    $stmt->execute(['id_usuario' => $id_usuario_logado]);

    while ($tarefa = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Aqui você deve converter entidades HTML para os caracteres apropriados
        // e então fazer a conversão de UTF-8 para ISO-8859-1 se necessário.
        $nomeStr = html_entity_decode($tarefa['nome_tarefa'], ENT_QUOTES, 'UTF-8');
        $nomeStr = iconv('UTF-8', 'windows-1252', $nomeStr); // Convertendo para a codificação do PDF
        $descricaoStr = html_entity_decode($tarefa['descricao_tarefa'], ENT_QUOTES, 'UTF-8');
        $descricaoStr = iconv('UTF-8', 'windows-1252', $descricaoStr); // Convertendo para a codificação do PDF
        $statusStr = $tarefa['is_complete'] ? 'Concluída' : 'Pendente';
        $statusStr = html_entity_decode($statusStr, ENT_QUOTES, 'UTF-8');
        $statusStr = iconv('UTF-8', 'windows-1252', $statusStr);

        // Verificar se a tarefa é importante para definir a cor de fundo
        $fill = $tarefa['is_important'] == 1;
        $pdf->SetFillColor($fill ? 255 : 255, $fill ? 255 : 255, $fill ? 224 : 255);

        // Define o estilo da fonte para negrito para o nome da tarefa e adiciona o status em itálico
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->MultiCell(0, 6, $nomeStr, 1, 1, $fill);

        // Imprime o status no canto superior direito
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->MultiCell(0, 6, $statusStr, 1, 1, $fill);

        // Resetar a posição X e imprimir a descrição
        $pdf->SetX(10);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(190, 5, $descricaoStr, 1, 'L', $fill);
        
        // Insere uma quebra de linha adicional entre as tarefas
        $pdf->Ln(2);
    }

    $pdf->Output('I', 'lista_tarefas.pdf');
} else {
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_decode('Erro: Usuário não logado ou sessão expirada.'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 10, utf8_decode("Por favor, retorne à página de login e tente novamente. Se o problema persistir, contate o administrador do sistema."), 0, 'C');
    $pdf->Output('I', 'erro_sessao.pdf');
}

?>

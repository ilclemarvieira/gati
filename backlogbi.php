<?php
// Definindo o tempo máximo da sessão para 6 horas (21600 segundos)
ini_set('session.gc_maxlifetime', 21600);

// Iniciando a sessão
session_start();

// Lembre-se de configurar o cookie de sessão para ter a mesma duração
$params = session_get_cookie_params();
setcookie(session_name(), $_COOKIE[session_name()], time() + 21600,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
);

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

// Definição de zona horária para evitar problemas com datas
date_default_timezone_set('America/Sao_Paulo');

// Se o parâmetro 'year' não estiver definido na URL, utiliza o ano atual como filtro
if (!isset($_GET['year']) || empty($_GET['year'])) {
    $_GET['year'] = date('Y'); // Define o ano atual
}

$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        // Redireciona para a página anterior ou para uma página padrão
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'index.php'; // Define 'index.php' como fallback
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}

// No início de cada página restrita, chame verificarPermissao com os perfis permitidos
$perfisPermitidos = [1, 2, 5]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);

$backlogbis = $pdo->query("
    SELECT b.*, u.Nome as NomeResponsavel 
    FROM backlogbi b
    LEFT JOIN usuarios u ON b.Responsavel = u.Id
")->fetchAll(PDO::FETCH_ASSOC);


// Adicione no início do arquivo para buscar os usuários cadastrados
$usuarios = $pdo->query("SELECT Id, Nome FROM usuarios WHERE PerfilAcesso IN (2, 5)")->fetchAll(PDO::FETCH_ASSOC);
$statusOptions = ["Não Começou", "Em análise", "Paralisada", "Em criação", "Cancelada", "Autorizada para Produção"];

$whereClauses = [];
$params = [];

if (isset($_GET['responsavel']) && $_GET['responsavel'] != '') {
    $whereClauses[] = 'b.Responsavel = :responsavel';
    $params[':responsavel'] = $_GET['responsavel'];
}

if (isset($_GET['status']) && $_GET['status'] != '') {
    $whereClauses[] = 'b.Status_ideia = :status';
    $params[':status'] = $_GET['status'];
}

if (isset($_GET['prioridade']) && $_GET['prioridade'] != '') {
    $whereClauses[] = 'b.Prioridade = :prioridade';
    $params[':prioridade'] = $_GET['prioridade'];
}

if (isset($_GET['encaminhado_os']) && $_GET['encaminhado_os'] != '') {
    $whereClauses[] = 'b.Encaminhado_os = :encaminhado_os';
    $params[':encaminhado_os'] = $_GET['encaminhado_os'];
}

if (isset($_GET['year']) && $_GET['year'] != '') {
    $whereClauses[] = 'YEAR(b.Dt_criacao) = :year';
    $params[':year'] = $_GET['year'];
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Defina os itens por página e o offset antes de usar na consulta SQL
$itensPorPagina = 12; // Definir quantas OS por página
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Construa a consulta base com ordenação e paginação
$query = "
    SELECT b.*, u.Nome as NomeResponsavel 
    FROM backlogbi b
    LEFT JOIN usuarios u ON b.Responsavel = u.Id
    {$whereSql}
    ORDER BY FIELD(b.Prioridade, 'Alta', 'Média', 'Baixa'), b.Dt_criacao
    LIMIT :offset, :itensPorPagina
";

// Prepare a consulta
$statement = $pdo->prepare($query);

// Vincule os parâmetros
foreach ($params as $key => $val) {
    $statement->bindValue($key, $val);
}

// Adicione cláusulas WHERE se necessário
if (!empty($whereClauses)) {
    $query .= ' WHERE ' . implode(' AND ', $whereClauses);
}

// Adicione cláusula LIMIT
$query .= " LIMIT :offset, :itensPorPagina";



// Vincule parâmetros de paginação
$statement->bindValue(':offset', $offset, PDO::PARAM_INT);
$statement->bindValue(':itensPorPagina', $itensPorPagina, PDO::PARAM_INT);

// Primeiro, obtenha o número total de registros
$totalRegistrosStmt = $pdo->prepare("SELECT COUNT(*) FROM backlogbi b {$whereSql}");
$totalRegistrosStmt->execute($params);
$totalRegistros = $totalRegistrosStmt->fetchColumn();

// Agora, calcule o total de páginas
$totalPaginas = ceil($totalRegistros / $itensPorPagina);


// Execute a consulta
try {
    $statement->execute();
    $backlogbis = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erro ao executar a consulta: ' . $e->getMessage());
}



// Buscar anos distintos de criação dos backlogbis
$yearsQuery = "SELECT DISTINCT YEAR(Dt_criacao) AS Year FROM backlogbi ORDER BY Year DESC";
$yearsStmt = $pdo->query($yearsQuery);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);


// Total de backlogbis
$totalbacklogbisStmt = $pdo->prepare("SELECT COUNT(*) FROM backlogbi");
$totalbacklogbisStmt->execute();
$totalbacklogbis = $totalbacklogbisStmt->fetchColumn();

// Total de backlogbis com prioridade alta
$totalPrioridadeAltaStmt = $pdo->prepare("SELECT COUNT(*) FROM backlogbi WHERE Prioridade = 'Alta'");
$totalPrioridadeAltaStmt->execute();
$totalPrioridadeAlta = $totalPrioridadeAltaStmt->fetchColumn();

// Total de backlogbis com prioridade média
$totalPrioridadeMediaStmt = $pdo->prepare("SELECT COUNT(*) FROM backlogbi WHERE Prioridade = 'Média'");
$totalPrioridadeMediaStmt->execute();
$totalPrioridadeMedia = $totalPrioridadeMediaStmt->fetchColumn();

// Total de backlogbis com prioridade baixa
$totalPrioridadeBaixaStmt = $pdo->prepare("SELECT COUNT(*) FROM backlogbi WHERE Prioridade = 'Baixa'");
$totalPrioridadeBaixaStmt->execute();
$totalPrioridadeBaixa = $totalPrioridadeBaixaStmt->fetchColumn();

// Totais para cada status
$statusOptions = ["Não Começou", "Em análise", "Paralisada", "Em criação", "Cancelada", "Autorizada para Produção"];
$statusTotais = [];
foreach ($statusOptions as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM backlogbi WHERE Status_ideia = ?");
    $stmt->execute([$status]);
    $statusTotais[$status] = $stmt->fetchColumn();
}



?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">

  <head>
    <?php include 'head.php'?>

    <style>
    .modal-content {
        /* Estilos para o conteúdo do modal */
    }

    .os-details p {
        /* Estilos para os parágrafos dos detalhes da OS */
    }

    .anexo-container {
        margin-top: 20px;
       
    }

    .anexo-container img, .anexo-container iframe {
        max-width: 100%; /* Garantir que o anexo não ultrapasse a largura do modal */
        height: auto;
    }
  .highlighted {
    background-color: #ffff0026;
}

/* Estado normal das linhas destacadas */
.content-table tbody tr.highlighted {
    background-color: #ffff0026;
}

/* Estado de hover para todas as linhas */
.content-table tbody tr:hover {
    background-color: #f5f5f5; /* ou qualquer outra cor que deseja para o hover */
}

/* Estado de hover especificamente para linhas destacadas */
.content-table tbody tr.highlighted:hover {
    background-color: #ffff0026 !important; /* Mantém a cor de fundo amarela mesmo no hover */
}

.filter-container {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 20px; /* Aumente o valor do gap para dar mais espaço entre os elementos */
    padding: 10px;
    border: 1px solid #3c4147;
    border-radius: 5px;
    margin-bottom: 20px;
}

.filter-container select, 
.filter-container button {
    height: 38px;
    border: 1px solid #5b5f69;
    background-color: #272b34;
    color: #b2b9b2;
    padding: 0 15px;
    font-size: 14px;
    margin-right: 10px; /* Espaçamento à direita para todos os selects */
}

.filter-container button {
    white-space: nowrap; /* Evita a quebra de linha dentro do botão */
    text-align: center; /* Alinha o texto do botão ao centro */
}

.filter-container select {
    flex-grow: 1; /* Permite que os selects cresçam para preencher o espaço */
}

/* Media query para telas pequenas */
@media (max-width: 768px) {
    .filter-container {
        flex-direction: column; /* Os elementos são empilhados verticalmente */
    }

    .filter-container select, 
    .filter-container button {
        width: 100%; /* Os elementos ocupam toda a largura disponível */
    }
    
    .filter-container button {
        order: 1; /* O botão é movido para cima */
        margin-top: 10px; /* Espaçamento acima do botão */
    }
}



/* Estilos para os ícones e espaçamento */
.round-lg {
  width: 50px; /* Tamanho do ícone */
  height: 50px; /* Tamanho do ícone */
  border-radius: 25px; /* Círculo perfeito */
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px; /* Espaçamento entre o ícone e o texto */
}

/* Ícones */
.fa {
  color: #b2b9aa; /* Ícones brancos para melhor contraste */
  font-size: 21px; /* Ícones maiores */
}

.card {
  background-color: #23272a; /* Fundo escuro, mas ligeiramente mais claro que o fundo da página para se destacar */
  color: #ffffff; /* Texto branco para contraste */
  border: none; /* Sem bordas */
  border-radius: 15px; /* Bordas mais arredondadas */
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5); /* Sombra mais intensa para um "pop" 3D */
  transition: transform 0.3s ease, box-shadow 0.3s ease; /* Transição suave para efeitos */
  overflow: hidden; /* Garante que todo o conteúdo fique contido dentro das bordas arredondadas */
  cursor: pointer; /* Cursor de mão */
}

.card:hover {
  transform: translateY(-5px); /* Move o card para cima ao passar o mouse */
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.8); /* Sombra mais intensa no hover */
}

.card-body {
  display: flex;
  align-items: center;
  justify-content: space-between; /* Espaçamento igual entre itens */
  padding: 25px; /* Mais espaço interno */
}

.card-title {
  font-size: 20px; /* Título maior */
  font-weight: 600; /* Peso da fonte aumentado */
}

.card-text {
  font-size: 18px; /* Texto maior */
  margin-top: 5px; /* Ajuste no espaçamento */
}

/* Cores personalizadas para ícones com base no tipo */
.bg-info { background-color: #17a2b8!important; }
.bg-high { background-color: #d9534f!important; } /* cor mais vibrante para alta prioridade */
.bg-medium { background-color: #f0ad4e!important; } /* cor mais vibrante para média prioridade */
.bg-low { background-color: #5bc0de!important; } /* cor mais vibrante para baixa prioridade */

.text-muted {
  display: block; /* Faz com que o texto ocupe sua própria linha */
}



.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    padding-top: 10px;
}

.modal-content {
    background-color: #fff;
    margin: 2% auto;
    padding: 20px;
    border-radius: 8px;
    width: 50%; /* Ajuste a largura conforme necessário */
    box-shadow: 0 4px 10px rgba(0,0,10,10.15);
}


/* Modal Header Styling */
.modal-content h2 {
    color: #333;
    font-size: 24px;
    font-weight: 500;
    border-bottom: 1px solid #e3e3e354;
    padding-bottom: 10px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.close {
    color: #aaaaaa;
    float: right;
    font-size: 30px;
    padding-bottom: 10px;
    margin-bottom: 10px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #000;
    text-decoration: none;
    cursor: pointer;
}

.form-row {
    display: flex;
    flex-direction: column;
    margin-bottom: 15px;
}

.form-row label {
    font-weight: 600;
    margin-bottom: 5px;
}

.form-row input[type=text],
.form-row input[type=date],
.form-row select,
.form-row textarea {
    width: 100%;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
    transition: border-color 0.3s;
}

.form-row input[type=text]:focus,
.form-row input[type=date]:focus,
.form-row select:focus,
.form-row textarea:focus {
    border-color: #007bff;
    outline: none;
}

.submit-btn {
    width: auto;
    padding: 10px 20px;
    font-weight: 500;
    background-color: rgba(252,75,108,.5)!important;
    color: white;
    border: rgba(252,75,108,.5)!important;
    border-radius: 40px;
    cursor: pointer;
    transition: background-color 0.2s;
    align-self: flex-end;
}

.submit-btn:hover {
    background-color: #0056b3;
}

/* Responsividade para telas menores */
@media screen and (max-width: 768px) {
    .modal-content {
        width: 90%;
        padding: 20px;
    }

    .form-row input[type=text],
    .form-row input[type=date],
    .form-row select,
    .form-row textarea {
        padding: 10px;
    }
}


/* Details Paragraph Styling */
.os-details p {
    padding: 2px 0;
    border-bottom: 1px solid #eeeeee0d;
    display: flex;
    color: #fff;
    justify-content: space-between; /* Align the label and value on opposite ends */
}

.os-details b {
    font-weight: 500;
    color: #333;
    margin-right: 15px; /* Spacing between label and value */
}

/* Anexo Container Styling */
.anexo-container {
    margin-top: 20px;
}

/* Flex Container for Two Column Layout */
.flex-container {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap; /* Allow wrapping for smaller screens */
}

/* Flex Item Styling */
.flex-item {
    flex-basis: 48%; /* Two items per row */
    margin-bottom: 10px; /* Spacing between items */
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .flex-item {
        flex-basis: 100%; /* Stack items on top of each other on small screens */
    }
}

/* Style the form rows */
.form-row {
    display: flex;
    flex-direction: column;
    margin-bottom: 15px;
}

.form-row label {
    font-weight: 600;
    margin-bottom: 5px;
}

.form-row input[type=text],
.form-row input[type=date],
.form-row select,
.form-row textarea {
    width: 100%;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
    transition: border-color 0.3s;
}

.form-row input[type=text]:focus,
.form-row input[type=date]:focus,
.form-row select:focus,
.form-row textarea:focus {
    border-color: #007bff;
    outline: none;
}


.readonly {
        background-color: #b3c0c7 !important; /* Cor de fundo cinza para indicar que está desativado */
        color: #272b34; !important; /* Cor do texto para indicar que está desativado */
        font-weight: bold;
        cursor: not-allowed !important; /* Cursor de não permitido para indicar que o campo é somente leitura */
    }



/* Responsive layout - when the screen is less than 600px wide, make the modal-content full width */
@media screen and (max-width: 600px) {
    .modal-content {
        width: 95%;
    }
}

/* Estilo para linhas finalizadas */
.finalizada {
    text-decoration: line-through;
    opacity: 0.5; /* Opcional: para tornar o texto mais claro indicando que está concluído */
}

.linha-taxada {
    text-decoration: line-through;
    opacity: 0.7; /* Opcional para tornar a linha mais clara */
}

/* Estilo personalizado para os checkboxes de prioridade */
.priority-check {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    position: relative;
    cursor: pointer;
    width: 20px;
    height: 20px;
    background-color: #323743;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 4px;
    vertical-align: middle;
    transition: background-color 0.2s, border-color 0.2s;
}

/* Quando o checkbox de prioridade estiver marcado */
.priority-check:checked {
    background-color: #198754; /* Um azul claro para distinção */
    border-color: #198754;
}

/* Estilo para o ícone de verificação quando o checkbox de prioridade está marcado */
.priority-check:checked::after {
    content: '\2713'; /* Código Unicode para o ícone de verificação */
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white; /* Ícone de verificação branco */
    font-size: 14px; /* Tamanho do ícone de verificação */
}

/* Hover efeito para o checkbox de prioridade */
.priority-check:hover:not(:checked) {
    border-color: #198754; /* Muda a cor da borda ao passar o mouse para um azul claro */
}

/* Foco no checkbox de prioridade */
.priority-check:focus {
    outline: none; /* Remove o contorno padrão */
    box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25); /* Adiciona um sombreado azul claro para indicar foco */
}

/* Estilo para desabilitar o checkbox de prioridade */
.priority-check:disabled {
    background-color: #e9ecef; /* Fundo cinza claro para o checkbox desabilitado */
    border-color: #ced4da; /* Borda cinza */
    cursor: not-allowed; /* Cursor de não permitido */
}

/* Estilo para o label associado ao checkbox de prioridade para melhor acessibilidade */
.priority-check + label {
    margin-left: 8px; /* Espaço entre o checkbox de prioridade e o label */
    cursor: pointer; /* Muda o cursor para indicar que o label é clicável */
}

/* Garanta que o label acione o estado de foco do checkbox de prioridade quando clicado */
.priority-check + label:hover {
    color: #0dcaf0; /* Muda a cor do texto ao passar o mouse para um azul claro */
}

/* Estilos para o botão Filtrar */
.btn-filtrar {
  background-color: #4a4e69; /* Cor de fundo do botão */
  color: #ffffff; /* Cor do texto */
  border: 1px solid #6c7293; /* Cor da borda */
  border-radius: 0px; /* Bordas arredondadas */
  padding: 10px 20px; /* Espaçamento interno */
  font-size: 16px; /* Tamanho do texto */
  transition: all 0.3s; /* Transição suave para hover */
  display: flex; /* Usa flexbox para alinhar ícone e texto */
  align-items: center; /* Centraliza itens verticalmente */
  justify-content: center; /* Centraliza itens horizontalmente */
  box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.2); /* Sombra suave */
}

.btn-filtrar i {
  margin-right: 5px; /* Espaço entre ícone e texto */
}

/* Efeito ao passar o mouse */
.btn-filtrar:hover {
  color: #b2b9aa;
  background-color: #5b5f69; /* Cor de fundo mais clara ao hover */
  border-color: #5b5f69; /* Cor da borda mais clara ao hover */
  box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.3); /* Sombra mais pronunciada ao hover */
  transform: translateY(-2px); /* Leve elevação do botão ao hover */
}

/* Efeito ao clicar no botão */
.btn-filtrar:active {
  transform: translateY(1px); /* Pressionando o botão para baixo */
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.2); /* Sombra mais plana quando pressionado */
}

</style> 
   
  </head>

  <body>    
    <div class="preloader">
      <svg
        class="tea lds-ripple"
        width="37"
        height="48"
        viewbox="0 0 37 48"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M27.0819 17H3.02508C1.91076 17 1.01376 17.9059 1.0485 19.0197C1.15761 22.5177 1.49703 29.7374 2.5 34C4.07125 40.6778 7.18553 44.8868 8.44856 46.3845C8.79051 46.79 9.29799 47 9.82843 47H20.0218C20.639 47 21.2193 46.7159 21.5659 46.2052C22.6765 44.5687 25.2312 40.4282 27.5 34C28.9757 29.8188 29.084 22.4043 29.0441 18.9156C29.0319 17.8436 28.1539 17 27.0819 17Z"
          stroke="#1e88e5"
          stroke-width="2"
        ></path>
        <path
          d="M29 23.5C29 23.5 34.5 20.5 35.5 25.4999C36.0986 28.4926 34.2033 31.5383 32 32.8713C29.4555 34.4108 28 34 28 34"
          stroke="#1e88e5"
          stroke-width="2"
        ></path>
        <path
          id="teabag"
          fill="#1e88e5"
          fill-rule="evenodd"
          clip-rule="evenodd"
          d="M16 25V17H14V25H12C10.3431 25 9 26.3431 9 28V34C9 35.6569 10.3431 37 12 37H18C19.6569 37 21 35.6569 21 34V28C21 26.3431 19.6569 25 18 25H16ZM11 28C11 27.4477 11.4477 27 12 27H18C18.5523 27 19 27.4477 19 28V34C19 34.5523 18.5523 35 18 35H12C11.4477 35 11 34.5523 11 34V28Z"
        ></path>
        <path
          id="steamL"
          d="M17 1C17 1 17 4.5 14 6.5C11 8.5 11 12 11 12"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke="#1e88e5"
        ></path>
        <path
          id="steamR"
          d="M21 6C21 6 21 8.22727 19 9.5C17 10.7727 17 13 17 13"
          stroke="#1e88e5"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        ></path>
      </svg>
    </div>
    <!-- -------------------------------------------------------------- -->
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- -------------------------------------------------------------- -->
    <div id="main-wrapper">
      <!-- -------------------------------------------------------------- -->
      <!-- Topbar header - style you can find in pages.scss -->
      <!-- -------------------------------------------------------------- -->
      <header class="topbar">
        <?php include 'header.php'?>   
      </header> 

       <?php include 'sidebar.php'?> 

      <div class="page-wrapper">
       
        <div class="row page-titles">
          <div class="col-md-5 col-12 align-self-center">
            <h3 class="text-themecolor mb-0"><i class="mdi mdi-monitor"></i> Desenvolvimento BI</h3>            
          </div>          
        </div>


       
        <div class="container-fluid">  

       <div class="row">
  <!-- Card Total de backlogbis -->
  <div class="col-lg-3 col-md-6">
    <div class="card" onclick="filterbacklogbis('')">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-info">
            <i class="fa fa-list-alt"></i> <!-- Ícone atualizado para Total de backlogbis -->
          </div>
          <div>
            <h3 class="card-title">Total de Projetos</h3>
            <p id="totalbacklogbis" class="card-text text-muted"><?php echo $totalbacklogbis; ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card Prioridade Alta -->
  <div class="col-lg-3 col-md-6">
    <div class="card" onclick="filterbacklogbis('Alta')">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-dark">
            <i class="fa fa-arrow-up"></i> <!-- Ícone atualizado para Prioridade Alta -->
          </div>
          <div>
            <h3 class="card-title">Prioridade Alta</h3>
            <p id="prioridadeAlta" class="card-text text-muted"><?php echo $totalPrioridadeAlta; ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card Prioridade Média -->
  <div class="col-lg-3 col-md-6">
    <div class="card" onclick="filterbacklogbis('Média')">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-warning">
            <i class="fa fa-exclamation-triangle""></i> <!-- Ícone atualizado para Prioridade Média -->
          </div>
          <div>
            <h3 class="card-title">Prioridade Média</h3>
            <p id="prioridadeMedia" class="card-text text-muted"><?php echo $totalPrioridadeMedia; ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card Prioridade Baixa -->
  <div class="col-lg-3 col-md-6">
    <div class="card" onclick="filterbacklogbis('Baixa')">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-primary">
            <i class="fa fa-arrow-down"></i> <!-- Ícone atualizado para Prioridade Baixa -->
          </div>
          <div>
            <h3 class="card-title">Prioridade Baixa</h3>
            <p id="prioridadeBaixa" class="card-text text-muted"><?php echo $totalPrioridadeBaixa; ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- Botão para abrir o modal de adicionar nova OS -->
        <button class="btn waves-effect waves-light btn-info" onclick="openModal('addModal')"><i class="ti-plus text"></i> Novo Projeto</button>

        <br><br>

       <!-- Filtro por Ano, Responsável, Status, Prioridade e Encaminhado para OS -->
       <div class="filter-container d-flex flex-wrap align-items-center justify-content-between">
    <form id="filterbacklogbiForm" action="backlogbi.php" method="get" class="d-flex flex-wrap align-items-center w-100">


        <!-- Filtro por Ano -->
        <select name="year">
            
            <?php foreach ($years as $year): ?>
                <option value="<?php echo $year; ?>" <?php if (isset($_GET['year']) && $_GET['year'] == $year) echo 'selected'; ?>>
                    <?php echo $year; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Filtro por Responsável -->
        <select name="responsavel">
            <option value="">Todos os Responsáveis</option>
            <?php foreach ($usuarios as $usuario): ?>
                <option value="<?php echo $usuario['Id']; ?>" <?php if (isset($_GET['responsavel']) && $_GET['responsavel'] == $usuario['Id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($usuario['Nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Filtro por Status -->
        <select name="status">
            <option value="">Todos os Status</option>
            <?php foreach ($statusOptions as $status): ?>
                <option value="<?php echo $status; ?>" <?php if (isset($_GET['status']) && $_GET['status'] == $status) echo 'selected'; ?>>
                    <?php echo $status; ?>
                </option>
            <?php endforeach; ?>
        </select>

       
        <!-- Filtro Encaminhado para OS -->
        <select name="encaminhado_os">
            <option value="">Todos Autorizados</option>
            <option value="1" <?php if (isset($_GET['encaminhado_os']) && $_GET['encaminhado_os'] == "1") echo 'selected'; ?>>Sim</option>
            <option value="0" <?php if (isset($_GET['encaminhado_os']) && $_GET['encaminhado_os'] == "0") echo 'selected'; ?>>Não</option>
        </select>

        <!-- Botão de filtro -->
        <button type="submit" class="btn btn-filtrar">
  <i class="fa fa-search"></i> Filtrar
</button>


    </form>
</div>


<br>

          <div class="row">

            <div class="table-responsive">

            
            
              <table class="table table-striped table mb-0" data-tablesaw-mode="columntoggle" id="tablesaw-6204">
            <thead class="thead-light" align="center">
                <tr style="background-color: #5b5f69">
                    <th></th>
                    <th scope="col" style="white-space: nowrap;">Nº BI</th>
                    <th scope="col" style="white-space: nowrap;">Projeto</th>
                    <th scope="col" style="white-space: nowrap;">Dt Criação</th>
                    <th scope="col" style="white-space: nowrap;">Prioridade</th>
                    <th scope="col" style="white-space: nowrap;">Status</th>
                    <th scope="col" style="white-space: nowrap;">Responsável</th>
                    <th scope="col" style="white-space: nowrap;">Autorizado p/ Produção</th>
                    <th></th>
                </tr>
            </thead>
            <tbody style="font-size:12.5px; font-weight: 400;">
              <?php if ($backlogbis): ?>
    <?php foreach ($backlogbis as $backlogbi): ?>

        <tr class="<?php echo $backlogbi['Encaminhado_os'] ? 'linha-taxada' : ''; ?>">

          <td style="text-align: center; vertical-align: middle;">
              <input type="checkbox" class="priority-check" data-backlogbi-id="<?php echo $backlogbi['Id']; ?>">
          </td>

          <td style="text-align: center; vertical-align: middle;"><span class="badge bg-info"><?php echo htmlspecialchars($backlogbi['Id']); ?></span></td>

        
         <td style="text-align: left; font-weight: 500; text-transform: uppercase; vertical-align: middle" data-label="Projeto"><?php echo htmlspecialchars($backlogbi['Projeto']); ?></td>

                    <td style="text-align: center; font-weight: 500; vertical-align: middle" data-label="Data de Criação"><?php echo date('d/m/Y', strtotime($backlogbi['Dt_criacao'])); ?></td>

                    <td style="text-align: center; vertical-align: middle" data-label="Prioridade">
                        <?php 
                        switch ($backlogbi['Prioridade']) {
                            case 'Alta':
                                echo '<span class="badge bg-danger">Alta</span>';
                                break;
                            case 'Média':
                                echo '<span class="badge bg-warning">Média</span>';
                                break;
                            case 'Baixa':
                                echo '<span class="badge bg-info">Baixa</span>';
                                break;
                        }
                        ?>
                    </td>


                    <td style="text-align: center; vertical-align: middle" data-label="Status">
                        <?php 
                        switch ($backlogbi['Status_ideia']) {
                            case 'Não Começou':
                                echo '<span class="badge bg-secondary">Não Começou</span>';
                                break;
                            case 'Em análise':
                                echo '<span class="badge bg-primary">Em análise</span>';
                                break;
                            case 'Paralisada':
                                echo '<span class="badge bg-warning">Paralisada</span>';
                                break;
                            case 'Em criação':
                                echo '<span class="badge bg-success">Em criação</span>';
                                break;
                            case 'Cancelada':
                                echo '<span class="badge bg-danger">Cancelada</span>';
                                break;
                            case 'Autorizada para Produção':
                                echo '<span class="badge bg-info">Autorizada para Produção</span>';
                                break;
                        }
                        ?>
                    </td>


                    <td style="text-align: center; font-weight: 500; vertical-align: middle" data-label="Responsável"><?php echo htmlspecialchars($backlogbi['NomeResponsavel'] ?? 'N/A'); ?></td>

                    <td style="text-align: center; vertical-align: middle" data-label="Autorizado Produção">
                        <?php if ($backlogbi['Encaminhado_os']): ?>
                            <span class="badge bg-info">Sim</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Não</span>
                        <?php endif; ?>
                    </td>



        <td style="text-align: center; vertical-align: middle" class="action-buttons">
    <div class="d-flex justify-content-center align-items-center">
        <!-- Botão Ver com ícone de olho -->
        <button onclick="viewbacklogbiDetails(<?php echo $backlogbi['Id']; ?>)" title="Ver" class="btn btn-sm btn-light-info text-white mx-1">
            <i data-feather="eye" class="feather-sm fill-white"></i>
        </button>

        <!-- Botão Editar com ícone de lápis -->
        <button onclick="loadbacklogbiDetails(<?php echo $backlogbi['Id']; ?>)" title="Editar" class="btn btn-sm btn-light-info text-white mx-1">
            <i data-feather="edit" class="feather-sm fill-white"></i>
        </button>

        <!-- Botão Gerar PDF com ícone de PDF -->
        <button onclick="window.open('gerarpdf_backlogbi.php?id=<?php echo $backlogbi['Id']; ?>', '_blank')" title="Gerar PDF" class="btn btn-sm btn-light-info text-white mx-1">
            <i data-feather="file-text" class="feather-sm fill-white"></i>
        </button>


        <!-- Botão Excluir com ícone de lixeira -->
        <button onclick="deletebacklogbi(<?php echo $backlogbi['Id']; ?>)" title="Excluir" class="btn btn-sm btn-light-danger text-white mx-1">
            <i data-feather="trash-2" class="feather-sm fill-white"></i>
        </button>
    </div>
</td>




    </tr>
    <?php endforeach; ?>
    <?php else: ?>
                <tr>
                    <td colspan="13" style="text-align:center;">Nenhum projeto encontrado</td>
                </tr>
            <?php endif; ?>
                </tbody>
        </table>
        </div>

        


            
          </div> <br>

         <div>
    <nav aria-label="Navegação de página exemplo">
        <ul class="pagination">
            <?php 
            // Gera a base da URL com os filtros atuais, exceto o parâmetro 'pagina'
            $parametrosFiltro = $_GET;
            unset($parametrosFiltro['pagina']); // Remove o parâmetro 'pagina' para evitar duplicações
            $baseURL = 'backlogbi?' . http_build_query($parametrosFiltro) . '&pagina=';
            
            for ($i = 1; $i <= $totalPaginas; $i++): 
                // Gera o link completo para a página atual incluindo os filtros
                $urlPagina = $baseURL . $i;
                $classeAtiva = $paginaAtual === $i ? 'active' : '';
            ?>
                <li class="page-item <?php echo $classeAtiva; ?>">
                    <a class="page-link" href="<?php echo $urlPagina; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

        <!-- Modal de Cadastro -->
<div id="addModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalCadastroLabel"><i class="mdi mdi-clipboard-text"></i>&nbsp;Cadastrar Projeto BI</h2>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form id="addbacklogbiForm" action="seu_script.php" method="post" enctype="multipart/form-data" onsubmit="event.preventDefault(); submitAddForm();">
                <div class="modal-body">
                    <!-- Campos para a inserção de dados no backlogbi -->
                    <div class="mb-3">
                        <!-- Projeto -->
                        <label for="addProjeto" class="form-label">Projeto</label>
                        <input type="text" class="form-control bg-secondary text-white" id="addProjeto" name="projeto" required>
                    </div>
                    <div class="mb-3">
                        <!-- Data de Criação -->
                        <label for="addDtCriacao" class="form-label">Data de Criação</label>
                        <input type="date" class="form-control bg-secondary text-white" id="addDtCriacao" name="dt_criacao" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <!-- Prioridade -->
                        <label for="addPrioridade" class="form-label">Prioridade</label>
                        <select id="addPrioridade" name="prioridade" class="form-select bg-secondary text-white" required>
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <!-- Status -->
                        <label for="addStatusIdeia" class="form-label">Status</label>
                        <select class="form-control bg-secondary text-white" id="addStatusIdeia" name="status_ideia" required>
                            <?php foreach ($statusOptions as $status): ?>
                        <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                    <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <!-- Responsável -->
                        <label for="addResponsavel" class="form-label">Responsável</label>
                        <select class="form-control bg-secondary text-white" id="addResponsavel" name="responsavel" required>
                            <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?php echo $usuario['Id']; ?>"><?php echo htmlspecialchars($usuario['Nome']); ?></option>
                    <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <!-- Encaminhado para OS -->
                        <label for="addEncaminhadoOs" class="form-label">Autorizado Produção</label>
                        <select id="addEncaminhadoOs" name="encaminhado_os" class="form-select bg-secondary text-white" required>
                            <option value="1">Sim</option>
                            <option value="0" selected>Não</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <!-- Descrição -->
                        <label for="addDescricao" class="form-label">Descrição</label>
                        <textarea id="addDescricao" name="descricao" class="form-control bg-secondary text-white" style="height: 300px"></textarea>
                    </div>
                    <div class="form-row">
                        <!-- Anexo -->
                        <label for="addAttachment" class="form-label">Anexar Documento</label>
                        <input type="file" id="addAttachment" name="anexo" class="form-control bg-secondary text-white">
                    </div>
                     <!-- Botão de submissão -->
        <div class="d-flex justify-content-end mt-4"> <!-- Adiciona margem superior e alinha à direita -->
            <button type="submit" class="btn btn-primary font-weight-medium rounded-pill px-4">
                <div class="d-flex align-items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-send feather-sm fill-white me-2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                    Cadastrar
                </div>
            </button>
        </div>
                </div>
                
            </form>
        </div>
    </div>
</div>






<!-- Modal de Visualização -->
<div id="viewModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalVisualizacaoLabel">
                    <i class="mdi mdi-eye-outline"></i>&nbsp;Visualizar Projeto BI
                </h2>

                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="os-details">
                    <div class="mb-3">
                        <h3 class="form-label">Projeto</h3>
                        <p id="viewProjeto"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Data de Criação</h3>
                        <p id="viewDtCriacao"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Prioridade</h3>
                        <p id="viewPrioridade"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Status</h3>
                        <p id="viewStatusIdeia"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Responsável</h3>
                        <p id="viewResponsavel"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Autorizado Produção</h3>
                        <p id="viewEncaminhadoOs"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Descrição</h3>
                        <p id="viewDescricao"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Anexo</h3>
                        <div id="viewAttachmentPreview"></div>
                    </div>
                </div>
            </div>
           

            <div class="d-flex justify-content-end mt-4"> <!-- Adiciona margem superior e alinha à direita -->
            <div class="form-row">
                <button type="button" class="submit-btn" onclick="closeModal('viewModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg> 
                    Fechar
                </button>

            </div>
            </div>

        </div>
    </div>
</div>








<!-- Modal de Edição -->
<div id="editModal" class="modal" style="display: none;">
     <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalEdicaoLabel"><i class="mdi mdi-pencil"></i>&nbsp;Editar Projeto BI</h2>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
        <form id="editbacklogbiForm" action="process_edit_backlogbi.php" method="post" enctype="multipart/form-data">
                <input type="hidden" id="editId" name="id">
                <input type="hidden" id="existingAttachment" name="existingAttachment">
                <div class="modal-body">
                    <!-- Projeto -->
                    <div class="mb-3">
                        <label for="editProjeto" class="form-label">Projeto</label>
                        <input type="text" class="form-control bg-secondary text-white" id="editProjeto" name="projeto" required>
                    </div>
            <!-- Data de Criação -->
                    <div class="mb-3">
                        <label for="editDtCriacao" class="form-label">Data de Criação</label>
                        <input type="date" class="form-control bg-secondary text-white" id="editDtCriacao" name="dt_criacao" required>
                    </div>
            <!-- Prioridade -->
                    <div class="mb-3">
                        <label for="editPrioridade" class="form-label">Prioridade</label>
                        <select id="editPrioridade" name="prioridade" class="form-select bg-secondary text-white" required>
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>
            <!-- Status -->
                    <div class="mb-3">
                        <label for="editStatusIdeia" class="form-label">Status</label>
                        <select class="form-control bg-secondary text-white" id="editStatusIdeia" name="status_ideia" required>
                            <?php foreach ($statusOptions as $status): ?>
                        <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                    <?php endforeach; ?>
                        </select>
                    </div>
            <!-- Responsável -->
                    <div class="mb-3">
                        <label for="editResponsavel" class="form-label">Responsável</label>
                        <select class="form-control bg-secondary text-white" id="editResponsavel" name="responsavel" required>
                            <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?php echo $usuario['Id']; ?>"><?php echo htmlspecialchars($usuario['Nome']); ?></option>
                    <?php endforeach; ?>
                        </select>
                    </div>
            <!-- Encaminhado para OS -->
                    <div class="mb-3">
                        <label for="editEncaminhadoOs" class="form-label">Autorizado Produção</label>
                        <select id="editEncaminhadoOs" name="encaminhado_os" class="form-select bg-secondary text-white" required>
                            <option value="0" <?php echo $backlogbi['Encaminhado_os'] == "0" ? 'selected' : ''; ?>>Não</option>
                    <option value="1" <?php echo $backlogbi['Encaminhado_os'] == "1" ? 'selected' : ''; ?>>Sim</option>
                        </select>
                    </div>

            <!-- Descrição -->
                    <div class="mb-3">
                        <label for="editDescricao" class="form-label">Descrição</label>
                        <textarea id="editDescricao" name="descricao" class="form-control bg-secondary text-white" style="height: 300px"></textarea>
                    </div>

            <div class="form-row">
                <label for="addAttachment" class="form-label">Anexar Documento</label>
                <div id="attachmentPreview"></div>
                <input class="form-control bg-secondary text-white" type="file" id="editAttachment" name="attachment" onchange="updateAttachmentPreview(this)">
            </div>

            <div class="d-flex justify-content-end mt-4"> <!-- Adiciona margem superior e alinha à direita -->
            <div class="form-row">
                <button type="submit" class="submit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-send feather-sm fill-white me-2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg> Salvar Alterações</button>
            </div>
            </div>
          
        </form>
    </div>
</div>
    </div>
</div>



        </div>  



        <?php include 'footer.php'?>        
      </div>      
    </div>

    <div class="chat-windows"></div>




    <script>

// Função para atualizar a visualização do anexo
function updateAddAttachmentPreview(input) {
    var previewContainer = document.getElementById('addAttachmentPreview');
    previewContainer.innerHTML = ''; // Limpa a visualização anterior

    if (input.files && input.files[0]) {
        var file = input.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            var fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                var image = new Image();
                image.src = e.target.result;
                image.style.maxWidth = '200px';
                image.style.maxHeight = '200px';
                previewContainer.appendChild(image);
            } else if (fileExtension === 'pdf') {
                var pdfIcon = document.createElement('img');
                pdfIcon.src = 'img/pdf.png'; // Ajuste o caminho conforme necessário
                pdfIcon.style.width = '50px';
                pdfIcon.style.height = '50px';
                previewContainer.appendChild(pdfIcon);
            } else if (['doc', 'docx'].includes(fileExtension)) {
                var docIcon = document.createElement('img');
                docIcon.src = 'img/doc.png'; // Ajuste o caminho conforme necessário
                docIcon.style.width = '50px';
                docIcon.style.height = '50px';
                previewContainer.appendChild(docIcon);
            } else {
                previewContainer.textContent = 'Tipo de arquivo não suportado para visualização';
            }
        };
        
        reader.readAsDataURL(file);
    }
}

// Função para atualizar a visualização do anexo
function updateAttachmentPreview(input) {
    var previewContainer = document.getElementById('attachmentPreview');
    previewContainer.innerHTML = ''; // Limpa a visualização anterior

    if (input.files && input.files[0]) {
        var file = input.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            // Verifica a extensão do arquivo para determinar o tipo de visualização
            var fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Se for uma imagem, cria uma visualização em miniatura
                var image = new Image();
                image.src = e.target.result;
                image.style.maxWidth = '200px';
                image.style.maxHeight = '200px';
                previewContainer.appendChild(image);
            } else if (fileExtension === 'pdf') {
                // Se for um PDF, mostra o ícone de PDF
                var pdfIcon = document.createElement('img');
                pdfIcon.src = 'img/pdf-icon.png'; // Ajuste o caminho conforme necessário
                pdfIcon.style.width = '50px';
                pdfIcon.style.height = '50px';
                previewContainer.appendChild(pdfIcon);
            } else if (['doc', 'docx'].includes(fileExtension)) {
                // Se for um documento Word, mostra o ícone de Word
                var docIcon = document.createElement('img');
                docIcon.src = 'img/doc-icon.png'; // Ajuste o caminho conforme necessário
                docIcon.style.width = '50px';
                docIcon.style.height = '50px';
                previewContainer.appendChild(docIcon);
            } else {
                // Para outros tipos de arquivos, exibe um texto padrão ou outro ícone
                previewContainer.textContent = 'Tipo de arquivo não suportado para visualização';
            }
        };
        
        // Lê o arquivo como URL de dados
        reader.readAsDataURL(file);
    }
}


// Certifique-se de que a variável 'backlogbis' contém todos os backlogbis
var backlogbis = <?php echo json_encode($backlogbis); ?>;

function loadbacklogbiDetails(id) {
    // Encontra o item de backlogbi específico pelo ID
    var backlogbiItem = backlogbis.find(item => item.Id == id);

    if (backlogbiItem) {
        // Preenche os campos do formulário com os dados do backlogbi
        document.getElementById('editId').value = backlogbiItem.Id;
        document.getElementById('editProjeto').value = backlogbiItem.Projeto;
        document.getElementById('editDtCriacao').value = backlogbiItem.Dt_criacao;
        document.getElementById('editPrioridade').value = backlogbiItem.Prioridade;
        document.getElementById('editStatusIdeia').value = backlogbiItem.Status_ideia;
        document.getElementById('editResponsavel').value = backlogbiItem.Responsavel;
        document.getElementById('editEncaminhadoOs').checked = backlogbiItem.Encaminhado_os === '0';
        document.getElementById('editDescricao').value = backlogbiItem.Descricao;
        document.getElementById('existingAttachment').value = backlogbiItem.Anexo; // Certifique-se de que este campo exista no seu banco de dados e no objeto backlogbiItem

         // Mostra a visualização do anexo existente
        displayExistingAttachment(backlogbiItem.Anexo);

        
        // Configura a visualização do anexo existente
        var attachmentPreview = document.getElementById('attachmentPreview');
        attachmentPreview.innerHTML = ''; // Limpa a visualização anterior

        if (backlogbiItem.Anexo) {
            // Se houver um anexo, mostra a visualização correspondente
            var attachmentLink = document.createElement('a');
            attachmentLink.href = backlogbiItem.Anexo;
            attachmentLink.textContent = 'Ver Anexo Existente';
            attachmentLink.target = '_blank';
            
            attachmentPreview.appendChild(attachmentLink);
        }

        // Abre o modal de edição
        openModal('editModal');
    } else {
        alert('Item de backlog não encontrado.');
    }
}

</script>


<script>
function deleteAttachment(backlogbiId) {
    if (!backlogbiId) {
        console.error('ID do backlog não fornecido.');
        return;
    }

    if (confirm('Tem certeza que deseja excluir este anexo?')) {
        // Limpe a visualização e o valor do anexo existente
        document.getElementById('attachmentPreview').innerHTML = '';
        document.getElementById('existingAttachment').value = '';

        // Envie uma solicitação ao backend para excluir o arquivo
        fetch('delete_attachment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'backlogbiId=' + backlogbiId
        })
        .then(response => response.text())
        .then(data => {
            alert(data); // Exibe a resposta do servidor
        })
        .catch(error => {
            console.error('Erro ao excluir o anexo:', error);
        });
    }
}


</script>

    <script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Inicialize uma variável para controlar se uma submissão está em andamento
let isSubmitting = false;

function submitAddForm() {
    var submitButton = document.querySelector('#addModal .submit-btn');
    if (!submitButton) {
        console.error('O botão de submissão não foi encontrado!');
        return;
    }

    if (isSubmitting) {
        return; // Se já estiver enviando, não faz nada
    }
    isSubmitting = true; // Define que a submissão começou
    submitButton.disabled = true;

    var formElement = document.getElementById('addbacklogbiForm');
    var formData = new FormData(formElement);
    document.querySelector('#addModal .submit-btn').disabled = true;

    fetch('process_cadastro_backlogbi.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        location.reload(); // Para atualizar a lista de itens do backlogbi
    })
    .catch(error => {
        console.error('Erro ao cadastrar o item do backlogbi:', error);
    })
    .finally(() => {
        document.querySelector('#addModal .submit-btn').disabled = true;
        isSubmitting = false; // Reseta a variável após a submissão
        closeModal('addModal');
    });
}


function submitEditForm() {
    if (isSubmitting) {
        return; // Se já estiver enviando, não faz nada
    }
    isSubmitting = true; // Define que a submissão começou

    var formElement = document.getElementById('editbacklogbiForm');
    var formData = new FormData(formElement);
    document.querySelector('#editModal .submit-btn').disabled = true;

    fetch('process_edit_backlogbi.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json()) // Agora esperamos JSON
.then(data => {
    if(data.success) {
        alert(data.message);
        window.location.href = 'backlogbi.php'; // Redireciona para backlogbi.php
    } else {
        throw new Error(data.message); // Lança um erro se não for bem-sucedido
    }
})
.catch(error => {
    console.error('Erro ao atualizar o item do backlogbi:', error);
    alert(error); // Exibe o erro em um alerta
})
.finally(() => {
    document.querySelector('#editModal .submit-btn').disabled = false;
    isSubmitting = false;
});
}




// Função para carregar os detalhes e a visualização do anexo do backlogbi para edição
function loadbacklogbiDetails(id) {
    const backlogbiItem = backlogbis.find(item => item.Id == id);
    if (backlogbiItem) {
        // Preenche os campos do formulário com os dados do item do backlogbi
        document.getElementById('editId').value = backlogbiItem.Id;
        document.getElementById('editProjeto').value = backlogbiItem.Projeto;
        document.getElementById('editDtCriacao').value = backlogbiItem.Dt_criacao;
        document.getElementById('editPrioridade').value = backlogbiItem.Prioridade;
        document.getElementById('editStatusIdeia').value = backlogbiItem.Status_ideia;
        document.getElementById('editResponsavel').value = backlogbiItem.Responsavel;
        var encaminhadoOsSelect = document.getElementById('editEncaminhadoOs');
        encaminhadoOsSelect.value = backlogbiItem.Encaminhado_os.toString();
        document.getElementById('editDescricao').value = backlogbiItem.Descricao;
        document.getElementById('existingAttachment').value = backlogbiItem.Anexo; // Certifique-se de que este campo exista no seu banco de dados e no objeto backlogbiItem

        // Mostra a visualização do anexo existente
        displayExistingAttachment(backlogbiItem.Anexo);

        // Abre o modal de edição
        openModal('editModal');
    } else {
        alert('Item de backlogbi não encontrado.');
    }
}

// Função para exibir a miniatura do anexo existente
function displayExistingAttachment(filePath) {
    const attachmentPreview = document.getElementById('attachmentPreview');
    attachmentPreview.innerHTML = ''; // Limpa o preview anterior

    if (filePath) {
        const fileExtension = filePath.split('.').pop().toLowerCase();

        if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
            // Se for uma imagem, mostra a miniatura
            const img = document.createElement('img');
            img.src = filePath;
            img.style.maxWidth = '200px';
            img.style.maxHeight = '200px';
            attachmentPreview.appendChild(img);
        } else {
            // Se for um PDF ou Word, mostra o ícone correspondente
            const iconImg = document.createElement('img');
            iconImg.style.width = '50px';
            iconImg.style.height = '50px';

            // Define o ícone com base na extensão do arquivo
            if (fileExtension === 'pdf') {
                iconImg.src = 'img/pdf.png'; // Atualize para o caminho correto do ícone de PDF
            } else if (['doc', 'docx'].includes(fileExtension)) {
                iconImg.src = 'img/doc.png'; // Atualize para o caminho correto do ícone de Word
            }

            // Cria um link que envolve o ícone
            const link = document.createElement('a');
            link.href = filePath;
            link.target = '_blank';
            link.appendChild(iconImg);

            // Adiciona o link com o ícone ao container de preview
            attachmentPreview.appendChild(link);
        }
    }
}


function deletebacklogbi(id) {
    if (confirm('Tem certeza que deseja excluir este item?')) {
        // Substitua 'delete_backlogbi.php' com o caminho correto para o seu script PHP de exclusão.
        fetch('delete_backlogbi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            window.location.reload(); // Recarrega a página para atualizar a lista de itens.
        })
        .catch(error => {
            console.error('Erro ao excluir o item:', error);
        });
    }
}


// Adicione event listeners para os formulários
document.getElementById('addbacklogbiForm').addEventListener('submit', submitAddForm);
document.getElementById('editbacklogbiForm').addEventListener('submit', submitEditForm);

</script>

<script> 

document.addEventListener('DOMContentLoaded', (event) => {
    document.getElementById('addbacklogbiForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Impede o envio padrão do formulário

        if (isSubmitting) {
            return; // Se já estiver enviando, não faz nada
        }
        isSubmitting = true; // Define que a submissão começou

        var formData = new FormData(this);

        fetch('process_cadastro_backlogbi.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data); // Exibe uma mensagem com a resposta do servidor
            location.reload(); // Recarrega a página ou redireciona conforme necessário
        })
        .catch(error => {
            console.error('Erro ao cadastrar o item do backlogbi:', error);
        })
        .finally(() => {
            isSubmitting = false; // Reseta a variável após a submissão
        });
    });
});


</script>


<script>
var backlogbis = <?php echo json_encode($backlogbis); ?>;
</script>


<script>

    function formatDate(date) {
  var d = new Date(date),
      month = '' + (d.getMonth() + 1),
      day = '' + d.getDate(),
      year = d.getFullYear();

  if (month.length < 2) month = '0' + month;
  if (day.length < 2) day = '0' + day;

  return [day, month, year].join('/');
}

function openModal(modalId) {
  var modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = 'block';
  } else {
    alert('Modal não encontrado: ' + modalId);
  }
}

function closeModal(modalId) {
  var modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = 'none';
  }
}

function parseDate(dateStr) {
    var parts = dateStr.split('/');
    // Certifique-se de que parts[2], parts[1] e parts[0] existem
    if (parts.length === 3) {
        var dt = new Date(parts[2], parts[1] - 1, parts[0]);
        return dt;
    }
    return null; // Retorna null se a data não estiver no formato esperado
}

function formatDate(dateString) {
    // Supõe que dateString está no formato aaaa-mm-dd (ISO 8601)
    var date = new Date(dateString + 'T00:00:00Z'); // Força a interpretação como UTC
    var day = ('0' + date.getUTCDate()).slice(-2); // Usa getUTCDate para obter o dia UTC
    var month = ('0' + (date.getUTCMonth() + 1)).slice(-2); // Usa getUTCMonth para obter o mês UTC
    var year = date.getUTCFullYear();
    return [day, month, year].join('/'); // Formato dd/mm/aaaa
}






// Certifique-se de que a variável 'backlogbis' contém todos os backlogbis
var backlogbis = <?php echo json_encode($backlogbis); ?>;
    
    // Função para carregar os detalhes no modal de visualização
    document.getElementById('viewProjeto').textContent = backlogbiItem.Projeto;
    function viewbacklogbiDetails(id) {
  const backlogbiItem = backlogbis.find(item => item.Id == id);
  if (backlogbiItem) {
    // Define o conteúdo do projeto
    document.getElementById('viewProjeto').textContent = backlogbiItem.Projeto;

    // Formata e define a data de criação
    var formattedDate = formatDate(backlogbiItem.Dt_criacao);    
    document.getElementById('viewDtCriacao').textContent = formattedDate;

    // Define outros campos...
    document.getElementById('viewPrioridade').textContent = backlogbiItem.Prioridade;
    document.getElementById('viewStatusIdeia').textContent = backlogbiItem.Status_ideia;
    document.getElementById('viewResponsavel').textContent = backlogbiItem.NomeResponsavel || 'N/A';
    document.getElementById('viewEncaminhadoOs').textContent = backlogbiItem.Encaminhado_os ? 'Sim' : 'Não';

    // Verifica e define a descrição
    if (backlogbiItem.Descricao && backlogbiItem.Descricao.trim() !== '') {
      document.getElementById('viewDescricao').parentNode.style.display = 'block';
      document.getElementById('viewDescricao').innerHTML = backlogbiItem.Descricao.replace(/\n/g, '<br>');
    } else {
      document.getElementById('viewDescricao').parentNode.style.display = 'none';
    }

    // Verifica e mostra o anexo
    if (backlogbiItem.Anexo && backlogbiItem.Anexo.trim() !== '') {
      document.getElementById('viewAttachmentPreview').parentNode.style.display = 'block';
      displayExistingAttachmentView(backlogbiItem.Anexo);
    } else {
      document.getElementById('viewAttachmentPreview').parentNode.style.display = 'none';
    }

    // Abre o modal
    openModal('viewModal');
  } else {
    alert('Item de backlogbi não encontrado.');
  }
}

// Atualize a função displayExistingAttachmentView para lidar com a verificação do anexo
function displayExistingAttachmentView(attachment) {
  const attachmentPreview = document.getElementById('viewAttachmentPreview');
  attachmentPreview.innerHTML = '';
  
  if (attachment && attachment.trim() !== '') {
    const fileExtension = attachment.split('.').pop().toLowerCase();

    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
      // Se for uma imagem, mostra a miniatura
      const img = document.createElement('img');
      img.src = attachment;
      img.style.maxWidth = '100%'; // Ajuste conforme necessário
      img.style.height = 'auto';
      attachmentPreview.appendChild(img);
    } else if (fileExtension === 'pdf') {
      // Se for um PDF, mostra o visualizador de PDF
      const iframe = document.createElement('iframe');
      iframe.src = attachment;
      iframe.style.width = '100%'; // Ajuste conforme necessário
      iframe.style.height = '500px'; // Ajuste a altura conforme necessário
      attachmentPreview.appendChild(iframe);
    } else {
      // Para outros tipos de arquivos, você pode decidir como melhor exibir
      const link = document.createElement('a');
      link.href = attachment;
      link.textContent = 'Clique para abrir o anexo';
      link.target = '_blank';
      attachmentPreview.appendChild(link);
    }
  }
}




</script>


<script> 
function togglePriority(checkbox, backlogbiId) {
    var row = checkbox.closest('tr');
    if (checkbox.checked) {
        row.classList.add('highlighted');
        localStorage.setItem('highlightedRow' + backlogbiId, 'true');
    } else {
        row.classList.remove('highlighted');
        localStorage.removeItem('highlightedRow' + backlogbiId);
    }
    sortHighlightedRows();
}

function sortHighlightedRows() {
    var table = document.getElementById('tablesaw-6204');
    var tbody = table.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr'));

    var priorityOrder = { 'Alta': 1, 'Média': 2, 'Baixa': 3 };

    rows.sort(function(a, b) {
        var highlightedA = a.classList.contains('highlighted');
        var highlightedB = b.classList.contains('highlighted');

        if (highlightedA && highlightedB) {
            var priorityA = priorityOrder[a.cells[2].textContent.trim()] || 4;
            var priorityB = priorityOrder[b.cells[2].textContent.trim()] || 4;
            if (priorityA !== priorityB) {
                return priorityA - priorityB;
            }

            // Converte a data de criação para objeto Date
            var datePartsA = a.cells[1].textContent.split('/');
            var dateA = new Date(datePartsA[2], datePartsA[1] - 1, datePartsA[0]);

            var datePartsB = b.cells[1].textContent.split('/');
            var dateB = new Date(datePartsB[2], datePartsB[1] - 1, datePartsB[0]);

            return dateA - dateB; // Compara as datas
        }

        return highlightedA ? -1 : highlightedB ? 1 : 0;
    });

    rows.forEach(function(row) {
        tbody.appendChild(row);
    });
}



document.addEventListener('DOMContentLoaded', function() {
    var checkboxes = document.querySelectorAll('.priority-check');
    checkboxes.forEach(function(checkbox) {
        var backlogbiId = checkbox.dataset.backlogbiId;
        checkbox.addEventListener('change', function() {
            togglePriority(this, backlogbiId);
        });

        if (localStorage.getItem('highlightedRow' + backlogbiId) === 'true') {
            checkbox.checked = true;
            checkbox.closest('tr').classList.add('highlighted');
        }
    });

    sortHighlightedRows();
});

</script>


<script>
document.addEventListener("DOMContentLoaded", function() {
    atualizarContadores();

    // Função para atualizar a visualização do total de BI e prioridades
function atualizarContadores() {
    var urlParams = new URLSearchParams(window.location.search);
    var ano = urlParams.get('year') || ''; // Pega o ano do filtro, se existir

    var url = 'obter_totais_backlogsbi.php';
    if (ano) {
        url += '?year=' + ano; // Adiciona o ano à URL, se necessário
    }

    fetch(url)
        .then(response => response.json())
        .then(data => {
            document.getElementById('totalbacklogbis').textContent = data.totalBacklogs;
            document.getElementById('prioridadeAlta').textContent = data.totalPrioridadeAlta;
            document.getElementById('prioridadeMedia').textContent = data.totalPrioridadeMedia;
            document.getElementById('prioridadeBaixa').textContent = data.totalPrioridadeBaixa;
        })
        .catch(error => console.error('Erro ao obter totais:', error));
}

document.addEventListener('DOMContentLoaded', atualizarContadores);



});

function filterbacklogbis(filtro) {
    var urlParams = new URLSearchParams(window.location.search);
    if(filtro) {
        urlParams.set('prioridade', filtro); // Define o filtro de prioridade
    } else {
        urlParams.delete('prioridade'); // Remove o filtro de prioridade se nenhum for selecionado
    }
    window.location.search = urlParams.toString(); // Atualiza a página com os novos parâmetros
}

</script>


   
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <!-- apps -->
    <script src="dist/js/app.min.js"></script>
    <script src="dist/js/app.init.dark.js"></script>
    <script src="dist/js/app-style-switcher.js"></script>
    <!-- slimscrollbar scrollbar JavaScript -->
    <script src="assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script>
    <script src="assets/extra-libs/sparkline/sparkline.js"></script>
    <!--Wave Effects -->
    <script src="dist/js/waves.js"></script>
    <!--Menu sidebar -->
    <script src="dist/js/sidebarmenu.js"></script>
    <!--Custom JavaScript -->
    <script src="dist/js/feather.min.js"></script>
    <script src="dist/js/custom.min.js"></script>
  </body>
</html>

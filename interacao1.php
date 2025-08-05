<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

$suportes = $pdo->query("
    SELECT s.*, u.Nome as NomeSolicitante, c.Nome as NomeContratada
    FROM suporte s
    LEFT JOIN usuarios u ON s.Solicitado_por = u.Id
    LEFT JOIN contratadas c ON s.Para_contratada = c.Id
")->fetchAll(PDO::FETCH_ASSOC);


$contratadas = $pdo->query("SELECT Id, Nome FROM contratadas")->fetchAll(PDO::FETCH_ASSOC);



// Adicione no início do arquivo para buscar os usuários cadastrados
$usuarios = $pdo->query("SELECT Id, Nome FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
$statusOptions = ["Não iniciada", "Em análise", "Paralisada", "Em atendimento", "Cancelada", "Resolvida"];

$whereClauses = [];
$params = [];

// Filtro por Ano de Criação
if (isset($_GET['year']) && $_GET['year'] != '') {
    $whereClauses[] = 'YEAR(s.Dt_criacao) = :year';
    $params[':year'] = $_GET['year'];
}

// Filtro por Solicitado Por
if (isset($_GET['solicitado_por']) && $_GET['solicitado_por'] != '') {
    $whereClauses[] = 's.Solicitado_por = :solicitado_por';
    $params[':solicitado_por'] = $_GET['solicitado_por'];
}

// Filtro por Status do Suporte
if (isset($_GET['status_suporte']) && $_GET['status_suporte'] != '') {
    $whereClauses[] = 's.Status_suporte = :status_suporte';
    $params[':status_suporte'] = $_GET['status_suporte'];
}

// Filtro por Prioridade
if (isset($_GET['prioridade']) && $_GET['prioridade'] != '') {
    $whereClauses[] = 's.Prioridade = :prioridade';
    $params[':prioridade'] = $_GET['prioridade'];
}

// Filtro por Status de Prazo
if (isset($_GET['status_prazo']) && $_GET['status_prazo'] != '') {
    if ($_GET['status_prazo'] == 'No Prazo') {
        $whereClauses[] = "(s.Prazo_previsto >= CURDATE() AND s.Status_suporte NOT IN ('Resolvida', 'Cancelada'))";
    } elseif ($_GET['status_prazo'] == 'Atrasada') {
        $whereClauses[] = "(s.Prazo_previsto < CURDATE() AND s.Status_suporte NOT IN ('Resolvida', 'Cancelada'))";
    }
}




// Filtro por Contratada
if (isset($_GET['para_contratada']) && $_GET['para_contratada'] != '') {
    $whereClauses[] = 's.Para_contratada = :para_contratada';
    $params[':para_contratada'] = $_GET['para_contratada'];
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';


// Defina os itens por página e o offset antes de usar na consulta SQL
$itensPorPagina = 12; // Definir quantas OS por página
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Construa a consulta base com ordenação e paginação
$query = "
    SELECT s.*, u.Nome as NomeSolicitante, c.Nome as NomeContratada
    FROM suporte s
    LEFT JOIN usuarios u ON s.Solicitado_por = u.Id
    LEFT JOIN contratadas c ON s.Para_contratada = c.Id
    {$whereSql}
    ORDER BY FIELD(s.Prioridade, 'Alta', 'Média', 'Baixa'), s.Dt_criacao
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

// Consulta SQL para contar registros com filtro
$totalRegistrosStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM suporte s
    LEFT JOIN usuarios u ON s.Solicitado_por = u.Id
    LEFT JOIN contratadas c ON s.Para_contratada = c.Id
    {$whereSql}
");

$totalRegistrosStmt->execute($params);
$totalRegistros = $totalRegistrosStmt->fetchColumn();


// Agora, calcule o total de páginas
$totalPaginas = ceil($totalRegistros / $itensPorPagina);


// Execute a consulta
try {
    $statement->execute();
    $suporte = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erro ao executar a consulta: ' . $e->getMessage());
}



// Buscar anos distintos de criação
$yearsQuery = "SELECT DISTINCT YEAR(Dt_criacao) AS Year FROM suporte ORDER BY Year DESC";
$yearsStmt = $pdo->query($yearsQuery);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);


// Total de suportes
$totalSuportesStmt = $pdo->prepare("SELECT COUNT(*) FROM suporte");
$totalSuportesStmt->execute();
$totalSuportes = $totalSuportesStmt->fetchColumn();

// Total de suportes com prioridade alta
$totalSuportesAltaStmt = $pdo->prepare("SELECT COUNT(*) FROM suporte WHERE Prioridade = 'Alta'");
$totalSuportesAltaStmt->execute();
$totalSuportesAlta = $totalSuportesAltaStmt->fetchColumn();

// Total de suportes com prioridade média
$totalSuportesMediaStmt = $pdo->prepare("SELECT COUNT(*) FROM suporte WHERE Prioridade = 'Média'");
$totalSuportesMediaStmt->execute();
$totalSuportesMedia = $totalSuportesMediaStmt->fetchColumn();

// Total de suportes com prioridade baixa
$totalSuportesBaixaStmt = $pdo->prepare("SELECT COUNT(*) FROM suporte WHERE Prioridade = 'Baixa'");
$totalSuportesBaixaStmt->execute();
$totalSuportesBaixa = $totalSuportesBaixaStmt->fetchColumn();


// Totais para cada status
$statusOptions = ["Não iniciada", "Em análise", "Paralisada", "Em atendimento", "Cancelada", "Resolvida"];
$statusTotais = [];
foreach ($statusOptions as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM suporte WHERE Status_suporte = ?");
    $stmt->execute([$status]);
    $statusTotais[$status] = $stmt->fetchColumn();
}

$statusColors = [
    "Não iniciada" => "secondary", // Cinza para indicar inatividade ou espera
    "Em análise" => "primary",     // Azul para indicar revisão ou consideração ativa
    "Paralisada" => "warning",     // Amarelo para alertar que a ação está em espera ou em pausa
    "Em atendimento" => "info",    // Azul claro para indicar atendimento em progresso
    "Cancelada" => "danger",       // Vermelho para indicar uma ação cancelada ou problema
    "Resolvida" => "success"       // Verde para indicar conclusão bem-sucedida ou resolução
];



?>

<!DOCTYPE html>
<html dir="ltr" lang="en">
  <head>
    <?php include 'head.php'?>
     
  </head>



  <body>
    <!-- -------------------------------------------------------------- -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- -------------------------------------------------------------- -->
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
            <h3 class="text-themecolor mb-0"><i class="mdi mdi-account-box"></i> Interação Inova</h3>            
          </div>          
        </div>

        <div class="container-fluid">          
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Custom Control Taskboard</h4>
                  <div id="todo-lists-demo-controls"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <?php include 'footer.php'?>  
        
      </div>
      
    </div>
       
    <div class="chat-windows"></div>

   
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery.ui.touch-punch-improved.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery-ui.min.js"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <!-- <script src="assets/libs/popper.js/popper.min.js"></script> -->
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
    <!--This page JavaScript -->
    <script src="assets/extra-libs/taskboard/js/lobilist.js"></script>
    <script src="assets/extra-libs/taskboard/js/lobibox.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/task-init.js"></script>
  </body>
</html>

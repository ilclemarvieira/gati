<?php

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}
include 'db.php';




$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        // Redireciona para a página anterior ou para uma página padrão
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'minhastarefas'; // Define 'index.php' como fallback
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}

// No início de cada página restrita, chame verificarPermissao com os perfis permitidos
$perfisPermitidos = [1, 2, 4, 5]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);



$stmt = $pdo->prepare("SELECT COUNT(*) AS total_os FROM os");
$stmt->execute();
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
$totalOs = $resultado['total_os'];

$stmt = $pdo->prepare("SELECT N_os, Nome_os, Valor FROM os WHERE Status_contratada = 'Em desenvolvimento' ORDER BY Dt_inicial DESC LIMIT 2");
$stmt->execute();
$ultimasOSEmDesenvolvimento = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentYear = date('Y'); // Certifique-se de que esta variável contém o ano atual

// Ajuste na consulta para buscar as últimas OS em Produção do ano atual
$stmtUltimasOSEmProducao = $pdo->prepare("SELECT N_os, Nome_os, Valor FROM os WHERE Status_contratada = 'Em Produção' AND YEAR(Dt_inicial) = :currentYear ORDER BY Dt_inicial DESC LIMIT 3");
$stmtUltimasOSEmProducao->bindParam(':currentYear', $currentYear, PDO::PARAM_INT);
$stmtUltimasOSEmProducao->execute();
$ultimasOSEmProducao = $stmtUltimasOSEmProducao->fetchAll(PDO::FETCH_ASSOC);


// Define as consultas para cada status
$statusValores = [
    'Em Produção' => "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = 'Em Produção'",
    'Em Desenvolvimento' => "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = 'Em Desenvolvimento'",
    'Em Homologação' => "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = 'Em Homologação'",
    'Não Começou' => "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = 'Não Começou'",
];

// Array para armazenar os resultados
$valoresStatus = [];

// Executa cada consulta e armazena os resultados
foreach ($statusValores as $status => $query) {
    $stmt = $pdo->query($query);
    $resultado = $stmt->fetch();
    $valoresStatus[$status] = $resultado['total'] ?? 0; // Usa operador de coalescência nula para evitar erros
}

// Consulta para o total de valores de OS pagas e não pagas
$totalPagas = $pdo->query("SELECT SUM(Valor) AS total FROM os WHERE Os_paga = 1")->fetch()['total'] ?? 0;
$totalNaoPagas = $pdo->query("SELECT SUM(Valor) AS total FROM os WHERE Os_paga = 0")->fetch()['total'] ?? 0;

// Consulta para obter o total de backlogs não encaminhados como OS
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_backlogs FROM backlog WHERE Encaminhado_os = 0");
$stmt->execute();
$resultadoBacklogs = $stmt->fetch(PDO::FETCH_ASSOC);
$totalBacklogs = $resultadoBacklogs['total_backlogs'];


// Consulta para obter o total de backlogs BI não encaminhados como OS
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_backlogbi FROM backlogbi WHERE Encaminhado_os = 0");
$stmt->execute();
$resultadoBacklogBI = $stmt->fetch(PDO::FETCH_ASSOC);
$totalBacklogBI = $resultadoBacklogBI['total_backlogbi'];

$stmt = $pdo->prepare("SELECT Id, Projeto FROM backlogbi WHERE Encaminhado_os = 1 ORDER BY Dt_criacao DESC LIMIT 2");
$stmt->execute();
$ultimosBacklogsBI = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Consulta para obter o total de suportes não cancelados ou resolvidos
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_suporte FROM suporte WHERE Status_suporte NOT IN ('Cancelada', 'Resolvida')");
$stmt->execute();
$resultadoSuporte = $stmt->fetch(PDO::FETCH_ASSOC);
$totalSuporte = $resultadoSuporte['total_suporte'];


$stmt = $pdo->prepare("SELECT COUNT(*) AS total_subtasks FROM subtasks");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalSubtasks = $result['total_subtasks'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS total_completed_tasks FROM subtasks WHERE status = 'done'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalCompletedTasks = $result['total_completed_tasks'];

// Calcula a porcentagem de tarefas concluídas
$percentageCompletedTasks = ($totalSubtasks > 0) ? ($totalCompletedTasks / $totalSubtasks * 100) : 0;


$stmt = $pdo->prepare("SELECT COUNT(*) AS total_pending_tasks FROM subtasks WHERE status = 'todo'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$totalPendingTasks = $result['total_pending_tasks'];

// Calcula a porcentagem de tarefas pendentes
$percentagePendingTasks = ($totalSubtasks > 0) ? ($totalPendingTasks / $totalSubtasks * 100) : 0;

// Consulta para obter o total de cards arquivados
$stmtArchived = $pdo->prepare("SELECT COUNT(*) AS total_archived FROM cards WHERE is_archived = 1");
$stmtArchived->execute();
$resultArchived = $stmtArchived->fetch(PDO::FETCH_ASSOC);
$totalArchived = $resultArchived['total_archived'];

// Consulta para obter o número total de cards criados
$stmtTotal = $pdo->prepare("SELECT COUNT(*) AS total_cards FROM cards");
$stmtTotal->execute();
$resultTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);
$totalCards = $resultTotal['total_cards'];




// Função para obter os anos distintos de uma coluna específica de uma tabela
function getDistinctYears($pdo, $columnName, $tableName) {
    $stmt = $pdo->prepare("SELECT DISTINCT YEAR($columnName) as year FROM $tableName ORDER BY year DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Juntar todos os anos distintos em um único array e remover duplicatas
$years = array_unique(array_merge(
    getDistinctYears($pdo, 'Dt_criacao', 'backlog'),
    getDistinctYears($pdo, 'Dt_criacao', 'backlogbi'),
    getDistinctYears($pdo, 'Dt_inicial', 'os'),
    getDistinctYears($pdo, 'Dt_criacao', 'suporte'),
    getDistinctYears($pdo, 'created_at', 'subtasks'),
    getDistinctYears($pdo, 'created_at', 'cards')
));

// Define o ano atual como padrão se nenhum ano for selecionado, e trata a seleção de "Todos os Anos"
$anoAtual = date('Y'); // Obtém o ano atual
$selectedYear = isset($_GET['year']) ? $_GET['year'] : $anoAtual;

// Se o usuário selecionou "Todos os Anos", $selectedYear será configurado como null
if ($selectedYear === 'all') {
    $selectedYear = null;
}

// Contagens de OS por status para o ano selecionado e soma dos valores
$statusList = ['Em Produção', 'Em Desenvolvimento', 'Em Homologação', 'Não Começou'];
$statusData = [];
foreach ($statusList as $status) {
    $count = getCountByStatus($pdo, $status, $selectedYear);
    $sum = getSumByStatus($pdo, $status, $selectedYear);
    $statusData[$status] = [
        'count' => $count,
        'sum' => $sum
    ];
}

// Função genérica para filtrar dados com base no ano selecionado
function fetchDataForYear($pdo, $tableName, $dateColumn, $selectedYear) {
    $query = "SELECT * FROM $tableName";
    if (!empty($selectedYear)) {
        $query .= " WHERE YEAR($dateColumn) = :year";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare($query);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Usar a função genérica para buscar dados de cada tabela
$backlogs = fetchDataForYear($pdo, 'backlog', 'Dt_criacao', $selectedYear);
$backlogbis = fetchDataForYear($pdo, 'backlogbi', 'Dt_criacao', $selectedYear);
$oses = fetchDataForYear($pdo, 'os', 'Dt_inicial', $selectedYear);
$suportes = fetchDataForYear($pdo, 'suporte', 'Dt_criacao', $selectedYear);
$subtasks = fetchDataForYear($pdo, 'subtasks', 'created_at', $selectedYear);
$cards = fetchDataForYear($pdo, 'cards', 'created_at', $selectedYear);

// Exemplo de como usar os dados filtrados (ajuste conforme necessário)
$totalOs = count($oses);
$totalBacklogs = count($backlogs);
$totalBacklogBI = count($backlogbis);
$totalSuporte = count($suportes);
$totalSubtasks = count($subtasks);
$totalPendingTasks = count(array_filter($subtasks, function($subtask) {
    // Verifique se 'last_edited_by' é NULL ou uma string vazia, dependendo de como seu banco de dados trata NULL
     return $subtask['status'] == 'todo';
}));

$totalCompletedTasks = count(array_filter($subtasks, function($subtask) {
    return $subtask['status'] == 'done';
}));

$totalArchived = count(array_filter($cards, function($card) { return $card['is_archived'] == 1; }));


// Consultas ajustadas para calcular valores com base no ano selecionado
$statusValoresQueries = [
    'Em Produção' => "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = 'Em Produção'" . ($selectedYear ? " AND YEAR(Dt_inicial) = :year" : ""),
    'Em Desenvolvimento' => "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = 'Em Desenvolvimento'" . ($selectedYear ? " AND YEAR(Dt_inicial) = :year" : ""),
    'Em Homologação' => "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = 'Em Homologação'" . ($selectedYear ? " AND YEAR(Dt_inicial) = :year" : ""),
    'Não Começou' => "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = 'Não Começou'" . ($selectedYear ? " AND YEAR(Dt_inicial) = :year" : "")
];

$valoresStatus = [];
foreach ($statusValoresQueries as $status => $query) {
    $stmt = $pdo->prepare($query);
    if ($selectedYear) {
        $stmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
    }
    $stmt->execute();
    $resultado = $stmt->fetch();
    $valoresStatus[$status] = $resultado['total'] ?? 0;
}

// Consultas para valores de OS pagas e não pagas ajustadas para o ano selecionado
$queryPagas = "SELECT SUM(Valor) AS total FROM os WHERE Os_paga = 1" . ($selectedYear ? " AND YEAR(Dt_inicial) = :year" : "");
$stmt = $pdo->prepare($queryPagas);
if ($selectedYear) {
    $stmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
}
$stmt->execute();
$totalPagas = $stmt->fetch()['total'] ?? 0;

$queryNaoPagas = "SELECT SUM(Valor) AS total FROM os WHERE Os_paga = 0" . ($selectedYear ? " AND YEAR(Dt_inicial) = :year" : "");
$stmt = $pdo->prepare($queryNaoPagas);
if ($selectedYear) {
    $stmt->bindParam(':year', $selectedYear, PDO::PARAM_INT);
}
$stmt->execute();
$totalNaoPagas = $stmt->fetch()['total'] ?? 0;


// Consulta para contar backlogs que foram enviados para OS no ano atual
$stmtEncaminhado1 = $pdo->prepare("SELECT COUNT(*) AS total FROM backlog WHERE Encaminhado_os = 1 AND YEAR(Dt_criacao) = ?");
$stmtEncaminhado1->execute([$selectedYear]);
$totalEncaminhado1 = $stmtEncaminhado1->fetch(PDO::FETCH_ASSOC)['total'];

// Consulta para contar backlogs que ainda não foram enviados para OS para o ano atual
$stmtNaoEncaminhado = $pdo->prepare("SELECT COUNT(*) AS total FROM backlog WHERE Encaminhado_os = 0 AND YEAR(Dt_criacao) = ?");
$stmtNaoEncaminhado->execute([$anoAtual]);
$totalNaoEncaminhado = $stmtNaoEncaminhado->fetch(PDO::FETCH_ASSOC)['total'];

// Consulta para contar todos os backlogs para o ano atual
$stmtTotalBacklogs = $pdo->prepare("SELECT COUNT(*) AS total FROM backlog WHERE YEAR(Dt_criacao) = ?");
$stmtTotalBacklogs->execute([$anoAtual]);
$totalBacklogsAnoAtual = $stmtTotalBacklogs->fetch(PDO::FETCH_ASSOC)['total'];

// Consulta para contar todos os backlogs para o ano selecionado ou todos os anos
$queryTotalBacklogs = $selectedYear === 'all' ?
    "SELECT COUNT(*) AS total FROM backlog" :
    "SELECT COUNT(*) AS total FROM backlog WHERE YEAR(Dt_criacao) = :year";

$stmtTotalBacklogs = $pdo->prepare($queryTotalBacklogs);
if ($selectedYear !== 'all') {
    $stmtTotalBacklogs->bindParam(':year', $selectedYear, PDO::PARAM_INT);
}
$stmtTotalBacklogs->execute();
$totalBacklogs = $stmtTotalBacklogs->fetch(PDO::FETCH_ASSOC)['total'];

// Agora você tem $totalEncaminhado1 e $totalBacklogs,
// então pode calcular a porcentagem de backlogs enviados para OS
$porcentagemEncaminhados = ($totalBacklogs > 0) ? ($totalEncaminhado1 / $totalBacklogs) * 100 : 0;

$selectedYear = date('Y'); // Ano atual como exemplo. Pode ser substituído por uma variável dinâmica conforme a necessidade.

// Consulta para contar o total de suportes que não estão com status 'Cancelada' ou 'Resolvida', filtrados pelo ano de criação
$stmtSuportesNaoCanceladosNaoResolvidos = $pdo->prepare("SELECT COUNT(*) AS total FROM suporte WHERE Status_suporte NOT IN ('Cancelada', 'Resolvida') AND YEAR(Dt_criacao) = :year");
$stmtSuportesNaoCanceladosNaoResolvidos->bindParam(':year', $selectedYear, PDO::PARAM_INT);
$stmtSuportesNaoCanceladosNaoResolvidos->execute();
$totalSuportesNaoCanceladosNaoResolvidos = $stmtSuportesNaoCanceladosNaoResolvidos->fetch(PDO::FETCH_ASSOC)['total'];

// Consulta para contar o total de suportes com status 'Resolvida', filtrados pelo ano de criação
$stmtSuportesResolvidos = $pdo->prepare("SELECT COUNT(*) AS total_resolvidos FROM suporte WHERE Status_suporte = 'Resolvida' AND YEAR(Dt_criacao) = :year");
$stmtSuportesResolvidos->bindParam(':year', $selectedYear, PDO::PARAM_INT);
$stmtSuportesResolvidos->execute();
$totalSuportesResolvidos = $stmtSuportesResolvidos->fetch(PDO::FETCH_ASSOC)['total_resolvidos'];

// O total geral de suportes é a soma dos resolvidos e dos pendentes
$totalGeralSuportes = $totalSuportesResolvidos + $totalSuportesNaoCanceladosNaoResolvidos;

// A porcentagem de suportes resolvidos deve ser calculada em relação ao total geral
$porcentagemSuportesResolvidos = ($totalGeralSuportes > 0) ? ($totalSuportesResolvidos / $totalGeralSuportes) * 100 : 0;






function getCountByStatus($pdo, $status, $year) {
    $query = "SELECT COUNT(*) AS total FROM os WHERE Status_contratada = :status";
    if ($year !== null) {
        $query .= " AND YEAR(Dt_inicial) = :year";
    }

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':status', $status);

    if ($year !== null) {
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
    }

    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getSumByStatus($pdo, $status, $year) {
    $query = "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = :status";
    if ($year !== null) {
        $query .= " AND YEAR(Dt_inicial) = :year";
    }

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':status', $status);

    if ($year !== null) {
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
    }

    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    return $result ? $result : 0; // Retorna 0 se o resultado for null
}



// Contagens de OS por status para o ano selecionado
$countStatus = [
    'Em Produção' => getCountByStatus($pdo, 'Em Produção', $selectedYear),
    'Em Desenvolvimento' => getCountByStatus($pdo, 'Em Desenvolvimento', $selectedYear),
    'Em Homologação' => getCountByStatus($pdo, 'Em Homologação', $selectedYear),
    'Não Começou' => getCountByStatus($pdo, 'Não Começou', $selectedYear),
];




?>


<!DOCTYPE html>
<html dir="ltr" lang="pt">

  <head>
    <?php include 'head.php'?> 

<style>

/* Estilos Gerais */
.card-group {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-around; /* Espaçamento uniforme entre os cards */
    gap: 10px; /* Espaçamento vertical e horizontal entre os cards */
    padding: 10px; /* Espaçamento em torno do grupo de cards */
}

.card {
    background-color: #333; /* Um tom mais escuro para contraste */
    color: #fff;
    border: none;
    border-radius: 15px; /* Cantos arredondados */
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5); /* Sombra para efeito de profundidade */
    transition: transform 0.3s ease-in-out, box-shadow 0.3s ease; /* Efeitos de transição suaves */
    width: calc(33% - 20px); /* Três cards por linha com espaçamento */
    margin: 10px; /* Espaçamento quando o gap não é suportado */
}

.card:hover {
    transform: scale(1.05); /* Aumenta e eleva ligeiramente o card ao passar o mouse */
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.7); /* Sombra mais escura e distante */
}

.card-body {
    padding: 25px; /* Espaçamento interno do card */
}

/* Títulos e Números Principais */
.card-title {
    font-size: 60px; /* Tamanho grande para destaque */
    font-weight: bold; /* Negrito para mais ênfase */
    margin-bottom: 15px; /* Espaçamento após o título */
}

/* Números Grandes e Destacados */
.card-body h3 {
    font-size: 58px; /* Aumenta o tamanho da fonte dos números */
    font-weight: bold; /* Aplica negrito para destaque */
    line-height: 1; /* Ajusta o espaçamento da linha para evitar excesso de altura */
    margin-bottom: 0.25em; /* Espaçamento mínimo após o número para separar do título */
}

.card-subtitle {
    font-size: 20px; /* Subtítulo um pouco maior para harmonia */
    margin-bottom: 25px; /* Espaçamento após o subtítulo */
    font-weight: bold;
    opacity: 0.85; /* Transparência para suavizar */
}

/* Estilos para as Barras de Progresso */
.progress {
    height: 10px; /* Diminui a altura da barra de progresso */
    background-color: #444; /* Cor de fundo mais escura para contraste */
    border-radius: 5px; /* Cantos ligeiramente arredondados para a barra */
}

.progress-bar {
    border-radius: 5px; /* Mantém os cantos da barra arredondados */
    box-shadow: none; /* Sem sombra interna para um look limpo */
}

/* Tooltips ajustados para ficar acima da barra de progresso */
.tooltip {
    top: -30px; /* Posiciona acima da barra */
    transform: translateX(-50%); /* Centraliza o tooltip */
    font-size: 12px; /* Tamanho de fonte menor para tooltips */
    padding: 5px; /* Espaçamento interno dos tooltips */
    border-radius: 4px; /* Bordas arredondadas para os tooltips */
}

/* Informações Adicionais no Canto Superior Direito */
.additional-info {
    position: absolute; /* Posicionamento absoluto dentro do card */
    top: 10px; /* Espaçamento do topo do card */
    right: 10px; /* Espaçamento da direita do card */
    font-size: 14px; /* Tamanho do texto menor para não competir com o título principal */
    color: #ddd; /* Cor do texto para contraste */
    background: none; /* Remove o fundo para transparência */
    box-shadow: none; /* Remove sombra interna para um efeito mais suave */
    padding: 4px 8px; /* Padding reduzido */
    border-radius: 10px; /* Cantos arredondados */
    text-align: center;
}

/* Estilos para Informação "Enviado OS" e "Concluídos" */
.enviado-os {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: rgb(50 55 67); /* Fundo translúcido para combinar com o card */
    color: #fff; /* Cor do texto branca para melhor leitura */
    padding: 5px 10px; /* Espaçamento interno adequado */
    border-radius: 9px; /* Bordas arredondadas para suavidade */
    font-size: 14px; /* Tamanho da fonte legível */
    box-shadow: 0 2px 4px rgba(0,0,0,0.2); /* Sombra sutil para efeito de profundidade */
    text-align: center;
    font-weight: bold; /* Negrito para destacar sem ser intrusivo */
}

/* Responsividade */
@media (max-width: 768px) {
    .card-group {
        flex-direction: column; /* Um card por linha em dispositivos móveis */
        align-items: center; /* Centraliza cards */
    }

    .card {
        width: 90%; /* Ocupa mais espaço na tela em dispositivos móveis */
        margin-bottom: 20px; /* Espaçamento entre os cards */
    }

     .card-body h3 {
        font-size: 48px; /* Tamanho do número reduzido para dispositivos móveis */
    }

    .card-title {
        font-size: 48px; /* Tamanho do número reduzido para dispositivos móveis */
    }
}

.total-annual-container {
    display: flex;
    justify-content: space-between;
    margin-bottom: 2rem; /* Espaço antes da tabela */
}

.total-annual-value {
    text-align: center;
}

.total-annual-value h2 {
    font-size: 25px;
    font-weight: 700;
    color: #2a7c8c; /* Cores distintas para pagas/não pagas */
    margin: 0; /* Remove margens padrão */
}

.total-annual-value h6 {
    font-size: 1rem;
    font-weight: 400;
    color: #a4acc4; /* Cor suave para os subtítulos */
}

.total-annual-value.unpaid h2 {
    color: #9f5568;
}

.status-table td {
    vertical-align: middle; /* Alinha o conteúdo da tabela verticalmente */
    padding: .5rem; /* Espaçamento reduzido para uma tabela mais compacta */
}

.status-table .badge {
    font-weight: 500;
    padding: .375rem .75rem;
    border-radius: .25rem; /* Bordas arredondadas para os badges */
}

/* Estilos para ícones */
.status-table img {
    width: 24px; /* Tamanho fixo para ícones */
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

        <div class="container-fluid"> 

       <div class="d-md-flex align-items-center">                   
    <div class="ms-auto">
        <form action="index" method="get">
            <select class="form-select" name="year" onchange="this.form.submit()">
                <option value="all" <?php echo $selectedYear == null ? 'selected' : ''; ?>>Todos os Anos</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                        <?php echo $year; ?>
                    </option>
                <?php endforeach; ?>
                
            </select>
        </form>

    </div>
</div>

                  <br>

          <div class="row">
            
            <div class="card-group">
    <!-- Card OS Criadas -->
    <div class="card bg-success">
        <div class="card-body text-white">
            <div class="row">
                <div class="col-12">
                    <h3 id="totalOs" style="color: #fff"><?php echo $totalOs; ?></h3>
                    <h6 class="card-subtitle" style="font-weight: bold; color:#fff">OS Criadas</h6>
                </div>
                <div class="col-12">
                    <div class="progress">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo ($totalOs > 100) ? '100%' : $totalOs.'%'; ?>" aria-valuenow="<?php echo $totalOs; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Backlog -->
<div class="card bg-info">
    <div class="card-body">
        <div class="row">
            <div class="col-12">
                <h3 id="totalBacklogs" style="color: #fff"><?php echo $totalNaoEncaminhado; ?></h3>
                <h6 class="card-subtitle" style="font-weight: bold; color:#fff">Backlog</h6>
            </div>
            <div class="col-12">
                <div class="progress">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $porcentagemEncaminhados; ?>%;" aria-valuenow="<?php echo $porcentagemEncaminhados; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <!-- Posicionamento da informação "Enviado OS" -->
                <div class="enviado-os">Enviado OS: <?php echo $totalEncaminhado1; ?></div>
            </div>
        </div>
    </div>
</div>


    <!-- Card Projetos BI (Sem alterações, mantido para consistência) -->
    <div class="card bg-warning">
        <div class="card-body">
            <div class="row">
                <div class="col-12">
                    <h3 id="totalBacklogBi" style="color: #fff"><?php echo $totalBacklogBI; ?></h3>
                    <h6 class="card-subtitle" style="font-weight: bold; color:#fff">Projetos BI</h6>
                </div>
                <div class="col-12">
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, $totalBacklogBI); ?>%;" aria-valuenow="<?php echo $totalBacklogBI; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Suportes Pendentes -->
    <div class="card bg-danger">
        <div class="card-body">
            <div class="row">
                <div class="col-12">
                    <h3 id="totalSuporte" style="color: #fff"><?php echo $totalSuportesNaoCanceladosNaoResolvidos; ?></h3>
                    <h6 class="card-subtitle" style="font-weight: bold; color:#fff">Suportes Pendentes</h6>
                </div>
                <div class="col-12">
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $porcentagemSuportesResolvidos ?>%;" aria-valuenow="<?= $porcentagemSuportesResolvidos ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="enviado-os" id="suportesResolvidos">Concluídos: <?= $totalSuportesResolvidos ?></div>
                </div>
            </div>
        </div>
    </div>
</div>




     <div class="row">
     
            <!-- Column -->
    <div class="col-lg-6 d-flex align-items-stretch">
  <div class="card w-100">
    <div class="card-body">
      <h4 class="card-title">ÚLTIMAS OS EM DESENVOLVIMENTO</h4>
      <div class="table-responsive">
        <table class="table stylish-table mt-4 no-wrap v-middle">
          <thead align="center">
            <tr>
              <th class="border-0 text-muted font-weight-medium" style="width: 90px">N° OS</th>
              <th class="border-0 text-muted font-weight-medium">Nome</th>
              <th class="border-0 text-muted font-weight-medium">Valor</th>
            </tr>
          </thead>
          <tbody id="ultimasOsDesenvolvimentoBody">
            <?php foreach($ultimasOSEmDesenvolvimento as $os): ?>
            <tr>
              <td style="text-align: center; vertical-align: middle;"><span class="badge bg-info"><?php echo $os['N_os']; ?></span></td>
              <td style="text-align: center; vertical-align: middle;">
                <h6 class="mb-0 font-weight-medium">
                  <a href="javascript:void(0)" class="link">
                    <?php 
                    echo (strlen($os['Nome_os']) > 50) ? substr($os['Nome_os'], 0, 50) . '...' : $os['Nome_os'];
                    ?>
                  </a>
                </h6>
              </td>
              <td style="text-align: center; vertical-align: middle;">
                <h6 class="mb-0 font-weight-medium">
                  <a href="javascript:void(0)" class="link">R$ <?php echo number_format($os['Valor'], 2, ',', '.'); ?></a>
                </h6>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


    <div class="col-lg-6 d-flex align-items-stretch">
  <div class="card w-100">
    <div class="card-body">
      <h4 class="card-title">ÚLTIMAS OS EM PRODUÇÃO</h4>
      <div class="table-responsive">
        <table class="table stylish-table mt-4 no-wrap v-middle">
          <thead align="center">
            <tr>
              <th class="border-0 text-muted font-weight-medium" style="width: 90px">N° OS</th>
              <th class="border-0 text-muted font-weight-medium">Nome</th>
              <th class="border-0 text-muted font-weight-medium">Valor</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($ultimasOSEmProducao as $os): ?>
            <tr>
              <td style="text-align: center; vertical-align: middle;"><span class="badge bg-info"><?php echo $os['N_os']; ?></span></td>
              <td style="text-align: center; vertical-align: middle;">
                <h6 class="mb-0 font-weight-medium">
                  <a href="javascript:void(0)" class="link">
                    <?php 
                    echo (strlen($os['Nome_os']) > 50) ? substr($os['Nome_os'], 0, 50) . '...' : $os['Nome_os'];
                    ?>
                  </a>
                </h6>
              </td>
              <td style="text-align: center; vertical-align: middle;">
                <h6 class="mb-0 font-weight-medium">
                  <a href="javascript:void(0)" class="link">R$ <?php echo number_format($os['Valor'], 2, ',', '.'); ?></a>
                </h6>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


</div>



            <div class="row">

                <div class="col-lg-6 d-flex align-items-stretch">

              <div class="card w-100">
                <div class="row">
                  <!-- Column -->
                  <div class="col-lg-6 col-xlg-3 col-md-6">
                    <div class="card-body">
                      <h3 class="card-title mb-3">TOTAL DE TAREFAS</h3>
                      <span class="mt-4 display-6 mt-4" id="totalSubtasks">***</span>
                      <h6 class="card-subtitle mb-3">
                        Interações da equipe Inova
                      </h6>                      
                      <div class="clearfix"></div>
                     <button type="button" class="btn btn-success mt-4" onclick="window.location.href='interacao'">
                      Ver Interações
                    </button>

                    </div>
                  </div>
                  <!-- Column -->
                  <div class="col-lg-6 col-xlg-9 col-md-6 border-left pl-0">
                    <ul class="product-review py-4 list-style-none mt-4 pe-4">
                      <li class="d-block py-3">
                        <div class="d-flex align-items-center">
                          <span class="text-muted display-5"><i class="mdi mdi-emoticon-cool"></i></span>
                          <div class="ms-3">
                            <div>
                              <h3 class="card-title text-nowrap">
                                Tarefas Concluídas
                              </h3>
                              <h6 class="card-subtitle" id="totalCompletedTasks"><?php echo $totalCompletedTasks; ?> Tarefas</h6>
                            </div>
                          </div>
                        </div>
                        <div class="progress-container">
                        <div class="progress">
                          <div class="progress-bar bg-success" role="progressbar" 
                               style="width: <?php echo $percentageCompletedTasks; ?>%;" 
                               aria-valuenow="<?php echo $totalCompletedTasks; ?>" 
                               aria-valuemin="0" aria-valuemax="100">
                            <span class="tooltip"><?php echo round($percentageCompletedTasks); ?>%</span>
                          </div>
                        </div>
                      </div>
                      </li>

                      <li class="d-block py-3">
                        <div class="d-flex align-items-center">
                          <span class="text-muted display-5"><i class="mdi mdi-emoticon-sad"></i></span>
                          <div class="ms-3">
                            <h3 class="card-title text-nowrap">
                              Tarefas Pendentes
                            </h3>
                            <h6 class="card-subtitle" id="totalPendingTasks"><?php echo $totalPendingTasks; ?> Tarefas</h6>
                          </div>
                        </div>
                       <div class="progress-container">
                        <div class="progress">
                          <div class="progress-bar bg-danger" role="progressbar" 
                               style="width: <?php echo $percentagePendingTasks; ?>%;" 
                               aria-valuenow="<?php echo $totalPendingTasks; ?>" 
                               aria-valuemin="0" aria-valuemax="100">
                            <span class="tooltip"><?php echo round($percentagePendingTasks); ?>%</span>
                          </div>
                        </div>
                      </div>
                      </li>


                    </ul>
                  </div>
                  <!-- Column -->
                </div>
              </div>
            </div>

            <!-- Column -->
           <div class="col-lg-6 d-flex align-items-stretch">

            <div class="card w-100">
              <div class="card-body">
                <div class="d-md-flex align-items-center">
                  <div>
                    <h4 class="card-title">ÚLTIMOS BI EM DESENVOLVIMENTO</h4>
                  </div>                    
                </div>
                <div class="table-responsive">
                  <table class="table stylish-table mt-4 no-wrap v-middle">
                    <thead align="center">
                      <tr>
                        <th class="border-0 text-muted font-weight-medium" style="width: 90px">
                          N° BI
                        </th>
                        <th class="border-0 text-muted font-weight-medium">
                          Nome
                        </th>                                                   
                      </tr>
                    </thead>
                    <tbody id="ultimosBacklogsBIBody">
                      <?php foreach ($ultimosBacklogsBI as $backlog): ?>
                      <tr>
                        <td style="text-align: center; vertical-align: middle;"><span class="badge bg-info"><?php echo $backlog['Id']; ?></span></td>
                        <td style="text-align: center; vertical-align: middle; text-transform: uppercase;">
                          <h6 class="mb-0 font-weight-medium">
                            <a href="javascript:void(0)" class="link"><?php echo htmlspecialchars($backlog['Projeto']); ?></a>
                          </h6>                            
                        </td>                          
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>



            
            </div>


<div class="row">

<div class="col-12 d-flex align-items-stretch">

  <div class="card w-100">
    <div class="card-body">
      <h4 class="card-title">TOTAL ANUAL EM OS</h4>
      <div class="total-annual-container">
        <div class="total-annual-value paid">
          <h2 id="totalPagas" style="color:#198754">R$ <?php echo number_format($totalPagas, 2, ',', '.'); ?></h2>
          <h6 align="left">Pagas</h6>
        </div>
        <div class="total-annual-value unpaid">
          <h2 id="totalNaoPagas">R$ <?php echo number_format($totalNaoPagas, 2, ',', '.'); ?></h2>
          <h6 align="left">Não pagas</h6>
        </div>

      </div>
       <table class="table mt-3 status-table">
    <?php foreach ($statusData as $status => $data): ?>
        <tr>
            <td class="ps-0" style="width: 40px">
                <?php
                // Define o caminho do ícone com base no status
                switch ($status) {
                    case 'Em Produção':
                        $iconPath = "assets/images/browser/producao.png";
                        break;
                    case 'Em Desenvolvimento':
                        $iconPath = "assets/images/browser/dev.png";
                        break;
                    case 'Em Homologação':
                        $iconPath = "assets/images/browser/hml.png";
                        break;
                    case 'Não Começou':
                        $iconPath = "assets/images/browser/naocomecou.png";
                        break;
                    default:
                        $iconPath = ""; // Caminho padrão ou ícone genérico se necessário
                }
                ?>
                <img src="<?php echo $iconPath; ?>" alt="<?php echo $status; ?>">
            </td>
            <td class="ps-0"><?php echo $status; ?> (<?php echo $data['count']; ?>)</td>
            <td class="ps-0 text-end">
                <?php
                // Determina a classe da cor de fundo com base no status
                $badgeClass = 'bg-light-info'; // Cor padrão
                if ($status == 'Em Produção') {
                    $badgeClass = 'bg-light-info';
                } elseif ($status == 'Em Desenvolvimento') {
                    $badgeClass = 'bg-success';
                } elseif ($status == 'Em Homologação') {
                    $badgeClass = 'bg-light-warning';
                } elseif ($status == 'Não Começou') {
                    $badgeClass = 'bg-light-danger';
                }
                ?>
                <span class="badge <?php echo $badgeClass; ?> text-white font-weight-medium">R$ <?php echo number_format($data['sum'], 2, ',', '.'); ?></span>
            </td>
        </tr>
    <?php endforeach; ?>
</table>     
    </div>
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
    function updateProgressBar(data) {
    const progressBar = document.querySelector('.progress-bar');
    const porcentagem = data.porcentagemSuportesResolvidos.toFixed(2);
    progressBar.style.width = porcentagem + '%';
    progressBar.setAttribute('aria-valuenow', porcentagem);
}

document.addEventListener('DOMContentLoaded', function() {
    function fetchData() {
        fetch('fetchData.php' + window.location.search) // Inclui os parâmetros da URL na solicitação
            .then(response => response.json())
            .then(data => {
                console.log(data); // Adicione esta linha temporariamente
                // Atualizações gerais
                document.getElementById('totalOs').textContent = data.totalOs;
                document.getElementById('totalBacklogs').textContent = data.totalBacklogs; // Apenas o total não encaminhado
                document.querySelector('.enviado-os').textContent = `Enviado OS: ${data.totalEncaminhado1}`;
                document.getElementById('totalBacklogBi').textContent = data.totalBacklogBi;
                document.getElementById('totalSuporte').textContent = data.totalSuporte;
                document.getElementById('suportesResolvidos').textContent = `Concluídos: ${data.totalSuportesResolvidos}`;

                document.getElementById('totalSubtasks').textContent = data.totalSubtasks;
                document.getElementById('totalCompletedTasks').textContent = data.totalCompletedTasks + ' Tarefas';
                document.getElementById('totalPendingTasks').textContent = data.totalPendingTasks + ' Tarefas';
                document.getElementById('totalArchived').textContent = data.totalArchived + ' Tarefas';
                document.getElementById('totalPagas').textContent = `R$ ${data.totalPagas}`;
                document.getElementById('totalNaoPagas').textContent = `R$ ${data.totalNaoPagas}`;
                




                // Atualizações para as últimas OS em Desenvolvimento e em Produção
                updateOsTable('ultimasOsDesenvolvimentoBody', data.ultimasOSEmDesenvolvimento);
                updateOsTable('ultimasOsProducaoBody', data.ultimasOSEmProducao);

                // Atualizações dos valores por status de OS
                // Certifique-se de que os IDs correspondem aos seus elementos HTML
                document.getElementById('valorEmProducao').textContent = `R$ ${data.valoresStatus['Em Produção']}`;
                document.getElementById('valorEmDesenvolvimento').textContent = `R$ ${data.valoresStatus['Em Desenvolvimento']}`;
                document.getElementById('valorEmHomologacao').textContent = `R$ ${data.valoresStatus['Em Homologação']}`;
                document.getElementById('valorNaoComecou').textContent = `R$ ${data.valoresStatus['Não Começou']}`;

                // Atualização da tabela de últimos BIs em Desenvolvimento
                const tbodyBI = document.getElementById('ultimosBacklogsBIBody');
                tbodyBI.innerHTML = ''; // Limpa antes de adicionar novos dados
                data.ultimosBacklogsBI.forEach(backlog => {
                    const row = `
                        <tr>
                            <td style="text-align: center; vertical-align: middle;"><span class="badge bg-info">${backlog.Id}</span></td>
                            <td style="text-align: center; vertical-align: middle;"><h6 class="mb-0 font-weight-medium"><a href="javascript:void(0)" class="link">${backlog.Projeto}</a></h6></td>
                        </tr>
                    `;
                    tbodyBI.innerHTML += row;
                });

                // Atualizações específicas para valores encaminhados e barra de progresso
                const totalEncaminhadosDentroDaBarra = document.getElementById('totalEncaminhadosDentroDaBarra');
                if (totalEncaminhadosDentroDaBarra) {
                    totalEncaminhadosDentroDaBarra.textContent = `Enviado OS: ${data.totalEncaminhado1}`;
                }
                // Exemplo de atualização da largura da barra de progresso para backlogs encaminhados
                // Altere '#totalBacklogs + .progress .progress-bar' conforme a estrutura do seu HTML
                const progressBar = document.querySelector('#totalBacklogs + .progress .progress-bar');
                if (progressBar) {
                    progressBar.style.width = `${data.porcentagemEncaminhados}%`;
                }

                updateProgressBar(data);
            })
            .catch(error => console.error('Erro ao buscar dados:', error));
}


function updateOsTable(tableId, osData) {
    const tableBody = document.getElementById(tableId);
    tableBody.innerHTML = ''; // Limpa a tabela antes de adicionar novos dados

    if (osData.length === 0) {
        const noDataRow = document.createElement('tr');
        noDataRow.innerHTML = '<td colspan="3" style="text-align:center;">Nenhuma informação disponível</td>';
        tableBody.appendChild(noDataRow);
    } else {
        osData.forEach(os => {
            const osRow = document.createElement('tr');
            osRow.innerHTML = `
                <td style="text-align: center; vertical-align: middle;"><span class="badge bg-info">${os.N_os}</span></td>
                <td style="text-align: center; vertical-align: middle;"><h6 class="mb-0 font-weight-medium"><a href="javascript:void(0)" class="link">${os.Nome_os.length > 50 ? os.Nome_os.substring(0, 50) + '...' : os.Nome_os}</a></h6></td>
                <td style="text-align: center; vertical-align: middle;"><h6 class="mb-0 font-weight-medium">R$ ${parseFloat(os.Valor).toFixed(2)}</h6></td>
            `;
            tableBody.appendChild(osRow);
        });
    }
}

// Inicia a atualização periódica ao carregar a página
setInterval(fetchData, 1000); // Atualiza dados a cada 1 segundo


});
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

<?php
include 'db.php'; // Assegure a conexão com o banco de dados

$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$year = isset($_GET['year']) && $_GET['year'] !== 'all' ? $_GET['year'] : null;

// Define o ano atual, independentemente do valor de 'year' na URL
$currentYear = date('Y');

// Verifica se o parâmetro 'year' está presente na URL e é válido
if (isset($_GET['year'])) {
    $year = $_GET['year'];
} else {
    // Define o ano atual como padrão se o parâmetro 'year' não estiver presente
    $year = date('Y');
}

// Inicializa os totais
$totalOs = 0;
$totalBacklogs = 0;
$totalBacklogBi = 0;
$totalSuporte = 0;
$totalSubtasks = 0;
$totalCompletedTasks = 0;
$totalPendingTasks = 0;
$totalArchived = 0;
$totalPagas = 0;
$totalNaoPagas = 0;
$valoresStatus = [];

// Consulta para a tabela 'os'
$queryOs = $year === 'all' ? 
    "SELECT COUNT(*) AS total FROM os" : 
    "SELECT COUNT(*) AS total FROM os WHERE YEAR(Dt_inicial) = :year";

// Consulta para a tabela 'backlog'
$queryBacklog = $year === 'all' ? 
    "SELECT COUNT(*) AS total FROM backlog" : 
    "SELECT COUNT(*) AS total FROM backlog WHERE YEAR(Dt_criacao) = :year";

// Consulta para a tabela 'backlogbi'
$queryBacklogBi = $year === 'all' ? 
    "SELECT COUNT(*) AS total FROM backlogbi" : 
    "SELECT COUNT(*) AS total FROM backlogbi WHERE YEAR(Dt_criacao) = :year";

    // Consulta para a tabela 'suporte'
$querySuporte = $year === 'all' ? 
    "SELECT COUNT(*) AS total FROM suporte" : 
    "SELECT COUNT(*) AS total FROM suporte WHERE YEAR(Dt_criacao) = :year";


    // Consulta para a tabela 'subtasks'
$querySubtasks = $year === 'all' ?
    "SELECT COUNT(*) AS total FROM subtasks" :
    "SELECT COUNT(*) AS total FROM subtasks WHERE YEAR(created_at) = :year";


// Consulta para contar tarefas concluídas
$queryCompletedTasks = "SELECT COUNT(*) AS total FROM subtasks WHERE status = 'done'";
if ($year !== 'all') {
    $queryCompletedTasks .= " AND YEAR(created_at) = :year";
}


// Consulta para contar tarefas pendentes
$queryPendingTasks = "SELECT COUNT(*) AS total FROM subtasks WHERE status IN ('todo', 'doing')";
if ($year !== 'all') {
    $queryPendingTasks .= " AND YEAR(created_at) = :year";
}


// Consulta para contar cards arquivados
$queryArchivedCards = "SELECT COUNT(*) AS total FROM cards WHERE is_archived = 1";
if ($year !== 'all') {
    $queryArchivedCards .= " AND YEAR(created_at) = :year";
}

$totalArchived = getTotal($pdo, $queryArchivedCards, $year); 


// Consulta para buscar as últimas OS em "Em Desenvolvimento", sempre do ano atual
$queryUltimasOSEmDesenvolvimento = "SELECT N_os, Nome_os, Valor FROM os 
WHERE Status_contratada = 'Em Desenvolvimento' AND YEAR(Dt_inicial) = '$currentYear' 
ORDER BY Dt_inicial DESC LIMIT 2";

$stmt = $pdo->prepare($queryUltimasOSEmDesenvolvimento);
$stmt->execute();
$ultimasOSEmDesenvolvimento = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatarValorMonetario($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Consulta para buscar as últimas OS em "Em Produção"
$queryUltimasOSEmProducao = "SELECT N_os, Nome_os, Valor FROM os 
WHERE Status_contratada = 'Em Produção' AND YEAR(Dt_inicial) = :currentYear 
ORDER BY Dt_inicial DESC LIMIT 2";

$stmt = $pdo->prepare($queryUltimasOSEmProducao);
$stmt->bindParam(':currentYear', $currentYear, PDO::PARAM_STR);
$stmt->execute();
$ultimasOSEmProducao = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Após buscar as últimas OS em desenvolvimento, formate os valores corretamente
foreach ($ultimasOSEmDesenvolvimento as $key => $os) {
    // Certifique-se de que o valor é um número antes de formatá-lo
    if (is_numeric($os['Valor'])) {
        $ultimasOSEmDesenvolvimento[$key]['Valor'] = formatarValorMonetario($os['Valor']);
    } else {
        // Caso não seja numérico, defina um valor padrão ou mantenha o valor original
        $ultimasOSEmDesenvolvimento[$key]['Valor'] = 'R$ 0,00'; // ou mantenha o valor original
    }
}

// Formatar os valores das últimas OS em produção
foreach ($ultimasOSEmProducao as $key => $os) {
    if (is_numeric($os['Valor'])) {
        $ultimasOSEmProducao[$key]['Valor'] = formatarValorMonetario($os['Valor']);
    } else {
        $ultimasOSEmProducao[$key]['Valor'] = 'R$ 0,00';
    }
}


// Adicione este bloco logo após definir as suas consultas iniciais e antes de qualquer chamada a `getTotal()`
$querySuportesPendentes = "SELECT COUNT(*) FROM suporte 
WHERE Status_suporte NOT IN ('Cancelada', 'Resolvida')
AND YEAR(Dt_criacao) = :year";

$stmtSuportesPendentes = $pdo->prepare($querySuportesPendentes);
$stmtSuportesPendentes->bindParam(':year', $year, PDO::PARAM_STR);
$stmtSuportesPendentes->execute();
$totalSuportesPendentes = $stmtSuportesPendentes->fetchColumn();



// Consulta para o total de OS pagas
$queryTotalPagas = "SELECT SUM(Valor) AS total FROM os WHERE Os_paga = 1";
if ($year !== 'all') {
    $queryTotalPagas .= " AND YEAR(Dt_inicial) = :year";
}
$totalPagas = getTotal($pdo, $queryTotalPagas, $year);
// Formatação aplicada após a obtenção do total
$totalPagasFormatted = number_format((float)$totalPagas, 2, ',', '.');

// Consulta para o total de OS não pagas
$queryTotalNaoPagas = "SELECT SUM(Valor) AS total FROM os WHERE Os_paga = 0";
if ($year !== 'all') {
    $queryTotalNaoPagas .= " AND YEAR(Dt_inicial) = :year";
}
$totalNaoPagas = getTotal($pdo, $queryTotalNaoPagas, $year);
// Formatação aplicada após a obtenção do total
$totalNaoPagasFormatted = number_format((float)$totalNaoPagas, 2, ',', '.');




// Valores por status de OS
$statuses = ['Em Produção', 'Em Desenvolvimento', 'Em Homologação', 'Não Começou'];
$valoresStatus = [];

foreach ($statuses as $status) {
    $queryStatus = "SELECT SUM(Valor) AS total FROM os WHERE Status_contratada = :status";
    if ($year !== 'all') {
        $queryStatus .= " AND YEAR(Dt_inicial) = :year";
    }
    $stmt = $pdo->prepare($queryStatus);
    $stmt->bindParam(':status', $status);
    if ($year !== 'all') {
        $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    }
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    // Certifique-se de que o valor está sendo corretamente recuperado e convertido
    $valorTotal = $resultado['total'] ?? 0;
    // Aplicando a formatação ao valor antes de adicionar ao array
    $valorFormatado = number_format($valorTotal, 2, ',', '.');
    $valoresStatus[$status] = $valorFormatado;
}


// Consulta para contar backlogs que foram enviados para OS no ano selecionado ou em todos os anos
$queryEncaminhado1 = $selectedYear === 'all' ? 
    "SELECT COUNT(*) AS total FROM backlog WHERE Encaminhado_os = 1" : 
    "SELECT COUNT(*) AS total FROM backlog WHERE Encaminhado_os = 1 AND YEAR(Dt_criacao) = :year";

$stmtEncaminhado1 = $pdo->prepare($queryEncaminhado1);
if ($selectedYear !== 'all') {
    $stmtEncaminhado1->bindParam(':year', $selectedYear, PDO::PARAM_INT);
}
$stmtEncaminhado1->execute();
$totalEncaminhado1 = $stmtEncaminhado1->fetch(PDO::FETCH_ASSOC)['total'];



// Consulta para buscar os últimos BIs encaminhados
$queryUltimosBacklogsBI = "SELECT Id, Projeto FROM backlogbi WHERE Encaminhado_os = 1 ORDER BY Dt_criacao DESC LIMIT 3";

$stmt = $pdo->prepare($queryUltimosBacklogsBI);
$stmt->execute();
$ultimosBacklogsBI = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consulta atualizada para contar apenas backlogs não encaminhados como OS
$queryBacklogNaoEncaminhados = $year === 'all' ? 
    "SELECT COUNT(*) AS total FROM backlog WHERE Encaminhado_os = 0" : 
    "SELECT COUNT(*) AS total FROM backlog WHERE Encaminhado_os = 0 AND YEAR(Dt_criacao) = :year";

// Executa a consulta para contar os backlogs não encaminhados
$totalBacklogsNaoEncaminhados = getTotal($pdo, $queryBacklogNaoEncaminhados, $year);


// Consulta para contar o total de suportes que não estão com status 'Cancelada' ou 'Resolvida', filtrados pelo ano de criação
$stmtSuportesAtivos = $pdo->prepare("SELECT COUNT(*) AS total FROM suporte WHERE Status_suporte NOT IN ('Cancelada') AND YEAR(Dt_criacao) = :year");
$stmtSuportesAtivos->bindParam(':year', $selectedYear, PDO::PARAM_INT);
$stmtSuportesAtivos->execute();
$totalSuportesAtivos = $stmtSuportesAtivos->fetch(PDO::FETCH_ASSOC)['total'];

// Consulta para contar o total de suportes com status 'Resolvida', filtrados pelo ano de criação
$stmtSuportesResolvidos = $pdo->prepare("SELECT COUNT(*) AS total_resolvidos FROM suporte WHERE Status_suporte = 'Resolvida' AND YEAR(Dt_criacao) = :year");
$stmtSuportesResolvidos->bindParam(':year', $selectedYear, PDO::PARAM_INT);
$stmtSuportesResolvidos->execute();
$totalSuportesResolvidos = $stmtSuportesResolvidos->fetch(PDO::FETCH_ASSOC)['total_resolvidos'];

// A porcentagem de suportes resolvidos é calculada em relação ao total geral de suportes ativos
$porcentagemSuportesResolvidos = ($totalSuportesAtivos > 0) ? ($totalSuportesResolvidos / $totalSuportesAtivos) * 100 : 0;






// Função ajustada para executar e obter o total de uma consulta
function getTotal($pdo, $query, $year) {
    $stmt = $pdo->prepare($query);
    if ($year !== 'all') {
        $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Obtém os totais
$totalOs = getTotal($pdo, $queryOs, $year);
$totalBacklogs = getTotal($pdo, $queryBacklog, $year);
$totalBacklogBi = getTotal($pdo, $queryBacklogBi, $year);
$totalSuporte = getTotal($pdo, $querySuporte, $year);
$totalSubtasks = getTotal($pdo, $querySubtasks, $year);
$totalCompletedTasks = getTotal($pdo, $queryCompletedTasks, $year);
$totalPendingTasks = getTotal($pdo, $queryPendingTasks, $year);
$totalArchived = getTotal($pdo, $queryArchivedCards, $year);

// Retorna os dados em formato JSON
header('Content-Type: application/json');
echo json_encode([
    "totalOs" => $totalOs,
    "totalBacklogs" => $totalBacklogsNaoEncaminhados,
    "totalBacklogBi" => $totalBacklogBi,
    "totalSuporte" => $totalSuportesPendentes,
    "totalSuportesResolvidos" => $totalSuportesResolvidos,
    "porcentagemSuportesResolvidos" => $porcentagemSuportesResolvidos,
    "totalSubtasks" => $totalSubtasks,
    "totalCompletedTasks" => $totalCompletedTasks,
    "totalPendingTasks" => $totalPendingTasks,
    "totalArchived" => $totalArchived,
    "totalPagas" => number_format($totalPagas, 2, ',', '.'),
    "totalNaoPagas" => number_format($totalNaoPagas, 2, ',', '.'),
    "valoresStatus" => $valoresStatus,
    "ultimasOSEmDesenvolvimento" => $ultimasOSEmDesenvolvimento,
    "ultimasOSEmProducao" => $ultimasOSEmProducao,
    "ultimosBacklogsBI" => $ultimosBacklogsBI,
    "totalEncaminhado1" => $totalEncaminhado1,
    



]);
?>

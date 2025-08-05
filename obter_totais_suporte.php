<?php
session_start();
require 'db.php';

// Redireciona se o usuário não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Define o ano atual como padrão se nenhum filtro de ano for passado
$anoAtual = date('Y');
$anoFiltrado = $_GET['year'] ?? $anoAtual;
$empresaId = $_SESSION['EmpresaId'] ?? null;

// Lista de estados que geralmente são excluídos dos totais a menos que especificamente filtrados
$statusExcluidos = ['Resolvida', 'Cancelada'];

// Monta a cláusula WHERE com base nos filtros
$whereClauses = " WHERE YEAR(Dt_criacao) = :ano";
$parametros = [':ano' => $anoFiltrado];

// Verifica se os estados 'Resolvida' ou 'Cancelada' devem ser incluídos
if (!empty($_GET['status_suporte']) && in_array($_GET['status_suporte'], $statusExcluidos)) {
    $whereClauses .= " AND Status_suporte = :statusSuporte";
    $parametros[':statusSuporte'] = $_GET['status_suporte'];
} else {
    // Exclui 'Resolvida' e 'Cancelada' se não forem especificamente filtrados
    $whereClauses .= " AND Status_suporte NOT IN (:status1, :status2)";
    $parametros[':status1'] = $statusExcluidos[0];
    $parametros[':status2'] = $statusExcluidos[1];
}

// Adiciona filtros adicionais
if (!empty($_GET['solicitado_por'])) {
    $whereClauses .= " AND Solicitado_por = :solicitadoPor";
    $parametros[':solicitadoPor'] = $_GET['solicitado_por'];
}

if (!empty($_GET['para_contratada'])) {
    $whereClauses .= " AND Para_contratada = :paraContratada";
    $parametros[':paraContratada'] = $_GET['para_contratada'];
}

if (isset($_GET['status_prazo'])) {
    if ($_GET['status_prazo'] == 'No Prazo') {
        $whereClauses .= " AND Prazo_previsto >= CURDATE()";
    } elseif ($_GET['status_prazo'] == 'Atrasada') {
        $whereClauses .= " AND Prazo_previsto < CURDATE()";
    }
}

if ($empresaId !== null) {
    $whereClauses .= " AND Para_contratada = :empresaId";
    $parametros[':empresaId'] = $empresaId;
}

// Consultas de total de suportes por prioridade
$totalSuportesStmt = $pdo->prepare("SELECT COUNT(*) FROM suporte" . $whereClauses);
$totalPrioridadeAltaStmt = $pdo->prepare("SELECT COUNT(*) FROM suporte" . $whereClauses . " AND Prioridade = 'Alta'");
$totalPrioridadeMediaStmt = $pdo->prepare("SELECT COUNT(*) FROM suporte" . $whereClauses . " AND Prioridade = 'Média'");
$totalPrioridadeBaixaStmt = $pdo->prepare("SELECT COUNT(*) FROM suporte" . $whereClauses . " AND Prioridade = 'Baixa'");

// Execução das consultas
foreach ([$totalSuportesStmt, $totalPrioridadeAltaStmt, $totalPrioridadeMediaStmt, $totalPrioridadeBaixaStmt] as $stmt) {
    $stmt->execute($parametros);
}

// Coleta dos resultados
$totais = [
    'totalSuportes' => $totalSuportesStmt->fetchColumn(),
    'totalPrioridadeAlta' => $totalPrioridadeAltaStmt->fetchColumn(),
    'totalPrioridadeMedia' => $totalPrioridadeMediaStmt->fetchColumn(),
    'totalPrioridadeBaixa' => $totalPrioridadeBaixaStmt->fetchColumn(),
];

header('Content-Type: application/json');
echo json_encode($totais);
?>

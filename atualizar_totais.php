<?php
// Inclui o arquivo de configuração do banco de dados
include 'db.php';

// Recebe os filtros do frontend
$ano = $_POST['year'] ?? null;
$responsavel = $_POST['responsavel'] ?? null;
$status = $_POST['status'] ?? null;
$prioridade = $_POST['prioridade'] ?? null;
$encaminhadoOs = $_POST['encaminhado_os'] ?? null;

// Prepara a cláusula WHERE com os filtros
$whereClauses = [];
$params = [];

if ($ano) {
    $whereClauses[] = "YEAR(b.Dt_criacao) = :year";
    $params[':year'] = $ano;
}
if ($responsavel) {
    $whereClauses[] = "b.Responsavel = :responsavel";
    $params[':responsavel'] = $responsavel;
}
// Adicione condições similares para outros filtros

$whereSql = $whereClauses ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Consulta para calcular o total de backlogbis
$query = "SELECT COUNT(*) AS total FROM backlogbi b" . $whereSql;

$stmt = $pdo->prepare($query);
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}

$stmt->execute();
$totalBacklogbis = $stmt->fetchColumn();

// Prepara os dados a serem retornados
$resultados = [
    'total' => $totalBacklogbis,
    // 'totalPrioridadeAlta' => $totalPrioridadeAlta, // Calcule e adicione outros totais conforme necessário
];

// Retorna os resultados em formato JSON
echo json_encode($resultados);
?>

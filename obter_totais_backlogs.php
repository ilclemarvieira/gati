<?php
require 'db.php';

// Define o ano atual se nenhum ano for passado via GET
$anoAtual = date('Y');
$anoFiltrado = $_GET['year'] ?? $anoAtual;

// Prepara a base da consulta SQL com o filtro de ano aplicado por padrão
$consultaBase = "SELECT COUNT(*) FROM backlog WHERE YEAR(Dt_criacao) = :ano";

// Define as condições adicionais para as consultas de prioridade
$condicaoPrioridade = " AND Prioridade = :prioridade";

// Prepara as consultas para cada prioridade
$consultaPrioridadeAlta = $consultaBase . $condicaoPrioridade;
$consultaPrioridadeMedia = $consultaPrioridadeAlta;
$consultaPrioridadeBaixa = $consultaPrioridadeAlta;

// Prepara os statements
$totalBacklogsStmt = $pdo->prepare($consultaBase);
$totalPrioridadeAltaStmt = $pdo->prepare($consultaPrioridadeAlta);
$totalPrioridadeMediaStmt = $pdo->prepare($consultaPrioridadeMedia);
$totalPrioridadeBaixaStmt = $pdo->prepare($consultaPrioridadeBaixa);

// Define os parâmetros de ano para todas as consultas
$parametrosAno = [':ano' => $anoFiltrado];

// Executa a consulta para o total de backlogs com o ano filtrado
$totalBacklogsStmt->execute($parametrosAno);

// Executa as consultas para as prioridades com o ano filtrado
$parametrosPrioridadeAlta = array_merge($parametrosAno, [':prioridade' => 'Alta']);
$totalPrioridadeAltaStmt->execute($parametrosPrioridadeAlta);

$parametrosPrioridadeMedia = array_merge($parametrosAno, [':prioridade' => 'Média']);
$totalPrioridadeMediaStmt->execute($parametrosPrioridadeMedia);

$parametrosPrioridadeBaixa = array_merge($parametrosAno, [':prioridade' => 'Baixa']);
$totalPrioridadeBaixaStmt->execute($parametrosPrioridadeBaixa);

// Obtém os totais
$totais = [
    'totalBacklogs' => $totalBacklogsStmt->fetchColumn(),
    'totalPrioridadeAlta' => $totalPrioridadeAltaStmt->fetchColumn(),
    'totalPrioridadeMedia' => $totalPrioridadeMediaStmt->fetchColumn(),
    'totalPrioridadeBaixa' => $totalPrioridadeBaixaStmt->fetchColumn(),
];

header('Content-Type: application/json');
echo json_encode($totais);

?>

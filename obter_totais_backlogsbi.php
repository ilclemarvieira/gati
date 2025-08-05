<?php
require 'db.php';

// Captura o filtro de ano da URL, se presente
$anoFiltrado = isset($_GET['year']) && $_GET['year'] !== '' ? $_GET['year'] : null;

// Prepara a base da consulta SQL
$consultaBase = "SELECT COUNT(*) FROM backlogbi";
$consultaComFiltro = $anoFiltrado ? $consultaBase . " WHERE YEAR(Dt_criacao) = :ano" : $consultaBase;

// Prepara as consultas para cada prioridade
$totalBacklogsStmt = $pdo->prepare($consultaComFiltro);
$totalPrioridadeAltaStmt = $pdo->prepare($consultaComFiltro . " AND Prioridade = 'Alta'");
$totalPrioridadeMediaStmt = $pdo->prepare($consultaComFiltro . " AND Prioridade = 'Média'");
$totalPrioridadeBaixaStmt = $pdo->prepare($consultaComFiltro . " AND Prioridade = 'Baixa'");

// Executa as consultas com ou sem filtro de ano
if ($anoFiltrado) {
    $parametros = [':ano' => $anoFiltrado];
    $totalBacklogsStmt->execute($parametros);
    $totalPrioridadeAltaStmt->execute($parametros);
    $totalPrioridadeMediaStmt->execute($parametros);
    $totalPrioridadeBaixaStmt->execute($parametros);
} else {
    $totalBacklogsStmt->execute();
    $totalPrioridadeAltaStmt->execute();
    $totalPrioridadeMediaStmt->execute();
    $totalPrioridadeBaixaStmt->execute();
}

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
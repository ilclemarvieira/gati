<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo "Não autorizado";
    exit;
}
include 'db.php';

// Recebe os dados do formulário
$valor = $_POST['valor'] ?? null;
$ano = $_POST['ano'] ?? null;

// Converte o valor para o formato numérico do PHP
// 1. Remove os pontos
$valor = str_replace('.', '', $valor);
// 2. Troca a vírgula por ponto
$valor = str_replace(',', '.', $valor);

// Validação simples dos dados
if ($valor === null || $ano === null) {
    echo "Dados inválidos.";
    exit;
}

// Prepara a inserção no banco de dados
$stmt = $pdo->prepare("INSERT INTO empenho (valor, ano) VALUES (?, ?)");
$success = $stmt->execute([$valor, $ano]);

if ($success) {
    echo "Empenho cadastrado com sucesso!";
} else {
    echo "Erro ao cadastrar empenho.";
}

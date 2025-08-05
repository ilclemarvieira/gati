<?php
// Inicia a sessão
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    // Se não estiver logado, retorna uma mensagem de erro
    echo "Não autorizado";
    exit;
}

// Inclui o arquivo de conexão com o banco de dados
include 'db.php';

// Recebe os dados do formulário
$id = $_POST['id'] ?? null;
$valor = $_POST['valor'] ?? null;
$ano = $_POST['ano'] ?? null;

// Converte o valor para o formato numérico do PHP
// 1. Remove os pontos
$valor = str_replace('.', '', $valor);
// 2. Troca a vírgula por ponto
$valor = str_replace(',', '.', $valor);

// Validação simples dos dados
if ($id === null || $valor === null || $ano === null) {
    echo "Dados inválidos.";
    exit;
}

// Prepara a atualização no banco de dados
$stmt = $pdo->prepare("UPDATE empenho SET valor = ?, ano = ? WHERE id = ?");
$success = $stmt->execute([$valor, $ano, $id]);

if ($success) {
    echo "Empenho atualizado com sucesso!";
} else {
    echo "Erro ao atualizar empenho.";
}
?>

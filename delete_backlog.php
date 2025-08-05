<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: login.php');
    exit;
}

require 'db.php'; // Conecta ao banco de dados

// Recebe o ID do item a ser excluído
$id = $_POST['id'];

// Primeiro, busca o caminho do arquivo anexado
$stmt = $pdo->prepare("SELECT Anexo FROM backlog WHERE Id = ?");
$stmt->execute([$id]);
$backlog = $stmt->fetch();

// Se existir um arquivo, tenta excluí-lo
if ($backlog && file_exists($backlog['Anexo'])) {
    unlink($backlog['Anexo']);
}

// Prepara a query SQL para excluir do banco de dados
$stmt = $pdo->prepare("DELETE FROM backlog WHERE Id = ?");

// Executa a query SQL
if ($stmt->execute([$id])) {
    echo "Item de backlog excluído com sucesso.";
} else {
    echo "Erro ao excluir item de backlog.";
}
?>

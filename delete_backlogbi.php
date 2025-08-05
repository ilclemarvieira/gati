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
$stmt = $pdo->prepare("SELECT Anexo FROM backlogbi WHERE Id = ?");
$stmt->execute([$id]);
$backlogbi = $stmt->fetch();

// Se existir um arquivo, tenta excluí-lo
if ($backlogbi && file_exists($backlogbi['Anexo'])) {
    unlink($backlogbi['Anexo']);
}

// Prepara a query SQL para excluir do banco de dados
$stmt = $pdo->prepare("DELETE FROM backlogbi WHERE Id = ?");

// Executa a query SQL
if ($stmt->execute([$id])) {
    echo "Item de backlog excluído com sucesso.";
} else {
    echo "Erro ao excluir item de backlog.";
}
?>

<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: login.php');
    exit;
}

require 'db.php'; // Conecta ao banco de dados

// Recebe o ID do suporte a ser excluído
$id = $_POST['id'];

// Primeiro, busca o caminho do anexo para esse suporte
$stmtAnexo = $pdo->prepare("SELECT Anexos FROM suporte WHERE Id = ?");
$stmtAnexo->execute([$id]);
$anexo = $stmtAnexo->fetchColumn();

// Prepara a query SQL para excluir o suporte do banco de dados
$stmt = $pdo->prepare("DELETE FROM suporte WHERE Id = ?");

// Executa a query SQL
if ($stmt->execute([$id])) {
    // Se houver um anexo, tenta excluí-lo do sistema de arquivos
    if ($anexo) {
        $caminhoCompleto = __DIR__ . '/' . $anexo;
        if (file_exists($caminhoCompleto)) {
            unlink($caminhoCompleto); // Exclui o arquivo
        }
    }
    echo "Suporte excluído com sucesso.";
} else {
    echo "Erro ao excluir o suporte.";
}
?>

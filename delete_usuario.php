<?php
session_start();
require 'db.php'; // Ajuste o caminho conforme necessário

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Verifica se um ID foi enviado
if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE Id = ?");
    if ($stmt->execute([$id])) {
        echo "Usuário excluído com sucesso.";
    } else {
        echo "Erro ao excluir o usuário.";
    }
}
?>

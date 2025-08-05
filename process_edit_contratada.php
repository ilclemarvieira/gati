<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? 0;
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';

    $stmt = $pdo->prepare("UPDATE contratadas SET Nome = ?, E_mail = ? WHERE Id = ?");
    if ($stmt->execute([$nome, $email, $id])) {
        echo "Contratada atualizada com sucesso.";
    } else {
        echo "Erro ao atualizar contratada.";
    }
}
?>

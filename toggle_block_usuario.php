<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    echo "Acesso negado.";
    exit;
}

require 'db.php';

if (isset($_POST['id']) && isset($_POST['bloqueado'])) {
    $id = $_POST['id'];
    $bloqueado = $_POST['bloqueado']; // 1 para bloqueado, 2 para desbloqueado

    $stmt = $pdo->prepare("UPDATE usuarios SET bloqueado = ? WHERE Id = ?");
    if ($stmt->execute([$bloqueado, $id])) {
        echo "Status do usuário atualizado com sucesso.";
    } else {
        echo "Erro ao atualizar o status do usuário.";
    }
} else {
    echo "Dados inválidos.";
}
?>

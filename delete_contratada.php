<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? 0;

    $stmt = $pdo->prepare("DELETE FROM contratadas WHERE Id = ?");
    if ($stmt->execute([$id])) {
        echo "Contratada excluída com sucesso.";
    } else {
        echo "Erro ao excluir contratada.";
    }
}
?>

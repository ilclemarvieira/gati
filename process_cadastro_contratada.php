<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO contratadas (Nome, E_mail) VALUES (?, ?)");
    if ($stmt->execute([$nome, $email])) {
        echo "Contratada cadastrada com sucesso.";
    } else {
        echo "Erro ao cadastrar contratada.";
    }
}
?>

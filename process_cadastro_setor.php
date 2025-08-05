<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo "Acesso negado!";
    exit;
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_do_setor = $_POST['nome_do_setor'] ?? '';
    $dt_cadastro   = $_POST['dt_cadastro']   ?? date('Y-m-d'); // Ou outra lógica

    try {
        $stmt = $pdo->prepare("INSERT INTO setores (nome_do_setor, dt_cadastro) VALUES (:nome_do_setor, :dt_cadastro)");
        $stmt->bindParam(':nome_do_setor', $nome_do_setor);
        $stmt->bindParam(':dt_cadastro', $dt_cadastro);
        $stmt->execute();

        echo "Setor cadastrado com sucesso!";
    } catch (Exception $e) {
        echo "Erro ao cadastrar setor: " . $e->getMessage();
    }
} else {
    echo "Requisição inválida!";
}

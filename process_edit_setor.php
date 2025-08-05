<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo "Acesso negado!";
    exit;
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id            = $_POST['id'] ?? null;
    $nome_do_setor = $_POST['nome_do_setor'] ?? '';
    $dt_cadastro   = $_POST['dt_cadastro'] ?? date('Y-m-d');

    if ($id) {
        try {
            $stmt = $pdo->prepare("UPDATE setores SET nome_do_setor = :nome_do_setor, dt_cadastro = :dt_cadastro WHERE id = :id");
            $stmt->bindParam(':nome_do_setor', $nome_do_setor);
            $stmt->bindParam(':dt_cadastro', $dt_cadastro);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            echo "Setor atualizado com sucesso!";
        } catch (Exception $e) {
            echo "Erro ao atualizar setor: " . $e->getMessage();
        }
    } else {
        echo "ID de setor inválido!";
    }
} else {
    echo "Requisição inválida!";
}

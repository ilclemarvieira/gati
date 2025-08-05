<?php
include 'db.php';
include 'autenticacao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_empresa = $_POST['id_empresa'];
    $inicio = $_POST['inicio'];
    $termino = $_POST['termino'];
    $maoDeObra = $_POST['maoDeObra'];

    $query = "INSERT INTO historico_pagamento (id_empresa, mes_inicio, mes_termino, valor_mao_obra) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issd", $id_empresa, $inicio, $termino, $maoDeObra);

    if($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Registro inserido com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $conn->error]);
    }

    $stmt->close();
    exit;
}
?>

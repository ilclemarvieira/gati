<?php
session_start();
include 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $Id_OS = $_POST['Id_OS'] ?? null;
    $trimestre = $_POST['trimestre'] ?? null;
    $mes = $_POST['mes'] ?? null;
    $ano = $_POST['ano'] ?? null;
    $status = $_POST['status'] ?? null;
    $usuario_id = $_SESSION['usuario_id'];
    $data_cadastro = date('Y-m-d');

    if (!$Id_OS || !$trimestre || !$mes || !$ano || !$status) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
        exit;
    }

    // Detecta se é backlog ou OS
    $tipo = 'OS';
    $idReal = $Id_OS;
    if (str_starts_with($Id_OS, 'B-')) {
        $tipo = 'Backlog';
        $idReal = substr($Id_OS, 2);
    }

    // Verifica se já existe no cronograma
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cronograma WHERE Id_OS = ? AND Tipo = ?");
    $stmt->execute([$idReal, $tipo]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Este item já está no cronograma.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO cronograma (Id_OS, Tipo, trimestre, ano, mes, status, data_cadastro, usuario_id) VALUES (?,?,?,?,?,?,?,?)");
    $ok = $stmt->execute([$idReal, $tipo, $trimestre, $ano, $mes, $status, $data_cadastro, $usuario_id]);

    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar tarefa.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
}

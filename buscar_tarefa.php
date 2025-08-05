<?php
session_start();
include 'db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID não fornecido.']);
    exit;
}

// Primeiro obter da cronograma para saber o Tipo:
$sqlTipo = "SELECT Tipo, Id_OS FROM cronograma WHERE id = ?";
$stmtTipo = $pdo->prepare($sqlTipo);
$stmtTipo->execute([$id]);
$crono = $stmtTipo->fetch(PDO::FETCH_ASSOC);

if (!$crono) {
    echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada no cronograma.']);
    exit;
}

$tipo = $crono['Tipo'];
$idOs = $crono['Id_OS'];

if ($tipo == 'OS') {
    // JOIN com OS
    $sql = "SELECT c.*, o.N_os, o.Nome_os, o.Apf, o.Valor, o.Dt_inicial, o.Prazo_entrega, 
                   o.Prioridade, o.Status_inova, o.Status_contratada, o.Responsavel, 
                   o.Id_contratada, o.Descricao AS OsDescricao, o.Os_paga, o.Anexo_nf, o.Observacao
            FROM cronograma c
            JOIN os o ON o.Id = c.Id_OS
            WHERE c.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

} else {
    // Tipo = 'Backlog'
    // JOIN com backlog
    $sql = "SELECT c.*, 
                   b.Projeto AS Nome_os, b.Projeto AS N_os, 
                   NULL AS Apf, NULL AS Valor, NULL AS Dt_inicial, NULL AS Prazo_entrega,
                   NULL AS Prioridade, NULL AS Status_inova, NULL AS Status_contratada,
                   NULL AS Responsavel, NULL AS Id_contratada, b.Descricao AS OsDescricao,
                   NULL AS Os_paga, NULL AS Anexo_nf, '' AS Observacao
            FROM cronograma c
            JOIN backlog b ON b.Id = c.Id_OS
            WHERE c.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($tarefa) {
    echo json_encode(['success' => true, 'tarefa' => $tarefa]);
} else {
    echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada.']);
}

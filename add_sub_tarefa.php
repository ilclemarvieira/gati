<?php
include 'db.php';

header('Content-Type: application/json');

if (!isset($_POST['sprint_id']) || !isset($_POST['nome'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

$sprint_id = $_POST['sprint_id'];
$item_id = $_POST['nome']; // ID da OS
$tipo = 'os';
$descricao = '';
$position = 0;

try {
    // Inserir a subtarefa
    $stmt = $pdo->prepare("INSERT INTO sprint_itens (sprint_id, tipo, item_id, position, descricao, data) VALUES (:sprint_id, :tipo, :item_id, :position, :descricao, CURDATE())");
    $stmt->bindParam(':sprint_id', $sprint_id, PDO::PARAM_INT);
    $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    $stmt->bindParam(':position', $position, PDO::PARAM_INT);
    $stmt->bindParam(':descricao', $descricao, PDO::PARAM_STR);

    if ($stmt->execute()) {
        // Buscar os detalhes da OS recém adicionada
        $osStmt = $pdo->prepare("SELECT N_os, Nome_os FROM os WHERE Id = :item_id");
        $osStmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
        $osStmt->execute();
        $osData = $osStmt->fetch(PDO::FETCH_ASSOC);

        if ($osData) {
            echo json_encode([
                'success' => true,
                'id' => $pdo->lastInsertId(),
                'os_numero' => $osData['N_os'], // Use 'os_numero' para manter a consistência
                'os_nome' => $osData['Nome_os']  // Use 'os_nome' para manter a consistência
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar detalhes da OS.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao adicionar item na sprint.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
exit;
?>
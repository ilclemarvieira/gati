<?php
// Inclua sua conexão com o banco de dados aqui
include 'db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if(isset($data['id'])) {
    $metaId = $data['id'];

    // Preparar statement para deletar
    $stmt = $pdo->prepare("DELETE FROM metas WHERE id = :id");
    $stmt->bindParam(':id', $metaId, PDO::PARAM_INT);
    
    if($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Não foi possível excluir a meta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID da meta não foi fornecido.']);
}
?>

<?php
// Inclua sua conexão com o banco de dados aqui
include 'db.php';

if (isset($_POST['os_id'])) {
    $osId = $_POST['os_id'];
    $stmt = $pdo->prepare("SELECT id, arquivo FROM os_anexos WHERE os_id = ?");
    $stmt->execute([$osId]);
    $anexos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($anexos);
} else {
    echo json_encode(['error' => 'ID da OS não fornecido.']);
}
?>

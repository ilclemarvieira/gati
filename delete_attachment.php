<?php
// delete_attachment.php
require 'db.php'; // Ajuste o caminho conforme o necessário

session_start();

// Verifique se o usuário está logado e tem permissão para excluir anexos
if (!isset($_SESSION['usuario_id'])) {
    echo "Usuário não autenticado.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backlogId'])) {
    $backlogId = $_POST['backlogId'];

    // Busque o caminho do anexo no banco de dados
    $stmt = $pdo->prepare("SELECT Anexo FROM backlog WHERE Id = :id");
    $stmt->execute([':id' => $backlogId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && file_exists($result['Anexo'])) {
        // Exclui o arquivo do sistema de arquivos
        unlink($result['Anexo']);

        // Remove a referência do anexo na tabela backlog
        $updateStmt = $pdo->prepare("UPDATE backlog SET Anexo = NULL WHERE Id = :id");
        $updateStmt->execute([':id' => $backlogId]);

        echo "Anexo excluído com sucesso.";
    } else {
        echo "Anexo não encontrado ou já foi excluído.";
    }
} else {
    echo "Requisição inválida.";
}
?>

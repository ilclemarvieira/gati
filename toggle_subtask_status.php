<?php
include 'db.php'; // Certifique-se de que este arquivo contém a conexão com o banco de dados

session_start(); // Inicia a sessão para acessar as variáveis de sessão

header('Content-Type: application/json');

// Verifica se os dados necessários foram enviados
if (isset($_POST['subtaskId'], $_POST['status'])) {
    $subtaskId = $_POST['subtaskId'];
    $status = $_POST['status'];

    // Verifica se o usuário está logado e obtém o ID do usuário logado
    $userId = $_SESSION['usuario_id'] ?? null; // Substitua 'usuario_id' pelo nome da chave real na sua sessão

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Usuário não logado.']);
        exit;
    }

    // Valida se o status é um dos valores permitidos
    if (!in_array($status, ['todo', 'doing', 'done'])) {
        echo json_encode(['success' => false, 'error' => 'Status inválido.']);
        exit;
    }

    // Atualiza a subtarefa com o ID do usuário logado
    $stmt = $pdo->prepare("UPDATE subtasks SET status = ?, updated_at = NOW(), last_edited_by = ? WHERE id = ?");
    if ($stmt->execute([$status, $userId, $subtaskId])) {
        // Após a atualização, busca as informações adicionais, incluindo o nome do usuário que fez a última edição
        $infoStmt = $pdo->prepare("
            SELECT 
                subtasks.updated_at, 
                usuarios.Nome as usuario_nome 
            FROM subtasks 
            LEFT JOIN usuarios ON subtasks.last_edited_by = usuarios.Id 
            WHERE subtasks.id = ?
        ");
        $infoStmt->execute([$subtaskId]);
        $subtaskInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);

        if ($subtaskInfo) {
            echo json_encode([
                'success' => true,
                'editedAt' => $subtaskInfo['updated_at'],
                'usuarioNome' => $subtaskInfo['usuario_nome'] // O nome do usuário que fez a última edição
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar informações da subtarefa.']);
        }
    } else {
        $error = $stmt->errorInfo();
        echo json_encode(['success' => false, 'error' => $error[2]]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Dados insuficientes.']);
}
?>

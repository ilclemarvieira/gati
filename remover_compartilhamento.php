<?php
// Iniciar sessão
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Conexão com o banco de dados
include 'db.php'; // Substitua 'db.php' pelo seu arquivo de conexão ao banco

// Supondo que $_POST['compartilhamento_id'] seja o ID na tabela tarefas_compartilhadas
if (isset($_POST['compartilhamento_id']) && is_numeric($_POST['compartilhamento_id'])) {
    $compartilhamentoId = (int)$_POST['compartilhamento_id'];
    $usuarioId = (int)$_SESSION['usuario_id'];

    // Preparar consulta para verificar se o compartilhamento pertence ao usuário logado
    $sql = "SELECT id FROM tarefas_compartilhadas WHERE id = :compartilhamento_id AND id_usuario_compartilhado = :usuario_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':compartilhamento_id', $compartilhamentoId, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Se o compartilhamento pertence ao usuário, procede com a remoção
        $sqlDelete = "DELETE FROM tarefas_compartilhadas WHERE id = :compartilhamento_id";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->bindParam(':compartilhamento_id', $compartilhamentoId, PDO::PARAM_INT);
        $stmtDelete->execute();

        echo json_encode(['success' => true, 'message' => 'Compartilhamento removido com sucesso']);
    } else {
        // Se o compartilhamento não pertence ao usuário, retorna erro
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para remover este compartilhamento']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
}
?>

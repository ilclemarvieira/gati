<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

include 'db.php'; // Inclui a conexão com o banco de dados

// Verifica se a ação de remoção foi acionada e se o ID da tarefa foi fornecido
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['tarefa_id'])) {
    $tarefaId = filter_input(INPUT_GET, 'tarefa_id', FILTER_SANITIZE_NUMBER_INT);
    $id_usuario = $_SESSION['usuario_id'];

    // Inicia a transação
    $pdo->beginTransaction();

    try {
        // Primeiro, remove os compartilhamentos associados à tarefa
        $sqlCompartilhamentos = "DELETE FROM tarefas_compartilhadas WHERE id_tarefa = :tarefaId";
        $stmtCompartilhamentos = $pdo->prepare($sqlCompartilhamentos);
        $stmtCompartilhamentos->bindParam(':tarefaId', $tarefaId, PDO::PARAM_INT);
        $stmtCompartilhamentos->execute();

        // Em seguida, remove a própria tarefa
        $sqlTarefa = "DELETE FROM tarefas WHERE id = :tarefaId AND id_usuario = :id_usuario";
        $stmtTarefa = $pdo->prepare($sqlTarefa);
        $stmtTarefa->bindParam(':tarefaId', $tarefaId, PDO::PARAM_INT);
        $stmtTarefa->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmtTarefa->execute();

        // Confirma as operações se a tarefa foi removida
        if ($stmtTarefa->rowCount() > 0) {
            $pdo->commit();
        } else {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Nenhuma tarefa encontrada para remover ou a tarefa não pertence ao usuário';
            header('Location: minhastarefas.php'); // Redireciona com mensagem de erro
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Erro ao remover tarefa e compartilhamentos: ' . $e->getMessage();
        header('Location: minhastarefas.php'); // Redireciona com mensagem de erro
        exit;
    }
    // Redireciona para minhastarefas.php sem definir mensagem de sucesso
    header('Location: minhastarefas.php');
    exit;
} else {
    $_SESSION['error_message'] = 'Dados inválidos';
    // Redireciona para minhastarefas.php com a mensagem de erro
    header('Location: minhastarefas.php');
    exit;
}
?>

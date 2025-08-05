<?php
// compartilhar_tarefa.php
include 'db.php';
session_start();

// Verifique se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_id'], $_POST['user_to_share'])) {
    $originalTaskId = $_POST['task_id'];
    $targetUserId = $_POST['user_to_share'];

    // Verifica se o usuário selecionado é o criador da tarefa
    $stmtCreatorCheck = $pdo->prepare("SELECT id FROM tarefas WHERE id = :id_tarefa AND id_usuario = :id_usuario");
    $stmtCreatorCheck->execute([
        'id_tarefa' => $originalTaskId,
        'id_usuario' => $targetUserId
    ]);
    $isCreator = $stmtCreatorCheck->fetch(PDO::FETCH_ASSOC);

    if ($isCreator) {
        echo "<script>alert('Não é possível compartilhar a tarefa com o usuário que já é o criador.'); window.history.back();</script>";
        exit;
    }

    // Verificar se a tarefa já foi compartilhada com o usuário selecionado
    $stmtCheckShared = $pdo->prepare("SELECT id FROM tarefas_compartilhadas WHERE id_tarefa = :id_tarefa AND id_usuario_compartilhado = :id_usuario_compartilhado");
    $stmtCheckShared->execute([
        'id_tarefa' => $originalTaskId,
        'id_usuario_compartilhado' => $targetUserId
    ]);
    $alreadyShared = $stmtCheckShared->fetch(PDO::FETCH_ASSOC);

    if ($alreadyShared) {
        echo "<script>alert('Esta tarefa já foi compartilhada com o usuário selecionado.'); window.history.back();</script>";
        exit;
    }

    // Compartilhar a tarefa inserindo um registro na tabela tarefas_compartilhadas
    $stmtInsertSharedTask = $pdo->prepare("INSERT INTO tarefas_compartilhadas (id_tarefa, id_usuario_compartilhado) VALUES (:id_tarefa, :id_usuario_compartilhado)");
    $sharedSuccess = $stmtInsertSharedTask->execute([
        'id_tarefa' => $originalTaskId,
        'id_usuario_compartilhado' => $targetUserId
    ]);

    if ($sharedSuccess) {
        $_SESSION['success_message'] = 'Tarefa compartilhada com sucesso!';
        header('Location: minhastarefas.php');
        exit;
    } else {
        echo "<script>alert('Erro ao compartilhar tarefa.'); window.history.back();</script>";
    }
}
?>

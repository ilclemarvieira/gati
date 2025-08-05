<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

include 'db.php'; // Conexão ao banco de dados

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id_usuario = $_SESSION['usuario_id']; // Pega o ID do usuário da sessão
    $tarefaId = filter_input(INPUT_POST, 'task_id', FILTER_SANITIZE_NUMBER_INT);
    $nome_tarefa = filter_input(INPUT_POST, 'nome_tarefa', FILTER_SANITIZE_STRING);
    $descricao_tarefa = filter_input(INPUT_POST, 'descricao_tarefa', FILTER_SANITIZE_STRING);

    if (empty($nome_tarefa) || empty($descricao_tarefa)) {
        $_SESSION['edit_error'] = 'Por favor, preencha todos os campos.';
        header('Location: minhastarefas.php');
        exit;
    } else {
        // Verifica se o usuário tem permissão para editar a tarefa
        $sqlPermissao = "SELECT 1 FROM tarefas 
                 WHERE id = :tarefaId AND (id_usuario = :id_usuario OR id IN 
                 (SELECT id_tarefa FROM tarefas_compartilhadas WHERE id_usuario_compartilhado = :id_usuario_compartilhado))";
$stmtPermissao = $pdo->prepare($sqlPermissao);
$stmtPermissao->execute([
    'tarefaId' => $tarefaId, 
    'id_usuario' => $id_usuario,
    'id_usuario_compartilhado' => $id_usuario // Aqui você usa o mesmo valor de $id_usuario, mas deixa claro que é para o campo id_usuario_compartilhado
]);

        
        if ($stmtPermissao->fetch()) {
// O usuário tem permissão para editar a tarefa
$sql = "UPDATE tarefas SET nome_tarefa = :nome_tarefa, descricao_tarefa = :descricao_tarefa WHERE id = :tarefaId";

try {
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':nome_tarefa', $nome_tarefa);
$stmt->bindParam(':descricao_tarefa', $descricao_tarefa);
$stmt->bindParam(':tarefaId', $tarefaId);
$stmt->execute();

            $_SESSION['edit_success'] = 'Tarefa atualizada com sucesso!';
        } catch (PDOException $e) {
            $_SESSION['edit_error'] = "Erro ao atualizar tarefa: " . $e->getMessage();
        }
    } else {
        // O usuário não tem permissão para editar a tarefa
        $_SESSION['edit_error'] = "Você não tem permissão para editar esta tarefa.";
    }
    header('Location: minhastarefas.php');
    exit;
}

}
?>

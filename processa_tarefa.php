<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

include 'db.php'; // Conexão com o banco de dados

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_usuario = $_SESSION['usuario_id']; // ID do usuário logado
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    switch ($action) {
        case 'add':
            $nome_tarefa = filter_input(INPUT_POST, 'nome_tarefa', FILTER_SANITIZE_STRING);
            $descricao_tarefa = filter_input(INPUT_POST, 'descricao_tarefa', FILTER_SANITIZE_STRING);

            if (!empty($nome_tarefa) && !empty($descricao_tarefa)) {
                $sql = "INSERT INTO tarefas (nome_tarefa, descricao_tarefa, id_usuario) VALUES (:nome_tarefa, :descricao_tarefa, :id_usuario)";
                $stmt = $pdo->prepare($sql);

                if ($stmt->execute([':nome_tarefa' => $nome_tarefa, ':descricao_tarefa' => $descricao_tarefa, ':id_usuario' => $id_usuario])) {
                    // Redireciona para evitar reenvio do formulário
                    header('Location: minhastarefas.php?success=add');
                    exit;
                } else {
                    $response['message'] = "Erro ao adicionar tarefa.";
                }
            } else {
                $response['message'] = "Por favor, preencha todos os campos.";
            }
            break;

        case 'mark_not_important':
        case 'mark_important':
        case 'mark_complete':
            $tarefaId = filter_input(INPUT_POST, 'tarefa_id', FILTER_SANITIZE_NUMBER_INT);
            $value = (isset($_POST['value']) && $_POST['value'] == 'true') ? 1 : 0;
            $column = ($action == 'mark_important' || $action == 'mark_not_important') ? 'is_important' : 'is_complete';

            if ($tarefaId !== false) {
                $sql = "UPDATE tarefas SET $column = :value WHERE id = :tarefaId AND id_usuario = :id_usuario";
                $stmt = $pdo->prepare($sql);

                if ($stmt->execute([':value' => $value, ':tarefaId' => $tarefaId, ':id_usuario' => $id_usuario])) {
                    $response['success'] = true;
                    $response['message'] = "Tarefa atualizada com sucesso.";
                } else {
                    $response['message'] = "Erro ao atualizar tarefa.";
                }
            } else {
                $response['message'] = "ID da tarefa inválido.";
            }
            break;
            
        default:
            $response['message'] = "Ação inválida.";
            break;
    }

    // Para ações que não são 'add', retorna uma resposta JSON
    if ($action !== 'add') {
        echo json_encode($response);
        exit;
    }
}
?>

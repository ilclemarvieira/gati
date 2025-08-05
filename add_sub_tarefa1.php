<?php
// add_sub_tarefa.php
include 'db.php';

// Coleta dos dados do POST
$sprint_id = $_POST['sprint_id'];
$nome = $_POST['nome'];
$descricao = $_POST['descricao']; // Adicionando o campo descricao
$data = $_POST['data']; // Adicionando o campo data

// Preparação da instrução SQL para inserir os novos dados
$stmt = $pdo->prepare("INSERT INTO sub_tarefas (sprint_id, nome, descricao, data) VALUES (?, ?, ?, ?)");
$stmt->execute([$sprint_id, $nome, $descricao, $data]);

$subTarefaId = $pdo->lastInsertId();

header('Content-Type: application/json');
// Retorna sucesso com os dados inseridos incluindo descricao e data
echo json_encode(['success' => true, 'id' => $subTarefaId, 'nome' => $nome, 'descricao' => $descricao, 'data' => $data]);
?>

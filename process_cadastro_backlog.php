<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Recebe os dados do formulário
$projeto = $_POST['projeto'];
$dt_criacao = $_POST['dt_criacao'];
$prioridade = $_POST['prioridade'];
$status_ideia = $_POST['status_ideia'];
$responsavel = $_POST['responsavel'];
$encaminhado_os = $_POST['encaminhado_os'] === "1" ? 1 : 0; // Ajuste conforme o valor recebido
$descricao = $_POST['descricao'];
$uploadPath = null;

// Processamento do anexo, se existir
if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] == UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/';
    $fileName = basename($_FILES['anexo']['name']);
    $uploadPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['anexo']['tmp_name'], $uploadPath)) {
        echo "Não foi possível salvar o arquivo anexado.";
        $uploadPath = null;
    }
}

// Verificar se o item já existe
$stmt = $pdo->prepare("SELECT * FROM backlog WHERE Projeto = ? AND Descricao = ?");
$stmt->execute([$projeto, $descricao]);

if ($stmt->rowCount() > 0) {
    echo "Um item com esses dados já existe no backlog.";
    exit;
}

// Inserção no banco de dados
$stmt = $pdo->prepare("INSERT INTO backlog (Projeto, Dt_criacao, Prioridade, Status_ideia, Responsavel, Encaminhado_os, Descricao, Anexo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

if ($stmt->execute([$projeto, $dt_criacao, $prioridade, $status_ideia, $responsavel, $encaminhado_os, $descricao, $uploadPath])) {
    echo "Item de backlog cadastrado com sucesso.";
} else {
    echo "Erro ao cadastrar item de backlog.";
}
?>

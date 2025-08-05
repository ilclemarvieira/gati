<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Recebe os dados do formulário
$id = $_POST['id'];
$projeto = $_POST['projeto'];
$dt_criacao = $_POST['dt_criacao'];
$prioridade = $_POST['prioridade'];
$status_ideia = $_POST['status_ideia'];
$responsavel = $_POST['responsavel'];
$encaminhado_os = $_POST['encaminhado_os'] === "1" ? 1 : 0;
$descricao = $_POST['descricao'];
$existingAttachment = $_POST['existingAttachment'];
$uploadPath = ''; // Inicializa o caminho de upload

// Lista de tipos de arquivos permitidos
$allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];

// Verifica se um arquivo foi enviado e se é do tipo permitido
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileType = finfo_file($fileInfo, $_FILES['attachment']['tmp_name']);
    finfo_close($fileInfo);

    if (!in_array($fileType, $allowedTypes)) {
        echo "Tipo de arquivo não permitido.";
        exit;
    }

    // Define o diretório de uploads
    $uploadDir = 'uploads/';
    $fileName = time() . '_' . basename($_FILES['attachment']['name']);
    $uploadPath = $uploadDir . $fileName;

    // Se um novo arquivo foi enviado e o antigo existir, exclui o antigo
    if ($existingAttachment && file_exists($existingAttachment)) {
        if (!unlink($existingAttachment)) {
            // Se não for possível excluir o arquivo, obtenha o erro.
            $error = error_get_last();
            echo "Erro ao excluir o arquivo antigo: " . $error['message'];
            exit; // Encerre a execução se o arquivo antigo não puder ser excluído.
        }
    }


    // Tenta mover o arquivo enviado para o diretório de uploads
    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath)) {
        echo "Erro ao fazer upload do arquivo.";
        // Se o upload falhar, mantém o anexo existente
        $uploadPath = $existingAttachment;
    }
} else {
    // Se nenhum novo arquivo foi enviado, mantém o anexo existente
    $uploadPath = $existingAttachment;
}

// Atualiza o banco de dados com as novas informações
$stmt = $pdo->prepare("UPDATE backlogbi SET Projeto = ?, Dt_criacao = ?, Prioridade = ?, Status_ideia = ?, Responsavel = ?, Encaminhado_os = ?, Descricao = ?, Anexo = ? WHERE Id = ?");

if ($stmt->execute([$projeto, $dt_criacao, $prioridade, $status_ideia, $responsavel, $encaminhado_os, $descricao, $uploadPath, $id])) {
    echo json_encode(["success" => true, "message" => "Item de backlog atualizado com sucesso."]);
} else {
    // HTTP response code 500 para indicar erro no servidor
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erro ao atualizar item de backlog."]);
}
?>

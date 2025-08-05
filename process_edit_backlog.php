<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Definir o diretório de uploads e a URL base para os uploads
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', 'uploads/'); // URL relativa ao script PHP

// Recebe os dados do formulário
$id = $_POST['id'];
$projeto = $_POST['projeto'];
$dt_criacao = $_POST['dt_criacao'];
$prioridade = $_POST['prioridade'];
$status_ideia = $_POST['status_ideia'];
$responsavel = $_POST['responsavel'];
$encaminhado_os = $_POST['encaminhado_os'] === "1" ? 1 : 0;
$descricao = $_POST['descricao'];
$existingAttachment = $_POST['existingAttachment']; // Este deve ser a URL relativa

// Inicializa o caminho de upload com o anexo existente
$uploadPath = $existingAttachment;

// Lista de tipos de arquivos permitidos
$allowedTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png',
    'image/gif'
];

// Array para armazenar mensagens de erro
$errors = [];

// Verifica se um arquivo foi enviado e se é do tipo permitido
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileType = finfo_file($fileInfo, $_FILES['attachment']['tmp_name']);
    finfo_close($fileInfo);

    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "Tipo de arquivo não permitido.";
    } else {
        // Verifica se o diretório existe, caso contrário, cria
        if (!is_dir(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0775, true)) {
                $errors[] = "Não foi possível criar o diretório de uploads.";
            }
        }

        // Verifica as permissões do diretório
        if (!is_writable(UPLOAD_DIR)) {
            $errors[] = "Diretório de uploads não possui permissão de escrita.";
        }

        // Se não houver erros até aqui
        if (empty($errors)) {
            $fileName = time() . '_' . basename($_FILES['attachment']['name']);
            $filePath = UPLOAD_DIR . $fileName;
            $fileURL = UPLOAD_URL . $fileName;

            // Se um novo arquivo foi enviado e o antigo existir, exclui o antigo
            if ($existingAttachment && file_exists(UPLOAD_DIR . basename($existingAttachment))) {
                if (!unlink(UPLOAD_DIR . basename($existingAttachment))) {
                    $errors[] = "Erro ao excluir o arquivo antigo.";
                }
            }

            // Tenta mover o arquivo enviado para o diretório de uploads
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $filePath)) {
                $errors[] = "Erro ao fazer upload do arquivo.";
                // Mantém o anexo existente
            } else {
                // Atualiza o caminho do upload
                $uploadPath = $fileURL;
            }
        }
    }
}

if (empty($errors)) {
    // Atualiza o banco de dados com as novas informações
    $stmt = $pdo->prepare("UPDATE backlog SET Projeto = ?, Dt_criacao = ?, Prioridade = ?, Status_ideia = ?, Responsavel = ?, Encaminhado_os = ?, Descricao = ?, Anexo = ? WHERE Id = ?");

    if ($stmt->execute([$projeto, $dt_criacao, $prioridade, $status_ideia, $responsavel, $encaminhado_os, $descricao, $uploadPath, $id])) {
        echo json_encode(["success" => true, "message" => "Item de backlog atualizado com sucesso."]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Erro ao atualizar item de backlog."]);
    }
} else {
    // Se houver erros, retorna como JSON
    http_response_code(400);
    echo json_encode(["success" => false, "message" => implode(" ", $errors)]);
}
?>

<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Definição de zona horária para evitar problemas com datas
date_default_timezone_set('America/Sao_Paulo');

$tarefa = $_POST['tarefa'] ?? '';
$dt_criacao = $_POST['dt_criacao'] ?? date('Y-m-d'); // Usa a data atual como padrão se não for fornecida
$prioridade = $_POST['prioridade'] ?? '';
$status_suporte = $_POST['status_suporte'] ?? '';
$solicitado_por = $_POST['solicitado_por'] ?? '';
$para_contratada = $_POST['para_contratada'] ?? '';
$prazo_previsto = $_POST['prazo_previsto'] ?? null; // Define como null se estiver vazio
$observacao = $_POST['observacao'] ?? '';
$caminhoAnexo = null;

// Se prazo_previsto estiver vazio, define como null
$prazo_previsto = empty($prazo_previsto) ? null : $prazo_previsto;

if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] == UPLOAD_ERR_OK) {
    $diretorioUpload = __DIR__ . '/anexosuporte/';
    if (!is_dir($diretorioUpload) && !mkdir($diretorioUpload, 0755, true)) {
        die("Erro ao criar diretório de uploads.");
    }

    // Sanitizar o nome do arquivo
    $nomeArquivoOriginal = basename($_FILES['anexo']['name']);
    $nomeArquivoSanitizado = preg_replace("/[^a-zA-Z0-9.]+/", "_", $nomeArquivoOriginal);
    $nomeArquivo = uniqid() . '-' . $nomeArquivoSanitizado;
    $caminhoCompleto = $diretorioUpload . $nomeArquivo;
    
    if (move_uploaded_file($_FILES['anexo']['tmp_name'], $caminhoCompleto)) {
        // Salvar apenas o caminho relativo no banco de dados
        $caminhoAnexo = 'anexosuporte/' . $nomeArquivo;
    } else {
        die("Erro ao mover o arquivo para $caminhoCompleto.");
    }
}

$stmt = $pdo->prepare("INSERT INTO suporte (Tarefa, Dt_criacao, Prioridade, Status_suporte, Solicitado_por, Para_contratada, Prazo_previsto, Observacao, Anexos) VALUES (:tarefa, :dt_criacao, :prioridade, :status_suporte, :solicitado_por, :para_contratada, :prazo_previsto, :observacao, :anexos)");

// Vincula os parâmetros à declaração preparada
$stmt->bindParam(':tarefa', $tarefa);
$stmt->bindParam(':dt_criacao', $dt_criacao);
$stmt->bindParam(':prioridade', $prioridade);
$stmt->bindParam(':status_suporte', $status_suporte);
$stmt->bindParam(':solicitado_por', $solicitado_por);
$stmt->bindParam(':para_contratada', $para_contratada);
$stmt->bindParam(':prazo_previsto', $prazo_previsto);
$stmt->bindParam(':observacao', $observacao);
$stmt->bindParam(':anexos', $caminhoAnexo);

// Executa a declaração e verifica se foi bem-sucedida
if ($stmt->execute()) {
    header('Location: suporte.php');
    exit;
} else {
    $errorInfo = $stmt->errorInfo();
    echo "Erro ao cadastrar suporte: {$errorInfo[2]}";
}
?>

<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Definição de zona horária para evitar problemas com datas
date_default_timezone_set('America/Sao_Paulo');

// Recebe os dados do formulário
$id = $_POST['id'];
$tarefa = $_POST['tarefa'];
$dt_criacao = $_POST['dt_criacao'];
$prioridade = $_POST['prioridade'];
$status_suporte = $_POST['status_suporte'];
$solicitado_por = $_POST['solicitado_por'];
$para_contratada = $_POST['para_contratada'];
$prazo_previsto = $_POST['prazo_previsto'] ?: null; // Se vazio, define como null
$observacao = $_POST['observacao'];
$usuario_logado_id = $_SESSION['usuario_id']; // ID do usuário logado
$data_resolvido = null; // Define como nulo por padrão

// Define a data resolvido se aplicável
if (in_array($status_suporte, ['Resolvida', 'Cancelada'])) {
    $data_resolvido = date('Y-m-d'); // Data atual no formato 'YYYY-MM-DD'
}

// Processamento e atualização do anexo, se houver
$anexo = $_FILES['anexo'] ?? null;
$caminhoAnexo = processaAnexo($anexo, $id, $pdo);

// Data e hora atual para registro de alteração
$dt_alteracao = date('Y-m-d H:i:s');

// Preparação da declaração SQL usando placeholders com nome
$stmt = $pdo->prepare("UPDATE suporte SET 
    Tarefa = :tarefa, 
    Dt_criacao = :dt_criacao, 
    Prioridade = :prioridade, 
    Status_suporte = :status_suporte, 
    Solicitado_por = :solicitado_por, 
    Para_contratada = :para_contratada, 
    Prazo_previsto = :prazo_previsto, 
    Observacao = :observacao, 
    Data_resolvido = :data_resolvido, 
    Anexos = :anexos, 
    Dt_alteracao = :dt_alteracao, 
    Alterado_por = :alterado_por
WHERE Id = :id");

// Vinculação de parâmetros
$stmt->bindParam(':tarefa', $tarefa);
$stmt->bindParam(':dt_criacao', $dt_criacao);
$stmt->bindParam(':prioridade', $prioridade);
$stmt->bindParam(':status_suporte', $status_suporte);
$stmt->bindParam(':solicitado_por', $solicitado_por);
$stmt->bindParam(':para_contratada', $para_contratada);
$stmt->bindParam(':prazo_previsto', $prazo_previsto);
$stmt->bindParam(':observacao', $observacao);
$stmt->bindParam(':data_resolvido', $data_resolvido);
$stmt->bindParam(':anexos', $caminhoAnexo);
$stmt->bindParam(':dt_alteracao', $dt_alteracao);
$stmt->bindParam(':alterado_por', $usuario_logado_id);
$stmt->bindParam(':id', $id);

// Execução e redirecionamento
if ($stmt->execute()) {
    header('Location: suporte.php');
    exit;
} else {
    $errorInfo = $stmt->errorInfo();
    echo "Erro ao atualizar suporte: {$errorInfo[2]}";
}

// Função para processar e salvar o anexo
function processaAnexo($anexo, $id, $pdo) {
    if ($anexo && $anexo['error'] === UPLOAD_ERR_OK) {
        $diretorioUpload = __DIR__ . '/anexosuporte/';
        if (!is_dir($diretorioUpload) && !mkdir($diretorioUpload, 0755, true)) {
            die("Erro ao criar diretório de uploads.");
        }
        $nomeArquivo = uniqid() . '-' . basename($anexo['name']);
        $caminhoCompleto = $diretorioUpload . $nomeArquivo;
        if (move_uploaded_file($anexo['tmp_name'], $caminhoCompleto)) {
            return 'anexosuporte/' . $nomeArquivo;
        } else {
            die("Erro ao mover o arquivo.");
        }
    } else {
        // Mantém o anexo atual se nenhum novo arquivo foi enviado
        $stmt = $pdo->prepare("SELECT Anexos FROM suporte WHERE Id = ?");
        $stmt->execute([$id]);
        $suporteAtual = $stmt->fetch();
        return $suporteAtual['Anexos'] ?? '';
    }
}
?>

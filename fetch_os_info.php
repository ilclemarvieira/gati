<?php
// Iniciar sessão e conectar ao banco de dados
session_start();
require 'db.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuário não logado']);
    exit;
}

// Obter o Id da OS da URL ou de uma requisição POST
$os_id = isset($_POST['os_id']) ? $_POST['os_id'] : '';

// Preparar e executar a consulta para buscar as informações da OS e o nome do responsável
$sql = "SELECT os.*, u.Nome as NomeResponsavel, c.Nome as NomeContratada
        FROM os
        LEFT JOIN usuarios u ON os.Responsavel = u.Id
        LEFT JOIN contratadas c ON os.Id_contratada = c.Id
        WHERE os.Id = :os_id";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':os_id', $os_id, PDO::PARAM_INT);
$stmt->execute();

$osDetails = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar se a OS foi encontrada
if (!$osDetails) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'OS não encontrada']);
    exit;
}

header('Content-Type: application/json');
echo json_encode($osDetails);
?>

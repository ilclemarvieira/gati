<?php
session_start();

// Verifica se o usuário está logado e se a requisição é GET
if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] != 'GET') {
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

require 'db.php';

if (isset($_GET['supportId'])) {
    $supportId = $_GET['supportId'];

    // Validação e/ou sanitização de $supportId aqui, se necessário

    // Executar consulta para buscar dados do suporte
    $stmt = $pdo->prepare("SELECT s.*, u.Nome as NomeSolicitante, c.Nome as NomeContratada FROM suporte s LEFT JOIN usuarios u ON s.Solicitado_por = u.Id LEFT JOIN contratadas c ON s.Para_contratada = c.Id WHERE s.Id = :supportId");
    $stmt->execute(['supportId' => $supportId]);
    $suporte = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($suporte) {
        // Retornar dados em formato JSON
        echo json_encode($suporte);
    } else {
        echo json_encode(['error' => 'Suporte não encontrado']);
    }
} else {
    echo json_encode(['error' => 'ID do suporte não fornecido']);
}
?>

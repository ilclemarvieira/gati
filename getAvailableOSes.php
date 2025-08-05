<?php
// getAvailableOSes.php

// Incluir o arquivo de conexão com o banco de dados
include 'db.php';

// Iniciar a sessão para verificar a autenticação do usuário
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

try {
    // Consulta SQL para buscar todas as OS que NÃO estão com Status_contratada = 'Em Produção' ou 'Paralisado'
    $sql = "SELECT Id, N_os, Nome_os 
            FROM os 
            WHERE Status_contratada NOT IN ('Em Produção', 'Paralisado')
            ORDER BY N_os ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $osList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $osList]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar OS: ' . $e->getMessage()]);
}
?>

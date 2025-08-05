<?php
session_start();
include 'db.php';

// Habilitar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Permitir credenciais nas requisições
header('Access-Control-Allow-Credentials: true');

// Caminho do arquivo de log
$log_file = __DIR__ . '/debug_log.txt';

// Logs para depuração
file_put_contents($log_file, "Requisição recebida em " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Verificar se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    file_put_contents($log_file, "Usuário não autenticado\n", FILE_APPEND);
    http_response_code(403);
    echo 'Acesso negado';
    exit;
}

if (isset($_POST['osId']) && isset($_POST['highlighted'])) {
    $osId = intval($_POST['osId']);
    $highlighted = intval($_POST['highlighted']);

    file_put_contents($log_file, "Dados recebidos - osId: $osId, highlighted: $highlighted\n", FILE_APPEND);
    file_put_contents($log_file, "Conteúdo de \$_POST: " . print_r($_POST, true) . "\n", FILE_APPEND);

    try {
        $stmt = $pdo->prepare("UPDATE os SET Highlighted = :highlighted WHERE Id = :osId");
        $stmt->execute([':highlighted' => $highlighted, ':osId' => $osId]);
        echo 'Success';
    } catch (PDOException $e) {
        file_put_contents($log_file, "Erro ao atualizar: " . $e->getMessage() . "\n", FILE_APPEND);
        echo 'Erro ao atualizar: ' . $e->getMessage();
    }
} else {
    file_put_contents($log_file, "Parâmetros inválidos. \$_POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
    echo 'Parâmetros inválidos';
}

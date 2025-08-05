<?php
session_start();
include 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    exit('Usuário não está logado.');
}

$titulo = $_POST['title'] ?? null;
$horario_inicio = $_POST['start_time'] ?? null;
$descricao = $_POST['description'] ?? null; // Garanta que este campo corresponde ao nome do campo no formulário
$data_inicio = $_POST['start_date'] ?? null;
$data_fim = $_POST['end_date'] ?? null;
$categoria = $_POST['category'] ?? null;
$link = $_POST['link'] ?? null;
$usuario_id = $_SESSION['usuario_id'];

// Log para debugar o conteúdo recebido
error_log('Dados recebidos: ' . print_r($_POST, true));

if (!$titulo || !$horario_inicio || !$data_inicio || !$usuario_id) {
    exit("Preencha todos os campos obrigatórios.");
}

try {
    // Verificar se o evento já existe no banco de dados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE titulo = ? AND horario_inicio = ? AND data_inicio = ? AND usuario_id = ?");
    $stmt->execute([$titulo, $horario_inicio, $data_inicio, $usuario_id]);
    $eventCount = $stmt->fetchColumn();

    if ($eventCount > 0) {
        exit("Este evento já foi adicionado.");
    }

    $stmt = $pdo->prepare("INSERT INTO eventos (titulo, horario_inicio, descricao, data_inicio, data_fim, categoria, link, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$titulo, $horario_inicio, $descricao, $data_inicio, $data_fim, $categoria, $link, $usuario_id]);

    echo "Evento adicionado com sucesso!";
} catch (PDOException $e) {
    echo "Erro ao salvar o evento: " . $e->getMessage();
}
?>

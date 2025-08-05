<?php
// Certifique-se de que está iniciando a sessão e conectando ao banco de dados aqui
session_start();
require 'db.php';

// Certifique-se de que o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuário não logado']);
    exit;
}

// Pega o evento_id da URL
$evento_id = isset($_GET['id']) ? $_GET['id'] : '';

// Prepara e executa a consulta para buscar as informações do evento e o nome do usuário que criou
$sql = "SELECT eventos.id, eventos.titulo, eventos.horario_inicio, eventos.descricao,
               eventos.data_inicio, eventos.data_fim, eventos.categoria, eventos.link,
               eventos.usuario_id, usuarios.nome AS usuarioNome
        FROM eventos
        JOIN usuarios ON eventos.usuario_id = usuarios.id
        WHERE eventos.id = :evento_id";

$stmt = $pdo->prepare($sql);
$stmt->execute(['evento_id' => $evento_id]);

$evento = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se o evento foi encontrado
if (!$evento) {
    echo json_encode(['error' => 'Evento não encontrado']);
    exit;
}

header('Content-Type: application/json');
echo json_encode($evento);
?>

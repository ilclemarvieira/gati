<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => true, 'id' => 1, 'message' => 'Teste']);
exit;

}
include 'db.php';

// Função para sanitizar entradas
function sanitize($data) {
    global $conn;
    return htmlspecialchars(strip_tags($conn->real_escape_string($data)));
}

// Função para adicionar uma nova tarefa
function adicionarTarefa($titulo, $cor) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO cardtarefas (titulo, cor, ordem) VALUES (?, ?, 0)");
    $stmt->bind_param("ss", $titulo, $cor);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        return $conn->insert_id;
    } else {
        return false;
    }
}

// Verificar se a requisição para adicionar tarefa foi feita
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adicionarTarefa'])) {
    $titulo = sanitize($_POST['titulo']);
    $cor = sanitize($_POST['cor']);
    $id = adicionarTarefa($titulo, $cor);
    
    if ($id) {
        $response = ['success' => true, 'id' => $id];
    } else {
        $response = ['success' => false, 'message' => 'Não foi possível adicionar a tarefa.'];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// ... Incluir mais código aqui para lidar com outras requisições, como adicionar subtarefas ...

?>

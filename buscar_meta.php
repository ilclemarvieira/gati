<?php
// Inclui o arquivo de conexão com o banco de dados e inicia a sessão
include 'db.php';
session_start();

header('Content-Type: application/json');

$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;
$id = $_GET['id'] ?? 0;

if (!is_numeric($id) || $id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID da meta não fornecido ou inválido.']);
    exit;
}

// Determina os perfis que o usuário logado pode ver
$perfisPermitidos = [];
switch ($perfilAcesso) {
    case 1:
        $perfisPermitidos = [1, 2, 4];
        break;
    case 2:
        $perfisPermitidos = [1, 2, 4, 5];
        break;
    case 4:
        $perfisPermitidos = [1, 2, 4];
        break;
    case 5:
        $perfisPermitidos = [2, 5];
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Perfil de acesso não autorizado.']);
        exit;
}

// Prepara e executa a consulta levando em conta os perfis permitidos
try {
    $query = "SELECT m.* FROM metas m 
              JOIN usuarios u ON m.usuario_id = u.Id 
              WHERE m.id = ? AND u.PerfilAcesso IN (" . implode(',', $perfisPermitidos) . ")";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id]);

    if ($meta = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => true, 'meta' => $meta]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Meta não encontrada ou sem permissão para visualizar.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar os dados da meta.', 'error' => $e->getMessage()]);
}
?>

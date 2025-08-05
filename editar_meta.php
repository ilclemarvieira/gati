<?php
// Inclui o arquivo de conexão com o banco de dados
include 'db.php';

header('Content-Type: application/json');

// Verifica se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Captura os dados do formulário
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT) : null;
    $nome = isset($_POST['nome']) ? filter_var($_POST['nome'], FILTER_SANITIZE_STRING) : '';
    $ano = isset($_POST['ano']) ? filter_var($_POST['ano'], FILTER_SANITIZE_NUMBER_INT) : date('Y');
    $mes = isset($_POST['mes']) ? filter_var($_POST['mes'], FILTER_SANITIZE_NUMBER_INT) : date('m');
    $status = isset($_POST['status']) ? filter_var($_POST['status'], FILTER_SANITIZE_STRING) : 'Pendente';
    $descricao = isset($_POST['descricao']) ? filter_var($_POST['descricao'], FILTER_SANITIZE_STRING) : '';

    // Validação adicional dos dados (opcional)
    if (empty($nome) || empty($id) || !in_array($status, ['Pendente', 'Em andamento', 'Concluída'])) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
        exit;
    }

    // Prepara o prazo como o último dia do mês especificado
    $prazo = date('Y-m-t', strtotime("$ano-$mes-01"));

    try {
        // Altere sua consulta SQL para incluir a descrição
        $query = "UPDATE metas SET nome = ?, ano = ?, prazo = ?, status = ?, descricao = ? WHERE id = ?";

        // Prepara a query SQL
        $stmt = $pdo->prepare($query);
        // Executa a query com os dados fornecidos
        $stmt->execute([$nome, $ano, $prazo, $status, $descricao, $id]);


        // Verifica se a atualização foi bem-sucedida
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Meta atualizada com sucesso.']);
        } else {
            // Nenhuma linha foi atualizada. Pode ser porque os dados são iguais ou a meta não existe.
            echo json_encode(['success' => false, 'message' => 'Nenhuma alteração detectada ou a meta não existe.']);
        }
    } catch (PDOException $e) {
        // Em caso de erro na query, retorna uma mensagem de erro
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar a meta no banco de dados.', 'error' => $e->getMessage()]);
    }
} else {
    // Método de requisição não suportado
    echo json_encode(['success' => false, 'message' => 'Método de requisição não suportado.']);
}

?>

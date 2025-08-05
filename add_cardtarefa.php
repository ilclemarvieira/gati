<?php
session_start();

// Inclui o arquivo de conexão com o banco de dados
require_once 'db.php'; // Altere para o caminho correto do seu arquivo de configuração de banco de dados

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo "Usuário não logado.";
    exit;
}

// Função para adicionar uma nova tarefa ao banco de dados
function addCardTarefa($titulo, $cor) {
    global $conn; // Use sua variável de conexão com o banco de dados
    $stmt = $conn->prepare("INSERT INTO cardtarefas (titulo, cor) VALUES (?, ?)");
    $stmt->bind_param("ss", $titulo, $cor);
    $stmt->execute();
    
    if ($stmt->error) {
        // Em produção, considere logar este erro em vez de exibi-lo
        return "Erro ao inserir tarefa: " . $stmt->error;
    }
    
    return $stmt->insert_id; // Retorna o ID da tarefa criada
}

// Verifica se o POST contém os dados esperados
if (isset($_POST['titulo']) && isset($_POST['cor'])) {
    $titulo = $_POST['titulo'];
    $cor = $_POST['cor'];

    // Chama a função para adicionar a tarefa e retorna o resultado
    $idTarefa = addCardTarefa($titulo, $cor);
    
    // Verifica se um ID foi retornado, indicando sucesso
    if(is_numeric($idTarefa)) {
        echo "Tarefa adicionada com sucesso. ID: " . $idTarefa;
    } else {
        // Se não for numérico, é uma mensagem de erro
        echo $idTarefa;
    }
} else {
    echo "Dados inválidos. POST: ";
    print_r($_POST); // Apenas para depuração, remover em produção
}
?>

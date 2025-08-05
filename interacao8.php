
<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

function formatarCPF($cpf) {
    return preg_replace("/^(\d{3})(\d{3})(\d{3})(\d{2})$/", "$1.$2.$3-$4", $cpf);
}

// Verifique se um POST foi feito
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json'); // Define o cabeçalho como JSON

    if (isset($_POST['taskName'], $_POST['column'])) {
        $taskName = filter_var($_POST['taskName'], FILTER_SANITIZE_STRING);
        $column = filter_var($_POST['column'], FILTER_SANITIZE_STRING);
        $userId = $_SESSION['usuario_id']; // Pegue o ID do usuário da sessão

        $stmt = $pdo->prepare("INSERT INTO tasks (title, status, user_id, created_at) VALUES (?, ?, ?, NOW())");
        $result = $stmt->execute([$taskName, $column, $userId]);
        
        if ($result) {
            echo json_encode(['task_id' => $pdo->lastInsertId(), 'success' => true]);
        } else {
            echo json_encode(['task_id' => null, 'success' => false, 'error' => 'Failed to insert task']);
        }
        exit; // Encerra a execução após o POST
    }
    
    if (isset($_POST['taskId'], $_POST['status'])) {
        $taskId = filter_var($_POST['taskId'], FILTER_VALIDATE_INT);
        $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
        
        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        $result = $stmt->execute([$status, $taskId]);
        
        echo json_encode(['success' => $result]);
        exit; // Encerra a execução após o POST
    }
}



// Buscar tarefas criadas para exibição
$tarefas = $pdo->query("
    SELECT tasks.*, usuarios.Nome AS usuario_nome, DATE_FORMAT(tasks.created_at, '%d/%m/%Y %H:%i') AS formatted_date 
    FROM tasks 
    LEFT JOIN usuarios ON tasks.user_id = usuarios.Id 
    ORDER BY tasks.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Buscar cards e subtarefas do banco de dados
$cards = $pdo->query("SELECT * FROM cards")->fetchAll(PDO::FETCH_ASSOC);
$subtasks = $pdo->query("SELECT * FROM subtasks")->fetchAll(PDO::FETCH_ASSOC);

// Supondo que você tem uma coluna user_id na tabela subtasks para vincular ao usuário
$subtasks = $pdo->query("
    SELECT subtasks.*, usuarios.Nome AS usuario_nome
    FROM subtasks 
    LEFT JOIN usuarios ON subtasks.user_id = usuarios.Id
    ORDER BY subtasks.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);


$itensPorPagina = 9; // Definir quantos cards por página

// Pegar a página atual da URL, padrão é 1 se não definido
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina; // Calcula o offset

// Verifica se o parâmetro 'tipo' está definido na URL
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'pendentes';
$isArchivedCondition = $tipo === 'arquivadas' ? 1 : 0;



// Consulta SQL ajustada para filtrar com base no campo 'is_archived'
$cards = $pdo->query("
    SELECT cards.*,
           (SELECT COUNT(*) FROM subtasks WHERE subtasks.card_id = cards.id) AS subtaskCount,
           COALESCE(MAX(subtasks.updated_at), cards.created_at) AS last_interaction
    FROM cards
    LEFT JOIN subtasks ON cards.id = subtasks.card_id
    WHERE cards.is_archived = $isArchivedCondition
    GROUP BY cards.id
    ORDER BY last_interaction DESC
    LIMIT $itensPorPagina OFFSET $offset
")->fetchAll(PDO::FETCH_ASSOC);

// Consulta SQL para contar o número de cards com base na condição 'is_archived'
$totalCards = $pdo->query("SELECT COUNT(*) FROM cards WHERE is_archived = $isArchivedCondition")->fetchColumn();
$totalPaginas = ceil($totalCards / $itensPorPagina);





// Buscar subtarefas ordenadas pela data de criação
$subtasks = $pdo->query("
    SELECT subtasks.*, usuarios.Nome AS usuario_nome
FROM (
    SELECT *,
           ROW_NUMBER() OVER (PARTITION BY card_id ORDER BY created_at ASC) as rn
    FROM subtasks
    WHERE status = 'todo'
) AS subtasks
LEFT JOIN usuarios ON subtasks.user_id = usuarios.Id
WHERE subtasks.rn <= 2
ORDER BY subtasks.card_id, subtasks.created_at ASC;

")->fetchAll(PDO::FETCH_ASSOC);


// Buscar todas as subtarefas para o modal de detalhes
$allSubtasksForModal = $pdo->query("
    SELECT subtasks.*, usuarios.Nome AS usuario_nome
    FROM subtasks
    LEFT JOIN usuarios ON subtasks.user_id = usuarios.Id
    ORDER BY subtasks.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>



<!DOCTYPE html>
<html dir="ltr" lang="pt">

  <head>
    <?php include 'head.php'?>
<script src="assets/libs/jquery/dist/jquery.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>



    <style>
    .modal-content {
}

    .os-details p {
        /* Estilos para os parágrafos dos detalhes da OS */
    }

    .anexo-container {
        margin-top: 20px;
       
    }

    .anexo-container img, .anexo-container iframe {
        max-width: 100%; /* Garantir que o anexo não ultrapasse a largura do modal */
        height: auto;
    }
  .highlighted {
    background-color: #ffff0026;
}

/* Estado normal das linhas destacadas */
.content-table tbody tr.highlighted {
    background-color: #ffff0026;
}

/* Estado de hover para todas as linhas */
.content-table tbody tr:hover {
    background-color: #f5f5f5; /* ou qualquer outra cor que deseja para o hover */
}

/* Estado de hover especificamente para linhas destacadas */
.content-table tbody tr.highlighted:hover {
    background-color: #ffff0026 !important; /* Mantém a cor de fundo amarela mesmo no hover */
}

.filter-container {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 15px; /* Aumentar o espaçamento */
    align-items: center;
    background-color: #272b34; /* Fundo escuro para o container */
    padding: 20px; /* Aumentar o padding */
    border-radius: 5px;
    margin-bottom: 20px; /* Espaçamento abaixo do filtro */

}

.filter-container select, .filter-container input[type="text"] {
    flex-grow: 1; /* Permitir que os campos cresçam */
    padding: 12px; /* Aumentar o padding */
    border-radius: 4px;
    border: 1px solid #666;
    background-color: #323743; /* Fundo escuro para os campos */
    color: #a1aab2; /* Cor do texto */
    font-size: 14px;
    margin: 0px 5px;
}

.filter-container select:focus, .filter-container input[type="text"]:focus {
    border-color: #007bff; /* Cor da borda ao focar */
}

.filter-container button {
    padding: 12px 15px; /* Ajustar o padding */
    border-radius: 4px;
    border: 1px solid #4a5f7c; /* Borda sutil */
    background-color: #4a5f7c; /* Cor de fundo do botão */
    color: #e6e6e6; /* Cor do texto */
    cursor: pointer;
    text-transform: uppercase; /* Estilo do texto */
    font-weight: bold;
    transition: background-color 0.3s, border-color 0.3s, color 0.3s; /* Efeito de transição suave */
    margin: 0px 5px;
}

.filter-container button:hover {
    background-color: #627d9a; /* Cor de fundo do botão ao passar o mouse */
    border-color: #627d9a; /* Cor da borda ao passar o mouse */
    color: #ffffff; /* Cor do texto ao passar o mouse */
}

/* Estilos responsivos para telas menores */
@media (max-width: 768px) {
    .filter-container {
        flex-direction: column; /* Campos em coluna para telas menores */
    }

    .filter-container select, 
    .filter-container input[type="text"], 
    .filter-container button {
        flex-basis: 100%; /* Ocupar toda a largura */
        margin-bottom: 10px; /* Adiciona um espaço abaixo de cada campo */
    }

    /* Último item não deve ter margin-bottom para não adicionar espaço extra após o último elemento */
    .filter-container button {
        margin-bottom: 0; 
    }
}

.filter-container input[type="text"] {
    /* Outras propriedades existentes... */
    font-size: 15px; /* Aumenta o tamanho da fonte */
    color: #a1aab2; /* Cor da fonte mais clara */
    padding: 12px; /* Aumenta o padding para dar mais espaço dentro do campo */
    width: 270px; /* Ajustado para ocupar 100% da largura do container */
    margin: 10px 0px;
}

/* Para garantir que o campo não fique muito grande em telas menores */
@media (max-width: 768px) {
    .filter-container input[type="text"] {
        font-size: 14px; /* Tamanho da fonte um pouco menor em telas pequenas */
    }
}




/* Estilos para os ícones e espaçamento */
.round-lg {
  width: 40px; /* Ajusta o tamanho da circunferência */
  height: 40px; /* Ajusta o tamanho da circunferência */
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px; /* Espaçamento entre o ícone e o texto */
}
.fa {
  font-size: 14px; /* Reduz o tamanho do ícone */
}
.card {
    cursor: pointer; /* Adiciona o cursor de mão */
    position: relative;
    padding-top: 30px; /* Espaço para o cabeçalho do card */
    background-color: #333; /* Uma cor de fundo sólida */
    border: none; /* Remova as bordas se houver */
    color: #fff; /* Texto branco para contraste */
    transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out; /* Suavizar transições */

}

.card:hover {
    transform: translateY(-5px); /* Eleva o card */
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5); /* Sombra mais pronunciada */
}

.card-header {
    top: 10px;
    width: 100%;
    height: 60px; /* Ajuste a altura se necessário */
    padding: 5px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-actions {
    display: flex;
    gap: 10px;
}

.card-body {
    display: flex;
    align-items: center; /* Centraliza verticalmente o conteúdo */
}

.card-title {
    margin-bottom: 0;
    font-size: 18px;
    font-weight: bold;
    /* white-space: nowrap; Removido para permitir a quebra de linha */
    /* overflow: hidden; Removido para mostrar todo o conteúdo */
    /* text-overflow: ellipsis; Removido para mostrar todo o conteúdo */
    word-wrap: break-word; /* Adicionado para quebrar a linha em qualquer palavra */
    white-space: normal; /* Adicionado para permitir a quebra de linha normal */
}

.card-text {
    margin-top: 4px; /* Espaçamento entre o título e o texto */
}

.text-muted {
    display: block; /* Faz com que o texto ocupe sua própria linha */
}


.hidden {
    display: none !important;
}



.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    padding-top: 10px;
}

.modal-content {
    background-color: #fff;
    margin: 2% auto;
    padding: 20px;
    border-radius: 8px;
    width: 50%; /* Ajuste a largura conforme necessário */
    box-shadow: 0 4px 10px rgba(0,0,10,10.15);
}


/* Modal Header Styling */
.modal-content h2 {
    color: #333;
    font-size: 24px;
    font-weight: 500;
    border-bottom: 1px solid #e3e3e354;
    padding-bottom: 10px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.close {
    color: #aaaaaa;
    float: right;
    font-size: 30px;
    padding-bottom: 10px;
    margin-bottom: 10px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #000;
    text-decoration: none;
    cursor: pointer;
}

.form-row {
    display: flex;
    flex-direction: column;
    margin-bottom: 15px;
}

.form-row label {
    font-weight: 600;
    margin-bottom: 5px;
}

.form-row input[type=text],
.form-row input[type=date],
.form-row select,
.form-row textarea {
    width: 100%;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
    transition: border-color 0.3s;
}

.form-row input[type=text]:focus,
.form-row input[type=date]:focus,
.form-row select:focus,
.form-row textarea:focus {
    border-color: #007bff;
    outline: none;
}

.submit-btn {
    width: auto;
    padding: 10px 20px;
    font-weight: 500;
    background-color: rgba(252,75,108,.5)!important;
    color: white;
    border: rgba(252,75,108,.5)!important;
    border-radius: 40px;
    cursor: pointer;
    transition: background-color 0.2s;
    align-self: flex-end;
}

.submit-btn:hover {
    background-color: #0056b3;
}

/* Responsividade para telas menores */
@media screen and (max-width: 768px) {
    .modal-content {
        width: 90%;
        padding: 20px;
    }

    .form-row input[type=text],
    .form-row input[type=date],
    .form-row select,
    .form-row textarea {
        padding: 10px;
    }
}


/* Details Paragraph Styling */
.os-details p {
    padding: 2px 0;
    border-bottom: 1px solid #eeeeee0d;
    display: flex;
    color: #fff;
    justify-content: space-between; /* Align the label and value on opposite ends */
}

.os-details b {
    font-weight: 500;
    color: #333;
    margin-right: 15px; /* Spacing between label and value */
}

/* Anexo Container Styling */
.anexo-container {
    margin-top: 20px;
}

/* Flex Container for Two Column Layout */
.flex-container {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap; /* Allow wrapping for smaller screens */
}

/* Flex Item Styling */
.flex-item {
    flex-basis: 48%; /* Two items per row */
    margin-bottom: 10px; /* Spacing between items */
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .flex-item {
        flex-basis: 100%; /* Stack items on top of each other on small screens */
    }
}

/* Style the form rows */
.form-row {
    display: flex;
    flex-direction: column;
    margin-bottom: 15px;
}

.form-row label {
    font-weight: 600;
    margin-bottom: 5px;
}

.form-row input[type=text],
.form-row input[type=date],
.form-row select,
.form-row textarea {
    width: 100%;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
    transition: border-color 0.3s;
}

.form-row input[type=text]:focus,
.form-row input[type=date]:focus,
.form-row select:focus,
.form-row textarea:focus {
    border-color: #007bff;
    outline: none;
}


.readonly {
        background-color: #b3c0c7 !important; /* Cor de fundo cinza para indicar que está desativado */
        color: #272b34; !important; /* Cor do texto para indicar que está desativado */
        font-weight: bold;
        cursor: not-allowed !important; /* Cursor de não permitido para indicar que o campo é somente leitura */
    }



/* Responsive layout - when the screen is less than 600px wide, make the modal-content full width */
@media screen and (max-width: 600px) {
    .modal-content {
        width: 95%;
    }
}

/* Estilo para linhas finalizadas */
.finalizada {
    text-decoration: line-through;
    opacity: 0.5; /* Opcional: para tornar o texto mais claro indicando que está concluído */
}

.linha-taxada {
    text-decoration: line-through;
    opacity: 0.7; /* Opcional para tornar a linha mais clara */
}

.taskboard {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 30px;
    padding: 0px;
}

.column {
    width: calc(33.333% - 20px);
    background: linear-gradient(327deg, #1d2126, #1d2126);
    color: #adbac7;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.3);
    position: relative; /* Para o botão de adicionar */
}

.column:hover {
    transform: translateY(-3px);
}

.task-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.task-item {
    background-color: #2a2d3d; /* Cor de fundo mais escura para contraste */
    color: #c7c7c7; /* Cor do texto */
    margin-top: 10px;
    padding: 20px;
    border-radius: 6px;
    position: relative; /* Posicionamento relativo para os elementos absolutos dentro */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Sombra suave para profundidade */
}

.task-item:hover {
    background-color: #323743;
    border-left-color: #4f8bc9;
}

/* CSS base para a barra lateral */
.task-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 4px;
    border-radius: 6px 0 0 6px; /* Arredondar os cantos da esquerda */
    transition: background-color 0.3s; /* Transição suave para a cor de fundo */
}

/* Cor da barra lateral para subtarefas com status "todo" */
.task-item[data-status="todo"]::before {
    background-color: #f94869; /* Azul para "todo" */
}

/* Cor da barra lateral para subtarefas com status "done" */
.task-item[data-status="done"]::before {
    background-color: #28a745; /* Verde para "done" */
}


.action-button {
    background: none;
    border: none;
    cursor: pointer;
    color: #adbac7;
    font-size: 1rem;
    transition: color 0.2s;
    padding: 5px; /* Espaçamento para facilitar o clique */
}

.action-button:hover {
    color: #ffffff;
}

/* Estilo personalizado para os checkboxes */
.task-checkbox {
   -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    position: relative;
    cursor: pointer;
    width: 20px;
    height: 20px;
    background-color: #f5f5f5;
    border: 2px solid #adb5bd;
    border-radius: 4px;
    vertical-align: middle;
    transition: background-color 0.2s, border-color 0.2s;
}

/* Quando o checkbox estiver marcado */
.task-checkbox:checked {
    background-color: #198754; /* Um verde diferente para distinção */
    border-color: #198754;
}

/* Estilo para o ícone de verificação quando o checkbox está marcado */
.task-checkbox:checked::after {
    content: '\2713'; /* Código Unicode para o ícone de verificação */
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white; /* Ícone de verificação branco */
    font-size: 14px; /* Tamanho do ícone de verificação */
}

/* Hover efeito para o checkbox */
.task-checkbox:hover:not(:checked) {
    border-color: #0d6efd; /* Muda a cor da borda ao passar o mouse */
}

/* Foco no checkbox */
.task-checkbox:focus {
    outline: none; /* Remove o contorno padrão */
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); /* Adiciona um sombreado azul para indicar foco */
}

/* Estilo para desabilitar o checkbox */
.task-checkbox:disabled {
    background-color: #e9ecef; /* Fundo cinza claro para o checkbox desabilitado */
    border-color: #ced4da; /* Borda cinza */
    cursor: not-allowed; /* Cursor de não permitido */
}

/* Estilo para o label associado ao checkbox para melhor acessibilidade */
.task-checkbox + label {
    margin-left: 8px; /* Espaço entre o checkbox e o label */
    cursor: pointer; /* Muda o cursor para indicar que o label é clicável */
}

/* Garanta que o label acione o estado de foco do checkbox quando clicado */
.task-checkbox + label:hover {
    color: #0d6efd; /* Muda a cor do texto ao passar o mouse */
}


/* Estilo personalizado para os checkboxes das subtarefas */
.subtask-checkbox {
    -webkit-appearance: none;
    -moz-appearance: none;
    flex-shrink: 0;
    appearance: none;
    position: relative;
    cursor: pointer;
    width: 20px;
    height: 20px;
    margin-right: 10px;
    align-self: center;
    margin-top: -2px;
    background-color: #f5f5f5;
    border: 2px solid #adb5bd;
    border-radius: 4px;
    vertical-align: middle;
    transition: background-color 0.2s, border-color 0.2s;
}

.subtask-checkbox,
.subtask-checkbox + * {
  flex-shrink: 0; /* Prevent checkbox and its immediate sibling from shrinking */
}



/* Estilo para a lista de subtarefas */
.subtask-list-item {
  display: flex;
  flex-direction: column;
  margin-bottom: 10px;
  list-style: none;
  width: 100%;
}

.subtask-content {
    display: flex;
    align-items: center; /* Centraliza verticalmente o conteúdo */
    gap: 10px; /* Espaço entre o checkbox e o título */
}


/* Flex container for title and status */
.subtask-content > div {
  flex-grow: 1;
  min-width: 0; /* Prevent flex item from overflowing */
}

/* Título da subtarefa */
.subtask-title {
    word-wrap: break-word; /* Permite que as palavras sejam quebradas para ir para a próxima linha */
    white-space: normal; /* Permite que o espaço branco seja quebrado */
    display: inline; /* Mantém o span na mesma linha */
    max-width: calc(72% - 30px); /* Subtrai o espaço ocupado pelo checkbox e gap */
}


.task-status {
  font-size: 0.9em;
  color: #555;
}

.task-status.completed,
.task-status.pending {
  display: block; /* Certifica-se de que o status esteja em uma nova linha */
  margin-top: 2px; /* Espaçamento acima do status */
}

.task-status.pending {
  color: #dc3545;
}

/* Texto de status da tarefa */
.task-status-text {
  display: block; /* Put the status text on a new line */
  margin-top: 5px; /* Space between the title and status text */
}

/* Detalhes da subtarefa */
.subtask-details {
    font-size: 0.8rem;
    color: #777;
    margin-top: 5px;
}


.subtask-checkbox:checked {
    background-color: #198754; /* Um verde diferente para distinção */
    border-color: #198754;
}

.subtask-checkbox:checked::after {
    content: '\2713';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 14px;
}

.subtask-checkbox:hover:not(:checked) {
    border-color: #198754; /* Cor verde ao passar o mouse */
}

.subtask-checkbox:focus {
    outline: none;
    box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25); /* Sombreado verde para foco */
}

.subtask-checkbox:disabled {
    background-color: #e9ecef;
    border-color: #ced4da;
    cursor: not-allowed;
}

.subtask-checkbox + label {
    margin-left: 8px;
    cursor: pointer;
}

.subtask-checkbox + label:hover {
    color: #198754; /* Cor verde ao passar o mouse sobre o label */
}


.task-title {
    margin: 0; /* Remove a margem padrão */
    font-size: 15px; /* Tamanho do texto */
    display: block; /* Ocupa toda a largura para forçar os detalhes para a próxima linha */
}

.task-details {
    font-size: 12px; /* Tamanho menor para os detalhes */
    color: #9a9a9a; /* Cor mais suave para os detalhes */
    margin-top: 5px; /* Espaço acima dos detalhes */
}

.task-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 10px;
}

.task-done .task-title {
    color: #6c757d;
    text-decoration: line-through;
}

.task-done .task-item {
    background-color: #353545;
}

/* Estilo para o botão de adicionar nova subtarefa */
.add-subtask-btn {
    position: absolute;
    bottom: -20px; /* Posiciona abaixo do card */
    right: 10px; /* Distância da direita */
    background-color: #198754; /* Cor de fundo azul */
    color: #ffffff; /* Texto branco */
    border-radius: 50%; /* Circular */
    width: 30px; /* Largura fixa */
    height: 30px; /* Altura fixa */
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); /* Sombra para o botão */
    transition: background-color 0.2s; /* Transição suave para o hover */
}

.add-subtask-btn:hover {
    background-color: #5591f0; /* Cor mais clara no hover */
}


/* Adicionando margem ao botão de adicionar nova subtarefa quando houver subtarefas */
.column:not(:empty) .add-subtask-btn {
    margin-top: 15px; /* Espaço extra quando houver subtarefas */
}

.task-completed {
    color: #28a745; /* Verde para tarefa concluída */
}

.task-pending {
    color: #f94869; /* Vermelho para tarefa pendente */
}


/* Estilo personalizado para checkbox */
.custom-checkbox .checkmark {
    position: relative;
    height: 25px; /* Altura do checkbox */
    width: 25px; /* Largura do checkbox */
    background-color: #eee; /* Cor de fundo do checkbox */
    border-radius: 5px; /* Bordas arredondadas */
    border: 1px solid #d1d3d4; /* Borda do checkbox */
}

/* Quando o checkbox está marcado */
.custom-checkbox input:checked ~ .checkmark {
    background-color: #2196F3; /* Cor de fundo quando marcado */
}

/* Estilo do indicador (a marca de verificação) */
.checkmark:after {
    content: "";
    position: absolute;
    display: none;
}

/* Estilo do indicador (a marca de verificação) quando está marcado */
.custom-checkbox input:checked ~ .checkmark:after {
    display: block;
}

/* Estilo e posição da marca de verificação */
.custom-checkbox .checkmark:after {
    left: 9px;
    top: 5px;
    width: 7px;
    height: 14px;
    border: solid white;
    border-width: 0 3px 3px 0;
    transform: rotate(45deg);
}

.nav-item .btn-success {
    background-color: #198754 !important; /* Cor de fundo verde */
    border-color: #198754 !important; /* Cor da borda */
    color: #fff !important; /* Cor do texto */
}

.nav-item .btn-success:hover {
    background-color: #146c43 !important; /* Cor de fundo um pouco mais escura ao passar o mouse */
    border-color: #145c38 !important; /* Cor da borda um pouco mais escura ao passar o mouse */
}



</style> 
   
  </head>

  <body>    
    <div class="preloader">
      <svg
        class="tea lds-ripple"
        width="37"
        height="48"
        viewbox="0 0 37 48"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M27.0819 17H3.02508C1.91076 17 1.01376 17.9059 1.0485 19.0197C1.15761 22.5177 1.49703 29.7374 2.5 34C4.07125 40.6778 7.18553 44.8868 8.44856 46.3845C8.79051 46.79 9.29799 47 9.82843 47H20.0218C20.639 47 21.2193 46.7159 21.5659 46.2052C22.6765 44.5687 25.2312 40.4282 27.5 34C28.9757 29.8188 29.084 22.4043 29.0441 18.9156C29.0319 17.8436 28.1539 17 27.0819 17Z"
          stroke="#1e88e5"
          stroke-width="2"
        ></path>
        <path
          d="M29 23.5C29 23.5 34.5 20.5 35.5 25.4999C36.0986 28.4926 34.2033 31.5383 32 32.8713C29.4555 34.4108 28 34 28 34"
          stroke="#1e88e5"
          stroke-width="2"
        ></path>
        <path
          id="teabag"
          fill="#1e88e5"
          fill-rule="evenodd"
          clip-rule="evenodd"
          d="M16 25V17H14V25H12C10.3431 25 9 26.3431 9 28V34C9 35.6569 10.3431 37 12 37H18C19.6569 37 21 35.6569 21 34V28C21 26.3431 19.6569 25 18 25H16ZM11 28C11 27.4477 11.4477 27 12 27H18C18.5523 27 19 27.4477 19 28V34C19 34.5523 18.5523 35 18 35H12C11.4477 35 11 34.5523 11 34V28Z"
        ></path>
        <path
          id="steamL"
          d="M17 1C17 1 17 4.5 14 6.5C11 8.5 11 12 11 12"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke="#1e88e5"
        ></path>
        <path
          id="steamR"
          d="M21 6C21 6 21 8.22727 19 9.5C17 10.7727 17 13 17 13"
          stroke="#1e88e5"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        ></path>
      </svg>
    </div>


    <!-- -------------------------------------------------------------- -->
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- -------------------------------------------------------------- -->
    <div id="main-wrapper">
      <!-- -------------------------------------------------------------- -->
      <!-- Topbar header - style you can find in pages.scss -->
      <!-- -------------------------------------------------------------- -->
      <header class="topbar">
        <?php include 'header.php'?>   
      </header> 

       <?php include 'sidebar.php'?> 

      <div class="page-wrapper">
       
        <div class="row page-titles">
          <div class="col-md-5 col-12 align-self-center">
            <h3 class="text-themecolor mb-0"><i class="mdi mdi-account-box"></i> Interação Inova</h3>            
          </div>          
        </div>


       
      <div class="container-fluid">

        <ul class="nav nav-pills p-3 bg-white mb-3 align-items-center">
            
            <li class="nav-item">
              <a href="interacao.php?tipo=pendentes" class="nav-link rounded-pill note-link d-flex align-items-center justify-content-center px-3 px-md-3 me-0 me-md-2" id="note-business">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-list feather-sm fill-white me-0 me-md-1"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3" y2="6"></line><line x1="3" y1="12" x2="3" y2="12"></line><line x1="3" y1="18" x2="3" y2="18"></line></svg><span class="d-none d-md-block font-weight-medium">Pendentes</span></a>
            </li>
            <li class="nav-item">
               <a href="interacao.php?tipo=arquivadas" class="nav-link rounded-pill note-link d-flex align-items-center justify-content-center px-3 px-md-3 me-0 me-md-2" id="note-social">
                    <i class="fa fa-archive feather-sm fill-white me-0 me-md-0"></i>
                    <span class="d-none d-md-block font-weight-medium">Arquivadas</span>
                </a>
            </li>



            
            <li class="nav-item ms-auto">
              <a href="javascript:void(0)" class="nav-link btn-success rounded-pill d-flex align-items-center px-3" id="add-card-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-file feather-sm fill-white me-0 me-md-1">
                  <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                  <polyline points="13 2 13 9 20 9"></polyline>
                </svg>
                <span class="d-none d-md-block font-weight-medium fs-3">Adicionar Tarefa</span>
              </a>
            </li>

          </ul>

          <div class="p-3">
                <div class="input-group searchbar">
                  <span class="input-group-text" id="search"><i class="icon-magnifier text-muted"></i></span>
                  <input type="text" class="form-control" placeholder="Pesquisar..." aria-describedby="search">
                </div>
              </div>
              <br>


<script> $(document).ready(function() {
  $('.searchbar input').on('keyup', function() {
    var searchTerm = $(this).val();

    // Somente realizar a busca se o termo de pesquisa tiver 3 ou mais caracteres
    if (searchTerm.length >= 3) {
      $.ajax({
        url: 'search_cards.php', // Arquivo PHP que irá processar a busca
        type: 'POST',
        data: { search: searchTerm },
        dataType: 'json',
        success: function(response) {
          // Atualiza o DOM com os cards e subtarefas retornados
          updateTaskboard(response.cards, response.subtasks);
        },
        error: function(xhr, status, error) {
          console.error('Erro na busca:', error);
        }
      });
    }
  });
});

function updateTaskboard(cards, subtasks) {
  // Limpa o quadro de tarefas e adiciona os resultados da busca
  $('#taskboard').empty();
  cards.forEach(addCardToDOM); // Sua função existente para adicionar cards
  subtasks.forEach(addSubtaskToDOM); // Sua função existente para adicionar subtarefas
}
</script>



    <div class="row">
        <div class="col-sm-12">            
            <div class="taskboard" id="taskboard">
                <!-- Os cards de tarefas serão inseridos aqui pelo JavaScript -->
            </div>
        </div>
    </div>

<div>
    <nav aria-label="Navegação de página exemplo">
        <ul class="pagination">
    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
        <li class="page-item <?php if ($paginaAtual === $i) echo 'active'; ?>">
            <a class="page-link" href="?tipo=<?php echo $tipo; ?>&pagina=<?php echo $i; ?>">
                <?php echo $i; ?>
            </a>
        </li>
    <?php endfor; ?>
</ul>

    </nav>
</div>


</div>



<!-- Modal de Visualização de Card -->
<div id="viewCardModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="viewCardModalLabel">
                    <i class="mdi mdi-eye-outline"></i>&nbsp;Visualizar Tarefa
                </h2>
                <span class="close" onclick="closeModalAndReload('viewCardModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="cardDetailsContainer">
                    <h3 class="form-label">Título da Tarefa</h3>
                    <p id="viewCardTitle"></p>
                    <h3 class="form-label">Responsáveis</h3>
                    <p id="viewResponsibleUsers"></p>
                    <h3 class="form-label">Subtarefas</h3>
                    <ul id="viewSubtasksList"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="submit-btn" onclick="closeModalAndReload('viewCardModal')">Fechar</button>

            </div>
        </div>
    </div>
</div>


<!-- Modal de Cadastro de Card (Tarefa) -->
<div id="addCardModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalCadastroLabel"><i class="mdi mdi-clipboard-text"></i>&nbsp;Cadastrar Tarefa</h2>
                <span class="close" onclick="closeModal('addCardModal')">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Campo para o título da tarefa -->
                <div class="mb-3">
                    <label for="addTarefaTitle" class="form-label">Título da Tarefa</label>
                    <input type="text" class="form-control bg-secondary text-white" id="addTarefaTitle" name="tarefa" required>
                </div>
                <!-- Campo para os usuários responsáveis -->
                <div class="mb-3">
                    <label for="responsibleUsers" class="form-label">Responsáveis</label>
                    <input type="text" class="form-control bg-secondary text-white" id="responsibleUsers" name="responsibleUsers" placeholder="Nomes separados por vírgula" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="submitCardForm()">Cadastrar</button>
            </div>
        </div>
    </div>
</div>



<!-- Modal de Edição de Card (Tarefa) -->
<div id="editCardModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalEdicaoLabel"><i class="mdi mdi-pencil"></i>&nbsp;Editar Tarefa</h2>
                <span class="close" onclick="closeModal('editCardModal')">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Campo para o título da tarefa -->
                <div class="mb-3">
                    <label for="editTarefaTitle" class="form-label">Título da Tarefa</label>
                    <input type="text" class="form-control bg-secondary text-white" id="editTarefaTitle" name="tarefa" required>
                </div>
                <!-- Campo para os usuários responsáveis -->
                <div class="mb-3">
                    <label for="editResponsibleUsers" class="form-label">Responsáveis</label>
                    <input type="text" class="form-control bg-secondary text-white" id="editResponsibleUsers" name="responsibleUsers" placeholder="Nomes separados por vírgula" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="submitEditCardForm()">Salvar Alterações</button>

            </div>
        </div>
    </div>
</div>




        <?php include 'footer.php'?>        
      </div>      
    </div>

    <div class="chat-windows"></div>


   <?php 
// Passar cards e subtarefas para o JavaScript
echo "<script>var cardsFromPHP = " . json_encode($cards) . ";</script>";
echo "<script>var subtasksFromPHP = " . json_encode($subtasks) . ";</script>";
echo "<script>var allSubtasksFromPHP = " . json_encode($allSubtasksForModal) . ";</script>";
echo "<script>var cardsFromPHP = " . json_encode($cards) . ";</script>";
echo "<script>var cardsFromPHP = " . json_encode($cards) . ";</script>";

   ?>


<script type="text/javascript">
        var tarefas = <?php echo json_encode($tarefas); ?>;
</script>

 <script>
$(document).ready(function() {
    // Funções auxiliares para criar botões de ação
    function createActionButton(iconClass, className, dataAttr, clickHandler, titleText) {
    var button = $('<button></button>')
        .addClass('action-button ' + className)
        .attr(dataAttr)
        .attr('title', titleText) // Adiciona o title aqui
        .append($('<i></i>').addClass(iconClass));
    button.on('click', clickHandler);
    return button;
}




    function addCardToDOM(card) {
    var archiveIconClass = card.is_archived === 0 ? 'fa fa-archive' : 'ti-menu';

    var cardElement = $('<div></div>')
        .addClass('card column clickable-card') // Adiciona a classe 'clickable-card' aqui
        .attr('data-card-id', card.id)
        .append($('<div></div>').addClass('card-header')
        .append($('<h2></h2>').html(`<span class="badge bg-danger" style="font-size: 17px;">${card.subtaskCount}</span> - ${card.title}`).css({
            'white-space': 'nowrap',
            'overflow': 'hidden',
            'text-overflow': 'ellipsis',
            'font-size': '17px',
            'text-transform': 'uppercase'
        }))

            .append($('<div></div>').addClass('card-actions')
            .append(createActionButton('fa fa-eye', 'view-card-btn', {'data-card-id': card.id}, function() {
                viewCardDetails(card.id);
            }, 'Ver tarefa')) // Passa 'Ver Tarefa' como title
            .append(createActionButton('fa fa-pencil-alt', 'edit-card-btn', {'data-card-id': card.id}, function() {
                editCard(card.id);
            }, 'Editar tarefa')) // Passa 'Editar Tarefa' como title
            .append(createActionButton(archiveIconClass, 'archive-card-btn', {'data-card-id': card.id}, function() {
                if (archiveIconClass === 'fa fa-archive') {
                    archiveCard(card.id);
                } else if (archiveIconClass === 'ti-menu') {
                    archiveCard(card.id);
                }
            }, archiveIconClass === 'fa fa-archive' ? 'Arquivar' : 'Voltar p/ pendente')) // Passa 'Arquivar' ou 'Pendente' como title dependendo do ícone
            .append(createActionButton('fa fa-trash', 'delete-card-btn', {'data-card-id': card.id}, function() {
                deleteCard(card.id);
            }, 'Excluir')) // Passa 'Excluir' como title
        )

        )
        .append($('<ul></ul>').addClass('task-list').attr('id', 'list-' + card.id))
        .append(createActionButton('fa fa-plus', 'add-subtask-btn', {'data-card-id': card.id}, function() {
            var subtaskTitle = prompt('Nome da Subtarefa:');
            if (subtaskTitle) {
                addSubtask(card.id, subtaskTitle);
            }
        }, 'Adicionar nova subtarefa'));

    $('#taskboard').append(cardElement);
}


// Certifique-se de que o evento está vinculado após o carregamento do DOM
$(document).on('click', '.clickable-card', function(event) {
    if (!$(event.target).is('.subtask-checkbox, button, i')) {
        var cardId = $(this).data('card-id');
        viewCardDetails(cardId);
    }
});







$(document).ready(function() {
    // Remova a classe 'active' de todas as abas inicialmente
    $(".nav-link").removeClass("active");

    // Pega o parâmetro 'tipo' da URL
    var tipo = new URLSearchParams(window.location.search).get('tipo');

    // Verifica se o parâmetro 'tipo' é 'arquivadas' ou 'pendentes' e ativa a aba correspondente
    if (tipo === 'arquivadas') {
        $("#note-social").addClass("active");
        showArchived(); // Mostra os cartões arquivados
    } else {
        $("#note-business").addClass("active");
        showPending(); // Mostra os cartões pendentes
    }

    // Adiciona o handler para quando as abas são clicadas
    $(".nav-link").click(function() {
        // Adiciona a classe 'active' à aba clicada
        $(this).addClass("active").siblings().removeClass("active");

        // Carrega os cartões com base na aba clicada
        if (this.id === "note-business") {
            showPending();
        } else if (this.id === "note-social") {
            showArchived();
        }

        // Previne o comportamento padrão do link
        return false;
    });
});



function archiveCard(cardId) {
    if (confirm('Tem certeza de que deseja alterar o estado desta tarefa?')) {
        $.ajax({
            url: 'archive_card.php',
            type: 'POST',
            data: { cardId: cardId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('O estado da tarefa foi alterado com sucesso!');
                    // Recarrega a página interacao.php para refletir as mudanças
                    window.location.href = 'interacao.php';
                } else {
                    alert('Erro ao alterar o estado da tarefa: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                alert('Erro ao enviar a requisição: ' + error);
            }
        });
    }
}



function filterCards(isArchived) {
    // Filtra os cards baseado no status de arquivado
    $('.card').each(function() {
        const cardId = $(this).data('card-id');
        const card = cardsFromPHP.find(c => c.id === cardId);
        if (card.is_archived === isArchived) {
            $(this).removeClass('hidden');
        } else {
            $(this).addClass('hidden');
        }
    });
}


   // Função para carregar os cartões e adicioná-los ao DOM
    function loadCards(isArchived) {
        $.ajax({
            url: 'fetch_cards.php',
            type: 'GET',
            data: { is_archived: isArchived },
            dataType: 'json',
            success: function(cards) {
                $('#taskboard').empty(); // Limpa o quadro de tarefas
                cards.forEach(addCardToDOM); // Adiciona os cartões ao DOM
            },
            error: function(xhr, status, error) {
                alert('Erro ao buscar tarefas: ' + error);
            }
        });
    }

    // Evento para mostrar cartões pendentes
    $('#note-business').click(function() {
        loadCards(0); // 0 para não arquivados
    });

    // Evento para mostrar cartões arquivados
    $('#note-social').click(function() {
        loadCards(1); // 1 para arquivados
    });




     // Função para visualizar os detalhes do card
function viewCardDetails(cardId) {
    // Encontra o card específico pelo ID
    const card = cardsFromPHP.find(c => c.id === cardId);
    if (!card) {
        alert('Card não encontrado.');
        return;
    }

    // Atualize o título do card no modal
    $('#viewCardTitle').text(card.title);

    // Atualize os usuários responsáveis        
    $('#viewResponsibleUsers').text(card.responsible_users || 'Nenhum usuário responsável definido');




function createSubtaskListItem(subtask) {
    const isChecked = subtask.status === 'done' ? 'checked' : '';
    const textDecoration = subtask.status === 'done' ? 'line-through' : 'none';

    // Botões de editar e excluir usando os mesmos ícones da página interacao.php
    // Note que os eventos de clique não estão mais sendo definidos aqui
    const editButton = $('<button></button>')
        .addClass('action-button edit-subtask-btn')
        .append($('<i></i>').addClass('fa fa-pencil-alt'))
        .attr({'data-subtask-id': subtask.id});

    const deleteButton = $('<button></button>')
        .addClass('action-button delete-subtask-btn')
        .append($('<i></i>').addClass('fa fa-trash'))
        .attr({'data-subtask-id': subtask.id});

    // Container para os botões de ação
    const actionContainer = $('<div></div>')
        .addClass('subtask-action-container')
        .css({
            'display': 'flex',
            'justify-content': 'flex-end',
            'gap': '10px',
            'margin-top': '10px'
        })
        .append(editButton)
        .append(deleteButton);

    // Template da subtarefa
    return `
        <li class="subtask-list-item">
            <div class="subtask-content">
                <input type="checkbox" class="subtask-checkbox" data-subtask-id="${subtask.id}" ${isChecked}>
                <span class="subtask-title" style="text-decoration: ${textDecoration};">
                    ${subtask.title}
                </span>
                ${actionContainer.prop('outerHTML')}
            </div>
            <div class="subtask-details">
                ${subtask.updated_at && subtask.created_at !== subtask.updated_at ? 
                    `Editada em: ${formatDate(subtask.updated_at)} por ${subtask.usuario_nome}` : 
                    `Criada em: ${formatDate(subtask.created_at)} por ${subtask.usuario_nome}`}
            </div>
        </li>
        <hr style="border-top: 1px solid #555;">
    `;
}

// Antes de adicionar novos manipuladores de eventos, remova os existentes para evitar duplicação
$(document).off('click', '.edit-subtask-btn').on('click', '.edit-subtask-btn', function() {
    var subtaskId = $(this).data('subtask-id');
    var currentSubtaskTitle = $(this).closest('.subtask-list-item').find('.subtask-title').text();
currentSubtaskTitle = currentSubtaskTitle.replace(/\s+/g, ' ').trim();
var newSubtaskTitle = prompt('Editar Subtarefa:', currentSubtaskTitle);

    // Verifique se o usuário não cancelou o prompt e se há uma mudança no título
    if (newSubtaskTitle !== null && newSubtaskTitle.trim() !== currentSubtaskTitle) {
        editSubtask(subtaskId, newSubtaskTitle.trim());
    }
});

$(document).off('click', '.delete-subtask-btn').on('click', '.delete-subtask-btn', function() {
    var subtaskId = $(this).data('subtask-id');
    if (confirm('Tem certeza que deseja excluir esta subtarefa?')) {
        deleteSubtask(subtaskId);
    }
});









const allSubtasksListHtml = allSubtasksFromPHP.filter(subtask => subtask.card_id === cardId)
    .map(createSubtaskListItem).join('');
$('#viewSubtasksList').html(allSubtasksListHtml);




    // Abra o modal
    $('#viewCardModal').show();
}

// Função para alternar a conclusão de uma subtarefa
$(document).on('click', '.subtask-checkbox', function(event) {
    event.stopPropagation(); 
    // O cardId agora é recuperado e passado para a função toggleSubtaskCompletion
    var subtaskId = $(this).data('subtask-id');
    var isCompleted = $(this).is(':checked');
    var cardId = $(this).closest('.card').data('card-id'); // Esta linha foi corrigida para pegar o card-id correto
    toggleSubtaskCompletion(subtaskId, isCompleted, cardId);
});

// Variável global ou localStorage para armazenar o ID do card atual
var currentCardId;

var userId = <?php echo $_SESSION['usuario_id']; ?>;

// Função para alternar a conclusão de uma subtarefa
function toggleSubtaskCompletion(subtaskId, isCompleted, cardId) {
    $.ajax({
        url: 'toggle_subtask_status.php',
        type: 'POST',
        data: {
            subtaskId: subtaskId,
            status: isCompleted ? 'done' : 'todo',
            userId: userId, // Envie o ID do usuário
            cardId: cardId
        },
        dataType: 'json',
        success: function(response) {
            console.log(response);
            if (response.success) {
                updateSubtaskElement(subtaskId, isCompleted, response);
                updateSubtaskElementInModal(subtaskId, isCompleted, response);
            } else {
                alert('Erro ao atualizar a subtarefa: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            alert('Erro na comunicação com o servidor: ' + error);
        }
    });
}


// Atualiza o elemento da subtarefa
function updateSubtaskElement(subtaskId, isCompleted, response) {
    var subtaskElement = $('[data-subtask-id="' + subtaskId + '"]').closest('.subtask-list-item');
    subtaskElement.toggleClass('task-done', isCompleted);
    subtaskElement.find('.subtask-checkbox').prop('checked', isCompleted);
    subtaskElement.find('.subtask-title').css('text-decoration', isCompleted ? 'line-through' : 'none');
    updateSubtaskDetails(subtaskElement, response);
}

// Atualiza o elemento da subtarefa no modal
function updateSubtaskElementInModal(subtaskId, isCompleted, response) {
    var subtaskElementModal = $('#viewSubtasksList').find('[data-subtask-id="' + subtaskId + '"]').closest('.subtask-list-item');
    if (subtaskElementModal.length) {
        subtaskElementModal.toggleClass('task-done', isCompleted);
        subtaskElementModal.find('.subtask-checkbox').prop('checked', isCompleted);
        subtaskElementModal.find('.subtask-title').css('text-decoration', isCompleted ? 'line-through' : 'none');
        updateSubtaskDetails(subtaskElementModal, response);
    }
}

// Atualiza os detalhes da subtarefa
function updateSubtaskDetails(subtaskElement, response) {
    if (response.editedAt && response.usuarioNome) {
        var formattedDate = formatDate(response.editedAt);
        var detailsText = 'Editada em: ' + formattedDate + ' por ' + response.usuarioNome;
        subtaskElement.find('.subtask-details').text(detailsText);
    }
}

// Listener para os checkboxes das subtarefas
$(document).on('click', '.subtask-checkbox', function(event) {
    event.stopPropagation(); 
    var subtaskId = $(this).data('subtask-id');
    var isCompleted = $(this).is(':checked');
    var cardId = $(this).closest('.card').data('card-id');
    toggleSubtaskCompletion(subtaskId, isCompleted, cardId);
});

// Função para formatar datas no formato local
function formatDate(dateString) {
    var date = new Date(dateString);
    var options = {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
    };
    return date.toLocaleDateString('pt-BR', options) + ' ' + date.toLocaleTimeString('pt-BR', options);
}


// Certifique-se de que o evento está vinculado após o carregamento do DOM e da lista de cards
$(document).on('click', '.view-card-btn', function() {
    var cardId = $(this).data('card-id');
    viewCardDetails(cardId);
})




    // Função para fechar o modal
    function closeModal(modalId) {
        $('#' + modalId).hide();
    }

    // Evento para fechar o modal quando o botão de fechar é clicado
    $('.modal-footer .submit-btn').click(function() {
        var modalId = $(this).closest('.modal').attr('id');
        closeModal(modalId);
    });

    // Evento para fechar o modal quando o "X" é clicado
    $('.modal-header .close').click(function() {
        var modalId = $(this).closest('.modal').attr('id');
        closeModal(modalId);
    });


// Abre o modal quando o botão de adicionar card é clicado
    $('#add-card-btn').click(function() {
        $('#addCardModal').show();
    });

    // Função para tratar a submissão do formulário de cadastro de tarefa
window.submitCardForm = function() {
    const title = $('#addTarefaTitle').val();
    const responsibleUsers = $('#responsibleUsers').val();

    $.ajax({
        url: 'add_card.php',
        type: 'POST',
        data: { cardTitle: title, responsibleUsers: responsibleUsers },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert("Tarefa adicionada com sucesso!");
                // Recarrega a página para mostrar a nova tarefa adicionada
                window.location.reload();
            } else {
                alert("Erro ao adicionar tarefa: " + response.error);
            }
            closeModal('addCardModal');
        },
        error: function(error) {
            alert("Erro na requisição: " + error.statusText);
            closeModal('addCardModal');
        }
    });
};

// Função para fechar modais
window.closeModal = function(modalId) {
    $('#' + modalId).hide();
};




    // Variável global para armazenar o ID do card que está sendo editado
let cardIdEditando = null;

// Função para abrir o modal de edição com os dados do card
function openEditCardModal(cardId) {
    cardIdEditando = cardId; // Armazena o ID do card

    // Encontre o card pelo ID e preencha os dados no modal
    const card = cardsFromPHP.find(c => c.id === cardId);
    if(card) {
        $('#editTarefaTitle').val(card.title);
        $('#editResponsibleUsers').val(card.responsible_users);
    }

    // Abra o modal
    $('#editCardModal').show();
}




// Função para tratar a submissão do formulário de edição de tarefa
    window.submitEditCardForm = function() {
        const title = $('#editTarefaTitle').val();
        const responsibleUsers = $('#editResponsibleUsers').val();

        // Submeter os dados editados via AJAX
        $.ajax({
            url: 'edit_card.php',
            type: 'POST',
            data: { cardId: cardIdEditando, title: title, responsibleUsers: responsibleUsers },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert("Tarefa atualizada com sucesso!");
                    window.location.reload();
                } else {
                    alert("Erro ao atualizar tarefa: " + response.error);
                }
                closeModal('editCardModal');
            },
            error: function(error) {
                alert("Erro na requisição: " + error.statusText);
                closeModal('editCardModal');
            }
        });

         // Após editar o card, atualize a última interação do card
    atualizarEOrdenarCards(cardIdEditando);

    };

// Adiciona a lógica de abertura do modal de edição
$('#taskboard').on('click', '.edit-card-btn', function() {
    var cardId = $(this).data('card-id');
    openEditCardModal(cardId);
});



// Evento para o botão de excluir
$('#taskboard').on('click', '.delete-card-btn', function() {
    var cardId = $(this).data('card-id');
    
    if (confirm('Tem certeza que deseja excluir esta tarefa?')) {
        $.ajax({
            url: 'delete_card.php', // URL do script PHP
            type: 'POST',
            data: { cardId: cardId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Tarefa excluída com sucesso!');
                    window.location.reload(); // Recarrega a página
                } else {
                    alert('Erro ao excluir tarefa: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                alert('Erro ao enviar a requisição: ' + error);
            }
        });
    }
});




    // Adicionar evento de clique para os botões de ação que são adicionados dinamicamente
   $('#taskboard').on('click', '.edit-subtask-btn', function() {
    var subtaskId = $(this).data('subtask-id');
    // Buscar o título atual da subtarefa
    var currentSubtaskTitle = $(this).closest('.task-item').find('.task-title').text();
    // Usar o título atual como valor padrão no prompt
    var newSubtaskTitle = prompt('Novo nome para a subtarefa:', currentSubtaskTitle).trim();
    if (newSubtaskTitle && newSubtaskTitle !== currentSubtaskTitle) {
        editSubtask(subtaskId, newSubtaskTitle);
    }
});


    $('#taskboard').on('click', '.delete-subtask-btn', function() {
        var subtaskId = $(this).attr('data-subtask-id');
        if (confirm('Tem certeza que deseja excluir esta subtarefa?')) {
            deleteSubtask(subtaskId);
        }
    });

// Função para adicionar uma subtarefa ao card correspondente no DOM
function addSubtaskToDOM(subtask) {
    var subtaskElement = $('<li></li>')
        .addClass('task-item')
        .attr('data-id', subtask.id)
        .attr('data-status', subtask.status)
        .css({'display': 'flex', 'flex-direction': 'column', 'margin-bottom': '10px'});

    var titleContainer = $('<div></div>') // Container para título e checkbox
        .css({'display': 'flex', 'align-items': 'center'});

    var checkBox = $('<input>').attr({
            type: 'checkbox',
            class: 'task-checkbox',
            'data-task-id': subtask.id,
            id: 'checkbox' + subtask.id
        }).prop('checked', subtask.status === 'done')
        .css({'margin-right': '10px'}); // Estilo para o checkbox

    var titleSpan = $('<span></span>')
        .addClass('task-title')
        .text(subtask.title)
        .css({
            'text-decoration': subtask.status === 'done' ? 'line-through' : 'none',
            'white-space': 'nowrap',
            'overflow': 'hidden',
            'text-overflow': 'ellipsis',
            'max-width': 'calc(91% - 40px)' // Ajuste a largura conforme necessário
        }); // Estilo condicional para o título

    titleContainer.append(checkBox).append(titleSpan);

    var detailsText = subtask.updated_at && subtask.updated_at !== subtask.created_at
        ? 'Editada em: ' + formatDate(subtask.updated_at) + ' por ' + subtask.usuario_nome
        : 'Criada em: ' + formatDate(subtask.created_at) + ' por ' + subtask.usuario_nome;

    var detailsDiv = $('<div></div>') // Utilizei div ao invés de span para garantir que é um bloco
        .addClass('task-details')
        .text(detailsText)
        .css({'font-size': '0.8rem', 'color': '#777'}); // Estilo para os detalhes

    var actionsDiv = $('<div></div>') // Container para ações
        .addClass('task-actions')
        .css({'display': 'flex', 'justify-content': 'flex-end', 'margin-top': '10px'}); // Ajuste para flexbox

    // Criar e adicionar botões de ação
    var editButton = createActionButton('fa fa-pencil-sdalt', 'edit-subtask-btn', {'data-subtask-id': subtask.id}, function() {
        // Adicione aqui a lógica de edição
    });

    var deleteButton = createActionButton('fa fa-trashdf', 'delete-subtask-btn', {'data-subtask-id': subtask.id}, function() {
        // Adicione aqui a lógica de exclusão
    });

    actionsDiv.append(editButton).append(deleteButton);

    subtaskElement
        .append(titleContainer)
        .append(detailsDiv)
        .append(actionsDiv);

    $('#list-' + subtask.card_id).append(subtaskElement);
}




    // Funções para adicionar cards e subtarefas ao quadro
    cardsFromPHP.forEach(addCardToDOM);
    subtasksFromPHP.forEach(addSubtaskToDOM);

    // Chame essa função quando a página carregar para mostrar inicialmente apenas tarefas pendentes
    showPending();



 // Eventos de clique para as abas
$(".nav-link").click(function() {
    // Remove a classe 'active' de todas as abas
    $(".nav-link").removeClass("active");

    // Adiciona a classe 'active' à aba clicada
    $(this).addClass("active");

    // Adiciona a lógica para carregar os cartões baseado na aba clicada
    if (this.id === "note-business") {
        // Carrega os cartões pendentes
        showPending();
    } else if (this.id === "note-social") {
        // Carrega os cartões arquivados
        showArchived();
    }
    // Previne o comportamento padrão do link
    return false;
});

// Carrega os cartões pendentes ou arquivados baseado na URL ao carregar a página
$(document).ready(function() {
    var tipo = getParameterByName('tipo'); // Implemente esta função para pegar o parâmetro da URL
    if (tipo === 'arquivadas') {
        $("#note-social").click();
    } else {
        $("#note-business").click();
    }
});

// Função para pegar o valor de um parâmetro específico da URL
function getParameterByName(name, url = window.location.href) {
    name = name.replace(/[\[\]]/g, '\\$&');
    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
}


    // Função para atualizar a data da última interação e reordenar os cards
function atualizarEOrdenarCards(cardId, lastInteractionDate) {
    var cardElement = $('[data-card-id="' + cardId + '"]');
    cardElement.data('last-interaction', lastInteractionDate);
    reordenarCards();
}

    // Função para enviar uma requisição para adicionar uma nova subtarefa
    function addSubtask(cardId, subtaskTitle) {
    $.ajax({
        url: 'add_subtask.php',
        type: 'POST',
        data: { cardId: cardId, subtaskTitle: subtaskTitle },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                addSubtaskToDOM({
                    id: response.subtaskId,
                    card_id: cardId,
                    title: subtaskTitle,
                    created_at: response.createdAt,
                    usuario_nome: response.usuarioNome,
                    status: 'todo' // Ou outro status padrão se necessário
                });

                // Atualiza a última interação do card
                var cardElement = $('#list-' + cardId).closest('.card');
                cardElement.data('last-interaction', new Date().toISOString());

                reordenarCards();

                // Força um recarregamento da página para garantir que os eventos de clique sejam reinicializados
                window.location.reload();
            } else {
                alert('Erro ao criar a subtarefa: ' + response.error);
            }
        },
        error: function() {
            alert('Erro ao enviar a requisição.');
        }
    });

        // Após adicionar a subtarefa, atualize a última interação do card
    atualizarEOrdenarCards(cardId);

    }

    function atualizarSubtarefas(cardId) {
    var listElement = $('#list-' + cardId);
    var subtasks = listElement.find('.task-item');

    // Mantém apenas as duas últimas subtarefas
    while (subtasks.length > 2) {
        subtasks.first().remove();
        subtasks = listElement.find('.task-item');
    }
}


    // Função para enviar uma requisição para marcar uma tarefa como concluída
    function markTaskCompleted(taskId) {
    if (confirm('Tem certeza que deseja marcar esta tarefa como concluída?')) {
        $.ajax({
            url: 'mark_task_completed.php',
            type: 'POST',
            data: { taskId: taskId, status: 'done' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Remova o card da tarefa do DOM
                    $('[data-task-id="' + taskId + '"]').closest('.card').remove();
                    alert('Tarefa marcada como concluída!');
                } else {
                    alert('Erro ao marcar a tarefa como concluída: ' + response.error);
                }
            },
            error: function() {
                alert('Erro ao enviar a requisição.');
            }
        });
    }
}



// Função para enviar uma requisição para editar uma subtarefa
function editSubtask(subtaskId, newTitle) {
    newTitle = newTitle.trim();
    $.ajax({
        url: 'edit_subtask.php',
        type: 'POST',
        data: { subtaskId: subtaskId, title: newTitle },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var subtaskElement = $('[data-subtask-id="' + subtaskId + '"]').closest('.subtask-list-item');
                subtaskElement.find('.subtask-title').text(newTitle);

                // Atualiza os detalhes da subtarefa com o nome do usuário e a data de edição
                if (response.usuarioNome && response.editedAt) {
                    var formattedDate = formatDate(response.editedAt);
                    var detailText = 'Editada em: ' + formattedDate + ' por ' + response.usuarioNome;
                    subtaskElement.find('.subtask-details').text(detailText);
                }

                updateSubtasksInDom(cardId);
            } else {
                alert('Erro ao editar a subtarefa: ' + response.error);
            }
        },
        error: function() {
            alert('Erro ao enviar a requisição.');
        }
    });

     var listItem = $('.subtask-list-item').filter(function() {
        return $(this).find('.subtask-checkbox').data('subtask-id') == subtaskId;
    });
    listItem.find('.subtask-title').text(newTitle).css('text-decoration', 'none'); // Atualize o título e remova o tachado

    // Após editar a subtarefa, encontre o cardId correspondente
    var cardId = $('[data-subtask-id="' + subtaskId + '"]').closest('.card').data('card-id');
    atualizarEOrdenarCards(cardId);

}


 // Função para atualizar a lista de subtarefas no modal de visualização
function updateSubtasksInDom(cardId) {
    const allSubtasksListHtml = allSubtasksFromPHP.filter(subtask => subtask.card_id === cardId)
        .map(createSubtaskListItem).join('');
    
    // Atualiza a lista de subtarefas no card
    $('#card-' + cardId + ' .subtasks-container').empty().append(allSubtasksListHtml);

    // Atualiza a lista de subtarefas no modal, se estiver aberto
    if ($('#viewCardModal').is(':visible')) {
        $('#viewSubtasksList').empty().append(allSubtasksListHtml);
    }
}





// Função para excluir subtarefa
function deleteSubtask(subtaskId) {
    if (confirm('Tem certeza que deseja excluir esta subtarefa?')) {
        $.ajax({
            url: 'delete_subtask.php',
            type: 'POST',
            data: { subtaskId: subtaskId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Subtarefa removida do DOM
                    $('[data-subtask-id="' + subtaskId + '"]').closest('.subtask-list-item').remove();
                    updateSubtasksInDom(cardId);
                } else {
                    alert('Erro ao excluir a subtarefa: ' + response.error);
                }
            },
            error: function() {
                alert('Erro ao enviar a requisição.');
            }
        });
    }
}






    // Função para formatar datas
    function formatDate(dateString) {
        var date = new Date(dateString);
        var options = { year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: false };
        return date.toLocaleDateString('pt-BR', options);
    }
});

// Adiciona a lógica de alternância ao clique do checkbox
    $(document).on('change', '.task-checkbox', function() {
    var taskId = $(this).data('task-id');
    var isCompleted = $(this).is(':checked');
    toggleTaskCompletion(taskId, isCompleted);
});

    // Chame essa função quando a página carregar para mostrar inicialmente apenas tarefas pendentes
    showPending(); // <-- Adicione esta linha aqui

// Função para alternar o estado concluído de uma tarefa
function toggleTaskCompletion(taskId, isCompleted) {
    $.ajax({
        url: 'toggle_task_status.php',
        type: 'POST',
        data: { taskId: taskId, status: isCompleted ? 'done' : 'todo' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Aqui você pode escolher entre recarregar a página ou remover o elemento do DOM
                window.location.href = 'interacao.php'; // Recarrega a página
                // Ou, para remover do DOM sem recarregar:
                // $('[data-task-id="' + taskId + '"]').closest('.card').remove();
            } else {
                alert('Erro ao atualizar o status da tarefa: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            alert('Erro ao enviar a requisição: ' + error);
        }
    });
}


// Chame essa função quando a página carregar e após adicionar uma nova tarefa
$(document).ready(function() {
    updateTaskCheckboxes();
});

// Função para reordenar os cards com base na última interação
function reordenarCards() {
    var cardsArray = $('.card').toArray();
    cardsArray.sort(function(a, b) {
        var lastInteractionA = $(a).data('last-interaction');
        var lastInteractionB = $(b).data('last-interaction');
        return new Date(lastInteractionB) - new Date(lastInteractionA);
    });
    $('#taskboard').empty().append(cardsArray);
}


function closeModalAndReload(modalId) {
    // Feche o modal
    closeModal(modalId);

    // Recarregue a página interacao.php
    window.location.href = 'interacao.php';
}

// Função para alternar entre pendentes e arquivadas
function showCardsByStatus(status) {
    window.location.href = 'interacao.php?status=' + status;
}

// Evento para mostrar cards pendentes
$('#note-business').click(function() {
    showPending();
});

// Evento para mostrar cards arquivados
$('#note-social').click(function() {
    showArchived();
});


</script>







<script>
// Função para formatar datas
function formatDate(dateString) {
    var date = new Date(dateString);
    return date.toLocaleDateString();
}

// Função para adicionar tarefas ao DOM
function addTaskToDOM(task) {
    var taskElement = $('<div></div>')
        .addClass('task-item')
        .toggleClass('task-done', task.status === 'done')
        .append(
            $('<input>')
                .attr('type', 'checkbox')
                .addClass('task-checkbox')
                .data('task-id', task.id)
                .prop('checked', task.status === 'done')
        )
        .append(
            $('<span></span>')
                .addClass('task-title')
                .text(task.title)
        )
        .append(
            $('<div></div>')
                .addClass('task-actions')
                .append(
                    $('<button></button>')
                        .addClass('edit-task-btn')
                        .data('task-id', task.id)
                        .html('✏️') // Você pode substituir por ícones apropriados
                )
                .append(
                    $('<button></button>')
                        .addClass('delete-task-btn')
                        .data('task-id', task.id)
                        .html('❌') // Você pode substituir por ícones apropriados
                )
        );

    $('#taskboard').append(taskElement);
}


// Adicionar tarefas ao quadro depois de carregar a página
$(document).ready(function() {
    tarefasFromPHP.forEach(addTaskToDOM);



// Eventos para checkbox, edição e exclusão
$(document).on('change', '.task-checkbox', function() {
    var taskId = $(this).data('task-id');
    toggleTaskCompletion(taskId);
});

$(document).on('click', '.edit-task-btn', function() {
    var taskId = $(this).data('task-id');
    // Adicione a lógica para editar a tarefa
});

$(document).on('click', '.delete-task-btn', function() {
    var taskId = $(this).data('task-id');
    // Adicione a lógica para excluir a tarefa
});



</script>





   
   
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <!-- apps -->
    <script src="dist/js/app.min.js"></script>
    <script src="dist/js/app.init.dark.js"></script>
    <script src="dist/js/app-style-switcher.js"></script>
    <!-- slimscrollbar scrollbar JavaScript -->
    <script src="assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script>
    <script src="assets/extra-libs/sparkline/sparkline.js"></script>
    <!--Wave Effects -->
    <script src="dist/js/waves.js"></script>
    <!--Menu sidebar -->
    <script src="dist/js/sidebarmenu.js"></script>
    <!--Custom JavaScript -->
    <script src="dist/js/feather.min.js"></script>
    <script src="dist/js/custom.min.js"></script>
  </body>
</html>

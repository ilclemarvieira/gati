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

// Passar cards e subtarefas para o JavaScript
echo "<script>var cardsFromPHP = " . json_encode($cards) . ";</script>";
echo "<script>var subtasksFromPHP = " . json_encode($subtasks) . ";</script>";

?>



<!DOCTYPE html>
<html dir="ltr" lang="pt">

  <head>
    <?php include 'head.php'?>


<script src="assets/libs/jquery/dist/jquery.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

    <style>
    .modal-content {
        /* Estilos para o conteúdo do modal */
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
  }

.card-body {
  display: flex;
  align-items: center; /* Centraliza verticalmente o conteúdo */
}
.card-title {
  margin-bottom: 0; /* Remove a margem padrão do título */
  font-size: 15px;
  Font-weight:bold;
}
.card-text {
  margin-top: 4px; /* Espaçamento entre o título e o texto */
  font-size: 16px;
}
.text-muted {
  display: block; /* Faz com que o texto ocupe sua própria linha */
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
    display: flex; /* Isso fará com que os cards fiquem um ao lado do outro */
    flex-wrap: wrap; /* Permite que os cards se envolvam na próxima linha se não houver espaço */
    justify-content: flex-start; /* Alinha os cards à esquerda */
    gap: 10px; /* Espaço entre os cards */
}

.column {
    width: calc(33.333% - 10px); /* Largura para 3 cards por linha com espaço de 10px */
    background-color: #333;
    color: white;
    padding: 10px;
    border-radius: 4px; /* Borda arredondada */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Sombra para os cards */
}

/* Ajustes responsivos para telas menores */
@media (max-width: 768px) {
    .column {
        width: calc(50% - 10px); /* 2 cards por linha em telas menores */
    }
}

@media (max-width: 480px) {
    .column {
        width: 100%; /* 1 card por linha em telas muito pequenas */
    }
}


.task-list {
  list-style: none;
  padding: 0;
}

.task-list li {
  background-color: #b8b97469;
  margin-bottom: 10px;
  padding: 10px;
  border-radius: 4px;
}

.add-task-btn {
  margin-top: 10px;
}

/* Adicione estas regras ao seu arquivo de estilo existente */
.task-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #FFF;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.task-checkbox {
    margin-right: 10px;
}

.task-title {
    flex-grow: 1;
}

.task-actions {
    display: flex;
    align-items: center;
}

.task-actions button {
    background: none;
    border: none;
    cursor: pointer;
    margin-left: 10px;
}

.task-done .task-title {
    text-decoration: line-through;
    color: #A9A9A9;
}


.task-done {
    background-color: #d4edda; /* Cor de fundo para tarefas concluídas */
    text-decoration: line-through; /* Tachado para tarefas concluídas */
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
    <div class="row">
        <div class="col-sm-12">
            <button id="add-card-btn" class="btn btn-primary">Adicionar Tarefa</button>
            <br><br>
            <div class="taskboard" id="taskboard">
                <!-- Os cards de tarefas serão inseridos aqui pelo JavaScript -->
            </div>
        </div>
    </div>
</div>




        <?php include 'footer.php'?>        
      </div>      
    </div>

    <div class="chat-windows"></div>





<script type="text/javascript">
        var tarefas = <?php echo json_encode($tarefas); ?>;
</script>

 <script>
$(document).ready(function() {
    // Funções auxiliares para criar botões de ação
    function createActionButton(text, className, dataAttr, clickHandler) {
        return $('<button></button>')
            .addClass(className)
            .text(text)
            .attr(dataAttr)
            .click(clickHandler);
    }

    // Função para adicionar um card ao DOM
    function addCardToDOM(card) {
        var cardElement = $('<div></div>')
            .addClass('column')
            .attr('data-card-id', card.id)
            .append($('<h2></h2>').text(card.title))
            .append($('<ul></ul>').addClass('task-list').attr('id', 'list-' + card.id))
            .append(createActionButton('Add new', 'add-subtask-btn', {'data-card-id': card.id}, function() {
                var subtaskTitle = prompt('Nome da Subtarefa:');
                if (subtaskTitle) {
                    addSubtask(card.id, subtaskTitle);
                }
            }));
        $('#taskboard').append(cardElement);
    }

    // Função para adicionar uma subtarefa ao card correspondente no DOM
    function addSubtaskToDOM(subtask) {
        var subtaskElement = $('<li></li>')
            .addClass('task-item')
            .attr('data-id', subtask.id)
            .text(subtask.title + ' - Criada em: ' + formatDate(subtask.created_at) + ' por ' + subtask.usuario_nome)
            .attr('data-status', subtask.status)
            .append(createActionButton('✔️', 'check-task-btn', {'data-task-id': subtask.id}, function() {
                markTaskCompleted($(this).data('task-id'));
            }))
            .append(createActionButton('✏️', 'edit-subtask-btn', {'data-subtask-id': subtask.id}, function() {
                var newTitle = prompt('Novo nome para a subtarefa:', subtask.title);
                if (newTitle) {
                    editSubtask($(this).data('subtask-id'), newTitle);
                }
            }))
            .append(createActionButton('❌', 'delete-subtask-btn', {'data-subtask-id': subtask.id}, function() {
                if (confirm('Tem certeza que deseja excluir esta subtarefa?')) {
                    deleteSubtask($(this).data('subtask-id'));
                }
            }));

        if(subtask.status === 'done') {
            subtaskElement.addClass('task-done');
        }

        $('#list-' + subtask.card_id).append(subtaskElement);
    }

    // Processar e adicionar cards e subtarefas ao quadro após o carregamento da página
    cardsFromPHP.forEach(addCardToDOM);
    subtasksFromPHP.forEach(addSubtaskToDOM);

    // Adicionar evento de clique para criar um novo card
    $('#add-card-btn').click(function() {
        var cardTitle = prompt('Nome da Tarefa:');
        if (cardTitle) {
            addCard(cardTitle);
        }
    });

    // Função para enviar uma requisição para adicionar um novo card
    function addCard(cardTitle) {
        $.ajax({
            url: 'add_card.php',
            type: 'POST',
            data: { cardTitle: cardTitle },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    addCardToDOM({
                        id: response.cardId,
                        title: cardTitle
                    });
                } else {
                    alert('Erro ao criar o card: ' + response.error);
                }
            },
            error: function() {
                alert('Erro ao enviar a requisição.');
            }
        });
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
                } else {
                    alert('Erro ao criar a subtarefa: ' + response.error);
                }
            },
            error: function() {
                alert('Erro ao enviar a requisição.');
            }
        });
    }

    // Função para enviar uma requisição para marcar uma tarefa como concluída
    function markTaskCompleted(taskId) {
        $.ajax({
            url: 'mark_task_completed.php',
            type: 'POST',
            data: { taskId: taskId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('[data-task-id="' + taskId + '"]').closest('.task-item').addClass('task-done');
                } else {
                    alert('Erro ao marcar a tarefa como concluída: ' + response.error);
                }
            },
            error: function() {
                alert('Erro ao enviar a requisição.');
            }
        });
    }

    // Função para enviar uma requisição para editar uma subtarefa
function editSubtask(subtaskId, newTitle) {
    $.ajax({
        url: 'edit_subtask.php',
        type: 'POST',
        data: { subtaskId: subtaskId, title: newTitle },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Atualize a interface do usuário conforme necessário...
                alert('Subtarefa atualizada com sucesso!');

                // Em seguida, redirecione para a página 'interacao.php'.
                window.location.href = 'interacao.php';
            } else {
                alert('Erro ao editar a subtarefa: ' + response.error);
            }
        },
        error: function() {
            alert('Erro ao enviar a requisição.');
        }
    });
}



    // Função para excluir subtarefa
    function deleteSubtask(subtaskId) {
        $.ajax({
            url: 'delete_subtask.php',
            type: 'POST',
            data: { subtaskId: subtaskId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('[data-subtask-id="' + subtaskId + '"]').remove();
                } else {
                    alert('Erro ao excluir a subtarefa: ' + response.error);
                }
            },
            error: function() {
                alert('Erro ao enviar a requisição.');
            }
        });
    }

    // Função para formatar datas
    function formatDate(dateString) {
        var date = new Date(dateString);
        var options = { year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: false };
        return date.toLocaleDateString('pt-BR', options);
    }
});

// Função para alternar o estado concluído de uma tarefa
function toggleTaskCompletion(taskId) {
    var taskItem = $('[data-task-id="' + taskId + '"]').closest('.task-item');
    var newStatus = taskItem.hasClass('task-done') ? 'todo' : 'done';

    $.ajax({
        url: 'toggle_task_status.php',
        type: 'POST',
        data: { taskId: taskId, status: newStatus },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                taskItem.toggleClass('task-done', response.status === 'done');
            } else {
                alert('Erro ao atualizar o status da tarefa: ' + response.error);
            }
        },
        error: function() {
            alert('Erro ao enviar a requisição.');
        }
    });
}


// Adiciona a lógica de alternância ao clique do botão de conclusão da tarefa
$(document).on('click', '.check-task-btn', function() {
    var taskId = $(this).data('task-id');
    toggleTaskCompletion(taskId);
});


</script>


<script> 

// Esta função pode ser chamada quando a página é carregada para atualizar o estado dos checkboxes
function updateTaskCheckboxes() {
    $('.task-checkbox').each(function() {
        var isChecked = $(this).closest('.task-item').hasClass('task-done');
        $(this).prop('checked', isChecked);
    });
}

// Atualizar o status da tarefa quando o checkbox é clicado
$(document).on('change', '.task-checkbox', function() {
    var taskId = $(this).data('task-id');
    var isChecked = $(this).is(':checked');
    var taskItem = $(this).closest('.task-item');
    var newStatus = isChecked ? 'done' : 'todo';

    $.ajax({
        url: 'toggle_task_status.php',
        type: 'POST',
        data: { taskId: taskId, status: newStatus },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                taskItem.toggleClass('task-done', isChecked);
            } else {
                alert('Erro ao atualizar o status da tarefa: ' + response.error);
            }
        },
        error: function() {
            alert('Erro ao enviar a requisição.');
        }
    });
});

// Chame essa função quando a página carregar e após adicionar uma nova tarefa
updateTaskCheckboxes();


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

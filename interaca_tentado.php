<?php
session_start();

// Redireciona o usuário se não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Inclui o arquivo de conexão com o banco de dados
include 'db.php';

// Função para adicionar uma nova tarefa
function addCardTarefa($titulo, $cor) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO cardtarefas (titulo, cor) VALUES (?, ?)");
    $stmt->bind_param("ss", $titulo, $cor);
    $stmt->execute();
    return $stmt->insert_id; // Retorna o ID da tarefa criada
}

// Função para adicionar uma nova subtarefa
function addSubTarefa($cardTarefaId, $descricao) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO subtarefas (cardtarefa_id, descricao) VALUES (?, ?)");
    $stmt->bind_param("is", $cardTarefaId, $descricao);
    $stmt->execute();
    return $stmt->insert_id; // Retorna o ID da subtarefa criada
}

// Função para listar as tarefas
function getCardTarefas() {
    global $conn;
    $result = $conn->query("SELECT * FROM cardtarefas ORDER BY ordem ASC");
    return $result->fetch_all(MYSQLI_ASSOC); // Retorna todas as tarefas como um array associativo
}

// Função para listar as subtarefas de uma tarefa específica
function getSubTarefas($cardTarefaId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM subtarefas WHERE cardtarefa_id = ? ORDER BY ordem ASC");
    $stmt->bind_param("i", $cardTarefaId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC); // Retorna todas as subtarefas como um array associativo
}

// Funções de atualização e remoção ficarão aqui...
// ...

// Exemplo de uso
// $newCardId = addCardTarefa("Nova Tarefa", "#ff0000");
// $newSubTarefaId = addSubTarefa($newCardId, "Nova Subtarefa");

// $tarefas = getCardTarefas();
// $subtarefas = getSubTarefas($newCardId);

// TODO: Implemente o resto da lógica para manipular tarefas e subtarefas
// ...

?>



<!DOCTYPE html>
<html dir="ltr" lang="pt">
  <head>
    <?php include 'head.php'?>
     
  </head>



  <body>
    <!-- -------------------------------------------------------------- -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- -------------------------------------------------------------- -->
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
            <h3 class="text-themecolor mb-0"><i class="mdi mdi-comment-processing-outline"></i> Interação Inova</h3>            
          </div>          
        </div>

        <div class="container-fluid">          
          <div class="row">
            <div class="col-sm-12">
               <button id="add-card-btn" class="btn btn-primary">Adicionar Tarefa</button>
            <br><br>
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Todas tarefas do Inova</h4>
                  <div class="lobilists single-line ui-sortable"><div class="lobilist-wrapper"><div id="todo" class="lobilist lobilist-danger ps-container ps-theme-default" data-ps-id="d3575429-bb5b-0b68-bc50-03f25c895dfa"><div class="lobilist-header ui-sortable-handle"><div class="lobilist-actions"><div class="dropdown"><button type="button" data-bs-toggle="dropdown" class="btn btn-xs"><i class="icon-grid"></i></button><div class="dropdown-menu dropdown-menu-right"><div class="lobilist-default"></div><div class="lobilist-danger"></div><div class="lobilist-success"></div><div class="lobilist-warning"></div><div class="lobilist-info"></div><div class="lobilist-primary"></div></div></div><button class="btn btn-xs"><i class="icon-pencil"></i></button><button class="btn btn-xs btn-finish-title-editing"><i class="ti-check-box"></i></button><button class="btn btn-xs btn-cancel-title-editing"><i class="ti-close"></i></button><button class="btn btn-xs"><i class="ti-plus"></i></button><button class="btn btn-xs"><i class="icon-trash"></i></button></div><div class="lobilist-title">Tarefa 1</div></div><div class="lobilist-body"><ul class="lobilist-items ui-sortable"></ul><form class="lobilist-add-todo-form hide"><input type="hidden" name="id" value=""><div class="form-group"><input type="text" name="title" class="form-control" placeholder="TODO title"></div><div class="form-group"><textarea rows="2" name="description" class="form-control" placeholder="TODO description"></textarea></div><div class="form-group"><input type="text" name="dueDate" class="datepicker form-control hasDatepicker" placeholder="Due Date" id="dp1705956499741"></div><div class="lobilist-form-footer"><button class="btn btn-primary btn-sm btn-add-todo">Add/Update</button><button type="button" class="btn btn-danger btn-sm btn-discard-todo">Cancel</button></div></form></div><div class="lobilist-footer"><button type="button" class="btn-light-primary text-primary btn btn-show-form">Adicionar Nova</button></div><div class="ps-scrollbar-x-rail" style="left: 0px; bottom: 0px;"><div class="ps-scrollbar-x" tabindex="0" style="left: 0px; width: 0px;"></div></div><div class="ps-scrollbar-y-rail" style="top: 0px; height: 419px; right: 3px;"><div class="ps-scrollbar-y" tabindex="0" style="top: 0px; height: 0px;"></div></div></div></div><div class="lobilist-wrapper"><div id="doing" class="lobilist lobilist-primary ps-container ps-theme-default" data-ps-id="1bda8100-0be7-24b1-0895-efcfc30040af"><div class="lobilist-header ui-sortable-handle"><div class="lobilist-actions"><div class="dropdown"><button type="button" data-bs-toggle="dropdown" class="btn btn-xs"><i class="icon-grid"></i></button><div class="dropdown-menu dropdown-menu-right"><div class="lobilist-default"></div><div class="lobilist-danger"></div><div class="lobilist-success"></div><div class="lobilist-warning"></div><div class="lobilist-info"></div><div class="lobilist-primary"></div></div></div><button class="btn btn-xs"><i class="icon-pencil"></i></button><button class="btn btn-xs btn-finish-title-editing"><i class="ti-check-box"></i></button><button class="btn btn-xs btn-cancel-title-editing"><i class="ti-close"></i></button><button class="btn btn-xs"><i class="ti-plus"></i></button><button class="btn btn-xs"><i class="icon-trash"></i></button></div><div class="lobilist-title">Tarefa 2</div></div><div class="lobilist-body"><ul class="lobilist-items ui-sortable"><li data-id="5" class="lobilist-item"><div class="lobilist-item-title">Composed trays</div><div class="lobilist-item-description text-muted">Hoary rattle exulting suspendisse elit paradises craft wistful. Bayonets allures prefer traits wrongs flushed. Tent wily matched bold polite slab coinage celerities gales beams.</div><label class="checkbox-inline lobilist-check form-check me-2"><input type="checkbox" class="form-check-input primary"></label><div class="todo-actions"><div class="edit-todo todo-action"><i class="ti-pencil"></i></div><div class="delete-todo todo-action"><i class="ti-close"></i></div></div><div class="drag-handler"></div></li></ul><form class="lobilist-add-todo-form hide"><input type="hidden" name="id"><div class="form-group"><input type="text" name="title" class="form-control" placeholder="TODO title"></div><div class="form-group"><textarea rows="2" name="description" class="form-control" placeholder="TODO description"></textarea></div><div class="form-group"><input type="text" name="dueDate" class="datepicker form-control hasDatepicker" placeholder="Due Date" id="dp1705956499742"></div><div class="lobilist-form-footer"><button class="btn btn-primary btn-sm btn-add-todo">Add/Update</button><button type="button" class="btn btn-danger btn-sm btn-discard-todo">Cancel</button></div></form></div><div class="lobilist-footer"><button type="button" class="btn-light-primary text-primary btn btn-show-form">Adicionar Nova</button></div><div class="ps-scrollbar-x-rail" style="left: 0px; bottom: 0px;"><div class="ps-scrollbar-x" tabindex="0" style="left: 0px; width: 0px;"></div></div><div class="ps-scrollbar-y-rail" style="top: 0px; right: 3px;"><div class="ps-scrollbar-y" tabindex="0" style="top: 0px; height: 0px;"></div></div></div></div><div class="lobilist-wrapper"><div id="Done" class="lobilist lobilist-success ps-container ps-theme-default" data-ps-id="d20b8633-3139-29f6-5c04-39c4eb6e7a60"><div class="lobilist-header ui-sortable-handle"><div class="lobilist-actions"><div class="dropdown"><button type="button" data-bs-toggle="dropdown" class="btn btn-xs"><i class="icon-grid"></i></button><div class="dropdown-menu dropdown-menu-right"><div class="lobilist-default"></div><div class="lobilist-danger"></div><div class="lobilist-success"></div><div class="lobilist-warning"></div><div class="lobilist-info"></div><div class="lobilist-primary"></div></div></div><button class="btn btn-xs"><i class="icon-pencil"></i></button><button class="btn btn-xs btn-finish-title-editing"><i class="ti-check-box"></i></button><button class="btn btn-xs btn-cancel-title-editing"><i class="ti-close"></i></button><button class="btn btn-xs"><i class="ti-plus"></i></button><button class="btn btn-xs"><i class="icon-trash"></i></button></div><div class="lobilist-title">Tarefa 3</div></div><div class="lobilist-body"><ul class="lobilist-items ui-sortable"><li data-id="8" class="lobilist-item"><div class="lobilist-item-title">Composed trays</div><div class="lobilist-item-description text-muted">Hoary rattle exulting suspendisse elit paradises craft wistful. Bayonets allures prefer traits wrongs flushed. Tent wily matched bold polite slab coinage celerities gales beams.</div><label class="checkbox-inline lobilist-check form-check me-2"><input type="checkbox" class="form-check-input primary"></label><div class="todo-actions"><div class="edit-todo todo-action"><i class="ti-pencil"></i></div><div class="delete-todo todo-action"><i class="ti-close"></i></div></div><div class="drag-handler"></div></li></ul><form class="lobilist-add-todo-form hide"><input type="hidden" name="id"><div class="form-group"><input type="text" name="title" class="form-control" placeholder="TODO title"></div><div class="form-group"><textarea rows="2" name="description" class="form-control" placeholder="TODO description"></textarea></div><div class="form-group"><input type="text" name="dueDate" class="datepicker form-control hasDatepicker" placeholder="Due Date" id="dp1705956499743"></div><div class="lobilist-form-footer"><button class="btn btn-primary btn-sm btn-add-todo">Add/Update</button><button type="button" class="btn btn-danger btn-sm btn-discard-todo">Cancel</button></div></form></div><div class="lobilist-footer"><button type="button" class="btn-light-primary text-primary btn btn-show-form">Adicionar Nova</button></div><div class="ps-scrollbar-x-rail" style="left: 0px; bottom: 0px;"><div class="ps-scrollbar-x" tabindex="0" style="left: 0px; width: 0px;"></div></div><div class="ps-scrollbar-y-rail" style="top: 0px; right: 3px;"><div class="ps-scrollbar-y" tabindex="0" style="top: 0px; height: 0px;"></div></div></div></div></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <?php include 'footer.php'?>  
        
      </div>
      
    </div>
       
    <div class="chat-windows"></div>


    <script> 
      $(document).ready(function() {
    $('#add-card-btn').on('click', function() {
        var tituloTarefa = prompt("Digite o título da tarefa:");
        if (tituloTarefa) {
            $.ajax({
                url: 'add_cardtarefa.php',
                type: 'POST',
                data: { titulo: tituloTarefa, cor: '#FFFFFF' },
                success: function(response) {
                    console.log("Sucesso:", response); // Para diagnóstico
                    // Adicione aqui o código para atualizar o DOM
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("Erro:", textStatus, errorThrown); // Para diagnóstico
                }
            });
        }
    });
});

    </script>


   
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery.ui.touch-punch-improved.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery-ui.min.js"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <!-- <script src="assets/libs/popper.js/popper.min.js"></script> -->
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
    <!--This page JavaScript -->
    <script src="assets/extra-libs/taskboard/js/lobilist.js"></script>
    <script src="assets/extra-libs/taskboard/js/lobibox.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/task-init.js"></script>
  </body>
</html>

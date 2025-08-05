<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Inclua seu arquivo de conexão com o banco de dados aqui
include 'db.php';

// Verifica se os dados do formulário foram enviados
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recupere os dados do formulário
    $nome_tarefa = filter_input(INPUT_POST, 'nome_tarefa', FILTER_SANITIZE_STRING);
    $descricao_tarefa = filter_input(INPUT_POST, 'descricao_tarefa', FILTER_SANITIZE_STRING);
    $id_usuario = $_SESSION['usuario_id']; // ID do usuário logado

    // Prepare o SQL para inserir a tarefa
    $sql = "INSERT INTO tarefas (nome_tarefa, descricao_tarefa, id_usuario) VALUES (:nome_tarefa, :descricao_tarefa, :id_usuario)";

    try {
        // Prepara a declaração para execução
        $stmt = $pdo->prepare($sql);

        // Vincula os parâmetros
        $stmt->bindParam(':nome_tarefa', $nome_tarefa);
        $stmt->bindParam(':descricao_tarefa', $descricao_tarefa);
        $stmt->bindParam(':id_usuario', $id_usuario);

        // Executa a declaração
        $stmt->execute();

        
    } catch (PDOException $e) {
        // Em caso de erro na inserção, exibe a mensagem
        echo "Erro ao cadastrar a tarefa: " . $e->getMessage();
    }
}



$id_usuario = $_SESSION['usuario_id'];

// Buscar todas as tarefas, pendentes e completas
$sql = "SELECT id, nome_tarefa, descricao_tarefa, is_important, is_complete, data_cadastro FROM tarefas WHERE id_usuario = :id_usuario ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id_usuario', $id_usuario);
$stmt->execute();
$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// A consulta para calcular o total de tarefas concluídas já está correta
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tarefas WHERE id_usuario = :id_usuario AND is_complete = 1");
$stmt->execute([':id_usuario' => $id_usuario]);
$totalTarefasConcluidas = $stmt->fetchColumn();

// Como $tarefas agora contém todas as tarefas, ajuste a lógica para calcular $totalPendentes
$totalPendentes = count(array_filter($tarefas, function ($tarefa) { return !$tarefa['is_complete']; }));

// O cálculo para o total de tarefas importantes e pendentes permanece o mesmo
$totalImportantes = count(array_filter($tarefas, function ($tarefa) { return $tarefa['is_important'] && !$tarefa['is_complete']; }));





?>


<!DOCTYPE html>
<html dir="ltr" lang="pt">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta
      name="keywords"
      content="wrappixel, admin dashboard, html css dashboard, web dashboard, bootstrap 5 admin, bootstrap 5, css3 dashboard, bootstrap 5 dashboard, material pro admin bootstrap 5 dashboard, frontend, responsive bootstrap 5 admin template, material design, material dashboard bootstrap 5 dashboard template"
    />
    <meta
      name="description"
      content="MaterialPro is powerful and clean admin dashboard template, inpired from Google's Material Design"
    />
    <meta name="robots" content="noindex,nofollow" />
    <title>GATI - Gestão Ágil em TI</title>
    <link
      rel="canonical"
      href="https://www.wrappixel.com/templates/materialpro/"
    />
    <!-- Favicon icon -->
    <link
      rel="icon"
      type="image/png"
      sizes="16x16"
      href="assets/images/favicon.png"
    />
    <!-- Custom CSS -->
    <link
      rel="stylesheet"
      type="text/css"
      href="assets/libs/quill/dist/quill.snow.css"
    />
    <!-- Custom CSS -->
    <link href="dist/css/style.min.css" rel="stylesheet" />
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

      <style>
         /* Estilo padrão para todos os badges */
.todo-badge {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 25px; /* Largura fixa para todos os badges */
    height: 25px; /* Altura fixa para todos os badges */
    padding: 0;
    border-radius: 50%; /* Faz com que o badge seja um círculo */
    color: #fff;
    font-size: 0.75rem; /* Tamanho da fonte reduzido para caber no badge */
    font-weight: 500; /* Peso da fonte para visibilidade */
    margin-left: auto; /* Alinha o badge à direita dentro do seu contêiner */
    line-height: 1; /* Ajuda a centralizar o texto verticalmente */
}

/* Estilos específicos para as cores de fundo de cada badge */
.todo-badge.bg-light-info {
    background-color: #5bc0de; /* Azul claro */
}

.todo-badge.bg-light-danger {
    background-color: #d9534f; /* Vermelho claro */
}

.todo-badge.bg-success {
    background-color: #5cb85c; /* Verde */
}


/* Estilos para o modal de detalhes da tarefa */
.task-text {
  font-family: 'Arial', sans-serif; /* Substitua pela fonte do seu sistema */
  font-size: 14px; /* Ajuste o tamanho da fonte conforme necessário */
  color: #333; /* Cor do texto */
  white-space: pre-wrap; /* Mantém a formatação de espaços e quebras de linha */
  margin: 0.5rem 0; /* Adiciona uma margem vertical */
  line-height: 1.5; /* Espaçamento entre linhas */
}

/* Se necessário, ajuste o título da tarefa */
.modal-title.task-heading {
  font-size: 1.25rem; /* Ajuste o tamanho da fonte do título */
  font-weight: 400; /* Se desejar um título em negrito */
}


      </style>

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
      <!-- -------------------------------------------------------------- -->
      <!-- End Topbar header -->
      <!-- -------------------------------------------------------------- -->
      <!-- -------------------------------------------------------------- -->
      <!-- Left Sidebar - style you can find in sidebar.scss  -->
      <!-- -------------------------------------------------------------- -->
     <?php include 'sidebar.php'?> 
      <!-- -------------------------------------------------------------- -->
      <!-- End Left Sidebar - style you can find in sidebar.scss  -->
      <!-- -------------------------------------------------------------- -->
      <!-- -------------------------------------------------------------- -->
      <!-- Page wrapper  -->
      <!-- -------------------------------------------------------------- -->
      <div class="page-wrapper">
        <div class="email-app todo-box-container">
          <!-- -------------------------------------------------------------- -->
          <!-- Left Part -->
          <!-- -------------------------------------------------------------- -->
          <div class="left-part list-of-tasks">
            <a
              class="
                ti-menu ti-close
                btn btn-success
                show-left-part
                d-block d-md-none
              "
              href="javascript:void(0)"
            ></a>
            <div class="scrollable" style="height: 100%">
              <div class="p-3">
                <a
                  class="waves-effect waves-light btn btn-info d-block"
                  href="javascript: void(0)"
                  id="add-task"
                  >Nova Tarefa</a
                >
              </div>              
              <div class="divider"></div>
              <ul class="list-group">
                <li>
                  <small
                    class="
                      p-3
                      d-block
                      text-uppercase text-dark
                      font-weight-medium
                    "
                    >Pastas</small
                  >
                </li>

                <li class="list-group-item p-0 border-0">
                  <a href="javascript:void(0)" class="todo-link active list-group-item-action p-3 d-flex align-items-center" id="all-todo-list">
                    <i data-feather="list" class="feather-sm me-2"></i> Pendentes
                    <span class="todo-badge badge bg-light-info text-info rounded-pill px-3 font-weight-medium ms-auto">14</span>
                  </a>
                </li>

                <li class="list-group-item p-0 border-0">
                <a href="javascript:void(0)" class="todo-link list-group-item-action p-3 d-flex align-items-center" id="current-task-important">
                  <i data-feather="star" class="feather-sm me-2"></i>
                  Importantes
                  <span class="todo-badge badge rounded-pill px-3 bg-light-danger text-danger font-weight-medium ms-auto">0</span>
                </a>
              </li>

                 <li class="list-group-item p-0 border-0">
                  <a href="javascript:void(0)" class="todo-link list-group-item-action p-3 d-flex align-items-center" id="current-task-done">
                    <i data-feather="send" class="feather-sm me-2"></i> Completas
                    <span class="todo-badge badge rounded-pill px-3 text-success font-weight-medium bg-success ms-auto">0
                      
                    </span>
                  </a>
                </li>

              </ul>
            </div>
          </div>
          <!-- -------------------------------------------------------------- -->
          <!-- Right Part -->
          <!-- -------------------------------------------------------------- -->
          <div class="right-part mail-list bg-white overflow-auto">
            <div id="todo-list-container">
              <div class="p-3 border-bottom">
                <div class="input-group searchbar">
                  <span class="input-group-text" id="search"
                    ><i class="icon-magnifier text-muted"></i
                  ></span>
                  <input
  type="text"
  class="form-control"
  placeholder="Pesquise tarefas aqui"
  aria-describedby="search"
  id="task-search"/>


                </div>
              </div>
              <!-- Todo list-->
              <div class="todo-listing">
                <div id="all-todo-container" class="p-3">
                <?php foreach ($tarefas as $tarefa):
    $checked = $tarefa['is_complete'] ? 'checked' : '';
    $style = $tarefa['is_complete'] ? 'text-decoration: line-through;' : '';
    $cursorStyle = 'cursor: pointer;'; // Adicione esta linha
    $isCompleteClass = $tarefa['is_complete'] ? 'complete-todo-list' : 'pending-todo-list';
    $isImportantClass = $tarefa['is_important'] ? 'important-todo-list' : '';
?>
                <div class="todo-item all-todo-list <?php echo $isCompleteClass; ?> <?php echo $isImportantClass; ?> p-3 border-bottom position-relative" style="<?php echo $style . $cursorStyle; ?>" onclick="openTaskModal(<?php echo $tarefa['id']; ?>)"> <!-- Adicione a ação de clique aqui -->



        <div class="inner-item d-flex align-items-start">
            <div class="w-100">
                <div class="checkbox checkbox-info d-flex align-items-start form-check">
                            <input type="checkbox" class="form-check-input flex-shrink-0 me-3 markCompleteButton" id="checkbox<?php echo $tarefa['id']; ?>" data-task-id="<?php echo $tarefa['id']; ?>" <?php echo $checked; ?>/>



                    <label class="form-check-label" for="checkbox<?php echo $tarefa['id']; ?>"></label>
                    <div>
  <div class="content-todo">
    <h5 class="font-weight-medium fs-4 todo-header" data-todo-header="<?php echo htmlspecialchars($tarefa['nome_tarefa']); ?>">
      <?php echo htmlspecialchars($tarefa['nome_tarefa']); ?>
    </h5>
    <div class="todo-subtext text-muted fs-3" data-todosubtext-html="<?php echo htmlspecialchars($tarefa['descricao_tarefa']); ?>" data-todosubtextText='<?php echo json_encode(["ops" => [["insert" => $tarefa['descricao_tarefa'] . "\n"]]]); ?>'>
      <?php echo nl2br(htmlspecialchars($tarefa['descricao_tarefa'])); ?>
    </div>
    <!-- Formatação e exibição da data de cadastro -->
    <span class="todo-time fs-2 text-muted">

      <?php
// Mapeamento dos meses em português
$meses = [
    'January' => 'Jan',
    'February' => 'Fev',
    'March' => 'Mar',
    'April' => 'Abr',
    'May' => 'Mai',
    'June' => 'Jun',
    'July' => 'Jul',
    'August' => 'Ago',
    'September' => 'Set',
    'October' => 'Out',
    'November' => 'Nov',
    'December' => 'Dez'
];

// Formatando a data para o padrão "14 Fev 2024"
$dataCadastro = strtotime($tarefa['data_cadastro']);
$dataFormatada = date('d M Y', $dataCadastro);
$dataFormatada = str_replace(array_keys($meses), array_values($meses), $dataFormatada);
?>
<i style="color: #198754;" class="icon-calender me-1"></i>
<span style="color: #198754;">
    <?php echo $dataFormatada; ?>
</span>


    </span>
  </div>
</div>

                    <div class="ms-auto">
    <!-- Inclui os botões de ação aqui -->
    <div class="dropdown-action">
    <div class="dropdown todo-action-dropdown">
        <button class="btn btn-link text-dark p-1 dropdown-toggle text-decoration-none todo-action-dropdown" type="button" id="more-action-<?php echo $tarefa['id']; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="icon-options-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="more-action-<?php echo $tarefa['id']; ?>">

           <a class="edit dropdown-item" href="javascript:void(0);" onclick="openEditTaskModal(<?php echo $tarefa['id']; ?>, '<?php echo addslashes($tarefa['nome_tarefa']); ?>', <?php echo htmlspecialchars(json_encode($tarefa['descricao_tarefa']), ENT_QUOTES, 'UTF-8'); ?>);">
  <i class="fas fa-edit text-info me-1"></i> Editar
</a>
            <!-- Alternar entre Importante e Não Importante baseado no estado da tarefa -->
            <?php if ($tarefa['is_important']): ?>
                <a class="not-important dropdown-item markNotImportantButton" href="javascript:void(0);" data-task-id="<?php echo $tarefa['id']; ?>"><i class="fas fa-star-half-alt text-warning me-1"></i> Não Importante</a>
            <?php else: ?>
                <a class="important dropdown-item markImportantButton" href="javascript:void(0);" data-task-id="<?php echo $tarefa['id']; ?>"><i class="fas fa-star text-warning me-1"></i> Importante</a>
            <?php endif; ?>
            <a class="remove dropdown-item" href="javascript:void(0);" data-task-id="<?php echo $tarefa['id']; ?>"><i class="far fa-trash-alt text-danger me-1"></i> Remover</a>
        </div>
    </div>
</div>

</div>

                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?> 


                  
                  
                </div>
                <!-- Modal -->
                <div
                  class="modal fade"
                  id="todoShowListItem"
                  tabindex="-1"
                  role="dialog"
                  aria-hidden="true"
                >
                  <div
                    class="modal-dialog modal-dialog-centered"
                    role="document"
                  >
                    <div class="modal-content border-0">
                      <div class="modal-header bg-light d-flex">
                        <h5 class="modal-title task-heading"></h5>
                        <button
                          type="button"
                          class="btn-close"
                          data-bs-dismiss="modal"
                          aria-label="Close"
                        ></button>
                      </div>
                      <div class="modal-body">
                        <div class="compose-box">
                          <div class="compose-content">
                            <label class="mb-0 fs-4" style="font-weight: bold; margin-bottom: 1rem;">Detalhe da Tarefa</label>
                            <!-- Use a tag <pre> para manter a formatação do texto incluindo quebras de linha -->
                            <pre class="task-text text-muted" style="white-space: pre-wrap;"></pre>
                          </div>

                        </div>
                      </div>
                      <div class="modal-footer">
                        <button
                          class="btn btn-danger btn-block"
                          data-bs-dismiss="modal"
                        >
                          Fechar
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Modal -->
          <div
            class="modal fade"
            id="addTaskModal"
            tabindex="-1"
            role="dialog"
            aria-labelledby="addTaskModalTitle"
            aria-hidden="true"
          >
            <div class="modal-dialog modal-dialog-centered" role="document">
              <div class="modal-content border-0">
                <div class="modal-header bg-info text-white">
                  <h5 class="modal-title text-white">Nova Tarefa</h5>
                  <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                  ></button>
                </div>
                <form method="post" action="minhastarefas.php">
  <div class="modal-body">
    <div class="compose-box">
      <div class="compose-content" id="addTaskModalTitle">
          <div class="row">
            <div class="col-md-12">
              <div class="d-flex mail-to mb-4">
                <div class="w-100">
                  <input
                    id="nome_tarefa"
                    type="text"
                    placeholder="Escreva a Tarefa"
                    class="form-control"
                    name="nome_tarefa"
                    required
                  />
                  <span class="validation-text"></span>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex mail-subject mb-4">
            <div class="w-100">
              <textarea
                id="descricao_tarefa"
                class="form-control"
                name="descricao_tarefa"
                placeholder="Descrição da Tarefa" style="height:280px"
                required
              ></textarea>
              <span class="validation-text"></span>
            </div>
          </div>
      </div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-danger" data-bs-dismiss="modal">
      <i class="flaticon-cancel-12"></i> Cancelar
    </button>
    <button type="submit" class="btn btn-info" name="action" value="add">
  Adicionar Tarefa
</button>

  </div>
</form>            
              </div>
            </div>
          </div>

            <!-- Modal Editar Tarefa -->
<div class="modal fade" id="editTaskModal" tabindex="-1" role="dialog" aria-labelledby="editTaskModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title text-white">Editar Tarefa</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
  <form method="post" action="processa_edit_tarefa.php">
        <div class="modal-body">
          <div class="compose-box">
            <div class="compose-content" id="editTaskModalTitle">
              <input type="hidden" id="edit_task_id" name="task_id" value=""> <!-- Input oculto para armazenar o ID da tarefa -->
              <div class="row">
                <div class="col-md-12">
                  <div class="d-flex mail-to mb-4">
                    <div class="w-100">
                      <textarea id="edit_nome_tarefa" type="text" placeholder="Escreva a Tarefa" class="form-control" name="nome_tarefa" required /> </textarea>
                      <span class="validation-text"></span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="d-flex mail-subject mb-4">
                <div class="w-100">
                  <textarea id="edit_descricao_tarefa" class="form-control" name="descricao_tarefa" placeholder="Descrição da Tarefa" required></textarea>
                  <span class="validation-text"></span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-danger" data-bs-dismiss="modal">
            <i class="flaticon-cancel-12"></i> Cancelar
          </button>
           <button type="submit" class="btn btn-info" name="action" value="edit">
            Salvar Alterações
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


          <!-- -------------------------------------------------------------- -->
          <!-- footer -->
          <!-- -------------------------------------------------------------- -->
          <?php include 'footer.php'?>    
          <!-- -------------------------------------------------------------- -->
          <!-- End footer -->
          <!-- -------------------------------------------------------------- -->
          <!-- -------------------------------------------------------------- -->
          <!-- End Page wrapper  -->
          <!-- -------------------------------------------------------------- -->
        </div>
      </div>
    </div>
    <!-- -------------------------------------------------------------- -->
    <!-- End Wrapper -->
    <!-- -------------------------------------------------------------- -->
    <!-- -------------------------------------------------------------- -->
    <!-- customizer Panel -->
    <!-- -------------------------------------------------------------- -->
    
    <div class="chat-windows"></div>
    <!-- -------------------------------------------------------------- -->
    <!-- All Jquery -->
    <!-- -------------------------------------------------------------- -->

    <script>
document.addEventListener('DOMContentLoaded', function() {

// Adiciona o evento de clique no filtro de tarefas pendentes
    document.getElementById('all-todo-list').addEventListener('click', function() {
        filterTasks('pending-todo-list');
    });

    // Adiciona o evento de clique no filtro de tarefas completas
    document.getElementById('current-task-done').addEventListener('click', function() {
        filterTasks('complete-todo-list');
    });

    // Adiciona o evento de clique no filtro de tarefas importantes
    document.getElementById('current-task-important').addEventListener('click', function() {
        filterTasks('important-todo-list');
    });

    function filterTasks(filterClass) {
        // Esconde todas as tarefas
        document.querySelectorAll('.todo-item').forEach(function(task) {
            task.style.display = 'none';
        });

        // Mostra apenas as tarefas com a classe correspondente ao filtro selecionado
        document.querySelectorAll('.' + filterClass).forEach(function(task) {
            task.style.display = '';
        });
    }

    // Atualiza o status de uma tarefa para completa ou não
    document.querySelectorAll('.todo-item .markCompleteButton').forEach(button => {
        button.addEventListener('change', function() {
            const taskId = this.getAttribute('data-task-id');
            const newState = this.checked ? 'true' : 'false';
            updateTaskStatus(taskId, 'mark_complete', newState);
        });
    });

    // Manipula cliques nos botões de marcar como importante/não importante
    document.querySelectorAll('.dropdown-menu .markImportantButton, .dropdown-menu .markNotImportantButton').forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            const action = this.classList.contains('markNotImportantButton') ? 'mark_not_important' : 'mark_important';
            updateTaskStatus(taskId, action, action === 'mark_important' ? 'true' : 'false');
        });
    });
        

    // Função para atualizar a contagem de tarefas em cada categoria
    function updateTaskCounters() {
        const totalPendentesElement = document.querySelector('#all-todo-list .todo-badge');
        const totalImportantesElement = document.querySelector('#current-task-important .todo-badge');
        const totalCompletasElement = document.querySelector('#current-task-done .todo-badge');

        const allTasks = document.querySelectorAll('.todo-item');
        const pendingTasks = document.querySelectorAll('.todo-item:not(.complete-todo-list)');
        const importantTasks = document.querySelectorAll('.important-todo-list:not(.complete-todo-list)');
        const completeTasks = document.querySelectorAll('.complete-todo-list');

        totalPendentesElement.textContent = pendingTasks.length;
        totalImportantesElement.textContent = importantTasks.length;
        totalCompletasElement.textContent = completeTasks.length;
    }

    // Função genérica para filtrar tarefas
    function filterTasks(filterClass) {
        const allTasks = document.querySelectorAll('.todo-item');
        allTasks.forEach(task => task.style.display = 'none');
        document.querySelectorAll('.' + filterClass).forEach(task => task.style.display = '');
        updateTaskCounters(); // Atualiza os contadores
    }

    // Adiciona o evento de clique nas abas para filtrar tarefas
    document.getElementById('all-todo-list').addEventListener('click', function() {
        filterTasks('pending-todo-list'); // Mostrar apenas as pendentes
    });

    document.getElementById('current-task-important').addEventListener('click', function() {
        filterTasks('important-todo-list'); // Mostrar apenas as importantes
    });

    document.getElementById('current-task-done').addEventListener('click', function() {
        filterTasks('complete-todo-list'); // Mostrar apenas as completas
    });

    // Chama a função de atualizar contadores ao carregar a página
    updateTaskCounters();
        
    
    // Função para mostrar apenas as tarefas completas
    function showCompleteTasks() {
        const allTasks = document.querySelectorAll('.todo-item');
        allTasks.forEach(task => task.style.display = 'none'); // Esconde todas as tarefas
        const completeTasks = document.querySelectorAll('.complete-todo-list');
        completeTasks.forEach(task => task.style.display = ''); // Mostra apenas as completas
        
        // Atualiza o contador de tarefas completas
        totalCompletasElement.textContent = completeTasks.length;
    }    

    // Função para mostrar apenas as tarefas importantes
    function showImportantTasks() {
        const allTasks = document.querySelectorAll('.todo-item');
        allTasks.forEach(task => task.style.display = 'none'); // Esconde todas as tarefas
        const importantTasks = document.querySelectorAll('.important-todo-list');
        importantTasks.forEach(task => task.style.display = ''); // Mostra apenas as importantes
    }

    // Atualiza o contador de tarefas completas
    function updateTaskCounter() {
        const completeTasks = document.querySelectorAll('.complete-todo-list');
        totalCompletasElement.textContent = completeTasks.length;
        // Armazena o total no localStorage para persistência após o refresh
        localStorage.setItem('totalTarefasConcluidas', completeTasks.length);
    }


    // Restaura o contador de tarefas importantes após o refresh
    function restoreImportantTaskCounter() {
        const storedCount = localStorage.getItem('totalTarefasImportantes');
        totalImportantesElement.textContent = storedCount || '0';
    }




        // Atualiza o contador de tarefas importantes
    function updateImportantTaskCounter() {
        const importantTasks = document.querySelectorAll('.important-todo-list');
        const totalImportant = importantTasks ? importantTasks.length : 0;
        totalImportantesElement.textContent = totalImportant;
        localStorage.setItem('totalTarefasImportantes', totalImportant);
    }

    // Chama a função logo após definí-la para inicializar o contador corretamente.
    updateImportantTaskCounter();

     

    // Restaura o contador de tarefas completas após o refresh
    function restoreCompleteTaskCounter() {
        const storedCount = localStorage.getItem('totalTarefasConcluidas');
        if (storedCount) {
            totalCompletasElement.textContent = storedCount;
        } else {
            // Se não houver nada no localStorage, calcula novamente
            updateTaskCounter();
        }
    }

    // Adiciona o evento de clique nas abas para mostrar as tarefas
    document.getElementById('current-task-done').addEventListener('click', showCompleteTasks);
    document.getElementById('current-task-important').addEventListener('click', showImportantTasks);

    // Função para alternar o estado de importante da tarefa
    function toggleImportant(taskId, isCurrentlyImportant) {
        // Alternar o estado de importante
        const isImportant = !isCurrentlyImportant;
        const button = document.querySelector(`[data-task-id="${taskId}"]`);
        if (button) {
            if (isImportant) {
                button.classList.remove('markNotImportantButton');
                button.classList.add('markImportantButton');
                button.innerHTML = '<i class="fas fa-star text-warning me-1"></i> Importante';
            } else {
                button.classList.remove('markImportantButton');
                button.classList.add('markNotImportantButton');
                button.innerHTML = '<i class="fas fa-star-half-alt text-warning me-1"></i> Não Importante';
            }
        }
        updateTaskStatus(taskId, 'mark_important', isImportant);
    }   


    // Adiciona o evento de clique nas abas para mostrar as tarefas
    document.getElementById('current-task-done').addEventListener('click', showCompleteTasks);
    document.getElementById('current-task-important').addEventListener('click', showImportantTasks);

    // Eventos para marcar tarefas como completas, incompletas ou importantes
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.addEventListener('click', function(event) {
            let target = event.target;
            if (target.classList.contains('markImportantButton') || target.classList.contains('markNotImportantButton')) {
                const taskId = target.getAttribute('data-task-id');
                // Determina o novo estado baseado na classe do botão
                const newState = target.classList.contains('markImportantButton');
                updateTaskStatus(taskId, 'mark_important', newState);
            }
        });
    });

    // Atualiza o status da tarefa no servidor
    function updateTaskStatus(taskId, action, value) {
        let formData = new FormData();
        formData.append('action', action);
        formData.append('tarefa_id', taskId);
        formData.append('value', value);

        fetch('processa_tarefa.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log(data.message); // Log para desenvolvimento, pode ser removido
            window.location.reload(); // Refresh na página para refletir as alterações
        })
        .catch(error => {
            console.error('Erro:', error);
        });
    }

    // Atualiza a interface do usuário quando uma tarefa é marcada ou desmarcada
    function updateUI(taskId, newState, action) {
        const item = document.querySelector(`[data-task-id="${taskId}"]`).closest('.todo-item');
        switch (action) {
            case 'mark_complete':
                if (newState) {
                    item.classList.add('complete-todo-list');
                    item.style.textDecoration = 'line-through';
                } else {
                    item.classList.remove('complete-todo-list');
                    item.style.textDecoration = 'none';
                }
                break;
            case 'mark_important':
                item.classList.add('important-todo-list');
                
            case 'mark_not_important':
                item.classList.remove('important-todo-list');
                
        }
    }

    // Restaura os contadores ao carregar a página
    restoreCompleteTaskCounter();
    restoreImportantTaskCounter();
});

</script>

<script>
function autoAdjustTextarea(textareaId) {
  var textarea = document.getElementById(textareaId);
  
  function adjustHeight() {
    textarea.style.height = 'auto'; // Reseta a altura para obter o scrollHeight correto
    textarea.style.height = textarea.scrollHeight + 'px'; // Ajusta a altura para o conteúdo
  }

  // Ajusta a altura inicialmente
  adjustHeight();

  // Ajusta a altura sempre que o conteúdo muda
  textarea.addEventListener('input', adjustHeight);
}

// Abre o modal e ajusta os campos
function openEditTaskModal(taskId, taskName, taskDescription) {
  document.getElementById('edit_task_id').value = taskId;
  document.getElementById('edit_nome_tarefa').value = taskName;
  document.getElementById('edit_descricao_tarefa').value = taskDescription;

  // Aguarda o modal estar visível para ajustar os campos
  $('#editTaskModal').on('shown.bs.modal', function () {
    autoAdjustTextarea('edit_descricao_tarefa');
  });

  // Abre o modal
  $('#editTaskModal').modal('show');
}

// Inicializa os campos assim que a página carrega
document.addEventListener('DOMContentLoaded', function () {
  autoAdjustTextarea('edit_descricao_tarefa');
});

</script>

<script> 
// Evento de clique para o botão de remoção
document.querySelectorAll('.remove').forEach(button => {
    button.addEventListener('click', function() {
        const taskId = this.getAttribute('data-task-id');
        if (taskId && confirm('Tem certeza que deseja remover esta tarefa?')) {
            removeTask(taskId);
        }
    });
});

// Função para remover tarefa
function removeTask(taskId) {
    let formData = new FormData();
    formData.append('action', 'remove');
    formData.append('tarefa_id', taskId);

    fetch('removertarefa.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message); // Mensagem de sucesso ou erro
        // Aguarda um breve momento antes de recarregar a página
        setTimeout(function() {
            window.location.reload();
        }, 10); // Ajuste o delay conforme necessário
    })
    .catch(error => {
        console.error('Erro:', error);
        // Em caso de erro na requisição, também oferece a opção de recarregar
        setTimeout(function() {
            window.location.reload();
        }, 10); // Ajuste o delay conforme necessário
    });
}
</script>



<script> 

function showTaskDetails(taskName, taskText) {
  // Define o título da tarefa
  document.querySelector('.modal-title.task-heading').textContent = taskName;

  // Define o texto da tarefa e converte quebras de linha em <br>
  document.querySelector('.modal-body .task-text').innerHTML = nl2br(htmlspecialchars(taskText));

  // Abre o modal
  $('#todoShowListItem').modal('show');
}

// Esta função converte quebras de linha em <br>
function nl2br(str, is_xhtml) {
  var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
  return (str + '').replace(/(\r\n|\n\r|\r|\n)/g, breakTag + '$1');
}

// Esta função converte caracteres especiais em entidades HTML
function htmlspecialchars(text) {
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}


</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('task-search');

  searchInput.addEventListener('input', function() {
    const searchText = this.value;
    const formData = new URLSearchParams();
    formData.append('search', searchText);

    if (searchText.length >= 3 || searchText.length === 0) {
      fetch('search_tarefas.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString()
      })
      .then(response => response.json())
      .then(data => {
        if (data.error) {
          console.error(data.error);
        } else {
          updateTasksList(data);
        }
      })
      .catch(error => {
        console.error('Erro:', error);
      });
    }
  });
});

function updateTasksList(tasks) {
  const tasksContainer = document.getElementById('all-todo-container');
  tasksContainer.innerHTML = '';

  tasks.forEach(task => {
    const taskElement = document.createElement('div');
    taskElement.className = `todo-item all-todo-list ${task.is_complete ? 'complete-todo-list' : 'pending-todo-list'} ${task.is_important ? 'important-todo-list' : ''} p-3 border-bottom`;
    taskElement.setAttribute('style', task.is_complete ? 'text-decoration: line-through;' : '');
    taskElement.innerHTML = `
      <div class="inner-item d-flex align-items-start">
        <div class="w-100">
          <div class="checkbox checkbox-info d-flex align-items-start form-check">
            <input type="checkbox" class="form-check-input flex-shrink-0 me-3 markCompleteButton" id="checkbox${task.id}" data-task-id="${task.id}" ${task.is_complete ? 'checked' : ''}>
            <label class="form-check-label" for="checkbox${task.id}"></label>
          </div>
          <div>
            <div class="content-todo">
              <h5 class="font-weight-medium fs-4 todo-header">${task.nome_tarefa}</h5>
              <div class="todo-subtext text-muted fs-3">${task.descricao_tarefa}</div>
            </div>
          </div>
        </div>
      </div>
    `;
    tasksContainer.appendChild(taskElement);
  });
}

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
    <script src="assets/libs/quill/dist/quill.min.js"></script>
    <!-- Initialize Quill editor -->
    <script src="dist/js/pages/todo/todo.js"></script>
  </body>
</html>

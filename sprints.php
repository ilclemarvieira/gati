<?php
// Definindo o tempo máximo da sessão para 6 horas (21600 segundos)
ini_set('session.gc_maxlifetime', 21600);

session_start();

// Lembre-se de configurar o cookie de sessão para ter a mesma duração
$params = session_get_cookie_params();
setcookie(session_name(), $_COOKIE[session_name()], time() + 21600,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
);
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}
// Incluir db.php para conexão com o banco de dados
include 'db.php';

$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        // Redireciona para a página anterior ou para uma página padrão
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'index.php'; // Define 'index.php' como fallback
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}

// No início de cada página restrita, chame verificarPermissao com os perfis permitidos
$perfisPermitidos = [1, 2, 3, 4, 9]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);

// Consulta para buscar todos os usuários
$usuarios = $pdo->query("SELECT * FROM usuarios")->fetchAll();

function formatarCPF($cpf) {
    $cpf = preg_replace("/\D/", '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' .
               substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

?>
<!DOCTYPE html>
<html dir="ltr" lang="pt">

<head>
    <!-- Cabeçalho original -->
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- Responsividade -->
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="keywords" content="" />
    <meta name="description" content="GATI - Gestão Ágil em TI" />
    <meta name="robots" content="noindex,nofollow" />
    <title>GATI - Gestão Ágil em TI</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="16x16"
          href="assets/images/favicon.png" />
    <!-- Bootstrap CSS -->
    <link href="assets/libs/bootstrap/dist/css/bootstrap.min.css"
          rel="stylesheet" />
    <!-- Custom CSS -->
    <link rel="stylesheet"
          href="assets/extra-libs/taskboard/css/lobilist.css" />
    <link rel="stylesheet"
          href="assets/extra-libs/taskboard/css/jquery-ui.min.css" />
    <link rel="stylesheet" type="text/css"
          href="assets/libs/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css" />
    <!-- Custom CSS -->
    <link href="dist/css/style.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
          integrity="sha512-..."
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
    <!-- Estilos personalizados -->
    <style>
/* Estilos personalizados */
body {
    background-color: #1f1f1f;
    color: #eaeaea;
    font-family: 'Roboto', sans-serif;
}

/* Container Principal */
#sprints-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    align-items: flex-start;
    gap: 20px;
}

/* Estilização dos Cards */
.sprint-column {
    background-color: #323743;
    color: #eaeaea;
    padding: 0; /* Remove padding extra que possa causar desalinhamento */
    overflow: hidden; /* Garante que nenhum elemento ultrapasse as bordas do card */
    padding-top: 0px;
    border-radius: 8px;
    box-sizing: border-box;
    box-shadow: 0 2px 4px rgba(10, 14, 9, 0.49);
    display: flex;
    flex-direction: column;
    transition: background-color 0.3s;
    flex: 0 0 calc(50% - 10px); /* 2 cards por linha */
}


/* Ajuste para 1 card em telas pequenas (celular) */
@media (max-width: 768px) {
    .sprint-column {
        flex: 0 0 100%; /* 1 card por linha em telas pequenas */
    }

    #sprints-container {
        justify-content: center; /* Centraliza os cards em telas pequenas */
    }
}

.sprint-column:hover {
    background-color: var(--card-bg); /* Substitua var(--card-bg) pela cor desejada */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
}

/* Removendo as bordas superiores */
.sprint-column.prazo {
    border-left: 6px solid #2d5f95; /* Borda azul para sprints em andamento */
    background-color: #323743; /* Mantenha a cor de fundo */
}

.sprint-column.vencido {
    border-left: 6px solid #fc4b6c; /* Borda vermelha para sprints encerradas */
    background-color: #323743; /* Mantenha a cor de fundo */
}

/* Ajuste para a largura da sprint-header ocupar 100% do card */
.sprint-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%; /* Garante que ocupe toda a largura disponível */
    box-sizing: border-box; /* Inclui o padding no cálculo da largura */
    padding: 0 20px; /* Ajuste o padding conforme necessário */
    margin: 0; /* Remove margens adicionais */
    height: 43px; 
}

/* Estilos específicos para o status */
.sprint-column.prazo .sprint-header {
    background-color: #2d5f95; /* Fundo azul para sprints em andamento */
}

.sprint-column.vencido .sprint-header {
    background-color: #fc4b6c; /* Fundo vermelho para sprints encerradas */
}

.sprint-status {
    color: #ccc;
    font-size: 0.9rem;
    font-weight: bold;
    margin: 0;
}

.card-buttons {
    display: flex;
    gap: 5px;
    align-items: center;
}

.btn-edit,
.btn-delete {
    border: none;
    background: none;
    color: #eaeaea;
    cursor: pointer;
    font-size: 1.2em;
    transition: color 0.3s;
}

.btn-edit, .btn-delete {
    border: none;
    background: none;
    color: #eaeaea;
    cursor: pointer;
    font-size: 1.2em;
    transition: color 0.3s;
}

.btn-edit:hover, .btn-delete:hover {
    color: #6ab04c; /* Cor de destaque no hover */
}

/* Estilo para o título da sprint e as informações de datas */
.sprint-title-container {
    display: flex;
    flex-direction: column;
    align-items: flex-start; /* Alinha os elementos à esquerda */
    margin-top: 15px; /* Espaçamento superior */
    padding-left: 10px; /* Adiciona espaçamento à esquerda para separar da borda */
}

/* Estilo do título da sprint */
.sprint-title {
    padding: 20px;
    font-size: 27px;
    color: #9fb497; /* Cor de destaque para o título */
    margin-bottom: -20px; /* Reduzido para um visual mais compacto */
    text-align: left; /* Alinha o texto à esquerda */
    font-weight: bold; /* Deixa o título em negrito para destaque */
}

/* Estilo das datas da sprint */
.sprint-dates {
    padding: 20px;
    margin-bottom: -20px; /* Espaço inferior menor para um visual compacto */
    font-size: 0.9em;
    color: #cccccc; /* Cor de texto secundária */
    line-height: 1.4; /* Melhora a legibilidade das linhas de texto */
}

/* Estilo de cada linha das datas */
.sprint-dates div {
    display: flex;
    align-items: center;
    margin-bottom: 4px; /* Ajusta o espaçamento entre as linhas de data */
}

/* Estilo dos ícones das datas */
.sprint-dates i {
    margin-right: 6px; /* Ajusta o espaço entre o ícone e o texto */
    color: #9fb497; /* Mantém a cor de destaque */
}



.sprint-items {
    list-style: none;
    padding: 20px;
    min-height: 50px;
    flex-grow: 1;
    margin-top: 10px; /* Espaço entre as subtarefas e o elemento acima */
    margin-bottom: 15px; /* Espaço entre as subtarefas e o elemento abaixo */
}

.sprint-items li {
    background-color: #fefefe1a;
    margin: 5px 0;
    padding: 10px;
    border-radius: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #eaeaea;
    word-wrap: break-word;
    transition: background-color 0.3s; /* Transição suave para o hover */
}

.sprint-items li:hover {
    background-color: #fefefe33; /* Escurece sutilmente o fundo no hover */
}

        .input-group {
            list-style: none;
            display: flex;
    flex-direction: column;
    gap: 10px; /* Espaçamento entre o select e o botão */
    padding: 20px;
    min-height: 50px;
    flex-grow: 1;
    margin-top: -20px; /* Espaço entre as subtarefas e o elemento acima */
    margin-bottom: 0px; /* Espaço entre as subtarefas e o elemento abaixo */
        }

        .input-group > .form-control,
        .input-group > .form-select {
            width: 100%;
        }

        .input-group select,
        .input-group textarea,
        .input-group input {
            width: 100%;
            padding: 8px;
            color: #eaeaea;
            background-color: #2b2b2b;
            border: 1px solid #555;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .input-group select:focus,
        .input-group textarea:focus,
        .input-group input:focus {
            outline: none;
            border-color: #6ab04c;
        }

        .input-group textarea {
            resize: vertical;
            min-height: 60px;
            max-height: 150px;
        }

        


        #sprints-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            align-items: flex-start;
        }

        .btn-add-subtask {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #4a90e2, #007aff); /* Gradiente de azul moderno */
    border: none;
    color: #ffffff;
    font-weight: bold;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.3s, transform 0.2s;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Sombra para destacar o botão */
}

.btn-add-subtask i {
    margin-right: 8px; /* Espaçamento entre o ícone e o texto */
    font-size: 1.2em; /* Tamanho do ícone */
}

.btn-add-subtask:hover {
    background: linear-gradient(135deg, #007aff, #4a90e2); /* Inverte o gradiente no hover */
    transform: translateY(-2px); /* Eleva o botão ao passar o mouse */
    box-shadow: 0 6px 10px rgba(0, 0, 0, 0.3); /* Aumenta a sombra no hover */
}

        .btn-action {
            border: none;
            background: none;
            color: #eaeaea;
            cursor: pointer;
            font-size: 1em;
        }

        .btn-action:hover {
            color: #6ab04c;
        }

        .sub-task-content {
            flex-grow: 1;
            word-wrap: break-word;
            margin-right: 10px;
        }

        /* Estilos para modais */
        .modal-content {
            background-color: #2e2e2e;
            color: #eaeaea;
            border: none;
        }

        .modal-header {
            background-color: #1f1f1f;
            color: #eaeaea;
            border-bottom: 1px solid #555;
        }

        .modal-title {
            margin: 0;
        }

        .modal-body input,
        .modal-body textarea {
            background-color: #2b2b2b;
            color: #eaeaea;
            border: 1px solid #555;
            width: 100%;
        }

        .modal-body input:focus,
        .modal-body textarea:focus {
            outline: none;
            border-color: #6ab04c;
        }

        .modal-footer {
            border-top: 1px solid #555;
        }

        .btn-primary {
            background-color: #6ab04c;
            border-color: #6ab04c;
            color: #ffffff;
            font-weight: bold;
        }

        .btn-primary:hover {
            background-color: #58b947;
            border-color: #58b947;
        }

        .btn-secondary {
            background-color: #555;
            border-color: #555;
            color: #eaeaea;
        }

        .btn-secondary:hover {
            background-color: #666;
            border-color: #666;
        }

        .os-number {
    color: var(--accent-color); /* Cor de destaque */
    font-size: 1.1em; 
}

.sub-task-content {
    cursor: pointer; /* Muda o cursor para uma mãozinha */
}



        /* Ajustes responsivos */
        @media (max-width: 768px) {
            .sprint-column {
                width: 100%;
                max-width: none;
            }
        }
    </style>
</head>

<body>
    <!-- Preloader e Header -->
    <div class="preloader">
        <!-- ... -->
    </div>
    <div id="main-wrapper">
        <!-- Topbar e Sidebar -->
        <header class="topbar">
            <?php include 'header.php' ?>
        </header>
        <?php include 'sidebar.php' ?>
        <!-- Conteúdo da página -->
        <div class="page-wrapper">
            <div class="row page-titles">
                <div class="col-md-5 col-12 align-self-center">
                    <h3 class="text-themecolor mb-0">
                        <i class="mdi mdi-timetable"></i> Sprints
                    </h3>
                </div>
            </div>
            <!-- Container -->
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="card"
                             style="background-color: #2e2e2e; color: #eaeaea;">
                            <div class="card-body">
   <div class="d-flex justify-content-between align-items-center mb-3"> 
        <div> <button type="button" class="btn waves-effect waves-light btn-info me-2" data-bs-toggle="modal" data-bs-target="#cadastrarSprintModal">
                <i class="ti-plus text"></i> Cadastrar Sprint
            </button>
            <a href="https://docs.google.com/spreadsheets/d/1qdMaKSncoqfQrMM8xd7Bkjk_CBq8S_uHa-jyjYwBJV0/edit?gid=0#gid=0" target="_blank" class="btn btn-success">
                Planilha egSYS
            </a>
        </div>

        <div class="form-group">
            <select class="form-control" id="anoFiltro">
                <?php
                $anoAtual = date('Y');
                for ($ano = $anoAtual; $ano >= 2020; $ano--) { 
                    $selected = ($ano == $anoAtual) ? 'selected' : '';
                    echo "<option value='$ano' $selected>$ano</option>";
                }
                ?>
            </select>
        </div>
    </div>

  <br><br>

    <div class="modal fade" id="cadastrarSprintModal" tabindex="-1" aria-labelledby="cadastrarSprintModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="cadastrarSprintForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="cadastrarSprintModalLabel">Cadastrar Nova Sprint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">   

                        <label for="novoTitulo"   
 class="form-label">Título da Sprint</label>
                        <input type="text" class="form-control" id="novoTitulo" name="titulo" required>
                    </div>
                    <div class="mb-3">
                        <label for="novaDataInicio" class="form-label">Data Inicial da Sprint</label>
                        <input type="date" class="form-control" id="novaDataInicio" name="data_inicio" required>
                    </div>
                    <div class="mb-3">
                        <label for="novaDataFim" class="form-label">Data Final da Sprint</label>
                        <input type="date" class="form-control" id="novaDataFim" name="data_fim" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>   

                    <button type="submit" class="btn btn-primary">Cadastrar</button>
                </div>
            </form>
        </div>
    </div>
    </div>
                                <!-- Local onde as sprints serão listadas -->
                                <div id="sprints-container">
                                    <!-- As sprints serão adicionadas aqui pelo JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <!-- Modais -->
        <!-- Modal de Edição de Sprint -->
        <div class="modal fade" id="editSprintModal" tabindex="-1"
             aria-labelledby="editSprintModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="editSprintForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editSprintModalLabel">
                                Editar Sprint
                            </h5>
                            <button type="button" class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="editSprintId"
                                   name="sprint_id">
                            <div class="mb-3">
                                <label for="editTitulo" class="form-label">
                                    Título da Sprint
                                </label>
                                <input type="text" class="form-control"
                                       id="editTitulo" name="titulo"
                                       required>
                            </div>
                            <div class="mb-3">
                                <label for="editDataInicio"
                                       class="form-label">
                                    Data Inicial da Sprint
                                </label>
                                <input type="date" class="form-control"
                                       id="editDataInicio" name="data_inicio"
                                       required>
                            </div>
                            <div class="mb-3">
                                <label for="editDataFim" class="form-label">
                                    Data Final da Sprint
                                </label>
                                <input type="date" class="form-control"
                                       id="editDataFim" name="data_fim"
                                       required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal de Edição de Subtarefa -->
<div class="modal fade" id="editSubTaskModal" tabindex="-1"
     aria-labelledby="editSubTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editSubTaskForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSubTaskModalLabel">
                        Editar Subtarefa
                    </h5>
                    <button type="button" class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editSubTaskId"
                           name="subtask_id">
                    <div class="mb-3">
                        <label for="editSubTaskNome" class="form-label">
                            OS Selecionada
                        </label>
                        <input type="text" class="form-control"
                               id="editSubTaskNome" name="nome"
                               readonly>
                    </div>
                    <div class="mb-3">
                        <label for="availableOsList" class="form-label">
                            Alterar para outra OS
                        </label>
                        <select class="form-select" id="availableOsList" name="availableOs">
                            <option value="">Selecione uma OS para alterar</option>
                            <!-- As opções serão preenchidas pelo JavaScript -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



        <!-- Footer -->
        <?php include 'footer.php' ?>
    </div>
    </div>
    <!-- JavaScript -->
    <!-- Scripts JavaScript -->
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery.ui.touch-punch-improved.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery-ui.min.js"></script>
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="dist/js/app.min.js"></script>
    <script src="dist/js/app.init.dark.js"></script>
    <script src="dist/js/app-style-switcher.js"></script>
    <script src="assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js"></script>
    <script src="assets/extra-libs/sparkline/sparkline.js"></script>
    <script src="dist/js/waves.js"></script>
    <script src="dist/js/sidebarmenu.js"></script>
    <script src="dist/js/feather.min.js"></script>
    <script src="dist/js/custom.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/lobilist.js"></script>
    <script src="assets/extra-libs/taskboard/js/lobibox.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/task-init.js"></script>
    <!-- Seu script JavaScript -->
    <script>
    let availableOSes = [];
    let editSprintModal;
    let editSubTaskModal;

    document.addEventListener('DOMContentLoaded', function () {
        fetchAvailableOSes()
            .then(() => {
                const anoAtual = new Date().getFullYear();
                fetchSprints(anoAtual); 
            });

        // Adicionar evento de mudança ao filtro de anos
        document.getElementById('anoFiltro').addEventListener('change', function() {
            const anoSelecionado = this.value;
            fetchSprints(anoSelecionado); 
        });

        // Inicializar o modal de cadastro
        const cadastrarSprintModal = new bootstrap.Modal(document.getElementById('cadastrarSprintModal'));

        // Manipular envio do formulário de cadastro de sprint
        document.getElementById('cadastrarSprintForm').addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            fetch('cadastrar_sprint.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addSprintToList(data.data);
                    cadastrarSprintModal.hide(); // Esconder o modal após o cadastro
                    this.reset(); // Limpar o formulário
                    markAgedOSes(); // Atualizar marcações após adicionar uma sprint
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Erro ao cadastrar a sprint:', error));
        });

        // Inicializar modais
        editSprintModal = new bootstrap.Modal(document.getElementById('editSprintModal'));
        editSubTaskModal = new bootstrap.Modal(document.getElementById('editSubTaskModal'));

        // Manipular envio do formulário de edição de sprint
        document.getElementById('editSprintForm').addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(this);
            fetch('edit_sprint.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(updateData => {
                    if (updateData.success) {
                        // Atualizar a sprint na interface
                        const sprintId = formData.get('sprint_id');
                        const sprintElement = document.querySelector(`.sprint-column[data-sprint-id="${sprintId}"]`);
                        if (sprintElement) {
                            // Atualizar o título
                            sprintElement.querySelector('.sprint-title').textContent = formData.get('titulo');
                            // Atualizar as datas
                            const sprintDatesElement = sprintElement.querySelector('.sprint-dates');
                            sprintDatesElement.innerHTML = generateSprintDatesHTML(formData.get('data_inicio'), formData.get('data_fim'));
                        }
                        editSprintModal.hide();
                        markAgedOSes(); // Atualizar marcações após editar uma sprint
                    } else {
                        alert('Erro ao editar sprint: ' + updateData.message);
                    }
                })
                .catch(error => {
                    console.error('Erro ao editar sprint:', error);
                    alert('Erro ao editar sprint: ' + error.message);
                });
        });

        // Manipular envio do formulário de edição de subtarefa
        document.getElementById('editSubTaskForm').addEventListener('submit', function (event) {
            event.preventDefault();

            const subtaskId = document.getElementById('editSubTaskId').value; // ID da subtarefa (campo hidden)
            const selectedOsId = document.getElementById('availableOsList').value; // ID da nova OS selecionada

            fetch('edit_sub_tarefa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    id: subtaskId, // Enviando o ID correto do item
                    nome: selectedOsId, // Enviando o ID da nova OS selecionada
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Subtarefa editada com sucesso!');

                    // Atualizar o DOM dinamicamente com as novas informações
                    const subtaskElement = document.querySelector(`[data-subtask-id="${subtaskId}"]`); // Selecionar o elemento correto pelo ID da subtarefa
                    if (subtaskElement) {
                        // Atualizar o texto ou outras propriedades com as novas informações
                        subtaskElement.querySelector('.sub-task-content').textContent = `${data.nome} - ${data.nome_os}`;
                    }

                    // Fechar o modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editSubTaskModal'));
                    modal.hide();

                    markAgedOSes(); // Atualizar marcações após editar uma subtarefa
                } else {
                    alert('Erro ao editar sub-tarefa: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro ao editar sub-tarefa:', error);
                alert('Erro ao editar sub-tarefa: ' + error.message);
            });
        });
    });

    function fetchAvailableOSes() {
        return fetch('getAvailableOSes.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    availableOSes = data.data;
                } else {
                    console.error('Erro ao buscar OS disponíveis:', data.message);
                }
            })
            .catch(error => console.error('Erro ao buscar OS disponíveis:', error));
    }

    function formatDateDisplay(dateString) {
        const options = { day: '2-digit', month: '2-digit', year: 'numeric' };
        return new Date(dateString + 'T00:00:00').toLocaleDateString('pt-BR', options);
    }

    function parseDate(dateString) {
        const parts = dateString.split('-');
        // month is 0-based
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    // Função para calcular a diferença de dias entre duas datas
    function calculateDaysLeft(endDate) {
        const currentDate = new Date();
        const end = new Date(endDate);

        // Calcula a diferença em milissegundos e converte para dias
        const differenceInTime = end.getTime() - currentDate.getTime();
        const differenceInDays = Math.ceil(differenceInTime / (1000 * 3600 * 24));

        // Se a data final já passou, retorna o número negativo de dias
        return differenceInDays;
    }

    // Função para gerar o HTML das datas da sprint com os dias restantes ou dias desde o encerramento
    function generateSprintDatesHTML(dataInicio, dataFim) {
        const daysLeft = calculateDaysLeft(dataFim); // Calcular os dias restantes ou desde o encerramento

        // Define o texto baseado se a sprint está encerrada ou em andamento
        let statusTexto;
        if (daysLeft < 0) {
            statusTexto = `Encerrada há ${Math.abs(daysLeft)} dia(s)`;
        } else if (daysLeft === 0) {
            statusTexto = `Encerrada hoje`;
        } else {
            statusTexto = `Termina em ${daysLeft} dia(s)`;
        }

        return `
            <div><i class="fas fa-calendar-alt"></i> <strong>Início:&nbsp;</strong> ${formatDateDisplay(dataInicio)}</div>
            <div><i class="fas fa-calendar-check"></i> <strong>Fim:&nbsp;</strong> ${formatDateDisplay(dataFim)}</div>
            <div><i class="fas fa-hourglass-half"></i> <strong>${statusTexto}</strong></div>
        `;
    }

    function addSprintToList(sprint) {
        const sprintContainer = document.getElementById('sprints-container');
        const sprintElement = document.createElement('div');

        // Verifica se o prazo da sprint está vencido
        const hoje = new Date();
        const dataInicio = new Date(sprint.data_inicio);
        const dataFim = new Date(sprint.data_fim);
        const isVencido = hoje > dataFim;

        // Define a classe de estilo com base no status da sprint
        sprintElement.className = `sprint-column ${isVencido ? 'vencido' : 'prazo'}`;
        sprintElement.setAttribute('data-sprint-id', sprint.id);

        // Define o status da sprint com base no prazo
        const sprintStatus = isVencido ? 'Sprint Encerrada' : 'Sprint em Andamento';

        // Obtém o ano da data de início
        const anoInicio = dataInicio.getFullYear();

        const tarefasHtml = sprint.tarefas
            .map((t) => {
                let nomeOS = t.Nome_os;
                let nomeOSTruncado = nomeOS;
                if (nomeOS.length > 43) {
                    nomeOSTruncado = nomeOS.substring(0, 40) + '...';
                }

                return `
                    <li data-subtask-id="${t.id}" data-os-id="${t.Id}" data-original-sprint-id="${sprint.id}">
                        <div class="sub-task-content" title="${nomeOS}">
                            <strong class="os-number">${t.N_os}</strong> - ${nomeOSTruncado}
                            <span class="badge bg-warning">${t.Status_contratada || 'Sem status'}</span>
                        </div>
                        <div class="card-buttons">
                            <button class="btn-action" onclick="editSubTask(${t.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn-action" onclick="deleteSubTask(${t.id}, ${sprint.id})"><i class="fas fa-trash"></i></button>
                        </div>
                    </li>
                `;
            })
            .join('');

        const osOptions = availableOSes
            .map((os) => `<option value="${os.Id}">${os.N_os} - ${os.Nome_os}</option>`)
            .join('');

        const inputGroupHtml = `
            <div class="input-group">
                <select class="form-select" id="nome-${sprint.id}">
                    <option value="">Selecione uma OS</option>
                    ${osOptions}
                </select>
                <button class="btn-add-subtask" onclick="addSubTask(${sprint.id})">
                    <i class="fas fa-plus-circle"></i> Adicionar
                </button>
            </div>
        `;

        sprintElement.innerHTML = `
            <div class="sprint-header">
                <span class="sprint-status">${sprintStatus}</span>
                <div class="card-buttons">
                    <button class="btn-edit" onclick="editCard(${sprint.id})"><i class="fas fa-edit"></i></button>
                    <button class="btn-delete" onclick="deleteCard(${sprint.id})"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <div class="sprint-title-container">
                <span class="sprint-title">${sprint.titulo} - ${anoInicio}</span>
                <div class="sprint-dates">
                    ${generateSprintDatesHTML(sprint.data_inicio, sprint.data_fim)}
                </div>
            </div>
            <ul class="sprint-items" id="sprint-items-${sprint.id}">${tarefasHtml}</ul>
            ${inputGroupHtml}
        `;

        sprintContainer.appendChild(sprintElement);
        initSortable(sprint.id);
    }

    function fetchSprints(ano = new Date().getFullYear()) { 
        fetch('getSprints.php?ano=' + ano)
            .then(response => response.json())
            .then(data => {
                const sprintContainer = document.getElementById('sprints-container');
                sprintContainer.innerHTML = '';

                if (data.success) {
                    data.data.forEach(sprint => {
                        addSprintToList(sprint);
                    });

                    updateAllSelects();

                    // Atualizar o filtro de anos com os anos disponíveis
                    const anoFiltro = document.getElementById('anoFiltro');
                    anoFiltro.innerHTML = ''; // Limpar as opções existentes

                    data.anosDisponiveis.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.ano;
                        option.textContent = item.ano;
                        if (item.ano == ano) { // Marcar o ano atual como selecionado
                            option.selected = true;
                        }
                        anoFiltro.appendChild(option);
                    });

                    // Após todas as sprints serem carregadas, marcar OS envelhecidas
                    markAgedOSes();

                } else {
                    console.error("Erro ao buscar sprints:", data.message);
                    // Exibir uma mensagem de erro amigável ao usuário, se necessário
                }
            })
            .catch(error => {
                console.error('Erro ao buscar sprints:', error);
                // Exibir uma mensagem de erro amigável ao usuário
            });
    }

    function initSortable(sprintItemsId) {
        const container = document.getElementById(`sprint-items-${sprintItemsId}`);
        if (!container) {
            console.error('Erro: Sprint container not found for ID:', sprintItemsId);
            return;
        }

        new Sortable(container, {
            group: 'shared', // Permite mover entre múltiplos sprints
            animation: 150,
            onEnd: function (event) {
                const subtaskId = event.item.dataset.subtaskId;
                const oldSprintId = event.from.closest('.sprint-column').dataset.sprintId;
                const newSprintId = event.to.closest('.sprint-column').dataset.sprintId;
                const newPosition = Array.from(event.to.children).indexOf(event.item);

                console.log("Subtask moved:", subtaskId, "from sprint", oldSprintId, "to sprint", newSprintId, "at position", newPosition);

                // Atualizar a posição da subtarefa no banco de dados
                updateSubTaskPosition(subtaskId, oldSprintId, newSprintId, newPosition)
                    .then(() => {
                        // Recalcula as posições para ajustar a ordem corretamente
                        recalculatePositions(oldSprintId);
                        recalculatePositions(newSprintId);
                        markAgedOSes(); // Atualizar marcações após mover uma subtarefa
                    })
                    .catch(error => {
                        console.error('Erro ao atualizar a posição da subtarefa:', error);
                        // Reverter drag-and-drop se houver erro
                        event.item.remove(); // Remove da nova posição
                        event.from.insertBefore(event.item, event.from.children[event.oldIndex]); // Insere de volta na posição antiga
                        alert('Erro ao mover subtarefa: ' + error.message);
                    });
            }
        });
    }

    function updateSubTaskPosition(subtaskId, oldSprintId, newSprintId, newPosition) {
        const formData = new FormData();
        formData.append('subtask_id', subtaskId);
        formData.append('old_sprint_id', oldSprintId);
        formData.append('new_sprint_id', newSprintId);
        formData.append('position', newPosition); // Inclui a nova posição

        return fetch('update_subtask_position.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error('Erro ao atualizar posição da sub-tarefa: ' + data.message);
            }
        });
    }

    function recalculatePositions(sprintId) {
        const sprintItems = document.getElementById(`sprint-items-${sprintId}`);
        const subtasks = sprintItems.querySelectorAll('li');

        for (let i = 0; i < subtasks.length; i++) {
            const subtaskId = subtasks[i].dataset.subtaskId;
            updateSubTaskPosition(subtaskId, sprintId, sprintId, i); 
        }
    }

    // Função para adicionar a subtarefa
    function addSubTask(sprintId) {
        const nomeInput = document.getElementById(`nome-${sprintId}`);
        const sprintItems = document.getElementById(`sprint-items-${sprintId}`);

        if (!sprintItems) {
            console.error('Erro: Não foi possível encontrar o elemento sprint-items para a sprint ID:', sprintId);
            return;
        }

        const selectedOSId = nomeInput.value;

        if (!selectedOSId) {
            alert('Por favor, selecione uma OS.');
            return;
        }

        const formData = new FormData();
        formData.append('sprint_id', sprintId);
        formData.append('nome', selectedOSId);

        fetch('add_sub_tarefa.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Recarrega a página após adicionar a subtarefa com sucesso
                window.location.reload();
            } else {
                alert('Erro ao adicionar sub-tarefa: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao adicionar sub-tarefa:', error);
            alert('Erro ao adicionar sub-tarefa: ' + error.message);
        });
    }

    function deleteSubTask(subtaskId, sprintId) {
        if (!confirm('Tem certeza que deseja excluir esta sub-tarefa?')) {
            return;
        }

        const formData = new FormData();
        formData.append('subtask_id', subtaskId);

        fetch('delete_sub_tarefa.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Recarrega a página após a remoção da subtarefa com sucesso
                window.location.reload();
            } else {
                alert('Erro ao excluir sub-tarefa: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao excluir sub-tarefa:', error);
            alert('Erro ao excluir sub-tarefa: ' + error.message);
        });
    }

    function editSubTask(subtaskId) {
        // Certifique-se de que o ID da subtarefa é passado corretamente
        if (!subtaskId) {
            console.error('Erro: ID da sub-tarefa não fornecido.');
            alert('Erro ao obter dados da sub-tarefa: ID da sub-tarefa não fornecido.');
            return;
        }

        // Obter os dados atuais da subtarefa
        fetch('get_sub_tarefa.php?id=' + subtaskId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualiza os elementos do modal com os dados obtidos
                    document.getElementById('editSubTaskId').value = data.id;
                    document.getElementById('editSubTaskNome').value = `${data.numero_os} - ${data.nome_os}`;
                    
                    // Atualiza a lista de OS disponíveis no modal de edição
                    const availableOsList = document.getElementById('availableOsList');
                    availableOsList.innerHTML = '<option value="">Selecione uma OS para alterar</option>';
                    availableOSes.forEach(os => {
                        if (os.Id !== data.numero_os) { // Exclui a OS já associada à subtarefa atual
                            const option = document.createElement('option');
                            option.value = os.Id;
                            option.textContent = `${os.N_os} - ${os.Nome_os}`;
                            availableOsList.appendChild(option);
                        }
                    });

                    // Exibe o modal de edição da subtarefa
                    editSubTaskModal.show();
                } else {
                    alert('Erro ao obter dados da sub-tarefa: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro ao obter dados da sub-tarefa:', error);
                alert('Erro ao obter dados da sub-tarefa: ' + error.message);
            });
    }

    // Função para abrir o modal com o ID correto da subtarefa
    document.addEventListener('click', function(event) {
        if (event.target.matches('.btn-action')) {
            const subtaskId = event.target.closest('li').getAttribute('data-subtask-id');
            editSubTask(subtaskId);
        }
    });

    function deleteCard(sprintId) {
        if (!confirm('Tem certeza que deseja excluir esta sprint?')) {
            return;
        }

        const formData = new FormData();
        formData.append('sprint_id', sprintId);

        fetch('delete_sprint.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remover a sprint da interface
                const sprintElement = document.querySelector(`.sprint-column[data-sprint-id="${sprintId}"]`);
                if (sprintElement) {
                    sprintElement.remove();
                }

                // Adicionar as OS das subtarefas excluídas de volta ao availableOSes
                data.os_list.forEach(os => {
                    availableOSes.push({ Id: os.id, N_os: os.nome });
                });

                // Atualizar os selects em todas as sprints
                updateAllSelects();
                markAgedOSes(); // Atualizar marcações após excluir uma sprint
            } else {
                alert('Erro ao excluir sprint: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao excluir sprint:', error);
            alert('Erro ao excluir sprint: ' + error.message);
        });
    }

    function editCard(sprintId) {
        // Obter os dados atuais da sprint
        fetch('get_sprint.php?id=' + sprintId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Preencher os campos do modal com os dados atuais
                    document.getElementById('editSprintId').value = data.id;
                    document.getElementById('editTitulo').value = data.titulo;
                    document.getElementById('editDataInicio').value = data.data_inicio;
                    document.getElementById('editDataFim').value = data.data_fim;

                    // Exibir o modal
                    editSprintModal.show();
                } else {
                    alert('Erro ao obter dados da sprint: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro ao obter dados da sprint:', error);
                alert('Erro ao obter dados da sprint: ' + error.message);
            });
    }

    // Função para atualizar todos os selects de OS na interface
    function updateAllSelects() {
        // Itera por cada select relacionado às OS
        document.querySelectorAll('.input-group select').forEach(select => {
            // Limpa as opções atuais do select
            select.innerHTML = '';

            // Adiciona a opção padrão de seleção
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Selecione uma OS';
            select.appendChild(defaultOption);

            // Adiciona as opções de OS que ainda não foram adicionadas
            availableOSes.forEach(os => {
                const option = document.createElement('option');
                option.value = os.Id;
                option.textContent = `${os.N_os} - ${os.Nome_os}`;
                select.appendChild(option);
            });
        });

        console.log('All selects have been updated to exclude already added OS.');
    }

    // Função para marcar OS repetidas como envelhecidas
    function markAgedOSes() {
        const osMap = {};

        // Seleciona todas as subtarefas de OS
        const osElements = document.querySelectorAll('.sprint-items li');

        osElements.forEach(li => {
            const osId = li.getAttribute('data-os-id');
            if (osId) {
                if (!osMap[osId]) {
                    osMap[osId] = [];
                }
                osMap[osId].push(li);
            }
        });

        // Para cada OS usada múltiplas vezes, marque as antigas como envelhecidas
        Object.keys(osMap).forEach(osId => {
            if (osMap[osId].length > 1) {
                // Ordena as subtarefas pela data de início da sprint (mais recente primeiro)
                const sortedElements = osMap[osId].sort((a, b) => {
                    const sprintA = a.closest('.sprint-column');
                    const sprintB = b.closest('.sprint-column');

                    const dataInicioA = new Date(sprintA.querySelector('.sprint-dates div:first-child').textContent.split(': ')[1]);
                    const dataInicioB = new Date(sprintB.querySelector('.sprint-dates div:first-child').textContent.split(': ')[1]);

                    return dataInicioB - dataInicioA; // Descendente: mais recente primeiro
                });

                // Mantém a primeira (mais recente) e marca as demais como envelhecidas
                sortedElements.slice(1).forEach(li => {
                    li.classList.add('os-aged');
                });
            }
        });
    }
</script>

</body>

</html>

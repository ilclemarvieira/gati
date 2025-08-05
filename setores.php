<?php
// Definindo o tempo máximo da sessão para 6 horas (21600 segundos)
ini_set('session.gc_maxlifetime', 21600);

// Iniciando a sessão
session_start();

// Configurando o cookie de sessão para durar 6 horas também
$params = session_get_cookie_params();
setcookie(
    session_name(),
    $_COOKIE[session_name()],
    time() + 21600,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
);

// Verificação de login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

// Verificando qual o perfil do usuário logado
$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

// Função para verificar permissão de acesso
function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        // Redireciona para a página anterior ou para uma página padrão
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}

// Defina os perfis que podem acessar esta página
$perfisPermitidos = [1, 2, 4, 9]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);

// Recupera os dados dos setores do banco
$setores = $pdo->query("SELECT * FROM setores")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">

<head>
    <?php include 'head.php'; ?>
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

        .anexo-container img,
        .anexo-container iframe {
            max-width: 100%;
            height: auto;
        }

        .highlighted {
            background-color: #ffff0026;
        }

        .content-table tbody tr.highlighted {
            background-color: #ffff0026;
        }

        .content-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .content-table tbody tr.highlighted:hover {
            background-color: #ffff0026 !important;
        }

        .filter-container {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            background-color: #272b34;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .filter-container select,
        .filter-container input[type="text"] {
            flex-grow: 1;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #666;
            background-color: #323743;
            color: #a1aab2;
            font-size: 14px;
            margin: 0px 5px;
        }

        .filter-container select:focus,
        .filter-container input[type="text"]:focus {
            border-color: #007bff;
        }

        .filter-container button {
            padding: 12px 15px;
            border-radius: 4px;
            border: 1px solid #4a5f7c;
            background-color: #4a5f7c;
            color: #e6e6e6;
            cursor: pointer;
            text-transform: uppercase;
            font-weight: bold;
            transition: background-color 0.3s, border-color 0.3s, color 0.3s;
            margin: 0px 5px;
        }

        .filter-container button:hover {
            background-color: #627d9a;
            border-color: #627d9a;
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
            }

            .filter-container select,
            .filter-container input[type="text"],
            .filter-container button {
                flex-basis: 100%;
                margin-bottom: 10px;
            }

            .filter-container button {
                margin-bottom: 0;
            }
        }

        .filter-container input[type="text"] {
            font-size: 15px;
            color: #a1aab2;
            padding: 12px;
            width: 270px;
            margin: 10px 0px;
        }

        @media (max-width: 768px) {
            .filter-container input[type="text"] {
                font-size: 14px;
            }
        }

        .round-lg {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .fa {
            font-size: 14px;
        }

        .card {
            cursor: pointer;
        }

        .card-body {
            display: flex;
            align-items: center;
        }

        .card-title {
            margin-bottom: 0;
            font-size: 15px;
            font-weight: bold;
        }

        .card-text {
            margin-top: 4px;
            font-size: 16px;
        }

        .text-muted {
            display: block;
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
            background-color: rgba(0, 0, 0, 0.4);
            padding-top: 10px;
        }

        .modal-content {
            background-color: #fff;
            margin: 2% auto;
            padding: 20px;
            border-radius: 8px;
            width: 50%;
            box-shadow: 0 4px 10px rgba(0, 0, 10, 10.15);
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
            background-color: rgba(252, 75, 108, .5) !important;
            color: white;
            border: rgba(252, 75, 108, .5) !important;
            border-radius: 40px;
            cursor: pointer;
            transition: background-color 0.2s;
            align-self: flex-end;
        }

        .submit-btn:hover {
            background-color: #0056b3;
        }

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

        .readonly {
            background-color: #b3c0c7 !important;
            color: #272b34 !important;
            font-weight: bold;
            cursor: not-allowed !important;
        }

        @media screen and (max-width: 600px) {
            .modal-content {
                width: 95%;
            }
        }

        .finalizada {
            text-decoration: line-through;
            opacity: 0.5;
        }

        .linha-taxada {
            text-decoration: line-through;
            opacity: 0.7;
        }
    </style>
</head>

<body>
    <div class="preloader">
        <svg class="tea lds-ripple" width="37" height="48" viewBox="0 0 37 48" fill="none"
            xmlns="http://www.w3.org/2000/svg">
            <path
                d="M27.0819 17H3.02508C1.91076 17 1.01376 17.9059 1.0485 19.0197C1.15761 22.5177 1.49703 29.7374 2.5 34C4.07125 40.6778 7.18553 44.8868 8.44856 46.3845C8.79051 46.79 9.29799 47 9.82843 47H20.0218C20.639 47 21.2193 46.7159 21.5659 46.2052C22.6765 44.5687 25.2312 40.4282 27.5 34C28.9757 29.8188 29.084 22.4043 29.0441 18.9156C29.0319 17.8436 28.1539 17 27.0819 17Z"
                stroke="#1e88e5" stroke-width="2"></path>
            <path
                d="M29 23.5C29 23.5 34.5 20.5 35.5 25.4999C36.0986 28.4926 34.2033 31.5383 32 32.8713C29.4555 34.4108 28 34 28 34"
                stroke="#1e88e5" stroke-width="2"></path>
            <path id="teabag" fill="#1e88e5" fill-rule="evenodd" clip-rule="evenodd"
                d="M16 25V17H14V25H12C10.3431 25 9 26.3431 9 28V34C9 35.6569 10.3431 37 12 37H18C19.6569 37 21 35.6569 21 34V28C21 26.3431 19.6569 25 18 25H16ZM11 28C11 27.4477 11.4477 27 12 27H18C18.5523 27 19 27.4477 19 28V34C19 34.5523 18.5523 35 18 35H12C11.4477 35 11 34.5523 11 34V28Z">
            </path>
            <path id="steamL" d="M17 1C17 1 17 4.5 14 6.5C11 8.5 11 12 11 12" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" stroke="#1e88e5"></path>
            <path id="steamR" d="M21 6C21 6 21 8.22727 19 9.5C17 10.7727 17 13 17 13" stroke="#1e88e5"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
    </div>

    <div id="main-wrapper">
        <header class="topbar">
            <?php include 'header.php'; ?>
        </header>

        <?php include 'sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="row page-titles">
                <div class="col-md-5 col-12 align-self-center">
                    <h3 class="text-themecolor mb-0"><i class="mdi mdi-book"></i> Setores</h3>
                </div>
            </div>

            <div class="container-fluid">
                <!-- Botão para abrir o modal de adicionar novo Setor -->
                <button class="btn waves-effect waves-light btn-info" onclick="openModal('addModal')">
                    <i class="ti-plus text"></i> Cadastrar Setor
                </button>
                <br><br>

                <div class="row">
                    <div class="table-responsive">
                        <table class="table table-striped table mb-0" data-tablesaw-mode="columntoggle"
                            id="tablesaw-6204">
                            <thead class="thead-light" align="center">
                                <tr style="background-color: #5b5f69;">
                                    <th scope="col" style="white-space: nowrap;">Nome do Setor</th>
                                    <th scope="col" style="white-space: nowrap;">Data de Cadastro</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($setores as $setor): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 500; text-transform: uppercase; vertical-align: middle;">
                                            <?php echo htmlspecialchars($setor['nome_do_setor']); ?>
                                        </td>
                                        <td style="text-align: center; vertical-align: middle;">
                                            <?php 
                                                // Formata a data para dd/mm/yyyy
                                                $dataCadastro = new DateTime($setor['dt_cadastro']);
                                                echo $dataCadastro->format('d/m/Y');
                                            ?>
                                        </td>
                                        <td style="text-align: center; vertical-align: middle" class="action-buttons">
                                            <div class="d-flex justify-content-center align-items-center">
                                                <!-- Botão Editar -->
                                                <button
                                                    onclick="loadEditModal(
                                                        <?php echo $setor['id'] . ', \'' . addslashes($setor['nome_do_setor']) . '\''; ?>
                                                    )"
                                                    title="Editar"
                                                    class="btn btn-sm btn-light-info text-white mx-1">
                                                    <i data-feather="edit" class="feather-sm fill-white"></i>
                                                </button>

                                                <!-- Botão Excluir -->
                                                <button
                                                    onclick="deleteSetor(<?php echo $setor['id']; ?>)"
                                                    title="Excluir"
                                                    class="btn btn-sm btn-light-danger text-white mx-1">
                                                    <i data-feather="trash-2" class="feather-sm fill-white"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal de Cadastro de Setor -->
                <div id="addModal" class="modal" style="display: none;">
                    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h2 class="modal-title" id="modalCadastroLabel">
                                    <i class="mdi mdi-account-plus"></i>&nbsp;Cadastrar Setor
                                </h2>
                                <span class="close" onclick="closeModal('addModal')">&times;</span>
                            </div>
                            <form id="addSetorForm" action="process_cadastro_setor.php" method="post" onsubmit="event.preventDefault(); submitAddForm();">
                                <div class="modal-body">
                                    <!-- Campo: nome_do_setor -->
                                    <div class="mb-3">
                                        <label for="addNomeSetor" class="form-label">Nome do Setor:</label>
                                        <input type="text" class="form-control bg-secondary text-white" id="addNomeSetor" name="nome_do_setor" required>
                                    </div>

                                    <!-- (Removido o campo data de cadastro, pois será automático) -->

                                    <!-- Botão de submissão -->
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-primary font-weight-medium rounded-pill px-4">
                                            <div class="d-flex align-items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                     width="24" height="24"
                                                     viewBox="0 0 24 24"
                                                     fill="none"
                                                     stroke="currentColor"
                                                     stroke-width="2"
                                                     stroke-linecap="round"
                                                     stroke-linejoin="round"
                                                     class="feather feather-send feather-sm fill-white me-2">
                                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                                </svg>
                                                Cadastrar
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal de Edição de Setor -->
                <div id="editModal" class="modal" style="display: none;">
                    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
                        <div class="modal-content bg-dark text-white">
                            <div class="modal-header">
                                <h2 class="modal-title" id="modalEditLabel">
                                    <i class="mdi mdi-account-edit"></i>&nbsp;Editar Setor
                                </h2>
                                <span class="close" onclick="closeModal('editModal')">&times;</span>
                            </div>
                            <form id="editSetorForm" action="process_edit_setor.php" method="post" onsubmit="event.preventDefault(); submitEditForm();">
                                <input type="hidden" id="editIdSetor" name="id">
                                <div class="modal-body">
                                    <!-- Campo: nome_do_setor -->
                                    <div class="mb-3">
                                        <label for="editNomeSetor" class="form-label">Nome do Setor:</label>
                                        <input type="text" class="form-control bg-secondary text-white" id="editNomeSetor" name="nome_do_setor" required>
                                    </div>

                                    <!-- (Removido o campo data de cadastro, pois não será editado) -->

                                    <!-- Botão de submissão -->
                                    <div class="d-flex justify-content-end mt-4">
                                        <div class="form-row">
                                            <button type="submit" class="submit-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                     width="24" height="24"
                                                     viewBox="0 0 24 24"
                                                     fill="none"
                                                     stroke="currentColor"
                                                     stroke-width="2"
                                                     stroke-linecap="round"
                                                     stroke-linejoin="round"
                                                     class="feather feather-send feather-sm fill-white me-2">
                                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                                </svg>
                                                Salvar Alterações
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <div class="chat-windows"></div>

    <!-- Scripts de manipulação do modal e das requisições -->
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Submissão do Form de Cadastro
        function submitAddForm() {
            const nomeSetor = document.getElementById('addNomeSetor').value;

            fetch('process_cadastro_setor.php', {
                method: 'POST',
                body: new URLSearchParams({
                    'nome_do_setor': nomeSetor
                })
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                closeModal('addModal');
                location.reload();
            })
            .catch(error => console.error(error));
        }

        // Submissão do Form de Edição
        function submitEditForm() {
            const id = document.getElementById('editIdSetor').value;
            const nomeSetor = document.getElementById('editNomeSetor').value;

            fetch('process_edit_setor.php', {
                method: 'POST',
                body: new URLSearchParams({
                    'id': id,
                    'nome_do_setor': nomeSetor
                })
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                closeModal('editModal');
                location.reload();
            })
            .catch(error => console.error(error));
        }

        // Carrega dados no modal de edição
        function loadEditModal(id, nomeSetor) {
            document.getElementById('editIdSetor').value = id;
            document.getElementById('editNomeSetor').value = nomeSetor;
            openModal('editModal');
        }

        // Exclui o setor - Função atualizada com a correção
        function deleteSetor(id) {
            if (confirm('Tem certeza que deseja excluir este setor? Esta ação também removerá todos os registros relacionados.')) {
                fetch('delete_setor.php', {
                    method: 'POST',
                    body: new URLSearchParams({ 'id': id })
                })
                .then(response => response.text())
                .then(data => {
                    if(data.includes("Erro")) {
                        alert("Não foi possível excluir o setor. Ele pode estar em uso em outras partes do sistema.");
                    } else {
                        alert(data);
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error(error);
                    alert("Ocorreu um erro ao tentar excluir o setor.");
                });
            }
        }
    </script>

    <!-- Scripts principais -->
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
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
</body>
</html>
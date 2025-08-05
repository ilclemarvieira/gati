<?php
// Definindo o tempo máximo da sessão para 6 horas (21600 segundos)
ini_set('session.gc_maxlifetime', 21600);

session_start();

// Configurando o cookie de sessão para ter a mesma duração
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
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}

// No início de cada página restrita, chame verificarPermissao com os perfis permitidos
$perfisPermitidos = [1, 2, 4];
verificarPermissao($perfisPermitidos);

// Consulta para buscar todos os usuários
$usuarios = $pdo->query("SELECT * FROM usuarios")->fetchAll();

function formatarCPF($cpf) {
    $cpf = preg_replace("/\D/", '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.
               substr($cpf, 6, 3).'-'.substr($cpf, 9, 2);
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
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon.png" />
    <!-- Bootstrap CSS -->
    <link href="assets/libs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/extra-libs/taskboard/css/lobilist.css" />
    <link rel="stylesheet" href="assets/extra-libs/taskboard/css/jquery-ui.min.css" />
    <link rel="stylesheet" type="text/css" href="assets/libs/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css" />
    <!-- Custom CSS -->
    <link href="dist/css/style.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
      integrity="sha512-..."
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>

    <!-- Estilos personalizados -->
    <style>
        /* ======== Estilos Gerais ======== */
        body {
            background-color: #1f1f1f;
            color: #eaeaea;
            font-family: 'Roboto', sans-serif;
        }
        .preloader {
            position: fixed;
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            z-index: 9999; 
            background: #1f1f1f url('assets/images/preloader.gif') no-repeat center center;
        }
        /* ======== Container Principal ======== */
        .form-section {
            background-color: #272b34;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .form-section h1 {
            color: #9fb497;
            font-weight: 700;
            margin-bottom: 2rem;
        }
        /* ======== Títulos Internos ======== */
        .section-title {
            font-weight: 800;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #9fb497;
            display: flex;
            align-items: center;
        }
        .section-title i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        /* ======== Formulário ======== */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        .form-group label i {
            margin-right: 8px;
        }
        .form-group input[type="number"],
        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            color: #eaeaea;
            background-color: #2b2b2b;
            border: 1px solid #555;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #6ab04c;
        }
        .input-group-custom {
            display: flex;
            gap: 15px;
        }
        .input-group-custom .flex-fill label {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        /* ======== Botões ======== */
        .btn-primary,
        .btn-success,
        .btn-secondary {
            font-weight: bold;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 25px;
            transition: background-color 0.3s, transform 0.2s, box-shadow 0.3s;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(45deg, #6ab04c, #58b947);
            color: #ffffff;
            width: auto;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #58b947, #6ab04c);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(106,176,76,0.3);
        }
        .btn-success {
            background-color: #28a745;
            color: #ffffff;
            width: auto;
        }
        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40,167,69,0.3);
        }
        .btn-secondary {
            background-color: #555;
            color: #eaeaea;
            width: auto;
        }
        .btn-secondary:hover {
            background-color: #666;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(85,85,85,0.3);
        }
        /* ======== Cards de Resultados ======== */
        .card-result {
            background-color: #323743;
            color: #eaeaea;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card-result h4 {
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-result h4 i {
            margin-right: 10px;
        }
        .card-result p {
            font-size: 24px;
            font-weight: bold;
        }
        /* ======== Modal customizado ======== */
        .modal {
            display: none;
            position: fixed;
            top: 0; 
            left: 0; 
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
            z-index: 9999; 
        }
        .modal.show {
            display: block;
        }
        .modal-dialog {
            max-width: 1000px;
            margin: 2rem auto;
        }
        .modal-content {
            background-color: #2e2e2e;
            color: #eaeaea;
            border: none;
            border-radius: 8px;
        }
        .modal-header {
            background-color: #1f1f1f;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid #555;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .modal-title {
            margin: 0;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        .modal-title i {
            margin-right: 10px;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-body h3 {
            color: #9fb497;
            margin-bottom: 15px;
        }
        .modal-footer {
            border-top: 1px solid #555;
            display: flex;
            justify-content: flex-end;
            padding: 15px;
        }
        .close {
            font-size: 1.4rem;
            color: #fff;
            cursor: pointer;
            background: none;
            border: 0;
            outline: 0;
        }
        .close:hover {
            color: #6ab04c;
        }
        /* ======== Estilizando Tabelas e Listas ======== */
        .list-group-item {
            background-color: #f8f9fa !important;
            color: #b2b9bf !important;
            border: 1px solid #ddd !important;
            font-size: 0.95rem;
        }
        .result-content table {
            width: 100%;
            border-collapse: collapse;
        }
        .result-content th,
        .result-content td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .result-content th {
            background-color: #343a40;
            color: #ffffff;
        }
        .result-content tr:nth-child(even) {
            background-color: #2e3240;
        }
        /* ======== Cabeçalhos de Página ======== */
        .page-titles h3 {
            font-weight: 700;
        }
        .page-titles h3 i {
            margin-right: 10px;
        }
        /* ======== Responsividade ======== */
        @media (max-width: 768px) {
            .form-group {
                margin-bottom: 15px;
            }
            .form-group label {
                font-size: 0.9rem;
            }
            .section-title {
                font-size: 1rem;
            }
            .input-group-custom {
                flex-direction: column;
            }
            .btn-primary,
            .btn-success,
            .btn-secondary {
                font-size: 14px;
                padding: 10px 18px;
                border-radius: 20px;
            }
            .card-result h4 {
                font-size: 16px;
            }
            .card-result p {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader"></div>

    <div id="main-wrapper">
        <!-- Topbar e Sidebar -->
        <header class="topbar">
            <?php include 'header.php'; ?>
        </header>
        <?php include 'sidebar.php'; ?>

        <!-- Conteúdo da página -->
        <div class="page-wrapper">
            <div class="row page-titles">
                <div class="col-md-5 col-12 align-self-center">
                    <h3 class="text-themecolor mb-0">
                        <i class="fas fa-calculator"></i> Análise de Pontos de Função
                    </h3>
                </div>
            </div>
            
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="container my-5">
                            <!-- Seção do Formulário -->
                            <div class="form-section">
                                <h1><i class="fas fa-pie-chart"></i> Formulário de Análise</h1>
                                <form id="functionPointForm" onsubmit="return false;">
                                    <!-- Número da OS -->
                                    <div class="form-group">
                                        <label for="osNumber">
                                            Número da Ordem de Serviço (OS):
                                        </label>
                                        <input
                                          type="text"
                                          id="osNumber"
                                          class="form-control"
                                          name="osNumber"
                                          placeholder="Digite o número da OS"
                                        >
                                    </div>

                                    <!-- Requisitos Não-Funcionais -->
                                    <div class="form-group">
                                        <label for="nonFunctionalPoints">
                                           Requisitos Não-Funcionais (em PF):
                                        </label>
                                        <input
                                          type="number"
                                          id="nonFunctionalPoints"
                                          class="form-control"
                                          name="nonFunctionalPoints"
                                          min="0"
                                          value="0"
                                          placeholder="Ex: 12"
                                        >
                                    </div><br>

                                    <!-- Funções de Dados -->
                                    <h2 class="section-title">
                                        <i class="fas fa-database"></i> Funções de Dados
                                    </h2>
                                    <div class="form-group">
                                        <label> Arquivos Lógicos Internos (ALI):</label>
                                        <div class="input-group-custom">
                                            <div class="flex-fill">
                                                <label for="aliHigh">
                                                    <i class="fas fa-arrow-up text-danger"></i> Alto
                                                </label>
                                                <input
                                                  type="number"
                                                  id="aliHigh"
                                                  class="form-control"
                                                  name="aliHigh"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="aliMedium">
                                                    <i class="fas fa-arrow-right text-warning"></i> Médio
                                                </label>
                                                <input
                                                  type="number"
                                                  id="aliMedium"
                                                  class="form-control"
                                                  name="aliMedium"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="aliLow">
                                                    <i class="fas fa-arrow-down text-success"></i> Baixo
                                                </label>
                                                <input
                                                  type="number"
                                                  id="aliLow"
                                                  class="form-control"
                                                  name="aliLow"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Arquivos de Interface Externa (AIE):</label>
                                        <div class="input-group-custom">
                                            <div class="flex-fill">
                                                <label for="aieHigh">
                                                    <i class="fas fa-arrow-up text-danger"></i> Alto
                                                </label>
                                                <input
                                                  type="number"
                                                  id="aieHigh"
                                                  class="form-control"
                                                  name="aieHigh"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="aieMedium">
                                                    <i class="fas fa-arrow-right text-warning"></i> Médio
                                                </label>
                                                <input
                                                  type="number"
                                                  id="aieMedium"
                                                  class="form-control"
                                                  name="aieMedium"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="aieLow">
                                                    <i class="fas fa-arrow-down text-success"></i> Baixo
                                                </label>
                                                <input
                                                  type="number"
                                                  id="aieLow"
                                                  class="form-control"
                                                  name="aieLow"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                        </div>
                                    </div><br>

                                    <!-- Funções de Transação -->
                                    <h2 class="section-title">
                                        <i class="fas fa-exchange-alt"></i> Funções de Transação
                                    </h2>
                                    <div class="form-group">
                                        <label> Entradas Externas (EE):</label>
                                        <div class="input-group-custom">
                                            <div class="flex-fill">
                                                <label for="eeHigh">
                                                    <i class="fas fa-arrow-up text-danger"></i> Alto
                                                </label>
                                                <input
                                                  type="number"
                                                  id="eeHigh"
                                                  class="form-control"
                                                  name="eeHigh"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="eeMedium">
                                                    <i class="fas fa-arrow-right text-warning"></i> Médio
                                                </label>
                                                <input
                                                  type="number"
                                                  id="eeMedium"
                                                  class="form-control"
                                                  name="eeMedium"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="eeLow">
                                                    <i class="fas fa-arrow-down text-success"></i> Baixo
                                                </label>
                                                <input
                                                  type="number"
                                                  id="eeLow"
                                                  class="form-control"
                                                  name="eeLow"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Saídas Externas (SE):</label>
                                        <div class="input-group-custom">
                                            <div class="flex-fill">
                                                <label for="seHigh">
                                                    <i class="fas fa-arrow-up text-danger"></i> Alto
                                                </label>
                                                <input
                                                  type="number"
                                                  id="seHigh"
                                                  class="form-control"
                                                  name="seHigh"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="seMedium">
                                                    <i class="fas fa-arrow-right text-warning"></i> Médio
                                                </label>
                                                <input
                                                  type="number"
                                                  id="seMedium"
                                                  class="form-control"
                                                  name="seMedium"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="seLow">
                                                    <i class="fas fa-arrow-down text-success"></i> Baixo
                                                </label>
                                                <input
                                                  type="number"
                                                  id="seLow"
                                                  class="form-control"
                                                  name="seLow"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Consultas Externas (CE):</label>
                                        <div class="input-group-custom">
                                            <div class="flex-fill">
                                                <label for="ceHigh">
                                                    <i class="fas fa-arrow-up text-danger"></i> Alto
                                                </label>
                                                <input
                                                  type="number"
                                                  id="ceHigh"
                                                  class="form-control"
                                                  name="ceHigh"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="ceMedium">
                                                    <i class="fas fa-arrow-right text-warning"></i> Médio
                                                </label>
                                                <input
                                                  type="number"
                                                  id="ceMedium"
                                                  class="form-control"
                                                  name="ceMedium"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                            <div class="flex-fill">
                                                <label for="ceLow">
                                                    <i class="fas fa-arrow-down text-success"></i> Baixo
                                                </label>
                                                <input
                                                  type="number"
                                                  id="ceLow"
                                                  class="form-control"
                                                  name="ceLow"
                                                  min="0"
                                                  value="0"
                                                  placeholder="0"
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botão Calcular -->
                                    <br>
                                    <div class="mt-2" align="right">
                                        <button
                                          type="button"
                                          class="btn btn-success"
                                          onclick="calculateFunctionPoints()"
                                        >
                                            <i class="fas fa-calculator"></i> Calcular Pontos de Função
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Modal de Resultados -->
                            <div id="resultModal" class="modal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="resultModalLabel">
                                                <i class="fas fa-chart-line"></i> Resultado da Análise de Pontos de Função
                                            </h5>
                                            <!-- Ícone X que fecha via JavaScript -->
                                            <button type="button" class="close" onclick="hideModal('resultModal')">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-3 col-sm-6 mb-4">
                                                    <div class="card-result card-non-adjusted">
                                                        <h4><i class="fas fa-exclamation-circle"></i> Não Ajustados</h4>
                                                        <p id="modalTotalNonAdjusted">0</p>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-4">
                                                    <div class="card-result card-adjusted">
                                                        <h4><i class="fas fa-check-circle"></i> Ajustados</h4>
                                                        <p id="modalTotalAdjusted">0</p>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-4">
                                                    <div class="card-result card-impact">
                                                        <h4><i class="fas fa-bolt"></i> PF Impactado</h4>
                                                        <p id="modalTotalPfImpactado">0</p>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 col-sm-6 mb-4">
                                                    <div class="card-result card-valor-pf">
                                                        <h4><i class="fas fa-money-bill-wave"></i> Valor PF</h4>
                                                        <p id="modalTotalValorPf">R$ 0,00</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="result-content mt-4">
                                                <h3>Detalhes do Cálculo:</h3>
                                                <ul class="list-group">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Requisitos Não-Funcionais (em PF):</span>
                                                        <span id="modalNonFunctionalPoints">0</span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Funções de Dados (ALI e AIE):</span>
                                                        <span id="modalTotalPointsData">0</span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span>Funções de Transação (EE, SE, CE):</span>
                                                        <span id="modalTotalPointsTransaction">0</span>
                                                    </li>
                                                </ul>

                                                <h3 class="mt-4">Detalhamento por Tipo de Função:</h3>
                                                <table class="table table-striped mt-3">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Tipo de Função</th>
                                                            <th scope="col">Não Ajustado</th>
                                                            <th scope="col">Ajustado</th>
                                                            <th scope="col">PF Impactado</th>
                                                            <th scope="col">Valor</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>ALI</td>
                                                            <td id="modalTotalAli">0</td>
                                                            <td id="modalAdjustedAli">0</td>
                                                            <td id="modalPfImpactadoAli">0</td>
                                                            <td id="modalValorAli">R$ 0,00</td>
                                                        </tr>
                                                        <tr>
                                                            <td>AIE</td>
                                                            <td id="modalTotalAie">0</td>
                                                            <td id="modalAdjustedAie">0</td>
                                                            <td id="modalPfImpactadoAie">0</td>
                                                            <td id="modalValorAie">R$ 0,00</td>
                                                        </tr>
                                                        <tr>
                                                            <td>EE</td>
                                                            <td id="modalTotalEe">0</td>
                                                            <td id="modalAdjustedEe">0</td>
                                                            <td id="modalPfImpactadoEe">0</td>
                                                            <td id="modalValorEe">R$ 0,00</td>
                                                        </tr>
                                                        <tr>
                                                            <td>SE</td>
                                                            <td id="modalTotalSe">0</td>
                                                            <td id="modalAdjustedSe">0</td>
                                                            <td id="modalPfImpactadoSe">0</td>
                                                            <td id="modalValorSe">R$ 0,00</td>
                                                        </tr>
                                                        <tr>
                                                            <td>CE</td>
                                                            <td id="modalTotalCe">0</td>
                                                            <td id="modalAdjustedCe">0</td>
                                                            <td id="modalPfImpactadoCe">0</td>
                                                            <td id="modalValorCe">R$ 0,00</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <!-- Botão Gerar PDF -->
                                            <button type="button" class="btn btn-success" onclick="printAnalysis()">
                                                <i class="fa fa-file-pdf"></i> Gerar PDF
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal PDF (aberto/fechado manualmente) -->
                            <div id="pdfModal" class="modal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="pdfModalLabel">Pré-visualização do PDF</h5>
                                            <button type="button" class="close" onclick="hideModal('pdfModal')">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <iframe id="pdfIframe" src="" frameborder="0" style="width: 100%; height: 80vh;"></iframe>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-primary" id="downloadPdfBtn">
                                               <i class="fas fa-arrow-circle-down"></i> Baixar PDF
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Fim Container -->
                        </div>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- jQuery deve vir antes do Bootstrap -->
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery.ui.touch-punch-improved.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery-ui.min.js"></script>
    <!-- Scripts do Bootstrap -->
    <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Scripts adicionais -->
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

    <!-- jsPDF e AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.21/jspdf.plugin.autotable.min.js"></script>

    <!-- Funções personalizadas para abrir/fechar modal -->
    <script>
    function showModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
        }
    }
    function hideModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
        }
    }
    </script>

    <!-- Script para Cálculo de Pontos de Função e Geração de PDF -->
    <script>
        function calculateFunctionPoints() {
            // Captura do formulário
            const osNumber = document.getElementById('osNumber').value.trim();
            const nonFunctionalPoints = parseFloat(document.getElementById('nonFunctionalPoints').value) || 0;

            // ALI
            const aliLow = parseInt(document.getElementById('aliLow').value) || 0;
            const aliMedium = parseInt(document.getElementById('aliMedium').value) || 0;
            const aliHigh = parseInt(document.getElementById('aliHigh').value) || 0;

            // AIE
            const aieLow = parseInt(document.getElementById('aieLow').value) || 0;
            const aieMedium = parseInt(document.getElementById('aieMedium').value) || 0;
            const aieHigh = parseInt(document.getElementById('aieHigh').value) || 0;

            // EE
            const eeLow = parseInt(document.getElementById('eeLow').value) || 0;
            const eeMedium = parseInt(document.getElementById('eeMedium').value) || 0;
            const eeHigh = parseInt(document.getElementById('eeHigh').value) || 0;

            // SE
            const seLow = parseInt(document.getElementById('seLow').value) || 0;
            const seMedium = parseInt(document.getElementById('seMedium').value) || 0;
            const seHigh = parseInt(document.getElementById('seHigh').value) || 0;

            // CE
            const ceLow = parseInt(document.getElementById('ceLow').value) || 0;
            const ceMedium = parseInt(document.getElementById('ceMedium').value) || 0;
            const ceHigh = parseInt(document.getElementById('ceHigh').value) || 0;

            // Pesos SISP
            const weights = {
                ali: { low: 7, medium: 10, high: 15 },
                aie: { low: 5, medium: 7, high: 10 },
                ee:  { low: 3, medium: 4, high: 6 },
                se:  { low: 4, medium: 5, high: 7 },
                ce:  { low: 3, medium: 4, high: 6 }
            };

            // Calcular PF não-ajustados (funcionais)
            const totalAli = (aliLow * weights.ali.low) + (aliMedium * weights.ali.medium) + (aliHigh * weights.ali.high);
            const totalAie = (aieLow * weights.aie.low) + (aieMedium * weights.aie.medium) + (aieHigh * weights.aie.high);
            const totalEe = (eeLow * weights.ee.low) + (eeMedium * weights.ee.medium) + (eeHigh * weights.ee.high);
            const totalSe = (seLow * weights.se.low) + (seMedium * weights.se.medium) + (seHigh * weights.se.high);
            const totalCe = (ceLow * weights.ce.low) + (ceMedium * weights.ce.medium) + (ceHigh * weights.ce.high);

            const totalPointsData = totalAli + totalAie;
            const totalPointsTransaction = totalEe + totalSe + totalCe;
            const totalNonAdjusted = totalPointsData + totalPointsTransaction + nonFunctionalPoints;

            // Fator de ajuste
            const adjustmentFactor = 1.01;
            const totalAdjusted = totalNonAdjusted * adjustmentFactor;

            // PF Impactado só das funções funcionais
            const adjustedAli = totalAli * adjustmentFactor;
            const adjustedAie = totalAie * adjustmentFactor;
            const adjustedEe = totalEe * adjustmentFactor;
            const adjustedSe = totalSe * adjustmentFactor;
            const adjustedCe = totalCe * adjustmentFactor;

            const fiPercentage = 0.15; // 15%
            const pfImpactadoAli = adjustedAli * fiPercentage;
            const pfImpactadoAie = adjustedAie * fiPercentage;
            const pfImpactadoEe = adjustedEe * fiPercentage;
            const pfImpactadoSe = adjustedSe * fiPercentage;
            const pfImpactadoCe = adjustedCe * fiPercentage;
            const totalPfImpactado = pfImpactadoAli + pfImpactadoAie + pfImpactadoEe + pfImpactadoSe + pfImpactadoCe;

            // Valor PF
            const pfValue = 732.72;
            const totalValorPf = totalAdjusted * pfValue;

            // Formatar valor em estilo brasileiro
            const formattedValorPf = "R$ " + totalValorPf.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            // Atualiza campos do modal
            document.getElementById('modalTotalNonAdjusted').textContent =
                totalNonAdjusted.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalTotalAdjusted').textContent =
                totalAdjusted.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalTotalPfImpactado').textContent =
                totalPfImpactado.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalTotalValorPf').textContent = formattedValorPf;

            // Detalhes
            document.getElementById('modalNonFunctionalPoints').textContent =
                nonFunctionalPoints.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalTotalPointsData').textContent =
                totalPointsData.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalTotalPointsTransaction').textContent =
                totalPointsTransaction.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Tabela Detalhada
            // ALI
            document.getElementById('modalTotalAli').textContent =
                totalAli.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalAdjustedAli').textContent =
                adjustedAli.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalPfImpactadoAli').textContent =
                pfImpactadoAli.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalValorAli').textContent =
                "R$ " + (adjustedAli * pfValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // AIE
            document.getElementById('modalTotalAie').textContent =
                totalAie.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalAdjustedAie').textContent =
                adjustedAie.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalPfImpactadoAie').textContent =
                pfImpactadoAie.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalValorAie').textContent =
                "R$ " + (adjustedAie * pfValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // EE
            document.getElementById('modalTotalEe').textContent =
                totalEe.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalAdjustedEe').textContent =
                adjustedEe.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalPfImpactadoEe').textContent =
                pfImpactadoEe.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalValorEe').textContent =
                "R$ " + (adjustedEe * pfValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // SE
            document.getElementById('modalTotalSe').textContent =
                totalSe.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalAdjustedSe').textContent =
                adjustedSe.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalPfImpactadoSe').textContent =
                pfImpactadoSe.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalValorSe').textContent =
                "R$ " + (adjustedSe * pfValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // CE
            document.getElementById('modalTotalCe').textContent =
                totalCe.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalAdjustedCe').textContent =
                adjustedCe.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalPfImpactadoCe').textContent =
                pfImpactadoCe.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalValorCe').textContent =
                "R$ " + (adjustedCe * pfValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Exibe o Modal de Resultados
            showModal('resultModal');
        }

        function printAnalysis() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');

            // Configurações Gerais
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const margin = 20;
            let currentY = margin;

            // Cabeçalho/Imagem
            const headerImg = 'img/Logo.jpg'; 
            const imgWidth = 30;
            const imgHeight = 30;
            doc.addImage(headerImg, 'JPEG', (pageWidth - imgWidth) / 2, currentY, imgWidth, imgHeight);
            currentY += imgHeight + 10;

            // Título
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(16);
            doc.setTextColor(0);
            const osNumber = document.getElementById('osNumber').value.trim();
            const title = osNumber
                ? `Resultado da Análise de Pontos de Função - OS ${osNumber}`
                : 'Resultado da Análise de Pontos de Função';
            doc.text(title, pageWidth / 2, currentY, { align: 'center' });
            currentY += 10;

            // Separador
            doc.setDrawColor(0);
            doc.setLineWidth(0.5);
            doc.line(margin, currentY, pageWidth - margin, currentY);
            currentY += 10;

            // Coletar Dados do Modal
            const totalNonAdjusted = parseFloat(
                document.getElementById('modalTotalNonAdjusted').textContent
                  .replace('.', '')
                  .replace(',', '.')
            ) || 0;
            const totalAdjusted = parseFloat(
                document.getElementById('modalTotalAdjusted').textContent
                  .replace('.', '')
                  .replace(',', '.')
            ) || 0;
            const totalPfImpactado = parseFloat(
                document.getElementById('modalTotalPfImpactado').textContent
                  .replace('.', '')
                  .replace(',', '.')
            ) || 0;

            let valorPfText = document.getElementById('modalTotalValorPf').textContent || "R$ 0,00";
            let valorPfSanitized = valorPfText.replace('R$','').trim().replace(/\./g,'').replace(',','.');
            const totalValorPf = parseFloat(valorPfSanitized) || 0.00;

            // Montar os cards (4) no PDF
            const cardTitles = ['Não Ajustado', 'Ajustado', 'PF Impactado', 'Valor PF'];
            const cardValues = [
                totalNonAdjusted.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                totalAdjusted.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                totalPfImpactado.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                'R$ ' + totalValorPf.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            ];
            const cardColors = [
                [220, 53, 69],
                [40, 167, 69],
                [255, 193, 7],
                [23, 162, 184]
            ];

            const cardWidth = (pageWidth - 2 * margin - 15) / 4;
            const cardHeight = 25;

            for (let i = 0; i < 4; i++) {
                const x = margin + i * (cardWidth + 5);
                const y = currentY;

                doc.setFillColor(...cardColors[i]);
                doc.roundedRect(x, y, cardWidth, cardHeight, 3, 3, 'F');

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(10);
                doc.setTextColor(255);
                doc.text(cardTitles[i], x + cardWidth / 2, y + 8, { align: 'center' });

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(14);
                doc.setTextColor(255);
                doc.text(cardValues[i], x + cardWidth / 2, y + 18, { align: 'center' });
            }

            currentY += cardHeight + 15;

            // Detalhes do Cálculo
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(14);
            doc.setTextColor(0);
            doc.text('Detalhes do Cálculo:', margin, currentY);
            currentY += 7;

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(12);
            doc.setTextColor(0);

            // Captura cada <li> da seção de detalhes
            const listItems = document.querySelectorAll('.result-content ul.list-group li.list-group-item');
            listItems.forEach((li) => {
                const textLine = li.textContent.replace(/\r?\n|\r/g, '').trim();
                const [label, value] = textLine.split(':').map((item) => item.trim());

                doc.text(`• ${label}: ${value}`, margin + 5, currentY);
                currentY += 7;

                if (currentY > pageHeight - margin - 20) {
                    doc.addPage();
                    currentY = margin;
                    doc.addImage(headerImg, 'JPEG', (pageWidth - imgWidth) / 2, currentY, imgWidth, imgHeight);
                    currentY += imgHeight + 10;
                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(16);
                    doc.setTextColor(0);
                    doc.text(title, pageWidth / 2, currentY, { align: 'center' });
                    currentY += 10;
                    doc.setDrawColor(0);
                    doc.setLineWidth(0.5);
                    doc.line(margin, currentY, pageWidth - margin, currentY);
                    currentY += 10;
                }
            });

            currentY += 5;

            // Tabela autoTable
            const tableContent = [];
            const rows = document.querySelectorAll('.result-content table tbody tr');
            rows.forEach((row) => {
                const cells = row.querySelectorAll('td');
                const rowData = [];
                cells.forEach((cell) => {
                    rowData.push(cell.textContent.trim());
                });
                tableContent.push(rowData);
            });

            if (tableContent.length > 0) {
                doc.autoTable({
                    startY: currentY,
                    head: [['Tipo de Função', 'Não Ajustado', 'Ajustado', 'PF Impactado', 'Valor']],
                    body: tableContent,
                    theme: 'grid',
                    styles: {
                        fontSize: 10,
                        halign: 'center',
                        valign: 'middle',
                        textColor: 0,
                        fillColor: [245,245,245],
                        lineColor: [0,0,0],
                        lineWidth: 0.1
                    },
                    headStyles: {
                        fillColor: [52,58,64],
                        textColor: 255,
                        fontStyle: 'bold'
                    },
                    alternateRowStyles: {
                        fillColor: [250,250,250]
                    },
                    margin: { left: margin, right: margin },
                    didDrawPage: function (data) {
                        if (data.pageNumber > 1) {
                            doc.addImage(headerImg, 'JPEG', (pageWidth - imgWidth) / 2, margin - 15, imgWidth, imgHeight);
                            doc.setFont('helvetica', 'bold');
                            doc.setFontSize(16);
                            doc.setTextColor(0);
                            doc.text(title, pageWidth / 2, margin + 10, { align: 'center' });
                            doc.setDrawColor(0);
                            doc.setLineWidth(0.5);
                            doc.line(margin, margin + 15, pageWidth - margin, margin + 15);
                        }
                    }
                });
                currentY = doc.lastAutoTable.finalY + 10;
            }

            const currentDate = new Date();
            const dateStr = currentDate.toLocaleDateString('pt-BR');
            const timeStr = currentDate.toLocaleTimeString('pt-BR');
            const footerText = `Data e Hora de Emissão: ${dateStr} ${timeStr}`;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.setTextColor(150);
            doc.text(footerText, pageWidth / 2, pageHeight - 10, { align: 'center' });

            // Exibe no iframe e abre modal PDF
            const pdfDataUri = doc.output('datauristring');
            document.getElementById('pdfIframe').src = pdfDataUri;
            showModal('pdfModal');

            // Botão de download
            document.getElementById('downloadPdfBtn').onclick = function () {
                doc.save(`${title}.pdf`);
            };
        }
    </script>
</body>
</html>

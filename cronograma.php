<?php
// Definindo o tempo máximo da sessão para 6 horas (21600 segundos)
ini_set('session.gc_maxlifetime', 21600);
session_start();

// Configurando o cookie de sessão para ter a mesma duração
$params = session_get_cookie_params();
setcookie(session_name(), $_COOKIE[session_name()], time() + 21600,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
);

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}

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

// Incluímos o perfil 5 também, caso seja necessário
$perfisPermitidos = [1, 2, 3, 4, 5, 9]; 
verificarPermissao($perfisPermitidos);

function buscarTarefasPorAno($ano) {
    global $pdo;
    $sql = "
    SELECT c.id, c.Id_OS, c.trimestre, c.ano, c.mes, c.status, c.data_cadastro, c.usuario_id, c.Tipo, c.posicao,
           CAST(o.N_os AS CHAR CHARACTER SET utf8mb4) AS N_os,
           CAST(o.Nome_os AS CHAR CHARACTER SET utf8mb4) AS Nome_os,
           o.Apf, o.Valor, o.Dt_inicial, o.Prazo_entrega,
           o.Prioridade,
           CAST(o.Status_inova AS CHAR CHARACTER SET utf8mb4) AS Status_inova,
           CAST(o.Status_contratada AS CHAR CHARACTER SET utf8mb4) AS Status_contratada,
           o.Responsavel, o.Id_contratada,
           CAST(o.Descricao AS CHAR CHARACTER SET utf8mb4) AS OsDescricao,
           o.Os_paga,
           CAST(o.Observacao AS CHAR CHARACTER SET utf8mb4) AS Observacao
    FROM cronograma c
    JOIN os o ON o.Id = c.Id_OS
    WHERE c.ano = ? AND c.Tipo = 'OS'

    UNION ALL

    SELECT c.id, c.Id_OS, c.trimestre, c.ano, c.mes, c.status, c.data_cadastro, c.usuario_id, c.Tipo, c.posicao,
           'Backlog' AS N_os,
           CAST(b.Projeto AS CHAR CHARACTER SET utf8mb4) AS Nome_os,
           NULL AS Apf, NULL AS Valor, NULL AS Dt_inicial, NULL AS Prazo_entrega,
           NULL AS Prioridade,
           NULL AS Status_inova,
           NULL AS Status_contratada,
           NULL AS Responsavel, NULL AS Id_contratada,
           CAST(b.Descricao AS CHAR CHARACTER SET utf8mb4) AS OsDescricao,
           NULL AS Os_paga,
           '' AS Observacao
    FROM cronograma c
    JOIN backlog b ON b.Id = c.Id_OS
    WHERE c.ano = ? AND c.Tipo = 'Backlog'

    ORDER BY trimestre, mes, posicao, id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ano, $ano]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarAnosDasTarefas() {
    global $pdo;
    $stmt = $pdo->query("SELECT DISTINCT ano FROM cronograma ORDER BY ano DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarBacklogDisponiveis() {
    global $pdo;
    $sql = "SELECT b.* FROM backlog b
            WHERE b.Id NOT IN (SELECT Id_OS FROM cronograma WHERE Tipo='Backlog')
            ORDER BY b.Id ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buscarOSDisponiveis() {
    global $pdo;
    $sql = "SELECT o.* FROM os o 
            WHERE o.Id NOT IN (SELECT Id_OS FROM cronograma WHERE Tipo='OS')
              AND o.Status_contratada NOT IN ('Em Produção', 'Paralisado')
              AND o.Status_inova <> 'Não aprovado'
            ORDER BY o.N_os ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$anoAtual = date('Y');
$selectedYear = isset($_GET['year']) && is_numeric($_GET['year']) ? $_GET['year'] : $anoAtual;
$tarefasDoAno = buscarTarefasPorAno($selectedYear);

$trimestresMeses = [
    1 => [1,2,3],
    2 => [4,5,6],
    3 => [7,8,9],
    4 => [10,11,12]
];

$tarefasPorTrimestreMes = [
    1 => [1 => [], 2 => [], 3 => []],
    2 => [4 => [], 5 => [], 6 => []],
    3 => [7 => [], 8 => [], 9 => []],
    4 => [10 => [], 11 => [], 12 => []]
];

foreach ($tarefasDoAno as $tarefa) {
    $tri = $tarefa['trimestre'];
    $mes = (int)$tarefa['mes'];
    if ($tri == 1 && in_array($mes, [1,2,3])) {
        $tarefasPorTrimestreMes[1][$mes][] = $tarefa;
    } elseif ($tri == 2 && in_array($mes, [4,5,6])) {
        $tarefasPorTrimestreMes[2][$mes][] = $tarefa;
    } elseif ($tri == 3 && in_array($mes, [7,8,9])) {
        $tarefasPorTrimestreMes[3][$mes][] = $tarefa;
    } elseif ($tri == 4 && in_array($mes, [10,11,12])) {
        $tarefasPorTrimestreMes[4][$mes][] = $tarefa;
    }
}

$anosDasTarefas = buscarAnosDasTarefas();
$backlogDisponiveis = buscarBacklogDisponiveis();
$osDisponiveis = buscarOSDisponiveis();

$nomeMeses = [
    1 => 'Jan', 2 => 'Fev', 3 => 'Mar',
    4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ago', 9 => 'Set',
    10 => 'Out',11 => 'Nov',12 => 'Dez'
];

$nomesTrimestres = [
    1 => '1º Trimestre',
    2 => '2º Trimestre',
    3 => '3º Trimestre',
    4 => '4º Trimestre'
];

$currentMonth = date('n'); 
$currentTri = ceil($currentMonth/3);
?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="GATI - Gestão Ágil em TI" />
    <title>GATI - Gestão Ágil em TI - Cronograma</title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon.png" />
    <link href="assets/libs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/extra-libs/taskboard/css/lobilist.css" />
    <link rel="stylesheet" href="assets/extra-libs/taskboard/css/jquery-ui.min.css" />
    <link href="dist/css/style.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" crossorigin="anonymous" />

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
    <style>
        /* Custom Styles */

        body {
            background-color: #1f1f1f;
            color: #eaeaea;
            font-family: 'Roboto', sans-serif;
        }

        .nav-tabs .nav-link {
            color: #eaeaea;
            background-color: #1d2126;
            border: 1px solid #444;
            margin-right:5px;
            transition: background-color 0.3s, color 0.3s;
        }

        .nav-tabs .nav-link.active {
            background-color: #2d5f95;
            border-color: #2d5f95;
            color: #fff;
        }

        .tab-content {
            margin-top:20px;
        }

        .mes-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .mes-container {
                flex-direction: column;
            }
        }

        .mes-column {
            background-color: #272b34;
            border-radius: 10px;
            flex: 1;
            min-width: 250px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .mes-column:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.5);
        }

        .mes-header {
            background-color: #2d5f95;
            padding: 15px;
            text-align: center;
            font-weight: bold;
            color: #fff;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            font-size: 1.1em;
        }

        .tarefa-lista {
            flex-grow: 1;
            padding: 15px;
            min-height: 100px;
            list-style: none;
            margin: 0;
            overflow-y: auto;
        }

        .tarefa-lista li {
            background-color: #343a40;
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
            color: #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s, transform 0.3s;
            cursor: pointer;
            position: relative;
        }

        .tarefa-lista li:hover {
            background-color: #495057;
            transform: scale(1.02);
        }

        .tarefa-detalhes {
            flex-grow: 1;
            margin-right: 10px;
            display: flex; /* Para alinhamento horizontal */
            align-items: center; /* Centraliza verticalmente */
        }

        .tarefa-detalhes strong {
            font-size: 1em;
            margin-bottom: 5px;
        }

        /* Animação de Pulsação para Status */
        .animate__fadeIn {
            animation-duration: 1s;
        }

        .card-buttons {
            display: flex;
            flex-direction: row;
            gap: 10px;
        }

        .btn-edit, .btn-delete {
            background: none;
            border: none;
            color: #eaeaea;
            cursor: pointer;
            font-size: 1.2em;
            transition: color 0.3s;
        }

        .btn-edit:hover, .btn-delete:hover {
            color: #6ab04c;
        }

        /* --- Novas Regras para Evitar Flickering Durante o Drag --- */
        /* Desabilita transições nos elementos durante o arrasto */
        .tarefa-lista li.sortable-chosen,
        .tarefa-lista li.sortable-ghost {
            transition: none;
        }

        /* Ajusta a aparência do elemento fantasma */
        .sortable-ghost {
            opacity: 0.4;
        }

        /* Feedback Visual para Sucesso */
        .alert-custom {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1055;
            display: none;
        }
    </style>
</head>
<body>
    <div class="preloader"></div>
    <div id="main-wrapper">
        <header class="topbar">
            <?php include 'header.php'?>
        </header>
        <?php include 'sidebar.php'?>
        <div class="page-wrapper">
            <div class="row page-titles">
                <div class="col-md-5 col-12 align-self-center">
                    <h3 class="text-themecolor mb-0"><i class="mdi mdi-calendar"></i> Cronograma Anual</h3>
                </div>
            </div>
            <div class="container-fluid">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <button type="button" class="btn waves-effect waves-light btn-info me-2" data-bs-toggle="modal" data-bs-target="#cadastrarTarefaModal" data-bs-toggle="tooltip" data-bs-placement="top" title="Adicionar Nova Tarefa">
                            <i class="ti-plus text"></i> Cadastrar Tarefa
                        </button>
                    </div>
                    <div class="form-group">
                        <select class="form-control" id="anoFiltro" data-bs-toggle="tooltip" data-bs-placement="top" title="Filtrar por Ano">
                            <?php foreach ($anosDasTarefas as $anoTarefa): 
                                $selected = ($anoTarefa['ano'] == $selectedYear) ? 'selected' : '';
                                echo "<option value='{$anoTarefa['ano']}' $selected>{$anoTarefa['ano']}</option>";
                            endforeach;?>
                        </select>
                    </div>
                </div>

                <ul class="nav nav-tabs" id="trimestreTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link <?php echo ($currentTri==1?'active':''); ?>" id="t1-tab" data-bs-toggle="tab" data-bs-target="#t1" type="button" role="tab" aria-controls="t1" aria-selected="<?php echo ($currentTri==1?'true':'false'); ?>">1º Trimestre</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?php echo ($currentTri==2?'active':''); ?>" id="t2-tab" data-bs-toggle="tab" data-bs-target="#t2" type="button" role="tab" aria-controls="t2" aria-selected="<?php echo ($currentTri==2?'true':'false'); ?>">2º Trimestre</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?php echo ($currentTri==3?'active':''); ?>" id="t3-tab" data-bs-toggle="tab" data-bs-target="#t3" type="button" role="tab" aria-controls="t3" aria-selected="<?php echo ($currentTri==3?'true':'false'); ?>">3º Trimestre</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?php echo ($currentTri==4?'active':''); ?>" id="t4-tab" data-bs-toggle="tab" data-bs-target="#t4" type="button" role="tab" aria-controls="t4" aria-selected="<?php echo ($currentTri==4?'true':'false'); ?>">4º Trimestre</button>
                    </li>
                </ul>

                <div class="tab-content" id="myTabContent">
                    <?php foreach ($trimestresMeses as $tri => $mesesArr): 
                        $activeClass = ($tri == $currentTri) ? 'show active' : '';
                    ?>
                    <div class="tab-pane fade <?php echo $activeClass; ?>" id="t<?php echo $tri; ?>" role="tabpanel" aria-labelledby="t<?php echo $tri; ?>-tab">
                        <div class="mes-container" id="mes-container-<?php echo $tri; ?>">
                            <?php foreach($mesesArr as $mesNum): ?>
                            <div class="mes-column" data-mes="<?php echo $mesNum; ?>">
    <div class="mes-header"><?php echo $nomeMeses[$mesNum]; ?></div>
    <ul class="tarefa-lista" id="tarefa-lista-<?php echo $tri; ?>-<?php echo $mesNum; ?>">
        <?php
        if (!empty($tarefasPorTrimestreMes[$tri][$mesNum])) {
            foreach ($tarefasPorTrimestreMes[$tri][$mesNum] as $tarefa) {
                $nomeOsOriginal = $tarefa['Nome_os'];
                $nomeOsLower = mb_strtolower($nomeOsOriginal, 'UTF-8'); 
                $formattedNomeOs = mb_strtoupper(mb_substr($nomeOsLower, 0, 1, 'UTF-8'), 'UTF-8') 
                    . mb_substr($nomeOsLower, 1, null, 'UTF-8');

                $taskStatus = $tarefa['status'];
                $statusIcon = '';
                $statusClass = '';
                if ($taskStatus == 'Pendente') {
                    $statusIcon = '<i class="fas fa-hourglass-half text-danger me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Pendente"></i>';
                    $statusClass = 'pendente';
                } elseif ($taskStatus == 'Em andamento') {
                    $statusIcon = '<i class="fas fa-running text-primary me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Em Andamento"></i>';
                    $statusClass = 'em-andamento';
                } elseif ($taskStatus == 'Concluída') {
                    $statusIcon = '<i class="fas fa-check-circle text-success me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="Concluída"></i>';
                    $statusClass = 'concluida';
                }

                echo '<li class="tarefa-item ' . $statusClass . '" data-id="'.$tarefa['id'].'" onclick="viewTarefa('.$tarefa['id'].')">
                        <div class="tarefa-detalhes">
                            '.$statusIcon.'<strong>'.htmlspecialchars($tarefa['N_os'].' - '.$formattedNomeOs).'</strong>
                        </div>
                        <div class="card-buttons">
                            <button class="btn btn-edit" onclick="editTarefa('.$tarefa['id'].'); event.stopPropagation();" data-bs-toggle="tooltip" data-bs-placement="top" title="Editar Tarefa">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-delete" onclick="deleteTarefa('.$tarefa['id'].'); event.stopPropagation();" data-bs-toggle="tooltip" data-bs-placement="top" title="Excluir Tarefa">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                      </li>';
            }
        } else {
            echo '<li class="text-center text-muted">Nenhuma tarefa</li>';
        }
        ?>
    </ul>
</div>

                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Feedback de Sucesso -->
                <div class="alert alert-success alert-custom" role="alert" id="successAlert">
                    Ordem das tarefas atualizada com sucesso!
                </div>

                <!-- Modal Cadastrar Tarefa -->
                <div class="modal fade" id="cadastrarTarefaModal" tabindex="-1" aria-labelledby="cadastrarTarefaModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form id="cadastrarTarefaForm">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="cadastrarTarefaModalLabel">Cadastrar Nova Tarefa</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <!-- Form fields -->
                                    <label class="form-label">Selecione a OS/Backlog</label>
                                    <select class="form-select" id="Id_OS" name="Id_OS" required>
                                        <option value="">Selecione</option>
                                        <?php
                                        // Primeiro, backlog (com prefixo B-)
                                        foreach ($backlogDisponiveis as $b) {
                                            $nomeProj = $b['Projeto'] ?? 'Sem nome';
                                            echo "<option value='B-{$b['Id']}'>Backlog - {$nomeProj}</option>";
                                        }

                                        // Depois, OS (sem prefixo)
                                        foreach ($osDisponiveis as $os) {
                                            $nomeOsLower = mb_strtolower($os['Nome_os'], 'UTF-8'); 
                                            $formattedOs = mb_strtoupper(mb_substr($nomeOsLower, 0, 1, 'UTF-8'), 'UTF-8')
                                                . mb_substr($nomeOsLower, 1, null, 'UTF-8');
                                            echo "<option value='{$os['Id']}'>{$os['N_os']} - {$formattedOs}</option>";
                                        }
                                        ?>
                                    </select>

                                    <label for="novoTrimestre" class="form-label">Trimestre</label>
                                    <select class="form-select" id="novoTrimestre" name="trimestre" required>
                                        <option value="1">1º Trimestre</option>
                                        <option value="2">2º Trimestre</option>
                                        <option value="3">3º Trimestre</option>
                                        <option value="4">4º Trimestre</option>
                                    </select>

                                    <label for="novoMes" class="form-label">Mês de Entrega</label>
                                    <select class="form-select" id="novoMes" name="mes" required></select>

                                    <label for="novoAno" class="form-label">Ano</label>
                                    <select class="form-select" id="novoAno" name="ano" required>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear; $year <= $currentYear + 5; $year++) {
                                            echo "<option value='$year'>$year</option>";
                                        }
                                        ?>
                                    </select>

                                    <label for="novoStatus" class="form-label">Status</label>
                                    <select class="form-select" id="novoStatus" name="status" required>
                                        <option value="Pendente">Pendente</option>
                                        <option value="Em andamento">Em andamento</option>
                                        <option value="Concluída">Concluída</option>
                                    </select>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Salvar Nova Tarefa">Cadastrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar Tarefa -->
                <div class="modal fade" id="editTarefaModal" tabindex="-1" aria-labelledby="editTarefaModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form id="editTarefaForm">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editTarefaModalLabel">Editar Tarefa</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" id="editId" name="id">

                                    <p style="color:#ccc; font-size:0.9em;">
                                        Ao editar, você pode alterar o trimestre, o mês, o ano e o status.
                                    </p>

                                    <label for="editTrimestre" class="form-label">Trimestre</label>
                                    <select class="form-select" id="editTrimestre" name="trimestre" required>
                                        <option value="1">1º Trimestre</option>
                                        <option value="2">2º Trimestre</option>
                                        <option value="3">3º Trimestre</option>
                                        <option value="4">4º Trimestre</option>
                                    </select>

                                    <label for="editMes" class="form-label">Mês de Entrega</label>
                                    <select class="form-select" id="editMes" name="mes" required></select>

                                    <label for="editAno" class="form-label">Ano</label>
                                    <select class="form-select" id="editAno" name="ano" required>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear; $year <= $currentYear + 5; $year++) {
                                            echo "<option value='$year'>$year</option>";
                                        }
                                        ?>
                                    </select>

                                    <label for="editStatus" class="form-label">Status</label>
                                    <select class="form-select" id="editStatus" name="status" required>
                                        <option value="Pendente">Pendente</option>
                                        <option value="Em andamento">Em andamento</option>
                                        <option value="Concluída">Concluída</option>
                                    </select>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Salvar Alterações da Tarefa">Salvar Alterações</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal Visualizar Tarefa -->
                <div class="modal fade" id="viewTarefaModal" tabindex="-1" aria-labelledby="viewTarefaModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content" style="background-color:#2b2b2b; color:#eaeaea; max-height:90vh; overflow:auto;">
                            <div class="modal-header" style="background-color:#1f1f1f; border-bottom:1px solid #555;">
                                <h5 class="modal-title" id="viewTarefaModalLabel" style="color:#eaeaea;">Detalhes</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body" style="font-size:0.95em;">

                                <h6 id="viewTipoTitulo" style="border-bottom:1px solid #444; padding-bottom:5px; margin-bottom:15px;"></h6>
                                <p><strong id="viewTipoLabel"></strong> <span id="viewN_os"></span> - <span id="viewNome_os"></span></p>
                                <p><strong>Descrição:</strong> <span id="viewDescricao"></span></p>

                                <h6 style="border-bottom:1px solid #444; padding-bottom:5px; margin:15px 0;">Informações da Tarefa</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Trimestre:</strong> <span id="viewTrimestre"></span></p>
                                        <p><strong>Ano:</strong> <span id="viewAno"></span></p>
                                        <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                                        <p><strong>Data de Cadastro:</strong> <span id="viewDataCadastro"></span></p>
                                    </div>
                                    <div class="col-md-6" id="viewOSFields">
                                        <p><strong>APF:</strong> <span id="viewApf"></span></p>
                                        <p><strong>Valor:</strong> <span id="viewValor"></span></p>
                                        <p><strong>Data Inicial:</strong> <span id="viewDt_inicial"></span></p>
                                        <p><strong>Prazo Entrega:</strong> <span id="viewPrazo_entrega"></span></p>
                                    </div>
                                </div>

                                <div id="viewOSAdicionais">
                                    <h6 style="border-bottom:1px solid #444; padding-bottom:5px; margin:15px 0;">Detalhes Adicionais</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Prioridade:</strong> <span id="viewPrioridade"></span></p>
                                            <p><strong>Status Inova:</strong> <span id="viewStatus_inova"></span></p>
                                            <p><strong>Status Contratada:</strong> <span id="viewStatus_contratada"></span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>OS Paga:</strong> <span id="viewOs_paga"></span></p>
                                        </div>
                                    </div>
                                </div>

                                <h6 style="border-bottom:1px solid #444; padding-bottom:5px; margin:15px 0;">Observação</h6>
                                <p><span id="viewObservacao"></span></p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <?php include 'footer.php' ?>
        </div>
    </div>

    <!-- -------------------------------------------------------------- -->
    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery-ui.min.js"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <script src="assets/libs/popper.js/popper.min.js"></script>
    <script src="assets/libs/bootstrap/dist/js/bootstrap.min.js"></script>
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
    <script src="assets/libs/moment/min/moment.min.js"></script>
    <script src="assets/libs/fullcalendar/dist/fullcalendar.min.js"></script>
    <script src="dist/js/pages/calendar/cal-init.js"></script>

    <script>
    const trimestreMesMap = {
        1: [ {val:1,text:'Jan'}, {val:2,text:'Fev'}, {val:3,text:'Mar'} ],
        2: [ {val:4,text:'Abr'}, {val:5,text:'Mai'}, {val:6,text:'Jun'} ],
        3: [ {val:7,text:'Jul'}, {val:8,text:'Ago'}, {val:9,text:'Set'} ],
        4: [ {val:10,text:'Out'}, {val:11,text:'Nov'}, {val:12,text:'Dez'} ]
    };

    function formatDateBr(dateStr) {
        if(!dateStr || dateStr=='0000-00-00') return '';
        const parts = dateStr.split('-');
        if(parts.length===3) {
            return parts[2]+'/'+parts[1]+'/'+parts[0];
        }
        return dateStr;
    }

    function formatValor(valor) {
        if(!valor) return '';
        let val = parseFloat(valor);
        if(isNaN(val)) val=0;
        return val.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
    }

    function decodeHtmlEntities(str) {
        if(!str) return '';
        const temp = document.createElement('div');
        temp.innerHTML = str;
        return temp.textContent || temp.innerText || '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Inicializa os tooltips do Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        console.log("Tooltips inicializados.");

        // Feedback de Sucesso
        const successAlert = document.getElementById('successAlert');
        function showSuccessAlert(message) {
            successAlert.textContent = message;
            successAlert.style.display = 'block';
            setTimeout(() => {
                successAlert.style.display = 'none';
            }, 3000);
        }

        // Atualização do Mês de acordo com o Trimestre no Cadastro
        const novoTrimestre = document.getElementById('novoTrimestre');
        if(novoTrimestre) {
            novoTrimestre.addEventListener('change', function() {
                console.log("Trimestre no Cadastro alterado para:", this.value);
                updateMesOptions('novoTrimestre','novoMes');
            });
            updateMesOptions('novoTrimestre','novoMes');
        }

        // Atualização do Mês de acordo com o Trimestre na Edição
        const editTrimestre = document.getElementById('editTrimestre');
        if(editTrimestre) {
            editTrimestre.addEventListener('change', function() {
                console.log("Trimestre na Edição alterado para:", this.value);
                updateMesOptions('editTrimestre','editMes');
            });
            updateMesOptions('editTrimestre','editMes');
        }

        // Filtro de Ano
        const anoFiltro = document.getElementById('anoFiltro');
        if(anoFiltro){
            anoFiltro.addEventListener('change', function() {
                const ano = this.value;
                console.log("Filtro de Ano alterado para:", ano);
                // Recarrega a página com o parâmetro year
                window.location.href = 'cronograma?year=' + ano;
            });
        }

        // Cadastro de Tarefa
        const cadastrarTarefaForm = document.getElementById('cadastrarTarefaForm');
        if(cadastrarTarefaForm){
            cadastrarTarefaForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log("Formulário de Cadastro de Tarefa submetido.");
                const formData = new FormData(this);
                fetch('cadastrar_tarefa.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        console.log("Tarefa cadastrada com sucesso.");
                        showSuccessAlert("Tarefa cadastrada com sucesso!");
                        // Opcional: Atualizar a lista de tarefas sem recarregar a página
                        window.location.reload(); // Remova esta linha se desejar evitar o recarregamento
                    } else {
                        console.error('Erro ao cadastrar tarefa:', data.message);
                        alert('Erro ao cadastrar tarefa: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error('Erro na requisição de cadastro:', err);
                });
            });
        }

        // Edição de Tarefa
        const editTarefaForm = document.getElementById('editTarefaForm');
        if(editTarefaForm){
            editTarefaForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log("Formulário de Edição de Tarefa submetido.");
                const formData = new FormData(this);
                fetch('editar_tarefa.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        console.log("Tarefa editada com sucesso.");
                        showSuccessAlert("Tarefa editada com sucesso!");
                        // Opcional: Atualizar a lista de tarefas sem recarregar a página
                        window.location.reload(); // Remova esta linha se desejar evitar o recarregamento
                    } else {
                        console.error('Erro ao editar tarefa:', data.message);
                        alert('Erro ao editar tarefa: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error('Erro na requisição de edição:', err);
                });
            });
        }

        // Inicialização do Drag and Drop com SortableJS
        const listasTarefas = document.querySelectorAll('.tarefa-lista');
        if(listasTarefas.length > 0){
            listasTarefas.forEach(list => {
                new Sortable(list, {
                    group: 'shared',
                    animation: 150,
                    ghostClass: 'sortable-ghost', // Classe para o elemento fantasma
                    chosenClass: 'sortable-chosen', // Classe para o elemento escolhido
                    dragClass: 'sortable-drag', // Classe para o elemento sendo arrastado
                    onEnd: function(evt) {
                        console.log("Tarefa movida:", evt.item);
                        const newMonthColumn = evt.to.closest('.mes-column');
                        const tabPane = newMonthColumn.closest('.tab-pane');
                        const newTrimestre = tabPane.id.replace('t',''); 
                        const newMes = newMonthColumn.getAttribute('data-mes');
                        const ids = Array.from(evt.to.querySelectorAll('li')).map(li => li.getAttribute('data-id'));
                        console.log("Novo Trimestre:", newTrimestre, "Novo Mês:", newMes, "IDs das Tarefas:", ids);

                        fetch('atualizar_trimestre.php', {
                            method: 'POST',
                            headers: {'Content-Type':'application/json'},
                            body: JSON.stringify({ids: ids, trimestre:newTrimestre, mes:newMes})
                        })
                        .then(r=>r.json())
                        .then(data=>{
                            if(!data.success) {
                                console.error('Erro ao atualizar a ordem das tarefas:', data.message);
                                alert('Erro ao atualizar a ordem das tarefas.');
                                // Opcional: Reverter a ordem se ocorrer um erro
                                window.location.reload(); // Pode ser removido para evitar recarregamento
                            } else {
                                console.log("Ordem das tarefas atualizada com sucesso.");
                                showSuccessAlert("Ordem das tarefas atualizada com sucesso!");
                                // Não recarregar a página para evitar flicker
                            }
                        })
                        .catch(err=>{
                            console.error('Erro na requisição de atualização:', err);
                            alert('Erro na requisição de atualização.');
                        });
                    }
                });
            });
            console.log("Sortable.js inicializado nas listas de tarefas.");
        } else {
            console.warn("Nenhuma lista de tarefas encontrada para Sortable.js.");
        }
    });

    // Atualização de Opções de Mês com Base no Trimestre
    function updateMesOptions(triSelectId, mesSelectId) {
        const tri = document.getElementById(triSelectId);
        if (!tri) return;
        const mesSelect = document.getElementById(mesSelectId);
        if (!mesSelect) return;

        mesSelect.innerHTML = '';
        if (tri.value in trimestreMesMap) {
            trimestreMesMap[tri.value].forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.val;
                opt.textContent = m.text;
                mesSelect.appendChild(opt);
            });
            console.log(`Opções de mês atualizadas para Trimestre ${tri.value}.`);
        }
    }

    // Função para Excluir Tarefa
    function deleteTarefa(id) {
        if (!confirm('Deseja realmente excluir esta tarefa?')) return;
        console.log(`Solicitando exclusão da tarefa ID: ${id}`);
        fetch('deletar_tarefa.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({id: id})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                console.log("Tarefa excluída com sucesso.");
                showSuccessAlert("Tarefa excluída com sucesso!");
                // Remover a tarefa do DOM sem recarregar
                const tarefaElemento = document.querySelector(`li[data-id="${id}"]`);
                if(tarefaElemento){
                    tarefaElemento.remove();
                }
            } else {
                console.error('Erro ao excluir tarefa:', data.message);
                alert('Erro ao excluir tarefa: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Erro na requisição de exclusão:', err);
        });
    }

    // Função para Editar Tarefa
    function editTarefa(id) {
        console.log(`Buscando dados para editar a tarefa ID: ${id}`);
        fetch('buscar_tarefa.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('editId').value = data.tarefa.id;
                document.getElementById('editTrimestre').value = data.tarefa.trimestre;
                updateMesOptions('editTrimestre','editMes');
                document.getElementById('editMes').value = data.tarefa.mes;
                document.getElementById('editAno').value = data.tarefa.ano;
                document.getElementById('editStatus').value = data.tarefa.status;

                const editTarefaModal = new bootstrap.Modal(document.getElementById('editTarefaModal'));
                editTarefaModal.show();
                console.log("Modal de Edição de Tarefa aberto.");
            } else {
                console.error('Erro ao buscar tarefa:', data.message);
                alert('Erro ao buscar tarefa: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Erro na requisição de busca:', err);
        });
    }

    // Função para Visualizar Tarefa
    function viewTarefa(id) {
        console.log(`Buscando dados para visualizar a tarefa ID: ${id}`);
        fetch('buscar_tarefa.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let nomeOsJS = data.tarefa.Nome_os || '';
                nomeOsJS = nomeOsJS.toLowerCase();
                nomeOsJS = nomeOsJS.charAt(0).toUpperCase() + nomeOsJS.slice(1);

                const descricaoDecodificada = decodeHtmlEntities(data.tarefa.OsDescricao || 'Nenhuma descrição.');
                const descricaoFormatada = descricaoDecodificada.replace(/\n/g, '<br>');

                const tipo = data.tarefa.Tipo; 
                const viewTipoTitulo = document.getElementById('viewTipoTitulo');
                const viewTipoLabel = document.getElementById('viewTipoLabel');
                const viewOSFields = document.getElementById('viewOSFields');
                const viewOSAdicionais = document.getElementById('viewOSAdicionais');

                if (tipo === 'Backlog') {
                    viewTipoTitulo.textContent = 'Informações do Backlog';
                    viewTipoLabel.textContent = 'Backlog:';
                    viewOSFields.style.display = 'none';
                    viewOSAdicionais.style.display = 'none';
                } else {
                    viewTipoTitulo.textContent = 'Informações da OS';
                    viewTipoLabel.textContent = 'OS:';
                    viewOSFields.style.display = 'block';
                    viewOSAdicionais.style.display = 'block';
                }

                document.getElementById('viewN_os').textContent = data.tarefa.N_os || '';
                document.getElementById('viewNome_os').textContent = nomeOsJS;
                document.getElementById('viewDescricao').innerHTML = descricaoFormatada;
                document.getElementById('viewTrimestre').textContent = data.tarefa.trimestre + 'º Trimestre';
                document.getElementById('viewAno').textContent = data.tarefa.ano;
                document.getElementById('viewStatus').textContent = data.tarefa.status;
                document.getElementById('viewDataCadastro').textContent = formatDateBr(data.tarefa.data_cadastro);

                document.getElementById('viewApf').textContent = data.tarefa.Apf || '';
                document.getElementById('viewValor').textContent = formatValor(data.tarefa.Valor);
                document.getElementById('viewDt_inicial').textContent = formatDateBr(data.tarefa.Dt_inicial);
                document.getElementById('viewPrazo_entrega').textContent = formatDateBr(data.tarefa.Prazo_entrega);
                document.getElementById('viewPrioridade').textContent = data.tarefa.Prioridade || '';
                document.getElementById('viewStatus_inova').textContent = data.tarefa.Status_inova || '';
                document.getElementById('viewStatus_contratada').textContent = data.tarefa.Status_contratada || '';
                document.getElementById('viewOs_paga').textContent = data.tarefa.Os_paga == 1 ? 'Sim' : 'Não';
                document.getElementById('viewObservacao').textContent = data.tarefa.Observacao || '';

                // Re-inicializa os tooltips dentro do modal
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('#viewTarefaModal [data-bs-toggle="tooltip"]'))
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                  return new bootstrap.Tooltip(tooltipTriggerEl)
                });

                const viewTarefaModal = new bootstrap.Modal(document.getElementById('viewTarefaModal'));
                viewTarefaModal.show();
                console.log("Modal de Visualização de Tarefa aberto.");
            } else {
                console.error('Erro ao buscar tarefa:', data.message);
                alert('Erro ao buscar tarefa: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Erro na requisição de visualização:', err);
        });
    }

    function addTarefaMes(trimestre, mes) {
        alert('Adição de tarefa desabilitada. Por favor, use o botão "Cadastrar Tarefa" no topo.');
    }
    </script>

</body>
</html>

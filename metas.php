<?php
// ============================
// Ajuste para cadastrar a meta
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'])) {
    // Aqui vamos inserir a nova meta no banco, usando as variáveis do formulário
    // Ajuste conforme sua tabela e campos
    include 'db.php';

    $nome       = $_POST['nome']       ?? '';
    $ano        = $_POST['ano']        ?? date('Y');
    $mes        = $_POST['mes']        ?? 1;
    $status     = $_POST['status']     ?? 'Pendente';
    $descricao  = $_POST['descricao']  ?? '';

    // Gerar a data de cadastro (hoje)
    $dataCadastro = date('Y-m-d');

    // Montar a data de prazo com base em ANO + MÊS => último dia do mês
    $ultimoDiaMes = date('t', strtotime("$ano-$mes-01")); 
    $prazo = "$ano-$mes-$ultimoDiaMes"; // Ex: 2024-12-31

    session_start();
    $usuarioId = $_SESSION['usuario_id'] ?? 1;

    // INSERT no banco
    $stmt = $pdo->prepare("
        INSERT INTO metas (nome, descricao, status, prazo, data_cadastro, ano, usuario_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nome,
        $descricao,
        $status,
        $prazo,
        $dataCadastro,
        $ano,
        $usuarioId
    ]);

    // Redirecionar ou simplesmente recarregar a página
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// ============================
// CONTINUA: Sessão, includes etc.
// ============================

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
    header('Location: login.php');
    exit;
}

// Incluir db.php para conexão com o banco de dados
include 'db.php';

$anoAtual = date('Y');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : $anoAtual;

// Perfil de Acesso do Usuário
$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

// Função para verificar permissões
function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}

$perfisPermitidos = [1,2,3,4,5,9];
verificarPermissao($perfisPermitidos);

// Buscar anos das metas para popular o dropdown
function buscarAnosDasMetas() {
    global $pdo;
    $stmt = $pdo->query("SELECT DISTINCT ano FROM metas ORDER BY ano DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$anosDasMetas = buscarAnosDasMetas();

// Função para buscar metas (com/sem filtragem por ano)
function buscarMetasPorAnoComUsuario($ano) {
    global $pdo, $perfilAcesso;

    // Define quais perfis o usuário logado pode visualizar
    $perfisPermitidos = [];
    switch ($perfilAcesso) {
        case 1:
            $perfisPermitidos = [1, 2, 3, 4, 9];
            break;
        case 9:
            $perfisPermitidos = [1, 2, 3, 4, 5, 9];
            break;
        case 3:
            $perfisPermitidos = [1, 2, 3, 4, 9];
            break;
        case 4:
            $perfisPermitidos = [1, 2, 3, 4, 9];
            break;
        case 5:
            $perfisPermitidos = [2, 5, 9];
            break;
        default:
            return [];
    }

    if ($ano === 'all') {
        $stmt = $pdo->prepare("
            SELECT m.*, u.Nome as usuario_nome
            FROM metas m
            JOIN usuarios u ON m.usuario_id = u.Id
            WHERE u.PerfilAcesso IN (" . implode(',', $perfisPermitidos) . ")
            ORDER BY m.ano DESC, m.prazo ASC
        ");
        $stmt->execute();
    } else {
        if (!is_numeric($ano)) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT m.*, u.Nome as usuario_nome
            FROM metas m
            JOIN usuarios u ON m.usuario_id = u.Id
            WHERE m.ano = ? AND u.PerfilAcesso IN (" . implode(',', $perfisPermitidos) . ")
            ORDER BY m.prazo ASC
        ");
        $stmt->execute([$ano]);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$metasDoAno = buscarMetasPorAnoComUsuario($selectedYear);

// Cálculo de progresso e ordenação
foreach ($metasDoAno as $index => $meta) {
    $dataCadastro = new DateTime($meta['data_cadastro']);
    $prazoFinal   = new DateTime($meta['prazo']);
    $hoje         = new DateTime();

    $intervaloTotal = $dataCadastro->diff($prazoFinal)->days;
    $diasRestantes  = ($hoje < $prazoFinal) ? $hoje->diff($prazoFinal)->days : 0;

    $porcentagemProgresso = ($intervaloTotal > 0)
        ? max(0, min(100, (100 - ($diasRestantes / $intervaloTotal) * 100)))
        : 0;

    $metasDoAno[$index]['dias_rest']  = $diasRestantes;
    $metasDoAno[$index]['porc_prog'] = $porcentagemProgresso;
}

// Ordenar por dias restantes
function ordenarPorDiasRestantes($a, $b) {
    $dataHoje = new DateTime();
    $prazoA = new DateTime($a['prazo']);
    $prazoB = new DateTime($b['prazo']);
    $diasRestantesA = $dataHoje->diff($prazoA)->days;
    $diasRestantesB = $dataHoje->diff($prazoB)->days;

    if ($diasRestantesA == $diasRestantesB) return 0;
    return ($diasRestantesA < $diasRestantesB) ? -1 : 1;
}
usort($metasDoAno, 'ordenarPorDiasRestantes');

// 'Concluída' fica por último
usort($metasDoAno, function ($a, $b) {
    if ($a['status'] == 'Concluída' && $b['status'] != 'Concluída') {
        return 1;
    } elseif ($b['status'] == 'Concluída' && $a['status'] != 'Concluída') {
        return -1;
    } else {
        return 0;
    }
});
?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">
<head>
    <?php include 'head.php'; ?>
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: #1f2029;
            color: #f5f5f5;
            margin: 0; 
            padding: 0;
        }
        .page-titles {
            margin-top: 0px;
        }
        .row { 
            margin-right: 0; 
            margin-left: 0; 
        }

        /* ============================================= */
        /*         CARD COM RIBBON DIAGONAL STATUS       */
        /* ============================================= */
        .card {
            position: relative;
            border: none;
            border-radius: 10px;
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(8px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            padding: 20px;
            animation: fadeUp 0.6s ease forwards;
            overflow: hidden;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }
        @keyframes fadeUp {
            0% { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            100% { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        .card:hover {
            transform: scale(1.02) translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.35);
        }

        /* Ribbon diagonal no topo */
        .ribbon-status {
            position: absolute;
            top: 248px;
            left: -115px;
            width: 897px; 
            height: 35px;
            color: #fff;
            text-align: center;
            line-height: 30px;
            font-size: 0.75rem;
            font-weight: bold;
            transform: rotate(-45deg);
            text-transform: uppercase;
            box-shadow: 0 3px 6px rgba(0,0,0,0.3);
        }
        /* Ajuste de cor por status */
        .pendente .ribbon-status {
            background: #7a7321;
        }
        .em-andamento .ribbon-status {
            background: #00bcd4;
        }
        .concluida .ribbon-status {
            background: #00c292;
        }
        .atrasado .ribbon-status {
            background: #b96262;
        }

        /* Badge "Meta X" */
        .badge.bg-success {
            font-size: 0.9rem;
            padding: 6px 15px;
            border-radius: 20px;
            color: #fff;
            background: #2ecc71;
        }

        /* Top row do card: "Meta X" + botões */
        .top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .card-buttons {
            display: flex; 
            gap: 10px;
        }
        .btn-view, .btn-edit, .btn-delete {
            background: none; 
            border: none;
            color: #fff; 
            font-size: 1.1rem;
            cursor: pointer; 
            transition: 0.3s; 
            opacity: 0.7;
        }
        .btn-view:hover, .btn-edit:hover, .btn-delete:hover {
            opacity: 1; 
            color: #198754;
        }

        /* Título principal */
        .card-title {
            font-size: 1.1rem; 
            font-weight: 600;
            text-transform: uppercase; 
            margin: 0 0 4px;
        }

        .card-date {
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        /* Efeito leve neon por status */
        .pendente {
            box-shadow: inset 0 0 0 6px rgb(122 115 33 / 22%);
        }
        .em-andamento {
            box-shadow: inset 0 0 0 6px rgba(0,188,212, 0.2);
        }
        .concluida {
            box-shadow: inset 0 0 0 6px rgba(0,194,146, 0.2);
        }
        .atrasado {
            box-shadow: inset 0 0 0 6px rgba(255,0,0, 0.2);
        }

        /* ============================================= */
        /*          CÍRCULO ANIMADO + COUNT-UP           */
        /* ============================================= */
        .circle-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 15px;
            position: relative;
            border: none !important;
            outline: none !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        .circle-container svg {
            transform: rotate(-90deg);
            width: 100%; 
            height: 100%;
        }
        .circle-bg {
            fill: none;
            stroke: rgba(255,255,255,0.2);
            stroke-width: 10;
        }
        .circle-progress {
            fill: none;
            stroke-width: 10;
            stroke-linecap: round;
            /* Se r=70 => Circunferência ~ 440 */
            stroke-dasharray: 440; 
            stroke-dashoffset: 440;
            transition: stroke-dashoffset 2.2s ease;
        }
        .pendente .circle-progress { 
            stroke: #7a7321; 
        }
        .em-andamento .circle-progress { 
            stroke: #00bcd4; 
        }
        .concluida .circle-progress { 
            stroke: #00c292; 
        }
        .atrasado .circle-progress { 
            stroke: #ff0000; 
        }

        .circle-text {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            text-align: center; 
            font-size: 0.75rem;
            color: #fff;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.6);
            white-space: nowrap; /* Não quebra linha */
        }

        /* ============================================= */
        /*               FORMULÁRIO E INPUTS             */
        /* ============================================= */
        .input-group {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px; 
            padding: 20px;
            min-height: 50px;
            flex-grow: 1;
            margin-top: -20px;
            margin-bottom: 0px;
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
            border: 1px solid #fff;
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

        /* ============================================= */
        /*               MODAIS E FORMULÁRIOS           */
        /* ============================================= */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 9999;
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%;
            background: rgba(0,0,0,0.6); 
            overflow: auto;
            padding-top: 10px;
        }
        .modal-content {
            background: #292d3e; 
            margin: 2% auto; 
            border-radius: 8px;
            width: 50%; 
            padding: 20px; 
            color: #fff; 
            position: relative;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }
        .modal-header {
            display: flex; 
            justify-content: space-between;
            align-items: center; 
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 15px;
        }
        .modal-header h2 {
            font-size: 1.5rem; 
            display: flex; 
            gap: 10px; 
            align-items: center;
        }
        .close {
            font-size: 25px; 
            font-weight: bold; 
            cursor: pointer;
            color: #aaa; 
            transition: 0.2s;
        }
        .close:hover { 
            color: #fff; 
        }
        .modal-body label {
            display: block; 
            margin-bottom: 5px; 
            font-weight: 500;
        }
        .modal-body input[type="text"],
        .modal-body textarea,
        .modal-body select {
            width: 100%; 
            padding: 10px; 
            margin-bottom: 15px;
            border-radius: 6px; 
            outline: none;
            background: #3a3f51; 
            color: #fff;
        }
        .modal-footer {
            text-align: right;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 15px;
        }
        .modal-footer button {
            background: linear-gradient(to right, #fc4b6c, #f68685);
            border: none; 
            padding: 10px 20px; 
            border-radius: 6px;
            color: #fff; 
            cursor: pointer; 
            transition: 0.3s;
        }
        .modal-footer button:hover { 
            opacity: 0.8; 
        }

        @media (max-width: 768px) {
            .modal-content { 
                width: 90%; 
            }
            .circle-container { 
                width: 120px; 
                height: 120px; 
            }
        }
    </style>
</head>
<body>
    <div class="preloader"></div>
    <div id="main-wrapper">
        <header class="topbar">
            <?php include 'header.php'; ?>
        </header>
        <?php include 'sidebar.php'; ?>

        <div class="page-wrapper">
            <div class="row page-titles">
                <div class="col-md-5 col-12 align-self-center">
                    <h3 class="text-themecolor mb-0">
                        <i class="mdi mdi-target"></i> Metas
                    </h3>
                </div>
            </div>

            <div class="container-fluid">
                <!-- Container flex -->
                <div class="d-md-flex align-items-center justify-content-between" style="margin-bottom: 20px;">
                    <!-- Botão de cadastrar -->
                    <button class="btn waves-effect waves-light btn-info" onclick="openModal('addMetaModal')">
                        <i class="ti-plus"></i> Cadastrar Meta
                    </button>

                    <!-- Form com select de ano -->
                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
                        <select class="form-select"
                                name="year"
                                onchange="this.form.submit()"
                                style="background:#272b34;color:#fff;border:none;padding:7px 15px;border-radius:5px;"
                        >
                            <?php foreach ($anosDasMetas as $anoMeta): ?>
                                <option
                                    value="<?php echo $anoMeta['ano']; ?>"
                                    <?php echo ($anoMeta['ano'] == $selectedYear) ? 'selected' : ''; ?>
                                >
                                    <?php echo $anoMeta['ano']; ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="all" <?php echo ($selectedYear == 'all') ? 'selected' : ''; ?>>
                                Todos os Anos
                            </option>
                        </select>
                    </form>
                </div>
                <br>

                <div class="row">
                    <?php if (!empty($metasDoAno)): ?>
                        <?php $posicao = 1; ?>
                        <?php foreach ($metasDoAno as $meta): ?>
                            <?php
                                $prazoFinal      = new DateTime($meta['prazo']);
                                $hoje            = new DateTime();
                                $diasRestantes   = $hoje->diff($prazoFinal)->format("%a");
                                $statusClasse    = strtolower(str_replace(' ', '-', $meta['status']));
                                $statusConcluido = ($meta['status'] === 'Concluída');
                                $estaAtrasado    = (!$statusConcluido && ($hoje > $prazoFinal));

                                // Cálculo do offset
                                // Se r=70 => ~440 de circunferência
                                $circleCircumference = 440; 
                                $offset = $circleCircumference - ($circleCircumference * ($meta['porc_prog'] / 100));
                            ?>
                            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                <div class="card meta-card 
                                    <?php echo $estaAtrasado ? 'atrasado' : $statusClasse; ?>"
                                >
                                    <!-- Ribbon diagonal do status -->
                                    <div class="ribbon-status">
                                        <?php
                                            if ($estaAtrasado) {
                                                echo "Atrasado";
                                            } else {
                                                echo htmlspecialchars($meta['status']);
                                            }
                                        ?>
                                    </div>

                                    <!-- Topo com "Meta X" e botões -->
                                    <div class="top-row">
                                        <span class="badge bg-success">
                                            Meta <?php echo $posicao; ?>
                                        </span>
                                        <div class="card-buttons">
                                            <button class="btn-view" onclick="viewMeta(<?php echo $meta['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-edit" onclick="editCard(<?php echo $meta['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-delete" onclick="deleteCard(<?php echo $meta['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Título -->
                                    <h4 class="card-title">
                                        <?php echo htmlspecialchars($meta['nome']); ?>
                                    </h4>

                                    <!-- Data de Entrega -->
                                    <p class="card-date">
                                        <strong>Data de Entrega:</strong>
                                        <?php echo date('d/m/Y', strtotime($meta['prazo'])); ?>
                                    </p>

                                    <!-- Círculo animado -->
                                    <div 
                                        class="circle-container
                                            <?php echo $estaAtrasado ? 'atrasado' : $statusClasse; ?>"
                                        data-offset="<?php echo $offset; ?>"
                                        data-dias="<?php echo $diasRestantes; ?>"
                                        data-status="<?php 
                                            if ($estaAtrasado) echo 'atrasado';
                                            else if ($statusConcluido) echo 'concluida';
                                            else echo 'normal';
                                        ?>"
                                    >
                                        <svg>
                                            <!-- Ajuste para r=70 => cx/cy=75 -->
                                            <circle class="circle-bg" cx="75" cy="75" r="70"></circle>
                                            <circle class="circle-progress" cx="75" cy="75" r="70"></circle>
                                        </svg>
                                        <div class="circle-text"></div>
                                    </div>
                                </div>
                            </div>
                            <?php $posicao++; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <p class="text-center">Nenhuma meta encontrada para o ano selecionado.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Modal - Adicionar Meta -->
                <div id="addMetaModal" class="modal">
                    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 class="modal-title" id="modalCadastroMetaLabel">
                                    <i class="mdi mdi-target"></i> Cadastrar Meta
                                </h2>
                                <span class="close" onclick="closeModal('addMetaModal')">&times;</span>
                            </div>
                            <form id="addMetaForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                <div class="modal-body">
                                    <label for="nome">Nome da Meta</label>
                                    <input class="form-control" type="text" id="nome" name="nome" required>

                                    <label for="ano">Ano da Meta</label>
                                    <select class="form-control" id="ano" name="ano" required>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear; $year <= $currentYear + 5; $year++) {
                                            $selected = ($year == $anoAtual) ? 'selected' : '';
                                            echo "<option value='$year' $selected>$year</option>";
                                        }
                                        ?>
                                    </select>

                                    <label for="mes">Mês do Prazo</label>
                                    <select class="form-control" id="mes" name="mes" required>
                                        <?php
                                        $meses = [
                                            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                                            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                                            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
                                        ];
                                        $mesAtual = date('n');
                                        foreach ($meses as $numero => $nome) {
                                            $selected = ($numero == $mesAtual) ? 'selected' : '';
                                            echo "<option value='{$numero}' {$selected}>{$nome}</option>";
                                        }
                                        ?>
                                    </select>

                                    <label for="status">Status da Meta</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="Pendente">Pendente</option>
                                        <option value="Em andamento">Em andamento</option>
                                        <option value="Concluída">Concluída</option>
                                    </select>

                                    <label for="descricao">Descrição</label>
                                    <textarea class="form-control" id="descricao" name="descricao" rows="4"
                                              placeholder="Descreva a meta..."></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit">Cadastrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal - Editar Meta -->
                <div id="editMetaModal" class="modal">
                    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 class="modal-title" id="modalEdicaoMetaLabel">
                                    <i class="mdi mdi-pencil"></i> Editar Meta
                                </h2>
                                <span class="close" onclick="closeModal('editMetaModal')">&times;</span>
                            </div>
                            <form id="editMetaForm" method="post" enctype="multipart/form-data">
                                <input type="hidden" id="editId" name="id">
                                <div class="modal-body">
                                    <label for="editNome">Nome da Meta</label>
                                    <input type="text" class="form-control" id="editNome" name="nome" required>

                                    <label for="editAno">Ano da Meta</label>
                                    <select id="editAno" class="form-control" name="ano" required>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear; $year <= $currentYear + 5; $year++) {
                                            echo "<option value='$year'>$year</option>";
                                        }
                                        ?>
                                    </select>

                                    <label for="editMes">Mês do Prazo</label>
                                    <select id="editMes" class="form-control" name="mes" required>
                                        <?php
                                        foreach ($meses as $numero => $nome) {
                                            echo "<option value='{$numero}'>{$nome}</option>";
                                        }
                                        ?>
                                    </select>

                                    <label for="editStatus">Status da Meta</label>
                                    <select id="editStatus" class="form-control" name="status" required>
                                        <option value="Pendente">Pendente</option>
                                        <option value="Em andamento">Em andamento</option>
                                        <option value="Concluída">Concluída</option>
                                    </select>

                                    <label for="editDescricao">Descrição da Meta</label>
                                    <textarea id="editDescricao" class="form-control" name="descricao" rows="4"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit">Salvar Alterações</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal - Visualizar Meta -->
                <div id="viewMetaModal" class="modal">
                    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 class="modal-title" id="modalVisualizacaoMetaLabel">
                                    <i class="mdi mdi-eye-outline"></i> Visualizar Meta
                                </h2>
                                <span class="close" onclick="closeModal('viewMetaModal')">&times;</span>
                            </div>
                            <div class="modal-body">
                                <div class="os-details">
                                    <div class="mb-3">
                                        <h3>Nome da Meta</h3>
                                        <p id="viewMetaNome"></p>
                                    </div>
                                    <div class="mb-3">
                                        <h3>Ano da Meta</h3>
                                        <p id="viewMetaAno"></p>
                                    </div>
                                    <div class="mb-3">
                                        <h3>Data de Cadastro</h3>
                                        <p id="viewMetaDataCadastro"></p>
                                    </div>
                                    <div class="mb-3">
                                        <h3>Prazo</h3>
                                        <p id="viewMetaPrazo"></p>
                                    </div>
                                    <div class="mb-3">
                                        <h3>Status</h3>
                                        <p id="viewMetaStatus"></p>
                                    </div>
                                    <div class="mb-3">
                                        <h3>Descrição</h3>
                                        <p id="viewMetaDescricao"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ============================= -->
                <!-- JAVASCRIPT DE ANIMAÇÃO CIRCLE -->
                <!-- ============================= -->
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const circles = document.querySelectorAll('.circle-container');
                    circles.forEach(container => {
                        const progressCircle = container.querySelector('.circle-progress');
                        const textEl = container.querySelector('.circle-text');
                        const offset = parseFloat(container.dataset.offset) || 440;
                        const dias = parseInt(container.dataset.dias) || 0;
                        const statusMode = container.dataset.status; 

                        // Anima o stroke-dashoffset para encher o círculo
                        progressCircle.style.strokeDashoffset = offset;

                        if (statusMode === 'atrasado') {
                            // Exibe "Atrasado há X dias"
                            textEl.textContent = `Atrasado há ${dias} dias`;
                        } else if (statusMode === 'concluida') {
                            // Exibe "100%"
                            textEl.textContent = '100%';
                        } else {
                            // Pendente ou Em andamento => "Faltam X dias"
                            textEl.textContent = `Faltam ${dias} dias`;
                        }
                    });
                });

                // Abre modal
                function openModal(modalId) {
                    var modal = document.getElementById(modalId);
                    if (modal) modal.style.display = "block";
                }
                // Fecha modal
                function closeModal(modalId) {
                    var modal = document.getElementById(modalId);
                    if (modal) modal.style.display = "none";
                }
                // Fecha ao clicar fora
                window.onclick = function(e) {
                    if (e.target.classList.contains('modal')) {
                        closeModal(e.target.id);
                    }
                }

                // Editar meta
                function editCard(metaId) {
                    const modal = document.getElementById('editMetaModal');
                    modal.style.display = "block";

                    fetch('buscar_meta.php?id=' + metaId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('editId').value        = data.meta.id;
                            document.getElementById('editNome').value      = data.meta.nome;
                            document.getElementById('editAno').value       = data.meta.ano;
                            document.getElementById('editDescricao').value = data.meta.descricao;

                            const mesNumerico = new Date(data.meta.prazo).getMonth() + 1;
                            document.getElementById('editMes').value = mesNumerico;

                            document.getElementById('editStatus').value = data.meta.status;
                        } else {
                            console.error("Erro ao buscar dados da meta.");
                            modal.style.display = "none";
                        }
                    })
                    .catch(error => console.error('Erro ao tentar buscar a meta:', error));
                }

                // Submeter edição
                document.getElementById('editMetaForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    var metaId    = document.getElementById('editId').value;
                    var nome      = document.getElementById('editNome').value;
                    var ano       = document.getElementById('editAno').value;
                    var mes       = document.getElementById('editMes').value;
                    var status    = document.getElementById('editStatus').value;
                    var descricao = document.getElementById('editDescricao').value;

                    var formData = new FormData();
                    formData.append('id', metaId);
                    formData.append('nome', nome);
                    formData.append('ano', ano);
                    formData.append('mes', mes);
                    formData.append('status', status);
                    formData.append('descricao', descricao);

                    fetch('editar_meta.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log("Meta atualizada com sucesso!");
                            closeModal('editMetaModal');
                            location.reload();
                        } else {
                            console.error("Erro ao atualizar a meta: ", data.message);
                        }
                    })
                    .catch(error => console.error('Erro ao enviar:', error));
                });

                // Visualizar meta
                function viewMeta(metaId) {
                    fetch('metadetails.php?id=' + metaId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('viewMetaNome').textContent = data.meta.nome;
                            document.getElementById('viewMetaAno').textContent = data.meta.ano;
                            document.getElementById('viewMetaDataCadastro').textContent 
                                = new Date(data.meta.data_cadastro).toLocaleDateString('pt-BR');
                            document.getElementById('viewMetaPrazo').textContent
                                = new Date(data.meta.prazo).toLocaleDateString('pt-BR');
                            document.getElementById('viewMetaStatus').textContent
                                = data.meta.status;
                            document.getElementById('viewMetaDescricao').textContent
                                = data.meta.descricao || 'Nenhuma descrição fornecida.';

                            openModal('viewMetaModal');
                        } else {
                            console.error('Erro ao buscar dados da meta:', data.message);
                        }
                    })
                    .catch(error => console.error('Erro na requisição:', error));
                }

                // Excluir meta
                function deleteCard(metaId) {
                    if (confirm('Tem certeza que deseja excluir esta meta?')) {
                        fetch('deletar_meta.php', {
                            method: 'POST',
                            body: JSON.stringify({ id: metaId }),
                            headers: { 'Content-Type': 'application/json' }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                console.log("Meta excluída com sucesso!");
                                location.reload();
                            } else {
                                console.error("Erro ao excluir a meta: ", data.message);
                            }
                        })
                        .catch(error => console.error('Erro ao enviar a requisição:', error));
                    }
                }
                </script>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <div class="chat-windows"></div>

    <!-- Bibliotecas JavaScript essenciais -->
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

<?php
// Definindo o tempo máximo da sessão para 6 horas (21600 segundos)
ini_set('session.gc_maxlifetime', 21600);
session_start();
$params = session_get_cookie_params();
setcookie(session_name(), session_id(), time() + 21600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
if (!isset($_SESSION['usuario_id'])) {
header('Location: login');
exit;
}
include 'db.php';
// Verifica permissões
$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;
$usuarioLogadoId = $_SESSION['usuario_id'] ?? null;
function verificarPermissao($perfisPermitidos) {
global $perfilAcesso;
if (!in_array($perfilAcesso, $perfisPermitidos)) {
$paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'minhastarefas';
header('Location: ' . $paginaRedirecionamento);
exit;
}
}
$perfisPermitidos = [1, 2, 4, 5, 9];
verificarPermissao($perfisPermitidos);

// Obtém o setor do usuário logado
$usuarioSetorId = null;
if ($usuarioLogadoId) {
$stmtSetor = $pdo->prepare("SELECT SetorId FROM usuarios WHERE Id = :id");
$stmtSetor->execute([':id' => $usuarioLogadoId]);
$usuarioSetorId = $stmtSetor->fetchColumn();
}

// Obtém os setores disponíveis para o filtro
$stmtSetores = $pdo->query("SELECT id, nome_do_setor FROM setores ORDER BY nome_do_setor");
$setores = $stmtSetores->fetchAll(PDO::FETCH_ASSOC);

// Filtros
$anoFiltro = $_GET['year'] ?? date('Y');
$setorFiltro = $_GET['setor'] ?? '';
$statusFiltro = $_GET['status'] ?? '';
$incluirBacklog = isset($_GET['backlog']) ? (int)$_GET['backlog'] : 0;

// Novos filtros para clique nos gráficos
$setorGraficoFiltro = $_GET['setorGrafico'] ?? '';
$statusGraficoFiltro = $_GET['statusGrafico'] ?? '';
$okrGraficoFiltro = isset($_GET['okrGrafico']) ? (int)$_GET['okrGrafico'] : -1;

// Ordenação da tabela
$campoOrdenacao = $_GET['orderBy'] ?? 'Id';
$direcaoOrdenacao = $_GET['orderDir'] ?? 'DESC';

// Lista de campos permitidos para ordenação
$camposPermitidos = ['Id', 'NomeProjeto', 'Setor', 'Responsavel', 'Status', 'Progresso', 'Tempo', 'EnviadoFunil'];

// Validação do campo de ordenação
if (!in_array($campoOrdenacao, $camposPermitidos)) {
    $campoOrdenacao = 'Id';
}

// Validação da direção de ordenação
if (!in_array($direcaoOrdenacao, ['ASC', 'DESC'])) {
    $direcaoOrdenacao = 'DESC';
}

// Converter nome do setor para ID do setor se necessário
$setorGraficoId = null;
if (!empty($setorGraficoFiltro)) {
    $stmtGetSetorId = $pdo->prepare("SELECT id FROM setores WHERE nome_do_setor = :nome");
    $stmtGetSetorId->execute([':nome' => $setorGraficoFiltro]);
    $setorGraficoId = $stmtGetSetorId->fetchColumn();
}

// Restrições de visualização baseadas no perfil de acesso
$filtroPerfil = "";
if (!in_array($perfilAcesso, [1,4,5,7,8,9])) {
    if ($perfilAcesso == 2) {
        $filtroPerfil = " AND p.SetorRelacionadoId = " . (int)$usuarioSetorId;
    }
}

// Obtém anos disponíveis para o filtro
function getDistinctYears($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT YEAR(DataCriacao) as year FROM projetos ORDER BY year DESC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$years = getDistinctYears($pdo);

// Parâmetros base para consultas
$baseQuery = "FROM projetos p WHERE 1=1 " . $filtroPerfil;
$baseParams = [];
if ($anoFiltro !== 'all') {
$baseQuery .= " AND YEAR(p.DataCriacao) = :ano";
$baseParams[':ano'] = $anoFiltro;
}
if (!empty($setorFiltro)) {
$baseQuery .= " AND p.SetorRelacionadoId = :setor";
$baseParams[':setor'] = $setorFiltro;
}
if ($setorGraficoId !== null) {
$baseQuery .= " AND p.SetorRelacionadoId = :setorGraficoId";
$baseParams[':setorGraficoId'] = $setorGraficoId;
}
if (!empty($statusFiltro)) {
$baseQuery .= " AND p.Status = :status";
$baseParams[':status'] = $statusFiltro;
}
if (!empty($statusGraficoFiltro)) {
$baseQuery .= " AND p.Status = :statusGrafico";
$baseParams[':statusGrafico'] = strtolower($statusGraficoFiltro);
}
if ($okrGraficoFiltro !== -1) {
$baseQuery .= " AND p.EnviadoFunil = :okrGrafico";
$baseParams[':okrGrafico'] = $okrGraficoFiltro;
}
if ($incluirBacklog == 0) {
$baseQuery .= " AND p.Status != 'backlog'";
}

// Após carregar $setores:
$setorMap = [];
foreach ($setores as $s) {
    // mapeia 'Nome do Setor' => 'id'
    $setorMap[$s['nome_do_setor']] = $s['id'];
}

// KPI 1: Total de projetos DTIC
$sqlTotalProjetos = "SELECT COUNT(*) as total " . $baseQuery;
$stmtTotalProjetos = $pdo->prepare($sqlTotalProjetos);
foreach ($baseParams as $k => $v) {
    $stmtTotalProjetos->bindValue($k, $v);
}
$stmtTotalProjetos->execute();
$totalProjetos = $stmtTotalProjetos->fetchColumn();

// KPI 2: Projetos por setor (para gráfico de pizza)
$sqlProjetosPorSetor = "
SELECT s.nome_do_setor as nome_setor, COUNT(p.Id) as total
FROM projetos p
JOIN setores s ON p.SetorRelacionadoId = s.id
WHERE 1=1 " . $filtroPerfil;
$params = [];
if ($anoFiltro !== 'all') {
$sqlProjetosPorSetor .= " AND YEAR(p.DataCriacao) = :ano";
$params[':ano'] = $anoFiltro;
}
if ($incluirBacklog == 0) {
$sqlProjetosPorSetor .= " AND p.Status != 'backlog'";
}
if (!empty($statusGraficoFiltro)) {
$sqlProjetosPorSetor .= " AND p.Status = :statusGrafico";
$params[':statusGrafico'] = strtolower($statusGraficoFiltro);
}
if ($okrGraficoFiltro !== -1) {
$sqlProjetosPorSetor .= " AND p.EnviadoFunil = :okrGrafico";
$params[':okrGrafico'] = $okrGraficoFiltro;
}
$sqlProjetosPorSetor .= " GROUP BY s.nome_do_setor ORDER BY total DESC";
$stmtProjetosPorSetor = $pdo->prepare($sqlProjetosPorSetor);
foreach ($params as $k => $v) {
    $stmtProjetosPorSetor->bindValue($k, $v);
}
$stmtProjetosPorSetor->execute();
$projetosPorSetor = $stmtProjetosPorSetor->fetchAll(PDO::FETCH_ASSOC);

// KPI 3: Projetos em OKR por setor
$sqlOKRsPorSetor = "
SELECT s.nome_do_setor as nome_setor, COUNT(p.Id) as total
FROM projetos p
JOIN setores s ON p.SetorRelacionadoId = s.id
WHERE p.EnviadoFunil = 1 AND p.Status = 'andamento' " . $filtroPerfil;
$params = [];
if ($anoFiltro !== 'all') {
$sqlOKRsPorSetor .= " AND YEAR(p.DataCriacao) = :ano";
$params[':ano'] = $anoFiltro;
}
if ($setorGraficoId !== null) {
$sqlOKRsPorSetor .= " AND p.SetorRelacionadoId = :setorGraficoId";
$params[':setorGraficoId'] = $setorGraficoId;
}
if (!empty($statusGraficoFiltro)) {
$sqlOKRsPorSetor .= " AND p.Status = :statusGrafico";
$params[':statusGrafico'] = strtolower($statusGraficoFiltro);
}
$sqlOKRsPorSetor .= " GROUP BY s.nome_do_setor ORDER BY total DESC";
$stmtOKRsPorSetor = $pdo->prepare($sqlOKRsPorSetor);
foreach ($params as $k => $v) {
    $stmtOKRsPorSetor->bindValue($k, $v);
}
$stmtOKRsPorSetor->execute();
$okrsPorSetor = $stmtOKRsPorSetor->fetchAll(PDO::FETCH_ASSOC);

// KPI 4: OKRs bloqueados
$sqlOKRsBloqueados = "
SELECT COUNT(*) as total
FROM projetos p
WHERE p.Status = 'bloqueado' AND p.EnviadoFunil = 1 " . $filtroPerfil;
$params = [];
if ($anoFiltro !== 'all') {
$sqlOKRsBloqueados .= " AND YEAR(p.DataCriacao) = :ano";
$params[':ano'] = $anoFiltro;
}
if (!empty($setorFiltro)) {
$sqlOKRsBloqueados .= " AND p.SetorRelacionadoId = :setor";
$params[':setor'] = $setorFiltro;
}
if ($setorGraficoId !== null) {
$sqlOKRsBloqueados .= " AND p.SetorRelacionadoId = :setorGraficoId";
$params[':setorGraficoId'] = $setorGraficoId;
}
$stmtOKRsBloqueados = $pdo->prepare($sqlOKRsBloqueados);
foreach ($params as $k => $v) {
    $stmtOKRsBloqueados->bindValue($k, $v);
}
$stmtOKRsBloqueados->execute();
$okrsBloqueados = $stmtOKRsBloqueados->fetchColumn();

// KPI 5: Tempo médio de projetos
$sqlTempoMedio = "
SELECT AVG(DATEDIFF(NOW(), DataCriacao)) as tempo_medio
FROM projetos p
WHERE p.Status = 'andamento' " . $filtroPerfil;
$params = [];
if ($anoFiltro !== 'all') {
$sqlTempoMedio .= " AND YEAR(p.DataCriacao) = :ano";
$params[':ano'] = $anoFiltro;
}
if (!empty($setorFiltro)) {
$sqlTempoMedio .= " AND p.SetorRelacionadoId = :setor";
$params[':setor'] = $setorFiltro;
}
if ($setorGraficoId !== null) {
$sqlTempoMedio .= " AND p.SetorRelacionadoId = :setorGraficoId";
$params[':setorGraficoId'] = $setorGraficoId;
}
if ($incluirBacklog == 0) {
$sqlTempoMedio .= " AND p.Status != 'backlog'";
}
$stmtTempoMedio = $pdo->prepare($sqlTempoMedio);
foreach ($params as $k => $v) {
    $stmtTempoMedio->bindValue($k, $v);
}
$stmtTempoMedio->execute();
$tempoMedioProjetos = round($stmtTempoMedio->fetchColumn());

// Dados para a tabela
$sqlTabela = "
SELECT
p.Id,
p.NomeProjeto,
p.descricao_projeto,
p.motivo_bloqueio,
s.nome_do_setor as Setor,
p.Status,
COALESCE(
(SELECT COUNT(concluida) FROM subtarefas_projetos
WHERE projeto_id = p.Id AND concluida = 1) /
NULLIF(
(SELECT COUNT(*) FROM subtarefas_projetos
WHERE projeto_id = p.Id),
0
) * 100,
0
) as Progresso,
DATEDIFF(NOW(), p.DataCriacao) as Tempo,
p.EnviadoFunil,
u.Nome as Responsavel
FROM projetos p
LEFT JOIN setores s ON p.SetorRelacionadoId = s.id
LEFT JOIN usuarios u ON p.ResponsavelId = u.Id
WHERE 1=1 " . $filtroPerfil;

// Paginação para a tabela
$itensPorPagina = 20;
$totalProjetos = 0;
$params = [];
if ($anoFiltro !== 'all') {
$sqlTabela .= " AND YEAR(p.DataCriacao) = :ano";
$params[':ano'] = $anoFiltro;
}
if (!empty($setorFiltro)) {
$sqlTabela .= " AND p.SetorRelacionadoId = :setor";
$params[':setor'] = $setorFiltro;
}
if ($setorGraficoId !== null) {
$sqlTabela .= " AND p.SetorRelacionadoId = :setorGraficoId";
$params[':setorGraficoId'] = $setorGraficoId;
}
if (!empty($statusFiltro)) {
$sqlTabela .= " AND p.Status = :status";
$params[':status'] = $statusFiltro;
}
if (!empty($statusGraficoFiltro)) {
$sqlTabela .= " AND p.Status = :statusGrafico";
$params[':statusGrafico'] = strtolower($statusGraficoFiltro);
}
if ($okrGraficoFiltro !== -1) {
$sqlTabela .= " AND p.EnviadoFunil = :okrGrafico";
$params[':okrGrafico'] = $okrGraficoFiltro;
}
if ($incluirBacklog == 0) {
$sqlTabela .= " AND p.Status != 'backlog'";
}

// Aplicar ordenação
switch ($campoOrdenacao) {
    case 'NomeProjeto':
        $sqlTabela .= " ORDER BY p.NomeProjeto " . $direcaoOrdenacao;
        break;
    case 'Setor':
        $sqlTabela .= " ORDER BY s.nome_do_setor " . $direcaoOrdenacao;
        break;
    case 'Responsavel':
        $sqlTabela .= " ORDER BY u.Nome " . $direcaoOrdenacao;
        break;
    case 'Status':
        $sqlTabela .= " ORDER BY p.Status " . $direcaoOrdenacao;
        break;
    case 'Progresso':
        $sqlTabela .= " ORDER BY Progresso " . $direcaoOrdenacao;
        break;
    case 'Tempo':
        $sqlTabela .= " ORDER BY Tempo " . $direcaoOrdenacao;
        break;
    case 'EnviadoFunil':
        $sqlTabela .= " ORDER BY p.EnviadoFunil " . $direcaoOrdenacao;
        break;
    default:
        $sqlTabela .= " ORDER BY p.Id " . $direcaoOrdenacao;
}

$stmtTabela = $pdo->prepare($sqlTabela);
foreach ($params as $k => $v) {
    $stmtTabela->bindValue($k, $v);
}
$stmtTabela->execute();
$projetosTabela = $stmtTabela->fetchAll(PDO::FETCH_ASSOC);
$totalProjetos = count($projetosTabela);

// Estatísticas de status
$sqlStatus = "
SELECT
p.Status,
COUNT(*) as total
FROM projetos p
WHERE 1=1 " . $filtroPerfil;
$params = [];
if ($anoFiltro !== 'all') {
$sqlStatus .= " AND YEAR(p.DataCriacao) = :ano";
$params[':ano'] = $anoFiltro;
}
if (!empty($setorFiltro)) {
$sqlStatus .= " AND p.SetorRelacionadoId = :setor";
$params[':setor'] = $setorFiltro;
}
if ($setorGraficoId !== null) {
$sqlStatus .= " AND p.SetorRelacionadoId = :setorGraficoId";
$params[':setorGraficoId'] = $setorGraficoId;
}
if ($okrGraficoFiltro !== -1) {
$sqlStatus .= " AND p.EnviadoFunil = :okrGrafico";
$params[':okrGrafico'] = $okrGraficoFiltro;
}
$sqlStatus .= " GROUP BY p.Status";
$stmtStatus = $pdo->prepare($sqlStatus);
foreach ($params as $k => $v) {
    $stmtStatus->bindValue($k, $v);
}
$stmtStatus->execute();
$statusProjetos = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

// Formata dados para gráfico de status
$statusLabels = [];
$statusData = [];
foreach ($statusProjetos as $status) {
    $statusLabels[] = ucfirst($status['Status']);
    $statusData[] = (int)$status['total'];
}

// Formata dados para gráficos de pizza
$setoresLabels = array_column($projetosPorSetor, 'nome_setor');
$setoresData = array_column($projetosPorSetor, 'total');

$okrSetoresLabels = array_column($okrsPorSetor, 'nome_setor');
$okrSetoresData = array_column($okrsPorSetor, 'total');

// Prepara dados para filtros ativos
$filtrosAtivos = [];
if (!empty($setorGraficoFiltro)) {
    $filtrosAtivos[] = [
        'tipo' => 'setor',
        'valor' => $setorGraficoFiltro,
        'label' => 'Setor: ' . $setorGraficoFiltro
    ];
}
if (!empty($statusGraficoFiltro)) {
    $filtrosAtivos[] = [
        'tipo' => 'status',
        'valor' => $statusGraficoFiltro,
        'label' => 'Status: ' . $statusGraficoFiltro
    ];
}
if ($okrGraficoFiltro !== -1) {
    $filtrosAtivos[] = [
        'tipo' => 'okr',
        'valor' => $okrGraficoFiltro,
        'label' => 'OKR: ' . ($okrGraficoFiltro ? 'Sim' : 'Não')
    ];
}

?>
<!DOCTYPE html>
<html dir="ltr" lang="pt">
<head>
    <?php include 'head.php'?> 
    
    <!-- Fontes Google -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-teal: #4A9B96;
            --primary-blue: #4A7BA7;
            --primary-gold: #B8860B;
            --primary-purple: #A4426B;
            --bg-dark: #2C2F36;
            --bg-darker: #1A1D23;
            --bg-card: #353842;
            --text-primary: #FFFFFF;
            --text-secondary: #B8BCC8;
            --border-color: #404651;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --shadow-default: 0 4px 20px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.2);
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --transition: all 0.3s ease;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif !important;
            background: var(--bg-dark);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        /* Fullscreen Mode */
        .fullscreen-mode {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-dark);
            z-index: 9999;
            overflow-y: auto;
            padding: 1rem;
        }

        .fullscreen-mode .page-wrapper {
            margin-left: 0 !important;
            padding: 0;
        }

        .fullscreen-mode .topbar,
        .fullscreen-mode .left-sidebar {
            display: none !important;
        }

        .fullscreen-btn {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 10000;
            background: linear-gradient(135deg, var(--primary-teal), var(--primary-blue));
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 0.75rem;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-default);
        }

        .fullscreen-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        /* Header Responsivo */
        .page-header {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-darker));
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-teal), var(--primary-blue), var(--primary-gold));
        }

        .page-title-modern {
            background: linear-gradient(135deg, var(--primary-teal), var(--primary-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: clamp(1.25rem, 4vw, 2rem);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: clamp(0.875rem, 2vw, 1rem);
            margin-top: 0.5rem;
            font-weight: 400;
        }

        /* Filtros Ultra Responsivos */
        .filters-section {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: clamp(1rem, 3vw, 1.5rem);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-default);
            position: relative;
            overflow: hidden;
        }

        .filters-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-teal), var(--primary-blue));
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-select {
            background: var(--bg-darker);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem;
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: var(--transition);
            width: 100%;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 3px rgba(74, 155, 150, 0.1);
        }

        .filter-checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--bg-darker);
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .filter-checkbox-group:hover {
            border-color: var(--primary-teal);
        }

        .modern-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-teal);
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--primary-teal), var(--primary-blue));
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-default);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        /* KPI Cards Responsivos */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: clamp(1rem, 3vw, 1.5rem);
            margin-bottom: 2rem;
        }

        @media (max-width: 576px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }
        }

        .kpi-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: clamp(1rem, 3vw, 1.5rem);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--card-color), rgba(255,255,255,0.3));
        }

        .kpi-card.teal::before { background: linear-gradient(90deg, var(--primary-teal), #7DD3C0); }
        .kpi-card.blue::before { background: linear-gradient(90deg, var(--primary-blue), #7FA7C7); }
        .kpi-card.gold::before { background: linear-gradient(90deg, var(--primary-gold), #D4AF37); }
        .kpi-card.purple::before { background: linear-gradient(90deg, var(--primary-purple), #C96584); }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .kpi-content {
            flex: 1;
        }

        .kpi-icon {
            width: clamp(40px, 8vw, 48px);
            height: clamp(40px, 8vw, 48px);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            flex-shrink: 0;
        }

        .kpi-icon.teal { background: rgba(74, 155, 150, 0.2); color: var(--primary-teal); }
        .kpi-icon.blue { background: rgba(74, 123, 167, 0.2); color: var(--primary-blue); }
        .kpi-icon.gold { background: rgba(184, 134, 11, 0.2); color: var(--primary-gold); }
        .kpi-icon.purple { background: rgba(164, 66, 107, 0.2); color: var(--primary-purple); }

        .kpi-value {
            font-size: clamp(1.75rem, 5vw, 2.5rem);
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .kpi-label {
            font-size: clamp(0.75rem, 2vw, 0.875rem);
            color: var(--text-secondary);
            font-weight: 500;
        }

        .kpi-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            gap: 1rem;
        }

        .kpi-progress {
            height: 6px;
            background: var(--bg-darker);
            border-radius: 3px;
            overflow: hidden;
            flex: 1;
            min-width: 80px;
        }

        .progress-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.8s ease;
        }

        .progress-bar.teal { background: var(--primary-teal); }
        .progress-bar.blue { background: var(--primary-blue); }
        .progress-bar.gold { background: var(--primary-gold); }
        .progress-bar.success { background: var(--success); }
        .progress-bar.warning { background: var(--warning); }
        .progress-bar.danger { background: var(--danger); }

        .kpi-badge {
            background: var(--bg-darker);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: clamp(0.625rem, 1.5vw, 0.75rem);
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        /* Charts Container Responsivo */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: clamp(1rem, 3vw, 1.5rem);
            margin-bottom: 2rem;
        }

        @media (min-width: 992px) {
            .charts-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .charts-grid.three-cols {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        .chart-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            padding: clamp(1rem, 3vw, 1.5rem);
            border: 1px solid var(--border-color);
            overflow: hidden;
            position: relative;
        }

        /* Novo: indicador de clicável */
        .chart-card::after {
            content: 'Clicável';
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 10px;
            background: var(--primary-teal);
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .chart-card:hover::after {
            opacity: 1;
        }

        .chart-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .chart-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(74, 155, 150, 0.2);
            color: var(--primary-teal);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .chart-title {
            font-size: clamp(1rem, 2.5vw, 1.125rem);
            font-weight: 600;
            margin: 0;
        }

        .chart-subtitle {
            font-size: clamp(0.75rem, 2vw, 0.875rem);
            color: var(--text-secondary);
            margin: 0.25rem 0 0 0;
        }

        .chart-container {
            position: relative;
            height: clamp(250px, 40vw, 300px);
            width: 100%;
            cursor: pointer;
        }

        /* Status Chart Full Width */
        .status-chart-container {
            grid-column: 1 / -1;
        }

        .status-chart-container .chart-container {
            height: clamp(200px, 30vw, 250px);
        }

        /* Tabela Ultra Responsiva */
        .table-card {
            background: var(--bg-card);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: clamp(1rem, 3vw, 1.5rem);
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-darker);
        }

        .table-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: clamp(1rem, 2.5vw, 1.125rem);
            font-weight: 600;
            flex-wrap: wrap;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .modern-table th {
            background: var(--bg-darker);
            padding: clamp(0.75rem, 2vw, 1rem);
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: clamp(0.75rem, 1.5vw, 0.875rem);
            text-transform: uppercase;
            letter-spacing: 0.025em;
            white-space: nowrap;
        }

        .modern-table td {
            padding: clamp(0.75rem, 2vw, 1rem);
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-size: clamp(0.8rem, 1.5vw, 0.875rem);
        }

        .modern-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .modern-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: clamp(0.625rem, 1.25vw, 0.75rem);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            white-space: nowrap;
        }

        .status-andamento { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .status-bloqueado { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .status-concluido { background: rgba(74, 155, 150, 0.2); color: var(--primary-teal); }
        .status-backlog { background: rgba(107, 114, 128, 0.2); color: #9CA3AF; }

        /* Progress Circle */
        .progress-circle {
            width: clamp(40px, 8vw, 50px);
            height: clamp(40px, 8vw, 50px);
            border-radius: 50%;
            background: var(--bg-darker);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(0.625rem, 1.25vw, 0.75rem);
            font-weight: 600;
            position: relative;
        }

        .progress-circle::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 50%;
            padding: 2px;
            background: conic-gradient(var(--primary-teal) var(--progress, 0%), transparent 0%);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }

        .okr-badge {
            background: linear-gradient(135deg, var(--primary-purple), #C96584);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: clamp(0.625rem, 1.25vw, 0.75rem);
            font-weight: 600;
            white-space: nowrap;
        }

        /* NOVOS ESTILOS: Filtros Ativos */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(74, 155, 150, 0.15);
            border: 1px solid rgba(74, 155, 150, 0.3);
            color: var(--primary-teal);
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .filter-pill:hover {
            background: rgba(74, 155, 150, 0.25);
        }

        .filter-pill .remove-filter {
            background: none;
            border: none;
            color: var(--primary-teal);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 10px;
            transition: var(--transition);
        }

        .filter-pill .remove-filter:hover {
            background: rgba(74, 155, 150, 0.3);
        }

        .filter-pill.setor {
            background: rgba(74, 155, 150, 0.15);
            border-color: rgba(74, 155, 150, 0.3);
            color: var(--primary-teal);
        }

        .filter-pill.status {
            background: rgba(74, 123, 167, 0.15);
            border-color: rgba(74, 123, 167, 0.3);
            color: var(--primary-blue);
        }

        .filter-pill.okr {
            background: rgba(164, 66, 107, 0.15);
            border-color: rgba(164, 66, 107, 0.3);
            color: var(--primary-purple);
        }

        .filter-pill.setor .remove-filter { color: var(--primary-teal); }
        .filter-pill.status .remove-filter { color: var(--primary-blue); }
        .filter-pill.okr .remove-filter { color: var(--primary-purple); }

        .clear-all-filters {
            background: rgba(107, 114, 128, 0.15);
            border: 1px solid rgba(107, 114, 128, 0.3);
            color: var(--text-secondary);
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .clear-all-filters:hover {
            background: rgba(107, 114, 128, 0.25);
        }

        /* Pulsing animation for chart elements */
        @keyframes pulse-highlight {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }

        .pulse-highlight {
            animation: pulse-highlight 1.5s infinite;
        }

        /* Mobile Improvements */
        @media (max-width: 768px) {
            .page-wrapper {
                padding: 0.5rem;
            }
            
            .container-fluid {
                padding: 0;
            }
            
            .kpi-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .kpi-meta {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            
            .chart-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-responsive {
                border-radius: var(--border-radius-sm);
            }
            
            .modern-table th,
            .modern-table td {
                padding: 0.5rem;
                font-size: 0.75rem;
            }
        }

        /* Animações */
        .kpi-card, .chart-card, .table-card, .filters-section {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pulse-danger {
            animation: pulse-danger 2s infinite;
        }

        @keyframes pulse-danger {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        }

        /* Scrollbar Customization */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: var(--bg-darker);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--primary-teal);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: var(--primary-blue);
        }

        /* Loading Animation */
        .loading-placeholder {
            background: linear-gradient(90deg, var(--bg-card) 25%, var(--bg-darker) 50%, var(--bg-card) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Transição de tabela */
        .table-fade-transition {
            transition: opacity 0.3s ease;
        }
        
        .table-fade-out {
            opacity: 0.3;
        }

        /* Tooltip estilizado */
        .chart-tooltip {
            position: absolute;
            background: rgba(28, 30, 38, 0.95);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            pointer-events: none;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            max-width: 200px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        /* Tooltip projeto */
        .projeto-tooltip {
            position: absolute;
            background: rgba(28, 30, 38, 0.95);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            pointer-events: none;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            max-width: 350px;
            opacity: 0;
            transition: opacity 0.2s ease;
            line-height: 1.5;
        }
        
        .projeto-tooltip h4 {
            margin-top: 0;
            margin-bottom: 8px;
            color: var(--primary-teal);
            font-size: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 6px;
        }
        
        .projeto-tooltip p {
            margin: 0;
            overflow-wrap: break-word;
        }
        
        .nome-projeto {
            cursor: help;
            border-bottom: 1px dashed rgba(74, 155, 150, 0.3);
            display: inline-block;
        }
        
        /* Estilos para o paginador com infinite scroll */
        .load-more-container {
            text-align: center;
            padding: 20px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        
        .loading-spinner {
            display: none;
            width: 30px;
            height: 30px;
            border: 3px solid rgba(74, 155, 150, 0.2);
            border-top-color: var(--primary-teal);
            border-radius: 50%;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .pagination-info {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }
        
        .hidden-row {
            display: none;
        }
        
        .load-more-button {
            background: linear-gradient(135deg, var(--primary-teal), var(--primary-blue));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .load-more-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .load-more-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .scroll-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-teal), var(--primary-blue));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transform: translateY(20px);
        }
        
        .scroll-to-top.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .scroll-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        /* Tooltip de bloqueio */
        .bloqueio-tooltip {
            position: absolute;
            background: rgba(239, 68, 68, 0.95);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            pointer-events: none;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(239, 68, 68, 0.5);
            max-width: 350px;
            opacity: 0;
            transition: opacity 0.2s ease;
            line-height: 1.5;
        }
        
        .bloqueio-tooltip h4 {
            margin-top: 0;
            margin-bottom: 8px;
            color: white;
            font-size: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding-bottom: 6px;
        }
        
        .bloqueio-tooltip p {
            margin: 0;
            overflow-wrap: break-word;
        }
        
        .status-bloqueado {
            cursor: help;
        }
        
        .order-column {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: color 0.2s ease;
        }
        
        .order-column:hover {
            color: var(--text-primary);
        }
        
        .order-column i {
            margin-left: 5px;
            opacity: 0.5;
        }
        
        .order-column:hover i {
            opacity: 1;
        }
        
        .fa-sort-up, .fa-sort-down {
            opacity: 1 !important;
            color: var(--primary-teal);
        }
        
        /* Highlight sorted column */
        th.sorted-column {
            background-color: rgba(74, 155, 150, 0.1);
        }
    </style>
</head>

<body>

    <div class="preloader">
        <svg class="tea lds-ripple" width="37" height="48" viewbox="0 0 37 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M27.0819 17H3.02508C1.91076 17 1.01376 17.9059 1.0485 19.0197C1.15761 22.5177 1.49703 29.7374 2.5 34C4.07125 40.6778 7.18553 44.8868 8.44856 46.3845C8.79051 46.79 9.29799 47 9.82843 47H20.0218C20.639 47 21.2193 46.7159 21.5659 46.2052C22.6765 44.5687 25.2312 40.4282 27.5 34C28.9757 29.8188 29.084 22.4043 29.0441 18.9156C29.0319 17.8436 28.1539 17 27.0819 17Z" stroke="#1e88e5" stroke-width="2"></path>
            <path d="M29 23.5C29 23.5 34.5 20.5 35.5 25.4999C36.0986 28.4926 34.2033 31.5383 32 32.8713C29.4555 34.4108 28 34 28 34" stroke="#1e88e5" stroke-width="2"></path>
            <path id="teabag" fill="#1e88e5" fill-rule="evenodd" clip-rule="evenodd" d="M16 25V17H14V25H12C10.3431 25 9 26.3431 9 28V34C9 35.6569 10.3431 37 12 37H18C19.6569 37 21 35.6569 21 34V28C21 26.3431 19.6569 25 18 25H16ZM11 28C11 27.4477 11.4477 27 12 27H18C18.5523 27 19 27.4477 19 28V34C19 34.5523 18.5523 35 18 35H12C11.4477 35 11 34.5523 11 34V28Z"></path>
            <path id="steamL" d="M17 1C17 1 17 4.5 14 6.5C11 8.5 11 12 11 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="#1e88e5"></path>
            <path id="steamR" d="M21 6C21 6 21 8.22727 19 9.5C17 10.7727 17 13 17 13" stroke="#1e88e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
    </div>
    
    <!-- Main wrapper -->
    <div id="main-wrapper">
        <!-- Topbar header -->
        <header class="topbar">
            <?php include 'header.php'?>   
        </header>
        
        <!-- Left Sidebar -->
        <?php include 'sidebar.php'?>       
        
        <!-- Page wrapper -->
        <div class="page-wrapper">
            <div class="container-fluid">
                <!-- Header da Página -->
                <section class="page-header">
                    <h1 class="page-title-modern">
                        <i class="fas fa-chart-line"></i>
                        Análise BI - Projetos DTIC
                    </h1>
                    <p class="page-subtitle">
                        Dashboard executivo com métricas e indicadores de performance dos projetos DTIC
                    </p>
                </section>
                
                <!-- Seção de Filtros Responsivos -->
                <section class="filters-section">
                    <form action="analisebi.php" method="get" id="filtroForm">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label for="year" class="filter-label">
                                    <i class="fas fa-calendar-alt"></i> 
                                    Período de Análise
                                </label>
                                <select name="year" id="year" class="filter-select">
                                    <option value="all" <?php echo $anoFiltro == 'all' ? 'selected' : ''; ?>>Todos os Anos</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $year == $anoFiltro ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="setor" class="filter-label">
                                    <i class="fas fa-building"></i> 
                                    Departamento
                                </label>
                                <select name="setor" id="setor" class="filter-select">
                                    <option value="">Todos os Setores</option>
                                    <?php foreach ($setores as $setor): ?>
                                        <option value="<?php echo $setor['id']; ?>" <?php echo $setor['id'] == $setorFiltro ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($setor['nome_do_setor']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-filter"></i> 
                                    Opções Avançadas
                                </label>
                                <div class="filter-checkbox-group">
                                    <input type="checkbox" id="backlog" name="backlog" value="1" class="modern-checkbox" <?php echo $incluirBacklog ? 'checked' : ''; ?>>
                                    <label for="backlog" class="filter-label" style="margin: 0;">Incluir projetos em backlog</label>
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label" style="opacity: 0;">Ação</label>
                                <button type="submit" class="filter-btn">
                                    <i class="fas fa-sync-alt"></i> 
                                    Atualizar Dashboard
                                </button>
                            </div>
                        </div>
                        
                        <!-- Campos ocultos para filtros de gráficos -->
                        <input type="hidden" name="setorGrafico" id="setorGrafico" value="<?php echo htmlspecialchars($setorGraficoFiltro); ?>">
                        <input type="hidden" name="statusGrafico" id="statusGrafico" value="<?php echo htmlspecialchars($statusGraficoFiltro); ?>">
                        <input type="hidden" name="okrGrafico" id="okrGrafico" value="<?php echo $okrGraficoFiltro; ?>">
                        
                        <!-- Mostrar Filtros Ativos -->
                        <?php if (count($filtrosAtivos) > 0): ?>
                        <div class="active-filters">
                            <?php foreach($filtrosAtivos as $filtro): ?>
                                <div class="filter-pill <?php echo $filtro['tipo']; ?>" data-tipo="<?php echo $filtro['tipo']; ?>" data-valor="<?php echo $filtro['valor']; ?>">
                                    <?php echo htmlspecialchars($filtro['label']); ?>
                                    <button type="button" class="remove-filter" onclick="removerFiltro('<?php echo $filtro['tipo']; ?>')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($filtrosAtivos) > 1): ?>
                                <button type="button" class="clear-all-filters" onclick="limparTodosFiltros()">
                                    Limpar todos
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </form>
                </section>

                <!-- KPIs Grid Responsivo -->
                <section class="kpi-grid">
                    <!-- Total de Projetos -->
                    <div class="kpi-card teal">
                        <div class="kpi-header">
                            <div class="kpi-content">
                                <div class="kpi-value" data-count="<?php echo $totalProjetos; ?>">0</div>
                                <div class="kpi-label">Total de Projetos DTIC</div>
                            </div>
                            <div class="kpi-icon teal">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                        </div>
                        <div class="kpi-meta">
                            <?php 
                            $andamentoCount = 0;
                            foreach ($statusProjetos as $status) {
                                if ($status['Status'] == 'andamento') {
                                    $andamentoCount = $status['total'];
                                    break;
                                }
                            }
                            $andamentoPct = $totalProjetos > 0 ? ($andamentoCount / $totalProjetos * 100) : 0;
                            ?>
                            <div class="kpi-progress">
                                <div class="progress-bar success" style="width: <?php echo $andamentoPct; ?>%;"></div>
                            </div>
                            <div class="kpi-badge">Em andamento: <?php echo $andamentoCount; ?></div>
                        </div>
                    </div>

                    <!-- Tempo Médio -->
                    <div class="kpi-card blue">
                        <div class="kpi-header">
                            <div class="kpi-content">
                                <div class="kpi-value" data-count="<?php echo $tempoMedioProjetos; ?>">0</div>
                                <div class="kpi-label">Dias Médios dos Projetos</div>
                            </div>
                            <div class="kpi-icon blue">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="kpi-meta">
                            <?php
                            $tempoClass = 'success';
                            $tempoLabel = 'Dentro do prazo';
                            
                            if ($tempoMedioProjetos > 90) {
                                $tempoClass = 'danger';
                                $tempoLabel = 'Acima do prazo';
                            } elseif ($tempoMedioProjetos > 60) {
                                $tempoClass = 'warning';
                                $tempoLabel = 'Prazo moderado';
                            }
                            ?>
                            <div class="kpi-progress">
                                <div class="progress-bar <?php echo $tempoClass; ?>" style="width: <?php echo min(100, $tempoMedioProjetos/120*100); ?>%;"></div>
                            </div>
                            <div class="kpi-badge"><?php echo $tempoLabel; ?></div>
                        </div>
                    </div>

                    <!-- OKRs Bloqueados -->
                    <div class="kpi-card purple">
                        <div class="kpi-header">
                            <div class="kpi-content">
                                <div class="kpi-value" data-count="<?php echo $okrsBloqueados; ?>">0</div>
                                <div class="kpi-label">OKRs Bloqueados</div>
                            </div>
                            <div class="kpi-icon purple">
                                <i class="fas fa-lock"></i>
                            </div>
                        </div>
                        <div class="kpi-meta">
                            <?php
                            $totalOKRs = 0;
                            foreach ($okrsPorSetor as $okr) {
                                $totalOKRs += $okr['total'];
                            }
                            $bloqueadosPct = $totalOKRs > 0 ? ($okrsBloqueados / $totalOKRs * 100) : 0;
                            ?>
                            <div class="kpi-progress">
                                <div class="progress-bar warning" style="width: <?php echo $bloqueadosPct; ?>%;"></div>
                            </div>
                            <div class="kpi-badge">
                                <?php if ($totalOKRs > 0): ?>
                                    <?php echo round($bloqueadosPct, 1); ?>% dos OKRs
                                <?php else: ?>
                                    Sem OKRs ativos
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Setores Ativos -->
                    <div class="kpi-card gold">
                        <div class="kpi-header">
                            <div class="kpi-content">
                                <div class="kpi-value" data-count="<?php echo count($projetosPorSetor); ?>">0</div>
                                <div class="kpi-label">Setores Ativos</div>
                            </div>
                            <div class="kpi-icon gold">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                        </div>
                        <div class="kpi-meta">
                            <div class="kpi-progress">
                                <div class="progress-bar gold" style="width: 85%;"></div>
                            </div>
                            <div class="kpi-badge">Distribuição equilibrada</div>
                        </div>
                    </div>
                    
                    <!-- Paginação -->
                    <?php if (count($projetosTabela) > $itensPorPagina): ?>
                    <div class="load-more-container" style="display: none;">
                        <div class="loading-spinner" id="loadingSpinner"></div>
                        <p class="pagination-info" id="paginationInfo" style="display: none;">
                            Mostrando <span id="projetosExibidos"><?php echo min($itensPorPagina, $totalProjetos); ?></span> de <span id="projetosTotal"><?php echo $totalProjetos; ?></span> projetos
                        </p>
                        <button id="loadMoreBtn" class="load-more-button" style="display: none;">
                            <i class="fas fa-chevron-down"></i> Carregar mais
                        </button>
                    </div>
                    <?php endif; ?>
                </section>
                
                <!-- Botão de voltar ao topo -->
                <div class="scroll-to-top" id="scrollToTop">
                    <i class="fas fa-arrow-up"></i>
                </div>

                <!-- Gráficos Responsivos -->
                <section class="charts-grid">
                    <!-- Projetos por Setor -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div>
                                <div class="chart-title">Projetos por Setor</div>
                                <div class="chart-subtitle">Distribuição de todos os projetos por departamento</div>
                            </div>
                        </div>
                        <div class="chart-container" id="projetosSetorContainer">
                            <canvas id="projetosPorSetorChart"></canvas>
                        </div>
                    </div>

                    <!-- OKRs por Setor -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-icon">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <div>
                                <div class="chart-title">OKRs por Setor</div>
                                <div class="chart-subtitle">Distribuição de projetos estratégicos por departamento</div>
                            </div>
                        </div>
                        <div class="chart-container" id="okrsSetorContainer">
                            <canvas id="okrsPorSetorChart"></canvas>
                        </div>
                    </div>

                    <!-- Status dos Projetos -->
                    <div class="chart-card status-chart-container">
                        <div class="chart-header">
                            <div class="chart-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div>
                                <div class="chart-title">Status dos Projetos</div>
                                <div class="chart-subtitle">Distribuição atual por status de execução</div>
                            </div>
                        </div>
                        <div class="chart-container" id="statusContainer">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </section>

                <!-- Tabela de Projetos Responsiva -->
                <section class="table-card">
                    <div class="table-header">
                        <div class="table-title">
                            <div class="chart-icon">
                                <i class="fas fa-table"></i>
                            </div>
                            <div>
                                <div>Projetos Detalhados</div>
                                <div class="chart-subtitle">Visão completa com métricas de performance</div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table table-fade-transition" id="projetosTable">
                            <thead>
                                <tr>
                                    <th><a href="javascript:void(0)" class="order-column" data-column="Id">ID <i class="fas fa-sort<?php echo $campoOrdenacao == 'Id' ? ($direcaoOrdenacao == 'ASC' ? '-up' : '-down') : ''; ?>"></i></a></th>
                                    <th><a href="javascript:void(0)" class="order-column" data-column="NomeProjeto">Projeto <i class="fas fa-sort<?php echo $campoOrdenacao == 'NomeProjeto' ? ($direcaoOrdenacao == 'ASC' ? '-up' : '-down') : ''; ?>"></i></a></th>
                                    <th><a href="javascript:void(0)" class="order-column" data-column="Setor">Setor <i class="fas fa-sort<?php echo $campoOrdenacao == 'Setor' ? ($direcaoOrdenacao == 'ASC' ? '-up' : '-down') : ''; ?>"></i></a></th>
                                    <th><a href="javascript:void(0)" class="order-column" data-column="Responsavel">Responsável <i class="fas fa-sort<?php echo $campoOrdenacao == 'Responsavel' ? ($direcaoOrdenacao == 'ASC' ? '-up' : '-down') : ''; ?>"></i></a></th>
                                    <th><a href="javascript:void(0)" class="order-column" data-column="Status">Status <i class="fas fa-sort<?php echo $campoOrdenacao == 'Status' ? ($direcaoOrdenacao == 'ASC' ? '-up' : '-down') : ''; ?>"></i></a></th>
                                    <th><a href="javascript:void(0)" class="order-column" data-column="Progresso">Progresso <i class="fas fa-sort<?php echo $campoOrdenacao == 'Progresso' ? ($direcaoOrdenacao == 'ASC' ? '-up' : '-down') : ''; ?>"></i></a></th>
                                    <th><a href="javascript:void(0)" class="order-column" data-column="Tempo">Tempo (dias) <i class="fas fa-sort<?php echo $campoOrdenacao == 'Tempo' ? ($direcaoOrdenacao == 'ASC' ? '-up' : '-down') : ''; ?>"></i></a></th>
                                    <th><a href="javascript:void(0)" class="order-column" data-column="EnviadoFunil">OKR <i class="fas fa-sort<?php echo $campoOrdenacao == 'EnviadoFunil' ? ($direcaoOrdenacao == 'ASC' ? '-up' : '-down') : ''; ?>"></i></a></th>
                                </tr>
                            </thead>
                            <tbody id="projetosTableBody">
                                <?php foreach($projetosTabela as $index => $projeto): ?>
                                    <tr class="<?php echo $index >= $itensPorPagina ? 'hidden-row' : ''; ?>">
                                        <td><strong>#<?php echo $projeto['Id']; ?></strong></td>
                                        <td style="max-width: 250px;">
                                            <div style="font-weight: 600; margin-bottom: 0.25rem;" class="nome-projeto" data-descricao="<?php echo htmlspecialchars($projeto['descricao_projeto'] ?? 'Sem descrição disponível.'); ?>">
                                                <?php echo htmlspecialchars($projeto['NomeProjeto']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($projeto['Setor']); ?></td>
                                        <td><?php echo htmlspecialchars($projeto['Responsavel'] ?? 'N/D'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $projeto['Status']; ?>" <?php if ($projeto['Status'] == 'bloqueado'): ?>data-motivo-bloqueio="<?php echo htmlspecialchars($projeto['motivo_bloqueio'] ?? 'Motivo de bloqueio não especificado.'); ?>"<?php endif; ?>>
                                                <?php 
                                                $statusIcons = [
                                                    'andamento' => 'fa-play',
                                                    'bloqueado' => 'fa-lock',
                                                    'concluido' => 'fa-check',
                                                    'backlog' => 'fa-pause'
                                                ];
                                                $statusText = [
                                                    'andamento' => 'Em Andamento',
                                                    'bloqueado' => 'Bloqueado',
                                                    'concluido' => 'Concluído',
                                                    'backlog' => 'Backlog'
                                                ];
                                                ?>
                                                <i class="fas <?php echo $statusIcons[$projeto['Status']] ?? 'fa-question'; ?>"></i>
                                                <?php echo $statusText[$projeto['Status']] ?? ucfirst($projeto['Status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress-circle" style="--progress: <?php echo round($projeto['Progresso']); ?>%">
                                                <?php echo round($projeto['Progresso']); ?>%
                                            </div>
                                        </td>
                                        <td>
                                            <span style="color: <?php echo $projeto['Tempo'] > 90 ? 'var(--danger)' : ($projeto['Tempo'] > 60 ? 'var(--warning)' : 'var(--success)'); ?>; font-weight: 600;">
                                                <?php echo $projeto['Tempo']; ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php if ($projeto['EnviadoFunil'] == 1): ?>
                                                <span class="okr-badge">
                                                    <i class="fas fa-bullseye"></i> OKR
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary);">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($projetosTabela)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                            Nenhum projeto encontrado com os filtros atuais.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                
                <!-- Tooltip para interação dos gráficos -->
                <div id="chartTooltip" class="chart-tooltip"></div>
                
                <!-- Tooltip para descrição dos projetos -->
                <div id="projetoTooltip" class="projeto-tooltip"></div>
                
                <!-- Tooltip para motivo de bloqueio -->
                <div id="bloqueioTooltip" class="bloqueio-tooltip"></div>
            </div>
            
            <?php include 'footer.php'?>
        </div>
    </div>

    <!-- Scripts -->
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

    <script>
        // Configuração global dos gráficos para responsividade total
        Chart.defaults.color = '#B8BCC8';
        Chart.defaults.borderColor = '#404651';
        Chart.defaults.backgroundColor = 'rgba(74, 155, 150, 0.1)';
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Paleta de cores moderna
        const modernColors = [
            '#4A9B96', '#4A7BA7', '#B8860B', '#A4426B', '#10B981', 
            '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#84CC16'
        ];

        // Configurações responsivas comuns
        const getResponsiveConfig = () => ({
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: window.innerWidth < 768 ? 'bottom' : 'right',
                    labels: {
                        color: '#B8BCC8',
                        usePointStyle: true,
                        padding: window.innerWidth < 768 ? 15 : 20,
                        font: {
                            size: window.innerWidth < 768 ? 11 : 12,
                            weight: '500'
                        },
                        boxWidth: window.innerWidth < 768 ? 12 : 15
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(35, 38, 66, 0.95)',
                    titleColor: '#FFFFFF',
                    bodyColor: '#B8BCC8',
                    borderColor: '#404651',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    titleFont: { size: window.innerWidth < 768 ? 12 : 14 },
                    bodyFont: { size: window.innerWidth < 768 ? 11 : 13 }
                }
            }
        });

        // Função para redimensionar gráficos
        const resizeCharts = () => {
            Chart.helpers.each(Chart.instances, (instance) => {
                instance.resize();
            });
        };

        // Gráfico: Projetos por Setor (Responsivo)
        const ctxProjetos = document.getElementById('projetosPorSetorChart').getContext('2d');
        const projetosChart = new Chart(ctxProjetos, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($setoresLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($setoresData); ?>,
                    backgroundColor: modernColors,
                    borderWidth: 0,
                    hoverBorderWidth: 3,
                    hoverBorderColor: '#FFFFFF'
                }]
            },
            options: {
                ...getResponsiveConfig(),
                cutout: window.innerWidth < 768 ? '60%' : '65%',
                plugins: {
                    ...getResponsiveConfig().plugins,
                    legend: {
                        ...getResponsiveConfig().plugins.legend,
                        position: window.innerWidth < 768 ? 'bottom' : 'right'
                    }
                },
                // NOVO: Interatividade
                onClick: (e, activeElements) => {
                    if (activeElements.length > 0) {
                        const clickedIndex = activeElements[0].index;
                        const setorClicado = projetosChart.data.labels[clickedIndex];
                        aplicarFiltroSetor(setorClicado);
                    }
                }
            }
        });

        // Gráfico: OKRs por Setor (Responsivo)
        const ctxOKRs = document.getElementById('okrsPorSetorChart').getContext('2d');
        const okrsChart = new Chart(ctxOKRs, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($okrSetoresLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($okrSetoresData); ?>,
                    backgroundColor: modernColors,
                    borderWidth: 0,
                    hoverBorderWidth: 3,
                    hoverBorderColor: '#FFFFFF'
                }]
            },
            options: {
                ...getResponsiveConfig(),
                cutout: window.innerWidth < 768 ? '60%' : '65%',
                plugins: {
                    ...getResponsiveConfig().plugins,
                    legend: {
                        ...getResponsiveConfig().plugins.legend,
                        position: window.innerWidth < 768 ? 'bottom' : 'right'
                    }
                },
                // NOVO: Interatividade
                onClick: (e, activeElements) => {
                    if (activeElements.length > 0) {
                        const clickedIndex = activeElements[0].index;
                        const setorClicado = okrsChart.data.labels[clickedIndex];
                        aplicarFiltroOKR(1);
                        aplicarFiltroSetor(setorClicado);
                    }
                }
            }
        });

        // Gráfico: Status dos Projetos (Responsivo)
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        const statusColors = {
            'Andamento': '#10B981',
            'Bloqueado': '#EF4444', 
            'Concluido': '#4A9B96',
            'Backlog': '#6B7280'
        };

        const statusBackgroundColors = <?php echo json_encode($statusLabels); ?>.map(
            label => statusColors[label] || '#9CA3AF'
        );

        const statusChart = new Chart(ctxStatus, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{
                    label: 'Quantidade de Projetos',
                    data: <?php echo json_encode($statusData); ?>,
                    backgroundColor: statusBackgroundColors,
                    borderRadius: window.innerWidth < 768 ? 6 : 8,
                    borderSkipped: false,
                }]
            },
            options: {
                ...getResponsiveConfig(),
                indexAxis: window.innerWidth < 576 ? 'y' : 'x',
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#404651',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#B8BCC8',
                            precision: 0,
                            font: {
                                size: window.innerWidth < 768 ? 10 : 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#B8BCC8',
                            font: {
                                size: window.innerWidth < 768 ? 10 : 11,
                                weight: '500'
                            },
                            maxRotation: window.innerWidth < 768 ? 45 : 0
                        }
                    }
                },
                plugins: {
                    ...getResponsiveConfig().plugins,
                    legend: {
                        display: false
                    }
                },
                // NOVO: Interatividade
                onClick: (e, activeElements) => {
                    if (activeElements.length > 0) {
                        const clickedIndex = activeElements[0].index;
                        const statusClicado = statusChart.data.labels[clickedIndex];
                        aplicarFiltroStatus(statusClicado.toLowerCase());
                    }
                }
            }
        });

        // NOVAS FUNÇÕES PARA INTERATIVIDADE
        
        // Aplicar filtro de setor
        function aplicarFiltroSetor(setor) {
            document.getElementById('setorGrafico').value = setor;
            document.getElementById('filtroForm').submit();
        }
        
        // Aplicar filtro de status
        function aplicarFiltroStatus(status) {
            document.getElementById('statusGrafico').value = status;
            document.getElementById('filtroForm').submit();
        }
        
        // Aplicar filtro de OKR
        function aplicarFiltroOKR(valor) {
            document.getElementById('okrGrafico').value = valor;
            document.getElementById('filtroForm').submit();
        }
        
        // Remover um filtro específico
        function removerFiltro(tipo) {
            if (tipo === 'setor') {
                document.getElementById('setorGrafico').value = '';
            } else if (tipo === 'status') {
                document.getElementById('statusGrafico').value = '';
            } else if (tipo === 'okr') {
                document.getElementById('okrGrafico').value = '-1';
            }
            document.getElementById('filtroForm').submit();
        }
        
        // Limpar todos os filtros
        function limparTodosFiltros() {
            document.getElementById('setorGrafico').value = '';
            document.getElementById('statusGrafico').value = '';
            document.getElementById('okrGrafico').value = '-1';
            document.getElementById('filtroForm').submit();
        }
        
        // NOVO: Ordenação da tabela
        document.querySelectorAll('.order-column').forEach(column => {
            column.addEventListener('click', function() {
                const columnName = this.getAttribute('data-column');
                let orderDirection = 'ASC';
                
                // Verificar ordenação atual
                const currentOrderBy = new URLSearchParams(window.location.search).get('orderBy');
                const currentOrderDir = new URLSearchParams(window.location.search).get('orderDir');
                
                // Inverter a ordem se já estiver ordenado por esta coluna
                if (currentOrderBy === columnName && currentOrderDir === 'ASC') {
                    orderDirection = 'DESC';
                }
                
                // Atualizar parâmetros e submeter formulário
                const form = document.getElementById('filtroForm');
                
                // Criar ou atualizar os campos hidden
                let orderByInput = document.getElementById('orderBy');
                let orderDirInput = document.getElementById('orderDir');
                
                if (!orderByInput) {
                    orderByInput = document.createElement('input');
                    orderByInput.type = 'hidden';
                    orderByInput.id = 'orderBy';
                    orderByInput.name = 'orderBy';
                    form.appendChild(orderByInput);
                }
                
                if (!orderDirInput) {
                    orderDirInput = document.createElement('input');
                    orderDirInput.type = 'hidden';
                    orderDirInput.id = 'orderDir';
                    orderDirInput.name = 'orderDir';
                    form.appendChild(orderDirInput);
                }
                
                orderByInput.value = columnName;
                orderDirInput.value = orderDirection;
                
                // Adicionar classe de animação de carregamento à tabela
                document.getElementById('projetosTable').classList.add('table-fade-out');
                
                // Pequeno atraso para a animação ser visível
                setTimeout(() => {
                    form.submit();
                }, 300);
            });
        });

        // Adicionar tooltip personalizado para interação
        const chartContainers = [
            { id: 'projetosSetorContainer', message: 'Clique para filtrar por setor' },
            { id: 'okrsSetorContainer', message: 'Clique para ver OKRs deste setor' },
            { id: 'statusContainer', message: 'Clique para filtrar por status' }
        ];
        
        const tooltip = document.getElementById('chartTooltip');
        
        chartContainers.forEach(container => {
            const element = document.getElementById(container.id);
            
            element.addEventListener('mousemove', (e) => {
                tooltip.style.opacity = '1';
                tooltip.textContent = container.message;
                tooltip.style.left = (e.pageX + 10) + 'px';
                tooltip.style.top = (e.pageY + 10) + 'px';
            });
            
            element.addEventListener('mouseleave', () => {
                tooltip.style.opacity = '0';
            });
            
            // Highlight efeito no hover
            element.addEventListener('mouseenter', () => {
                element.classList.add('pulse-highlight');
            });
            
            element.addEventListener('mouseleave', () => {
                element.classList.remove('pulse-highlight');
            });
        });

        // Funcionalidade de Tela Cheia
        function toggleFullscreen() {
            const body = document.body;
            const icon = document.getElementById('fullscreen-icon');
            
            if (body.classList.contains('fullscreen-mode')) {
                body.classList.remove('fullscreen-mode');
                icon.className = 'fas fa-expand';
                
                // Reativa outros elementos
                document.querySelector('.topbar').style.display = '';
                document.querySelector('.left-sidebar').style.display = '';
            } else {
                body.classList.add('fullscreen-mode');
                icon.className = 'fas fa-compress';
                
                // Esconde outros elementos
                document.querySelector('.topbar').style.display = 'none';
                document.querySelector('.left-sidebar').style.display = 'none';
            }
            
            // Redimensiona gráficos após mudança
            setTimeout(resizeCharts, 300);
        }

        // Auto-submit nos filtros com debounce
        let filterTimeout;
        function submitFilters() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(() => {
                document.getElementById('filtroForm').submit();
            }, 500);
        }

        document.getElementById('year').addEventListener('change', submitFilters);
        document.getElementById('setor').addEventListener('change', submitFilters);
        document.getElementById('backlog').addEventListener('change', submitFilters);

        // Animação dos KPIs ao carregar
        function animateKPIs() {
            const kpiValues = document.querySelectorAll('.kpi-value[data-count]');
            
            kpiValues.forEach(kpi => {
                const finalValue = parseInt(kpi.getAttribute('data-count'));
                let currentValue = 0;
                const increment = Math.ceil(finalValue / 50);
                
                const timer = setInterval(() => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        currentValue = finalValue;
                        clearInterval(timer);
                    }
                    kpi.textContent = currentValue;
                }, 30);
            });
        }

        // Animação das barras de progresso
        function animateProgressBars() {
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach((bar, index) => {
                setTimeout(() => {
                    bar.style.transform = 'scaleX(1)';
                    bar.style.transformOrigin = 'left';
                }, index * 100);
            });
        }

        // Listener para redimensionamento
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                // Atualiza configurações responsivas dos gráficos
                [projetosChart, okrsChart, statusChart].forEach(chart => {
                    if (chart) {
                        chart.options = {
                            ...chart.options,
                            ...getResponsiveConfig()
                        };
                        chart.update('none');
                    }
                });
                
                resizeCharts();
            }, 250);
        });

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            // Animações iniciais
            setTimeout(animateKPIs, 500);
            setTimeout(animateProgressBars, 800);
            
            // Suporte a touch para mobile
            if ('ontouchstart' in window) {
                document.body.classList.add('touch-device');
            }
            
            // Destacar coluna ordenada
            const orderBy = '<?php echo $campoOrdenacao; ?>';
            if (orderBy) {
                document.querySelectorAll('.order-column').forEach(col => {
                    if (col.getAttribute('data-column') === orderBy) {
                        col.closest('th').classList.add('sorted-column');
                    }
                });
            }
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F11') {
                    e.preventDefault();
                    toggleFullscreen();
                }
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    location.reload();
                }
            });
            
            // Loading state removal
            setTimeout(() => {
                const preloader = document.querySelector('.preloader');
                if (preloader) {
                    preloader.style.opacity = '0';
                    setTimeout(() => preloader.remove(), 300);
                }
            }, 1000);
        });

        // Highlight setores/status já filtrados nos gráficos
        function destacarFiltrosAtivos() {
            // Destaca setor selecionado no gráfico de projetos por setor
            const setorAtivo = document.getElementById('setorGrafico').value;
            if (setorAtivo && projetosChart) {
                const indexSetor = projetosChart.data.labels.findIndex(s => s === setorAtivo);
                if (indexSetor !== -1) {
                    const metaIndex = projetosChart.getDatasetMeta(0).data[indexSetor];
                    metaIndex.outerRadius += 10;
                    projetosChart.update();
                }
            }
            
            // Destaca status selecionado no gráfico de status
            const statusAtivo = document.getElementById('statusGrafico').value;
            if (statusAtivo && statusChart) {
                const indexStatus = statusChart.data.labels.findIndex(s => s.toLowerCase() === statusAtivo);
                if (indexStatus !== -1) {
                    const newBackgroundColors = [...statusBackgroundColors];
                    newBackgroundColors[indexStatus] = '#FFFFFF';
                    statusChart.data.datasets[0].backgroundColor = newBackgroundColors;
                    statusChart.data.datasets[0].borderColor = statusBackgroundColors;
                    statusChart.data.datasets[0].borderWidth = 2;
                    statusChart.update();
                }
            }
        }
        
        // Tooltip para descrições de projetos
        const projetoTooltip = document.getElementById('projetoTooltip');
        
        document.querySelectorAll('.nome-projeto').forEach(projeto => {
            projeto.addEventListener('mouseenter', function(e) {
                const descricao = this.getAttribute('data-descricao');
                const nomeProjeto = this.textContent.trim();
                
                // Estrutura do tooltip
                projetoTooltip.innerHTML = `
                    <h4>Descrição do Projeto</h4>
                    <p>${descricao || 'Nenhuma descrição disponível.'}</p>
                `;
                
                // Posicionar o tooltip
                projetoTooltip.style.opacity = '1';
                projetoTooltip.style.left = `${e.pageX + 15}px`;
                projetoTooltip.style.top = `${e.pageY + 15}px`;
                
                // Evitar que o tooltip saia da tela
                const tooltipRect = projetoTooltip.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                
                if (tooltipRect.right > viewportWidth) {
                    projetoTooltip.style.left = `${e.pageX - tooltipRect.width - 15}px`;
                }
            });
            
            projeto.addEventListener('mousemove', function(e) {
                // Atualizar posição ao mover o mouse
                const tooltipRect = projetoTooltip.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                
                if (tooltipRect.right > viewportWidth) {
                    projetoTooltip.style.left = `${e.pageX - tooltipRect.width - 15}px`;
                } else {
                    projetoTooltip.style.left = `${e.pageX + 15}px`;
                }
                projetoTooltip.style.top = `${e.pageY + 15}px`;
            });
            
            projeto.addEventListener('mouseleave', function() {
                projetoTooltip.style.opacity = '0';
            });
        });
        
        // Chama a função após carregar os gráficos
        setTimeout(destacarFiltrosAtivos, 1500);
        
        // Implementação de paginação com infinite scroll
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        const tableBody = document.getElementById('projetosTableBody');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const projetosExibidos = document.getElementById('projetosExibidos');
        const projetosTotal = document.getElementById('projetosTotal');
        const scrollToTopBtn = document.getElementById('scrollToTop');
        
        // Variáveis de controle da paginação
        const itensPorPagina = 20;
        let paginaAtual = 1;
        let carregando = false;
        
        // Função para carregar mais itens
        function carregarMaisItens() {
            if (carregando) return;
            
            carregando = true;
            if (loadMoreBtn) {
                loadMoreBtn.disabled = true;
            }
            if (loadingSpinner) {
                loadingSpinner.style.display = 'block';
            }
            
            // Simula atraso para mostrar o spinner (pode remover em produção)
            setTimeout(() => {
                const linhas = tableBody.querySelectorAll('tr.hidden-row');
                const totalLinhas = linhas.length;
                const inicio = (paginaAtual * itensPorPagina) - itensPorPagina;
                const fim = Math.min(inicio + itensPorPagina, totalLinhas);
                
                // Mostra as próximas linhas
                for (let i = inicio; i < fim; i++) {
                    linhas[i].classList.remove('hidden-row');
                }
                
                // Atualiza contador
                if (projetosExibidos) {
                    const totalExibidos = (paginaAtual * itensPorPagina) + (fim - inicio);
                    projetosExibidos.textContent = Math.min(totalExibidos, <?php echo $totalProjetos; ?>);
                }
                
                // Verifica se ainda há mais itens para carregar
                if (loadMoreBtn) {
                    if (fim >= totalLinhas) {
                        loadMoreBtn.style.display = 'none';
                    } else {
                        paginaAtual++;
                        loadMoreBtn.disabled = false;
                    }
                }
                
                carregando = false;
                if (loadingSpinner) {
                    loadingSpinner.style.display = 'none';
                }
                
                // Reinicializa os tooltips para os novos elementos carregados
                initializeProjectTooltips();
                initializeBloqueioTooltips();
            }, 500);
        }
        
        // Event listeners
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', carregarMaisItens);
        }
        
        // Infinite scroll automático ao rolar a página
        window.addEventListener('scroll', function() {
            if (!loadMoreBtn || loadMoreBtn.style.display === 'none') return;
            
            const tableRect = document.querySelector('.table-card').getBoundingClientRect();
            
            // Se o final da tabela estiver visível, carrega mais itens
            if (tableRect.bottom <= window.innerHeight + 200 && !carregando) {
                carregarMaisItens();
            }
            
            // Mostrar/esconder botão de voltar ao topo
            if (scrollToTopBtn) {
                if (window.pageYOffset > 300) {
                    scrollToTopBtn.classList.add('visible');
                } else {
                    scrollToTopBtn.classList.remove('visible');
                }
            }
        });
        
        // Botão de voltar ao topo
        if (scrollToTopBtn) {
            scrollToTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
        
        // Função para inicializar tooltips dos projetos
        function initializeProjectTooltips() {
            document.querySelectorAll('.nome-projeto:not([data-tooltip-initialized])').forEach(projeto => {
                projeto.setAttribute('data-tooltip-initialized', 'true');
                
                projeto.addEventListener('mouseenter', function(e) {
                    const descricao = this.getAttribute('data-descricao');
                    
                    // Estrutura do tooltip
                    projetoTooltip.innerHTML = `
                        <h4>Descrição do Projeto</h4>
                        <p>${descricao || 'Nenhuma descrição disponível.'}</p>
                    `;
                    
                    // Posicionar o tooltip
                    projetoTooltip.style.opacity = '1';
                    projetoTooltip.style.left = `${e.pageX + 15}px`;
                    projetoTooltip.style.top = `${e.pageY + 15}px`;
                    
                    // Evitar que o tooltip saia da tela
                    const tooltipRect = projetoTooltip.getBoundingClientRect();
                    const viewportWidth = window.innerWidth;
                    
                    if (tooltipRect.right > viewportWidth) {
                        projetoTooltip.style.left = `${e.pageX - tooltipRect.width - 15}px`;
                    }
                });
                
                projeto.addEventListener('mousemove', function(e) {
                    // Atualizar posição ao mover o mouse
                    const tooltipRect = projetoTooltip.getBoundingClientRect();
                    const viewportWidth = window.innerWidth;
                    
                    if (tooltipRect.right > viewportWidth) {
                        projetoTooltip.style.left = `${e.pageX - tooltipRect.width - 15}px`;
                    } else {
                        projetoTooltip.style.left = `${e.pageX + 15}px`;
                    }
                    projetoTooltip.style.top = `${e.pageY + 15}px`;
                });
                
                projeto.addEventListener('mouseleave', function() {
                    projetoTooltip.style.opacity = '0';
                });
            });
        }
        
        // Tooltip para bloqueios
        const bloqueioTooltip = document.getElementById('bloqueioTooltip');
        
        function initializeBloqueioTooltips() {
            document.querySelectorAll('.status-badge.status-bloqueado:not([data-bloqueio-initialized])').forEach(badge => {
                badge.setAttribute('data-bloqueio-initialized', 'true');
                
                badge.addEventListener('mouseenter', function(e) {
                    const motivo = this.getAttribute('data-motivo-bloqueio');
                    
                    // Estrutura do tooltip
                    bloqueioTooltip.innerHTML = `
                        <h4>Motivo do Bloqueio</h4>
                        <p>${motivo || 'Motivo não especificado.'}</p>
                    `;
                    
                    // Posicionar o tooltip
                    bloqueioTooltip.style.opacity = '1';
                    bloqueioTooltip.style.left = `${e.pageX + 15}px`;
                    bloqueioTooltip.style.top = `${e.pageY + 15}px`;
                    
                    // Evitar que o tooltip saia da tela
                    const tooltipRect = bloqueioTooltip.getBoundingClientRect();
                    const viewportWidth = window.innerWidth;
                    
                    if (tooltipRect.right > viewportWidth) {
                        bloqueioTooltip.style.left = `${e.pageX - tooltipRect.width - 15}px`;
                    }
                });
                
                badge.addEventListener('mousemove', function(e) {
                    // Atualizar posição ao mover o mouse
                    const tooltipRect = bloqueioTooltip.getBoundingClientRect();
                    const viewportWidth = window.innerWidth;
                    
                    if (tooltipRect.right > viewportWidth) {
                        bloqueioTooltip.style.left = `${e.pageX - tooltipRect.width - 15}px`;
                    } else {
                        bloqueioTooltip.style.left = `${e.pageX + 15}px`;
                    }
                    bloqueioTooltip.style.top = `${e.pageY + 15}px`;
                });
                
                badge.addEventListener('mouseleave', function() {
                    bloqueioTooltip.style.opacity = '0';
                });
            });
        }
        
        // Inicializar tooltips na primeira carga
        initializeProjectTooltips();
        initializeBloqueioTooltips();

        // Service Worker para performance (opcional)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .catch(() => console.log('Service Worker registration failed'));
            });
        }

        // Controle de performance para dispositivos mais lentos
        const isLowPerformanceDevice = () => {
            return navigator.hardwareConcurrency <= 2 || 
                   navigator.deviceMemory <= 2 ||
                   window.innerWidth < 768;
        };

        if (isLowPerformanceDevice()) {
            // Reduz animações em dispositivos com menor performance
            document.documentElement.style.setProperty('--transition', 'all 0.2s ease');
        }
    </script>
</body>
</html>
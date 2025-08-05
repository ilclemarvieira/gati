<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
// Incluir db.php para conexão com o banco de dados
include 'db.php';

$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

function formatarDataEmPortugues($data) {
    // Array de meses em português
    $meses = [
        '01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr',
        '05' => 'mai', '06' => 'jun', '07' => 'jul', '08' => 'ago',
        '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'
    ];

    // Converte a string de data para timestamp
    $timestamp = strtotime($data);

    // Extrai o dia, mês e ano
    $dia = date('d', $timestamp);
    $mes = date('m', $timestamp);
    $ano = date('Y', $timestamp);

    // Monta a data formatada
    return $dia . ' ' . $meses[$mes] . ' ' . $ano;
}

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
$perfisPermitidos = [1, 4, 9]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);

// Consulta para buscar todos os usuários
$usuarios = $pdo->query("SELECT * FROM usuarios")->fetchAll();

function formatarCPF($cpf) {
    $cpf = preg_replace("/\D/", '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

function buscarEventosProximos() {
    global $pdo;
    $hoje = date('Y-m-d'); // Data de hoje
    $inicioSemana = date('Y-m-d', strtotime('monday this week')); // Segunda-feira desta semana
    $fimSemana = date('Y-m-d', strtotime('sunday this week')); // Domingo desta semana

    // Garante que a data de início é a maior entre hoje e o início da semana
    $inicioEfetivo = max($hoje, $inicioSemana);

    // Busca os usuários com perfis 1, 2 ou 4
    $sqlUsuarios = "SELECT Id FROM usuarios WHERE PerfilAcesso IN (1, 2, 4)";
    $stmtUsuarios = $pdo->query($sqlUsuarios);
    $usuariosPermitidos = $stmtUsuarios->fetchAll(PDO::FETCH_COLUMN);

    if (empty($usuariosPermitidos)) {
        return []; // Retorna vazio se não houver usuários com esses perfis
    }

    // Preparar lista de IDs para consulta
    $usuariosPermitidos = implode(',', $usuariosPermitidos);

    // Consulta para buscar eventos dos usuários selecionados
    $sqlEventos = "SELECT * FROM eventos WHERE usuario_id IN ($usuariosPermitidos) AND data_inicio BETWEEN :inicioEfetivo AND :fimSemana ORDER BY data_inicio ASC, horario_inicio ASC";
    $stmtEventos = $pdo->prepare($sqlEventos);
    $stmtEventos->bindParam(':inicioEfetivo', $inicioEfetivo);
    $stmtEventos->bindParam(':fimSemana', $fimSemana);
    $stmtEventos->execute();
    return $stmtEventos->fetchAll();
}

// Busca os eventos da semana atual do perfil permitido
$eventosProximos = buscarEventosProximos();




function buscarUltimasOsUsuario($usuarioId) {
    global $pdo; // Usando a variável $pdo já existente para conexão com o banco
    $anoAtual = date('Y'); // Ano atual

    // A consulta agora exclui as OS com status 'Em Produção'
    $sql = "SELECT * FROM os 
            WHERE Responsavel = :usuarioId 
            AND YEAR(Dt_inicial) = :anoAtual 
            AND Status_contratada <> 'Em Produção' 
            ORDER BY Dt_inicial DESC 
            LIMIT 5"; // Limitando a busca aos últimos 5 registros
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmt->bindParam(':anoAtual', $anoAtual, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Busca as últimas OS do ano atual do usuário logado
$ultimasOs = buscarUltimasOsUsuario($_SESSION['usuario_id']);



function buscarTarefasAnoAtual($usuarioId) {
    global $pdo; // Usando a variável $pdo já existente para conexão com o banco
    $anoAtual = date('Y'); // Ano atual

    $sql = "SELECT * FROM (
                SELECT t.id, t.nome_tarefa, t.data_cadastro FROM tarefas t 
                WHERE t.id_usuario = :usuarioId AND YEAR(t.data_cadastro) = :anoAtual AND t.is_complete = 0
                UNION
                SELECT t.id, t.nome_tarefa, t.data_cadastro FROM tarefas t 
                INNER JOIN tarefas_compartilhadas tc ON t.id = tc.id_tarefa 
                WHERE tc.id_usuario_compartilhado = :usuarioIdCompartilhado AND YEAR(t.data_cadastro) = :anoAtual2 AND t.is_complete = 0
            ) AS combined_results
            ORDER BY data_cadastro DESC
            LIMIT 5";  // Limitando a busca aos últimos 8 registros
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmt->bindParam(':usuarioIdCompartilhado', $usuarioId, PDO::PARAM_INT);
    $stmt->bindParam(':anoAtual', $anoAtual, PDO::PARAM_INT);
    $stmt->bindParam(':anoAtual2', $anoAtual, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Busca as últimas 8 tarefas e tarefas compartilhadas do ano atual do usuário logado
$tarefasAnoAtual = buscarTarefasAnoAtual($_SESSION['usuario_id']);



function buscarUltimosSuportesUsuario($usuarioId) {
    global $pdo; // Usando a variável $pdo já existente para conexão com o banco
    $anoAtual = date('Y'); // Ano atual

    // A consulta agora exclui os suportes com status 'Resolvida' e 'Cancelada'
    $sql = "SELECT Id, Tarefa, Status_suporte FROM suporte 
            WHERE Solicitado_por = :usuarioId AND YEAR(Dt_criacao) = :anoAtual 
            AND Status_suporte NOT IN ('Resolvida', 'Cancelada')
            ORDER BY Dt_criacao DESC
            LIMIT 5";  // Limitando a busca aos últimos 5 registros
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmt->bindParam(':anoAtual', $anoAtual, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Busca os últimos suportes do ano atual do usuário logado
$ultimosSuportes = buscarUltimosSuportesUsuario($_SESSION['usuario_id']);




function contarOsAnoAtualUsuario($usuarioId) {
    global $pdo; // Usando a variável $pdo já existente para conexão com o banco
    $anoAtual = date('Y'); // Ano atual, ajuste manualmente para '2024' se necessário

    $sql = "SELECT COUNT(*) as total FROM os WHERE Responsavel = :usuarioId AND YEAR(Dt_inicial) = :anoAtual";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmt->bindParam(':anoAtual', $anoAtual, PDO::PARAM_INT);
    $stmt->execute();
    $resultado = $stmt->fetch();
    return $resultado['total'];
}

function contarOsFinalizadasAnoAtualUsuario($usuarioId) {
    global $pdo; // Usando a variável $pdo já existente para conexão com o banco
    $anoAtual = date('Y'); // Ano atual, ajuste manualmente para '2024' se necessário

    $sql = "SELECT COUNT(*) as total FROM os WHERE Responsavel = :usuarioId AND YEAR(Dt_inicial) = :anoAtual AND Status_inova = 'finalizado'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmt->bindParam(':anoAtual', $anoAtual, PDO::PARAM_INT);
    $stmt->execute();
    $resultado = $stmt->fetch();
    return $resultado['total'];
}

// Busca o total de OS e o total de OS finalizadas do ano atual do usuário logado
$totalOs = contarOsAnoAtualUsuario($_SESSION['usuario_id']);
$totalOsFinalizadas = contarOsFinalizadasAnoAtualUsuario($_SESSION['usuario_id']);

function contarTotalBacklogsUsuario($usuarioId) {
    global $pdo; // Usando a variável $pdo já existente para conexão com o banco
    $anoAtual = date('Y'); // Ano atual, ajuste manualmente para '2024' se necessário

    $sql = "SELECT COUNT(*) as total FROM backlog 
            WHERE Responsavel = :usuarioId AND YEAR(Dt_criacao) = :anoAtual";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuarioId', $usuarioId, PDO::PARAM_STR);
    $stmt->bindParam(':anoAtual', $anoAtual, PDO::PARAM_STR); // Garantindo que anoAtual também seja vinculado
    $stmt->execute();
    $resultado = $stmt->fetch();
    return $resultado['total'];
}

function contarBacklogsEncaminhadosOsUsuario($usuarioId) {
    global $pdo; // Usando a variável $pdo já existente para conexão com o banco
    $anoAtual = date('Y'); // Ano atual, ajuste manualmente para '2024' se necessário

    $sql = "SELECT COUNT(*) as total FROM backlog 
            WHERE Responsavel = :usuarioId AND YEAR(Dt_criacao) = :anoAtual AND Encaminhado_os = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuarioId', $usuarioId, PDO::PARAM_STR);
    $stmt->bindParam(':anoAtual', $anoAtual, PDO::PARAM_STR); // Garantindo que anoAtual também seja vinculado
    $stmt->execute();
    $resultado = $stmt->fetch();
    return $resultado['total'];
}


// Busca o total de backlogs do ano atual e o total encaminhado para OS do usuário logado
$totalBacklogs = contarTotalBacklogsUsuario($_SESSION['usuario_id']);
$totalBacklogsEncaminhadosOs = contarBacklogsEncaminhadosOsUsuario($_SESSION['usuario_id']);





function contarTarefasUsuario($usuarioId) {
    global $pdo;
    $anoAtual = date('Y');

    // Consulta para contar tarefas próprias pendentes
    $sqlPendentes = "SELECT COUNT(*) FROM tarefas WHERE id_usuario = :usuarioId AND YEAR(data_cadastro) = :anoAtual AND is_complete = 0";
    $stmtPendentes = $pdo->prepare($sqlPendentes);
    $stmtPendentes->bindParam(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmtPendentes->bindParam(':anoAtual', $anoAtual, PDO::PARAM_INT);
    $stmtPendentes->execute();
    $totalPendentes = $stmtPendentes->fetchColumn();

    // Consulta para contar tarefas próprias concluídas
    $sqlConcluidas = "SELECT COUNT(*) FROM tarefas WHERE id_usuario = :usuarioId AND YEAR(data_cadastro) = :anoAtual AND is_complete = 1";
    $stmtConcluidas = $pdo->prepare($sqlConcluidas);
    $stmtConcluidas->bindParam(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmtConcluidas->bindParam(':anoAtual', $anoAtual, PDO::PARAM_INT);
    $stmtConcluidas->execute();
    $totalConcluidas = $stmtConcluidas->fetchColumn();

    // O total geral de tarefas é a soma das pendentes e concluídas
    $totalTarefas = $totalPendentes + $totalConcluidas;

    return [
        'pendentes' => $totalPendentes,
        'concluidas' => $totalConcluidas,
        'total' => $totalTarefas
    ];
}

$infoTarefas = contarTarefasUsuario($_SESSION['usuario_id']);



function contarSuportesUsuario($usuarioId) {
    global $pdo; // Usando a variável $pdo já existente para conexão com o banco
    $anoAtual = date('Y'); // Obtem o ano atual dinamicamente

    // Consulta para contar suportes não resolvidos
    $sqlPendentes = "SELECT COUNT(*) FROM suporte 
                     WHERE Solicitado_por = :usuarioId AND YEAR(Dt_criacao) = :anoAtual 
                     AND Status_suporte != 'Resolvida'";
    $stmtPendentes = $pdo->prepare($sqlPendentes);
    $stmtPendentes->bindParam(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmtPendentes->bindParam(':anoAtual', $anoAtual, PDO::PARAM_STR);
    $stmtPendentes->execute();
    $totalPendentes = $stmtPendentes->fetchColumn();

    // Consulta para contar suportes resolvidos
    $sqlResolvidos = "SELECT COUNT(*) FROM suporte 
                      WHERE Solicitado_por = :usuarioId AND YEAR(Dt_criacao) = :anoAtual 
                      AND Status_suporte = 'Resolvida'";
    $stmtResolvidos = $pdo->prepare($sqlResolvidos);
    $stmtResolvidos->bindParam(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmtResolvidos->bindParam(':anoAtual', $anoAtual, PDO::PARAM_STR);
    $stmtResolvidos->execute();
    $totalResolvidos = $stmtResolvidos->fetchColumn();

    return [
        'pendentes' => $totalPendentes,
        'resolvidos' => $totalResolvidos,
        'total' => $totalPendentes + $totalResolvidos
    ];
}

// Busca o total de suportes do ano atual do usuário logado
$infoSuportes = contarSuportesUsuario($_SESSION['usuario_id']);

$nomeUsuario = $_SESSION['nome_usuario'] ?? 'Visitante'; // Use 'Visitante' como padrão se não estiver definido


// Preparar a consulta SQL para buscar os detalhes do suporte e os nomes dos usuários relacionados
$query = $pdo->prepare("
    SELECT s.*, us.Nome as NomeSolicitante, uc.Nome as NomeContratada
    FROM suporte s
    LEFT JOIN usuarios us ON s.Solicitado_por = us.Id
    LEFT JOIN usuarios uc ON s.Para_contratada = uc.Id
    WHERE s.Id = :id
");
$query->bindParam(':id', $suporteId, PDO::PARAM_INT);
$query->execute();


?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">

  <head>
    <?php include 'head.php'?>

<style>

/* Estilos Gerais */
.card-group {
    /* Remover overflow-x: auto; */
    overflow-x: visible; 
}

.col-lg-6 {
    position: relative; 
}


.card {
    background-color: #333;
    color: #fff;
    border: none;
    border-radius: 15px;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5);
    transition: transform 0.3s ease-in-out, box-shadow 0.3s ease;
    flex: 1 1 calc(33% - 20px);
    min-width: 300px; /* Ajuste conforme necessidade */
    margin: 10px;
}

.card:hover {
    transform: scale(1.05); /* Aumenta e eleva ligeiramente o card ao passar o mouse */
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.7); /* Sombra mais escura e distante */
}

.card-body {
    padding: 25px; /* Espaçamento interno do card */
}

/* Títulos e Números Principais */
.card-title {
    font-size: 60px; /* Tamanho grande para destaque */
    font-weight: bold; /* Negrito para mais ênfase */
    margin-bottom: 15px; /* Espaçamento após o título */
}

/* Números Grandes e Destacados */
.card-body h3 {
    font-size: 58px; /* Aumenta o tamanho da fonte dos números */
    font-weight: bold; /* Aplica negrito para destaque */
    line-height: 1; /* Ajusta o espaçamento da linha para evitar excesso de altura */
    margin-bottom: 0.25em; /* Espaçamento mínimo após o número para separar do título */
}

.card-subtitle {
    font-size: 20px; /* Subtítulo um pouco maior para harmonia */
    margin-bottom: 25px; /* Espaçamento após o subtítulo */
    font-weight: bold;
    opacity: 0.85; /* Transparência para suavizar */
}

/* Estilos para as Barras de Progresso */
.progress {
    height: 10px; /* Diminui a altura da barra de progresso */
    background-color: #444; /* Cor de fundo mais escura para contraste */
    border-radius: 5px; /* Cantos ligeiramente arredondados para a barra */
}

.progress-bar {
    border-radius: 5px; /* Mantém os cantos da barra arredondados */
    box-shadow: none; /* Sem sombra interna para um look limpo */
}

/* Tooltips ajustados para ficar acima da barra de progresso */
.tooltip {
    top: -30px; /* Posiciona acima da barra */
    transform: translateX(-50%); /* Centraliza o tooltip */
    font-size: 12px; /* Tamanho de fonte menor para tooltips */
    padding: 5px; /* Espaçamento interno dos tooltips */
    border-radius: 4px; /* Bordas arredondadas para os tooltips */
}

/* Informações Adicionais no Canto Superior Direito */
.additional-info {
    position: absolute; /* Posicionamento absoluto dentro do card */
    top: 10px; /* Espaçamento do topo do card */
    right: 10px; /* Espaçamento da direita do card */
    font-size: 14px; /* Tamanho do texto menor para não competir com o título principal */
    color: #ddd; /* Cor do texto para contraste */
    background: none; /* Remove o fundo para transparência */
    box-shadow: none; /* Remove sombra interna para um efeito mais suave */
    padding: 4px 8px; /* Padding reduzido */
    border-radius: 10px; /* Cantos arredondados */
    text-align: center;
}

/* Estilos para Informação "Enviado OS" e "Concluídos" */
.enviado-os {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: rgb(50 55 67); /* Fundo translúcido para combinar com o card */
    color: #fff; /* Cor do texto branca para melhor leitura */
    padding: 5px 10px; /* Espaçamento interno adequado */
    border-radius: 9px; /* Bordas arredondadas para suavidade */
    font-size: 14px; /* Tamanho da fonte legível */
    box-shadow: 0 2px 4px rgba(0,0,0,0.2); /* Sombra sutil para efeito de profundidade */
    text-align: center;
    font-weight: bold; /* Negrito para destacar sem ser intrusivo */
}

/* Responsividade */
@media (max-width: 768px) {
    .card {
        flex: 1 1 90%;
        min-width: auto;
    }

    .card {
        width: 90%; /* Ocupa mais espaço na tela em dispositivos móveis */
        margin-bottom: 20px; /* Espaçamento entre os cards */
    }

     .card-body h3 {
        font-size: 48px; /* Tamanho do número reduzido para dispositivos móveis */
    }

    .card-title {
        font-size: 48px; /* Tamanho do número reduzido para dispositivos móveis */
    }
}

.total-annual-container {
    display: flex;
    justify-content: space-between;
    margin-bottom: 2rem; /* Espaço antes da tabela */
}

.total-annual-value {
    text-align: center;
}

.total-annual-value h2 {
    font-size: 25px;
    font-weight: 700;
    color: #2a7c8c; /* Cores distintas para pagas/não pagas */
    margin: 0; /* Remove margens padrão */
}

.total-annual-value h6 {
    font-size: 1rem;
    font-weight: 400;
    color: #a4acc4; /* Cor suave para os subtítulos */
}

.total-annual-value.unpaid h2 {
    color: #9f5568;
}

.status-table td {
    vertical-align: middle; /* Alinha o conteúdo da tabela verticalmente */
    padding: .5rem; /* Espaçamento reduzido para uma tabela mais compacta */
}

.status-table .badge {
    font-weight: 500;
    padding: .375rem .75rem;
    border-radius: .25rem; /* Bordas arredondadas para os badges */
}

/* Estilos para ícones */
.status-table img {
    width: 24px; /* Tamanho fixo para ícones */
}

.username-highlight {
    color: #a5a6a8; /* Cor verde para o nome do usuário */
    font-size: 16px; /* Tamanho menor da fonte para o nome */
    font-weight: 500; /* Torna o nome do usuário em negrito para destaque */
}

#viewDescricao {
    white-space: pre-wrap;
    word-break: break-word;
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
    background-color: #2A2D34; /* Cor de fundo para modal */
    color: #FFFFFF; /* Cor do texto para modal */
    border-radius: 8px; /* Bordas arredondadas para modal */
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
    color: #CCCCCC;
    float: right;
    font-size: 30px;
    padding-bottom: 10px;
    margin-bottom: 10px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #FFFFFF;
    text-decoration: none;
    cursor: pointer;
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
    color: #fff;
    /* Remova as linhas abaixo que causam o problema */
    /* display: flex; */
    /* justify-content: space-between; */
    word-break: break-word; /* Se precisar quebrar palavras muito longas */
    white-space: pre-wrap; /* Mantém as quebras de linha do texto */
}


.os-details b {
    font-weight: 500;
    color: #333;
    margin-right: 15px; /* Spacing between label and value */
}

.fc-draggable {
    z-index: 100; /* Mantém o card no topo durante o arrasto */
    opacity: 0.8; /* Torna o card transparente durante o arrasto */
}

.card-group .fc-draggable {
    margin-bottom: 10px; /* Garante espaço entre os cards */
}

.card-group {
    min-height: 191px; /* Assegura altura suficiente para a área de soltar */
}

.fc-draggable {
    cursor: pointer; /* Altera o cursor para indicar que pode ser arrastado */
}

.fc-draggable:hover {
    box-shadow: 0px 0px 10px #666; /* Sombra para destacar o card selecionado */
}

.card-group .ui-droppable-hover {
    background-color: #f8f9fa; /* Muda a cor de fundo ao passar por cima com um card */
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
        <link href="assets/libs/fullcalendar/dist/fullcalendar.min.css" rel="stylesheet" />


<!-- Inclusão do jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Inclusão do Popper.js (necessário para Bootstrap 4, opcional para Bootstrap 5) -->
<script src="https://unpkg.com/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
      </header> 

       <?php include 'sidebar.php'?> 

      <div class="page-wrapper">
       
        <div class="row page-titles">
          <div class="col-md-5 col-12 align-self-center">
           <?php 
// Define o fuso horário para São Paulo
date_default_timezone_set('America/Sao_Paulo');

// Obtém a hora atual
$horaAtual = date('H');

// Define a saudação baseada na hora do dia
if ($horaAtual < 5) {
    $saudacao = 'Boa madrugada';
} elseif ($horaAtual < 12) {
    $saudacao = 'Bom dia';
} elseif ($horaAtual < 18) {
    $saudacao = 'Boa tarde';
} else {
    $saudacao = 'Boa noite';
}
?>

<h3 class="text-themecolor mb-0" style="font-size: 16px; font-weight: 300;">
    <i class="mdi mdi-desktop-mac"></i> <?php echo $saudacao; ?>, <span class="username-highlight"><?php echo htmlspecialchars($nomeUsuario); ?>!</span>
</h3>


          
          </div>          
        </div>       
        <div class="container-fluid">


           <div class="card-group">

<!-- Card OS Criadas -->
<div class="card bg-success">
    <div class="card-body text-white">
        <div class="row">
            <div class="col-12">
                <h3 id="totalOs" style="color: #fff"><?php echo $totalOs; ?></h3>
                <h6 class="card-subtitle" style="font-weight: bold; color:#fff">Minhas OS</h6>
            </div>
            <div class="col-12">
                <div class="progress">
                    <?php
                    $percentComplete = ($totalOs > 0) ? ($totalOsFinalizadas / $totalOs * 100) : 0;
                    ?>
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percentComplete; ?>%" aria-valuenow="<?php echo $percentComplete; ?>" aria-valuemin="0" aria-valuemax="100" title="Completo: <?php echo $percentComplete; ?>%"></div>
                </div>
                <div class="enviado-os">Em produção: <?php echo $totalOsFinalizadas; ?></div>
            </div>
        </div>
    </div>
</div>


<!-- Card Backlog -->
<div class="card bg-info">
    <div class="card-body">
        <div class="row">
            <div class="col-12">
                <h3 id="totalBacklogs" style="color: #fff"><?php echo $totalBacklogs; ?></h3>
                <h6 class="card-subtitle" style="font-weight: bold; color:#fff">Backlog</h6>
            </div>
            <div class="col-12">
                <div class="progress">
                    <?php
                    $percentComplete = ($totalBacklogs > 0) ? ($totalBacklogsEncaminhadosOs / $totalBacklogs * 100) : 0;
                    ?>
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentComplete; ?>%;" aria-valuenow="<?php echo $percentComplete; ?>" aria-valuemin="0" aria-valuemax="100" title="Progresso: <?php echo $percentComplete; ?>%"></div>
                </div>
                <div class="enviado-os">Enviado para OS: <?php echo $totalBacklogsEncaminhadosOs; ?></div>
            </div>
        </div>
    </div>
</div>



    <!-- Tarefas pendentes -->
<div class="card bg-warning">
    <div class="card-body">
        <div class="row">
            <div class="col-12">
                <h3 id="totalBacklogBi" style="color: #fff"><?php echo $infoTarefas['pendentes']; ?></h3>
                <h6 class="card-subtitle" style="font-weight: bold; color:#fff">Tarefas Pendentes</h6>
                <div class="progress">
                    <?php
                    $percentComplete = ($infoTarefas['total'] > 0) ? ($infoTarefas['concluidas'] / $infoTarefas['total'] * 100) : 0;
                    ?>
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $infoTarefas['total'] > 0 ? ($infoTarefas['concluidas'] / $infoTarefas['total'] * 100) : 0; ?>%;" aria-valuenow="<?php echo $infoTarefas['total'] > 0 ? ($infoTarefas['concluidas'] / $infoTarefas['total'] * 100) : 0; ?>" aria-valuemin="0" aria-valuemax="100" title="Concluído: <?php echo $infoTarefas['total'] > 0 ? number_format(($infoTarefas['concluidas'] / $infoTarefas['total'] * 100), 2) : '0.00'; ?>%"></div>

                </div>
                <div class="enviado-os">Concluídas: <?php echo $infoTarefas['concluidas']; ?></div>

            </div>
        </div>
    </div>
</div>








<!-- Card Suportes Pendentes -->
<div class="card bg-danger">
    <div class="card-body">
        <div class="row">
            <div class="col-12">
                <h3 id="totalSuporte" style="color: #fff"><?php echo $infoSuportes['pendentes']; ?></h3>
                <h6 class="card-subtitle" style="font-weight: bold; color:#fff">Suportes Pendentes</h6>
            </div>
            <div class="col-12">
                <div class="progress">
                    <?php
                    $percentComplete = ($infoSuportes['total'] > 0) ? ($infoSuportes['resolvidos'] / $infoSuportes['total'] * 100) : 0;
                    ?>
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentComplete; ?>%;" aria-valuenow="<?php echo $percentComplete; ?>" aria-valuemin="0" aria-valuemax="100" title="Resolvidos: <?php echo $percentComplete; ?>%"></div>
                </div>
                <div class="enviado-os" id="suportesResolvidos">Concluídos: <?php echo $infoSuportes['resolvidos']; ?></div>
            </div>
        </div>
    </div>
</div>


</div>



         <div class="row">
     
            
          <div class="col-lg-6 d-flex align-items-stretch" id="coluna-1">
        <div class="card w-100 fc-draggable" id="card-agenda-semanal">
        <div class="card-body">
            <div class="d-md-flex align-items-center">
                <div>
                    <h4 class="card-title" style="font-size:20px"><i class="mdi mdi-account-box"></i> AGENDA SEMANAL</h4>
                </div>                    
            </div>
            <div class="table-responsive">
                <table class="table stylish-table mt-4 no-wrap v-middle">
                    <thead align="center">
                        <tr>
                            <th class="border-0 text-muted font-weight-medium" style="width: 90px">
                                Data
                            </th>
                            <th class="border-0 text-muted font-weight-medium">
                                Evento
                            </th> 
                            <th class="border-0 text-muted font-weight-medium">
                                Horário
                            </th>                                                  
                        </tr>
                    </thead>
                    <tbody id="ultimosBacklogsBIBody">
                     <?php 
// Array para mapear os dias da semana de inglês para português
$mapaDias = [
    'Sunday' => 'Domingo',
    'Monday' => 'Segunda',
    'Tuesday' => 'Terça',
    'Wednesday' => 'Quarta',
    'Thursday' => 'Quinta',
    'Friday' => 'Sexta',
    'Saturday' => 'Sábado'
];

if (!empty($eventosProximos)): ?>
    <?php foreach ($eventosProximos as $evento): ?>
        <?php 
        // Determina o nome do dia da semana em inglês
        $diaSemanaIngles = date('l', strtotime($evento['data_inicio']));
        // Traduz para português usando o array de mapeamento
        $diaSemana = $mapaDias[$diaSemanaIngles] ?? $diaSemanaIngles;
        // Prepara a string da data
        $dataEvento = date('d/m', strtotime($evento['data_inicio']));
        // Determina se o evento é hoje, amanhã ou outro dia
        if (date('Y-m-d') == date('Y-m-d', strtotime($evento['data_inicio']))) {
            $dataFormatada = "<span class='badge' style='background-color: #974157;'>Hoje</span>";
        } elseif (date('Y-m-d', strtotime('tomorrow')) == date('Y-m-d', strtotime($evento['data_inicio']))) {
            $dataFormatada = "<span class='badge bg-info'>Amanhã - " . $dataEvento . "</span>";
        } else {
            $dataFormatada = "<span class='badge bg-info'>" . $diaSemana . " - " . $dataEvento . "</span>";
        }
        ?>
        <tr>
            <td style="text-align: center; vertical-align: middle;">
                <?php echo $dataFormatada; ?>
            </td>
            <td style="text-align: center; vertical-align: middle; text-transform: uppercase;">
                <h6 class="mb-0 font-weight-medium">
                    <a href="agenda.php?evento_id=<?php echo $evento['id']; ?>" class="link"> <?php echo htmlspecialchars($evento['titulo']); ?></a>
                </h6>                            
            </td>
            <td style="text-align: center; vertical-align: middle;">
                <?php echo date('H:i', strtotime($evento['horario_inicio'])); ?>
            </td> 

<td style="text-align: center; vertical-align: middle;">
    <button type="button" class="btn btn-outline-secondary" onclick="viewMeta(<?php echo $evento['id']; ?>)" title="Ver evento">
                    <i class="fas fa-eye"></i>
                </button>
</td>



<script>
function viewMeta(evento_id) {
    window.location.href = 'agenda.php?evento_id=' + evento_id;
}
</script>
                         
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="3" style="text-align: center; vertical-align: middle;">
            Não existe eventos essa semana
        </td>
    </tr>
<?php endif; ?>


                    </tbody>

                </table>
            </div>
        </div>
    </div>
</div>






    <div class="col-lg-6 d-flex align-items-stretch" id="coluna-2">
        <div class="card w-100 fc-draggable" id="card-ultimas-os">
    <div class="card-body">
      <h4 class="card-title" style="font-size:20px"><i class="mdi mdi-slack"></i> ÚLTIMAS OS</h4>
      <div class="table-responsive">
        <table class="table stylish-table mt-4 no-wrap v-middle">
          <thead align="center">
            <tr>
              <th class="border-0 text-muted font-weight-medium" style="width: 90px">N° OS</th>
              <th class="border-0 text-muted font-weight-medium">Projeto</th>
              <th class="border-0 text-muted font-weight-medium">Status</th>
            </tr>
          </thead>
          <tbody id="ultimasOsDesenvolvimentoBody">
    <?php if (!empty($ultimasOs)): ?>
        <?php foreach ($ultimasOs as $os): ?>
            <tr>
                <td style="text-align: center; vertical-align: middle;">
                    <span class="badge bg-info"><?php echo htmlspecialchars($os['N_os']); ?></span>
                </td>
                <td style="text-align: center; vertical-align: middle; text-transform: uppercase;">
                    <h6 class="mb-0 font-weight-medium">
                        <a href="javascript:void(0)" class="link">
                            <?php 
                            // Exibindo no máximo 50 caracteres. Ajuste conforme necessário.
                            $nomeOs = htmlspecialchars($os['Nome_os']);
                            echo mb_strlen($nomeOs) > 50 ? mb_substr($nomeOs, 0, 30) . "..." : $nomeOs;
                            ?>
                        </a>
                    </h6>
                </td>

                <td style="text-align: center; vertical-align: middle;">
                    <h6 class="mb-0 font-weight-medium">
                        <a href="javascript:void(0)" class="link"><?php echo htmlspecialchars($os['Status_contratada']); ?></a>
                    </h6>
                </td>
               <td style="text-align: center; vertical-align: middle;">

       <button type="button" class="btn btn-outline-secondary" onclick="viewOS(<?php echo $os['Id']; ?>)" title="Ver OS">
    <i class="fas fa-eye"></i>
</button>

</td>






            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="3" style="text-align: center; vertical-align: middle;">
                Não há OS atribuídas a você
            </td>
        </tr>
    <?php endif; ?>
</tbody>

        </table>
      </div>
    </div>
  </div>
</div>



</div>

<div class="row">
     
            
             <div class="col-lg-6 d-flex align-items-stretch" id="coluna-3">
        <div class="card w-100 fc-draggable" id="card-ultimas-tarefas">
              <div class="card-body">
                <div class="d-md-flex align-items-center">
                  <div>
                    <h4 class="card-title" style="font-size:20px"><i class="mdi mdi-book"></i> ÚLTIMAS TAREFAS</h4>
                  </div>                    
                </div>
                <div class="table-responsive">
    <table class="table stylish-table mt-4 no-wrap v-middle">
        <thead align="center">
            <tr>
                <th class="border-0 text-muted font-weight-medium" style="width: 90px">
                    Data
                </th>
                <th class="border-0 text-muted font-weight-medium">
                    Tarefa
                </th>                                                   
            </tr>
        </thead>
        <tbody id="ultimosBacklogsBIBody">
            <?php if (!empty($tarefasAnoAtual)): ?>
                <?php foreach ($tarefasAnoAtual as $tarefa): ?>
                    <tr>
                       <td style="text-align: center; vertical-align: middle;">
    <span class="badge bg-info">
        <?php 
        echo formatarDataEmPortugues($tarefa['data_cadastro']); 
        ?>
    </span>
</td>
<td style="text-align: center; vertical-align: middle; text-transform: uppercase;">
    <h6 class="mb-0 font-weight-medium">
        <a href="javascript:void(0)" class="link">
            <?php 
            $nomeTarefa = htmlspecialchars($tarefa['nome_tarefa']);
            echo (mb_strlen($nomeTarefa) > 50) ? mb_substr($nomeTarefa, 0, 30) . "..." : $nomeTarefa;
            ?>
        </a>
    </h6>
</td>
                        
                        
                       <td style="text-align: center; vertical-align: middle;">
    <button type="button" class="btn btn-outline-secondary btn-view-tarefa" data-tarefa-id="<?php echo $tarefa['id']; ?>" title="Ver Tarefa">
        <i class="fas fa-eye"></i>
    </button>
</td>
                        
                         
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="2" style="text-align: center; vertical-align: middle;">
                        Não há tarefas para este ano.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

              </div>
            </div>
          </div>
    



    <div class="col-lg-6 d-flex align-items-stretch" id="coluna-4">
        <div class="card w-100 fc-draggable" id="card-ultimos-suportes">
    <div class="card-body">
      <h4 class="card-title" style="font-size:20px"><i class="mdi mdi-headset"></i> ÚLTIMOS SUPORTES</h4>
      <div class="table-responsive">
        <table class="table stylish-table mt-4 no-wrap v-middle">
          <thead align="center">
            <tr>
              <th class="border-0 text-muted font-weight-medium" style="width: 90px">Código</th>
              <th class="border-0 text-muted font-weight-medium">Tarefa</th>
              <th class="border-0 text-muted font-weight-medium">Status</th>
            </tr>
          </thead>
          <tbody id="ultimosSuportesBody">
                <?php if (!empty($ultimosSuportes)): ?>
                    <?php foreach ($ultimosSuportes as $suporte): ?>
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                <span class="badge bg-info"><?php echo htmlspecialchars($suporte['Id']); ?></span>
                            </td>
                            <td style="text-align: center; vertical-align: middle; text-transform: uppercase;">
                                <h6 class="mb-0 font-weight-medium">
                                    <a href="javascript:void(0)" class="link">
                                        <?php
                                        // Limitando a exibição do nome da tarefa a 50 caracteres
                                        $tarefa = htmlspecialchars($suporte['Tarefa']);
                                        echo (mb_strlen($tarefa) > 50) ? mb_substr($tarefa, 0, 30) . "..." : $tarefa;
                                        ?>
                                    </a>
                                </h6>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <h6 class="mb-0 font-weight-medium">
                                    <?php echo htmlspecialchars($suporte['Status_suporte']); ?>
                                </h6>
                            </td>
                             <td style="text-align: center; vertical-align: middle;">

       <button type="button" class="btn btn-outline-secondary" onclick="viewSuporte(<?php echo $suporte['Id']; ?>)" title="Ver Suporte">
    <i class="fas fa-eye"></i>
</button>

</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; vertical-align: middle;">
                            Não há suportes para este ano.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>

        </table>
      </div>
    </div>
  </div>
</div>




</div>

    
        </div>


<!-- Modal de Visualização de OS -->
<div id="viewOsModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="mdi mdi-eye-outline"></i>&nbsp; Visualizar O.S
                </h2>
                <span class="close" onclick="closeModal('viewOsModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="os-details">
                    <div class="mb-3">
                        <h3 class="form-label">Nº OS</h3>
                        <p id="viewNumeroOs"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Nome da OS</h3>
                        <p id="viewNomeOs"></p>
                    </div>
                    <div class="mb-3" id="viewApfContainer">
                        <h3 class="form-label">PF</h3>
                        <p id="viewApf"></p>
                    </div>
                    <div class="mb-3" id="viewValorContainer">
                        <h3 class="form-label">Valor</h3>
                        <p id="viewValor"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Data Inicial</h3>
                        <p id="viewDtInicial"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Prioridade</h3>
                        <p id="viewPrioridade"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Status</h3>
                        <p id="viewStatusContratada"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Responsável</h3>
                        <p id="viewResponsavel"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Descrição da O.S</h3>
                        <p id="viewDescricao"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="submit-btn" onclick="closeModal('viewOsModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>




          
<!-- Modal de Visualização de Suporte -->
<div id="viewSupportModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalVisualizacaoSuporteLabel">
                    <i class="mdi mdi-eye-outline"></i>&nbsp;Visualizar Tarefa
                </h2>
                <span class="close" onclick="closeModal('viewSupportModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="os-details">
        <div class="mb-3" id="tarefaContainer">
            <h3 class="form-label">Tarefa</h3>
            <p id="viewTarefa"></p>
        </div>
        <div class="mb-3" id="dtCriacaoContainer">
            <h3 class="form-label">Data de Criação</h3>
            <p id="viewDtCriacao"></p>
        </div>
        <div class="mb-3" id="prioridadeContainer">
            <h3 class="form-label">Prioridade</h3>
            <p id="viewPrioridade"></p>
        </div>
        <div class="mb-3" id="statusSuporteContainer">
            <h3 class="form-label">Status</h3>
            <p id="viewStatusSuporte"></p>
        </div>
        <div class="mb-3" id="solicitadoPorContainer">
            <h3 class="form-label">Solicitado por</h3>
            <p id="viewSolicitadoPor"></p>
        </div>
        <div class="mb-3" id="contratadaContainer">
            <h3 class="form-label">Responsável</h3>
            <p id="viewContratada"></p>
        </div>
        <div class="mb-3" id="prazoPrevistoContainer">
            <h3 class="form-label">Prazo Previsto</h3>
            <p id="viewPrazoPrevisto"></p>
        </div>
        <div class="mb-3" id="observacaoContainer">
            <h3 class="form-label">Descrição da Tarefa</h3>
            <p id="viewObservacao" style="word-wrap: break-word; overflow-wrap: break-word;"></p>
        </div>

    </div>
</div>


            <div class="modal-footer">
                <button type="button" class="submit-btn" onclick="closeModal('viewSupportModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>
          
          
<!-- Modal de Visualização de Tarefa -->
<div id="viewTarefaModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTarefaLabel">
                    <i class="mdi mdi-eye-outline"></i>&nbsp;Visualizar Tarefa
                </h2>
                <span class="close" onclick="closeModal('viewTarefaModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="os-details">
                    <div class="mb-3">
                        <h3 class="form-label">Nome da Tarefa</h3>
                        <p id="modalTarefaTitulo"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Descrição</h3>
                        <p id="modalTarefaDescricao" style="word-wrap: break-word; overflow-wrap: break-word;"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Criado por</h3>
                        <p id="modalTarefaUsuario"></p>
                    </div>
                    <div class="mb-3">
                        <h3 class="form-label">Compartilhado com</h3>
                        <p id="modalTarefaCompartilhado"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="submit-btn" onclick="closeModal('viewTarefaModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Fechar
                </button>
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
    $(".fc-draggable").draggable({
        helper: 'clone',
        revert: 'invalid',
        start: function(event, ui) {
            $(ui.helper).addClass("dragging");
        },
        stop: function(event, ui) {
            $(ui.helper).removeClass("dragging");
        }
    });

    $(".col-lg-6").droppable({
        accept: ".fc-draggable",
        tolerance: 'pointer',
        drop: function(event, ui) {
            var droppedOn = $(this);
            var dragged = ui.draggable;
            var draggedFrom = dragged.parent();

            // Verifica se o card está sendo solto na mesma coluna ou em uma coluna diferente com menos de 2 cards
            if (!droppedOn.is(draggedFrom) && droppedOn.find('.card').length < 2) {
                // Troca os cards se houver já um card na coluna de destino
                if (droppedOn.find('.card').length === 1) {
                    var cardToSwap = droppedOn.find('.card').first();
                    draggedFrom.append(cardToSwap);
                }

                // Move o card arrastado para a nova coluna
                droppedOn.append(dragged);

                // Salva a nova posição dos dois cards envolvidos
                salvarPosicao(dragged.attr('id'), droppedOn.attr('id').replace('coluna-', ''));
                if (cardToSwap) {
                    salvarPosicao(cardToSwap.attr('id'), draggedFrom.attr('id').replace('coluna-', ''));
                }
            }

            // Reseta os estilos após a movimentação
            $('.fc-draggable').css({top: '0px', left: '0px'});
        }
    });

    function salvarPosicao(cardId, novaPosicao) {
        $.ajax({
            url: 'salvar_posicao.php',
            method: 'POST',
            data: {
                card_id: cardId,
                posicao: parseInt(novaPosicao) // Assegura que o dado enviado é um número
            },
            success: function(response) {
                console.log('Resposta:', response);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('Erro ao salvar posição:', textStatus, errorThrown);
            }
        });
    }

    // Carregar as posições salvas dos cards ao inicializar a página
    $.ajax({
        url: 'carregar_posicoes.php',
        method: 'GET',
        dataType: 'json',
        success: function(posicoes) {
            posicoes.forEach(function(pos) {
                var card = $('#' + pos.card_id);
                var colunaId = 'coluna-' + pos.posicao;
                $('#' + colunaId).append(card).css({ top: '0px', left: '0px' });
            });
        },
        error: function() {
            console.error('Erro ao carregar as posições dos cards.');
        }
    });
});
</script>













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
function formatDate(dateString) {
    if (!dateString || new Date(dateString).getFullYear() < 1970) {
        return ''; // Retorna vazio se não for uma data válida
    }
    var date = new Date(dateString);
    var day = ('0' + date.getDate()).slice(-2); // Adiciona zero à esquerda
    var month = ('0' + (date.getMonth() + 1)).slice(-2);
    var year = date.getFullYear();
    return day + '/' + month + '/' + year;
}

function convertLineBreaksToHtml(text) {
    return text.replace(/\n/g, '<br>'); // Substitui todas as quebras de linha por <br>
}

function viewSuporte(suporteId) {
    $.ajax({
        url: 'fetch_suporte_details.php',  // URL para buscar os detalhes do suporte
        type: 'POST',
        dataType: 'json',
        data: { id: suporteId },
        success: function(response) {
            if (response.success) {
                // Atualiza os campos do modal com os dados recebidos
                $('#viewTarefa').text(response.data.Tarefa);
                $('#viewDtCriacao').text(formatDate(response.data.Dt_criacao));
                $('#viewPrioridade').text(response.data.Prioridade);
                $('#viewStatusSuporte').text(response.data.Status_suporte);
                $('#viewSolicitadoPor').text(response.data.NomeSolicitante);
                $('#viewContratada').text(response.data.NomeContratada);
                $('#viewPrazoPrevisto').text(formatDate(response.data.Prazo_previsto));
                $('#viewObservacao').html(convertLineBreaksToHtml(response.data.Observacao)); // Aplica a conversão de quebras de linha

                // Abre o modal
                $('#viewSupportModal').fadeIn(); // Use fadeIn para um efeito suave
            } else {
                alert('Erro ao carregar os detalhes: ' + response.error);
            }
        },
        error: function() {
            alert('Erro ao fazer a requisição');
        }
    });
}

function closeModal(modalId) {
    $('#' + modalId).fadeOut(); // Use fadeOut para fechar o modal suavemente
}


</script>
      
      
<script>
$(document).ready(function() {
    // Evento de clique para todos os botões com a classe btn-view-tarefa
    $('.btn-view-tarefa').on('click', function() {
        var tarefaId = $(this).data('tarefa-id');  // Recupera o ID da tarefa do atributo data-tarefa-id
        viewTarefa(tarefaId);  // Chama a função viewTarefa passando o ID da tarefa
    });
});

function viewTarefa(tarefaId) {
    $.ajax({
        url: 'fetch_tarefa_details.php',  // URL do servidor para buscar os detalhes da tarefa
        type: 'POST',
        dataType: 'json',
        data: { id: tarefaId },  // Dados enviados ao servidor, o ID da tarefa
        success: function(response) {
            if (response.success) {
                // Atualiza os elementos do modal com os dados da resposta
                $('#modalTarefaTitulo').text(response.data.nome_tarefa);
                $('#modalTarefaDescricao').html(convertLineBreaksToHtml(response.data.descricao_tarefa));
                $('#modalTarefaUsuario').text(response.data.nome_usuario);
                $('#modalTarefaCompartilhado').text(response.data.nome_usuario_compartilhado);
                
                // Mostra o modal utilizando fadeIn para efeito suave
                $('#viewTarefaModal').fadeIn();
            } else {
                alert('Erro ao carregar detalhes: ' + response.error);
            }
        },
        error: function() {
            alert('Erro ao fazer a requisição');
        }
    });
}

function closeModal(modalId) {
    $('#' + modalId).fadeOut(); // Fecha o modal utilizando fadeOut
}
</script>


<script>
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

function convertLineBreaksToHtml(text) {
    return text.replace(/\n/g, '<br>'); // Substitui todas as quebras de linha por <br>
}

function viewOS(osId) {
    $.ajax({
        url: 'fetch_os_details.php',  // Caminho para o script PHP que busca os dados da OS
        type: 'POST',
        dataType: 'json',
        data: { id: osId },
        success: function(response) {
            if (response.success) {
                // Atualiza os campos do modal com os dados da OS
                $('#viewNumeroOs').text(response.data.N_os);
                $('#viewNomeOs').text(response.data.Nome_os);
                if (response.data.Apf) {
                    $('#viewApf').text(response.data.Apf);
                    $('#viewApfContainer').show();
                } else {
                    $('#viewApfContainer').hide();
                }
                if (response.data.Valor) {
                    $('#viewValor').text(formatCurrency(response.data.Valor));
                    $('#viewValorContainer').show();
                } else {
                    $('#viewValorContainer').hide();
                }
                $('#viewDtInicial').text(response.data.Dt_inicial);
                $('#viewPrioridade').text(response.data.Prioridade);
                $('#viewStatusContratada').text(response.data.Status_contratada);
                $('#viewResponsavel').text(response.data.Responsavel);
                $('#viewDescricao').text(response.data.Descricao);
                
                // Abre o modal
                $('#viewOsModal').fadeIn();
            } else {
                alert('Erro ao carregar os detalhes: ' + response.error);
            }
        },
        error: function() {
            alert('Erro ao fazer a requisição');
        }
    });
}

function closeModal(modalId) {
    $('#' + modalId).fadeOut();
}



</script>


  </body>
</html>

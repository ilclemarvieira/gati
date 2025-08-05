<?php
// Definindo o tempo máximo da sessão para 6 horas (21600 segundos)
ini_set('session.gc_maxlifetime', 21600);

// Iniciando a sessão
session_start();

// Lembre-se de configurar o cookie de sessão para ter a mesma duração
$params = session_get_cookie_params();
setcookie(session_name(), $_COOKIE[session_name()], time() + 21600,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
);


include 'db.php';

// Definição de zona horária para evitar problemas com datas
date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}

// As operações do banco de dados aqui estão corretas, após a inclusão do db.php
if (isset($_SESSION['usuario_id'])) {
    $query = $pdo->prepare("SELECT * FROM usuarios WHERE Id = :usuario_id");
    $query->bindParam(':usuario_id', $_SESSION['usuario_id']);
    $query->execute();
    $dadosUsuario = $query->fetch(PDO::FETCH_ASSOC);
    $_SESSION['EmpresaId'] = $dadosUsuario['EmpresaId'];  // Certifique-se que esta chave existe no array
} else {
    header('Location: login');
    exit;
}


// Se o parâmetro 'year' não estiver definido na URL, utiliza o ano atual como filtro
if (!isset($_GET['year']) || empty($_GET['year'])) {
    $_GET['year'] = date('Y'); // Define o ano atual para 2024 durante o ajuste
}

$_SESSION['EmpresaId'] = $dadosUsuario['EmpresaId']; // Assumindo que $dadosUsuario é sua variável com dados do usuário

$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        // Redireciona para a página anterior ou para uma página padrão
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'index'; // Define 'index.php' como fallback
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}

// Supondo que $_SESSION['usuario_id'] armazena o ID do usuário logado
$usuarioLogadoId = $_SESSION['usuario_id'];  // ou outra lógica que você use para identificar o usuário logado

// Prepare sua query de atualização (substitua com seus campos específicos conforme necessário)
$updateQuery = "UPDATE suporte SET 
                    Tarefa = :tarefa, 
                    Prioridade = :prioridade, 
                    Status_suporte = :status_suporte, 
                    Dt_alteracao = NOW(), 
                    Alterado_por = :alterado_por
                WHERE Id = :id";

$updateStmt = $pdo->prepare($updateQuery);
$updateStmt->bindParam(':tarefa', $tarefa);
$updateStmt->bindParam(':prioridade', $prioridade);
$updateStmt->bindParam(':status_suporte', $statusSuporte);
$updateStmt->bindParam(':alterado_por', $usuarioLogadoId);
$updateStmt->bindParam(':id', $idTarefa);
$updateStmt->execute();





// No início de cada página restrita, chame verificarPermissao com os perfis permitidos
$perfisPermitidos = [1, 2, 3, 4, 6]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);



$empresaIdUsuarioLogado = $_SESSION['EmpresaId'] ?? null;

// Agora, modifique a consulta para filtrar pelo EmpresaId
$suportes = $pdo->prepare("
    SELECT s.*, u.Nome as NomeSolicitante, c.Nome as NomeContratada, a.Nome as NomeAlterador
    FROM suporte s
    LEFT JOIN usuarios u ON s.Solicitado_por = u.Id
    LEFT JOIN usuarios a ON s.Alterado_por = a.Id
    LEFT JOIN contratadas c ON s.Para_contratada = c.Id
    WHERE s.Para_contratada = :empresaIdUsuarioLogado
");

$suportes->bindParam(':empresaIdUsuarioLogado', $empresaIdUsuarioLogado);
$suportes->execute();
$resultadoSuportes = $suportes->fetchAll(PDO::FETCH_ASSOC);




$contratadas = $pdo->query("SELECT Id, Nome FROM contratadas")->fetchAll(PDO::FETCH_ASSOC);



// Adicione no início do arquivo para buscar os usuários cadastrados
$usuarios = $pdo->query("SELECT Id, Nome FROM usuarios WHERE PerfilAcesso IN (1, 2, 4) ORDER BY Nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$statusOptions = ["Não iniciada", "Em análise", "Paralisada", "Em atendimento", "Cancelada", "Resolvida"];

// Preparação das cláusulas WHERE baseado nos filtros
$whereClauses = [];
$params = [];

// Filtro por Ano de Criação
if (isset($_GET['year']) && $_GET['year'] !== '') {  // Verifica se um ano específico foi escolhido
    $whereClauses[] = 'YEAR(s.Dt_criacao) = :year';
    $params[':year'] = $_GET['year'];
}


// Filtro por Solicitado Por
if (isset($_GET['solicitado_por']) && $_GET['solicitado_por'] != '') {
    $whereClauses[] = 's.Solicitado_por = :solicitado_por';
    $params[':solicitado_por'] = $_GET['solicitado_por'];
}

// Filtro por Status do Suporte
if (isset($_GET['status_suporte']) && $_GET['status_suporte'] != '') {
    $whereClauses[] = 's.Status_suporte = :status_suporte';
    $params[':status_suporte'] = $_GET['status_suporte'];
}

// Filtro por Prioridade
if (isset($_GET['prioridade']) && $_GET['prioridade'] != '') {
    $whereClauses[] = 's.Prioridade = :prioridade';
    $params[':prioridade'] = $_GET['prioridade'];
}

// Filtro por Status de Prazo
if (isset($_GET['status_prazo']) && $_GET['status_prazo'] != '') {
    if ($_GET['status_prazo'] == 'No Prazo') {
        $whereClauses[] = "(s.Prazo_previsto >= CURDATE() AND s.Status_suporte NOT IN ('Resolvida', 'Cancelada'))";
    } elseif ($_GET['status_prazo'] == 'Atrasada') {
        $whereClauses[] = "(s.Prazo_previsto < CURDATE() AND s.Status_suporte NOT IN ('Resolvida', 'Cancelada'))";
    }
}

// Monta a cláusula WHERE final para a consulta SQL
$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Filtro por Contratada
if (isset($_GET['para_contratada']) && $_GET['para_contratada'] != '') {
    $whereClauses[] = 's.Para_contratada = :para_contratada';
    $params[':para_contratada'] = $_GET['para_contratada'];
}

// Verifica se o filtro de status foi aplicado
$filtroStatusAplicado = isset($_GET['status_suporte']) && $_GET['status_suporte'] != '';

// Se nenhum filtro de status específico foi aplicado, então adicione a cláusula para excluir 'Resolvida' e 'Cancelada'
if (!$filtroStatusAplicado) {
    $whereClauses[] = "s.Status_suporte NOT IN ('Resolvida', 'Cancelada')";
}




// Defina os itens por página e o offset antes de usar na consulta SQL
$itensPorPagina = 12; // Definir quantas OS por página
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Define a cláusula WHERE baseada na presença do EmpresaId
$whereSql = "";
if ($empresaIdUsuarioLogado !== null) {
    // Filtra por EmpresaId específico se não for NULL
    $whereSql = " WHERE s.Para_contratada = :empresaIdUsuarioLogado";
    if (!empty($whereClauses)) {
        $whereSql .= " AND " . implode(' AND ', $whereClauses);
    }
} else {
    // Se EmpresaId for NULL, remove qualquer filtro específico de Para_contratada
    if (!empty($whereClauses)) {
        $whereSql = " WHERE " . implode(' AND ', $whereClauses);
    }
}

$query = "SELECT s.*, u.Nome as NomeSolicitante, c.Nome as NomeContratada, a.Nome as NomeAlterador
          FROM suporte s
          LEFT JOIN usuarios u ON s.Solicitado_por = u.Id
          LEFT JOIN usuarios a ON s.Alterado_por = a.Id
          LEFT JOIN contratadas c ON s.Para_contratada = c.Id
          $whereSql
          ORDER BY FIELD(s.Prioridade, 'Alta', 'Média', 'Baixa'), s.Dt_criacao DESC, s.Dt_alteracao DESC
          LIMIT :offset, :itensPorPagina";

$statement = $pdo->prepare($query);
if ($empresaIdUsuarioLogado !== null) {
    $statement->bindParam(':empresaIdUsuarioLogado', $empresaIdUsuarioLogado);
}
foreach ($params as $key => $val) {
    $statement->bindValue($key, $val);
}
$statement->bindValue(':offset', $offset, PDO::PARAM_INT);
$statement->bindValue(':itensPorPagina', $itensPorPagina, PDO::PARAM_INT);
$statement->execute();
$resultadoSuportes = $statement->fetchAll(PDO::FETCH_ASSOC);

// Consulta SQL para contar registros com filtro
$totalRegistrosQuery = "SELECT COUNT(*) FROM suporte s
    LEFT JOIN usuarios u ON s.Solicitado_por = u.Id
    LEFT JOIN contratadas c ON s.Para_contratada = c.Id
    $whereSql";

$totalRegistrosStmt = $pdo->prepare($totalRegistrosQuery);
if ($empresaIdUsuarioLogado !== null) {
    $totalRegistrosStmt->bindParam(':empresaIdUsuarioLogado', $empresaIdUsuarioLogado);
}
foreach ($params as $key => $val) {
    $totalRegistrosStmt->bindValue($key, $val);
}
$totalRegistrosStmt->execute();
$totalRegistros = $totalRegistrosStmt->fetchColumn();

// Agora, calcule o total de páginas
$totalPaginas = ceil($totalRegistros / $itensPorPagina);


// Execute a consulta
try {
    $statement->execute();
    $suporte = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erro ao executar a consulta: ' . $e->getMessage());
}



// Buscar anos distintos de criação
$yearsQuery = "SELECT DISTINCT YEAR(Dt_criacao) AS Year FROM suporte ORDER BY Year DESC";
$yearsStmt = $pdo->query($yearsQuery);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);


$whereSqlCount = ($empresaIdUsuarioLogado !== null) ? " AND s.Para_contratada = :empresaIdUsuarioLogado" : "";

// Total de suportes excluindo "Resolvida" e "Cancelada"
$query = "SELECT COUNT(*) FROM suporte s WHERE Status_suporte NOT IN ('Resolvida', 'Cancelada') $whereSqlCount";
$totalSuportesStmt = $pdo->prepare($query);
if ($empresaIdUsuarioLogado !== null) {
    $totalSuportesStmt->bindParam(':empresaIdUsuarioLogado', $empresaIdUsuarioLogado);
}
$totalSuportesStmt->execute();
$totalSuportes = $totalSuportesStmt->fetchColumn();

// Total de suportes com prioridade alta excluindo "Resolvida" e "Cancelada"
$query = "SELECT COUNT(*) FROM suporte s WHERE Prioridade = 'Alta' AND Status_suporte NOT IN ('Resolvida', 'Cancelada') $whereSqlCount";
$totalSuportesAltaStmt = $pdo->prepare($query);
if ($empresaIdUsuarioLogado !== null) {
    $totalSuportesAltaStmt->bindParam(':empresaIdUsuarioLogado', $empresaIdUsuarioLogado);
}
$totalSuportesAltaStmt->execute();
$totalSuportesAlta = $totalSuportesAltaStmt->fetchColumn();

// Total de suportes com prioridade média excluindo "Resolvida" e "Cancelada"
$query = "SELECT COUNT(*) FROM suporte s WHERE Prioridade = 'Média' AND Status_suporte NOT IN ('Resolvida', 'Cancelada') $whereSqlCount";
$totalSuportesMediaStmt = $pdo->prepare($query);
if ($empresaIdUsuarioLogado !== null) {
    $totalSuportesMediaStmt->bindParam(':empresaIdUsuarioLogado', $empresaIdUsuarioLogado);
}
$totalSuportesMediaStmt->execute();
$totalSuportesMedia = $totalSuportesMediaStmt->fetchColumn();

// Total de suportes com prioridade baixa excluindo "Resolvida" e "Cancelada"
$query = "SELECT COUNT(*) FROM suporte s WHERE Prioridade = 'Baixa' AND Status_suporte NOT IN ('Resolvida', 'Cancelada') $whereSqlCount";
$totalSuportesBaixaStmt = $pdo->prepare($query);
if ($empresaIdUsuarioLogado !== null) {
    $totalSuportesBaixaStmt->bindParam(':empresaIdUsuarioLogado', $empresaIdUsuarioLogado);
}
$totalSuportesBaixaStmt->execute();
$totalSuportesBaixa = $totalSuportesBaixaStmt->fetchColumn();

// Totais para cada status, se necessário
$statusOptions = ["Não iniciada", "Em análise", "Paralisada", "Em atendimento", "Cancelada", "Resolvida"];
$statusTotais = [];
foreach ($statusOptions as $status) {
    $query = "SELECT COUNT(*) FROM suporte s WHERE Status_suporte = :status $whereSqlCount";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':status', $status);
    if ($empresaIdUsuarioLogado !== null) {
        $stmt->bindParam(':empresaIdUsuarioLogado', $empresaIdUsuarioLogado);
    }
    $stmt->execute();
    $statusTotais[$status] = $stmt->fetchColumn();
}


$statusColors = [
    "Não iniciada" => "secondary", // Cinza para indicar inatividade ou espera
    "Em análise" => "primary",     // Azul para indicar revisão ou consideração ativa
    "Paralisada" => "warning",     // Amarelo para alertar que a ação está em espera ou em pausa
    "Em atendimento" => "info",    // Azul claro para indicar atendimento em progresso
    "Cancelada" => "danger",       // Vermelho para indicar uma ação cancelada ou problema
    "Resolvida" => "success"       // Verde para indicar conclusão bem-sucedida ou resolução
];


// Exemplo de obtenção de anos do banco de dados
$yearsStmt = $pdo->query("SELECT DISTINCT YEAR(dt_criacao) AS Year FROM suporte ORDER BY Year DESC");
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);


?>

<!DOCTYPE html>
<html dir="ltr" lang="pt">

  <head>
    <?php include 'head.php'?>

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
    flex-wrap: wrap;
    align-items: center;
    gap: 20px; /* Aumente o valor do gap para dar mais espaço entre os elementos */
    padding: 10px;
    border: 1px solid #3c4147;
    border-radius: 5px;
    margin-bottom: 20px;
}

.filter-container select, 
.filter-container button {
    height: 38px;
    border: 1px solid #5b5f69;
    background-color: #272b34;
    color: #b2b9b2;
    padding: 0 15px;
    font-size: 14px;
    margin-right: 10px; /* Espaçamento à direita para todos os selects */
}

.filter-container button {
    white-space: nowrap; /* Evita a quebra de linha dentro do botão */
    text-align: center; /* Alinha o texto do botão ao centro */
}



.filter-container select {
    flex-grow: 1; /* Permite que os selects cresçam para preencher o espaço */
}

/* Media query para telas pequenas */
@media (max-width: 768px) {
    .filter-container {
        flex-direction: column; /* Os elementos são empilhados verticalmente */
    }

    .filter-container select, 
    .filter-container button {
        width: 100%; /* Os elementos ocupam toda a largura disponível */
    }
    
    .filter-container button {
        order: 1; /* O botão é movido para cima */
        margin-top: 10px; /* Espaçamento acima do botão */
    }
}





/* Estilos para os ícones e espaçamento */
.round-lg {
  width: 50px; /* Tamanho do ícone */
  height: 50px; /* Tamanho do ícone */
  border-radius: 25px; /* Círculo perfeito */
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px; /* Espaçamento entre o ícone e o texto */
}

/* Ícones */
.fa {
  color: #b2b9aa; /* Ícones brancos para melhor contraste */
  font-size: 21px; /* Ícones maiores */
}

.card {
  background-color: #23272a; /* Fundo escuro, mas ligeiramente mais claro que o fundo da página para se destacar */
  color: #ffffff; /* Texto branco para contraste */
  border: none; /* Sem bordas */
  border-radius: 15px; /* Bordas mais arredondadas */
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5); /* Sombra mais intensa para um "pop" 3D */
  transition: transform 0.3s ease, box-shadow 0.3s ease; /* Transição suave para efeitos */
  overflow: hidden; /* Garante que todo o conteúdo fique contido dentro das bordas arredondadas */
  cursor: pointer; /* Cursor de mão */
}

.card:hover {
  transform: translateY(-5px); /* Move o card para cima ao passar o mouse */
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.8); /* Sombra mais intensa no hover */
}

.card-body {
  display: flex;
  align-items: center;
  justify-content: space-between; /* Espaçamento igual entre itens */
  padding: 25px; /* Mais espaço interno */
}

.card-title {
  font-size: 20px; /* Título maior */
  font-weight: 600; /* Peso da fonte aumentado */
}

.card-text {
  font-size: 18px; /* Texto maior */
  margin-top: 5px; /* Ajuste no espaçamento */
}

/* Cores personalizadas para ícones com base no tipo */
.bg-info { background-color: #17a2b8!important; }
.bg-high { background-color: #d9534f!important; } /* cor mais vibrante para alta prioridade */
.bg-medium { background-color: #f0ad4e!important; } /* cor mais vibrante para média prioridade */
.bg-low { background-color: #5bc0de!important; } /* cor mais vibrante para baixa prioridade */


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

.linha-resolvida {
    text-decoration: line-through;
    opacity: 0.7; /* Opcional para tornar a linha mais clara */
}

.linha-resolvida .action-buttons button,
.linha-resolvida .action-buttons .dropdown-menu a {
    opacity: 1 !important; /* Força a opacidade a 1 (100% visível) */
}


/* Estilo personalizado para os checkboxes de prioridade */
.priority-check {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    position: relative;
    cursor: pointer;
    width: 20px;
    height: 20px;
    background-color: #323743;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 4px;
    vertical-align: middle;
    transition: background-color 0.2s, border-color 0.2s;
}

/* Quando o checkbox de prioridade estiver marcado */
.priority-check:checked {
    background-color: #198754; /* Um azul claro para distinção */
    border-color: #198754;
}

/* Estilo para o ícone de verificação quando o checkbox de prioridade está marcado */
.priority-check:checked::after {
    content: '\2713'; /* Código Unicode para o ícone de verificação */
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white; /* Ícone de verificação branco */
    font-size: 14px; /* Tamanho do ícone de verificação */
}

/* Hover efeito para o checkbox de prioridade */
.priority-check:hover:not(:checked) {
    border-color: #198754; /* Muda a cor da borda ao passar o mouse para um azul claro */
}

/* Foco no checkbox de prioridade */
.priority-check:focus {
    outline: none; /* Remove o contorno padrão */
    box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25); /* Adiciona um sombreado azul claro para indicar foco */
}

/* Estilo para desabilitar o checkbox de prioridade */
.priority-check:disabled {
    background-color: #e9ecef; /* Fundo cinza claro para o checkbox desabilitado */
    border-color: #ced4da; /* Borda cinza */
    cursor: not-allowed; /* Cursor de não permitido */
}

/* Estilo para o label associado ao checkbox de prioridade para melhor acessibilidade */
.priority-check + label {
    margin-left: 8px; /* Espaço entre o checkbox de prioridade e o label */
    cursor: pointer; /* Muda o cursor para indicar que o label é clicável */
}

/* Garanta que o label acione o estado de foco do checkbox de prioridade quando clicado */
.priority-check + label:hover {
    color: #0dcaf0; /* Muda a cor do texto ao passar o mouse para um azul claro */
}

/* Estilos para o botão Filtrar */
.btn-filtrar {
  background-color: #4a4e69; /* Cor de fundo do botão */
  color: #ffffff; /* Cor do texto */
  border: 1px solid #6c7293; /* Cor da borda */
  border-radius: 0px; /* Bordas arredondadas */
  padding: 10px 20px; /* Espaçamento interno */
  font-size: 16px; /* Tamanho do texto */
  transition: all 0.3s; /* Transição suave para hover */
  display: flex; /* Usa flexbox para alinhar ícone e texto */
  align-items: center; /* Centraliza itens verticalmente */
  justify-content: center; /* Centraliza itens horizontalmente */
  box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.2); /* Sombra suave */
}

.btn-filtrar i {
  margin-right: 5px; /* Espaço entre ícone e texto */
}

/* Efeito ao passar o mouse */
.btn-filtrar:hover {
  color: #b2b9aa;
  background-color: #5b5f69; /* Cor de fundo mais clara ao hover */
  border-color: #5b5f69; /* Cor da borda mais clara ao hover */
  box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.3); /* Sombra mais pronunciada ao hover */
  transform: translateY(-2px); /* Leve elevação do botão ao hover */
}

/* Efeito ao clicar no botão */
.btn-filtrar:active {
  transform: translateY(1px); /* Pressionando o botão para baixo */
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.2); /* Sombra mais plana quando pressionado */
}

.linha-atrasada {
    position: relative;
    border-left: 7px solid #cb8a00; /* Barra lateral para destacar */
    background-image: linear-gradient(to right, rgb(94 176 112 / 0%) 0%, rgb(81 91 98 / 11%) 10%, rgb(100 212 114 / 6%) 50%, rgb(80 148 120 / 0%) 90%, rgb(80 90 97 / 0%) 100%);
    animation: blink-animation 2s infinite; /* Animação da barra lateral */
}

@keyframes blink-animation {
    0% { border-left-color: #198754; }
    50% { border-left-color: #4f5660; }
    100% { border-left-color: #198754; }
}


.break-all {
    word-break: break-all;
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
            <h3 class="text-themecolor mb-0"><i class="mdi mdi-headset"></i> Suporte</h3>            
          </div>          
        </div>


       
        <div class="container-fluid">  

      <div class="row">
  <!-- Card Total de Suportes -->
  <div class="col-lg-3 col-md-6">
    <div class="card" onclick="filterSuportes('')">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-info">
            <i class="fa fa-list-alt"></i>
          </div>
          <div>
            <h3 class="card-title">Tarefas Abertas</h3>
            <p id="totalSuporte" class="card-text text-muted"><?php echo $totalSuportes; ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card Prioridade Alta -->
  <div class="col-lg-3 col-md-6">
    <div class="card" onclick="filterSuportes('Alta')">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-dark">
            <i class="fa fa-arrow-up"></i>
          </div>
          <div>
            <h3 class="card-title">Prioridade Alta</h3>
            <p id="prioridadeAlta" class="card-text text-muted"><?php echo $totalSuportesAlta; ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card Prioridade Média -->
  <div class="col-lg-3 col-md-6">
    <div class="card" onclick="filterSuportes('Média')">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-warning">
            <i class="fa fa-exclamation-triangle"></i>
          </div>
          <div>
            <h3 class="card-title">Prioridade Média</h3>
            <p id="prioridadeMedia" class="card-text text-muted"><?php echo $totalSuportesMedia; ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card Prioridade Baixa -->
  <div class="col-lg-3 col-md-6">
    <div class="card" onclick="filterSuportes('Baixa')">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-primary">
            <i class="fa fa-arrow-down"></i>
          </div>
          <div>
            <h3 class="card-title">Prioridade Baixa</h3>
            <p id="prioridadeBaixa" class="card-text text-muted"><?php echo $totalSuportesBaixa; ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- Botão para abrir o modal de adicionar nova OS -->
        <button class="btn waves-effect waves-light btn-info" onclick="openModal('addSupportModal')"><i class="ti-plus text"></i> Cadastrar Tarefa</button>

        <br><br>

      <!-- Filtro por Ano, Solicitado Por, Status do Suporte, Prioridade e Contratada -->
<div class="filter-container d-flex flex-wrap align-items-center justify-content-between">
    <form id="filterSuporteForm" action="suporte" method="get" class="d-flex flex-wrap align-items-center w-100">

       <!-- Filtro por Ano -->
<!-- Filtro por Ano -->
<select name="year">
    <?php
    // Define o ano atual como padrão se nenhum ano for especificado
    $selectedYear = $_GET['year'] ?? date('Y'); // Utiliza o operador null coalesce para simplificar a lógica
    foreach ($years as $year): // Certifique-se de que $years contém os anos retornados pela consulta SQL acima
        $selected = ($selectedYear == $year) ? 'selected' : '';
    ?>
        <option value="<?php echo $year; ?>" <?php echo $selected; ?>>
            <?php echo $year; ?>
        </option>
    <?php endforeach; ?>
</select>



        <!-- Filtro por Solicitado Por -->
        <select name="solicitado_por">
            <option value="">Todos os Solicitantes</option>
            <?php foreach ($usuarios as $usuario): ?>
                <option value="<?php echo $usuario['Id']; ?>" <?php if (isset($_GET['solicitado_por']) && $_GET['solicitado_por'] == $usuario['Id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($usuario['Nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Filtro por Status do Suporte -->
        <select name="status_suporte">
            <option value="">Todos os Status</option>
            <?php foreach ($statusOptions as $status): ?>
                <option value="<?php echo $status; ?>" <?php if (isset($_GET['status_suporte']) && $_GET['status_suporte'] == $status) echo 'selected'; ?>>
                    <?php echo $status; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="para_contratada">
    <option value="">Todos Responsáveis</option>
    <?php
    // Verifica se o usuário logado está associado a uma empresa específica ou não
    if (isset($_SESSION['EmpresaId']) && $_SESSION['EmpresaId'] !== null) {
        // Usuário está vinculado a uma empresa específica, mostra apenas essa empresa
        $empresaQuery = $pdo->prepare("SELECT Id, Nome FROM contratadas WHERE Id = :empresaId");
        $empresaQuery->bindParam(':empresaId', $_SESSION['EmpresaId']);
        $empresaQuery->execute();
        $empresa = $empresaQuery->fetch(PDO::FETCH_ASSOC);
        if ($empresa) { // Verifica se a consulta retornou algum resultado
            echo '<option value="' . $empresa['Id'] . '" selected>' . htmlspecialchars($empresa['Nome']) . '</option>';
        }
    } else {
        // Usuário pode ver todas as contratadas
        $contratadas = $pdo->query("SELECT Id, Nome FROM contratadas")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($contratadas as $contratada) {
            $selected = isset($_GET['para_contratada']) && $_GET['para_contratada'] == $contratada['Id'] ? 'selected' : '';
            echo '<option value="' . $contratada['Id'] . '" ' . $selected . '>' . htmlspecialchars($contratada['Nome']) . '</option>';
        }
    }
    ?>
</select>


        <!-- Filtro por Status de Prazo -->
<select name="status_prazo">
    <option value="">Todos os Status de Prazo</option>
    <option value="No Prazo" <?php if (isset($_GET['status_prazo']) && $_GET['status_prazo'] == 'No Prazo') echo 'selected'; ?>>No Prazo</option>
    <option value="Atrasada" <?php if (isset($_GET['status_prazo']) && $_GET['status_prazo'] == 'Atrasada') echo 'selected'; ?>>Atrasada</option>
</select>



        <!-- Botão de filtro -->
        <button type="submit" class="btn btn-filtrar">
  <i class="fa fa-search"></i> Filtrar
</button>

    </form>
</div>



<br>

          <div class="row">

            <div class="table-responsive">

            
            
              <table class="table table-striped table mb-0" data-tablesaw-mode="columntoggle" id="tablesaw-6204">
    <thead class="thead-light" align="center">
        <tr style="background-color: #5b5f69">
            <th></th>
                <th scope="col">Código</th>
                <th scope="col">Tarefa</th>
                <th scope="col">Criação</th>
                <th scope="col">Prioridade</th>
                <th scope="col">Status</th>
                <th scope="col">Solicitado por</th>
                <th scope="col">Responsável</th>
                <th scope="col">Alteração</th>
                <th scope="col">Entrega</th>
                <th scope="col">Previsão</th>
                <th></th>
        </tr>
    </thead>
    <tbody style="font-size:12.5px; font-weight: 400;">
    <?php if ($suporte): ?>
       <?php foreach ($suporte as $item): ?>
    <?php
    // Calcula a diferença em dias entre a data atual e a data de entrega
    $hoje = new DateTime();
    $dataEntrega = new DateTime($item['Prazo_previsto']);
    $diferencaDias = $hoje->diff($dataEntrega)->days;
    $atrasado = ($hoje > $dataEntrega) && $diferencaDias > 1; // Mais de 1 dia de atraso

    // Define a classe CSS baseado no atraso
    $classeAtrasada = $atrasado ? 'linha-atrasada' : '';
    ?>
    <tr class="<?php echo $classeAtrasada; ?>">
                    <td style="text-align: center; vertical-align: middle;">
                        <input type="checkbox" class="priority-check" data-support-id="<?php echo $item['Id']; ?>">
                    </td>
                    
                    <td style="text-align: center; vertical-align: middle;"><span class="badge bg-info"><?php echo htmlspecialchars($item['Id']); ?></span></td>

                    <td style="text-align: left; font-weight: 500; text-transform: uppercase; vertical-align: middle" data-label="Tarefa"><?php echo htmlspecialchars($item['Tarefa']); ?></td>

                    <td style="text-align: center; font-weight: 500; vertical-align: middle" data-label="Data de Criação"><?php echo date('d/m/Y', strtotime($item['Dt_criacao'])); ?></td>

                    <td style="text-align: center; vertical-align: middle" data-label="Prioridade">
                        <?php echo '<span class="badge bg-' . ($item['Prioridade'] == 'Alta' ? 'danger' : ($item['Prioridade'] == 'Média' ? 'warning' : 'info')) . '">' . $item['Prioridade'] . '</span>'; ?>
                    </td>
                    <td style="text-align: center; vertical-align: middle" data-label="Status">
                        <?php
                            $status = htmlspecialchars($item['Status_suporte']);
                            $color = $statusColors[$status] ?? "secondary"; // Usa "secondary" como cor padrão caso o status não esteja definido
                            echo "<span class='badge bg-$color'>$status</span>";
                        ?>
                    </td>


                    <td style="text-align: center; font-weight: 500; vertical-align: middle" data-label="Solicitado por"><?php echo htmlspecialchars($item['NomeSolicitante']); ?></td>

                    <td style="text-align: center; font-weight: 500; vertical-align: middle" data-label="Responsável"><?php echo htmlspecialchars($item['NomeContratada']); ?></td>

                    <td style="text-align: center; font-weight: 500; vertical-align: middle" data-label="Dt Alteração">
    <?php 
    if (!empty($item['Dt_alteracao'])) {
        echo date('d/m/Y', strtotime($item['Dt_alteracao']));
    } else {
        echo '';
    }
    ?>
    <br>
    <?php 
    if (!empty($item['NomeAlterador'])) {
        echo '<small style="font-size:7px"><i class="fas fa-user me-1"></i>' . htmlspecialchars($item['NomeAlterador']) . '</small>';
    } else {
        echo '<small style="font-size:7px"></small>';
    }
    ?>
</td>


                    <td style="text-align: center; font-weight: 500; vertical-align: middle" data-label="Data de Entrega">
    <?php echo !empty($item['Prazo_previsto']) ? date('d/m/Y', strtotime($item['Prazo_previsto'])) : ''; ?>
</td>


                  <td style="text-align: center; vertical-align: middle" data-label="Prazo">
    <?php 
        if (!empty($item['Prazo_previsto'])) {
            $dtCriacao = new DateTime($item['Dt_criacao']);
            $prazoPrevisto = new DateTime($item['Prazo_previsto']);
            $dataAtual = new DateTime(); // Data atual
            $dataAtual->setTime(0, 0, 0);
            $prazoPrevisto->setTime(0, 0, 0);

            $statusSuporte = $item['Status_suporte'];
            $dataResolvido = isset($item['Data_resolvido']) ? new DateTime($item['Data_resolvido']) : null;
            $resolvidoNoPrazo = $dataResolvido && $dataResolvido <= $prazoPrevisto;
            $resolvidoComAtraso = $dataResolvido && $dataResolvido > $prazoPrevisto;

            if ($resolvidoNoPrazo && in_array($statusSuporte, ['Resolvida', 'Cancelada'])) {
                echo "<span class='badge bg-info'>Resolvido no Prazo</span>";
            } elseif ($resolvidoComAtraso && in_array($statusSuporte, ['Resolvida', 'Cancelada'])) {
                echo "<span class='badge bg-danger'>Resolvido com Atraso</span>";
            } else {
                $intervalo = $dataAtual->diff($prazoPrevisto);
                $diasRestantes = $intervalo->days;
                if ($dataAtual <= $prazoPrevisto) {
                    $status = $diasRestantes == 0 ? "Vence hoje" : ($diasRestantes == 1 ? "Vence em 1 dia" : "Vence em <b>$diasRestantes dias</b>");
                    echo "<span class='badge bg-info'>$status</span>";
                } else {
                    $statusAtrasado = $diasRestantes == 1 ? "Atrasada há 1 dia" : "Atrasada há <b>$diasRestantes dias</b>";
                    echo "<span class='badge bg-danger'>$statusAtrasado</span>";
                }
            }
        } else {
            echo "<span class='badge bg-warning'>Sem previsão</span>";
        }
    ?>
</td>



                    <td style="text-align: center; vertical-align: middle" class="action-buttons">
    <div class="d-flex justify-content-center align-items-center">
        <!-- Botão Ver com ícone de olho -->
        <button onclick="viewSupportModal(<?php echo $item['Id']; ?>)" title="Ver" class="btn btn-sm btn-light-info text-white mx-1">
            <i data-feather="eye" class="feather-sm fill-white"></i>
        </button>

        <!-- Botão Editar com ícone de lápis -->
        <button onclick="loadSupportDetails(<?php echo $item['Id']; ?>)" title="Editar" class="btn btn-sm btn-light-info text-white mx-1">
            <i data-feather="edit" class="feather-sm fill-white"></i>
        </button>

         <button onclick="window.open('gerar_pdf.php?id=<?php echo $item['Id']; ?>', '_blank')" title="Gerar PDF" class="btn btn-sm btn-light-info text-white mx-1">
            <i data-feather="file-text" class="feather-sm fill-white"></i>
        </button>

        <!-- Botão Excluir com ícone de lixeira -->
        <button onclick="deleteSupport(<?php echo $item['Id']; ?>)" title="Excluir" class="btn btn-sm btn-light-danger text-white mx-1">
            <i data-feather="trash-2" class="feather-sm fill-white"></i>
        </button>
    </div>
</td>




                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="12" style="text-align:center;">Nenhum suporte encontrado</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

        </div>

        


            
          </div> <br>

         <div>
    <nav aria-label="Navegação de página exemplo">
        <ul class="pagination">
            <?php
            // Função para gerar a URL base para paginação mantendo os filtros
            function gerarUrlBasePaginacaoSuporte() {
                $queryParams = $_GET; // Obtém os parâmetros atuais
                unset($queryParams['pagina']); // Remove o parâmetro de paginação para evitar duplicidade
                $baseURL = 'suporte.php';
                return $baseURL . '?' . http_build_query($queryParams); // Gera a URL base mantendo os parâmetros de filtro
            }

            $urlBaseSuporte = gerarUrlBasePaginacaoSuporte();

            for ($i = 1; $i <= $totalPaginas; $i++): 
                $classActive = ($paginaAtual == $i) ? 'active' : '';
                // Acrescenta o número da página ao URL base mantendo os filtros
                $linkPaginaSuporte = $urlBaseSuporte . '&pagina=' . $i;
                ?>
                <li class="page-item <?php echo $classActive; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($linkPaginaSuporte); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>




       <!-- Modal de Cadastro de Suporte -->
<div id="addSupportModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalCadastroLabel"><i class="mdi mdi-headset"></i>&nbsp;Cadastrar Tarefa</h2>
                <span class="close" onclick="closeModal('addSupportModal')">&times;</span>
            </div>
            <form id="addSupportForm" action="process_cadastro_suporte.php" method="post" enctype="multipart/form-data">


                <div class="modal-body">
                    <!-- Campos do formulário -->
                    <div class="mb-3">
                        <label for="addTarefa" class="form-label">Tarefa</label>
                        <input type="text" class="form-control bg-secondary text-white" id="addTarefa" name="tarefa" required></input>
                    </div>
                    <!-- Data de Criação -->
                    <div class="mb-3">
                        <label for="dt_criacao">Data de Criação</label>
                        <input type="date" id="dt_criacao" name="dt_criacao" class="form-control" required>
                    </div>
                    <!-- Prioridade -->
                    <div class="mb-3">
                        <label for="prioridade">Prioridade</label>
                        <select id="prioridade" name="prioridade" class="form-select" required>
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>
                    <!-- Status -->                    
                    <div class="mb-3">
                        <label for="status_suporte" class="form-label">Status</label>
                        <select id="status_suporte" name="status_suporte" class="form-select" required>
                            <option value="">Selecione o Status</option>
                            <?php foreach ($statusOptions as $status): ?>
                                <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Solicitado Por -->
                    <div class="mb-3">
                        <label for="solicitado_por">Solicitado Por</label>
                        <select id="solicitado_por" name="solicitado_por" class="form-select" required>
                            <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?php echo $usuario['Id']; ?>"><?php echo htmlspecialchars($usuario['Nome']); ?></option>
                    <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Campo Para Contratada -->
                    <div class="mb-3">
                        <label for="para_contratada" class="form-label">Responsável</label>
                        <select id="para_contratada" name="para_contratada" class="form-select" required>
                            <option value="">Selecione o Responsável</option>
                            <?php
                            // Verifica se o usuário logado está associado a uma empresa específica ou tem acesso a todas
                            if (isset($_SESSION['EmpresaId']) && $_SESSION['EmpresaId'] !== null) {
                                // Usuário está vinculado a uma empresa específica, busca apenas essa empresa
                                $empresaQuery = $pdo->prepare("SELECT Id, Nome FROM contratadas WHERE Id = :empresaId");
                                $empresaQuery->bindParam(':empresaId', $_SESSION['EmpresaId']);
                                $empresaQuery->execute();
                                $empresa = $empresaQuery->fetch(PDO::FETCH_ASSOC);
                                if ($empresa) { // Verifica se a consulta retornou algum resultado
                                    echo '<option value="' . $empresa['Id'] . '" selected>' . htmlspecialchars($empresa['Nome']) . '</option>';
                                }
                            } else {
                                // Usuário pode ver todas as contratadas
                                $contratadas = $pdo->query("SELECT Id, Nome FROM contratadas")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($contratadas as $contratada) {
                                    echo '<option value="' . $contratada['Id'] . '">' . htmlspecialchars($contratada['Nome']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>


                    <!-- Prazo Previsto -->
                    <div class="mb-3">
                        <label for="prazo_previsto">Prazo Previsto</label>
                        <input type="date" id="prazo_previsto" name="prazo_previsto" class="form-control">
                    </div>
                    <!-- Observação -->
                    <div class="mb-3">
                        <label for="observacao">Descrição da Tarefa</label>
                        <textarea id="observacao" name="observacao" class="form-control" style="height: 300px"></textarea>
                    </div>

                    <!-- Anexar Arquivos -->
                    <div class="mb-3">
                        <label for="anexo">Anexar Arquivo</label>
                        <input type="file" id="anexo" name="anexo" class="form-control">
                        <small class="text-muted">Você pode anexar um arquivo (imagem, documento Word, PDF, TXT, etc.).</small>
                    </div>





                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary font-weight-medium rounded-pill px-4">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-send"></i>&nbsp;Cadastrar
                        </div>
            </form>
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

        <!-- Anexo -->
                <div class="mb-3" id="anexoContainer">
                    <h3 class="form-label">Anexo</h3>
                    <div id="viewAnexo"></div>
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










<!-- Modal de Edição de Suporte -->
<div id="editSupportModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalCadastroOsLabel"><i class="mdi mdi-pencil"></i>&nbsp;Editar Tarefa </h2>
                <span class="close" onclick="closeModal('editSupportModal')">&times;</span>
            </div>
            <form id="editSupportForm" action="process_edit_suporte.php" method="post" enctype="multipart/form-data">
                <!-- Campo oculto para Data de Alteração -->
                <input type="hidden" id="editDtAlteracao" name="dt_alteracao">

                <div class="modal-body">
                    <!-- Campo de ID (oculto) -->
                    <input type="hidden" id="editId" name="id">

                    <!-- Campo para Tarefa -->
                    <div class="mb-3">
                        <label for="editTarefa" class="form-label">Tarefa</label>
                        <input type="text" class="form-control bg-secondary text-white"  id="editTarefa" name="tarefa" required></input>
                    </div>

                    <!-- Data de Criação -->
                    <div class="mb-3">
                        <label for="editDtCriacao" class="form-label">Data de Criação</label>
                        <input type="date" class="form-control" id="editDtCriacao" name="dt_criacao" required>
                    </div>

                    <!-- Prioridade -->
                    <div class="mb-3">
                        <label for="editPrioridade" class="form-label">Prioridade</label>
                        <select class="form-select" id="editPrioridade" name="prioridade" required>
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                        </select>
                    </div>

                    <!-- Status do Suporte -->
                    <div class="mb-3">
                        <label for="editStatusSuporte" class="form-label">Status do Suporte</label>
                        <select class="form-select" id="editStatusSuporte" name="status_suporte" required>
                            <option value="">Selecione o Status</option>
                            <?php foreach ($statusOptions as $status): ?>
                                <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Solicitado Por -->
                    <div class="mb-3">
                        <label for="editSolicitadoPor" class="form-label">Solicitado Por</label>
                        <select class="form-select" id="editSolicitadoPor" name="solicitado_por" required>
                            <option value="">Selecione o Solicitante</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['Id']; ?>"><?php echo htmlspecialchars($usuario['Nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Para Contratada -->
                   <div class="mb-3">
                        <label for="editParaContratada" class="form-label">Responsável</label>
                        <select class="form-select" id="editParaContratada" name="para_contratada" required>
                            <option value="">Selecione o Responsável</option>
                            <?php
                            // Verifica se o usuário logado está associado a uma empresa específica ou tem acesso a todas
                            if (isset($_SESSION['EmpresaId']) && $_SESSION['EmpresaId'] !== null) {
                                // Usuário está vinculado a uma empresa específica, busca apenas essa empresa
                                $empresaQuery = $pdo->prepare("SELECT Id, Nome FROM contratadas WHERE Id = :empresaId");
                                $empresaQuery->bindParam(':empresaId', $_SESSION['EmpresaId']);
                                $empresaQuery->execute();
                                $empresa = $empresaQuery->fetch(PDO::FETCH_ASSOC);
                                if ($empresa) { // Verifica se a consulta retornou algum resultado
                                    echo '<option value="' . $empresa['Id'] . '" selected>' . htmlspecialchars($empresa['Nome']) . '</option>';
                                }
                            } else {
                                // Usuário pode ver todas as contratadas
                                $contratadas = $pdo->query("SELECT Id, Nome FROM contratadas")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($contratadas as $contratada) {
                                    // Você pode precisar preservar a seleção atual em caso de edição
                                    $selected = (isset($currentParaContratada) && $currentParaContratada == $contratada['Id']) ? 'selected' : '';
                                    echo '<option value="' . $contratada['Id'] . '" ' . $selected . '>' . htmlspecialchars($contratada['Nome']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>



                    <!-- Prazo Previsto -->
                    <div class="mb-3">
                        <label for="editPrazoPrevisto" class="form-label">Prazo Previsto</label>
                        <input type="date" class="form-control" id="editPrazoPrevisto" name="prazo_previsto">
                    </div>

                    <!-- Observação -->
                    <div class="mb-3">
                        <label for="editObservacao" class="form-label">Descrição da Tarefa</label>
                        <textarea class="form-control" id="editObservacao" name="observacao" style="height: 300px"></textarea>
                    </div>

                    <div class="mb-3" id="editAnexoContainer">
    <!-- O link do anexo atual será inserido aqui -->
</div>

<!-- Campo para substituição do anexo -->
<div class="mb-3">
    <label for="editAnexo" class="form-label">Substituir Anexo</label>
    <input type="file" class="form-control" id="editAnexo" name="anexo">
    <small class="text-muted">Escolha um novo arquivo para substituir o anexo atual, se necessário.</small>
</div>



                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
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
function viewSupportModal(supportId) {
    fetch('api_suporte.php?supportId=' + supportId)
    .then(response => response.json())
    .then(data => {
        // Atribui os dados obtidos aos elementos do modal
        document.getElementById('viewTarefa').textContent = data.Tarefa;
        document.getElementById('viewDtCriacao').textContent = formatDate(data.Dt_criacao);
        document.getElementById('viewPrioridade').textContent = data.Prioridade;
        document.getElementById('viewStatusSuporte').textContent = data.Status_suporte;
        document.getElementById('viewSolicitadoPor').textContent = data.NomeSolicitante;
        document.getElementById('viewContratada').textContent = data.NomeContratada;
        document.getElementById('viewPrazoPrevisto').textContent = formatDate(data.Prazo_previsto);
        document.getElementById('viewObservacao').style.wordBreak = 'break-all';
        document.getElementById('viewObservacao').innerHTML = data.Observacao.replace(/\n/g, '<br>');

        var anexoContainer = document.getElementById('viewAnexo');
anexoContainer.innerHTML = ''; // Limpa o container do anexo

if (data.Anexos && data.Anexos !== "") {
    var linkAnexo = '' + data.Anexos; // caminho relativo à raiz do projeto

    var extensao = data.Anexos.split('.').pop().toLowerCase();
    if (extensao === 'pdf') {
        anexoContainer.innerHTML = `<embed src="${linkAnexo}" width="100%" height="500px" type="application/pdf">`;
    } else if (['jpg', 'jpeg', 'gif', 'png'].includes(extensao)) {
        anexoContainer.innerHTML = `<img src="${linkAnexo}" alt="Anexo" style="max-width: 100%; height: auto;">`;
    } else {
        anexoContainer.innerHTML = `<a href="${linkAnexo}" target="_blank" download>Baixar anexo</a>`;
    }
} else {
    anexoContainer.textContent = 'Nenhum anexo disponível.';
}



        // Abre o modal
        openModal('viewSupportModal');
    })
    .catch(error => {
        console.error('Erro ao buscar dados do suporte:', error);
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    var date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR');
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
</script>



<script>
  
function loadSupportDetails(supportId) {
    fetch('api_suporte.php?supportId=' + supportId)
    .then(response => response.json())
    .then(data => {
        if (data && !data.error) {
            // Preenche os campos do modal com os dados do suporte
            document.getElementById('editId').value = data.Id;
            document.getElementById('editTarefa').value = data.Tarefa;
            document.getElementById('editDtCriacao').value = data.Dt_criacao;
            document.getElementById('editPrioridade').value = data.Prioridade;
            document.getElementById('editStatusSuporte').value = data.Status_suporte;
            document.getElementById('editSolicitadoPor').value = data.Solicitado_por;
            document.getElementById('editParaContratada').value = data.Para_contratada;
            document.getElementById('editPrazoPrevisto').value = data.Prazo_previsto;
            document.getElementById('editObservacao').value = data.Observacao; // Use .value para campos de texto

            // Exibe o anexo atual, se houver
            const anexoContainer = document.getElementById('editAnexoContainer');
            anexoContainer.innerHTML = ''; // Limpa conteúdo anterior
            if (data.Anexos) {
                const anexoLink = document.createElement('a');
                anexoLink.href = data.Anexos;
                anexoLink.textContent = 'Ver anexo atual';
                anexoLink.target = '_blank';
                anexoContainer.appendChild(anexoLink);
            } else {
                anexoContainer.textContent = 'Nenhum anexo atual.';
            }

            // Abre o modal de edição
            openModal('editSupportModal');
        } else {
            alert('Erro ao carregar os detalhes do suporte.');
        }
    })
    .catch(error => {
        console.error('Erro ao buscar dados do suporte:', error);
    });
}


</script>


    


<script>
function deleteSupport(supportId) {
    if (!supportId) {
        console.error('ID do suporte não fornecido.');
        return;
    }

    if (confirm('Tem certeza que deseja excluir este suporte e seu anexo associado?')) {
        // Envie uma solicitação ao backend para excluir o suporte e o anexo
        fetch('delete_suporte.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + supportId
        })
        .then(response => response.text())
        .then(data => {
            alert(data); // Exibe a resposta do servidor
            location.reload(); // Recarrega a página para atualizar a lista de suportes
        })
        .catch(error => {
            console.error('Erro ao excluir o suporte:', error);
        });
    }
}

</script>


    <script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Inicialize uma variável para controlar se uma submissão está em andamento
let isSubmitting = false;

function submitAddForm() {
    if (isSubmitting) {
        return; // Se já estiver enviando, não faz nada
    }
    isSubmitting = true; // Define que a submissão começou

    var formElement = document.getElementById('addSuporteForm');
    var formData = new FormData(formElement);
    document.querySelector('#addModal .submit-btn').disabled = true;

    fetch('process_cadastro_suporte.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        location.reload(); // Para atualizar a lista de itens do suporte
    })
    .catch(error => {
        console.error('Erro ao cadastrar o item do suporte:', error);
    })
    .finally(() => {
        document.querySelector('#addModal .submit-btn').disabled = false;
        isSubmitting = false; // Reseta a variável após a submissão
        closeModal('addModal');
    });
}


function submitEditForm() {
    if (isSubmitting) {
        return; // Se já estiver enviando, não faz nada
    }
    isSubmitting = true; // Define que a submissão começou

    var formElement = document.getElementById('editBacklogForm');
    var formData = new FormData(formElement);
    document.querySelector('#editModal .submit-btn').disabled = true;

    fetch('process_edit_backlog.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        location.reload(); // Para atualizar a lista de itens do backlog
    })
    .catch(error => {
        console.error('Erro ao atualizar o item do backlog:', error);
    })
    .finally(() => {
        document.querySelector('#editModal .submit-btn').disabled = false;
        isSubmitting = false; // Reseta a variável após a submissão
        closeModal('editModal');
    });
}



// Função para carregar os detalhes e a visualização do anexo do backlog para edição
function loadBacklogDetails(id) {
    const backlogItem = backlogs.find(item => item.Id == id);
    if (backlogItem) {
        // Preenche os campos do formulário com os dados do item do backlog
        document.getElementById('editId').value = backlogItem.Id;
        document.getElementById('editProjeto').value = backlogItem.Projeto;
        document.getElementById('editDtCriacao').value = backlogItem.Dt_criacao;
        document.getElementById('editPrioridade').value = backlogItem.Prioridade;
        document.getElementById('editStatusIdeia').value = backlogItem.Status_ideia;
        document.getElementById('editResponsavel').value = backlogItem.Responsavel;
        var encaminhadoOsSelect = document.getElementById('editEncaminhadoOs');
        encaminhadoOsSelect.value = backlogItem.Encaminhado_os.toString();
        document.getElementById('editDescricao').value = backlogItem.Descricao;
        document.getElementById('existingAttachment').value = backlogItem.Anexo; // Certifique-se de que este campo exista no seu banco de dados e no objeto backlogItem

        // Mostra a visualização do anexo existente
        displayExistingAttachment(backlogItem.Anexo);

        // Abre o modal de edição
        openModal('editModal');
    } else {
        alert('Item de backlog não encontrado.');
    }
}

// Função para exibir a miniatura do anexo existente
function displayExistingAttachment(filePath) {
    const attachmentPreview = document.getElementById('attachmentPreview');
    attachmentPreview.innerHTML = ''; // Limpa o preview anterior

    if (filePath) {
        const fileExtension = filePath.split('.').pop().toLowerCase();

        if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
            // Se for uma imagem, mostra a miniatura
            const img = document.createElement('img');
            img.src = filePath;
            img.style.maxWidth = '200px';
            img.style.maxHeight = '200px';
            attachmentPreview.appendChild(img);
        } else {
            // Se for um PDF ou Word, mostra o ícone correspondente
            const iconImg = document.createElement('img');
            iconImg.style.width = '50px';
            iconImg.style.height = '50px';

            // Define o ícone com base na extensão do arquivo
            if (fileExtension === 'pdf') {
                iconImg.src = 'img/pdf.png'; // Atualize para o caminho correto do ícone de PDF
            } else if (['doc', 'docx'].includes(fileExtension)) {
                iconImg.src = 'img/doc.png'; // Atualize para o caminho correto do ícone de Word
            }

            // Cria um link que envolve o ícone
            const link = document.createElement('a');
            link.href = filePath;
            link.target = '_blank';
            link.appendChild(iconImg);

            // Adiciona o link com o ícone ao container de preview
            attachmentPreview.appendChild(link);
        }
    }
}


function deleteBacklog(id) {
    if (confirm('Tem certeza que deseja excluir este item?')) {
        // Substitua 'delete_backlog.php' com o caminho correto para o seu script PHP de exclusão.
        fetch('delete_backlog.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            window.location.reload(); // Recarrega a página para atualizar a lista de itens.
        })
        .catch(error => {
            console.error('Erro ao excluir o item:', error);
        });
    }
}


// Adicione event listeners para os formulários
document.getElementById('addBacklogForm').addEventListener('submit', submitAddForm);
document.getElementById('editBacklogForm').addEventListener('submit', submitEditForm);

</script>

<script> 
function togglePriority(checkbox, supportId) {
    var row = checkbox.closest('tr');
    if (checkbox.checked) {
        row.classList.add('highlighted');
        localStorage.setItem('highlightedRow' + supportId, 'true');
    } else {
        row.classList.remove('highlighted');
        localStorage.removeItem('highlightedRow' + supportId);
    }
    sortHighlightedRows(); // Chama a ordenação após alterar o destaque
}

function sortHighlightedRows() {
    var table = document.getElementById('tablesaw-6204');
    var tbody = table.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var priorityOrder = { 'Alta': 1, 'Média': 2, 'Baixa': 3 };

    rows.sort(function(a, b) {
        var highlightedA = a.classList.contains('highlighted');
        var highlightedB = b.classList.contains('highlighted');
        if (highlightedA && highlightedB) {
            var priorityA = priorityOrder[a.cells[2].textContent.trim()] || 4;
            var priorityB = priorityOrder[b.cells[2].textContent.trim()] || 4;
            return priorityA - priorityB;
        }
        return highlightedA ? -1 : highlightedB ? 1 : 0;
    });

    rows.forEach(function(row) {
        tbody.appendChild(row); // Re-anexa a linha à tabela para aplicar a ordenação
    });
}

function applyHighlightFromLocalStorage() {
    var checkboxes = document.querySelectorAll('.priority-check');
    checkboxes.forEach(function(checkbox) {
        var supportId = checkbox.dataset.supportId;
        if (localStorage.getItem('highlightedRow' + supportId) === 'true') {
            checkbox.checked = true;
            checkbox.closest('tr').classList.add('highlighted');
        } else {
            checkbox.checked = false;
            checkbox.closest('tr').classList.remove('highlighted');
        }
    });
    sortHighlightedRows(); // Chama a ordenação após aplicar o destaque inicial
}

document.addEventListener('DOMContentLoaded', function() {
    applyHighlightFromLocalStorage();
    var checkboxes = document.querySelectorAll('.priority-check');
    checkboxes.forEach(function(checkbox) {
        var supportId = checkbox.dataset.supportId;
        checkbox.addEventListener('change', function() {
            togglePriority(this, supportId);
        });
    });
});

</script>




<script>
document.addEventListener("DOMContentLoaded", atualizarContadores);

// Função para atualizar a visualização do total de BI e prioridades
function atualizarContadores() {
    const urlParams = new URLSearchParams(window.location.search);
    const url = 'obter_totais_suporte.php' + (urlParams.toString() ? '?' + urlParams.toString() : '');

    fetch(url)
        .then(response => response.json())
        .then(data => {
            // Atualiza os textos dos contadores nos cards com os dados recebidos
            document.getElementById('totalSuporte').textContent = data.totalSuportes;
            document.getElementById('prioridadeAlta').textContent = data.totalPrioridadeAlta;
            document.getElementById('prioridadeMedia').textContent = data.totalPrioridadeMedia;
            document.getElementById('prioridadeBaixa').textContent = data.totalPrioridadeBaixa;
        })
        .catch(error => console.error('Erro ao obter totais:', error));
}

function filterSuportes(filtro) {
    var urlParams = new URLSearchParams(window.location.search);
    if (filtro) {
        urlParams.set('prioridade', filtro); // Define o filtro de prioridade
    } else {
        urlParams.delete('prioridade'); // Remove o filtro de prioridade se nenhum for selecionado
    }
    // Redireciona para a mesma página com os novos parâmetros de consulta
    window.location.search = urlParams.toString();
}


// Para garantir que a função `atualizarContadores` seja chamada após um filtro ser aplicado
window.onpopstate = atualizarContadores;



</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var dataAtual = new Date();
        var dia = ('0' + dataAtual.getDate()).slice(-2); // Adiciona zero à esquerda se necessário
        var mes = ('0' + (dataAtual.getMonth() + 1)).slice(-2); // Adiciona zero à esquerda se necessário
        var ano = dataAtual.getFullYear();
        var dataFormatada = ano + '-' + mes + '-' + dia;

        document.getElementById('dt_criacao').value = dataFormatada;
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

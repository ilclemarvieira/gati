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

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
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
$perfisPermitidos = [1, 2, 5, 9]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);

// Define o ano atual
$anoAtual = date('Y');

// Aqui você faria a consulta ao banco de dados para obter os dados da tabela 'os'
$oss = $pdo->query("SELECT b.*, u.Nome as NomeResponsavel, c.Nome as NomeContratada FROM bi b LEFT JOIN usuarios u ON b.Responsavel = u.Id LEFT JOIN contratadas c ON b.Id_contratada = c.Id")->fetchAll(PDO::FETCH_ASSOC);


// Inicializa as cláusulas WHERE e o array de parâmetros
$whereClauses = [];
$params = [];

// Filtro por responsável
if (isset($_GET['responsavel']) && $_GET['responsavel'] != '') {
    $whereClauses[] = 'Responsavel = :responsavel';
    $params[':responsavel'] = $_GET['responsavel'];
}

// Filtro por status_inova
if (isset($_GET['status_inova']) && $_GET['status_inova'] !== '') {
    $whereClauses[] = 'Status_inova = :status_inova';
    $params[':status_inova'] = $_GET['status_inova'];
}

// Filtro por os_paga
if (isset($_GET['os_paga']) && $_GET['os_paga'] !== '') {
    $whereClauses[] = 'Os_paga = :os_paga';
    $params[':os_paga'] = $_GET['os_paga'];
}

// Filtro por prioridade
if (isset($_GET['prioridade']) && $_GET['prioridade'] != '') {
    $whereClauses[] = 'Prioridade = :prioridade';
    $params[':prioridade'] = $_GET['prioridade'];
}

// Filtro por número ou nome da OS
if (isset($_GET['numero_os']) && $_GET['numero_os'] !== '') {
    $whereClauses[] = '(N_os LIKE :numero_os OR Nome_os LIKE :nome_os)';
    $params[':numero_os'] = '%' . $_GET['numero_os'] . '%';
    $params[':nome_os'] = '%' . $_GET['numero_os'] . '%';
}

// Filtro por ano ajustado
if (isset($_GET['year']) && $_GET['year'] !== '' && $_GET['year'] !== 'all') {
    $whereClauses[] = 'YEAR(Dt_inicial) = :year';
    $params[':year'] = $_GET['year'];
} elseif (!isset($_GET['year']) || $_GET['year'] == '') {
    // Define o ano atual como padrão se não houver seleção
    $_GET['year'] = $anoAtual;
    $whereClauses[] = 'YEAR(Dt_inicial) = :year';
    $params[':year'] = $anoAtual;
}


// Excluir registros com Status_inova "Finalizado" se nenhum filtro de status_inova estiver ativo
if (!isset($_GET['status_inova']) || $_GET['status_inova'] == '' || $_GET['status_inova'] !== 'Finalizado') {
    $whereClauses[] = "b.Status_inova != 'Finalizado'";
}

// Verifica se algum filtro foi realmente aplicado
$filtrosAplicados = 
    (isset($_GET['responsavel']) && $_GET['responsavel'] !== '') ||
    (isset($_GET['status_inova']) && $_GET['status_inova'] !== '') ||
    (isset($_GET['os_paga']) && $_GET['os_paga'] !== '') ||
    (isset($_GET['prioridade']) && $_GET['prioridade'] !== '') ||
    (isset($_GET['numero_os']) && $_GET['numero_os'] !== '') ||
    (isset($_GET['year']) && $_GET['year'] !== '' && $_GET['year'] !== 'all');

// Se nenhum filtro foi aplicado, adicionar a cláusula para excluir os "Finalizados"
if (!$filtrosAplicados) {
    $whereClauses[] = "b.Status_inova != 'Finalizado'";
}

// Construção da cláusula WHERE dinâmica
$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Consulta SQL principal com a cláusula WHERE ajustada
$query = "
    SELECT b.*, u.Nome as NomeResponsavel, c.Nome as NomeContratada 
    FROM bi b
    LEFT JOIN usuarios u ON b.Responsavel = u.Id
    LEFT JOIN contratadas c ON b.Id_contratada = c.Id
    $whereSql
    ORDER BY FIELD(b.Prioridade, 'Alta', 'Média', 'Baixa'), b.N_os ASC
";

// Consulta para obter os status distintos
$statusesQuery = "SELECT DISTINCT Status_inova FROM bi ORDER BY Status_inova ASC";
$statusesStmt = $pdo->query($statusesQuery);
$statuses = $statusesStmt->fetchAll(PDO::FETCH_COLUMN);

// Prepara e executa a consulta principal
$statement = $pdo->prepare($query);
$statement->execute($params); // Executa com os parâmetros definidos
$oss = $statement->fetchAll(PDO::FETCH_ASSOC);

// Buscar anos distintos de criação dos oss
$yearsQuery = "SELECT DISTINCT YEAR(Dt_inicial) AS Year FROM bi ORDER BY Year DESC";
$yearsStmt = $pdo->query($yearsQuery);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

// Consulta para buscar todos os usuários com perfil 2 (Gestor) ou 5 (Bi)
$usuarios = $pdo->query("SELECT Id, Nome FROM usuarios WHERE PerfilAcesso IN (2, 5) ORDER BY Nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Consulta para buscar todas as contratadas
$contratadasStmt = $pdo->query("SELECT Id, Nome FROM contratadas");
$contratadas = $contratadasStmt->fetchAll(PDO::FETCH_ASSOC);


function gerarProximoNumeroOS($pdo) {
    $currentYear = date('Y'); // Obtém o ano atual
    $query = "SELECT MAX(CAST(SUBSTRING_INDEX(N_os, '-', 1) AS UNSIGNED)) AS ultimo_numero 
              FROM bi 
              WHERE N_os LIKE '%-$currentYear'";
    $stmt = $pdo->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $ultimoNumero = $result['ultimo_numero'] ?? 0;
    $proximoNumero = $ultimoNumero + 1;
    return str_pad($proximoNumero, 2, '0', STR_PAD_LEFT) . '-' . $currentYear;
}

// Exemplo de uso
$proximoNumeroOS = gerarProximoNumeroOS($pdo);

$statement = $pdo->prepare($query);
$statement->execute($params);
$oss = $statement->fetchAll(PDO::FETCH_ASSOC);

// Verifica se há resultados para exibir
$hasResults = !empty($oss);

$statusColorsInova = [
    'Aguardando Horas' => 'bg-secondary',
    'Em Análise' => 'bg-warning',
    'Não Aprovada' => 'bg-danger',
    'Aprovada' => 'bg-success',
    'Em Backlog' => 'bg-orange', 
    'Em Desenvolvimento' => 'bg-info',
    'Pendente' => 'bg-primary',
    'Paralisado' => 'bg-dark',
    'Finalizado' => 'bg-light'
];

$statusColorsContratada = [
    'Não Começou' => 'bg-secondary', // Cinza
    'Em Análise' => 'bg-warning', // Amarelo
    'Gerando Horas' => 'bg-info', // Azul claro
    'Paralisado' => 'bg-danger', // Vermelho
    'Aprovado Inova' => 'bg-success', // Verde
    'Em Desenvolvimento' => 'bg-primary', // Azul escuro
    'Em Testes' => 'bg-teal', // Cor personalizada (exemplo: turquesa)
    'Em Homologação' => 'bg-purple', // Cor personalizada (exemplo: púrpura)
    'Em Produção' => 'bg-orange' // Cor personalizada (exemplo: laranja)
];


// Array associativo para mapear prioridades a cores
$prioridadesCores = [
    'Baixa' => 'bg-info',
    'Média' => 'bg-warning',
    'Alta' => 'bg-danger'
];


$whereClauses = [];
$params = [];

// Adicionando filtro por responsável
if (isset($_GET['responsavel']) && $_GET['responsavel'] != '') {
    $whereClauses[] = 'Responsavel = :responsavel';
    $params[':responsavel'] = $_GET['responsavel'];
}

// Adicionando filtro por status_inova
if (isset($_GET['status_inova']) && $_GET['status_inova'] !== '') {
    $whereClauses[] = 'Status_inova = :status_inova';
    $params[':status_inova'] = $_GET['status_inova'];
}

// Adicionando filtro por ano
// Filtro por ano ajustado
if (isset($_GET['year']) && $_GET['year'] !== '' && $_GET['year'] !== 'all') {
    $whereClauses[] = 'YEAR(Dt_inicial) = :year';
    $params[':year'] = $_GET['year'];
} elseif (!isset($_GET['year']) || $_GET['year'] == '') {
    // Define o ano atual como padrão se não houver seleção
    $_GET['year'] = $anoAtual;
    $whereClauses[] = 'YEAR(Dt_inicial) = :year';
    $params[':year'] = $anoAtual;
}

// Montando a consulta SQL com cláusulas WHERE
$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';


// Consulta para somar os valores de 'Apf'
$somaTotalQuery = "SELECT SUM(Apf) AS SomaApf FROM bi" . $whereSql;
$somaTotalStmt = $pdo->prepare($somaTotalQuery);
$somaTotalStmt->execute($params);
$somaTotalResult = $somaTotalStmt->fetch(PDO::FETCH_ASSOC);

if ($somaTotalResult && $somaTotalResult['SomaApf'] !== null) {
    $tempoFormatado = (int) $somaTotalResult['SomaApf'];
} else {
    $tempoFormatado = 0;
}




// Consulta para calcular a soma total de 'Apf' e 'Valor'
$somaTotalQuery = "SELECT SUM(b.Apf) AS SomaApf, SUM(b.Valor) AS SomaValor FROM bi b {$whereSql}";
$somaTotalStmt = $pdo->prepare($somaTotalQuery);
$somaTotalStmt->execute($params);
$somaTotalResult = $somaTotalStmt->fetch(PDO::FETCH_ASSOC);

// Exibindo os resultados (opcional, depende da sua necessidade)
if ($somaTotalResult) {
    $somaApf = $somaTotalResult['SomaApf']; // Pode precisar de formatação similar ao 'tempoFormatado' dependendo do formato de 'Apf'
    $somaValor = $somaTotalResult['SomaValor']; // Supondo que este seja um valor monetário, poderia precisar de formatação de moeda
}





$itensPorPagina = 20; // Definir quantas OS por página
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;


$query .= " LIMIT :offset, :itensPorPagina";
$statement = $pdo->prepare($query);
foreach ($params as $key => $val) {
    $statement->bindValue($key, $val);
}

$statement->bindParam(':offset', $offset, PDO::PARAM_INT);
$statement->bindParam(':itensPorPagina', $itensPorPagina, PDO::PARAM_INT);
$statement->execute();
$oss = $statement->fetchAll(PDO::FETCH_ASSOC);

// Calcula o total de projetos aplicando os filtros
$totalOsStmt = $pdo->prepare("SELECT COUNT(*) FROM bi b {$whereSql}");
$totalOsStmt->execute($params);
$totalOs = $totalOsStmt->fetchColumn();

$totalPaginas = ceil($totalOs / $itensPorPagina);


function buscarValorTotalEmpenhoPorAno($pdo, $ano) {
    $query = "SELECT SUM(valor) AS total_empenho FROM empenho";
    if ($ano !== null && $ano !== 'all') {
        $query .= " WHERE ano = :ano";
    }

    $stmt = $pdo->prepare($query);
    if ($ano !== null && $ano !== 'all') {
        $stmt->bindParam(':ano', $ano, PDO::PARAM_INT);
    }
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ? $resultado['total_empenho'] : 0;
}

function buscarValorTotalGastoPorAno($pdo, $ano) {
    $query = "SELECT SUM(Valor) AS total_gasto FROM os WHERE Os_paga = 1";
    if ($ano !== null && $ano !== 'all') {
        $query .= " AND YEAR(Dt_inicial) = :ano";
    }

    $stmt = $pdo->prepare($query);
    if ($ano !== null && $ano !== 'all') {
        $stmt->bindParam(':ano', $ano, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ? $resultado['total_gasto'] : 0;
}

// Para calcular o valor disponível para todos os anos ou um ano específico
function calcularValorTotalDisponivel($pdo, $ano) {
    $totalEmpenho = buscarValorTotalEmpenhoPorAno($pdo, $ano);
    $totalGasto = buscarValorTotalGastoPorAno($pdo, $ano);
    return $totalEmpenho - $totalGasto;
}


// Verifique se o filtro de ano foi definido como "Todos os Anos"
$anoFiltrado = $_GET['year'] ?? 'all';
if ($anoFiltrado === 'all') {
    // Calcula o valor disponível para todos os anos
    $valorTotalDisponivel = calcularValorTotalDisponivel($pdo, null);
} else {
    // Calcula o valor disponível para o ano filtrado
    $valorTotalDisponivel = calcularValorTotalDisponivel($pdo, $anoFiltrado);
}



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
    gap: 10px; /* Espaçamento reduzido entre os campos */
    padding: 10px;
    border: 1px solid #3c4147;
    border-radius: 5px;
    margin-bottom: 20px;
}

.filter-container select,
.filter-container input[type="text"], /* Estilizando o input de texto */
.filter-container button {
    height: 38px;
    border: 1px solid #5b5f69;
    background-color: #272b34;
    color: #b2b9b2;
    padding: 0 15px;
    font-size: 14px;
    margin-right: 10px; /* Margem à direita dos elementos */
}

.filter-container input[type="text"] {
    flex-grow: 1;
    margin: 0; /* Removendo margens adicionais */
    margin-right: 10px; /* Margem à direita dos elementos */
}

.filter-container button {
    white-space: nowrap;
    text-align: center;
    cursor: pointer;
    transition: background-color 0.3s, border-color 0.3s, color 0.3s;
}

.filter-container button:hover {
    background-color: #5a6268;
    border-color: #545b62;
    color: #ffffff;
}

/* Ajustando para telas pequenas */
@media (max-width: 768px) {
    .filter-container {
        flex-direction: column;
    }

    .filter-container select,
    .filter-container input[type="text"],
    .filter-container button {
        width: 100%;
        margin-right: 0; /* Removendo a margem à direita em telas menores */
        margin-top: 10px; /* Espaçamento vertical entre os elementos */
    }
    
    .filter-container button {
        order: 1; /* Movendo o botão para a parte superior do container */
        margin-top: 10px; /* Espaçamento acima do botão */
    }
}

.filter-container input[type="text"] {
    margin-top: 0; /* Corrigindo a margem superior para inputs de texto */
}


input[type="text"]::placeholder {
    color: #b2b9b2; /* Altera a cor do placeholder */
    opacity: 1; /* Necessário para corrigir a opacidade padrão em alguns navegadores */
}

/* Para suporte a navegadores antigos como o Internet Explorer */
input[type="text"]:-ms-input-placeholder {
    color: #b2b9b2;
}

input[type="text"]::-ms-input-placeholder {
    color: #b2b9b2;
}

/* Para suporte ao Mozilla Firefox 18- */
input[type="text"]::-moz-placeholder {
    color: #b2b9b2;
    opacity: 1;
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
    background-color: #fff;
    margin: 2% auto;
    padding: 20px;
    border-radius: 8px;
    width: 60%; /* Ajuste a largura conforme necessário */
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
  background-color: #6268d3; /* Cor de fundo mais clara ao hover */
  border-color: #8084d3; /* Cor da borda mais clara ao hover */
  box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.3); /* Sombra mais pronunciada ao hover */
  transform: translateY(-2px); /* Leve elevação do botão ao hover */
}

/* Efeito ao clicar no botão */
.btn-filtrar:active {
  transform: translateY(1px); /* Pressionando o botão para baixo */
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.2); /* Sombra mais plana quando pressionado */
}

#currentAttachments {
    flex-direction: column; /* Organiza os anexos em colunas */
    align-items: flex-start; /* Alinha os itens à esquerda */
}

.attachment-container {
    background-color: #02080e14; /* Fundo claro para cada anexo */
    border: 1px solid #dee2e621; /* Borda sutil */
    padding: 8px; /* Espaçamento interno */
    margin-bottom: 8px; /* Espaçamento entre os anexos */
    border-radius: 4px; /* Bordas arredondadas */
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.attachment-container a {
    color: #5fa2e9; /* Cor do link */
    text-decoration: none; /* Remove sublinhado do link */
    margin-right: 16px; /* Espaçamento à direita do link */
}

.attachment-remove-btn {
    background-color: #dc3545; /* Cor de fundo vermelha */
    color: white; /* Texto branco */
    border: none; /* Sem borda */
    padding: 6px 12px; /* Espaçamento interno */
    cursor: pointer; /* Cursor de clique */
    border-radius: 4px; /* Bordas arredondadas */
    font-size: 14px; /* Tamanho do texto */
}

.attachment-remove-btn:hover {
    background-color: #c82333; /* Cor de fundo mais escura ao passar o mouse */
}

#viewAnexoContainer {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.anexo-item {
    background-color: #02080e14;
    border: 1px solid #dee2e621;
    padding: 8px;
    margin-bottom: 8px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.anexo-item a {
    color: #5fa2e9;
    text-decoration: none;
    margin-right: 16px;
}

.view-button {
    padding: 6px 12px;
    background-color: #495057; /* A darker shade for visibility */
    color: #f8f9fa; /* Light color for text for better contrast */
    border: 1px solid #f8f9fa; /* Light border to help the button stand out */
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin-top: 10px;
    transition: background-color 0.2s, color 0.2s; /* Smooth transition for hover effect */
}

.view-button:hover {
    background-color: #6c757d; /* A lighter shade for hover */
    color: #dee2e6; /* A darker shade for text on hover for contrast */
}

.star-rating {
    display: inline-flex; /* Usar inline-flex para manter as estrelas na mesma linha */
    flex-wrap: nowrap; /* Impede a quebra de linha */
    gap: 2px; /* Espaçamento entre as estrelas, ajuste conforme necessário */
}

.star {
    font-size: 20px; /* Tamanho das estrelas */
    cursor: pointer;
    color: #cccccc91; /* Cor das estrelas não preenchidas */
    transition: color 0.3s; /* Transição suave de cor */
}

.star.filled {
    color: gold; /* Cor das estrelas preenchidas */
}

/* Estilo do switch - versão ajustada */
.switch {
  position: relative;
  display: inline-block;
  width: 50px;  /* Reduzido para um visual mais compacto */
  height: 24px; /* Reduzido para destacar menos */
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cccccc91;
  transition: .4s;
  border-radius: 24px; /* Totalmente arredondado */
}

.slider:before {
  position: absolute;
  content: "";
  height: 18px;  /* Reduzido para um visual mais delicado */
  width: 18px;   /* Mantido proporcional */
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);  /* Sombra sutil para destacar */
}

input:checked + .slider {
  background-color: #28a745;  /* Verde para indicar "pago" */
}

input:focus + .slider {
  box-shadow: 0 0 1px #28a745;
}

input:checked + .slider:before {
  transform: translateX(26px);  /* Ajustado para corresponder ao novo tamanho do slider */
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
            <h3 class="text-themecolor mb-0"><i class="mdi mdi-poll"></i> Dev BI</h3>            
          </div>          
        </div>


       
        <div class="container-fluid">  

       <div class="row">
<div class="col-lg-4 col-md-6">
  <div class="card">
    <div class="card-body">
      <div class="d-flex flex-row">
        <div class="round round-lg text-white rounded-circle bg-info">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-clipboard">
            <path d="M19 2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"></path>
            <path d="M17 2v4H7V2z"></path>
            <line x1="12" y1="11" x2="12" y2="11"></line>
            <line x1="12" y1="15" x2="12" y2="15"></line>
          </svg>
        </div>
        <div>
          <h3 class="card-title">Total de Projetos</h3>
          <p class="card-text text-muted"><?php echo $totalOs; ?></p>
        </div>
      </div>
    </div>
  </div>
</div>


<div class="col-lg-4 col-md-6">
  <div class="card">
    <div class="card-body">
      <div class="d-flex flex-row">
        <div class="round round-lg text-white rounded-circle bg-warning">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-calculator">
            <rect x="2" y="2" width="20" height="20" rx="2" ry="2"></rect>
            <line x1="8" y1="2" x2="8" y2="22"></line>
            <line x1="16" y1="2" x2="16" y2="22"></line>
            <line x1="2" y1="10" x2="22" y2="10"></line>
            <line x1="2" y1="14" x2="8" y2="14"></line>
            <line x1="2" y1="18" x2="8" y2="18"></line>
            <line x1="16" y1="14" x2="22" y2="14"></line>
            <line x1="16" y1="18" x2="22" y2="18"></line>
          </svg>
        </div>
        <div>
    <h3 class="card-title">Total de Horas</h3>
    <p class="card-text text-muted"><?php echo $tempoFormatado; ?></p>
</div>


      </div>
    </div>
  </div>
</div>


  <?php
// Define um título padrão
$tituloCard = "Valor Total";

// Verifique se o filtro 'os_paga' está definido e ajuste o título conforme o valor
if (isset($_GET['os_paga'])) {
    if ($_GET['os_paga'] === "1") {
        $tituloCard = "Valor Utilizado";
    } elseif ($_GET['os_paga'] === "0") {
        $tituloCard = "Valor Pendente";
    }
}

?>

<!-- Card Valor Total Gasto -->
<div class="col-lg-4 col-md-6">
  <div class="card">
    <div class="card-body">
      <div class="d-flex flex-row">
        <div class="round round-lg text-white rounded-circle bg-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-dollar-sign">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
        </div>
        <div>
          <h3 class="card-title"><?php echo $tituloCard; ?></h3>
          <p class="card-text text-muted"><?php echo 'R$ ' . number_format($somaTotalResult['SomaValor'], 2, ',', '.'); ?></p>
        </div>
      </div>
    </div>
  </div>
</div>


</div>

<!-- Botão para abrir o modal de adicionar nova OS -->
        <button class="btn waves-effect waves-light btn-info" onclick="openModal('addOsModal')"><i class="ti-plus text"></i> Novo Projeto BI</button>

        <br><br>

        <!-- Filtro por Ano -->
        <div class="filter-container d-flex flex-wrap align-items-center justify-content-between">
    <form id="filterOsForm" action="bi.php" method="get" class="d-flex flex-wrap align-items-center w-100">

        <select name="year">
    <option value="all">Todos os Anos</option>
    <?php foreach ($years as $year): ?>
        <option value="<?php echo $year; ?>" <?php if ($_GET['year'] == $year) echo 'selected'; ?>>
            <?php echo $year; ?>
        </option>
    <?php endforeach; ?>
</select>

        <!-- Filtro por Responsável -->
<select name="responsavel">
    <option value="">Todos os Responsáveis</option>
    <?php foreach ($usuarios as $usuario): ?>
        <option value="<?php echo $usuario['Id']; ?>" <?php if (isset($_GET['responsavel']) && $_GET['responsavel'] == $usuario['Id']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($usuario['Nome']); ?>
        </option>
    <?php endforeach; ?>
</select>


        <!-- Filtro por Status da Contratada -->
        <select name="status_inova">
    <option value="">Todos os Status</option>
    <?php foreach ($statuses as $status): ?>
        <option value="<?php echo htmlspecialchars($status); ?>" <?php if (isset($_GET['status_inova']) && $_GET['status_inova'] == $status) echo 'selected'; ?>>
            <?php echo htmlspecialchars($status); ?>
        </option>
    <?php endforeach; ?>
</select>

        <!-- Filtro por OS Paga -->
        <select name="os_paga">
            <option value="">BI Pago e Não Pago</option>
            <option value="1" <?php if (isset($_GET['os_paga']) && $_GET['os_paga'] == "1") echo 'selected'; ?>>Sim</option>
            <option value="0" <?php if (isset($_GET['os_paga']) && $_GET['os_paga'] == "0") echo 'selected'; ?>>Não</option>
        </select>

        <!-- Input para buscar pelo número ou nome da OS -->
        <input type="text" name="numero_os" placeholder="Digite o número ou nome do BI" value="<?php echo isset($_GET['numero_os']) ? $_GET['numero_os'] : ''; ?>" />


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
                    <th>N° BI</th>
                    <th scope="col" style="white-space: nowrap;">Nome</th>
                    <th scope="col" style="white-space: nowrap;">Dt Criação</th>
                    <th scope="col" style="white-space: nowrap;">Horas</th>
                    <th scope="col" style="white-space: nowrap;">Valor</th>                    
                    <th scope="col" style="white-space: nowrap;">Prioridade</th>
                    <th scope="col" style="white-space: nowrap;">Status</th>                    
                    <th scope="col" style="white-space: nowrap;">Responsável</th>
                    <th scope="col" style="white-space: nowrap;">Pago?</th>
                    <th scope="col" style="white-space: nowrap;">Andamento</th>

                    <th></th>
                </tr>
            </thead>
            <tbody style="font-size:12.5px; font-weight: 400;">
    <?php if ($hasResults): ?>
    <?php foreach ($oss as $os): 
    // Determine se a linha deve ter o estilo de finalizada
    $finalizadaClass = ($os['Status_contratada'] == 'Em Produção' && $os['Os_paga'] == '1') ? 'finalizada' : '';
    // Formata a hora no formato HH:MM
    $tempoFormatado = $os['Apf']; // Assume que Apf já está no formato HH:MM
    ?>
    <tr role="row" class="odd <?php echo $finalizadaClass; ?>">
        <td style="text-align: center; vertical-align: middle;"><input type="checkbox" class="priority-check" data-os-id="<?php echo $os['Id']; ?>" onclick="togglePriority(this, <?php echo $os['Id']; ?>)"></td>
        <td style="text-align: center; vertical-align: middle;"><span class="badge bg-info"><?php echo htmlspecialchars($os['N_os']); ?></span></td>
        <td style="text-align: left; font-weight: 500; text-transform: uppercase; vertical-align: middle;"><?php echo htmlspecialchars($os['Nome_os']); ?></td>
        <td style="text-align: center; font-weight: 500; vertical-align: middle;"><?php echo htmlspecialchars(date('d/m/Y', strtotime($os['Dt_inicial']))); ?></td>

       <td style="text-align: center; font-weight: 500; vertical-align: middle;">
    <?php echo htmlspecialchars(round((float)$tempoFormatado)); ?>
</td>
        <td style="text-align: center; font-weight: 500; vertical-align: middle;"><?php echo 'R$ ' . htmlspecialchars(number_format($os['Valor'], 2, ',', '.')); ?></td>
        <td style="text-align: center; vertical-align: middle;"><span class="badge <?php echo $prioridadesCores[$os['Prioridade']] ?? 'bg-default'; ?>"><?php echo htmlspecialchars($os['Prioridade']); ?></span></td>
        <td style="text-align: center; vertical-align: middle;"><span class="badge <?php echo $statusColorsInova[$os['Status_inova']] ?? 'bg-default'; ?>"><?php echo htmlspecialchars($os['Status_inova']); ?></span></td>
        <?php
// Recupera os IDs dos responsáveis separados por vírgulas e converte em um array
$responsaveisArray = explode(',', $os['Responsavel']);

// Array para armazenar os nomes dos responsáveis
$responsaveisNomes = [];

// Loop para buscar os nomes dos responsáveis com base nos IDs
foreach ($usuarios as $usuario) {
    if (in_array($usuario['Id'], $responsaveisArray)) {
        $responsaveisNomes[] = $usuario['Nome'];
    }
}
?>

<!-- Exibição dos nomes dos responsáveis, separados por vírgulas -->
<td style="text-align: center; font-weight: 500; vertical-align: middle;">
    <?php echo htmlspecialchars(implode(', ', $responsaveisNomes)); ?>
</td>

        <td style="text-align: center; vertical-align: middle;">
    <label class="switch">
        <input type="checkbox" onchange="togglePagamento(<?php echo $os['Id']; ?>, this)" <?php echo $os['Os_paga'] ? 'checked' : ''; ?>>
        <span class="slider"></span>
    </label>
</td>




        <td style="text-align: center; vertical-align: middle;">
    <div class="star-rating" data-os-id="<?php echo $os['Id']; ?>">
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="star <?php echo ($i <= $os['Andamento']) ? 'filled' : ''; ?>" data-value="<?php echo $i; ?>">&#9733;</span>
        <?php endfor; ?>
            </div>
        </td>

        <td style="text-align: center; vertical-align: middle" class="action-buttons">
            <div class="d-flex justify-content-center align-items-center">
                <button onclick="viewOsDetails(<?php echo $os['Id']; ?>)" title="Ver" class="btn btn-sm btn-light-info text-white mx-1">
                    <i data-feather="eye" class="feather-sm fill-white"></i>
                </button>
                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($os)); ?>)" title="Editar" class="btn btn-sm btn-light-info text-white mx-1">
                    <i data-feather="edit" class="feather-sm fill-white"></i>
                </button>
                <button onclick="window.open('gerar_pdf_bi.php?osId=<?php echo $os['Id']; ?>', '_blank')" title="Gerar PDF BI" class="btn btn-sm btn-light-info text-white mx-1">
                    <i data-feather="file-text" class="feather-sm fill-white"></i>
                </button>
                <button onclick="deleteOs(<?php echo $os['Id']; ?>)" title="Excluir" class="btn btn-sm btn-light-danger text-white mx-1">
                    <i data-feather="trash-2" class="feather-sm fill-white"></i>
                </button>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="13" style="text-align:center;">Nenhum projeto encontrado</td>
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
            // Gera a base da URL com os filtros atuais, exceto o parâmetro 'pagina'
            $parametrosFiltro = $_GET;
            unset($parametrosFiltro['pagina']); // Remove o parâmetro 'pagina' para evitar duplicações
            $baseURL = 'bi?' . http_build_query($parametrosFiltro) . '&pagina=';
            
            for ($i = 1; $i <= $totalPaginas; $i++): 
                // Gera o link completo para a página atual incluindo os filtros
                $urlPagina = $baseURL . $i;
                $classeAtiva = $paginaAtual === $i ? 'active' : '';
            ?>
                <li class="page-item <?php echo $classeAtiva; ?>">
                    <a class="page-link" href="<?php echo $urlPagina; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>



          <!-- Modal de Cadastro de OS -->
<div id="addOsModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalCadastroOsLabel"><i class="mdi mdi-poll"></i>&nbsp;Novo Projeto BI</h2>
                <span class="close" onclick="closeModal('addOsModal')">&times;</span>
            </div>
        
        <form id="addOsForm" action="process_add_bi.php" method="post" enctype="multipart/form-data">
            <div class="modal-body">
            <input type="hidden" id="addValorNumerico" name="valor_numerico">
            <!-- Campos para a inserção de dados de uma nova OS -->
            <!-- Número da OS -->
            <div class="mb-3">
                <label for="addNOs" class="form-label">N° BI</label>
                <input style="background-color: #000" type="text" class="form-control bg-secondary text-white" id="addNOs" name="n_os" value="<?php echo $proximoNumeroOS; ?>"  class="readonly">
            </div>
            
            <!-- Nome da OS -->
            <div class="mb-3">
              <label for="addNomeOs" class="form-label">Nome do Projeto</label>
              <input type="text" class="form-control bg-secondary text-white" id="addNomeOs" name="nome_os" required>
            </div>        
            
            
           <div class="mb-3">
    <label for="addApf" class="form-label">Horas</label>
    <input type="number" class="form-control bg-secondary text-white" id="addApf" name="apf" oninput="calculateValueFromHours(this, 'add');" placeholder="Informe a quantidade de horas" min="0" step="0.01">
</div>




            
            <!-- Valor (calculado automaticamente com base na APF) -->
            <div class="mb-3">
    <label for="addValor" class="form-label">Valor (R$)</label>
    <input type="text" class="form-control bg-secondary text-white" id="addValor" name="valor" readonly>
</div>


            
            <!-- Data Inicial -->
            <div class="mb-3">
                <label for="addDtInicial" class="form-label">Data Inicial</label>
                <input type="date" class="form-control bg-secondary text-white" id="addDtInicial" name="dt_inicial" required>
            </div>
            
            <!-- Prazo de Entrega -->
            <div class="mb-3">
                <label for="addPrazoEntrega" class="form-label">Data de Entrega</label>
                <input type="date" class="form-control bg-secondary text-white" id="addPrazoEntrega" name="prazo_entrega">
            </div>
            
            <!-- Prioridade -->
            <div class="mb-3">
                <label for="addPrioridade" class="form-label">Prioridade</label>
                <select class="form-select" id="addPrioridade" name="prioridade" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Média">Média</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>

            <div class="mb-3">
    <label for="addStatusInova" class="form-label">Status Inova</label>
    <select class="form-select" id="addStatusInova" name="status_inova" required>
        <option value="Em Backlog">Em Backlog</option>
        <option value="Aguardando Horas">Aguardando Horas</option>
        <option value="Paralisado">Paralisado</option>
        <option value="Pendente">Pendente</option>        
        <option value="Em Análise">Em Análise</option>
        <option value="Aprovada">Aprovada</option>
        <option value="Não Aprovada">Não Aprovada</option>        
        <option value="Em Desenvolvimento">Em Desenvolvimento</option>        
        <option value="Finalizado">Finalizado</option>
    </select>
</div>
            

<div class="mb-3">
    <label for="addResponsaveis" class="form-label">Responsáveis</label>
    <select class="form-select" id="addResponsaveis" name="responsaveis[]" multiple required>
        <?php foreach ($usuarios as $usuario): ?>
            <option value="<?php echo $usuario['Id']; ?>">
                <?php echo htmlspecialchars($usuario['Nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>


<div class="mb-3">
    <label for="addIdContratada" class="form-label">Contratada</label>
    <select class="form-select" id="addIdContratada" name="id_contratada">
        <option value="0">Nenhuma</option>
        <?php foreach ($contratadas as $contratada): ?>            
            <option value="<?php echo $contratada['Id']; ?>">                
                <?php echo htmlspecialchars($contratada['Nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

        <div class="mb-3">
        <label for="addDescricao" class="form-label">Descrição</label>
       <textarea id="addDescricao" name="descricao" class="form-control bg-secondary text-white" style="height: 250px"></textarea>
       </div>


<!-- Campo oculto para 'OS Paga' -->
        <input type="hidden" id="addOsPaga" name="os_paga" value="0">

<div class="mb-3">
    <label for="addAnexoNf" class="form-label">Anexo</label>
    <input type="file" class="form-control" id="addAnexoNf" name="anexo_nf[]" multiple>

    <!-- Botão para excluir o anexo existente -->
    <?php if (isset($osItem['Anexo_nf']) && $osItem['Anexo_nf']): ?>
     <button type="button" onclick="deleteOs(<?php echo $os['Id']; ?>)">Excluir Projeto</button>
    <?php endif; ?>
</div>


<div class="mb-3" style="display: none;">
    <label for="addObservacao" class="form-label">Observação</label>
    <textarea class="form-control bg-secondary text-white" id="addObservacao" name="observacao" style="height: 150px;"></textarea>
</div>

            
            <!-- Botão de submissão -->
        <div class="d-flex justify-content-end mt-4"> <!-- Adiciona margem superior e alinha à direita -->
            <button type="submit" class="btn btn-primary font-weight-medium rounded-pill px-4">
                <div class="d-flex align-items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-send feather-sm fill-white me-2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                    Cadastrar
                </div>
            </button>
        </div>

        </form>
    </div>
</div>
</div>
</div>





        <!-- Modal de Visualização -->
<div id="viewOsModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalVisualizacaoLabel">
                    <i class="mdi mdi-eye-outline"></i>&nbsp;Visualizar BI 
                </h2>

                <span class="close" onclick="closeModal('viewOsModal')">&times;</span>
            </div>

        <div class="modal-body">
        <div class="os-details">

            <div class="mb-3">
                <h3 style="font-size:18px">Nº BI</h3>
                <p style="font-size:15px" id="viewNOs"></p>
            </div>
            <div class="mb-3">
                <h3 style="font-size:18px">Nome do Projeto</h3>
                <p style="font-size:15px" id="viewNomeOs"></p>
            </div>
            <div class="mb-3">
                <h3 style="font-size:18px">Horas</h3>
                <p style="font-size:15px" id="viewApf">15</p>
            </div>
            <div class="mb-3">
                <h3 style="font-size:18px">Valor</h3>
                <p style="font-size:15px" id="viewValor"></p>
            </div>
            <div class="mb-3">
                <h3 style="font-size:18px">Data Inicial</h3>
                <p style="font-size:15px" id="viewDtInicial"></p>
            </div>
            <div class="mb-3">
                <h3 style="font-size:18px">Data de Entrega</h3>
                <p style="font-size:15px" id="viewPrazoEntrega"></p>
            </div>
            <div class="mb-3">
                <h3 style="font-size:18px">Prioridade</h3>
                <p style="font-size:15px" id="viewPrioridade"></p>
            </div>
            <div class="mb-3">
                <h3 style="font-size:18px">Status Inova</h3>
                <p style="font-size:15px" id="viewStatusInova"></p>
            </div>            
            <div class="mb-3">
                <h3 style="font-size:18px">Responsável</h3>
                <p style="font-size:15px" id="viewResponsavel"></p>
            </div>
            <div class="mb-3">
                <h3 style="font-size:18px">Contratada</h3>
                <p style="font-size:15px" id="viewIdContratada"></p>
            </div>
            <div class="mb-3">
                <h3 style="font-size:18px">Descrição</h3>
                <p style="font-size:15px" id="viewDescricao"></p>
            </div>

            <div id="viewObservacaoContainer" class="mb-3">
                <h3 style="font-size:18px">Observação</h3>
                <p style="font-size:15px" id="viewObservacao"></p>
            </div>
        </div>
        
                    <div class="anexo-container">
                <h3>Anexos</h3>
                <div id="viewAnexoContainer" class="view-anexo-container">
                    <!-- Os anexos serão inseridos aqui pelo JavaScript -->
                </div>
            </div>


        <div class="d-flex justify-content-end mt-4"> <!-- Adiciona margem superior e alinha à direita -->
            <div class="form-row">
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
    </div>

 </div>


<!-- Modal de Edição de OS -->
        <div id="editOsModal" class="modal" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 90%;">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h2 class="modal-title" id="modalCadastroOsLabel"><i class="mdi mdi-pencil"></i>&nbsp;Editar BI </h2>
                <span class="close" onclick="closeModal('editOsModal')">&times;</span>
            </div>

        <form id="editOsForm" action="process_edit_bi.php" method="post" enctype="multipart/form-data">
            <input type="hidden" id="editValorNumerico" name="valor_numerico">


            <input type="hidden" id="editId" name="os_id">

            <div class="mb-3">
                <label for="editNOs" class="form-label">N° BI</label>
                <input type="text" class="form-control bg-secondary text-white" id="editNOs" name="n_os" readonly class="readonly">
            </div>

            <div class="mb-3">
                <label for="editNomeOs" class="form-label">Nome do Projeto</label>
                <input type="text" class="form-control bg-secondary text-white" id="editNomeOs" name="nome_os" required>
            </div>

               <div class="mb-3">
                    <label for="editApf" class="form-label">Horas</label>
                    <input type="number" class="form-control" id="editApf" name="apf" oninput="calculateValueFromHours(this, 'edit');" placeholder="Informe a quantidade de horas" min="0" step="0.01" value="<?php echo $currentHours; ?>">
                </div>


                <div class="mb-3">
                    <label for="editValor" class="form-label">Valor (R$)</label>
                    <input type="text" class="form-control bg-secondary text-white" id="editValor" name="valor_numerico" readonly value="<?php echo $currentValor; ?>">

                </div>


            <div class="mb-3">
                <label for="editDtInicial" class="form-label">Data Inicial</label>
                <input type="date" class="form-control bg-secondary text-white" id="editDtInicial" name="dt_inicial" required>
            </div>

            <div class="mb-3">
                <label for="editPrazoEntrega" class="form-label">Data de Entrega</label>
                <input type="date" class="form-control bg-secondary text-white" id="editPrazoEntrega" name="prazo_entrega">
            </div>

            <div class="mb-3">
                <label for="editPrioridade" class="form-label">Prioridade</label>
                <select class="form-select" id="editPrioridade" name="prioridade" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Média">Média</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>

            <div class="mb-3">
    <label for="editStatusInova" class="form-label">Status Inova</label>
    <select class="form-select" id="editStatusInova" name="status_inova" required>
        <option value="Em Backlog">Em Backlog</option>
        <option value="Aguardando Horas">Aguardando Horas</option>
        <option value="Paralisado">Paralisado</option>
        <option value="Pendente">Pendente</option>           
        <option value="Em Análise">Em Análise</option>
        <option value="Aprovada">Aprovada</option>
        <option value="Não Aprovada">Não Aprovada</option>        
        <option value="Em Desenvolvimento">Em Desenvolvimento</option>             
        <option value="Finalizado">Finalizado</option>
    </select>
</div>

<div class="mb-3" hidden>
    <label for="editStatusContratada" class="form-label">Status Icon Data</label>
    <select class="form-select"required>
        <option value="Não Começou">Não Começou</option>
        <option value="Paralisado">Paralisado</option>        
        <option value="Em Análise">Em Análise</option>
        <option value="Gerando Horas">Gerando Horas</option>
        <option value="Aprovado Inova">Aprovado Inova</option>        
        <option value="Em Desenvolvimento">Em Desenvolvimento</option>
        <option value="Em Testes">Em Testes</option>
        <option value="Em Homologação">Em Homologação</option>
        <option value="Em Produção">Em Produção</option>
    </select>
</div>



           <div class="mb-3">
    <label for="editResponsaveis" class="form-label">Responsáveis</label>
    <select name="responsavel[]" id="editResponsavel" class="form-select" multiple required>
    <?php foreach ($usuarios as $usuario): ?>
        <option value="<?php echo $usuario['Id']; ?>"><?php echo htmlspecialchars($usuario['Nome']); ?></option>
    <?php endforeach; ?>
</select>
</div>



            <div class="mb-3">
    <label for="editIdContratada" class="form-label">Contratada</label>
    <select class="form-select" id="editIdContratada" name="id_contratada">
        <option value="0">Nenhuma</option>
        <?php foreach ($contratadas as $contratada): ?>
            <option value="<?php echo $contratada['Id']; ?>"><?php echo htmlspecialchars($contratada['Nome']); ?></option>
        <?php endforeach; ?>
    </select>
</div>

         <div class="mb-3">
                <label for="editOsPaga" class="form-label">BI Pago</label>
                <select class="form-select" id="editOsPaga" name="os_paga" required>
                    <option value="0">Não</option>
                    <option value="1">Sim</option>                    
                </select>
         </div>

            <div class="mb-3">
    <label for="editDescricao" class="form-label">Descrição</label>
    <textarea class="form-control bg-secondary text-white" id="editDescricao" name="descricao" style="height: 250px"></textarea>

</div>

            <!-- Anexos Atuais -->
            <div class="mb-3">
                <label for="editAnexoNf" class="form-label">Anexos Atuais</label>
                <div id="currentAttachments">
                    <!-- Anexos existentes serão listados aqui -->
                </div>
                <input class="form-control bg-secondary text-white" type="file" id="editAnexoNf" name="anexo_nf[]" multiple>
            </div>


             <div class="mb-3">
            <label for="editObservacao" class="form-label">Observação</label>
            <textarea class="form-control bg-secondary text-white" id="editObservacao" name="observacao" style="height: 150px"></textarea>

            </div>


        <!-- Botão de submissão -->
        <div class="d-flex justify-content-end mt-4"> <!-- Adiciona margem superior e alinha à direita -->
            <button type="submit" class="btn btn-danger font-weight-medium rounded-pill px-4">
                <div class="d-flex align-items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-send feather-sm fill-white me-2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                    Salvar Alteração
                </div>
            </button>
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
function calculateValueFromHours(input, context) {
    var hours = parseFloat(input.value); // Obtém as horas como um número decimal
    if (!isNaN(hours) && hours >= 0) {
        var value = hours * 218; // Multiplica as horas pelo valor (ajuste conforme necessário)
        var valueField = document.getElementById(context + 'Valor');
        var valueNumericField = document.getElementById(context + 'ValorNumerico');
        valueField.value = value.toFixed(2); // Formata o valor para duas casas decimais
        valueNumericField.value = value; // Armazena o valor numérico para enviar ao servidor
    } else {
        alert("Por favor, insira um número válido de horas.");
        input.value = '';
    }
}
</script>


<script>
function togglePagamento(osId, checkbox) {
    // Determina o novo valor de pagamento baseado no estado do checkbox
    var novoValorPago = checkbox.checked ? 1 : 0;

    // Envia uma requisição AJAX para atualizar o valor no banco de dados
    fetch('update_pagamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `osId=${osId}&os_paga=${novoValorPago}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Erro ao atualizar o status de pagamento.');
            checkbox.checked = !checkbox.checked; // Reverte o estado se houver erro
        }
    })
    .catch(error => {
        console.error('Erro ao enviar requisição:', error);
        checkbox.checked = !checkbox.checked; // Reverte o estado se houver erro
    });
}
</script>





    <script> 
function togglePriority(checkbox, osId) {
    var row = checkbox.closest('tr'); // Encontra a linha da tabela mais próxima do checkbox
    if (checkbox.checked) {
        row.classList.add('highlighted');
        localStorage.setItem('highlightedRow' + osId, 'true');
    } else {
        row.classList.remove('highlighted');
        localStorage.removeItem('highlightedRow' + osId);
    }
    sortHighlightedRows(); // Reordenar as linhas após a mudança do estado do checkbox
}

function sortHighlightedRows() {
    var table = document.getElementById('tablesaw-6204');
    var tbody = table.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr'));

    // Ordena as linhas destacadas por prioridade e depois por número da OS
    rows.sort(function(a, b) {
        var highlightedA = a.classList.contains('highlighted');
        var highlightedB = b.classList.contains('highlighted');
        if (highlightedA && !highlightedB) {
            return -1; // A antes de B
        } else if (!highlightedA && highlightedB) {
            return 1; // B antes de A
        } else if (highlightedA && highlightedB) {
            // Ordena por prioridade e depois por número da OS dentro das linhas destacadas
            var priorityOrder = { 'Alta': 1, 'Média': 2, 'Baixa': 3 };
            var priorityA = priorityOrder[a.cells[6].textContent.trim()] || 4; // Ajuste o índice para a célula de prioridade
            var priorityB = priorityOrder[b.cells[6].textContent.trim()] || 4; // Ajuste o índice para a célula de prioridade
            if (priorityA !== priorityB) {
                return priorityA - priorityB;
            }

            // Se as prioridades são iguais, compara pelo número da OS
            var osNumberA = parseInt(a.cells[1].textContent.trim().split('-')[1], 10);
            var osNumberB = parseInt(b.cells[1].textContent.trim().split('-')[1], 10);
            return osNumberA - osNumberB;
        }
        return 0; // Se não estiverem destacados, mantém a ordem atual
    });

    // Reanexa as linhas no corpo da tabela na ordem correta
    rows.forEach(function(row) {
        tbody.appendChild(row);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.star-rating .star').forEach(star => {
        star.addEventListener('click', function () {
            const osId = this.parentElement.getAttribute('data-os-id');
            const rating = parseInt(this.getAttribute('data-value'), 10);
            const stars = this.parentElement.querySelectorAll('.star');

            // Verificar se o clique foi em uma estrela preenchida e se é a última preenchida
            if (this.classList.contains('filled') && rating === [...stars].filter(s => s.classList.contains('filled')).length) {
                // Se for, desmarcar todas as estrelas posteriores e esta
                stars.forEach(s => {
                    if (parseInt(s.getAttribute('data-value'), 10) >= rating) {
                        s.classList.remove('filled');
                    }
                });
                // Atualizar o banco de dados com zero estrelas se nenhuma estiver marcada
                updateAndamento(osId, rating - 1);
            } else {
                // Marcar todas as estrelas até a clicada como preenchidas
                stars.forEach(s => s.classList.remove('filled'));
                for (let i = 0; i < rating; i++) {
                    stars[i].classList.add('filled');
                }
                // Enviar o novo valor para o servidor
                updateAndamento(osId, rating);
            }
        });
    });
});

// Função para enviar a atualização do andamento para o servidor
function updateAndamento(osId, rating) {
    fetch('update_andamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `osId=${osId}&andamento=${rating}`
    })
    .then(response => response.text())
    .then(data => {
        console.log('Andamento atualizado com sucesso:', data);
    })
    .catch(error => {
        console.error('Erro ao atualizar o andamento:', error);
    });
}





document.addEventListener('DOMContentLoaded', function() {
    var checkboxes = document.querySelectorAll('.priority-check');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            togglePriority(this, this.dataset.osId);
        });

        // Restaura o estado dos checkboxes
        var osId = checkbox.dataset.osId;
        if (localStorage.getItem('highlightedRow' + osId) === 'true') {
            checkbox.checked = true;
            checkbox.closest('tr').classList.add('highlighted');
        }
    });

    // Ordena as linhas assim que a página for carregada
    sortHighlightedRows();
});

function togglePriority(checkbox, osId) {
    var row = checkbox.closest('tr');
    if (checkbox.checked) {
        row.classList.add('highlighted');
        localStorage.setItem('highlightedRow' + osId, 'true');
    } else {
        row.classList.remove('highlighted');
        localStorage.removeItem('highlightedRow' + osId);
    }
    sortHighlightedRows();
}


</script>

<script> 

function sortTableByCheckboxes() {
    var rows = $('.content-table tbody tr').get();

    rows.sort(function(a, b) {
        var keyA = $(a).find('.priority-check').is(':checked');
        var keyB = $(b).find('.priority-check').is(':checked');
        if (keyA && !keyB) return -1;
        if (!keyA && keyB) return 1;
        return 0;
    });

    $.each(rows, function(index, row) {
        $('.content-table').children('tbody').append(row);
    });
}

// Chame essa função sempre que precisar ordenar a tabela após a interação do usuário
sortTableByCheckboxes();


</script>

<script>
// Função para definir a data de hoje como valor padrão para o campo "Data Inicial"
function definirDataInicialHoje() {
    var dataHoje = new Date();
    var dia = ('0' + dataHoje.getDate()).slice(-2);
    var mes = ('0' + (dataHoje.getMonth() + 1)).slice(-2);
    var ano = dataHoje.getFullYear();
    var dataFormatada = ano + '-' + mes + '-' + dia;

    document.getElementById('addDtInicial').value = dataFormatada;
}

// Chame essa função quando abrir o modal de adicionar nova OS
function openModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        if (modalId === 'addOsModal') {
            definirDataInicialHoje(); // Define a data de hoje para o campo de data inicial
        }
    }
}


// Função para fechar o modal
function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Função para formatar como moeda

function formatarMoeda(valor) {
    return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}


// Função para remover a formatação monetária e retornar um número
function removerFormatacao(valor) {
    return parseFloat(valor.replace(/R\$/g, '').replace(/\./g, '').replace(',', '.'));
}


// Função modificada para calcular o valor automaticamente e atualizar o campo oculto
function calculateValue(context = 'add') {
    var apfInput = document.getElementById(context + 'Apf');
    // Chama a função para substituir vírgulas por pontos e formatar o input corretamente
    formatInput(apfInput);

    var valorInput = document.getElementById(context + 'Valor');
    var valorNumericoInput = document.getElementById(context + 'ValorNumerico'); // Campo oculto
    var apfValue = apfInput.value;
    var baseValue = 218;

    if (apfValue) {
        var calculatedValue = parseFloat(apfValue) * baseValue;
        valorInput.value = formatarMoeda(calculatedValue);
        valorNumericoInput.value = removerFormatacao(valorInput.value); // Atualizar o campo oculto com o valor numérico
    } else {
        valorInput.value = '';
        valorNumericoInput.value = ''; // Limpar o campo oculto
    }
}



// Em process_add_os.php e process_edit_os.php
$valor = $_POST['valor_numerico']; // Usar o valor numérico enviado




// Evento de submissão do formulário
document.getElementById('addOsForm').addEventListener('submit', function(event) {
    //event.preventDefault();

    var formData = new FormData(this);
    // Adicione aqui a lógica de envio do formulário
});

function formatInput(input) {
    // Substitui vírgulas por pontos e permite apenas números e ponto
    let value = input.value.replace(/,/g, '.').replace(/[^\d.]/g, '');

    // Evita múltiplos pontos convertendo o valor para um array e juntando novamente com apenas um ponto
    const parts = value.split('.');
    if (parts.length > 2) {
        // Mantém a primeira parte e junta as demais com um ponto, recriando um número decimal válido
        value = parts.shift() + '.' + parts.join('');
    }

    // Atualiza o valor do input
    input.value = value;
}



</script>   

<script>
var oss = <?php echo json_encode($oss); ?>;
</script>  

<script>
var osData = <?php echo json_encode($oss); ?>;
</script>

<script>  
function deleteOs(osId) {
    if (confirm('Tem certeza que deseja excluir este Projeto BI?')) {
        fetch('delete_bi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'osId=' + osId
        })
        .then(response => response.text())
        .then(data => {
            alert(data); // Exibe a resposta do servidor
            location.reload(); // Recarrega a página para atualizar a lista de OS
        })
        .catch(error => {
            console.error('Erro ao excluir o projeto:', error);
        });
    }
}


</script>



<script>
   function formatarData(dataString) {
    var data = new Date(dataString);
    var dia = ('0' + data.getDate()).slice(-2);
    var mes = ('0' + (data.getMonth() + 1)).slice(-2);
    var ano = data.getFullYear();
    return dia + '/' + mes + '/' + ano;
}

function convertLineBreaksToHtml(text) {
    return text.replace(/\n/g, '<br>');
}

function updateModalContent(elementId, content) {
    var element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = content;
    } else {
        console.error(`Elemento '${elementId}' não encontrado.`);
    }
}

function fetchAttachments(osId) {
    return fetch('get_bi_attachments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `os_id=${osId}`
    }).then(response => response.json());
}

function displayAttachments(attachments, container) {
    container.innerHTML = ''; // Limpar o container de anexos
    if (attachments && attachments.length > 0) {
        attachments.forEach(anexo => {
            const anexoItem = document.createElement('div');
            anexoItem.classList.add('anexo-item');

            const anexoLink = document.createElement('a');
            anexoLink.href = anexo.arquivo;
            anexoLink.textContent = anexo.arquivo.split('/').pop();
            anexoLink.target = '_blank';
            anexoItem.appendChild(anexoLink);

            const viewButton = document.createElement('a');
            viewButton.href = anexo.arquivo;
            viewButton.textContent = 'Visualizar';
            viewButton.target = '_blank';
            viewButton.classList.add('view-button');
            anexoItem.appendChild(viewButton);

            container.appendChild(anexoItem);
        });
    } else {
        container.innerHTML = '<p>Nenhum anexo cadastrado.</p>';
    }
}


function viewOsDetails(osId) {
    fetch('get_bi_details.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `osId=${osId}`
    })
    .then(response => response.json())
    .then(data => {
        // Atualize os campos com os dados obtidos
        updateModalContent('viewNOs', data.N_os);
        updateModalContent('viewNomeOs', data.Nome_os);
        updateModalContent('viewApf', formatarTempo(data.Apf));
        updateModalContent('viewValor', formatarMoeda(parseFloat(data.Valor)));
        updateModalContent('viewDtInicial', formatarData(data.Dt_inicial));
        updateModalContent('viewPrazoEntrega', formatarData(data.Prazo_entrega));
        updateModalContent('viewPrioridade', data.Prioridade);
        updateModalContent('viewStatusInova', data.Status_inova);
        updateModalContent('viewStatusContratada', data.Status_contratada);

        // Atualiza os nomes dos responsáveis
        const responsaveis = data.Responsavel.split(',').map(id => {
            return data.NomesResponsaveis[id]; // Supondo que `NomesResponsaveis` é um objeto com IDs como chaves
        }).filter(Boolean).join(', '); // Filtra e junta os nomes com vírgula
        updateModalContent('viewResponsavel', responsaveis);

        updateModalContent('viewIdContratada', data.NomeContratada);
        updateModalContent('viewDescricao', convertLineBreaksToHtml(data.Descricao));
        
        // Verifica se há observações para exibir
        const observacaoContainer = document.getElementById('viewObservacaoContainer');
        if (data.Observacao && data.Observacao.trim() !== '') {
            updateModalContent('viewObservacao', convertLineBreaksToHtml(data.Observacao));
            observacaoContainer.style.display = 'block';
        } else {
            observacaoContainer.style.display = 'none';
        }

        // Verifica se há anexos para exibir
        fetchAttachments(osId).then(attachments => {
            const anexoContainer = document.getElementById('viewAnexoContainer');
            if (attachments.length > 0) {
                displayAttachments(attachments, anexoContainer);
                anexoContainer.parentElement.style.display = 'block';
            } else {
                anexoContainer.parentElement.style.display = 'none';
            }
        });

        // Exibir o modal
        document.getElementById('viewOsModal').style.display = 'block';
    })
    .catch(error => {
        console.error('Erro ao buscar detalhes do projeto BI', error);
    });
}


</script>

<script>
    function atualizarPreviewAnexo(input) {
    var previewDiv = document.getElementById('previewAnexoNf');
    previewDiv.innerHTML = ''; // Limpar conteúdo anterior

    if (input.files && input.files[0]) {
        var reader = new FileReader();
        var fileType = input.files[0].type;

        reader.onload = function(e) {
            var img = new Image();
            img.style.maxWidth = '50px'; // Ajustar tamanho conforme necessário
            img.style.cursor = 'pointer'; // Adicionar o cursor 'pointer'

            if (fileType.startsWith('image/')) {
                img.src = e.target.result;
            } else if (fileType === 'application/pdf') {
                img.src = 'img/pdf.png'; // Ícone de PDF
            } else if (fileType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || fileType === 'application/msword') {
                img.src = 'img/doc.png'; // Ícone de Word
            }

            img.onclick = function() { window.open(img.src, '_blank'); }; // Abrir a pré-visualização em uma nova aba
            previewDiv.appendChild(img);
        };

        reader.readAsDataURL(input.files[0]);
    }
}

</script>


<script>
// Função para abrir o modal de edição
function openEditModal(osData) {
    // Preenchimento dos campos do formulário com os dados da OS
    document.getElementById('editId').value = osData.Id;
    document.getElementById('editNOs').value = osData.N_os;
    document.getElementById('editNomeOs').value = osData.Nome_os;
    document.getElementById('editApf').value = osData.Apf;
    document.getElementById('editValor').value = osData.Valor;
    document.getElementById('editDtInicial').value = formatDate(osData.Dt_inicial);
    document.getElementById('editPrazoEntrega').value = formatDate(osData.Prazo_entrega);
    document.getElementById('editPrioridade').value = osData.Prioridade;
    document.getElementById('editStatusInova').value = osData.Status_inova;
    document.getElementById('editResponsavel').value = osData.Responsavel;
    document.getElementById('editIdContratada').value = osData.Id_contratada;
    document.getElementById('editOsPaga').value = osData.Os_paga.toString();
    document.getElementById('editDescricao').value = osData.Descricao;
    document.getElementById('editObservacao').value = osData.Observacao;

    

    // Abre o modal
    document.getElementById('editOsModal').style.display = 'block';
}

// Função para garantir que o campo esteja visível
function ensureFieldVisibility() {
    const statusContratada = document.getElementById('editStatusContratada');
    if (statusContratada.hidden || statusContratada.style.display === 'none') {
        statusContratada.removeAttribute('required'); // Remove o atributo required se o campo estiver oculto
    } else {
        statusContratada.setAttribute('required', 'true'); // Adiciona required se o campo estiver visível
    }
}

// Chame essa função no momento adequado, como ao abrir o modal
openEditModal(osData);
ensureFieldVisibility();



async function fetchAnexos(osId) {
    const response = await fetch('get_bi_attachments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `os_id=${osId}`
    });
    const anexos = await response.json();

    const attachmentsDiv = document.getElementById('currentAttachments');
    attachmentsDiv.innerHTML = ''; // Limpar a seção de anexos atuais

    if (anexos.length > 0) {
        anexos.forEach(anexo => {
        const container = document.createElement('div');
        container.className = 'attachment-container';

        const link = document.createElement('a');
        link.href = anexo.arquivo;
        link.textContent = anexo.arquivo.split('/').pop();
        link.target = '_blank';
        container.appendChild(link);

        const removeBtn = document.createElement('button');
        removeBtn.textContent = 'Remover';
        removeBtn.className = 'attachment-remove-btn';
        removeBtn.onclick = function() { deleteAnexo(anexo.id, osId); };
        container.appendChild(removeBtn);

        attachmentsDiv.appendChild(container);
    });
    } else {
        attachmentsDiv.innerHTML = 'Nenhum anexo cadastrado.';
    }
}

function deleteAnexo(anexoId, osId) {
    if (!confirm('Tem certeza que deseja remover este anexo?')) return;

    // Alterando para usar FormData e compatibilidade com o PHP $_POST
    let formData = new FormData();
    formData.append('id', anexoId);

    fetch('excluir_anexo_bi.php', {
        method: 'POST',
        body: formData // Enviando como FormData
    })
    .then(response => response.json())
    .then(data => {
    // Ajustando para tratar tanto respostas de sucesso quanto de erro ou aviso da mesma maneira
    let mensagem = data.message || 'Ocorreu um erro inesperado.';
    if(data.success) {
        alert('Sucesso: ' + mensagem);
    } else {
        // Trata erro ou aviso. Ajuste conforme necessário para diferenciar se for desejado.
        alert('Erro ao remover anexo: ' + mensagem);
    }
    fetchAnexos(osId); // Recarrega os anexos independente do resultado, para atualizar a lista de anexos.
})
.catch(error => console.error('Erro ao excluir anexo:', error));

}


function formatDate(dateString) {
    const date = new Date(dateString);
    const day = ('0' + date.getDate()).slice(-2);
    const month = ('0' + (date.getMonth() + 1)).slice(-2);
    const year = date.getFullYear();
    return `${year}-${month}-${day}`;
}

function formatarTempo(tempo) {
    var totalMinutes;
    if (typeof tempo === 'number') {
        // Se 'tempo' é um número, assume que representa horas como decimal
        totalMinutes = Math.round(tempo * 60);
    } else if (typeof tempo === 'string') {
        // Tenta converter a string em número
        var num = parseFloat(tempo);
        if (!isNaN(num)) {
            totalMinutes = Math.round(num * 60);
        } else {
            // Se não for um número, tenta dividir a string
            var parts = tempo.split(':');
            if (parts.length >= 2) {
                return parts[0] + ':' + parts[1];  // Retorna apenas horas e minutos
            } else {
                return tempo;  // Retorna o tempo original se não estiver no formato esperado
            }
        }
    } else {
        return tempo; // Retorna o tempo original se não for número nem string
    }

    var hours = Math.floor(totalMinutes / 60);
    var minutes = totalMinutes % 60;
    return hours + 'h ' + minutes + 'm';
}



</script>


<script>  
function formatarData(dataString) {
    if (!dataString || dataString === '0000-00-00') {
        return null; // Retorna null para datas inválidas ou '0000-00-00'
    }

    var data = new Date(dataString);
    var dia = ('0' + data.getDate()).slice(-2);
    var mes = ('0' + (data.getMonth() + 1)).slice(-2);
    var ano = data.getFullYear();
    return dia + '/' + mes + '/' + ano;
}

function updateModalContent(elementId, content) {
    var element = document.getElementById(elementId);
    if (element) {
        // Se o conteúdo for null, oculta o elemento
        if (content === null) {
            element.parentElement.style.display = 'none'; // Oculta o elemento pai (assumindo que o elemento está dentro de um div ou span)
        } else {
            element.innerHTML = content;
            element.parentElement.style.display = 'block'; // Mostra o elemento pai
        }
    } else {
        console.error(`Elemento '${elementId}' não encontrado.`);
    }
}
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

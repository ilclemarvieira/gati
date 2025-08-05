<?php

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

// Aqui você faria a consulta ao banco de dados para obter os dados da tabela 'os'
$oss = $pdo->query("SELECT o.*, u.Nome as NomeResponsavel, c.Nome as NomeContratada FROM os o LEFT JOIN usuarios u ON o.Responsavel = u.Id LEFT JOIN contratadas c ON o.Id_contratada = c.Id")->fetchAll(PDO::FETCH_ASSOC);


$whereClauses = [];
$params = [];

if (isset($_GET['responsavel']) && $_GET['responsavel'] != '') {
    $whereClauses[] = 'o.Responsavel = :responsavel';
    $params[':responsavel'] = $_GET['responsavel'];
}

if (isset($_GET['status_contratada']) && $_GET['status_contratada'] !== '') {
    $whereClauses[] = 'o.Status_contratada = :status_contratada';
    $params[':status_contratada'] = $_GET['status_contratada'];
}

if (isset($_GET['os_paga']) && $_GET['os_paga'] !== '') {
    $whereClauses[] = 'o.Os_paga = :os_paga';
    $params[':os_paga'] = $_GET['os_paga'];
}

if (isset($_GET['prioridade']) && $_GET['prioridade'] != '') {
    $whereClauses[] = 'b.Prioridade = :prioridade';
    $params[':prioridade'] = $_GET['prioridade'];
}

if (isset($_GET['numero_os']) && $_GET['numero_os'] !== '') {
    $whereClauses[] = 'o.N_os LIKE :numero_os';
    $params[':numero_os'] = '%' . $_GET['numero_os'] . '%';
}

if (isset($_GET['year']) && $_GET['year'] != '') {
    $whereClauses[] = 'YEAR(o.Dt_inicial) = :year';
    $params[':year'] = $_GET['year'];
}
$whereSql = $whereClauses ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

$query = "
    SELECT o.*, u.Nome as NomeResponsavel, c.Nome as NomeContratada 
    FROM os o
    LEFT JOIN usuarios u ON o.Responsavel = u.Id
    LEFT JOIN contratadas c ON o.Id_contratada = c.Id
    $whereSql
    ORDER BY FIELD(o.Prioridade, 'Alta', 'Média', 'Baixa'), o.N_os ASC
";


// Query to fetch distinct 'status_contratada' values
$statusesQuery = "SELECT DISTINCT Status_contratada FROM os ORDER BY Status_contratada ASC";
$statusesStmt = $pdo->query($statusesQuery);
$statuses = $statusesStmt->fetchAll(PDO::FETCH_COLUMN);

$statement = $pdo->prepare($query);
$statement->execute($params);
$oss = $statement->fetchAll(PDO::FETCH_ASSOC);

// Buscar anos distintos de criação dos oss
$yearsQuery = "SELECT DISTINCT YEAR(Dt_inicial) AS Year FROM os ORDER BY Year DESC";
$yearsStmt = $pdo->query($yearsQuery);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);

// Consulta para buscar todos os usuários
$usuariosStmt = $pdo->query("SELECT Id, Nome FROM usuarios");
$usuarios = $usuariosStmt->fetchAll(PDO::FETCH_ASSOC);

// Consulta para buscar todas as contratadas
$contratadasStmt = $pdo->query("SELECT Id, Nome FROM contratadas");
$contratadas = $contratadasStmt->fetchAll(PDO::FETCH_ASSOC);

function gerarProximoNumeroOS($pdo) {
    $currentYear = date('Y'); // Obtém o ano atual
    $query = "SELECT MAX(CAST(SUBSTRING_INDEX(N_os, '-', 1) AS UNSIGNED)) AS ultimo_numero 
              FROM os 
              WHERE N_os LIKE '%-$currentYear'";
    $stmt = $pdo->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $ultimoNumero = $result['ultimo_numero'] ?? 0;
    $proximoNumero = $ultimoNumero + 1;
    return str_pad($proximoNumero, 2, '0', STR_PAD_LEFT) . '-' . $currentYear;
}

// Exemplo de uso
$proximoNumeroOS = gerarProximoNumeroOS($pdo);

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
  

.filter-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px; /* Espaçamento entre os campos */
    align-items: center;
}

.filter-container select {
    flex: 1; /* Os seletores ocupam o espaço disponível */
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #ccc;
}

.filter-container input[type="text"] {
    width: 30%; /* Adjust this value as needed for desired width */
    margin-top: 10px;
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #ccc;
}


.filter-container button {
    flex-basis: 100%; /* O botão ocupa toda a largura */
    padding: 10px;
    border-radius: 4px;
    border: none;
    background-color: #007bff;
    color: white;
    cursor: pointer;
    margin-top: 10px; /* Espaçamento acima do botão */
}

.filter-container button:hover {
    background-color: #0056b3;
}

/* Estilos responsivos para telas menores */
@media (max-width: 768px) {
    .filter-container input[type="text"] {
        flex-basis: 100%; /* O campo de texto ocupa toda a largura em telas menores */
    }
}

.content-table tbody tr.highlighted,
.content-table tbody tr:nth-of-type(even).highlighted {
    background-color: yellow;
}

/* Estilos para os ícones e espaçamento */
.round-lg {
  width: 50px; /* Ajusta o tamanho da circunferência */
  height: 50px; /* Ajusta o tamanho da circunferência */
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px; /* Espaçamento entre o ícone e o texto */
}
.fa {
  font-size: 24px; /* Reduz o tamanho do ícone */
}
.card-body {
  display: flex;
  align-items: center; /* Centraliza verticalmente o conteúdo */
}
.card-title {
  margin-bottom: 0; /* Remove a margem padrão do título */
  font-size: 24px;
  Font-weight:bold;
}
.card-text {
  margin-top: 4px; /* Espaçamento entre o título e o texto */
  font-size: 21px;
}
.text-muted {
  display: block; /* Faz com que o texto ocupe sua própria linha */
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
            <h3 class="text-themecolor mb-0">Ordens de Serviço</h3>            
          </div>          
        </div>


       
        <div class="container-fluid">  

        <div class="row">
  <!-- Card Total de O.S -->
  <div class="col-lg-4 col-md-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-info">
            <i class="fa fa-clipboard-list"></i>
          </div>
          <div>
            <h3 class="card-title">Total de O.S</h3>
            <p class="card-text text-muted"><?php echo count($oss); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Card Total de P.F -->
  <div class="col-lg-4 col-md-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-warning">
            <i class="fa fa-calculator"></i>
          </div>
          <div>
            <h3 class="card-title">Total de P.F</h3>
            <p class="card-text text-muted"><?php echo array_sum(array_column($oss, 'Apf')); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Card Valor Total -->
  <div class="col-lg-4 col-md-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-row">
          <div class="round round-lg text-white rounded-circle bg-primary">
            <i class="fa fa-money-bill-wave"></i>
          </div>
          <div>
            <h3 class="card-title">Valor Total</h3>
            <p class="card-text text-muted"><?php echo 'R$ ' . number_format(array_sum(array_column($oss, 'Valor')), 2, ',', '.'); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- Botão para abrir o modal de adicionar nova OS -->
        <button class="btn btn-primary" onclick="openModal('addOsModal')">Cadastrar O.S</button>

        <br><br>

        <!-- Filtros para a lista de OS -->
<div class="filter-container">
    <form id="filterOsForm" action="os.php" method="get">
        <!-- Filtro por Ano -->
                <select name="year">
            <option value="">Todos os Anos</option>
            <?php foreach ($years as $year): ?>
                <option value="<?php echo $year; ?>" <?php if (isset($_GET['year']) && $_GET['year'] == $year) echo 'selected'; ?>>
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
        <select name="status_contratada">
    <option value="">Todos os Status</option>
    <?php foreach ($statuses as $status): ?>
        <option value="<?php echo htmlspecialchars($status); ?>" <?php if (isset($_GET['status_contratada']) && $_GET['status_contratada'] == $status) echo 'selected'; ?>>
            <?php echo htmlspecialchars($status); ?>
        </option>
    <?php endforeach; ?>
</select>



        <!-- Filtro por OS Paga -->
        <select name="os_paga">
            <option value="">OS Paga e Não Paga</option>
            <option value="1" <?php if (isset($_GET['os_paga']) && $_GET['os_paga'] == "1") echo 'selected'; ?>>Sim</option>
            <option value="0" <?php if (isset($_GET['os_paga']) && $_GET['os_paga'] == "0") echo 'selected'; ?>>Não</option>
        </select>


        <!-- Input para buscar pelo número da OS -->
       <input type="text" name="numero_os" placeholder="Digite o número da OS" value="<?php echo isset($_GET['numero_os']) ? $_GET['numero_os'] : ''; ?>" />


        <!-- Botão de filtro -->
        <button type="submit">Filtrar</button>
    </form>
</div>

<br>

          <div class="row">

            <div class="table-responsive">

            
            <table class="tablesaw table-striped table-hover table-bordered table no-wrap tablesaw-columntoggle table-responsive" data-tablesaw-mode="columntoggle" id="tablesaw-8258">
            <thead>
                <tr>
                    <th></th>
                    <th>N° OS</th>
                    <th>Nome OS</th>
                    <th>PF</th>
                    <th>Valor</th>                    
                    <th>Prioridade</th>
                    <th>Status Inova</th>
                    <th>Status Contratada</th>
                    <th>Data Inicial</th>
                    <th>Prazo de Entrega</th>
                    <th>Responsável</th>
                    <th>OS Paga</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
    <?php foreach ($oss as $os): ?>
    <tr>
        <td><input type="checkbox" class="priority-check" data-os-id="<?php echo $os['Id']; ?>" onclick="togglePriority(this, <?php echo $os['Id']; ?>)"></td>
        <td><?php echo htmlspecialchars($os['N_os']); ?></td>
        <td><?php echo htmlspecialchars($os['Nome_os']); ?></td>
        <td><?php echo htmlspecialchars($os['Apf']); ?></td>
        <td><?php echo 'R$ ' . htmlspecialchars(number_format($os['Valor'], 2, ',', '.')); ?></td>        
        <td><?php echo htmlspecialchars($os['Prioridade']); ?></td>
        <td><?php echo htmlspecialchars($os['Status_inova']); ?></td>
        <td><?php echo htmlspecialchars($os['Status_contratada']); ?></td>
        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($os['Dt_inicial']))); ?></td>
        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($os['Prazo_entrega']))); ?></td>
        <td><?php echo htmlspecialchars($os['NomeResponsavel']); ?></td> <!-- Exibindo o nome do responsável -->
        <td><?php echo $os['Os_paga'] ? 'Sim' : 'Não'; ?></td>                    
        <td class="action-buttons">
            <button onclick="viewOsDetails(<?php echo $os['Id']; ?>)">Ver</button>
            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($os)); ?>)">Editar</button>
            <button onclick="deleteOs(<?php echo $os['Id']; ?>)">Excluir</button>            
            <button onclick="window.open('gerar_pdf_os.php?osId=<?php echo $os['Id']; ?>', '_blank')">Gerar O.S</button>
        </td>

    </tr>
    <?php endforeach; ?>
</tbody>

        </table>
        </div>

            
          </div> 


          <!-- Modal de Cadastro de OS -->
<div class="modal fade show" id="bs-example-modal-lg" tabindex="-1" aria-labelledby="bs-example-modal-lg" style=aria-modal="true" role="dialog">
                       
    <div class="modal-content">
        <span class="close" onclick="closeModal('addOsModal')">&times;</span>
        <h2>Cadastrar Nova O.S</h2>
        <form id="addOsForm" action="process_add_os.php" method="post" enctype="multipart/form-data">
            <input type="hidden" id="addValorNumerico" name="valor_numerico">


            <!-- Campos para a inserção de dados de uma nova OS -->
            <!-- Número da OS -->
            <div class="form-row">
                <label for="addNOs">N° OS:</label>
                <input type="text" id="addNOs" name="n_os" value="<?php echo $proximoNumeroOS; ?>" readonly>
            </div>
            
            <!-- Nome da OS -->
            <div class="form-row">
                <label for="addNomeOs">Nome da OS:</label>
                <input type="text" id="addNomeOs" name="nome_os" required>
            </div>           
            
            
            <!-- APF -->
            <div class="form-row">
                <label for="addApf">APF:</label>
                <input type="text" id="addApf" name="apf" oninput="calculateValue()">
            </div>
            
            <!-- Valor (calculado automaticamente com base na APF) -->
            <div class="form-row">
                <label for="addValor">Valor:</label>
                <input type="text" id="addValor" name="valor" readonly>
            </div>
            
            <!-- Data Inicial -->
            <div class="form-row">
                <label for="addDtInicial">Data Inicial:</label>
                <input type="date" id="addDtInicial" name="dt_inicial" required>
            </div>
            
            <!-- Prazo de Entrega -->
            <div class="form-row">
                <label for="addPrazoEntrega">Prazo de Entrega:</label>
                <input type="date" id="addPrazoEntrega" name="prazo_entrega" required>
            </div>
            
            <!-- Prioridade -->
            <div class="form-row">
                <label for="addPrioridade">Prioridade:</label>
                <select id="addPrioridade" name="prioridade" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Média">Média</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>

            <div class="form-row">
    <label for="addStatusInova">Status Inova:</label>
    <select id="addStatusInova" name="status_inova" required>
        <option value="Aguardando APF">Aguardando APF</option>
        <option value="Em Análise">Em Análise</option>
        <option value="Não Aprovada">Não Aprovada</option>
        <option value="Aprovada">Aprovada</option>
        <option value="Em Desenvolvimento">Em Desenvolvimento</option>
        <option value="Pendente">Pendente</option>
        <option value="Paralisado">Paralisado</option>
        <option value="Finalizado">Finalizado</option>
    </select>
</div>
            
            <div class="form-row">
    <label for="addStatusContratada">Status Contratada:</label>
    <select id="addStatusContratada" name="status_contratada" required>
        <option value="Não Começou">Não Começou</option>
        <option value="Em Análise">Em Análise</option>
        <option value="Criando APF">Criando APF</option>
        <option value="Paralisado">Paralisado</option>
        <option value="Em Desenvolvimento">Em Desenvolvimento</option>
        <option value="Em Testes">Em Testes</option>
        <option value="Em Homologação">Em Homologação</option>
        <option value="Em Produção">Em Produção</option>
    </select>
</div>

<div class="form-row">
    <label for="addResponsavel">Responsável:</label>
    <select id="addResponsavel" name="responsavel" required>
        <!-- As opções de responsável serão geradas dinamicamente -->
        <?php foreach ($usuarios as $usuario): ?>
            <option value="<?php echo $usuario['Id']; ?>">
                <?php echo htmlspecialchars($usuario['Nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-row">
    <label for="addIdContratada">Contratada:</label>
    <select id="addIdContratada" name="id_contratada" required>
        <!-- As opções de contratadas serão geradas dinamicamente -->
        <?php foreach ($contratadas as $contratada): ?>
            <option value="<?php echo $contratada['Id']; ?>">
                <?php echo htmlspecialchars($contratada['Nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-row">
    <label for="addDescricao">Descrição:</label>
    <textarea id="addDescricao" name="descricao"></textarea>
</div>


<div class="form-row">
    <label for="addOsPaga">OS Paga:</label>
    <select id="addOsPaga" name="os_paga" required>
        <option value="0">Não</option>
        <option value="1">Sim</option>        
    </select>
</div>

<div class="form-row">
    <label for="addAnexoNf">Anexo:</label>
    <input type="file" id="addAnexoNf" name="anexo_nf" onchange="updateAnexoNfPreview(this)">
    <!-- Botão para excluir o anexo existente -->
    <?php if (isset($osItem['Anexo_nf']) && $osItem['Anexo_nf']): ?>
     <button type="button" onclick="deleteOs(<?php echo $os['Id']; ?>)">Excluir OS</button>
    <?php endif; ?>
</div>
            
            <!-- Botão de submissão -->
            <div class="form-row">
                <button type="submit" class="submit-btn">Cadastrar</button>
            </div>
        </form>
    </div>
</div>




<!-- Modal de Visualização de OS -->
<div id="viewOsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('viewOsModal')">&times;</span>
        <h2>Visualizar OS</h2>
        <div class="os-details">
            <!-- Campos para exibir as informações da OS -->
            <!-- Adicione outras classes CSS conforme necessário para estilizar -->
            <p><b>N° OS:</b> <span id="viewNOs"></span></p>
            <p><b>Nome da OS:</b> <span id="viewNomeOs"></span></p>
            <p><b>APF:</b> <span id="viewApf"></span></p>
            <p><b>Valor:</b> <span id="viewValor"></span></p>
            <p><b>Data Inicial:</b> <span id="viewDtInicial"></span></p>
            <p><b>Prazo de Entrega:</b> <span id="viewPrazoEntrega"></span></p>
            <p><b>Prioridade:</b> <span id="viewPrioridade"></span></p>
            <p><b>Status Inova:</b> <span id="viewStatusInova"></span></p>
            <p><b>Status Contratada:</b> <span id="viewStatusContratada"></span></p>
            <p><b>Responsável:</b> <span id="viewResponsavel"></span></p>
            <p><b>Contratada:</b> <span id="viewIdContratada"></span></p>
            <p><b>Descrição:</b> <span id="viewDescricao"></span></p>
            <p><b>OS Paga:</b> <span id="viewOsPaga"></span></p>
        </div>

        <!-- Container para o anexo -->
        <div class="anexo-container">
            <h3>Anexo</h3>
            <div class="form-row" id="viewAnexoNfContainer"></div>

        </div>
    </div>
</div>




<!-- Modal de Edição de OS -->
<div id="editOsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editOsModal')">&times;</span>
        <h2>Editar O.S</h2>
        <form id="editOsForm" action="process_edit_os.php" method="post" enctype="multipart/form-data">
            <input type="hidden" id="editValorNumerico" name="valor_numerico">


            <input type="hidden" id="editId" name="os_id">

            <div class="form-row">
                <label for="editNOs">N° OS:</label>
                <input type="text" id="editNOs" name="n_os" readonly>
            </div>

            <div class="form-row">
                <label for="editNomeOs">Nome da OS:</label>
                <input type="text" id="editNomeOs" name="nome_os" required>
            </div>

                <div class="form-row">
                    <label for="editApf">APF:</label>
                    <input type="text" id="editApf" name="apf" oninput="calculateValue('edit')">
                 </div>
                <div class="form-row">
                    <label for="editValor">Valor:</label>
                    <input type="text" id="editValor" name="valor" readonly>
                </div>

            <div class="form-row">
                <label for="editDtInicial">Data Inicial:</label>
                <input type="date" id="editDtInicial" name="dt_inicial" required>
            </div>

            <div class="form-row">
                <label for="editPrazoEntrega">Prazo de Entrega:</label>
                <input type="date" id="editPrazoEntrega" name="prazo_entrega" required>
            </div>

            <div class="form-row">
                <label for="editPrioridade">Prioridade:</label>
                <select id="editPrioridade" name="prioridade" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Média">Média</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>

            <div class="form-row">
    <label for="editStatusInova">Status Inova:</label>
    <select id="editStatusInova" name="status_inova" required>
        <option value="Aguardando APF">Aguardando APF</option>
        <option value="Em Análise">Em Análise</option>
        <option value="Não Aprovada">Não Aprovada</option>
        <option value="Aprovada">Aprovada</option>
        <option value="Em Desenvolvimento">Em Desenvolvimento</option>
        <option value="Pendente">Pendente</option>
        <option value="Paralisado">Paralisado</option>
        <option value="Finalizado">Finalizado</option>
    </select>
</div>

<div class="form-row">
    <label for="editStatusContratada">Status Contratada:</label>
    <select id="editStatusContratada" name="status_contratada" required>
        <option value="Não Começou">Não Começou</option>
        <option value="Em Análise">Em Análise</option>
        <option value="Criando APF">Criando APF</option>
        <option value="Paralisado">Paralisado</option>
        <option value="Em Desenvolvimento">Em Desenvolvimento</option>
        <option value="Em Testes">Em Testes</option>
        <option value="Em Homologação">Em Homologação</option>
        <option value="Em Produção">Em Produção</option>
    </select>
</div>


            <div class="form-row">
    <label for="editResponsavel">Responsável:</label>
    <select id="editResponsavel" name="responsavel" required>
        <?php foreach ($usuarios as $usuario): ?>
            <option value="<?php echo $usuario['Id']; ?>"><?php echo htmlspecialchars($usuario['Nome']); ?></option>
        <?php endforeach; ?>
    </select>
</div>


            <div class="form-row">
    <label for="editIdContratada">Contratada:</label>
    <select id="editIdContratada" name="id_contratada" required>
        <?php foreach ($contratadas as $contratada): ?>
            <option value="<?php echo $contratada['Id']; ?>"><?php echo htmlspecialchars($contratada['Nome']); ?></option>
        <?php endforeach; ?>
    </select>
</div>

            <div class="form-row">
    <label for="editDescricao">Descrição:</label>
    <textarea id="editDescricao" name="descricao"></textarea>

</div>



            <div class="form-row">
                <label for="editOsPaga">OS Paga:</label>
                <select id="editOsPaga" name="os_paga" required>
                    <option value="0">Não</option>
                    <option value="1">Sim</option>                    
                </select>
            </div>

            <div class="form-row">
            <label for="editAnexoNf">Anexo:</label>
            <div id="previewAnexoNf"></div> <!-- Elemento para a miniatura -->
            <input type="file" id="editAnexoNf" name="anexo_nf" onchange="atualizarPreviewAnexo(this)">
             </div>


            <button type="submit">Salvar Alterações</button>
        </form>
    </div>
</div>


        </div>  



        <?php include 'footer.php'?>        
      </div>      
    </div>

    <div class="chat-windows"></div>




    <script> 
function togglePriority(checkbox, osId) {
    var row = checkbox.parentElement.parentElement;
    if (checkbox.checked) {
        row.classList.add('highlighted');
        localStorage.setItem('highlightedRow' + osId, true);
    } else {
        row.classList.remove('highlighted');
        localStorage.removeItem('highlightedRow' + osId);
    }
}

// When the page loads
document.addEventListener('DOMContentLoaded', function() {
    var checkboxes = document.querySelectorAll('.priority-check');
    checkboxes.forEach(function(checkbox) {
        var osId = checkbox.getAttribute('data-os-id');
        if (localStorage.getItem('highlightedRow' + osId)) {
            checkbox.checked = true;
            checkbox.parentElement.parentElement.classList.add('highlighted');
        }
    });
});
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
    var valorInput = document.getElementById(context + 'Valor');
    var valorNumericoInput = document.getElementById(context + 'ValorNumerico'); // Campo oculto
    var apfValue = apfInput.value;
    var baseValue = 732.72;

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


</script>   

<script>
var oss = <?php echo json_encode($oss); ?>;
</script>  

<script>
var osData = <?php echo json_encode($oss); ?>;
</script>

<script>  
function deleteOs(osId) {
    if (confirm('Tem certeza que deseja excluir esta OS?')) {
        fetch('delete_os.php', {
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
            console.error('Erro ao excluir a OS:', error);
        });
    }
}


</script>



<script>
    // Função para converter datas de 'YYYY-MM-DD' para 'DD/MM/YYYY'
function formatarData(dataString) {
    var data = new Date(dataString);
    var dia = ('0' + data.getDate()).slice(-2);
    var mes = ('0' + (data.getMonth() + 1)).slice(-2);
    var ano = data.getFullYear();
    return dia + '/' + mes + '/' + ano;
}

// Função auxiliar para converter quebras de linha de \n para <br> para exibição em HTML
function convertLineBreaksToHtml(text) {
    return text.replace(/\n/g, '<br>');
}

   function viewOsDetails(osId) {
    fetch('get_os_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'osId=' + osId
    })
    .then(response => response.json())
    .then(data => {
        // Verificações e preenchimento dos campos do modal
        updateModalContent('viewNOs', data.N_os);
        updateModalContent('viewNomeOs', data.Nome_os);
        updateModalContent('viewApf', data.Apf);
        updateModalContent('viewValor', formatarMoeda(parseFloat(data.Valor)));
        updateModalContent('viewDtInicial', formatarData(data.Dt_inicial));
        updateModalContent('viewPrazoEntrega', formatarData(data.Prazo_entrega));
        updateModalContent('viewPrioridade', data.Prioridade);
        updateModalContent('viewStatusInova', data.Status_inova);
        updateModalContent('viewStatusContratada', data.Status_contratada);
        updateModalContent('viewResponsavel', data.NomeResponsavel);
        updateModalContent('viewIdContratada', data.NomeContratada);
        updateModalContent('viewDescricao', convertLineBreaksToHtml(data.Descricao));
        updateModalContent('viewOsPaga', data.Os_paga ? 'Sim' : 'Não');
        updateModalContent('viewAnexoNf', data.Anexo_nf);

       // Obtém a seção do anexo e o container
        var anexoSection = document.querySelector('.anexo-container');
        var anexoContainer = document.getElementById('viewAnexoNfContainer');

        // Verifica se o anexo existe e atualiza a visibilidade da seção
        if (data.Anexo_nf && anexoContainer) {
            anexoContainer.innerHTML = ''; // Limpa o container do anexo
            anexoSection.style.display = 'block'; // Exibe a seção do anexo

            var anexoElement;

            if (data.Anexo_nf.endsWith('.pdf')) {
                // Se for um PDF, cria um iframe
                anexoElement = document.createElement('iframe');
                anexoElement.src = data.Anexo_nf;
                anexoElement.style.width = '100%';
                anexoElement.style.height = '500px';
            } else {
                // Para outros tipos de arquivo (imagens, etc.)
                anexoElement = new Image();
                anexoElement.src = data.Anexo_nf;
                anexoElement.style.maxWidth = '100%';
                anexoElement.style.height = 'auto';
            }

            anexoContainer.appendChild(anexoElement);
        } else {
            // Oculta a seção do anexo se não houver anexo ou se o container não for encontrado
            anexoSection.style.display = 'none';
        }

        // Exibir o modal
        document.getElementById('viewOsModal').style.display = 'block';
    })
    .catch(error => {
        console.error('Erro:', error);
    });
}

function updateModalContent(elementId, content) {
    var element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = content; // Atualiza o conteúdo como HTML
    } else {
        console.error(`Elemento '${elementId}' não encontrado.`);
    }
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
function openEditModal(osData) {
    // Preencha os campos do formulário com os dados da OS
    document.getElementById('editId').value = osData.Id;
    document.getElementById('editNOs').value = osData.N_os;
    document.getElementById('editNomeOs').value = osData.Nome_os;
    document.getElementById('editApf').value = osData.Apf;
    document.getElementById('editValor').value = osData.Valor;
    document.getElementById('editDtInicial').value = formatDate(osData.Dt_inicial);
    document.getElementById('editPrazoEntrega').value = formatDate(osData.Prazo_entrega);
    document.getElementById('editPrioridade').value = osData.Prioridade;
    document.getElementById('editStatusInova').value = osData.Status_inova;
    document.getElementById('editStatusContratada').value = osData.Status_contratada;
    document.getElementById('editResponsavel').value = osData.Responsavel;
    document.getElementById('editIdContratada').value = osData.Id_contratada;
    document.getElementById('editOsPaga').value = osData.Os_paga.toString();    
    document.getElementById('editDescricao').value = osData.Descricao;



     // Configura a miniatura do anexo, se houver
    var anexoPreviewDiv = document.getElementById('previewAnexoNf');
    anexoPreviewDiv.innerHTML = ''; // Limpar a miniatura anterior

    if (osData.Anexo_nf) {
        var img = new Image();
        img.style.maxWidth = '50px'; // Ajustar tamanho conforme necessário
        img.style.cursor = 'pointer'; // Adicionar o cursor 'pointer'

        // Verificar a extensão do arquivo para determinar a miniatura
        if (osData.Anexo_nf.endsWith('.pdf') || osData.Anexo_nf.endsWith('.doc') || osData.Anexo_nf.endsWith('.docx')) {
            img.src = osData.Anexo_nf.endsWith('.pdf') ? 'img/pdf.png' : 'img/doc.png'; // Ícone de PDF ou Word
            img.onclick = function() { window.open(osData.Anexo_nf, '_blank'); }; // Abrir o arquivo em uma nova aba
        } else {
            img.src = osData.Anexo_nf; // Caminho do anexo (para imagens)
            img.onclick = function() { window.open(img.src, '_blank'); }; // Abrir a imagem em uma nova aba
        }
        
        anexoPreviewDiv.appendChild(img);
    }

    // Abre o modal
    document.getElementById('editOsModal').style.display = 'block';
    calculateValue('edit'); // Recalcular o valor quando o modal é aberto
}

// Função auxiliar para formatar datas no formato apropriado
function formatDate(dateString) {
    var date = new Date(dateString);
    var day = ('0' + date.getDate()).slice(-2);
    var month = ('0' + (date.getMonth() + 1)).slice(-2);
    var year = date.getFullYear();
    return year + '-' + month + '-' + day; // Formato YYYY-MM-DD
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

<?php
// os.php
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
    $whereClauses[] = 'b.Responsavel = :responsavel';
    $params[':responsavel'] = $_GET['responsavel'];
}

if (isset($_GET['status']) && $_GET['status'] != '') {
    $whereClauses[] = 'b.Status_inova = :status';
    $params[':status'] = $_GET['status'];
}

if (isset($_GET['prioridade']) && $_GET['prioridade'] != '') {
    $whereClauses[] = 'b.Prioridade = :prioridade';
    $params[':prioridade'] = $_GET['prioridade'];
}

if (isset($_GET['encaminhado_os']) && $_GET['encaminhado_os'] != '') {
    $whereClauses[] = 'b.Encaminhado_os = :encaminhado_os';
    $params[':encaminhado_os'] = $_GET['encaminhado_os'];
}

if (isset($_GET['year']) && $_GET['year'] != '') {
    $whereClauses[] = 'YEAR(b.Dt_inicial) = :year';
    $params[':year'] = $_GET['year'];
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$query = "
    SELECT b.*, u.Nome as NomeResponsavel 
    FROM os b
    LEFT JOIN usuarios u ON b.Responsavel = u.Id
    $whereSql
";

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





?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>iNOVA ERP - OS</title>
    <link rel="stylesheet" href="style.css">
    <!-- Inclua aqui outros arquivos de estilo ou scripts necessários -->
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Ordens de Serviço (OS)</h1>

                <!-- Botão para abrir o modal de adicionar nova OS -->
        <button onclick="openModal('addOsModal')">Cadastrar O.S</button>

        <table class="content-table">
            <thead>
                <tr>
                    <th>N° OS</th>
                    <th>Nome OS</th>
                    <th>APF</th>
                    <th>Valor</th>
                    <th>Data Inicial</th>
                    <th>Prazo de Entrega</th>
                    <th>Prioridade</th>
                    <th>Status Inova</th>
                    <th>Status Contratada</th>
                    <th>Responsável</th>
                    <th>OS Paga</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
    <?php foreach ($oss as $os): ?>
    <tr>
        <td><?php echo htmlspecialchars($os['N_os']); ?></td>
        <td><?php echo htmlspecialchars($os['Nome_os']); ?></td>
        <td><?php echo htmlspecialchars($os['Apf']); ?></td>
        <td><?php echo htmlspecialchars(number_format($os['Valor'], 2, ',', '.')); ?></td>
        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($os['Dt_inicial']))); ?></td>
        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($os['Prazo_entrega']))); ?></td>
        <td><?php echo htmlspecialchars($os['Prioridade']); ?></td>
        <td><?php echo htmlspecialchars($os['Status_inova']); ?></td>
        <td><?php echo htmlspecialchars($os['Status_contratada']); ?></td>
        <td><?php echo htmlspecialchars($os['NomeResponsavel']); ?></td> <!-- Exibindo o nome do responsável -->
        <td><?php echo $os['Os_paga'] ? 'Sim' : 'Não'; ?></td>                    
        <td class="action-buttons">
            <button onclick="viewOsDetails(<?php echo $os['Id']; ?>)">Visualizar</button>
            <button onclick="viewOsDetails(<?php echo $os['Id']; ?>)">Editar</button>
            <button onclick="viewOsDetails(<?php echo $os['Id']; ?>)">Excluir</button>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>

        </table>
    </div>

<!-- Modal de Cadastro de OS -->
<div id="addOsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addOsModal')">&times;</span>
        <h2>Cadastrar Nova O.S</h2>
        <form id="addOsForm" action="process_add_os.php" method="post" enctype="multipart/form-data">
            <!-- Campos para a inserção de dados de uma nova OS -->
            <!-- Número da OS -->
            <div class="form-row">
                <label for="addNOs">N° OS:</label>
                <input type="text" id="addNOs" name="n_os" required>
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
    <label for="editStatusInova">Status Inova:</label>
    <select id="editStatusInova" name="status_inova" required>
        <!-- Adicione as opções de status da contratada aqui -->
        <option value="Status1">Status1</option>
        <option value="Status2">Status2</option>
        <!-- etc... -->
    </select>
</div>
            
            <div class="form-row">
    <label for="editStatusContratada">Status Contratada:</label>
    <select id="editStatusContratada" name="status_contratada" required>
        <!-- Adicione as opções de status da contratada aqui -->
        <option value="Status1">Status1</option>
        <option value="Status2">Status2</option>
        <!-- etc... -->
    </select>
</div>

<div class="form-row">
    <label for="editResponsavel">Responsável:</label>
    <select id="editResponsavel" name="responsavel" required>
        <!-- As opções de responsável serão geradas dinamicamente -->
        <?php foreach ($usuarios as $usuario): ?>
            <option value="<?php echo $usuario['Id']; ?>">
                <?php echo htmlspecialchars($usuario['Nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-row">
    <label for="editIdContratada">Contratada:</label>
    <select id="editIdContratada" name="id_contratada" required>
        <!-- As opções de contratadas serão geradas dinamicamente -->
        <?php foreach ($contratadas as $contratada): ?>
            <option value="<?php echo $contratada['Id']; ?>">
                <?php echo htmlspecialchars($contratada['Nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-row">
    <label for="editOsPaga">OS Paga:</label>
    <select id="editOsPaga" name="os_paga" required>
        <option value="1">Sim</option>
        <option value="0">Não</option>
    </select>
</div>

<div class="form-row">
    <label for="editAnexoNf">Anexo NF:</label>
    <input type="file" id="editAnexoNf" name="anexo_nf" onchange="updateAnexoNfPreview(this)">
    <!-- Botão para excluir o anexo existente -->
    <?php if (isset($osItem['Anexo_nf']) && $osItem['Anexo_nf']): ?>
        <button type="button" onclick="deleteAnexoNf(<?php echo $osItem['Id']; ?>)">Excluir Anexo</button>
    <?php endif; ?>
</div>
            
            <!-- Botão de submissão -->
            <div class="form-row">
                <button type="submit" class="submit-btn">Cadastrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Visualização -->
<div id="viewModal" class="modal">
  <div class="modal-content">
    
  </div>
</div>



<!-- Modal de Edição -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editModal')">&times;</span>
        <h2>Editar O.S</h2>
       
    </div>
</div>

<script>
// Função para abrir o modal
function openModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

// Função para fechar o modal
function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Função para calcular o valor automaticamente
function calculateValue() {
    var apfInput = document.getElementById('addApf');
    var valorInput = document.getElementById('addValor');
    var apfValue = apfInput.value;

    // Aqui você deve substituir pela lógica de cálculo correta
    // Exemplo: se APF é um multiplicador e o valor padrão é 700,5
    var baseValue = 700.5; // Substitua pelo valor base adequado
    if (apfValue) {
        // Aqui usamos a função parseFloat para converter a string para um número
        // e garantir que a operação de multiplicação funcione corretamente.
        var calculatedValue = parseFloat(apfValue) * baseValue;
        valorInput.value = calculatedValue.toFixed(2); // Fixa duas casas decimais
    } else {
        valorInput.value = ''; // Limpa o campo de valor
    }
}

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

</body>
</html>



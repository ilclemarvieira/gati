<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
include 'db.php';

$backlogs = $pdo->query("
    SELECT b.*, u.Nome as NomeResponsavel 
    FROM backlog b
    LEFT JOIN usuarios u ON b.Responsavel = u.Id
")->fetchAll(PDO::FETCH_ASSOC);


// Adicione no início do arquivo para buscar os usuários cadastrados
$usuarios = $pdo->query("SELECT Id, Nome FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
$statusOptions = ["Não Começou", "Em análise", "Paralisada", "Em criação", "Cancelada", "Autorizada para O.S"];

$whereClauses = [];
$params = [];

if (isset($_GET['responsavel']) && $_GET['responsavel'] != '') {
    $whereClauses[] = 'b.Responsavel = :responsavel';
    $params[':responsavel'] = $_GET['responsavel'];
}

if (isset($_GET['status']) && $_GET['status'] != '') {
    $whereClauses[] = 'b.Status_ideia = :status';
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
    $whereClauses[] = 'YEAR(b.Dt_criacao) = :year';
    $params[':year'] = $_GET['year'];
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$query = "
    SELECT b.*, u.Nome as NomeResponsavel 
    FROM backlog b
    LEFT JOIN usuarios u ON b.Responsavel = u.Id
    $whereSql
";

$statement = $pdo->prepare($query);
$statement->execute($params);
$backlogs = $statement->fetchAll(PDO::FETCH_ASSOC);

// Buscar anos distintos de criação dos backlogs
$yearsQuery = "SELECT DISTINCT YEAR(Dt_criacao) AS Year FROM backlog ORDER BY Year DESC";
$yearsStmt = $pdo->query($yearsQuery);
$years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);



?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>iNOVA ERP - Backlog</title>
    <link rel="stylesheet" href="style.css">

    <style>
    .modal-content {
        /* Estilos para o conteúdo do modal */
    }

    .os-details p {
        /* Estilos para os parágrafos dos detalhes da OS */
    }

    .anexo-container {
        margin-top: 20px;
        text-align: center; /* Centralizar o anexo */
    }

    .anexo-container img, .anexo-container iframe {
        max-width: 100%; /* Garantir que o anexo não ultrapasse a largura do modal */
        height: auto;
    }
    .card-container {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 20px;
}

.card {
    border: 1px solid #ccc;
    padding: 20px;
    text-align: center;
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

</style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Backlog</h1>


        <div class="filter-cards">
    <div class="card" onclick="filterBacklogs('')">
        <p>Total de Backlogs</p>
        <p id="totalBacklogs">0</p>
    </div>
    <div class="card" onclick="filterBacklogs('Alta')">
        <p>Prioridade Alta</p>
        <p id="prioridadeAlta">0</p>
    </div>
    <div class="card" onclick="filterBacklogs('Média')">
        <p>Prioridade Média</p>
        <p id="prioridadeMedia">0</p>
    </div>
    <div class="card" onclick="filterBacklogs('Baixa')">
        <p>Prioridade Baixa</p>
        <p id="prioridadeBaixa">0</p>
    </div>
</div>

<button onclick="openModal('addModal')">Adicionar ao Backlog</button>
        <br><br>


        <div class="filter-container">
    <form action="backlog.php" method="GET">

        <select id="filterYear" name="year">
    <option value="">Todos os Anos</option>
    <?php foreach ($years as $year): ?>
        <option value="<?php echo $year; ?>" <?php if (isset($_GET['year']) && $_GET['year'] == $year) echo 'selected'; ?>>
            <?php echo $year; ?>
        </option>
    <?php endforeach; ?>
</select>


        <select id="filterResponsavel" name="responsavel">
    <option value="">Todos os Responsáveis</option>
    <?php foreach ($usuarios as $usuario): ?>
        <option value="<?php echo $usuario['Id']; ?>" <?php if (isset($_GET['responsavel']) && $_GET['responsavel'] == $usuario['Id']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($usuario['Nome']); ?>
        </option>
    <?php endforeach; ?>
</select>


        <select id="filterStatus" name="status">
    <option value="">Todos os Status</option>
    <?php foreach ($statusOptions as $status): ?>
        <option value="<?php echo $status; ?>" <?php if (isset($_GET['status']) && $_GET['status'] == $status) echo 'selected'; ?>>
            <?php echo $status; ?>
        </option>
    <?php endforeach; ?>
</select>


        <select id="filterPrioridade" name="prioridade">
    <option value="">Todas as Prioridades</option>
    <option value="Baixa" <?php if (isset($_GET['prioridade']) && $_GET['prioridade'] == 'Baixa') echo 'selected'; ?>>Baixa</option>
    <option value="Média" <?php if (isset($_GET['prioridade']) && $_GET['prioridade'] == 'Média') echo 'selected'; ?>>Média</option>
    <option value="Alta" <?php if (isset($_GET['prioridade']) && $_GET['prioridade'] == 'Alta') echo 'selected'; ?>>Alta</option>
</select>


        <select id="filterEncaminhadoOs" name="encaminhado_os">
    <option value="">Todos Encaminhado para OS</option>
    <option value="1" <?php if (isset($_GET['encaminhado_os']) && $_GET['encaminhado_os'] == "1") echo 'selected'; ?>>Sim</option>
    <option value="0" <?php if (isset($_GET['encaminhado_os']) && $_GET['encaminhado_os'] == "0") echo 'selected'; ?>>Não</option>
</select>


        <button type="submit">Filtrar</button>
    </form>
</div>



        <table class="content-table">
            <thead>
                <tr>
                    <th>Projeto</th>
                    <th>Data de Criação</th>
                    <th>Prioridade</th>
                    <th>Status</th>
                    <th>Responsável</th>
                    <th>Encaminhado para OS</th>
                    <th></th>
                </tr>
            </thead>
                            <tbody>
                  <?php foreach ($backlogs as $backlog): ?>
                  <tr>
                    <td data-label="Projeto"><?php echo htmlspecialchars($backlog['Projeto']); ?></td>
                    <td data-label="Data de Criação"><?php echo date('d/m/Y', strtotime($backlog['Dt_criacao'])); ?></td>
                    <td data-label="Prioridade"><?php echo htmlspecialchars($backlog['Prioridade']); ?></td>
                    <td data-label="Status"><?php echo htmlspecialchars($backlog['Status_ideia']); ?></td>
                    <td data-label="Responsável"><?php echo htmlspecialchars($backlog['NomeResponsavel'] ?? 'N/A'); ?></td>
                    <td data-label="Encaminhado para OS"><?php echo $backlog['Encaminhado_os'] ? 'Sim' : 'Não'; ?></td>
                    <td data-label="Ações" class="action-buttons">
                      <button onclick="viewBacklogDetails(<?php echo $backlog['Id']; ?>)">Visualizar</button>
                      <button onclick="loadBacklogDetails(<?php echo $backlog['Id']; ?>)">Editar</button>
                      <button onclick="deleteBacklog(<?php echo $backlog['Id']; ?>)">Excluir</button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>

        </table>
    </div>

    <!-- Modal de Cadastro -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addModal')">&times;</span>
        <h2>Adicionar ao Backlog</h2>
            <form id="addBacklogForm" action="seu_script.php" method="post" enctype="multipart/form-data" onsubmit="event.preventDefault(); submitAddForm();">
            <div class="form-row">
                <label for="addProjeto">Projeto:</label>
                <input type="text" id="addProjeto" name="projeto" required>
            </div>
            <div class="form-row">
                <label for="addDtCriacao">Data de Criação:</label>
                <input type="date" id="addDtCriacao" name="dt_criacao" required>
            </div>
            <div class="form-row">
                <label for="addPrioridade">Prioridade:</label>
                <select id="addPrioridade" name="prioridade" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Média">Média</option>
                    <option value="Alta">Alta</option>
                </select>
            </div>
            <div class="form-row">
        <label for="addStatusIdeia">Status:</label>
        <select id="addStatusIdeia" name="status_ideia" required>
            <?php foreach ($statusOptions as $status): ?>
                <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
            <div class="form-row">
        <label for="addResponsavel">Responsável:</label>
        <select id="addResponsavel" name="responsavel" required>
            <?php foreach ($usuarios as $usuario): ?>
                <option value="<?php echo $usuario['Id']; ?>"><?php echo htmlspecialchars($usuario['Nome']); ?></option>
            <?php endforeach; ?>
            </select>
        </div>
            <div class="form-row">
                <label for="addEncaminhadoOs">Encaminhado para OS:</label>
                <input type="checkbox" id="addEncaminhadoOs" name="encaminhado_os">
            </div>
            <div class="form-row">
                <label for="addDescricao">Descrição:</label>
                <textarea id="addDescricao" name="descricao"></textarea>
            </div>
            <div class="form-row">
    <label for="addAttachment">Anexar Documento:</label>
    <div id="addAttachmentPreview" style="margin-bottom: 15px;"></div>
    <input type="file" id="addAttachment" name="anexo" onchange="updateAddAttachmentPreview(this)">
</div>

            <div class="form-row">
                <button type="submit" class="submit-btn">Adicionar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Visualização -->
<div id="viewModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('viewModal')">&times;</span>
    <h2>Visualizar Item do Backlog</h2>
    <div class="view-container">
      <div class="view-row"><strong>Projeto:</strong><p id="viewProjeto"></p></div>
      <div class="view-row"><strong>Data de Criação:</strong><p id="viewDtCriacao"></p></div>
      <div class="view-row"><strong>Prioridade:</strong><p id="viewPrioridade"></p></div>
      <div class="view-row"><strong>Status:</strong><p id="viewStatusIdeia"></p></div>
      <div class="view-row"><strong>Responsável:</strong><p id="viewResponsavel"></p></div>
      <div class="view-row"><strong>Encaminhado para OS:</strong><p id="viewEncaminhadoOs"></p></div>
      <div class="view-row"><strong>Descrição:</strong><p id="viewDescricao" class="view-descricao"></p></div>
      <div class="view-row">
        <strong>Anexo:</strong>
        <div id="viewAttachmentPreview" class="attachment-preview"></div>
      </div>
    </div>
  </div>
</div>



<!-- Modal de Edição -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editModal')">&times;</span>
        <h2>Editar Item do Backlog</h2>
        <form id="editBacklogForm" action="process_edit_backlog.php" method="post" enctype="multipart/form-data" onsubmit="event.preventDefault(); submitEditForm();">
            <input type="hidden" id="editId" name="id">
            <input type="hidden" id="existingAttachment" name="existingAttachment">
            
            <div class="form-row">
                <label for="editProjeto">Projeto:</label>
                <input type="text" id="editProjeto" name="projeto" required>
            </div>
            <div class="form-row">
                <label for="editDtCriacao">Data de Criação:</label>
                <input type="date" id="editDtCriacao" name="dt_criacao" required>
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
                <label for="editStatusIdeia">Status:</label>
                <select id="editStatusIdeia" name="status_ideia" required>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                    <?php endforeach; ?>
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
                <label for="editEncaminhadoOs">Encaminhado para OS:</label>
                <input type="checkbox" id="editEncaminhadoOs" name="encaminhado_os">
            </div>
            <div class="form-row">
                <label for="editDescricao">Descrição:</label>
                <textarea id="editDescricao" name="descricao"></textarea>
            </div>
            <div class="form-row">
            <label for="editAttachment">Anexo:</label>
            <div id="attachmentPreview" style="margin-bottom: 15px;"></div>
            <!-- Botão para excluir o anexo -->
            <button type="button" id="deleteAttachment" data-backlog-id="<?php echo $backlogItem['Id']; ?>" onclick="deleteAttachment(this.getAttribute('data-backlog-id'))">Excluir Anexo</button>
            <input type="file" id="editAttachment" name="attachment" onchange="updateAttachmentPreview(this)">
            </div>           
            
            <div class="form-row">
                <button type="submit" class="submit-btn">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>



<script>

// Função para atualizar a visualização do anexo
function updateAddAttachmentPreview(input) {
    var previewContainer = document.getElementById('addAttachmentPreview');
    previewContainer.innerHTML = ''; // Limpa a visualização anterior

    if (input.files && input.files[0]) {
        var file = input.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            var fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                var image = new Image();
                image.src = e.target.result;
                image.style.maxWidth = '200px';
                image.style.maxHeight = '200px';
                previewContainer.appendChild(image);
            } else if (fileExtension === 'pdf') {
                var pdfIcon = document.createElement('img');
                pdfIcon.src = 'img/pdf.png'; // Ajuste o caminho conforme necessário
                pdfIcon.style.width = '50px';
                pdfIcon.style.height = '50px';
                previewContainer.appendChild(pdfIcon);
            } else if (['doc', 'docx'].includes(fileExtension)) {
                var docIcon = document.createElement('img');
                docIcon.src = 'img/doc.png'; // Ajuste o caminho conforme necessário
                docIcon.style.width = '50px';
                docIcon.style.height = '50px';
                previewContainer.appendChild(docIcon);
            } else {
                previewContainer.textContent = 'Tipo de arquivo não suportado para visualização';
            }
        };
        
        reader.readAsDataURL(file);
    }
}

// Função para atualizar a visualização do anexo
function updateAttachmentPreview(input) {
    var previewContainer = document.getElementById('attachmentPreview');
    previewContainer.innerHTML = ''; // Limpa a visualização anterior

    if (input.files && input.files[0]) {
        var file = input.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            // Verifica a extensão do arquivo para determinar o tipo de visualização
            var fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                // Se for uma imagem, cria uma visualização em miniatura
                var image = new Image();
                image.src = e.target.result;
                image.style.maxWidth = '200px';
                image.style.maxHeight = '200px';
                previewContainer.appendChild(image);
            } else if (fileExtension === 'pdf') {
                // Se for um PDF, mostra o ícone de PDF
                var pdfIcon = document.createElement('img');
                pdfIcon.src = 'img/pdf-icon.png'; // Ajuste o caminho conforme necessário
                pdfIcon.style.width = '50px';
                pdfIcon.style.height = '50px';
                previewContainer.appendChild(pdfIcon);
            } else if (['doc', 'docx'].includes(fileExtension)) {
                // Se for um documento Word, mostra o ícone de Word
                var docIcon = document.createElement('img');
                docIcon.src = 'img/doc-icon.png'; // Ajuste o caminho conforme necessário
                docIcon.style.width = '50px';
                docIcon.style.height = '50px';
                previewContainer.appendChild(docIcon);
            } else {
                // Para outros tipos de arquivos, exibe um texto padrão ou outro ícone
                previewContainer.textContent = 'Tipo de arquivo não suportado para visualização';
            }
        };
        
        // Lê o arquivo como URL de dados
        reader.readAsDataURL(file);
    }
}


// Certifique-se de que a variável 'backlogs' contém todos os backlogs
var backlogs = <?php echo json_encode($backlogs); ?>;

function loadBacklogDetails(id) {
    // Encontra o item de backlog específico pelo ID
    var backlogItem = backlogs.find(item => item.Id == id);

    if (backlogItem) {
        // Preenche os campos do formulário com os dados do backlog
        document.getElementById('editId').value = backlogItem.Id;
        document.getElementById('editProjeto').value = backlogItem.Projeto;
        document.getElementById('editDtCriacao').value = backlogItem.Dt_criacao;
        document.getElementById('editPrioridade').value = backlogItem.Prioridade;
        document.getElementById('editStatusIdeia').value = backlogItem.Status_ideia;
        document.getElementById('editResponsavel').value = backlogItem.Responsavel;
        document.getElementById('editEncaminhadoOs').checked = backlogItem.Encaminhado_os === '1';
        document.getElementById('editDescricao').value = backlogItem.Descricao;
        document.getElementById('existingAttachment').value = backlogItem.Anexo; // Certifique-se de que este campo exista no seu banco de dados e no objeto backlogItem

         // Mostra a visualização do anexo existente
        displayExistingAttachment(backlogItem.Anexo);

        
        // Configura a visualização do anexo existente
        var attachmentPreview = document.getElementById('attachmentPreview');
        attachmentPreview.innerHTML = ''; // Limpa a visualização anterior

        if (backlogItem.Anexo) {
            // Se houver um anexo, mostra a visualização correspondente
            var attachmentLink = document.createElement('a');
            attachmentLink.href = backlogItem.Anexo;
            attachmentLink.textContent = 'Ver Anexo Existente';
            attachmentLink.target = '_blank';
            
            attachmentPreview.appendChild(attachmentLink);
        }

        // Abre o modal de edição
        openModal('editModal');
    } else {
        alert('Item de backlog não encontrado.');
    }
}

</script>


<script>
function deleteAttachment(backlogId) {
    if (!backlogId) {
        console.error('ID do backlog não fornecido.');
        return;
    }

    if (confirm('Tem certeza que deseja excluir este anexo?')) {
        // Limpe a visualização e o valor do anexo existente
        document.getElementById('attachmentPreview').innerHTML = '';
        document.getElementById('existingAttachment').value = '';

        // Envie uma solicitação ao backend para excluir o arquivo
        fetch('delete_attachment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'backlogId=' + backlogId
        })
        .then(response => response.text())
        .then(data => {
            alert(data); // Exibe a resposta do servidor
        })
        .catch(error => {
            console.error('Erro ao excluir o anexo:', error);
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

    var formElement = document.getElementById('addBacklogForm');
    var formData = new FormData(formElement);
    document.querySelector('#addModal .submit-btn').disabled = true;

    fetch('process_cadastro_backlog.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        location.reload(); // Para atualizar a lista de itens do backlog
    })
    .catch(error => {
        console.error('Erro ao cadastrar o item do backlog:', error);
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
        document.getElementById('editEncaminhadoOs').checked = backlogItem.Encaminhado_os == '1';
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
var backlogs = <?php echo json_encode($backlogs); ?>;
</script>


<script>

    function formatDate(date) {
  var d = new Date(date),
      month = '' + (d.getMonth() + 1),
      day = '' + d.getDate(),
      year = d.getFullYear();

  if (month.length < 2) month = '0' + month;
  if (day.length < 2) day = '0' + day;

  return [day, month, year].join('/');
}

function openModal(modalId) {
  var modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = 'block';
  } else {
    alert('Modal não encontrado: ' + modalId);
  }
}

function closeModal(modalId) {
  var modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = 'none';
  }
}

// Certifique-se de que a variável 'backlogs' contém todos os backlogs
var backlogs = <?php echo json_encode($backlogs); ?>;
    
    // Função para carregar os detalhes no modal de visualização
function viewBacklogDetails(id) {
  const backlogItem = backlogs.find(item => item.Id == id);
  if (backlogItem) {
    document.getElementById('viewProjeto').textContent = backlogItem.Projeto;
    document.getElementById('viewDtCriacao').textContent = formatDate(new Date(backlogItem.Dt_criacao));
    document.getElementById('viewPrioridade').textContent = backlogItem.Prioridade;
    document.getElementById('viewStatusIdeia').textContent = backlogItem.Status_ideia;
    document.getElementById('viewResponsavel').textContent = backlogItem.NomeResponsavel || 'N/A';
    document.getElementById('viewEncaminhadoOs').textContent = backlogItem.Encaminhado_os ? 'Sim' : 'Não';

    // Verifica se há uma descrição antes de adicioná-la ao modal
    if (backlogItem.Descricao && backlogItem.Descricao.trim() !== '') {
      document.getElementById('viewDescricao').parentNode.style.display = 'block';
      document.getElementById('viewDescricao').textContent = backlogItem.Descricao;
    } else {
      document.getElementById('viewDescricao').parentNode.style.display = 'none';
    }

    // Verifica se há um anexo antes de adicioná-lo ao modal
    if (backlogItem.Anexo && backlogItem.Anexo.trim() !== '') {
      document.getElementById('viewAttachmentPreview').parentNode.style.display = 'block';
      displayExistingAttachmentView(backlogItem.Anexo);
    } else {
      document.getElementById('viewAttachmentPreview').parentNode.style.display = 'none';
    }

    openModal('viewModal');
  } else {
    alert('Item de backlog não encontrado.');
  }
}

// Atualize a função displayExistingAttachmentView para lidar com a verificação do anexo
function displayExistingAttachmentView(attachment) {
  const attachmentPreview = document.getElementById('viewAttachmentPreview');
  attachmentPreview.innerHTML = '';
  if (attachment && attachment.trim() !== '') {
    const link = document.createElement('a');
    link.href = attachment; // O caminho para o arquivo
    link.textContent = 'Clique para abrir o anexo';
    link.target = '_blank';
    attachmentPreview.appendChild(link);
  }
}



</script>


<script>
document.addEventListener("DOMContentLoaded", function() {
    // Pega os parâmetros da URL
    var urlParams = new URLSearchParams(window.location.search);
    var filtroPrioridade = urlParams.get('prioridade');

    // Função para contar itens baseado no filtro
    function contarBacklogs(filtro) {
        return backlogs.filter(function(b) {
            // Se não houver filtro, conta todos
            if (!filtro) return true;
            // Caso contrário, conta de acordo com o filtro
            return b.Prioridade === filtro;
        }).length;
    }

    // Atualiza os contadores
    document.getElementById('totalBacklogs').textContent = contarBacklogs();
    document.getElementById('prioridadeAlta').textContent = contarBacklogs('Alta');
    document.getElementById('prioridadeMedia').textContent = contarBacklogs('Média');
    document.getElementById('prioridadeBaixa').textContent = contarBacklogs('Baixa');

    // Aplica a classe 'active' ao card correspondente ao filtro atual
    if (filtroPrioridade) {
        var cards = document.querySelectorAll('.card');
        cards.forEach(function(card) {
            if (card.textContent.includes(filtroPrioridade)) {
                card.classList.add('active');
            }
        });
    }
});

function filterBacklogs(prioridade) {
    // Atualiza a página com o filtro aplicado ou remove o filtro se necessário
    var queryParams = new URLSearchParams(window.location.search);
    if (prioridade) {
        queryParams.set('prioridade', prioridade);
    } else {
        queryParams.delete('prioridade');
    }
    window.location.search = queryParams.toString();
}

</script>
   

</body>
</html>



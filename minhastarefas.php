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


include 'db.php'; // Conexão com o banco de dados

$perfilAcesso = $_SESSION['PerfilAcesso'] ?? null;

function verificarPermissao($perfisPermitidos) {
    global $perfilAcesso;
    if (!in_array($perfilAcesso, $perfisPermitidos)) {
        // Redireciona para a página anterior ou para uma página padrão
        $paginaRedirecionamento = $_SERVER['HTTP_REFERER'] ?? 'minhastarefas.php'; // Define 'index.php' como fallback
        header('Location: ' . $paginaRedirecionamento);
        exit;
    }
}

// No início de cada página restrita, chame verificarPermissao com os perfis permitidos
$perfisPermitidos = [1, 2, 3, 4, 5, 6, 7, 8, 9]; // Exemplo: somente Admin, Gestor e Inova podem acessar
verificarPermissao($perfisPermitidos);



// Verifica se a flag mostrar_mensagem_boas_vindas está definida e é verdadeira
if (isset($_SESSION['mostrar_mensagem_boas_vindas']) && $_SESSION['mostrar_mensagem_boas_vindas']) {
    // Prepara a variável JavaScript para exibir o modal
    $script = "<script>
                 $(document).ready(function(){
                     $('#modalBoasVindas').modal('show');
                 });
               </script>";
    // Limpa a flag para evitar mostrar o modal novamente após o refresh
    unset($_SESSION['mostrar_mensagem_boas_vindas']);
} else {
    // Se a flag não está definida, o script JavaScript está vazio
    $script = "";
}




if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recupere os dados do formulário
    $nome_tarefa = filter_input(INPUT_POST, 'nome_tarefa', FILTER_SANITIZE_STRING);
    $descricao_tarefa = filter_input(INPUT_POST, 'descricao_tarefa', FILTER_SANITIZE_STRING);
    $id_usuario = $_SESSION['usuario_id']; // ID do usuário logado

    // Limita a descrição da tarefa a 500 caracteres, adicionando reticências se for cortado
    $descricao_tarefa = mb_strimwidth($descricao_tarefa, 0, 20000, "…");

    // Prepare o SQL para inserir a tarefa
    $sql = "INSERT INTO tarefas (nome_tarefa, descricao_tarefa, id_usuario) VALUES (:nome_tarefa, :descricao_tarefa, :id_usuario)";

    try {
        // Prepara a declaração para execução
        $stmt = $pdo->prepare($sql);

        // Vincula os parâmetros
        $stmt->bindParam(':nome_tarefa', $nome_tarefa);
        $stmt->bindParam(':descricao_tarefa', $descricao_tarefa);
        $stmt->bindParam(':id_usuario', $id_usuario);

        // Executa a declaração
        $stmt->execute();

        // Redireciona para a mesma página após o processamento do POST
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;

    } catch (PDOException $e) {
        // Em caso de erro na inserção, exibe a mensagem
        echo "Erro ao cadastrar a tarefa: " . $e->getMessage();
    }
}



// Inicialize a variável $tarefas como um array vazio para evitar erros, caso não seja definida posteriormente
$tarefas = [];

$id_usuario = $_SESSION['usuario_id'];

// A query abaixo deveria ser executada em algum lugar do seu script para definir $tarefas
$sql = "SELECT t.id, t.nome_tarefa, t.descricao_tarefa, t.is_important, t.is_complete, t.data_cadastro 
        FROM tarefas AS t
        LEFT JOIN tarefas_compartilhadas AS tc ON t.id = tc.id_tarefa
        WHERE t.id_usuario = :id_usuario OR tc.id_usuario_compartilhado = :id_usuario_compartilhado
        GROUP BY t.id
        ORDER BY t.data_cadastro DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id_usuario', $id_usuario);
$stmt->bindParam(':id_usuario_compartilhado', $id_usuario);
$stmt->execute();
$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agora que $tarefas é sempre um array, você pode usá-lo com array_filter sem problemas
$totalPendentes = count(array_filter($tarefas, function ($tarefa) { return !$tarefa['is_complete']; }));
$totalImportantes = count(array_filter($tarefas, function ($tarefa) { return $tarefa['is_important'] && !$tarefa['is_complete']; }));





// A consulta para calcular o total de tarefas concluídas já está correta
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tarefas WHERE id_usuario = :id_usuario AND is_complete = 1");
$stmt->execute([':id_usuario' => $id_usuario]);
$totalTarefasConcluidas = $stmt->fetchColumn();

// Como $tarefas agora contém todas as tarefas, ajuste a lógica para calcular $totalPendentes
$totalPendentes = count(array_filter($tarefas, function ($tarefa) { return !$tarefa['is_complete']; }));

// O cálculo para o total de tarefas importantes e pendentes permanece o mesmo
$totalImportantes = count(array_filter($tarefas, function ($tarefa) { return $tarefa['is_important'] && !$tarefa['is_complete']; }));


// Busca tarefas pendentes criadas pelo usuário
// Busca tarefas pendentes criadas pelo usuário incluindo informações sobre compartilhamento
$sqlPendentes = "
    SELECT t.id, t.nome_tarefa, t.descricao_tarefa, t.data_cadastro, GROUP_CONCAT(u.nome SEPARATOR ', ') AS usuarios_compartilhados
    FROM tarefas t
    LEFT JOIN tarefas_compartilhadas tc ON t.id = tc.id_tarefa
    LEFT JOIN usuarios u ON tc.id_usuario_compartilhado = u.id
    WHERE t.id_usuario = :id_usuario AND t.is_complete = 0
    GROUP BY t.id
    ORDER BY t.data_cadastro DESC
";
$stmtPendentes = $pdo->prepare($sqlPendentes);
$stmtPendentes->bindParam(':id_usuario', $id_usuario);
$stmtPendentes->execute();
$tarefasPendentes = $stmtPendentes->fetchAll(PDO::FETCH_ASSOC);



// Busca todas as tarefas compartilhadas com o usuário logado que não foram criadas por ele
$sqlTarefasCompartilhadas = "SELECT t.* 
                             FROM tarefas AS t
                             INNER JOIN tarefas_compartilhadas AS tc ON t.id = tc.id_tarefa
                             WHERE tc.id_usuario_compartilhado = :id_usuario_compartilhado
                             AND t.id_usuario != :id_usuario
                             ORDER BY t.data_cadastro DESC";

$stmtTarefasCompartilhadas = $pdo->prepare($sqlTarefasCompartilhadas);
$stmtTarefasCompartilhadas->execute(['id_usuario_compartilhado' => $id_usuario, 'id_usuario' => $id_usuario]);
$tarefasCompartilhadasComUsuario = $stmtTarefasCompartilhadas->fetchAll(PDO::FETCH_ASSOC);



$sqlImportantes = "SELECT * FROM tarefas WHERE id_usuario = :id_usuario AND is_important = 1 AND is_complete = 0 ORDER BY data_cadastro DESC";
$stmtImportantes = $pdo->prepare($sqlImportantes);
$stmtImportantes->bindParam(':id_usuario', $id_usuario);
$stmtImportantes->execute();
$tarefasImportantes = $stmtImportantes->fetchAll(PDO::FETCH_ASSOC);


$sqlConcluidas = "SELECT * FROM tarefas WHERE id_usuario = :id_usuario AND is_complete = 1 ORDER BY data_cadastro DESC";
$stmtConcluidas = $pdo->prepare($sqlConcluidas);
$stmtConcluidas->bindParam(':id_usuario', $id_usuario);
$stmtConcluidas->execute();
$tarefasConcluidas = $stmtConcluidas->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("SELECT Id, Nome FROM usuarios WHERE Id != :userId");
$stmt->bindParam(':userId', $_SESSION['usuario_id']);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$task_id = isset($_POST['task_id']) ? $_POST['task_id'] : 0;

$stmt = $pdo->prepare("SELECT u.Nome FROM tarefas_compartilhadas AS tc JOIN usuarios AS u ON tc.id_usuario_compartilhado = u.Id WHERE tc.id_tarefa = :task_id");
$stmt->execute(['task_id' => $task_id]);
$usuariosCompartilhados = $stmt->fetchAll(PDO::FETCH_ASSOC);



$sql = "SELECT t.id, t.nome_tarefa, t.descricao_tarefa, t.is_important, t.is_complete, t.data_cadastro, u.Nome as nome_usuario
        FROM tarefas AS t
        JOIN usuarios AS u ON t.id_usuario = u.Id
        LEFT JOIN tarefas_compartilhadas AS tc ON t.id = tc.id_tarefa
        WHERE t.id_usuario = :id_usuario OR tc.id_usuario_compartilhado = :id_usuario_compartilhado
        GROUP BY t.id
        ORDER BY t.data_cadastro DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id_usuario', $id_usuario);
$stmt->bindParam(':id_usuario_compartilhado', $id_usuario);
$stmt->execute();
$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);




// Buscar tarefas compartilhadas com o usuário logado que estão pendentes
$sqlCompartilhadas = "SELECT t.id, t.nome_tarefa, t.descricao_tarefa, t.is_important, t.is_complete, t.data_cadastro, u.Nome as nome_usuario
                      FROM tarefas AS t
                      INNER JOIN tarefas_compartilhadas AS tc ON t.id = tc.id_tarefa
                      INNER JOIN usuarios AS u ON t.id_usuario = u.Id
                      WHERE tc.id_usuario_compartilhado = :id_usuario_compartilhado
                      AND t.is_complete = 0
                      ORDER BY t.data_cadastro DESC";
$stmtCompartilhadas = $pdo->prepare($sqlCompartilhadas);
$stmtCompartilhadas->execute(['id_usuario_compartilhado' => $_SESSION['usuario_id']]);
$tarefasCompartilhadas = $stmtCompartilhadas->fetchAll(PDO::FETCH_ASSOC);



// Primeiro, cria um array associativo de tarefas com o ID da tarefa como chave
// Isso facilita a verificação da existência da tarefa pelo ID
$tarefasAssociativas = [];
foreach ($tarefas as $tarefa) {
    $tarefasAssociativas[$tarefa['id']] = $tarefa;
}

// Itera sobre as tarefas compartilhadas
foreach ($tarefasCompartilhadas as $tarefaCompartilhada) {
    $tarefaCompartilhada['is_shared'] = true; // Marca como compartilhada

    // Verifica se a tarefa já está na lista de tarefas pessoais
    if (isset($tarefasAssociativas[$tarefaCompartilhada['id']])) {
        // Atualiza a tarefa pessoal existente para marcar como compartilhada
        $tarefasAssociativas[$tarefaCompartilhada['id']]['is_shared'] = true;
    } else {
        // Se não, adiciona à lista
        $tarefasAssociativas[$tarefaCompartilhada['id']] = $tarefaCompartilhada;
    }
}

$tarefas = array_values($tarefasAssociativas);

$mensagens = [
    "Acredite em si próprio e chegará um dia em que os outros não terão outra escolha senão acreditar com você.",
    "Você é mais forte do que pensa e será mais feliz do que imagina.",
    "Tenha em mente sempre que o seu desejo de sucesso é mais importante do que qualquer coisa.",
    "A motivação é o que te faz começar. O hábito é o que te faz continuar.",
    "O sucesso é ir de fracasso em fracasso sem perder o entusiasmo.",
    "Faça o que você pode, com o que você tem, onde você estiver.",
    "A melhor maneira de prever o futuro é inventá-lo.",
    "A persistência é o caminho do êxito.",
    "O único lugar onde o sucesso vem antes do trabalho é no dicionário.",
    "A vida é 10% o que acontece comigo e 90% de como eu reajo a isso.",
    "Para ter sucesso, sua vontade de sucesso deve ser maior do que seu medo de falhar.",
    "Você não precisa ser ótimo para começar, mas você precisa começar para ser ótimo.",
    "A diferença entre um sonho e um objetivo é um plano.",
    "Seu futuro é criado pelo que você faz hoje, não amanhã.",
    "Não espere por oportunidades extraordinárias. Seize momentos comuns e os faça grandes.",
    "A coragem não é a ausência de medo, mas a convicção de que algo é mais importante do que o medo.",
    "Pequenos progressos cada dia somam grandes resultados.",
    "O sucesso não consiste em não errar, mas não cometer os mesmos equívocos mais de uma vez.",
    "Não é sobre ter todas as pessoas ao seu lado, mas sim sobre ter a direção e a coragem de seguir sozinho se necessário.",
    "O amanhã é construído pelos sonhos que perseguimos hoje."
];

date_default_timezone_set('America/Sao_Paulo'); // Configura o fuso horário para Brasília

$diaDoAno = date('z'); // Obtém o número do dia do ano (0 a 365)

$indexMensagem = $diaDoAno % count($mensagens); // Calcula o índice da mensagem com base no dia do ano

$mensagemDoDia = $mensagens[$indexMensagem]; // Seleciona a mensagem do dia



// Pega o dia da semana e a semana do ano
$diaDaSemana = date('w'); // 0 (para domingo) até 6 (para sábado)
$semanaDoAno = date('W');

// Mensagens variadas para cada semana do mês
$mensagensSemanais = [
    // Semana 1
    [
        "Domingo, reserve um momento para planejar: a semana promete e você é o gestor dessa jornada.",
        "Segunda-feira, não mate a vaca por causa do carrapato - foque nas soluções, não nos problemas.",
        "Terça-feira, se pegar um projeto, pegue para entregar e não apenas pra fazer. Faça a diferença.",
        "Quarta-feira, se você for escolhido, pense que não é apenas perfil, você é o melhor.",
        "Quinta-feira, as coisas têm que ser feitas por camadas, como descascar o milho 🌽.",
        "Sextou! Se não der certo, sempre tem Batalhão aqui do lado. E lembre-se, sexta é de celebração e reflexão.",
        "Sábado, um passo atrás apenas para pegar impulso. Descanse e reflita sobre a próxima semana."
    ],
    // Semana 2
    [
        "Domingo, olhe o horizonte com esperança e estratégia. Uma nova semana é um novo campo de possibilidades.",
        "Segunda-feira, inicie com energia: não deixe que o medo do carrapato te impeça de valorizar a vaca.",
        "Terça-feira, a excelência está no compromisso de entregar, não apenas de iniciar. Vá além.",
        "Quarta-feira, lembre-se: ser escolhido é uma chance para mostrar que você é mais do que apenas uma opção.",
        "Quinta-feira, desenvolva cada tarefa com a paciência e a precisão de quem descasca um milho, camada por camada.",
        "Sextou! E se a semana foi desafiadora, lembre-se do Batalhão ao lado e diga 'Sextou!' com alegria.",
        "Sábado, tire um tempo para si e recarregue, porque a verdadeira evolução vem do equilíbrio."
    ],
    // Semana 3
    [
        "Domingo, é tempo de meditar sobre o que foi e energizar-se para o que será. Prepare-se para ser incrível.",
        "Segunda-feira, aborde cada problema como um desafio a ser superado, não uma fatalidade a ser temida.",
        "Terça-feira, assuma cada responsabilidade com a vontade de concluir com maestria. Mostre seu valor.",
        "Quarta-feira, cada escolha é um reflexo do seu potencial. Escolha ser extraordinário.",
        "Quinta-feira, na jornada para o topo, cada etapa é crucial. Valorize cada camada de seu esforço.",
        "Sextou! E se por acaso os planos falharem, há sempre uma nova oportunidade para quem tem coragem.",
        "Sábado, descansar é também preparar-se. Recupere-se hoje para amanhã ser o seu melhor."
    ],
    // Semana 4
    [
        "Domingo, é hora de alinhar suas expectativas com suas ações. Uma nova semana é um novo começo.",
        "Segunda-feira, com cada desafio, lembre-se: não é preciso abater a vaca por conta do carrapato.",
        "Terça-feira, a verdadeira medida de um projeto é a qualidade da entrega. Dê o seu melhor.",
        "Quarta-feira, aceite ser escolhido como um sinal de que você não é apenas adequado - você é insubstituível.",
        "Quinta-feira, como no cultivo do milho, cada fase é essencial. Construa seu sucesso camada por camada.",
        "Sextou! E se as coisas não saírem como planejado, o Batalhão é um lembrete de que você não está sozinho.",
        "Sábado, a reflexão é o solo fértil para o crescimento. Cultive suas ideias para florescerem na semana que vem."
    ],
];


// Mensagem especial para a última sexta-feira do mês
$ultimoDiaDoMes = date('t');
if ($diaDaSemana == 5 && date('j') > ($ultimoDiaDoMes - 7)) {
    $mensagemDoDia = "Última sexta-feira do mês! Hora de celebrar as conquistas!";
} else {
    // Calcula qual conjunto de mensagens usar
    $mensagensDaSemana = $mensagensSemanais[($semanaDoAno - 1) % count($mensagensSemanais)];
    $mensagemDoDia = $mensagensDaSemana[$diaDaSemana];
}

// Calcula saudação baseada na hora do dia
$horaAtual = date('G');
$saudacao = $horaAtual < 6 ? 'Boa madrugada' : ($horaAtual < 12 ? 'Bom dia' : ($horaAtual < 18 ? 'Boa tarde' : 'Boa noite'));

$nomeUsuario = $_SESSION['nome_usuario'] ?? 'Usuário'; // Ajuste conforme sua sessão



?>


<!DOCTYPE html>
<html dir="ltr" lang="pt">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta
      name="keywords"
      content="wrappixel, admin dashboard, html css dashboard, web dashboard, bootstrap 5 admin, bootstrap 5, css3 dashboard, bootstrap 5 dashboard, material pro admin bootstrap 5 dashboard, frontend, responsive bootstrap 5 admin template, material design, material dashboard bootstrap 5 dashboard template"
    />
    <meta
      name="description"
      content="MaterialPro is powerful and clean admin dashboard template, inpired from Google's Material Design"
    />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">

    <meta name="robots" content="noindex,nofollow" />
    <title>GATI - Gestão Ágil em TI</title>
    <link
      rel="canonical"
      href="https://sistemagati.com"
    />
    <!-- Favicon icon -->
    <link
      rel="icon"
      type="image/png"
      sizes="16x16"
      href="assets/images/favicon.png"
    />
    <!-- Custom CSS -->
    <link
      rel="stylesheet"
      type="text/css"
      href="assets/libs/quill/dist/quill.snow.css"
    />
    <!-- Custom CSS -->
    <link href="dist/css/style.min.css" rel="stylesheet" />
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

      <style>
         /* Estilo padrão para todos os badges */
.todo-badge {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 25px; /* Largura fixa para todos os badges */
    height: 25px; /* Altura fixa para todos os badges */
    padding: 0;
    border-radius: 50%; /* Faz com que o badge seja um círculo */
    color: #fff;
    font-size: 0.75rem; /* Tamanho da fonte reduzido para caber no badge */
    font-weight: 500; /* Peso da fonte para visibilidade */
    margin-left: auto; /* Alinha o badge à direita dentro do seu contêiner */
    line-height: 1; /* Ajuda a centralizar o texto verticalmente */
}

/* Estilos específicos para as cores de fundo de cada badge */
.todo-badge.bg-light-info {
    background-color: #5bc0de; /* Azul claro */
}

.todo-badge.bg-light-danger {
    background-color: #d9534f; /* Vermelho claro */
}

.todo-badge.bg-success {
    background-color: #5cb85c; /* Verde */
}


/* Estilos para o modal de detalhes da tarefa */
.task-text {
  font-family: 'Arial', sans-serif; /* Substitua pela fonte do seu sistema */
  font-size: 14px; /* Ajuste o tamanho da fonte conforme necessário */
  color: #333; /* Cor do texto */
  white-space: pre-wrap; /* Mantém a formatação de espaços e quebras de linha */
  margin: 0.5rem 0; /* Adiciona uma margem vertical */
  line-height: 1.5; /* Espaçamento entre linhas */
}

/* Se necessário, ajuste o título da tarefa */
.modal-title.task-heading {
  font-size: 1.25rem; /* Ajuste o tamanho da fonte do título */
  font-weight: 400; /* Se desejar um título em negrito */
}

.motivational-message-container {
    background-color: #272b34; /* Uma cor escura para combinar com o tema */
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center; /* Centraliza o texto para chamar atenção */
}

.motivational-message-container h2 {
    color: #21e6c1; /* Uma cor de destaque suave */
    margin-bottom: 10px;
}

.motivational-message-container p {
    color: #a9def9; /* Um tom azulado claro para a frase */
    background-color: #484e70; /* Um tom intermediário para o fundo da frase */
    padding: 15px;
    border-radius: 5px;
    font-size: 18px;
    font-style: italic;
}

/* Estilos para o botão */
.btn-outline-light {
    border-color: #198754;
    color: #198754;
}

.btn-outline-light:hover {
    background: #198754;
    color: #fff;
    border-color: #198754;
}


/* Estilos personalizados para o modal */
.modal-custom-content {
  background-color: #1e1e2f;
  border-radius: 10px;
  animation: fadeIn 0.5s ease-out forwards;
}

.modal-custom-header {
  background-color: #272b34;
  border-bottom: 2px solid #3a3f55;
}

.modal-custom-title {
  color: #21e6c1;
  font-weight: bold;
}


.modal-custom-body {
  color: #c0c0c0;
  background: #1f2128;
  padding: 25px;
}

.inspirational-message {
  text-align: center;
}

.bulb-icon {
  color: #198754;
  font-size: 24px;
  animation: lightBulb 2s infinite alternate;
}

.inspirational-quote {
  font-size: 18px;
  font-style: italic;
  color: #a9def9;
  padding: 20px;
  border-radius: 5px;
  display: inline-block;
}

.team-message {
  text-align: center;
  font-size: 16px;
}

.modal-custom-footer {
  background-color: #272b34;
  border-top: 2px solid #3a3f55;
}

/* Estilo do botão */
.btn-custom-close {
  color: #21e6c1; /* Cor de destaque suave para o texto */
  border: 2px solid #21e6c1; /* Cor de destaque suave para a borda */
  background-color: transparent; /* Fundo transparente */
  transition: all 0.3s ease-in-out;
  border-radius: 4px; /* Arredondamento das bordas */
  padding: 10px 20px; /* Preenchimento vertical e horizontal */
  font-size: 16px; /* Tamanho do texto */
  font-weight: 600; /* Peso da fonte */
  letter-spacing: 0.05em; /* Espaçamento entre letras */
  box-shadow: 0 2px 5px 0 rgba(0, 0, 0, 0.2); /* Sombra sutil */
}

/* Estilo do botão ao passar o mouse (hover) */
.btn-custom-close:hover {
  background-color: #21e6c1; /* Cor de destaque suave para o fundo */
  color: #272b34; /* Cor do texto */
  border-color: #21e6c1; /* Cor de destaque suave para a borda */
  box-shadow: 0 0 15px 0 rgba(33, 230, 193, 0.5); /* Sombra com efeito "glow" */
}

/* Estilo do botão ao ser pressionado (active) */
.btn-custom-close:active {
  background-color: #198754; /* Cor verde escura */
  color: #fff; /* Cor branca para o texto */
  border-color: #198754; /* Cor verde escura para a borda */
  box-shadow: 0 0 10px 0 rgba(25, 135, 84, 0.5); /* Sombra com efeito "glow" */
}

/* Estilo do botão ao ter o foco (focus) */
.btn-custom-close:focus {
  outline: none; /* Remove o contorno padrão */
  box-shadow: 0 0 10px 0 rgba(33, 230, 193, 0.5); /* Sombra com efeito "glow" */
}



/* Animações */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes lightBulb {
  from {
    text-shadow: 0 0 5px #198754;
  }
  to {
    text-shadow: 0 0 20px #198754;
  }
}





      </style>

  </head>

  <body>
    <!-- -------------------------------------------------------------- -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- -------------------------------------------------------------- -->
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
      <!-- -------------------------------------------------------------- -->
      <!-- End Topbar header -->
      <!-- -------------------------------------------------------------- -->
      <!-- -------------------------------------------------------------- -->
      <!-- Left Sidebar - style you can find in sidebar.scss  -->
      <!-- -------------------------------------------------------------- -->
     <?php include 'sidebar.php'?> 
      <!-- -------------------------------------------------------------- -->
      <!-- End Left Sidebar - style you can find in sidebar.scss  -->
      <!-- -------------------------------------------------------------- -->
      <!-- -------------------------------------------------------------- -->
      <!-- Page wrapper  -->
      <!-- -------------------------------------------------------------- -->
      <div class="page-wrapper">
        <div class="email-app todo-box-container">
          <!-- -------------------------------------------------------------- -->
          <!-- Left Part -->
          <!-- -------------------------------------------------------------- -->
          <div class="left-part list-of-tasks">
            <a
              class="
                ti-menu ti-close
                btn btn-success
                show-left-part
                d-block d-md-none
              "
              href="javascript:void(0)"
            ></a>
            <div class="scrollable" style="height: 100%">
              <div class="p-3">
                <a
                  class="waves-effect waves-light btn btn-info d-block"
                  href="javascript: void(0)"
                  id="add-task"
                  >Nova Tarefa</a
                >
              </div>              
              <div class="divider"></div>
              <ul class="list-group">
                <li>
                  <small
                    class="
                      p-3
                      d-block
                      text-uppercase text-dark
                      font-weight-medium
                    "
                    >Pastas</small
                  >
                </li>

                <li class="list-group-item p-0 border-0">
                  <a href="javascript:void(0)" class="todo-link active list-group-item-action p-3 d-flex align-items-center" id="all-todo-list">
                    <i data-feather="list" class="feather-sm me-2"></i> Pendentes
                    <span class="todo-badge badge bg-danger text-white rounded-pill px-3 font-weight-medium ms-auto">
  <?php echo count($tarefasPendentes); ?>
</span>
                  </a>
                </li>

                <li class="list-group-item p-0 border-0">
                <a href="javascript:void(0)" class="todo-link list-group-item-action p-3 d-flex align-items-center" id="current-task-important">
                  <i data-feather="star" class="feather-sm me-2"></i>
                  Importantes
                  <span class="todo-badge badge rounded-pill px-3 bg-warning text-white font-weight-medium ms-auto">
  <?php echo count($tarefasImportantes); ?>
</span>
                </a>
              </li>

              <li class="list-group-item p-0 border-0">
  <a href="javascript:void(0)" class="todo-link list-group-item-action p-3 d-flex align-items-center" id="shared-tasks">
    <i data-feather="share-2" class="feather-sm me-2"></i>
    Compartilhadas
    <span class="todo-badge badge rounded-pill px-3 text-white font-weight-medium bg-primary ms-auto" id="shared-tasks-count">
  <?php echo count($tarefasCompartilhadas); ?>
</span>

  </a>
</li>
                 <li class="list-group-item p-0 border-0">
                  <a href="javascript:void(0)" class="todo-link list-group-item-action p-3 d-flex align-items-center" id="current-task-done">
                    <i data-feather="send" class="feather-sm me-2"></i> Concluídas
                    <span class="todo-badge badge rounded-pill px-3 text-white font-weight-medium bg-info ms-auto">
  <?php echo count($tarefasConcluidas); ?>
</span>
                  </a>
                </li>

              </ul>
            </div>
          </div>
          <!-- -------------------------------------------------------------- -->
          <!-- Right Part -->
          <!-- -------------------------------------------------------------- -->
          <div class="right-part mail-list bg-white overflow-auto">
            <div id="todo-list-container">
    <!-- Container para a mensagem motivacional -->
    <!-- Nome do usuário -->
        <?php
date_default_timezone_set('America/Sao_Paulo'); // Configura para o fuso horário de Brasília
$horaAtual = date('H'); // Obtem a hora atual de acordo com o fuso horário configurado

// Define a saudação com base no horário
if ($horaAtual < 5) {
    $saudacao = "Boa madrugada";
} elseif ($horaAtual < 12) {
    $saudacao = "Bom dia";
} elseif ($horaAtual < 18) {
    $saudacao = "Boa tarde";
} else {
    $saudacao = "Boa noite";
}
?>

    <div class="p-3 border-bottom" align="right">
        <!-- Botão para gerar PDF das Tarefas -->
        <button onclick="gerarPdfTarefas()" class="btn btn-outline-light">
            <i class="fas fa-print"></i> Gerar PDF das Tarefas
        </button>
    </div>
    <!-- Mais conteúdo do seu app -->
</div>



              <!-- Todo list-->
              <div class="todo-listing">
                <div id="all-todo-container" class="p-3">                  

               <?php foreach ($tarefas as $tarefa):

                // Busca os usuários com quem a tarefa foi compartilhada
    $sqlCompartilhados = "SELECT u.Nome FROM tarefas_compartilhadas AS tc JOIN usuarios AS u ON tc.id_usuario_compartilhado = u.Id WHERE tc.id_tarefa = :tarefaId";
    $stmtCompartilhados = $pdo->prepare($sqlCompartilhados);
    $stmtCompartilhados->bindParam(':tarefaId', $tarefa['id'], PDO::PARAM_INT);
    $stmtCompartilhados->execute();
    $usuariosCompartilhados = $stmtCompartilhados->fetchAll(PDO::FETCH_ASSOC);

    $isShared = $tarefa['is_shared'] ?? false; // Define se a tarefa é compartilhada
    $checked = $tarefa['is_complete'] ? 'checked' : '';
    $style = $tarefa['is_complete'] ? 'text-decoration: line-through;' : '';
    $cursorStyle = 'cursor: pointer;'; // Garante que cursorStyle também esteja definido

    // Ajuste para classificar corretamente a tarefa
    $isCompleteClass = $tarefa['is_complete'] ? 'complete-todo-list' : '';
    $isImportantClass = $tarefa['is_important'] && !$tarefa['is_complete'] ? 'important-todo-list' : '';
    // Define isPendingClass baseado no estado da tarefa
    $isPendingClass = !$tarefa['is_complete'] && !$isShared ? 'pending-todo-list' : ''; // Certifique-se de excluir as compartilhadas, se necessário
    $isSharedClass = $isShared ? 'compartilhada' : '';

?>
<div class="todo-item <?php echo "$isCompleteClass $isImportantClass $isPendingClass $isSharedClass"; ?> p-3 border-bottom position-relative" style="<?php echo $style . $cursorStyle; ?>" onclick="openTaskModal(<?php echo $tarefa['id']; ?>)">



        <!-- Restante do código HTML para mostrar detalhes da tarefa -->

        <div class="inner-item d-flex align-items-start">
            <div class="w-100">
                <div class="checkbox checkbox-info d-flex align-items-start form-check">
                            <input type="checkbox" class="form-check-input flex-shrink-0 me-3 markCompleteButton" id="checkbox<?php echo $tarefa['id']; ?>" data-task-id="<?php echo $tarefa['id']; ?>" <?php echo $checked; ?>/>



                    <label class="form-check-label" for="checkbox<?php echo $tarefa['id']; ?>"></label>
                    <div>
  <div class="content-todo">
    <h5 class="font-weight-medium fs-4 todo-header" style="text-transform: uppercase;" data-todo-header="<?php echo htmlspecialchars($tarefa['nome_tarefa']); ?>">
      <?php echo htmlspecialchars_decode(htmlspecialchars($tarefa['nome_tarefa'])); ?>
    </h5>
    <div class="todo-subtext text-muted fs-3" data-todosubtext-html="<?php echo htmlspecialchars_decode(htmlspecialchars($tarefa['descricao_tarefa'])); ?>" data-todosubtextText='<?php echo json_encode(["ops" => [["insert" => htmlspecialchars_decode($tarefa['descricao_tarefa']) . "\n"]]]); ?>'>
      <?php echo nl2br(htmlspecialchars_decode(htmlspecialchars(mb_strimwidth($tarefa['descricao_tarefa'], 0, 300, "…")))); ?>
    </div>
    <!-- Formatação e exibição da data de cadastro -->
    <span class="todo-time fs-2 text-muted">
        <?php
        // Mapeamento dos meses em português
        $meses = [
            'January' => 'Jan',
            'February' => 'Fev',
            'March' => 'Mar',
            'April' => 'Abr',
            'May' => 'Mai',
            'June' => 'Jun',
            'July' => 'Jul',
            'August' => 'Ago',
            'September' => 'Set',
            'October' => 'Out',
            'November' => 'Nov',
            'December' => 'Dez'
        ];

        // Formatando a data para o padrão "14 Fev 2024"
        $dataCadastro = strtotime($tarefa['data_cadastro']);
        $dataFormatada = date('d M Y', $dataCadastro);
        $dataFormatada = str_replace(array_keys($meses), array_values($meses), $dataFormatada);
        ?><br>
        <i style="color: #198754;" class="fas fa-calendar me-1"></i>
        <span style="color: #198754; font-weight: bold;">
            <?php echo htmlspecialchars_decode($dataFormatada); ?>
        </span>
        <div style="margin-top: 5px; color: #198754;">
            <i class="fas fa-user me-1"></i>
            <span>Criada por <b><?php echo htmlspecialchars_decode(htmlspecialchars($tarefa['nome_usuario'])); ?></b></span>
        </div>
        <?php foreach ($usuariosCompartilhados as $usuario): ?>
        <div style="margin-top: 5px; color: #198754">
            <i class='fas fa-share-alt me-1'></i> Compartilhado com: <b><?php echo htmlspecialchars_decode(htmlspecialchars($usuario['Nome'])); ?></b>
        </div>
        <?php endforeach; ?>
    </span>
</div>

</div>


                   <div class="ms-auto">
    <!-- Inclui os botões de ação aqui -->
    <div class="dropdown-action">
        <div class="dropdown todo-action-dropdown">
            <button class="btn btn-link text-dark p-1 dropdown-toggle text-decoration-none todo-action-dropdown" type="button" id="more-action-<?php echo $tarefa['id']; ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="icon-options-vertical"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="more-action-<?php echo $tarefa['id']; ?>">
                <a class="edit dropdown-item" href="javascript:void(0);" onclick="openEditTaskModal(<?php echo $tarefa['id']; ?>, '<?php echo addslashes($tarefa['nome_tarefa']); ?>', <?php echo htmlspecialchars(json_encode($tarefa['descricao_tarefa']), ENT_QUOTES, 'UTF-8'); ?>);">
                    <i class="fas fa-edit text-info me-1"></i> Editar
                </a>

                <?php if ($isShared): ?>
                    <!-- Botão de remover compartilhamento para tarefas compartilhadas -->
                    <?php
                    $stmt = $pdo->prepare("SELECT id FROM tarefas_compartilhadas WHERE id_tarefa = :id_tarefa AND id_usuario_compartilhado = :usuario_id");
                    $stmt->execute(['id_tarefa' => $tarefa['id'], 'usuario_id' => $_SESSION['usuario_id']]);
                    $compartilhamento = $stmt->fetch();
                    if ($compartilhamento): ?>
                        <a class="remove-share dropdown-item" href="javascript:void(0);" data-compartilhamento-id="<?php echo $compartilhamento['id']; ?>" onclick="removerCompartilhamento(this.getAttribute('data-compartilhamento-id'));">
                            <i class="fas fa-user-slash text-danger me-1"></i>Sair da Tarefa
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Botão Compartilhar para tarefas não compartilhadas -->
                    <a class="share dropdown-item shareTaskButton" href="javascript:void(0);" data-task-id="<?php echo $tarefa['id']; ?>">
                        <i class="fas fa-share-alt text-primary me-1"></i> Compartilhar
                    </a>
                <?php endif; ?>

                <!-- Alternar entre Importante e Não Importante baseado no estado da tarefa -->
                <?php if ($tarefa['is_important']): ?>
                    <a class="not-important dropdown-item markNotImportantButton" href="javascript:void(0);" data-task-id="<?php echo $tarefa['id']; ?>"><i class="far fa-star text-warning me-1"></i> Não Importante</a>
                <?php else: ?>
                    <a class="important dropdown-item markImportantButton" href="javascript:void(0);" data-task-id="<?php echo $tarefa['id']; ?>"><i class="fas fa-star text-warning me-1"></i> Importante</a>
                <?php endif; ?>


                <!-- Formulário oculto para remoção da tarefa -->
        <form id="remove-task-form-<?php echo $tarefa['id']; ?>" style="display: none;" action="removertarefa.php" method="POST">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="tarefa_id" value="<?php echo $tarefa['id']; ?>">
        </form>

        <a class="remove dropdown-item" href="javascript:void(0);" onclick="confirmRemoval(<?php echo $tarefa['id']; ?>)">
    <i class="far fa-trash-alt text-danger me-1"></i> Remover
</a>


            </div>
        </div>
    </div>
</div>


                </div>
            </div>
        </div>
    </div>

<?php endforeach; ?> 


                  
                  
                </div>
                <!-- Modal -->
                <div
                  class="modal fade"
                  id="todoShowListItem"
                  tabindex="-1"
                  role="dialog"
                  aria-hidden="true"
                >
                  <div
                    class="modal-dialog modal-dialog-centered"
                    role="document"
                  >
                    <div class="modal-content border-0">
                      <div class="modal-header bg-light d-flex">
                        <h5 style="text-transform: uppercase;" class="modal-title task-heading"></h5>
                        <button
                          type="button"
                          class="btn-close"
                          data-bs-dismiss="modal"
                          aria-label="Close"
                        ></button>
                      </div>
                      <div class="modal-body">
                        <div class="compose-box">
                          <div class="compose-content">
                            <label class="mb-0 fs-4" style="font-weight: bold; margin-bottom: 1rem;">Detalhe da Tarefa</label>
                            <!-- Use a tag <pre> para manter a formatação do texto incluindo quebras de linha -->
                            <pre class="task-text text-muted" style="white-space: pre-wrap;"></pre>
                          </div>

                        </div>
                      </div>
                      <div class="modal-footer">
                        <button
                          class="btn btn-danger btn-block"
                          data-bs-dismiss="modal"
                        >
                          Fechar
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Modal -->
          <div
            class="modal fade"
            id="addTaskModal"
            tabindex="-1"
            role="dialog"
            aria-labelledby="addTaskModalTitle"
            aria-hidden="true"
          >
            <div class="modal-dialog modal-dialog-centered" role="document">
              <div class="modal-content border-0">
                <div class="modal-header bg-info text-white">
                  <h5 class="modal-title text-white">Nova Tarefa</h5>
                  <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                  ></button>
                </div>
                <form method="post" action="minhastarefas.php">
  <div class="modal-body">
    <div class="compose-box">
      <div class="compose-content" id="addTaskModalTitle">
          <div class="row">
            <div class="col-md-12">
              <div class="d-flex mail-to mb-4">
                <div class="w-100">
                  <input
                    id="nome_tarefa"
                    type="text"
                    placeholder="Escreva a Tarefa"
                    class="form-control"
                    name="nome_tarefa"
                    required
                  />
                  <span class="validation-text"></span>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex mail-subject mb-4">
            <div class="w-100">
              <textarea
                id="descricao_tarefa"
                class="form-control"
                name="descricao_tarefa"
                placeholder="Descrição da Tarefa" style="height:280px"
                required
              ></textarea>
              <span class="validation-text"></span>
            </div>
          </div>
      </div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-danger" data-bs-dismiss="modal">
      <i class="flaticon-cancel-12"></i> Cancelar
    </button>
    <button type="submit" class="btn btn-info" name="action" value="add">
  Adicionar Tarefa
</button>

  </div>
</form>            
              </div>
            </div>
          </div>

            <!-- Modal Editar Tarefa -->
<div class="modal fade" id="editTaskModal" tabindex="-1" role="dialog" aria-labelledby="editTaskModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title text-white">Editar Tarefa</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
  <form method="post" action="processa_edit_tarefa.php">
        <div class="modal-body">
          <div class="compose-box">
            <div class="compose-content" id="editTaskModalTitle">
              <input type="hidden" id="edit_task_id" name="task_id" value=""> <!-- Input oculto para armazenar o ID da tarefa -->
              <div class="row">
                <div class="col-md-12">
                  <div class="d-flex mail-to mb-4">
                    <div class="w-100">
                      <textarea id="edit_nome_tarefa" type="text" placeholder="Escreva a Tarefa" class="form-control" name="nome_tarefa" required /> </textarea>
                      <span class="validation-text"></span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="d-flex mail-subject mb-4">
                <div class="w-100">
                  <textarea id="edit_descricao_tarefa" class="form-control" name="descricao_tarefa" placeholder="Descrição da Tarefa" required></textarea>
                  <span class="validation-text"></span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-danger" data-bs-dismiss="modal">
            <i class="flaticon-cancel-12"></i> Cancelar
          </button>
           <button type="submit" class="btn btn-info" name="action" value="edit">
            Salvar Alterações
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal Compartilhar Tarefa -->
<div class="modal fade" id="shareTaskModal" tabindex="-1" role="dialog" aria-labelledby="shareTaskModalTitle" aria-hidden="true">

  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title text-white">Compartilhar Tarefa</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="compartilhar_tarefa.php">
        <div class="modal-body">
          <input type="hidden" id="share_task_id" name="task_id" value=""> <!-- Input oculto para armazenar o ID da tarefa -->
          <div class="form-group">
            <label for="user_to_share">Deseja compartilhar essa tarefa com quem?</label>
            <select class="form-control" id="user_to_share" name="user_to_share" required>
                <?php foreach ($usuarios as $usuario): ?>
                    <!-- Pula o usuário logado -->
                    <?php if ($usuario['Id'] != $_SESSION['usuario_id']): ?>
                        <option value="<?php echo htmlspecialchars($usuario['Id']); ?>">
                            <?php echo htmlspecialchars($usuario['Nome']); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

          </div>
    <!-- Seção para exibir usuários com quem a tarefa foi compartilhada -->
<div class="form-group mt-3">
  <label>Já compartilhada com:</label>
  <ul style="padding-left:0rem;" id="sharedWithList">

  </ul>
</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info">Compartilhar</button>

        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal de Boas-Vindas -->
<div class="modal fade" id="modalBoasVindas" tabindex="-1" aria-labelledby="modalBoasVindasLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-custom-content">
      <div class="modal-header modal-custom-header">
        <h5 class="modal-title modal-custom-title" id="modalBoasVindasLabel">
          <?php echo "{$saudacao}, " . "<strong>" . $_SESSION['nome_usuario'] . "!</strong>"; ?>
        </h5>
        <button type="button" class="btn-close btn-custom-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body modal-custom-body">
        <div class="inspirational-message">
          <i class="fas fa-lightbulb bulb-icon"></i>
          <p class="inspirational-quote">
            "<?php echo $mensagemDoDia; ?>"
          </p>
        </div>
        <div class="team-message">
          <p><strong>Impulsionados pela tecnologia, rumo à inovação. Sucesso!</strong> 👊</p>
          <p>Avante e inovem,<br><strong>Equipe INOVA</strong>.</p>
        </div>
      </div>
      <div class="modal-footer modal-custom-footer">
        <button type="button" class="btn btn-custom-close" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>






          <!-- -------------------------------------------------------------- -->
          <!-- footer -->
          <!-- -------------------------------------------------------------- -->
          <?php include 'footer.php'?>    
          <!-- -------------------------------------------------------------- -->
          <!-- End footer -->
          <!-- -------------------------------------------------------------- -->
          <!-- -------------------------------------------------------------- -->
          <!-- End Page wrapper  -->
          <!-- -------------------------------------------------------------- -->
        </div>
      </div>
    </div>
    <!-- -------------------------------------------------------------- -->
    <!-- End Wrapper -->
    <!-- -------------------------------------------------------------- -->
    <!-- -------------------------------------------------------------- -->
    <!-- customizer Panel -->
    <!-- -------------------------------------------------------------- -->
    
    <div class="chat-windows"></div>
    <!-- -------------------------------------------------------------- -->
    <!-- All Jquery -->
    <!-- -------------------------------------------------------------- -->



<script>
function confirmRemoval(taskId) {
    if (confirm('Tem certeza que deseja remover esta tarefa?')) {
        window.location.href = 'removertarefa.php?action=remove&tarefa_id=' + taskId;
    }
}
</script>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $('.shareTaskButton').on('click', function() {
    var taskId = $(this).data('task-id');
    openShareTaskModal(taskId);
});

function openShareTaskModal(taskId) {
    $('#share_task_id').val(taskId); // Define o ID da tarefa no input oculto
    
    // Faz a chamada AJAX para buscar os usuários compartilhados
    $.ajax({
        url: 'buscar_compartilhamentos.php',
        type: 'POST',
        data: { task_id: taskId },
        dataType: 'json',
        success: function(response) {
            let userListHtml = '';
            if (response && response.length > 0) {
                response.forEach(function(user) {
                    userListHtml += '<p><i class="fas fa-share-alt text-info me-1"></i> ' + user.Nome + '</p>';
                });
            } else {
                userListHtml = '<p><i class="fas fa-times text-danger me-1"></i> A tarefa ainda não foi compartilhada.</p>';
            }
            $('#sharedWithList').html(userListHtml); // Atualiza a lista de usuários compartilhados
        },
        error: function(xhr, status, error) {
            console.error("Erro ao buscar usuários compartilhados: " + error);
            $('#sharedWithList').html('<p><i class="fas fa-exclamation-triangle text-warning me-1"></i> Erro ao buscar usuários compartilhados.</p>');
        }
    });

    $('#shareTaskModal').modal('show'); // Abre o modal após buscar as informações
}


function removerCompartilhamento(compartilhamentoId) {
    if (confirm('Tem certeza que deseja sair desta tarefa?')) {
        $.post('remover_compartilhamento.php', { compartilhamento_id: compartilhamentoId }, function(data) {
            if (data.success) {
                alert('Tarefa removida com sucesso.');
                window.location.reload(); // Ou outra lógica para atualizar a interface
            } else {
                alert('Erro ao remover tarefa: ' + data.message);
            }
        }, 'json').fail(function() {
            alert('Erro ao comunicar com o servidor.');
        });
    }
}


</script>




    <?php if (isset($_SESSION['success_message'])): ?>
<script>
    alert('<?php echo $_SESSION['success_message']; ?>');
    <?php unset($_SESSION['success_message']); // Limpa a mensagem após exibição ?>
</script>
<?php endif; ?>


    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Invoca diretamente a função para aplicar o filtro de tarefas pendentes
    filterTasks('pending-todo-list');
    updateActiveTab('pending-todo-list');

// Adiciona o evento de clique no filtro de tarefas pendentes
    document.getElementById('all-todo-list').addEventListener('click', function() {
        filterTasks('pending-todo-list');
    });

    // Adiciona o evento de clique no filtro de tarefas completas
    document.getElementById('current-task-done').addEventListener('click', function() {
        filterTasks('complete-todo-list');
    });

    // Adiciona o evento de clique no filtro de tarefas importantes
    document.getElementById('current-task-important').addEventListener('click', function() {
        filterTasks('important-todo-list');
    });

    // Adiciona o evento de clique no filtro de tarefas compartilhadas
    document.getElementById('shared-tasks').addEventListener('click', function() {
        filterTasks('compartilhada');
    });




     // Função para atualizar a visibilidade das tarefas e contadores
    function filterTasks(filterClass) {
        // Esconde todas as tarefas
        document.querySelectorAll('.todo-item').forEach(function(task) {
            task.style.display = 'none';
        });

        // Mostra as tarefas que correspondem à classe de filtro
        document.querySelectorAll('.' + filterClass).forEach(function(task) {
            task.style.display = '';
        });

        // Atualiza a aba ativa e os contadores, se necessário
        updateActiveTab(filterClass);
        updateTaskCounters();
    }

 function updateActiveTab(activeFilter) {
        // Remove a classe 'active' de todos os links
        document.querySelectorAll('.todo-link').forEach(link => link.classList.remove('active'));
        
        // Adiciona a classe 'active' ao link atual baseado no filtro ativo
        switch(activeFilter) {
            case 'pending-todo-list':
                document.getElementById('all-todo-list').classList.add('active');
                break;
            case 'important-todo-list':
                document.getElementById('current-task-important').classList.add('active');
                break;
            case 'complete-todo-list':
                document.getElementById('current-task-done').classList.add('active');
                break;
            case 'compartilhada':
                document.getElementById('shared-tasks').classList.add('active');
                break;
        }
    }

    // Atualiza o status de uma tarefa para completa ou não
    document.querySelectorAll('.todo-item .markCompleteButton').forEach(button => {
        button.addEventListener('change', function() {
            const taskId = this.getAttribute('data-task-id');
            const newState = this.checked ? 'true' : 'false';
            updateTaskStatus(taskId, 'mark_complete', newState);
        });
    });

    // Manipula cliques nos botões de marcar como importante/não importante
    document.querySelectorAll('.dropdown-menu .markImportantButton, .dropdown-menu .markNotImportantButton').forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            const action = this.classList.contains('markNotImportantButton') ? 'mark_not_important' : 'mark_important';
            updateTaskStatus(taskId, action, action === 'mark_important' ? 'true' : 'false');
        });
    });
        

    // Função para atualizar a contagem de tarefas em cada categoria
    function updateTaskCounters() {
        const totalPendentesElement = document.querySelector('#all-todo-list .todo-badge');
        const totalImportantesElement = document.querySelector('#current-task-important .todo-badge');
        const totalCompletasElement = document.querySelector('#current-task-done .todo-badge');

        const allTasks = document.querySelectorAll('.todo-item');
        const pendingTasks = document.querySelectorAll('.todo-item:not(.complete-todo-list)');
        const importantTasks = document.querySelectorAll('.important-todo-list:not(.complete-todo-list)');
        const completeTasks = document.querySelectorAll('.complete-todo-list');

        totalPendentesElement.textContent = pendingTasks.length;
        totalImportantesElement.textContent = importantTasks.length;
        totalCompletasElement.textContent = completeTasks.length;
    }

    // Função genérica para filtrar tarefas
    function filterTasks(filterClass) {
        const allTasks = document.querySelectorAll('.todo-item');
        allTasks.forEach(task => task.style.display = 'none');
        document.querySelectorAll('.' + filterClass).forEach(task => task.style.display = '');
        updateTaskCounters(); // Atualiza os contadores
    }

    // Adiciona o evento de clique nas abas para filtrar tarefas
    document.getElementById('all-todo-list').addEventListener('click', function() {
    filterTasks('pending-todo-list');
});
document.getElementById('current-task-done').addEventListener('click', function() {
    filterTasks('complete-todo-list');
});
document.getElementById('current-task-important').addEventListener('click', function() {
    filterTasks('important-todo-list');
});

// Adicionar: Listener para tarefas compartilhadas.
document.getElementById('shared-tasks').addEventListener('click', function() {
    filterTasks('compartilhada'); // Assumindo que 'compartilhada' é a classe para tarefas compartilhadas.
});

    function updateActiveTab(activeClass) {
        // Remove a classe 'active' de todas as abas
        document.querySelectorAll('.todo-link').forEach(function(link) {
            link.classList.remove('active');
        });

        // Adiciona a classe 'active' na aba correta
        let activeTab;
        switch(activeClass) {
            case 'pending-todo-list':
                activeTab = document.getElementById('all-todo-list');
                break;
            case 'important-todo-list':
                activeTab = document.getElementById('current-task-important');
                break;
            case 'complete-todo-list':
                activeTab = document.getElementById('current-task-done');
                break;
            case 'compartilhada':
                activeTab = document.getElementById('shared-tasks');
                break;
        }

        if(activeTab) {
            activeTab.classList.add('active');
        }
    }

    // Chama a função de atualizar contadores ao carregar a página
    updateTaskCounters();
        
    
    // Função para mostrar apenas as tarefas completas
    function showCompleteTasks() {
        const allTasks = document.querySelectorAll('.todo-item');
        allTasks.forEach(task => task.style.display = 'none'); // Esconde todas as tarefas
        const completeTasks = document.querySelectorAll('.complete-todo-list');
        completeTasks.forEach(task => task.style.display = ''); // Mostra apenas as completas
        
        // Atualiza o contador de tarefas completas
        totalCompletasElement.textContent = completeTasks.length;
    }    

    // Função para mostrar apenas as tarefas importantes
    function showImportantTasks() {
        const allTasks = document.querySelectorAll('.todo-item');
        allTasks.forEach(task => task.style.display = 'none'); // Esconde todas as tarefas
        const importantTasks = document.querySelectorAll('.important-todo-list');
        importantTasks.forEach(task => task.style.display = ''); // Mostra apenas as importantes
    }

    // Atualiza o contador de tarefas completas
    function updateTaskCounter() {
        const completeTasks = document.querySelectorAll('.complete-todo-list');
        totalCompletasElement.textContent = completeTasks.length;
        // Armazena o total no localStorage para persistência após o refresh
        localStorage.setItem('totalTarefasConcluidas', completeTasks.length);
    }


    // Restaura o contador de tarefas importantes após o refresh
    function restoreImportantTaskCounter() {
        const storedCount = localStorage.getItem('totalTarefasImportantes');
        totalImportantesElement.textContent = storedCount || '0';
    }




        // Atualiza o contador de tarefas importantes
    function updateImportantTaskCounter() {
        const importantTasks = document.querySelectorAll('.important-todo-list');
        const totalImportant = importantTasks ? importantTasks.length : 0;
        totalImportantesElement.textContent = totalImportant;
        localStorage.setItem('totalTarefasImportantes', totalImportant);
    }

    // Chama a função logo após definí-la para inicializar o contador corretamente.
    updateImportantTaskCounter();

     

    // Restaura o contador de tarefas completas após o refresh
    function restoreCompleteTaskCounter() {
        const storedCount = localStorage.getItem('totalTarefasConcluidas');
        if (storedCount) {
            totalCompletasElement.textContent = storedCount;
        } else {
            // Se não houver nada no localStorage, calcula novamente
            updateTaskCounter();
        }
    }

    // Adiciona o evento de clique nas abas para mostrar as tarefas
    document.getElementById('current-task-done').addEventListener('click', showCompleteTasks);
    document.getElementById('current-task-important').addEventListener('click', showImportantTasks);

    // Função para alternar o estado de importante da tarefa
    function toggleImportant(taskId, isCurrentlyImportant) {
        // Alternar o estado de importante
        const isImportant = !isCurrentlyImportant;
        const button = document.querySelector(`[data-task-id="${taskId}"]`);
        if (button) {
            if (isImportant) {
                button.classList.remove('markNotImportantButton');
                button.classList.add('markImportantButton');
                button.innerHTML = '<i class="fas fa-star text-warning me-1"></i> Importante';
            } else {
                button.classList.remove('markImportantButton');
                button.classList.add('markNotImportantButton');
                button.innerHTML = '<i class="fas fa-star-half-alt text-warning me-1"></i> Não Importante';
            }
        }
        updateTaskStatus(taskId, 'mark_important', isImportant);
    }   


    // Adiciona o evento de clique nas abas para mostrar as tarefas
    document.getElementById('current-task-done').addEventListener('click', showCompleteTasks);
    document.getElementById('current-task-important').addEventListener('click', showImportantTasks);

    // Eventos para marcar tarefas como completas, incompletas ou importantes
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.addEventListener('click', function(event) {
            let target = event.target;
            if (target.classList.contains('markImportantButton') || target.classList.contains('markNotImportantButton')) {
                const taskId = target.getAttribute('data-task-id');
                // Determina o novo estado baseado na classe do botão
                const newState = target.classList.contains('markImportantButton');
                updateTaskStatus(taskId, 'mark_important', newState);
            }
        });
    });

    // Atualiza o status da tarefa no servidor e na UI
    function updateTaskStatus(taskId, action, value) {
    let formData = new FormData();
    formData.append('action', action);
    formData.append('tarefa_id', taskId);
    formData.append('value', value);

    fetch('processa_tarefa.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualiza a UI com base no resultado da ação
            // Se você quiser manter a atualização da UI sem recarregar, descomente a próxima linha
            // updateUI(taskId, value === 'true', action);
            
            // Recarrega a página para refletir as mudanças
            window.location.reload();
        } else {
            console.error('Erro:', data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
    });
}


    // Função para atualizar o status do botão Importante/Não importante
    function updateImportanceButton(taskId, isImportant) {
        const importanceButton = document.querySelector(`[data-task-id="${taskId}"].importance-button`);
        if (importanceButton) {
            if (isImportant) {
                importanceButton.textContent = 'Não Importante';
                importanceButton.classList.remove('markImportantButton');
                importanceButton.classList.add('markNotImportantButton');
            } else {
                importanceButton.textContent = 'Importante';
                importanceButton.classList.remove('markNotImportantButton');
                importanceButton.classList.add('markImportantButton');
            }
        }
    }

 // Atualiza a interface do usuário quando uma tarefa é marcada ou desmarcada
function updateUI(taskId, newState, action) {
    const item = document.querySelector(`[data-task-id="${taskId}"]`).closest('.todo-item');
    const importanceButton = item.querySelector('.markImportantButton, .markNotImportantButton');

    // Ação baseada no tipo de atualização realizada
    switch (action) {
        case 'mark_complete':
            item.classList.toggle('complete-todo-list', newState === 'true');
            item.classList.toggle('pending-todo-list', newState !== 'true');
            item.style.textDecoration = newState === 'true' ? 'line-through' : 'none';
            break;
        case 'mark_important':
            if (newState === 'true') {
                if (importanceButton) {
                    importanceButton.textContent = 'Não Importante'; // Mudar texto conforme necessário
                    importanceButton.classList.remove('markImportantButton');
                    importanceButton.classList.add('markNotImportantButton');
                }
                item.classList.add('important-todo-list');
            }
            break;
        case 'mark_not_important':
            if (importanceButton) {
                importanceButton.textContent = 'Importante'; // Mudar texto conforme necessário
                importanceButton.classList.remove('markNotImportantButton');
                importanceButton.classList.add('markImportantButton');
            }
            item.classList.remove('important-todo-list');
            break;
    }

    // Atualiza a visibilidade e contadores das tarefas após a alteração
    updateTaskVisibilityAndCounters();
}


// Função para atualizar a visibilidade das tarefas e contadores
function updateTaskVisibilityAndCounters() {
    // Atualizar a visibilidade baseada no filtro ativo
    const filterButtons = document.querySelectorAll('.filter-button');
    filterButtons.forEach(button => {
        if(button.classList.contains('active')) {
            const filterClass = button.getAttribute('data-filter');
            filterTasks(filterClass);
        }
    });

    // Atualizar os contadores de tarefas
    updateTaskCounters();
}


    // Chama as funções de atualização e restauração ao carregar a página
    updateTaskCounters();
    restoreCompleteTaskCounter();
    restoreImportantTaskCounter();
});

</script>

<script>
function autoAdjustTextarea(textareaId) {
  var textarea = document.getElementById(textareaId);
  
  function adjustHeight() {
    textarea.style.height = 'auto'; // Reseta a altura para obter o scrollHeight correto
    textarea.style.height = textarea.scrollHeight + 'px'; // Ajusta a altura para o conteúdo
  }

  // Ajusta a altura inicialmente
  adjustHeight();

  // Ajusta a altura sempre que o conteúdo muda
  textarea.addEventListener('input', adjustHeight);
}

// Abre o modal e ajusta os campos
function openEditTaskModal(taskId, taskName, taskDescription) {
  document.getElementById('edit_task_id').value = taskId;
  document.getElementById('edit_nome_tarefa').value = taskName;
  document.getElementById('edit_descricao_tarefa').value = taskDescription;

  // Aguarda o modal estar visível para ajustar os campos
  $('#editTaskModal').on('shown.bs.modal', function () {
    autoAdjustTextarea('edit_descricao_tarefa');
  });

  // Abre o modal
  $('#editTaskModal').modal('show');
}

// Inicializa os campos assim que a página carrega
document.addEventListener('DOMContentLoaded', function () {
  autoAdjustTextarea('edit_descricao_tarefa');
});

</script>


<script> 
function alterarStatusTarefa(tarefaId, acao) {
    $.ajax({
        url: 'atualizar_status_tarefa.php',
        type: 'POST',
        data: {
            tarefa_id: tarefaId,
            action: acao
        },
        dataType: 'json',
        success: function(response) {
            if(response.success) {
                alert(response.message);
                // Atualiza a página ou faz ajustes no DOM para refletir a mudança
                location.reload(); // Simples recarga da página. Considere ajustar sem recarga.
            } else {
                alert('Erro: ' + response.message);
            }
        },
        error: function() {
            alert('Erro ao alterar o status da tarefa.');
        }
    });
}

</script>






<script> 

function showTaskDetails(taskName, taskText) {
  // Define o título da tarefa
  document.querySelector('.modal-title.task-heading').textContent = taskName;

  // Define o texto da tarefa e converte quebras de linha em <br>
  document.querySelector('.modal-body .task-text').innerHTML = nl2br(htmlspecialchars(taskText));

  // Abre o modal
  $('#todoShowListItem').modal('show');
}

// Esta função converte quebras de linha em <br>
function nl2br(str, is_xhtml) {
  var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
  return (str + '').replace(/(\r\n|\n\r|\r|\n)/g, breakTag + '$1');
}

// Esta função converte caracteres especiais em entidades HTML
function htmlspecialchars(text) {
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>


   <script> 
      function gerarPdfTarefas() {
    window.open('gerar_pdf_tarefas.php', '_blank');
    }
   </script>

<?php if (isset($_SESSION['mostrar_mensagem_boas_vindas']) && $_SESSION['mostrar_mensagem_boas_vindas']): ?>
    <script>
        $(document).ready(function(){
            $('#modalBoasVindas').modal('show');
            <?php unset($_SESSION['mostrar_mensagem_boas_vindas']); // Remove a variável para não mostrar o modal novamente ?>
        });
    </script>
<?php endif; ?>



    <script src="assets/libs/jquery/dist/jquery.min.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery.ui.touch-punch-improved.js"></script>
    <script src="assets/extra-libs/taskboard/js/jquery-ui.min.js"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <!-- <script src="assets/libs/popper.js/popper.min.js"></script> -->
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
    <!--This page JavaScript -->
    <script src="assets/libs/quill/dist/quill.min.js"></script>
    <!-- Initialize Quill editor -->
    <script src="dist/js/pages/todo/todo.js"></script>

    <?php echo $script; // Imprime o script para exibir o modal de boas-vindas ?>
  </body>
</html>

<?php
session_start();
require 'db.php'; // Ajuste o caminho conforme necessário

// Verifique se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    // Se não estiver logado, retorne um array vazio em formato JSON
    echo json_encode([]);
    exit;
}

// Atribuição do ID do usuário logado e perfil de acesso
$usuario_id = $_SESSION['usuario_id'];
$perfilAcessoUsuario = $_SESSION['PerfilAcesso'] ?? null;

// Defina as cores de fundo e de texto para as categorias de eventos
$categoriasCores = [
    "Reunião presencial" => ["#1d5c96", "#e1e1e1"],   // Azul Escuro com texto cinza claro
    "Reunião online" => ["#276633", "#e1e1e1"],       // Verde Escuro com texto cinza claro
    "Reunião de planejamento" => ["#993333", "#e1e1e1"], // Vermelho Vinho com texto cinza claro
    "Evento" => ["#3b7078", "#e1e1e1"],               // Azul Petróleo com texto cinza claro
    "Curso" => ["#2b2b65", "#e1e1e1"],                // Azul Escuro (mais suave) com texto cinza claro
    "Tipo de operação" => ["#bf8c30", "#e1e1e1"],     // Ouro Velho com texto cinza claro
    "Treinamento" => ["#605da5", "#e1e1e1"],          // Roxo Médio com texto cinza claro
    "Happy Hour" => ["#bf6544", "#e1e1e1"],           // Marrom Terra com texto cinza claro
    "Confraternização" => ["#3d8080", "#e1e1e1"],     // Verde Azulado com texto cinza claro
    "Aniversário" => ["#7d5ba6", "#e1e1e1"],          // Roxo Lavanda com texto cinza claro
    "Lançamento de produto" => ["#bfbf30", "#e1e1e1"], // Verde Oliva com texto cinza claro
    // Adicione mais categorias e cores conforme necessário
];



// Definindo a consulta SQL baseada no perfil do usuário
switch ($perfilAcessoUsuario) {
    case 1:
    case 4:
        // Perfis 1 e 4 podem ver as agendas dos perfis 1, 2 e 4
        $sql = "SELECT e.id, e.titulo AS title, CONCAT(e.data_inicio, 'T', e.horario_inicio) AS start,
                e.data_fim AS end, e.descricao AS description, e.categoria, e.link, u.Nome AS usuarioNome
                FROM eventos e
                INNER JOIN usuarios u ON e.usuario_id = u.Id
                WHERE u.PerfilAcesso IN (1, 2, 4)";
        break;
    case 2:
        // Perfil 2 pode ver as agendas dos perfis 1, 2, 4 e 5
        $sql = "SELECT e.id, e.titulo AS title, CONCAT(e.data_inicio, 'T', e.horario_inicio) AS start,
                e.data_fim AS end, e.descricao AS description, e.categoria, e.link, u.Nome AS usuarioNome
                FROM eventos e
                INNER JOIN usuarios u ON e.usuario_id = u.Id
                WHERE u.PerfilAcesso IN (1, 2, 4, 5)";
        break;
    case 5:
        // Perfil 5 pode ver as agendas dos perfis 2 e 5
        $sql = "SELECT e.id, e.titulo AS title, CONCAT(e.data_inicio, 'T', e.horario_inicio) AS start,
                e.data_fim AS end, e.descricao AS description, e.categoria, e.link, u.Nome AS usuarioNome
                FROM eventos e
                INNER JOIN usuarios u ON e.usuario_id = u.Id
                WHERE u.PerfilAcesso IN (2, 5)";
        break;
    default:
        // Para segurança, se o perfil não for reconhecido, não retorna eventos
        echo json_encode([]);
        exit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute();
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($eventos as &$evento) {
    $categoria = $evento['categoria'];
    if (isset($categoriasCores[$categoria])) {
        $evento['color'] = $categoriasCores[$categoria][0];
        $evento['textColor'] = $categoriasCores[$categoria][1];
    } else {
        $evento['color'] = "#333333";
        $evento['textColor'] = "#ffffff";
    }
    // Adiciona o nome do usuário ao array do evento
    $evento['usuarioNome'] = $evento['usuarioNome'];
}

header('Content-Type: application/json');
echo json_encode($eventos);
?>

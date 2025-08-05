<?php
// Inclua seu arquivo de conexão com o banco de dados
include 'db.php';

// Checa se o ID foi enviado e se é um número
if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $osId = $_POST['id'];

    // Preparar a consulta SQL para buscar os detalhes da OS
    $query = $pdo->prepare("SELECT os.*, u.Nome as ResponsavelNome FROM os
                            LEFT JOIN usuarios u ON os.Responsavel = u.Id
                            WHERE os.Id = :id");
    $query->bindParam(':id', $osId, PDO::PARAM_INT);
    $query->execute();

    $os = $query->fetch(PDO::FETCH_ASSOC);

    if ($os) {
        // Formata datas
        if (!empty($os['Dt_inicial']) && $os['Dt_inicial'] != '0000-00-00') {
            $os['Dt_inicial'] = date('d/m/Y', strtotime($os['Dt_inicial']));
        }
        if (!empty($os['Prazo_entrega']) && $os['Prazo_entrega'] != '0000-00-00') {
            $os['Prazo_entrega'] = date('d/m/Y', strtotime($os['Prazo_entrega']));
        }

        // Define o responsável
        $os['Responsavel'] = $os['ResponsavelNome'] ?? 'Não especificado';
        unset($os['ResponsavelNome']);

        // Processar a descrição
        if (!empty($os['Descricao'])) {
            $descricaoOriginal = $os['Descricao'];

            // Converte <br> em quebras de linha
            $descricaoProcessada = preg_replace('/<br\s*\/?>/i', "\n", $descricaoOriginal);

            // Converte <p ... > em uma única quebra de linha no início do parágrafo
            // Remove as tags <p> e substitui por quebra de linha
            $descricaoProcessada = preg_replace('/<p[^>]*>/i', "\n", $descricaoProcessada);
            // Remove a tag </p> sem inserir novas quebras (apenas remove)
            $descricaoProcessada = str_replace('</p>', '', $descricaoProcessada);

            // Remove todas as tags HTML remanescentes
            $descricaoProcessada = strip_tags($descricaoProcessada);

            // Decodifica entidades HTML
            $descricaoProcessada = html_entity_decode($descricaoProcessada, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Remove espaços em excesso no início e no fim
            $descricaoProcessada = trim($descricaoProcessada);

            // Substitui múltiplas quebras de linha consecutivas por apenas uma
            $descricaoProcessada = preg_replace("/\n{2,}/", "\n\n", $descricaoProcessada);

            $os['Descricao'] = $descricaoProcessada;
        }

        // Envio dos detalhes da OS em formato JSON
        echo json_encode(['success' => true, 'data' => $os]);
    } else {
        // Nenhuma OS encontrada com o ID fornecido
        echo json_encode(['success' => false, 'error' => 'Nenhuma OS encontrada com o ID especificado.']);
    }
} else {
    // ID não fornecido ou inválido
    echo json_encode(['success' => false, 'error' => 'ID da OS não fornecido ou inválido.']);
}

<?php
require_once __DIR__ . '/vendor/autoload.php';
include 'db.php';

use setasign\Fpdi\Fpdi;

class PDF_HTML extends Fpdi {
    // Variáveis para armazenar o tamanho e a família atuais da fonte
    private $currentFontSize = 12;
    private $currentFontFamily = 'Arial';
    private $lineHeight = 5; // Reduzido para 5

    // Variáveis para controle de listas
    private $listType = null; // 'ol' ou 'ul'
    private $listLevel = 0;
    private $olCount = [];

    function WriteHTML($html) {
        // Substituir quebras de linha do tipo Windows (\r\n) ou Mac (\r) por Unix (\n)
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = str_replace(['<br>', '<br/>', '<br />'], "\n", $html);

        // Remover atributos data-* e class que podem estar causando problemas
        $html = preg_replace('/\sdata-[a-z-]+="[^"]*"/i', '', $html);
        $html = preg_replace('/\sclass="[^"]*"/i', '', $html);
        
        // Remover 'dir="ltr"' do HTML
        $html = preg_replace('/ dir="ltr"/i', '', $html);

        // Tratar os caracteres `uuid` e `tooltip` para manter formatação correta
        $html = str_replace('`uuid`', '<span style="font-family: Courier, monospace;">uuid</span>', $html);
        $html = str_replace('`tooltip`', '<span style="font-family: Courier, monospace;">tooltip</span>', $html);
        $html = str_replace('*tooltip*', '<i>tooltip</i>', $html);

        // Substituir tags <strong> por <b> e <em> por <i> para facilitar o processamento
        $html = str_ireplace(['<strong>', '</strong>'], ['<b>', '</b>'], $html);
        $html = str_ireplace(['<em>', '</em>'], ['<i>', '</i>'], $html);

        // Adicionar suporte para tags <code>
        $html = str_ireplace(['<code>', '</code>'], ['<span style="font-family: Courier, monospace;">', '</span>'], $html);

        // Converter <span style="text-decoration: underline;"> para <u>
        $html = preg_replace('/<span\s+style=["\']text-decoration:\s*underline;["\']>/i', '<u>', $html);
        $html = str_ireplace('</span>', '</u>', $html);

        // Decodificar entidades HTML para caracteres
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Procurar por linhas que começam com números seguidos de ponto (itens de lista ordenada)
        // e convertê-los explicitamente para o formato correto
        $linhas = explode("\n", $html);
        $novoHtml = '';
        
        foreach ($linhas as $linha) {
            // Checar se a linha começa com um número seguido de ponto e espaço (ex: "1. ")
            if (preg_match('/^\s*(\d+)\.\s+(.*)$/', $linha, $matches)) {
                $numero = $matches[1];
                $texto = $matches[2];
                // Substituir por marca específica para processar como item de lista numerada
                $novoHtml .= "[@NUM{$numero}@] " . $texto . "\n";
            } else {
                $novoHtml .= $linha . "\n";
            }
        }
        
        $html = $novoHtml;

        // Dividir o HTML em partes de texto e tags
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        // Inicializar flags para estilos
        $bold = false;
        $italic = false;
        $underline = false;
        $monospace = false;
        $inListItem = false;

        foreach ($parts as $part) {
            if (preg_match('/^<b>/i', $part)) {
                $bold = true;
                $this->SetFontStyle($bold, $italic, $underline, $monospace);
            } elseif (preg_match('/^<\/b>/i', $part)) {
                $bold = false;
                $this->SetFontStyle($bold, $italic, $underline, $monospace);
            } elseif (preg_match('/^<i>/i', $part)) {
                $italic = true;
                $this->SetFontStyle($bold, $italic, $underline, $monospace);
            } elseif (preg_match('/^<\/i>/i', $part)) {
                $italic = false;
                $this->SetFontStyle($bold, $italic, $underline, $monospace);
            } elseif (preg_match('/^<u>/i', $part)) {
                $underline = true;
                $this->SetFontStyle($bold, $italic, $underline, $monospace);
            } elseif (preg_match('/^<\/u>/i', $part)) {
                $underline = false;
                $this->SetFontStyle($bold, $italic, $underline, $monospace);
            } elseif (preg_match('/^<span style="font-family: Courier, monospace;">/i', $part)) {
                $monospace = true;
                $this->SetFontStyle($bold, $italic, $underline, $monospace);
            } elseif (preg_match('/^<\/span>/i', $part)) {
                $monospace = false;
                $this->SetFontStyle($bold, $italic, $underline, $monospace);
            } elseif (preg_match('/^<ol>/i', $part)) {
                $this->listType = 'ol';
                $this->listLevel++;
                $this->olCount[$this->listLevel] = 1; // Iniciar contador para este nível
            } elseif (preg_match('/^<\/ol>/i', $part)) {
                $this->listType = null;
                $this->listLevel--;
            } elseif (preg_match('/^<ul>/i', $part)) {
                $this->listType = 'ul';
                $this->listLevel++;
            } elseif (preg_match('/^<\/ul>/i', $part)) {
                $this->listType = null;
                $this->listLevel--;
            } elseif (preg_match('/^<li>/i', $part)) {
                $inListItem = true;
                $this->Ln(3); // Espaço antes do item
                $this->SetX($this->GetX() + 5); // Indentação
                
                if ($this->listType == 'ol') {
                    $itemNumber = $this->olCount[$this->listLevel];
                    $this->olCount[$this->listLevel]++;
                    $this->Write($this->lineHeight, $itemNumber . '. ');
                } else if ($this->listType == 'ul') {
                    $this->Write($this->lineHeight, '• ');
                }
            } elseif (preg_match('/^<\/li>/i', $part)) {
                $inListItem = false;
                $this->Ln($this->lineHeight); // Quebra de linha após o item
            } elseif (preg_match('/^<p>/i', $part)) {
                if (!$inListItem) {
                    $this->Ln(3); // Espaço antes do parágrafo
                }
            } elseif (preg_match('/^<\/p>/i', $part)) {
                if (!$inListItem) {
                    $this->Ln(3); // Espaço após o parágrafo
                }
            } elseif (preg_match('/^<img/i', $part)) {
                // Código para inserir imagem, mantido igual
                preg_match('/src=["\']([^"\']+)["\']/', $part, $srcMatch);
                if (isset($srcMatch[1])) {
                    $imgSrc = $srcMatch[1];
                    $this->Ln(3);
                    
                    // Verificar se é uma imagem base64
                    if (strpos($imgSrc, 'data:image/') === 0) {
                        preg_match('/^data:image\/(\w+);base64,/', $imgSrc, $imageType);
                        $imgData = preg_replace('/^data:image\/\w+;base64,/', '', $imgSrc);
                        $imgData = base64_decode($imgData);
                        $tempImagePath = sys_get_temp_dir() . '/temp_image.' . strtolower($imageType[1]);
                        file_put_contents($tempImagePath, $imgData);
                        $this->InsertImage($tempImagePath);
                        unlink($tempImagePath);
                    } else {
                        $localImagePath = $this->baixar_imagem($imgSrc);
                        if ($localImagePath) {
                            $this->InsertImage($localImagePath);
                            unlink($localImagePath);
                        } else {
                            $this->Write($this->lineHeight, 'Imagem não disponível');
                        }
                    }
                    $this->Ln(3);
                }
            } elseif (preg_match('/^<br\s*\/?>$/i', $part)) {
                $this->Ln(3);
            } else {
                // Procurar por marcação especial de item numerado [@NUMx@]
                if (preg_match('/\[@NUM(\d+)@\]\s+(.*)/', $part, $matches)) {
                    $numeroItem = $matches[1];
                    $textoItem = $matches[2];
                    
                    // Iniciar um novo parágrafo
                    $this->Ln(3);
                    
                    // Indentação para o item de lista
                    $this->SetX($this->GetX() + 5);
                    
                    // Escrever o número do item
                    $this->Write($this->lineHeight, $numeroItem . '. ');
                    
                    // Escrever o texto do item
                    $this->Write($this->lineHeight, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $textoItem));
                    
                    // Quebra de linha após o item
                    $this->Ln(3);
                } else {
                    // Escrever o texto normal com a formatação atual
                    $lines = explode("\n", $part);
                    foreach ($lines as $lineIndex => $line) {
                        if ($lineIndex > 0) {
                            $this->Ln();
                        }
                        if (trim($line) !== '') {
                            $this->Write($this->lineHeight, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $line));
                        }
                    }
                }
            }
        }
    }

    // Função auxiliar para definir o estilo da fonte com base nas flags
    private function SetFontStyle($bold, $italic, $underline, $monospace = false) {
        $style = '';
        if ($bold) {
            $style .= 'B';
        }
        if ($italic) {
            $style .= 'I';
        }
        if ($underline) {
            $style .= 'U';
        }

        // Escolher a fonte baseado no flag monospace
        $fontFamily = $monospace ? 'Courier' : $this->currentFontFamily;

        // Manter o tamanho da fonte consistente
        $this->SetFont($fontFamily, $style, $this->currentFontSize);
    }

    // Sobrescrever o método SetFont para capturar o tamanho da fonte e a família atual
    function SetFont($family, $style = '', $size = 12) {
        $this->currentFontFamily = $family;
        $this->currentFontSize = $size;
        parent::SetFont($family, $style, $size);
    }

    // Função para baixar a imagem e salvar localmente com extensão correta
    private function baixar_imagem($url) {
        // Verifica se a URL é válida
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Obtém o conteúdo da imagem
        $conteudo = @file_get_contents($url);
        if ($conteudo === false) {
            return false;
        }

        // Determina o tipo MIME da imagem
        $info = getimagesizefromstring($conteudo);
        if ($info === false) {
            return false;
        }

        // Mapeia o tipo MIME para a extensão do arquivo
        $extensao = '';
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $extensao = 'jpg';
                break;
            case IMAGETYPE_PNG:
                $extensao = 'png';
                break;
            case IMAGETYPE_GIF:
                $extensao = 'gif';
                break;
            default:
                return false; // Tipo de imagem não suportado
        }

        // Gera um nome de arquivo único
        $nome_arquivo = uniqid('imagem_', true) . '.' . $extensao;

        // Define o caminho completo para salvar a imagem
        $caminho_arquivo = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nome_arquivo;

        // Salva a imagem no caminho especificado
        $salvo = @file_put_contents($caminho_arquivo, $conteudo);
        if ($salvo === false) {
            return false;
        }

        return $caminho_arquivo;
    }

    // Função para inserir imagens com tamanho original, ajustando se necessário e alinhando à esquerda
    private function InsertImage($filePath) {
        // Verificar se o arquivo existe
        if (!file_exists($filePath)) {
            $this->Write($this->lineHeight, 'Imagem não encontrada.');
            return;
        }

        // Obter as dimensões da imagem em pixels
        list($widthPx, $heightPx) = getimagesize($filePath);

        // Definir DPI (assumindo 96 DPI)
        $dpi = 96;

        // Converter pixels para milímetros
        $widthMm = ($widthPx / $dpi) * 25.4;
        $heightMm = ($heightPx / $dpi) * 25.4;

        // Obter as dimensões da página
        $pageWidth = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
        $pageHeight = $this->GetPageHeight() - $this->tMargin - $this->bMargin;

        // Calcular escala para ajustar a imagem dentro da página, se necessário
        $scale = 1;
        if ($widthMm > $pageWidth || $heightMm > $pageHeight) {
            $scale = min($pageWidth / $widthMm, $pageHeight / $heightMm);
        }

        // Aplicar a escala
        $finalWidth = $widthMm * $scale;
        $finalHeight = $heightMm * $scale;

        // Definir posição alinhada à esquerda
        $x = $this->lMargin;
        $y = $this->GetY(); // Manter a posição Y atual

        // Inserir a imagem
        $this->Image($filePath, $x, $y, $finalWidth, $finalHeight);

        // Atualizar a posição Y para após a imagem
        $this->SetY($y + $finalHeight + 5); // 5mm de espaçamento após a imagem
    }
}

$osId = $_GET['osId'];

// Verificar se o parâmetro osId está definido e é um número inteiro
if (!isset($osId) || !ctype_digit($osId)) {
    die("ID da OS inválido.");
}

// Buscar dados da OS
$stmt = $pdo->prepare("
    SELECT o.*, u.Nome as NomeResponsavel, c.Nome as NomeContratada
    FROM os o
    LEFT JOIN usuarios u ON o.Responsavel = u.Id
    LEFT JOIN contratadas c ON o.Id_contratada = c.Id
    WHERE o.Id = :osId
");
$stmt->bindParam(':osId', $osId, PDO::PARAM_INT);

if (!$stmt->execute()) {
    die("Erro na consulta SQL");
}

$osData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$osData) {
    die("Dados da OS não encontrados");
}

// Criar instância do PDF_HTML
$pdf = new PDF_HTML('P', 'mm', 'A4');
$leftMargin = 10;
$rightMargin = 10;
$topMargin = 10;
$pdf->SetMargins($leftMargin, $topMargin, $rightMargin);
$pdf->AddPage();

// Inclua a logomarca
$logoPath = 'path/to/fpdf/img/logo.jpg'; // Ajuste o caminho para o seu logo
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 10, 10, 33);
    $pdf->Ln(20); // Espaço após o logo
} else {
    $pdf->Ln(53); // Espaço reservado
}

// Cabeçalho
$pdf->SetFont('Arial', 'B', 17);
$pdf->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'REGISTRO DE ORDEM DE SERVIÇO'), 0, 1, 'C');
$pdf->Ln(3); // Espaço reduzido

// Número e Data da OS
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(216, 216, 216);
$pdf->Cell(95, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $osData['N_os']), 1, 0, 'C', true);
$pdf->Cell(95, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'DATA DE ABERTURA: ' . date('d/m/Y', strtotime($osData['Dt_inicial']))), 1, 1, 'C', true);
$pdf->Ln(3); // Espaço reduzido

// Prioridade
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169); // Cor de preenchimento cinza
$pdf->Cell(150, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'PRIORIDADE'), 1, 0, 'C', true);
$pdf->SetFillColor(216, 216, 216);

$prioridade = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $osData['Prioridade']);
$pdf->Cell(40, 8, $prioridade, 1, 1, 'C', true);
$pdf->Ln(3); // Espaço reduzido

// Nome da OS
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169);
$pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'NOME DA ORDEM DE SERVIÇO'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 12);
$pdf->SetFillColor(255, 255, 255); // Branco
$pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $osData['Nome_os']), 1, 1, 'L', true);
$pdf->Ln(3); // Espaço reduzido

// Descrição da OS
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(169, 169, 169);
$pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'DESCRIÇÃO DA ORDEM DE SERVIÇO'), 1, 1, 'C', true);
$pdf->Ln(5); // Espaço reduzido
$pdf->SetFont('Arial', '', 10);
$pdf->SetFillColor(255, 255, 255);

// Obter a descrição e decodificar entidades HTML
$descricao = $osData['Descricao'];
$descricao = html_entity_decode($descricao, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Pré-processamento para listas numeradas - procura padrões como "1." e os formata
$descricao = preg_replace('/(\n\s*)(\d+)\.(\s+)/', "$1[@NUM$2@]$3", $descricao);

// Escrever a descrição interpretando o HTML
$pdf->WriteHTML($descricao);

// Buscar anexos relacionados à OS
$stmtAnexos = $pdo->prepare("SELECT arquivo FROM os_anexos WHERE os_id = :osId");
$stmtAnexos->bindParam(':osId', $osId, PDO::PARAM_INT);
$stmtAnexos->execute();
$anexos = $stmtAnexos->fetchAll(PDO::FETCH_COLUMN);

// Incluir os anexos
if ($anexos) {
    foreach ($anexos as $anexo) {
        $filePath = $anexo; // Ajuste o caminho conforme necessário

        if (file_exists($filePath)) {
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
                $pdf->AddPage(); // Adicionar uma nova página para a imagem
                $pdf->InsertImage($filePath);
            } elseif ($fileExtension === 'pdf') {
                try {
                    $pageCount = $pdf->setSourceFile($filePath);
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        $templateId = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($templateId);

                        $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                        $pdf->AddPage($orientation, 'A4');

                        $pageWidth = $pdf->GetPageWidth();
                        $pageHeight = $pdf->GetPageHeight();

                        $scale = min($pageWidth / $size['width'], $pageHeight / $size['height']);
                        $width = $size['width'] * $scale;
                        $height = $size['height'] * $scale;
                        $x = ($pageWidth - $width) / 2;
                        $y = ($pageHeight - $height) / 2;

                        $pdf->useTemplate($templateId, $x, $y, $width, $height);
                    }
                } catch (Exception $e) {
                    $pdf->SetFont('Arial', 'B', 12);
                    $pdf->Cell(0, 10, 'Erro ao importar o PDF: ' . $filePath, 0, 1, 'C');
                }
            }
        }
    }
}

// Gerar o PDF
$pdfFileName = 'OS_' . $osData['N_os'] . '.pdf';
$pdf->Output('I', $pdfFileName);
?>
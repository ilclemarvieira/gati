-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 10-Abr-2024 às 19:05
-- Versão do servidor: 10.4.27-MariaDB
-- versão do PHP: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `inovaerp`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `os`
--

CREATE TABLE `os` (
  `Id` int(11) NOT NULL,
  `N_os` varchar(255) DEFAULT NULL,
  `Nome_os` varchar(255) DEFAULT NULL,
  `Apf` varchar(255) DEFAULT NULL,
  `Valor` decimal(10,2) DEFAULT NULL,
  `Dt_inicial` date DEFAULT NULL,
  `Prazo_entrega` date NOT NULL,
  `Prioridade` enum('Baixa','Média','Alta') DEFAULT NULL,
  `Status_inova` varchar(255) DEFAULT NULL,
  `Status_contratada` varchar(255) DEFAULT NULL,
  `Responsavel` int(11) DEFAULT NULL,
  `Id_contratada` int(11) DEFAULT NULL,
  `Descricao` varchar(2550) DEFAULT NULL,
  `Os_paga` tinyint(1) DEFAULT NULL,
  `Anexo_nf` varchar(255) DEFAULT NULL,
  `Observacao` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `os`
--

INSERT INTO `os` (`Id`, `N_os`, `Nome_os`, `Apf`, `Valor`, `Dt_inicial`, `Prazo_entrega`, `Prioridade`, `Status_inova`, `Status_contratada`, `Responsavel`, `Id_contratada`, `Descricao`, `Os_paga`, `Anexo_nf`, `Observacao`) VALUES
(9, '04-2023', 'AJUSTE NO TIPO DE OPERAÇÃO NA PROGRAMAÇÃO OPERACIONAL', '15', '10990.80', '2023-11-14', '2023-11-15', 'Alta', 'Aguardando APF', 'Não Começou', 1, 1, 'FLAG dentro do banco de dados para vínculo da programação operacional a cada ocorrência.\r\n\r\nPROBLEMA: Não ser possível identificar com exatidão quais ocorrências fazem parte de uma programação operacional.\r\n\r\n\r\nSOLUÇÃO: Adicionar dentro do banco de dados uma coluna que indique que a ocorrência faz parte de uma programação operacional.\r\n\r\n\r\nJUSTIFICATIVA: Fazendo o ajuste será possível fazer análises precisas dentro do BI de indicadores relacionados a programação operacional.\r\n\r\n', 1, NULL, ''),
(10, '05-2023', 'CADASTRO DE FUNÇÕES', '120', '87926.40', '2023-01-22', '2023-11-22', 'Baixa', 'Aguardando APF', 'Em Produção', 1, 1, 'CRIAR LOCAL PARA VISUALIZAÇÃO DAS ABORDAGENS QUALIFICADAS\r\nPROBLEMA: Dificuldade para achar as abordagens qualificadas dentro do SADE.\r\nSOLUÇÃO: Inserir na aba de “Ocorrências -> Consultas ->” o campo para acesso às abordagens qualificadas. Trazer os filtros conforme imagem a seguir.\r\nJUSTIFICATIVA: Assim será possível que os batalhões e setores interessados acessem e utilizem a informação.', 1, NULL, ''),
(14, '08-2023', 'ATUALIZAÇÃO DO FORMULÁRIO - FRIDA', '66.81', '48953.02', '2023-12-12', '2024-01-02', 'Média', 'Aguardando APF', 'Não Começou', 1, 1, 'Atualizar o formulário de acordo com o Formulário Nacional de Avaliação de Risco (ANEXO). \r\n\r\nConsiderando que o formulário que consta no PMSC Mobile está desatualizado. O arquivo é dividido em blocos, sendo assim o PMSC Mobile deve usar a mesma lógica para agrupar as perguntas e ao passar de bloco, salvar automaticamente. Além disso, os dados cadastrais da vítima de agressor devem permitir a consulta via sistema de consultas.\r\n\r\nBLOCO 1 – DADOS DO FATO \r\n- Identificação das Partes Identificação da vítima \r\nListar histórico do último formulário de avaliação de risco da vítima com a data. (Caso exista e se o agressor for o mesmo, será preenchido automaticamente (com base no último) a partir do bloco 2, com opção de editar). \r\n\r\nIdentificação do agressor \r\nVínculo entre vítima e o agressor: Companheiro(a), Casado(a), União Estável, Namorado(a) e Separados(as). \r\n\r\nDados Iniciais [virem autopreenchidas exceto responsável pelas respostas] \r\nUnidade: \r\nFato: Data: \r\nHora: Endereço do fato: \r\nResponsável pelas respostas: \r\n\r\n- Terceiro comunicante [abrir campo para informar os dados do terceiro comunicante: nome completo, cpf, telefone, endereço]\r\n- Vítima [nome da vítima] \r\n- Vítima recusou-se a preencher o formulário.', 0, NULL, ''),
(32, '01-2024', 'Inclusão da edição do AIT', '38.42', '28151.10', '2024-01-02', '2024-01-16', 'Baixa', 'Finalizado', 'Em Homologação', 5, 1, '1 - Edição do AIT\r\n\r\n2 - Aumentar para 4 fotos do veículo. (OK)\r\n\r\n3 - Acrescentar no RRDT um checkbox na parte de documentação recolhida para informar se é documento digital (em ambos os formulários) (OK)\r\n\r\n4 - Após selecionar um orgão autuador, salvar no cache e perguntar se deseja reutilizar. (OK)\r\n\r\n5 - Permitir reutilizar a assinatura do PM somente dentro de 30 minutos após o ultimo AIT. (OK)', 1, NULL, ''),
(33, '02-2024', 'AJUSTE NO AIT (PREVIEW DA IMPRESSÃO E COPIAR AIT)', '19.84', '14537.16', '2024-01-02', '2024-01-17', 'Média', 'Finalizado', 'Em Homologação', 5, 1, 'Remover mídias do agrupador de veículos e adicionar nos agrupadores de dados finais e observações.\r\n\r\nGerar uma imagem com o preview da impressão.\r\n\r\nCriar demais providências a partir do copiar AIT', 1, NULL, ''),
(35, '03-2024', 'ATUALIZAÇÃO DO NOVO AIT E RRDT', '49.88', '36548.07', '2023-12-31', '2024-02-17', 'Alta', 'Finalizado', 'Em Produção', 1, 1, 'AIT - Caso o condutor não seja abordado, esconder agrupador de infrator;\r\nHome - Composição da Guarnição;\r\nHome - Troca de Efetivo Responsável;\r\nProvidências - Botões anular e salvar ficarem acima do teclado;\r\nProvidências - Histórico de Observações e Enquadramentos;\r\nProvidências - Implementar aviso de necessidade de impressão das providências;\r\nProvidências - Persistir informação do condutor proprietário para reutilizar dados do condutor nas demais providências\r\npara persistir dados do proprietário;\r\nProvidências - Remover midias do agrupador de veículos e adicionar nos agrupadores de dados finais e observações;\r\nProvidências - Tornar código das providências copiável;\r\nRRDT - Criar endpoints para listar/salvar liberação do documento recolhido;\r\nRRDT - Refatoração da documentação recolhida.', 1, NULL, ''),
(53, '04-2024', 'Ajustar no SADE os fatos de TC envolvendo Criança e/ou Adolescente como vítima', '3.20', '2344.70', '2024-02-06', '2024-02-20', 'Alta', 'Em Desenvolvimento', 'Em Produção', 1, 1, 'Providenciar para que todas as ocorrências envolvendo Criança e/ou Adolescente como vítima, não seja permitido finalizar como BO-TC.', 0, NULL, ''),
(54, '05-2024', 'Alterar a nomenclatura de Acidente de Trânsito para Sinistro de Trânsito', '6.40', '4689.41', '2024-02-27', '2024-03-12', 'Alta', 'Aprovada', 'Em Desenvolvimento', 1, 1, 'Conforme estabelecido pelo Código de Trânsito Brasileiro (CTB), a terminologia \"Acidente de Trânsito\" será substituída por \"Sinistro de Trânsito\". Diante disso, solicito a alteração dessa nomenclatura em todas as plataformas - Mobile, SADE, Gestão e AIT - substituindo \"Acidente de Trânsito\" por \"Sinistro de Trânsito\".', 0, NULL, ''),
(55, '06-2024', 'Regra permitir chegada prog. operacionais e alertas sonoro', '4.96', '3634.29', '2024-01-14', '2024-03-11', 'Média', 'Aprovada', 'Em Homologação', 1, 1, 'Foi solicitado a alteração do som de alerta das ocorrências e das programações operacionais. Além disso, dentro da mesma Ordem de Serviço, solicitou-se a implementação de um popup de alerta para quando o usuário tentasse realizar o procedimento de J10 na programação operacional antes do horário previsto.', 0, NULL, ''),
(56, '07-2024', 'Geração de ocorrência através de API (PMSC Policial)', '27.40', '20076.53', '2024-01-02', '2024-02-07', 'Média', 'Aprovada', 'Em Homologação', 1, 1, 'Objetivo\r\n- O propósito é habilitar a criação de ocorrências no SADE por meio de uma API dedicada. Esta especificação busca assegurar que a API atenda às exigências da PMSC, possibilitando a criação eficiente e segura de ocorrências no SADE por meio de integrações externas.\r\n\r\nGerenciamento de Usuários\r\n- Criação de Usuários: O SADE deve permitir a criação de usuários específicos para a API, com a possibilidade de definir senhas.\r\n- Atualização de Usuários: Deve ser possível atualizar as informações de usuários existentes, incluindo a troca de senhas e a ativação/desativação de contas conforme necessário.\r\n- Permissões: Os usuários criados pelo SADE devem possuir as permissões adequadas para acessar e usar as funcionalidades necessárias para a geração de ocorrências.\r\n\r\nAutenticação e Autorização\r\n- A API deve oferecer um mecanismo seguro de autenticação e autorização, garantindo que apenas usuários autorizados tenham acesso e possam usar os recursos da API.\r\n- A autenticação dos usuários autorizados deve ser realizada utilizando credenciais específicas fornecidas pela PMSC.\r\n\r\nGeração de Ocorrência\r\n- Criação via API: Deve ser possível criar uma nova ocorrência no SADE por meio da API, com a inclusão de todos os dados necessários para o registro da ocorrência.\r\n\r\n- Dados Obrigatórios: Os dados obrigatórios para a criação de uma ocorrência devem incluir, no mínimo, detalhes sobre a natureza da ocorrência, localização, data e hora, descrição e outras informações pertinentes conforme requerido pela PMSC.\r\n\r\nEsta especificação visa assegurar a implementação eficaz e segura das funcionalidades de geração de ocorrências, respeitando as necessidades e os padrões de segurança exigidos pela PMSC.', 0, 'uploads/SADE PMSC - API Geração de Ocorrências.pdf', ''),
(58, '08-2024', 'PMSC Trânsito  Impressão, preview, fila de comandos e PDFs', '45.24', '33148.25', '2024-03-18', '2024-03-18', 'Baixa', 'Aprovada', 'Em Homologação', 1, 1, 'PMSC Trânsito - Impressão, preview, fila de comandos e PDFs\r\n', 0, NULL, ''),
(60, '09-2024', 'asdsad', '20', '14654.40', '2024-03-23', '2024-03-23', 'Baixa', 'Aguardando PF', 'Não Começou', 1, 1, 'asdad', 0, NULL, ''),
(61, '10-2024', 'asd', '0', '0.00', '2024-03-24', '0000-00-00', 'Baixa', 'Aguardando PF', 'Não Começou', 1, 1, 'asdad', 0, NULL, '');

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `os`
--
ALTER TABLE `os`
  ADD PRIMARY KEY (`Id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `os`
--
ALTER TABLE `os`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

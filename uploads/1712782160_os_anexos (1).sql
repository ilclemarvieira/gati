-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 10-Abr-2024 às 22:20
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
-- Estrutura da tabela `os_anexos`
--

CREATE TABLE `os_anexos` (
  `id` int(11) NOT NULL,
  `os_id` int(11) NOT NULL,
  `arquivo` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `os_anexos`
--

INSERT INTO `os_anexos` (`id`, `os_id`, `arquivo`) VALUES
(1, 62, 'uploads/1712769202_os (9).sql'),
(2, 62, 'uploads/1712769202_os_anexos.sql'),
(3, 62, 'uploads/1712773539_os (9).sql'),
(4, 62, 'uploads/1712773625_jogo-gerado-08-04-2024-141219.pdf'),
(5, 56, 'uploads/1712773959_[PMSC] APF-291 Tela controle envio de Montas.pdf'),
(6, 56, 'uploads/1712776724_[PMSC] APF-291 Tela controle envio de Montas_revisada.pdf'),
(7, 62, 'uploads/1712778632_[PMSC] APF-291 Tela controle envio de Montas_revisada.pdf'),
(8, 61, 'uploads/1712780223_os (9).sql'),
(9, 61, 'uploads/1712780223_os_anexos.sql');

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `os_anexos`
--
ALTER TABLE `os_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `os_id` (`os_id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `os_anexos`
--
ALTER TABLE `os_anexos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `os_anexos`
--
ALTER TABLE `os_anexos`
  ADD CONSTRAINT `os_anexos_ibfk_1` FOREIGN KEY (`os_id`) REFERENCES `os` (`Id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

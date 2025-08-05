-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 09-Abr-2024 às 21:31
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
-- Estrutura da tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `Id` int(11) NOT NULL,
  `Nome` varchar(255) DEFAULT NULL,
  `Cpf` varchar(14) DEFAULT NULL,
  `E_mail` varchar(255) DEFAULT NULL,
  `Senha` char(255) DEFAULT NULL,
  `bloqueado` tinyint(4) NOT NULL DEFAULT 2,
  `PerfilAcesso` int(11) NOT NULL DEFAULT 0,
  `TokenExpiracao` datetime DEFAULT NULL,
  `Token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `usuarios`
--

INSERT INTO `usuarios` (`Id`, `Nome`, `Cpf`, `E_mail`, `Senha`, `bloqueado`, `PerfilAcesso`, `TokenExpiracao`, `Token`) VALUES
(1, 'Ilclemar', '042.864.275-60', 'ilclemarvieira@gmail.com', '$2y$10$xkeBKO5/PJW5sDtYJIg.NuvzCo7O3sGSCZhZNLhtcAiWiIakh9XTe', 2, 1, '2024-03-27 18:31:39', '4099e948d16f7d2fa0149968cfb336c03ceaa01c91a1713d3434d7479a4b30679f80bca8089330a0ff13ff40751535583661'),
(3, 'Alex Oliveira', '000.000.000-00', 'alexoliveira@gmail.com', '$2y$10$Pq/dC26WnA.4a8YeGkSIyeaZdOn5P0YYNai6QvsEJnBjnO1ZRwB8.', 2, 4, '2024-03-27 18:21:00', 'e1c005ab3900a6f14bf953327f7c61464cdd4900ad33d2cfeb7be8a6dff1bd0d91639713907a30f09d4c0652f55bd3eb2e21'),
(4, 'Mariana', '678.789.999-36', 'mariana@gmail.com', '$2y$10$AZX2rPUK8wmdtkXYls7mAufHrRxaZaUadghyiX11M0VITiztcjgi6', 2, 6, NULL, NULL),
(5, 'Mazzola', '000.000.000-01', 'mazzola@gmail.com', '$2y$10$RBNAt7zJ3LHsYfickyA0Me7rc5bHVqOsOwzrwOuVaU1XKnKJnLM96', 2, 4, NULL, NULL),
(14, 'Thiesen', '010.101.010-10', 'thiesen@gmail.com', '$2y$10$OtsuzvVNHtJ5PPKKMiCBKeHDRJZf3p1fWamRNe4ZmnOpork47Bdqq', 2, 2, NULL, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`Id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

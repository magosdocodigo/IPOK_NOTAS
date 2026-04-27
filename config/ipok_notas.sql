-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 22/04/2026 às 00:26
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `ipok_notas`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos`
--

CREATE TABLE `alunos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `numero_matricula` varchar(20) NOT NULL,
  `data_matricula` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN01 — Dados específicos dos alunos';

-- --------------------------------------------------------

--
-- Estrutura para tabela `atribuicoes`
--

CREATE TABLE `atribuicoes` (
  `id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `turma_disciplina_id` int(11) NOT NULL,
  `ano_letivo` year(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN05/RN08 — Associação professor ↔ turma ↔ disciplina';

-- --------------------------------------------------------

--
-- Estrutura para tabela `disciplinas`
--

CREATE TABLE `disciplinas` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enturmacoes`
--

CREATE TABLE `enturmacoes` (
  `id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `data_enturmacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN10 — Só alunos enturmados podem receber notas';

-- --------------------------------------------------------

--
-- Estrutura para tabela `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `identificador` varchar(100) DEFAULT NULL COMMENT 'username ou nº matrícula tentado',
  `tentativa_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN02 — Rate limiting para login';

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_auditoria`
--

CREATE TABLE `logs_auditoria` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(50) NOT NULL COMMENT 'Ex: INSERIR_NOTA, EDITAR_NOTA, ABRIR_PERIODO, FECHAR_PERIODO',
  `tabela` varchar(50) NOT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `dados_antigos` text DEFAULT NULL COMMENT 'JSON com valores anteriores',
  `dados_novos` text DEFAULT NULL COMMENT 'JSON com valores novos',
  `data_hora` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN05/RN09/RN10 — Auditoria completa de acções sensíveis';

-- --------------------------------------------------------

--
-- Estrutura para tabela `notas`
--

CREATE TABLE `notas` (
  `id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `disciplina_id` int(11) NOT NULL,
  `ano_letivo` year(4) NOT NULL,
  `trimestre` tinyint(1) NOT NULL,
  `nota_trimestre` decimal(4,1) DEFAULT NULL,
  `media_final` decimal(4,1) GENERATED ALWAYS AS (`nota_trimestre`) STORED COMMENT 'A média final do trimestre é a própria nota lançada',
  `estado` varchar(10) GENERATED ALWAYS AS (case when `nota_trimestre` is not null then if(`nota_trimestre` >= 10,'Aprovado','Reprovado') else NULL end) STORED,
  `ultima_edicao_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultima_edicao_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `periodos`
--

CREATE TABLE `periodos` (
  `id` int(11) NOT NULL,
  `ano_letivo` year(4) NOT NULL,
  `trimestre` tinyint(1) NOT NULL COMMENT '1, 2 ou 3 — RN03',
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `status` enum('aberto','fechado') NOT NULL DEFAULT 'fechado' COMMENT 'RN06 — Só admin altera',
  `aberto_por` int(11) DEFAULT NULL COMMENT 'Utilizador que abriu',
  `fechado_por` int(11) DEFAULT NULL COMMENT 'Utilizador que fechou',
  `aberto_em` datetime DEFAULT NULL,
  `fechado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professores`
--

CREATE TABLE `professores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `codigo_funcionario` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Dados específicos dos professores';

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas`
--

CREATE TABLE `turmas` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `ano_letivo` year(4) NOT NULL,
  `curso` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `turma_disciplina`
--

CREATE TABLE `turma_disciplina` (
  `id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `disciplina_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel` enum('admin','professor','aluno') NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN01 — Utilizadores do sistema com perfis distintos';

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_notas_aluno`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_notas_aluno` (
`aluno_id` int(11)
,`disciplina_id` int(11)
,`disciplina` varchar(100)
,`ano_letivo` year(4)
,`trimestre` tinyint(1)
,`nota_trimestre` decimal(4,1)
,`media_final` decimal(4,1)
,`estado` varchar(10)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_notas_professor`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_notas_professor` (
`professor_id` int(11)
,`nome_aluno` varchar(100)
,`numero_matricula` varchar(20)
,`disciplina` varchar(100)
,`turma` varchar(50)
,`ano_letivo` year(4)
,`trimestre` tinyint(1)
,`nota_trimestre` decimal(4,1)
,`media_final` decimal(4,1)
,`estado` varchar(10)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_notas_aluno`
--
DROP TABLE IF EXISTS `vw_notas_aluno`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_notas_aluno`  AS SELECT `n`.`aluno_id` AS `aluno_id`, `n`.`disciplina_id` AS `disciplina_id`, `d`.`nome` AS `disciplina`, `n`.`ano_letivo` AS `ano_letivo`, `n`.`trimestre` AS `trimestre`, `n`.`nota_trimestre` AS `nota_trimestre`, `n`.`media_final` AS `media_final`, `n`.`estado` AS `estado` FROM ((`notas` `n` join `disciplinas` `d` on(`d`.`id` = `n`.`disciplina_id`)) join `periodos` `p` on(`p`.`ano_letivo` = `n`.`ano_letivo` and `p`.`trimestre` = `n`.`trimestre` and `p`.`status` = 'fechado')) ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_notas_professor`
--
DROP TABLE IF EXISTS `vw_notas_professor`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_notas_professor`  AS SELECT `a_prof`.`professor_id` AS `professor_id`, `u`.`nome` AS `nome_aluno`, `al`.`numero_matricula` AS `numero_matricula`, `d`.`nome` AS `disciplina`, `t`.`nome` AS `turma`, `n`.`ano_letivo` AS `ano_letivo`, `n`.`trimestre` AS `trimestre`, `n`.`nota_trimestre` AS `nota_trimestre`, `n`.`media_final` AS `media_final`, `n`.`estado` AS `estado` FROM (((((((`notas` `n` join `alunos` `al` on(`al`.`id` = `n`.`aluno_id`)) join `usuarios` `u` on(`u`.`id` = `al`.`usuario_id`)) join `disciplinas` `d` on(`d`.`id` = `n`.`disciplina_id`)) join `enturmacoes` `e` on(`e`.`aluno_id` = `al`.`id`)) join `turmas` `t` on(`t`.`id` = `e`.`turma_id`)) join `turma_disciplina` `td` on(`td`.`turma_id` = `t`.`id` and `td`.`disciplina_id` = `d`.`id`)) join `atribuicoes` `a_prof` on(`a_prof`.`turma_disciplina_id` = `td`.`id`)) ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alunos`
--
ALTER TABLE `alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_matricula` (`numero_matricula`),
  ADD UNIQUE KEY `uq_usuario_id` (`usuario_id`);

--
-- Índices de tabela `atribuicoes`
--
ALTER TABLE `atribuicoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_atribuicao` (`professor_id`,`turma_disciplina_id`,`ano_letivo`),
  ADD KEY `idx_turma_disciplina_id` (`turma_disciplina_id`);

--
-- Índices de tabela `disciplinas`
--
ALTER TABLE `disciplinas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_codigo` (`codigo`);

--
-- Índices de tabela `enturmacoes`
--
ALTER TABLE `enturmacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_aluno_turma` (`aluno_id`,`turma_id`),
  ADD KEY `idx_turma_id` (`turma_id`);

--
-- Índices de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip`),
  ADD KEY `idx_tentativa_em` (`tentativa_em`);

--
-- Índices de tabela `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_id` (`usuario_id`),
  ADD KEY `idx_data_hora` (`data_hora`),
  ADD KEY `idx_acao` (`acao`);

--
-- Índices de tabela `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nota` (`aluno_id`,`disciplina_id`,`ano_letivo`,`trimestre`),
  ADD KEY `idx_disciplina_id` (`disciplina_id`),
  ADD KEY `idx_ultima_edicao_por` (`ultima_edicao_por`),
  ADD KEY `idx_ano_trimestre` (`ano_letivo`,`trimestre`);

--
-- Índices de tabela `periodos`
--
ALTER TABLE `periodos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_periodo` (`ano_letivo`,`trimestre`),
  ADD KEY `fk_periodos_aberto_por` (`aberto_por`),
  ADD KEY `fk_periodos_fechado_por` (`fechado_por`);

--
-- Índices de tabela `professores`
--
ALTER TABLE `professores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuario_id` (`usuario_id`),
  ADD UNIQUE KEY `uq_codigo_funcionario` (`codigo_funcionario`);

--
-- Índices de tabela `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_turma` (`nome`,`ano_letivo`);

--
-- Índices de tabela `turma_disciplina`
--
ALTER TABLE `turma_disciplina`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_turma_disciplina` (`turma_id`,`disciplina_id`),
  ADD KEY `idx_disciplina_id` (`disciplina_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD UNIQUE KEY `uq_username` (`username`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos`
--
ALTER TABLE `alunos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `atribuicoes`
--
ALTER TABLE `atribuicoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `disciplinas`
--
ALTER TABLE `disciplinas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `enturmacoes`
--
ALTER TABLE `enturmacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notas`
--
ALTER TABLE `notas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `periodos`
--
ALTER TABLE `periodos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `professores`
--
ALTER TABLE `professores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `turma_disciplina`
--
ALTER TABLE `turma_disciplina`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `alunos`
--
ALTER TABLE `alunos`
  ADD CONSTRAINT `fk_alunos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `atribuicoes`
--
ALTER TABLE `atribuicoes`
  ADD CONSTRAINT `fk_atribuicoes_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_atribuicoes_td` FOREIGN KEY (`turma_disciplina_id`) REFERENCES `turma_disciplina` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `enturmacoes`
--
ALTER TABLE `enturmacoes`
  ADD CONSTRAINT `fk_enturmacoes_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_enturmacoes_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  ADD CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `notas`
--
ALTER TABLE `notas`
  ADD CONSTRAINT `fk_notas_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notas_disciplina` FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notas_editor` FOREIGN KEY (`ultima_edicao_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `periodos`
--
ALTER TABLE `periodos`
  ADD CONSTRAINT `fk_periodos_aberto_por` FOREIGN KEY (`aberto_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_periodos_fechado_por` FOREIGN KEY (`fechado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `professores`
--
ALTER TABLE `professores`
  ADD CONSTRAINT `fk_professores_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `turma_disciplina`
--
ALTER TABLE `turma_disciplina`
  ADD CONSTRAINT `fk_td_disciplina` FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_td_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

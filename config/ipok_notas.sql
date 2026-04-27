-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: ipok_notas
-- ------------------------------------------------------
-- Server version	9.7.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;



--
-- GTID state at the beginning of the backup 
--


--
-- Table structure for table `alunos`
--

DROP TABLE IF EXISTS `alunos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alunos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `numero_matricula` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `data_matricula` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_matricula` (`numero_matricula`),
  UNIQUE KEY `uq_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_alunos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN01 — Dados específicos dos alunos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alunos`
--

LOCK TABLES `alunos` WRITE;
/*!40000 ALTER TABLE `alunos` DISABLE KEYS */;
/*!40000 ALTER TABLE `alunos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `atribuicoes`
--

DROP TABLE IF EXISTS `atribuicoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `atribuicoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professor_id` int NOT NULL,
  `turma_disciplina_id` int NOT NULL,
  `ano_letivo` year NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_atribuicao` (`professor_id`,`turma_disciplina_id`,`ano_letivo`),
  KEY `idx_turma_disciplina_id` (`turma_disciplina_id`),
  CONSTRAINT `fk_atribuicoes_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_atribuicoes_td` FOREIGN KEY (`turma_disciplina_id`) REFERENCES `turma_disciplina` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN05/RN08 — Associação professor ↔ turma ↔ disciplina';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `atribuicoes`
--

LOCK TABLES `atribuicoes` WRITE;
/*!40000 ALTER TABLE `atribuicoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `atribuicoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disciplinas`
--

DROP TABLE IF EXISTS `disciplinas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disciplinas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disciplinas`
--

LOCK TABLES `disciplinas` WRITE;
/*!40000 ALTER TABLE `disciplinas` DISABLE KEYS */;
/*!40000 ALTER TABLE `disciplinas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enturmacoes`
--

DROP TABLE IF EXISTS `enturmacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enturmacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `turma_id` int NOT NULL,
  `data_enturmacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aluno_turma` (`aluno_id`,`turma_id`),
  KEY `idx_turma_id` (`turma_id`),
  CONSTRAINT `fk_enturmacoes_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enturmacoes_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN10 — Só alunos enturmados podem receber notas';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enturmacoes`
--

LOCK TABLES `enturmacoes` WRITE;
/*!40000 ALTER TABLE `enturmacoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `enturmacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `identificador` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'username ou nº matrícula tentado',
  `tentativa_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip`),
  KEY `idx_tentativa_em` (`tentativa_em`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN02 — Rate limiting para login';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'::1','eminenciaemanuel4@gmail.com','2026-04-27 18:49:47'),(2,'::1','eminenciaemanuel4@gmail.com','2026-04-27 18:50:06'),(3,'::1','admin@ipok.com','2026-04-27 19:26:59');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs_auditoria`
--

DROP TABLE IF EXISTS `logs_auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs_auditoria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `acao` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Ex: INSERIR_NOTA, EDITAR_NOTA, ABRIR_PERIODO, FECHAR_PERIODO',
  `tabela` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `registro_id` int DEFAULT NULL,
  `dados_antigos` text COLLATE utf8mb4_general_ci COMMENT 'JSON com valores anteriores',
  `dados_novos` text COLLATE utf8mb4_general_ci COMMENT 'JSON com valores novos',
  `ip` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data_hora` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_data_hora` (`data_hora`),
  KEY `idx_acao` (`acao`),
  CONSTRAINT `fk_logs_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN05/RN09/RN10 — Auditoria completa de acções sensíveis';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs_auditoria`
--

LOCK TABLES `logs_auditoria` WRITE;
/*!40000 ALTER TABLE `logs_auditoria` DISABLE KEYS */;
INSERT INTO `logs_auditoria` VALUES (1,NULL,'LOGIN','usuarios',NULL,NULL,NULL,NULL,'2026-04-27 19:26:59'),(2,NULL,'CRIAR_USUARIO','usuarios',2,NULL,NULL,'::1','2026-04-27 19:51:34'),(3,NULL,'CRIAR_USUARIO','usuarios',3,NULL,NULL,'::1','2026-04-27 19:58:23'),(4,NULL,'DELETAR_USUARIO','usuarios',3,NULL,NULL,'::1','2026-04-27 20:00:49'),(5,NULL,'CRIAR_USUARIO','usuarios',4,NULL,NULL,'::1','2026-04-27 20:03:30'),(6,NULL,'DELETAR_USUARIO','usuarios',4,NULL,NULL,'::1','2026-04-27 20:05:38'),(7,NULL,'CRIAR_USUARIO','usuarios',5,NULL,NULL,'::1','2026-04-27 20:06:06'),(8,NULL,'DELETAR_USUARIO','usuarios',4,NULL,NULL,'::1','2026-04-27 20:08:05'),(9,NULL,'DELETAR_USUARIO','usuarios',5,NULL,NULL,'::1','2026-04-27 20:13:52'),(10,NULL,'CRIAR_USUARIO','usuarios',6,NULL,NULL,'::1','2026-04-27 20:14:22'),(11,NULL,'DELETAR_USUARIO','usuarios',6,NULL,NULL,'::1','2026-04-27 20:14:34'),(12,NULL,'DELETAR_USUARIO','usuarios',2,NULL,NULL,'::1','2026-04-27 20:16:14'),(13,NULL,'DELETAR_USUARIO','usuarios',1,NULL,NULL,'::1','2026-04-27 20:23:37');
/*!40000 ALTER TABLE `logs_auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notas`
--

DROP TABLE IF EXISTS `notas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `disciplina_id` int NOT NULL,
  `ano_letivo` year NOT NULL,
  `trimestre` tinyint(1) NOT NULL,
  `nota_trimestre` decimal(4,1) DEFAULT NULL,
  `media_final` decimal(4,1) GENERATED ALWAYS AS (`nota_trimestre`) STORED COMMENT 'A média final do trimestre é a própria nota lançada',
  `estado` varchar(10) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS ((case when (`nota_trimestre` is not null) then if((`nota_trimestre` >= 10),_utf8mb4'Aprovado',_utf8mb4'Reprovado') else NULL end)) STORED,
  `ultima_edicao_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ultima_edicao_por` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nota` (`aluno_id`,`disciplina_id`,`ano_letivo`,`trimestre`),
  KEY `idx_disciplina_id` (`disciplina_id`),
  KEY `idx_ultima_edicao_por` (`ultima_edicao_por`),
  KEY `idx_ano_trimestre` (`ano_letivo`,`trimestre`),
  CONSTRAINT `fk_notas_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notas_disciplina` FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notas_editor` FOREIGN KEY (`ultima_edicao_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notas`
--

LOCK TABLES `notas` WRITE;
/*!40000 ALTER TABLE `notas` DISABLE KEYS */;
/*!40000 ALTER TABLE `notas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `periodos`
--

DROP TABLE IF EXISTS `periodos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ano_letivo` year NOT NULL,
  `trimestre` tinyint(1) NOT NULL COMMENT '1, 2 ou 3 — RN03',
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `status` enum('aberto','fechado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'fechado' COMMENT 'RN06 — Só admin altera',
  `aberto_por` int DEFAULT NULL COMMENT 'Utilizador que abriu',
  `fechado_por` int DEFAULT NULL COMMENT 'Utilizador que fechou',
  `aberto_em` datetime DEFAULT NULL,
  `fechado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_periodo` (`ano_letivo`,`trimestre`),
  KEY `fk_periodos_aberto_por` (`aberto_por`),
  KEY `fk_periodos_fechado_por` (`fechado_por`),
  CONSTRAINT `fk_periodos_aberto_por` FOREIGN KEY (`aberto_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_periodos_fechado_por` FOREIGN KEY (`fechado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `periodos`
--

LOCK TABLES `periodos` WRITE;
/*!40000 ALTER TABLE `periodos` DISABLE KEYS */;
/*!40000 ALTER TABLE `periodos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `professores`
--

DROP TABLE IF EXISTS `professores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `professores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `codigo_funcionario` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_id` (`usuario_id`),
  UNIQUE KEY `uq_codigo_funcionario` (`codigo_funcionario`),
  CONSTRAINT `fk_professores_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Dados específicos dos professores';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `professores`
--

LOCK TABLES `professores` WRITE;
/*!40000 ALTER TABLE `professores` DISABLE KEYS */;
/*!40000 ALTER TABLE `professores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turma_disciplina`
--

DROP TABLE IF EXISTS `turma_disciplina`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turma_disciplina` (
  `id` int NOT NULL AUTO_INCREMENT,
  `turma_id` int NOT NULL,
  `disciplina_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_turma_disciplina` (`turma_id`,`disciplina_id`),
  KEY `idx_disciplina_id` (`disciplina_id`),
  CONSTRAINT `fk_td_disciplina` FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_td_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turma_disciplina`
--

LOCK TABLES `turma_disciplina` WRITE;
/*!40000 ALTER TABLE `turma_disciplina` DISABLE KEYS */;
/*!40000 ALTER TABLE `turma_disciplina` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turmas`
--

DROP TABLE IF EXISTS `turmas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `ano_letivo` year NOT NULL,
  `curso` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_turma` (`nome`,`ano_letivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turmas`
--

LOCK TABLES `turmas` WRITE;
/*!40000 ALTER TABLE `turmas` DISABLE KEYS */;
/*!40000 ALTER TABLE `turmas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `senha` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nivel` enum('admin','professor','aluno') COLLATE utf8mb4_general_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acesso` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='RN01 — Utilizadores do sistema com perfis distintos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (7,'Administrador','admin@ipok.com','admin','$2y$10$5qhNVknBndVqV.v0Rt7Enu3Uz5ALZ4vN5PKh4WhlFvYgRYDnNc5eW','admin',1,'2026-04-27 20:40:16',NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `vw_notas_aluno`
--

DROP TABLE IF EXISTS `vw_notas_aluno`;
/*!50001 DROP VIEW IF EXISTS `vw_notas_aluno`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_notas_aluno` AS SELECT 
 1 AS `aluno_id`,
 1 AS `disciplina_id`,
 1 AS `disciplina`,
 1 AS `ano_letivo`,
 1 AS `trimestre`,
 1 AS `nota_trimestre`,
 1 AS `media_final`,
 1 AS `estado`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_notas_professor`
--

DROP TABLE IF EXISTS `vw_notas_professor`;
/*!50001 DROP VIEW IF EXISTS `vw_notas_professor`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_notas_professor` AS SELECT 
 1 AS `professor_id`,
 1 AS `nome_aluno`,
 1 AS `numero_matricula`,
 1 AS `disciplina`,
 1 AS `turma`,
 1 AS `ano_letivo`,
 1 AS `trimestre`,
 1 AS `nota_trimestre`,
 1 AS `media_final`,
 1 AS `estado`*/;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `vw_notas_aluno`
--

/*!50001 DROP VIEW IF EXISTS `vw_notas_aluno`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_notas_aluno` AS select `n`.`aluno_id` AS `aluno_id`,`n`.`disciplina_id` AS `disciplina_id`,`d`.`nome` AS `disciplina`,`n`.`ano_letivo` AS `ano_letivo`,`n`.`trimestre` AS `trimestre`,`n`.`nota_trimestre` AS `nota_trimestre`,`n`.`media_final` AS `media_final`,`n`.`estado` AS `estado` from ((`notas` `n` join `disciplinas` `d` on((`d`.`id` = `n`.`disciplina_id`))) join `periodos` `p` on(((`p`.`ano_letivo` = `n`.`ano_letivo`) and (`p`.`trimestre` = `n`.`trimestre`) and (`p`.`status` = 'fechado')))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_notas_professor`
--

/*!50001 DROP VIEW IF EXISTS `vw_notas_professor`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_notas_professor` AS select `a_prof`.`professor_id` AS `professor_id`,`u`.`nome` AS `nome_aluno`,`al`.`numero_matricula` AS `numero_matricula`,`d`.`nome` AS `disciplina`,`t`.`nome` AS `turma`,`n`.`ano_letivo` AS `ano_letivo`,`n`.`trimestre` AS `trimestre`,`n`.`nota_trimestre` AS `nota_trimestre`,`n`.`media_final` AS `media_final`,`n`.`estado` AS `estado` from (((((((`notas` `n` join `alunos` `al` on((`al`.`id` = `n`.`aluno_id`))) join `usuarios` `u` on((`u`.`id` = `al`.`usuario_id`))) join `disciplinas` `d` on((`d`.`id` = `n`.`disciplina_id`))) join `enturmacoes` `e` on((`e`.`aluno_id` = `al`.`id`))) join `turmas` `t` on((`t`.`id` = `e`.`turma_id`))) join `turma_disciplina` `td` on(((`td`.`turma_id` = `t`.`id`) and (`td`.`disciplina_id` = `d`.`id`)))) join `atribuicoes` `a_prof` on((`a_prof`.`turma_disciplina_id` = `td`.`id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-27 21:55:40

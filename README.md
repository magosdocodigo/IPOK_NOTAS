# 🎓 IPOK_NOTAS – Sistema de Gestão de Notas

Sistema web para gestão académica, lançamento e consulta de notas, desenvolvido para o **Instituto Politécnico do Kituma (IPOK)**.  
Permite que administradores, professores e alunos interajam com o processo avaliativo de forma segura, organizada e em conformidade com as regras de negócio estabelecidas.

---

## 📋 Índice

- [Visão Geral](#-visão-geral)
- [Tecnologias Utilizadas](#-tecnologias-utilizadas)
- [Estrutura do Banco de Dados](#️-estrutura-do-banco-de-dados)
- [Funcionalidades Principais](#-funcionalidades-principais)
- [Regras de Negócio Implementadas](#-regras-de-negócio-implementadas)
- [Instalação e Configuração](#-instalação-e-configuração)
- [Estrutura de Pastas](#-estrutura-de-pastas)
- [Como Usar](#️-como-usar)
- [Documentação Adicional](#-documentação-adicional)
- [Contribuição](#-contribuição)
- [Licença](#-licença)

---

## 🧭 Visão Geral

O **IPOK_NOTAS** é um sistema de gestão académica focado no lançamento e consulta de notas. Atende três perfis:

- **Administrador** – gestão de utilizadores, turmas, disciplinas, períodos, atribuições de professores e enturmações.
- **Professor** – lançamento e edição de notas (apenas uma nota por trimestre), visualização de boletins e relatórios das suas turmas.
- **Aluno** – consulta do seu boletim, histórico escolar e acompanhamento do desempenho ao longo do ano letivo.

O sistema foi construído com foco na simplicidade e na rastreabilidade de ações através de logs de auditoria.

---

## 💻 Tecnologias Utilizadas

| Camada             | Tecnologia                                                         |
|--------------------|--------------------------------------------------------------------|
| **Backend**        | PHP 8.2 (orientado a objetos, mysqli)                             |
| **Frontend**       | HTML5, CSS3, Bootstrap 5, JavaScript (Chart.js, GSAP, Select2)   |
| **Banco de Dados** | MySQL (MariaDB 10.4)                                              |
| **Servidor**       | Apache (XAMPP)                                                    |
| **Bibliotecas**    | DomPDF (geração de PDF), Font Awesome 6, jQuery, DataTables       |

---

## 🗄️ Estrutura do Banco de Dados

O banco de dados `ipok_notas` foi projetado para atender às regras de negócio e garantir a integridade referencial.

### Tabelas principais

| Tabela              | Descrição                                                                  |
|---------------------|----------------------------------------------------------------------------|
| `usuarios`          | Armazena todos os utilizadores (admin, professor, aluno) com credenciais. |
| `alunos`            | Dados específicos do aluno (matrícula, data de matrícula).                |
| `professores`       | Dados específicos do professor (código de funcionário).                   |
| `turmas`            | Turmas (nome, ano letivo, curso).                                         |
| `disciplinas`       | Disciplinas (nome, código). **Sem carga horária** (conforme nova regra). |
| `turma_disciplina`  | Relaciona turmas com disciplinas.                                         |
| `atribuicoes`       | Atribuição de professores a uma combinação turma+disciplina (ano letivo). |
| `enturmacoes`       | Matrícula de alunos em turmas (data de enturmação).                       |
| `periodos`          | Períodos letivos (trimestre, datas, status aberto/fechado).               |
| `notas`             | Notas simplificadas: apenas `nota_trimestre` (0–20).                      |
| `logs_auditoria`    | Registo de todas as ações sensíveis (criação, edição, exclusão, login).   |
| `login_attempts`    | Controlo de tentativas de login (anti brute force).                       |

### Estrutura da tabela `notas` (versão final)

```sql
CREATE TABLE `notas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `disciplina_id` int(11) NOT NULL,
  `ano_letivo` year(4) NOT NULL,
  `trimestre` tinyint(1) NOT NULL,
  `nota_trimestre` decimal(4,1) DEFAULT NULL,
  `media_final` decimal(4,1) GENERATED ALWAYS AS (`nota_trimestre`) STORED,
  `estado` varchar(10) GENERATED ALWAYS AS (
    CASE WHEN `nota_trimestre` IS NOT NULL
         THEN IF(`nota_trimestre` >= 10, 'Aprovado', 'Reprovado')
         ELSE NULL END
  ) STORED,
  `ultima_edicao_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultima_edicao_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nota` (`aluno_id`,`disciplina_id`,`ano_letivo`,`trimestre`),
  CONSTRAINT `fk_notas_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notas_disciplina` FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notas_editor` FOREIGN KEY (`ultima_edicao_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> **Nota:** As colunas `media_final` e `estado` são geradas automaticamente a partir da `nota_trimestre`. Não é necessário inseri-las ou atualizá-las manualmente.

---

## ⚙️ Funcionalidades Principais

### 👤 Administrador
- Gestão completa de utilizadores (CRUD, ativação/desativação, reset de senha).
- Gestão de turmas, disciplinas e períodos letivos.
- Atribuição de professores a disciplinas/turmas (por ano letivo).
- Enturmação de alunos (individual ou em massa).
- Visualização de logs de auditoria (ações de todos os utilizadores).
- Geração de relatórios (boletins, pautas, aproveitamento) em HTML, PDF e CSV.

### 👨‍🏫 Professor
- Listagem das suas turmas e disciplinas atribuídas.
- Lançamento de notas (uma nota por disciplina/trimestre) – apenas durante períodos abertos.
- Edição de notas já lançadas.
- Visualização de boletins individuais dos seus alunos.
- Estatísticas básicas da turma (média, aprovados, reprovados).

### 👩‍🎓 Aluno
- Dashboard com resumo de desempenho (média geral, evolução por trimestre, melhor/pior disciplina).
- Consulta de boletim completo (notas por trimestre, situação final).
- Histórico escolar (todos os anos letivos).
- Exportação do boletim em PDF e CSV.

### 🔐 Geral
- Login seguro com hash de senha (bcrypt) e proteção contra brute force.
- Sessões e controlo de acesso baseado em perfis.
- Logs de auditoria detalhados (quem, quando, o quê, IP, dados antigos/novos).
- Interface responsiva e moderna (Bootstrap 5 + animações GSAP).

---

## 📜 Regras de Negócio Implementadas

| Regra  | Descrição |
|--------|-----------|
| **RN01** | Dados específicos – Alunos e professores têm tabelas separadas com dados complementares. |
| **RN02** | Rate limiting – Bloqueio temporário após 5 tentativas de login falhadas em 5 minutos. |
| **RN03** | Cálculo automático de médias – As médias e o estado são calculados pelo banco de dados (colunas geradas). |
| **RN04** | Aprovação – Aprovado quando `nota_trimestre >= 10`. |
| **RN05** | Atribuição de professores – Apenas professores atribuídos a uma turma/disciplina podem lançar notas. |
| **RN06** | Períodos – Somente administradores abrem/fecham períodos. |
| **RN07** | Visibilidade de notas – Alunos veem apenas notas de períodos fechados (via view `vw_notas_aluno`). |
| **RN08** | Visão do professor – Professor vê apenas suas turmas/disciplinas atribuídas. |
| **RN09** | Auditoria – Logs obrigatórios para ações sensíveis (inserção, edição, exclusão, login). |
| **RN10** | Enturmação – Só alunos enturmados podem receber notas. |
| **Soft delete** | Ao "excluir" um professor, ele é apenas desativado, preservando notas e atribuições. |

---

## 🚀 Instalação e Configuração

### Pré-requisitos

- XAMPP (PHP ≥ 8.2, MySQL, Apache) instalado.
- Git (opcional, para clonar o repositório).

### Passos

**1. Clone ou copie o projeto para a pasta `C:\xampp\htdocs\`:**

```bash
git clone https://github.com/seu-usuario/IPOK_NOTAS.git
```

(ou extraia o conteúdo diretamente em `C:\xampp\htdocs\IPOK_NOTAS`)

**2. Inicie o XAMPP** – ligue o Apache e o MySQL.

**3. Crie o banco de dados:**

- Acesse `http://localhost/phpmyadmin`.
- Crie um novo banco de dados chamado `ipok_notas` (collation `utf8mb4_general_ci`).
- Importe o ficheiro `database/ipok_notas.sql` (disponível no projeto) ou execute o script completo fornecido na documentação.

**4. Configure a conexão** com o banco no arquivo `config/database.php` (utilizador `root`, senha vazia, a menos que tenha alterado).

**5. Instale as dependências do Composer** (necessário apenas para gerar PDF com DomPDF):

```bash
composer install
```

> Se não tiver o Composer instalado, siga as instruções em [getcomposer.org](https://getcomposer.org).

**6. Acesse o sistema:**

- URL: `http://localhost/IPOK_NOTAS/`

### Credenciais padrão

| Perfil          | Identificador                  | Senha      |
|-----------------|--------------------------------|------------|
| Administrador   | admin@ipok.ao                  | ipok2026   |
| Professor       | eminenciaemanuel4@gmail.com    | ipok2026   |
| Aluno           | 17819 (ou 17820)               | ipok2026   |

> ⚠️ **Altere as senhas dos utilizadores padrão após o primeiro login!**

---

## 📁 Estrutura de Pastas

```
IPOK_NOTAS/
├── admin/               # Área administrativa
│   ├── atribuicoes.php
│   ├── dashboard.php
│   ├── disciplinas.php
│   ├── enturmacoes.php
│   ├── logs.php
│   ├── periodos.php
│   ├── relatorios.php
│   ├── turmas.php
│   ├── turma_disciplinas.php
│   └── usuarios.php
├── aluno/               # Área do aluno
│   ├── boletim.php
│   ├── consultar_notas.php
│   ├── dashboard.php
│   └── historico.php
├── professor/           # Área do professor
│   ├── boletins.php
│   ├── dashboard.php
│   ├── editar-notas.php
│   ├── lancar-notas.php
│   └── minhas-turmas.php
├── assets/              # Recursos estáticos (CSS, JS, imagens)
│   ├── css/
│   ├── js/
│   └── img/
├── config/              # Configurações
│   └── database.php
├── includes/            # Componentes reutilizáveis (sidebar, auth)
├── vendor/              # Dependências do Composer (DomPDF)
├── index.php            # Página inicial (redireciona para login)
├── login.php            # Tela de login
├── logout.php           # Destruição de sessão
├── splash.php           # Tela de transição após login
└── README.md
```

---

## 🖥️ Como Usar

### Acesso inicial

1. Abra o navegador e aceda a `http://localhost/IPOK_NOTAS/`.
2. Insira as credenciais conforme o perfil desejado.

### Fluxo básico (Administrador)

1. Crie turmas, disciplinas e períodos.
2. Atribua professores a turmas/disciplinas.
3. Enturme os alunos (individualmente ou em massa).
4. Acompanhe os logs de auditoria e gere relatórios.

### Fluxo (Professor)

1. Selecione a turma, disciplina e trimestre na página de lançamento de notas.
2. Insira a nota para cada aluno (apenas um campo).
3. Salve as notas – o sistema calcula automaticamente a média e o estado.
4. Edite notas enquanto o período estiver aberto.
5. Visualize boletins individuais dos alunos.

### Fluxo (Aluno)

1. No dashboard, veja as estatísticas do seu desempenho.
2. Aceda ao boletim para ver as notas de cada trimestre.
3. Consulte o histórico escolar de anos anteriores.
4. Exporte o boletim em PDF ou CSV.

---

## 📄 Documentação Adicional

- **Diagrama do banco de dados** – disponível na pasta `docs/` (se houver).
- **Política de privacidade** – O sistema não armazena dados sensíveis desnecessários.
- **Manual do utilizador** – *(em construção)*.

---

## 🤝 Contribuição

Contribuições são bem-vindas! Siga os passos:

1. Faça um fork deste repositório.
2. Crie uma branch para a sua feature:
   ```bash
   git checkout -b feature/nova-funcionalidade
   ```
3. Commit suas alterações:
   ```bash
   git commit -m 'Adiciona nova funcionalidade'
   ```
4. Push para a branch:
   ```bash
   git push origin feature/nova-funcionalidade
   ```
5. Abra um **Pull Request**.

---

## 📜 Licença

Este projeto está licenciado sob a **MIT License** – consulte o ficheiro `LICENSE` para mais detalhes.

---

## ✨ Créditos

Desenvolvido por: **Equipa IPOK**

🏫 Instituto Politécnico do Kituma (IPOK) – Angola  
🛠️ Suporte técnico: Eminência Emanuel  
📅 Última atualização: Abril de 2026

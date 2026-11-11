-- AgenDay 2.0: modelo para banco NOVO e SEPARADO (microtarefa 2.2)
-- Selecione o banco independente no phpMyAdmin antes de importar.
-- Não execute no banco do integrante original. Sem credenciais.
-- Somente tabelas; nenhuma migração ou conexão com servidores é realizada.

CREATE TABLE IF NOT EXISTS proprietario (
  id_proprietario INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome_proprietario VARCHAR(120) NOT NULL,
  email_proprietario VARCHAR(190),
  contato_proprietario VARCHAR(20),
  data_nascimento DATE,
  arquivo_proprietario VARCHAR(255),
  PRIMARY KEY (id_proprietario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estabelecimento (
  id_estabelecimento INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_proprietario INT UNSIGNED,
  nome_estabelecimento VARCHAR(120) NOT NULL,
  email_estabelecimento VARCHAR(190) NOT NULL,
  contato_estabelecimento VARCHAR(20),
  senha_estabelecimento VARCHAR(255) NOT NULL,
  arquivo_servicos VARCHAR(255),
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_estabelecimento),
  UNIQUE KEY uq_estabelecimento_email (email_estabelecimento),
  KEY idx_estabelecimento_proprietario (id_proprietario),
  CONSTRAINT fk_estabelecimento_proprietario FOREIGN KEY (id_proprietario)
    REFERENCES proprietario (id_proprietario) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS endereco (
  id_endereco INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  cnpj CHAR(14),
  endereco VARCHAR(200) NOT NULL,
  numero VARCHAR(15),
  cep CHAR(8),
  complemento VARCHAR(120),
  cidade VARCHAR(100) NOT NULL,
  estado CHAR(2) NOT NULL,
  PRIMARY KEY (id_endereco),
  UNIQUE KEY uq_endereco_empresa (id_estabelecimento),
  UNIQUE KEY uq_endereco_cnpj (cnpj),
  CONSTRAINT fk_endereco_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id_cliente INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  nome_cliente VARCHAR(120) NOT NULL,
  email_cliente VARCHAR(190),
  contato_cliente VARCHAR(20) NOT NULL,
  arquivo_cliente VARCHAR(255),
  PRIMARY KEY (id_cliente),
  UNIQUE KEY uq_clientes_empresa_id (id_estabelecimento, id_cliente),
  KEY idx_clientes_empresa_nome (id_estabelecimento, nome_cliente),
  CONSTRAINT fk_clientes_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicos (
  id_servicos INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  nome_servico VARCHAR(120) NOT NULL,
  duracao_servico TIME NOT NULL,
  preco_servico DECIMAL(10,2) NOT NULL,
  descricao_servico TEXT,
  PRIMARY KEY (id_servicos),
  UNIQUE KEY uq_servicos_empresa_id (id_estabelecimento, id_servicos),
  UNIQUE KEY uq_servicos_empresa_nome (id_estabelecimento, nome_servico),
  CONSTRAINT fk_servicos_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS funcionarios (
  id_funcionario INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  nome_funcionario VARCHAR(120) NOT NULL,
  contato_funcionario VARCHAR(20) NOT NULL,
  arquivo_funcionario VARCHAR(255),
  PRIMARY KEY (id_funcionario),
  UNIQUE KEY uq_funcionarios_empresa_id (id_estabelecimento, id_funcionario),
  KEY idx_funcionarios_empresa_nome (id_estabelecimento, nome_funcionario),
  CONSTRAINT fk_funcionarios_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- N:N entre funcionarios e servicos, sempre do mesmo estabelecimento.
CREATE TABLE IF NOT EXISTS funcionario_servico (
  id_estabelecimento INT UNSIGNED NOT NULL,
  id_funcionario INT UNSIGNED NOT NULL,
  id_servicos INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_estabelecimento, id_funcionario, id_servicos),
  KEY idx_funcionario_servico_empresa_servico (id_estabelecimento, id_servicos),
  CONSTRAINT fk_funcionario_servico_funcionario
    FOREIGN KEY (id_estabelecimento, id_funcionario)
    REFERENCES funcionarios (id_estabelecimento, id_funcionario)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_funcionario_servico_servico
    FOREIGN KEY (id_estabelecimento, id_servicos)
    REFERENCES servicos (id_estabelecimento, id_servicos)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agendamentos (
  id_agendamento INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  id_cliente INT UNSIGNED NOT NULL,
  id_funcionario INT UNSIGNED NOT NULL,
  id_servicos INT UNSIGNED NOT NULL,
  data_inicio DATETIME NOT NULL,
  data_fim DATETIME NOT NULL,
  status ENUM('agendado','confirmado','concluido','cancelado')
    NOT NULL DEFAULT 'agendado',
  observacoes TEXT,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_agendamento),
  KEY idx_agenda_empresa_data (id_estabelecimento, data_inicio),
  KEY idx_agenda_funcionario_horario (id_estabelecimento, id_funcionario, data_inicio, data_fim),
  KEY idx_agenda_cliente (id_estabelecimento, id_cliente),
  KEY idx_agenda_servico (id_estabelecimento, id_servicos),
  CONSTRAINT fk_agenda_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_agenda_cliente FOREIGN KEY (id_estabelecimento, id_cliente)
    REFERENCES clientes (id_estabelecimento, id_cliente) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_agenda_servico FOREIGN KEY (id_estabelecimento, id_servicos)
    REFERENCES servicos (id_estabelecimento, id_servicos) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_agenda_funcionario_servico
    FOREIGN KEY (id_estabelecimento, id_funcionario, id_servicos)
    REFERENCES funcionario_servico (id_estabelecimento, id_funcionario, id_servicos)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regras pendentes de código: data_fim > data_inicio; duração positiva;
-- agendamento sem sobreposição para o funcionário em horários ativos;
-- nas inserções, usar transação e SELECT ... FOR UPDATE para serializar
-- marcações concorrentes; todas as consultas devem filtrar id_estabelecimento
-- recuperado da sessão autenticada, nunca do formulário.

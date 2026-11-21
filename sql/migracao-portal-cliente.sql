-- AgenDay 2.0 — migração do portal do cliente
-- Plano: docs/PORTAL_CLIENTE_PLANO.md (seção 5)
--
-- ADITIVA: só cria colunas, índices e tabelas. Não remove nem altera dados
-- existentes. Pode ser executada mais de uma vez (colunas com IF NOT EXISTS e
-- índices conferidos em information_schema).
--
-- Aplicação: manual, no phpMyAdmin, SELECIONANDO O BANCO antes de executar.
-- Nunca reimportar sql/BD_v2.sql em banco existente.
--
-- Ordem: aplicar o SQL ANTES de publicar o código que usa estas estruturas.

-- ---------------------------------------------------------------------------
-- 1. Estabelecimento: slug público, domínio do portal e chave de contingência
-- ---------------------------------------------------------------------------

ALTER TABLE estabelecimento
  ADD COLUMN IF NOT EXISTS slug VARCHAR(60) NULL AFTER nome_estabelecimento,
  ADD COLUMN IF NOT EXISTS dominio VARCHAR(120) NULL AFTER slug,
  ADD COLUMN IF NOT EXISTS exigir_codigo_whatsapp TINYINT(1) NOT NULL DEFAULT 1 AFTER dominio;

-- Índices únicos criados por bloco dinâmico: ADD KEY IF NOT EXISTS só existe
-- em MariaDB 10.5+; o bloco abaixo funciona também em versões anteriores.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estabelecimento'
      AND INDEX_NAME = 'uq_estabelecimento_slug') = 0,
  'ALTER TABLE estabelecimento ADD UNIQUE KEY uq_estabelecimento_slug (slug)',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estabelecimento'
      AND INDEX_NAME = 'uq_estabelecimento_dominio') = 0,
  'ALTER TABLE estabelecimento ADD UNIQUE KEY uq_estabelecimento_dominio (dominio)',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 2. Clientes: telefone normalizado (o portal identifica o cliente pelo número)
-- ---------------------------------------------------------------------------

ALTER TABLE clientes
  ADD COLUMN IF NOT EXISTS telefone_digits VARCHAR(15) NULL AFTER contato_cliente;

-- Preenche a partir do telefone já cadastrado, na mesma regra usada por
-- lib/portal-cliente.php: só dígitos; remove o prefixo 55 (país) quando houver;
-- guarda apenas DDD + número (10 ou 11 dígitos).
UPDATE clientes
SET telefone_digits = CASE
    WHEN CHAR_LENGTH(REGEXP_REPLACE(contato_cliente, '[^0-9]', '')) IN (12, 13)
         AND REGEXP_REPLACE(contato_cliente, '[^0-9]', '') LIKE '55%'
      THEN SUBSTRING(REGEXP_REPLACE(contato_cliente, '[^0-9]', ''), 3)
    ELSE REGEXP_REPLACE(contato_cliente, '[^0-9]', '')
  END
WHERE contato_cliente IS NOT NULL
  AND contato_cliente <> ''
  AND (telefone_digits IS NULL OR telefone_digits = '');

UPDATE clientes
SET telefone_digits = NULL
WHERE telefone_digits IS NOT NULL
  AND CHAR_LENGTH(telefone_digits) NOT IN (10, 11);

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes'
      AND INDEX_NAME = 'idx_clientes_telefone') = 0,
  'ALTER TABLE clientes ADD KEY idx_clientes_telefone (id_estabelecimento, telefone_digits)',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3. Agendamentos: token público para cancelamento sem login
-- ---------------------------------------------------------------------------

ALTER TABLE agendamentos
  ADD COLUMN IF NOT EXISTS token_publico CHAR(64) NULL AFTER observacoes;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agendamentos'
      AND INDEX_NAME = 'uq_agendamentos_token') = 0,
  'ALTER TABLE agendamentos ADD UNIQUE KEY uq_agendamentos_token (token_publico)',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 4. Verificação de acesso (código de 6 dígitos enviado pelo WhatsApp)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS verificacao_acesso (
  id_verificacao INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  telefone VARCHAR(15) NOT NULL,
  nome_informado VARCHAR(120) NULL,
  codigo_hash CHAR(64) NOT NULL,
  salt CHAR(32) NOT NULL,
  tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expira_em DATETIME NOT NULL,
  usado_em DATETIME NULL,
  ip_hash CHAR(64) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_verificacao),
  KEY idx_verificacao_busca (id_estabelecimento, telefone, criado_em),
  KEY idx_verificacao_ip (ip_hash, criado_em),
  CONSTRAINT fk_verificacao_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. Sessão do cliente (sobrevive à limpeza da sessão do PHP e é revogável)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS sessao_cliente (
  id_sessao CHAR(64) NOT NULL,
  id_estabelecimento INT UNSIGNED NOT NULL,
  id_cliente INT UNSIGNED NOT NULL,
  csrf_token CHAR(64) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acesso_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em DATETIME NOT NULL,
  ip_hash CHAR(64) NULL,
  PRIMARY KEY (id_sessao),
  KEY idx_sessao_cliente (id_estabelecimento, id_cliente),
  KEY idx_sessao_expira (expira_em),
  CONSTRAINT fk_sessao_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_sessao_cliente FOREIGN KEY (id_estabelecimento, id_cliente)
    REFERENCES clientes (id_estabelecimento, id_cliente)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6. Fila de mensagens do WhatsApp
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS mensagem_whatsapp (
  id_mensagem INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  id_agendamento INT UNSIGNED NULL,
  telefone VARCHAR(15) NOT NULL,
  tipo ENUM('codigo','confirmacao','lembrete','cancelamento','remarcacao') NOT NULL,
  corpo TEXT NOT NULL,
  status ENUM('pendente','enviando','enviado','erro') NOT NULL DEFAULT 'pendente',
  tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  agendado_para DATETIME NOT NULL,
  enviado_em DATETIME NULL,
  erro VARCHAR(255) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_mensagem),
  KEY idx_fila (status, agendado_para),
  KEY idx_mensagem_empresa (id_estabelecimento, criado_em),
  KEY idx_mensagem_agendamento (id_agendamento),
  CONSTRAINT fk_mensagem_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 7. Configuração do WAHA (uma linha; ajustada em Configurações > WhatsApp)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS whatsapp_config (
  id_config TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ativo TINYINT(1) NOT NULL DEFAULT 0,
  base_url VARCHAR(190) NOT NULL DEFAULT '',
  api_key VARCHAR(190) NOT NULL DEFAULT '',
  sessao VARCHAR(60) NOT NULL DEFAULT 'default',
  ultima_execucao DATETIME NULL,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_config)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO whatsapp_config (id_config) VALUES (1);

-- ---------------------------------------------------------------------------
-- 8. Estabelecimento de demonstração (usa o e-mail para valer no local e em
--    produção sem depender do ID). Conferir as linhas afetadas ao executar.
-- ---------------------------------------------------------------------------

UPDATE estabelecimento
SET slug = 'studio',
    dominio = 'agenday-agendamentos.infinityfree.io'
WHERE email_estabelecimento = 'studio@demonstracao.com';

-- ---------------------------------------------------------------------------
-- Conferência rápida (deve retornar as 4 tabelas novas e as colunas criadas)
-- ---------------------------------------------------------------------------

SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('verificacao_acesso', 'sessao_cliente', 'mensagem_whatsapp', 'whatsapp_config')
ORDER BY TABLE_NAME;

SELECT id_estabelecimento, nome_estabelecimento, slug, dominio, exigir_codigo_whatsapp
FROM estabelecimento
WHERE email_estabelecimento = 'studio@demonstracao.com';

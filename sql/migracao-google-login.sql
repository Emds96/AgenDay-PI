-- AgenDay 2.0 — proposta de migração Google (NÃO EXECUTAR AUTOMATICAMENTE)
-- Destino previsto: banco independente do AgenDay na conta InfinityFree.
-- Exigir backup de arquivos e banco, teste em cópia e autorização específica
-- antes de executar em produção. Não alterar banco do integrante original.

-- Permitir login exclusivamente Google, sem senha local:
ALTER TABLE estabelecimento
  MODIFY COLUMN senha_estabelecimento VARCHAR(255) NULL DEFAULT NULL;

-- Associação estável pela identidade 'sub' emitida pelo Google,
-- sem vincular automaticamente contas por igualdade de e-mail.
CREATE TABLE IF NOT EXISTS estabelecimento_google (
  google_sub VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  id_estabelecimento INT UNSIGNED NOT NULL,
  email_google VARCHAR(190) NOT NULL,
  vinculado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (google_sub),
  UNIQUE KEY uq_estabelecimento_google_id (id_estabelecimento),
  CONSTRAINT fk_estabelecimento_google
    FOREIGN KEY (id_estabelecimento) REFERENCES estabelecimento (id_estabelecimento)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conferências manuais ANTES de liberar Google no site:
-- SHOW COLUMNS FROM estabelecimento LIKE 'senha_estabelecimento';
-- SHOW TABLES LIKE 'estabelecimento_google';
-- SHOW CREATE TABLE estabelecimento_google;
-- SELECT COUNT(*) AS total_estabelecimentos FROM estabelecimento;
-- Em seguida, exercitar cadastro tradicional e rollback em ambiente de teste.
--
-- Reversão: não executar NOT NULL enquanto existir alguma conta somente Google.
-- Reverter por procedimento validado ou backup pré-migração, com plano de
-- preservação para usuários Google criados depois da mudança.

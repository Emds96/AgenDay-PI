-- QA-03 — migração incremental para o banco independente do AgenDay.
-- EXECUTAR somente após backup validado e autorização de alteração do banco.
-- Não importar novamente o esquema histórico BD_v2.sql.
-- Após executar, conferir a estrutura e testar com contas de QA.

CREATE TABLE IF NOT EXISTS recuperacao_senha (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estabelecimento INT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    criado_em DATETIME NOT NULL,
    expira_em DATETIME NOT NULL,
    utilizado_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recuperacao_token_hash (token_hash),
    KEY idx_recuperacao_conta_criado (id_estabelecimento, criado_em),
    KEY idx_recuperacao_expiracao (expira_em),
    CONSTRAINT fk_recuperacao_estabelecimento
      FOREIGN KEY (id_estabelecimento)
      REFERENCES estabelecimento(id_estabelecimento)
      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

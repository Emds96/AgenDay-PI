-- AgenDay 2.0 - Migracao: horario de funcionamento, bloqueios e regras de agendamento
-- Aplicar no phpMyAdmin (aba SQL) ANTES de publicar o codigo que le estas tabelas.
-- MariaDB 10.2+ / MySQL 8: os comandos sao idempotentes; rodar duas vezes nao duplica nada.
-- Se o servidor reclamar de "IF NOT EXISTS" em ADD COLUMN, remova essas duas palavras
-- e execute o bloco ALTER uma unica vez.
-- Nenhum dado existente e apagado: as colunas novas entram com valores padrao.

-- 1) Horario de funcionamento: uma linha por dia da semana (0 = domingo ... 6 = sabado).
CREATE TABLE IF NOT EXISTS horario_funcionamento (
  id_estabelecimento INT UNSIGNED NOT NULL,
  dia_semana TINYINT UNSIGNED NOT NULL,
  aberto TINYINT(1) NOT NULL DEFAULT 1,
  hora_abertura TIME NOT NULL DEFAULT '08:00:00',
  hora_fechamento TIME NOT NULL DEFAULT '18:00:00',
  inicio_almoco TIME NULL DEFAULT NULL,
  fim_almoco TIME NULL DEFAULT NULL,
  PRIMARY KEY (id_estabelecimento, dia_semana),
  CONSTRAINT fk_horario_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Bloqueios: feriado, folga, curso, medico, "fecha mais cedo".
-- id_funcionario NULL = bloqueio do estabelecimento inteiro.
CREATE TABLE IF NOT EXISTS bloqueio (
  id_bloqueio INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  id_funcionario INT UNSIGNED NULL DEFAULT NULL,
  inicio DATETIME NOT NULL,
  fim DATETIME NOT NULL,
  motivo VARCHAR(120) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_bloqueio),
  KEY idx_bloqueio_empresa_inicio (id_estabelecimento, inicio),
  KEY idx_bloqueio_funcionario (id_estabelecimento, id_funcionario, inicio),
  CONSTRAINT fk_bloqueio_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_bloqueio_funcionario FOREIGN KEY (id_estabelecimento, id_funcionario)
    REFERENCES funcionarios (id_estabelecimento, id_funcionario)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Regras de agendamento do estabelecimento (uma por conta, valem para a semana toda).
ALTER TABLE estabelecimento
  ADD COLUMN IF NOT EXISTS intervalo_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  ADD COLUMN IF NOT EXISTS antecedencia_minima_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  ADD COLUMN IF NOT EXISTS janela_maxima_dias SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  ADD COLUMN IF NOT EXISTS horas_cancelamento SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  ADD COLUMN IF NOT EXISTS tolerancia_atraso_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 10;

-- Conferencia rapida (opcional):
-- SHOW COLUMNS FROM horario_funcionamento;
-- SHOW COLUMNS FROM bloqueio;
-- SHOW COLUMNS FROM estabelecimento LIKE '%minutos%';

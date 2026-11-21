-- AgenDay 2.0 - Status de comparecimento e tolerância de chegada (lote ATT-01)
-- Aplicar no phpMyAdmin ANTES de publicar o código que usa os status novos.
-- Idempotente: pode ser executado mais de uma vez.

-- 1) Novos status: em_atendimento (chegou) e faltou (no-show).
ALTER TABLE agendamentos
  MODIFY COLUMN status ENUM('agendado','confirmado','em_atendimento','concluido','faltou','cancelado')
  NOT NULL DEFAULT 'agendado';

-- 2) Tolerância de chegada passa a 30 minutos por padrão.
ALTER TABLE estabelecimento
  ALTER COLUMN tolerancia_atraso_minutos SET DEFAULT 30;

-- 3) Contas que ainda estavam no padrão antigo (10) passam para 30.
--    Quem configurou outro valor é preservado.
UPDATE estabelecimento
   SET tolerancia_atraso_minutos = 30
 WHERE tolerancia_atraso_minutos = 10;

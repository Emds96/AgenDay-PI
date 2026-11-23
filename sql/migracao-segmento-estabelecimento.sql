-- AgenDay 2.0 - Tipo de negócio guardado na conta (PRIMEIROS-PASSOS-13)
-- Aplicar no phpMyAdmin ANTES de publicar o código que grava a escolha.
-- O código publicado funciona sem esta coluna: nesse caso volta a ler o
-- cookie do navegador, como antes.
--
-- Não é idempotente no MySQL antigo (ADD COLUMN falha se a coluna existir).
-- Confira antes: SHOW COLUMNS FROM estabelecimento LIKE 'segmento';

ALTER TABLE estabelecimento
  ADD COLUMN segmento VARCHAR(30) NULL DEFAULT NULL AFTER slug;

-- Contas que já tinham o tipo escolhido só no navegador voltam a pedir uma vez
-- e gravam aqui. Nenhum dado existente é alterado.

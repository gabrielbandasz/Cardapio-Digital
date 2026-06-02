-- ============================================================
-- Migration segura: suporte a extras/removidos em pedido_itens
-- Execute uma única vez no seu banco MySQL/MariaDB.
-- Não apaga dados existentes.
-- ============================================================

-- 1. Garantir que pedido_itens.customizacoes existe (TEXT)
--    (já existe no schema original — este ALTER é idempotente via IF NOT EXISTS)
ALTER TABLE `pedido_itens`
  MODIFY COLUMN `customizacoes` TEXT DEFAULT NULL COMMENT 'JSON: {extras:[{id,nome,preco,emoji}], removidos:["nome",...], variacoes:["nome",...]}';

-- 2. Garantir que pedido_itens.obs existe
ALTER TABLE `pedido_itens`
  MODIFY COLUMN `obs` TEXT DEFAULT NULL COMMENT 'Observação livre digitada pelo cliente';

-- 3. Índice auxiliar para consultas de admin (opcional, melhora performance)
-- Só cria se não existir
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'pedido_itens'
    AND INDEX_NAME   = 'idx_pedido_itens_pedido'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `pedido_itens` ADD INDEX `idx_pedido_itens_pedido` (`pedido_id`)',
  'SELECT "idx já existe"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Garantir índice em produto_opcoes (já existe no schema original)
-- produto_opcoes: idx_produto_tipo (produto_id, tipo) — sem ação necessária

SELECT 'Migration concluída com sucesso.' AS resultado;

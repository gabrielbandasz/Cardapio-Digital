-- ============================================================
-- Melhorias v3 — Execute após melhorias.sql
-- ============================================================
USE cardapio_digital;

-- Coluna imagem nos produtos (caso não exista)
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS imagem VARCHAR(500) DEFAULT NULL;
-- Coluna preco_original (alias para preco_antigo, ambas funcionam)
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_original DECIMAL(10,2) DEFAULT NULL;
-- Sincronizar se já havia preco_antigo
UPDATE produtos SET preco_original=preco_antigo WHERE preco_antigo IS NOT NULL AND preco_original IS NULL;

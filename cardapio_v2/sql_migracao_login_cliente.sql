-- Migração: adicionar suporte a login de clientes
-- Execute este script no banco cardapio_digital existente

ALTER TABLE `clientes`
  ADD COLUMN IF NOT EXISTS `senha_hash` varchar(255) DEFAULT NULL AFTER `whatsapp`;

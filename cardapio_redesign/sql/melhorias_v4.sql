-- Melhorias v4: Mercado Pago + segurança
ALTER TABLE config
  ADD COLUMN IF NOT EXISTS mp_access_token VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS logo VARCHAR(255) DEFAULT NULL;

ALTER TABLE pedidos
  ADD COLUMN IF NOT EXISTS mp_preference_id VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS pagamento_online VARCHAR(20) DEFAULT NULL;

-- Execute este arquivo no seu banco de dados MySQL

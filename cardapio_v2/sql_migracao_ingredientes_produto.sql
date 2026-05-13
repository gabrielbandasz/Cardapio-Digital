-- Migração: ingredientes removíveis e extras por produto
-- Rode este arquivo no phpMyAdmin caso seu banco ainda não tenha a tabela produto_opcoes.

CREATE TABLE IF NOT EXISTS produto_opcoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  tipo ENUM('remover','extra') NOT NULL DEFAULT 'remover',
  nome VARCHAR(120) NOT NULL,
  preco DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  emoji VARCHAR(10) DEFAULT NULL,
  ordem TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_produto_tipo (produto_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

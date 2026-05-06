-- ============================================================
-- Melhorias v2 — Execute após setup.sql
-- ============================================================
USE cardapio_digital;

-- Clientes (mini CRM)
CREATE TABLE IF NOT EXISTS clientes (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(255),
  whatsapp      VARCHAR(20) NOT NULL UNIQUE,
  total_pedidos INT          NOT NULL DEFAULT 0,
  total_gasto   DECIMAL(10,2) NOT NULL DEFAULT 0,
  pontos        INT          NOT NULL DEFAULT 0,
  ultimo_pedido DATETIME     DEFAULT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cupons de desconto
CREATE TABLE IF NOT EXISTS cupons (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  codigo          VARCHAR(50)   NOT NULL UNIQUE,
  tipo            ENUM('percentual','fixo') NOT NULL DEFAULT 'percentual',
  valor           DECIMAL(10,2) NOT NULL DEFAULT 10,
  uso_maximo      INT           DEFAULT NULL,
  uso_atual       INT           NOT NULL DEFAULT 0,
  valido_ate      DATETIME      DEFAULT NULL,
  ativo           TINYINT       NOT NULL DEFAULT 1,
  descricao       VARCHAR(255),
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cupons (codigo,tipo,valor,uso_maximo,descricao) VALUES
  ('PRIMEIRACOMPRA','percentual',10,NULL,'10% off na primeira compra'),
  ('VOLTA10','percentual',10,NULL,'10% off para cliente que voltou'),
  ('FRETE0','fixo',5,100,'Frete grátis')
ON DUPLICATE KEY UPDATE codigo=codigo;

-- Zonas de entrega (frete inteligente)
CREATE TABLE IF NOT EXISTS zonas_entrega (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(100) NOT NULL,
  bairros    TEXT,
  taxa       DECIMAL(10,2) NOT NULL DEFAULT 5.00,
  ativo      TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO zonas_entrega (nome,bairros,taxa,ativo) VALUES
  ('Centro','Centro,República,Sé',5.00,1),
  ('Zona Norte','Santana,Tucuruvi,Vila Guilherme',8.00,1),
  ('Zona Sul','Saúde,Jabaquara,Santo André',10.00,1),
  ('Zona Leste','Tatuapé,Penha,Mooca',9.00,1)
ON DUPLICATE KEY UPDATE nome=nome;

-- Variações de produtos
CREATE TABLE IF NOT EXISTS produto_variacoes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  produto_id   INT NOT NULL,
  grupo        VARCHAR(100) NOT NULL,
  nome         VARCHAR(100) NOT NULL,
  preco_extra  DECIMAL(10,2) NOT NULL DEFAULT 0,
  disponivel   TINYINT NOT NULL DEFAULT 1,
  FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exemplos de variações para X-Burguer Clássico (id=1)
INSERT INTO produto_variacoes (produto_id,grupo,nome,preco_extra) VALUES
  (1,'Tamanho','P — 120g',-3.00),(1,'Tamanho','M — 180g',0.00),(1,'Tamanho','G — 240g',4.00),
  (1,'Adicionais','+ Bacon',3.50),(1,'Adicionais','+ Queijo extra',2.00),(1,'Adicionais','+ Ovo',1.50)
ON DUPLICATE KEY UPDATE grupo=grupo;

-- Campos extras na config
ALTER TABLE config ADD COLUMN IF NOT EXISTS cor_primaria VARCHAR(7) DEFAULT '#e85d04';
ALTER TABLE config ADD COLUMN IF NOT EXISTS fidelidade_ativo TINYINT NOT NULL DEFAULT 0;
ALTER TABLE config ADD COLUMN IF NOT EXISTS fidelidade_pedidos INT NOT NULL DEFAULT 5;
ALTER TABLE config ADD COLUMN IF NOT EXISTS fidelidade_desconto INT NOT NULL DEFAULT 10;
ALTER TABLE config ADD COLUMN IF NOT EXISTS horario_auto TINYINT NOT NULL DEFAULT 0;
ALTER TABLE config ADD COLUMN IF NOT EXISTS horario_abre VARCHAR(5) DEFAULT '11:00';
ALTER TABLE config ADD COLUMN IF NOT EXISTS horario_fecha VARCHAR(5) DEFAULT '23:00';
ALTER TABLE config ADD COLUMN IF NOT EXISTS dias_funcionamento VARCHAR(50) DEFAULT '1,2,3,4,5,6';
ALTER TABLE config ADD COLUMN IF NOT EXISTS tempo_preparo_base INT NOT NULL DEFAULT 30;
ALTER TABLE config ADD COLUMN IF NOT EXISTS tempo_preparo_por_pedido INT NOT NULL DEFAULT 5;
ALTER TABLE config ADD COLUMN IF NOT EXISTS frete_por_zona TINYINT NOT NULL DEFAULT 0;
ALTER TABLE config ADD COLUMN IF NOT EXISTS whatsapp_offline_msg TEXT DEFAULT NULL;

-- Campo cupom no pedido
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS cupom_codigo VARCHAR(50) DEFAULT NULL;
ALTER TABLE pedidos ADD COLUMN IF NOT EXISTS cupom_desconto DECIMAL(10,2) DEFAULT 0;

-- Customizações dos itens do pedido (variações escolhidas)
ALTER TABLE pedido_itens ADD COLUMN IF NOT EXISTS customizacoes TEXT DEFAULT NULL;

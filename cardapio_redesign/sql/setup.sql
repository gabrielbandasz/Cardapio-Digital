-- Cardápio Digital PLUS — Setup
CREATE DATABASE IF NOT EXISTS cardapio_digital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cardapio_digital;

CREATE TABLE IF NOT EXISTS config (
  id INT NOT NULL DEFAULT 1, nome_restaurante VARCHAR(255) NOT NULL DEFAULT 'Lancheria do Zé',
  descricao TEXT, whatsapp VARCHAR(20) NOT NULL DEFAULT '5551999999999',
  endereco VARCHAR(255), horario VARCHAR(255),
  taxa_entrega DECIMAL(10,2) NOT NULL DEFAULT 5.00, pedido_minimo DECIMAL(10,2) NOT NULL DEFAULT 20.00,
  tempo_entrega VARCHAR(100), logo_emoji VARCHAR(10) NOT NULL DEFAULT '🍔', aberto TINYINT NOT NULL DEFAULT 1,
  pix_chave VARCHAR(255), pix_tipo ENUM('cpf','cnpj','email','telefone','aleatoria') DEFAULT 'aleatoria', pix_nome VARCHAR(255),
  promo_ativa TINYINT NOT NULL DEFAULT 0, promo_titulo VARCHAR(255) DEFAULT '🔥 Promoção relâmpago!',
  promo_desconto INT NOT NULL DEFAULT 10, promo_fim DATETIME DEFAULT NULL,
  modo_pico TINYINT NOT NULL DEFAULT 0, pico_tempo VARCHAR(100) DEFAULT '60-80 min',
  loja_slug VARCHAR(100) DEFAULT 'ze-burguer', PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO config VALUES (1,'Lancheria do Zé','Os melhores lanches da cidade!','5551999999999','Rua das Flores, 123 - Centro','Seg-Sáb: 11h às 23h | Dom: 12h às 22h',5.00,20.00,'30-50 min','🍔',1,NULL,'aleatoria',NULL,0,'🔥 Promoção relâmpago!',10,NULL,0,'60-80 min','ze-burguer') ON DUPLICATE KEY UPDATE id=1;

CREATE TABLE IF NOT EXISTS categorias (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, emoji VARCHAR(10), ordem INT NOT NULL DEFAULT 0, ativo TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO categorias (nome,emoji,ordem,ativo) VALUES ('Lanches','🍔',1,1),('Porções','🍟',2,1),('Bebidas','🥤',3,1),('Sobremesas','🍨',4,1),('Combos','🎯',5,1);

CREATE TABLE IF NOT EXISTS produtos (id INT AUTO_INCREMENT PRIMARY KEY, categoria_id INT NOT NULL, nome VARCHAR(255) NOT NULL, descricao TEXT, preco DECIMAL(10,2) NOT NULL, preco_antigo DECIMAL(10,2) DEFAULT NULL, emoji VARCHAR(10), destaque TINYINT NOT NULL DEFAULT 0, mais_vendido TINYINT NOT NULL DEFAULT 0, disponivel TINYINT NOT NULL DEFAULT 1, ordem INT NOT NULL DEFAULT 0, total_vendido INT NOT NULL DEFAULT 0, FOREIGN KEY (categoria_id) REFERENCES categorias(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO produtos (categoria_id,nome,descricao,preco,preco_antigo,emoji,destaque,mais_vendido,disponivel,ordem) VALUES
(1,'X-Burguer Clássico','Hambúrguer artesanal 180g, queijo, alface, tomate e molho especial',18.90,22.90,'🍔',1,1,1,1),
(1,'X-Bacon Duplo','Dois hambúrgueres 150g, bacon crocante, cheddar e molho barbecue',26.90,NULL,'🥓',0,0,1,2),
(1,'X-Frango Grelhado','Filé de frango grelhado, alface, tomate e maionese temperada',19.90,NULL,'🐔',0,0,1,3),
(1,'Veggie Burger','Hambúrguer de grão-de-bico, rúcula, tomate seco e aioli de ervas',21.90,NULL,'🥗',0,0,1,4),
(2,'Batata Frita G','Batata palito crocante, tempero especial e molho à escolha',14.90,NULL,'🍟',0,1,1,1),
(2,'Onion Rings','Anéis de cebola empanados com molho ranch',13.90,NULL,'🧅',0,0,1,2),
(2,'Nuggets 10un','Nuggets de frango crocantes com molho barbecue ou mostarda-mel',16.90,NULL,'🍗',0,0,1,3),
(3,'Refrigerante Lata','Coca-Cola, Guaraná ou Sprite 350ml',6.00,NULL,'🥤',0,0,1,1),
(3,'Suco Natural 500ml','Laranja, limão ou maracujá',9.90,NULL,'🍊',0,0,1,2),
(3,'Milk Shake','Chocolate, baunilha ou morango — 400ml',14.90,NULL,'🥛',0,0,1,3),
(4,'Brownie com Sorvete','Brownie quentinho com bola de sorvete de creme',14.90,NULL,'🍫',1,0,1,1),
(4,'Petit Gâteau','Bolinho quente de chocolate com sorvete de creme',16.90,NULL,'🎂',0,0,1,2),
(5,'Combo Família','2 X-Burguer Clássico + Batata G + 2 Refrigerantes',49.90,59.90,'🎯',1,0,1,1),
(5,'Combo Individual','X-Burguer à escolha + Batata M + Refrigerante',32.90,39.90,'🎁',0,0,1,2);

CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE, senha_hash VARCHAR(64) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO admins (nome,email,senha_hash) VALUES ('Administrador','admin@cardapio.com','64c9bdf3ce03037143fe7e55a4c3fb4199e1788bca08d4c436bcef9e2e31fea4') ON DUPLICATE KEY UPDATE email=email;

CREATE TABLE IF NOT EXISTS pedidos (id INT AUTO_INCREMENT PRIMARY KEY, numero VARCHAR(20) NOT NULL UNIQUE, nome_cliente VARCHAR(255), whatsapp_cliente VARCHAR(20), tipo_entrega ENUM('entrega','retirada') NOT NULL DEFAULT 'entrega', endereco_entrega TEXT, subtotal DECIMAL(10,2) NOT NULL DEFAULT 0, taxa_entrega DECIMAL(10,2) NOT NULL DEFAULT 0, total DECIMAL(10,2) NOT NULL DEFAULT 0, observacoes TEXT, status ENUM('novo','confirmado','preparo','pronto','entregue','cancelado') NOT NULL DEFAULT 'novo', pagamento ENUM('dinheiro','pix','cartao') NOT NULL DEFAULT 'dinheiro', mensagem_whatsapp TEXT, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedido_itens (id INT AUTO_INCREMENT PRIMARY KEY, pedido_id INT NOT NULL, produto_id INT NOT NULL, nome_produto VARCHAR(255) NOT NULL, preco_unitario DECIMAL(10,2) NOT NULL, quantidade INT NOT NULL DEFAULT 1, subtotal DECIMAL(10,2) NOT NULL, obs TEXT, FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

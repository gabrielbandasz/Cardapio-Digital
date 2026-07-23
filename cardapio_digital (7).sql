-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 24/07/2026 às 00:58
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `cardapio_digital`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `admins`
--

INSERT INTO `admins` (`id`, `nome`, `email`, `senha_hash`, `ultimo_login`, `ativo`) VALUES
(3, 'Gabriel', 'admin@admin.com', 'b3c314f99a5f2a7b8bd231fc92ba871a01510904f529970b8e93c4903a3725a8', NULL, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `emoji` varchar(10) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id`, `nome`, `emoji`, `ordem`, `ativo`) VALUES
(1, 'Lanches', '🍔', 1, 1),
(2, 'Porções', '🍟', 2, 1),
(3, 'Bebidas', '🥤', 3, 1),
(4, 'Sobremesas', '🍨', 4, 1),
(5, 'Combos', '🎯', 5, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `whatsapp` varchar(20) NOT NULL,
  `senha_hash` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `codigo_email` varchar(10) DEFAULT NULL,
  `codigo_expira` datetime DEFAULT NULL,
  `endereco` varchar(500) DEFAULT NULL,
  `total_pedidos` int(11) NOT NULL DEFAULT 0,
  `total_gasto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pontos` int(11) NOT NULL DEFAULT 0,
  `ultimo_pedido` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `clientes`
--

INSERT INTO `clientes` (`id`, `nome`, `whatsapp`, `senha_hash`, `email`, `email_verificado`, `codigo_email`, `codigo_expira`, `endereco`, `total_pedidos`, `total_gasto`, `pontos`, `ultimo_pedido`, `created_at`) VALUES
(1, 'a', '51980314793', '$2y$10$/7vsKz9HkgayYbOSh6r4vugTpt7SyTAGbWjnrys.JIkZ3U1EEJi0O', 'gab@gmail.com', 1, NULL, NULL, 'soledade 805 esteio rs', 0, 0.00, 0, NULL, '2026-05-15 19:27:16'),
(2, 'gabriel', '51997067875', '$2y$10$q4r0e5RsxYr3aZuFKBaDOO8rg6J8PUnsYGFoh5Rcmnw2hvwv5bw.G', 'mauriciobandasz@gmail.com', 1, NULL, NULL, 'Soledade 805 esteio rs', 0, 0.00, 0, NULL, '2026-05-15 19:51:33'),
(3, 'a', '51980314783', '$2y$10$IFB7JZrKf3PAP0.2Si6Q1ubwR/Ui6tGHf51/wHN0Ky55e9CK8mMLO', 'gabriel@gmail.com', 1, NULL, NULL, 'rua nova aaa', 0, 0.00, 0, NULL, '2026-05-15 19:53:36'),
(4, 'Gabriel Prade Bandasz', '51984241664', NULL, NULL, 0, NULL, NULL, NULL, 1, 14.90, 1, '2026-07-23 13:51:23', '2026-07-23 16:51:23');

-- --------------------------------------------------------

--
-- Estrutura para tabela `config`
--

CREATE TABLE `config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `nome_restaurante` varchar(255) NOT NULL DEFAULT 'XIS DO LICO',
  `descricao` text DEFAULT NULL,
  `whatsapp` varchar(20) NOT NULL DEFAULT '5551999999999',
  `endereco` varchar(255) DEFAULT NULL,
  `horario` varchar(255) DEFAULT NULL,
  `taxa_entrega` decimal(10,2) NOT NULL DEFAULT 5.00,
  `pedido_minimo` decimal(10,2) NOT NULL DEFAULT 20.00,
  `tempo_entrega` varchar(100) DEFAULT NULL,
  `logo_emoji` varchar(10) NOT NULL DEFAULT '?',
  `aberto` tinyint(4) NOT NULL DEFAULT 1,
  `pix_chave` varchar(255) DEFAULT NULL,
  `pix_tipo` enum('cpf','cnpj','email','telefone','aleatoria') DEFAULT 'aleatoria',
  `pix_nome` varchar(255) DEFAULT NULL,
  `promo_ativa` tinyint(4) NOT NULL DEFAULT 0,
  `promo_titulo` varchar(255) DEFAULT '? Promoção relâmpago!',
  `promo_desconto` int(11) NOT NULL DEFAULT 10,
  `promo_fim` datetime DEFAULT NULL,
  `modo_pico` tinyint(4) NOT NULL DEFAULT 0,
  `pico_tempo` varchar(100) DEFAULT '60-80 min',
  `loja_slug` varchar(100) DEFAULT 'xis-do-lico',
  `cor_primaria` varchar(7) DEFAULT '#e85d04',
  `fidelidade_ativo` tinyint(4) NOT NULL DEFAULT 0,
  `fidelidade_pedidos` int(11) NOT NULL DEFAULT 5,
  `fidelidade_desconto` int(11) NOT NULL DEFAULT 10,
  `horario_auto` tinyint(4) NOT NULL DEFAULT 0,
  `horario_abre` varchar(5) DEFAULT '11:00',
  `horario_fecha` varchar(5) DEFAULT '23:00',
  `dias_funcionamento` varchar(50) DEFAULT '1,2,3,4,5,6',
  `tempo_preparo_base` int(11) NOT NULL DEFAULT 30,
  `tempo_preparo_por_pedido` int(11) NOT NULL DEFAULT 5,
  `frete_por_zona` tinyint(4) NOT NULL DEFAULT 0,
  `whatsapp_offline_msg` text DEFAULT NULL,
  `mp_access_token` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `tiktok` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `config`
--

INSERT INTO `config` (`id`, `nome_restaurante`, `descricao`, `whatsapp`, `endereco`, `horario`, `taxa_entrega`, `pedido_minimo`, `tempo_entrega`, `logo_emoji`, `aberto`, `pix_chave`, `pix_tipo`, `pix_nome`, `promo_ativa`, `promo_titulo`, `promo_desconto`, `promo_fim`, `modo_pico`, `pico_tempo`, `loja_slug`, `cor_primaria`, `fidelidade_ativo`, `fidelidade_pedidos`, `fidelidade_desconto`, `horario_auto`, `horario_abre`, `horario_fecha`, `dias_funcionamento`, `tempo_preparo_base`, `tempo_preparo_por_pedido`, `frete_por_zona`, `whatsapp_offline_msg`, `mp_access_token`, `logo`, `banner`, `instagram`, `facebook`, `tiktok`) VALUES
(1, 'Seu Restaurante', 'Os melhores lanches da cidade!', '5551999999999', 'Rua das Flores, 123 - Centro', 'Seg-Sáb: 11h às 23h | Dom: 12h às 22h', 5.00, 10.00, '30-50 min', '🍔', 1, '', 'aleatoria', '', 0, '🔥 Promoção relâmpago!', 10, NULL, 0, '60-80 min', 'xis-do-lico', '#e70404', 0, 5, 10, 0, '11:00', '23:00', '1,2,3,4,5,6', 30, 5, 0, NULL, NULL, 'assets/uploads/img_564767f87c7a40ac.png', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `cupons`
--

CREATE TABLE `cupons` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `tipo` enum('percentual','fixo') NOT NULL DEFAULT 'percentual',
  `valor` decimal(10,2) NOT NULL DEFAULT 10.00,
  `uso_maximo` int(11) DEFAULT NULL,
  `uso_atual` int(11) NOT NULL DEFAULT 0,
  `valido_ate` datetime DEFAULT NULL,
  `ativo` tinyint(4) NOT NULL DEFAULT 1,
  `descricao` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cupons`
--

INSERT INTO `cupons` (`id`, `codigo`, `tipo`, `valor`, `uso_maximo`, `uso_atual`, `valido_ate`, `ativo`, `descricao`, `created_at`) VALUES
(1, 'PRIMEIRACOMPRA', 'percentual', 10.00, NULL, 0, NULL, 1, '10% off na primeira compra', '2026-05-09 23:52:55'),
(2, 'VOLTA10', 'percentual', 10.00, NULL, 0, NULL, 1, '10% off para cliente que voltou', '2026-05-09 23:52:55'),
(3, 'FRETE0', 'fixo', 5.00, 100, 0, NULL, 1, 'Frete grátis', '2026-05-09 23:52:55');

-- --------------------------------------------------------

--
-- Estrutura para tabela `ingredientes`
--

CREATE TABLE `ingredientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `emoji` varchar(20) DEFAULT '?',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `log_admin`
--

CREATE TABLE `log_admin` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `detalhes` text DEFAULT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `nome_cliente` varchar(255) DEFAULT NULL,
  `whatsapp_cliente` varchar(20) DEFAULT NULL,
  `tipo_entrega` enum('entrega','retirada') NOT NULL DEFAULT 'entrega',
  `endereco_entrega` text DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `taxa_entrega` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `status` enum('novo','confirmado','preparo','pronto','entregue','cancelado') NOT NULL DEFAULT 'novo',
  `pagamento` enum('dinheiro','pix','cartao') NOT NULL DEFAULT 'dinheiro',
  `mensagem_whatsapp` text DEFAULT NULL,
  `cupom_codigo` varchar(50) DEFAULT NULL,
  `cupom_desconto` decimal(10,2) DEFAULT 0.00,
  `mp_preference_id` varchar(100) DEFAULT NULL,
  `pagamento_online` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pedidos`
--

INSERT INTO `pedidos` (`id`, `numero`, `nome_cliente`, `whatsapp_cliente`, `tipo_entrega`, `endereco_entrega`, `subtotal`, `taxa_entrega`, `total`, `observacoes`, `status`, `pagamento`, `mensagem_whatsapp`, `cupom_codigo`, `cupom_desconto`, `mp_preference_id`, `pagamento_online`, `created_at`) VALUES
(1, '50C340', 'Gabriel Prade Bandasz', '51984241664', 'retirada', 'Rua Soledade, 805', 14.90, 0.00, 14.90, '', 'entregue', 'dinheiro', 'Pedido #50C340 - Gabriel Prade Bandasz', NULL, 0.00, NULL, NULL, '2026-07-23 16:51:23');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedido_itens`
--

CREATE TABLE `pedido_itens` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `nome_produto` varchar(255) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL,
  `obs` text DEFAULT NULL COMMENT 'Observação livre digitada pelo cliente',
  `customizacoes` text DEFAULT NULL COMMENT 'JSON: {extras:[{id,nome,preco,emoji}], removidos:["nome",...], variacoes:["nome",...]}'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pedido_itens`
--

INSERT INTO `pedido_itens` (`id`, `pedido_id`, `produto_id`, `nome_produto`, `preco_unitario`, `quantidade`, `subtotal`, `obs`, `customizacoes`) VALUES
(1, 1, 10, 'Milk Shake', 14.90, 1, 14.90, 'Chocolate', '{\"extras\":[],\"removidos\":[],\"variacoes\":[]}');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `preco_antigo` decimal(10,2) DEFAULT NULL,
  `preco_original` decimal(10,2) DEFAULT NULL,
  `emoji` varchar(10) DEFAULT NULL,
  `imagem` varchar(500) DEFAULT NULL,
  `destaque` tinyint(4) NOT NULL DEFAULT 0,
  `mais_vendido` tinyint(4) NOT NULL DEFAULT 0,
  `disponivel` tinyint(4) NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `total_vendido` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `categoria_id`, `nome`, `descricao`, `preco`, `preco_antigo`, `preco_original`, `emoji`, `imagem`, `destaque`, `mais_vendido`, `disponivel`, `ordem`, `total_vendido`) VALUES
(1, 1, 'X-Burguer Clássico', 'Hambúrguer artesanal 180g, queijo, alface, tomate e molho especial', 18.90, 22.90, 22.90, '🍔', 'assets/uploads/img_6a628d075e724.jpg', 1, 0, 1, 1, 0),
(2, 1, 'X-Bacon Duplo', 'Dois hambúrgueres 150g, bacon crocante, cheddar e molho barbecue', 26.90, NULL, NULL, '🥓', 'assets/uploads/img_6a62927be24c4.jpg', 0, 0, 1, 2, 0),
(3, 1, 'X-Frango Grelhado', 'Filé de frango grelhado, alface, tomate e maionese temperada', 19.90, NULL, NULL, '🐔', 'assets/uploads/img_6a629256858cb.jpg', 0, 0, 1, 3, 0),
(4, 1, 'Veggie Burger', 'Hambúrguer de grão-de-bico, rúcula, tomate seco e aioli de ervas', 21.90, NULL, NULL, '🥗', 'assets/uploads/img_6a6292263599c.jpg', 0, 0, 1, 4, 0),
(5, 2, 'Batata Frita G', 'Batata palito crocante, tempero especial e molho à escolha', 14.90, NULL, NULL, '🍟', 'assets/uploads/img_6a6292a7be95b.jpg', 0, 0, 1, 1, 0),
(6, 2, 'Onion Rings', 'Anéis de cebola empanados com molho ranch', 13.90, NULL, NULL, '🧅', 'assets/uploads/img_6a6292e806843.jpg', 0, 0, 1, 2, 0),
(7, 2, 'Nuggets 10un', 'Nuggets de frango crocantes com molho barbecue ou mostarda-mel', 16.90, NULL, NULL, '🍗', 'assets/uploads/img_6a6292c50ea29.jpg', 0, 0, 1, 3, 0),
(8, 3, 'Refrigerante Lata', 'Coca-Cola, Guaraná ou Sprite 350ml', 6.00, NULL, NULL, '🥤', 'assets/uploads/img_6a62912d4b33f.jpg', 0, 0, 1, 1, 0),
(9, 3, 'Suco Natural 500ml', 'Laranja, limão ou maracujá', 9.90, NULL, NULL, '🍊', 'assets/uploads/img_6a629162c3e80.png', 0, 0, 1, 2, 0),
(10, 3, 'Milk Shake', 'Chocolate, baunilha ou morango — 400ml', 14.90, NULL, NULL, '🥛', 'assets/uploads/img_6a625f4e9c253.jpg', 0, 1, 1, 3, 1),
(11, 4, 'Brownie com Sorvete', 'Brownie quentinho com bola de sorvete de creme', 14.90, NULL, NULL, '🍫', 'assets/uploads/img_6a6293109dbca.jpg', 1, 0, 1, 1, 0),
(13, 5, 'Combo Família', '2 X-Burguer Clássico + Batata G + 2 Refrigerantes', 49.90, 59.90, 59.90, '🎯', 'assets/uploads/img_6a6291c136a26.webp', 1, 0, 1, 1, 0),
(14, 5, 'Combo Individual', 'X-Burguer à escolha + Batata M + Refrigerante', 32.90, 39.90, 39.90, '🎁', 'assets/uploads/img_6a629201ce109.jpg', 0, 0, 1, 2, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_opcoes`
--

CREATE TABLE `produto_opcoes` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `tipo` enum('remover','extra') NOT NULL DEFAULT 'remover',
  `nome` varchar(120) NOT NULL,
  `preco` decimal(8,2) NOT NULL DEFAULT 0.00,
  `emoji` varchar(10) DEFAULT NULL,
  `ordem` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_variacoes`
--

CREATE TABLE `produto_variacoes` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `grupo` varchar(100) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `preco_extra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `disponivel` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produto_variacoes`
--

INSERT INTO `produto_variacoes` (`id`, `produto_id`, `grupo`, `nome`, `preco_extra`, `disponivel`) VALUES
(1, 1, 'Tamanho', 'P — 120g', -3.00, 1),
(2, 1, 'Tamanho', 'M — 180g', 0.00, 1),
(3, 1, 'Tamanho', 'G — 240g', 4.00, 1),
(4, 1, 'Adicionais', '+ Bacon', 3.50, 1),
(5, 1, 'Adicionais', '+ Queijo extra', 2.00, 1),
(6, 1, 'Adicionais', '+ Ovo', 1.50, 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `zonas_entrega`
--

CREATE TABLE `zonas_entrega` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `bairros` text DEFAULT NULL,
  `taxa` decimal(10,2) NOT NULL DEFAULT 5.00,
  `ativo` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `zonas_entrega`
--

INSERT INTO `zonas_entrega` (`id`, `nome`, `bairros`, `taxa`, `ativo`) VALUES
(1, 'Centro', 'Centro,República,Sé', 5.00, 1),
(2, 'Zona Norte', 'Santana,Tucuruvi,Vila Guilherme', 8.00, 1),
(3, 'Zona Sul', 'Saúde,Jabaquara,Santo André', 10.00, 1),
(4, 'Zona Leste', 'Tatuapé,Penha,Mooca', 9.00, 1);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `whatsapp` (`whatsapp`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD KEY `idx_whatsapp` (`whatsapp`);

--
-- Índices de tabela `config`
--
ALTER TABLE `config`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `cupons`
--
ALTER TABLE `cupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Índices de tabela `ingredientes`
--
ALTER TABLE `ingredientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `log_admin`
--
ALTER TABLE `log_admin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_data` (`created_at`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `idx_status_data` (`status`,`created_at`),
  ADD KEY `idx_data` (`created_at`);

--
-- Índices de tabela `pedido_itens`
--
ALTER TABLE `pedido_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `idx_pedido` (`pedido_id`),
  ADD KEY `idx_pedido_itens_pedido` (`pedido_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `idx_cat_disp` (`categoria_id`,`disponivel`);

--
-- Índices de tabela `produto_opcoes`
--
ALTER TABLE `produto_opcoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produto_tipo` (`produto_id`,`tipo`);

--
-- Índices de tabela `produto_variacoes`
--
ALTER TABLE `produto_variacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `zonas_entrega`
--
ALTER TABLE `zonas_entrega`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `cupons`
--
ALTER TABLE `cupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `ingredientes`
--
ALTER TABLE `ingredientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `log_admin`
--
ALTER TABLE `log_admin`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `pedido_itens`
--
ALTER TABLE `pedido_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `produto_opcoes`
--
ALTER TABLE `produto_opcoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `produto_variacoes`
--
ALTER TABLE `produto_variacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `zonas_entrega`
--
ALTER TABLE `zonas_entrega`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `pedido_itens`
--
ALTER TABLE `pedido_itens`
  ADD CONSTRAINT `pedido_itens_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`);

--
-- Restrições para tabelas `produto_variacoes`
--
ALTER TABLE `produto_variacoes`
  ADD CONSTRAINT `produto_variacoes_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

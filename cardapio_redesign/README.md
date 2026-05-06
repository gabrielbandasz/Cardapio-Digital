# 🍔 Cardápio Digital PLUS

Sistema completo de cardápio digital com pedidos via WhatsApp, painel admin e 20 recursos avançados.

## ✅ Recursos implementados

1. **Pedido em 2 Cliques** — Botão direto para WhatsApp com pedido pronto
2. **Cliente Recorrente** — Salva nome/endereço no navegador, exibe "Bem-vindo de volta"
3. **Pedido Inteligente** — Sugestões "quem pediu X também pediu Y"
4. **Promoção Relâmpago** — Desconto percentual com timer de contagem regressiva
5. **Link Personalizado** — Slug via `.htaccess` (ex: seusite.com/ze-burguer)
6. **Status do Pedido** — Página de rastreamento em tempo real
7. **Repetir Pedido** — Botão para reenviar último pedido via WhatsApp
8. **Horário de Pico** — Aviso de tempo de entrega maior
9. **Painel Ultra Simples** — Adicionar/editar/pausar produtos com 1 clique
10. **Cardápio que Vende** — Badge "mais vendido", preço riscado, combos
11. **PIX Integrado** — Chave PIX exibida no carrinho com botão copiar
12. **Modo Dark** — Alternância manual salva no navegador
13. **Compartilhar Pedido** — Compartilhar resumo do pedido com amigos
14. **Timer de Promoção** — Contador regressivo no topo da página
15. **Áudio do Pedido** — Leitura por voz do resumo do carrinho
16. **Som no Admin** — Alerta sonoro quando novo pedido chega
17. **Relatórios** — Produtos mais vendidos, faturamento por período e forma de pagamento
18. **Loja Fechada** — Exibe aviso + convida a montar pedido para depois
19. **Nome na Mensagem** — "Pedido de Gabriel" no WhatsApp
20. **Atualização de Status via WhatsApp** — Botões prontos para notificar cliente

## 📋 Instalação

### 1. Banco de dados
```sql
-- No phpMyAdmin ou terminal MySQL:
source sql/setup.sql
```

### 2. Configurar conexão
Edite `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_NAME', 'cardapio_digital');
```

### 3. Upload para servidor
- Faça upload de todos os arquivos para a pasta do seu domínio
- Certifique-se que o servidor tem PHP 8.0+ e MySQL 5.7+
- Habilite `mod_rewrite` no Apache (para slugs personalizados)

## 🔐 Acesso Admin

URL: `seusite.com/admin/login.php`
- Email: `admin@cardapio.com`
- Senha: `admin123`

**⚠️ Troque a senha após o primeiro acesso!**

## 🗂 Estrutura de arquivos

```
├── index.php           # Cardápio (página do cliente)
├── carrinho.php        # Carrinho de compras
├── pedido-confirmado.php
├── status.php          # Rastreamento de pedido
├── admin/
│   ├── login.php
│   ├── dashboard.php   # Painel com som + toggles
│   ├── pedidos.php     # Lista de pedidos
│   ├── pedido.php      # Detalhe + avisos WhatsApp
│   ├── produtos.php    # Gestão de produtos
│   ├── relatorios.php  # Relatórios automáticos
│   └── configuracoes.php # PIX, promoção, slug, pico
├── api/
│   ├── criar_pedido.php
│   ├── sugestoes.php
│   ├── status_pedido.php
│   ├── toggle_admin.php
│   ├── atualizar_status.php
│   └── novo_pedido_poll.php
├── assets/css/style.css
├── assets/js/cart.js
├── sql/setup.sql
└── config/db.php
```

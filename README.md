# 🎂 Diego Gourmet — Sistema V4

Sistema completo de comercialização para confeitaria artesanal.
Projeto Integrador ADS504 — UNIVIÇOSA 2025/2026
Autores: Diego Araujo Arruda · Matheus Lopes Zinato

---

## ⚡ Início Rápido (3 passos)

Este sistema tem **instalação automática** — você não precisa criar o banco
nem rodar comandos SQL manualmente.

### 1. Configure o `.env`

```bash
cp .env.example .env
```

Edite o `.env` e ajuste apenas:

```ini
DB_USER=root
DB_PASS=sua_senha_do_mysql
JWT_SECRET=qualquer_texto_aleatorio_com_32_caracteres
PASSWORD_PEPPER=outro_texto_aleatorio
```

> Gere segredos fortes com: `openssl rand -hex 32`

### 2. Garanta que o MySQL está rodando

```bash
brew services start mysql      # macOS
# ou: sudo systemctl start mysql   (Linux)
```

### 3. Suba o servidor

```bash
php -S localhost:8000 -t public/
```

**Pronto!** Acesse `http://localhost:8000`

No primeiro acesso o sistema **cria o banco, as 18 tabelas e popula os
dados automaticamente**. Se preferir uma tela visual, acesse
`http://localhost:8000/install.php`.

---

## 🔑 Acessos Padrão

| Perfil  | Telefone      | Senha    |
|---------|---------------|----------|
| Admin   | 31999999999   | `@admin` |
| Cliente | 31999999998   | `@user`  |
| Cliente | 31988887777   | `@user`  |

---

## 📁 Estrutura do Projeto

```
diego-gourmet-v4/
├── .env.example            Modelo de configuração
├── composer.json           Dependências opcionais (JWT, PHPMailer)
├── install.php → public/   Instalador web visual
│
├── public/                 ← Raiz pública (document root)
│   ├── index.html          Frontend SPA (integrado à API)
│   ├── install.php         Instalador visual
│   ├── assets/
│   │   ├── js/app.js       Camada de integração com a API
│   │   └── images/uploads/ Imagens de produtos enviadas
│   └── api/                49 endpoints REST
│       ├── auth/           login, register, refresh, reset…
│       ├── produtos/       CRUD + reordenar + toggle
│       ├── pedidos/        criar, status, agenda, histórico
│       ├── clientes/       perfil, histórico, CRM
│       ├── estoque/        insumos, lotes, baixa, alertas
│       ├── categorias/     CRUD
│       ├── configuracoes/  taxas, custos, datas bloqueadas
│       ├── fidelidade/     regras, progresso
│       ├── bi/             KPIs, ROI, exportação CSV
│       ├── pagamentos/     Mercado Pago (PIX, cartão, webhook)
│       └── upload/         upload de imagens
│
├── backend/                ← Código PHP (fora do document root)
│   ├── bootstrap.php       Autoload + .env + CORS + auto-install
│   ├── config/             Database, Security, MercadoPago, Email
│   ├── controllers/        12 controllers
│   ├── models/             7 models (PDO + prepared statements)
│   ├── middleware/         Auth (JWT), AdminOnly, RateLimit
│   └── helpers/            Response, JWT, Hash, Upload, MercadoPago…
│
├── database/
│   ├── Setup.php           ⭐ Auto-setup: cria DB + tabelas + seeds
│   └── schema.sql          Referência (opcional)
│
├── emails/templates/       5 templates HTML de e-mail
└── storage/logs/           Logs de erro
```

---

## 🔌 Integrações Opcionais

### Mercado Pago (pagamentos reais)
No `.env`, preencha:
```ini
MP_ACCESS_TOKEN=seu_token_de_producao_ou_teste
MP_PUBLIC_KEY=sua_public_key
```
Sem isso, os endpoints de pagamento retornam aviso 503 (resto funciona).

### E-mail (SMTP)
```ini
MAIL_USER=seu@gmail.com
MAIL_PASS=sua_senha_de_app
MAIL_FROM_ADDRESS=seu@gmail.com
```

### Dependências Composer (recomendado para produção)
```bash
composer install
```
Instala JWT oficial e PHPMailer. O sistema funciona **sem** elas
(usa implementação própria de JWT e `mail()` como fallback).

---

## 🛠️ Solução de Problemas

| Problema | Solução |
|----------|---------|
| "Erro de conexão com o banco" | Verifique `DB_USER`/`DB_PASS` no `.env` e se o MySQL está rodando |
| Vitrine vazia | Acesse `/install.php` e clique em "Instalar Agora" |
| Login retorna 401 | Reinstale via `/install.php` → "Reinstalar do Zero" |
| Quer recomeçar do zero | Delete `storage/installed.lock` e recarregue |

---

## 🧱 Reinstalar / Resetar

Para recriar o banco do zero, acesse `http://localhost:8000/install.php`
e clique em **"Reinstalar do Zero"**, ou delete o arquivo de lock:

```bash
rm storage/installed.lock
```

E recarregue a página — o setup roda de novo automaticamente.

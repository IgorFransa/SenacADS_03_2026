# Sistema de Empréstimo de Itens — Condomínio

Aplicação PHP com MySQL para moradores solicitarem aluguel de itens comuns e administradores confirmarem retiradas.

## Autoria

O **código da aplicação** (lógica PHP, telas, consultas ao banco e script SQL do domínio) foi desenvolvido por **Juraci** e **Fernando**.

## O que é de cada um

| Juraci e Fernando | Reorganização do repositório |
|-------------------|------------------------------|
| Conexão PDO e credenciais do banco | Pastas `auth/`, `usuario/`, `admin/`, `config/`, `assets/`, `includes/` |
| Tela e regras de login (`pi_login.php`) | `auth/login.php` — mesma lógica; apenas caminhos de `require` e redirecionamento |
| Fluxo do morador: itens, calendário, reservas (`pi_locacao.php`) | `usuario/locacao.php` — mesma lógica; links e `action` dos formulários ajustados |
| Painel admin: confirmar retirada (`pi_retira.php`) | `admin/retira.php` — mesma lógica; caminhos atualizados |
| Script `database/empCondominio2.sql` | SQL mantido; pasta `database/` já existia |
| — | `config/conexao.php` (conteúdo equivalente a `pi_conexao.php`) |
| — | `auth/logout.php` (novo; antes só era citado nas telas) |
| — | `index.php` na raiz |
| — | Este `README.md` (autoria, layout antigo vs novo, como executar) |

Nenhuma regra de negócio nova foi introduzida na reorganização — só estrutura, encerramento de sessão e documentação.

## Como era o projeto antes da reorganização

Tudo ficava na **raiz do repositório**, com o prefixo `pi_`:

| Arquivo original   | Função |
|--------------------|--------|
| `pi_conexao.php`   | Conexão PDO com MySQL (`conectarBanco()`) |
| `pi_login.php`     | Login, sessão e redirecionamento por `nivel_acesso` |
| `pi_locacao.php`   | Morador: listagem de itens, calendário, solicitação e cancelamento de reservas |
| `pi_retira.php`    | Administrador: confirmação de retirada de empréstimos pendentes |
| `database/empCondominio2.sql` | Criação do banco `empCondominio2` e tabelas (`Usuario`, `Item`, `Emprestimo`, etc.) |

Não havia `README.md`. As páginas citavam `pi_logout.php`, mas esse arquivo **não estava** no repositório.

## Estrutura atual (após reorganização)

Os arquivos de Juraci e Fernando foram **renomeados e movidos**; o conteúdo funcional permanece o mesmo, com caminhos de `require` e redirecionamentos ajustados:

```
SenacADS_03_2026/
├── index.php              # Novo: redireciona para o login
├── auth/
│   ├── login.php          # era pi_login.php
│   └── logout.php         # novo (substitui referência a pi_logout.php)
├── usuario/
│   └── locacao.php        # era pi_locacao.php
├── admin/
│   └── retira.php         # era pi_retira.php
├── config/
│   └── conexao.php        # era pi_conexao.php
├── database/
│   ├── empCondominio2.sql
│   └── docker/            # schema usado pelo MySQL no Docker
├── docker/                # entrypoint do container web
├── scripts/               # seed de demonstração (Docker)
├── Dockerfile
├── docker-compose.yml
├── includes/              # reservado para código compartilhado
└── assets/                # reservado para CSS e JavaScript
    ├── css/
    └── js/
```

## Executar com Docker (recomendado)

Requisitos: [Docker Desktop](https://www.docker.com/products/docker-desktop/) (ou Docker Engine + Compose).

Na raiz do projeto:

```bash
docker compose up --build
```

Abra **http://localhost:8080** (redireciona para o login).

Na primeira subida, o MySQL aplica `database/docker/01-schema.sql` e o container web cria **usuários e itens de demonstração** (senha abaixo).

| E-mail | Perfil | Senha |
|--------|--------|-------|
| `admin@condominio.local` | administrador | `Senha123` |
| `morador@condominio.local` | usuário (morador) | `Senha123` |

Comandos úteis:

```bash
docker compose down          # parar
docker compose down -v       # parar e apagar volume do banco (recomeça do zero)
```

Arquivos: `Dockerfile`, `docker-compose.yml`, `docker/entrypoint.sh`, `scripts/seed-database.php`.

## Executar manualmente (XAMPP / PHP local)

1. Importe `database/empCondominio2.sql` no MySQL.
2. Ajuste credenciais em `config/conexao.php` se necessário (variáveis de ambiente `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` também são suportadas).
3. Aponte o servidor web (ex.: XAMPP) para a raiz deste projeto.
4. Acesse `http://localhost/.../index.php`.

Crie usuários no banco com `senha_hash` gerado por `password_hash()` — o script SQL original não inclui dados de exemplo.

## Perfis de acesso

| Perfil           | Após o login        |
|------------------|---------------------|
| `administrador`  | `admin/retira.php`  |
| `usuario`        | `usuario/locacao.php` |

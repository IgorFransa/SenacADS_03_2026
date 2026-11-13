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
│   └── empCondominio2.sql # inalterado em relação ao original
├── includes/              # reservado para código compartilhado
└── assets/                # reservado para CSS e JavaScript
    ├── css/
    └── js/
```

## Como executar

1. Importe `database/empCondominio2.sql` no MySQL.
2. Configure credenciais em `config/conexao.php`, se necessário.
3. Aponte o servidor web (ex.: XAMPP) para a raiz deste projeto.
4. Acesse `http://localhost/.../index.php`.

## Perfis de acesso

| Perfil           | Após o login        |
|------------------|---------------------|
| `administrador`  | `admin/retira.php`  |
| `usuario`        | `usuario/locacao.php` |

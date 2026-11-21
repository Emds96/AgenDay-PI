# Ambiente local de desenvolvimento — AgenDay 2.0

**Revisão:** 21/11/2026 · **Escopo:** PC Windows do responsável (Éder). Este documento descreve o que **não está no Git** — ferramentas instaladas, banco local, pastas privadas e credenciais — para que outra sessão, outra IA ou o próprio responsável consiga retomar o ambiente sem redescobrir tudo.

> **Este arquivo não contém senhas.** Ele indica **onde** cada credencial está e **quem** deve fornecê-la. Nunca colar valores de senha, token ou chave em documento, commit ou chat.

## 1. Resumo do ambiente

| Item | Valor |
| --- | --- |
| Código do projeto | `C:\Users\EDER\AgenDay-PI` |
| URL local | http://127.0.0.1:8080 |
| Branch de trabalho | `dev-feira-uninorte` |
| PHP | 8.4.28 (CLI, NTS) — `%USERPROFILE%\Documents\AgenDay-Tools\php-8.4\php.exe` |
| Banco | MariaDB 11.8.9 portátil — `%USERPROFILE%\Documents\AgenDay-Tools\mariadb-11.8.9-winx64\bin` |
| Banco de dados local | `agenday_local` em `127.0.0.1:3306` |
| Configuração do MariaDB portátil | `%USERPROFILE%\Documents\AgenDay-Tools\agenday-restore-db\my.ini` |
| Conexão privada da aplicação | `C:\Users\EDER\AgenDay-PI\conexao.php` (ignorado pelo Git) |
| Subir / derrubar o ambiente | `C:\Users\EDER\AgenDay-Local\iniciar.ps1` e `parar.ps1` |
| Credenciais de administração do MariaDB | `%USERPROFILE%\Documents\AgenDay-Backups\.mysql-local.cnf` |
| Credenciais FTPS da produção | `%USERPROFILE%\Documents\AgenDay-Backups\.env.backup` |
| Backups automáticos | `%USERPROFILE%\Documents\AgenDay-Backups\<AAAA-MM-DD>_<HHMMSS>_<hash>` |
| Repositório | `Emds96/AgenDay-PI` |
| Site publicado | https://agenday-sistema.infinityfree.io/ |

**Ferramentas conferidas no PC em 21/11/2026:** Node v24.22.0, Python 3.15.0, Git 2.55.0, `gh` 2.104.0 autenticado como `Emds96` (escopos `repo` e `workflow`).

## 2. O que fica fora do repositório — e por quê

| Pasta / arquivo | Conteúdo | Motivo de estar fora do Git |
| --- | --- | --- |
| `C:\Users\EDER\AgenDay-PI\conexao.php` | host, usuário, senha e nome do banco | `conexao.php` está no `.gitignore`; credenciais nunca versionadas |
| `Documents\AgenDay-Tools\` | PHP portátil, MariaDB portátil, data dir restaurado e logs | Binários locais; caminhos específicos desta máquina |
| `Documents\AgenDay-Backups\` | `.env.backup`, `.mysql-local.cnf`, backups datados e `relatorio.json` | Contém credenciais FTP/MySQL e dados reais de clientes |
| `C:\Users\EDER\AgenDay-Local\` | `iniciar.ps1`, `parar.ps1`, `credenciais-locais.txt`, `HANDOFF-CHATGPT.md` | Scripts de máquina e logins de QA |
| Uploads locais (`arquivos/`) | fotos de clientes e funcionários | Dados pessoais; não versionados |

O **GitHub não tem** `conexao.php`, uploads, backups nem credenciais. Um clone do repositório **não sobe sozinho**: precisa dos itens acima.

## 3. Subir e derrubar o ambiente

### 3.1 Forma normal (scripts do PC)

```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\EDER\AgenDay-Local\iniciar.ps1"
powershell -ExecutionPolicy Bypass -File "C:\Users\EDER\AgenDay-Local\parar.ps1"
```

`iniciar.ps1` faz, nesta ordem:

1. Se a porta **3306** estiver fechada, sobe o `mariadbd.exe` com `--defaults-file=...\agenday-restore-db\my.ini`, escutando em `127.0.0.1:3306`, e espera até 20 segundos.
2. Se a porta **8080** estiver fechada, sobe o PHP embutido com `-S 127.0.0.1:8080 -t C:\Users\EDER\AgenDay-PI`.
3. Confirma as duas portas e abre `http://127.0.0.1:8080` no navegador; se falhar, manda olhar os logs em `Documents\AgenDay-Tools`.

`parar.ps1` encerra **somente** o processo `php.exe` cuja linha de comando contém `-S 127.0.0.1:8080` e desliga o MariaDB local pelo `mariadb-admin` com o `.mysql-local.cnf`.

**Logs úteis:** `Documents\AgenDay-Tools\php-dev.out.log`, `php-dev.err.log`, `mariadb-dev.out.log`, `mariadb-dev.err.log`.

### 3.2 Se os scripts forem perdidos

```powershell
$tools = "$env:USERPROFILE\Documents\AgenDay-Tools"
$bin   = "$tools\mariadb-11.8.9-winx64\bin"
$app   = 'C:\Users\EDER\AgenDay-PI'

Start-Process "$bin\mariadbd.exe" -ArgumentList @("--defaults-file=$tools\agenday-restore-db\my.ini", '--bind-address=127.0.0.1', '--port=3306') -WorkingDirectory $bin -WindowStyle Hidden
Start-Process "$tools\php-8.4\php.exe" -ArgumentList @('-S', '127.0.0.1:8080', '-t', $app) -WorkingDirectory $app -WindowStyle Hidden

foreach ($p in 3306, 8080) {
  "$p : " + (@(Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue).Count -gt 0)
}
```

## 4. Banco local `agenday_local`

- **12 tabelas**, no mesmo modelo do `sql/BD_v2.sql`, com as migrações já aplicadas: `sql/migracao-horario-funcionamento.sql`, `sql/migracao-status-comparecimento.sql` e o estado do login Google.
- **Não reimportar `sql/BD_v2.sql`** sobre este banco: ele serve para criar um ambiente **novo e vazio**. Reimportar apaga/reconstrói dados.
- O banco local **não é cópia automática da produção**. Ele contém a conta de demonstração e testes; a produção contém os dados reais.
- Acesso administrativo pelo cliente `mariadb.exe`:

```powershell
& "$env:USERPROFILE\Documents\AgenDay-Tools\mariadb-11.8.9-winx64\bin\mariadb.exe" "--defaults-extra-file=$env:USERPROFILE\Documents\AgenDay-Backups\.mysql-local.cnf" --host=127.0.0.1 --port=3306 agenday_local
```

- `conexao.php` local usa `HOST=127.0.0.1`, porta `3306` e `DB=agenday_local`. A senha está **apenas** nesse arquivo privado.

### 4.1 Conta de demonstração

- Existe **no banco local e no site publicado** (produção, estabelecimento `#22`).
- Login: `studio@demonstracao.com` — **a senha está com o responsável e não é versionada**. Não procurar em documentação nem em commit.
- Conteúdo: Studio Bella Vita, 5 serviços, 4 funcionárias, 15 clientes, horário seg–sáb com almoço, 2 bloqueios e **173 agendamentos** fictícios.
- Recriar no local: `C:\Users\EDER\AgenDay-Local\ferramentas\semear-demo-local.php` (executa só contra `127.0.0.1`, apaga e recria apenas a conta demo, e não deve ser copiado para o repositório). Uso:

```powershell
& "$env:USERPROFILE\Documents\AgenDay-Tools\php-8.4\php.exe" "C:\Users\EDER\AgenDay-Local\ferramentas\semear-demo-local.php"
```

- O gerador é determinístico e ancora os 173 agendamentos em **20/11/2026** (semana da feira); o `id` local da conta pode mudar a cada recriação.
- Recriar em produção é operação de **banco de produção**: exige autorização própria, backup recente e o procedimento da seção "Mudança de banco em produção" em [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md).

## 5. Testes que rodam neste PC

```powershell
$php = "$env:USERPROFILE\Documents\AgenDay-Tools\php-8.4\php.exe"
$repo = 'C:\Users\EDER\AgenDay-PI'

& $php -l "$repo\agendar.php"                       # sintaxe de qualquer PHP alterado
& $php "$repo\tests\agendamento-validacao.php"      # não conecta ao banco
& $php "$repo\tests\agendamento-cancelamento.php"   # não conecta ao banco
node "$repo\tests\responsividade-check.js"          # 210 verificações; exige versão igual do responsivo.css
python -m unittest discover -s "$repo\tests" -p test_publicacao_assistida.py -v
```

O CI roda `php -l`, os dois testes de agendamento e o `responsividade-check`. **CI verde não prova integração com MySQL nem comportamento no site publicado.**

## 6. Navegador e QA local

- O navegador usado nos testes é o **Edge** do próprio PC, com sessões reais (inclusive InfinityFree/phpMyAdmin).
- **Não há HTTPS local.** Login Google e recursos que exigem origem segura podem falhar em `127.0.0.1:8080`; use usuário e senha do banco local.
- Contas de QA locais: `C:\Users\EDER\AgenDay-Local\credenciais-locais.txt` (fora do Git).
- Ao trocar de conta, **sair antes** de entrar com outra: a mesma sessão de navegador é compartilhada entre abas.
- Se outra pessoa estiver testando no site publicado ao mesmo tempo, coordenar antes de trocar a sessão.

## 7. Problemas comuns neste ambiente

| Sintoma | Causa provável | O que fazer |
| --- | --- | --- |
| Página abre em branco / erro de conexão | MariaDB desligado | Rodar `iniciar.ps1`; conferir porta 3306 |
| `port 8080 already in use` | Servidor PHP antigo rodando | `parar.ps1` ou encerrar o `php.exe` da porta |
| "A conexão com o banco ainda não foi configurada" | `conexao.php` ausente ou com placeholder | Copiar de `conexao.exemplo.php` e preencher no PC |
| CSS/JS antigo no navegador | Cache ou versão `?v=` não atualizada | Subir a versão no PHP que carrega o arquivo; recarregar com cache limpo |
| `declare(strict_types=1)` quebra após editar | BOM UTF-8 gravado no início do arquivo | Salvar como UTF-8 **sem BOM** (`[IO.File]::WriteAllText` com `UTF8Encoding($false)`); PowerShell 5.1 `Set-Content -Encoding UTF8` grava BOM |
| Acentuação corrompida em arquivo novo | Gravado em ANSI/Windows-1252 | Regravar em UTF-8 sem BOM e conferir com um script PHP |
| Upload de foto não aparece | Pasta `arquivos/` ausente ou sem permissão | Conferir `arquivos/clientes` e `arquivos/funcionarios` |

## 8. Limites deste ambiente

- É **desenvolvimento**, não produção: sem HTTPS, sem CDN, banco local com dados fictícios e de teste.
- Restaurar o banco local a partir de um backup **sobrescreve** os dados atuais; fazer cópia antes.
- Os caminhos acima são desta máquina. Em outro PC, os mesmos papéis existem, mas os caminhos mudam — atualizar este documento em vez de assumir.

## 9. Para outra sessão ou outra IA

1. Ler [PASSAGEM_DE_CONTEXTO.md](PASSAGEM_DE_CONTEXTO.md) — estado atual, regras, o que pedir ao responsável e equivalência de ferramentas.
2. Ler [AGENTS.md](../AGENTS.md) antes de qualquer alteração.
3. Conferir `git status`, `gh auth status` e as portas 3306/8080 **antes** de concluir que algo está quebrado.
4. Não copiar `conexao.php`, `.env.backup`, dumps ou `credenciais-locais.txt` para o repositório, PR, chat ou print.

# WhatsApp do AgenDay (WAHA + ngrok) — guia operacional

Atualizado em 23/11/2026. Este guia é para o **PC que fica ligado durante a feira**, não para a hospedagem.

O AgenDay não envia WhatsApp direto: ele coloca a mensagem numa fila (`mensagem_whatsapp`) e chama, por HTTPS de saída, um servidor **WAHA** que roda no seu PC. O WAHA é um WhatsApp Web automatizado, pareado ao número de um celular por QR code. Como a hospedagem não consegue enxergar o seu PC, o WAHA precisa de um endereço público: é o **ngrok** que cria esse endereço.

```
Navegador do cliente
   → agenday-agendamentos.infinityfree.io (PHP/MySQL)
      → fila no banco
         → HTTPS de saída → https://xxxx.ngrok-free.app (túnel)
            → WAHA no PC (localhost:3000) → WhatsApp do celular pareado
```

Consequências que precisam ser aceitas:

- O PC precisa estar **ligado, com Docker Desktop aberto e o ngrok rodando** durante todo o uso.
- Após reiniciar o PC, confira se o túnel voltou com o endereço configurado em `whatsapp-plataforma.php`. Atualize a URL nessa página apenas se o endereço tiver mudado.
- O número pareado é o remetente. Quem escaneia o QR precisa estar disponível no momento do pareamento.
- WAHA é um projeto não oficial: existe risco de o WhatsApp bloquear o número. **Não usar o número pessoal principal.**
- Sem WhatsApp, o portal continua funcionando com a contingência (login só pelo celular), descrita no fim.

## Pré-requisitos

Na conferência de 23/11/2026, `docker ps` mostrou o contêiner `agenday-waha` em execução e a API local do ngrok informou um túnel ativo. Isso comprova o estado dos processos naquele momento, não a entrega de um lembrete ao celular. Os passos de instalação abaixo servem para preparar outro PC ou repor um componente ausente.

| Item | Referência para operação |
| --- | --- |
| Docker | No PC da feira, conferir se `agenday-waha` aparece em `docker ps`. |
| WSL2 | Requisito da instalação com o backend WSL2 do Docker Desktop. |
| ngrok | Conferir o túnel na API local `http://127.0.0.1:4040/api/tunnels`. |
| Conta ngrok + authtoken | já existe (o token **não** vai para o chat nem para o Git) |
| Celular do número remetente | combinado com o responsável pelo número |

## Passo 1 — Instalar o Docker Desktop (exige administrador e reinício)

Em um PowerShell **como administrador**:

```powershell
winget install --id Docker.DockerDesktop -e --accept-package-agreements --accept-source-agreements
```

Depois **reinicie o PC**. Ao voltar, abra o **Docker Desktop**, aceite o contrato e, quando pedir, permita a integração com o WSL2. Não é necessário criar conta: escolha "Skip" / "Continue without signing in".

Confirme em um PowerShell novo:

```powershell
docker --version
docker run --rm hello-world
```

Se `hello-world` imprimir a mensagem de sucesso, o Docker está pronto.

## Passo 2 — Criar a pasta do WAHA e as credenciais

A pasta fica **fora do repositório** (o `.env` e as sessões nunca podem entrar no Git):

```powershell
New-Item -ItemType Directory -Force "$env:USERPROFILE\Documents\AgenDay-Tools\waha" | Out-Null
Set-Location "$env:USERPROFILE\Documents\AgenDay-Tools\waha"
docker run --rm -v "${PWD}:/app/env" devlikeapro/waha init-waha /app/env
Get-Content .env
```

O comando gera um `.env` com `WAHA_API_KEY`, `WAHA_DASHBOARD_USERNAME` e `WAHA_DASHBOARD_PASSWORD`. Guarde a **API key** (a mesma que vai na página WhatsApp da plataforma, `whatsapp-plataforma.php`) e a senha do painel.

## Passo 3 — Subir o WAHA

Ainda na mesma pasta:

```powershell
docker run -d --name agenday-waha --restart unless-stopped `
  --env-file "${PWD}\.env" `
  -v "${PWD}\sessions:/app/.sessions" `
  -p 3000:3000 `
  devlikeapro/waha
```

- `--restart unless-stopped`: volta sozinho quando o PC liga (desde que o Docker Desktop inicie junto).
- O volume `sessions` guarda o pareamento: sem ele, o QR teria que ser escaneado de novo a cada reinício.
- Se já existir um contêiner com esse nome: `docker rm -f agenday-waha` e rode o comando de novo.

Verificação:

```powershell
docker ps --filter name=agenday-waha
curl.exe -s -H "X-Api-Key: SUA_CHAVE" http://127.0.0.1:3000/api/sessions/default
```

Deve responder um JSON com `"status": "STOPPED"` (ou `SCAN_QR`/`WORKING`).

## Passo 4 — Parear o número (QR)

1. Abra <http://localhost:3000/dashboard>.
2. Entre com o usuário e a senha do `.env`.
3. Cole a **API key** quando o painel pedir para conectar ao servidor.
4. Inicie a sessão **`default`** (nome que o AgenDay usa).
5. Quando o status virar `SCAN_QR`, clique no ícone da câmera para ver o QR.
6. No celular do número remetente: **WhatsApp → Configurações → Aparelhos conectados → Conectar aparelho** e escaneie.
7. O status precisa chegar a **`WORKING`**. Se aparecer "Click to reload QR", pare a sessão e inicie de novo.

## Passo 5 — Publicar o endereço com o ngrok

Instale (uma vez):

```powershell
winget install --id Ngrok.Ngrok -e --accept-package-agreements --accept-source-agreements
```

Registre o token da sua conta (uma vez, no seu PC; não cole em conversa nem em arquivo do repositório):

```powershell
ngrok config add-authtoken SEU_TOKEN
```

Abra o túnel (deixe esta janela aberta enquanto o sistema estiver em uso):

```powershell
ngrok http 3000
```

Copie o endereço `https://...ngrok-free.app` que aparece em "Forwarding". Teste de fora do PC:

```powershell
curl.exe -s -H "X-Api-Key: SUA_CHAVE" https://SEU-ENDERECO.ngrok-free.app/api/sessions/default
```

Se responder o JSON da sessão, a hospedagem também conseguirá falar com o WAHA.

## Passo 6 — Configurar no AgenDay

A conexão do WhatsApp é **uma só, da plataforma**: o número é único e atende todos os estabelecimentos. Por isso ela não fica na tela de Configurações do estabelecimento.

No domínio administrativo (`agenday-sistema.infinityfree.io`), com qualquer conta logada, abra **`https://agenday-sistema.infinityfree.io/whatsapp-plataforma.php`**:

| Campo | Valor |
| --- | --- |
| Ativar envio pelo WhatsApp | ligado |
| Endereço do WAHA | `https://SEU-ENDERECO.ngrok-free.app` (**sem** barra no fim) — no PC do responsável é o domínio fixo `https://additionally-nondeluded-elane.ngrok-free.dev` |
| Sessão | `default` |
| Chave da API | a `WAHA_API_KEY` do `.env` |

Salve e clique em **Testar conexão**. O retorno esperado é a sessão `WORKING`. Se houver mensagens na fila, use **Enviar pendentes agora**.

> **Alteração protegida (21/11/2026):** depois da primeira configuração, salvar exige digitar a **chave de API atual** no campo de confirmação. Assim, um estabelecimento logado não consegue trocar o endereço nem desligar o envio de todos. A chave nunca volta para o navegador.

> **Produção (21/11/2026):** o código do portal já está publicado no InfinityFree e a conexão **já foi preenchida e testada** pelo responsável (`ativo=1`, domínio fixo do ngrok, sessão `default`). O primeiro agendamento real pelo portal foi confirmado com mensagem recebida. Ao trocar de computador ou reinstalar o WAHA, refaça este passo com a nova chave.

## Passo 7 — Teste real (obrigatório antes da feira)

1. Abra `https://agenday-agendamentos.infinityfree.io/?e=studio` em um celular com outro número.
2. Informe o celular, receba o código no WhatsApp e entre.
3. Agende um horário livre e confira a mensagem de confirmação.
4. Use o link de cancelamento da mensagem e confira o aviso de cancelamento.
5. No painel do WAHA, confira que a sessão continua `WORKING`.

## Rotina do dia da feira

1. Dar dois cliques no atalho **AgenDay - Iniciar WhatsApp** na Área de Trabalho (ou rodar `powershell -ExecutionPolicy Bypass -File scripts\iniciar-feira.ps1`). Ele limpa as variáveis de proxy, sobe o Docker Desktop se preciso, inicia o contêiner `agenday-waha`, confere se a sessão `default` está `WORKING` e abre o ngrok no domínio fixo. A chave do WAHA é lida do próprio contêiner e não aparece na tela.
2. Ler o resumo: se aparecer `Sessao: WORKING` e `ngrok: https://...`, está pronto. Se o script avisar de pendência, ele diz o que fazer (normalmente escanear o QR em <http://localhost:3000/dashboard>).
3. Se o endereço do ngrok mudou, atualizar em **`whatsapp-plataforma.php`** (informando a chave de API atual) e salvar.
4. **Testar conexão** e enviar uma mensagem de teste para o próprio celular.
5. Deixar o PC ligado, sem suspender, e o ngrok aberto.

O script não contém senhas nem tokens e não altera banco, código ou hospedagem. `-ComPhp` também sobe o PHP local em `127.0.0.1:8080`; `-SemPausa` serve para execução automática.

Depois de reiniciar o computador, o Docker Desktop e o contêiner voltam sozinhos (`restart unless-stopped`) e o pareamento é preservado em `Documents\AgenDay-Tools\waha\sessions`; o que **não** volta sozinho é o ngrok — é justamente o que o script resolve.

## Problemas comuns

| Sintoma | Causa provável | O que fazer |
| --- | --- | --- |
| "Não foi possível falar com o WhatsApp" | ngrok parado ou endereço antigo | rodar o atalho **AgenDay - Iniciar WhatsApp**, conferir o endereço e salvar |
| Erro 401 | chave da API diferente do `.env` | copiar de novo a `WAHA_API_KEY` |
| Sessão `STOPPED` / `SCAN_QR` | pareamento perdido | iniciar a sessão e escanear o QR outra vez |
| Mensagem fica "pendente" | WAHA fora no momento do envio | abrir a agenda ou usar "Enviar pendentes agora" |
| Erro de certificado | endereço `http://` ou IP | usar sempre o `https://...ngrok-free.app` |
| `docker: command not found` | Docker Desktop fechado | abrir o Docker Desktop e esperar o ícone ficar verde |

A fila tenta de novo sozinha (até 5 tentativas, com espera crescente) e também é processada quando alguém abre a agenda ou o portal — não existe cron na hospedagem.

## Plano B — feira sem WhatsApp

Se o WhatsApp não puder ser usado, o portal funciona sem ele: em **Configurações → Estabelecimento**, desligue **Exigir código de 6 dígitos enviado pelo WhatsApp para entrar**. O cliente entra só com o celular, agenda, remarca e cancela normalmente; as mensagens ficam na fila sem atrapalhar. Cada estabelecimento controla essa opção na própria conta — a conexão da plataforma continua como está.

Isso serve para demonstração: sem o código, quem souber o número de outra pessoa entra na conta dela. Não deixar desligado fora do período da feira.

## Segurança e limites

- A chave da API e o `.env` ficam **somente** no PC, fora do repositório; nunca colar em documento, print ou chat.
- O túnel expõe **apenas** a porta 3000 do WAHA, que exige `X-Api-Key`. Não criar túnel para outras portas do PC.
- Não usar o número pessoal principal nem o número da coordenação.
- WAHA Core gratuito: uma sessão e mensagens de texto — é o suficiente para código, confirmação, lembrete, remarcação e cancelamento.

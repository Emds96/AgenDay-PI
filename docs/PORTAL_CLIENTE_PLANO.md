# Portal do cliente — plano completo

**Status:** aguardando aprovação do responsável em 21/11/2026.
**Escopo:** página pública do cliente, acesso por telefone, agendamento, remarcação, cancelamento e avisos por WhatsApp.
**Regra:** este documento **não** autoriza deploy, alteração de banco em produção nem publicação. Ele descreve o que será feito depois da aprovação.

Checklist vivo: [`PORTAL_CLIENTE_CHECKLIST.md`](PORTAL_CLIENTE_CHECKLIST.md).

---

## 1. Objetivo

Dar ao cliente final um endereço próprio (`agenday-agendamentos.infinityfree.io`) onde ele:

1. entra com o **número de celular** confirmado por código no WhatsApp;
2. **agenda** escolhendo serviço, profissional, data e hora;
3. vê **seus agendamentos**;
4. **remarca** ou **cancela** sozinho;
5. recebe **confirmação e lembrete** no WhatsApp.

O estabelecimento continua usando o sistema atual em `agenday-sistema.infinityfree.io`, com login por e-mail e senha. Os dois domínios usam **o mesmo banco e o mesmo código**.

## 2. Decisões travadas

| Tema | Decisão | Data |
| --- | --- | --- |
| Arquitetura | Mesmo app em duas pastas, um banco, um deploy (plano A) | 21/11/2026 |
| Domínio do cliente | `agenday-agendamentos.infinityfree.io` (criado, pasta própria) | 21/11/2026 |
| Identificação do estabelecimento | `slug` público no link (`?e=studio`) | 21/11/2026 |
| Acesso do cliente | Login por telefone + código no WhatsApp, sessão de 30 dias | 21/11/2026 |
| Código no acesso | Obrigatório, com contingência documentada | 21/11/2026 |
| WhatsApp | WAHA (não oficial) rodando no PC do responsável + túnel HTTPS | 21/11/2026 |
| Banco | Migração aditiva aplicada manualmente no phpMyAdmin antes do deploy | 21/11/2026 |
| Deploy | Um só, com backup verificado e autorização de SHA/destino | 21/11/2026 |

## 3. Estado da infraestrutura (verificado em 21/11/2026)

| Item | Situação |
| --- | --- |
| Domínio novo | criado; pasta `agenday-agendamentos.infinityfree.io/htdocs` |
| PHP na pasta nova | 8.4.28, `mysqli`, `curl`, `openssl` funcionando |
| Banco na pasta nova | conecta no mesmo banco (12 tabelas) |
| `conexao.php` na pasta nova | copiado sem exibição de conteúdo |
| Saída HTTPS do servidor | funciona (teste com `example.com` e `api.brevo.com`) |
| `open_basedir` | restrito à pasta do domínio — **impede** incluir arquivos do `htdocs` principal |
| Conteúdo da pasta nova | vazia (só `conexao.php`); o domínio responde 403 até receber o app |
| Cron na InfinityFree | inexistente; agendador do MySQL desligado |
| Acesso automatizado de entrada | bloqueado pelo sistema de segurança (exige JavaScript e cookie) |
| Limite de requisições | 50.000 por dia por conta (os dois domínios dividem a conta) |

Consequências registradas:

- A publicação precisa enviar o pacote para **as duas pastas**.
- Nenhum serviço externo consegue "chamar" o site; só o site consegue chamar para fora.
- Não existe lembrete com hora garantida sem cron; ver seção 8.

## 4. Arquitetura

```text
Cliente (celular)
   │  https://agenday-agendamentos.infinityfree.io/?e=studio
   ▼
[cliente.php + api/cliente-*]  ── mesmo banco ──  [agenda.php + admin]
   │
   │ HTTPS de saída (funciona)
   ▼
[ngrok: endereço fixo] → [WAHA no PC] → WhatsApp
```

- **Um código, um banco, um deploy.**
- O domínio do cliente é uma segunda porta; a guarda de host impede o admin nele.
- O site **chama** o WAHA; o WAHA nunca chama o site.

## 5. Banco — migração aditiva

Arquivo `sql/migracao-portal-cliente.sql`. Só adiciona; não altera nem remove dados existentes. Aplicação manual no phpMyAdmin, **antes** do deploy.

### 5.1 Estabelecimento

```sql
ALTER TABLE estabelecimento
  ADD COLUMN slug VARCHAR(60) NULL AFTER nome_estabelecimento,
  ADD COLUMN dominio VARCHAR(120) NULL AFTER slug,
  ADD COLUMN exigir_codigo_whatsapp TINYINT(1) NOT NULL DEFAULT 1 AFTER dominio,
  ADD UNIQUE KEY uq_estabelecimento_slug (slug),
  ADD UNIQUE KEY uq_estabelecimento_dominio (dominio);
```

- `slug`: apelido público; nulo enquanto o estabelecimento não usar o portal.
- `dominio`: permite abrir o portal pelo domínio, sem `?e=`.
- `exigir_codigo_whatsapp`: contingência (seção 8.5).

### 5.2 Telefone normalizado em clientes

```sql
ALTER TABLE clientes
  ADD COLUMN telefone_digits VARCHAR(15) NULL AFTER contato_cliente,
  ADD KEY idx_clientes_telefone (id_estabelecimento, telefone_digits);

UPDATE clientes
SET telefone_digits = REGEXP_REPLACE(contato_cliente, '[^0-9]', '')
WHERE contato_cliente IS NOT NULL;
```

Motivo: o portal procura o cliente pelo telefone. Hoje o campo é digitado livremente (`(92) 99999-9999`, `92999999999`). Sem normalizar, a mesma pessoa viraria dois cadastros.

### 5.3 Token público do agendamento

```sql
ALTER TABLE agendamentos
  ADD COLUMN token_publico CHAR(64) NULL AFTER observacoes,
  ADD UNIQUE KEY uq_agendamentos_token (token_publico);
```

Usado no link de cancelamento enviado por WhatsApp. Gerado na criação pelo portal e, sob demanda, antes de enviar qualquer mensagem.

### 5.4 Verificação de acesso

```sql
CREATE TABLE IF NOT EXISTS verificacao_acesso (
  id_verificacao INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  telefone VARCHAR(15) NOT NULL,
  nome_informado VARCHAR(120) NULL,
  codigo_hash CHAR(64) NOT NULL,
  salt CHAR(32) NOT NULL,
  tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expira_em DATETIME NOT NULL,
  usado_em DATETIME NULL,
  ip_hash CHAR(64) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_verificacao),
  KEY idx_verificacao_busca (id_estabelecimento, telefone, criado_em),
  CONSTRAINT fk_verificacao_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.5 Sessão do cliente

```sql
CREATE TABLE IF NOT EXISTS sessao_cliente (
  id_sessao CHAR(64) NOT NULL,
  id_estabelecimento INT UNSIGNED NOT NULL,
  id_cliente INT UNSIGNED NOT NULL,
  csrf_token CHAR(64) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acesso_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em DATETIME NOT NULL,
  ip_hash CHAR(64) NULL,
  PRIMARY KEY (id_sessao),
  KEY idx_sessao_cliente (id_estabelecimento, id_cliente),
  CONSTRAINT fk_sessao_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_sessao_cliente FOREIGN KEY (id_estabelecimento, id_cliente)
    REFERENCES clientes (id_estabelecimento, id_cliente)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Sessão no banco (não na sessão PHP) para sobreviver à limpeza do PHP e permitir revogação.

### 5.6 Fila de mensagens

```sql
CREATE TABLE IF NOT EXISTS mensagem_whatsapp (
  id_mensagem INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_estabelecimento INT UNSIGNED NOT NULL,
  id_agendamento INT UNSIGNED NULL,
  telefone VARCHAR(15) NOT NULL,
  tipo ENUM('codigo','confirmacao','lembrete','cancelamento','remarcacao') NOT NULL,
  corpo TEXT NOT NULL,
  status ENUM('pendente','enviando','enviado','erro') NOT NULL DEFAULT 'pendente',
  tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  agendado_para DATETIME NOT NULL,
  enviado_em DATETIME NULL,
  erro VARCHAR(255) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_mensagem),
  KEY idx_fila (status, agendado_para),
  KEY idx_mensagem_empresa (id_estabelecimento, criado_em),
  KEY idx_mensagem_agendamento (id_agendamento),
  CONSTRAINT fk_mensagem_empresa FOREIGN KEY (id_estabelecimento)
    REFERENCES estabelecimento (id_estabelecimento)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5.7 Configuração do WhatsApp

```sql
CREATE TABLE IF NOT EXISTS whatsapp_config (
  id_config TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ativo TINYINT(1) NOT NULL DEFAULT 0,
  base_url VARCHAR(190) NOT NULL DEFAULT '',
  api_key VARCHAR(190) NOT NULL DEFAULT '',
  sessao VARCHAR(60) NOT NULL DEFAULT 'default',
  ultima_execucao DATETIME NULL,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_config)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO whatsapp_config (id_config) VALUES (1);
```

### 5.8 Estabelecimento de demonstração

```sql
UPDATE estabelecimento
SET slug = 'studio',
    dominio = 'agenday-agendamentos.infinityfree.io'
WHERE email_estabelecimento = 'studio@demonstracao.com';
```

Usa o e-mail (e não o ID) para funcionar igual no banco local e no de produção. Conferir a quantidade de linhas afetadas depois de executar.

## 6. Acesso do cliente (login por telefone)

### 6.1 Fluxo

1. Cliente abre o link, escolhe o serviço e o horário (pode explorar antes de entrar).
2. Ao confirmar, informa **telefone**; se for o primeiro acesso, também o **nome**.
3. Recebe **6 dígitos** no WhatsApp; o código vale 10 minutos e 5 tentativas.
4. Ao acertar, a sessão é criada (30 dias) e o agendamento é gravado.

Alternativa mais direta: entrar primeiro (telefone → código) e depois agendar. A interface oferece as duas ordens; o código só é pedido uma vez por sessão.

### 6.2 Regras

- Telefone normalizado para dígitos (DDD + número, 10 ou 11 dígitos).
- Código guardado como hash com `salt`; nunca em texto.
- Limites: 3 códigos por 10 minutos por telefone, 10 por dia por telefone, 20 por hora por IP.
- Sessão: cookie `AGENDAY_CLIENTE`, 64 caracteres hexadecimais, `HttpOnly`, `Secure`, `SameSite=Lax`, 30 dias.
- CSRF: token por sessão, enviado no cabeçalho `X-CSRF-Token`.
- Logout apaga a linha da sessão.

### 6.3 Por que não senha

O telefone é a identidade e o WhatsApp é o segundo fator. Sem senha para esquecer, sem recuperação de senha, e o número fica comprovado — que é exatamente o que o responsável pediu.

## 7. Agendamento, remarcação e cancelamento

### 7.1 Agendar

- Serviço (nome, duração, preço) → profissional (ou "qualquer um") → data e hora.
- Horários calculados por `lib/disponibilidade.php` (mesmo motor da agenda do admin): horário de funcionamento, almoço, bloqueios, agendamentos existentes, antecedência mínima e janela máxima.
- Gravação com transação e `SELECT ... FOR UPDATE` por profissional, igual a `criar-agendamento.php`.
- Duração vem do cadastro do serviço, nunca do navegador.

### 7.2 Meus agendamentos

- Lista os próximos e os últimos 90 dias.
- Mostra serviço, profissional, data, hora, status e se a mensagem foi enviada.

### 7.3 Remarcar

- Escolhe outro horário livre; atualiza o registro em uma transação.
- Se mudar de profissional, os dois são bloqueados na ordem crescente de ID (evita deadlock).
- Respeita a antecedência mínima de cancelamento do estabelecimento.
- Enfileira mensagem de remarcação.

### 7.4 Cancelar

- Pelo portal (sessão) ou pelo link da mensagem (`cliente-cancelar.php?t=token`), sem login.
- Respeita `horas_cancelamento` do estabelecimento; fora do prazo, explica e orienta a falar com o estabelecimento.
- Mantém o histórico (`status = 'cancelado'`), como hoje.
- Enfileira mensagem de cancelamento.

## 8. WhatsApp (WAHA)

### 8.1 Como funciona

- WAHA roda no PC do responsável, dentro do Docker, e conecta ao WhatsApp como **aparelho vinculado** (igual ao WhatsApp Web).
- Um túnel (ngrok) publica o WAHA em um endereço HTTPS fixo.
- O site chama `POST {base_url}/api/sendText` com a chave no cabeçalho e timeout de 5 segundos.

### 8.2 Dois caminhos de envio

| Tipo | Caminho | Motivo |
| --- | --- | --- |
| Código de acesso | **imediato** (síncrono) | o cliente está esperando |
| Confirmação, lembrete, remarcação, cancelamento | **fila** | tolera atraso e reenvio |

Se o envio imediato falhar, a API responde erro e **nada é gravado** — sem agendamento fantasma.

### 8.3 Fila e reenvio

- Status: `pendente → enviando → enviado` ou `erro`.
- Até 5 tentativas, com espera crescente.
- Gatilho sem cron: ao abrir a agenda do admin ou o portal, o sistema processa até 2 pendências (trava de 1 minuto). Botão "Enviar pendentes agora" na tela de configurações.
- Quando disponível, o processamento roda depois da resposta (`fastcgi_finish_request`) para não atrasar a página.

### 8.4 Mensagens

- **Código:** nome do estabelecimento + 6 dígitos + validade.
- **Confirmação:** serviço, data, hora, profissional e link de cancelamento.
- **Remarcação:** novo dia e hora + link.
- **Cancelamento:** dia e hora cancelados + link para agendar de novo.
- **Lembrete:** véspera do atendimento (quando processado).

### 8.5 Contingência

Se o WhatsApp estiver fora durante a apresentação:

1. **Sessão de 30 dias** criada no dia anterior continua válida — o cliente que já entrou segue agendando.
2. **Link de cancelamento** funciona sem login.
3. **Chave `exigir_codigo_whatsapp`** pode ser desligada em Configurações. Com ela desligada, o acesso por telefone não pede código — usar **somente** em contingência e religar depois.

### 8.6 Instalação (o que falta)

| Item | Situação | Responsável |
| --- | --- | --- |
| Número ativo no WhatsApp | número de um amigo; QR escaneado uma vez por ele | responsável |
| Docker Desktop + WSL2 | não instalado | IA instala, responsável aprova reinício |
| Conta gratuita no ngrok | não criada | responsável |
| WAHA (imagem, configuração, pareamento) | não iniciado | IA |
| Integração, testes e ajustes | não iniciado | IA |

Custo previsto: R$ 0 (WAHA Community, 1 sessão; ngrok free, 20.000 requisições/mês).

## 9. Guarda de host

- `lib/guarda-host.php` identifica o domínio da requisição.
- Em `sessao.php`, antes de iniciar a sessão: se o host for do cliente e a página não for do portal, redireciona para `/`.
- No domínio do cliente respondem apenas: `cliente.php`, `cliente-cancelar.php`, `api/cliente-*`, CSS, JS e imagens.
- No domínio do admin, `cliente.php?e=slug` continua funcionando como plano B permanente.
- Validação local: requisições com `Host: agenday-agendamentos.infinityfree.io` contra o servidor local, conferindo que nenhuma rota administrativa responde 200.

## 10. Página e identidade visual

- Mobile primeiro, seguindo os tokens e componentes do projeto (`css/design-system.css`, `css/responsivo.css`).
- Etapas: serviço → profissional → data/hora → dados → código → confirmação.
- Estados de erro claros (horário ocupado, código expirado, WhatsApp indisponível).
- Link "Adicionar ao calendário" (arquivo `.ics`) na confirmação.
- Acessibilidade: foco visível, rótulos, contraste e navegação por teclado.

## 11. Publicação nas duas pastas

- `scripts/publicar_ftp.py` passa a receber uma lista de destinos: **primeiro** `agenday-agendamentos.infinityfree.io/htdocs`, **depois** `htdocs`.
- Se o primeiro destino falhar, o processo aborta antes de tocar no site atual.
- `index.php` é sempre o último arquivo de cada pasta.
- `conexao.php` não entra no pacote e nunca é sobrescrito.
- `config/brevo-api-config.php` é gravado nas duas pastas a partir do secret.
- Nenhum arquivo remoto é apagado.
- A verificação pós-deploy é feita no navegador do PC (o sistema de segurança da InfinityFree bloqueia verificação por `curl` na CI).

## 12. Segurança e privacidade

- Estabelecimento resolvido no servidor (slug/domínio); nunca por `id_estabelecimento` enviado pelo navegador.
- Prepared statements em todas as consultas.
- Código com hash + salt, expiração, tentativas limitadas e uso único.
- Sessão e CSRF no banco; cookie `HttpOnly` e `Secure`.
- Token de cancelamento aleatório de 64 caracteres, comparado com `hash_equals`.
- O portal só devolve os dados do próprio cliente autenticado.
- Telefone é dado pessoal: guardar apenas o necessário, com aceite explícito no formulário.
- Chave do WAHA fica na tabela de configuração, acessível só ao admin; registrar como limitação (não é criptografada em repouso).

## 13. Testes

| Onde | O que |
| --- | --- |
| Local | `php -l` em todos os arquivos alterados |
| Local | testes puros: telefone, slug, código, token, templates |
| Local | fila de mensagens com remetente simulado (sem rede) |
| Local | fluxo completo no navegador: entrar, agendar, listar, remarcar, cancelar |
| Local | guarda de host com `Host` falso em todas as rotas da raiz |
| Local | `node tests/responsividade-check.js` com a página nova |
| CI | testes atuais + os novos |
| Produção | dois domínios, um agendamento real no número da demonstração, mensagem recebida, cancelamento pelo link |

## 14. Cronograma

| Quando | Entrega |
| --- | --- |
| 21/11 | migração, bibliotecas, acesso, agendamento, meus agendamentos, cancelar |
| 22/11 | remarcar, WhatsApp, configurações, guarda, publicador, testes, deploy |
| 23/11 | congelamento; apenas correção crítica |

Se o tempo apertar, "remarcar" é entregue como "cancelar + agendar" na interface, sem endpoint novo.

## 15. Riscos e contingência

| Risco | Impacto | Mitigação |
| --- | --- | --- |
| WAHA não subir a tempo | sem código de acesso | chave de contingência; sessão criada antes |
| Túnel bloqueado no local da feira | sem mensagens | hotspot do celular; link de cancelamento independente |
| Número bloqueado pelo WhatsApp | sem envio | volume baixo, só números informados; número reserva |
| Sem cron | lembrete atrasado | gatilho por uso + botão manual; limitação registrada |
| Migração falhar | deploy bloqueado | migração aditiva, testada no banco local antes |
| Duas pastas divergirem | comportamento diferente por domínio | deploy sempre nas duas, na mesma execução |

## 16. Fora de escopo

- Conta do cliente em vários estabelecimentos.
- Pagamento, sinal ou cobrança.
- Resposta automática "SIM" no WhatsApp.
- Lembretes com horário garantido.
- Promoções e campanhas.
- Aplicativo móvel.

## 17. Pendências para começar

1. Confirmar o slug do estabelecimento de demonstração (`studio`).
2. Confirmar que o número está ativo no WhatsApp.
3. Autorizar a instalação do Docker Desktop (exige reinício do PC).
4. Criar a conta gratuita no ngrok.
5. Aprovar este plano.

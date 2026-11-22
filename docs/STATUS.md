# STATUS — AgenDay 2.0

## MENSAGENS-02 — Texto da confirmação fechado; lembrete e taxa em aberto (22/11/2026)

- **Contexto:** revisão das cinco mensagens do WhatsApp com o responsável. Código de acesso, remarcação e cancelamento foram aprovados sem mudança. A confirmação foi fechada; o lembrete e a ideia de taxa de cancelamento seguem em decisão.
- **Confirmação — texto aprovado (ainda não implementado):**
  ```
  Olá, Ana Souza!

  Agendamento confirmado no Studio Bella Vita:
  Serviço: Corte feminino
  Profissional: Bia
  Data: 24/11/2026 às 14:30

  Remarcar ou cancelar: até 24h antes do horário.
  Precisa remarcar ou cancelar? Acesse: https://agenday-agendamentos.infinityfree.io/?e=studio

  Enviado pela AgenDay em nome de Studio Bella Vita.
  ```
  Regras: **um único link** (portal `?e=slug`), sem o link de token de cancelamento; o prazo é **dinâmico**, lido de `horas_cancelamento` (24 → "24h"; 1 → "1 hora"; 0 → "até o horário do atendimento"). O link do portal exige o celular (e o código, quando o estabelecimento exige) — não é remarcação em um clique.
- **Lembrete — em aberto:** sem nenhum link. A primeira linha é "O prazo para remarcar ou cancelar já passou."; a última linha ainda será escolhida entre: (A) "Se não puder comparecer, avise com antecedência."; (B) "Se não puder comparecer, avise o Studio Bella Vita."; (C) telefone do estabelecimento quando cadastrado, com A como alternativa. Se `horas_cancelamento` for menor que 24, a frase muda para "Você ainda pode remarcar ou cancelar até X antes do horário."
- **Taxa de cancelamento — ideia do responsável, adiada:** cobrar uma taxa quando o cliente cancela ou remarca em cima da hora. O sistema **não tem meio de cobrança** (sem gateway, sem cartão salvo), então a taxa não pode ser aplicada automaticamente. Sem SQL, o máximo é um **aviso textual** na mensagem; registrar cancelamento tardio no painel é possível derivando `data_inicio` × `atualizado_em`, mas remarcação tardia **não** é recuperável (o horário antigo é sobrescrito). Uma configuração por estabelecimento exigiria coluna nova — depois da feira.
- **Estado real:** decisão registrada; **nada implementado além do LEMBRETE-01**; não publicado.

---

## LEMBRETE-01 — Lembrete automático 24h antes do horário (22/11/2026)

- **Pedido do responsável (22/11/2026):** com o celular já confirmado (TEL-01), avisar o cliente **um dia antes** do horário. Respondeu "pode fazer" para executar **somente no local**, sem publicar e sem SQL.
- **Por que só agora:** o lembrete depende de um celular com WhatsApp confirmado; antes do TEL-01 o painel aceitava fixo e número digitado errado. O `tipo='lembrete'` e a coluna `agendado_para` já existiam em `mensagem_whatsapp` — **nenhuma alteração de banco**.
- **Como funciona (`lib/whatsapp.php`):** `agenday_whatsapp_agendar_lembrete()` lê cliente, serviço, profissional e estabelecimento do próprio agendamento e enfileira o lembrete com `agendado_para = data_inicio − 24h`. Não agenda quando o cliente não tem celular com WhatsApp, o agendamento está cancelado/já começou, ou faltam menos de 30 minutos para a hora do lembrete (a confirmação acabou de sair). `agenday_whatsapp_cancelar_lembrete()` remove o pendente.
- **Um lembrete por agendamento:** a função apaga o lembrete antigo **antes** das checagens, então remarcar troca o aviso e um horário novo sem lembrete não deixa o aviso do horário antigo na fila.
- **Onde é chamado:** portal na **criação** e na **remarcação** (`api/cliente-agendamentos.php`) e painel na **criação** (`salvar-agendamento.php`). O **cancelamento** apaga o lembrete dentro de `agenday_agenda_cancelar_para()`, cobrindo portal, painel e link público de cancelamento de uma vez.
- **Fila sem cron:** antes de enviar, `agenday_whatsapp_limpar_lembretes_vencidos()` descarta lembrete de agendamento cancelado ou que já começou. A janela é tolerante: o aviso sai no próximo uso do sistema enquanto o horário não chegou — nunca depois.
- **Texto (`agenday_whatsapp_texto()`):** o lembrete deixou de cair em "Agendamento confirmado" e tem título próprio **"Lembrete do seu horário"**, mantendo serviço, profissional, data/hora, link de cancelamento e a assinatura "Enviado pela AgenDay em nome de …".
- **Link absoluto:** `agenday_portal_link_cancelamento()` passou a usar o **domínio público do portal** quando o estabelecimento não tem domínio próprio; antes devolvia caminho relativo, que não abre no WhatsApp.
- **Testes no PC local (22/11/2026):** `php -l` limpo nos cinco arquivos; `tests/portal-cliente.php` de 122 para **134** (título próprio, sem "confirmado", link, assinatura, constantes/funções e ligação nos fluxos); `responsividade-check` **219**; `agendamento-validacao` (19), `agendamento-cancelamento` (20), `configuracao-estabelecimento` (13) e `alteracao-senha-validacao` (16) limpos.
- **Provas com banco local (`agenday_local`, estabelecimento 29), sem envio real:** lembrete enfileirado 24h antes; reagendar não acumula; remarcação troca a data; horário a menos de 24h não gera; cancelar remove o pendente (inclusive cancelamento repetido); fila descarta lembrete de horário já iniciado e de agendamento cancelado; telefone fixo não gera. Cliente, agendamentos e mensagens de teste apagados ao final.
- **Pendências:** não publicado e sem teste na hospedagem; o **opt-out "responda SAIR"** continua adiado (exigiria coluna/tabela); agendamento criado no **painel** recebe lembrete, mas não recebe confirmação imediata — decisão para depois da feira.
- **Estado real:** implementado e testado **somente no PC local**, **não publicado**, **sem alteração de banco**.

---

## TEL-01 — Celular do cliente confirmado por código no painel (22/11/2026)

- **Pedido do responsável (21/11/2026):** a recepcionista pede o número, o sistema manda o código no WhatsApp e ela digita de volta — igual ao portal. Só número com WhatsApp; sem WhatsApp, o cliente é salvo sem número. Número repetido avisa e deixa continuar. Autorizou executar **somente no local**, sem publicar e sem SQL.
- **Por que importa:** o telefone é a identidade do cliente no portal (`telefone_digits`). Sem confirmação, o painel aceitava fixo e número digitado errado — e esse cliente nunca conseguia entrar no portal nem receber lembrete.
- **Regra separada:** `agenday_portal_telefone_whatsapp_ok()` (11 dígitos, 9 na terceira posição). O telefone fixo continua valendo como contato na ficha; só não recebe mensagem.
- **Endpoint novo `api/painel-telefone.php`** (POST autenticado + CSRF): `enviar` e `confirmar`. Reaproveita `verificacao_acesso` e o mesmo esquema de código do portal (6 dígitos, 10 minutos, 5 tentativas), com limites de 1 código por minuto, 3 a cada 10 minutos e 10 por dia. Número de outro cliente responde **409** com o nome, e a tela pergunta se deve continuar.
- **Confirmação na sessão, não no banco:** vale 30 minutos e é consumida no cadastro/edição. Nenhuma coluna nova, nenhum SQL.
- **Telas:** cadastro de cliente, edição de cliente e cliente rápido do agendamento usam o mesmo bloco (`js/confirmar-telefone.js`) com "Confirmar no WhatsApp" e "Este cliente não tem WhatsApp".
- **Edição não trava cliente antigo:** o celular só é exigido quando o número **muda**; corrigir nome de quem tem fixo continua funcionando. O modal já abre com "sem WhatsApp" marcado quando o cliente não tem número confirmado.
- **Sem trava no atendimento:** se o WhatsApp da plataforma estiver fora do ar (`agenday_whatsapp_pronto()` falso), a confirmação deixa de ser exigida e o cadastro segue — com o número entrando como não confirmado.
- **Provas no PC local (22/11/2026):** `php -l` limpo nos sete arquivos; `tests/portal-cliente.php` de 117 para **122** (celular com WhatsApp, fixo, 10 dígitos, vazio e nulo); `responsividade-check` de 214 para **219** (blocos e script nas duas telas).
- **Prova real com banco local:** fixo recusado no envio; número da Mariana recusado como duplicado com o nome; salvar sem confirmar recusado; "sem WhatsApp" salvou com `telefone_digits` NULL; código errado recusado; código certo confirmou (`(92) 9****-7777`) e o cadastro salvou; cliente rápido recusou sem confirmação; edição só do nome passou, troca para celular sem confirmação foi recusada, fixo com "sem WhatsApp" passou. As contas e verificações de teste foram apagadas.
- **Pendências:** o **lembrete automático** (lote B) foi implementado depois, no registro LEMBRETE-01; nada foi publicado e o comportamento na hospedagem não foi conferido.
- **Estado real:** implementado e testado **somente no PC local**, **não publicado**, **sem alteração de banco**.

---

## SLUG-01 — Endereço público automático e editável (21/11/2026)

- **Pedido do responsável (21/11/2026):** ao criar uma conta, o estabelecimento já deve sair com link público funcionando; e o dono precisa poder **editar o endereço** (exemplo: o nome repetido gerou `barbearia-joao-2` e ele quer `barbearia-joaoo`). Autorizou executar **somente no local**, sem publicar e sem SQL.
- **Diagnóstico:** `cadastrar.php` e `google-cadastro-concluir.php` gravavam apenas nome, e-mail e senha — `slug` e `dominio` ficavam **NULL**. Nenhuma tela gravava slug (não existia campo, nem `SET slug`). A função `agenday_portal_slug_sugerir()` existia, mas não era chamada. Resultado: só o Studio (id 29) tinha link, porque foi definido **à mão** na migração; e a tela do estabelecimento mostrava um link quebrado (`cliente.php?e=`) em vez de avisar que faltava o endereço.
- **Biblioteca (`lib/portal-cliente.php`):** novas funções `agenday_portal_slug_ocupado()` (consulta com `id_estabelecimento <> ?`), `agenday_portal_slug_disponivel()` (`barbearia-joao`, `barbearia-joao-2`… até 50, com fallback aleatório), `agenday_portal_slug_gravar()` (grava e repete em colisão de última hora, erro `1062`) e `agenday_portal_slug_analisar()` (normaliza + valida + mensagem de erro). `agenday_portal_base_url()` agora devolve **string vazia sem slug** e, sem domínio próprio, usa o **domínio público do portal** (`agenday_portal_hosts_publicos()`), em vez de um caminho relativo.
- **Cadastro:** `cadastrar.php` e `google-cadastro-concluir.php` geram o slug logo depois do `INSERT`. A geração fica em `try/catch`: se a migração do portal não estiver aplicada, o cadastro continua normalmente e o endereço fica pendente.
- **Tela do estabelecimento (`config.php`):** campo **"Endereço do portal"** na aba Estabelecimento. Texto com maiúscula ou acento é **convertido**, não recusado; reservado, curto e duplicado recebem mensagem própria. Campo em branco **gera pelo nome** apenas quando a conta ainda não tem endereço — nos outros casos mantém o atual. Grava `NULL` (nunca string vazia) para não colidir no índice único. A variável `$whatsappDisponivel` passou a se chamar `$portalDisponivel`, porque agora cobre slug e link, não só o WhatsApp.
- **Testes no PC local (21/11/2026):** `php -l` limpo nos quatro arquivos alterados; `tests/portal-cliente.php` de 110 para **117 verificações** (endereço digitado normalizado, letra repetida aceita, reservado/curto/vazio recusados, link vazio sem slug, domínio público sem domínio próprio, domínio próprio com prioridade); `responsividade-check` com **214**; `agendamento-validacao` (19), `agendamento-cancelamento` (20), `configuracao-estabelecimento` (13) e `alteracao-senha-validacao` (16) limpos.
- **Prova real no banco local (`agenday_local`):** dois cadastros "Barbearia Joao" geraram `barbearia-joao` (id 30) e `barbearia-joao-2` (id 31); editar o 31 para `barbearia-joaoo` gravou; editar o 30 para `barbearia-joaoo` recusou com "já está em uso"; `api` recusou como reservado; `ab` recusou por tamanho; campo vazio manteve o endereço; `  Barbearia   JOAO  ` normalizou para o mesmo endereço; com `slug = NULL`, salvar em branco gerou `barbearia-joao`.
- **Prova por HTTP local:** `cliente.php?e=barbearia-joao` e `?e=barbearia-joaoo` responderam **200** com o nome do estabelecimento; `?e=nao-existe` respondeu **404**. A tela de Configurações passou a exibir `http://127.0.0.1:8080/cliente.php?e=barbearia-joao` e o campo preenchido.
- **Limpeza:** as duas contas de teste e seus 14 horários foram apagados; o banco voltou a **24 estabelecimentos**, com apenas o Studio (`slug=studio`) preenchido.
- **Pendências conhecidas:** (1) trocar o endereço invalida o link antigo — não há tabela de apelidos, e não vale criar antes da feira; (2) os outros 23 estabelecimentos continuam sem slug e passam a ter um quando salvarem a tela com o campo em branco.
- **Estado real:** implementado e testado **somente no PC local**, **não publicado** e **sem alteração de banco**.

---

## WA-01 — Conexão do WhatsApp da plataforma e assinatura da mensagem (21/11/2026)

- **Pedido do responsável (21/11/2026):** confirmou **um número único global** ("vamos usar somente esse numero de forma global"), concordou em **tirar toda a configuração do WhatsApp da tela do estabelecimento** e em a mensagem **dizer em nome de quem fala**. Autorizou executar **somente no local**, sem publicar e sem SQL.
- **Decisão de arquitetura (debate fechado):** o "canal" é o número que envia. Hoje existe **um canal, o da AgenDay**, e ele atende todos os estabelecimentos. Se um dia um salão conectar o próprio número, a regra é *"manda pelo número do salão se ele tiver um; senão, pelo da plataforma"*. **Não** foi criada tabela de canais agora: com um número só ela não mudaria o comportamento e a fila (`mensagem_whatsapp`) já é por estabelecimento, então a migração depois é acréscimo, não reescrita.
- **Risco corrigido (item 2 da fila):** até aqui qualquer estabelecimento logado abria **Configurações → WhatsApp** e podia apagar o endereço ou a chave do WAHA, derrubando o envio de **todos**. A conexão saiu da tela do estabelecimento e foi para `whatsapp-plataforma.php` (endereço `https://agenday-sistema.infinityfree.io/whatsapp-plataforma.php`), onde **salvar exige digitar a chave de API atual** (`hash_equals`). A chave continua sem voltar ao navegador.
- **Assinatura da mensagem (`lib/whatsapp.php`):** nova constante `AGENDAY_WHATSAPP_REMETENTE = 'AgenDay'` e função `agenday_whatsapp_assinatura()`. Os cinco textos (`codigo`, `confirmacao`, `lembrete`, `remarcacao`, `cancelamento`) passam a terminar com **"Enviado pela AgenDay em nome de <estabelecimento>."** — o cliente recebe do número da plataforma, mas sabe de quem é o agendamento.
- **Tela do estabelecimento (`config.php`):** a aba **WhatsApp foi removida** (agora são **três** abas: Estabelecimento, Horários, Minha Conta). As ações `salvar_whatsapp`, `testar_whatsapp` e `enviar_pendentes` deixaram de existir ali; o `require` de `lib/whatsapp.php` também saiu. **O que é do estabelecimento ficou na aba Estabelecimento**: a chave **"Exigir código de 6 dígitos enviado pelo WhatsApp"** (coluna `exigir_codigo_whatsapp`) e o **link do portal** com o botão copiar.
- **Página da plataforma (`whatsapp-plataforma.php`, novo):** mostra situação, endereço, sessão e fila; permite ativar/desativar, trocar endereço/sessão, definir nova chave, **testar conexão** e **enviar pendentes agora**. Aviso explícito de que a conexão é de **todos** os estabelecimentos. Ela não aparece no menu: o operador chega pela URL.
- **Front-end:** `js/configuracoes.js` perdeu o mapeamento da aba `whatsapp`; `tests/responsividade-check.js` passou a exigir **três** abas e a falhar se `whatsapp_api_key`/`whatsapp_base_url` voltarem para `config.php`.
- **Testes executados no PC local (21/11/2026):** `php -l` limpo em `config.php`, `whatsapp-plataforma.php` e `lib/whatsapp.php`; `tests/portal-cliente.php` de 100 para **110 verificações** (assinatura nos cinco tipos de texto, assinatura sem estabelecimento); `responsividade-check` com **213 verificações**; `agendamento-validacao` (19) e `agendamento-cancelamento` (20) limpos.
- **Prova funcional no banco local (`agenday_local`, estabelecimento 29):** POST em `config.php` com `salvar_estabelecimento` gravou `exigir_codigo_whatsapp` de **1 → 0** e devolveu para **1**; POST em `whatsapp-plataforma.php` com **chave errada** mostrou "Confirme a chave de API atual…" e com **chave certa** mostrou "Conexão da plataforma salva."; as duas páginas renderizaram sem `Fatal error`/`Warning` (126 KB e 24 KB).
- **Pendências conhecidas:** (1) não existe papel de operador da plataforma no banco, então a proteção é a chave de API — criar permissão própria fica para depois da feira; (2) o **opt-out "responda SAIR"** não foi implementado (exigiria coluna/tabela nova, fora do escopo autorizado); (3) o **lembrete automático** continuava sem gatilho (item 3 da fila) e foi implementado depois, no LEMBRETE-01.
- **Estado real:** implementado e testado **somente no PC local**, **não publicado** e **sem alteração de banco**.

---

## DEPLOY-03 — Publicação do PORTAL-07 (21/11/2026)

- **Autorização do responsável (21/11/2026):** confirmou "Sim, publicar agora" para o lote do PORTAL-07 (cancelamento por link sem exigir `Origin`), **somente código**, sem SQL.
- **Backup prévio:** `Documents\AgenDay-Backups\2026-11-21_202710_e149e0c8` — `BACKUP OK`, 137 arquivos, 16 tabelas, restauração local **aprovada**; `sha256_sql` `a6d9d0a6…c7eea`, `sha256_zip` `e6fc1aca…46583`. Feito depois do DEPLOY-02, então representa o estado atual do servidor.
- **SHA e destino:** `b6887f60bc2eb6d9694dfa659d533490713138e0` (`dev-feira-uninorte`, merge do PR #78), conferido igual no `git rev-parse` local e na ref remota antes de ativar as flags. Destino: as duas pastas (`htdocs` e `agenday-agendamentos.infinityfree.io/htdocs`). Sem migração.
- **Publicação:** `workflow_dispatch` no run **`44967236148`**, environment `producao-agenday` aprovado. Os três jobs concluíram com **success** — `Validar codigo e pacote`, `Testar FTPS sem publicar arquivos` e `Enviar ao InfinityFree somente com autorizacao` — em cerca de 4,5 minutos.
- **Flags:** `AGENDAY_DEPLOY_ENABLED=true` e `AGENDAY_DEPLOY_MODE=publish` ativadas às 00:31 UTC e restauradas para **`false`/`disabled`** às 00:38 UTC.
- **Conferência pós-deploy (21/11/2026, navegador):** a página de cancelamento em produção responde agora `Referrer-Policy: same-origin` (antes `no-referrer`) e mantém o campo oculto `assinatura` com 64 caracteres — confirmando que a versão publicada é a do PORTAL-07.
- **Validação do responsável (21/11/2026):** informou que o cancelamento **funcionou** depois deste deploy. É o teste que faltava — clique no botão pelo celular.
- **Estado real:** publicado, conferido e **validado pelo responsável**.

---

## PORTAL-07 — "Pedido não reconhecido" continuava no celular (21/11/2026)

- **Relato do responsável (21/11/2026):** depois do deploy do PORTAL-06, ao clicar em **Confirmar cancelamento** pelo celular ainda aparecia **"Pedido não reconhecido, recarregue esta página e tente novamente"**.
- **O que foi conferido em produção:** o formulário publicado já trazia o campo oculto `assinatura` com 64 caracteres, e o valor conferia exatamente com `sha256('agenday-cancelar-v1|' . token)` calculado no PC — ou seja, o código publicado estava consistente e a assinatura não era o problema. O 403 só podia vir da exigência de `Origin`/`Referer`: a página respondia `Referrer-Policy: no-referrer` e o POST era recusado quando o navegador não mandava o cabeçalho (ou mandava `Origin: null`, comum em navegador embutido do WhatsApp).
- **Correção (`lib/portal-cliente.php`, `cliente-cancelar.php`):** nova função `agenday_portal_cancelamento_autorizado($token, $post)` — o POST é aceito se a **assinatura** confere (prova independente de cabeçalho) **ou** se o `Origin`/`Referer` aponta para o próprio site. Antes a assinatura era obrigatória e o `Origin` era conferido quando presente; agora a assinatura vale sozinha e o `Origin` serve de alternativa para páginas que ficaram em cache antes da correção.
- **`Referrer-Policy`:** passou de `no-referrer` para **`same-origin`**. O `Referer` volta nas requisições para o próprio site (segunda prova no POST) e continua **não** sendo enviado ao CDN do Bootstrap nem a terceiros, então o token do link não vaza.
- **Testes no PC local (21/11/2026):** `tests/portal-cliente.php` subiu de 93 para **100 verificações** (assinatura sem `Origin`, assinatura com `Origin: null`, página em cache sem assinatura usando `Origin`, assinatura de outro token recusada, assinatura correta prevalecendo sobre origem estranha); demais suítes limpas (19, 20, 13, 16, 210); `php -l` limpo.
- **Prova por HTTP local:** sem `Origin` + assinatura → `200 Agendamento cancelado`; `Origin` válido sem assinatura → `200`; `Origin: null` + assinatura → `200`; sem nada → `403`; `Origin` falso + assinatura correta → `200`; assinatura errada → `403`.
- **Custo do teste:** os quatro cancelamentos de teste dispararam o aviso real de WhatsApp para o responsável (4 mensagens seguidas às 20:24). Agendamentos e mensagens de teste apagados do `agenday_local` em seguida.
- **Validação do responsável (21/11/2026):** depois do DEPLOY-03, informou que o cancelamento pelo link **funcionou**. A pendência do celular físico está fechada; o registro é o relato do responsável, somado à conferência técnica em produção (`Referrer-Policy: same-origin` e `assinatura` de 64 caracteres).
- **Estado real:** corrigido, testado no PC local, **publicado** no SHA `b6887f6` (DEPLOY-03) e **validado pelo responsável**.

---

## DEPLOY-02 — Publicação do PORTAL-06 (21/11/2026)

- **Autorização do responsável (21/11/2026):** "pode publicar" para o lote do PORTAL-06 (link de cancelamento no celular e aviso de remarcação), **somente código**, sem SQL.
- **Backup prévio:** `Documents\AgenDay-Backups\2026-11-21_200133_5aab7a74` — `BACKUP OK`, 137 arquivos, 16 tabelas, restauração local **aprovada**; `sha256_sql` `2c16d520…c4c5`, `sha256_zip` `afc6986e…1fb39`. Feito depois do DEPLOY-01, então representa o estado atual do servidor (o backup das 17:46 era anterior ao portal).
- **SHA e destino:** `4dbf2277ea397d9776fc3c3ef02a564bcdd5c2ae` (`dev-feira-uninorte`, merge do PR #76), conferido igual no `git rev-parse` local e na ref remota antes de ativar as flags. Destino: as duas pastas (`htdocs` e `agenday-agendamentos.infinityfree.io/htdocs`). Sem migração: nenhuma alteração de banco.
- **Publicação:** `workflow_dispatch` no run **`44965206160`**, environment `producao-agenday` aprovado pelo `gh` autenticado como `Emds96`. Os três jobs concluíram com **success** — `Validar codigo e pacote`, `Testar FTPS sem publicar arquivos` e `Enviar ao InfinityFree somente com autorizacao` — em cerca de 7 minutos.
- **Flags:** `AGENDAY_DEPLOY_ENABLED=true` e `AGENDAY_DEPLOY_MODE=publish` ativadas às 00:08/00:09 UTC e restauradas para **`false`/`disabled`** às 00:16 UTC, depois do término do job.
- **Conferência pós-deploy (21/11/2026, navegador):** `https://agenday-agendamentos.infinityfree.io/?e=studio` carrega "Studio Bella Vita" com as abas Agendar/Meus agendamentos; `https://agenday-sistema.infinityfree.io/` abre `agenda.php` sem erro de PHP; o link de cancelamento com token real mostra "Cancelar agendamento" e agora traz o campo oculto **`assinatura` com 64 caracteres**, comprovando que a versão publicada é a do PORTAL-06.
- **Pendência:** clicar no botão **Confirmar cancelamento** pelo celular físico (o teste do POST no aparelho é o que fecha a correção do `Origin`) e confirmar o recebimento do aviso de remarcação.
- **Estado real:** código publicado e página nova no ar; **fluxo do botão ainda não validado no celular**.

---

## PORTAL-06 — Link de cancelamento no celular e aviso de remarcação (21/11/2026)

- **Pedido do responsável (21/11/2026):** o cancelamento pelo painel e a remarcação funcionam, mas o link de cancelamento enviado no WhatsApp "não funciona"; também pediu mensagem no WhatsApp confirmando a remarcação. Tudo **somente no local**, sem publicar.
- **Diagnóstico com evidência:** o link e o token estavam corretos — o endereço enviado (`cliente-cancelar.php?t=<64 hex>`) abriu a página de cancelamento em produção com um token real, e a mensagem de remarcação **já existia e já era enviada** (registros do WAHA em 21/11 às 20:04 e 23:44 UTC). Dois defeitos reais foram encontrados:
  1. **O botão "Confirmar cancelamento" falhava em parte dos navegadores móveis.** A página não tem sessão e dependia só de `Origin`/`Referer`, com `Referrer-Policy: no-referrer`; reproduzido no local: POST com `Origin` → `200 Agendamento cancelado`, POST sem `Origin` → `403 Pedido não reconhecido`.
  2. **Mensagens geradas no PC apontavam para produção com um token que só existia no banco local** (o estabelecimento local 29 tem `dominio=agenday-agendamentos.infinityfree.io`). Exemplo real: a mensagem das 21:22 UTC levava o token `2db1930e…`, presente apenas em `agenday_local`; clicar nela abre produção e mostra "agendamento não encontrado".
- **Correção do link (`cliente-cancelar.php`, `lib/portal-cliente.php`):** o formulário ganhou o campo oculto `assinatura` (`sha256` do token, `agenday_portal_assinatura_cancelamento()`), conferido com `hash_equals`. O `Origin` passou a ser exigido **apenas quando o navegador o envia**; sem cabeçalho, a assinatura prova que a página foi carregada. POST cego de outro site continua bloqueado (sem o token não há assinatura válida). O link "Voltar sem cancelar" preserva `?e=slug`.
- **Links no local (`lib/portal-cliente.php`):** `agenday_portal_host_local()` reconhece `localhost`, `127.0.0.1`, IPv6 local, faixas privadas e `*.local`; nesses casos `agenday_portal_base_url()` e `agenday_portal_link_cancelamento()` usam o host atual (`http://127.0.0.1:8080/...`). Fora do local, nada muda: continua o `dominio` do estabelecimento.
- **Envio imediato (`lib/whatsapp.php`):** a entrega de uma mensagem foi extraída para `agenday_whatsapp_entregar()` e existe `agenday_whatsapp_enviar_agora()`, que envia a mensagem recém-enfileirada **sem esperar a trava de 1 minuto** da fila. Criação, remarcação e cancelamento usam essa via — sem cron na hospedagem, a confirmação não fica parada até a próxima página abrir. A reserva por mensagem (`pendente/erro` → `enviando`) continua evitando envio duplicado.
- **Testes executados no local (21/11/2026):** `tests/portal-cliente.php` subiu de 84 para **93 verificações** (host local, links locais, assinatura estável e distinta por token); `agendamento-validacao` (19), `agendamento-cancelamento` (20), `configuracao-estabelecimento` (13), `alteracao-senha-validacao` (16) e `responsividade-check` (210) limpos; `php -l` limpo nos quatro arquivos alterados.
- **Prova por HTTP local:** POST **sem** `Origin` e com assinatura correta → `200 Agendamento cancelado`; assinatura errada → `403`; `Origin` falso com assinatura correta → `403`. A mensagem de cancelamento gerada no teste saiu com `http://127.0.0.1:8080/cliente.php?e=studio` e status `enviado`. Agendamentos e mensagens de teste apagados do `agenday_local` no fim.
- **Ainda pendente:** clicar no botão **Confirmar cancelamento** pelo celular físico em produção (o POST no aparelho é o que fecha a correção do `Origin`); a mensagem de remarcação já foi vista no aparelho do responsável.
- **Estado real:** corrigido e testado no PC local e **publicado** em 21/11/2026 no deploy do SHA `4dbf227` (DEPLOY-02).

---

## DEPLOY-01 — Migração e publicação do portal do cliente (21/11/2026)

- **Autorização do responsável (21/11/2026):** fechar o lote, publicar, aplicar as migrações SQL pendentes em produção e deixar o sistema online.
- **Backup prévio:** `Documents\AgenDay-Backups\2026-11-21_174618_aa74da7d` — `BACKUP OK`, 126 arquivos, 12 tabelas, restauração em MySQL local aprovada; `sha256_sql` `35cea56e…d4e3fd`, `sha256_zip` `52b33240…3cc402`. O backup anterior (20/11 22:28) estava fora da janela de 6 horas e não foi usado.
- **Migração em produção (phpMyAdmin, banco `if0_43415733_agenday`, MariaDB 11.4.13):** aplicada antes do código, em blocos equivalentes a `sql/migracao-portal-cliente.sql` — `estabelecimento.slug`, `dominio`, `exigir_codigo_whatsapp`; `clientes.telefone_digits` com backfill (30 dos 32 clientes normalizados, 2 sem telefone no cadastro) e `idx_clientes_telefone`; `agendamentos.token_publico` com `uq_agendamentos_token`; tabelas `verificacao_acesso`, `sessao_cliente`, `mensagem_whatsapp`, `whatsapp_config` (linha 1 criada); índices únicos `uq_estabelecimento_slug` e `uq_estabelecimento_dominio`. O estabelecimento demo "Studio Bella Vita" (id 22) ficou com `slug=studio`, `dominio=agenday-agendamentos.infinityfree.io` e `exigir_codigo_whatsapp=1`. Conferido por consultas de leitura depois da execução.
- **Publicação:** `workflow_dispatch` no run **`44953952790`**, SHA **`4bd707aa33d4be09c5367b2745fffcb80872f96a`** (`dev-feira-uninorte`), environment `producao-agenday` aprovado com o comentário de autorização. `publicar` concluído com sucesso em **4m18s** nas duas pastas (`htdocs` e `agenday-agendamentos.infinityfree.io/htdocs`); `diagnosticar_ftp` (somente leitura) também verde. Flags `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled` restauradas às 21:59 UTC.
- **Conferência pós-deploy no navegador (21/11/2026):** `https://agenday-sistema.infinityfree.io/` carrega o login sem erro de PHP; `https://agenday-agendamentos.infinityfree.io/?e=studio` mostra "Studio Bella Vita" com as abas Entrar/Cadastro, dropdowns e calendário no DOM; `agenda.php` no domínio do cliente redireciona para `cliente.php` (guarda de host ativa).
- **Validação do responsável em produção (21/11/2026):** preencheu **Configurações → WhatsApp** pela tela (confirmado no banco: `ativo=1`, `base_url` com 52 caracteres igual ao domínio fixo do ngrok, chave de 32 caracteres, sessão `default`), testou a conexão, criou conta pelo portal com o código recebido no WhatsApp, escolheu serviço, profissional, dia e horário, e confirmou o agendamento. Relato textual: "eu consegui criar a conta, consegui receber mensagem no WhatsApp, eu consegui agendar e confirmar no agenday sistema e foi cadastrado o agendamento apareceu certinho os horários disponíveis".
- **Conferência no banco depois do teste:** cliente novo no Studio (17 → 18); agendamento **189** em 23/11/2026 das 15:00 às 17:00 (serviço 17, profissional 23, status `agendado`) com `token_publico` preenchido; mensagem `confirmacao` com status `enviado`, criada 14:48:29 e enviada 14:48:32 (3 segundos).
- **Pendência restante:** cancelamento pelo link ainda não foi testado em produção (o teste cobriu cadastro, código, agendamento e confirmação). O restante da Fase 8 segue em `docs/PORTAL_CLIENTE_CHECKLIST.md`.
- **Estado real:** código e banco de produção atualizados, CI e deploy verdes e **fluxo principal validado em produção pelo responsável**.

---

## OPS-01 — Script e atalho de início do WhatsApp (21/11/2026)

- **Pedido do responsável:** um script para o caso de reiniciar o computador, com atalho na Área de Trabalho, e autorização para apagar o cliente de teste "Eder QA" do banco local.
- **`scripts/iniciar-feira.ps1`:** limpa `http_proxy`/`https_proxy` (erro que já atrapalhou o ngrok), sobe o Docker Desktop se o motor não responder, inicia o contêiner `agenday-waha` quando parado, espera a API em `127.0.0.1:3000`, lê a `WAHA_API_KEY` do próprio contêiner (nunca imprime), confere a sessão `default` e tenta iniciá-la se estiver `STOPPED`, abre o ngrok no domínio fixo `additionally-nondeluded-elane.ngrok-free.dev` e confirma o túnel pelo painel local (`4040`). Termina com resumo e código de saída 1 se algo faltar. `-ComPhp` sobe também o PHP local em `127.0.0.1:8080`; `-SemPausa` dispensa o Enter final.
- **Atalho:** `AgenDay - Iniciar WhatsApp.lnk` na Área de Trabalho, apontando para `powershell.exe -NoProfile -ExecutionPolicy Bypass -File ...\scripts\iniciar-feira.ps1`, com diretório de trabalho na raiz do projeto.
- **Testes executados no local (21/11/2026):** com tudo já no ar, o script devolveu `Docker: ok`, `Sessao: WORKING` e o túnel ativo (saída 0). Com o processo do ngrok encerrado de propósito, o script reabriu o túnel no domínio fixo e o endpoint público `https://additionally-nondeluded-elane.ngrok-free.dev/api/sessions/default` respondeu `WORKING`.
- **O que volta sozinho após reiniciar:** Docker Desktop (`AutoStart=True`) e o contêiner (`restart unless-stopped`), com o pareamento preservado em `Documents\AgenDay-Tools\waha\sessions`. O ngrok é o único que não volta sozinho — é o que o script resolve.
- **Limpeza local autorizada:** apagados o agendamento de teste 553 (cancelado), 3 mensagens da fila ligadas a ele, 2 sessões e o cliente "Eder QA" (id 73, `92994331267`) do banco `agenday_local`, em transação. Conferido depois: zero linhas remanescentes e a conta `EMDS` (id 77, `92981351207`) intacta. **Não afeta o envio de mensagens:** o remetente é a conta pareada na sessão do WAHA (`559294331267`), não uma linha do banco; `whatsapp_config` guarda apenas `ativo`, `base_url`, `api_key` e `sessao`.
- **Documentação:** `docs/WHATSAPP_WAHA_GUIA.md` — rotina do dia da feira reescrita em torno do atalho e do script, com a explicação do que volta sozinho após reiniciar.
- **Estado real:** ferramenta local, testada e integrada; não altera código publicado, banco de produção nem deploy.

---

## PORTAL-05 — Serviço e profissional em menu, dia no calendário (21/11/2026)

- **Pedido do responsável:** na tela do cliente, serviço e profissional devem ser escolhidos em **menu dropdown**, mantendo a identidade visual dos controles atuais, e o dia deve ser escolhido em **calendário**, com os horários aparecendo abaixo.
- **Tela (`cliente.php`):** os blocos 1 e 2 trocaram as listas de botões por um gatilho (`.cliente-dropdown-gatilho`) com valor escolhido e seta, e um painel de opções (`role="group"`, `aria-labelledby` do título da etapa, `hidden` quando fechado). A escolha marcada usa `aria-current` e ícone de check; não foi usado `role="listbox"`, para não prometer um padrão de teclado que não existe. O bloco 3 trocou o `<input type="date">` por um calendário com cabeçalho de mês, navegação anterior/próximo e grade de dias.
- **Comportamento (`js/cliente.js`):** `abrirDropdown`/`fecharDropdown` mantêm `aria-expanded`, fecham ao clicar fora, ao escolher e no `Tab`; o teclado funciona com setas, `Home`, `End` e `Esc` (devolvendo o foco ao gatilho). Depois de escolher serviço ou profissional, o foco vai para a próxima etapa. `renderCalendario` limita a navegação ao mês atual e ao mês de `dataMax` (janela de `regras.janela_dias`), desabilita dias fora da janela e marca hoje e o dia escolhido. `moverFocoCalendario` anda com as setas e troca de mês quando o foco sai da grade. `escolherData` carrega os horários e leva o foco ao dia selecionado.
- **Estilo (`css/cliente.css`):** o `<body>` do portal também carrega `.agenday-auth`, cuja regra genérica pinta todo botão de turquesa. Os controles novos receberam overrides com o prefixo `.agenday-auth.agenday-portal`, o que também devolveu aos horários o aspecto de cartão claro (antes apareciam preenchidos). Abaixo de 576 px o painel do dropdown passa a abrir dentro do cartão (`position: static`), sem flutuar sobre o conteúdo. Os cartões ganharam `scroll-margin-top` para não ficarem sob o cabeçalho fixo.
- **Validação visual e funcional no local (21/11/2026):** fluxo completo em **1366 px** e em **360 px** — abrir serviço, escolher "Corte Feminino", escolher "Qualquer profissional", escolher 24/11 no calendário, escolher 14:00, confirmar; o agendamento foi gravado (profissional atribuída: Ana Beatriz) e depois cancelado pela aba "Meus agendamentos". Sem rolagem horizontal em 360 px (`scrollWidth` = 360).
- **Testes:** `php -l cliente.php`, `node --check js/cliente.js`, `tests/portal-cliente.php` (84), `agendamento-validacao` (19), `agendamento-cancelamento` (20) e `node tests/responsividade-check.js` (210), todos limpos.
- **Estado real:** implementado e testado no PC local; **publicado** em 21/11/2026 no deploy do SHA `4bd707a` (DEPLOY-01).

---

## PORTAL-04 — Acesso em duas abas e celular com o 9 (21/11/2026)

- **Pedido do responsável:** a tela do cliente deve lembrar o login do sistema, com uma aba para **entrar** (só o celular) e outra para **cadastro** (celular + nome). O celular deve seguir o padrão atual do Brasil, com o 9 na frente (ex.: `92 99433-1267`).
- **Tela:** `cliente.php` ganhou as abas "Entrar" e "Cadastro", no mesmo componente visual das abas internas. Quem digita na aba Entrar um celular sem cadastro é levado automaticamente para Cadastro, com o número preenchido e o aviso correspondente. `js/cliente.js` centraliza o comportamento em `mostrarAcesso`, `entrarDireto`, `pedirCodigo` e `ligarFormularioAcesso`.
- **Celular com o 9:** `agenday_portal_telefone_normalizar()` passou a converter o formato antigo (DDD + 8 dígitos começando em 6, 7, 8 ou 9) para DDD + 9 + 8 dígitos; fixos continuam com 10. A migração `sql/migracao-portal-cliente.sql` faz o mesmo backfill. `agenday_portal_telefone_variantes()` procura as duas formas, então cadastros antigos continuam sendo encontrados e não duplicam.
- **Envio no WhatsApp:** o motor GOWS recusava (`no LID found`) números cujo WhatsApp usa o formato antigo. `agenday_whatsapp_resolver_chat()` consulta `check-exists` e envia para o JID real (`pn`); em falha da consulta, mantém o formato padrão. O erro de "número sem WhatsApp" agora tem mensagem própria.
- **Testes:** `tests/portal-cliente.php` subiu para **84 verificações** (normalização, variantes e casos antigos). `php -l`, `node --check`, `agendamento-validacao` (19), `agendamento-cancelamento` (20) e `responsividade-check` (210) limpos.
- **Validação real (21/11/2026, local + WAHA/GOWS + ngrok):** envio com `92994331267` e com `9294331267` retornou **HTTP 201**; login na aba Entrar com o número novo entrou direto no cadastro existente ("Bem-vindo de volta!"); cadastro na aba Cadastro com o formato antigo enviou o código, mostrou `(92) 9****-1267` e concluiu com o código recebido. Dados de teste apagados e estabelecimento temporário revertido.
- **Validação do responsável (21/11/2026, navegador local):** entrou com `92994331267` (formato novo) e criou a conta `92 98135-1207` na aba Cadastro, concluindo com o código recebido no WhatsApp. O cadastro ficou gravado como `(92) 98135-1207` / `92981351207` (cliente local `EMDS`, id 77), mantido por ser conta do responsável.
- **Integração:** PR #68 (`feat/acesso-abas-celular-9` → `dev-feira-uninorte`) integrado em `fdde916`; CI verde nos dois workflows (runs `44948346357` e `44948346362`), com `publicar` e o teste FTPS `skipped`.
- **Estado real:** implementado, testado no PC local e integrado na `dev-feira-uninorte`; **publicado** em 21/11/2026 no deploy do SHA `4bd707a` (DEPLOY-01).

---

## PORTAL-03 — Código no WhatsApp só no cadastro (21/11/2026)

- **Decisão do responsável (21/11/2026):** o código de 6 dígitos confirma o número **no primeiro acesso**; cliente já cadastrado entra digitando apenas o celular. Antes, o código era pedido sempre que não havia sessão válida.
- **Código:** nova função `agenday_portal_acesso_decidir()` em `lib/portal-cliente.php` centraliza a regra (cliente existente → direto; primeiro acesso → pede nome e envia código; `exigir_codigo_whatsapp` desligado → direto até no primeiro acesso). `api/cliente-acesso.php` passou a usá-la e devolve `cliente_existente` na resposta. `js/cliente.js` mostra "Bem-vindo de volta!" para quem já tem cadastro; texto de `cliente.php` e versão do JS (`cl02`) atualizados.
- **Testes:** `tests/portal-cliente.php` subiu de 69 para **74 verificações**, cobrindo as quatro combinações da regra. `php -l` limpo nos quatro arquivos alterados.
- **Validação real no local (PHP 8.4 + MariaDB 11.8.9 + WAHA/GOWS):** cliente já cadastrado (`9294331267`, Studio) entrou **só com o celular**, com "Bem-vindo de volta!" e **sem** nova mensagem (contador de `mensagem_whatsapp` ficou em 3). Em um estabelecimento temporário, o mesmo número foi tratado como primeiro acesso: pediu o nome, enviou o código no WhatsApp, aceitou o código e, ao sair e entrar de novo, entrou direto. Estabelecimento temporário revertido e dados de teste apagados.
- **Risco assumido e documentado:** quem souber o celular de um cliente pode abrir a sessão dele e ver/cancelar agendamentos. Registrado em `docs/PORTAL_CLIENTE_PLANO.md` §6.3, com o ponto único de mudança caso seja preciso exigir código em todo login.
- **Integração:** PR #66 (`feat/login-codigo-so-cadastro` → `dev-feira-uninorte`) integrado em `01a7fa3`; CI verde nos dois workflows (runs `44946287856` e `44946287878`), com `publicar` e o teste FTPS `skipped`.
- **Estado real:** implementado, testado no PC local e integrado na `dev-feira-uninorte`; **publicado** em 21/11/2026 no deploy do SHA `4bd707a` (DEPLOY-01).

---

## PORTAL-02 — Guia do WhatsApp e PR #64 integrado (21/11/2026)

- **PR #64** (`feat/portal-cliente` → `dev-feira-uninorte`) integrado em `1e670cd`. CI verde nos dois workflows: `PHP syntax check` e `Validacao e publicacao controlada` (run `44936376746`), que agora executa também `tests/portal-cliente.php` (69 verificações). Nada foi publicado: `AGENDAY_DEPLOY_ENABLED`/`AGENDAY_DEPLOY_MODE` continuam `false`/`disabled`, e o job `publicar` ficou `skipped`.
- **Novo documento:** `docs/WHATSAPP_WAHA_GUIA.md` — instalação do Docker Desktop (winget + reinício), geração das credenciais do WAHA, pareamento por QR, túnel ngrok, preenchimento da aba WhatsApp, teste real, rotina do dia da feira, falhas comuns e plano B sem WhatsApp.
- **Código:** `lib/whatsapp.php` passou a enviar o cabeçalho `ngrok-skip-browser-warning` no envio e no teste de conexão, para o túnel gratuito não devolver a página de aviso em vez do JSON.
- **Correção de isolamento no mesmo lote:** `agenday_portal_sessao_ler()` passou a receber o estabelecimento e a filtrar por ele; testado com dois estabelecimentos no banco local (sessão de `studio` não vale em `teste-b`, e a escrita nesse domínio devolve 401).
- **Estado real:** implementado e testado no PC local; **publicado** em 21/11/2026 no deploy do SHA `4bd707a` (DEPLOY-01). O envio real passou a ser validado depois da instalação do WAHA/GOWS no PC (ver PORTAL-04).

---

## PORTAL-01 — Portal público do cliente (21/11/2026; implementado no local, **publicado** em 21/11/2026 no SHA `4bd707a`)

- **Escopo autorizado:** plano aprovado em 21/11 (`docs/PORTAL_CLIENTE_PLANO.md`) — segundo domínio para o cliente, login por celular com código no WhatsApp, agendar, remarcar e cancelar. Branch `feat/portal-cliente` a partir de `849af46`. (A regra do código foi ajustada em **PORTAL-03**: só no cadastro.)
- **Banco (aditivo, reexecutável):** `sql/migracao-portal-cliente.sql` acrescenta `estabelecimento.slug`, `dominio` e `exigir_codigo_whatsapp`; `clientes.telefone_digits` (com backfill pela mesma regra do PHP); `agendamentos.token_publico`; e cria `verificacao_acesso`, `sessao_cliente`, `mensagem_whatsapp` e `whatsapp_config`. Aplicada **somente no banco local**, duas execuções, sem erro; 29/29 clientes normalizados. **Aplicada em produção em 21/11/2026, antes do código (DEPLOY-01).**
- **Bibliotecas:** `lib/portal-cliente.php` (slug, telefone, cliente, token, sessão por cookie `AGENDAY_CLIENTE`, CSRF, origem, prazo de cancelamento, JSON); `lib/guarda-host.php`; `lib/whatsapp.php` (configuração, fila com backoff, envio WAHA, cinco templates, gatilho pós-resposta).
- **Endpoints:** `api/cliente-acesso.php` (`estado`, `entrar`, `verificar`, `sair`), `api/cliente-dados.php`, `api/cliente-horarios.php` (reusa `lib/disponibilidade.php`), `api/cliente-agendamentos.php` (`criar`, `listar`, `cancelar`, `remarcar`).
- **Telas:** `cliente.php` (mobile-first, etapas de acesso e agendamento, abas Agendar/Meus agendamentos, `.ics`) e `cliente-cancelar.php?t=token` (cancelamento sem login, POST com conferência de origem). `css/cliente.css` usa apenas tokens do design system.
- **Reaproveitamento:** `lib/agendamento-gravar.php` e `lib/agendamento-cancelar.php` passaram a aceitar o estabelecimento por parâmetro (`agenday_agenda_inserir`, `agenday_agenda_cancelar_para`), mantendo as funções antigas com a sessão. `agenday_agenda_remarcar` usa a mesma ordem de locks da criação.
- **Configurações:** nova aba **WhatsApp** em `config.php` (ativar, endereço do WAHA, sessão, chave, exigir código, link do portal com botão copiar, testar conexão e enviar pendentes). Salvamento por `acao=salvar_whatsapp` dentro da própria página, com CSRF — não foi criado `salvar-whatsapp.php`.
- **Segurança:** código de 6 dígitos com salt e hash, 10 minutos, uso único, 5 tentativas, 3/10 min e 10/dia por telefone, 20/h por IP; `id_estabelecimento` e `id_cliente` sempre resolvidos no servidor; nenhuma escrita aceita sem CSRF (sessão) ou origem (pré-login); `conexao.php` continua fora do Git.
- **Guarda de host:** `sessao.php` aplica a guarda antes de `session_start()`. Testado por `curl` com `Host` falso: `agenda.php`, `index.php`, `login.php`, `api/horarios.php` e `config.php` devolvem 302 para `/cliente.php`; `cliente.php`, `cliente-cancelar.php` e `api/cliente-*.php` respondem normalmente.
- **Validação local (21/11/2026, PHP 8.4.28 + MariaDB 11.8.9):** login com código enviado a um **WAHA simulado local**; código errado contabilizou tentativa; código certo abriu sessão; reuso recusado; criar, listar, remarcar, cancelar e cancelar por link funcionaram; conflito de horário recusado (409); POST sem origem recusado (403); página de cancelamento inativa mostra "Nada a cancelar"; fila ficou em `erro` com o serviço fora e foi reenviada sozinha ao abrir o portal, sem atrasar a página (8 ms). `php -l` limpo, `tests/portal-cliente.php` (69), `agendamento-validacao` (19), `agendamento-cancelamento` (20) e `responsividade-check` (210) aprovados. Dados de teste removidos; conta demo voltou a 173 agendamentos e 15 clientes.
- **Publicação:** `scripts/publicar_ftp.py` passou a enviar o mesmo pacote para `htdocs` e para `agenday-agendamentos.infinityfree.io/htdocs` (cliente primeiro; a configuração privada do Brevo só na pasta principal). O teste de FTPS somente leitura do workflow confere as duas pastas.
- **Pendências:** instalar Docker/WAHA e parear o número (`docs/WHATSAPP_WAHA_GUIA.md`); publicar o túnel ngrok; validação visual do responsável; aplicar a migração em produção; deploy único com as duas pastas; teste real de mensagem.
- **Riscos registrados:** sem cron, a fila depende de visita ao portal/agenda ou do botão manual; com o WAHA fora, a primeira tentativa espera até 8 s por mensagem, mas fora do tempo de resposta da página; o link `cliente.php` no domínio principal é o plano B caso o domínio novo apresente problema.

---

## DOC-01 — Passagem de contexto para qualquer IA e ambiente local documentado (21/11/2026)

- **Escopo:** somente documentação. Sem alteração de código, banco, hospedagem, workflow, Secrets ou deploy.
- **Motivo:** permitir que outra IA (rodando no PC ou na nuvem) ou outra pessoa continue o projeto sem depender do histórico de chat desta sessão.
- **Novos documentos:** `docs/PASSAGEM_DE_CONTEXTO.md` (estado em 21/11/2026, regras, **equivalência das ferramentas** citadas nos documentos antigos, checklist de acessos, primeiros 30 minutos e mensagem pronta de retomada) e `docs/AMBIENTE_LOCAL.md` (PHP 8.4.28, MariaDB 11.8.9, portas 3306/8080, banco `agenday_local`, `iniciar.ps1`/`parar.ps1`, testes e problemas comuns).
- **Atualizados:** `docs/GUIA_DE_CONTINUIDADE.md` (ferramentas como exemplos + ponteiro para a equivalência; nova mensagem de retomada), `docs/PUBLICACAO_AUTOMATICA.md` (nova seção **Mudança de banco em produção**, com phpMyAdmin e semeador temporário), `docs/INDICE_DA_DOCUMENTACAO.md`, `README.md` e este arquivo.
- **Handoff antigo:** `C:\Users\EDER\AgenDay-Local\HANDOFF-CHATGPT.md` marcado como vencido — apontava o SHA `18f83a9`, anterior ao deploy de 21/11 e à conta demo.
- **Fatos conferidos no PC em 21/11/2026:** PHP 8.4.28, MariaDB 11.8.9, Node v24.22.0, Python 3.15.0, Git 2.55.0 e `gh` 2.104.0 autenticado como `Emds96`; banco local com 12 tabelas; `conexao.php` fora do Git; portas 3306 e 8080 ativas.
- **Sem senhas:** os documentos indicam **onde** cada credencial está (`.env.backup`, `.mysql-local.cnf`, `credenciais-locais.txt`) e não versionam valores; a senha da conta de demonstração continua com o responsável.
- **Validação:** links conferidos, arquivos gravados em UTF-8 sem BOM e CI do PR. Nenhum teste funcional foi necessário, pois não houve mudança de aplicação.
- **Pendências:** nenhuma operacional nova; seguem PEND-01 (modo híbrido), PEND-02 (lembretes), reagendamento com vínculo e QA em celular.

---

## DEPLOY-20261121 — Publicação do SHA 47fb029 e conta de demonstração (21/11/2026)

- **Autorização:** responsável autorizou publicar tudo o que estava no local e criar a conta de demonstração no site oficial.
- **Backup antes da publicação:** `C:\Users\EDER\Documents\AgenDay-Backups\2026-11-20_222428_b9ce5ee5` (`BACKUP OK`).
- **SQL no phpMyAdmin (antes do código):** `sql/migracao-status-comparecimento.sql` — enum com `em_atendimento` e `faltou`, tolerância padrão 30 e atualização de 10 → 30. Conferido por `SHOW COLUMNS`.
- **Código:** run `44856321866` no SHA `47fb029`; três jobs com sucesso. Flags ligadas apenas durante a execução e desligadas ao final (`false` / `disabled`).
- **Validação por FTPS:** `agendar.php`, `criar-agendamento.php`, `lib/comparecimento.php`, `api/comparecimento.php`, `api/horarios.php`, `lib/disponibilidade.php`, `js/calendario.js`, `js/agendar.js` e `css/agendar.css` presentes; `arquivos/` e `conexao.php` preservados.
- **Conta de demonstração:** `studio@demonstracao.com` (estabelecimento #22), senha definida pelo responsável no chat. Dados fictícios: **Studio Bella Vita**, 5 serviços, 4 funcionárias, 15 clientes, horário seg–sáb com almoço, 2 bloqueios e **173 agendamentos** (16/10 a 11/12): 100 concluídos, 12 faltas, 12 cancelados, 29 agendados e 20 confirmados.
- **Como a demo foi criada:** semeador PHP temporário com token aleatório, enviado por FTPS, executado uma vez e **removido em seguida** (conferido: nenhum `_agenday_seed_` restante). Nenhum script executável ficou na hospedagem.
- **Validação no site publicado:** login da demo funcionando; agenda abrindo em 20/11 com 4 atendimentos e 90 registros em novembro; `agendar.php` listando os serviços da demo e 12 horários livres em 24/11.
- **Observação:** a senha da conta demo foi informada ao responsável no chat; a conta é de demonstração, sem dados reais.

---

## ATT-01 — Comparecimento, faltou e fechamento do dia (21/11/2026; implementado no local e publicado em 21/11 — SHA `47fb029`)

- **Escopo autorizado:** lote 1 do PEND-02 — registrar chegada, falta e encerrar o atendimento sem depender de cron.
- **Banco:** `sql/migracao-status-comparecimento.sql` acrescenta `em_atendimento` e `faltou` ao enum de status e eleva a tolerância padrão para **30 minutos** (contas ainda em 10 são atualizadas; quem configurou outro valor é preservado). `sql/BD_v2.sql` atualizado. Migração aplicada no banco local.
- **Código:** `lib/comparecimento.php` (fechamento automático, marcação e sugestão de falta), `api/comparecimento.php` (POST com CSRF), `api/agendamentos.php` (fecha ao ler e devolve `sugere_faltou`), `agenda.php` (token CSRF no calendário), `js/calendario.js` (novos status, destaque de pendente e menu **Ações**), `css/agenda-calendario.css` (cores e selo "Confirmar presença").
- **Fluxo:** `agendado/confirmado` → **Chegou** = `em_atendimento` → ao passar do fim vira `concluido` automaticamente na próxima leitura; **Faltou** = `faltou`; **Concluir** encerra manualmente; **Cancelar** mantém o fluxo existente. Passou o início + tolerância sem marcação, o cartão fica destacado com "Confirmar presença" (não fecha sozinho).
- **Segurança:** CSRF no endpoint; `id_estabelecimento` da sessão; transições inválidas recusadas; agendamento de outra conta devolve "não encontrado".
- **Validação local (21/11/2026, PHP 8.4 + MariaDB 11.8):** enum e default 30 conferidos no MySQL; atendimento passado ficou com `sugere_faltou=true`; `em_atendimento` vencido virou `concluido` na leitura; Chegou/Concluir/Faltou conferidos por API e pelo menu da agenda; transição inválida, POST sem CSRF e registro de outra conta recusados; `php -l`, `node --check` e `responsividade-check` (210) aprovados; dados de teste removidos.
- **Pendências:** migração aplicada em produção em 21/11/2026 (ver DEPLOY-20261121); seguir com reagendamento com vínculo e lembretes (lotes seguintes) e QA em celular.

---

## PEND-02 — Comparecimento, status e fechamento do dia (decisões de 21/11/2026; lote 1 implementado em ATT-01)

- **Problema:** hoje só `cancelado` é escrito. `confirmado` e `concluido` existem no enum e nunca são usados, e não há `faltou`. Atendimento passado fica `agendado` para sempre e continua contando como ativo.
- **Decisões do responsável:**
  - Cada dia mostra **somente os atendimentos daquele dia** (comportamento atual do painel) e o passado fica como **histórico**, com status encerrado.
  - **Chegou** coloca em **`em_atendimento`**; ao passar o horário de fim, o atendimento passa a **`concluido`** automaticamente.
  - **Faltou** vira **status novo** (`faltou`).
  - **Reagendar** é uma ação separada (avaliar vínculo entre o antigo e o novo).
  - Fechamento: passou o fim + tolerância sem marcação, o sistema **sugere "faltou"** e a recepção confirma (não fecha sozinho).
  - **Tolerância de chegada** deve subir de 10 para **25–30 minutos** (configurável em Configurações).
  - **Lembrete ao cliente:** preferência por **SMS**; se não houver opção gratuita viável, usar **e-mail** (Brevo). WhatsApp depois.
- **Restrição técnica registrada:** SMS no Brasil exige provedor (Twilio, Zenvia e similares) e é **pago por mensagem**; não há caminho gratuito confiável integrado ao InfinityFree. E-mail pelo Brevo é gratuito no volume atual e já está integrado. Decidir com custo em mãos antes de implementar.
- **Fechamento sem cron:** o InfinityFree não oferece agendador confiável no plano gratuito; a atualização de status deve ser calculada ao abrir a agenda e persistida na primeira leitura (sem depender de tarefa externa).
- **Pendências:** migração do enum de status, ações no painel do dia (dropdown), regra de sugestão de falta, ajuste da tolerância, lembrete por e-mail e avaliação de SMS.

---

## AGD-01 — Tela de agendamento com horários livres (21/11/2026; publicada em 21/11 — SHA `47fb029`)

- **Escopo autorizado:** implementar no local, sem publicar, a tela de agendamento desenhada e validada no protótipo — **um atendimento avulso**, com disponibilidade real vinda do servidor.
- **Arquivos novos:** `agendar.php` (página), `css/agendar.css`, `js/agendar.js`, `lib/disponibilidade.php` (cálculo dos horários), `api/horarios.php` (JSON), `api/clientes-busca.php` (busca por nome/telefone), `criar-agendamento.php` (gravação) e `criar-cliente-rapido.php` (cliente sem sair da tela).
- **Funcionamento:** o usuário escolhe serviço, profissional (ou "qualquer um"), dia e horário; a cada mudança a tela consulta a API e redesenha os horários. Ao salvar, o servidor revalida tudo e grava com lock por funcionário. Cliente novo pede **nome + telefone** e reaproveita o cadastro quando o telefone já existe.
- **Regras aplicadas na disponibilidade:** horário de funcionamento por dia, almoço, bloqueios do estabelecimento e do profissional, agendamentos ativos (cancelado não ocupa), duração do serviço, intervalo configurado, antecedência mínima e janela máxima. Horários que já passaram não aparecem.
- **Segurança:** `id_estabelecimento` sempre da sessão; CSRF na página, no agendamento e no cliente rápido; serviço/cliente/funcionário validados contra a conta; POST sem CSRF recusado; GET no endpoint de gravação responde 405.
- **Interface:** desktop em duas colunas (decisões à esquerda, horários à direita); celular em uma coluna com grade de 4 horários; resumo fixo com o que será gravado; botão **Hoje** só aparece quando o dia aberto é hoje; dia fechado, bloqueio e data passada têm mensagem própria.
- **Validação local (21/11/2026, PHP 8.4 + MariaDB 11.8 local):** `php -l` e `node --check` aprovados; API conferida em dia aberto (16 horários), domingo fechado, data passada, profissional sem vínculo e serviço de outra conta; criação real pelo navegador gravou `#14` e foi removida depois; repetir o mesmo horário foi recusado; POST sem CSRF recusado; cliente de outra conta recusado; cliente novo criado e reaproveitado pelo telefone; desktop e 390/360 px sem overflow. `responsividade-check` (205), agenda (19), cancelamento (20), configuração (13) e reenvio (11) aprovados.
- **Ajuste de 21/11 (pedido do responsável):** ao focar o campo de cliente, a lista dos cadastrados aparece (até 20); a busca filtra a cada letra, priorizando nomes que **começam** com o texto digitado e usando "contém" somente quando não há começo correspondente; telefone também filtra; sem resultado, mostra "Nenhum cliente encontrado" e mantém o cadastro rápido.
- **Ajuste de 21/11 (tela igual ao protótipo aprovado):** `agendar.php` recebeu **Repetição** (não se repete / pacote de sessões / retorno recorrente), **A cada N dias** (7 a 60), **Quantas vezes**, **mapa da série** e **lista de sessões** com horário por sessão. O servidor passou a gravar **várias sessões na mesma transação** (tudo ou nada), com revalidação e lock por profissional. O formulário antigo saiu de `agenda.php`, que agora leva para a tela nova; o menu ganhou o item **Novo agendamento**.
- **Validação do ajuste (21/11/2026, local):** 3 sessões gravadas pelo navegador (`#15`, `#16`, `#17`) e conferidas no MySQL; conflito na sessão 1 recusou o lote inteiro sem gravar nada; retorno com uma sessão gravou `#18`; todos os registros de teste foram removidos. Desktop e 390 px sem overflow.
- **Navegação (21/11, decisão do responsável):** o menu passou a ter **um único item "Agenda"**; a tela de agendamento é aberta por ela, em dois pontos (cartão no topo e botão no painel do dia). `agendar.php` mantém "Agenda" como item ativo no menu para não perder a orientação.
- **Correção (21/11):** ao clicar num horário, a grade não era redesenhada e o horário escolhido não ficava marcado visualmente. Agora o botão selecionado recebe o destaque do protótipo, por sessão (trocar de sessão destaca o horário daquela sessão).
- **Não implementado neste lote:** pacote de sessões, retorno recorrente, mini mapa, modo híbrido (PEND-01) e remoção do formulário antigo de `agenda.php` (ainda funcional, mas sem as regras novas).
- **Pendências:** validar a tela no navegador do responsável; decidir quando o formulário antigo sai; publicar só depois da validação.

---

## PEND-01 — Modo híbrido: sessão única x pacote de sessões (decisão de 20/11/2026; a implementar)

- **Pedido do responsável:** o sistema precisa servir **os dois tipos de estabelecimento**: quem agenda **um atendimento avulso** (barbearia, salão) e quem trabalha com **pacote de sessões** (clínica de estética, odontologia). A escolha deve ser **confirmada no cadastro** e **alterável em Configurações**, com a opção de **ativar/desativar**; desativado, os controles de sessão **não aparecem**.
- **Decisão registrada:** o recurso de sessões fica para um **lote posterior**; o agendamento avulso é a base e não pode depender dele.
- **Efeito no desenho:** a mesma página de agendamento ganha o campo **Sessões** apenas quando o estabelecimento tiver o modo habilitado. Desligado, a página volta ao fluxo simples (1 cliente, 1 serviço, 1 horário).
- **Onde entra:** cadastro (escolha inicial, com padrão **sessão única**) e Configurações → Estabelecimento (ativar/desativar). Precisa de coluna própria no banco, o que exige migração e autorização separada.
- **Pendências:** definir o nome do campo, o padrão para contas existentes, o texto da tela e se a mudança de modo afeta agendamentos já criados. **Não implementar sem autorização.**

---

## DEPLOY-20261120-B — Publicação do SHA 0be0606 (20/11/2026)

- **Autorização:** responsável autorizou publicar o SHA `0be06062eee9f8920d06eceba49e6e40d4db1542`, seguindo a regra de **1 deploy por dia** e publicação ao concluir a funcionalidade.
- **Backup antes da publicação:** `C:\Users\EDER\Documents\AgenDay-Backups\2026-11-20_195512_79e5f900` (`BACKUP OK`).
- **SQL:** nenhum neste lote.
- **Execução:** run `44840901688` na branch `dev-feira-uninorte`; `Validar codigo e pacote` ✅, `Testar FTPS sem publicar arquivos` ✅, `Enviar ao InfinityFree somente com autorizacao` ✅.
- **Flags:** ligadas durante a execução e desligadas ao final (`false` / `disabled`, conferido em 21/11 00:04 UTC).
- **Validação após publicar:** por FTPS — `excluir-cliente.php` presente, `js/excluir-registro.js` presente, `bi-trash` em `cad-cliente.php` e `responsivo.css?v=20261120-del01`. Nenhuma alteração de banco.
- **Pendências:** validar a exclusão no site com a conta real e em celular físico.

---

## DEL-01 — Excluir cliente, funcionário e serviço (20/11/2026; integrada no PR #51 e publicada em 20/11/2026 — SHA `0be0606`)

- **Escopo autorizado:** lixeira ao lado do botão **Editar** (abaixo dele no celular) nas listas de clientes, funcionários e serviços, com pop-up de confirmação contendo **Cancelar** e **Excluir**. Nenhum deploy.
- **Arquivos:** `excluir-cliente.php`, `excluir-funcionario.php`, `excluir-servico.php` (novos, somente POST), `js/excluir-registro.js` (novo, preenche o modal), `cad-cliente.php`, `cad-funcionario.php`, `cad.servicos.php` (botão, modal e script), `css/responsivo.css` (alvo de 44 px no celular, classe `ag-acoes-registro`), `tests/responsividade-check.js` (+12 verificações, 185 no total).
- **Regras aplicadas:** `id_estabelecimento` sempre da sessão; CSRF obrigatório; GET responde 405; registro de outra conta devolve "não encontrado"; exclusão bloqueada quando existe **agendamento** do cliente, funcionário ou serviço, preservando o histórico. Funcionário leva junto vínculos com serviços e bloqueios; serviço leva junto o vínculo com funcionários; ambas as operações rodam em transação com rollback.
- **Interface:** desktop mostra **Editar** e **Excluir** lado a lado; celular empilha a lixeira abaixo do Editar, com alvos de 44 × 44 px. O modal mostra o nome do registro, avisa que a ação não pode ser desfeita e, no funcionário/serviço, informa o que mais será removido. O botão de confirmar recebe foco ao abrir.
- **Validação local (20/11/2026, PHP 8.4 + MariaDB 11.8 local):** `php -l` aprovado nos 11 arquivos e `node --check` no JS; exclusões reais de cliente, serviço (com vínculo) e funcionário (com vínculo) conferidas no MySQL; bloqueio confirmado para os três quando há agendamento, com registro preservado; cliente de outra conta devolve "Cliente não encontrado" e sobrevive; POST sem CSRF recusado; GET no endpoint responde 405; exclusão completa pelo navegador com o modal (id preenchido e mensagem de sucesso); desktop e 360 px sem overflow. `responsividade-check` (185), agenda (19), cancelamento (20), configuração (13) e reenvio (11) aprovados.
- **Pendências:** validar no site publicado após o próximo deploy; celular físico; a foto do cliente/funcionário permanece em `arquivos/` após a exclusão (limpeza de arquivo órfão não faz parte deste lote).

---

## DEPLOY-20261120 — Publicação do SHA 84eef5d (20/11/2026)

- **Autorização:** responsável autorizou publicar o SHA `84eef5dd2ee861669c79daf592b1219410811669` em `https://agenday-sistema.infinityfree.io/`, mantendo os dados de produção.
- **Backup antes da publicação:** `C:\Users\EDER\Documents\AgenDay-Backups\2026-11-20_192137_a1d631ca` — `backup-completo.zip` (8,8 MB), `banco-agenday.sql` (22,1 KB), árvore `htdocs` e `relatorio.json`. Rotina validada (`Executar-Backup-Agenday.ps1`).
- **Banco:** `sql/migracao-horario-funcionamento.sql` aplicado no phpMyAdmin antes do código. Conferido por consulta: tabelas `horario_funcionamento` e `bloqueio` = 1 e 1; 5 colunas novas em `estabelecimento`. Nenhum dado apagado.
- **Execução:** workflow `agenday-publicar.yml` disparado por `workflow_dispatch` na branch `dev-feira-uninorte` (SHA exato). Run `44837030817`: `Validar codigo e pacote` ✅, `Testar FTPS sem publicar arquivos` ✅, `Enviar ao InfinityFree somente com autorizacao` ✅ (sucesso em 20/11/2026).
- **Flags:** `AGENDAY_DEPLOY_ENABLED=true` / `AGENDAY_DEPLOY_MODE=publish` ligadas apenas durante a execução e **desligadas ao final** (`false` / `disabled`, conferido em 23:33).
- **Validação após publicar:** arquivos conferidos por FTPS — `index.php` com o bloco de horário no cadastro (`name="dias[]"`, `name="abertura"`, legenda "Horário de funcionamento") e `login.css?v=20261120-cad01`; `config.php` com aba Horários, `id="formBloqueio"`, `criar_bloqueio` e `configuracoes-v2.css?v=20261120-ui16`; `js/configuracoes.js` com o atalho 23:59; `lib/bloqueios.php` e `lib/horario-funcionamento.php` presentes. Navegador real: tela de login carregando e bloco de horário visível. `arquivos/` e `conexao.php` preservados; nenhum exportador temporário de backup restou.
- **O que muda para o usuário:** edição de cliente, funcionário e serviço; aba **Horários**; cadastro já com horário; tela de **Bloqueios**.
- **O que ainda não muda:** a agenda continua sem usar horário, bloqueio e regras no cálculo de horário livre; isso é a próxima fase (AGD-01). O envio do código e o SQL não alteram esse comportamento.
- **Pendências:** validar os fluxos autenticados no site publicado com a conta do responsável (login, salvar horário, criar/excluir bloqueio, cadastro novo); celular físico; implementar a agenda.

---

## BLQ-01 — Tela de bloqueios (20/11/2026; integrada no PR #48)

- **Escopo autorizado:** interface para cadastrar e excluir bloqueios de agenda (feriado, folga, curso, "fecha mais cedo"), dentro de **Configurações → Horários**, abaixo das regras. Nenhum deploy e nenhuma alteração em banco de produção.
- **Arquivos:** `lib/bloqueios.php` (novo: validação, listagem, criação e exclusão), `config.php` (seção Bloqueios + ações `criar_bloqueio`/`excluir_bloqueio`), `js/configuracoes.js` (sugere 23:59 ao informar o início e valida fim > início), `css/configuracoes-v2.css` (cartões de bloqueio e botão de 44 px), `tests/responsividade-check.js` (+3 verificações, 173 no total).
- **Regras aplicadas:** `id_estabelecimento` sempre da sessão; CSRF obrigatório; `inicio` e `fim` validados como data/hora real (rejeita 30/02 e formato ambíguo); fim depois do início; motivo obrigatório até 120 caracteres; funcionário precisa pertencer à conta; exclusão filtra por estabelecimento e bloqueio, devolvendo "não encontrado" para outra conta.
- **Interface:** quatro campos (Início, Fim, Motivo, Quem fica bloqueado), lista em ordem de início com período, motivo e escopo, e botão de excluir com alvo de 44 px. Sem funcionário selecionado, vale para o estabelecimento inteiro.
- **Validação local (20/11/2026, PHP 8.4 + MariaDB 11.8 local):** `php -l` e `node --check` aprovados; POSTs reais criaram feriado de dia inteiro e folga de funcionário, conferidos no MySQL; rejeitados fim antes do início, motivo vazio, 30 de fevereiro, funcionário de outra conta e POST sem CSRF (403); exclusão removeu apenas o registro da própria conta; tentativa de excluir bloqueio de outra conta devolveu "Bloqueio não encontrado" e o registro sobreviveu; lista de outra conta não aparece; navegador em desktop e 360 px sem overflow, com o atalho de 23:59 funcionando. `responsividade-check` (173), agenda (19), cancelamento (20) e configuração (13) aprovados.
- **Pendências:** a agenda ainda **não usa** os bloqueios no cálculo de horário livre (próxima fase); validar em celular físico; aplicação da migração no InfinityFree antes do deploy; PR/CI.

---

## CAD-02 — Horário de funcionamento no cadastro (20/11/2026; branch `feat/cadastro-horario`, integrada nos PRs #46/#47)

- **Escopo autorizado:** pedido do responsável para informar o horário básico já na criação da conta, antes da tela de bloqueios. Nenhum deploy e nenhuma alteração em banco de produção.
- **Formulário (`index.php`):** bloco compacto **"Horário de funcionamento"** no cadastro, com os 7 dias em checkboxes (seg–sex marcados), **Abre às / Fecha às** pré-preenchidos com 08:00/18:00 e legenda apontando para Configurações → Horários. O mesmo horário vale para todos os dias marcados; almoço e horário diferente por dia continuam em Configurações.
- **Backend (`cadastrar.php`):** valida dias (0–6), exige pelo menos um dia, horário no formato HH:MM e fechamento depois da abertura; monta os 7 dias e grava com `agenday_horario_criar_padrao($conexao, $novoId, $diasFuncionamento)`.
- **Biblioteca (`lib/horario-funcionamento.php`):** `agenday_horario_criar_padrao()` passou a aceitar o 3º parâmetro opcional `$dias`; sem ele, mantém o padrão (usado pelo cadastro Google). Continua com `INSERT IGNORE` e `try/catch`, sem interromper o cadastro se a migração não existir.
- **Estilo (`css/login.css`):** bloco em cartão claro, checkboxes com estado marcado visível, alvos de toque de 36 px no celular; `index.php` passou a carregar `login.css?v=20261120-cad01`.
- **Regras aplicadas:** CSRF obrigatório; dias fora de 0–6 ignorados; nenhum dia aberto é recusado; horário inválido (`25:00`) é recusado; fechamento antes da abertura é recusado; a conta **não** é criada nesses casos.
- **Validação local (20/11/2026, PHP 8.4 + MariaDB 11.8 local):** `php -l` aprovado em `cadastrar.php`, `index.php` e na biblioteca; POSTs reais conferidos no MySQL — seg–sex 09:00–19:00 gerou 7 linhas com sábado e domingo fechados; todos os dias 07:30–22:00 gerou 7 abertos; nenhum dia, horário invertido, `25:00` e POST sem CSRF recusados sem criar conta; `node tests/responsividade-check.js` (170 verificações) e os testes de agenda, cancelamento, senha, configuração e reenvio aprovados; desktop e 360 px sem overflow. O teste `google-identidade-validacao.php` não roda neste Windows por falha de `openssl_pkey_new` (limitação do ambiente, sem relação com o lote).
- **Pendências:** cadastro via Google ainda usa o horário padrão (não recebeu o bloco); validar em celular físico; aplicação da migração de horário no InfinityFree antes do deploy; PR/CI.

---

## CAD-01 — Cadastro cria o horário de funcionamento (20/11/2026; branch `fix/cadastro-horario-padrao`, integrada no PR #45)

- **Escopo autorizado:** corrigir o cadastro para que toda conta nova já nasça com os 7 dias de `horario_funcionamento` gravados, em vez de depender do padrão apenas em memória. Nenhum deploy e nenhuma alteração em banco de produção.
- **Arquivos:** `lib/horario-funcionamento.php` (nova `agenday_horario_criar_padrao()`), `cadastrar.php` (cadastro comum) e `google-cadastro-concluir.php` (cadastro via Google). `tests/responsividade-check.js` ganhou 3 verificações estruturais (168 no total).
- **Regras aplicadas:** os 7 dias usam o mesmo `agenday_horario_padrao()` da tela (seg–sex 08:00–18:00, sábado 08:00–12:00, domingo fechado, sem almoço); a gravação é `INSERT IGNORE`, então nunca sobrescreve linha existente; no cadastro Google a criação ocorre dentro da transação já existente; se a migração de horário ainda não estiver aplicada, a função apenas registra no log e **o cadastro continua funcionando**.
- **Validação local (20/11/2026, PHP 8.4 + MariaDB 11.8 local):** `php -l` aprovado nos 3 arquivos; cadastro real pela tela criou a conta id 22 com 7 linhas conferidas no MySQL e as 5 regras com os padrões; a conta nova abre Configurações → Horários já preenchida e sem aviso de migração; simulação de migração ausente (tabela renomeada) manteve o cadastro funcionando, com `agenday_horario_criar_padrao()` devolvendo `false` e log registrado, e a tabela restaurada depois; `node tests/responsividade-check.js` (168 verificações), `agendamento-validacao` (19), `agendamento-cancelamento` (20), `alteracao-senha-validacao` (16), `configuracao-estabelecimento` (13) e `cadastro-reenvio` (11) aprovados. Cadastro via Google **não** foi exercitado de ponta a ponta (exige OAuth real); validado por código e `php -l`.
- **Pendências:** validar o cadastro Google em ambiente real; aplicação da migração de horário no InfinityFree antes do deploy; validação visual do responsável; PR/CI.

---

## HOR-01 — Horário de funcionamento e regras de agendamento (20/11/2026; branch `feat/horario-funcionamento`, publicada no deploy de 20/11/2026 — SHA `84eef5d`)

- **Escopo autorizado:** criar a base de tempo do agendamento — horário de funcionamento por dia (com almoço e intervalo), tabela de bloqueios e regras do estabelecimento (antecedência mínima, janela máxima, prazo de cancelamento e tolerância de atraso) — e editá-las em **Configurações → Horários**. Nenhum deploy e nenhuma alteração em banco de produção.
- **Banco:** `sql/migracao-horario-funcionamento.sql` (aplicar no phpMyAdmin **antes** de publicar o código) cria `horario_funcionamento` (1 linha por dia: aberto, abre, fecha e almoço), `bloqueio` (estabelecimento inteiro ou funcionário) e adiciona 5 colunas em `estabelecimento`: intervalo da agenda (**um só para a semana**, padrão 30 min), antecedência mínima, janela máxima, prazo de cancelamento e tolerância de atraso. `sql/BD_v2.sql` foi atualizado como modelo para banco novo. Nenhum dado existente é apagado; quem não tem linhas em `horario_funcionamento` recebe o padrão do código (seg–sáb 08:00–18:00, sábado até 12:00, domingo fechado).
- **Código:** `lib/horario-funcionamento.php` (padrões, carga, validação, gravação em transação e `agenday_horario_janelas()`, que devolve os períodos do dia já sem o almoço), `config.php` (terceira aba **Horários**, POST `salvar_funcionamento`), `js/configuracoes.js` (dia fechado desabilita os campos) e `css/configuracoes-v2.css` (grade em tabela no desktop e cartões no celular).
- **Regras aplicadas:** `id_estabelecimento` sempre da sessão; CSRF obrigatório; fechamento depois da abertura; almoço dentro do expediente e com início antes do fim; pelo menos um dia aberto; intervalo da agenda restrito a 10/15/20/30/45/60 min; limites das demais regras validados no servidor. Se a migração ainda não foi aplicada, a página continua funcionando e apenas a aba de horários fica bloqueada com aviso.
- **Ajuste de 20/11 (pedido do responsável):** o intervalo saiu da linha de cada dia e passou a ser **um único valor para a semana**, no campo renomeado **"Novo horário a cada"**, com legenda explicando que define de quanto em quanto tempo os horários são oferecidos (ex.: 30 min → 09:00, 09:30, 10:00) e que não altera a duração do serviço.
- **Validação local (20/11/2026, PHP 8.4 + MariaDB 11.8 local):** migração aplicada e reexecutada sem erro; `php -l` e `node tests/responsividade-check.js` (165 verificações) aprovados; gravação conferida no MySQL (7 dias, almoço, intervalo da semana e as demais regras); rejeitados fechamento antes da abertura, almoço incompleto, almoço fora do expediente, todos os dias fechados, janela fora de 1–365, intervalo fora das opções e POST sem CSRF (403); estabelecimento sem linhas recebe o padrão sem gravar nada; `agenday_horario_janelas()` devolve dois períodos quando há almoço; desktop e 360 px sem overflow, com o dia fechado desabilitando os campos.
- **Pendências:** tela de bloqueios (tabela criada, ainda sem interface), uso das janelas pela agenda, validação visual do responsável, celular físico, PR/CI e aplicação do SQL no InfinityFree antes do deploy.

---

## EDIT-SER-01 — Edição de serviço (19/11/2026; implementada na branch `feat/editar-servico`, publicada no deploy de 20/11/2026 — SHA `84eef5d`)

- **Escopo autorizado:** editar serviço já cadastrado (nome, duração, preço e descrição) com validação equivalente à do cadastro, CSRF e isolamento por `id_estabelecimento` da sessão.
- **Arquivos:** `editar-servico.php` (novo endpoint, somente POST) e `cad.servicos.php` (botão **Editar**, modal preenchido por `data-*` e mensagem de sucesso variável).
- **Regras aplicadas:** o serviço só é atualizado quando `id_servicos` pertence ao estabelecimento da sessão; duração convertida de minutos para `TIME`; preço gravado como `DECIMAL(10,2)` sem aritmética de ponto flutuante; nome duplicado reaproveita a mensagem de unicidade já existente no cadastro.
- **Validação local (19/11/2026, PHP 8.4 + MariaDB 11.8 local):** `php -l` aprovado; edição real de nome/duração/preço/descrição conferida no MySQL; nome duplicado rejeitado com a mensagem de unicidade; serviço de outro estabelecimento rejeitado ("Serviço não encontrado."); duração 0 e preço negativo rejeitados; GET responde 405; modal pré-preenchido e sem overflow em 360 px.
- **Pendências:** validação visual do responsável, celular físico e CI do PR.

---

## EDIT-FUN-01 — Edição de funcionário (19/11/2026; implementada na branch `feat/editar-cliente`, publicada no deploy de 20/11/2026 — SHA `84eef5d`)

- **Escopo autorizado:** editar funcionário já cadastrado (nome, contato, foto opcional e vínculos com serviços), com validação, CSRF, transação e isolamento por `id_estabelecimento` da sessão. Sem autorização de deploy; PR/merge não realizados.
- **Arquivos:** `editar-funcionario.php` (novo endpoint, somente POST), `cad-funcionario.php` (botão **Editar**, modal com os mesmos checkboxes de serviços e mensagem de sucesso variável) e `info-funcionario.php` (passa a expor `servicos_ids` para pré-marcar os serviços do funcionário).
- **Regras aplicadas:** funcionário só é atualizado quando `id_funcionario` e `id_estabelecimento` da sessão coincidem; cada serviço enviado é reconferido como pertencente ao estabelecimento antes de gravar; os vínculos são recompostos em transação (DELETE + INSERT) e revertidos por rollback em caso de falha; foto antiga só é apagada após commit e se o caminho casar com o padrão gerado pelo servidor.
- **Validação local (19/11/2026, PHP 8.4 + MariaDB 11.8 local):** `php -l` aprovado nos três arquivos; cadastro de teste com um serviço → edição de nome/contato → vínculos recompostos para dois serviços conferidos no MySQL; remoção de todos os vínculos funcionou; funcionário de outro estabelecimento rejeitado ("Funcionário não encontrado."); serviço de outro estabelecimento rejeitado; telefone inválido e POST sem CSRF rejeitados. Modal pré-marca o serviço existente e, em 360 px, não há overflow horizontal.
- **Pendências:** validação visual do responsável, upload de foto substituta, celular físico e CI do PR.

---

## EDIT-CLI-01 — Edição de cliente (19/11/2026; implementada na branch `feat/editar-cliente`, publicada no deploy de 20/11/2026 — SHA `84eef5d`)

- **Escopo autorizado:** permitir editar cliente já cadastrado (nome, e-mail, contato e troca opcional de foto), mantendo validação, CSRF e isolamento por `id_estabelecimento` da sessão. Não houve autorização de deploy; PR/merge não realizados.
- **Arquivos:** `editar-cliente.php` (novo endpoint, somente POST) e `cad-cliente.php` (botão **Editar** por linha, modal preenchido por `data-*`, mensagem de sucesso variável, sem alterar o cadastro existente).
- **Regras aplicadas:** cliente só é atualizado quando `id_cliente` e `id_estabelecimento` da sessão coincidem; foto nova só substitui a anterior após UPDATE bem-sucedido e o arquivo antigo só é removido se o caminho casar com o padrão gerado pelo servidor.
- **Validação local (19/11/2026, servidor PHP 8.4 + MariaDB 11.8 local):** `php -l` aprovado nos dois arquivos; fluxo HTTP real com cadastro de teste → edição conferida no MySQL local → cliente de outro estabelecimento rejeitado com "Cliente não encontrado." → telefone inválido e POST sem CSRF rejeitados → GET devolve 405. Em 360 px não há overflow horizontal e o botão vira ícone.
- **Pendências:** validação visual do responsável, teste de upload de foto substituta, celular físico e CI do PR.

---

## FIX-01 — Reenvio duplo em Clientes/Funcionários (19/11/2026; integrado, publicado, QA técnico aprovado; validação do responsável pendente)

- **Problema confirmado em produção, antes da correção:** phpMyAdmin mostrou dois registros diferentes para o mesmo nome/telefone nos exemplos da conta de teste (clientes IDs 9/10; funcionários IDs 12/13); as listagens exibiam esses pares. Nenhum registro existente foi apagado.
- **Escopo aprovado:** proteção front-end e PHP para cadastro de cliente e funcionário; testes, PR/merge e publicação autorizada no InfinityFree. Não alterar tabelas, excluir duplicatas nem tocar na `main`.
- **Solução:** bloqueio visual do botão Concluir com `Salvando...`; token único de cadastro (separado de CSRF), emitido na sessão para cada formulário e consumido **antes do INSERT e upload**. Sessão PHP permanece aberta durante o POST para serializar requisições da mesma sessão. Token expira após 30 minutos, possui limite em memória e não é usado como regra de unicidade de telefone.
- **Arquivos:** `cad-cliente.php`, `cad-funcionario.php`, `cadastro-clientes.php`, `cadastro-funcionario.php`, `lib/cadastro-reenvio.php`, `js/cadastro-envio.js` e `tests/cadastro-reenvio.php`.
- **Integração:** [PR #40](https://github.com/Emds96/AgenDay-PI/pull/40) mesclado na `dev-feira-uninorte`, aplicação no SHA `0eb23f1d2e21562a5e326518a31ea8c80fbef9bf`. No Windows, `node tests/responsividade-check.js` aprovou **164 verificações**, e `node --check` aprovou os JS. CI no [run #44661762542](https://github.com/Emds96/AgenDay-PI/actions/runs/44661762542) passou, incluindo **11 verificações PHP anti-replay**; lint PHP no [run #44661762611](https://github.com/Emds96/AgenDay-PI/actions/runs/44661762611) também aprovado.
- **Backup pré-deploy:** cópia privada `2026-11-19_223607_3f40700e`, **BACKUP OK**, 105 arquivos de `htdocs`, 10 tabelas no MySQL, importação/restauração local MariaDB aprovada. Sem backup em GitHub ou chat.
- **Publicação controlada:** assistente `scripts/publicacao_assistida.py` em modo real para SHA exato autorizado; [run #44662655163](https://github.com/Emds96/AgenDay-PI/actions/runs/44662655163) com jobs `validar`, `diagnosticar_ftp` e `publicar` = **success**. Flags reconfirmadas por consulta independente: `AGENDAY_DEPLOY_ENABLED=false`, `AGENDAY_DEPLOY_MODE=disabled`. Nenhuma alteração em `main`, Secrets, banco ou workflow.
- **QA real via DeskBrowse + consulta MySQL somente leitura (19/11):** na conta de QA, cadastro de `QA AntiDuplo Cliente 19Nov` feito uma vez e reenvio idêntico (mesmo token) rejeitado; listagem e phpMyAdmin confirmaram **exatamente um novo registro** (ID 12, estabelecimento de QA 19). Repetido com `QA AntiDuplo Funcionario 19Nov`, incluindo vínculo a serviço: reenvio idêntico rejeitado; listagem e MySQL confirmaram **um registro novo** (ID 15, estabelecimento de QA 19). Esses dois registros de teste foram mantidos; não apagar sem autorização. Duplicações antigas não foram alteradas.
- **Pendências:** validação visual do responsável no celular e eventual teste de clique duplo humano em rede lenta; os testes E2E comprovam especificamente **reenvio da mesma tentativa**, não impedimento de dois cadastros novos independentes com iguais dados.
- **Limitação conhecida:** o token por sessão protege replay da mesma tentativa e cliques duplos; **não** impede cadastros separados e intencionais com nome/telefone iguais em outras sessões. Garantia global de idempotência exigiria persistência no banco, fora deste escopo.

---


## OPS-01 — Fluxo rápido e assistente de publicação ([PR #39](https://github.com/Emds96/AgenDay-PI/pull/39) integrado; piloto real validado no FIX-01)

- **Autorizado:** documentar classificacao simples/media/complexa, autorizacao de tarefa com escopo claro, criar assistente operacional e testes sem publicar; criar branch e PR para `dev-feira-uninorte`. **Autorizacao inicial:** sem merge ou deploy. **Autorizacao posterior:** responsavel solicitou continuidade apos validacao Windows, permitindo revisao e integracao do PR #39. **Permanece sem autorizacao:** deploy, modificacao de Secrets, banco ou workflow.
- **Implementacao proposta:** `scripts/publicacao_assistida.py` com simulacao padrao sem chamadas a GitHub; modo real separado por `--executar`, exigindo SHA integral, confirmacao literal, backup privado recente e integro, CI do mesmo SHA, flags em estado seguro e aprovacao humana do environment. Nao substitui backup nem rollback.
- **Validacao no Windows do responsavel via DeskBrowse (19/11):** arquivos do commit do PR consultados pelo `gh` em pasta temporaria, `python -m py_compile` aprovado, `python -m unittest discover -s tests -p test_publicacao_assistida.py -v` **7/7 aprovados** e simulacao com SHA real `cfa8203541d7f26a251df7be9f38156aa2a79271` aprovada (`SIMULACAO_PC_OK`). A pasta temporaria foi removida. Nenhum deploy, SQL ou acesso FTP foi realizado pelos testes.
- **Uso real posterior e autorizado em 19/11:** modo `--executar` utilizado para publicar exclusivamente o SHA do FIX-01 com backup validado, CI aprovada e aprovação do environment no run `44662655163`; jobs bem-sucedidos e flags restauradas a `false/disabled` no final. Execução completa do assistente levou aproximadamente 222 segundos; backup completo prévio, aproximadamente 315 segundos. Esses tempos são observações desta execução e podem variar. O piloto não elimina aprovação por tarefa nem o risco de deploy FTPS parcial. Outras tarefas exigem nova autorização de publicação.

---

## UI-10 — Remoção do dropdown da conta no cabeçalho (19/11/2026; integrada, publicada, QA inicial aprovado)

- **Escopo aprovado:** retirar inteiramente do cabeçalho autenticado avatar/foto, nome, seta e dropdown da conta em desktop/mobile, preservando à esquerda o hambúrguer e ao centro **AgenDay** com Space Grotesk Medium. A autorização abrange implementação, PR/integração com `dev-feira-uninorte` e deploy para validação visual; **não** inclui mudanças na `main`, banco, cadastros, agendamento ou outros módulos.
- **Pré-condição confirmada no código:** `lib/menu-lateral.php` já possui os links reais `config.php` (**Configurações**) e `logout.php` (**Sair**), portanto o menu hambúrguer não precisa ser modificado.
- **Alteração restrita:** removidas as oito inclusões de `lib/menu-conta.php` em `agenda.php`, `cad-cliente.php`, `cad-funcionario.php`, `cad.servicos.php`, `comandas-abertas.php`, `financeiro.php`, `historico-comandas.php` e `config.php`; excluído do repositório o componente exclusivo `lib/menu-conta.php`. Em `css/responsivo.css`, eliminadas somente as regras da conta/dropdown que não têm mais uso, mantendo a grade de três colunas **simétricas** (hambúrguer no trilho esquerdo, marca no centro, trilho direito sem conteúdo) para preservar a centralização. URLs CSS versionadas `?v=20261119-ui10` para invalidar cache.
- **O que permanece:** marca e tipografia atuais, `lib/menu-lateral.php`, rotas/funcionalidades Configurações e Sair, interface interna de `config.php`, imagem que o estabelecimento salvou na pasta `arquivos/estabelecimentos/` e suas funções `lib/avatar-estabelecimento.php`, dados da sessão e todas as funcionalidades de negócio. Nenhum script específico do dropdown foi identificado; Bootstrap e scripts gerais permanecem por serem utilizados no restante da interface.
- **Testes locais e CI:** `node tests/responsividade-check.js` passou com **159 verificações**; `python scripts/preparar_publicacao.py` gerou pacote com **88 arquivos**, com menu lateral presente, sem `lib/menu-conta.php`, SQL, segredos ou uploads; `git diff --check` aprovado. [CI do PR #44519684723](https://github.com/Emds96/AgenDay-PI/actions/runs/44519684723) e CI pós-merge [PHP #44522416532](https://github.com/Emds96/AgenDay-PI/actions/runs/44522416532) + [validação #44522416531](https://github.com/Emds96/AgenDay-PI/actions/runs/44522416531) aprovadas no SHA correspondente.
- **Integração e backup:** [PR #37](https://github.com/Emds96/AgenDay-PI/pull/37) mesclado na `dev-feira-uninorte`, commit `22ff46775d4c373159538b4a6436e1e67fd752e8`. Backup privado pré-deploy `2026-11-19_081115_3ea3891b` com **BACKUP OK**, **97 arquivos** de `htdocs`, **10 tabelas** MySQL, restauração MariaDB local aprovada e SHA-256 de SQL/ZIP conferidos; pasta de uploads e configuração privada incluídas no backup. Uma primeira tentativa por atalho ficou presa e outras tentativas não aprovaram backup; **não foram utilizadas**. O deploy só foi liberado após o backup validado.
- **Deploy controlado:** [workflow #44522593229](https://github.com/Emds96/AgenDay-PI/actions/runs/44522593229), SHA de aplicação `22ff46775d4c373159538b4a6436e1e67fd752e8`, três jobs `success` (validar, diagnosticar FTPS, publicar). Flags reconfirmadas ao final: `AGENDAY_DEPLOY_ENABLED=false`, `AGENDAY_DEPLOY_MODE=disabled`. Sem alteração em `main`, banco, scripts de publicação ou credenciais.
- **QA real no Edge/ZERO Browser MCP (sessão autenticada, sem escrita, 19/11/2026):** `config.php` em **320, 360, 390, 412, 768 e 1366px** apresentou apenas botão hambúrguer e título `AgenDay` com **deslocamento 0 px** entre centros da marca e do cabeçalho nas seis larguras; fonte computada `Space Grotesk`, peso `500`; CSS servido com `?v=20261119-ui10`; sem overflow, avatar, nome, seta ou dropdown. Em **oito páginas** conferidas no viewport de 412 px, marca centrada 0 px, sem dropdown e sem erro PHP visível. Hambúrguer abre (menu `Menu`), Configurações e Sair permanecem na lista; fechar por Escape e X funcionou. Navegação real Agenda → Configurações funcionou, abas `Estabelecimento`/`Minha Conta` preservadas, e campo de foto e botão de senha presentes. A foto salva não foi excluída nem manipulada; nenhum POST de dados foi realizado.
- **Pendências:** aprovação visual do responsável em seu dispositivo real, clique efetivo em `Sair`/logout (neste QA somente a rota foi verificada, para preservar a sessão), teste com leitor de tela e outros navegadores. Não confundir CI com testes integrados de gravação. A documentação pós-deploy é registrada em PR separado, **sem outra publicação**.
- **Nota operacional:** o publicador FTPS adiciona/substitui arquivos e **não exclui arquivos antigos remotamente**. Mesmo que o antigo PHP deixe de existir no repositório/pacote, nenhuma página terá referência a ele; a eventual limpeza física do arquivo órfão na hospedagem exige procedimento de manutenção próprio, não necessário à remoção visual e não executado neste escopo.

---

## UI-09 — Menu hambúrguer padronizado (18/11/2026; integrado, publicado, QA inicial aprovado)

- **Autorização e escopo explícitos:** responsável aprovou a prévia visual e solicitou apenas trocar o título do menu de “AgenDay” para **“Menu”**, manter os cinco links individuais **Agenda, Clientes, Serviços, Funcionários, Configurações**, destaque da página ativa, ícones e paleta verde da identidade original e **Sair** destacado na parte inferior. Foi autorizada a implementação completa, PR/merge em `dev-feira-uninorte` e publicação/QA na hospedagem.
- **Fora do escopo e preservado:** o dropdown da conta no canto superior direito, com Configurações e Sair, **não foi removido**; essa alteração foi explicitamente separada para outra decisão. Também não alterar cabeçalho central AgenDay/Space Grotesk, páginas internas, autenticação, cadastros, agendamentos, banco, upload, Financeiro, Comandas, hospedagem/configuração privada ou `main`. Os módulos Financeiro e Comandas permanecem acessíveis em suas rotas existentes, só não aparecem no menu simplificado.
- **Estrutura implementada:** componente único `lib/menu-lateral.php`, utilizado por `agenda.php`, `cad-cliente.php`, `cad-funcionario.php`, `cad.servicos.php`, `comandas-abertas.php`, `financeiro.php`, `historico-comandas.php` e `config.php`. Mantém o ID/bootstrap offcanvas, botão de fechar à esquerda, título **Menu** à direita, cinco itens na ordem aprovada, `aria-current` na página selecionada e logout fixado no rodapé. SVGs decorativos em verde e ícone `img/icon-sair.svg` existente. Nenhuma rota PHP mudou.
- **CSS:** somente `css/responsivo.css` recebeu estilos específicos para `.ad-sidebar` (foco visível, fundo mint da opção ativa, espaçamento e toque, rodapé, safe-area mobile e sem overflow). Referências versionadas a `?v=20261118-ui09` nas oito páginas para invalidar cache sem afetar o CSS de outros componentes.
- **Validação do código:** `node tests/responsividade-check.js` passou com **144 verificações**; `python scripts/preparar_publicacao.py` gerou pacote com **89 arquivos**, incluindo `lib/menu-lateral.php`, sem SQL, segredos ou uploads. [CI do PR #44454381950](https://github.com/Emds96/AgenDay-PI/actions/runs/44454381950) aprovou PHP lint, testes PHP/Node e pacote; CI após integração [PHP syntax #44454449889](https://github.com/Emds96/AgenDay-PI/actions/runs/44454449889) e [validação #44454449838](https://github.com/Emds96/AgenDay-PI/actions/runs/44454449838) em `success`.
- **Integração e publicação:** [PR #35](https://github.com/Emds96/AgenDay-PI/pull/35) integrado em `dev-feira-uninorte`, commit de merge `f545f15565e98568ad72a63ada9e90d4e7ffa0f8`. Backup privado `2026-11-18_234446_3ce8f750`: **BACKUP OK**, 96 arquivos de `htdocs`, 10 tabelas SQL, restauração MariaDB local aprovada, hashes SHA-256 de SQL e ZIP conferidos. Deploy no [workflow #44454541668](https://github.com/Emds96/AgenDay-PI/actions/runs/44454541668): `validar=success`, `diagnosticar_ftp=success`, `publicar=success`. Houve HTTP 502 temporário da API ao desligar uma variável; nova tentativa restabeleceu e verificou **ambas** `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. `main`, banco, secrets e botão da conta não foram alterados.
- **QA real inicial (ZERO Browser MCP/Edge, sessão autenticada, 18/11/2026):** `config.php` com menu aberto em 320, 360, 390, 412, 768 e 1366 px: título **Menu**, cinco rotas individuais em ordem aprovada, fundo ativo verde-claro `rgb(231,248,244)`, área de toque de 56 px, **Sair** encostado no rodapé, sem overflow; Escape fechou e removeu backdrop nas seis larguras. Oito páginas administrativas foram inspecionadas em 412 px: título, links, opção ativa quando presente (Agenda, Clientes, Serviços, Funcionários e Configurações), e conta superior preservada. Três leituras de abertura haviam ocorrido antes do carregamento completo/transição; ao repetir em `load`, abriram corretamente. Navegação real de Configurações → Clientes funcionou; X e toque fora fecharam; nenhuma escrita em dados, upload ou senha foi feita.
- **Pendente:** aprovação visual humana no celular do responsável, teste dedicado com leitor de tela/outros navegadores e clique real em **Sair** com logout/login (o href foi conferido, mas a sessão não foi encerrada neste QA para não interferir no usuário). Não remover o dropdown superior sem autorização específica.

---

## UI-08 — Configurações em duas abas + Space Grotesk (18/11/2026; publicada e testada parcialmente em produção)

- **Integração autorizada e realizada:** [PR #33](https://github.com/Emds96/AgenDay-PI/pull/33) integrado em `dev-feira-uninorte` no merge `5637ef4092792d6493c461a0d15de5636244101a`. Branch `main` e esquema MySQL preservados. A autorização posterior para merge e deploy foi concedida para esta etapa, sem estendê-la a alterações de dados QA.
- **Alterações implementadas:** `config.php` com exatamente duas abas (**Estabelecimento** e **Minha Conta**) e um único painel visível; nome/telefone persistidos via SQL já existente, e-mail somente leitura na aba Conta, foto e Segurança com modal Alterar senha; estilos em `css/configuracoes-v2.css` e interação em `js/configuracoes.js`. Sem CNPJ, endereço, horário ou terceira aba.
- **Compatibilidade e senha:** `perfil.php` direciona ao painel Conta e o GET de `alterar-senha.php` também; o POST de senha mantém CSRF, validação pura, `password_verify`, `password_hash`, compare-and-swap e PRG com feedback exibido no modal/aba. A troca real de senha na hospedagem ainda não foi testada nesta versão.
- **Foto da conta, sem coluna SQL:** biblioteca `lib/avatar-estabelecimento.php` usa somente ID da sessão para arquivos públicos JPG/PNG em `arquivos/estabelecimentos/avatar-ID.ext`, até 2 MiB e 4096x4096, validação real de imagem/MIME e bloqueio a uploads não HTTP. Regra `.htaccess` nova, scripts de empacotamento/publicação ajustados para enviar apenas a regra, não fotos. Avatar aparece no componente compartilhado com fallback SVG. **Imagem pública por URL** e necessária em backup completo de `htdocs`.
- **Tipografia:** Space Grotesk peso 500 importada por HTTPS apenas para `.ad-header-brand` em desktop/mobile. As páginas autenticadas receberam versão de URL `?v=20261118-ui08` para CSS novo sem cache; fontes dos formulários permanecem inalteradas.
- **Testes locais realmente executados na branch:** `node --check js/configuracoes.js` sem erro; `node tests/responsividade-check.js` com **121 verificações aprovadas**; `python scripts/preparar_publicacao.py` com pacote de 88 arquivos sem SQL, senhas, fotos ou outros uploads, contendo nova regra anti-execução e arquivos UI-08. **CI PHP aprovada:** [run #44424205050](https://github.com/Emds96/AgenDay-PI/actions/runs/44424205050) no commit `fe7855b8319811e27dc48c16c629150a7bceccef`: sintaxe PHP, testes (incluindo nome/telefone, validação de senha e avatar), preparação do pacote e verificações anti-vazamento com `success`. Jobs de FTPS e publicação **skipped**; o computador Windows não possui PHP CLI configurado.
- **CI integrada:** [PHP lint #44428652349](https://github.com/Emds96/AgenDay-PI/actions/runs/44428652349) e [validação/pacote #44428652461](https://github.com/Emds96/AgenDay-PI/actions/runs/44428652461), ambas `success` no commit de merge. Testes da branch de tarefa também foram aprovados no [run #44424603604](https://github.com/Emds96/AgenDay-PI/actions/runs/44424603604).
- **Backup de segurança pré-deploy:** cópia privada no computador autorizado `2026-11-18_201846_a7eb4bb3` com **BACKUP OK**, 91 arquivos de `htdocs`, 10 tabelas exportadas, restauração em MariaDB local aprovada e SHA-256 de SQL/ZIP conferidos.
- **Publicação confirmada:** [workflow #44428793171](https://github.com/Emds96/AgenDay-PI/actions/runs/44428793171), SHA `5637ef4`, três jobs em `success` (código/pacote, conexão FTPS e publicação). Variáveis reconfirmadas depois do envio: `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. Nenhuma mudança em `main`, banco ou secrets.
- **QA real de leitura/navegação (ZERO Browser MCP/Edge, conta fictícia B, 18/11/2026):** `config.php` renderizou apenas as abas **Estabelecimento** e **Minha Conta** com um painel visível por vez. Em larguras 320, 360, 390, 412, 768 e 1366 px não houve overflow horizontal; marca centralizada, CSS versionado UI-08, nome da conta no desktop e oculto no mobile. O navegador confirmou Space Grotesk 500 carregada para `AgenDay`, mantendo fonte system-ui em título e campos. Telefone opcional formatou número brasileiro de teste ao perder foco (sem salvar). Aba Conta exibiu e-mail preenchido e somente leitura, entrada para foto e botão de senha. Navegação das abas com setas funcionou.
- **QA do modal (somente interface, sem alteração de senha):** três campos renderizados, foco inicial em senha atual e retorno ao acionador ao fechar com Escape, Cancelar ou X, quando aguardada a conclusão da transição Bootstrap. Formulário aponta para `alterar-senha.php`. Testes de senha errada, senha nova válida e persistência **não executados**.
- **Regressão de navegação e proteção:** dez rotas administrativas visitadas em 412 px, sem erro PHP visível nem overflow; `perfil.php` e GET de `alterar-senha.php` redirecionaram a `config.php?aba=conta`. Pasta `arquivos/estabelecimentos/` retornou HTTP 403 para listagem. Não houve criação/alteração/exclusão de dados nas contas QA.
- **Pendente antes de declarar conclusão funcional:** aprovação visual do responsável; teste real de salvar nome/telefone com recarga, upload JPG/PNG e troca da foto refletida no cabeçalho, alteração segura de senha (erro e sucesso), isolamento cruzado de A e B, leitor de tela e regressões adicionais em dispositivos físicos. Testes automatizados e de navegação **não comprovam** esses fluxos persistentes. Ver [CONFIGURACOES_UI08.md](CONFIGURACOES_UI08.md).

---

## UI-07 — Padronização do cabeçalho autenticado (18/11/2026; integrada, publicada e aprovada visualmente)

- **Escopo autorizado:** padronização do nome real da conta no cabeçalho, layout desktop/mobile e simplificação do dropdown. Nada de alterações nas páginas internas de Empresa, Perfil ou Alterar senha, agendamentos, banco, infraestrutura ou deploy.
- **Origem do dado verificada no código:** login.php, google-entrar.php e google-cadastro-concluir.php definem `$_SESSION['nome_estabelecimento']` a partir da conta autenticada; config.php atualiza o valor ao salvar o nome. O cabeçalho antes era replicado, com texto `Barbearia` fixo em páginas legadas.
- **Código implementado:** componente `lib/menu-conta.php` reutilizado pelas 10 páginas protegidas; avatar de conta local em SVG; nome escapado e exibido no botão somente no desktop, sempre identificado dentro do dropdown; opções limitadas a Configurações e Sair. Regras CSS de grid e breakpoint 767,98px em `css/responsivo.css`, preservando os offcanvas existentes.
- **Validação de código:** smoke checks estruturais adicionados em `tests/responsividade-check.js`. Execução manual do workflow de validação [#44409803682](https://github.com/Emds96/AgenDay-PI/actions/runs/44409803682) no commit `c65bcf59` com `validar=success`: PHP lint, testes automatizados, preparo e verificação do pacote aprovados; `diagnosticar_ftp=skipped` e `publicar=skipped`. Os checks não simulam navegação nem provam renderização PHP/Bootstrap.
- **Integração e primeira publicação:** [PR #29](https://github.com/Emds96/AgenDay-PI/pull/29) integrado em `dev-feira-uninorte`, merge `235502e72320cad158eb947869359cec59b7dbb7`; workflows de [PHP lint #44411838371](https://github.com/Emds96/AgenDay-PI/actions/runs/44411838371) e [validação #44411838496](https://github.com/Emds96/AgenDay-PI/actions/runs/44411838496) aprovados. Backup privado `2026-11-18_182233_6f8f3d6c`: BACKUP OK, 90 arquivos, 10 tabelas, restauração MariaDB local aprovada e hashes SQL/ZIP conferidos. Deploy [#44411994848](https://github.com/Emds96/AgenDay-PI/actions/runs/44411994848) `success` nos três jobs. Travas reestabelecidas `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`.
- **QA após primeira publicação (ZERO Browser MCP):** em conta fictícia B (`aq02teste`) o HTML do cabeçalho e o dropdown com `nome`, `Configurações`, `Sair` estavam corretos, mas a folha `css/responsivo.css` era servida do cache do navegador: nome aparecia em 320/412px e marca não ficava centralizada. Confirmação: requisição do CSS com query de versão carregou grid, ocultou o nome em 412px e não gerou overflow. **Correção em preparação:** versionar a URL de `css/responsivo.css` somente nas dez páginas autenticadas, evitando exigir limpeza manual de cache. Nenhum dado de negócio foi modificado nesse QA.
- **Correção do cache publicada:** [PR #30](https://github.com/Emds96/AgenDay-PI/pull/30) integrado em `dev-feira-uninorte` no commit `7a617429d0f3a7ba2adbd51480d09f055a2d2479` para adicionar `?v=20261118-ui07` à referência da folha responsiva nas 10 páginas autenticadas, sem alterar o CSS funcional. Validação de branch [#44413489673](https://github.com/Emds96/AgenDay-PI/actions/runs/44413489673) e CI integrada [PHP lint #44413614542](https://github.com/Emds96/AgenDay-PI/actions/runs/44413614542) + [validar pacote #44413614561](https://github.com/Emds96/AgenDay-PI/actions/runs/44413614561) com `success`.
- **Novo backup pré-deploy:** cópia privada `2026-11-18_183856_fdb6f960`, **BACKUP OK**, 91 arquivos e 10 tabelas, restauração MariaDB local aprovada, hashes SQL e ZIP reconferidos. Deploy do SHA `7a617429` pelo [run #44414427979](https://github.com/Emds96/AgenDay-PI/actions/runs/44414427979) em 18/11/2026, **validar=success, diagnosticar_ftp=success, publicar=success**. Depois do job, variáveis reconfirmadas `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. Não houve alterações no banco, secrets, workflow ou `main`.
- **QA de navegador após publicação final (ZERO Browser MCP/Edge, conta fictícia B):** `agenda.php` em 1366px exibiu `aq02teste` no topo; em 412px e 320px o botão ocultou o nome, com texto apenas dentro do dropdown; itens observados exatamente `aq02teste`, `Configurações` e `Sair`, links para `config.php` e `logout.php`, dropdown abrindo por clique. Nome real escapado e CSS `?v=20261118-ui07` confirmados; layout sem overflow horizontal e acionador de conta com 44px. Em **todas as dez páginas autenticadas** conferiu-se no viewport de 412px: CSS versionado carregado, marca `AgenDay`, nome da conta oculto no botão e ausência de overflow horizontal. Nenhum dado foi criado/editado/excluído nesse QA.
- **Aprovação visual humana (relato do responsável, 18/11/2026):** após o deploy final e as instruções para conferir o cabeçalho no site, o responsável respondeu **“ok tudo certo”**, aprovando visualmente a melhoria UI-07. Registro de aceite informado na conversa, não um teste automatizado adicional; o dispositivo e os tamanhos efetivamente utilizados pelo responsável não foram especificados. Evidências automatizadas no Edge permanecem descritas acima.
- **Cobertura adicional ainda pendente:** regressão em navegadores/dispositivos físicos diversos, testes dedicados de teclado/leitor de tela, 390/768px e confirmação por navegação efetiva de Configurações/Sair. A auditoria de isolamento cruzado continua separada; a aprovação do cabeçalho não conclui essas outras frentes.

---


## QA operacional — login automático DeskBrowse com duas contas (18/11/2026)

- **VALIDADO em produção na sessão atual, usando o plugin DeskBrowse:** `bridge_status` confirmou browser e desktop conectados; o novo `login_salvo` foi executado apenas com o nome das contas, sem senhas expostas.
- **Pré-condição observada:** a primeira tentativa de login A falhou enquanto o AgenDay já tinha a conta `aq02teste` autenticada na aba. Após logout pelo menu e retorno ao formulário público, a ferramenta funcionou. Não atribuir essa primeira falha à senha.
- **Conta A:** `login_salvo({"conta":"agenday-conta-a"})` enviou login; `browser__execute` confirmou `/agenda.php`, área administrativa e cabeçalho `qa01teste` (**PASS**).
- **Alternância:** logout da conta A confirmado em `/index.php` com formulário de senha (**PASS**).
- **Conta B:** `login_salvo({"conta":"agenday-conta-b"})` enviou login; `browser__execute` confirmou `/agenda.php`, área administrativa e cabeçalho `aq02teste` (**PASS**). Sessão B mantida aberta na aba de teste.
- **Alcance:** nenhum cadastro/agendamento/configuração foi alterado. Login A/B não comprova isolamento cruzado das operações; essa auditoria permanece pendente. A troca de contas pode interferir em outras abas do mesmo perfil Edge.
- **Procedimento definitivo:** [docs/DESKBROWSE_LOGIN_QA.md](DESKBROWSE_LOGIN_QA.md), referenciado por AGENTS.md, guia de continuidade e QA. Não armazenar nomes de usuário do cofre, e-mails ou senhas no GitHub.

---

## QA-04 — Configurações básicas do estabelecimento (18/11/2026; publicada e testada)

- **PR #26 integrado** na branch dev-feira-uninorte (merge 5f2c8666df9d3e63828509b4dd923f58020aac28) em 18/11/2026. Código: config.php, lib/configuracao-estabelecimento.php, js/button-add.js e tests/configuracao-estabelecimento.php.
- **CI**: [PHP lint #44365662706](https://github.com/Emds96/AgenDay-PI/actions/runs/44365662706) e [validação/pacote #44365662758](https://github.com/Emds96/AgenDay-PI/actions/runs/44365662758), ambas com success. Teste independente de validação presente em tests/configuracao-estabelecimento.php, mas não executado separadamente no workflow vigente.
- **Backup pré-deploy real**: 2026-11-18_140222_e2169559, status BACKUP OK, 89 arquivos, 10 tabelas, restauração MariaDB local aprovada, hashes SQL/ZIP conferidos; sem segredos no GitHub.
- **Deploy autorizado**: commit 5f2c8666 publicado pelo [run #44367305044](https://github.com/Emds96/AgenDay-PI/actions/runs/44367305044), com validar, diagnosticar FTPS e publicar success. Variáveis restauradas e reconfirmadas: AGENDAY_DEPLOY_ENABLED=false e AGENDAY_DEPLOY_MODE=disabled. Sem alterar banco, workflow, secrets, main ou repositório original.
- **QA real no Edge via DeskBrowse, conta fictícia QA01 (18/11):** leitura de nome/e-mail/contato do estabelecimento; e-mail readonly; gravação de nome e contato com feedback positivo; persistência após recarregar; sessão atualizou cabeçalho. Valores originais restaurados e reconferidos após recarga.
- **Negativos testados:** POST com token CSRF inválido recusado HTTP 403; nome curto/telefone inválido apresentou feedback de validação; dados da conta inalterados. Em 320, 390, 768 e 1366 px não houve rolagem horizontal.
- **Limites:** ainda falta teste cruzado completo A/B, ensaio de falha do banco, regressão com outros dispositivos e validação humana. perfil.php, endereço, CNPJ e horários permanecem fora do escopo. Ver [QA-04](QA04_CONFIG_ESTABELECIMENTO.md).


---

**Atualizado em:** 18/11/2026 (login automático A/B DeskBrowse validado; QA-04 publicada; isolamento cruzado de dados ainda parcialmente auditado)
**Repositório:** `Emds96/AgenDay-PI`  
**Referência de desenvolvimento:** `dev-feira-uninorte`  
**Commit publicado comprovado:** 5f2c8666df9d3e63828509b4dd923f58020aac28 (QA-04, PR #26) via [GitHub Actions #44367305044](https://github.com/Emds96/AgenDay-PI/actions/runs/44367305044) em 18/11/2026, com flags false/disabled verificadas.
**Fonte da evidência funcional:** QA autenticada de 16/11 relatada pelo responsável, login Google e QA-03 testados manualmente em 17/11, e QA-04 testada diretamente via DeskBrowse em 18/11 com uma conta fictícia (sem confundir com aprovação humana).

## Última validação confirmada pelo responsável — QA-03 (17/11/2026)

- **Recuperação de senha:** concluída e confirmada em teste real: link recebido (na primeira tentativa, no spam), definição de senha nova, login válido com a nova e rejeição da antiga; relato manual do responsável.
- **Interface:** páginas `esqueci-senha.php` e `redefinir-senha.php` com identidade do login e aviso sobre atraso/spam publicadas no PR #21, deploy #44207273591; QA visual do navegador em 390px e POST com e-mail fictício aprovados.
- **Remetente profissionalizado:** `AgenDay Sistema` verificado na Brevo; [PR #23](https://github.com/Emds96/AgenDay-PI/pull/23) publicado no deploy [#44212874922](https://github.com/Emds96/AgenDay-PI/actions/runs/44212874922); sandbox HTTP 201 pela hospedagem. **Captura real do responsável:** Caixa de entrada às 21h40, `De: agendaysistema@12832884.brevosend.com`, `Responder para: agendaysistema@gmail.com`. **APROVADO no cenário testado**.
- **Restantes não cobertos:** expiração/reutilização de token, limites de tentativa, medição de entrega em múltiplos provedores e auditoria geral de segurança. A `main` não mudou; flags de deploy restabelecidas em `false/disabled`.
- **Regra de continuidade:** toda confirmação de validação pelo responsável deve gerar, **antes da resposta de encerramento**, atualização integrada em `docs/STATUS.md` e nos documentos ativos afetados. Ver `AGENTS.md` e `docs/GUIA_DE_CONTINUIDADE.md`.

---

## Backup PHP efêmero + FTPS (17/11/2026; teste real aprovado)

- [PR #19](https://github.com/Emds96/AgenDay-PI/pull/19) **integrado** em `dev-feira-uninorte` no merge `2885411e726607475ec2d085716dbe20c98db6c0` (17/11/2026). Codigo do backup/documentacao integrados, **sem deploy da aplicação**; o exportador PHP existiu somente durante o backup e foi removido.
- Criados `scripts/backup_agenday.py`, `scripts/backup_php_template.txt`, configuracoes de exemplo sem credenciais, testes e `docs/BACKUP_EFEMERO_POC.md`. O template PHP nao entra no pacote de deploy.
- Script validado: gera endpoint e nonce temporarios, abre URL propria do site com formulario GET/POST no mesmo dominio, exporta MySQL usando `mysqli` e `utf8mb4`, baixa por FTPS e espelha htdocs, valida SQL, testa restauracao apenas em MySQL local, gera ZIP/relatorio privado. Ha fallback `--manual-sql`.
- **Testes:** 10 testes Python aprovados, lint Python, [CI pós-merge #44185531738](https://github.com/Emds96/AgenDay-PI/actions/runs/44185531738) `validar=success` e [PHP lint #44185531595](https://github.com/Emds96/AgenDay-PI/actions/runs/44185531595) `success` com PHP-lint e pacote público; deploy `skipped`. Após isso, exportação e restauração reais no PC passaram.
- **Execução real VALIDADA:** usuário configurou FTPS diretamente no PC via Pairdesk (sem compartilhar credenciais). MariaDB 11.8.9 portátil configurado apenas em `127.0.0.1`. Teste HTTP 403 em pasta com `.htaccess` passou; PHP HTTP 200 passou. Backup `2026-11-17_174312_82e123c1`: `82` arquivos (incluindo `conexao.php`, configuração privada Google e uploads), SQL de `9` tabelas / `51` linhas, restauração local com contagens aprovada, ZIP de `83` entradas, SHA-256 SQL/ZIP conferidos, sem temporários no servidor. O primeiro teste do exportador teve timeout; código foi ajustado com diagnóstico por fase, e o segundo concluiu. **Segunda execução integral**, iniciada pelo launcher Windows com MariaDB inicialmente desligado, também teve `BACKUP OK`: `2026-11-17_175147_81686ab8`, 82 arquivos, 9 tabelas, 83 entradas ZIP, SHA-256 coincidente e banco local encerrado após execução.
- O provedor recomenda FTP + phpMyAdmin; a POC PHP e uma alternativa experimental com risco de timeout/tamanho. Nao assumir backup valido antes de verificacao e restauracao. Nao guardar dumps nem segredos em repositorio ou artifacts.
- Procedimento oficial atualizado em `docs/BACKUP_E_RECUPERACAO.md` com modo automático validado, atalho **Backup AgenDay** na área de trabalho e fallback manual. A QA-03 foi publicada após backups validados, e a **entrega real e a redefinição de senha foram confirmadas manualmente pelo responsável** (ver seção QA-03).

---

## QA-03 — Recuperação de senha com Brevo (17/11/2026; PR #18 integrado)

- [PR #18](https://github.com/Emds96/AgenDay-PI/pull/18) **integrado** em `dev-feira-uninorte` no commit `4ebaffea224e2a825723af3de1b513c52b978f60`, autorizado pelo responsável em 17/11/2026. A `main` permanece preservada.
- Código integrado: botão Esqueceu a senha, telas `esqueci-senha.php`/`redefinir-senha.php`, token de 30 minutos com SHA-256 e uso único, envio HTTPS Brevo e migração incremental `sql/QA03_recuperacao_senha.sql`.
- **Banco de produção:** antes da migração foi criado novo backup completo `2026-11-17_190728_fe52b711` com `BACKUP OK` e restauração local. A migração incremental `sql/QA03_recuperacao_senha.sql` foi aplicada por executor PHP temporário de uso único; retorno `QA03_MIGRATION_OK`, seis colunas e chave estrangeira validadas, temporários remotos removidos.
- **Publicação:** commit `43676874d7a3abeba3de72ec00da62af2cd8bc68` enviado pelo workflow [#44197680873](https://github.com/Emds96/AgenDay-PI/actions/runs/44197680873); validação, diagnóstico FTPS e publicação `success`. Depois, `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`.
- **Site real:** `esqueci-senha.php` HTTP 200, `redefinir-senha.php` rejeita token inválido, configuração Brevo privada HTTP 403. Formulário com e-mail fictício inexistente exibiu mensagem genérica esperada, sem disparo real. Arquivo de configuração Brevo foi confirmado por FTPS sem leitura de seu conteúdo.
- **Brevo:** Secret `AGENDAY_BREVO_API_KEY` presente. Sandbox do endpoint `/v3/smtp/email` retornou HTTP 401 antes da autorização de IP. Painel mostrava 1 IP bloqueado e 0 autorizados. Após confirmação pelo responsável, painel mostrava **1 IP autorizado e 0 bloqueados**, mantendo a restrição por IP ativada; o teste sandbox do endpoint de envio passou com **HTTP 201**, sem entrega de mensagem. Temporários do teste foram removidos.
- **Novo remetente AgenDay Sistema publicado:** o responsável criou `agendaysistema@gmail.com` e verificou o remetente com nome **AgenDay Sistema** no painel Brevo (o remetente anterior foi mantido no painel). [PR #23](https://github.com/Emds96/AgenDay-PI/pull/23) integrado na `dev-feira-uninorte`; commit `5749f333338e599c67c9e6050e39caa684091bac` publicado no [workflow #44212874922](https://github.com/Emds96/AgenDay-PI/actions/runs/44212874922) (validação, FTPS, publicação success). Backup pré-deploy `2026-11-17_205855_4f8c886e` concluiu `BACKUP OK` com restauração local. FTPS confirmou `lib/brevo-email.php` com remetente/nome novos e sem o e-mail antigo. O endpoint Brevo `/v3/smtp/email` aceitou **sandbox HTTP 201** com o novo remetente, sem envio real; temporários removidos. Login e recuperação HTTP 200, config Brevo privada HTTP 403. Variáveis restauradas: `AGENDAY_DEPLOY_ENABLED=false`, `AGENDAY_DEPLOY_MODE=disabled`. **Validação real recebida após o deploy:** o responsável apresentou captura do Gmail com mensagem entregue à **Caixa de entrada** (17/11/2026, 21h40), nome **AgenDay Sistema**, campo **De:** `agendaysistema@12832884.brevosend.com` e **Responder para:** `agendaysistema@gmail.com`. Isso confirma o endereço mostrado no caso testado; taxa de entregabilidade em outros provedores não foi medida. A chave API não foi rotacionada/alterada nesta tarefa.
- **Teste real relatado pelo responsável (17/11/2026):** o e-mail de recuperação chegou com atraso na pasta spam de sua conta; o link permitiu criar uma nova senha, que foi aceita no login, e a senha antiga foi rejeitada. É relato de validação manual, não repetição automatizada. Permanecem pendentes os testes de expiração/reutilização do token, limites de solicitações e regressão do login Google.
- **Melhoria visual QA-03 publicada e verificada:** [PR #21](https://github.com/Emds96/AgenDay-PI/pull/21) no commit `393b281e` publicado no [run #44207273591](https://github.com/Emds96/AgenDay-PI/actions/runs/44207273591), validação/FTPS/deploy `success`. Backup pré-publicação `2026-11-17_201733_0559724e` com `BACKUP OK` e restauração local aprovada. As telas `esqueci-senha.php` e `redefinir-senha.php` respondem HTTP 200 com novo CSS, visual alinhado ao login e alerta de spam visível. Teste móvel de 390px: uma coluna, sem rolagem horizontal; POST com e-mail fictício retorna mensagem genérica com aviso de atraso/spam, sem erro. Token inválido rejeitado e config Brevo privada continua HTTP 403. Flags novamente `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. Nenhuma senha de usuário foi modificada neste deploy.
- **Segurança:** rotação da chave previamente transitada por Google Keep/Drive continua recomendada; a API não revela se a chave publicada já foi rotacionada. Não registrar a chave no Git ou no chat.
- Procedimento de ativação em `docs/QA03_RECUPERACAO_SENHA_BREVO.md`.

---
## Login com Google — publicação direta autorizada e hotfix (17/11/2026)

**Autorização:** responsável pediu publicação direta para teste no site oficial, sem criar ambiente de testes separado. **Alvo:** somente `agenday-sistema.infinityfree.io`, branch `dev-feira-uninorte`, sem mexer na `main` ou na conta do repositório original.

**Código e banco:**
- [PR #12](https://github.com/Emds96/AgenDay-PI/pull/12) integrado à dev no commit `ce8358018ff06e24786064d489741f312df04c68`: botões Google via GIS, token verificado no PHP, cadastro inicial com pop-up de visual AgenDay, senha opcional, aviso de que não é senha Google, sessões e vinculação segura a conta existente após login por senha; testes de ID token no workflow. **Os fluxos principais de cadastro/login Google foram validados manualmente em produção em 17/11/2026, após o hotfix**, conforme registro abaixo.
- Google Auth Platform: projeto `agenday-515003`, público Externo, status **Testando**, **1 conta de teste** autorizada. O client ID é identificador público, não Client Secret.
- No phpMyAdmin do banco independente, após exportar backup SQL privado, aplicado com retorno sem erro: `CREATE TABLE IF NOT EXISTS estabelecimento_google` (sub único, FK estabelecimento), e `ALTER TABLE estabelecimento MODIFY senha_estabelecimento VARCHAR(255) NULL DEFAULT NULL`. **Não foram executados DELETE, DROP ou UPDATE em registros existentes.**
- Backup SQL de 17/11, pasta privada `Documents/AgenDay-Backups/2026-11-17-pre-google/`, verificado por leitura ZIP, 8 definições de tabelas e SHA-256. **Backup completo de arquivos mais recente confirmado permanece de 16/11**, de antes desta publicação. Não afirmar backup integral da mesma janela temporal nem restauração testada.
- Criado `htdocs/google-oauth-config.php` por File Manager. O upload original do PR #12 foi concluído no [run #44065895502](https://github.com/Emds96/AgenDay-PI/actions/runs/44065895502), mas a inspeção do navegador encontrou **HTTP 500 em `index.php`**. Suspeita principal: carregamento direto do arquivo privado de configuração, **causa exata não confirmada por log PHP**.
- [PR #13](https://github.com/Emds96/AgenDay-PI/pull/13) corrige carregamento: centraliza no helper, captura exceções do arquivo privado e fornece fallback com **OAuth Client ID público**, sem expor Client Secret; merge `b06ae1f1019cee78ebdc90285ea808f6c6062730`. [Deploy hotfix #44067516631](https://github.com/Emds96/AgenDay-PI/actions/runs/44067516631): `validar=success`, `diagnosticar_ftp=success`, `publicar=success`. Após terminar, flags conferidas: `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`.
- **Verificação inicial pós-hotfix via ZERO Browser MCP**: `https://agenday-sistema.infinityfree.io/index.php` retornou página `Login` em vez de HTTP 500, com login e cadastro tradicionais presentes, script oficial GIS, `g_id_onload` e **2 contêineres de botões Google**. **Essa inspeção isolada não executou cadastro nem autenticação Google**; os testes manuais posteriores do responsável estão registrados a seguir. A inspeção não comprovou separadamente a compatibilidade cURL com os certificados Google.

### Validação manual dos fluxos principais do login Google — 17/11/2026

**Evidência:** testes executados no site publicado e informados diretamente pelo responsável. Não houve repetição desses cenários por automação nesta etapa. Detalhes e pendências em [`docs/LOGIN_GOOGLE_CHECKLIST.md`](LOGIN_GOOGLE_CHECKLIST.md).

- [x] Primeiro cadastro via Google, com pop-up de complemento e nome do estabelecimento, escolhendo **Agora não** para não cadastrar senha local.
- [x] Logout e novo login pelo botão Google na conta criada sem senha do AgenDay.
- [x] Cadastro de outra conta via Google, desta vez com **senha própria do AgenDay**.
- [x] Logout e login tradicional usando **e-mail e senha do AgenDay** nessa segunda conta.
- [x] Logout e login via botão **Entrar com Google** nessa segunda conta que também possui senha local.
- [x] Alternância no seletor de contas Google, retornando com sucesso à primeira conta (sem senha local).

**Resultado:** **APROVADO — fluxos principais de cadastro e login Google em produção**, conforme teste manual do responsável. Isso não equivale à aprovação de todos os casos de segurança ou de isolamento entre estabelecimentos.

**Situação atual:** **login Google aprovado nos fluxos principais testados manualmente em produção**. Pendências independentes: vincular conta tradicional já existente, avaliar visual/responsividade do pop-up, casos negativos de autenticação e regressão ampla do sistema. **O isolamento de dados entre estabelecimentos não invalida a validação do login Google**: sua revisão estática e verificações de consistência foram favoráveis, mas o teste cruzado autenticado A/B permanece pendente. Não presumir Google Cloud em `Produção` sem confirmação; não executar outro deploy sem autorização.

## QA-02 — alteração de senha ([PR #10](https://github.com/Emds96/AgenDay-PI/pull/10) integrado e publicado, 16/11/2026)

**Problema confirmado no código:** `alterar-senha.php` mostrava formulário legado de cliente (nome, contato, e-mail, foto), sem ação para trocar a senha.

**Implementação em branch de tarefa** `fix/qa02-alteracao-senha-20261116`, derivada da `dev-feira-uninorte` no SHA `e2f5d61e5289261b8b74dc31ebbdf1f7aa82cce3`:
- `alterar-senha.php`: formulário autenticado com senha atual, nova senha e confirmação; `POST` com CSRF, tratamento seguro de falhas e sucesso, `303` após envio válido, mensagem acessível. Consulta o hash somente da conta autenticada, usa `password_verify`, grava `password_hash(PASSWORD_DEFAULT)` com `UPDATE` preparado e verificação do hash anterior para evitar sobrescrita simultânea; renova o ID de sessão e token CSRF após sucesso.
- `lib/alteracao-senha.php`: valida entrada e aplica o limite de 8–72 bytes compatível com o cadastro, rejeita repetição e divergência.
- `css/alterar-senha.css`: remove altura fixa para acomodar campos/mensagens em celular.
- `tests/alteracao-senha-validacao.php`: testes sem banco dos dados válidos/inválidos, limite multibyte, confirmação e comportamento dos hashes.

**Testes efetivamente executados:** PHP CLI 8.4.24 em ambiente isolado: `php -l` nos arquivos de validação/teste e **16 verificações aprovadas**; hashes dos dois arquivos comparados aos blobs do GitHub. Workflow existente de **lint PHP** disparado somente na branch de tarefa: [run 43988658296](https://github.com/Emds96/AgenDay-PI/actions/runs/43988658296), **success**, sem publicação ou alteração do workflow.

**Escopo e limitações:** a implementação da QA-02 renova o ID da sessão **atual**, mas **não revoga outras sessões** já autenticadas; essa ampliação exige decisão arquitetural separada. Sem migração SQL. **PR #10 integrado no commit `e3174bc713b2b4c286066b9b4c8abb622bc3b766` e publicado com sucesso.** A etapa inicial de automação não conseguiu enviar o formulário por bloqueio da ferramenta; **posteriormente, o responsável confirmou manualmente o fluxo principal da troca de senha em produção**, conforme registro abaixo. Não houve comparação direta do hash anterior/posterior no banco nem teste de revogação de outras sessões. QA-03 (esqueceu a senha) permanece separada, ainda sem implementação ou decisão de escopo.



### Publicação e QA parcial da alteração de senha — 16/11/2026

- **Backup fresco pré-deploy:** obtidos pelo ZERO Browser MCP do InfinityFree, em `Documents/AgenDay-Backups/2026-11-16-pre-QA02-deploy` do computador autorizado. Arquivo `htdocs-pre-QA02-2026-11-16.zip`: **75 arquivos**, inclui `conexao.php`, `index.php`, uploads e **dois `.htaccess`**; CRC válido. Exportação `agenday-db-pre-QA02-2026-11-16.sql.zip`: estrutura de **8 tabelas**, dados de estabelecimento presentes, CRC válido. SHA-256 dos dois arquivos registrados no relatório privado `STATUS_BACKUP_VALIDADO_PRE_QA02.txt`. **Restauração não testada.** Nenhum backup, senha ou conexão privada enviado ao repositório.
- **Deploy:** responsável autorizou o SHA exato `e3174bc713b2b4c286066b9b4c8abb622bc3b766` de `dev-feira-uninorte`, publicado somente pelo workflow `.github/workflows/agenday-publicar.yml` no ambiente protegido `producao-agenday`. [Run 43991172102](https://github.com/Emds96/AgenDay-PI/actions/runs/43991172102): **`validar=success`, `diagnosticar_ftp=success`, `publicar=success`**. Duas Variables de gate restauradas e conferidas: `AGENDAY_DEPLOY_ENABLED=false`, `AGENDAY_DEPLOY_MODE=disabled`. Sem alteração de banco/SQL, Secrets, workflow, `main` ou repositório original.
- **QA com ZERO Browser MCP em conta fictícia anteriormente autenticada:** `/alterar-senha.php` acessível; exibe senha atual, nova senha, confirmação, token CSRF oculto e botão de envio. Formulário visível sem overflow horizontal em **320, 390, 768 e 1366 px**. Nenhum dado existente foi alterado por essa inspeção.
- **Nova conta exclusiva para QA-02, 16/11/2026:** estabelecimento fictício **`QA02 Conta de Teste`**, e-mail **`qa.senha.20261116.dbf094aee1111e@example.com`**. Cadastro no InfinityFree e login em `agenda.php` **comprovados pelo ZERO Browser MCP**. A senha inicial foi gerada e salva com criptografia **Windows DPAPI**, sem divulgação no chat ou no repositório, no computador autorizado em `%USERPROFILE%\Documents\AgenDay-QA\qa02-current.clixml`. O script local `%USERPROFILE%\Documents\AgenDay-QA\gerenciar-credencial-qa02.ps1` permite copiar a senha vigente à área de transferência somente naquele usuário/dispositivo; após uso, limpar a área de transferência. Uma segunda credencial criptografada em `qa02-next.clixml` foi **preparada para troca futura, mas ainda NÃO é a senha ativa**. Metadados sem segredo em `qa02-info.json`. A senha inicial é a única comprovada no login.
- **Revalidação de acesso da conta QA-02 (continuação em 16/11/2026):** a sessão autenticada foi confirmada pelo ZERO Browser MCP; em seguida foi realizado logout e **novo login com a senha inicial guardada no cofre DPAPI**, com resultado **PASS** e identificação visual de `QA02 Conta de Teste`. A senha inicial continua comprovadamente válida. A tentativa de concluir o formulário de alteração permaneceu bloqueada pela ferramenta de segurança; **não houve confirmação de troca nem envio de nova senha**. A credencial inicial continua ativa, a segunda permanece reservada/inativa, e a área de transferência foi limpa. **Naquele momento, a QA-02 E2E ainda dependia de teste manual; o responsável aprovou posteriormente o fluxo principal em outra conta, conforme registro abaixo.**
- **Limite da tentativa automatizada:** a ferramenta de segurança impediu o envio do formulário; **nenhuma alteração de senha foi confirmada ou aplicada nessa tentativa**. O processo temporário foi interrompido e a área de transferência foi sobrescrita. Não foram testados nessa etapa: rejeição da senha atual incorreta, confirmação divergente e gravação real do hash. A conta acima pode ser reutilizada em outra sessão via acesso autorizado ao cofre criptografado do mesmo computador, sem expor o segredo em relatórios. **O teste manual posterior, feito em outra conta pelo responsável, comprovou a rejeição da senha antiga e a aceitação da nova.**
- **Conclusão da etapa automatizada:** QA-02 teve **código integrado, CI aprovado, publicação realizada, interface validada e cadastro/login de uma conta exclusiva de QA comprovados**. A tentativa de troca automatizada foi bloqueada, mas o **teste manual posterior do responsável aprovou o fluxo principal**; a conta automatizada e suas duas credenciais locais continuam separadas desse teste manual.

### Validação manual da QA-02 e conferência de banco — 16/11/2026

- **Responsável testou em produção, com conta nova criada por ele:** efetuou cadastro, alterou a senha informando a senha antiga, tentou entrar com a **senha antiga e recebeu rejeição**, e entrou com a **nova senha com sucesso**. Trata-se de **relato de teste manual do responsável**, não de automação do ZERO Browser. **Resultado: PASS para o cenário principal de troca e reautenticação.**
- **Conferência independente de leitura no phpMyAdmin:** banco `if0_43415733_agenday` acessível, com as **8 tabelas** esperadas, **16 estabelecimentos** e **16 registros de senha apresentados em formato de hash** na inspeção realizada. Esses resultados são compatíveis com a inclusão de novo cadastro, mas **não identificam qual cadastro o responsável utilizou nem provam comparação criptográfica do hash antes/depois**. Nenhum registro foi alterado durante a inspeção.
- **Conclusão da QA-02:** **APROVADA NO FLUXO PRINCIPAL EM PRODUÇÃO**, com base na confirmação manual do responsável e no conjunto de evidências de CI, publicação, interface e banco. Permanecem fora dessa aprovação: validações negativas de formulário (senha atual incorreta, divergência e CSRF), revogação de sessões simultâneas e restauração de backup; não marcar esses cenários como executados.
- **Continuidade:** manter a conta exclusiva `QA02 Conta de Teste` e sua **senha inicial** guardada no cofre criptografado local, sem substituí-la pela senha preparada em `qa02-next.clixml` (a automação não a utilizou). **Não iniciar QA-03**: o responsável pediu **discutir primeiro os requisitos e a abordagem** antes de qualquer implementação.

## Publicação FTPS autorizada e validação pós-deploy — 16/11/2026

- **Alvo e autorização:** responsável autorizou publicar exclusivamente o commit `7980db9c3c371f66fb86881cae4bba86cafd3317` da `dev-feira-uninorte`, no site independente `https://agenday-sistema.infinityfree.io/`, via workflow existente `.github/workflows/agenday-publicar.yml`, ambiente `producao-agenday`. Não houve promoção à `main`, alteração no repositório original, banco, Secrets ou scripts do publicador.
- **Backup pré-publicação:** `htdocs-2026-11-16.zip` e `agenday-db-2026-11-16.sql.zip` existentes em armazenamento privado local, com CRC ZIP válido e hashes SHA-256 conferidos contra o relatório local. Backup de arquivos com 73 entradas de arquivos, incluindo conexão privada e uploads; SQL com estrutura de 8 tabelas. **Restauração não testada.** Nenhuma cópia/credencial/SQL anexada ao GitHub.
- **Evidência do deploy:** [GitHub Actions #43976344193](https://github.com/Emds96/AgenDay-PI/actions/runs/43976344193), evento `workflow_dispatch`, SHA correto; jobs **`validar=success`, `diagnosticar_ftp=success`, `publicar=success`**. Log do publicador: **"Envio concluído: 70 arquivos; nenhum arquivo remoto apagado."** O passo de verificar `arquivos/clientes/.htaccess` e `arquivos/funcionarios/.htaccess` após baixar o artifact **passou**. Foram publicados arquivos permitidos; o envio não apaga arquivos remotos nem reimporta SQL.
- **Incidente sem upload:** na primeira tentativa de aprovar o environment, um comando local tentou converter o ID grande do ambiente para inteiro de 32 bits e falhou. O procedimento **restaurou `false/disabled`** antes de qualquer envio. Usando inteiro de 64 bits, aprovou-se **a mesma execução #43976344193**, concluída com sucesso. Não foram criados outros workflows nem alterado o YAML por esse ajuste local.
- **Travas após publicação:** consulta independente por `gh variable list` confirmou **`AGENDAY_DEPLOY_ENABLED=false`** e **`AGENDAY_DEPLOY_MODE=disabled`**.
- **Verificação no site:** o `/css/responsivo.css` público respondeu HTTP 200 (`text/css`) e contém a regra `.agenday-app #offcanvasDarkNavbar.offcanvas.show:not(.hiding)` e `transform: none !important` (correção QA-01 publicada). O navegador TinyFish em leitura confirmou tela de login/cadastro pública e redirecionamento de `/agenda.php` para login sem autenticação.
- **Conta de testes para continuidade:** estabelecimento fictício **`QA Menu AgenDay 1611`**, e-mail **`qa.menu.7980.14f23f102577e4e7@example.com`** (domínio reservado para exemplos). Cadastro e login concluídos no site publicado via ZERO Browser MCP em 16/11/2026; acesso autenticado a `agenda.php` confirmado. **A senha não foi registrada ou armazenada na documentação nem está disponível para retomada.** O e-mail identifica a conta, mas, para novo login, será necessária uma senha válida fornecida diretamente no navegador ou redefinição autorizada. Não registrar senhas/segredos no GitHub ou no chat.
- **QA-01 autenticado após deploy:** ZERO Browser MCP verificou abertura e fechamento (botão X, tecla Esc e backdrop) do menu em **320, 390, 768 e 1366 px** com `prefers-reduced-motion: reduce`; links de **Clientes, Serviços e Funcionários** navegaram corretamente. Com a animação padrão, a automação manteve a transição CSS em `0 ms`, dando um falso sinal de menu escondido. Ao concluir a transição programaticamente, o painel ficou visível em 390/1366 px. **A fluidez da animação normal no navegador do usuário ainda não foi validada.** Algumas abas usaram CSS em cache; com URL CSS atualizada, a regra do PR #4 foi carregada corretamente.
- **Limites remanescentes:** QA-01 validado funcionalmente sem animação e por finalização da transição, mas ainda pendente de conferência humana da animação padrão em celular/desktop. Os dois `.htaccess` constam do artifact e o envio terminou com sucesso, porém o bloqueio real de execução PHP nos uploads não foi testado diretamente. Os demais fluxos PHP/MySQL integrados não foram retestados ponta a ponta após o deploy; restauração do backup não testada.
- **Próxima ação:** conferir manualmente a animação normal do menu no celular, validar atualização de cache se necessário e prosseguir com QA-02 em tarefa/PR separado. Preservar `false/disabled` até outra autorização específica.

## Verificação de backups e correção do artifact — 16/11/2026 (PR #8)

**Verificação direta nesta sessão, somente leitura:** backups privados em `Documents/AgenDay-Backups/2026-11-16` com `htdocs-2026-11-16.zip` (73 arquivos, incluindo configuração privada e uploads) e `agenday-db-2026-11-16.sql.zip` (8 tabelas com estrutura, dados em 6), ambos com CRC ZIP válido e SHA-256 correspondente ao relatório local. **Não foi feito teste de restauração.** Os arquivos e seus hashes permanecem privados, fora do repositório.

**Pendência identificada:** nenhum `.htaccess` aparece no ZIP obtido da hospedagem; isso **não prova ausência no servidor**, pois o método de download pode omitir arquivos ocultos. O artifact da [CI 43965721301](https://github.com/Emds96/AgenDay-PI/actions/runs/43965721301) também foi inspecionado diretamente: continha 68 arquivos e **nenhum dos dois** `arquivos/clientes/.htaccess` e `arquivos/funcionarios/.htaccess`. Os dois arquivos estão no GitHub e o empacotador os inclui; a etapa `actions/upload-artifact@v4` omitira arquivos ocultos.

**Correção proposta:** [PR #8](https://github.com/Emds96/AgenDay-PI/pull/8) acrescenta `include-hidden-files: true` ao upload do artifact e verificação dos dois `.htaccess` após download, antes de qualquer conexão FTPS. Revisão estática confirma a alteração restrita ao workflow, sem mudanças em código PHP/MySQL, Secrets ou publicador. **Essa descrição era o estado durante a preparação do PR #8; o merge, a execução FTPS e a validação posteriores estão registrados na seção de publicação acima. A proteção efetiva dos diretórios no servidor ainda precisa de regressão específica.**

## Auditoria transversal de documentação e continuidade — 16/11/2026

**Motivo:** a documentação anterior explicava o workflow, mas omitia a sequência concreta de trabalho entre sessões e o **modo de obtenção dos backups**. A revisão abrangente analisou todos os `.md` existentes (`AGENTS.md`, `README.md`, 13 documentos históricos de microtarefas e `docs/STATUS.md`/`docs/PUBLICACAO_AUTOMATICA.md`), os workflows e scripts operacionais. Mantiveram-se os documentos históricos; o estado mais recente continua neste STATUS. Esta auditoria **não executou deploy, QA de escrita, backup, SQL ou mudança de infraestrutura**.

**Documentos operacionais complementares criados:**
- [`INDICE_DA_DOCUMENTACAO.md`](INDICE_DA_DOCUMENTACAO.md): ordem de leitura e prioridade de evidências.
- [`GUIA_DE_CONTINUIDADE.md`](GUIA_DE_CONTINUIDADE.md): como trabalhávamos com plugin GitHub, Desktop Commander, `gh`, branches, PRs, CI, ZERO Browser/TinyFish, autorizações e troca de sessão.
- [`BACKUP_E_RECUPERACAO.md`](BACKUP_E_RECUPERACAO.md): **como obter** backup integral de `htdocs` e exportação do MySQL pelo phpMyAdmin; verificar cobertura, integridade, confidencialidade e recuperação planejada.
- [`TESTES_E_QUALIDADE.md`](TESTES_E_QUALIDADE.md): níveis de evidência, fluxo de QA, cenários, defeitos QA-01–QA-08 e controles de acesso.
- [`ARQUITETURA_E_DECISOES.md`](ARQUITETURA_E_DECISOES.md): módulos e limitações, interfaces de estabelecimento/cliente, infraestrutura própria e decisões que ainda aguardam alinhamento com Gabriel.

**Backup — verificação direta somente leitura no computador autorizado:** em `%USERPROFILE%\Documents\AgenDay-Backups\2026-11-16` havia apenas `STATUS_BACKUP_PENDENTE.txt`, registrado pela outra sessão; **não havia cópias atuais do `htdocs` ou SQL**. Em `...\2026-11-13` havia `htdocs.zip` (apenas 3 entradas, conforme status registrado localmente) e um ZIP SQL anterior ao QA de 16/11. **Integridade ZIP anterior não significa cópia atual/completa.** O provedor InfinityFree orienta download por FTP e exportação por phpMyAdmin, e não permite conexão MySQL externa no plano gratuito. Nenhuma credencial foi inspecionada; detalhes e fontes oficiais no runbook.

**Estado GitHub revisitado:** `dev-feira-uninorte` em `040ba53be17bc655438f3ee1a30765b950250ca1` na abertura desta auditoria (PR #6 integrado). Variáveis `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`; [CI 43960024149](https://github.com/Emds96/AgenDay-PI/actions/runs/43960024149) `validar=success` e `publicar=skipped`. Esses números são **fotografia de 16/11**, não garantia para a execução futura. **Reconsultar SHA e runs após integrar este PR documental.**

**Bloqueios antes do próximo deploy:** (1) backups atuais de arquivos e MySQL ainda não verificados; (2) autorização específica do responsável para SHA e execução; (3) confirmar HEAD, Secrets/Variables e status das outras sessões; (4) QA-01 corrigido no GitHub, mas ainda não validado no site. O workflow existente **não precisa de alteração** para resolver esses bloqueios.

**Regra de continuidade aprovada:** **documentar primeiro → autorizar → executar → validar → registrar**. Novas sessões devem ler o índice/guia antes de recorrer a métodos alternativos.

## Objetivo e prazos

Preparar o AgenDay, sistema web PHP/MySQL de gerenciamento de agendamentos, para apresentação na feira tecnológica da UniNorte.

- **24/11/2026:** prazo para ter o projeto pronto, conforme relato do responsável sobre a orientação do professor; **ainda não confirmado por um documento oficial**. Adotar como **prazo operacional de conclusão** para planejamento.
- **27/11/2026:** data da feira informada no `README.md`; **aguarda confirmação independente**. Planejar demonstração e ajustes finais sem confundir com o prazo de conclusão.
- Antes de mudar esses registros, confirmar com o responsável e atualizar este arquivo e o `README.md`.

## Visão rápida (reconciliada após a QA-03 de 17/11/2026)

| Frente | Estado com evidência | Validação restante |
| --- | --- | --- |
| Banco e backup | Banco v2 com migrações Google + QA-03; exportação FTPS e restauração MariaDB local aprovadas | Recuperação completa do site em outra hospedagem ainda não ensaiada |
| Cadastro/login/logout | Fluxos tradicionais e login Google aprovados nos cenários manuais reportados | Vinculação de conta tradicional ao Google, regressão e negativos adicionais |
| Alteração de senha (QA-02) | Fluxo principal em produção **aprovado manualmente** após PR #10 | CSRF/negativos e revogação de outras sessões não validados integralmente |
| Recuperação de senha (QA-03) | **Aprovada manualmente:** envio real, redefinição e login; visual com aviso spam, remetente AgenDay Sistema e endereço de origem confirmados no Gmail | Expiração/reutilização e limites de tentativas, além de entregabilidade em outros provedores |
| Clientes, serviços e funcionários | Cadastros, pesquisa, vínculo e persistência aprovados no QA reportado de 16/11 | Regressão e autorização aprofundada |
| Agenda/calendário | Criação, bloqueio de sobreposição, cancelamento e reutilização aprovados no QA reportado | Concorrência simultânea e autorização cruzada aprofundada |
| Navegação e visual | Correção de menu integrada e publicada em conjunto com outros commits; tela de recuperação responsiva validada em 390px | Revalidar menu e modais no navegador real em vários tamanhos |
| Perfil/configurações | Configuração básica da empresa publicada e testada em QA01; perfil e configurações avançadas demonstrativos | Implementar perfil, endereço, CNPJ e horários em etapas separadas |
| Comandas, histórico e financeiro | Páginas legadas sem operações de negócio integradas comprovadas | Definir escopo da feira ou ocultar opções não funcionais |
| Publicação atual | SHA de aplicação `5f2c8666` publicado no run #44367305044, com backup validado e flags `false/disabled` | Próximas alterações de aplicação exigem outro deploy autorizado, com backup |

> **Atenção:** “implementado no código” não significa “validado em produção”. As páginas podem existir e estar acessíveis sem todas as ações funcionarem.

## Conferência técnica e operacional do deploy — 16/11/2026

**Realizada nesta revisão por leitura do GitHub e consultas somente leitura via GitHub CLI no Remote Desktop Commander.** Fonte operacional e comandos: [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md). Não houve alteração de workflow, variáveis, secrets ou site; **nenhuma publicação foi iniciada**.

| Item verificado | Resultado |
| --- | --- |
| Branch de trabalho | `dev-feira-uninorte` em `ac53c8ea9b8be206c032d4072bf07213a9769698` antes deste PR |
| Histórico recente | PRs [#3](https://github.com/Emds96/AgenDay-PI/pull/3), [#4](https://github.com/Emds96/AgenDay-PI/pull/4) e [#5](https://github.com/Emds96/AgenDay-PI/pull/5) integrados; **#4 altera CSS e teste do menu, #5 documenta QA** |
| CI do último commit consultado | [run 43953454744](https://github.com/Emds96/AgenDay-PI/actions/runs/43953454744): `validar=success`, `publicar=skipped`, `diagnosticar_ftp=skipped`. `php-lint` também `success` |
| Última publicação comprovada | [run 43518952935](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935), commit `f807327811895bc97827929f4e30c2e60a180110`, 13/11; versão posterior **não comprovada** |
| Travas consultadas | `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`, variáveis de repositório |
| Secrets conferidos | Nomes `AGENDAY_FTP_HOST`, `AGENDAY_FTP_USERNAME` e `AGENDAY_FTP_PASSWORD` presentes no ambiente; **valores não consultados, validade não testada** |
| Environment | `producao-agenday` requer revisor `Emds96`, aceita `dev-feira-uninorte`; confirmação da política necessária em futuras execuções |
| Ferramenta operacional recuperada | Desktop Commander online; `gh` 2.104.0 autenticado como `Emds96`; não é preciso usar browser para controlar workflow enquanto a conexão persistir |
| Backup atual | **Verificado em leitura nesta auditoria: pasta 16/11 apenas com status pendente; ZIPs 13/11 antigos/incompletos.** Exigir cópias atuais de arquivos e MySQL conforme guia de backup. |
| QA-01 | Corrigido em código no PR #4; **não confirmado no site**. Regressão visual necessária depois de publicar |
| QA-02–QA-08 | Pendentes conforme registro de defeitos abaixo; não alterados neste PR |

**Atenção:** a tentativa via interface gráfica que exigia confirmação de identidade por e-mail foi um bloqueio daquele caminho, **não demonstra que `gh` esteja indisponível**. A consulta autenticada por CLI funcionou nesta revisão. Se as configurações exigirem outra verificação no momento do deploy, relatar sem pedir códigos no chat.

**Próximo passo aprovado no planejamento:** documentação primeiro; em **outra sessão**, inspecionar esta fonte, autorizar explicitamente e executar o workflow **existente**; confirmar a publicação do commit escolhido, restaurar `false/disabled` e repetir testes do menu. Nenhum deploy é autorizado por esta atualização documental.

## Estado da publicação — evidências de 16/11/2026

- Site independente: `https://agenday-sistema.infinityfree.io/`. O endereço respondeu com a página de login pública; arquivos CSS `css/design-system.css` e `css/responsivo.css` também foram acessados publicamente.
- GitHub Actions: o [workflow manual de 13/11/2026, execução 43518952935](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935), concluiu com **sucesso** os jobs **Validar código e pacote**, **Testar FTPS sem publicar arquivos** e **Enviar ao InfinityFree somente com autorização**. Portanto, há **evidência concreta de publicação pelo workflow**, ao contrário da afirmação anterior de que o sistema não havia sido publicado.
- A execução de publicação acima usou o commit `f807327811895bc97827929f4e30c2e60a180110` (13/11/2026). **Não foi confirmado se a hospedagem contém commits posteriores** nem se houve outro upload fora do GitHub Actions.
- O workflow `.github/workflows/agenday-publicar.yml` continua condicionado às variáveis `AGENDAY_DEPLOY_ENABLED=true` e `AGENDAY_DEPLOY_MODE=publish`, além de dependências de ambiente. A execução bem-sucedida demonstra que o job publicou naquele momento, **não prova o estado atual de variáveis/secrets, nem garante que todo push futuro publicará**. A execução de 16/11 relacionada à branch estava pendente na última consulta.
- **Limite da verificação:** foram acessados apenas recursos públicos; não houve login, testes com banco de dados, comparação integral do conteúdo publicado ou alteração de servidor. Não declarar o aplicativo funcional até testar.
- O `README.md` e `docs/PUBLICACAO_AUTOMATICA.md` continham descrições históricas de publicação desativada, agora conciliadas com as evidências. Não editar segredos, acionar publicação, importar SQL ou alterar hospedagem sem autorização e backup.

Referência: [publicação controlada](PUBLICACAO_AUTOMATICA.md).

## Testes e evidências

**Verificações identificadas no repositório:**

- `php tests/agendamento-validacao.php`: validações de entrada, sem banco.
- `php tests/agendamento-cancelamento.php`: regras auxiliares, sem prova de concorrência real.
- `node tests/responsividade-check.js`: estrutura e eventos de interface; não substitui teste visual.
- `.github/workflows/php-lint.yml`: checagem de sintaxe PHP. Uma execução de 13/11/2026 foi registrada como bem-sucedida na consulta ao GitHub Actions.

**Verificação documental anterior:** não executou PHP, Node, navegação autenticada ou operações de banco; leu repositório, CI e recursos públicos. **Desde então, o relatório QA de 16/11 recebido nesta sessão registra testes reais de autenticação e persistência na hospedagem**, detalhados abaixo. O relato é evidência fornecida pelo responsável e não foi reproduzido nesta revisão do documento.

O checklist de integração mais importante está em [AGENDAMENTOS_4_5.md](AGENDAMENTOS_4_5.md), complementado por [RESPONSIVIDADE_5_2.md](RESPONSIVIDADE_5_2.md).


## Auditoria funcional no InfinityFree — 16/11/2026 (ZERO Browser MCP)

**Origem:** relatório de testes encaminhado pelo responsável, gerado em sessão dedicada de QA com ZERO Browser MCP (Microsoft Edge). **Evidência relatada, não reexecutada neste commit de documentação.** Execução em https://agenday-sistema.infinityfree.io/ contra a versão publicada, usando registros fictícios. Branch de referência no momento do relatório: `dev-feira-uninorte`, commit `6596bebc7a9b56b48dc136123266c9946780dd5c`.

### Cobertura e resultados reportados

| Área | Resultado reportado | Observações |
| --- | --- | --- |
| Cadastro/login/logout | **Aprovado** | Duas contas novas; senha divergente, e-mail inválido/duplicado e credenciais incorretas rejeitados; reautenticação bem-sucedida |
| Clientes/serviços/funcionários | **Aprovado** | Cadastros persistiram após recarga; pesquisas, vínculo funcionário–serviço e fotos PNG testados |
| Validações de cadastro | **Aprovado nos casos testados** | Serviço duplicado, duração zero, preço negativo e arquivo inválido rejeitados |
| Agenda e calendário | **Aprovado nos casos testados** | Criou #3 (20/11, 14h–14h45), bloqueou conflito às 14h30, cancelou e preservou histórico; navegou meses |
| Segundo estabelecimento | **Aprovado nas listagens normais** | Conta B não exibiu registros A; #4 e #5 da B foram criados, cancelados e permaneceram como histórico; horário cancelado reutilizado |
| API agenda | **Aprovado nos casos testados** | Respostas HTTP 200 e 422; restrição sem login e filtros de empresa observados |
| Segurança | **Parcial** | Rotas privadas redirecionaram; NÃO houve teste extensivo de autorização entre contas, concorrência simultânea ou ataque |
| Responsividade | **Falhou no menu** | 320/390/768/1366 px: classe `show` aplicada mas painel deslocado à esquerda; demais páginas sem overflow geral |
| Perfil/configuração/senha | **Falhou ou incompleto** | Dados demonstrativos fixos e formulários não funcionais |
| Comandas/histórico/financeiro | **Incompleto** | Páginas disponíveis no menu, sem fluxo de negócio funcional comprovado |
| Correspondência GitHub × site | **Parcial** | Relato informa hashes iguais de `css/responsivo.css`, `css/design-system.css` e `js/login.js`; PHP integral sem commit verificável |

### Registro de defeitos

| ID | Prioridade QA | Descrição | Evidência/reprodução relatada |
| --- | --- | --- | --- |
| **QA-01** | ALTO | Offcanvas do menu aparece como aberto no DOM mas permanece fora da tela | Abrir `agenda.php`, clicar no menu; reproduzido em 320, 390, 768 e 1366 px |
| **QA-02** | ALTO | Alterar senha mostra formulário de cliente/upload, não troca de senha | Abrir `alterar-senha.php` |
| **QA-03** | ALTO | “Esqueceu a senha?” aponta para `#`, sem recuperação | Abrir a tela de login e clicar no link |
| **QA-04** | MÉDIO | Perfil/configuração exibem dados e ações ilustrativos | Abrir `perfil.php` ou `config.php` autenticado |
| **QA-05** | MÉDIO | Comandas, histórico e financeiro não disponibilizam operações reais | Abrir os três módulos pelo menu |
| **QA-06** | MÉDIO | Erro JavaScript em `js/button-add.js:4` na configuração | Abrir `config.php` e inspecionar console |
| **QA-07** | BAIXO | Cabeçalhos legados exibem “Barbearia” independentemente da empresa | Consultar páginas legadas sob conta fictícia |
| **QA-08** | BAIXO | Modais com cerca de 12 px de overflow interno horizontal | Abrir modais de cadastro em larguras móveis |

**Inspeção estática adicional nesta etapa:** `alterar-senha.php` contém campos de cliente; o link de recuperação em `index.php` usa `href="#"`; `js/button-add.js` tenta chamar `addEventListener` sem conferir se existe o elemento. A causa CSS precisa ser corrigida e revalidada; não se deve declarar QA-01 resolvido antes de teste visual real.

### Dados de teste e limites

- **Conta QA A:** `QA_AGENDAY_20261116_A`, com serviços, funcionários, clientes e duas imagens PNG fictícias. Agendamento **#3 cancelado**.
- **Conta QA B:** `QA_AGENDAY_20261116_B`, com serviço, funcionário, cliente e agendamentos **#4 e #5 cancelados**.
- Segundo o relatório, **nenhum dos três agendamentos ficou ativo**; houve logout ao fim. Uma submissão de cadastro interrompida pode ter criado conta adicional: **não confirmado**.
- O relatório cita 205 requisições inspecionadas sem falhas HTTP na varredura, mas com erro JS em configuração. Não foi exportado arquivo de screenshots para o repositório.
- **Não testado:** agendamento verdadeiramente concorrente, cobertura completa de autorização dos endpoints, commit exato do PHP publicado e alteração/exclusão não implementadas.
- Esses resultados comprovam somente os cenários executados e relatados; **não constituem certificação integral de segurança**. Não armazenar credenciais de contas QA aqui.

### Plano de correção sequencial — um defeito por Pull Request

**Regra:** cada correção nasce de branch curta baseada na última `dev-feira-uninorte`, gera PR separado com objetivo, testes e registro de evidências. **Só iniciar a próxima correção depois de revisar/integrar a anterior**, salvo autorização diferente. Não fazer merge nem deploy sem autorização explícita.

1. **QA-01 — menu lateral (primeiro):** [PR #4](https://github.com/Emds96/AgenDay-PI/pull/4) **integrado em 16/11/2026**, com CSS e teste estrutural. **CI passou, mas publicação não ocorreu:** o job de envio foi ignorado porque a automação permanece bloqueada. A interface gráfica havia exigido verificação adicional de identidade; **em nova leitura de 16/11 o GitHub CLI estava autenticado e as variáveis consultáveis**, sem contornar qualquer proteção. A nova publicação exige autorização, backup e as condições do guia operacional. Após o envio autorizado e concluído, validar abertura/fechamento, backdrop, navegação e viewport 320/390/768/1366 px. **Critério de aceitação:** painel visível e interativo dentro da janela ao abrir, desaparece ao fechar, sem regressão.
2. **QA-02 — alteração de senha:** implementar endpoint autenticado com senha atual, nova senha, confirmação, CSRF, `password_verify`/`password_hash` e mensagens seguras; testar persistência e sessão.
3. **QA-03 — recuperação de senha:** decidir mecanismo apropriado à hospedagem (envio de e-mail confiável, token de uso único/validade e limitação de tentativas), ou remover/desativar o link até ser implementado; jamais oferecer recuperação insegura.
4. **QA-04 — perfil/configurações:** substituir informações fictícias por dados da conta e definir quais ações realmente serão implementadas, com permissões e validações.
5. **QA-05 — comandas/histórico/financeiro:** decidir, com o responsável, se implementar MVP ou ocultar recursos não funcionais para a feira.
6. **QA-06 — erro JS da configuração:** isolar e remover/corrigir referência inválida a elemento, com teste de console.
7. **QA-07 — textos fixos:** corrigir identificação do estabelecimento nos cabeçalhos das páginas legadas.
8. **QA-08 — overflow dos modais:** ajustar dimensões internas e testar em celular/tablet.

**Entre PRs:** validar CI, revisar diferenças e autorizar merge; após merge/deploy controlado, executar regressão usando ZERO Browser MCP. Atualizar este STATUS com número do PR, commit, resultado de teste e pendências. Prioridade geral: navegação e apresentação funcional até o prazo operacional de 24/11.


## Situação da correção QA-01 — 16/11/2026

- **PRs integrados:** [#3 — auditoria](https://github.com/Emds96/AgenDay-PI/pull/3), [#4 — CSS do menu](https://github.com/Emds96/AgenDay-PI/pull/4) e [#5 — acompanhamento da publicação](https://github.com/Emds96/AgenDay-PI/pull/5), todos em `dev-feira-uninorte`. Commit de integração do CSS: `949ba126ad5eac6dda07043b560f44a20873c336`.
- **Arquivos da correção:** `css/responsivo.css` e `tests/responsividade-check.js`, somente; sem PHP/SQL/modificação de regras de negócio.
- **CI:** [execução 43951659658](https://github.com/Emds96/AgenDay-PI/actions/runs/43951659658) passou em sintaxe PHP, testes locais e preparação de pacote; **job de publicação foi `skipped`**, não executado.
- **Bloqueio constatado na tentativa via navegador:** variáveis de repositório `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. A interface do GitHub exigiu confirmação de identidade por e-mail antes de permitir alterações. O responsável deve concluir a verificação diretamente no GitHub; **não solicitar nem guardar o código de verificação no chat**.
- **Ação já realizada:** cancelamento de workflow legado de diagnóstico FTPS que permanecia aguardando aprovação desde 13/11; isso liberou a validação do commit atual. Não houve publicação de código nessa ação.
- **Conferência da hospedagem:** em 16/11, o `css/responsivo.css` público **não continha o seletor introduzido no PR #4**. Portanto o bug QA-01 **continua pendente de validação em produção**.
- **Próximo passo:** utilizando o GitHub CLI já recuperado, após autorização e backup, executar publicação autorizada da branch correta, restaurar imediatamente os bloqueios `false/disabled`, verificar o CSS servido e repetir testes de menu em 320/390/768/1366 px. Manter próxima correção QA-02 aguardando essa etapa.

## Estratégia de branches aprovada — 16/11/2026

- **`dev-feira-uninorte`** é a branch de integração do desenvolvimento: novas tarefas devem preferencialmente nascer de branches curtas baseadas nela, com Pull Requests direcionados a ela.
- **`main` do fork `Emds96/AgenDay-PI`** será a referência da versão oficial/estável **depois** da validação e aprovação. No momento, continua preservada e contém o workflow de deploy antigo, cuja configuração precisa ser revista antes de qualquer merge.
- **Promover** significa abrir e aprovar um PR de `dev-feira-uninorte` para a `main` do fork. Isso **não altera o repositório `oliv-gabriel/AgenDay-PI`**; não há promoção automática, nem obrigação de esperar o dia da feira após aprovação da versão estável.
- Exigir autorização específica para promoção, alterações de workflow e deploy. O merge na `main` pode disparar o workflow legado; é indispensável revisar o risco antes.

## Próximas prioridades propostas (atualizadas após QA-02/QA-03)

1. **Isolamento de dados entre estabelecimentos:** completar os testes cruzados de autorização entre duas contas de QA; as listagens normais já passaram, mas endpoints, escritas e concorrência não receberam cobertura integral.
2. **Experiência pública de agendamento para o consumidor:** definir e implementar o MVP se aprovado; atualmente existe apenas administração/autenticação de estabelecimento.
3. **QA-04/QA-05:** transformar perfil e configurações fictícios em informações reais; decidir se comandas/histórico/financeiro terão MVP ou serão sinalizados como demonstrativos na feira.
4. **QA-06/QA-07/QA-08:** corrigir erro JavaScript de configuração, cabeçalhos com `Barbearia` fixa e overflow em modais; validar o menu responsivo (QA-01) visualmente após publicação.
5. **Regressão:** testar novamente cadastros/agendamentos, login tradicional/Google, recuperação QA-03 (negativos de token/limites) e dispositivos móveis.
6. **Feira:** confirmar o prazo operacional 24/11 e a apresentação 27/11, revisar dados de demonstração e plano de contingência. Não promover a `main` sem aprovação específica.

Essa lista é uma **proposta de prioridades**, não um backlog já aprovado ou uma confirmação de que algo foi testado.

## Pontos de atenção

- Os documentos por microtarefa foram escritos em datas diferentes e podem retratar etapas superadas. Em caso de contradição, consultar primeiro os arquivos atuais da `dev-feira-uninorte`, os commits e as evidências recentes.
- O `docs/MODELO_BANCO_V2.md` descreve a fase inicial de planejamento; o README contém registro posterior da importação do banco. Não tomar o texto inicial como estado atual.
- O código de serviços ainda usa o nome legado `cad.servicos.php`; não renomear sem avaliar todas as referências.
- Arquivos privados de conexão e credenciais não devem ser commitados. A `main` do fork e o repositório original não devem receber alterações por implicação.
- Alterações de arquivos, branches, PRs, banco e deploy exigem autorização prévia do responsável.

## Passagem de contexto para uma nova conversa

Em uma sessão nova, solicitar:

> Trabalhe em `Emds96/AgenDay-PI`, branch `dev-feira-uninorte`. Leia `AGENTS.md`, `docs/INDICE_DA_DOCUMENTACAO.md`, `docs/GUIA_DE_CONTINUIDADE.md`, `docs/STATUS.md` e o guia específico de backup, QA ou deploy. Confira o estado real antes de agir; a pasta de backups de 16/11 estava pendente na última consulta. Verifique commits recentes e eventual divergência com o status; diga o que está confirmado, o que falta e recomende o próximo passo. Não faça alterações sem minha autorização.

**Como atualizar este arquivo depois:** registrar a data, o commit-base, entregas comprovadas, testes realmente realizados, limitações, decisões aprovadas e próximo passo; preferir a atualização no PR da mesma tarefa. Não apagar o histórico técnico em `docs/`.

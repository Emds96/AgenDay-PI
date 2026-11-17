# Configuração de login com Google — AgenDay 2.0

**Estado em 17/11/2026:** código publicado **no site oficial para testes autorizados**; migração SQL realizada e cliente OAuth configurado. **Página de login e botões Google confirmados, mas autenticação Google real, onboarding e persistência ainda não testados de ponta a ponta.**

## Configuração confirmada no Google Cloud — 16/11/2026

- **Projeto criado:** `AgenDay`, ID `agenday-515003`.
- **Google Auth Platform:** nome `AgenDay`, público **Externo**, e-mail de suporte/contato selecionado da conta conectada e aceite da política de dados realizado com autorização do responsável.
- **Cliente OAuth:** `AgenDay Web - InfinityFree`, tipo **Aplicativo da Web**, criado e verificado no Console.
- **Origem JavaScript autorizada:** `https://agenday-sistema.infinityfree.io`.
- **URI de redirecionamento autorizado:** `https://agenday-sistema.infinityfree.io/google-entrar.php`.
- **Publicação do aplicativo:** **Testando**, não liberado para qualquer usuário.
- **Usuários de teste:** conta de suporte adicionada com sucesso via ZERO Browser MCP; a tela **Público-alvo** mostra **1 usuário (1 de teste, 0 outros)** após reabertura, com status **Testando**. O primeiro clique em **Salvar** não submetia o formulário porque o campo ainda perdia foco; a submissão só ocorreu no segundo clique, com o campo já validado. **Isso autoriza somente o teste no Google Cloud e não significa que o login do AgenDay foi testado.**
- **17/11/2026:** arquivo privado `google-oauth-config.php` criado no servidor e migração do banco aplicada após exportação de backup SQL. O primeiro deploy encontrou HTTP 500 na página inicial; o hotfix PR #13 captura falhas do arquivo local e usa fallback com o **OAuth Client ID público** no código (não é Client Secret). O site voltou a carregar e mostrou os dois botões Google. **A integridade do arquivo privado e o login completo ainda precisam de revisão. Não expor nenhum Client Secret.**

A página de configuração do cliente é `https://console.cloud.google.com/auth/clients?project=agenday-515003`. A integração não está liberada em produção.

## Configuração Google Cloud (ambiente de produção)

1. Criar/selecionar projeto no [Google Auth Platform](https://console.cloud.google.com/auth/overview) e configurar o consentimento/branding.
2. Criar **OAuth 2.0 Client ID** do tipo **Aplicativo da Web**.
3. Em **Origens JavaScript autorizadas**, cadastrar:
   `https://agenday-sistema.infinityfree.io`
4. Em **URIs de redirecionamento autorizados**, cadastrar exatamente:
   `https://agenday-sistema.infinityfree.io/google-entrar.php`
5. Se o aplicativo estiver em modo de teste, cadastrar usuários de teste antes de validar o acesso com contas reais.

O ID do cliente é público por natureza, mas mantemos a configuração do ambiente em um arquivo específico para ativar o recurso de maneira controlada.

Criar manualmente no servidor autorizado (NÃO enviar ao GitHub), na raiz de `htdocs`:

```php
<?php
return [
    'client_id' => 'SEU_CLIENT_ID.apps.googleusercontent.com',
];
```

**Nome exato:** `google-oauth-config.php`. Esse arquivo está no `.gitignore`. A rotina automática de publicação não o deve recriar nem sobrescrever; validar e manter essa separação.

## Evidências da publicação de 17/11/2026

- PR #12: integração de código em `dev-feira-uninorte`, commit `ce8358018ff06e24786064d489741f312df04c68`.
- Deploy inicial: [run #44065895502](https://github.com/Emds96/AgenDay-PI/actions/runs/44065895502) — arquivos enviados, porém HTTP 500 na página de login.
- Hotfix PR #13: commit `b06ae1f1019cee78ebdc90285ea808f6c6062730`, deploy [run #44067516631](https://github.com/Emds96/AgenDay-PI/actions/runs/44067516631) success.
- ZERO Browser MCP confirmou após hotfix: login, cadastro, `g_id_onload` e dois componentes Google, sem HTTP 500.
- Fluxo real Google **ainda não comprovado**. Google Auth Platform permanece em `Testando` com apenas uma conta autorizada.
- Flags de deploy restauradas para `AGENDAY_DEPLOY_ENABLED=false`, `AGENDAY_DEPLOY_MODE=disabled`.
- Backup SQL privado de 17/11 validado; último backup completo de arquivos confirmado é de 16/11; restauração não testada.

## Pré-requisitos antes de disponibilizar os botões

- Banco de produção com **backup fresco validado**. Executar em cópia de teste a migração proposta em `sql/migracao-google-login.sql` e conferir integridade, incluindo dados preexistentes. Depois da aprovação específica, aplicar em produção.
- Verificar se o InfinityFree oferece `openssl`, `curl`, `mysqli`, `mbstring` e acesso HTTPS de saída para `https://www.googleapis.com/oauth2/v1/certs`. O código **nega o login** se os certificados não puderem ser verificados.
- Revisar segurança do verificador OpenSSL, política de cache dos certificados e controles antiabuso. A recomendação oficial é usar biblioteca Google para produção; o verificador local exige revisão e teste independentes.
- Validar o comportamento real dos botões oficiais Google no domínio e a responsividade/identidade visual do pop-up.
- Criar/vincular identidade Google a uma conta local existente **somente após autenticação prévia dessa conta**, nunca automaticamente por e-mail igual. Esse fluxo ainda está pendente.
- Confirmar que contas só Google não conseguem autenticar por senha vazia; contas locais continuam funcionando.
- Registrar resultados (CI/QA) em `docs/LOGIN_GOOGLE_CHECKLIST.md` e `docs/STATUS.md`.

## Fluxo previsto

1. **Cadastro tradicional:** mantém os campos de estabelecimento, e-mail, senha e confirmação como hoje.
2. **Criar conta com Google / Entrar com Google:** dois botões oficiais separados nas telas, enviados ao mesmo endpoint seguro `google-entrar.php`.
3. **Google conhecido:** consultar associação pelo `sub` verificado e entrar diretamente na agenda.
4. **Google novo:** guardar só `sub` e e-mail verificado em sessão temporária e mostrar o pop-up com o visual original do AgenDay. Exigir nome, oferecer senha local opcional e aviso explícito de que não é a senha Google.
5. **Concluir cadastro:** criar estabelecimento e associação Google dentro da mesma transação; mostrar a agenda.
6. **E-mail já cadastrado sem vínculo Google:** o código solicita login tradicional por senha para efetivar a associação com o `sub` verificado. **Não vincula somente porque os e-mails são iguais.** O fluxo foi implementado, mas ainda precisa ser testado no site.

**Fora de escopo:** QA-03 (esqueceu a senha), alterações em `main` ou banco/site de outro integrante.

**Documentação oficial:** [Botão GIS](https://developers.google.com/identity/gsi/web/guides/display-button), [verificação de ID tokens](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token), [API HTML do GIS](https://developers.google.com/identity/gsi/web/reference/html-reference).

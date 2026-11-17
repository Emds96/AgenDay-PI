# Configuração de login com Google — AgenDay 2.0

**Estado em 16/11/2026:** código preparado em branch de desenvolvimento. **Não habilitar em produção** sem as verificações, migração, backup e autorização de publicação.

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
6. **E-mail já cadastrado sem vínculo Google:** recusar criação ou vinculação automática. Implementar fluxo de vinculação protegida antes de considerar esse cenário concluído.

**Fora de escopo:** QA-03 (esqueceu a senha), alterações em `main` ou banco/site de outro integrante.

**Documentação oficial:** [Botão GIS](https://developers.google.com/identity/gsi/web/guides/display-button), [verificação de ID tokens](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token), [API HTML do GIS](https://developers.google.com/identity/gsi/web/reference/html-reference).

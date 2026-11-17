# Checklist de implementação — Login com Google (AgenDay 2.0)

**Iniciado:** 16/11/2026  
**Repositório:** `Emds96/AgenDay-PI`  
**Branch-base:** `dev-feira-uninorte` (base inicial `e3174bc713b2b4c286066b9b4c8abb622bc3b766`)  
**Branch de trabalho:** `feat/login-google-primeiro-acesso-20261116`  
**Estado:** desenvolvimento inicial; **não publicado**.

> Marque [x] apenas após execução real e verificação. Este documento é o acompanhamento persistente. QA-03 (recuperação por e-mail) é outra tarefa, sem implementação neste escopo.

## 1. Requisitos e segurança

- [x] Alinhado com o responsável: manter **exatamente a identidade visual atual** do login e cadastro, sem redesenhar as telas nem eliminar os campos existentes.
- [x] Alinhado: dois botões distintos: **Criar conta com Google** no cadastro e **Entrar com Google** no login, preservando o acesso tradicional (inclusive e-mail corporativo).
- [x] Alinhado: primeiro acesso Google com **pop-up no mesmo design do login/cadastro**, nome obrigatório, senha do AgenDay opcional e confirmar senha se definida; opção **Agora não** para não definir senha.
- [x] Alinhado: texto do pop-up informa claramente que a senha é **exclusivamente para o AgenDay**, **não é a senha do Google**.
- [x] Inspecionados `index.php`, `css/login.css`, `css/design-system.css`, `css/responsivo.css`, `js/login.js`, `cadastrar.php`, `login.php`, `sessao.php` e `sql/BD_v2.sql` na branch correta.
- [x] Criada branch de tarefa a partir do SHA exato da `dev-feira-uninorte`.
- [ ] Verificar configuração/viabilidade prática do Google Identity Services no InfinityFree (HTTPS externo, biblioteca PHP e extensões).
- [x] Criado projeto independente **AgenDay** no Google Cloud: `agenday-515003`, sem ativar faturamento.
- [ ] Concluir Google Auth Platform: branding iniciado com nome `AgenDay`, público **Externo** e endereço de suporte/contato selecionado da própria conta Google, mas **pendente o aceite da política de dados do usuário pelo responsável**, exigido no passo final do assistente.
- [ ] Criar e configurar **OAuth Web Client ID** no Google Cloud, incluindo origem autorizada de produção e consentimento; credenciais/configurações fora do repositório quando aplicável.

- [x] Implementados inicialmente os endpoints PHP de login e cadastro Google, validação de assinatura por OpenSSL, controle CSRF e sessão temporária, **sem configurar ou usar em produção**. Falta validação de segurança e testes integrados.
- [x] Preparada proposta de migração `sql/migracao-google-login.sql` **sem executar no banco**.
- [x] Criado [PR #12 em rascunho](https://github.com/Emds96/AgenDay-PI/pull/12), com destino exclusivo à `dev-feira-uninorte`.
- [x] Executado o workflow de **sintaxe PHP** do GitHub Actions na branch: [run 44007574268](https://github.com/Emds96/AgenDay-PI/actions/runs/44007574268): **success** no commit `8fca310e7cdc33f211fc3bcd646d799103753abd` (segunda execução, após ajuste nos testes). **Isso não executa os testes funcionais da integração Google**.

## 2. Interface e design — identidade visual é requisito de aceite

- [x] Codificada a renderização oficial do botão **Criar conta com Google**, condicional ao OAuth Client ID privado; sem retirar os campos atuais. **Ainda não testada no site.**
- [x] Codificada a renderização oficial do botão **Entrar com Google**, condicional ao OAuth Client ID privado. **Ainda não testada no site.**
- [x] Criado no código o pop-up com a mesma paleta e tipografia do login (QA visual ainda pendente): `#A2ECE4`, `#7AD6CC`, `#18544D`, branco, campos arredondados, sombra e gradiente; seguir `css/design-system.css`.
- [x] Codificados campos do pop-up: nome obrigatório; senha opcional (8–72 bytes); confirmação somente com senha; concluir e prosseguir sem senha. **Integração com o banco não testada.**
- [x] Incluído no pop-up o aviso explícito: **"Esta senha é exclusiva para acessar o AgenDay com seu e-mail. Não é a senha da sua conta Google. Nunca informe sua senha do Google aqui."**
- [ ] Adaptar alturas/rolagem dos formulários no desktop e mobile sem quebrar animação/alternância existentes.
- [ ] Testar visual e acessibilidade em 320, 390, 768 e 1366 px, teclado e mensagens de erro.

## 3. Autenticação e banco (sem tocar produção sem backup/validação)

- [ ] Validar ID token Google **no servidor** (assinatura, `aud`, `iss`, `exp`, `sub`) com implementação confiável; nunca confiar só no JS ou no e-mail.
- [ ] Desenhar e revisar migração SQL reversível: identidade Google (`sub` único) e senha do estabelecimento opcional; preservar todas as linhas existentes.
- [ ] Impedir vinculação automática de Google a conta existente por igualdade de e-mail; requerer autenticação prévia e confirmação apropriada.
- [ ] Implementar criação incompleta sem conceder acesso à agenda antes do nome do estabelecimento.
- [ ] Concluir cadastro com nome, com ou sem senha, e liberar agenda; tornar pop-up desnecessário nos próximos logins.
- [ ] Manter login com e-mail/senha funcionando; contas exclusivamente Google sem senha não podem autenticar por esse caminho.
- [ ] Tratar token inválido/expirado, cancelamento, corrida de cadastros, CSRF e erros sem expor tokens.
- [ ] Testar a criação de senha posterior/vinculação pelo usuário autenticado de forma segura (se aplicável).

## 4. Testes, revisão e entrega

- [ ] Criar testes automatizados pertinentes e rodar `php -l` / suíte de validações.
- [ ] Testar cadastro e login tradicionais sem regressão, incluindo e-mail corporativo.
- [ ] Testar primeiro acesso Google com senha e sem senha, retorno direto e isolamento por estabelecimento.
- [ ] Testar tentativas de duplicação/vinculação indevida e rejeições de acesso.
- [ ] Revisar diff completo, cenários e CI do PR; manter sem merge automático.
- [ ] Preparar **backup novo** de arquivos e banco e validar antes de qualquer mudança em produção.
- [ ] Solicitar autorização específica para migração/alteração do banco em produção e deploy; não mudar gates/Secrets por conta própria.
- [ ] Publicar versão aprovada via fluxo vigente e testar fim a fim no InfinityFree.
- [ ] Atualizar `docs/STATUS.md` com evidências reais, estado final e pendências.

## Histórico verificável desta execução

**Configuração Google Cloud, 16/11/2026:** ZERO Browser MCP conectado à conta existente; novo projeto `agenday-515003` criado e confirmado no Console. Tela `https://console.cloud.google.com/auth/overview/create?project=agenday-515003` exige no passo 4 assinalar a declaração de concordância com a política de dados de usuário dos serviços de API do Google. **Não foi assinalada e o formulário não foi submetido.** Nenhum OAuth Web Client ID foi criado nesta etapa. Aguardar o responsável aceitar expressamente a política ou concluir esse passo por conta própria. O nome do projeto `AgenDay 2.0` foi rejeitado por caracteres inválidos; usado `AgenDay`. Nenhuma cobrança, banco, deploy ou segredo foi alterado.



**16/11/2026:** implementação inicial salva apenas na branch `feat/login-google-primeiro-acesso-20261116`: `index.php`, `css/google-auth.css`, `js/google-auth.js`, `lib/google-identidade.php`, `google-entrar.php`, `google-cadastro-concluir.php`, `login.php`, `.gitignore`, `sql/migracao-google-login.sql`, `tests/google-identidade-validacao.php`, `docs/GOOGLE_LOGIN_SETUP.md`. **Sem alteração do banco de produção, configuração do Google Cloud, deploy, merge ou QA-03**. O teste PHP de token foi escrito, mas **não executado**; o único teste automatizado efetivamente concluído nesta etapa é o lint PHP do workflow.

## Dependências e decisões técnicas

1. A coluna atual `estabelecimento.senha_estabelecimento VARCHAR(255) NOT NULL` **não suporta conta sem senha**; requer migração antes do recurso entrar em produção.
2. O Google exige **OAuth Web Client ID** e origem autorizada; o botão oficial deve ser produzido pela Google Identity Services (não imitar o botão com JavaScript não suportado).
3. A recomendação oficial é validar ID tokens na aplicação PHP com a biblioteca Google e identificar usuários pelo `sub` (referências: https://developers.google.com/identity/gsi/web/guides/verify-google-id-token e https://developers.google.com/identity/gsi/web/guides/display-button).
4. `main`, banco/hospedagem e QA-03 permanecem **intocados** até autorização e conclusão das verificações necessárias.

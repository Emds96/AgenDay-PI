# QA-03 — Recuperação de senha via Brevo

**Estado (17/11/2026):** migração, telas visuais e novo remetente publicados. O responsável validou manualmente recebimento do e-mail anterior (no spam), redefinição de senha e login com a nova senha; o remetente novo foi verificado na Brevo e aceito pelo endpoint de envio em sandbox (HTTP 201). Ainda falta confirmar a aparência do endereço novo em um e-mail real.

## Implementado
- Link de index.php leva a esqueci-senha.php.
- Solicitação retorna mensagem genérica para não revelar contas.
- Token aleatório de 32 bytes, armazenado apenas como SHA-256, validade 30 minutos, uso único.
- Limites de uma emissão por conta a cada 15 minutos e por sessão a cada 60 segundos.
- Redefinição em transação invalida outros links da mesma conta.
- Contas Google sem senha local podem definir senha do AgenDay; isso não modifica a senha Google.
- Brevo por HTTPS com cURL, remetente verificado e configuração de credencial privada.

## Ativação executada em 17/11/2026

1. Backup integral imediatamente anterior, `2026-11-17_190728_fe52b711`, com restauração local aprovada.
2. `sql/QA03_recuperacao_senha.sql` aplicado somente no banco independente do AgenDay, por PHP efêmero; `QA03_MIGRATION_OK`, seis colunas e chave estrangeira verificadas. Temporários removidos.
3. Publicado o commit `43676874d7a3abeba3de72ec00da62af2cd8bc68` via [GitHub Actions #44197680873](https://github.com/Emds96/AgenDay-PI/actions/runs/44197680873); jobs de validação, diagnóstico FTPS e publicação `success`. Travas de deploy restabelecidas em `false` / `disabled`.
4. Páginas de solicitação e redefinição testadas por navegador: HTTP 200; token inválido rejeitado, pasta privada de configuração Brevo retorna HTTP 403.
5. `AGENDAY_BREVO_API_KEY` instalado pelo deploy protegido; não houve leitura/divulgação de valores. Painel Brevo: política de IPs autorizados ativada; após autorização pelo responsável, 1 IP autorizado e nenhum não autorizado. A chamada sandbox ao endpoint de e-mail foi aceita (HTTP 201); sem entrega real, log de e-mail ou envio ao usuário.

## Mudança de remetente (17/11/2026)

- O responsável criou e verificou no painel Brevo o endereço `agendaysistema@gmail.com` com nome **AgenDay Sistema**. O remetente anterior continua cadastrado, sem modificação.
- [PR #23](https://github.com/Emds96/AgenDay-PI/pull/23) alterou apenas o remetente em `lib/brevo-email.php`, sem mexer na chave API ou nas regras de IP. Commit `5749f333338e599c67c9e6050e39caa684091bac` publicado pelo [workflow #44212874922](https://github.com/Emds96/AgenDay-PI/actions/runs/44212874922), com validação, diagnóstico FTPS e envio aprovados.
- Backup pré-deploy `2026-11-17_205855_4f8c886e` foi aprovado (`BACKUP OK`, restauração local). Confirmação FTPS: código publicado usa o e-mail/nome novos e não contém o endereço antigo como remetente.
- Teste de envio em **sandbox** pela hospedagem, com novo remetente, recebeu HTTP 201; nenhum e-mail real foi enviado. O executor temporário foi removido. Login e tela de recuperação HTTP 200, configuração privada Brevo HTTP 403. Flags de deploy voltaram para `false`/`disabled`.
- **Ainda validar:** mensagem real recebida, endereço From gerado pela Brevo (inclusive possível reescrita para `brevosend.com`) e entregabilidade. Não afirmar que o prefixo/domínio exatos estão confirmados por teste de caixa de entrada.

## Validação funcional relatada e verificações restantes

- **Relato manual do responsável (17/11/2026):** solicitação de recuperação para conta própria, mensagem entregue com atraso na pasta de spam, link utilizado para cadastrar nova senha, autenticação aprovada com a senha nova e senha antiga rejeitada. Não houve automação independente desse teste de caixa de entrada.
- **Experiência de uso (publicada):** o aviso sobre possível atraso e spam/lixo eletrônico está visível antes e depois do envio, e as páginas de solicitação/redefinição usam a identidade do login. [PR #21](https://github.com/Emds96/AgenDay-PI/pull/21) publicado no commit `393b281e` pelo [workflow #44207273591](https://github.com/Emds96/AgenDay-PI/actions/runs/44207273591), com backup `2026-11-17_201733_0559724e` validado anteriormente. Conferência no Edge: HTTP 200 em ambas as telas e no novo CSS, teste 390px sem rolagem horizontal, mensagem genérica com aviso de spam, token inválido rejeitado; config Brevo permanece HTTP 403. Flags de deploy desligadas depois.
- **Ainda testar:** tentativa de reutilizar o link, expiração de 30 minutos, limites de solicitações e regressão do login Google.
- Em falhas futuras de entrega, consultar os logs transacionais Brevo sem expor token ou credenciais.

## Riscos e limitações
- HTTPS da InfinityFree para `api.brevo.com/v3/smtp/email` comprovado por sandbox HTTP 201 após autorização de IP. O responsável confirmou entrega real (com atraso, no spam); entregabilidade consistente para diferentes provedores ainda não foi avaliada.
- Limitação por IP não foi implementada; existem limites por conta e sessão, que não substituem defesa distribuída.
- Troca de senha não revoga sessões preexistentes em outros dispositivos.
- Remetente Gmail verificado pode ter limitações de entregabilidade; revisar DNS/remetente em fase posterior.
- Não considerar QA-03 funcional apenas por ter código e formulário.

## Segurança do pipeline
- O job de validação não recebe a credencial; só o job `publicar` em ambiente `producao-agenday` a recebe.
- O artifact inclui apenas `config/.htaccess`, não o arquivo privado com a chave.
- Na InfinityFree, acessar arquivos PHP fora de `htdocs` é restringido; a configuração será mantida em `config/` com bloqueio Apache para acesso HTTP.
- O bloqueio da URL privada HTTP 403 foi verificado. Entrega e troca de senha foram relatadas pelo responsável em conta própria; faltam os testes de reutilização e expiração de token.

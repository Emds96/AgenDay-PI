# QA-03 — Recuperação de senha via Brevo

**Estado (17/11/2026):** migração e deploy executados em produção; envio Brevo validado em sandbox (HTTP 201). **O responsável relatou recebimento real do e-mail no spam, redefinição concluída, acesso com senha nova e rejeição da senha antiga.** A interface das telas está sendo alinhada à identidade do login na tarefa QA-03 visual, sem alterar o fluxo de segurança.

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

## Validação funcional relatada e verificações restantes

- **Relato manual do responsável (17/11/2026):** solicitação de recuperação para conta própria, mensagem entregue com atraso na pasta de spam, link utilizado para cadastrar nova senha, autenticação aprovada com a senha nova e senha antiga rejeitada. Não houve automação independente desse teste de caixa de entrada.
- **Experiência de uso:** o aviso de que o e-mail pode demorar e chegar ao spam/lixo eletrônico foi incorporado à solicitação, juntamente com a identidade visual do login nas páginas de solicitação e redefinição. Validar visualmente após publicação autorizada.
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

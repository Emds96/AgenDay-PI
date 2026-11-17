# QA-03 — Recuperação de senha via Brevo

**Estado:** código preparado; sem deploy, migração de banco ou envio real confirmado.

## Implementado
- Link de index.php leva a esqueci-senha.php.
- Solicitação retorna mensagem genérica para não revelar contas.
- Token aleatório de 32 bytes, armazenado apenas como SHA-256, validade 30 minutos, uso único.
- Limites de uma emissão por conta a cada 15 minutos e por sessão a cada 60 segundos.
- Redefinição em transação invalida outros links da mesma conta.
- Contas Google sem senha local podem definir senha do AgenDay; isso não modifica a senha Google.
- Brevo por HTTPS com cURL, remetente verificado e configuração de credencial privada.

## Ativação ainda pendente
1. Validar backups recentes dos arquivos integrais de htdocs e do banco conforme docs/BACKUP_E_RECUPERACAO.md.
2. Após autorização para alteração de banco, executar somente sql/QA03_recuperacao_senha.sql no banco independente do AgenDay.
3. A chave é configurada pelo Secret `AGENDAY_BREVO_API_KEY` do GitHub Environment `producao-agenday` (nome confirmado em 17/11/2026, conteúdo não acessado). Durante o job protegido de publicação, `scripts/publicar_ftp.py` cria `htdocs/config/brevo-api-config.php` via FTPS a partir do Secret sem salvá-lo em Git ou artifacts. O `.htaccess` dessa pasta bloqueia acesso HTTP. Isso ainda não foi publicado.
4. Antes da publicação, revogar/rotacionar a chave que passou temporariamente pelo Keep/Drive, substituindo manualmente o VALOR do Secret no Environment do GitHub. O nome permanece o mesmo; não divulgar a nova chave no chat.
5. Com backups verificados e autorização para SHA/destino, publicar pelo fluxo existente em docs/PUBLICACAO_AUTOMATICA.md.
6. Em produção, testar entrega real e redirecionamento, token expirado/reutilizado/alterado, senha nova/antiga, conta inexistente, login Google e limites de solicitações.

## Riscos e limitações
- Compatibilidade de chamadas HTTPS da InfinityFree para api.brevo.com ainda não comprovada. A existência do Secret não ativa a chave no PHP por si só.
- Limitação por IP não foi implementada; existem limites por conta e sessão, que não substituem defesa distribuída.
- Troca de senha não revoga sessões preexistentes em outros dispositivos.
- Remetente Gmail verificado pode ter limitações de entregabilidade; revisar DNS/remetente em fase posterior.
- Não considerar QA-03 funcional apenas por ter código e formulário.

## Segurança do pipeline
- O job de validação não recebe a credencial; só o job `publicar` em ambiente `producao-agenday` a recebe.
- O artifact inclui apenas `config/.htaccess`, não o arquivo privado com a chave.
- Na InfinityFree, acessar arquivos PHP fora de `htdocs` é restringido; a configuração será mantida em `config/` com bloqueio Apache para acesso HTTP.
- Verificar bloqueio da URL de configuração e e-mail real antes de concluir QA-03.

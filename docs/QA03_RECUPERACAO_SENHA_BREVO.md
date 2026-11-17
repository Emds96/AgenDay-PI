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
3. Inserir uma chave NOVA diretamente na hospedagem, fora do GitHub/chat/Keep/Drive: variável AGENDAY_BREVO_API_KEY ou arquivo privado brevo-api-config.php que retorna um array com api_key. Proteger o arquivo contra acesso HTTP. Avaliar possibilidade de guardá-lo fora de htdocs.
4. Revogar chave que tenha sido armazenada em texto no Keep/Drive durante testes, limpar cópias temporárias e lixeiras, e só depois ativar a chave substituta.
5. Com backups verificados e autorização para SHA/destino, publicar pelo fluxo existente em docs/PUBLICACAO_AUTOMATICA.md.
6. Em produção, testar entrega real e redirecionamento, token expirado/reutilizado/alterado, senha nova/antiga, conta inexistente, login Google e limites de solicitações.

## Riscos e limitações
- Compatibilidade de chamadas HTTPS da InfinityFree para api.brevo.com ainda não comprovada.
- Limitação por IP não foi implementada; existem limites por conta e sessão, que não substituem defesa distribuída.
- Troca de senha não revoga sessões preexistentes em outros dispositivos.
- Remetente Gmail verificado pode ter limitações de entregabilidade; revisar DNS/remetente em fase posterior.
- Não considerar QA-03 funcional apenas por ter código e formulário.

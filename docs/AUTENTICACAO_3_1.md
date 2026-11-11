# Autenticação do AgenDay 2.0 — microtarefa 3.1

## O que foi alterado (somente na branch de desenvolvimento)

- `sessao.php` inicia sessões com cookies HttpOnly, SameSite=Lax e Secure quando HTTPS é usado, e gera/valida tokens CSRF para autenticação.
- `login.php` consulta o estabelecimento por e-mail usando prepared statement, verifica hash com `password_verify()`, regenera o ID da sessão após login e armazena `id_estabelecimento`.
- `cadastrar.php` valida nome, e-mail, senha/confirmacão, usa prepared statements e grava senhas com `password_hash()`.
- `index.php` envia token CSRF nos formulários e exige confirmação de senha no servidor.
- `verifica_login.php` valida a sessão do estabelecimento e impede cache de páginas privadas.
- `logout.php` destrói a sessão e expira o cookie.
- Todas as páginas administrativas passam por `verifica_login.php` antes de emitir HTML.
- Os três endpoints de listagem usam `id_estabelecimento` da sessão para separar dados entre negócios.
- Os três endpoints legados de cadastro também exigem autenticação; a lógica interna será reescrita em 3.2–3.4.

## Credenciais

O banco está hospedado na conta independente `if0_43415733_agenday`. **Nenhuma senha foi adicionada ao repositório.** No InfinityFree, a conexão deve permanecer no arquivo privado `htdocs/conexao.php` (GitHub ignora esse caminho). Não compartilhe credenciais de banco no chat nem em commits.

## Verificação de funcionamento (a realizar após publicação controlada)

1. Abrir `index.php` sem login; criar conta teste com senha de pelo menos 8 bytes.
2. Confirmar rejeição de senha diferente no campo de confirmação e e-mail duplicado.
3. Autenticar com a conta recém-criada; confirmar redirecionamento para `agenda.php`.
4. Abrir `agenda.php` em navegação anônima; confirmar redirecionamento para `index.php`.
5. Sair e tentar abrir novamente `agenda.php`; acesso deve ser negado.
6. Confirmar que a senha no MySQL é um hash (não MD5 ou texto puro).
7. Conferir `error_log` da hospedagem para erros de PHP/MySQL sem mostrar detalhes públicos.

## Limites importantes

- Estas são mudanças **no código**, ainda **não implantadas ou testadas no InfinityFree**. Não afirmar que o login foi validado ao vivo.
- O banco V2 começa vazio, portanto não existe conta de demonstração até que uma seja criada.
- Senhas antigas MD5 não são aceitas nesta nova versão. Como o banco é independente, isso é intencional.
- Cadastro/consulta de clientes, funcionários e serviços ainda exigem a adaptação completa à estrutura V2 nas microtarefas **3.2, 3.3 e 3.4**; há formulários e colunas legados. Para evitar vazamento entre empresas, **não publique a aplicação completa** antes desses ajustes.
- As operações restantes de gravação ainda precisam de CSRF, validação, prepared statements e `id_estabelecimento` na inserção. Não estão concluídas nesta microtarefa.
- `alterar-senha.php`, finanças e comandas ainda não implementam todas as ações; as telas apenas ganharam a verificação de sessão.

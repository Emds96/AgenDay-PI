# Publicação automatizada GitHub → InfinityFree (preparação segura)

**Situação:** os scripts e o fluxo de GitHub Actions estão versionados na `dev-feira-uninorte`. O envio ao site oficial está **DESATIVADO** por padrão e nenhuma credencial de FTP foi configurada por esta alteração. A branch `main` e o projeto original não foram modificados.

## Objetivo

Depois de uma autorização inicial, cada atualização da branch `dev-feira-uninorte` pode ser testada e publicada por FTPS no próprio site `agenday-sistema.infinityfree.io`, sem precisar repetir upload manual ou enviar capturas de tela.

## Arquivos criados

- `.github/workflows/agenday-publicar.yml`: fluxo acionado por commits na branch de desenvolvimento. O job `validar` verifica sintaxe PHP e testes antes do pacote; `publicar` só roda com duas variáveis de autorização ativas.
- `scripts/preparar_publicacao.py`: gera a pasta temporária `deploy-package/` somente com arquivos PHP da aplicação, CSS, JavaScript, imagens da interface e ícones.
- `scripts/publicar_ftp.py`: utiliza FTPS explícito na porta 21, com certificado TLS validado, enviando arquivos ao diretório `htdocs/`.

## Proteções de segurança

- **Não são transferidos** `conexao.php`, `conexao.exemplo.php`, `arquivos/` (fotos enviadas por clientes e funcionários), `sql/`, `tests/`, `docs/`, `.github/`, notas e outros arquivos privados.
- O publicador **não apaga arquivos do servidor**, não executa SQL e não modifica o banco; as fotos e o `htdocs/conexao.php` privado permanecem fora da publicação.
- Não usa a configuração antiga da `main`, que aponta para infraestrutura do projeto original.
- Caso o servidor tenha certificado FTPS inválido, o envio falha com segurança. Nunca desativar validação TLS ou enviar senha por FTP em texto puro.
- São necessárias **duas variáveis de GitHub Actions**: `AGENDAY_DEPLOY_ENABLED=true` e `AGENDAY_DEPLOY_MODE=publish`. Sem ambas, o job `publicar` não executa.
- São necessários **três Secrets do GitHub Actions**, exclusivos da conta independente do AgenDay: `AGENDAY_FTP_HOST`, `AGENDAY_FTP_USERNAME` e `AGENDAY_FTP_PASSWORD`. Jamais cadastrar senhas em arquivos versionados ou nesta conversa.
- O fluxo tem permissões mínimas `contents: read`. O upload de código fica restrito à branch e ao repositório do fork.

## O que falta antes da ativação

1. Confirmar que o endereço oficial está liberado para testes e obter autorização para a primeira publicação.
2. Fazer backup do estado atual da hospedagem **e do MySQL independente**. O workflow não cria backup de banco.
3. Conferir que o arquivo `htdocs/conexao.php` do InfinityFree aponta somente para o banco próprio.
4. Configurar Secrets e variáveis do GitHub via sessão autorizada, sem enviar senha por chat. Até que isso aconteça, o job de validação pode rodar, mas o de publicação estará bloqueado.
5. Após a ativação, executar testes integrados de PHP/MySQL (login, clientes, funcionários, serviços, criar/cancelar agendamentos e isolamento por estabelecimento), além de conferir o design no celular.

## Quando ativado

Commits na `dev-feira-uninorte` executam testes e, se aprovados, atualizam o site por FTPS. Os arquivos são enviados diretamente para a hospedagem, então podem existir breves períodos de inconsistência durante uma publicação. Se o site já atende clientes reais, deve-se usar homologação em vez de publicação contínua diretamente no endereço oficial.

## Limitações conhecidas

- A conexão atual com o GitHub permite editar arquivos e ler resultados de Actions, mas não gerencia os Secrets/Variables ou o login no InfinityFree. A autorização inicial exigirá acesso delegado por uma ferramenta apropriada, como o navegador do ChatGPT Work, caso disponível.
- GitHub Actions **não acessa** o MySQL diretamente; os testes integrados do banco precisam ser feitos a partir do PHP no servidor.
- Os testes atuais são verificação de sintaxe, scripts locais e pacote. Não substituem os testes reais no InfinityFree.

**Estado final desta tarefa:** automação preparada para aprovação e configuração; **não publicada e não habilitada**.

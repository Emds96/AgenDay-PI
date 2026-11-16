# Publicação automatizada GitHub → InfinityFree (histórico e estado verificado)

**Situação (revisada em 16/11/2026):** os scripts e o fluxo GitHub Actions estão versionados na `dev-feira-uninorte`. A automação foi inicialmente criada bloqueada por padrão; **posteriormente houve publicação FTPS bem-sucedida em 13/11/2026**, comprovada pela [execução 43518952935](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935). A página pública do site e os arquivos CSS da versão 2.0 estavam acessíveis em 16/11. **Não foi verificado se o último commit está publicado, nem o estado atual das variáveis e dos secrets**. A `main` do fork e o repositório original permanecem fora desse fluxo.

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

## Evidência de publicação e condições para futuras publicações

1. **13/11/2026:** na [execução 43518952935](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935), os jobs de validação, teste FTPS somente leitura e **envio ao InfinityFree** concluíram com sucesso no commit `f807327811895bc97827929f4e30c2e60a180110`.
2. **16/11/2026:** o site `https://agenday-sistema.infinityfree.io/` respondeu com tela de login; `css/design-system.css` e `css/responsivo.css` estavam acessíveis. Isso **não comprova que o banco e as funcionalidades estejam operacionais**, nem que os commits posteriores estejam implantados.
3. Antes de **qualquer nova publicação**, confirmar autorização do responsável, destino, último commit no servidor, backups do site e do MySQL independente. O workflow não realiza backup de banco.
4. Conferir de forma segura que `htdocs/conexao.php` aponta apenas para o banco próprio; nunca divulgar credenciais por chat ou commit. Validar se variáveis/secrets de autorização seguem corretos sem revelar seus valores.
5. Após publicação autorizada, executar testes integrados de PHP/MySQL (login, clientes, funcionários, serviços, criar/cancelar agendamentos e isolamento por estabelecimento) e conferir o design no celular.

## Quando ativado

Commits na `dev-feira-uninorte` executam testes e, se aprovados, atualizam o site por FTPS. Os arquivos são enviados diretamente para a hospedagem, então podem existir breves períodos de inconsistência durante uma publicação. Se o site já atende clientes reais, deve-se usar homologação em vez de publicação contínua diretamente no endereço oficial.

## Limitações conhecidas

- A conexão atual com o GitHub permite editar arquivos e ler resultados de Actions, mas não gerencia os Secrets/Variables ou o login no InfinityFree. A autorização inicial exigirá acesso delegado por uma ferramenta apropriada, como o navegador do ChatGPT Work, caso disponível.
- GitHub Actions **não acessa** o MySQL diretamente; os testes integrados do banco precisam ser feitos a partir do PHP no servidor.
- Os testes atuais são verificação de sintaxe, scripts locais e pacote. Não substituem os testes reais no InfinityFree.

**Estado registrado em 16/11/2026:** a automação **já realizou pelo menos uma publicação FTPS bem-sucedida**. O estado atual de habilitação automática não foi comprovado e os testes funcionais no ambiente publicado permanecem pendentes. Para continuidade e prazos, consultar [STATUS.md](STATUS.md).

# Índice e ordem de leitura — documentação AgenDay 2.0

**Revisão transversal de 16/11/2026:** auditados `AGENTS.md`, `README.md`, todos os Markdown de `docs/`, workflows e scripts de publicação. Documentos antigos por microtarefa permanecem como **histórico**, com avisos de defasagem; não são o status atual.

## Para qualquer nova sessão

1. [`AGENTS.md`](../AGENTS.md): regras de segurança, autorização e versionamento.
2. [`PASSAGEM_DE_CONTEXTO.md`](PASSAGEM_DE_CONTEXTO.md): **estado atual, regras, equivalência de ferramentas e checklist de acessos** para qualquer IA ou pessoa.
3. [`GUIA_DE_CONTINUIDADE.md`](GUIA_DE_CONTINUIDADE.md): **como trabalhávamos**, ferramentas, sequência de ações, comunicação entre sessões e decisão do responsável.
4. [`AMBIENTE_LOCAL.md`](AMBIENTE_LOCAL.md): PHP, MariaDB, portas, banco local, scripts, testes e problemas comuns no PC de desenvolvimento.
5. [`STATUS.md`](STATUS.md): histórico recente, PRs, CI, QA, defeitos e bloqueios. **Sempre verificar GitHub/infra antes de afirmar estado atual**.
6. [`ARQUITETURA_E_DECISOES.md`](ARQUITETURA_E_DECISOES.md): módulos, limites, decisões tomadas e opções pendentes.
7. [`README.md`](../README.md): visão geral e como executar em desenvolvimento.

**Fluxo rapido (piloto):** a classificacao simples/media/complexa e a regra de autorizacao unica estao em [AGENTS.md](../AGENTS.md) e [GUIA_DE_CONTINUIDADE.md](GUIA_DE_CONTINUIDADE.md). Para o assistente de deploy (simulacao por padrao e publicacao real ainda nao validada), consultar [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md) e `scripts/publicacao_assistida.py`. A aprovacao de PR e a documentacao nao equivalem a autorizacao de deploy.

## Escolher o manual específico

| Tarefa | Documento | Conteúdo |
| --- | --- | --- |
| Continuar o projeto em outra IA ou PC | [`PASSAGEM_DE_CONTEXTO.md`](PASSAGEM_DE_CONTEXTO.md) | Estado atual, regras, **equivalência das ferramentas** citadas nos docs e checklist do que passar para a IA nova |
| Subir ou recriar o ambiente local | [`AMBIENTE_LOCAL.md`](AMBIENTE_LOCAL.md) | PHP 8.4, MariaDB 11.8, portas, banco `agenday_local`, scripts, testes e problemas comuns |
| Alterar o banco de produção | [`PUBLICACAO_AUTOMATICA.md`](PUBLICACAO_AUTOMATICA.md) | Seção "Mudança de banco em produção": phpMyAdmin, semeador temporário e o que nunca fazer |
| Preparar/validar backup ou recuperar | [`BACKUP_E_RECUPERACAO.md`](BACKUP_E_RECUPERACAO.md) | Download de `htdocs`, exportação MySQL, confidencialidade, integridade e recuperação autorizada |
| Publicar código no InfinityFree | [`PUBLICACAO_AUTOMATICA.md`](PUBLICACAO_AUTOMATICA.md) | **Workflow existente** + Desktop Commander + `gh` + aprovação/Variables/FTPS; exige backup validado |
| QA/regressão/testes | [`TESTES_E_QUALIDADE.md`](TESTES_E_QUALIDADE.md) | Casos reais, diferenças entre evidências, ferramentas e defeitos |
| Login automático com contas A/B | [`DESKBROWSE_LOGIN_QA.md`](DESKBROWSE_LOGIN_QA.md) | DeskBrowse + cofre local, aliases `agenday-conta-a/b`, logout obrigatório, verificação real na agenda; sem senhas |
| Nova funcionalidade/bug | [`STATUS.md`](STATUS.md) + código atual | Branch curta, testes, PR direcionado à dev, merge autorizado, registro de resultados |
| Portal do cliente (**publicado em 21/11/2026**, SHA `4bd707a`) | [`PORTAL_CLIENTE_PLANO.md`](PORTAL_CLIENTE_PLANO.md) e [`PORTAL_CLIENTE_CHECKLIST.md`](PORTAL_CLIENTE_CHECKLIST.md) | Página pública em `agenday-agendamentos.infinityfree.io/?e=slug`, login por celular (código só no primeiro acesso), agendamento, remarcação, cancelamento, WhatsApp (WAHA) e publicação nas duas pastas |
| Instalar/operar o WhatsApp (WAHA + ngrok) | [`WHATSAPP_WAHA_GUIA.md`](WHATSAPP_WAHA_GUIA.md) | Docker Desktop, credenciais, QR do número, túnel, configuração no AgenDay, rotina da feira, falhas comuns e plano B |
| Configurações do estabelecimento (QA-04) | [`QA04_CONFIG_ESTABELECIMENTO.md`](QA04_CONFIG_ESTABELECIMENTO.md) | Histórico da configuração básica publicada |
| Nova central Configurações (UI-08) | [`CONFIGURACOES_UI08.md`](CONFIGURACOES_UI08.md) | Duas abas, avatar sem migração, senha em modal, Space Grotesk e QA pendente |
| Banco/esquema | [`MODELO_BANCO_V2.md`](MODELO_BANCO_V2.md) e [`REVISAO_BANCO.md`](REVISAO_BANCO.md) | História da modelagem, **não** autorização para importar SQL |
| Autenticação/cadastros | [`AUTENTICACAO_3_1.md`](AUTENTICACAO_3_1.md), [`CLIENTES_3_2.md`](CLIENTES_3_2.md), [`FUNCIONARIOS_3_3.md`](FUNCIONARIOS_3_3.md), [`SERVICOS_3_4.md`](SERVICOS_3_4.md) | Evolução de cada etapa |
| Agenda e conflitos | [`AGENDAMENTOS_4_1.md`](AGENDAMENTOS_4_1.md) a [`AGENDAMENTOS_4_5.md`](AGENDAMENTOS_4_5.md) | Evolução da API, formulário, gravação, calendário e cancelamento |
| Design e mobile | [`DESIGN_SYSTEM_5_1.md`](DESIGN_SYSTEM_5_1.md), [`RESPONSIVIDADE_5_2.md`](RESPONSIVIDADE_5_2.md) | Paleta e histórico de adaptação; QA recente prevalece |

## Hierarquia de fatos e atenção a conflitos

1. **GitHub real no SHA consultado**, arquivos atuais, histórico, PRs, CI e environment.
2. **Evidência autenticada do site realmente publicado**, indicando data/cenário e limites.
3. **`STATUS.md` recente**, com referências verificáveis.
4. Guias operacionais (este índice, continuidade, backups, deploy, testes) — procedimento, não prova de execução.
5. `README.md` e documentos históricos por microtarefa.
6. Relatos de outras conversas e hipóteses — registrar como tal, não como observação direta.

**Nunca usar “CI success” como “site atualizado”, “página existe” como “salva no MySQL” ou “ZIP íntegro” como “backup atual completo”.** Quando houver divergência, verificar e registrar no `STATUS.md`.

## Registro obrigatório ao concluir (orientação atualizada em 17/11/2026)

Ao receber confirmação de teste/aprovação do responsável, **antes de responder à conclusão**, atualizar e integrar documentação no GitHub. Conferir primeiro `STATUS.md` e o documento da funcionalidade, depois ajustar `README.md`, guias operacionais e arquitetura/QA quando o estado ou procedimento mudou. Informar fonte e escopo da validação; não confundir relato de teste manual com automação. Corrigir resumos ativos que ainda exibem pendências superadas; preservar o histórico datado. Esta regra está detalhada em [`AGENTS.md`](../AGENTS.md) e [`GUIA_DE_CONTINUIDADE.md`](GUIA_DE_CONTINUIDADE.md).

## Rotina para manter o índice útil

- Ao finalizar tarefa: atualizar `STATUS.md` **no PR pertinente**; se mudar procedimento, atualizar manual correspondente e links aqui.
- Ao abrir uma sessão: buscar mudanças posteriores ao último SHA registrado, PRs de outra sessão e informações vencidas antes de executar.
- Ao iniciar deploy: abrir **primeiro** o manual de backups e depois o de publicação. Registrar aprovação explícita, backup e run.
- Não colocar tokens, segredos, dumps SQL, caminhos de credenciais ou dados de terceiros na documentação pública.

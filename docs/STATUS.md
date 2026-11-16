# STATUS — AgenDay 2.0

**Atualizado em:** 16/11/2026  
**Repositório:** `Emds96/AgenDay-PI`  
**Referência de desenvolvimento:** `dev-feira-uninorte`  
**Último commit da branch consultado nesta revisão:** `f807327811895bc97827929f4e30c2e60a180110` (13/11/2026)  
**Natureza desta revisão:** leitura de código, documentos, histórico recente e GitHub Actions. **Não houve testes integrados no site ou no banco durante esta revisão.**

## Objetivo e prazos

Preparar o AgenDay, sistema web PHP/MySQL de gerenciamento de agendamentos, para apresentação na feira tecnológica da UniNorte. O `README.md` informa **27/11/2026** como data da feira; em conversa com o responsável também foi mencionado **24/11/2026** como prazo de entrega do projeto. **Confirmar e registrar separadamente o prazo de conclusão e o dia da apresentação**; até lá, priorizar o cenário mais curto sem tratá-lo como data oficialmente confirmada.

## Visão rápida

| Frente | Estado verificado | Validação ainda necessária |
| --- | --- | --- |
| Banco relacional v2 | `sql/BD_v2.sql` com 8 tabelas e vínculos por estabelecimento; README registra importação em banco independente | Conferir esquema e dados no MySQL real antes de novas operações |
| Cadastro/login/logout | Fluxos de código com `password_hash`/`password_verify`, sessão, CSRF e verificação de páginas privadas | Teste de ponta a ponta na hospedagem |
| Clientes | Cadastro, pesquisa e foto opcional implementados em PHP | Testar gravação, pesquisa, upload e isolamento entre estabelecimentos |
| Serviços | Cadastro, duração, preço e pesquisa implementados em PHP | Testar valores, duplicidade e leitura no banco |
| Funcionários | Cadastro, busca, upload e relação N:N com serviços implementados | Testar vínculos, buscas, arquivos e isolamento |
| Agenda/calendário | Código para criar, listar e cancelar agendamentos; regra de conflitos de horários e trava transacional | Testar PHP/MySQL, cancelamento, horários simultâneos e dados de dois estabelecimentos |
| Tema e responsividade | Arquivos CSS/JS e ajustes aplicados | Inspeção visual real em celular, tablet e desktop |
| Comandas, histórico e financeiro | Páginas legadas presentes, sem fluxo de negócio integrado validado | Definir escopo mínimo ou retirar opções da demonstração |
| Perfil, configurações e alterar senha | Páginas protegidas, com conteúdo/ações ainda ilustrativos ou incompletos | Implementar ou sinalizar/ocultar funcionalidades não operacionais |
| CI | GitHub Actions com lint PHP e rotinas locais; execução de lint com sucesso no histórico consultado | Acompanhar execuções futuras e não confundir CI verde com teste online |
| Publicação | Workflow de validação/publicação controlada e documentação presentes | **Estado do site em produção não confirmado nesta revisão** |

> **Atenção:** “implementado no código” não significa “validado em produção”. As páginas podem existir e estar acessíveis sem todas as ações funcionarem.

## Estado da publicação — esclarecer antes de modificar

- Site independente informado pelo responsável: `https://agenday-sistema.infinityfree.io/`.
- `README.md` e `docs/PUBLICACAO_AUTOMATICA.md` registram que a versão atualizada **não foi publicada** e que a publicação automatizada foi preparada com bloqueios de autorização.
- O workflow `.github/workflows/agenday-publicar.yml` está configurado para validação e publicação controlada a partir da `dev-feira-uninorte`; a publicação exige variáveis/segredos específicos. Uma execução de workflow bem-sucedida **não é prova de que houve upload**.
- O responsável relatou uma publicação do site independente. Pode haver upload manual, versão anterior publicada ou outro mecanismo. **Não afirmar qual versão está online até verificar o site, os arquivos implantados e os registros adequados do deploy.**
- Não ativar publicação, editar secrets, reimportar SQL ou modificar o InfinityFree sem autorização e backup.

Referência: [publicação controlada](PUBLICACAO_AUTOMATICA.md).

## Testes e evidências

**Verificações identificadas no repositório:**

- `php tests/agendamento-validacao.php`: validações de entrada, sem banco.
- `php tests/agendamento-cancelamento.php`: regras auxiliares, sem prova de concorrência real.
- `node tests/responsividade-check.js`: estrutura e eventos de interface; não substitui teste visual.
- `.github/workflows/php-lint.yml`: checagem de sintaxe PHP. Uma execução de 13/11/2026 foi registrada como bem-sucedida na consulta ao GitHub Actions.

**Nesta atualização de documentação:** não foram executados PHP, Node, navegação autenticada ou operações de banco; apenas leitura de repositório e metadados da CI. O estado real da hospedagem continua pendente de confirmação.

O checklist de integração mais importante está em [AGENDAMENTOS_4_5.md](AGENDAMENTOS_4_5.md), complementado por [RESPONSIVIDADE_5_2.md](RESPONSIVIDADE_5_2.md).

## Próximas prioridades propostas

1. **Confirmar prazo:** distinguir entrega exigida pelo professor (24/11, citado em conversa) de feira (27/11, documentada no README).
2. **Levantar o estado do site publicado** sem alterar dados: identificar versão implantada, método de upload e diferenças em relação a `dev-feira-uninorte`.
3. **Executar testes integrados controlados** com dados fictícios no ambiente independente: autenticação; clientes, serviços e funcionários; agendamentos; cancelamento; conflitos; isolamento de duas empresas.
4. **Definir escopo da demonstração:** decidir se comandas, histórico, financeiro, perfil, configurações e alterar senha terão implementação mínima ou serão sinalizados/ocultados no menu para não sugerir recursos inexistentes.
5. **Conferir experiência em telas reais**, sobretudo celular, formulários, calendário, mensagens de erro, upload e navegação.
6. **Preparar dados fictícios, roteiro e ensaio da feira**, com plano de contingência caso internet ou hospedagem falhe.
7. **Após autorização e evidências**, reconciliar documentação de publicação e orientar eventual PR/deploy. Não executar merge na `main` por padrão.

Essa lista é uma **proposta de prioridades**, não um backlog já aprovado ou uma confirmação de que algo foi testado.

## Pontos de atenção

- Os documentos por microtarefa foram escritos em datas diferentes e podem retratar etapas superadas. Em caso de contradição, consultar primeiro os arquivos atuais da `dev-feira-uninorte`, os commits e as evidências recentes.
- O `docs/MODELO_BANCO_V2.md` descreve a fase inicial de planejamento; o README contém registro posterior da importação do banco. Não tomar o texto inicial como estado atual.
- O código de serviços ainda usa o nome legado `cad.servicos.php`; não renomear sem avaliar todas as referências.
- Arquivos privados de conexão e credenciais não devem ser commitados. A `main` do fork e o repositório original não devem receber alterações por implicação.
- Alterações de arquivos, branches, PRs, banco e deploy exigem autorização prévia do responsável.

## Passagem de contexto para uma nova conversa

Em uma sessão nova, solicitar:

> Trabalhe em `Emds96/AgenDay-PI`, branch `dev-feira-uninorte`. Leia `AGENTS.md`, `docs/STATUS.md`, `README.md` e os documentos específicos da tarefa. Verifique commits recentes e eventual divergência com o status; diga o que está confirmado, o que falta e recomende o próximo passo. Não faça alterações sem minha autorização.

**Como atualizar este arquivo depois:** registrar a data, o commit-base, entregas comprovadas, testes realmente realizados, limitações, decisões aprovadas e próximo passo; preferir a atualização no PR da mesma tarefa. Não apagar o histórico técnico em `docs/`.

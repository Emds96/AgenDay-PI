# STATUS — AgenDay 2.0

**Atualizado em:** 16/11/2026  
**Repositório:** `Emds96/AgenDay-PI`  
**Referência de desenvolvimento:** `dev-feira-uninorte`  
**Último commit da branch consultado nesta revisão:** `682b1be4df7ebdaaad7196989482d4c707ab49b0` (16/11/2026; merge da documentação inicial)  
**Natureza desta revisão:** leitura de código, documentos, histórico recente, GitHub Actions e páginas/arquivos públicos do site. **Não houve testes integrados autenticados nem operações no banco durante esta revisão.**

## Objetivo e prazos

Preparar o AgenDay, sistema web PHP/MySQL de gerenciamento de agendamentos, para apresentação na feira tecnológica da UniNorte.

- **24/11/2026:** prazo para ter o projeto pronto, conforme relato do responsável sobre a orientação do professor; **ainda não confirmado por um documento oficial**. Adotar como **prazo operacional de conclusão** para planejamento.
- **27/11/2026:** data da feira informada no `README.md`; **aguarda confirmação independente**. Planejar demonstração e ajustes finais sem confundir com o prazo de conclusão.
- Antes de mudar esses registros, confirmar com o responsável e atualizar este arquivo e o `README.md`.

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
| Publicação | **Publicação FTPS confirmada pelo GitHub Actions em 13/11/2026**; página de login e CSS 2.0 acessíveis no site independente em 16/11/2026 | Confirmar commit exato em produção, execução integrada das funcionalidades e estado atual dos controles de publicação |

> **Atenção:** “implementado no código” não significa “validado em produção”. As páginas podem existir e estar acessíveis sem todas as ações funcionarem.

## Estado da publicação — evidências de 16/11/2026

- Site independente: `https://agenday-sistema.infinityfree.io/`. O endereço respondeu com a página de login pública; arquivos CSS `css/design-system.css` e `css/responsivo.css` também foram acessados publicamente.
- GitHub Actions: o [workflow manual de 13/11/2026, execução 43518952935](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935), concluiu com **sucesso** os jobs **Validar código e pacote**, **Testar FTPS sem publicar arquivos** e **Enviar ao InfinityFree somente com autorização**. Portanto, há **evidência concreta de publicação pelo workflow**, ao contrário da afirmação anterior de que o sistema não havia sido publicado.
- A execução de publicação acima usou o commit `f807327811895bc97827929f4e30c2e60a180110` (13/11/2026). **Não foi confirmado se a hospedagem contém commits posteriores** nem se houve outro upload fora do GitHub Actions.
- O workflow `.github/workflows/agenday-publicar.yml` continua condicionado às variáveis `AGENDAY_DEPLOY_ENABLED=true` e `AGENDAY_DEPLOY_MODE=publish`, além de dependências de ambiente. A execução bem-sucedida demonstra que o job publicou naquele momento, **não prova o estado atual de variáveis/secrets, nem garante que todo push futuro publicará**. A execução de 16/11 relacionada à branch estava pendente na última consulta.
- **Limite da verificação:** foram acessados apenas recursos públicos; não houve login, testes com banco de dados, comparação integral do conteúdo publicado ou alteração de servidor. Não declarar o aplicativo funcional até testar.
- O `README.md` e `docs/PUBLICACAO_AUTOMATICA.md` continham descrições históricas de publicação desativada, agora conciliadas com as evidências. Não editar segredos, acionar publicação, importar SQL ou alterar hospedagem sem autorização e backup.

Referência: [publicação controlada](PUBLICACAO_AUTOMATICA.md).

## Testes e evidências

**Verificações identificadas no repositório:**

- `php tests/agendamento-validacao.php`: validações de entrada, sem banco.
- `php tests/agendamento-cancelamento.php`: regras auxiliares, sem prova de concorrência real.
- `node tests/responsividade-check.js`: estrutura e eventos de interface; não substitui teste visual.
- `.github/workflows/php-lint.yml`: checagem de sintaxe PHP. Uma execução de 13/11/2026 foi registrada como bem-sucedida na consulta ao GitHub Actions.

**Nesta atualização de documentação:** não foram executados PHP, Node, navegação autenticada ou operações de banco. Houve leitura do repositório, dos jobs do GitHub Actions e dos recursos públicos da hospedagem. A publicação FTPS de 13/11 foi comprovada pela CI, mas o commit exato atualmente implantado e os fluxos de banco ainda não foram confirmados.

O checklist de integração mais importante está em [AGENDAMENTOS_4_5.md](AGENDAMENTOS_4_5.md), complementado por [RESPONSIVIDADE_5_2.md](RESPONSIVIDADE_5_2.md).

## Estratégia de branches aprovada — 16/11/2026

- **`dev-feira-uninorte`** é a branch de integração do desenvolvimento: novas tarefas devem preferencialmente nascer de branches curtas baseadas nela, com Pull Requests direcionados a ela.
- **`main` do fork `Emds96/AgenDay-PI`** será a referência da versão oficial/estável **depois** da validação e aprovação. No momento, continua preservada e contém o workflow de deploy antigo, cuja configuração precisa ser revista antes de qualquer merge.
- **Promover** significa abrir e aprovar um PR de `dev-feira-uninorte` para a `main` do fork. Isso **não altera o repositório `oliv-gabriel/AgenDay-PI`**; não há promoção automática, nem obrigação de esperar o dia da feira após aprovação da versão estável.
- Exigir autorização específica para promoção, alterações de workflow e deploy. O merge na `main` pode disparar o workflow legado; é indispensável revisar o risco antes.

## Próximas prioridades propostas

1. **Confirmar os dois marcos:** 24/11 como prazo operacional de conclusão (relato do responsável) e 27/11 como data documentada da feira; validar ambos com professor/organização.
2. **Identificar a versão exata do site publicado** e diferenças em relação à `dev-feira-uninorte`, apesar de já existir evidência de publicação FTPS em 13/11.
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

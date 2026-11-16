# STATUS — AgenDay 2.0

**Atualizado em:** 16/11/2026  
**Repositório:** `Emds96/AgenDay-PI`  
**Referência de desenvolvimento:** `dev-feira-uninorte`  
**Último commit da branch consultado nesta revisão:** `6596bebc7a9b56b48dc136123266c9946780dd5c` (16/11/2026; antes das correções QA)  
**Fonte da evidência funcional:** relatório detalhado da auditoria de 16/11/2026 executada em outra sessão com ZERO Browser MCP, fornecido pelo responsável. **Esta atualização registra os resultados relatados; não repete os testes autenticados.** A leitura estática do código confirmou alguns problemas.

## Objetivo e prazos

Preparar o AgenDay, sistema web PHP/MySQL de gerenciamento de agendamentos, para apresentação na feira tecnológica da UniNorte.

- **24/11/2026:** prazo para ter o projeto pronto, conforme relato do responsável sobre a orientação do professor; **ainda não confirmado por um documento oficial**. Adotar como **prazo operacional de conclusão** para planejamento.
- **27/11/2026:** data da feira informada no `README.md`; **aguarda confirmação independente**. Planejar demonstração e ajustes finais sem confundir com o prazo de conclusão.
- Antes de mudar esses registros, confirmar com o responsável e atualizar este arquivo e o `README.md`.

## Visão rápida

| Frente | Estado verificado | Validação ainda necessária |
| --- | --- | --- |
| Banco relacional v2 | `sql/BD_v2.sql` com 8 tabelas e vínculos por estabelecimento; README registra importação em banco independente | Conferir esquema e dados no MySQL real antes de novas operações |
| Cadastro/login/logout | **Aprovados no cenário QA reportado:** duas contas de teste, login, logout, reautenticação e validações | Recuperação e alteração de senha não funcionais; revalidar após correções |
| Clientes | **Aprovados no cenário QA reportado:** cadastro, pesquisa, persistência, PNG e arquivo inválido | Aprofundar autorização de endpoints e regressão |
| Serviços | **Aprovados no cenário QA reportado:** cadastro, preço, duração, pesquisa e rejeição de duplicidade/valores inválidos | Regressão e demais casos-limite |
| Funcionários | **Aprovados no cenário QA reportado:** cadastro, pesquisa, PNG, vínculo a serviço e persistência | Regressão e demais casos-limite |
| Agenda/calendário | **Aprovados no cenário QA reportado:** criação, exibição, recarga, bloqueio de sobreposição, cancelamento, reutilização e listagens isoladas entre duas contas | Concorrência realmente simultânea e verificação aprofundada de isolamento ainda pendentes |
| Tema e responsividade | **Falha QA-01:** menu lateral permanece fora da tela em 320, 390, 768 e 1366 px; pequenos transbordamentos internos em modais | Corrigir menu e repetir testes em múltiplas larguras |
| Comandas, histórico e financeiro | Páginas legadas presentes, sem fluxo de negócio integrado validado | Definir escopo mínimo ou retirar opções da demonstração |
| Perfil, configurações e alterar senha | Páginas protegidas, com conteúdo/ações ainda ilustrativos ou incompletos | Implementar ou sinalizar/ocultar funcionalidades não operacionais |
| CI | GitHub Actions com lint PHP e rotinas locais; execução de lint com sucesso no histórico consultado | Acompanhar execuções futuras e não confundir CI verde com teste online |
| Publicação | Publicação FTPS confirmada em 13/11; **auditoria QA reportou fluxos PHP/MySQL funcionais em 16/11** e igualdade de hashes de três arquivos públicos com GitHub | Commit integral do PHP em produção continua sem verificação; regressão após cada publicação |

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

**Verificação documental anterior:** não executou PHP, Node, navegação autenticada ou operações de banco; leu repositório, CI e recursos públicos. **Desde então, o relatório QA de 16/11 recebido nesta sessão registra testes reais de autenticação e persistência na hospedagem**, detalhados abaixo. O relato é evidência fornecida pelo responsável e não foi reproduzido nesta revisão do documento.

O checklist de integração mais importante está em [AGENDAMENTOS_4_5.md](AGENDAMENTOS_4_5.md), complementado por [RESPONSIVIDADE_5_2.md](RESPONSIVIDADE_5_2.md).


## Auditoria funcional no InfinityFree — 16/11/2026 (ZERO Browser MCP)

**Origem:** relatório de testes encaminhado pelo responsável, gerado em sessão dedicada de QA com ZERO Browser MCP (Microsoft Edge). **Evidência relatada, não reexecutada neste commit de documentação.** Execução em https://agenday-sistema.infinityfree.io/ contra a versão publicada, usando registros fictícios. Branch de referência no momento do relatório: `dev-feira-uninorte`, commit `6596bebc7a9b56b48dc136123266c9946780dd5c`.

### Cobertura e resultados reportados

| Área | Resultado reportado | Observações |
| --- | --- | --- |
| Cadastro/login/logout | **Aprovado** | Duas contas novas; senha divergente, e-mail inválido/duplicado e credenciais incorretas rejeitados; reautenticação bem-sucedida |
| Clientes/serviços/funcionários | **Aprovado** | Cadastros persistiram após recarga; pesquisas, vínculo funcionário–serviço e fotos PNG testados |
| Validações de cadastro | **Aprovado nos casos testados** | Serviço duplicado, duração zero, preço negativo e arquivo inválido rejeitados |
| Agenda e calendário | **Aprovado nos casos testados** | Criou #3 (20/11, 14h–14h45), bloqueou conflito às 14h30, cancelou e preservou histórico; navegou meses |
| Segundo estabelecimento | **Aprovado nas listagens normais** | Conta B não exibiu registros A; #4 e #5 da B foram criados, cancelados e permaneceram como histórico; horário cancelado reutilizado |
| API agenda | **Aprovado nos casos testados** | Respostas HTTP 200 e 422; restrição sem login e filtros de empresa observados |
| Segurança | **Parcial** | Rotas privadas redirecionaram; NÃO houve teste extensivo de autorização entre contas, concorrência simultânea ou ataque |
| Responsividade | **Falhou no menu** | 320/390/768/1366 px: classe `show` aplicada mas painel deslocado à esquerda; demais páginas sem overflow geral |
| Perfil/configuração/senha | **Falhou ou incompleto** | Dados demonstrativos fixos e formulários não funcionais |
| Comandas/histórico/financeiro | **Incompleto** | Páginas disponíveis no menu, sem fluxo de negócio funcional comprovado |
| Correspondência GitHub × site | **Parcial** | Relato informa hashes iguais de `css/responsivo.css`, `css/design-system.css` e `js/login.js`; PHP integral sem commit verificável |

### Registro de defeitos

| ID | Prioridade QA | Descrição | Evidência/reprodução relatada |
| --- | --- | --- | --- |
| **QA-01** | ALTO | Offcanvas do menu aparece como aberto no DOM mas permanece fora da tela | Abrir `agenda.php`, clicar no menu; reproduzido em 320, 390, 768 e 1366 px |
| **QA-02** | ALTO | Alterar senha mostra formulário de cliente/upload, não troca de senha | Abrir `alterar-senha.php` |
| **QA-03** | ALTO | “Esqueceu a senha?” aponta para `#`, sem recuperação | Abrir a tela de login e clicar no link |
| **QA-04** | MÉDIO | Perfil/configuração exibem dados e ações ilustrativos | Abrir `perfil.php` ou `config.php` autenticado |
| **QA-05** | MÉDIO | Comandas, histórico e financeiro não disponibilizam operações reais | Abrir os três módulos pelo menu |
| **QA-06** | MÉDIO | Erro JavaScript em `js/button-add.js:4` na configuração | Abrir `config.php` e inspecionar console |
| **QA-07** | BAIXO | Cabeçalhos legados exibem “Barbearia” independentemente da empresa | Consultar páginas legadas sob conta fictícia |
| **QA-08** | BAIXO | Modais com cerca de 12 px de overflow interno horizontal | Abrir modais de cadastro em larguras móveis |

**Inspeção estática adicional nesta etapa:** `alterar-senha.php` contém campos de cliente; o link de recuperação em `index.php` usa `href="#"`; `js/button-add.js` tenta chamar `addEventListener` sem conferir se existe o elemento. A causa CSS precisa ser corrigida e revalidada; não se deve declarar QA-01 resolvido antes de teste visual real.

### Dados de teste e limites

- **Conta QA A:** `QA_AGENDAY_20261116_A`, com serviços, funcionários, clientes e duas imagens PNG fictícias. Agendamento **#3 cancelado**.
- **Conta QA B:** `QA_AGENDAY_20261116_B`, com serviço, funcionário, cliente e agendamentos **#4 e #5 cancelados**.
- Segundo o relatório, **nenhum dos três agendamentos ficou ativo**; houve logout ao fim. Uma submissão de cadastro interrompida pode ter criado conta adicional: **não confirmado**.
- O relatório cita 205 requisições inspecionadas sem falhas HTTP na varredura, mas com erro JS em configuração. Não foi exportado arquivo de screenshots para o repositório.
- **Não testado:** agendamento verdadeiramente concorrente, cobertura completa de autorização dos endpoints, commit exato do PHP publicado e alteração/exclusão não implementadas.
- Esses resultados comprovam somente os cenários executados e relatados; **não constituem certificação integral de segurança**. Não armazenar credenciais de contas QA aqui.

### Plano de correção sequencial — um defeito por Pull Request

**Regra:** cada correção nasce de branch curta baseada na última `dev-feira-uninorte`, gera PR separado com objetivo, testes e registro de evidências. **Só iniciar a próxima correção depois de revisar/integrar a anterior**, salvo autorização diferente. Não fazer merge nem deploy sem autorização explícita.

1. **QA-01 — menu lateral (primeiro):** reproduzir/avaliar CSS de `offcanvas`; corrigir estritamente abertura/fechamento, mantendo Bootstrap e responsividade; confirmar fechamento, backdrop, navegação e viewport 320/390/768/1366 px. **Critério de aceitação:** painel visível e interativo dentro da janela ao abrir, desaparece ao fechar, sem regressão.
2. **QA-02 — alteração de senha:** implementar endpoint autenticado com senha atual, nova senha, confirmação, CSRF, `password_verify`/`password_hash` e mensagens seguras; testar persistência e sessão.
3. **QA-03 — recuperação de senha:** decidir mecanismo apropriado à hospedagem (envio de e-mail confiável, token de uso único/validade e limitação de tentativas), ou remover/desativar o link até ser implementado; jamais oferecer recuperação insegura.
4. **QA-04 — perfil/configurações:** substituir informações fictícias por dados da conta e definir quais ações realmente serão implementadas, com permissões e validações.
5. **QA-05 — comandas/histórico/financeiro:** decidir, com o responsável, se implementar MVP ou ocultar recursos não funcionais para a feira.
6. **QA-06 — erro JS da configuração:** isolar e remover/corrigir referência inválida a elemento, com teste de console.
7. **QA-07 — textos fixos:** corrigir identificação do estabelecimento nos cabeçalhos das páginas legadas.
8. **QA-08 — overflow dos modais:** ajustar dimensões internas e testar em celular/tablet.

**Entre PRs:** validar CI, revisar diferenças e autorizar merge; após merge/deploy controlado, executar regressão usando ZERO Browser MCP. Atualizar este STATUS com número do PR, commit, resultado de teste e pendências. Prioridade geral: navegação e apresentação funcional até o prazo operacional de 24/11.


## Estratégia de branches aprovada — 16/11/2026

- **`dev-feira-uninorte`** é a branch de integração do desenvolvimento: novas tarefas devem preferencialmente nascer de branches curtas baseadas nela, com Pull Requests direcionados a ela.
- **`main` do fork `Emds96/AgenDay-PI`** será a referência da versão oficial/estável **depois** da validação e aprovação. No momento, continua preservada e contém o workflow de deploy antigo, cuja configuração precisa ser revista antes de qualquer merge.
- **Promover** significa abrir e aprovar um PR de `dev-feira-uninorte` para a `main` do fork. Isso **não altera o repositório `oliv-gabriel/AgenDay-PI`**; não há promoção automática, nem obrigação de esperar o dia da feira após aprovação da versão estável.
- Exigir autorização específica para promoção, alterações de workflow e deploy. O merge na `main` pode disparar o workflow legado; é indispensável revisar o risco antes.

## Próximas prioridades propostas

1. **QA-01:** corrigir o menu lateral em PR isolado e repetir o teste visual no navegador real.
2. **QA-02 e QA-03:** definir e corrigir alteração e recuperação de senha, sem introduzir fluxo inseguro.
3. **QA-04–QA-07:** corrigir dados ilustrativos, console e escopo dos módulos legados por PRs separados.
4. **QA-08:** refinar modais após estabilizar a navegação.
5. **Regressão:** repetir o fluxo principal auditado; ampliar testes de concorrência e autorização usando contas próprias.
6. **Documentar a versão implantada:** confirmar o commit PHP publicado e o deploy; manter CI/deploy sob autorização.
7. **Prazos e apresentação:** confirmar 24/11 e 27/11 e preparar dados de demonstração/contingência. Não promover para `main` sem versão estável e aprovação.

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

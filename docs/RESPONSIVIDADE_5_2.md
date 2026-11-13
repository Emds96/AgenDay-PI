# Microtarefa 5.2 — Responsividade para celular e tablet

**Status:** ajustes implementados na branch `dev-feira-uninorte` do fork. **Ainda não publicados** no InfinityFree nem verificados em um navegador com backend real. O banco, a `main` e o repositório original não foram alterados.

## Entrega

- `css/responsivo.css`: folha de estilos responsiva e isolada por `body.agenday-app` ou `body.agenday-auth`. É carregada depois de `css/design-system.css` nas 11 páginas atuais.
- `index.php`: inclui `meta viewport`, alternância visível entre **Entrar** e **Criar conta** no celular e restaura a opção de cadastro ao retornar de erro no formulário.
- `js/login.js`: atualiza a opção ativa dos botões de autenticação tanto no celular quanto no layout de painel duplo do desktop. Não modifica os endpoints de cadastro/login.
- Todas as páginas administrativas: o menu lateral deixa de depender dos 20% fixos escritos no HTML; abre em largura apropriada e permanece navegável com rolagem própria.
- Clientes, funcionários e serviços: busca e botão **Adicionar** empilhados em telas pequenas; campos dos modais ocupam a largura disponível, cartões da listagem não ficam presos à altura fixa.
- Configurações, perfil e alterar senha: caixas/áreas de rolagem e formulários podem se ajustar à altura e largura do celular.
- Calendário: dimensionamento dos dias para telas estreitas, cartões com textos longos que podem quebrar linha e controles acessíveis.
- Mantém a identidade turquesa e os tokens da etapa 5.1, sem adicionar troca de tema por cliente.

## Regras de breakpoint

| Largura | Estratégia |
| --- | --- |
| Acima de 992 px | Layout de desktop mantido; menu lateral com largura razoável |
| Até 991,98 px | Ajustes para tablets; limites de largura de menu e modais |
| Até 767,98 px | Login em coluna única, busca empilhada, cards e formulários fluidos |
| Até 390 px | Calendário e cabeçalho mais compactos |

Todos os ajustes usam CSS com media queries. A navegação do login é feita com botões `type=button` e estados `aria-pressed`, preservando os formulários PHP/CSRF.

## Testes locais disponíveis

```bash
node tests/responsividade-check.js
```

O script testa a estrutura do código (presença das regras, links e controles) sem depender de servidor ou MySQL. A alternância de telas do login também foi verificada com eventos JavaScript simulados. **Esses testes não garantem que a interface esteja perfeita em um aparelho real.**

## Checklist visual obrigatório antes de publicar

1. Abrir `index.php` em 320, 375, 390 e 430 px: verificar entrada, criação de conta, teclado de e-mail, campos, alertas e erros de cadastro.
2. Abrir o menu lateral nas páginas internas em celular/tablet; verificar se nenhum item fica oculto e se a tela atrás permanece utilizável ao fechar.
3. Criar, pesquisar e visualizar cliente, funcionário e serviço; conferir foto, nomes longos, e-mail e valores.
4. Abrir modais de cadastro e rolar até **Concluir**, inclusive com teclado virtual e arquivo de foto.
5. Usar o calendário em meses com 28/29/30/31 dias, abrir um atendimento, cancelar e confirmar a mensagem de retorno.
6. Verificar a agenda com o formulário completo em 320 px, 768 px e desktop; evitar sobreposição dos controles.
7. Revisar perfil/configurações/alterar senha e confirmar que não há rolagem horizontal indevida.
8. Testar navegação por teclado e leitor de tela, incluindo `aria-pressed` do seletor de autenticação.
9. Testar Safari/iOS e Chrome/Android, onde `100dvh`, campos e modais podem se comportar de forma diferente.
10. Confirmar que login, salvamento e cancelamento ainda funcionam em PHP/MySQL real e que dados de outros estabelecimentos não aparecem.

## Limites de escopo

- **A etapa 5.2 é uma melhoria de apresentação**, não uma validação de todas as páginas legadas. Telas de financeiro/comandas e alguns formulários de perfil/configuração ainda contêm comportamentos ilustrativos/incompletos.
- Não inclui imagens novas, logo, tema White Label, alterações SQL ou publicação.
- Próximas tarefas: **5.3 — experiência da agenda e formulários** e **5.4 — mensagens, erros e estados vazios**.
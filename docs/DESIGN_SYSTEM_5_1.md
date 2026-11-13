# Design System AgenDay 2.0 — Microtarefa 5.1

**Status:** tema base incluído no código da branch `dev-feira-uninorte`. Não publicado nem validado visualmente em navegadores reais; `main` e a hospedagem não foram modificadas.

## Princípio de marca

A cor percebida no login é uma família turquesa. A etapa 5.1 preserva essa identidade e prioriza contraste legível: o turquesa claro funciona melhor como destaque; textos e botões principais usam verde escuro.

| Token | Cor | Uso sugerido |
| --- | --- | --- |
| `--ad-primary` | `#18544D` | Botões primários, menu e textos de ênfase |
| `--ad-primary-hover` | `#11675C` | Hover de botões primários |
| `--ad-accent` | `#7AD6CC` | Identidade turquesa e botões secundários legados |
| `--ad-accent-hover` | `#5CC6BA` | Hover de ações turquesa; também usado no login original |
| `--ad-accent-soft` | `#A2ECE4` | Destaques suaves; gradiente original do login |
| `--ad-accent-strong` | `#1CC1AE` | Terceira cor do gradiente original do login |
| `--ad-background` | `#E3F2FD` | Fundo claro das páginas |
| `--ad-surface` | `#FFFFFF` | Cartões e formulários |
| `--ad-surface-muted` | `#F5FBFA` | Superfícies secundárias |
| `--ad-text` | `#203B38` | Texto corrido |
| `--ad-text-muted` | `#486560` | Texto complementar |
| `--ad-border` | `#CCDFDB` | Bordas |
| `--ad-focus` | `#11675C` | Contorno de foco do teclado |

Todos os valores ficam no arquivo [`css/design-system.css`](../css/design-system.css), carregado **depois** dos CSS anteriores. Classes `agenday-app` e `agenday-auth` no `<body>` limitam as regras às páginas do sistema, preservando a estrutura legada.

## Componentes abrangidos

- Login: fonte base, foco acessível e botões na família turquesa; o degradê original é mantido.
- Páginas internas: cabeçalho, menus, cartões, listas, títulos e campos com cores e bordas consistentes.
- Ações: `.btn-primary`, `.btn-outline-primary`, `.adicionar`, `.concluir` e `.text-bg-primary` padronizados; ações perigosas do Bootstrap continuam vermelhas.
- Agenda: dia selecionado, dia atual, indicadores, controles de mês e cartões usam o verde principal. Status cancelado continua discreto.
- Fontes: pilha de fontes de sistema, sem baixar novos arquivos de fonte.
- Foco: contorno visível para navegação por teclado; respeito a `prefers-reduced-motion` na navegação.

## Exemplo de uso em componentes novos

```css
.botao-exemplo {
  color: #fff;
  background-color: var(--ad-primary);
  border-radius: var(--ad-radius-sm);
}
```

## O que esta etapa NÃO implementa

- Não há troca de tema por estabelecimento nem alteração de logo: isso poderá ser feito no futuro, com configuração persistente por conta e validação de contraste.
- Não modifica o banco de dados, regras de agendamento ou arquivos privados de conexão.
- Não reorganiza menus para telas pequenas nem reestrutura modais: isso será tratado na **5.2**.
- Não afirma que todas as telas já passaram por revisão visual: navegadores, desktop e celular precisam de validação após publicação controlada.
- Comandas e financeiro continuam com limitações funcionais legadas mesmo recebendo o tema base.

## Checklist visual para 5.2 / testes

1. Abrir login, cadastro, agenda, clientes, funcionários, serviços, configurações e perfil em navegadores reais.
2. Conferir contraste dos textos e estados hover/foco/disabled com teclado.
3. Confirmar que o CSS novo carrega após os estilos antigos e que o gradiente do login continua visível.
4. Verificar modais, barra lateral e formulários nos tamanhos de celular, tablet e desktop.
5. Corrigir qualquer conflito visual antes de publicação definitiva.
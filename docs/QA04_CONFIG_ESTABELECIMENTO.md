# QA-04 — Configurações do estabelecimento (18/11/2026)

## Escopo autorizado

O responsável autorizou implementação, integração e deploy:
1. Nome, e-mail e telefone reais da conta autenticada.
2. Editar somente nome e telefone. E-mail de login somente leitura.
3. Salvar no MySQL, com validações e mensagens.
4. Exigir sessão, CSRF e atualizar somente o estabelecimento da sessão, por consultas preparadas.
5. Remover exemplos da seção básica, corrigir erro JavaScript e adaptar ao celular.

**Fora de escopo:** edição de e-mail, CNPJ, endereço, horários, foto/logo, dados do proprietário e novas tabelas/migrações.

## Implementação

- config.php: leitura e atualização SELECT/UPDATE limitadas ao ID de estabelecimento da sessão. Apenas nome e contato editáveis; e-mail real em somente leitura, com escape HTML. Feedback seguro de sucesso/erro.
- lib/configuracao-estabelecimento.php: validação do nome (2–120 caracteres), telefone opcional (8–15 dígitos, máximo 20 caracteres) e escape HTML.
- js/button-add.js: corrige exceção antiga de evento sem elemento e referência inválida ao campo de contato. Formulário básico não depende desse script.
- tests/configuracao-estabelecimento.php: teste isolado das validações e escape HTML.
- Atualização de sessão do nome após gravação, para atualizar o cabeçalho da agenda e cadastros imediatamente.
- POST + token CSRF da sessão + prepared statement + POST/Redirect/GET. Nem e-mail nem ID de estabelecimento são aceitos como campos editáveis.
- Formulário Bootstrap 5 responsivo; nenhuma informação ilustrativa de endereço, horário e redes sociais é exibida como real.

## Critérios de aceite

- [ ] CI, PHP lint e testes unitários aprovados.
- [ ] Backup recente verificado antes de deploy, workflow com publicar=success e flags restauradas.
- [ ] QA das informações reais e atualização persistente após recarga em conta de teste.
- [ ] Mensagem em erros de validação, CSRF inválido e falhas de banco.
- [ ] Sem overflow em 320/390 px, navegação funcional.
- [ ] Teste de isolamento de estabelecimentos A/B ainda é uma auditoria separada.
- [ ] Após validação humana, atualizar documentação antes de declarar encerrado.

## Limitações

Testes isolados/CI não são prova de persistência no MySQL de produção. A tela perfil.php e ajustes de localização/horário ficam para outra etapa. Não publicar credenciais, dados de terceiros ou dumps.

**Estado:** código preparado; atualizar PR, SHA, deploy, testes E2E e evidência ao concluir.

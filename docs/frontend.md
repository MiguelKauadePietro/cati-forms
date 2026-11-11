# Frontend — CATI Forms

## Tecnologias

- Vue 3 com TypeScript, integrado ao Laravel por Inertia.js.
- **Vuetify 3 é a biblioteca oficial de componentes.** Reutilize seus componentes nativos quando adequados.
- Tailwind CSS, Reka UI e componentes anteriores podem continuar no starter kit enquanto estiverem em uso; preserve o legado até uma migração explícita.
- Não introduzir shadcn-vue nem combinar Vuetify e Reka UI dentro de uma mesma **nova** interface.

## Responsabilidades

- Organizar páginas por funcionalidade em `resources/js/pages` e componentes compartilhados em `resources/js/components`, respeitando a estrutura real do projeto.
- Apresentar dados enviados pelo Laravel/Inertia, controlar interações do usuário, estado de envio e feedback visual.
- A validação do frontend melhora a experiência, mas **não substitui** a validação do backend.
- Não implementar lógica de autorização, consultas de banco ou regras críticas apenas no Vue.

## Experiência e interface

- Seguir identidade visual coerente e simples, com hierarquia de informação, tipografia e espaçamento consistentes.
- Usar Vuetify para inputs, botões, alertas, feedback de carregamento e layout responsivo.
- Associar rótulos aos campos; fornecer mensagens de erro visíveis e acessíveis.
- Evitar envios duplicados e exibir estados de carregamento, sucesso e erro.
- Garantir uso em celulares e desktops, especialmente na página pública de respostas (RNF02).
- Aplicar boas práticas de acessibilidade e não adicionar complexidade estética sem propósito.

## Fluxos principais

- Cadastro/login: username e senha; mostrar erros retornados pelo servidor e redirecionar corretamente.
- RF03: Inertia entrega `forms/Create`; `resources/js/api/forms.ts` envia JSON
  por fetch para `POST /api/v1/forms`, sem `router.post` ou `useForm.post` do
  Inertia. Os tipos de entrada, registro, erros e a chamada ficam nesse módulo.
  Ler `XSRF-TOKEN` apenas no envio, decodificar e enviar em `X-XSRF-TOKEN`, com
  `credentials: same-origin`, `Accept` e `Content-Type: application/json`.
  Não acessar document durante SSR. 201 limpa os campos e mostra sucesso; 422
  mostra erros por campo; 401/419 orientam renovar o acesso em outra aba para
  preservar o preenchimento. Falhas de rede/servidor não repetem POST
  automaticamente: o resultado anterior pode ser desconhecido.
- Criação de formulários: suportar os três tipos de pergunta previstos em `docs/requirements.md`.
- Resposta pública: permitir acesso sem conta, sem exibir dados internos ou respostas de terceiros.
- Consulta e exportação: telas privadas para qualquer usuário autenticado.

## Skill aplicável

- `ui-taste`: consultar em tarefas de interface para orientar UX, hierarquia visual, responsividade e acessibilidade, **sempre respeitando a adoção do Vuetify 3**.
- Em tarefas que incluam backend, consulte também `docs/backend.md` e suas skills pertinentes.

## Evolução RF04

forms/Create mantém perguntas no estado Vue até o único POST de criação. Cada
item tem chave local estável e enunciado; erros questions.N.prompt são mostrados
por item, e erros de posição antigos são descartados ao remover. Controles ficam
bloqueados durante envio; falhas preservam os dados, sucesso limpa a lista.

## RF05–RF06

QuestionEditor compartilha enunciado e alternativas entre os tipos. A página
continua dona do envio. Tipos e dados de transporte ficam em api/forms.ts; o
preenchimento com chaves locais fica em types/forms.ts. Alternativas ocultas
pela escolha de discursiva não são enviadas. Remoção limpa erros antigos por
índice. Não há campos de seleção/justificativa de participante no editor.

## Dashboard — meus formulários

Inertia entrega `Dashboard.vue`, sem consultar formulários na rota web. Ao montar
no navegador, a página chama `listMyForms(page)` em `resources/js/api/forms.ts`,
que faz GET `/api/v1/forms/mine` com cookies da sessão e recebe JSON paginado.
`FormController::mine` usa a relação do usuário autenticado, ordena por data e ID
decrescentes e serializa com `FormResource`, sem carregar perguntas.

A tela Vuetify mostra título, descrição, data localizada em pt-BR e paginação,
com estados de carregamento, vazio e erro com nova tentativa. A chamada fica em
`onMounted`, sem acesso à sessão do navegador durante SSR. O botão de criação
continua usando `forms.create`. Não há ações de detalhes, edição ou exclusão.

A rota `/` mantém o nome `home` e redireciona visitantes para `/login` e usuários
autenticados para `/dashboard`. O redirecionamento não é middleware global;
futuras rotas públicas permanecem possíveis. `Welcome` saiu do fluxo inicial,
a demonstração deixou o dashboard e os links do starter kit saíram da barra lateral.

Verificação: testes de `DashboardTest`, `ExampleTest` e `ListMyFormsTest` cobrem
página, redirecionamentos e consulta com SQLite isolado. O contrato e os exemplos
estão em [API](api/v1/forms.md) e [Postman](testes/postman.md).

Validação desta melhoria: 81 testes direcionados passaram (407 assertions),
incluindo a criação RF03–RF06, a nova consulta e os redirecionamentos. Pint,
PHPStan, TypeScript, `npm run check` e build do cliente passaram. Prévia Chromium
com respostas simuladas verificou listagem, paginação, recuperação de erro e
estado vazio em 390 px (claro) e 1440 px (escuro), sem overflow horizontal.
A prévia não acessou o banco; não houve execução no Postman nem build SSR.
Os estilos são locais à página: as utilities do Vuetify estão desabilitadas na
configuração existente. Não houve alteração nessa configuração ou em migrations.

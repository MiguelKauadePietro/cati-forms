# RF03 — criação de formulários

Atende RF03, RN02 e RN03. Respeita RN07 ao não disponibilizar edição ou exclusão
de formulários. Título e descrição obrigatórios; autoria registrada sem limitar
acesso por criador. Perguntas discursivas foram acrescentadas pelo RF04; publicação, respostas e
listagem ainda não estão implementadas.

## Percurso do código

1. Dashboard oferece acesso a `GET /forms/create`, protegido por auth. Essa rota
   entrega a página `resources/js/pages/forms/Create.vue` pelo Inertia.
2. A página usa Vuetify para os campos, carregamento e mensagens. O submit chama
   `createForm` de `resources/js/api/forms.ts`, que envia JSON por fetch.
3. `bootstrap/app.php` registra `routes/api.php` sob `/api/v1` com web; a rota
   `POST /forms` exige auth:web. Sessão e CSRF são os mesmos do Fortify.
4. `StoreFormRequest` autoriza usuário autenticado e valida os dados no servidor.
   Campos inválidos geram JSON 422 antes de executar o controller.
5. `Api/V1/FormController::store` delega a `CreateForm::handle`, passando o
   usuário da sessão e título, descrição e perguntas validados. A Action grava
   o conjunto em transação, definindo autoria, vínculo e ordem no servidor.
6. `FormResource` limita os campos da resposta e o controller retorna 201.
   A página limpa os campos e anuncia sucesso. Erros preservam o preenchimento.

Uma **migration** define a estrutura; o **model** representa registros e relações;
o **Form Request** valida entradas. A Action foi acrescentada pelo RF04 para
coordenar várias gravações dependentes. Não existe controller web salvando dados
em paralelo. O [registro RF04](../explicacao.md) explica a transação e as relações.
O [guia Postman](../testes/postman.md) concentra os JSONs de testes manuais.

## Validação histórica da entrega RF03

- Suíte Pest completa: **85 testes, 354 asserções**, todos aprovados em SQLite
  isolado. Inclui 21 cenários novos de API/página e regressões existentes.
- Autenticação isolada: **27 testes, 132 asserções**, todos aprovados.
- PHPStan sem erros, TypeScript e `npm run check` aprovados.
- Pint serial (`vendor/bin/pint --test`) aprovado. O target `make lint` usa
  paralelismo e foi impedido pelo bloqueio de sockets do sandbox; não alteramos
  o Makefile para contornar uma restrição específica do ambiente.
- Builds cliente e SSR (`npm run build:ssr`) aprovados. Permanece o aviso
  não bloqueante sobre a dependência opcional fontaine.
- Chromium: renderização SSR e hidratação nos temas claro/escuro em 390×844 e
  1440×1000, sem overflow horizontal e com envio visível. Respostas simuladas
  verificaram sucesso, 422, 401, 419, 500, falha de rede, bloqueio de envio
  duplicado, corpo JSON e cabeçalho XSRF. Nenhuma exceção de execução observada.
  Screenshots e relatório temporários em `/tmp/cati-rf03-preview/`.

O preview usa somente dados fictícios e API simulada: não substitui o teste
Pest do processamento Laravel nem afirma validação ponta a ponta no MySQL.
Os servidores temporários da conferência foram encerrados. Naquela entrega, a migration ficou
pendente no banco principal. Este registro não atesta o estado atual do MySQL.

## Banco e implantação

Migration nova: `2026_11_02_020137_create_forms_table.php`. Não foi aplicada ao
MySQL principal na entrega RF03; os testes migraram somente SQLite em memória.
Antes de usar a tela nesse banco, revisar e aplicar a migration em implantação
separada. Não executar rollback, fresh ou wipe para experimentar o requisito.
Ver [estrutura](../banco-de-dados/estrutura.md).

## Como verificar

- Backend: `php artisan test tests/Feature/Api/V1/CreateFormTest.php tests/Feature/Forms/CreateFormPageTest.php`.
- Regressões: `make test`; `make test-auth` para autenticação isoladamente.
- Qualidade: `make lint`, `composer types:check`, `npm run types:check`, `npm run check`.
- Compilação: `make build` e `npm run build:ssr`.

No ambiente com a migration aplicada: entrar, acessar Criar formulário pelo
dashboard, preencher e enviar. Esperado: POST JSON 201, confirmação e campos
limpos. Via API, campos inválidos retornam 422 sem gravar. A interface não repete
requisições automaticamente e mantém o conteúdo em caso de erro; 401/419 oferecem
login em outra aba.

Skills consultadas: laravel-best-practices (migrations, Eloquent, validação,
rotas, arquitetura e segurança), security-guidance (referências ASVS indicadas
ausentes, sem alegar conformidade formal) e ui-taste (operate/craft, preservando
layout, paleta e temas). A documentação permanece ignorada pela regra /docs do
.gitignore; requer inclusão explícita para ser versionada.

## Evolução RF04

A página agora monta perguntas discursivas localmente antes do POST de criação.
CreateForm coordena formulário e perguntas numa transação; perguntas são entidades
separadas com relação hasMany/belongsTo. A resposta inclui questions ordenadas,
e a requisição mantém compatibilidade sem perguntas. A explicação completa e o
percurso atualizado estão no [registro RF04](../explicacao.md). Nenhuma edição posterior ou
resposta de participante foi implementada. Os resultados de validação RF03 acima
são históricos; consulte o registro RF04 para os resultados daquela entrega.

## RF05–RF06

O editor agora configura os três tipos em QuestionEditor e mantém alternativas
localmente até o mesmo POST de criação. A Action grava forms, questions e
question_options na transação. O registro didático está em
[explicação do módulo](../explicacao.md); o [guia Postman](../testes/postman.md)
concentra os exemplos. Escolha de alternativas e justificativa do participante
serão tratadas no envio de respostas, ainda não implementado.

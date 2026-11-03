# RF04 — Inclusão de perguntas discursivas

## Objetivo e decisões

Evolução do RF03: o usuário define perguntas enquanto preenche a tela, antes de
criar o formulário. Adicionar, corrigir e remover nesse momento altera apenas
estado local do Vue. Depois de criar, não há endpoint de edição, conforme RN07.
RF05/RF06 e respostas dos participantes não foram implementados.

## Banco: migration e relacionamentos

A migration `2026_11_03_010114_create_questions_table.php` cria questions com id,
form_id obrigatório, type, prompt, position e timestamps. A FK aponta para forms
com restrição de exclusão; o índice único (form_id, position) evita posições
repetidas dentro do mesmo formulário. A migration é aditiva e não foi aplicada
ao MySQL principal. Não editar migrations já existentes ou usar fresh/wipe.
O down remove dados: não executar rollback sem aprovação.

Question é um model Eloquent, que representa registros da tabela. Form::questions()
usa hasMany, pois um formulário tem várias perguntas, ordenadas por position.
Question::form() usa belongsTo, pois cada pergunta pertence a um formulário.
Isso atende RNF08: perguntas têm identidade própria, sem ficarem embutidas num
JSON do formulário. Não há tabela de respostas nesta etapa.

## API e validação

O endpoint continua POST /api/v1/forms, com sessão Fortify, web, auth:web e CSRF.
questions é uma lista opcional. Ausência e [] preservam o RF03. Cada item aceita
somente type e prompt; type deve ser discursive, prompt é texto obrigatório de
até 10.000 caracteres. Esse limite técnico foi aprovado no planejamento.
A RN04 não define quantidade mínima de perguntas, e não há opção de pergunta
facultativa. A obrigatoriedade das respostas será aplicada nos próximos requisitos.

StoreFormRequest valida toda a entrada antes de gravar. Erros são JSON 422,
por exemplo questions.0.prompt. Identificadores ou posição enviados nos objetos
são rejeitados. O servidor define form_id e posições sequenciais a partir de 1.
Sucesso continua JSON 201 e inclui data.questions, mesmo vazio, com id, type,
prompt e position. FormResource e QuestionResource delimitam os campos expostos.

## Action e transação

O controller injeta CreateForm e passa o usuário da sessão e os campos validados.
Agora a Action se justifica porque coordena múltiplas gravações dependentes.

```php
return DB::transaction(function () use ($user, $title, $description, $questions): Form {
    // Cria o formulário e todas as suas perguntas pela relação Eloquent.
    // Uma exceção desfaz todas as gravações desta operação.
});
```

A implementação real está em app/Actions/Forms/CreateForm.php. A autoria continua
vindo da sessão. A relação do formulário preenche o vínculo de cada pergunta,
e a Action define position. O controller retorna o recurso carregado com as
perguntas, sem duplicar a gravação em uma rota web.

## Frontend e comunicação

Inertia entrega forms/Create. A página mantém uma lista reativa de perguntas com
chaves locais estáveis, para remover um item sem trocar a identidade dos demais.
Essas chaves não são enviadas à API. O botão Adicionar pergunta discursiva inclui
um enunciado; Remover atua somente sobre o preenchimento ainda não salvo.

resources/js/api/forms.ts amplia os tipos e envia o mesmo POST JSON com as
perguntas, usando fetch e X-XSRF-TOKEN. Durante envio, os controles são bloqueados.
A ordem da lista vira position no servidor. Erros aninhados aparecem no enunciado
correspondente; ao remover um item, erros antigos de índice são descartados.
Sucesso limpa título, descrição e perguntas. Falhas preservam o conteúdo e não
provocam repetição automática do POST. Os temas e componentes Vuetify existentes
foram mantidos.

## Arquivos principais

- Migration nova e app/Models/Question.php: estrutura e entidade.
- app/Models/Form.php: relação ordenada.
- app/Actions/Forms/CreateForm.php: persistência transacional.
- StoreFormRequest e FormController: validação e coordenação HTTP.
- FormResource e QuestionResource: resposta JSON.
- resources/js/api/forms.ts e pages/forms/Create.vue: contrato e interface.
- tests/Feature/Api/V1/CreateDiscursiveQuestionsTest.php: casos RF04.
- CreateFormTest.php: resposta RF03 acrescida de questions vazio.

## Como verificar

Execute php artisan test tests/Feature/Api/V1 para o contrato e make test para
regressões. SQLite :memory: e RefreshDatabase mantêm o MySQL fora dos testes.
Os testes incluem vínculo, ordem, independência, limite acentuado, dados inválidos,
campos extras, compatibilidade sem perguntas, preservação após exclusão de conta
e rollback real após uma exceção na segunda pergunta.

Na interface de um ambiente com migrations aplicadas, preencha os metadados,
adicione duas perguntas, remova uma e envie. Esperado: um POST com a lista local,
201 e campos limpos. Nenhuma rota acrescenta perguntas a formulários existentes.

## Conceitos para estudar

1. hasMany / belongsTo: navegação e vínculo entre registros.
2. Form Request: validação de listas e mensagens por caminho de campo.
3. Transação: confirmação conjunta ou reversão de gravações dependentes.

## Skills e limitações

Aplicadas as orientações de laravel-best-practices, security-guidance e ui-taste
já consultadas: relações explícitas, validação no servidor, transações, sessão/CSRF
e interface acessível no sistema visual existente. As referências ASVS da skill
não estão instaladas; não se afirma conformidade formal.

## Resultados desta entrega

- make test: 104 testes aprovados, 460 asserções, sem acessar MySQL.
- API v1: 38 testes aprovados, 181 asserções.
- PHPStan, Pint serial, TypeScript e npm run check aprovados.
- Builds cliente e SSR aprovados; aviso opcional fontaine não bloqueante.
- Chromium com SSR e dados fictícios: temas claro/escuro em larguras 390 e 1440,
  sem overflow horizontal. Adicionar/remover, erros aninhados 422, preservação de
  enunciado, payload e limpeza após 201 verificados, sem exceções de execução.
  A API do preview foi simulada; o processamento Laravel é coberto pelo Pest.
  Screenshots temporários: /tmp/rf04-{light,dark}-{390,1440}.png.
- Servidor de preview encerrado. Migration pendente no MySQL principal.

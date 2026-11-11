# RF04 — Inclusão de perguntas discursivas

## Objetivo e decisões

Evolução do RF03: o usuário define perguntas enquanto preenche a tela, antes de
criar o formulário. Adicionar, corrigir e remover nesse momento altera apenas
estado local do Vue. Depois de criar, não há endpoint de edição, conforme RN07.
Na entrega RF04, RF05/RF06 ainda não estavam implementados. Sua evolução está
registrada nas seções abaixo. Respostas dos participantes continuam fora desta etapa.

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

Os exemplos JSON e a configuração de autenticação do Postman estão no
[guia de testes manuais](testes/postman.md). Não repetir esses exemplos aqui.

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

## Resultados históricos da entrega RF04

- make test: 104 testes aprovados, 460 asserções, sem acessar MySQL.
- API v1: 38 testes aprovados, 181 asserções.
- PHPStan, Pint serial, TypeScript e npm run check aprovados.
- Builds cliente e SSR aprovados; aviso opcional fontaine não bloqueante.
- Chromium com SSR e dados fictícios: temas claro/escuro em larguras 390 e 1440,
  sem overflow horizontal. Adicionar/remover, erros aninhados 422, preservação de
  enunciado, payload e limpeza após 201 verificados, sem exceções de execução.
  A API do preview foi simulada; o processamento Laravel é coberto pelo Pest.
  Screenshots temporários: /tmp/rf04-{light,dark}-{390,1440}.png.
- Servidor de preview encerrado. Na entrega RF04, a migration ficou pendente
  no MySQL principal; este histórico não confirma o estado atual de implantação.

# RF05 — Múltipla escolha

## Fluxo e decisões

O editor agora permite selecionar o tipo da pergunta e adicionar alternativas.
QuestionEditor é compartilhado: recebe o preenchimento local, erros e estado de
envio; a página Create monta o JSON e chama a API existente. As chaves locais
não vão ao servidor; alternativas ocultas ao trocar para discursiva não são enviadas.

QuestionOption pertence a Question; Question::options() tem várias alternativas
ordenadas. A migration 2026_11_03_205430_create_question_options_table.php cria
question_options com id, question_id obrigatório/restritivo, label, position,
datas e unicidade (question_id, position). Migração aditiva, não aplicada ao MySQL.

StoreFormRequest aceita multiple_choice, exige pelo menos uma alternativa (decisão
aprovada) e texto obrigatório até 10.000 caracteres (limite técnico aprovado).
Não há exigência de labels distintos ou teto de quantidade de alternativas.
Discursiva aceita ausência ou [] e rejeita alternativas não vazias. IDs, vínculos,
posições, campos de resposta e limites de marcação não são recebidos nos objetos.

CreateForm grava alternativas pela relação da pergunta dentro da transação já
existente; falha em uma alternativa desfaz formulário, perguntas e alternativas.
A resposta carrega questions.options antecipadamente e QuestionOptionResource
retorna id, label e position. Discursivas retornam options vazio. Não há operação
para adicionar alternativas depois de criar; o editor atua apenas antes do POST.

A seleção pelo participante ainda não foi implementada. O tipo multiple_choice
registra que será possível marcar várias alternativas, sem limite de marcações.
Não adicionamos configuração max_selections ou campo de resposta.

## Arquivos e estudo

QuestionOption e a nova migration definem a entidade; Question define a relação;
StoreFormRequest valida condicionalmente; CreateForm coordena persistência;
QuestionResource/QuestionOptionResource delimitam a saída. No Vue, QuestionEditor
reutiliza campos e alternativas; api/forms.ts e types/forms.ts descrevem contrato
e estado local. Estudar: relações hasMany/belongsTo, validação de listas aninhadas
e atomicidade da transação. Exemplos manuais em [Postman](testes/postman.md).

## Testes RF05

API v1: 60 testes aprovados, 305 asserções. Cobrem lista com uma ou várias opções,
labels repetidos, vínculos, ordem, erros aninhados, limite acentuado, preservação
após excluir conta e rollback real na segunda alternativa. RF06 ainda rejeitado
nesta etapa. Todos usam SQLite isolado; nenhum dado MySQL foi alterado.

Validação final RF05 antes de avançar ao RF06: suíte completa com 126 testes e
584 asserções; PHPStan, Pint serial, TypeScript, lint/formatação e builds cliente/SSR
aprovados. Preview Chromium com API simulada passou nos temas claro/escuro em
390×900 e 1440×900, incluindo inclusão/remoção, troca de tipo preservando dados,
erros aninhados, payload, bloqueio de duplo envio e limpeza após sucesso. Nenhuma
exceção de execução observada. A API real é coberta pelos testes Pest.

# RF06 — Escolha única com justificativa

## Reutilização e limites

O tipo single_choice_with_justification reutiliza Question, QuestionOption,
CreateForm e o mesmo QuestionEditor. Não exige migration adicional. A diferença
registrada pelo type será aplicada na submissão futura: escolher exatamente uma
alternativa e enviar justificativa obrigatória. Não há campo de justificativa no
JSON de criação, toggle para desativá-la ou envio de respostas nesta entrega.
Todas as perguntas continuam obrigatórias por regra de negócio.

StoreFormRequest admite o novo tipo e reutiliza a validação objetiva: pelo menos
uma alternativa com texto válido. QuestionEditor apresenta o mesmo editor de
alternativas e explica a futura exigência de justificativa. Ao trocar entre tipos
objetivos, os textos locais são mantidos. Ao escolher discursiva, ficam ocultos e
não vão ao POST, podendo ser reutilizados se voltar ao tipo objetivo antes de criar.
A transação e a serialização são as mesmas do RF05, sem duplicar o caso de uso.

## Testes e documentação

Os testes RF06 verificam um formulário com os três tipos, opções independentes,
ordem, lista ausente/vazia, labels inválidos e rejeição de campos de participante
ou configuração facultativa de justificativa. A API v1 passou com 69 testes e
351 asserções. JSONs válidos, inválidos e mistos em [Postman](testes/postman.md).
Resultados finais da suíte e conferência visual serão registrados nesta seção.

## Validação final RF06 e regressões

- make test: **135 testes aprovados, 630 asserções** em SQLite isolado.
- API v1: **69 testes, 351 asserções**; autenticação: **27 testes, 132 asserções**.
- PHPStan, Pint serial, TypeScript, lint e formatação do frontend aprovados.
- Builds cliente e SSR aprovados; aviso opcional fontaine não bloqueante.
- Preview Chromium RF06 com API simulada: claro/escuro, 390×900 e 1440×900,
  ausência de overflow horizontal, troca entre os três tipos, alternativas
  preservadas, inclusão/remoção, erros aninhados, payload, bloqueio de duplo envio
  e limpeza após sucesso. Nenhuma exceção de execução observada. Capturas locais
  em /tmp/cati-RF06-{light,dark}-{390,1440}.png. O teste real da persistência é Pest;
  não se afirma execução manual no aplicativo Postman ou gravação no MySQL.
- Os 13 blocos JSON do guia Postman e os links locais afetados foram verificados.
- Os servidores temporários foram encerrados. A nova migration não foi aplicada
  ao MySQL principal; nenhuma credencial local foi alterada.

Skills: laravel-best-practices (relações, validação, migration, transação e
carregamento antecipado), security-guidance (controle dos campos e sessão/CSRF;
referências ASVS ausentes, sem alegar conformidade formal) e ui-taste (editor
compartilhado, estados, rótulos e conferência responsiva no sistema visual existente).

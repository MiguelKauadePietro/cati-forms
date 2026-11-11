# API v1 — formulários

## Contrato vigente

`POST /api/v1/forms`, nome `api.v1.forms.store`. Corpo e resposta JSON.
Autenticação por sessão Fortify (`auth:web`), com middleware web e proteção CSRF.
Não há Bearer token ou POST web duplicado. A consulta do dashboard está descrita abaixo.

| Entrada               | Validação                                                                                 |
| --------------------- | ----------------------------------------------------------------------------------------- |
| title                 | Texto obrigatório, até 255 caracteres; não precisa ser único.                             |
| description           | Texto obrigatório, até 10.000 caracteres.                                                 |
| request_contact_email | Booleano opcional; ausência usa false. Aceita true/false, 0/1 e "0"/"1"; null é inválido. |
| questions             | Lista opcional; ausência ou [] preserva o RF03. Null não é aceito.                        |
| questions.N           | Objeto contendo type, prompt e, conforme o tipo, options.                                 |
| questions.N.type      | Obrigatório; discursive, multiple_choice ou single_choice_with_justification.             |
| questions.N.prompt    | Texto obrigatório, até 10.000 caracteres.                                                 |

Aplicam-se trim e conversão nativos de strings vazias para null. Campos extras
no nível do formulário não são persistidos; campos extras dentro de perguntas
são rejeitados. Autoria, IDs, vínculo e posições são definidos pelo servidor.
Não há quantidade mínima de perguntas ou opção de pergunta facultativa.

## Resposta

201 retorna data com id, public_id, public_url, title, description, request_contact_email, creator_id, created_at, updated_at e
questions, sempre uma lista. Cada pergunta tem id, type, prompt e position.
Datas são ISO 8601 em UTC; posições começam em 1, na ordem enviada.
creator_id pode ficar nulo após exclusão da conta; formulário e perguntas são
preservados. Não se serializam dados privados da conta.

| HTTP | Situação                                                |
| ---- | ------------------------------------------------------- |
| 201  | Criação confirmada em transação.                        |
| 401  | Sem autenticação, após passar pela proteção CSRF.       |
| 419  | Rejeição pela proteção CSRF.                            |
| 422  | Validação; errors usa caminhos como questions.0.prompt. |

Erros têm message; validação também inclui errors com listas de mensagens.
As mensagens podem variar. CSRF precede auth: um cliente sem autenticação e sem
CSRF válido pode receber 419. Em produção, manter APP_DEBUG=false.

CreateForm grava formulário e perguntas em transação: uma falha desfaz o conjunto.
Não há idempotência: repetir um POST válido cria outro formulário. Não há edição,
exclusão ou acréscimo posterior de perguntas. RF05/RF06 acrescentam alternativas; ver seção abaixo.

## Exemplos e verificação

Os JSONs copiáveis de entrada/saída, headers, autenticação automática e erros ficam
no [guia único do Postman](../../testes/postman.md). A implementação é explicada em
[criação](../../formularios/criacao.md) e [RF04](../../explicacao.md).

Testes correspondentes: CreateFormTest.php e CreateDiscursiveQuestionsTest.php,
em tests/Feature/Api/V1, usando SQLite isolado. Comando:
`php artisan test tests/Feature/Api/V1`. Não há necessidade de gravar no MySQL
para executar esses testes.

## Evolução RF05–RF06 — contrato vigente de alternativas

Tipos aceitos: discursive, multiple_choice e single_choice_with_justification.
Cada pergunta aceita type, prompt e options; os enunciados mantêm as regras
anteriores. A criação sem perguntas ou somente com discursivas permanece válida.

options é lista obrigatória nas objetivas, com pelo menos um item (decisão
aprovada pelo usuário). Cada objeto aceita somente label, texto obrigatório até
10.000 caracteres. Não há unicidade de label nem máximo de alternativas imposto.
Discursiva aceita ausência ou [] e rejeita alternativas não vazias. O retorno
sempre inclui options; cada item tem id, label e position, definida pelo servidor.
A transação inclui todas as alternativas e o carregamento antecipado evita N+1.
Erros 422 usam caminhos como questions.0.options.0.label. A API não aceita
justification, seleções, IDs, vínculos ou posições nos objetos de definição.
Não há novos endpoints, alteração de auth/CSRF ou envio de respostas. A distinção
de seleção e justificativa será aplicada no futuro requisito de submissões.
Exemplos atualizados no [guia Postman](../../testes/postman.md).

## Dashboard — meus formulários

`GET /api/v1/forms/mine`, nome `api.v1.forms.mine`, usa os mesmos middleware
`web` e `auth:web`. Não recebe corpo; `page` é opcional, inteiro maior ou igual
a 1. A página tem 12 registros, ordenados por `created_at` e `id` decrescentes.
O servidor consulta `$request->user()->forms()`; parâmetros `creator_id` ou
`user_id` não escolhem o usuário nem alteram a filtragem.

200 retorna `data` com os mesmos campos básicos de `FormResource` (id, title,
description, request_contact_email, public_id, public_url, creator_id, created_at, updated_at), sem `questions`, pois essa
relação não é carregada na listagem. A resposta inclui `links` e `meta` da
paginação Laravel. Sem resultados, `data` é `[]` e `meta.total` é 0.
Visitantes recebem 401 JSON; `page` inválido retorna 422 com `errors.page`.
GET não exige cabeçalho CSRF; mantém cookies de sessão e `Accept: application/json`.

Esse filtro é específico de “Meus formulários”, sem scope global ou política por
autoria: não restringe o acesso a todos os formulários previsto pelo RF14.
A consulta geral autenticada de detalhes e respostas está descrita na seção RF14. Não há edição ou exclusão. A leitura pública está descrita abaixo.
Exemplos: [Postman](../../testes/postman.md). Testes: `ListMyFormsTest.php`.

## RF07 — solicitação de e-mail de contato

`request_contact_email` configura se o formulário solicitará e-mail do respondente.
O valor é persistido na criação e sempre retornado como booleano pelo POST e pelo
GET de meus formulários. Omissão usa `false`, preservando clientes anteriores;
formulários existentes recebem `false` pela migration aditiva.
Valores inválidos retornam 422 com `errors.request_contact_email`, sem gravar o
conjunto. As strings `"true"` e `"false"` não são booleanos aceitos; prefira os
literais JSON `true` e `false`.

Não há edição posterior. RF08–RF09 acrescentam o envio público e a coleta opcional de e-mail descritos abaixo.
A migration `2026_11_06_000000_add_request_contact_email_to_forms_table.php` deve
ser aplicada antes de usar esse contrato; não há alteração de registros históricos
além do novo campo com padrão false. Reverter essa migration remove a coluna e
perde suas configurações; não executar rollback em dados reais sem aprovação.

## RF08 — link público

Cada formulário possui `public_id` UUID v4 único, gerado pelo servidor, e a URL
`/f/{public_id}`. POST de criação e GET de meus formulários acrescentam `public_id`
e `public_url` (URL absoluta). O corpo de criação continua compatível; o cliente
não escolhe o identificador público. A URL não expira e não depende de login.
A migration atribui UUIDs aos formulários existentes sem alterar seu conteúdo.
Não há renovação, edição ou exclusão de links nesta versão.

## RF09 — leitura e envio públicos

| Método | URL                                            | Retorno                               |
| ------ | ---------------------------------------------- | ------------------------------------- |
| GET    | `/f/{public_id}`                               | Página Inertia pública `forms/Public` |
| GET    | `/api/v1/public/forms/{public_id}`             | 200 JSON com `data`                   |
| POST   | `/api/v1/public/forms/{public_id}/submissions` | 201 JSON com confirmação              |

As três rotas dispensam autenticação. A API pública reutiliza o middleware web e
CSRF; abra a página ou faça o GET público para obter os cookies, depois envie o
cookie XSRF decodificado em `X-XSRF-TOKEN`, com a mesma sessão. Não precisa de
`GET /login`, login, Bearer ou Sanctum. A API administrativa permanece privada.

### Leitura

`data` contém somente `public_id`, `title`, `description`, `request_contact_email`
e `questions`. Perguntas reutilizam o contrato `id`, `type`, `prompt`, `position`,
`options`; alternativas têm `id`, `label`, `position`. Os IDs de perguntas e
alternativas servem para montar o envio. Não são expostos ID interno do formulário,
criador, datas internas, contatos, submissões ou respostas de participantes.
Links malformados ou inexistentes recebem 404 (JSON na API; página de erro no web).

### Envio

| Campo                     | Validação                                                                                                                                             |
| ------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| `contact_email`           | Opcional e nullable quando solicitado; texto, formato e-mail RFC, até 254 caracteres. Não vazio é proibido quando `request_contact_email=false`.      |
| `answers`                 | Lista obrigatória, com exatamente uma resposta por pergunta do formulário. Para formulário sem perguntas, enviar `[]`.                                |
| `answers.N.question_id`   | Inteiro JSON (não string, booleano ou decimal), sem repetição, pertencente ao formulário da URL.                                                      |
| `answers.N.text`          | Obrigatório apenas para discursiva; texto não vazio, até 10.000 caracteres.                                                                           |
| `answers.N.option_ids`    | Lista de inteiros JSON sem repetição, pertencentes à pergunta. Múltipla escolha: uma ou mais, sem limite de marcações. Escolha única: exatamente uma. |
| `answers.N.justification` | Obrigatório apenas para escolha única com justificativa; texto não vazio, até 10.000 caracteres.                                                      |

Perguntas e alternativas são verificadas contra o formulário da URL, nunca contra
um `form_id` fornecido pelo cliente. Campos incompatíveis com o tipo são proibidos
quando preenchidos. Objetos de resposta aceitam somente as quatro chaves da tabela;
campos extras nesse nível são rejeitados. Autoria, ID de submissão e vínculos são
controlados pelo servidor; campos extras na raiz não são persistidos.

201 retorna somente `{"message":"Resposta enviada com sucesso."}`. Não devolve ID,
contato nem conteúdo armazenado. `submissions`, `answers` e `answer_options`
armazenam o conjunto em uma transação; falha não deixa gravação parcial. Relações
usam chaves estrangeiras e não há dependência de conta do participante.

422 contém `message` e `errors` com caminhos como `answers.0.text` e
`answers.2.justification`. CSRF ausente ou inválido retorna 419 antes da validação.
O POST limita 60 requisições/minuto por IP (inclusive tentativas inválidas após
passarem pelo CSRF), com 429 e `Retry-After`. Não há bloqueio de envios repetidos
por identidade ou e-mail, nem repetição automática após falha de rede.

RF10 e RF11 são validados nesse envio, RF12 preserva a estrutura independente já
existente, e RF13 possui persistência de submissões vinculadas ao formulário.
Consulta de respostas e exportação não fazem parte desses endpoints.

### Instalação e verificação

Aplicar as migrations aditivas de `public_id` e de submissões/respostas antes de
usar o contrato. Elas não foram executadas automaticamente no MySQL. Rollback
remove links ou respostas e exige aprovação antes de uso em dados reais.
Testes: `php artisan test tests/Feature/PublicForms`. Exemplos completos de
requisição e resposta: [Postman](../../testes/postman.md).

## RF10–RF14 — validação, persistência e consulta geral

RF10–RF13 reutilizam o envio público do RF09: todas as perguntas são obrigatórias,
IDs de perguntas e alternativas são validados no contexto do formulário e da
pergunta, respectivamente. Escolha única exige exatamente uma alternativa e
justificativa não vazia após trim. A transação grava submissão, respostas e
alternativas como um conjunto; erros 422 ou falhas de escrita não deixam dados
parciais. Não há novas tabelas ou migrations para esta conclusão.

### Consulta autenticada (RF14)

Todos os usuários autenticados têm o mesmo acesso, independentemente da autoria
(RN02/RN10). Autenticação por sessão, middleware `web` e `auth:web`, sem Bearer.
GET não exige header CSRF. Visitantes recebem 401 JSON.

| Método | URL                                | Resposta 200                                                                      |
| ------ | ---------------------------------- | --------------------------------------------------------------------------------- |
| GET    | `/api/v1/forms`                    | `data` com resumos de todos os formulários, `links` e `meta`.                     |
| GET    | `/api/v1/forms/{form}`             | `data` com FormResource completo, incluindo perguntas e alternativas por posição. |
| GET    | `/api/v1/forms/{form}/submissions` | `data` com submissões do formulário, `links` e `meta`.                            |

`{form}` é o ID numérico, não o UUID público. Listagens usam `page` opcional,
inteiro >= 1, 12 itens por página, ordenados por `created_at` e `id` decrescentes.
Filtros `creator_id` e `user_id` não restringem a listagem geral. O endpoint
`/forms/mine` permanece filtrado pelo usuário da sessão.

Cada submissão contém `id`, `form_id`, `contact_email` (nullable), `created_at`
(ISO 8601 UTC) e `answers`. Cada resposta contém `id`, `question_id`, `text`
(nullable), `justification` (nullable) e `options` com `id`, `label`, `position`.
Os IDs de perguntas permitem associar as respostas aos detalhes do formulário.
Não são serializados dados da conta do criador nem campos do pivot.
Sem submissões, `data: []` e `meta.total: 0`; formulário sem perguntas pode ter
submissões com `answers: []`. IDs inexistentes retornam 404; `page` inválida,
422 com `errors.page`. Nenhum endpoint público retorna respostas recebidas.

Frontend: `/forms` lista todos os formulários; `/forms/{form}` exibe detalhes e
respostas paginadas. Ambas as páginas exigem login. O dashboard mantém “Meus
formulários” com links para os detalhes.

Testes: `ReadFormsTest.php` e `PublicSubmissionTest.php` cobrem permissões,
paginação, integridade e os três tipos de pergunta, usando SQLite em memória.

## RF15–RF16 — exportação das respostas

| Método | URL                                | Content-Type              |
| ------ | ---------------------------------- | ------------------------- |
| GET    | `/api/v1/forms/{form}/exports/csv` | `text/csv; charset=UTF-8` |
| GET    | `/api/v1/forms/{form}/exports/pdf` | `application/pdf`         |

`{form}` é o ID numérico. Ambos exigem sessão autenticada (`web`, `auth:web`),
com o mesmo acesso para todos os usuários (RN02/RN10). Não recebem corpo nem
exigem header CSRF. Não há exportação pública. Retornam **200** com o arquivo,
`Content-Disposition: attachment; filename="formulario-{id}-respostas.csv"`
(ou `.pdf`), `Cache-Control: private, no-store` e `X-Content-Type-Options: nosniff`.
Sem login retornam **401** JSON; formulário inexistente, **404** JSON. Erros da
API mantêm `message`; não há envelope JSON nos downloads bem-sucedidos.

As exportações incluem todas as submissões do formulário, em ordem crescente de
ID, independentemente de `page` ou da página exibida na interface. Não alteram
registros nem armazenam cópias dos arquivos no banco.

### CSV

UTF-8 com BOM, delimitador vírgula, aspas duplas duplicadas e registros terminados
em CRLF. Quebras de linha dentro dos campos são preservadas. Uma linha por envio:
`Formulário ID`, `Formulário` (título), `Envio ID`, `Enviado em (UTC)` (ISO 8601),
`E-mail de contato` somente quando solicitado, e colunas `P{posição} — {enunciado}`.
Discursivas contêm texto; objetivas contêm rótulos selecionados, na ordem das
alternativas, separados por quebra de linha. Escolha única também possui a coluna
`P{posição} — Justificativa`. E-mail opcional ausente vira célula vazia.

Células com início potencialmente interpretável como fórmula (`=`, `+`, `-`, `@`,
suas variantes de largura completa, inclusive após espaços/caracteres de controle;
ou tabulação/quebra de linha inicial) recebem apóstrofo de proteção. Essa mudança
existe apenas no arquivo; os dados armazenados permanecem intactos. Na importação
manual, selecione UTF-8 e delimitador vírgula. Sem respostas, retorna apenas BOM e
cabeçalho. O servidor escreve CSV em fluxo, consultando submissões em lotes.

### PDF

PDF A4 com fonte DejaVu Sans, título, descrição, identificação do formulário,
envio e data UTC. Perguntas são apresentadas por posição, com textos, alternativas
selecionadas e justificativas; e-mail aparece quando coletado. Submissões seguintes
começam em nova página; respostas longas podem continuar nas páginas seguintes.
Sem respostas, inclui a mensagem “Nenhuma resposta recebida.”.

Geração via `dompdf/dompdf`, instalado por Composer por não haver gerador PDF
anterior. Conteúdo é escapado no Blade; recursos remotos, PHP e JavaScript do
renderizador permanecem desabilitados. O PDF é renderizado em memória; arquivos
muito grandes dependem dos limites de memória e tempo do servidor.

A página autenticada `/forms/{form}` oferece “Exportar CSV” e “Exportar PDF”, com
indicação de processamento e erro recuperável, sem substituir a consulta.
Testes: `ExportResponsesTest.php`, em SQLite em memória. A extração automatizada
de texto PDF usa `pdftotext` quando disponível; sem ele, os testes de extração são
marcados como ignorados, mantendo as verificações CSV, HTTP, estrutura PDF e Blade.

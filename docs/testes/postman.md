# Testes manuais no Postman — RF01 a RF09

## Preparo e autenticação

A criação administrativa usa `POST /api/v1/forms`; as operações públicas de RF08–RF09 estão ao final. Cadastro, login e logout são
endpoints do Fortify, sem prefixo `/api/v1`; são necessários para obter a sessão.
Não há Bearer token ou Sanctum. O GET de “Meus formulários” está documentado ao final.

Crie uma coleção CATI Forms e a variável `base_url` com `http://127.0.0.1:8000`.
Salve todas as requisições abaixo nessa coleção. Use sempre o mesmo host, sem
alternar localhost e 127.0.0.1. Inicie o servidor com `make backend`. O banco deve
estar preparado; `make migrate-status` consulta as migrations sem aplicá-las.
POSTs válidos criam dados no banco configurado do servidor, não no SQLite dos
testes Pest. Cada POST de criação válido cria outro formulário.

Nas requisições: Authorization = No Auth, Disable cookie jar = desativado,
Automatically follow redirects = desativado. Remova scripts antigos e headers
Cookie/XSRF manuais. O Postman guardará e enviará o cookie de sessão automaticamente.
Para os POSTs, Body → raw → JSON e header `Content-Type: application/json`.

### Scripts únicos da coleção

Em **Scripts → Post-response**, leia o Set-Cookie da resposta e guarde o XSRF
atualizado. Não copie atributos Path/Expires, nem o prefixo XSRF-TOKEN= para o header.

```javascript
const cookie = pm.response.headers
    .all()
    .filter((header) => header.key.toLowerCase() === 'set-cookie')
    .map((header) => header.value)
    .find((value) => value.startsWith('XSRF-TOKEN='));

if (cookie) {
    const token = cookie.split(';')[0].slice('XSRF-TOKEN='.length);
    pm.collectionVariables.set('cati_xsrf', decodeURIComponent(token));
}
```

Em **Scripts → Pre-request**, envie esse token nos POSTs:

```javascript
if (!['GET', 'HEAD', 'OPTIONS'].includes(pm.request.method)) {
    const token = pm.collectionVariables.get('cati_xsrf');
    if (!token) {
        throw new Error(
            'Execute primeiro GET /login ou o GET público desta coleção.',
        );
    }
    pm.request.headers.upsert({ key: 'X-XSRF-TOKEN', value: token });
    pm.request.headers.upsert({ key: 'Accept', value: 'application/json' });
}
```

Cookie de sessão identifica o usuário; XSRF protege contra requisições forjadas.
Os scripts não criam autenticação: apenas encaminham o token da sessão. Não
compartilhe cookies, tokens ou credenciais reais. Referências do Postman:
[cookies](https://learning.postman.com/docs/use/send-requests/response-data/cookies),
[respostas](https://learning.postman.com/docs/tests-and-scripts/write-scripts/postman-sandbox-reference/pm-response/),
[variáveis](https://learning.postman.com/docs/tests-and-scripts/write-scripts/postman-sandbox-reference/pm-variables/).

## 1. Obter sessão e CSRF — GET {{base_url}}/login

Sem corpo. Esperado: **200 com HTML**, não JSON. Cookies incluem XSRF-TOKEN e o
cookie de sessão (o nome depende da configuração). O script guarda o XSRF.
Faça este GET no início, antes de cadastro ou login, não antes de cada POST.

## 2. RF01 — POST {{base_url}}/register

Use um username fictício ainda não cadastrado. O cadastro já autentica a conta.

```json
{
    "username": "cati_postman",
    "password": "Cati!Exemplo2026#Seguro",
    "password_confirmation": "Cati!Exemplo2026#Seguro"
}
```

Com Accept JSON: **201**, corpo JSON `""` (string vazia), sem objeto de usuário.
Sem solicitar JSON, o Fortify redireciona ao dashboard. Senha de exemplo não é
credencial real; em produção a política inclui checagem externa de vazamentos,
portanto uma senha ilustrativa pode ser rejeitada. Username é normalizado para
minúsculas e deve ser único. E-mail não é necessário.

Erros essenciais: **422** por username repetido/ausente ou senha/confirmação
inválida. Mensagens dependem da tradução; chaves de errors incluem username e
password. Nunca use o hash do banco como senha na requisição.

## 3. RF02 — POST {{base_url}}/login

Para uma conta existente, faça GET /login e envie:

```json
{
    "username": "cati_postman",
    "password": "Cati!Exemplo2026#Seguro"
}
```

Com Accept JSON: **200**, exatamente o formato do Fortify instalado:

```json
{
    "two_factor": false
}
```

Esse campo não ativa 2FA; o recurso está desabilitado. Sem solicitar JSON, ocorre
redirecionamento ao dashboard. O Postman atualiza cookies e o script atualiza
XSRF após a resposta. Não copie valores da sessão anterior ao login.

Credenciais inválidas: **422**, erro em username, sem distinguir conta inexistente
de senha incorreta. A sexta requisição de login dentro do limite de cinco por
minuto para username/IP recebe **429**. Validação de campos também pode gerar 422.

## 4. RF03–RF04 — POST {{base_url}}/api/v1/forms

Requer sessão autenticada. Headers efetivos: Accept e Content-Type como
application/json, X-XSRF-TOKEN pelo script e Cookie pelo gerenciador do Postman.
Não envie X-Inertia: esta operação retorna JSON comum.

### RF03 — formulário sem perguntas

```json
{
    "title": "Inscrição CATI Jr.",
    "description": "Participe do processo seletivo."
}
```

questions é opcional; ausência ou [] produz data.questions vazio na resposta.

### RF04 — formulário com perguntas discursivas

```json
{
    "title": "Inscrição CATI Jr.",
    "description": "Participe do processo seletivo.",
    "questions": [
        {
            "type": "discursive",
            "prompt": "Por que deseja participar da CATI Jr.?"
        },
        {
            "type": "discursive",
            "prompt": "Quais experiências gostaria de compartilhar?"
        }
    ]
}
```

Título obrigatório até 255 caracteres; descrição e enunciados obrigatórios até
10.000. questions deve ser lista. Neste exemplo RF04, cada objeto usa type e prompt.
Para os tipos objetivos, veja RF05 e RF06 abaixo. Não envie id, form_id ou position.
Não há quantidade mínima de perguntas. A ordem enviada define as posições.

### Resposta — 201 Created

IDs e datas abaixo são ilustrativos e variam a cada criação:

```json
{
    "data": {
        "id": 1,
        "title": "Inscrição CATI Jr.",
        "description": "Participe do processo seletivo.",
        "request_contact_email": false,
        "creator_id": 7,
        "created_at": "2026-11-03T12:00:00.000000Z",
        "updated_at": "2026-11-03T12:00:00.000000Z",
        "questions": [
            {
                "id": 1,
                "type": "discursive",
                "prompt": "Por que deseja participar da CATI Jr.?",
                "position": 1
            },
            {
                "id": 2,
                "type": "discursive",
                "prompt": "Quais experiências gostaria de compartilhar?",
                "position": 2
            }
        ]
    }
}
```

creator_id vem da sessão. Datas estão em UTC. Formulário e perguntas são gravados
na mesma transação; falha na operação não deve deixar registros parciais.
Não há endpoint para adicionar perguntas depois da criação ou editar o conjunto.

### Erro de validação — 422

Para testar, use o JSON RF04 acima e troque o primeiro prompt por uma string vazia.
Com os demais campos válidos, a resposta esperada é:

```json
{
    "message": "Informe o enunciado da pergunta.",
    "errors": {
        "questions.0.prompt": ["Informe o enunciado da pergunta."]
    }
}
```

O índice 0 representa a primeira pergunta. Remover title também gera 422, com
errors.title. Outro type gera erro em questions.N.type. Nenhum desses casos cria
formulário. Mensagens gerais podem variar conforme quantidade de erros/tradução.

## 5. RF02 — POST {{base_url}}/logout

Envie `{}` como JSON, com os mesmos scripts e cookies. Com Accept JSON:
**204 No Content**, sem corpo. A sessão é invalidada e o token CSRF renovado.
Sem solicitar JSON, o Fortify redireciona à página inicial.

Após logout, um POST válido de formulário com CSRF atualizado retorna **401**:

```json
{
    "message": "Unauthenticated."
}
```

A mensagem segue a configuração do framework. Faça novo login para continuar.

## Diagnóstico e conferência

| Resultado                      | O que conferir                                                                                                              |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------- |
| Mensagem do script pedindo GET | GET e POST devem estar salvos na mesma coleção com os scripts ativos.                                                       |
| 419                            | Cookie jar habilitado, mesmo host, cookie de sessão enviado e XSRF da mesma sessão. Remova headers/scripts manuais antigos. |
| 401 na API                     | Sessão sem autenticação; faça login.                                                                                        |
| 422                            | Leia errors; a proteção CSRF já passou.                                                                                     |
| 429 no login                   | Aguarde a janela do limite; não repita credenciais continuamente.                                                           |
| 302 ou HTML no lugar do JSON   | Confira Accept e redirecionamentos automáticos. GET /login retorna HTML por definição.                                      |
| 500 por tabela ausente         | Consulte migrations pendentes; não use fresh/wipe para preparar testes.                                                     |

CSRF precede autenticação: sem cookies/token válidos, pode haver 419 em vez de 401. Não simule Sec-Fetch-Site para contornar a proteção. A sessão pode expirar;
nesse caso recomece com GET /login e login. Não é necessário copiar cookies.

Não existe GET /api/v1/forms. Para listar os próprios registros, use GET /api/v1/forms/mine (ao final). Confira os campos da resposta
201 e use a suíte para verificar armazenamento, relações e rollback:

```bash
php artisan test tests/Feature/Auth tests/Feature/Api/V1
```

Esses testes usam SQLite isolado com RefreshDatabase e proteção do banco. Resultados
anteriores estão nos registros didáticos; esta revisão documental não executa
POSTs no MySQL nem afirma execução manual destes scripts dentro do Postman.

Referências: [contrato](../api/v1/forms.md), [RF01–RF02](../usuarios/autenticacao.md),
[criação](../formularios/criacao.md), [RF04](../explicacao.md).

## Verificação desta revisão documental

Rotas conferidas por route:list; campos e status conferidos nos Requests,
Resources, Action e respostas do Fortify instalado. Oito blocos JSON e links
locais foram validados. Os dois scripts foram exercitados com objetos Postman
simulados: captura/decodificação, preservação do token quando não há Set-Cookie,
GET sem header, POST com headers e erro de token ausente. Não houve execução
real no aplicativo Postman, teste de credenciais ou acesso ao banco nesta revisão.
A suíte Pest e o build não foram repetidos, pois somente Markdown foi alterado.

## RF05 — múltipla escolha no POST {{base_url}}/api/v1/forms

Use os mesmos scripts, cookies e headers JSON. Corpo válido:

```json
{
    "title": "Interesses",
    "description": "Conheça as áreas.",
    "questions": [
        {
            "type": "multiple_choice",
            "prompt": "Quais áreas interessam a você?",
            "options": [{ "label": "Design" }, { "label": "Desenvolvimento" }]
        }
    ]
}
```

201 inclui data.questions.0.options com id, label e position a partir de 1.
Cada pergunta objetiva exige pelo menos uma alternativa, com label obrigatório
até 10.000 caracteres. Repetir labels é permitido. IDs e posições são atribuídos
pelo servidor. Discursivas aceitam options ausente ou []; o retorno inclui [].
Para testar erro, troque options por []: 422 em questions.0.options. Nenhum
registro é gravado. Não envie marcações: respostas dos participantes são futuras.

## RF06 e formulário misto — POST {{base_url}}/api/v1/forms

Mesma autenticação e headers das seções anteriores. Exemplo válido combinando
os três tipos; a terceira pergunta define escolha única com justificativa:

```json
{
    "title": "Perfil de interesses",
    "description": "Apresente suas experiências e preferências.",
    "questions": [
        {
            "type": "discursive",
            "prompt": "Conte sua experiência."
        },
        {
            "type": "multiple_choice",
            "prompt": "Quais áreas interessam a você?",
            "options": [{ "label": "Design" }, { "label": "Desenvolvimento" }]
        },
        {
            "type": "single_choice_with_justification",
            "prompt": "Qual é sua primeira escolha?",
            "options": [{ "label": "Design" }, { "label": "Desenvolvimento" }]
        }
    ]
}
```

Resposta 201: data contém os campos anteriores e questions em posições 1, 2 e 3.
Cada pergunta inclui options; na discursiva é []. Nas objetivas cada alternativa
retorna id, label e position. Exemplo do objeto da terceira pergunta (IDs variam):

```json
{
    "id": 3,
    "type": "single_choice_with_justification",
    "prompt": "Qual é sua primeira escolha?",
    "position": 3,
    "options": [
        { "id": 3, "label": "Design", "position": 1 },
        { "id": 4, "label": "Desenvolvimento", "position": 2 }
    ]
}
```

Não envie justificativa nesta criação: a regra para o participante é representada
pelo tipo. Nenhum endpoint de respostas foi acrescentado. Tanto múltipla escolha
quanto escolha única exigem ao menos uma alternativa; não há teto de marcações
configurável. Labels podem se repetir e têm limite técnico de 10.000 caracteres.
A edição é somente local antes do POST; depois de criar, o conjunto é imutável.

### RF05 inválido — lista vazia

```json
{
    "title": "Exemplo inválido",
    "description": "Não será gravado.",
    "questions": [
        { "type": "multiple_choice", "prompt": "Quais áreas?", "options": [] }
    ]
}
```

422 em questions.0.options, com mensagem Adicione pelo menos uma alternativa.
Também vale para single_choice_with_justification. O formulário inteiro é rejeitado.

### RF06 inválido — resposta enviada na criação

```json
{
    "title": "Exemplo inválido",
    "description": "Não será gravado.",
    "questions": [
        {
            "type": "single_choice_with_justification",
            "prompt": "Qual área?",
            "options": [{ "label": "Design" }],
            "justification": "Este campo pertence à resposta do participante."
        }
    ]
}
```

422 em questions.0 porque o objeto contém chave não permitida. Para testar um
texto vazio, remova justification e mude label para "": errors terá
questions.0.options.0.label, com Informe o texto da alternativa. Nenhum registro
parcial é criado. Ids, question_id e position nos objetos também são rejeitados.
Os status 401/419 seguem a preparação de sessão já documentada.

Testes automatizados: CreateChoiceQuestionsTest.php, junto às regressões RF03/04.
O preview de UI usa dados fictícios; não afirma teste manual real destes JSONs
no aplicativo Postman ou gravação no MySQL.

## Dashboard — GET {{base_url}}/api/v1/forms/mine

Depois do login descrito neste guia, mantenha os cookies da sessão e envie:

```http
GET {{base_url}}/api/v1/forms/mine?page=1
Accept: application/json
```

Sem corpo JSON nem cabeçalho CSRF nesse GET. A autenticação continua por sessão,
sem Bearer token. O script da coleção já ignora CSRF em GET.
O endpoint lista somente formulários criados pela conta autenticada, dos mais
recentes aos mais antigos, com 12 por página. `creator_id`/`user_id` na URL não
permitem consultar a lista de outra conta.

Resposta **200**, exemplo do conteúdo de `data`:

```json
[
    {
        "id": 1,
        "title": "Pesquisa CATI",
        "description": "Conte como podemos melhorar nossos encontros.",
        "request_contact_email": false,
        "creator_id": 1,
        "created_at": "2026-11-03T20:00:00.000000Z",
        "updated_at": "2026-11-03T20:00:00.000000Z"
    }
]
```

O objeto completo da resposta contém `data` (a lista acima), `links` e `meta`.
`links` contém `first`, `last`, `prev` e `next`; `meta` inclui `current_page`,
`last_page`, `per_page` (12), `total`, `from`, `to` e links de navegação.
IDs, datas, endereços e textos da paginação variam conforme o ambiente.
Não há campo `questions` nessa consulta. Para a próxima página, envie `page=2`.
Sem formulários da conta, a resposta continua 200 com `data: []` e `meta.total: 0`.

Erros essenciais:

- **401**, sem sessão: JSON com `message` de não autenticação; refaça o login.
- **422**, por exemplo `?page=0` ou `?page=abc`: JSON com `message` e
  `errors.page` (lista de mensagens). `page` deve ser inteiro maior ou igual a 1.

Para verificar, crie um formulário pelo POST já documentado e consulte esse GET
com a mesma conta. Outra conta não verá esse registro em “Meus formulários”.
Esse recorte do dashboard não altera a regra futura de consulta global do RF14.
Testes automatizados: `php artisan test tests/Feature/Api/V1/ListMyFormsTest.php`,
com SQLite isolado. Os exemplos não pressupõem execução real no Postman.

## RF07 — configurar solicitação de e-mail

Use `POST {{base_url}}/api/v1/forms` com a mesma sessão, cookies e proteção CSRF
já descritos neste guia. Headers: `Accept: application/json`,
`Content-Type: application/json` e `X-XSRF-TOKEN` pelo script existente da coleção.

Solicitação ativada (raw JSON):

```json
{
    "title": "Pesquisa com contato",
    "description": "Compartilhe sua opinião.",
    "request_contact_email": true,
    "questions": [{ "type": "discursive", "prompt": "Como podemos melhorar?" }]
}
```

Solicitação desativada:

```json
{
    "title": "Pesquisa sem contato",
    "description": "Compartilhe sua opinião.",
    "request_contact_email": false
}
```

Requisição anterior, sem a configuração, continua válida e usa `false`:

```json
{
    "title": "Pesquisa compatível",
    "description": "Compartilhe sua opinião."
}
```

Retorno **201**, exemplo da solicitação ativada (IDs e datas ilustrativos):

```json
{
    "data": {
        "id": 10,
        "title": "Pesquisa com contato",
        "description": "Compartilhe sua opinião.",
        "request_contact_email": true,
        "creator_id": 1,
        "created_at": "2026-11-06T12:00:00.000000Z",
        "updated_at": "2026-11-06T12:00:00.000000Z",
        "questions": [
            {
                "id": 20,
                "type": "discursive",
                "prompt": "Como podemos melhorar?",
                "position": 1,
                "options": []
            }
        ]
    }
}
```

Exemplo inválido (`null` não é aceito):

```json
{
    "title": "Pesquisa inválida",
    "description": "Não deverá ser gravada.",
    "request_contact_email": null
}
```

Retorno **422**:

```json
{
    "message": "Informe se o formulário deve solicitar e-mail de contato.",
    "errors": {
        "request_contact_email": [
            "Informe se o formulário deve solicitar e-mail de contato."
        ]
    }
}
```

A regra nativa `boolean` também aceita `0`, `1`, `"0"` e `"1"`, mas rejeita
`"true"`, `"false"`, listas e objetos. Prefira booleanos JSON.
GET `{{base_url}}/api/v1/forms/mine` também inclui `request_contact_email` em cada
item de `data`, permitindo conferir o valor salvo. A configuração não pode ser
editada depois da criação. O envio de e-mail opcional está descrito no RF09 abaixo.
As regras de autenticação e os demais erros do POST permanecem os mesmos.

## RF08–RF09 — acesso e envio sem login

### 1. Obter o link

Crie um formulário pelos exemplos anteriores ou use “Copiar link” no dashboard.
O POST e o GET de meus formulários agora incluem `public_id` (UUID v4) e
`public_url`. Salve o UUID em `{{public_id}}`; a URL da página é
`{{base_url}}/f/{{public_id}}`. Ele é gerado pelo servidor e não é o ID numérico.
Os exemplos de resposta anteriores também passam a conter esses dois campos.

Para testar anonimamente, use outra coleção/limpe os cookies de autenticação.
**Mantenha o Cookie Jar habilitado.** Não faça login para os próximos passos.
Os scripts de CSRF descritos no início podem ser reutilizados: GET é isento do
header e o POST deve enviar o XSRF obtido pelo GET público, sem passar por `/login`.
Se um script local exigir especificamente GET /login, substitua a instrução por
GET público desta seção; o requisito técnico é obter cookies da mesma sessão.

### 2. Ler o formulário — GET

```http
GET {{base_url}}/api/v1/public/forms/{{public_id}}
Accept: application/json
```

Não há corpo nem token Bearer. Esse GET inicia a sessão anônima e devolve cookies
`XSRF-TOKEN` e de sessão, além dos dados públicos. Resposta **200**, exemplo:

```json
{
    "data": {
        "public_id": "6f887a59-9eb5-49e2-9a11-cf16c6ad5f3b",
        "title": "Pesquisa CATI",
        "description": "Compartilhe sua opinião.",
        "request_contact_email": true,
        "questions": [
            {
                "id": 1,
                "type": "discursive",
                "prompt": "Como podemos melhorar?",
                "position": 1,
                "options": []
            },
            {
                "id": 2,
                "type": "multiple_choice",
                "prompt": "Quais áreas interessam?",
                "position": 2,
                "options": [
                    { "id": 1, "label": "Desenvolvimento", "position": 1 },
                    { "id": 2, "label": "Design", "position": 2 }
                ]
            },
            {
                "id": 3,
                "type": "single_choice_with_justification",
                "prompt": "Qual prefere?",
                "position": 3,
                "options": [
                    { "id": 3, "label": "Online", "position": 1 },
                    { "id": 4, "label": "Presencial", "position": 2 }
                ]
            }
        ]
    }
}
```

UUID e IDs acima são ilustrativos. **Use os IDs retornados pelo GET do seu
formulário** nos JSONs seguintes. Não há criador nem respostas anteriores na saída.
A página `/f/{{public_id}}` permite preencher os mesmos campos no navegador.

### 3. Enviar respostas — POST anônimo

```http
POST {{base_url}}/api/v1/public/forms/{{public_id}}/submissions
Accept: application/json
Content-Type: application/json
X-XSRF-TOKEN: <valor decodificado do cookie XSRF-TOKEN da mesma sessão>
```

O script da coleção pode preencher o header automaticamente. Preserve os cookies
do GET anterior; não os copie como cabeçalhos separados. Corpo raw JSON:

```json
{
    "contact_email": "participante@example.com",
    "answers": [
        { "question_id": 1, "text": "Gostaria de mais encontros técnicos." },
        { "question_id": 2, "option_ids": [1, 2] },
        {
            "question_id": 3,
            "option_ids": [3],
            "justification": "Facilita a participação de quem mora longe."
        }
    ]
}
```

Retorno **201 Created**:

```json
{ "message": "Resposta enviada com sucesso." }
```

O e-mail é **opcional**, mesmo quando solicitado. Pode ser omitido, vazio ou null;
quando preenchido deve ter formato válido, até 254 caracteres. Se a solicitação
estiver desativada, omita o campo: e-mail não vazio é rejeitado com 422.
Perguntas são obrigatórias; texto e justificativa aceitam até 10.000 caracteres.
Múltipla escolha permite todas as alternativas, sem limite de marcações.
Para formulário sem perguntas, o corpo válido é `{"answers":[]}`.

### 4. Exemplo inválido — justificativa ausente

```json
{
    "answers": [
        { "question_id": 1, "text": "Gostaria de mais encontros técnicos." },
        { "question_id": 2, "option_ids": [1] },
        { "question_id": 3, "option_ids": [3] }
    ]
}
```

Retorno **422**:

```json
{
    "message": "Informe a justificativa da escolha.",
    "errors": {
        "answers.2.justification": ["Informe a justificativa da escolha."]
    }
}
```

Esse erro pressupõe que os IDs pertencem ao formulário consultado. Outros erros:

| Cenário                                                     | Resultado                                               |
| ----------------------------------------------------------- | ------------------------------------------------------- |
| UUID malformado, numérico ou inexistente                    | 404 com `message` na API; não redireciona para login.   |
| Pergunta omitida/repetida ou pertencente a outro formulário | 422, sem gravar a submissão.                            |
| Alternativa de outra pergunta, repetida ou inexistente      | 422 em `answers.N.option_ids` ou seus itens.            |
| Duas alternativas em escolha única                          | 422 em `answers.N.option_ids`.                          |
| Texto discursivo ou justificativa em branco                 | 422 no campo correspondente.                            |
| Token CSRF ausente/inválido no POST                         | 419; repita o GET público para obter cookies da sessão. |
| Mais de 60 requisições de envio/minuto pelo mesmo IP        | 429, com `Retry-After`.                                 |

A confirmação não devolve dados privados nem ID de submissão. Não há endpoint
público para consultar respostas. Repetir um POST válido cria outra submissão;
não há deduplicação por e-mail. Falhas de rede não devem ser repetidas sem avaliar
a possibilidade de o primeiro envio já ter sido gravado.

Verificação automatizada: `php artisan test tests/Feature/PublicForms`, em SQLite
isolado, incluindo persistência dos três tipos e rollback. A execução real no
Postman depende de aplicar as migrations e usar o link/IDs do seu ambiente.

## RF14 — consultar todos os formulários e respostas

Use a sessão autenticada obtida no fluxo de login deste guia e mantenha o Cookie
Jar habilitado. Todas as requisições abaixo usam `Accept: application/json`, sem
corpo, token Bearer ou header CSRF. Qualquer usuário cadastrado pode consultar
formulários de outro criador.

### Listagem geral

```http
GET {{base_url}}/api/v1/forms?page=1
Accept: application/json
```

Retorna 200 com `data` (mesmos resumos de `/forms/mine`), `links` e `meta`, agora
incluindo todos os criadores. São 12 itens por página, mais recentes primeiro.
Salve o `id` numérico de um item em `{{form_id}}`. `/forms/mine` continua exibindo
somente os formulários do usuário autenticado.

### Detalhes

```http
GET {{base_url}}/api/v1/forms/{{form_id}}
Accept: application/json
```

Retorna 200 com `data` nos mesmos campos do POST de criação, incluindo todas as
perguntas e alternativas com seus IDs. Use esses IDs para relacionar as respostas
abaixo. Não use `{{public_id}}` nessas rotas autenticadas.

### Respostas recebidas

```http
GET {{base_url}}/api/v1/forms/{{form_id}}/submissions?page=1
Accept: application/json
```

Exemplo dos registros em `data` (IDs ilustrativos):

```json
{
    "data": [
        {
            "id": 10,
            "form_id": 1,
            "contact_email": "participante@example.com",
            "created_at": "2026-11-10T12:00:00.000000Z",
            "answers": [
                {
                    "id": 20,
                    "question_id": 1,
                    "text": "Mais encontros técnicos.",
                    "justification": null,
                    "options": []
                },
                {
                    "id": 21,
                    "question_id": 2,
                    "text": null,
                    "justification": null,
                    "options": [
                        { "id": 1, "label": "Desenvolvimento", "position": 1 },
                        { "id": 2, "label": "Design", "position": 2 }
                    ]
                },
                {
                    "id": 22,
                    "question_id": 3,
                    "text": null,
                    "justification": "Facilita a participação.",
                    "options": [{ "id": 3, "label": "Online", "position": 1 }]
                }
            ]
        }
    ]
}
```

A resposta completa também contém `links` e `meta` da paginação Laravel, como
na listagem de formulários, com 12 envios por página. `contact_email` pode ser
null. Sem respostas, `data` é `[]` e `meta.total` é 0.

Verifique com outra sessão autenticada: os mesmos detalhes e respostas devem
estar disponíveis. Sem login, as três consultas retornam **401** JSON e nenhum
dado de respostas. Formulário inexistente retorna **404**. `page=0` retorna
**422** com `errors.page`. O GET público continua sem expor respostas.

## RF15–RF16 — baixar CSV e PDF

Reutilize o login e o Cookie Jar da coleção. Salve o ID numérico do formulário
em `{{form_id}}`. Qualquer usuário autenticado pode exportar, inclusive formulários
de outro criador. Não envie corpo, Bearer token nem header CSRF.

```http
GET {{base_url}}/api/v1/forms/{{form_id}}/exports/csv
```

```http
GET {{base_url}}/api/v1/forms/{{form_id}}/exports/pdf
```

Use **Send and Download** no menu ao lado de **Send**, ou envie normalmente e
use **Save Response → Save to a file** (os nomes podem variar conforme a versão).
Salve com a extensão `.csv` ou `.pdf`. Os arquivos são respostas de download,
sem payload JSON. O header `Accept: application/json` pode ser mantido para erros;
o sucesso continua retornando o arquivo no Content-Type correspondente.

Confira **200**, `Content-Disposition: attachment` com nome
`formulario-{{form_id}}-respostas.csv` ou `.pdf`, `Content-Type` de CSV UTF-8 ou PDF,
e `Cache-Control` contendo `no-store`.

- CSV: importe com codificação UTF-8 e delimitador vírgula. Confira uma linha por
  envio, acentos, aspas, quebras de linha, alternativas selecionadas, justificativas
  e e-mail quando coletado. Células com início de fórmula recebem apóstrofo de
  proteção, sem modificar o banco.
- PDF: abra em um leitor de PDF e confira título, descrição, identificação de cada
  envio, perguntas, alternativas e justificativas. Submissões e textos longos
  devem continuar em páginas legíveis.
- Confirme que todas as respostas do formulário são incluídas, mesmo quando a
  interface de consulta possui várias páginas, e que nenhum outro formulário aparece.
- Sem respostas: CSV contém cabeçalho; PDF informa “Nenhuma resposta recebida.”.
- Sem sessão autenticada: **401** JSON, sem download. ID inexistente: **404** JSON.
  Não use a rota pública nem o UUID para exportar.

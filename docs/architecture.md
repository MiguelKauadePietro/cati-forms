# Arquitetura — CATI Forms

## Estilo arquitetural

- **Monólito modular**: um único projeto Laravel com Vue integrado por Inertia.js.
- Adotar **MVC com camada de serviços** quando existirem casos de uso ou regras de negócio que justifiquem a separação.
- Não criar microsserviços, repositories genéricos, interfaces artificiais ou camadas extras sem necessidade demonstrável.
- O frontend e o backend compartilham o mesmo repositório, mas mantêm responsabilidades distintas.

## Stack e diretrizes de integração

- Backend: Laravel, na versão instalada e travada pelo projeto.
- Frontend: Vue 3, TypeScript e Inertia.js.
- Biblioteca **principal** de componentes: Vuetify 3.
- Estilos: Tailwind CSS está presente no starter kit; preservar estilos/componentes legados usados até migração controlada, sem criar novas interfaces em shadcn-vue/Reka UI.
- Persistência principal: MySQL; testes automatizados podem usar SQLite isolado, com verificação adicional de compatibilidade com MySQL.
- Testes: usar a infraestrutura Pest já configurada.

## Responsabilidades

- **Pages e components Vue:** exibir dados, controlar interações, apresentar erros e feedback de carregamento. Não concentrar regras de autorização ou persistência.
- **Rotas e controllers Laravel:** receber solicitações, aplicar middleware e coordenar casos de uso. Manter controllers pequenos.
- **Form Requests:** validar e autorizar entradas quando aplicável.
- **Services / Actions:** implementar casos de uso que exigem coordenação ou regras de negócio. Reutilizar mecanismos nativos do framework, por exemplo Fortify na autenticação.
- **Models Eloquent:** representar entidades e relacionamentos com persistência no banco.
- **Migrations:** versionar alterações do esquema.

## Módulos por domínio

| Módulo                  | Escopo                                | Requisitos |
| ----------------------- | ------------------------------------- | ---------- |
| Usuários e autenticação | Cadastro, login e logout              | RF01–RF02  |
| Formulários e perguntas | Criação e configuração das perguntas  | RF03–RF07  |
| Compartilhamento        | Link público e acesso sem login       | RF08–RF09  |
| Respostas e consultas   | Validar, armazenar e consultar envios | RF10–RF14  |
| Exportação              | Exportar resultados em CSV/PDF        | RF15–RF16  |

## Dados e integração futura

- Manter formulários, perguntas e respostas como entidades independentes e relacionadas (RNF08).
- Disponibilizar contratos bem definidos e projetar integrações futuras com o módulo de Controle de Pessoas (RNF07), **sem antecipar uma API REST completa para todas as páginas Inertia**.
- Definir os relacionamentos e a integridade das entidades antes de criar migrations de cada módulo.
- Não implementar antecipadamente funcionalidades futuras fora do MVP.

## Comunicação e API

O CATI Forms utiliza arquitetura híbrida:

- Inertia.js entrega as páginas. No módulo de formulários, as operações de dados
  são feitas pela API REST JSON versionada em `/api/v1`.
- RF03 usa somente `POST /api/v1/forms` para criar registros; não há POST web
  equivalente. A página `GET /forms/create` apenas entrega `forms/Create`.
- Controllers Web e API compartilham Services quando houver uma operação com
  regras ou coordenação reutilizável. Não criar wrappers para uma gravação trivial.
- Regras de negócio não devem ficar nos Controllers.
- Endpoints privados exigem autenticação.
- Endpoints públicos retornam somente dados necessários.
- Documentar contratos, respostas e códigos HTTP.
- Não duplicar funcionalidades sem necessidade.

### Decisão RF03: API com sessão na mesma origem

`routes/api.php` é registrado explicitamente pelo callback `then` do bootstrap,
com prefixo `api/v1`, nomes `api.v1.*` e middleware `web`. O POST privado exige
`auth:web`. Assim cookies, sessão Fortify e proteção CSRF são reutilizados sem
Sanctum, tokens ou dependências novas. O grupo padrão `api`, sozinho, não inicia
sessão nem verifica CSRF. O nome do grupo de middleware não determina se a
resposta é JSON: o controller usa Resource e as exceções de `api/*` já são JSON.

Esse contrato atende o frontend servido pela própria aplicação. Autenticação de
clientes externos e chamadas entre origens exigirão decisão própria; não são
implementadas agora. Novos endpoints públicos devem declarar seu acesso sem
remover a proteção do POST privado. Ver [contrato RF03](api/v1/forms.md).

## Decisões arquiteturais

Quando houver uma decisão relevante (novo módulo, nova dependência, padrão, contrato de integração), registrar motivação, alternativas e impacto na documentação correspondente.

Consulte `docs/requirements.md` para regras e comportamentos; este documento descreve apenas as decisões técnicas.

## Persistência técnica do MVP

Cache em arquivos e fila síncrona reduzem as tabelas técnicas a users, sessions
e migrations. Sessões permanecem no banco. Detalhes e limites de implantação:
[banco-de-dados/estrutura.md](banco-de-dados/estrutura.md).

## Evolução RF04 — criação atômica

A Action CreateForm passa a coordenar múltiplas gravações dependentes em uma
transação: forms e questions. Inertia continua entregando a página; a mesma API
cria o conjunto completo. Não há rascunho persistido ou edição posterior (RN07).
questions é entidade independente relacionada, conforme RNF08. Campos de RF05 e
RF06, respostas e novos endpoints não são antecipados. Ver explicacao.md na raiz.

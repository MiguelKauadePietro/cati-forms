# Requisitos do produto — CATI Forms

**Fonte:** documento de requisitos “Banco de Talentos — Software 2026”, CATI Jr. Este arquivo consolida os requisitos para uso do Codex; em caso de divergência, conferir o documento original com a equipe.

## Objetivo

Criar uma plataforma web de criação, compartilhamento e armazenamento de formulários semelhante ao Google Forms, para coletar e organizar respostas e possibilitar futura integração ao módulo de Controle de Pessoas.

## Requisitos funcionais

### Gestão de usuários

- **RF01:** cadastro de usuários com **username único** e senha.
- **RF02:** login e logout de usuários cadastrados.

### Criação de formulários

- **RF03:** qualquer usuário cadastrado pode criar formulário com título e descrição.
- **RF04:** perguntas discursivas.
- **RF05:** perguntas de múltipla escolha, permitindo marcar uma quantidade ilimitada de opções.
- **RF06:** perguntas de escolha única com justificativa.
- **RF07:** criador define se o formulário solicitará o e-mail de contato do respondente.

### Compartilhamento

- **RF08:** gerar link de acesso para cada formulário criado.
- **RF09:** qualquer pessoa com o link pode acessar e responder, sem cadastro.

### Respostas e consulta

- **RF10:** todas as perguntas devem estar preenchidas antes do envio.
- **RF11:** justificativa obrigatória nas perguntas de escolha única com justificativa.
- **RF12:** armazenar cada formulário e suas perguntas de forma independente.
- **RF13:** armazenar respostas vinculadas ao formulário correspondente.
- **RF14:** qualquer usuário cadastrado visualiza lista, detalhes e respostas de **todos** os formulários.

### Exportação

- **RF15:** exportar respostas de um formulário em CSV.
- **RF16:** exportar respostas de um formulário em PDF.

## Regras de negócio

- **RN01:** username único em toda a plataforma.
- **RN02:** apenas um tipo de usuário, com acesso total a qualquer formulário.
- **RN03:** qualquer pessoa cadastrada e autenticada pode criar formulário.
- **RN04:** todas as perguntas são obrigatórias nesta versão.
- **RN05:** na escolha única, justificativa obrigatória.
- **RN06:** na múltipla escolha, não há limite de opções marcadas.
- **RN07:** formulários não podem ser editados nem excluídos após a criação.
- **RN08:** solicitação de e-mail de contato é opcional e definida pelo criador de cada formulário.
- **RN09:** respondentes externos não precisam de cadastro e acessam por link.
- **RN10:** usuários cadastrados podem ver todos os detalhes de qualquer formulário, inclusive respostas.
- **RN11:** respostas devem ser organizáveis e exportáveis em PDF e CSV.

## Requisitos não funcionais

- **RNF01:** interface simples e intuitiva, sem exigir treinamento prévio para criar formulário.
- **RNF02:** página de resposta responsiva em computadores e celulares.
- **RNF03:** carregamento de formulário para resposta em tempo adequado; meta preliminar de até 3 segundos.
- **RNF04:** acesso pelo navegador, sem instalação.
- **RNF05:** senhas armazenadas em **hash**, nunca em texto puro.
- **RNF06:** perfis e controles avançados de segurança não são prioridade nesta versão.
- **RNF07:** modularidade e API bem definida para integração futura ao módulo de Controle de Pessoas.
- **RNF08:** formulários, perguntas e respostas como entidades independentes e relacionadas.

## Fora do escopo do MVP

- Edição e exclusão de formulários após criação.
- Níveis de acesso e perfis diferenciados.
- Perguntas opcionais e limites de opções na múltipla escolha.
- Tipos de pergunta adicionais aos três definidos.

## Possíveis evoluções (não implementar agora)

Integração com Controle de Pessoas; edição/exclusão; perfis; perguntas opcionais; escala, data ou upload; recuperação de senha e controles adicionais de privacidade.

## Pontos não especificados — não inventar

O documento não define expressamente, por exemplo, se o mesmo respondente pode enviar múltiplas vezes, qual deve ser o número mínimo de alternativas objetivas, se o e-mail solicitado ao respondente é obrigatório, ou o layout do PDF exportado. Propor decisões separadamente e validá-las antes de torná-las regras do sistema.

# Interface com Vuetify 3

Vuetify é a biblioteca principal para novos componentes. Reka UI e Tailwind
continuam disponíveis para as telas existentes, inclusive autenticação.
A demonstração está no dashboard autenticado; não há novas rotas.
Esta integração prepara a interface dos RF03–RF16, sem implementar essas regras.

## Inicialização e estilos

`resources/js/plugins/vuetify.ts` cria uma instância por aplicação no callback
`withApp` do Inertia. O plugin Vite importa automaticamente os componentes usados.
Material Design Icons usa `@mdi/font` e `vuetify/iconsets/mdi`, com arquivos locais
empacotados pelo build. Não é necessário CDN nem alterar o .env.

Sass configura os estilos em `resources/css/vuetify-settings.scss`. O reset,
as utilities e o color pack globais do Vuetify estão desativados para evitar
conflitos com Tailwind; componentes e temas do Vuetify permanecem disponíveis.
Cada nova área Vuetify deve ter um contexto VApp. A demonstração limita a altura
mínima desse contexto para coexistir com o layout atual do starter kit.

## Aparência e SSR

A preferência existente light/dark/system continua usando localStorage, cookie
e a classe dark do documento. O middleware aceita somente esses três valores e
compartilha a preferência com Blade e Inertia. No navegador, a classe do documento
sincroniza o Vuetify por um observer, removido quando a aplicação é desmontada.
Mudanças da preferência do sistema atualizam também a aparência resolvida.

SSR já está habilitado em config/inertia.php. A versão instalada de
@inertiajs/vite usa app.ts como entrada de cliente e servidor; não se cria uma
entrada ssr.ts duplicada. Vuetify usa ssr: true e ssr.noExternal no Vite.
A instância de tema não é compartilhada entre requisições. Para system, servidor
e primeira renderização de hidratação usam light; a preferência do sistema é
aplicada após a montagem. A inicialização do navegador ocorre em mounted.

## Verificação

- npm run types:check
- npm run check
- npm run build
- npm run build:ssr
- php artisan test --compact tests/Feature/AppearanceTest.php

O teste de aparência verifica valores válidos e fallback de cookie inválido.
Validar também a renderização do bundle SSR para dashboard claro/escuro/system,
login e cadastro. No navegador, conferir fontes MDI, interação local, alternância
de tema e preservação das telas existentes. A demonstração não envia dados.

## Paleta de cores

A cor principal é #1976D2; a secundária e variante primary-darken-1 é #0D47A1.
Os temas Vuetify e tokens Tailwind compartilham essas cores nos modos claro e
escuro. Botões preenchidos usam texto branco. Links de autenticação e da página
inicial usam azul no claro e branco sublinhado no escuro para manter contraste.
Foco, navegação lateral e o indicador Inertia acompanham a paleta. Fundos neutros,
ilustrações e cores semânticas de erro/sucesso são preservados.

Skill ui-taste consultada, com os playbooks polish e craft: consistência de
cores, foco visível e contraste aplicados ao plugin Vuetify, app.css, ações da
página Welcome, links Login/Register e marca da navegação lateral.

Validação da paleta: TypeScript, lint, formatação e builds cliente/SSR passaram.
O texto branco sobre #1976D2 tem contraste 4,60:1; sobre #0D47A1, 8,63:1.
Login, cadastro e dashboard foram inspecionados em 12 capturas Chromium, nos
modos claro/escuro e tamanhos 390×844 e 1440×1000. A prévia usou o bundle SSR e
assets compilados com dados fictícios, sem banco ou submissões. Capturas em
/tmp/cati-color-{Login,Register,Dashboard}-{light,dark}-{mobile,desktop}.png.
Não houve teste manual de submissão de formulários nesta alteração de cores.

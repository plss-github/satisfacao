# Pesquisa de Satisfação — plugin GLPI 11

Plugin que dispara uma pesquisa de satisfação configurável sempre que um
chamado é fechado (status 6). A resposta acontece dentro do próprio
chamado — como **item da linha do tempo** (mesmo mecanismo visual de
acompanhamentos/tarefas/soluções) e também como aba —, disponível apenas
para o(s) solicitante(s), uma única vez por chamado.

## Funcionalidades

- **Entrada própria no menu Administração** ("Pesquisa de Satisfação",
  com submenu Perguntas/Mensagens/Resultados), visível só pra quem tem o
  direito `config`. Continua acessível também pelo ícone de engrenagem
  em Configurar > Plugins (as duas entradas levam pras mesmas telas).
- Editor de perguntas com 4 tipos de campo: nota (1-5), texto livre, caixa de
  seleção (múltipla escolha) e lista suspensa (escolha única). Perguntas
  são por entidade, têm ordem de exibição, podem ser marcadas como
  obrigatórias e ativadas/desativadas sem serem excluídas. Não existe um
  objeto "pesquisa" separado: todas as perguntas ativas de uma entidade
  formam a pesquisa daquela entidade.
- **Campo condicional ("outros, comente"):** em perguntas de caixa de
  seleção ou lista suspensa, é possível marcar uma das opções (ex:
  "OUTROS, por favor comente.") como a "opção que libera campo de
  comentário livre". Ao marcar/selecionar essa opção específica, aparece
  (via JS, sem recarregar a página) uma caixa de texto livre opcional
  pro respondente comentar. Configurado no formulário da pergunta,
  colando o texto exato da opção no campo dedicado.
- Ao fechar um chamado, se a entidade tiver ao menos uma pergunta ativa,
  o plugin cria automaticamente a pesquisa. Ela aparece **na linha do
  tempo do chamado** (via hooks `timeline_answer_actions` +
  `timeline_items`, o mesmo mecanismo que a nativa do GLPI *deveria* usar
  — ver nota abaixo) e também como aba "Pesquisa de satisfação"
  (mantida como acesso alternativo mais simples/estável).
- **Notificação quando a pesquisa é gerada.** O plugin registra um
  evento próprio (`satisfacao_survey`) no sistema de notificações do
  GLPI e cria, na instalação, uma notificação padrão pronta pra uso
  (modelo + destinatário "Solicitante" já configurados) — não precisa
  configurar nada a mais, só habilitar notificações por e-mail no GLPI
  (Configurar > Notificações) se ainda não estiver. Editável/desativável
  normalmente em Configurar > Notificações > Ticket, como qualquer outra
  notificação.
- Depois de respondida, tanto o item da timeline quanto a aba passam a
  somente leitura (visíveis para qualquer um com acesso ao chamado).
- **Mensagens personalizadas por entidade** em **Configurar > Plugins >
  Pesquisa de Satisfação > Mensagens de cabeçalho e agradecimento**:
  mensagem de cabeçalho (aparece acima das perguntas, antes de
  responder) e mensagem de agradecimento (aparece depois de responder,
  junto com a data de resposta). A tela edita a mensagem da **entidade
  ativa no momento** (troque a entidade pelo seletor do topo do GLPI
  pra editar outra). Deixar em branco = não mostra nada extra
  (cabeçalho) ou mantém o texto padrão "Respondida em..." (agradecimento).
- Tela de resultados (mesma área de configuração) com indicadores —
  total de pesquisas geradas, respondidas, taxa de resposta e nota média
  — e uma listagem (motor de busca nativo do GLPI: filtro, ordenação e
  exportação CSV/PDF) de cada chamado pesquisado, com acesso ao
  detalhe das respostas.
- **Critérios de exclusão de disparo**, também em **Mensagens** (seção
  "Não gerar pesquisa quando"), por entidade: origem da requisição
  (`RequestType`, ex: um tipo dedicado a chamados abertos por ferramenta
  de monitoramento/integração), categoria do chamado, um usuário
  solicitante específico (ex: conta técnica usada por uma integração) ou
  um grupo do solicitante. Se um chamado bater em **qualquer** um dos
  critérios marcados, a pesquisa não é criada quando ele fecha — os
  demais critérios (pergunta ativa, "uma pesquisa por chamado") continuam
  valendo normalmente. Sem nada marcado, nenhum chamado é excluído
  (comportamento igual ao de antes dessa funcionalidade existir).
  Implementado em `SurveySettings::ticketIsExcluded()`, chamado por
  `SurveyTicket::createForTicket()` antes de criar a pesquisa.

## Formato das respostas (`glpi_plugin_satisfacao_answers.answer`)

- `rating`: número (`"1"`..`"5"`) como texto simples.
- `text`: texto livre como está.
- `checkbox` **sem** `other_option_label` configurado: JSON array simples
  das opções marcadas, ex. `["Opção A","Opção B"]`.
- `checkbox` **com** `other_option_label` configurado: JSON objeto
  `{"selected": ["Opção A", "..."], "other": "texto do comentário ou null"}`.
- `dropdown` **sem** `other_option_label`: texto simples com a opção
  escolhida.
- `dropdown` **com** `other_option_label`: JSON objeto
  `{"selected": "opção escolhida", "other": "texto do comentário ou null"}`.

`SurveyTicket::decodeChoiceAnswer()` lê os dois formatos (novo e antigo)
de forma transparente, então perguntas que já tinham respostas antes
dessa funcionalidade continuam sendo exibidas corretamente.

## Acesso via banco pra BI externo

O plugin cria, na instalação, a view **`glpi_plugin_satisfacao_vw_answers`**
— uma linha por resposta, já com o chamado e a entidade, e já tratando o
JSON dos tipos checkbox/lista (não precisa fazer parse manual na
ferramenta de BI). Aponte o painel pra essa view em vez das tabelas
internas: ela é o "contrato estável" do plugin — se eu mudar o schema
interno no futuro, a view continua com essa mesma estrutura de colunas.

| Coluna | Conteúdo |
|---|---|
| `survey_id`, `tickets_id`, `ticket_title` | identificação do chamado |
| `entities_id`, `entity_name` | entidade do chamado |
| `ticket_closed_date` | quando o chamado fechou (= `date_begin` da pesquisa) |
| `survey_answered_date` | quando foi respondida (`NULL` se ainda pendente) |
| `survey_status`, `survey_status_label` | `1`/"Aguardando resposta" ou `2`/"Respondida" |
| `answered_by_users_id`, `answered_by_login` | quem respondeu |
| `question_id`, `question_name`, `question_type`, `question_rank` | a pergunta |
| `answer_id`, `answer_raw` | resposta bruta (como está gravada) |
| `answer_value` | resposta "limpa": número/texto direto pra `rating`/`text`/`dropdown` e `checkbox` sem opção condicional; array JSON como texto pra `checkbox` com múltiplas opções marcadas (não dá pra achatar em uma coluna escalar sem duplicar linha por opção) |
| `answer_other_comment` | o comentário livre da opção "outros" (`NULL` se a pergunta não tem essa opção ou a pessoa não marcou ela) |

Exemplos de consulta:

```sql
-- Nota média por mês, só perguntas do tipo "nota"
SELECT
    DATE_FORMAT(ticket_closed_date, '%Y-%m') AS mes,
    ROUND(AVG(answer_value), 2)              AS nota_media,
    COUNT(*)                                 AS respostas
FROM glpi_plugin_satisfacao_vw_answers
WHERE question_type = 'rating' AND survey_status = 2
GROUP BY mes
ORDER BY mes;

-- Taxa de resposta por entidade
SELECT
    entity_name,
    SUM(survey_status = 2) / COUNT(DISTINCT survey_id) AS taxa_resposta
FROM glpi_plugin_satisfacao_vw_answers
GROUP BY entity_name;

-- Todos os comentários livres (incluindo "outros")
SELECT ticket_title, question_name, answer_other_comment
FROM glpi_plugin_satisfacao_vw_answers
WHERE answer_other_comment IS NOT NULL
   OR question_type = 'text';
```

Pra checkbox com múltiplas opções marcadas, se precisar de uma linha por
opção (pra contar ocorrência de cada opção separadamente), extraia com
`JSON_TABLE` (MariaDB 10.6+) direto na sua consulta — não incluí isso na
view pra manter uma linha por resposta como padrão.

**Segurança:** não aponte a ferramenta de BI pro usuário/senha principal
do GLPI. Crie um usuário só de leitura, restrito à view (opcional:
restrinja mais ainda, sem acesso às tabelas internas do plugin nem ao
resto do banco do GLPI):

```sql
CREATE USER 'satisfacao_bi'@'%' IDENTIFIED BY 'uma_senha_forte_aqui';
GRANT SELECT ON glpidb.glpi_plugin_satisfacao_vw_answers TO 'satisfacao_bi'@'%';
FLUSH PRIVILEGES;
```

(troque `glpidb` pelo nome real do banco do GLPI de vocês, e `%` pelo
host/IP de onde a ferramenta de BI vai conectar, se quiser restringir).

## Atenção: coexistência com a pesquisa nativa do GLPI

Este plugin **não desabilita** a pesquisa de satisfação nativa do GLPI —
são dois mecanismos independentes. Testado: se a entidade tiver a
pesquisa nativa configurada com taxa de amostragem > 0% (Configurar >
Geral > Assistência, ou por entidade), fechar um chamado gera **as
duas** pesquisas ao mesmo tempo. Em instalação nova do GLPI a taxa vem
em 0% (nativa efetivamente desligada), mas confirme isso nas entidades
onde for usar este plugin — desative a nativa (taxa 0% ou tipo
"Nenhuma") pra não duplicar.

## Instalação

1. Copiar a pasta `satisfacao/` para `<raiz do GLPI>/plugins/` (ou
   `marketplace/`), resultando em `<raiz do GLPI>/plugins/satisfacao/`.
2. Em **Configurar > Plugins**, localizar "Pesquisa de Satisfação",
   clicar em **Instalar** e depois **Ativar**.
3. Clicar no ícone de engrenagem do plugin para abrir a tela de
   perguntas e cadastrar ao menos uma pergunta ativa por entidade que
   deve usar a pesquisa.

Se já tinha uma instalação anterior deste plugin (antes do campo
"opção que libera campo de comentário", da notificação ou da view de
BI), rodar **Instalar** de novo em Configurar > Plugins (ou
`bin/console plugin:install satisfacao --force`) adiciona o que estiver
faltando sem apagar perguntas/respostas existentes.

## Testado

Validado de ponta a ponta contra uma instância real (Docker, imagem
`glpi/glpi:11.0.9` + MariaDB 10.11 — ver `docker-compose.yml` na raiz do
repositório), incluindo reinstalação completa do zero (sem depender de
cache manual limpo durante o desenvolvimento): instalação/ativação via
`bin/console plugin:install` e `plugin:activate`, CRUD das 4 perguntas,
fechamento de chamado disparando a pesquisa, regra de "uma pesquisa por
chamado" (reabrir/fechar de novo não duplica), o item aparecendo na
**linha do tempo do chamado** com o formulário completo (nota, texto
livre, checkbox, lista), envio da resposta pelo formulário da timeline,
transição para somente leitura (na timeline e na aba) após responder,
bloqueio de reenvio, e tela de resultados com indicadores + listagem.
Também validado: solicitante com perfil **Self-Service** (interface
simplificada) conseguindo ver e responder normalmente; e o campo
condicional "opção que libera comentário" em pergunta de checkbox e de
lista suspensa (toggle via JS, gravação no formato objeto, exibição
somente leitura mostrando o comentário); as mensagens de cabeçalho e
agradecimento por entidade, aparecendo no lugar certo do formulário
(cabeçalho antes de responder, agradecimento depois); e a entrada no
menu Administração — aparece com o ícone e a URL certos pra quem tem o
direito `config`, breadcrumb da página reflete "Administração > Pesquisa
de Satisfação" em vez de "Configurar > Plugins", e a entrada **some**
(além do acesso direto por URL continuar bloqueado, 403) pra um usuário
Self-Service sem o direito. Também validado: instalação limpa já cria a
notificação (modelo + tradução + notificação + destinatário) pronta pra
uso; fechar um chamado de teste (com notificações e e-mail do solicitante
habilitados) enfileira a notificação em `glpi_queuednotifications` com
assunto, corpo e link pro chamado corretos, sem precisar de nenhuma
configuração manual extra. Também validado: a view
`glpi_plugin_satisfacao_vw_answers` é criada na instalação limpa e, com
dados de teste cobrindo dropdown simples e checkbox com opção "outros",
`answer_value`/`answer_other_comment` extraem certinho do JSON via
`JSON_EXTRACT`/`JSON_UNQUOTE`. Também validado: a coluna "Entidade"
aparece em "Selecionar itens padrão a exibir" e na listagem (nome
correto da entidade, ex. "Root entity") tanto na lista de perguntas
quanto na de resultados, junto com as outras colunas próprias (tipo,
ativa, obrigatória, ordem, última modificação).

Ainda não testado manualmente: múltiplas entidades, e o cenário de outro
usuário (não solicitante) vendo a aba em modo "aguardando resposta"
(a lógica existe em `SurveyTicket::showSurvey()`, mas não foi exercitada
com um segundo usuário logado).

## Revisão contra a skill `glpi-plugin-dev` (padrões oficiais 11.x)

Revisão feita comparando o plugin com a documentação oficial de plugins
GLPI e com o código real do plugin oficial `satisfaction` (10.x → 11.x),
mais verificação direta contra o código-fonte do GLPI 11.0.9 rodando no
ambiente de teste. Corrigido:

- **`include('../../../inc/includes.php')` removido** de todos os
  `front/*.php` e `ajax/*.php`. Confirmado no fonte: esse arquivo, no
  GLPI 11, só avisa sobre variáveis globais obsoletas — não faz mais
  bootstrap (isso já é feito pelo kernel Symfony antes do script rodar).
  O plugin oficial `satisfaction` remove esse include na versão 11.x.
- **`Plugin::getWebDir('satisfacao')` → constante `PLUGIN_SATISFACAO_WEBDIR`**
  (`$CFG_GLPI['root_doc'] . '/plugins/satisfacao'`, definida em
  `setup.php`). Confirmado: `Plugin::getWebDir()` está marcado
  `@deprecated 11.0` no código-fonte (`Toolbox::deprecated()` é chamado a
  cada uso).
- **`Html::displayRightError()` → `throw new
  \Glpi\Exception\Http\AccessDeniedHttpException()`** em `ajax/answer.php`.
  Confirmado deprecated no fonte (a própria função chama
  `Toolbox::deprecated()` e lança a exceção por baixo — funcionava, mas
  pelo caminho descontinuado).
- **`Html::entities_deep()` → `htmlescape()`** em todo o plugin.
  Confirmado `@deprecated 11.0.0` no fonte; `htmlescape()` é o
  substituto direto (`htmlspecialchars((string) $v)`).
- **`$PLUGIN_HOOKS['csrf_compliant']` removido** — não lido em nenhum
  lugar do core GLPI 11.0.9 (grep no fonte não encontra consumidor); o
  plugin oficial `satisfaction` também removeu esse hook na versão 11.x.
- **`requirements.php.min` adicionado** em `setup.php` (`8.2`, mínimo
  observado nos plugins oficiais 11.x).

Testado de novo depois das correções: páginas carregam, link/form apontam
pra URL certa, fluxo completo (fechar → responder → somente leitura)
funciona, e um teste de erro (pesquisa inexistente) devolve HTTP 403 com
a página padrão de acesso negado do GLPI, confirmando que a exceção
substitui `displayRightError()` corretamente.

### Desvios conhecidos, não corrigidos (decisão em aberto)

- **Direito próprio do plugin.** O plugin oficial `satisfaction` declara
  um direito dedicado (`plugin_satisfaction`), registrado via
  `ProfileRight::addProfileRights()` na instalação, com aba própria no
  perfil. Este plugin reaproveita o direito `config` do GLPI (com
  `canCreate()`/`canView()`/`canUpdate()`/`canPurge()` sobrescritos,
  porque `config` só tem os bits READ/UPDATE). Funciona, mas não dá pra
  liberar "ver resultados" sem liberar acesso total de configuração do
  GLPI pra alguém. Documentado desde a v1 como limitação conhecida; não
  mudei porque é uma alteração de modelo de direitos com impacto em quem
  já tem o plugin instalado — avisar antes de implementar.
- **HTML montado em PHP (`echo`) em vez de templates Twig.** O plugin
  oficial `satisfaction` 11.x renderiza todos os formulários via
  `TemplateRenderer` + arquivos `.twig` em `templates/`. Este plugin usa
  `echo` direto (funciona e é mais simples de manter num plugin deste
  tamanho, mas não é o padrão "idiomático" do GLPI 11). Não reescrevi
  porque é uma refatoração grande e de alto risco de regressão sem
  ganho funcional — o comportamento já está validado de ponta a ponta.
- **`timeline_items`/`timeline_answer_actions`** não aparecem na
  documentação da skill (que cobre até `timeline_actions`/
  `show_in_timeline`), mas são os hooks corretos pro GLPI 11: o
  código-fonte marca `Hooks::SHOW_IN_TIMELINE` como
  `@deprecated 11.0.0 Use TIMELINE_ITEMS instead`. Mantido como está.

## Correções feitas a partir do teste em ambiente real

O GLPI 11 mudou algumas convenções em relação a versões anteriores; as
correções abaixo só apareceram testando contra uma instância de verdade:

- **`$_SERVER['PHP_SELF']` não reflete mais a URL da página.** No GLPI
  11 toda requisição passa por um único front controller
  (`public/index.php`), então `PHP_SELF` resolve pra `/index.php` (ou
  `/`), não pro `front/xxx.php` que está sendo exibido. Usar isso como
  destino de formulário (`<form action="...">`) faz o POST cair na
  URL `/`, e o GLPI tem um listener (`CatchInventoryAgentRequestListener`)
  que trata **qualquer POST não-vazio pra `/`** como um agente de
  inventário enviando dados — resultando no erro "Inventory is
  disabled" em vez de processar o formulário. Troquei toda referência a
  `$_SERVER['PHP_SELF']` (em `Html::header()`, `Html::redirect()` e
  `<form action>`) pela URL explícita via `PLUGIN_SATISFACAO_WEBDIR`.
  Isso derrubou o salvamento das mensagens de cabeçalho/agradecimento
  especificamente (o único form "cru" do plugin, feito à mão em vez de
  usar `showForm()`/`initForm()` do `CommonDBTM`, que monta a URL certa
  sozinho).
- `CommonDBTM::rawSearchOptions()` e `CommonGLPI::getTabNameForItem()`
  agora são métodos de **instância** (não estáticos) no GLPI 11.
- **A coluna "Entidade" não aparece automaticamente** em "Selecionar
  itens padrão a exibir" pra um itemtype de plugin, mesmo a tabela tendo
  `entities_id`. O `CommonDBTM::rawSearchOptions()` padrão só adiciona
  `name` (se o campo existir) e `is_recursive` (id 86, também só se o
  campo existir — nosso `Question`/`SurveyTicket` não têm essa coluna).
  A injeção automática do id 80 "Entity" que existe no motor de busca é
  restrita a um conjunto fixo de itemtypes do core (`Glpi\Search\SearchOption`),
  não genérica. `Question` e `SurveyTicket` agora sobrescrevem
  `rawSearchOptions()` com as colunas próprias (nome, tipo, ativa,
  obrigatória, ordem, última modificação) mais uma entrada manual pra
  entidade (`id => 80, table => 'glpi_entities', field => 'completename'`
  — o motor de busca já sabe fazer o join usando a coluna `entities_id`
  padrão, não precisa de `linkfield`).
- O direito `config` só tem os bits READ/UPDATE — não CREATE/PURGE — então
  `Question` precisa sobrescrever `canCreate()`/`canView()`/`canUpdate()`/
  `canPurge()` para funcionar com o form padrão do GLPI.
- `Html::autocompletionTextField()` foi removido do GLPI 11; usar
  `Html::input()`.
- O nome de tabela de uma classe `CommonDBTM` é derivado do nome da
  classe (plural, minúsculo) — `SurveyTicket` mapeia para
  `glpi_plugin_satisfacao_surveytickets`, não para um nome arbitrário.
- O kernel do GLPI 11 já valida (e **consome**) o token CSRF
  automaticamente pra qualquer POST não-XHR
  (`Glpi\Kernel\Listener\ControllerListener\CheckCsrfListener`) — um
  plugin **não deve** chamar `Session::checkCSRF()` de novo no próprio
  script, ou a segunda validação falha porque o token já foi consumido.
- A pesquisa nativa de satisfação (`TicketSatisfaction`) não usa nenhum
  hook de plugin — é hardcoded em `CommonITILObject`/`Ticket`, renderizada
  como formulário fixo logo abaixo da timeline. Pra um item de plugin
  aparecer *dentro* da timeline de verdade (cronológico, junto com
  acompanhamentos/tarefas), o mecanismo suportado são os hooks
  `timeline_answer_actions` (registra o tipo + o template Twig) e
  `timeline_items` (injeta a entrada no array da timeline, passado por
  referência). O hook `timeline_answer_actions` é resolvido via
  `is_callable()` direto pelo core, **sem garantir que `hook.php` do
  plugin já foi incluído** — por isso o registro usa um callable de
  array `[Classe::class, 'metodo']` apontando pra um método estático
  autoloadável via PSR-4 (`SurveyTicket::getTimelineAnswerActions()`),
  não uma função solta em `hook.php`.
- O template Twig registrado em `timeline_answer_actions` é chamado
  **duas vezes**: uma pra cada item real da timeline (com `entry_i`
  definido) e outra durante a montagem do menu "+" de novos itens (sem
  `entry_i` no contexto) — mesmo com `hide_in_menu: true`. O template
  precisa checar `entry_i is defined` antes de usar os dados, ou quebra
  nessa segunda passada.
- Templates Twig de plugin ficam em `<plugin>/templates/`, acessíveis
  como `@<plugin_key>/arquivo.html.twig` (namespace registrado
  automaticamente pelo GLPI pra cada plugin ativo). Editar um `.twig`
  durante o desenvolvimento pode não refletir na próxima requisição — o
  GLPI cacheia o Twig compilado em `<GLPI_VAR_DIR>/_cache/<versão>/templates/`;
  se a mudança não aparecer, apague esse diretório.
- **Notificações:** criar a `Notification` + `NotificationTemplate` não
  basta — sem uma linha em `glpi_notificationtargets` (classe
  `NotificationTarget`) dizendo QUEM recebe, `NotificationEvent::raiseEvent()`
  roda sem erro e retorna `true`, mas não enfileira nada pra ninguém (o
  `foreach` sobre os alvos simplesmente não tem o que iterar). Pra
  eventos de objetos ITIL (Ticket/Change/Problem), o destinatário
  "Solicitante" é `['items_id' => Notification::AUTHOR, 'type' =>
  Notification::USER_TYPE]` — é assim que o core rotula esse mesmo
  destino na notificação nativa "satisfaction" (`AUTHOR` = "Requester"
  no rótulo da UI, apesar do nome da constante).
- **Menu do GLPI é cacheado em `$_SESSION['glpimenu']` no login** —
  mudanças em `menu_toadd`/`Menu::getMenuContent()` só aparecem depois
  de deslogar e logar de novo (ou em modo debug).

## Limitações conhecidas (v1)

- A integração com a linha do tempo (`timeline_answer_actions` +
  `timeline_items`) usa uma API interna do core (Twig/estrutura de
  `timeline.html.twig`) que **não é documentada como contrato estável**
  pra plugins — pode quebrar em atualizações do GLPI 11.x. Se isso
  acontecer, a aba "Pesquisa de satisfação" continua funcionando
  normalmente (mecanismo independente e estável) como acesso alternativo.
- Perguntas são vinculadas a uma entidade específica (sem propagação
  recursiva para sub-entidades por enquanto).
- Acesso ao cadastro de perguntas e aos resultados usa o direito global
  `config` (mesmo direito que abre a tela de plugins), em vez de um
  direito próprio do plugin — suficiente para administradores, mas pode
  ser refinado depois se for necessário liberar a visualização de
  resultados para outro perfil sem dar acesso total de configuração.
- Critérios de exclusão de disparo cobrem origem da requisição,
  categoria, solicitante e grupo do solicitante — não há (ainda) opção
  de excluir por tipo de chamado (Incidente x Requisição) nem um lembrete
  automático (cron) para pesquisas pendentes há muito tempo.

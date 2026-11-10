# Arquitetura

## Escopo atual

Monólito Laravel 13, MySQL compartilhado, autenticação por sessão, Blade, Tailwind 4 e JavaScript nativo empacotado pelo Vite. O novo pedido reorganizou a sequência: Fase 1 Fundação; **Fase 2 Gestão e Programação de Treinos**; Fase 3 Personalização, Cronômetros e TV Mode (concluída). A integração Filament/Livewire continua opcional e não foi introduzida; a personalização funciona em Blade. Mantivemos o código funcional e todas as dependências da Fase 1.

Ambiente verificado: PHP host 8.3.6 / Sail 8.5.12, Laravel 13.40.0, MySQL 8.4, Composer 2.7.1, PHPUnit 12.5.43, Node 22.22.3, npm 10.9.8, Tailwind 4.3.5 e Vite 8.3.7. Boost 2.10.4 permanece instalado. O lock foi resolvido na Fase 1 para PHP 8.3 com Symfony 7.4, sem ignorar requisitos.

Compatibilidade futura do painel foi verificada na Fase 1 por Composer dry-run: Filament 5.11.2 e Livewire 4.4.8. Fontes oficiais: [Laravel 13](https://laravel.com/docs/13.x/releases), [Filament 5](https://filamentphp.com/docs/5.x/introduction/installation). Não instalar um painel global de boxes sem configurar tenant e autorização em cada recurso.

## Responsabilidades

| Local | Responsabilidade |
| --- | --- |
| `app/Actions/RegisterBox.php` | Cadastro transacional e administrador da box |
| `app/Actions/SaveWorkout.php` | Validar e salvar o agregado workout/blocos/estações em transação |
| `app/Actions/PublishWorkout.php` | Publicar com a mesma validação usada pelo editor; retornar a rascunho |
| `app/Actions/DuplicateWorkout.php` | Cópia profunda, IDs independentes, autor atual, status draft |
| `app/Actions/CopyWorkoutWeek.php` | Cópia semanal atômica com política explícita de conflitos |
| `app/Actions/DeleteWorkout.php` | Excluir filhos individualmente pelos modelos e depois o treino |
| `app/Workouts/WorkoutDefinition.php` | Regras estruturais e de publicação, serialização para edição, tratamento de old input inválido |
| `app/Workouts/TimerConfiguration.php` | Formatos por modalidade/bloco e normalização da configuração versionada |
| `app/Http/Requests` | Validação e autorização na entrada HTTP |
| `app/Http/Controllers` | Orquestração HTTP e consultas de calendário/histórico/painel |
| `app/Tenancy`, `app/Models/Scopes` | Contexto por ciclo e escopo global de box |
| `app/Policies/WorkoutPolicy.php` | Permissões por box e papel |
| `app/Support/BoxTheme.php` | Hex seguro, texto de botão por contraste e acentos legíveis |
| `resources/views/components/workouts` | Formulários e revisão estática dos treinos; components/sessions compartilha o board de coach/TV |
| `resources/js/app.js` | Campos condicionais, adicionar/remover/reordenar estações, feedback ao salvar |

## Isolamento e integridade

O tenant HTTP vem exclusivamente de `auth()->user()->box_id`. `ResolveCurrentBox` roda após autenticação e antes de route binding; limpa o contexto em `finally`. Identificadores de outra box retornam 404 em todas as rotas de workout. Headers, query strings e formulários não escolhem tenant.

Workout, WorkoutBlock e WorkoutStation estendem TenantModel: leitura sem contexto retorna zero linhas; escrita sem contexto é rejeitada; transferência de tenant é proibida. Policies também verificam pertencimento e papel. Admin e coach gerenciam treinos da própria box; somente admin exclui. Requisições não podem definir autor, tenant, IDs de filhos ou posições: são atribuídos pelo servidor. A ordem do array validado de estações determina `position`.

FKs compostas impedem vínculos cruzados entre autor/workout, workout/bloco e bloco/estação mesmo se a validação de aplicação for contornada. Testes exercitam essas constraints diretamente. Escritas usam modelos/eventos, sem SQL bruto, updates em massa, `withoutGlobalScopes`, insert/upsert ou cascatas que pulem a proteção de tenant.

User não tem escopo global porque o provider de login precisa encontrá-lo antes do contexto. Box é raiz e TrainingModality é catálogo global somente leitura. Não expor bindings/listagens globais de User/Box. O acesso à box HTTP usa o usuário autenticado. A leitura da logo é autenticada, restrita a `boxes/{box_id}/` e extensões raster; upload funcional em Configurações da Box valida raster, extensão, tamanho e dimensões. Jobs/comandos devem estabelecer e limpar CurrentBox explicitamente em try/finally.

## Persistência e publicação

A data civil `scheduled_for` usa o fuso IANA da box para consultas de hoje e histórico. A semana começa explicitamente na segunda-feira, independentemente do locale do processo. Vários treinos por dia e modalidade são permitidos. Não alterar timezone global. Eventos de execução usam UTC.

Rascunhos aceitam conteúdo incompleto, inclusive sem blocos, mas ainda exigem modalidade, estrutura e data válidas. Parâmetros enviados continuam sujeitos a tipo, faixa e lista de campos permitidos. Publicação exige a estrutura completa (warmup/skill/workout ou somente workout), conteúdo ou estações, título da técnica e parâmetros específicos do formato. A publicação de um registro já salvo reaplica a mesma definição, em transação e com bloqueio do workout.

Edição substitui os filhos dentro de uma transação, preservando autor e workout. Isso simplifica reordenação sem violar índices de posição e evita IDs enviados pelo cliente. Os IDs de blocos/estações mudam ao editar. A Fase 3 resolveu esse vínculo com snapshots imutáveis em timer_sessions, sem FK aos IDs de blocos/estações originais. Edições não afetam a execução ativa. Workouts com histórico não podem ser excluídos.

Cópia individual preserva o original e cria novos IDs, com status draft. Cópia semanal normaliza datas para segunda-feira e mantém o deslocamento de cada dia. `abort` cancela a transação se qualquer treino existir na semana de destino; `append` preserva todos e adiciona rascunhos. Confirmação é obrigatória no HTTP. Não há sobrescrita silenciosa. Um lock da linha da box serializa cópias semanais concorrentes; as leituras de origem e cópias são transacionais. O filtro de modalidade do calendário não limita a cópia semanal, como informado na tela.

## Interface e segurança

Mantidos hashing nativo, validação, CSRF, rotação/invalidação de sessão e limitação do login/cadastro. Conteúdo de treino é texto escapado por Blade e `white-space: pre-wrap`, nunca HTML cru. Configurações de tema aceitam somente hex de seis dígitos; o texto de botões escolhe preto/branco pelo contraste, e acentos são clareados quando necessário para o fundo escuro.

A pré-visualização é estática e reutiliza block-display. Rascunhos podem ser revisados em preview; a rota presentation exige publicado. O preview pode rolar verticalmente por ser revisão. O formulário Iniciar apresentação prepara uma sessão persistida, sem iniciar o timer. A TV funcional fica em rota própria por sessão, ocupa a tela e pagina conteúdo longo, sem rolagem principal.

Validação: testes MySQL, regressão da autenticação/tenant, build/Pint e fluxo Chrome em Full HD + formulário mobile. Na Fase 3, o fluxo adicional foi homologado no Chrome em Full HD, 1440p e 4K, incluindo sincronização e recuperação de rede. A saída física de áudio/TV permanece uma etapa de homologação no equipamento da box.

## Componentes da Fase 3

- UpdateBoxSettingsRequest/Action/Controller, settings.box e box-settings.js: administrador altera identidade da própria box, upload substituível/removível, cores/contraste e fuso. Logo e tema são aplicados no cabeçalho e nas telas principais. A API de estado envia a identidade atual da box para que TV aberta também receba novas cores/logo.
- CompileTimer: normaliza o contrato em segundos para configuração compacta em ms sem reescrever os treinos legados.
- SessionState: tempo autoritativo, máquina de estados e avanço temporal automático opt-in.
- PrepareWorkoutSession: snapshot transacional e uma aula ativa por workout, retornando a existente em preparação repetida.
- ControlWorkoutSession: autorização, lease, locks, versões e idempotência; nunca confia em estado/tempo/configuração enviados pelo navegador.
- WorkoutSessionResource: snapshot JSON com timestamps absolutos UTC, versão e permissões. Endpoint de estado normaliza fronteiras temporais e usa no-store.
- engine.js/SessionClock: núcleo puro e relógio monotônico compartilhado por coach e TV. polling-transport.js: interface de transporte sem acoplar cálculo ao HTTP.
- session-app.js: renderização, controles apenas após confirmação, polling/backoff, recuperação e lease de aba. content-pager.js: medição e páginas de texto seguro. sound-signals.js: áudio original após gesto.
- sessions.control/tv/summary/index: operação, apresentação de leitura, histórico e resumo. O responsável e admins controlam; outros coaches da box acompanham.

A TV não usa os templates administrativos e não contém comandos de execução. Essa distinção de interface não substitui a policy no servidor: um usuário responsável continua autorizado pela sua conta, enquanto um leitor não obtém controle alterando DOM ou requests. A autorização é por usuário/tenant e o lease coordena as abas desse usuário.

Limitações deliberadas: polling padrão 1 s, uma aula ativa por workout, lease de 6 s no padrão, sem fila de novos comandos offline. Ao recarregar o controle, recuperar lease por expiração ou Assumir controle. Áudio/fullscreen requerem clique. Conteúdo da TV é paginado, sem reduzir indefinidamente a fonte nem ocultar exercícios. Não foram instalados WebSockets, Redis, framework frontend novo ou bibliotecas de timer.

## Fase 3.1 — consistência de revisão e unidades de entrada

Causa confirmada do título antigo: PrepareWorkoutSession retornava qualquer sessão com active_workout_id correspondente antes de comparar o conteúdo. O painel e a TV liam title e blocos congelados dessa sessão. SaveWorkout atualizava corretamente o workout e recriava seus filhos em transação; detalhe/preview liam esses dados atuais. Não há Livewire instalado nesse fluxo; PollingTransport usa cache=no-store e os endpoints devolvem no-store. Não foi necessário limpar caches nem invalidar todas as páginas.

`workouts.content_version` começa em 1 e incrementa em cada edição atômica por SaveWorkout, inclusive rascunhos. Publicar sem editar não duplica a revisão. Cópias recebem sua própria revisão inicial. A sessão captura `workout_version`; esse número não substitui `session.version`, que continua sendo controle de concorrência.

Ao preparar um publicado: sessão ativa da mesma revisão é retomada; preparação desatualizada sem atividade é encerrada como substituída e uma nova captura o agregado inteiro atual. A substituição preserva o registro antigo e referencia superseded_by_id com FK de tenant. Aulas já iniciadas, ou com avanço manual anterior, mantêm seu snapshot; o painel informa a alteração e permite revisar o cadastro e confirmar nova execução. Reinício com versão atual exige responsável/admin, lease da aba, confirmação e versões atuais da sessão e do workout. Sessões encerradas nunca são selecionadas pela busca de ativa.

Preparações sem atividade que ficaram desatualizadas não podem ser iniciadas nem avançadas pelo endpoint de comandos: retornam conflito e exigem a nova preparação. Leitores da mesma box não substituem a preparação de outro responsável. Um início concorrente com preparação é serializado pelo lock da sessão; ao detectar atividade, a preparação não troca o conteúdo.

Workouts anteriores à migration recebem revisão inicial 1; snapshots existentes ficam com workout_version=null, pois sua correspondência não pode ser inferida honestamente. Pendentes antigas podem ser substituídas; aulas com atividade/históricas são preservadas e a interface informa a revisão não confirmada. Nenhum título, bloco ou tempo existente foi reescrito para mascarar o problema.

WorkoutRequest e WorkoutDurationInput formam a fronteira de unidades: o formulário envia minutos inteiros e segundos adicionais opcionais; a validação deriva duration_seconds uma única vez e entrega dados canônicos à Action. Contratos internos e configurações dos timers não passam por essa conversão. JSON/integrações anteriores que enviam somente duration_seconds continuam compatíveis; misturar as duas entradas no mesmo bloco é rejeitado. O editor faz divisão inteira/resto para não perder segundos de registros antigos. Valores fracionários não são aceitos; 1 min 30 s é explícito, sem arredondamento binário.

Dashboard ganhou identidade própria com logo maior, Head Coach, data e ações. Se houver um treino publicado hoje, o botão prepara esse treino; com vários, leva à escolha e não inicia um deles arbitrariamente. A marca BOXPULSE continua no cabeçalho, sem repetir ali a logo da box nessa tela. A TV mantém tema e logo proporcional em área maior.

A futura separação Gestão/Aula está apenas proposta em [coach-workspaces.md](coach-workspaces.md), sem segundo projeto ou refatoração de rotas nesta entrega.

## Fase 3.2 — disponibilidade e cargas

`TrainingModality` permanece catálogo global estável. A disponibilidade vem de `Box::modalities()` em `box_modalities`; não é um novo catálogo por tenant. Cadastro e configurações validam uma lista distinta, não vazia, de IDs existentes. Escritas de configurações derivam a box exclusivamente do usuário autenticado. A migration faz o backfill uma única vez; novas boxes usam sua seleção explícita.

Dashboard consulta somente IDs habilitados, incluindo treinos publicados de hoje. Novo treino sem modalidade usa a primeira ativa, sem pressupor CrossFit. URL de modalidade inativa não oferece criação. Calendário prioriza ativas e conserva registros desativados identificados para consulta; histórico conserva todas. `BoxModalities` faz a verificação também nas Actions. As operações obtêm lock na box antes de workout/sessão, serializando mudanças de disponibilidade com criação/publicação/cópia/preparação/comandos. Cópia semanal copia apenas ativas; cópia individual de inativa é rejeitada. Edição de inativa pode salvar draft, sem publicar.

Desativação não altera workouts, blocos ou snapshots. Execução com atividade continua com pause/resume e troca de etapas; preparação ainda sem atividade não pode iniciar nem ser substituída enquanto inativa. A mesma execução iniciada pode ser retomada; preparar outra ou reiniciar com uma nova revisão exige modalidade ativa. Reativação recupera as operações existentes.

`SuggestedLoads` normaliza um JSON opcional por bloco, usando equipment/rx/intermediate/scale/unit. Ausência de níveis normaliza null; o texto zero é válido. Apenas principal é permitido nesta fase. Equipamento até 80 e valores até 60 caracteres, sem controles; unidade opcional kg/lb. Campo omitido em edição preserva o valor anterior do mesmo tipo de bloco; null/vazio remove. Duplicação replica o atributo. A preparação copia para `TimerSession`, separada da configuração temporal. Publicação não exige cargas. Unidade acompanha apenas números isolados; não há parsing/conversão de pares ou descrições. Blade/textContent mantêm texto escapado. Coach/TV atualizam a seção ao trocar de timer e ocultam inteiramente se a etapa não tiver cargas.

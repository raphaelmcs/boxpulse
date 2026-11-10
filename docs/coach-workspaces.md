# Proposta — ambientes de Gestão e Aula

Documento de preparação arquitetural da Fase 3.1. **A separação visual ainda não foi implementada.** O BOXPULSE continua em uma única aplicação Laravel, com a navegação atual e todas as rotas existentes operacionais.

## Objetivo e fronteiras

Gestão prepara o conteúdo: cadastrar/editar workouts, publicar, programar semanas, copiar aulas, consultar treinos anteriores, personalizar a box e futuramente gerenciar a equipe. Aula executa uma versão desse conteúdo: escolher treino publicado do dia, preparar ou retomar sessão, controlar timer, apresentar na TV e consultar o resumo.

Workout representa o conteúdo cadastrado atual. WorkoutSession representa uma execução específica com snapshot e workout_version. O estado operacional usa outra numeração (`session.version`), destinada à concorrência, sem relação com `workout.content_version`. A interface deve mostrar claramente qual execução e versão foram selecionadas.

Não duplicar modelos, autenticação, banco, motor de timers ou código de domínio para separar esses ambientes. Não criar um segundo projeto Laravel, guard de login ou dependência de frontend.

## Reaproveitamento

| Peça atual | Uso futuro |
| --- | --- |
| WorkoutController, WorkoutRequest, SaveWorkout, PublishWorkout | Gestão de treinos, mantendo conversão da entrada e revisão atômica |
| ScheduleController, CopyWorkoutWeek, DuplicateWorkout | Agenda e cópias da Gestão |
| BoxSettingsController, UpdateBoxSettings, BoxTheme | Identidade e tema compartilhados |
| DashboardController e componentes de identidade | Base para seletor de treinos de hoje no ambiente de Aula |
| PrepareWorkoutSession | Preparar/retomar/substituir pendentes com política de revisão |
| WorkoutSessionController e SessionStateController | Controle, TV, histórico e resumo |
| WorkoutSessionResource, ControlWorkoutSession, SessionState | Uma única fonte autoritativa, autorização, versões, lease e idempotência |
| components/workouts | Formulários e revisão do conteúdo na Gestão |
| components/sessions, views/sessions e resources/js/timers | Apresentação e operação da Aula, com o mesmo núcleo de tempo |

Extrair layouts de navegação a partir do atual `layouts/app`, preservando tema, logo, feedback, CSRF e links nomeados. TV continua com layout próprio sem menu administrativo, ocupando 100dvh. A composição visual não altera policies ou regras de negócio.

## Organização proposta de rotas

| Destino futuro | Responsabilidade | Rotas atuais reaproveitadas |
| --- | --- | --- |
| `/manage/workouts/*` | Cadastro, edição, revisão e histórico de treinos | `/workouts/*` |
| `/manage/schedule` | Programação e cópia semanal | `/schedule`, `/schedule/copy` |
| `/manage/box/settings` | Identidade da box | `/box/settings` |
| `/coach/today` | Seleção por modalidade/data no fuso da box | Consulta de treinos publicados do dashboard |
| `/coach/sessions/*` | Preparação, controle, revisão de versão e resumo | `/sessions/*`, `/workouts/{id}/sessions` |
| `/coach/sessions/{id}/tv` | Exibição de leitura | `/sessions/{id}/tv` |

Nomes de rota podem ser agrupados em `management.*` e `classroom.*` gradualmente. Não migrar todos os nomes/URLs em um único deploy. URLs de estado/lease/comandos são geradas no servidor e entregues ao PollingTransport, evitando hardcode no JavaScript.

As APIs de execução podem manter os caminhos atuais mesmo após a troca dos layouts. Rotas antigas devem continuar como aliases de controller durante a transição. Para GET, redirecionar somente após validar compatibilidade e medir uso. **Não redirecionar POST/PUT/DELETE por um fluxo que perca método, corpo ou CSRF**; preservar aliases executando a mesma action.

Não incluir box_id escolhido pelo navegador nos novos prefixos. O tenant continua vindo do usuário autenticado.

## Navegação sugerida

1. Após login, entrar em Aula/Hoje, com identidade da box em destaque e treinos publicados por modalidade. Manter acesso claro a Gestão.
2. Gestão: editar treino, salvar/publicar e revisar. Ao concluir, oferecer retornar à programação ou abrir o preparo da aula, sem iniciar cronômetros automaticamente.
3. Aula: selecionar um treino específico quando houver vários; preparar sessão atual; iniciar etapa por clique; abrir TV em outra janela.
4. Sessão pendente desatualizada gera uma nova preparação atual. Sessão com atividade preserva snapshot e oferece revisão/reinício explícito. Sessão finalizada é histórica e não é retomada como uma execução nova.
5. Links de Gestão durante uma aula devem preservar o painel/TV e não enviar comandos. Preferir abrir revisão do treino em outra aba quando o coach estiver controlando uma sessão.
6. Finalizar/cancelar leva a um resumo e permite voltar à seleção do dia. Não reaplicar comandos do histórico.

Uma alternância visual Gestão/Aula deve indicar contexto sem exigir logout ou trocar a box silenciosamente.

## Autenticação e autorização

Reutilizar login por username e sessão Laravel do guard web. Os dois ambientes e a TV compartilham a mesma autenticação e tenant, com CSRF, rate limits e middleware `auth`/`box` antes de bindings. Nenhuma alteração na chave primária ou nas relações dos usuários.

Policies continuam sendo a fronteira de permissão: admin configura a box; coach/admin gerenciam os treinos conforme regras atuais; responsável/admin controlam uma aula; outros coaches da box acompanham. Uma aba de TV não apresenta controles e não adquire lease. Ocultar um menu não substitui autorização no backend.

Uma segunda aba de controle mantém seu próprio client_id, acompanha o snapshot e requer takeover explícito. O layout novo não pode criar um relógio paralelo, mudar a versão do estado ou reduzir as verificações do servidor.

## Riscos de migração

- Links salvos, janelas de TV abertas e consumidores de rotas antigas podem quebrar se caminhos ou nomes forem removidos cedo.
- Misturar os contadores de versão do conteúdo e da execução pode invalidar comandos ou esconder mudanças de treino.
- Carregar novamente componentes de aula por navegação visual não pode criar sessões duplicadas, resetar tempo ou ativar áudio automaticamente.
- Novos formulários devem continuar convertendo minutos apenas na entrada; Actions e motor continuam em segundos/ms canônicos.
- Qualquer editor futuro, inclusive Filament/Livewire, deve chamar SaveWorkout; gravações parciais diretas nos models podem deixar de incrementar a revisão do agregado.
- Views e assets novos/antigos podem coexistir em abas abertas durante deploy. Manter o contrato de estado aditivo e as rotas antigas durante toda a janela de transição.
- Tema/logo devem ser compartilhados sem duplicação excessiva de identidade; a navegação de Gestão não pode causar overflow na TV.

## Plano sem interromper funcionalidades

1. Mapear rotas nomeadas, links e fluxos existentes com testes. Manter contratos de payload e comandos.
2. Extrair somente layouts/componentes de navegação, com ativação gradual por configuração. Não mover regras de negócio.
3. Introduzir Aula/Hoje usando a mesma consulta de data/fuso/modalidade do dashboard. Preservar escolha explícita quando houver mais de um treino.
4. Adicionar grupos de URLs novos como aliases, mantendo os caminhos atuais e um único conjunto de controllers/actions.
5. Homologar em uma box de teste: login, tenant, CRUD, programação, versões, pending/running/finished, duas abas, TV/reload/offline, áudio/fullscreen e precisão.
6. Ativar a navegação nova gradualmente, acompanhar logs e manter retorno ao layout anterior sem rollback destrutivo de schema/dados.
7. Somente depois migrar links canônicos e discutir descontinuação de aliases com compatibilidade documentada.

Essa evolução é uma próxima fase. A Fase 3.1 limita código às correções de consistência, unidades e identidade do dashboard.


## Fase 4 — mapeamento da implementação

Inspeção confirmou rotas web com auth/box antes de bindings, login por username, DashboardController, ScheduleController/calendário, WorkoutController/WorkoutDefinition/Actions, componentes de blocos/cargas, WorkoutSessionController e SessionStateController. PrepareWorkoutSession mantém lock box → workout → sessão e snapshot; SessionState/CompileTimer/engine.js preservam segundos/ms, versão otimista, lease e idempotência. Modalidades ativas vêm da relação Box::modalities(). Layout app/BoxTheme/CSS e board/paginador da TV serão reutilizados.

Decisão da fase: seleção em /workspace após login, gestão em /manage com /dashboard compatível, CRUD/calendário/configuração nas URLs atuais, Modo Aula em /coach. Novas rotas de preparo/resumo inicial reutilizam a mesma Action e os snapshots; controle, estado, lease, comandos, TV e resumo mantêm os caminhos/nomes existentes. Não há outro guard, banco ou motor. A entrada após login difere da sugestão inicial deste documento por exigência explícita da Fase 4: primeiro escolher ambiente.

# Ambientes de Gestão e Aula — Fase 4

Implementados na mesma aplicação Laravel, banco, guard web e contexto de tenant. Não há segundo projeto ou editor duplicado. A proposta da Fase 3.1 foi usada como base; o requisito novo definiu a seleção de ambientes como primeira tela após autenticar.

## Mapeamento e reutilização

| Componente existente | Responsabilidade preservada |
| --- | --- |
| auth, LoginRequest, ResolveCurrentBox, policies | Username/senha, sessão regenerada, tenant antes dos bindings e autorização |
| DashboardController, ScheduleController, WorkoutController | Gestão, programação, cadastro/edição e consulta |
| SaveWorkout, PublishWorkout, DuplicateWorkout, CopyWorkoutWeek | Transações, revisão do agregado, publicação, cópias e unidades |
| BoxSettingsController, BoxTheme e componentes Blade | Modalidades, logo raster, cores e contraste |
| PrepareWorkoutSession | Uma preparação ativa por workout e snapshots versionados |
| SessionState, ControlWorkoutSession, SessionStateController | Tempo autoritativo, locks, lease, idempotência, conflitos e restauração |
| sessions/board, engine.js, session-app.js | Mesmo cronômetro para coach/TV, sem relógio paralelo |

Novos controllers `WorkspaceController` e `ClassroomController` compõem telas e chamam os serviços existentes. `ClassroomWorkouts` centraliza treinos publicados de hoje por modalidade ativa e normaliza sessões com o mesmo SessionState antes de mostrar sua situação. `WorkoutPresentation` formata referências de duração usando CompileTimer, inclusive circuitos/intervalados.

## Rotas e compatibilidade

| Rota | Nome | Uso |
| --- | --- | --- |
| `/workspace` | workspace | Seleção após login/cadastro |
| `/manage` | manage.dashboard | Visão geral da Gestão |
| `/dashboard` | dashboard | Entrada anterior preservada, mesmo controller/view |
| `/coach` | coach.today | Aulas publicadas de hoje, filtros ativos e aulas em andamento |
| POST `/coach/workouts/{workout}/prepare` | coach.prepare | Preparar/retomar e abrir resumo inicial |
| `/coach/workouts/{workout}/sessions` | coach.history | Escolher/consultar execuções específicas de um treino |
| `/coach/sessions/{session}/ready` | coach.ready | Snapshot, conteúdo, cargas e links TV/controle |
| `/sessions/{session}/control` | sessions.control | Controle operacional, na mesma URL anterior |
| `/sessions/{session}/tv` | sessions.tv | Apresentação sem menu administrativo |
| `/sessions/{session}/summary` | sessions.summary | Encerramento e retorno ao Modo Aula |

CRUD `/workouts`, calendário `/schedule`, configurações `/box/settings`, histórico `/sessions` e endpoints de state/lease/commands/restart conservam todos os nomes e caminhos. POST/PUT/DELETE não são redirecionados para aliases com perda de corpo/método/CSRF. O endpoint antigo POST `/workouts/{workout}/sessions` continua chamando a mesma Action e redirecionando ao controle.

Após autenticar, `/workspace` substitui o destino antigo `/dashboard`, inclusive quando havia intenção de abrir um bookmark. O link autenticado continua funcionando diretamente. Guest middleware e home também usam a seleção. Nenhum logout é necessário para alternar ambientes.

## Fluxo e navegação

1. Login/cadastro → escolha entre **Gerenciar treinos** e **Iniciar aula**, com logo/nome/cores/data da box.
2. Gestão → visão geral, programação, novo treino, histórico, sessões anteriores e configurações admin-only. Dashboard conserva publicados, identifica drafts do dia e mostra próximos seis registros reais. Acesso de retorno à seleção e entrada no Modo Aula.
3. Modo Aula → publicados da data civil da box. Filtros só para modalidades ativas, omitidos quando há uma. Cards mostram modalidade, título, composição real de etapas, formato/cap/duração e identificação da sessão. Não existe campo de horário agendado na modelagem atual; não inventar um.
4. Preparar/continuar preparação → resumo do snapshot, abrir TV em nova aba ou ir ao controle. Nenhum timer ou lease é iniciado pela tela ready/TV.
5. Controle → ações principais grandes, controles secundários recolhíveis e confirmações para reset/encerramento. Pode conduzir pela mesma tela ou abrir TV separada. Status escrito para conexão, espera, preparação, execução, pausa e encerramento.
6. Encerrar → resumo, voltar às aulas ou iniciar outra, sem passar por formulários administrativos.

Aulas com atividade, inclusive de outro dia ou modalidade recém-desativada, aparecem em uma seção de retomada explícita. Não ficam disponíveis como novos treinos dessa modalidade. Cada execução mostra número e horário no fuso da box. Histórico por treino permite abrir a execução correta, em vez de escolher arbitrariamente um registro antigo.

## Sessões e concorrência

- Workout.content_version e WorkoutSession.version têm funções diferentes. Novas sessões capturam conteúdo; iniciadas preservam snapshot. Preparação pendente desatualizada é substituída conforme a Fase 3.1.
- Unique `(box_id,active_workout_id)` e locks box → workout → sessão deduplicam pedidos simultâneos/repetidos. Ready/TV/control sempre referenciam o mesmo ID retornado pelo preparo.
- Após histórico encerrado, uma nova preparação exige `new_session_confirmation` e `previous_session_id` da última execução, conferidos sob lock. Outro pedido da mesma rodada retoma a preparação criada; pedido atrasado depois de ela também encerrar é rejeitado. A regra também protege o endpoint antigo e o formulário administrativo ganhou a confirmação.
- Abrir/recarregar controle ou TV não reinicia. BFCache recuperado força leitura nova da página. Outra aba de controle continua sujeita ao lease e takeover explícito; TV só lê. Fim impede comandos operacionais e mantém histórico.
- A lista e o resumo inicial usam SessionState para reconhecer encerramento temporal automático, sem duplicar cálculo de tempo.
- Modalidade desativada bloqueia novas preparações; uma aula com atividade pode continuar e concluir. Autorização e tenant independem do menu visível.

## Homologação

Antes do hotfix subsequente, 169 testes PHP / 1.346 verificações passaram. A regressão final integrada também inclui essas funcionalidades. Chrome isolado validou login/escolha, Gestão, lista vazia, cadastro/publicação, ready, coach/TV, pausa/reload, offline, fullscreen, revisão preservada e histórico. Duas rodadas de oito POSTs simultâneos retornaram uma única sessão por rodada. TV Full HD e 4K sem rolagem. Nenhum teste escreveu no banco de produção.

Roteiro: entrar em `/workspace` → Gestão → cadastrar/publicar → retornar à seleção → Iniciar aula → Preparar → Abrir na TV e controle → iniciar/pausar/continuar/próxima etapa → finalizar com confirmação → resumo → Modo Aula. Repetir com um, dois e três blocos e com outra box. Para o layout adaptativo e etapas opcionais posteriores, consultar [hotfix local](production-hotfix.md).

Nenhuma migration foi necessária para a separação dos ambientes. A antiga proposta deixou de ser uma pendência; futuras evoluções devem reutilizar os contratos atuais.

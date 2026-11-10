# Motor universal de cronômetros

## Implementação

Operacional na Fase 3, sem novas dependências. `CompileTimer` converte o contrato legado de segundos para uma configuração imutável em milissegundos ao preparar a sessão. O núcleo JavaScript `resources/js/timers/engine.js` projeta tempo, fase, round, estação, progresso e alertas. Coach e TV usam exatamente esse núcleo. Não existe contador decrementado por setInterval.

O backend não calcula fases visuais em componentes: `SessionState` mantém estado/âncoras autoritativos e normaliza preparação/finalização comparando elapsed com os limites compilados. A mesma configuração compacta é entregue às duas interfaces. Fixtures compartilhadas em tests/fixtures verificam compilação e projeção.

## Contratos e compatibilidade

Workouts preservam `timer_type`, `duration_seconds` e `timer_settings.version=1`, com tempos em **segundos inteiros**. Não houve migração/substituição dessas configurações. Foi acrescentada apenas a opção compatível `stop_at_cap` para FOR TIME: ausente/true finaliza no cap, false sinaliza e continua.

O compilador produz version=1, preparation_ms, duration_ms, limit_ms, direction, interval_ms, rounds, cycle_ms e segments. Cada segmento tem phase, duration_ms e station opcional. Ciclos são repetidos matematicamente, sem expandir milhares de rounds em arrays. As estações originais continuam relacionais; o snapshot de execução mantém uma cópia versionada da lista e dos valores usados pelo compilador.

| Formato | Execução |
| --- | --- |
| stopwatch | Progressivo desde zero; sem limite obrigatório. Se existir duração no legado, ela atua como limite. |
| countdown | Regride pela duração; finaliza automaticamente no zero. |
| for_time | Progressivo; cap opcional, sinalizado; finaliza por padrão, ou continua quando stop_at_cap=false. |
| amrap | Regressivo pela duração obrigatória. |
| emom | Regride no intervalo, mostra round atual e restante total; duração é múltipla do intervalo. |
| tabata | Trabalho/descanso por ciclos. |
| interval | Trabalho/descanso/transição e rounds. |
| circuit | Trabalho/descanso/transição por estação, repetindo rounds. Tempos da estação sobrescrevem o bloco; null herda, zero de descanso/transição é significativo. |
| none / repetitions | Etapa livre, sem timer automático; progresso depende do coach. |

Intervalados encerram **ao fim do último trabalho do último round**, sem descanso/transição final. Descanso/transição continuam entre estações e rounds anteriores. Segmentos de duração zero são omitidos. EMOM muda de round exatamente no intervalo. FOR TIME sem limite requer finalização manual.

Preparação vem do bloco (padrão 10 s) ou de override explícito da sessão, 0–60 s. A interface oferece 0/3/5/10/15 e herança por bloco. Blocos sem tempo não executam preparação temporal. Pausa congela também uma preparação parcialmente decorrida.

## Tempo e restauração

Tempo acumulado inclui preparação. Enquanto preparing/running:

`elapsed_ms = persisted_elapsed_ms + max(0, server_now_ms - effective_started_at_ms)`

Pausa persiste o resultado e remove a âncora ativa; continuar conserva acumulado e estabelece nova âncora. O início original também permanece em started_at. Campos de evento são UTC com precisão de milissegundos. performance.now() nunca é persistido.

No navegador, `SessionClock` ancora um snapshot recebido no relógio monotônico, estimando meia latência do request com seus timestamps monotônicos. Entre respostas usa apenas o delta de performance.now(). Recarregar reconstrói pelo timestamp UTC do servidor; mudar o relógio civil local não altera a execução. Versões antigas são ignoradas. Amostras da mesma versão não fazem o relógio ativo regredir.

A aba pode ficar em segundo plano: ao redesenhar, a projeção deriva a fase atual pelo tempo efetivo e não tenta reproduzir ticks perdidos. Polling detecta e persiste transições automáticas. O cliente pode mostrar o limite matemático atingido, mas sinaliza espera de sincronização e **nunca avança blocos sozinho**. Avanço depende do estado confirmado do servidor.

## Máquina de estados

Estado da aula: active → completed/cancelled. Estado de cada timer:

- idle → preparing/running por start.
- preparing → running ao passar a preparação.
- preparing/running → paused por pause.
- paused → preparing/running por resume, conforme tempo acumulado.
- running → completed ao atingir limite automático.
- idle/preparing/running/paused → completed por finalizar etapa.
- reset com confirmação → idle, acumulado zero, sem iniciar novamente.
- cancel encerra a aula e o timer atual como cancelled; finish encerra a aula como completed.

Next completa a etapa atual e seleciona a seguinte; ela fica parada, salvo avanço **temporal** automático explicitamente habilitado. Previous pausa uma etapa em execução antes de voltar. Uma etapa já finalizada permanece finalizada ao revisitá-la; use reiniciar com confirmação para executá-la de novo. Voltar não dispara novamente o avanço automático de uma conclusão já processada.

Por padrão, terminar o timer deixa a aula ativa para o coach escolher a próxima etapa ou finalizar. Com auto_advance=true, cada fim temporal inicia o próximo bloco na hora real de conclusão anterior, incluindo sua preparação; polling após suspensão pode recuperar mais de uma fronteira. No último bloco, encerra a aula. Blocos livres exigem intervenção manual.

## Concorrência e sincronização

`PollingTransport` separa transporte da UI e permite futura substituição por broadcasting. MVP: polling GET a cada ~1 s, com backoff offline, sem Reverb/WebSockets. O render não depende dessas requisições para cada segundo.

Um lease identifica a aba controladora por client_id UUID, mantido só em memória. Duração padrão 6 s; renovação a cada ~2 s. Outra aba lê e pode assumir explicitamente; a anterior perde o lease. O lease não substitui autorização: só o responsável ou um administrador do mesmo tenant pode controlar; outros coaches da box são leitores. TV não solicita lease nem envia comandos.

Cada comando contém client_id, command_id UUID, expected_version, action e confirmation para reset. Em transação com lock da aula: normalizar relógio, verificar idempotência, status, lease, versão e transição. Comando já aplicado com os mesmos dados retorna o estado atual sem reaplicar. Mesmo UUID com outro payload ou versão/lease antigos retorna 409 com snapshot. Estado/transição inválidos retornam 422. Controller renovações não alteram versão quando a identidade da aba não muda; takeover altera.

Unique `(box_id, active_workout_id)` e lock do workout impedem criar duas sessões ativas ao repetir preparação. Unique `(box_id, session_id, command_id)` reforça idempotência. Estado de aulas concluídas e comandos é mantido no banco, independentemente da vida da aba.

Offline: continuar apenas a projeção do snapshot já confirmado; bloquear comandos novos. Um comando cuja resposta foi perdida é reenviado, em memória, com o mesmo UUID e payload ao reconectar. Não criar fila de novas ações offline nem reproduzir comandos automaticamente com versões novas. Reload elimina pendências locais e recupera o estado real do servidor.

## Áudio e apresentação

`SoundSignals` cria AudioContext somente após clicar em Ativar sons. Osciladores senoidais originais sinalizam preparação/últimos segundos, início, trabalho, descanso, transição, finalização e cap. Mute suspende áudio. Não há mídia externa nem licença de terceiros. Ao voltar de suspensão, não toca uma sequência de sons atrasados.

TV usa cores contrastantes, rótulos de estado, rounds e estação atual; sinais nunca dependem só de cor/áudio. `ContentPager` mede espaço disponível, divide texto e estações sem HTML cru, preserva conteúdo e recalcula em ResizeObserver. Páginas têm navegação e rotação controlável de 10 s. A tela principal ocupa 100dvh sem scroll; conteúdo extenso não é simplesmente cortado.

## Verificação

- `npm run test:timers`: 16 testes puros Node, relógio injetado, sem esperar segundos reais.
- `artisan test`: compilação/fixtures, precisão em ms, preparação, pausa/retomada/reset, limites, auto/manual, restauração, permissões, FKs, snapshots, versões, lease, idempotência e regressão.
- Chrome: upload/tema, sessão completa, três resoluções, pausa/reload, fullscreen, ativação de áudio e mute, rede offline/online, duas abas, comandos simultâneos 200/409 e repetição idempotente 200.

Validação visual/de API no Chrome não substitui conferir volume e saída de áudio no equipamento físico da box. Não foram feitos testes de carga ou homologação em todos os modelos de Smart TV.

## Fase 3.1 — formulário em minutos, motor preservado

Duração principal de warm-up, skill e WOD usa `duration_minutes` inteiro, com `duration_extra_seconds` opcional entre 0 e 59. Fórmula exata na entrada: minutos × 60 + segundos adicionais. Exemplos: 6 → 360 s; 12 → 720 s; 1 min + 31 s → 91 s. O editor usa intdiv/resto para recuperar os dois campos sem arredondamento ou nova multiplicação do valor persistido.

A entrada fracionária é rejeitada de forma explícita. FOR TIME pode deixar duração vazia; AMRAP/EMOM exigem duração ao publicar. Os limites continuam sendo os segundos canônicos existentes (até 86400 por duração); não há alteração em massa do banco. Tabata, trabalho/descanso/transição de intervalos/estações, preparação e intervalo EMOM continuam em segundos. EMOM oferece 60 s como padrão no formulário, sem mudar configurações existentes.

Somente WorkoutRequest converte a entrada do navegador; WorkoutDefinition, Actions, snapshots e CompileTimer continuam com os contratos anteriores. Não aplicar conversão em PublishWorkout ou em dados já recuperados do banco. O motor JavaScript e o cálculo temporal não foram alterados nesta fase.

Uma revisão de conteúdo diferente não modifica timers de uma execução iniciada. Preparações pendentes desatualizadas são substituídas preservando o registro anterior. Uma nova execução explícita captura os tempos da revisão publicada nova e começa parada, sem reaproveitar o acumulado anterior. O reinício de timer da etapa continua sendo uma ação diferente, sem atualizar o snapshot do conteúdo.

## Fase 3.2 — referências de carga e disponibilidade

`timer_sessions.suggested_loads` guarda a cópia das cargas do bloco no momento da preparação. A API serializa `timers[].suggested_loads`; não acrescenta essas referências a `config`, nem interfere em segundos, ms ou projeção temporal. O painel e a TV exibem somente os níveis preenchidos do timer atual. Sem cargas, a seção desaparece e o paginador recupera o espaço. Trocar para técnica/aquecimento não reaproveita as cargas do principal.

O source informa `modality_enabled`; `source.published` só habilita uma nova preparação/reinício quando a modalidade também está ativa. Desativar não interrompe sessão com atividade, mas bloqueia start/next/complete em preparação ainda pendente. Esses controles têm proteção no backend, além do estado de UI. Comandos obtêm lock na box antes da sessão; a política de lease, versões e idempotência permanece a mesma. Snapshots legados têm carga null e não recebem backfill de conteúdo atual.

# Roadmap

## Fases entregues

1. **Fundação:** Laravel/Sail/MySQL, cadastro transacional, autenticação por username, tenant scoped e policies.
2. **Gestão/programação:** CrossFit, HYROX e Funcional, completo/principal, estações, rascunho/publicação, calendário, histórico, cópias e revisão estática.
3. **Personalização + motor universal + TV Mode:** operacional conforme o último pedido de produto. O plano anterior de identidade pendente foi concluído nesta fase.

## Fase 3 — funcionalidades

- Configurações da Box acessível no menu: nomes, administrador, timezone, logo e duas cores. Color picker + hex, prévia, contraste, persistência após login e tema/logo nas telas principais e TV aberta.
- Upload PNG/JPEG/WebP até 2 MB, nome exclusivo, substituição/remover, validação de dimensões, limpeza no rollback e exclusão da antiga após commit. Storage público com symlink relativo configurado.
- Motor puro compartilhado por coach/TV: stopwatch, countdown, FOR TIME, AMRAP, EMOM, Tabata, intervalado, circuito e etapas livres. Preparação configurável; contratos legados em segundos preservados, snapshot executável em ms.
- Máquina de estados persistente: preparar, iniciar, pausar, continuar, reiniciar confirmado, finalizar etapa/aula, cancelar e anterior/próxima. Avanço temporal automático somente opt-in.
- Snapshots imutáveis, uma sessão ativa por workout, logs e histórico preservado. Edição do treino não muda aulas preparadas; exclusão com histórico é bloqueada.
- TV em layout próprio, logo, hora/data da box, tempo grande, rounds/estações, barra de progresso, estados com texto e cor, exercícios e paginação controlada para conteúdo longo.
- Áudio original com opt-in por clique e mute; Fullscreen API com entrada/saída explícitas.
- Polling padrão 1 s, relógio monotônico entre requests, reconstrução por timestamps, controle de versão, comandos idempotentes, lease de aba e takeover explícito. TV somente leitura; controles autorizados ao responsável ou admin da box.
- Indicador offline, cálculo visual mantido, bloqueio de novos comandos offline, confirmação idempotente de comando em trânsito e reconciliação.

## Arquivos principais da Fase 3

| Área | Arquivos |
| --- | --- |
| Personalização | `UpdateBoxSettingsRequest`, `UpdateBoxSettings`, `BoxSettingsController`, `settings/box.blade.php`, `box-settings.js`, `BoxTheme` |
| Compilação/estado | `app/Timers/CompileTimer.php`, `app/Timers/SessionState.php`, extensão compatível de `TimerConfiguration`/`WorkoutDefinition` |
| Sessões e comandos | Models `WorkoutSession`, `TimerSession`, `SessionCommand`; Actions `PrepareWorkoutSession` e `ControlWorkoutSession`; `WorkoutSessionPolicy`; `SessionCommandRequest` |
| HTTP | `WorkoutSessionController`, `SessionStateController`, `WorkoutSessionResource`, `routes/web.php`, rate limits em `AppServiceProvider`, `config/boxpulse.php` |
| UI | `views/sessions`, `components/sessions`, layout/menu, CTA dos workouts, CSS de sessão/TV, `resources/js/timers/{engine,polling-transport,session-app,content-pager,sound-signals}.js` |
| Integridade | Migration `2026_11_10_132606_create_workout_session_tables`, factories, proteção de histórico em `DeleteWorkout` |
| Testes | `BoxSettingsTest`, `WorkoutSessionTest`, `SessionSynchronizationTest`, `CompileTimerTest`, `InteractsWithSessions`, fixtures compartilhadas e `tests/js/timer-engine.test.js` |
| Documentação | README, AGENTS, architecture/database/timers/roadmap e guia de homologação |

## Migration executada

`2026_11_10_132606_create_workout_session_tables` cria **workout_sessions**, **timer_sessions** e **session_commands**, com índices únicos e FKs compostas por box. Nenhuma tabela existente foi recriada e nenhuma configuração de treino anterior foi substituída.

## Validação

- **114 testes PHP / 656 verificações** no PHP 8.3 do host e no Sail PHP 8.5, incluindo toda a regressão das fases anteriores.
- **16 testes Node** do núcleo temporal, com relógio simulado e sem sleeps reais de vários segundos.
- Build Vite, Pint, syntax check JS, Composer validate/check-platform-reqs e diff check aprovados.
- Chrome com conta local QA: personalização/upload real, aula completa, três blocos, pausa/reload da TV, áudio somente após gesto e mute, fullscreen sem encerrar aula, rede offline/online, takeover entre abas e finalização/resumo.
- Requisições HTTP realmente simultâneas: uma aplicada (200), outra recusada por versão (409); repetição do comando aplicado respondeu 200 sem nova ação.
- Viewports **1920×1080, 2560×1440 e 3840×2160** sem overflow horizontal/vertical. Texto extenso distribuído em oito páginas, sem perda de conteúdo. Prévia com cores preto/branco também manteve contraste.

## Limitações conhecidas

Polling pode levar aproximadamente um intervalo para refletir uma ação remota, embora o relógio continue localmente entre snapshots. Há uma aula ativa por workout. Reiniciar uma aba de controle gera outra identidade de aba: assumir controle ou aguardar o lease expirar. Não se aceitam novas ações offline; um comando já enviado é reconciliado com seu UUID original.

Som e fullscreen dependem de gesto e suporte do navegador. A validação foi no Chrome; saída física de áudio, TVs reais, outros navegadores e carga de muitas boxes ainda exigem homologação operacional. O preview estático continua separado da execução da sessão.

## Próxima etapa — qualidade e preparação comercial

Homologar em TV/áudio reais, automatizar CI, avaliar carga do polling, ampliar testes de navegadores, preparar deploy/HTTPS/backups e restauração. Gestão de equipe/convites, recuperação de acesso e eventual painel Filament continuam fora deste escopo. Broadcasting pode substituir PollingTransport futuramente, sem mover cálculo temporal para componentes da UI.

Para revisão manual, seguir [qa-phase3.md](qa-phase3.md).

## Fase 3.1 — correções concluídas

**Causa do título antigo:** a preparação retomava a sessão ativa por workout sem verificar se seu snapshot correspondia ao cadastro editado. Detalhe/preview liam o cadastro atual, enquanto coach/TV exibiam o title persistido na execução anterior. Não era cache nem estado Livewire.

**Solução:** content_version por edição atômica de workout, workout_version capturada na sessão e superseded_by_id preservando a cadeia de substituições. Pendentes sem atividade desatualizadas geram nova preparação; execuções com atividade mantêm snapshot e aviso, revisão do cadastro e reinício explicitamente confirmado sob autorização, lease e versões atuais. Históricos finalizados permanecem intactos. Comandos não iniciam/avançam uma preparação pendente desatualizada. Versões antigas desconhecidas ficam null, sem backfill arbitrário.

**Unidades:** durações principais em minutos inteiros; segundos adicionais 0–59 opcionais para preservar tempos como 91 s exatamente. Converter só na entrada HTTP; edição usa divisão inteira/resto. Tabata, trabalho/descanso/transição, estações, preparação e intervalo EMOM seguem em segundos. EMOM oferece 60 s de intervalo padrão. Actions, banco e motor mantêm os contratos canônicos anteriores.

**Identidade:** dashboard com logo maior (proporcional, contain), nome da box, Head Coach, data e ações. Logo pequena removida do cabeçalho apenas no dashboard, mantendo BOXPULSE. Um treino hoje pode ser preparado diretamente; vários exigem escolha. Logo da TV ampliada proporcionalmente.

**Migration aplicada:** `2026_11_10_144718_add_workout_content_versions_to_sessions`. Somente metadados de revisão/substituição; nenhum dado de duração foi reconvertido e nenhum registro histórico foi apagado.

**Arquivos principais:** PrepareWorkoutSession, SaveWorkout, DuplicateWorkout, ControlWorkoutSession; models Workout/WorkoutSession; WorkoutRequest/RestartWorkoutSessionRequest; WorkoutDurationInput; WorkoutSessionController/Resource; formulário/blocos, dashboard/layout, controle/resumo/lista de sessões; app.js/session-app.js e CSS; quatro arquivos de testes novos/estendidos e documentação.

**Validação:** 130 testes PHP / 803 verificações no PHP 8.3; suíte Sail PHP 8.5 e regressão temporal verificadas; 16 testes Node do motor. Build, Pint, Composer e diff check aprovados. Chrome reproduziu Front Squat → Clean & Jerk com alteração de blocos/timers, nova preparação, coach/TV, preservação de execução iniciada, aviso/reinício explícito e histórico. Logos horizontal/quadrada/vertical sem corte ou duplicação no dashboard; mobile sem overflow e TV Full HD sem rolagem.

**Pendências:** não foi feita a separação visual Gestão/Aula; a proposta solicitada está em [coach-workspaces.md](coach-workspaces.md). Recomenda-se implementá-la gradualmente com layouts e aliases de rotas, reutilizando autenticação, policies, Actions e motor. Homologação em equipamentos físicos e preparação comercial continuam sendo etapas operacionais posteriores.

## Fase 3.2 — cargas e modalidades concluídas

Implementadas seleção obrigatória por box no cadastro/configurações, dashboard dinâmico, bloqueios nas Actions e consultas históricas preservadas. Relação reutiliza catálogo e tem par único/FKs; migration habilitou inicialmente as três modalidades em todas as boxes anteriores. Cargas textuais opcionais por principal acompanham edição/cópias e snapshots, com exibição condicional em detalhes, preview, coach e TV. Aula iniciada continua após desativação; nova preparação não é permitida até reativar.

Duas migrations aditivas aplicadas sem reescrever conteúdo, tempos ou histórico. 152 testes PHP / 1.089 verificações em PHP 8.3 e Sail PHP 8.5, 16 testes Node, build/Pint/Composer aprovados. Chrome confirmou fluxo real e TV Full HD/QHD/4K sem rolagem, além de viewport mobile. Inventário, decisões, rotas, limites e roteiro em [Fase 3.2](phase-3.2.md).

Próxima etapa sugerida: evolução gradual Gestão/Aula conforme proposta, sem outro projeto ou novo mecanismo de autenticação. Não há pendência funcional conhecida desta fase.

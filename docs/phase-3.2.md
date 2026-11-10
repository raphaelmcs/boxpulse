# BOXPULSE — Fase 3.2

Entrega no projeto Laravel/Blade existente, sem novas dependências, outro aplicativo ou instalação de Livewire.

## Comportamento entregue

- Relação por box com o catálogo CrossFit/HYROX/Treinamento Funcional existente. Cadastro e configurações exigem uma a três modalidades válidas. Cards acessíveis com checkbox nativo, ícone, descrição, foco e seleção na cor da box.
- Dashboard consulta apenas modalidades/treinos habilitados. Um card ocupa o espaço disponível; dois e três redistribuem a grade. Novo treino sem filtro usa a primeira ativa.
- Desativar preserva dados. Novos drafts, publicação, cópia individual e preparação são rejeitados no backend; cópia semanal considera somente ativas. Consulta de registros desativados permanece no calendário e histórico, com badge. Pode editar como draft. Aulas com atividade continuam até concluir. Preparações sem atividade ficam bloqueadas. Reativação recupera operações.
- Cadastro preserva autenticação e adiciona identidade opcional (logo/cores/fuso) com a mesma política de upload da box. Configurações agrupam identidade, administração e modalidades, mantendo feedback e preview.
- Cargas opcionais no principal de qualquer modalidade: equipamento livre até 80 caracteres, RX/Intermediate/Scale até 60, unidade opcional kg/lb. Sem níveis preenchidos, não há seção visível. Zero é válido. Unidade só acompanha valores numéricos isolados, como `22.5 lb`; pares (`22/16`) e descrições (`40/30 kg`) ficam exatamente como cadastrados, sem conversões.
- Edição, remoção individual/total e cópias preservam os dados esperados. Omissão do campo ao alterar outros campos conserva a referência do mesmo tipo de bloco; null ou níveis vazios remove explicitamente.
- Detalhes e preview exibem cada referência junto ao bloco; coach/TV exibem apenas a etapa atual. Snapshot de aula iniciada permanece intacto; novas revisões seguem a política 3.1 de substituição pendente/reinício explícito.

## Arquivos criados

- `database/migrations/2026_11_10_153006_create_box_modalities_table.php`
- `database/migrations/2026_11_10_153007_add_suggested_loads_to_workout_blocks_and_timer_sessions.php`
- `app/Workouts/BoxModalities.php`, `app/Workouts/SuggestedLoads.php`
- `resources/views/components/workouts/{modality-options,modality-status,suggested-loads}.blade.php`
- `tests/Feature/{BoxModalitiesTest,SuggestedLoadsTest}.php`
- Este relatório.

## Arquivos modificados nesta fase

- Models: `Box`, `WorkoutBlock`, `TimerSession`.
- Actions: `RegisterBox`, `UpdateBoxSettings`, `SaveWorkout`, `PublishWorkout`, `DuplicateWorkout`, `CopyWorkoutWeek`, `PrepareWorkoutSession`, `ControlWorkoutSession`.
- Requests: `Auth/RegisterBoxRequest`, `UpdateBoxSettingsRequest`, `WorkoutRequest`; recurso `WorkoutSessionResource`.
- Controllers: `Auth/RegisteredBoxController`, `BoxSettingsController`, `DashboardController`, `ModalityController`, `WorkoutController`, `WorkoutPreviewController`, `ScheduleController`.
- Definição: `app/Workouts/WorkoutDefinition.php`.
- Views: `auth/register`, `settings/box`, `workouts/{form,show,preview,history}`, `schedule/index`, componentes `workouts/{block-fields,block-display}`, `sessions/{prepare,board}`.
- Frontend: `resources/css/app.css`, `resources/js/box-settings.js`, `resources/js/timers/session-app.js`.
- Fixtures: `database/factories/BoxFactory.php`, `database/seeders/DatabaseSeeder.php`; payloads dos testes `RegistrationTest`, `BoxSettingsTest` agora escolhem modalidades explicitamente.
- Documentação: `AGENTS.md`, `README.md`, `docs/{architecture,database,timers,roadmap}.md`.

Rotas existentes foram reutilizadas; nenhum endpoint novo necessário.

## Migrations executadas

As duas migrations acima foram revisadas com `migrate --pretend` e aplicadas com `migrate --no-interaction`. Primeira habilita as três para boxes anteriores sem duplicar pares; reexecução de up com tabela existente não reativa escolhas removidas. Segunda adiciona JSON null em blocos/timers, preservando snapshots legados. Nenhum migrate:fresh, wipe, truncation ou conversão de tempos no banco da aplicação.

## Validação

- 152 testes PHP, 1.089 verificações: PHP 8.3 host e PHP 8.5 Sail, MySQL exclusivo `testing`.
- 22 testes novos, cobrindo seleção 1/2/3, obrigatoriedade, validação, backfill idempotente, permissões/tenant, configuração, desativação/reativação, cópias, continuidade e bloqueio de preparação; cargas para três modalidades, opcionais/parciais, edição/remoção, cópia/data/semana, apresentação/snapshots, versões e escape.
- 16 testes JavaScript do motor mantidos e aprovados. Build Vite, Pint e Composer aprovados.
- Chrome real isolado, box QA própria: cadastro CrossFit, habilitação de HYROX, dashboard 1/2, novo treino completo com três cargas, detalhes/preview, coach e TV. Aquecimento/técnica ocultam cargas; principal exibe; voltar oculta novamente. HYROX desativado durante execução: pause/resume e conclusão funcionam; dashboard remove operações e consulta identifica desativada; reativação recupera.
- TV 1920×1080, 2560×1440 e 3840×2160 sem rolagem; área de exercícios continuou com 389/527/804 px de altura, com cargas visíveis. Cadastro/dashboard em viewport 390 px sem overflow horizontal. Sem exceções JavaScript observadas no fluxo concluído.

## Rotas e roteiro manual

1. `/register`: criar outra box com apenas CrossFit, depois com duas/três; sem seleção deve falhar sem criar box órfã. Conferir campos de identidade e sessão autenticada.
2. `/box/settings`: admin escolhe modalidades e salva. Abrir `/dashboard` e atualizar: somente ativas. Conferir logo, cores e dados administrativos.
3. `/workouts/create?modality=crossfit&structure=full`: cadastrar principal com DB / RX 22/16 / Intermediate 20/14 / Scale 18/12, publicar e abrir detalhes e `/workouts/{id}/preview`.
4. `/workouts/{id}/edit`: alterar RX, esvaziar um nível, salvar; depois esvaziar os três e confirmar seção oculta. Cadastrar `60` com kg e `40/30 kg` com lb: primeiro mostra `60 kg`, segundo mantém `40/30 kg`.
5. Duplicar para outra data e usar `/schedule` para copiar semana. Conferir cargas, novos IDs, draft e origem preservada.
6. Preparar apresentação, abrir `/sessions/{id}/control` e `/sessions/{id}/tv`. Avançar/voltar etapas: cargas só no principal, sem espaço reservado quando ocultas. Abrir TV em tela cheia.
7. Iniciar aula; em outra aba editar/republicar cargas. Preview usa novas; aula em execução usa originais. Confirmar reinício explícito ou concluir e preparar nova sessão para receber novas cargas.
8. Em outra aba desativar a modalidade da aula iniciada. Continuar pause/resume/troca de etapa e concluir. Dashboard não oferece essa modalidade; consulta em programação/histórico permanece identificada. Criar/publicar/copiar/preparar novos registros deve falhar também por requisição direta.
9. Reativar e conferir os mesmos treinos sem recriação. Repetir com outra box para comprovar independência; coach não pode alterar Configurações.

## Limites deliberados

Catálogo continua com as três modalidades pedidas; não há cadastro de novas modalidades, planos ou cobrança. Cargas são referências textuais, sem cálculo/conversão, por principal nesta fase; JSON por bloco permite evolução posterior. Som físico não foi homologado nesta fase; o motor e seus testes permaneceram preservados. A separação Gestão/Aula continua apenas documentada na proposta anterior.

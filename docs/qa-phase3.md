# Homologação manual — Fase 3

## Preparação

Aplicação local em http://localhost com Sail e migrations aplicadas. Faça login na sua box ou cadastre uma conta em `/register`. Os dados de QA criados na homologação automatizada pertencem a uma box separada; não foram alteradas boxes preexistentes. Não existe senha demo compartilhada.

## Identidade

1. Clique em **Configurações da Box** ou abra `/box/settings` como administrador.
2. Altere os nomes, fuso e as duas cores usando picker/hex. Observe a prévia. Experimente preto/branco para conferir ajuste de contraste.
3. Envie PNG/JPEG/WebP válido até 2 MB. A prévia deve preservar a proporção. Salve e confira logo/cores no dashboard, calendário, treino e preview.
4. Atualize a página, saia e entre novamente. A personalização deve permanecer.
5. Substitua a logo e depois remova. Arquivos inválidos, SVG, arquivo acima do limite e hex inválido devem apresentar erro sem apagar a logo atual.
6. Coach sem papel admin não deve acessar/alterar configurações. IDs de outra box não podem escolher o tenant.

## Treino e sessão

1. Em `/dashboard`, selecione modalidade e cadastre um treino completo (ou use um publicado existente). Publique e revise os blocos.
2. No detalhe `/workouts/{id}`, use **Iniciar apresentação**. Escolha 3 s para facilitar a checagem; mantenha avanço automático desmarcado.
3. O painel `/sessions/{id}/control` deve mostrar o primeiro bloco parado. Clique em **Iniciar etapa / aula** e veja preparação → execução.
4. Pause: o tempo deve congelar. Continue: não deve reiniciar do zero. Reinicie: deve pedir confirmação e voltar parado ao início.
5. Avance/volte entre etapas. O bloco selecionado não deve começar sozinho. Uma etapa concluída requer reinício confirmado para executar novamente.
6. Crie uma sessão separada com avanço automático marcado: fins temporais devem iniciar o próximo bloco com a preparação configurada. Blocos livres continuam manuais.
7. Faça fim/cancelamento e consulte `/sessions` e `/sessions/{id}/summary`. O histórico deve permanecer. Preparar o workout novamente cria uma sessão nova após encerrar a anterior.

## TV, precisão e rede

1. Use **Abrir TV Mode** em outra janela e mova para o monitor/TV. Rota: `/sessions/{id}/tv`.
2. Ative tela cheia por clique; saia pelo botão ou Esc. Isso não encerra a aula.
3. Ative sons por clique e confira na saída física da TV: preparação, início, últimos segundos, trabalho/descanso/transição e fim/cap. Teste mute. Ajuste o volume no equipamento.
4. Confira 1920×1080, 2560×1440 e 3840×2160: tipografia, logo, tempo, fases, rounds, estação, progresso e etapas devem permanecer legíveis.
5. Use descrição longa e estações. Navegue páginas e habilite/desabilite rotação de 10 s. Não deve haver scroll principal nem informações cortadas; todas devem estar nas páginas.
6. Com timer ativo, atualize a TV, oculte a aba e volte. O estado/bloco/tempo devem reconstruir pelo snapshot, sem zerar ou acumular deriva por ticks perdidos.
7. Interrompa a rede da TV por alguns segundos: mostrar sincronização indisponível, mantendo cálculo local. Pause no coach e reconecte a TV: ela deve receber o estado pausado confirmado, sem aplicar comandos inventados offline.

## Casos rápidos de formato

Use uma sessão nova ou reinicie com confirmação após ajustar o workout e preparar outra sessão (snapshots já criados não recebem edições):

| Formato | Configuração de teste | Resultado esperado |
| --- | --- | --- |
| Stopwatch | Sem duração | Progressivo até finalização manual |
| Countdown | 10 s | Regressivo até zero |
| FOR TIME | Cap 10 s | Progressivo até cap; com sinalizar/continuar, não para no cap |
| AMRAP | 10 s | Regressivo e fim automático |
| EMOM | Total 20 s, intervalo 10 s | Dois intervalos, round 2 aos 10 s |
| Tabata | Trabalho 3 s, descanso 2 s, 2 ciclos | Trabalho/descanso/trabalho; 8 s no total |
| Intervalado | 3 s trabalho, 2 s descanso, 1 s transição, 2 rounds | 9 s no total, sem descanso/transição final |
| Circuito | 2 estações, 3/2/1 s, 2 rounds | Alternar estações, repetir rounds e encerrar no último trabalho |

## Concorrência e isolamento

1. Abra uma segunda aba de controle da mesma sessão. Ela deve acompanhar em leitura. Clique **Assumir controle**: a anterior deve perder os botões de execução.
2. Requests duplicados com mesmo command_id/payload não reaplicam. Versões antigas ou lease vencido recebem 409 com snapshot. Reset sem confirmação e transições inválidas recebem 422.
3. Uma conta de outra box não deve ler TV/estado/resumo nem enviar comandos, mesmo conhecendo o ID. Um coach leitor da mesma box não pode se promover a controlador; somente responsável/admin têm essa autorização.
4. Edite o treino original após preparar uma sessão: a aula existente deve manter seu snapshot. Exclusão de workout com histórico deve ser bloqueada com mensagem clara.

## Comandos de validação

```sh
vendor/bin/sail artisan test --compact
npm run test:timers
vendor/bin/pint --dirty --format agent
composer validate --no-check-publish
composer check-platform-reqs
npm run build
```

Testes PHP usam apenas o banco MySQL `testing`, protegido pelo TestCase. Não apontar testes para banco com dados úteis. Não usar comandos destrutivos para atualizar a instalação.

## Regressão específica da Fase 3.1

1. Cadastre e publique Front Squat completo com warm-up de 6 minutos, skill de 10 e time cap de 15. Confira 360/600/900 segundos no snapshot, sem entrar 360 no formulário.
2. Prepare a aula, mas não a inicie. Edite/publice como Clean & Jerk, alterando também descrições, formato e duração do WOD. Confira detalhe e preview e prepare novamente: deve abrir outro ID de sessão com conteúdo novo. A antiga fica substituída, sem ser apagada.
3. Inicie a sessão atual e edite/publice o cadastro em outra aba. Coach/TV devem manter a execução original; o coach deve receber aviso e link para revisar o cadastro.
4. Confirme o reinício da aula com versão atual, usando a aba que possui controle. A aula anterior fica no histórico; a nova abre parada com título, blocos e timers atualizados. A TV anterior não é redirecionada silenciosamente: abra a TV da nova sessão.
5. Finalize uma aula, edite o cadastro e prepare outra: o histórico finalizado não pode mudar nem ser reutilizado como aula nova.
6. Edite duração antiga de 91 s: deve aparecer 1 minuto + 31 segundos. Salvar sem alterar mantém 91 s; mudar para 12 minutos e zero adicionais resulta em 720 s. Tabata 20/10, preparação 10 e circuito 45/15 continuam em segundos. Para os testes rápidos anteriores de countdown/AMRAP de 10 s, use 0 minutos + 10 adicionais.
7. Confira dashboard com logo horizontal, quadrada e vertical: contain, área maior e sem segunda logo no cabeçalho. Teste mobile, tema e TV. Com vários treinos hoje, selecionar explicitamente um deles.

As sessões anteriores ao versionamento não recebem uma revisão fictícia. Preparações antigas sem atividade podem ser substituídas; execuções existentes/históricas são preservadas.

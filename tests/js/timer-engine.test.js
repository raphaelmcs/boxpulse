import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { projectTimer, SessionClock, formatTime } from '../../resources/js/timers/engine.js';
const cases = JSON.parse(readFileSync(new URL('../fixtures/timer-configurations.json', import.meta.url)));
const configs = Object.fromEntries(cases.map((item) => [item.name, item.compiled]));
const at = (name, seconds, state = 'running') => projectTimer(configs[name], configs[name].preparation_ms + seconds * 1000, state);

test('stopwatch and uncapped FOR TIME advance from zero', () => {
    assert.equal(at('stopwatch', 12.5).display_ms, 12500);
    assert.equal(at('for_time', 30).display_ms, 30000);
    assert.equal(at('uncapped_for_time', 121).completed, false);
    assert.equal(at('uncapped_for_time', 121).cap_reached, true);
    assert.equal(at('uncapped_for_time', 121).progress, 1);
});
test('countdown and AMRAP finish exactly at zero', () => {
    assert.equal(at('countdown', 30).display_ms, 30000);
    assert.equal(at('countdown', 60).phase, 'completed');
    assert.equal(at('amrap', 600).display_ms, 0);
    assert.equal(at('amrap', 1000).elapsed_ms, 600000);
});
test('FOR TIME automatically caps by default', () => {
    assert.equal(at('for_time', 120).completed, true);
    assert.equal(at('for_time', 121).display_ms, 120000);
    assert.equal(at('for_time', 121).awaiting_confirmation, true);
    assert.equal(at('for_time', 121, 'completed').awaiting_confirmation, false);
});
test('preparation uses timestamps and transitions without subtracting ticks', () => {
    assert.equal(projectTimer(configs.countdown, 0, 'preparing').display_ms, 10000);
    assert.equal(projectTimer(configs.countdown, 5000, 'preparing').display_ms, 5000);
    assert.equal(projectTimer(configs.countdown, 10000, 'preparing').phase, 'work');
    assert.equal(projectTimer(configs.countdown, 10000, 'preparing').display_ms, 60000);
});
test('EMOM derives interval number and remaining interval', () => {
    assert.equal(at('emom', 59).round, 1);
    assert.equal(at('emom', 60).round, 2);
    assert.equal(at('emom', 60).display_ms, 60000);
    assert.equal(at('emom', 600).round, 10);
});
test('Tabata alternates work/rest with no final rest', () => {
    assert.equal(at('tabata', 20).phase, 'rest');
    assert.equal(at('tabata', 30).phase, 'work');
    assert.equal(at('tabata', 30).round, 2);
    assert.equal(at('tabata', 230).phase, 'completed');
});
test('interval adds explicit transition and skips final rest/transition', () => {
    assert.equal(at('interval', 30).phase, 'rest');
    assert.equal(at('interval', 40).phase, 'transition');
    assert.equal(at('interval', 45).round, 2);
    assert.equal(at('interval', 120).completed, true);
});
test('circuit respects station overrides including zero rest', () => {
    assert.equal(at('circuit', 0).station, 0);
    assert.equal(at('circuit', 20).phase, 'transition');
    assert.equal(at('circuit', 25).station, 1);
    assert.equal(at('circuit', 55).phase, 'rest');
    assert.equal(at('circuit', 65).station, 0);
    assert.equal(at('circuit', 65).round, 2);
    assert.equal(at('circuit', 120).completed, true);
});
test('free blocks do not start an automatic timer or completion', () => {
    assert.equal(configs.none.direction, 'none');
    assert.equal(configs.repetitions.preparation_ms, 0);
    assert.equal(at('repetitions', 100000).completed, false);
});
test('background suspension crosses many phases without drift', () => {
    const projection = at('tabata', 215);
    assert.equal(projection.round, 8);
    assert.equal(projection.phase, 'work');
    assert.equal(projection.display_ms, 15000);
});
function snapshot(version, state, elapsed, start = null) {
    return {version, current_position:0, server_time_ms:100000, timers:[{state, elapsed_ms:elapsed, effective_started_at_ms:start, config:configs.stopwatch}]};
}
test('monotonic clock survives local wall clock changes and short offline periods', () => {
    let time = 50;
    const clock = new SessionClock(() => time);
    clock.accept(snapshot(1,'running',0,90000), 50, 50);
    time += 5000;
    assert.equal(clock.elapsed(), 15000);
    time += 20000;
    assert.equal(clock.elapsed(), 35000);
});
test('pause freezes elapsed, resume uses a new anchor, reset returns idle', () => {
    let time = 0;
    const clock = new SessionClock(() => time);
    clock.accept(snapshot(1,'paused',15000),0,0);
    time += 60000;
    assert.equal(clock.elapsed(),15000);
    clock.accept(snapshot(2,'running',15000,100000),time,time);
    time += 1000;
    assert.equal(clock.elapsed(),16000);
    clock.accept(snapshot(3,'idle',0),time,time);
    assert.equal(clock.elapsed(),0);
});
test('reload reconstructs timestamps without persisting performance.now', () => {
    const restored = new SessionClock(() => 0);
    restored.accept(snapshot(3,'running',5000,90000),0,0);
    assert.equal(restored.elapsed(),15000);
});
test('polling ignores older versions and estimates network latency', () => {
    const clock = new SessionClock(() => 100);
    clock.accept(snapshot(3,'running',0,90000),0,100);
    assert.equal(clock.elapsed(),10050);
    assert.equal(clock.accept(snapshot(2,'idle',0),100,100),false);
    assert.equal(clock.snapshot.version,3);
});
test('idle, cancelled and manually completed states project deterministically', () => {
    assert.equal(projectTimer(configs.countdown,0,'idle').display_ms,60000);
    assert.equal(at('countdown',10,'cancelled').phase,'cancelled');
    assert.equal(at('stopwatch',5,'completed').phase,'completed');
    assert.equal(at('circuit',5,'completed').round,1);
});
test('formatted time rounds countdown up and stopwatch down', () => {
    assert.equal(formatTime(59001,'down'),'01:00');
    assert.equal(formatTime(59001,'up'),'00:59');
    assert.equal(formatTime(86400000,'up'),'24:00:00');
});

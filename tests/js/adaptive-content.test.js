import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {previewTimer} from '../../resources/js/timers/preview-timer.js';
import {contentGroups, balancedSplit, largestFittingFont} from '../../resources/js/timers/adaptive-content.js';
import {loadCardValue, presentationLoads} from '../../resources/js/timers/suggested-loads.js';

test('shows units for numeric references without relabeling free text or legacy pairs', () => {
    assert.deepEqual(loadCardValue('22,5 / 15 kg', 'kg'), {text: '22,5 / 15', unit: 'kg'});
    assert.deepEqual(loadCardValue('60/30', null), {text: '60/30', unit: ''});
    assert.deepEqual(loadCardValue('60/30', 'lb'), {text: '60/30', unit: 'lb'});
    assert.deepEqual(loadCardValue('20 kg por braço', 'lb'), {text: '20 kg por braço', unit: ''});
});

test('keeps generic and movement loads separate and omits references without weights', () => {
    const generic = {equipment: 'Barbell', rx: '0', unit: 'kg'};
    const deadlift = {equipment: 'Barbell', movement: 'Deadlift', scale: '60 / 30'};
    const squat = {equipment: 'Barbell', movement: 'Front Squat', intermediate: '50 / 30'};
    assert.deepEqual(presentationLoads({suggested_loads: generic, load_entries: [
        deadlift, {equipment: 'Barbell', movement: 'Power Clean', rx: ' '}, squat,
    ]}), [generic, deadlift, squat]);
    assert.deepEqual(presentationLoads({suggested_loads: null, load_entries: null}), []);
});

for(const count of [3,8,15,25])test(`all ${count} exercises remain in their original order`,()=>{
    const lines=Array.from({length:count},(_,index)=>`${index+1} / 10 dumbbell clean`);
    assert.deepEqual(contentGroups({description:lines.join('\r\n')}).map(group=>group.text),lines);
});
test('long instructions are never split into fragments',()=>{
    const instruction='Instrução longa: '+ 'execute com cuidado '.repeat(100);
    assert.equal(contentGroups({description:instruction})[0].text,instruction);
});
test('headings remain attached to the following instruction',()=>{
    const result=contentGroups({description:'3 rounds\n10 burpees\n20 squats'});
    assert.deepEqual(result.map(group=>group.text),['3 rounds\n10 burpees','20 squats']);
});
test('paragraphs preserve small exercise groups',()=>{
    assert.deepEqual(contentGroups({description:'Parte A\n10 burpees\n\nParte B\n20 squats'}).map(group=>group.text),['Parte A\n10 burpees','Parte B\n20 squats']);
});
test('stations preserve names metrics and complete descriptions as a group',()=>{
    const result=contentGroups({stations:[{name:'Row',distance:500,load:0,description:'Técnica contínua'}]});
    assert.equal(result.length,1);assert.equal(result[0].station,0);assert.match(result[0].text,/Row\n500 m · 0 kg\nTécnica contínua/);
});
test('balanced columns preserve order and never split a group',()=>{
    assert.equal(balancedSplit([10,10,10,10]),2);
    assert.equal(balancedSplit([30,5,5]),1);
    assert.equal(balancedSplit([5,5,30]),2);
    assert.equal(balancedSplit([100]),1);
});
test('literal markup remains ordinary text',()=>{
    assert.equal(contentGroups({description:'<script>alert(1)</script>'})[0].text,'<script>alert(1)</script>');
});

test('explicit paragraphs and indented continuations stay indivisible',()=>{
    const paragraph='Instrução essencial\nlinha 2\nlinha 3\nlinha 4\nlinha 5';
    assert.deepEqual(contentGroups({description:paragraph+'\n\nParte B'}).map(group=>group.text),[paragraph,'Parte B']);
    assert.deepEqual(contentGroups({description:'10 cleans\n  manter os cotovelos altos\n20 squats\n\n'}).map(group=>group.text),['10 cleans\n  manter os cotovelos altos','20 squats']);
});

test('presentation keeps individual styling attached to original lines and groups subtitles', () => {
    const styles = {0: {font_weight: 'normal'}, 2: {display_role: 'subtitle', text_color: '#facc15'}, 3: {font_weight: 'bold'}};
    const groups = contentGroups({description: 'First\n\nRound\nDo it', text_style: {description: styles}});
    assert.deepEqual(groups.map(group => group.lineStyles), [[styles[0]], [styles[2], styles[3]]]);
    const grouped = contentGroups({description: 'Round\nDo it\nRest', text_style: {description: {0: styles[2], 1: styles[3]}}});
    assert.deepEqual(grouped.map(group => group.text), ['Round\nDo it', 'Rest']);
});

test('uses the largest fitting font and rejects text at the minimum when it cannot fit', () => {
    assert.equal(largestFittingFont(22, 76, size => size <= 43), 43);
    assert.equal(largestFittingFont(22, 76, () => true), 76);
    assert.equal(largestFittingFont(22, 76, () => false), null);
    assert.equal(largestFittingFont(22, 76, size => size === 22), 22);
});

const fixtures = JSON.parse(readFileSync(new URL('../fixtures/timer-configurations.json', import.meta.url)));
const defaults = {preparation_seconds: 10, interval_seconds: 60};
for (const {name, block, compiled} of fixtures) {
    test(`idle preview matches the compiled ${name} fixture`, () => {
        assert.deepEqual(previewTimer(block, defaults).config, compiled);
    });
}

test('the editor converts minutes once and a draft can preview an incomplete timed block', () => {
    const timer = previewTimer({type: 'skill', timer_type: 'countdown', duration_minutes: '1', duration_extra_seconds: '30'}, defaults, '0');
    assert.equal(timer.config.duration_ms, 90_000);
    assert.equal(timer.config.preparation_ms, 0);
    assert.equal(timer.state, 'idle');
    const incomplete = previewTimer({type: 'skill', timer_type: 'countdown', duration_minutes: '0'}, defaults);
    assert.equal(incomplete.config.duration_ms, null);
    assert.equal(incomplete.incomplete, true);
});

test('blank station metrics do not occupy space in the editor preview', () => {
    const timer = previewTimer({type: 'workout', timer_type: 'circuit', stations: [{name: 'Bike', distance: '25', load: '', rest_seconds: '0'}]}, defaults);
    assert.equal(contentGroups(timer)[0].text, '1 / Bike\n25.00 m · 0 s de descanso');
});

import test from 'node:test';
import assert from 'node:assert/strict';
import {contentGroups, balancedSplit} from '../../resources/js/timers/adaptive-content.js';

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

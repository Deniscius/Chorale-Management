import test from 'node:test';
import assert from 'node:assert/strict';
import {modalPanel} from '../../resources/js/modal-panel.js';
function fixture(event='close-form') {
 const listeners=new Map(), dispatches=[]; let hook,cleanup=false;
 const field={focus(){document.activeElement=this;},getClientRects:()=>[{}],closest:()=>null};
 const last={...field}; const opener={isConnected:true,inert:false,focus(){document.activeElement=this;}};
 const background={tagName:'MAIN',inert:false};
 const body={style:{overflow:'auto'},children:[]};
 const root={parentElement:body,focus(){document.activeElement=this;},querySelector:()=>null,querySelectorAll:()=>[field,last],contains:el=>[root,field,last].includes(el)};
 body.children=[root,background];
 globalThis.document={body,activeElement:opener,addEventListener:(n,f)=>listeners.set(n,f),removeEventListener:n=>listeners.delete(n)};
 globalThis.window={Livewire:{hook:(n,f)=>{hook=f;return ()=>{cleanup=true;};}}};
 const state=modalPanel(event);Object.assign(state,{$el:root,$refs:{keepEditing:field},$nextTick:f=>f(),$dispatch:n=>dispatches.push(n)});state.init();
 return {state,field,last,opener,root,background,dispatches,listeners,hook:()=>hook,cleaned:()=>cleanup};
}
test('clean close dispatches the configured event and restores scroll and focus',()=>{const f=fixture('close-access');try{assert.equal(f.background.inert,true);assert.equal(document.body.style.overflow,'hidden');f.state.requestClose();assert.deepEqual(f.dispatches,['close-access']);}finally{f.state.destroy();}assert.equal(f.background.inert,false);assert.equal(document.body.style.overflow,'auto');assert.equal(document.activeElement,f.opener);assert.equal(f.cleaned(),true);});
test('dirty close stays in the dialog until explicitly discarded',()=>{const f=fixture('close-participants');try{f.state.dirty=true;f.state.requestClose();assert.equal(f.state.confirming,true);assert.deepEqual(f.dispatches,[]);f.state.keepEditing();assert.equal(f.state.confirming,false);assert.equal(f.state.dirty,true);f.state.requestClose();f.state.discard();assert.deepEqual(f.dispatches,['close-participants']);assert.equal(f.state.dirty,false);}finally{f.state.destroy();}});
test('pending requests and uploads prevent closing or discarding',()=>{const f=fixture();try{for(const property of ['pending','uploads']){f.state[property]=1;f.state.requestClose();f.state.discard();assert.deepEqual(f.dispatches,[]);f.state[property]=0;}f.state.requestClose();assert.deepEqual(f.dispatches,['close-form']);}finally{f.state.destroy();}});
test('escape dismisses the discard prompt before trying to close the dialog',()=>{const f=fixture();try{f.state.dirty=true;const e={preventDefault(){},stopImmediatePropagation(){}};f.state.escape(e);assert.equal(f.state.confirming,true);f.state.escape(e);assert.equal(f.state.confirming,false);assert.deepEqual(f.dispatches,[]);}finally{f.state.destroy();}});
test('tab and shift-tab wrap focus within the dialog',()=>{const f=fixture();try{let prevented=0;document.activeElement=f.last;f.state.trapTab({shiftKey:false,preventDefault:()=>prevented++});assert.equal(document.activeElement,f.field);f.state.trapTab({shiftKey:true,preventDefault:()=>prevented++});assert.equal(document.activeElement,f.last);assert.equal(prevented,2);}finally{f.state.destroy();}});
test('background focus is returned to the dialog and listeners are cleaned up',()=>{const f=fixture();f.listeners.get('focusin')({target:f.background});assert.equal(document.activeElement,f.field);f.state.destroy();assert.equal(f.listeners.size,0);});
test('commit completion releases the busy state after validation or failure',()=>{const f=fixture();try{let respond;f.hook()({component:{el:f.root},respond:fn=>respond=fn,succeed:()=>{}});assert.equal(f.state.busy,true);respond();assert.equal(f.state.busy,false);}finally{f.state.destroy();}});
test('unrelated component requests do not block this dialog',()=>{const f=fixture();try{f.hook()({component:{el:{contains:()=>false}},respond:()=>assert.fail(),succeed:()=>assert.fail()});assert.equal(f.state.busy,false);}finally{f.state.destroy();}});
test('server success marks an inline participant form clean',()=>{const f=fixture();try{let succeed;f.state.dirty=true;f.hook()({component:{el:f.root},respond:()=>{},succeed:fn=>succeed=fn});succeed({effects:{dispatches:[{name:'toast',params:{type:'success'}}]}});assert.equal(f.state.dirty,false);}finally{f.state.destroy();}});
test('beforeunload only warns about dirty or busy forms',()=>{const f=fixture();try{let count=0;const e={preventDefault:()=>count++};f.state.beforeUnload(e);assert.equal(count,0);f.state.dirty=true;f.state.beforeUnload(e);assert.equal(count,1);assert.equal(e.returnValue,'');}finally{f.state.destroy();}});

test('close buttons use the stored dialog root when Alpine changes the event element',()=>{const f=fixture('close-team');try{f.state.$el=f.last;f.state.requestClose();assert.deepEqual(f.dispatches,['close-team']);}finally{f.state.destroy();}});

import test from 'node:test';
import assert from 'node:assert/strict';
import { showFlashNotifications } from '../resources/js/flash-notifications.js';
test('success toast and blocking error use text instead of HTML',async()=>{
 const calls=[];const alert={fire:async options=>{calls.push(options);}};
 await showFlashNotifications({dataset:{notifications:JSON.stringify([{icon:'success',title:'Thành công',text:'Đã lưu',toast:true},{icon:'error',title:'Lỗi',text:'<script>bad()</script>',toast:false}])}},alert);
 assert.equal(calls[0].toast,true);assert.equal(calls[0].timer,5500);assert.equal(calls[1].showConfirmButton,true);assert.equal(calls[1].text,'<script>bad()</script>');assert.equal(calls[1].html,undefined);
});
test('missing or invalid notification payload does not open an alert',async()=>{
 const alert={fire:()=>{throw new Error('must not show');}};
 await showFlashNotifications(null,alert);await showFlashNotifications({dataset:{notifications:'bad'}},alert);
});
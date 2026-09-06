// Local VM regression checks. No browser, provider calls, or credentials needed.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '..', 'chat.js'), 'utf8');
function setup() {
  const nodes = new Map();
  function element() {
    return { hidden: false, disabled: false, value: '', textContent: '', children: [], handlers: {}, attrs: {},
      classList: { toggle() {} }, setAttribute(k,v) { this.attrs[k]=v; },
      addEventListener(k,fn) { this.handlers[k]=fn; }, focus() {}, remove() {},
      appendChild(child) { this.children.push(child); }, replaceChildren() { this.children=[]; },
      scrollTop: 0, scrollHeight: 100 };
  }
  const document = { querySelector(selector) { if(!nodes.has(selector)) nodes.set(selector,element()); return nodes.get(selector); }, createElement: element, addEventListener() {} };
  const widgets = [], requests = [], responses = [], removed = [];
  const window = { innerWidth: 800, addEventListener() {}, setTimeout(fn) { fn(); },
    turnstile: { render(container,options) { widgets.push(options); return widgets.length-1; }, remove(id) { removed.push(id); }, reset() {} } };
  const context = { document, window, location: { hostname: '127.0.0.1' }, matchMedia: () => ({ matches: true }),
    fetch: async (url, options) => { requests.push(JSON.parse(options.body)); const data = responses.shift(); assert.ok(data); return { ok: !data.error, json: async()=>data }; } };
  vm.runInNewContext(source,context);
  const click = selector => nodes.get(selector).handlers.click();
  const send = async (text,response) => { responses.push(response); nodes.get('#chat-input').value=text; nodes.get('#chat-form').handlers.submit({preventDefault(){}}); await new Promise(resolve=>setImmediate(resolve)); };
  return {nodes,widgets,requests,removed,click,send};
}
(async()=>{
  const ui = setup();
  ui.click('#chat-launcher');
  ui.widgets[0].callback('captcha-first');
  await ui.send('What is NetSuite?', {reply:'An ERP.',chat_session:'server-session'});
  assert.deepEqual(ui.removed,[0]);
  ui.widgets[0]['expired-callback']();
  ui.widgets[0]['error-callback']();
  assert.equal(ui.nodes.get('#chat-input').disabled,false);
  ui.click('#chat-close'); ui.click('#chat-launcher');
  assert.equal(ui.widgets.length,1,'Reopening a verified chat must not recreate CAPTCHA');
  await ui.send('Another tip?', {reply:'Use dashboards.',chat_session:'server-session'});
  assert.equal(ui.requests[1].chat_session,'server-session');
  assert.equal(ui.requests[1]['cf-turnstile-response'],'');
  assert.equal('history' in ui.requests[1],false,'Do not send client-owned history');
  await ui.send('Tell me more', {error:'Session expired',verification_required:true});
  assert.equal(ui.nodes.get('#chat-input').disabled,true);
  assert.equal(ui.widgets.length,2);
  assert.ok(ui.nodes.get('#chat-body').children.some(n=>n.textContent.includes('previous chat session has expired')));
  ui.widgets[1].callback('captcha-new');
  await ui.send('New question', {reply:'New answer.',chat_session:'new-session'});
  assert.equal(ui.requests[3].chat_session,'');
  assert.equal(ui.requests[3]['cf-turnstile-response'],'captcha-new');
  const fresh = setup(); fresh.click('#chat-launcher'); fresh.widgets[0].callback('fresh');
  await fresh.send('Hello', {reply:'Hello.',chat_session:'fresh-session'});
  assert.equal(fresh.requests[0].chat_session,'','Page refresh must not recover previous context');
  const cookies = fs.readFileSync(path.join(__dirname,'..','cookie-consent.js'),'utf8');
  for(const target of ['cookies.html','privacy.html','cookie-preferences.html']) {
    assert.ok(cookies.includes(`href="/${target}"`));
    assert.equal(new URL('/'+target,'https://cloudsysllc.com/insights/article').pathname,'/'+target);
  }
  console.log('Chat expiry, reopen, fresh-session, and cookie-link regressions passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});

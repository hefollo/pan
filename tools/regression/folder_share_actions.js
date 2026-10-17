/* Execute production requests against an isolated transport; no real uploads. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let source = fs.readFileSync('assets/js/folder-share.js', 'utf8');
source = source.replace('  var pending;', '  window.test = { replaceFile: replaceFile, hashFile: hashFile }; var pending;');
const listeners = [], calls = [];
const status = { textContent: '' };
const context = {
  URL, URLSearchParams, FormData, Promise, AbortController,
  Element: { prototype: { replaceWith() {} } },
  document: { addEventListener(event, handler) { if (event === 'click') listeners.push(handler); }, getElementById(id) { return id === 'share-status' ? status : { getAttribute() { return 'owner'; } }; } },
  location: { href: 'https://example.test/share.php?token=secret' },
  window: { fetch: true, AbortController, URLSearchParams, addEventListener() {}, confirm() { return true; }, prompt() { return 'new.txt'; } },
  fetch: async (url, options = {}) => {
    calls.push({ url, options });
    const data = url.includes('csrf_token') ? { csrf_token: 'fresh' }
      : url.includes('pre_upload') ? { code: 0, hash: 'hash', chunks: 2, chunksize: 2 }
      : url.includes('upload_part') ? { code: options.body.get('chunk') === '2' ? 1 : 0 }
      : { code: 0 };
    return { ok: true, json: async () => data };
  },
  FileReader: class { readAsArrayBuffer(blob) { blob.arrayBuffer().then(value => this.onload({ target: { result: value } })); } },
};
context.window.SparkMD5 = { ArrayBuffer: class { append() {} end() { return 'hash'; } } };
context.SparkMD5 = context.window.SparkMD5;
vm.createContext(context); vm.runInContext(source, context);
(async () => {
  const file = new Blob(['abcd']); file.name = 'new.txt';
  await context.window.test.replaceFile(file, '3');
  assert.deepEqual(calls.map(c => c.url), ['./ajax.php?act=csrf_token', './ajax.php?act=pre_upload', './ajax.php?act=upload_part', './ajax.php?act=upload_part']);
  assert.equal(calls[1].options.body.get('replace_id'), '3');
  assert.equal(calls[2].options.body.get('csrf_token'), 'fresh');
  assert.equal(calls[2].options.body.get('file').size, 2);
  assert.equal(calls[3].options.body.get('file').size, 2);
  console.log('PASS replacement keeps target, fresh CSRF, sequential chunks and completion');
  calls.length = 0;
  context.document.getElementById = () => ({ getAttribute() { return ''; } });
  listeners[0]({ target: { closest() { return { getAttribute() { return 'del'; } }; } } });
  assert.equal(calls.length, 0);
  console.log('PASS visitor cannot initiate management requests');
  let copied;
  context.navigator = { clipboard: { async writeText(url) { copied = url; } } };
  context.window.isSecureContext = true;
  listeners[0]({ target: { closest() { return { getAttribute(key) { return key === 'data-share-action' ? 'copy' : './share.php?token=secret&file=3'; } }; } } });
  await Promise.resolve();
  assert.equal(copied, 'https://example.test/share.php?token=secret&file=3');
  console.log('PASS visitor copies absolute share-scoped URL');
})().catch(error => { console.error(error); process.exitCode = 1; });

const editor = document.querySelector('#article-editor');
const bodyField = document.querySelector('#article-body');
const titleField = document.querySelector('#article-title');
const slugField = document.querySelector('#article-slug');
let dirty = false;
let slugEdited = Boolean(slugField?.value);
slugField?.addEventListener('input', () => { slugEdited = true; });
titleField?.addEventListener('input', () => {
  if (slugField && !slugField.readOnly && !slugEdited) slugField.value = titleField.value.toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 160).replace(/-$/, '');
});
editor?.addEventListener('input', () => { dirty = true; });
document.querySelectorAll('.editor-toolbar button').forEach((button) => {
  button.addEventListener('click', () => {
    if (!bodyField) return;
    const start = bodyField.selectionStart, end = bodyField.selectionEnd;
    const selection = bodyField.value.slice(start, end) || 'Your text';
    let text;
    if (button.hasAttribute('data-paragraph')) {
      const paragraph = selection.split('\n').map((line) => line.replace(/^(?:#{1,6}|[-*]|\d+\.)\s+/, '').trim()).filter(Boolean).join(' ');
      text = (start > 0 && !bodyField.value.slice(0, start).endsWith('\n\n') ? '\n\n' : '') + paragraph + (!bodyField.value.slice(end).startsWith('\n\n') ? '\n\n' : '');
    } else if (button.hasAttribute('data-link')) text = `[${selection}](https://example.com)`;
    else if (button.dataset.wrap) text = button.dataset.wrap + selection + button.dataset.wrap;
    else text = (start > 0 && bodyField.value[start - 1] !== '\n' ? '\n' : '') + selection.split('\n').map((line) => button.dataset.prefix + line).join('\n') + '\n';
    bodyField.setRangeText(text, start, end, 'select'); bodyField.focus(); dirty = true;
  });
});
editor?.addEventListener('submit', (event) => {
  if (event.submitter?.hasAttribute('data-publish') && !window.confirm('Publish this article so everyone can read it?')) { event.preventDefault(); return; }
  dirty = false;
});
window.addEventListener('beforeunload', (event) => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });

const editor = document.querySelector('#article-editor');
const bodyField = document.querySelector('#article-body');
const titleField = document.querySelector('#article-title');
const slugField = document.querySelector('#article-slug');
const summaryField = document.querySelector('#article-summary');
const categoryField = document.querySelector('#article-category');
const authorField = document.querySelector('#article-author');
const coverField = document.querySelector('#article-cover');
const previewTitle = document.querySelector('#live-preview-title');
const previewSummary = document.querySelector('#preview-summary');
const previewCategory = document.querySelector('#preview-category');
const previewByline = document.querySelector('#preview-byline');
const previewBody = document.querySelector('#preview-body');
const previewCover = document.querySelector('#preview-cover');
const previewCoverWrap = document.querySelector('.editor-preview-cover-wrap');
let dirty = false;
let slugEdited = Boolean(slugField?.value);
let previewCoverUrl = '';

const appendInlinePreview = (container, source) => {
  const pattern = /(\*\*[^*\r\n]+\*\*|\[[^\]\r\n]+\]\(https?:\/\/[^\s)<>"']+\))/gi;
  let cursor = 0;
  for (const match of source.matchAll(pattern)) {
    container.append(document.createTextNode(source.slice(cursor, match.index)));
    const token = match[0];
    if (token.startsWith('**')) {
      const strong = document.createElement('strong');
      strong.textContent = token.slice(2, -2);
      container.append(strong);
    } else {
      const parts = token.match(/^\[([^\]]+)\]\((https?:\/\/[^\s)<>"']+)\)$/i);
      const link = document.createElement('a');
      link.textContent = parts[1];
      link.href = parts[2];
      link.rel = 'noopener noreferrer';
      container.append(link);
    }
    cursor = match.index + token.length;
  }
  container.append(document.createTextNode(source.slice(cursor)));
};

const renderBodyPreview = (source) => {
  previewBody.replaceChildren();
  const lines = source.split(/\r?\n/);
  let paragraph = [];
  let list = null;
  const flush = () => {
    if (paragraph.length) {
      const element = document.createElement('p');
      appendInlinePreview(element, paragraph.join('\n'));
      previewBody.append(element);
      paragraph = [];
    }
    list = null;
  };
  for (const line of lines) {
    if (!line.trim()) { flush(); continue; }
    const heading = line.match(/^(#{2,3})\s+(.+)$/);
    if (heading) {
      flush();
      const element = document.createElement(heading[1].length === 2 ? 'h2' : 'h3');
      appendInlinePreview(element, heading[2]);
      previewBody.append(element);
      continue;
    }
    const item = line.match(/^(?:([-*])|\d+\.)\s+(.+)$/);
    if (item) {
      const type = item[1] ? 'UL' : 'OL';
      if (!list || list.tagName !== type) {
        flush();
        list = document.createElement(type.toLowerCase());
        previewBody.append(list);
      }
      const element = document.createElement('li');
      appendInlinePreview(element, item[2]);
      list.append(element);
      continue;
    }
    if (list) flush();
    paragraph.push(line);
  }
  flush();
  if (!previewBody.children.length) {
    const empty = document.createElement('p');
    empty.textContent = 'Your formatted article text will appear here.';
    previewBody.append(empty);
  }
};

const updatePreview = () => {
  if (!previewTitle) return;
  previewTitle.textContent = titleField?.value.trim() || 'Your article title';
  previewSummary.textContent = summaryField?.value.trim() || 'Your article summary will appear here.';
  previewCategory.textContent = categoryField?.selectedOptions[0]?.value ? categoryField.selectedOptions[0].textContent : 'Category';
  previewByline.textContent = authorField?.value.trim() || 'CloudSys';
  renderBodyPreview(bodyField?.value || '');
};
slugField?.addEventListener('input', () => { slugEdited = true; });
titleField?.addEventListener('input', () => {
  if (slugField && !slugField.readOnly && !slugEdited) slugField.value = titleField.value.toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 160).replace(/-$/, '');
});
editor?.addEventListener('input', () => { dirty = true; updatePreview(); });
editor?.addEventListener('change', updatePreview);
coverField?.addEventListener('change', () => {
  if (previewCoverUrl) URL.revokeObjectURL(previewCoverUrl);
  const file = coverField.files?.[0];
  if (!file) return;
  previewCoverUrl = URL.createObjectURL(file);
  previewCover.src = previewCoverUrl;
  previewCoverWrap.hidden = false;
});
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
window.addEventListener('unload', () => { if (previewCoverUrl) URL.revokeObjectURL(previewCoverUrl); });
updatePreview();

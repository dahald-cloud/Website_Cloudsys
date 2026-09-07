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
const previewDialog = document.querySelector('#article-preview-dialog');
const previewOpen = document.querySelector('#preview-article-button');
const previewClose = document.querySelector('#close-article-preview');
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
    const video = line.match(/^\[video\]\((https?:\/\/[^\s)<>"']+)\)$/i);
    if (video) {
      flush();
      const frame = document.createElement('div');
      frame.className = 'article-video';
      const iframe = document.createElement('iframe');
      const youtube = video[1].match(/^https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})(?:[&#?].*)?$/i);
      const vimeo = video[1].match(/^https?:\/\/(?:www\.)?vimeo\.com\/([0-9]{6,12})(?:[/?#].*)?$/i);
      if (youtube) iframe.src = 'https://www.youtube-nocookie.com/embed/' + youtube[1];
      else if (vimeo) iframe.src = 'https://player.vimeo.com/video/' + vimeo[1];
      else {
        const warning = document.createElement('p');
        warning.className = 'editor-preview-warning';
        warning.textContent = 'This video link is not an approved YouTube or Vimeo URL.';
        previewBody.append(warning);
        continue;
      }
      iframe.title = 'Embedded article video';
      iframe.loading = 'lazy';
      iframe.allowFullscreen = true;
      frame.append(iframe);
      previewBody.append(frame);
      continue;
    }
    const image = line.match(/^!\[([^\]\r\n]{1,255})\]\(\/article-inline-media\.php\?id=([1-9][0-9]{0,18})\)$/);
    if (image) {
      flush();
      const figure = document.createElement('figure');
      figure.className = 'article-inline-image';
      const element = document.createElement('img');
      element.src = '/article-inline-media.php?id=' + image[2];
      element.alt = image[1];
      element.width = 1600;
      element.height = 1000;
      figure.append(element);
      previewBody.append(figure);
      continue;
    }
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
    } else if (button.hasAttribute('data-video')) text = '[video](' + (selection.startsWith('http') ? selection : 'https://www.youtube.com/watch?v=VIDEO_ID') + ')';
    else if (button.hasAttribute('data-link')) text = `[${selection}](https://example.com)`;
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
previewOpen?.addEventListener('click', () => {
  updatePreview();
  previewDialog?.showModal();
  document.body.classList.add('preview-modal-open');
});
previewClose?.addEventListener('click', () => previewDialog?.close());
previewDialog?.addEventListener('click', (event) => {
  const box = previewDialog.getBoundingClientRect();
  if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) previewDialog.close();
});
previewDialog?.addEventListener('close', () => document.body.classList.remove('preview-modal-open'));
document.querySelectorAll('.editor-copy-code').forEach((button) => {
  button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(button.dataset.code || '');
      button.textContent = 'Copied';
      window.setTimeout(() => { button.textContent = 'Copy code'; }, 1600);
    } catch { button.textContent = 'Select the code above'; }
  });
});
updatePreview();

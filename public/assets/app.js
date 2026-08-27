/* One page, three states. No framework, no external requests. */
'use strict';

const $ = (id) => document.getElementById(id);

const ui = {
  gate: $('gate'), gateForm: $('gate-form'), gateErr: $('gate-err'), gateGo: $('gate-go'),
  chat: $('chat'), scene: $('scene'), log: $('log'), chatErr: $('chat-err'),
  sayForm: $('say-form'), say: $('say'), send: $('send'), finish: $('finish'),
  report: $('report'), reportBody: $('report-body'), download: $('download'), saved: $('saved'),
  counter: $('counter'), thinking: $('thinking'),
};

const state = { turn: 0, maxTurns: 50, name: 'the client', markdown: '', filename: 'transcript.md' };

// ---------------------------------------------------------------- transport

async function api(action, payload = {}, quiet = false) {
  if (!quiet) ui.thinking.hidden = false;
  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, ...payload }),
    });
    let data;
    try {
      data = await res.json();
    } catch {
      throw new Error('The server sent something unexpected. Try again in a moment.');
    }
    if (!data.ok) throw new Error(data.error || 'Something went wrong.');
    return data;
  } finally {
    if (!quiet) ui.thinking.hidden = true;
  }
}

function showError(el, message) {
  el.textContent = message;
  el.hidden = false;
}

function clearError(el) {
  el.hidden = true;
  el.textContent = '';
}

// ------------------------------------------------------------------ display

function addTurn(who, name, text) {
  const wrap = document.createElement('div');
  wrap.className = 'turn ' + who;
  const label = document.createElement('span');
  label.className = 'who';
  label.textContent = name;
  const body = document.createElement('div');
  body.className = 'what';
  body.textContent = text;            // textContent, never innerHTML
  wrap.append(label, body);
  ui.log.append(wrap);
  wrap.scrollIntoView({ block: 'end', behavior: 'smooth' });
}

function updateCounter() {
  const left = Math.max(0, state.maxTurns - state.turn);
  ui.counter.hidden = false;
  ui.counter.textContent = left > 10
    ? `turn ${state.turn}`
    : `turn ${state.turn} — ${left} left`;
}

/* She has gone, or the turns have run out: no more talking, only finishing. */
function closeConversation(message) {
  showError(ui.chatErr, message);
  ui.say.disabled = true;
  ui.send.disabled = true;
  ui.finish.disabled = false;
  ui.finish.classList.remove('ghost');
  ui.finish.classList.add('primary');
  ui.finish.focus();
}

function enterChat(data) {
  state.turn = data.turn;
  state.maxTurns = data.max_turns;
  state.name = data.name;
  ui.scene.textContent = data.scene;
  ui.log.replaceChildren();
  (data.messages || []).forEach((m) => addTurn(m.who, m.name, m.text));
  ui.gate.hidden = true;
  ui.chat.hidden = false;
  updateCounter();
  if (data.walked_out) {
    closeConversation(data.name + ' has ended the meeting. Close the session to get your feedback.');
  } else {
    ui.say.focus();
  }
}

function enterReport(data) {
  state.markdown = data.markdown || '';
  state.filename = data.filename || 'transcript.md';
  ui.reportBody.innerHTML = renderMarkdown(state.markdown);
  ui.saved.textContent = data.note || 'Saved.';
  ui.chat.hidden = true;
  ui.report.hidden = false;
  ui.counter.hidden = true;
  window.scrollTo({ top: 0 });
}

// ----------------------------------------------------------------- markdown

/* A small renderer for the report we generate ourselves. Everything is escaped
   before any markup is added, so student text can never become HTML. */
function renderMarkdown(md) {
  const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const inline = (s) => esc(s)
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|\W)_(.+?)_(?=\W|$)/g, '$1<em>$2</em>')
    .replace(/`(.+?)`/g, '<code>$1</code>');

  const out = [];
  const lines = md.split('\n');
  let para = [], list = null, quote = [], code = null;

  const flushPara = () => { if (para.length) { out.push('<p>' + inline(para.join(' ')) + '</p>'); para = []; } };
  const flushQuote = () => { if (quote.length) { out.push('<blockquote>' + inline(quote.join(' ')) + '</blockquote>'); quote = []; } };
  const flushList = () => { if (list) { out.push(`<${list.tag}>` + list.items.map((i) => '<li>' + inline(i) + '</li>').join('') + `</${list.tag}>`); list = null; } };
  const flushAll = () => { flushPara(); flushQuote(); flushList(); };

  for (const raw of lines) {
    const line = raw.replace(/\s+$/, '');

    if (code !== null) {
      if (line.trim() === '```') { out.push('<pre>' + esc(code.join('\n')) + '</pre>'); code = null; }
      else code.push(raw);
      continue;
    }
    if (line.trim().startsWith('```')) { flushAll(); code = []; continue; }

    if (!line.trim()) { flushAll(); continue; }

    const h = line.match(/^(#{1,3})\s+(.*)$/);
    if (h) { flushAll(); out.push(`<h${h[1].length}>${inline(h[2])}</h${h[1].length}>`); continue; }

    if (/^---+$/.test(line.trim())) { flushAll(); out.push('<hr>'); continue; }

    if (line.startsWith('> ')) { flushPara(); flushList(); quote.push(line.slice(2)); continue; }

    const ol = line.match(/^(\d+)\.\s+(.*)$/);
    const ul = line.match(/^[-*]\s+(.*)$/);
    if (ol || ul) {
      flushPara(); flushQuote();
      const tag = ol ? 'ol' : 'ul';
      if (!list || list.tag !== tag) { flushList(); list = { tag, items: [] }; }
      list.items.push((ol ? ol[2] : ul[1]));
      continue;
    }

    flushQuote(); flushList();
    para.push(line.trim());
  }
  flushAll();
  if (code !== null) out.push('<pre>' + esc(code.join('\n')) + '</pre>');
  return out.join('\n');
}

// -------------------------------------------------------------------- events

ui.gateForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  clearError(ui.gateErr);
  ui.gateGo.disabled = true;
  try {
    const data = await api('start', {
      student: $('student').value,
      code: $('code').value,
    });
    if (data.ended) {
      enterChat(data);
      await finishUp();
    } else {
      enterChat(data);
    }
  } catch (err) {
    showError(ui.gateErr, err.message);
  } finally {
    ui.gateGo.disabled = false;
  }
});

ui.sayForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const text = ui.say.value.trim();
  if (!text) return;
  clearError(ui.chatErr);

  addTurn('you', 'You', text);
  ui.say.value = '';
  ui.send.disabled = ui.finish.disabled = ui.say.disabled = true;

  try {
    const data = await api('message', { text, turn: state.turn });
    state.turn = data.turn;
    addTurn('client', state.name, data.say);
    updateCounter();
    if (data.walked_out) {
      closeConversation(state.name + ' has ended the meeting. Close the session to get your feedback.');
      return;
    }
    if (data.left === 0) {
      showError(ui.chatErr, 'That is the last turn. Close the session to get your feedback.');
      ui.say.disabled = true;
      ui.send.disabled = true;
      ui.finish.disabled = false;
      return;
    }
  } catch (err) {
    showError(ui.chatErr, err.message + ' Your message is below; you can send it again.');
    ui.say.value = text;
    ui.log.lastElementChild?.remove();
  } finally {
    if (state.turn < state.maxTurns) {
      ui.send.disabled = ui.finish.disabled = ui.say.disabled = false;
      ui.say.focus();
    }
  }
});

// Enter sends, Shift+Enter is a newline. Familiar, and hard to get wrong.
ui.say.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    ui.sayForm.requestSubmit();
  }
});

async function finishUp() {
  clearError(ui.chatErr);
  ui.finish.disabled = ui.send.disabled = ui.say.disabled = true;
  try {
    enterReport(await api('end'));
  } catch (err) {
    showError(ui.chatErr, err.message);
    ui.finish.disabled = ui.send.disabled = ui.say.disabled = false;
  }
}

ui.finish.addEventListener('click', () => {
  if (state.turn === 0) {
    showError(ui.chatErr, 'Say something to her first.');
    return;
  }
  if (confirm('Close the session and get your feedback? You cannot go back to the conversation afterwards.')) {
    finishUp();
  }
});

ui.download.addEventListener('click', () => {
  const blob = new Blob([state.markdown], { type: 'text/markdown;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = state.filename;
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
});

// A closed tab should not cost forty turns of work.
(async function resume() {
  try {
    const data = await api('resume', {}, true);   // silent: the student never asked
    if (!data.resumed) return;
    if (data.ended) return;                 // finished sessions start fresh at the gate
    enterChat(data);
  } catch {
    /* no session to resume; the gate is already showing */
  }
})();

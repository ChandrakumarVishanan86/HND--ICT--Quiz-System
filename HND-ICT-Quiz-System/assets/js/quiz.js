/* Quiz player: loads the questions, saves every answer to the server, runs the clock, submits. */
(() => {
    const body = document.body;
    const attemptId = Number(body.dataset.attempt);
    const csrf = body.dataset.csrf;
    const LETTERS = ['A', 'B', 'C', 'D'];

    const $ = (id) => document.getElementById(id);
    const el = {
        sheet: $('sheet'), pal: $('pal'), bar: $('bar'), count: $('qCount'), timer: $('timer'), time: $('timeText'),
        prev: $('prevBtn'), next: $('nextBtn'), flag: $('flagBtn'), submit: $('submitBtn'),
        dialog: $('confirm'), dialogText: $('confirmText'), dialogOk: $('confirmOk'),
    };

    const state = { questions: [], answers: {}, flags: new Set(), i: 0, deadline: 0, submitting: false };

    /* ---------- helpers ---------- */
    const h = (tag, cls, text) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    };
    const answered = () => state.questions.filter((q) => state.answers[q.id] !== undefined && state.answers[q.id] !== null).length;

    async function api(path, opts) {
        const res = await fetch(path, Object.assign({ credentials: 'same-origin' }, opts));
        let data = {};
        try { data = await res.json(); } catch (e) { /* not JSON */ }
        return { ok: res.ok, status: res.status, data };
    }

    function post(path, payload) {
        return api(path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(payload),
        });
    }

    /* ---------- rendering ---------- */
    function renderPalette() {
        el.pal.replaceChildren();
        state.questions.forEach((q, idx) => {
            const b = h('button', 'pb', String(idx + 1));
            b.type = 'button';
            if (state.answers[q.id] !== undefined && state.answers[q.id] !== null) b.classList.add('done');
            if (state.flags.has(q.id)) b.classList.add('flag');
            if (idx === state.i) { b.classList.add('now'); b.setAttribute('aria-current', 'true'); }
            b.setAttribute('aria-label', 'Question ' + (idx + 1) + (b.classList.contains('done') ? ', answered' : ', blank') + (b.classList.contains('flag') ? ', flagged' : ''));
            b.addEventListener('click', () => go(idx));
            el.pal.appendChild(b);
        });
    }

    function renderQuestion() {
        const q = state.questions[state.i];
        el.sheet.replaceChildren();

        const head = h('div', 'q-head');
        head.appendChild(h('span', 'q-num', String(state.i + 1)));
        const text = h('h1', 'q-text', q.text);
        text.id = 'qtext';
        head.appendChild(text);
        el.sheet.appendChild(head);

        const group = h('div', 'q-opts');
        group.setAttribute('role', 'radiogroup');
        group.setAttribute('aria-labelledby', 'qtext');
        q.options.forEach((label, idx) => {
            const on = state.answers[q.id] === idx;
            const b = h('button', 'opt' + (on ? ' picked' : ''));
            b.type = 'button';
            b.setAttribute('role', 'radio');
            b.setAttribute('aria-checked', on ? 'true' : 'false');
            b.appendChild(h('span', 'b', LETTERS[idx]));
            b.appendChild(h('span', 'opt-text', label));
            b.addEventListener('click', () => choose(idx));
            group.appendChild(b);
        });
        el.sheet.appendChild(group);
        el.sheet.appendChild(h('p', 'q-hint', 'Press A to D to choose. Click your answer again to clear it.'));

        el.prev.disabled = state.i === 0;
        const last = state.i === state.questions.length - 1;
        el.next.replaceChildren(document.createTextNode(last ? 'Review and submit' : 'Next'));
        const flagged = state.flags.has(q.id);
        el.flag.setAttribute('aria-pressed', flagged ? 'true' : 'false');
        el.flag.classList.toggle('on', flagged);

        el.count.textContent = 'Question ' + (state.i + 1) + ' of ' + state.questions.length + ', ' + answered() + ' answered';
        el.bar.style.width = (answered() / state.questions.length * 100) + '%';
        renderPalette();
    }

    /* ---------- actions ---------- */
    function go(idx) {
        if (idx < 0 || idx >= state.questions.length) return;
        state.i = idx;
        renderQuestion();
        window.scrollTo({ top: 0 });
    }

    async function choose(idx) {
        const q = state.questions[state.i];
        const value = state.answers[q.id] === idx ? null : idx;     // clicking the marked bubble clears it
        if (value === null) delete state.answers[q.id]; else state.answers[q.id] = value;
        renderQuestion();
        try {
            const r = await post('api/answer.php', { attempt_id: attemptId, question_id: q.id, choice: value });
            if (r.status === 409 && r.data && r.data.expired) submit(true);
        } catch (e) { /* offline: the full answer set is sent again on submit */ }
    }

    function toggleFlag() {
        const q = state.questions[state.i];
        if (state.flags.has(q.id)) state.flags.delete(q.id); else state.flags.add(q.id);
        renderQuestion();
    }

    function openConfirm() {
        const blank = state.questions.length - answered();
        const flagged = state.flags.size;
        const parts = [];
        parts.push(blank === 0 ? 'You answered every question.' : blank + (blank === 1 ? ' question is' : ' questions are') + ' still blank and will count as wrong.');
        if (flagged) parts.push(flagged + (flagged === 1 ? ' question is' : ' questions are') + ' flagged for review.');
        parts.push('You cannot change answers after submitting.');
        el.dialogText.textContent = parts.join(' ');
        if (typeof el.dialog.showModal === 'function') el.dialog.showModal();
        else if (window.confirm(parts.join(' ') + ' Submit now?')) submit(false);
    }

    async function submit(auto) {
        if (state.submitting) return;
        state.submitting = true;
        el.submit.disabled = el.next.disabled = el.prev.disabled = el.flag.disabled = true;
        el.submit.textContent = auto ? 'Time is up. Submitting...' : 'Submitting...';
        try {
            const r = await post('api/submit.php', { attempt_id: attemptId, answers: state.answers });
            if (r.data && r.data.redirect) { window.location.href = r.data.redirect; return; }
            throw new Error((r.data && r.data.error) || 'Could not submit.');
        } catch (e) {
            state.submitting = false;
            el.submit.disabled = el.next.disabled = el.flag.disabled = false;
            el.prev.disabled = state.i === 0;
            el.submit.textContent = 'Submit answers';
            window.alert((e && e.message ? e.message : 'Could not submit.') + ' Check your connection and press Submit again.');
        }
    }

    /* ---------- clock ---------- */
    function tick() {
        const left = Math.max(0, Math.round((state.deadline - Date.now()) / 1000));
        const m = Math.floor(left / 60), s = left % 60;
        el.time.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        el.timer.classList.toggle('low', left <= 60);
        if (left === 0) submit(true);
    }

    /* ---------- events ---------- */
    el.prev.addEventListener('click', () => go(state.i - 1));
    el.next.addEventListener('click', () => {
        if (state.i === state.questions.length - 1) openConfirm(); else go(state.i + 1);
    });
    el.flag.addEventListener('click', toggleFlag);
    el.submit.addEventListener('click', openConfirm);
    el.dialog.addEventListener('close', () => { if (el.dialog.returnValue === 'ok') submit(false); });

    document.addEventListener('keydown', (e) => {
        if (el.dialog.open || state.submitting || !state.questions.length || e.ctrlKey || e.metaKey || e.altKey) return;
        const k = e.key.toLowerCase();
        const pick = 'abcd'.indexOf(k) !== -1 && k.length === 1 ? 'abcd'.indexOf(k) : ('1234'.indexOf(k) !== -1 && k.length === 1 ? '1234'.indexOf(k) : -1);
        if (pick !== -1) { e.preventDefault(); choose(pick); }
        else if (e.key === 'ArrowRight') go(state.i + 1);
        else if (e.key === 'ArrowLeft') go(state.i - 1);
        else if (k === 'f') toggleFlag();
    });

    /* ---------- start ---------- */
    (async () => {
        try {
            const r = await api('api/attempt.php?id=' + attemptId);
            if (r.data && r.data.redirect) { window.location.href = r.data.redirect; return; }
            if (!r.ok) throw new Error((r.data && r.data.error) || 'Could not load the quiz.');

            state.questions = r.data.questions;
            Object.keys(r.data.saved || {}).forEach((k) => { state.answers[Number(k)] = r.data.saved[k]; });
            state.deadline = Date.now() + r.data.seconds * 1000;

            // open on the first blank question when resuming
            const firstBlank = state.questions.findIndex((q) => state.answers[q.id] === undefined);
            state.i = firstBlank === -1 ? 0 : firstBlank;

            renderQuestion();
            tick();
            setInterval(tick, 500);
        } catch (e) {
            el.sheet.replaceChildren();
            el.sheet.appendChild(h('p', 'alert err', e.message || 'Could not load the quiz.'));
            const a = h('a', 'btn primary', 'Back to modules');
            a.href = 'dashboard.php';
            el.sheet.appendChild(a);
        }
    })();
})();

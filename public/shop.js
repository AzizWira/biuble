const rupiah = amount => new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(amount);
const whatsappOpen = document.querySelector('[data-whatsapp-open]');
if (whatsappOpen) {
    const dialog = document.getElementById(whatsappOpen.getAttribute('aria-controls'));
    whatsappOpen.hidden = false;
    whatsappOpen.addEventListener('click', () => dialog.showModal());
}
for (const form of document.querySelectorAll('.fee-form')) {
    function updateFee() {
        const base = Number(form.querySelector('[name=amount]')?.value || form.dataset.base || 0);
        const method = form.querySelector('[name=method]').value;
        let fee = 0;
        if (form.dataset.fees) {
            fee = Number(JSON.parse(form.dataset.fees)[method] || 0);
        } else if (!['manual','QRIS','CREDIT_CARD'].includes(method) && JSON.parse(form.dataset.channels || '[]').includes(method)) {
            fee = form.dataset.type === 'percent' ? Math.round(base * Number(form.dataset.value) / 100) : Number(form.dataset.value);
        }
        form.querySelector('[data-fee]').textContent = rupiah(fee);
        form.querySelector('[data-total]').textContent = rupiah(base + fee);
        form.querySelector('[name=expected_total]').value = base + fee;
        const proof = form.querySelector('[name=proof]');
        if (proof) proof.required = method === 'manual';
        for (const field of form.querySelectorAll('.manual-only')) field.hidden = method !== 'manual';
    }
    form.addEventListener('input', updateFee);
    form.addEventListener('change', updateFee);
    updateFee();
}
for (const form of document.querySelectorAll('.withdraw-form')) {
    function updateWithdrawal() {
        const amount = ['topup','cancellation','refund'].reduce((sum,key) => sum + Number(form.elements[key].value || 0), 0);
        const fee = Number(form.elements.refund.value || 0) > 0 ? Number(form.dataset.fee) : 0;
        form.querySelector('[data-fee]').textContent = rupiah(fee);
        form.querySelector('[data-net]').textContent = rupiah(Math.max(0, amount-fee));
        form.querySelector('[name=expected_fee]').value = fee;
    }
    form.addEventListener('input', updateWithdrawal);
    updateWithdrawal();
}
const confirmation = document.querySelector('[data-confirm-dialog]');
if (confirmation) {
    let pending = null;
    let confirmedForm = null;

    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || !form.matches('form[data-confirm]') || form === confirmedForm) return;
        event.preventDefault();
        if (confirmation.open || pending) return;
        const submitter = event.submitter;
        confirmation.querySelector('#confirm-title').textContent = submitter?.dataset.confirmTitle || form.dataset.confirmTitle || 'Konfirmasi tindakan';
        confirmation.querySelector('#confirm-copy').textContent = submitter?.dataset.confirm || form.dataset.confirm;
        confirmation.querySelector('[data-confirm-accept]').textContent = submitter?.dataset.confirmLabel || form.dataset.confirmLabel || 'Lanjutkan';
        pending = {form, submitter};
        confirmation.returnValue = 'cancel';
        confirmation.showModal();
    });

    confirmation.addEventListener('close', () => {
        if (!pending) return;
        const {form, submitter} = pending;
        pending = null;
        if (confirmation.returnValue !== 'accept') return;
        confirmedForm = form;
        try {
            form.requestSubmit(submitter ?? undefined);
        } finally {
            confirmedForm = null;
        }
    });
}

const stockSelectors = [...document.querySelectorAll('[data-stock-select]')];
for (const selector of stockSelectors) {
    const search = selector.querySelector('[data-stock-search]');
    const options = [...selector.querySelectorAll('[data-stock-option]')];
    search.hidden = !options.length;
    search.addEventListener('input', () => {
        const terms = search.value.trim().toLocaleLowerCase('id').split(/\s+/);
        for (const option of options) {
            const label = option.textContent.toLocaleLowerCase('id');
            option.hidden = !terms.every(term => label.includes(term));
        }
        selector.querySelector('[data-stock-empty]').hidden = options.some(option => !option.hidden);
    });
    selector.addEventListener('toggle', () => {
        if (!selector.open) return;
        for (const other of stockSelectors) if (other !== selector) other.open = false;
        if (!search.hidden && !selector.classList.contains('stock-select-inline')) search.focus();
    });
    selector.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !selector.open) return;
        event.preventDefault();
        selector.open = false;
        selector.querySelector('summary').focus();
    });
}
if (stockSelectors.length) {
    document.addEventListener('click', event => {
        for (const selector of stockSelectors) {
            if (!selector.classList.contains('stock-select-inline') && !selector.contains(event.target)) selector.open = false;
        }
    });
}

const stockAccessDialog = document.querySelector('[data-stock-access-dialog]');
if (stockAccessDialog) {
    const title = stockAccessDialog.querySelector('#stock-access-title');
    const status = stockAccessDialog.querySelector('[data-stock-access-status]');
    const content = stockAccessDialog.querySelector('[data-stock-access-content]');
    let activeRequest;

    stockAccessDialog.querySelector('[data-stock-access-close]').addEventListener('click', () => stockAccessDialog.close());
    stockAccessDialog.addEventListener('close', () => {
        activeRequest?.abort();
        content.textContent = '';
        content.hidden = true;
    });
    for (const button of document.querySelectorAll('[data-stock-access]')) {
        button.addEventListener('click', async () => {
            const request = new AbortController();
            activeRequest = request;
            title.textContent = button.dataset.accountLabel;
            content.textContent = '';
            content.hidden = true;
            status.hidden = false;
            status.textContent = 'Memuat detail akses…';
            stockAccessDialog.showModal();
            try {
                const response = await fetch(button.dataset.stockAccess, {
                    headers: {Accept: 'application/json'}, cache: 'no-store', signal: request.signal,
                });
                if (!response.ok) throw new Error('Detail akses tidak tersedia');
                const {access} = await response.json();
                if (!stockAccessDialog.open || activeRequest !== request) return;
                content.textContent = access ?? '';
                content.hidden = !access;
                status.hidden = Boolean(access);
                status.textContent = access ? '' : 'Belum ada detail akses untuk akun ini.';
            } catch {
                if (stockAccessDialog.open && !request.signal.aborted && activeRequest === request) {
                    status.textContent = 'Detail akses belum bisa dimuat. Tutup dialog lalu coba lagi.';
                }
            }
        });
    }
}



const updateSurface = document.querySelector('[data-updates-url]');
if (updateSurface) {
    let editing = false;
    updateSurface.addEventListener('input', () => { editing = true; });
    async function checkUpdates() {
        if (document.hidden) return;
        try {
            const response = await fetch(updateSurface.dataset.updatesUrl, { headers: {Accept:'application/json'} });
            if (!response.ok) return;
            const data = await response.json();
            if (data.version !== updateSurface.dataset.updatesVersion) {
                if (editing) {
                    document.querySelector('#updates-notice').hidden = false;
                } else {
                    window.location.reload();
                }
            }
        } catch {
            // The persisted transaction remains available after connectivity returns.
        }
    }
    setInterval(checkUpdates, 10000);
}

for (const button of document.querySelectorAll('[data-quantity-step]')) {
    button.addEventListener('click', () => {
        const input = button.closest('.quantity-control').querySelector('input');
        input.value = Math.min(Number(input.max), Math.max(Number(input.min), Number(input.value || 1) + Number(button.dataset.quantityStep)));
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

// Animate only the disclosure height and the glyph, keeping the button stationary.
for (const card of document.querySelectorAll('.variant-disclosure')) {
    const summary = card.querySelector('summary');
    const panel = card.querySelector('.variant-expand');
    let expanded = card.open;
    let animation = null;
    card.classList.add('is-enhanced');
    summary.setAttribute('aria-expanded', String(expanded));
    panel.inert = !expanded;
    summary.addEventListener('click', event => {
        event.preventDefault();
        const from = card.getBoundingClientRect().height;
        animation?.cancel();
        expanded = !expanded;
        card.classList.toggle('is-expanded', expanded);
        summary.setAttribute('aria-expanded', String(expanded));
        panel.inert = !expanded;
        card.open = true;
        const style = getComputedStyle(card);
        const border = parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
        const to = expanded ? card.scrollHeight + border : summary.getBoundingClientRect().height
            + parseFloat(style.paddingTop) + parseFloat(style.paddingBottom) + border;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            card.open = expanded;
            card.classList.remove('is-animating');
            return;
        }
        card.classList.add('is-animating');
        const active = card.animate([{height:from+'px'}, {height:to+'px'}], {
            duration:220, easing:'cubic-bezier(.2,.7,.2,1)',
        });
        animation = active;
        active.onfinish = () => {
            if (animation !== active) return;
            card.open = expanded;
            card.classList.remove('is-animating');
            animation = null;
        };
    });
}

// Match the thread endpoints to actual card centres, including expanded forms.
for (const list of document.querySelectorAll('.variant-list')) {
    const cards = [...list.querySelectorAll('.variant-card')];
    if (cards.length < 2) continue;
    let frame = null;
    const update = () => {
        frame = null;
        const base = list.getBoundingClientRect().top;
        const first = cards[0].getBoundingClientRect();
        const last = cards.at(-1).getBoundingClientRect();
        const top = first.top + first.height/2 - base;
        const height = last.top + last.height/2 - base - top;
        list.style.setProperty('--thread-top', top+'px');
        list.style.setProperty('--thread-height', height+'px');
        list.classList.add('has-thread');
    };
    const observer = new ResizeObserver(() => {
        if (frame === null) frame = requestAnimationFrame(update);
    });
    cards.forEach(card => observer.observe(card));
    update();
}

const staffMenuToggle = document.querySelector('.staff-menu-toggle');
if (staffMenuToggle) {
    const menu = document.querySelector('#staff-menus');
    const phone = matchMedia('(max-width: 640px)');
    const setMenu = collapsed => {
        menu.dataset.collapsed = String(collapsed);
        staffMenuToggle.setAttribute('aria-expanded', String(!collapsed));
    };
    setMenu(phone.matches);
    staffMenuToggle.classList.add('is-ready');
    staffMenuToggle.addEventListener('click', () => setMenu(menu.dataset.collapsed !== 'true'));
    phone.addEventListener('change', event => setMenu(event.matches));
}

if (document.body.dataset.accountReadonly === 'true') {
    for (const form of document.querySelectorAll('main form')) {
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get' || form.hasAttribute('data-allow-readonly')) continue;
        for (const control of form.querySelectorAll('button,input:not([type=hidden]),select,textarea')) control.disabled = true;
        form.classList.add('readonly-form');
        form.addEventListener('submit', event => event.preventDefault());
    }
}

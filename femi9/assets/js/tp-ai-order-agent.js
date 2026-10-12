/**
 * TP "order assistant" chat widget — floating bubble + panel, text input and
 * (where the browser supports it) a mic button using the Web Speech API for
 * voice-to-text. Talks to territory-partner/ai-order-agent.php, which
 * returns matched {product_id, quantity} lines; this widget's only job past
 * that is to set the page's own product-select + qty fields and fire the
 * same change events a human click would, then (per-page config) either
 * click "Add" or leave it for the TP to confirm — all actual validation
 * (price, stock, carton multiples) stays in each page's existing code.
 *
 * Usage (once per page, after the normal product form has rendered):
 *   TpAiOrderAgent.init({
 *     flow: 'po' | 'invoice',
 *     productType: 'napkin' | 'diaper',   // only matters for flow:'po'
 *     productSelectSelector: '#pr_select',
 *     qtyInputSelector: '#po_qty',
 *     onLineFilled: function (productId, qty) { ... }  // e.g. call addPoLine()
 *   });
 */
(function (window, document) {
    'use strict';

    function TpAiOrderAgent() {}

    TpAiOrderAgent.prototype.init = function (config) {
        this.flow = config.flow === 'invoice' ? 'invoice' : 'po';
        this.productType = config.productType === 'diaper' ? 'diaper' : 'napkin';
        this.productSelectSelector = config.productSelectSelector;
        this.qtyInputSelector = config.qtyInputSelector;
        // Optional — a hidden input holding the currently-selected Channel
        // Partner's id (add-purchase-order.php's #preferred_cp_id_input).
        // Read fresh on every send so switching CP mid-chat is picked up,
        // so the assistant only matches/quotes stock for what THIS CP
        // actually has, never the unrestricted company-wide catalog.
        this.cpIdSelector = config.cpIdSelector || null;
        this.onLineFilled = typeof config.onLineFilled === 'function' ? config.onLineFilled : null;
        this.endpoint = config.endpoint || 'ai-order-agent.php';
        this.historyKey = 'tpAiAgentHistory_' + this.flow;

        this.history = this._loadHistory();
        this._injectStyles();
        this._buildDom();
        this._wireEvents();
        this._renderHistory();
    };

    TpAiOrderAgent.prototype._loadHistory = function () {
        try {
            var raw = sessionStorage.getItem(this.historyKey);
            return raw ? JSON.parse(raw) : [];
        } catch (e) { return []; }
    };

    TpAiOrderAgent.prototype._saveHistory = function () {
        try { sessionStorage.setItem(this.historyKey, JSON.stringify(this.history.slice(-20))); } catch (e) {}
    };

    TpAiOrderAgent.prototype._injectStyles = function () {
        if (document.getElementById('tpAiAgentStyles')) return;
        var style = document.createElement('style');
        style.id = 'tpAiAgentStyles';
        style.textContent =
            '.tp-ai-bubble{position:fixed;right:20px;bottom:20px;width:56px;height:56px;border-radius:50%;' +
            'background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;display:flex;align-items:center;' +
            'justify-content:center;box-shadow:0 4px 14px rgba(102,126,234,.4);cursor:pointer;z-index:9998;border:none;}' +
            '.tp-ai-bubble .material-icons{font-size:26px;}' +
            '.tp-ai-panel{position:fixed;right:20px;bottom:88px;width:340px;max-width:calc(100vw - 32px);height:440px;' +
            'max-height:calc(100vh - 140px);background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.18);' +
            'display:none;flex-direction:column;overflow:hidden;z-index:9999;font-family:inherit;}' +
            '.tp-ai-panel.open{display:flex;}' +
            // Phones — kept deliberately short (not a near-full-screen
            // sheet) so the order/invoice table underneath stays partly
            // visible while the TP is chatting, instead of the panel
            // blocking the one place they'd actually see whether a
            // product landed in their order.
            '@media (max-width:480px){' +
            '.tp-ai-bubble{right:14px;bottom:14px;width:50px;height:50px;}' +
            '.tp-ai-bubble .material-icons{font-size:22px;}' +
            // left:10px/right:10px on a fixed element can get computed
            // against a horizontally-overflowed page on some mobile
            // browsers (a wide table elsewhere on the page pushes the
            // document wider than the viewport), visibly shoving this off
            // the right edge — centering from the viewport's own midpoint
            // instead sidesteps that regardless of what else on the page
            // is overflowing.
            '.tp-ai-panel{left:50%;right:auto;transform:translateX(-50%);bottom:70px;width:calc(100vw - 20px);max-width:calc(100vw - 20px);height:46vh;max-height:340px;}' +
            '.tp-ai-foot{box-sizing:border-box;}' +
            '.tp-ai-input{min-width:0;box-sizing:border-box;}' +
            '}' +
            '.tp-ai-head{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:14px 16px;' +
            'display:flex;align-items:center;justify-content:space-between;}' +
            '.tp-ai-head-title{font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px;}' +
            '.tp-ai-close{background:none;border:none;color:#fff;cursor:pointer;display:flex;}' +
            '.tp-ai-body{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px;background:#f8fafc;}' +
            '.tp-ai-msg{max-width:85%;padding:8px 12px;border-radius:12px;font-size:13px;line-height:1.4;white-space:pre-wrap;}' +
            '.tp-ai-msg.user{align-self:flex-end;background:#667eea;color:#fff;border-bottom-right-radius:3px;}' +
            '.tp-ai-msg.assistant{align-self:flex-start;background:#fff;color:#1f2937;border:1px solid #eef0f3;border-bottom-left-radius:3px;}' +
            '.tp-ai-msg.lines{font-size:12px;color:#4b5563;}' +
            '.tp-ai-msg.lines ul{margin:4px 0 0;padding-left:16px;}' +
            '.tp-ai-foot{border-top:1px solid #eef0f3;padding:8px;display:flex;gap:6px;align-items:center;background:#fff;}' +
            '.tp-ai-input{flex:1;border:1px solid #dde1ea;border-radius:20px;padding:8px 14px;font-size:13px;outline:none;}' +
            '.tp-ai-input:focus{border-color:#667eea;}' +
            '.tp-ai-mic,.tp-ai-send{border:none;background:#f3f4f6;color:#4b5563;width:34px;height:34px;border-radius:50%;' +
            'display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;}' +
            '.tp-ai-send{background:#667eea;color:#fff;}' +
            '.tp-ai-mic.listening{background:#ef4444;color:#fff;}' +
            '.tp-ai-typing{align-self:flex-start;font-size:12px;color:#9ca3af;padding:0 4px;}' +
            '.tp-ai-options{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start;max-width:95%;}' +
            '.tp-ai-option-btn{border:1.5px solid #667eea;background:#fff;color:#667eea;font-size:12.5px;font-weight:600;' +
            'padding:7px 13px;border-radius:16px;cursor:pointer;white-space:nowrap;transition:background .15s,color .15s;}' +
            '.tp-ai-option-btn:hover,.tp-ai-option-btn:active{background:#667eea;color:#fff;}';
        document.head.appendChild(style);
    };

    TpAiOrderAgent.prototype._buildDom = function () {
        var self = this;

        var bubble = document.createElement('button');
        bubble.type = 'button';
        bubble.className = 'tp-ai-bubble';
        bubble.title = 'Order Assistant';
        bubble.innerHTML = '<i class="material-icons">smart_toy</i>';
        document.body.appendChild(bubble);
        this.bubble = bubble;

        var panel = document.createElement('div');
        panel.className = 'tp-ai-panel';
        panel.innerHTML =
            '<div class="tp-ai-head">' +
            '  <div class="tp-ai-head-title"><i class="material-icons" style="font-size:18px;">smart_toy</i> Order Assistant</div>' +
            '  <button type="button" class="tp-ai-close"><i class="material-icons" style="font-size:18px;">close</i></button>' +
            '</div>' +
            '<div class="tp-ai-body"></div>' +
            '<div class="tp-ai-foot">' +
            '  <button type="button" class="tp-ai-mic" title="Speak"><i class="material-icons" style="font-size:18px;">mic</i></button>' +
            '  <input type="text" class="tp-ai-input" placeholder="Type what you need…">' +
            '  <button type="button" class="tp-ai-send" title="Send"><i class="material-icons" style="font-size:18px;">send</i></button>' +
            '</div>';
        document.body.appendChild(panel);
        this.panel = panel;
        this.body = panel.querySelector('.tp-ai-body');
        this.input = panel.querySelector('.tp-ai-input');
        this.micBtn = panel.querySelector('.tp-ai-mic');
        this.sendBtn = panel.querySelector('.tp-ai-send');
        this.closeBtn = panel.querySelector('.tp-ai-close');

        if (this.history.length === 0) {
            this._appendMessage('assistant', this.flow === 'invoice'
                ? "Hi! Tell me what you're invoicing — e.g. \"2 packs of 330mm XL 6 pcs\"."
                : "Hi! Tell me what you'd like to order — e.g. \"3 cartons of 330mm XL 6 pcs\".");
        }
    };

    TpAiOrderAgent.prototype._wireEvents = function () {
        var self = this;
        this.bubble.addEventListener('click', function () { self.panel.classList.add('open'); self.input.focus(); });
        this.closeBtn.addEventListener('click', function () { self.panel.classList.remove('open'); });
        this.sendBtn.addEventListener('click', function () { self._send(); });
        this.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); self._send(); }
        });
        this._wireMic();
    };

    TpAiOrderAgent.prototype._wireMic = function () {
        var self = this;
        var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            this.micBtn.style.display = 'none';
            return;
        }
        var recognition = new SpeechRecognition();
        recognition.lang = 'en-IN';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.onresult = function (e) {
            var transcript = e.results[0][0].transcript;
            self.input.value = transcript;
            self._send();
        };
        recognition.onend = function () { self.micBtn.classList.remove('listening'); };
        recognition.onerror = function () { self.micBtn.classList.remove('listening'); };

        this.micBtn.addEventListener('click', function () {
            if (self.micBtn.classList.contains('listening')) {
                recognition.stop();
                return;
            }
            self.micBtn.classList.add('listening');
            try { recognition.start(); } catch (e) { self.micBtn.classList.remove('listening'); }
        });
    };

    TpAiOrderAgent.prototype._appendMessage = function (role, text) {
        var div = document.createElement('div');
        div.className = 'tp-ai-msg ' + role;
        div.textContent = text;
        this.body.appendChild(div);
        this.body.scrollTop = this.body.scrollHeight;
    };

    TpAiOrderAgent.prototype._appendLines = function (lines) {
        if (!lines || !lines.length) return;
        var div = document.createElement('div');
        div.className = 'tp-ai-msg lines assistant';
        var html = '<ul>';
        lines.forEach(function (l) { html += '<li>' + l.product_name + ' &times; ' + l.quantity + '</li>'; });
        html += '</ul>';
        div.innerHTML = html;
        this.body.appendChild(div);
        this.body.scrollTop = this.body.scrollHeight;
    };

    // Tappable choices for a closed-choice question (e.g. "DW-9 PCS" vs
    // "SW-9 PCS") — tapping one sends that exact text as the TP's next
    // message through the normal _send() pipeline, same as if they'd typed
    // or spoken it, so conversation history/context carries over correctly.
    TpAiOrderAgent.prototype._appendOptions = function (options) {
        var self = this;
        if (!options || !options.length) return;
        var wrap = document.createElement('div');
        wrap.className = 'tp-ai-options';
        options.forEach(function (opt) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tp-ai-option-btn';
            btn.textContent = opt;
            btn.addEventListener('click', function () {
                wrap.remove();
                self.input.value = opt;
                self._send();
            });
            wrap.appendChild(btn);
        });
        this.body.appendChild(wrap);
        this.body.scrollTop = this.body.scrollHeight;
    };

    TpAiOrderAgent.prototype._renderHistory = function () {
        var self = this;
        this.history.forEach(function (turn) { self._appendMessage(turn.role, turn.text); });
    };

    TpAiOrderAgent.prototype._send = function () {
        var self = this;
        var message = this.input.value.trim();
        if (!message) return;
        this.input.value = '';
        this.input.disabled = true;
        this.sendBtn.disabled = true;

        this._appendMessage('user', message);
        this.history.push({ role: 'user', text: message });
        this._saveHistory();

        var typing = document.createElement('div');
        typing.className = 'tp-ai-typing';
        typing.textContent = 'Thinking…';
        this.body.appendChild(typing);
        this.body.scrollTop = this.body.scrollHeight;

        fetch(this.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message: message,
                flow: this.flow,
                product_type: this.productType,
                cp_id: this._currentCpId(),
                history: this.history.slice(0, -1),
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            typing.remove();
            self.input.disabled = false;
            self.sendBtn.disabled = false;

            var reply = (data && data.reply) ? data.reply : "Sorry, something went wrong — please try again.";
            self._appendMessage('assistant', reply);
            self.history.push({ role: 'assistant', text: reply });
            self._saveHistory();

            if (data && data.success && data.lines && data.lines.length) {
                self._appendLines(data.lines);
                data.lines.forEach(function (line) { self._fillLine(line.product_id, line.quantity); });
            } else if (data && data.success && data.options && data.options.length) {
                self._appendOptions(data.options);
            }
        })
        .catch(function () {
            typing.remove();
            self.input.disabled = false;
            self.sendBtn.disabled = false;
            self._appendMessage('assistant', "Sorry, I couldn't reach the assistant — please try again.");
        });
    };

    TpAiOrderAgent.prototype._currentCpId = function () {
        if (!this.cpIdSelector) return null;
        var el = document.querySelector(this.cpIdSelector);
        var val = el ? parseInt(el.value, 10) : 0;
        return val > 0 ? val : null;
    };

    TpAiOrderAgent.prototype._fillLine = function (productId, qty) {
        var select = document.querySelector(this.productSelectSelector);
        var qtyInput = document.querySelector(this.qtyInputSelector);
        if (!select || !qtyInput) return;

        select.value = String(productId);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        // Select2-driven selects (jQuery) need their own change trigger too —
        // a plain DOM 'change' event doesn't update select2's rendered label.
        if (window.jQuery) { window.jQuery(select).trigger('change.select2').trigger('change'); }

        qtyInput.value = String(qty);
        qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
        qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
        qtyInput.dispatchEvent(new Event('keyup', { bubbles: true }));

        // onLineFilled can report back whether it actually landed in the
        // order — it may silently fail past this point (carton-multiple
        // rule, stock cap, a native alert() the TP dismissed without
        // noticing) even though the AI's own reply already claimed
        // success. This is the one place that checks the real outcome and
        // tells the TP plainly, instead of them finding out only by
        // scrolling down to the order table themselves — the main reason
        // this matters more on a phone, where that table is off-screen.
        if (this.onLineFilled) {
            var result = this.onLineFilled(productId, qty);
            if (result && result.ok === false) {
                this._appendMessage('assistant', '⚠️ ' + (result.reason || 'Could not add this to your order — please check and add it manually.'));
            } else if (result && result.ok === true) {
                this._appendMessage('assistant', '✅ Confirmed in your order list below.');
            }
        }
    };

    window.TpAiOrderAgent = new TpAiOrderAgent();
})(window, document);

(function () {
    'use strict';

    // 1. Prevent accidental double submits: the button is disabled as soon as the
    //    form is sent. (The server ALSO protects against duplicates, see the
    //    submission_token idempotency key; this is only a convenience.)
    function resetForms() {
        document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
            form.dataset.submitted = '0';
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = false;
            });
        });
    }

    document.querySelectorAll('form[data-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.submitted === '1') {
                event.preventDefault();
                return;
            }
            form.dataset.submitted = '1';
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
            });
        });
    });

    // Coming back with the Back button must not leave a disabled button behind.
    window.addEventListener('pageshow', resetForms);

    // 2. "Copy number" button on the result pages.
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var source = document.querySelector(button.getAttribute('data-copy'));
            if (!source) { return; }
            var text = source.textContent.trim();
            var done = function () {
                var original = button.textContent;
                button.textContent = 'Copied!';
                setTimeout(function () { button.textContent = original; }, 1500);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done);
                return;
            }

            // Fallback for plain http:// on a LAN address.
            var area = document.createElement('textarea');
            area.value = text;
            document.body.appendChild(area);
            area.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
            document.body.removeChild(area);
        });
    });

    // 3. Filter bar date fields: type the date (YYYY-MM-DD, or DD/MM/YYYY) or use the calendar button.
    document.querySelectorAll('[data-date-input]').forEach(function (wrap) {
        var text = wrap.querySelector('input[type="text"]');
        var native = wrap.querySelector('.date-native');
        var button = wrap.querySelector('[data-date-picker]');
        if (!text || !native || !button) { return; }

        function toIso(value) {
            value = value.trim();
            var m = /^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/.exec(value);
            if (m) { return m[1] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[3]).slice(-2); }
            m = /^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/.exec(value);
            if (m) { return m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2); }
            return null;
        }

        // Tidy a typed date when leaving the field; anything else is left for the server to report.
        text.addEventListener('blur', function () {
            var iso = toIso(text.value);
            if (iso) { text.value = iso; }
        });

        button.addEventListener('click', function () {
            native.value = toIso(text.value) || '';
            try {
                native.showPicker();
            } catch (e) {
                native.style.pointerEvents = 'auto';
                native.focus();
                native.click();
                native.style.pointerEvents = '';
            }
        });

        native.addEventListener('change', function () {
            text.value = native.value;
            text.focus();
        });
    });
})();

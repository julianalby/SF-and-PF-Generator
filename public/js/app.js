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
})();

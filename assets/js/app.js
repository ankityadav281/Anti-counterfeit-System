(function () {
    const copyButtons = document.querySelectorAll('[data-copy]');

    copyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            navigator.clipboard.writeText(button.getAttribute('data-copy'));
            const original = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check me-1"></i> Copied';
            setTimeout(function () {
                button.innerHTML = original;
            }, 1400);
        });
    });

    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.getAttribute('data-toggle-password'));
            const icon = button.querySelector('i');

            if (!input) {
                return;
            }

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

            if (icon) {
                icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
            }
        });
    });

    document.querySelectorAll('[data-google-auth]').forEach(function (button) {
        button.addEventListener('click', function () {
            alert('Google sign-in needs Google OAuth Client ID setup before it can work on localhost or hosting.');
        });
    });

    const forms = document.querySelectorAll('form[data-autosave], form');
    forms.forEach(function (form, index) {
        const key = 'autosave:' + location.pathname + ':' + index;
        if (form.method && form.method.toLowerCase() === 'post') {
            const saved = localStorage.getItem(key);
            if (saved && !form.dataset.restored) {
                try {
                    const values = JSON.parse(saved);
                    Object.keys(values).forEach(function (name) {
                        const field = form.elements[name];
                        if (field && field.type !== 'hidden' && field.type !== 'password' && field.type !== 'file') {
                            field.value = values[name];
                        }
                    });
                    form.dataset.restored = 'true';
                } catch (error) {}
            }
            form.addEventListener('input', function () {
                const values = {};
                Array.from(form.elements).forEach(function (field) {
                    if (field.name && field.type !== 'hidden' && field.type !== 'password' && field.type !== 'file') {
                        values[field.name] = field.value;
                    }
                });
                localStorage.setItem(key, JSON.stringify(values));
            });
            form.addEventListener('submit', function () {
                localStorage.removeItem(key);
            });
        }
    });

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            const search = document.querySelector('input[type="search"], input[name="q"]');
            if (search) {
                event.preventDefault();
                search.focus();
            }
        }
    });

    let idleWarning;
    let idleLogout;
    function resetIdleTimers() {
        clearTimeout(idleWarning);
        clearTimeout(idleLogout);
        idleWarning = setTimeout(function () {
            if (document.body.dataset.sessionWarned !== 'true') {
                document.body.dataset.sessionWarned = 'true';
                alert('Your session has been idle for a while. You will be logged out soon if there is no activity.');
            }
        }, 20 * 60 * 1000);
        idleLogout = setTimeout(function () {
            window.location.href = window.APP_LOGOUT_URL || 'auth/logout.php';
        }, 30 * 60 * 1000);
    }
    ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (eventName) {
        document.addEventListener(eventName, function () {
            document.body.dataset.sessionWarned = 'false';
            resetIdleTimers();
        }, { passive: true });
    });
    resetIdleTimers();
})();

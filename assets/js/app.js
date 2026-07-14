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

    function sendGoogleLogin(data) {
        const originalButtons = document.querySelectorAll('[data-google-auth]');
        originalButtons.forEach(btn => {
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Connecting...';
        });

        fetch(window.APP_CONFIG.googleLoginUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.redirect) {
                window.location.href = res.redirect;
            } else {
                alert(res.error || 'Google Authentication failed.');
                originalButtons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = btn.dataset.originalHtml || '<i class="fab fa-google me-2"></i>Continue with Google';
                });
            }
        })
        .catch(err => {
            console.error(err);
            alert('A connection error occurred. Please verify database connection and configuration.');
            originalButtons.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml || '<i class="fab fa-google me-2"></i>Continue with Google';
            });
        });
    }

    document.querySelectorAll('[data-google-auth]').forEach(function (button) {
        button.addEventListener('click', function () {
            const clientID = window.APP_CONFIG.googleClientId;
            
            if (clientID && clientID.trim() !== "") {
                // Real Google login using GSI OAuth 2.0 flow
                if (window.google && window.google.accounts && window.google.accounts.oauth2) {
                    let selectedRole = 'customer';
                    const activeRoleRadio = document.querySelector('input[name="account_type"]:checked');
                    if (activeRoleRadio) {
                        selectedRole = activeRoleRadio.value;
                    }
                    
                    const tokenClient = google.accounts.oauth2.initTokenClient({
                        client_id: clientID,
                        scope: 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
                        callback: function (tokenResponse) {
                            if (tokenResponse && tokenResponse.access_token) {
                                sendGoogleLogin({
                                    access_token: tokenResponse.access_token,
                                    role: selectedRole
                                });
                            }
                        }
                    });
                    tokenClient.requestAccessToken();
                } else {
                    alert('Google Sign-In API library failed to load. Please check your internet connection.');
                }
            } else {
                // If developer mode is on, show sandbox simulator
                if (window.APP_CONFIG.appDebug) {
                    const modalId = 'googleSandboxModal';
                    let modalEl = document.getElementById(modalId);
                    
                    if (!modalEl) {
                        let selectedRole = 'customer';
                        const activeRoleRadio = document.querySelector('input[name="account_type"]:checked');
                        if (activeRoleRadio) {
                            selectedRole = activeRoleRadio.value;
                        }

                        const modalHTML = `
                        <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content shadow-lg border-0" style="border-radius: 12px;">
                                    <div class="modal-header bg-dark text-white" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                                        <h5 class="modal-title" id="${modalId}Label">
                                            <i class="fab fa-google me-2 text-warning"></i>Google Auth Sandbox Simulator
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4 text-start">
                                        <div class="alert alert-info py-2 px-3 mb-3" style="font-size: 0.88rem;">
                                            <i class="fas fa-info-circle me-1"></i> <strong>Developer Notice:</strong> <code>GOOGLE_CLIENT_ID</code> is not configured. You can simulate Google Sign-in locally using this sandbox.
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="sandbox-email" class="form-label fw-semibold">Simulated Google Profile</label>
                                            <select class="form-select" id="sandbox-email">
                                                <option value="test.manufacturer@google.com">test.manufacturer@google.com (Manufacturer)</option>
                                                <option value="test.customer@google.com" selected>test.customer@google.com (Customer)</option>
                                                <option value="test.retailer@google.com">test.retailer@google.com (Retailer)</option>
                                                <option value="test.auditor@google.com">test.auditor@google.com (Auditor)</option>
                                                <option value="custom">-- Use Custom Profile --</option>
                                            </select>
                                        </div>

                                        <div id="sandbox-custom-fields" class="d-none border p-3 mb-3 bg-light rounded">
                                            <div class="mb-2">
                                                <label for="sandbox-custom-name" class="form-label" style="font-size: 0.85rem;">Full Name</label>
                                                <input type="text" class="form-control form-control-sm" id="sandbox-custom-name" placeholder="John Doe" value="Developer Test">
                                            </div>
                                            <div>
                                                <label for="sandbox-custom-email" class="form-label" style="font-size: 0.85rem;">Email Address</label>
                                                <input type="email" class="form-control form-control-sm" id="sandbox-custom-email" placeholder="john.doe@gmail.com">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="sandbox-role" class="form-label fw-semibold">Sign in / Register As</label>
                                            <select class="form-select" id="sandbox-role">
                                                <option value="manufacturer" ${selectedRole === 'manufacturer' ? 'selected' : ''}>Manufacturer</option>
                                                <option value="customer" ${selectedRole === 'customer' ? 'selected' : ''}>Customer</option>
                                                <option value="retailer" ${selectedRole === 'retailer' ? 'selected' : ''}>Retailer</option>
                                                <option value="auditor" ${selectedRole === 'auditor' ? 'selected' : ''}>Auditor</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 p-3 bg-light" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-primary px-4" id="btn-simulate-google">
                                            <i class="fas fa-plug me-2"></i>Authenticate Sandbox
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                        document.body.insertAdjacentHTML('beforeend', modalHTML);
                        modalEl = document.getElementById(modalId);
                        
                        const selectEmail = document.getElementById('sandbox-email');
                        const customFields = document.getElementById('sandbox-custom-fields');
                        selectEmail.addEventListener('change', function() {
                            if (selectEmail.value === 'custom') {
                                customFields.classList.remove('d-none');
                            } else {
                                customFields.classList.add('d-none');
                            }
                            
                            if (selectEmail.value !== 'custom') {
                                const roleSelect = document.getElementById('sandbox-role');
                                if (selectEmail.value.includes('manufacturer')) roleSelect.value = 'manufacturer';
                                else if (selectEmail.value.includes('customer')) roleSelect.value = 'customer';
                                else if (selectEmail.value.includes('retailer')) roleSelect.value = 'retailer';
                                else if (selectEmail.value.includes('auditor')) roleSelect.value = 'auditor';
                            }
                        });
                    }
                    
                    const bsModal = new bootstrap.Modal(modalEl);
                    bsModal.show();

                    document.getElementById('btn-simulate-google').onclick = function() {
                        bsModal.hide();
                        
                        const selectEmail = document.getElementById('sandbox-email').value;
                        let email = selectEmail;
                        let name = selectEmail.split('@')[0].replace('.', ' ');
                        name = name.charAt(0).toUpperCase() + name.slice(1);
                        
                        if (selectEmail === 'custom') {
                            email = document.getElementById('sandbox-custom-email').value.trim();
                            name = document.getElementById('sandbox-custom-name').value.trim() || 'Mock User';
                            if (!email) {
                                alert('Please enter a valid mock email address.');
                                return;
                            }
                        }
                        
                        const role = document.getElementById('sandbox-role').value;
                        
                        sendGoogleLogin({
                            is_mock: true,
                            mock_email: email,
                            mock_name: name,
                            role: role
                        });
                    };
                } else {
                    alert('Google OAuth Client ID must be configured in your .env file.');
                }
            }
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

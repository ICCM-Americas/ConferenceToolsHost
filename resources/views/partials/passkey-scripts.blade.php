{{-- Vanilla WebAuthn client for the laravel/passkeys endpoints. Best-effort for
     real browsers; no build step required. --}}
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    function b64urlToBuf(value) {
        const pad = '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = (value + pad).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        const buf = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) buf[i] = raw.charCodeAt(i);
        return buf.buffer;
    }

    function bufToB64url(buf) {
        const bytes = new Uint8Array(buf);
        let str = '';
        for (let i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    async function getJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error('Request failed');
        return res.json();
    }

    async function postJson(url, body, method = 'POST') {
        return fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
    }

    function serializeAssertion(cred) {
        return {
            id: cred.id,
            rawId: bufToB64url(cred.rawId),
            type: cred.type,
            response: {
                clientDataJSON: bufToB64url(cred.response.clientDataJSON),
                authenticatorData: bufToB64url(cred.response.authenticatorData),
                signature: bufToB64url(cred.response.signature),
                userHandle: cred.response.userHandle ? bufToB64url(cred.response.userHandle) : null,
            },
            clientExtensionResults: cred.getClientExtensionResults ? cred.getClientExtensionResults() : {},
        };
    }

    function serializeAttestation(cred) {
        return {
            id: cred.id,
            rawId: bufToB64url(cred.rawId),
            type: cred.type,
            response: {
                clientDataJSON: bufToB64url(cred.response.clientDataJSON),
                attestationObject: bufToB64url(cred.response.attestationObject),
            },
            clientExtensionResults: cred.getClientExtensionResults ? cred.getClientExtensionResults() : {},
        };
    }

    window.passkeyLogin = async function (optionsUrl, loginUrl, redirectUrl) {
        try {
            const { options } = await getJson(optionsUrl);
            options.challenge = b64urlToBuf(options.challenge);
            if (Array.isArray(options.allowCredentials)) {
                options.allowCredentials = options.allowCredentials.map(c => ({ ...c, id: b64urlToBuf(c.id) }));
            }
            const cred = await navigator.credentials.get({ publicKey: options });
            const res = await postJson(loginUrl, { credential: serializeAssertion(cred) });
            if (res.ok) { window.location = redirectUrl; }
            else { alert(@json(__('auth_ui.passkey_login_failed'))); }
        } catch (e) { alert(@json(__('auth_ui.passkey_login_canceled'))); }
    };

    window.passkeyRegister = async function (optionsUrl, storeUrl, name, redirectUrl) {
        try {
            const { options } = await getJson(optionsUrl);
            options.challenge = b64urlToBuf(options.challenge);
            options.user.id = b64urlToBuf(options.user.id);
            if (Array.isArray(options.excludeCredentials)) {
                options.excludeCredentials = options.excludeCredentials.map(c => ({ ...c, id: b64urlToBuf(c.id) }));
            }
            const cred = await navigator.credentials.create({ publicKey: options });
            const res = await postJson(storeUrl, { name: name, credential: serializeAttestation(cred) });
            if (res.ok) { window.location = redirectUrl; }
            else { alert(@json(__('auth_ui.passkey_register_failed'))); }
        } catch (e) { alert(@json(__('auth_ui.passkey_register_canceled'))); }
    };

    document.addEventListener('DOMContentLoaded', function () {
        const loginBtn = document.getElementById('passkey-login-btn');
        if (loginBtn) {
            loginBtn.addEventListener('click', function () {
                window.passkeyLogin(loginBtn.dataset.optionsUrl, loginBtn.dataset.loginUrl, loginBtn.dataset.redirectUrl);
            });
        }

        const registerBtn = document.getElementById('passkey-register-btn');
        if (registerBtn) {
            registerBtn.addEventListener('click', function () {
                const nameInput = document.getElementById('passkey_name');
                window.passkeyRegister(
                    registerBtn.dataset.optionsUrl,
                    registerBtn.dataset.storeUrl,
                    (nameInput && nameInput.value) || 'Passkey',
                    registerBtn.dataset.redirectUrl
                );
            });
        }
    });
})();
</script>

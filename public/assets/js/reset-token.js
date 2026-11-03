'use strict';
// Keep the reset secret in the URL fragment; it is not transmitted in HTTP requests or access logs.
(function () {
    var form = document.getElementById('reset-password-form');
    if (!form) return;
    var value = new URLSearchParams(window.location.hash.slice(1)).get('token') || '';
    var hidden = document.getElementById('reset-token');
    if (/^[0-9a-f]{64}$/.test(value)) hidden.value = value;
    // Remove the fragment after filling the form, without another HTTP request.
    if (window.location.hash) history.replaceState(null, '', window.location.pathname + window.location.search);
    var info = document.getElementById('reset-token-info');
    if (info && !hidden.value) info.textContent = 'Open your original password-reset email link to continue.';
})();

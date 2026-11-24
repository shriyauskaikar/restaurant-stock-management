/* =====================================================================
   assets/js/login.js
   ---------------------------------------------------------------------
   The little bit of JavaScript the login page needs:
     1. the show / hide password button
     2. simple form validation before the form is sent
   Plain JavaScript, no libraries, so it is easy to explain in a viva.
   ===================================================================== */

(function () {
    'use strict';

    /* ---------------------------------------------------------------
       1. SHOW / HIDE THE PASSWORD
       ---------------------------------------------------------------
       Clicking the eye icon swaps the input between type="password"
       (hidden) and type="text" (visible).
       --------------------------------------------------------------- */
    function initTogglePassword() {
        var buttons = document.querySelectorAll('.toggle-password');
        if (!buttons.length) return;

        buttons.forEach(function (button) {
            // Most buttons target #password; the confirm field names
            // its input explicitly with a data attribute.
            var targetId = button.getAttribute('data-toggle-target') || 'password';
            var input = document.getElementById(targetId);
            if (!input) return;

            button.addEventListener('click', function () {
                var showing = input.getAttribute('type') === 'text';

                if (showing) {
                    input.setAttribute('type', 'password');
                } else {
                    input.setAttribute('type', 'text');
                }

                // The class swaps the two eye icons, and aria-pressed tells
                // screen readers whether the password is visible right now.
                button.classList.toggle('is-visible', !showing);
                button.setAttribute('aria-pressed', showing ? 'false' : 'true');
                button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');

                // Put the cursor back in the field so typing continues.
                input.focus();
            });
        });
    }

    /* ---------------------------------------------------------------
       2. FORM VALIDATION
       ---------------------------------------------------------------
       This only checks the obvious things (empty fields, and that the
       email looks like an email). The server checks everything again,
       because a browser check can always be bypassed.
       --------------------------------------------------------------- */
    function initValidation() {
        var form = document.querySelector('.login-form');
        if (!form) return;

        var loginInput    = document.getElementById('login');
        var passwordInput = document.getElementById('password');

        form.addEventListener('submit', function (event) {
            var login    = loginInput.value.trim();
            var password = passwordInput.value;

            if (login === '') {
                event.preventDefault();
                showError(loginInput, 'Please enter your username or email address.');
                return;
            }

            // If what was typed looks like an email address, check it
            // properly. Otherwise it is a username, which can be
            // anything, so we only check that it is not empty.
            if (login.indexOf('@') !== -1 &&
                !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(login)) {
                event.preventDefault();
                showError(loginInput, 'That email address does not look right.');
                return;
            }

            if (password === '') {
                event.preventDefault();
                showError(passwordInput, 'Please enter your password.');
                return;
            }

            clearError();
        });

        // Remove the error as soon as the user starts typing again.
        [loginInput, passwordInput].forEach(function (input) {
            if (!input) return;
            input.addEventListener('input', function () {
                clearError(input);
            });
        });
    }

    /* --- Show a small red message under a field ------------------- */
    function showError(input, message) {
        clearError();

        var box = document.createElement('p');
        box.className = 'field-error';
        box.textContent = message;

        input.parentElement.parentElement.appendChild(box);  // .field
        input.style.borderColor = 'var(--critical)';
        input.focus();
    }

    /* --- Remove any error message --------------------------------- */
    function clearError(input) {
        var fields = document.querySelectorAll('.field');
        fields.forEach(function (field) {
            var error = field.querySelector('.field-error');
            if (error) error.remove();
        });

        document.querySelectorAll('.input-wrap input').forEach(function (field) {
            field.style.borderColor = '';
        });
    }

    /* ---------------------------------------------------------------
       3. SIGN-UP FORM VALIDATION
       The server checks everything again; this just gives instant
       feedback while the user is typing.
       --------------------------------------------------------------- */
    function initSignupValidation() {
        var form = document.querySelector('.auth-form');
        if (!form) return;

        var username = document.getElementById('username');
        var email    = document.getElementById('email');
        var password = document.getElementById('password');
        var confirm  = document.getElementById('confirm_password');

        form.addEventListener('submit', function (event) {
            if (username.value.trim() === '') {
                event.preventDefault();
                showError(username, 'Please choose a username.');
                return;
            }
            if (!/^[A-Za-z0-9_.-]{3,50}$/.test(username.value.trim())) {
                event.preventDefault();
                showError(username, 'Use 3-50 letters, numbers, dot, dash or underscore.');
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                event.preventDefault();
                showError(email, 'Please enter a valid email address.');
                return;
            }
            if (password.value.length < 8) {
                event.preventDefault();
                showError(password, 'Password must be at least 8 characters.');
                return;
            }
            if (password.value !== confirm.value) {
                event.preventDefault();
                showError(confirm, 'The two passwords do not match.');
                return;
            }
            clearError();
        });

        [username, email, password, confirm].forEach(function (input) {
            if (!input) return;
            input.addEventListener('input', function () { clearError(input); });
        });
    }

    /* ---------------------------------------------------------------
       START
       --------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        initTogglePassword();
        initValidation();
        initSignupValidation();
    });
})();

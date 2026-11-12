/* =====================================================================
   assets/js/app.js
   ---------------------------------------------------------------------
   The small amount of JavaScript the project needs:
     - the mobile sidebar toggle
     - the search / filter box on each list page
     - the add / edit modal windows
     - the live total-cost calculation on the purchases page
   Plain JavaScript, no libraries, so it is easy to explain in a viva.
   ===================================================================== */

(function () {
    'use strict';

    /* ---------------------------------------------------------------
       1. MOBILE SIDEBAR
       --------------------------------------------------------------- */
    var toggle = document.getElementById('sidebarToggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var open = document.body.classList.toggle('nav-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        // Clicking the dark overlay closes the sidebar
        document.addEventListener('click', function (event) {
            if (!document.body.classList.contains('nav-open')) return;
            if (event.target.closest('.sidebar') || event.target.closest('.sidebar-toggle')) return;
            document.body.classList.remove('nav-open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    }

    /* ---------------------------------------------------------------
       2. SEARCH / FILTER BOX
       Filters the rows of a table as the user types.
       --------------------------------------------------------------- */
    function initSearch() {
        var input = document.getElementById('tableSearch');
        if (!input) return;

        var table   = document.querySelector(input.getAttribute('data-target'));
        if (!table) return;

        var rows    = table.querySelectorAll('tbody tr');
        var empty   = document.getElementById('noResults');
        var counter = document.getElementById('searchCount');

        input.addEventListener('input', function () {
            var term = input.value.trim().toLowerCase();
            var shown = 0;

            Array.prototype.forEach.call(rows, function (row) {
                var match = term === '' || row.textContent.toLowerCase().indexOf(term) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) shown++;
            });

            if (empty) {
                empty.style.display = shown === 0 ? '' : 'none';
            }
            if (counter) {
                counter.textContent = term === ''
                    ? shown + ' record(s)'
                    : shown + ' of ' + rows.length + ' record(s)';
            }
        });
    }

    /* ---------------------------------------------------------------
       3. MODAL WINDOWS (add / edit forms)
       --------------------------------------------------------------- */
    window.openModal = function (id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        // Focus the first input so the user can type straight away
        var first = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (first) setTimeout(function () { first.focus(); }, 50);
    };

    window.closeModal = function (id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    };

    function initModals() {
        // Any element with data-modal="id" opens that modal when clicked
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-modal]');
            if (trigger) {
                event.preventDefault();
                window.openModal(trigger.getAttribute('data-modal'));
                return;
            }

            // Clicking the backdrop (outside the white panel) closes it
            if (event.target.classList.contains('modal')) {
                window.closeModal(event.target.id);
                return;
            }

            // The X button
            var closer = event.target.closest('[data-close-modal]');
            if (closer) {
                event.preventDefault();
                window.closeModal(closer.getAttribute('data-close-modal'));
            }
        });

        // The Escape key closes an open modal
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                var open = document.querySelector('.modal.is-open');
                if (open) window.closeModal(open.id);
            }
        });
    }

    /* ---------------------------------------------------------------
       4. PURCHASE FORM  ->  live total cost
       total cost = quantity purchased x price per unit
       The price is read from the selected ingredient.
       --------------------------------------------------------------- */
    function initPurchaseCalculator() {
        var qtyInput    = document.getElementById('quantity_purchased');
        var ingredient  = document.getElementById('ingredient_id');
        var readout     = document.getElementById('totalCost');
        if (!qtyInput || !ingredient || !readout) return;

        // Price per unit for each ingredient (filled in by PHP)
        var prices = {};
        Array.prototype.forEach.call(ingredient.options, function (option) {
            if (option.value !== '') prices[option.value] = parseFloat(option.getAttribute('data-price') || '0');
        });

        function update() {
            var price = prices[ingredient.value] || 0;
            var qty   = parseFloat(qtyInput.value || '0');
            var total = isNaN(qty) ? 0 : qty * price;

            readout.textContent = '₹' + total.toFixed(2);

            // Show the price per unit so the maths is easy to follow
            var priceNote = document.getElementById('priceNote');
            if (priceNote) {
                priceNote.textContent = ingredient.value === ''
                    ? 'Choose an ingredient to see its price per unit.'
                    : 'Price per unit: ₹' + price.toFixed(2);
            }
        }

        qtyInput.addEventListener('input', update);
        ingredient.addEventListener('change', update);
        update();
    }

    /* ---------------------------------------------------------------
       5. DELETE CONFIRMATION
       Stops a purchase or supplier being deleted by accident.
       --------------------------------------------------------------- */
    function initConfirm() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form.hasAttribute('data-confirm')) return;

            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    }

    /* ---------------------------------------------------------------
       6. FILL A TABLE ROW INTO THE EDIT FORM
       Used by the suppliers / ingredients pages: clicking the edit
       button copies that row's values into the form fields.
       --------------------------------------------------------------- */
    function initRowEdit() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-fill]');
            if (!button) return;

            var form = document.getElementById(button.getAttribute('data-fill'));
            if (!form) return;

            var row = button.closest('tr');
            if (!row) return;

            // Every data-field-* attribute on the button holds one value
            // for the matching input, e.g. data-field-supplier_name="Fresh Co"
            var fields = {};
            Array.prototype.forEach.call(button.attributes, function (attr) {
                if (attr.name.indexOf('data-field-') === 0) {
                    fields[attr.name.replace('data-field-', '')] = attr.value;
                }
            });

            Object.keys(fields).forEach(function (fieldName) {
                var input = form.querySelector('[name="' + fieldName + '"]');
                if (input) input.value = fields[fieldName];
            });
        });
    }

    /* ---------------------------------------------------------------
       7. UPDATE THE ROW COUNT WHEN A TABLE IS SEARCHED
       --------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        initSearch();
        initModals();
        initPurchaseCalculator();
        initConfirm();
        initRowEdit();
    });
})();

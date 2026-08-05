(function () {
    'use strict';

    var PAGE_SIZE_OPTIONS = [5, 10];
    var DEFAULT_PAGE_SIZE = 10;
    var MIN_ROWS_TO_PAGINATE = 10;
    var MAX_PAGE_BUTTONS = 7;

    function getDataRows(table) {
        var thead = table.tHead;
        var tbody = table.tBodies[0];
        if (!thead || !tbody) return [];

        var headerRow = thead.rows[thead.rows.length - 1];
        var headerCount = headerRow.cells.length;

        return Array.prototype.slice.call(tbody.rows).filter(function (row) {
            return row.cells.length === headerCount;
        });
    }

    function computeVisiblePages(current, total) {
        if (total <= MAX_PAGE_BUTTONS) {
            var all = [];
            for (var i = 1; i <= total; i++) all.push(i);
            return all;
        }

        var pages = [1];
        var start = Math.max(2, current - 1);
        var end = Math.min(total - 1, current + 1);

        if (start > 2) pages.push('…');
        for (var p = start; p <= end; p++) pages.push(p);
        if (end < total - 1) pages.push('…');
        pages.push(total);

        return pages;
    }

    function buildControls(table, state) {
        var wrap = document.createElement('div');
        wrap.className = 'table-pagination';

        var sizeWrap = document.createElement('div');
        sizeWrap.className = 'table-pagination-size';

        var label = document.createElement('label');
        var selectId = 'table-pagination-size-' + state.id;
        label.setAttribute('for', selectId);
        label.textContent = 'Rows per page';

        var select = document.createElement('select');
        select.id = selectId;
        PAGE_SIZE_OPTIONS.forEach(function (size) {
            var opt = document.createElement('option');
            opt.value = String(size);
            opt.textContent = 'View ' + size;
            if (size === state.pageSize) opt.selected = true;
            select.appendChild(opt);
        });
        select.addEventListener('change', function () {
            state.pageSize = parseInt(select.value, 10) || DEFAULT_PAGE_SIZE;
            state.page = 1;
            render();
        });

        sizeWrap.appendChild(label);
        sizeWrap.appendChild(select);

        var status = document.createElement('span');
        status.className = 'table-pagination-status';

        var pagesWrap = document.createElement('div');
        pagesWrap.className = 'table-pagination-pages';
        pagesWrap.setAttribute('role', 'navigation');
        pagesWrap.setAttribute('aria-label', 'Table pages');

        wrap.appendChild(sizeWrap);
        wrap.appendChild(status);
        wrap.appendChild(pagesWrap);

        function pageCount() {
            return Math.max(1, Math.ceil(state.rows.length / state.pageSize));
        }

        function goTo(page) {
            state.page = Math.min(Math.max(1, page), pageCount());
            render();
        }

        function renderPageButtons() {
            pagesWrap.innerHTML = '';
            var total = pageCount();

            var prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'table-pagination-nav';
            prev.textContent = 'Prev';
            prev.disabled = state.page <= 1;
            prev.addEventListener('click', function () { goTo(state.page - 1); });
            pagesWrap.appendChild(prev);

            computeVisiblePages(state.page, total).forEach(function (entry) {
                if (entry === '…') {
                    var ellipsis = document.createElement('span');
                    ellipsis.className = 'table-pagination-ellipsis';
                    ellipsis.textContent = '…';
                    ellipsis.setAttribute('aria-hidden', 'true');
                    pagesWrap.appendChild(ellipsis);
                    return;
                }

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'table-pagination-page';
                btn.textContent = String(entry);
                if (entry === state.page) {
                    btn.classList.add('is-active');
                    btn.setAttribute('aria-current', 'page');
                }
                btn.addEventListener('click', function () { goTo(entry); });
                pagesWrap.appendChild(btn);
            });

            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'table-pagination-nav';
            next.textContent = 'Next';
            next.disabled = state.page >= total;
            next.addEventListener('click', function () { goTo(state.page + 1); });
            pagesWrap.appendChild(next);
        }

        function render() {
            state.rows = getDataRows(table);
            var total = pageCount();
            if (state.page > total) state.page = total;

            var start = (state.page - 1) * state.pageSize;
            var end = start + state.pageSize;

            state.rows.forEach(function (row, i) {
                row.style.display = (i >= start && i < end) ? '' : 'none';
            });

            status.textContent = state.rows.length === 0
                ? ''
                : 'Showing ' + (start + 1) + '–' + Math.min(end, state.rows.length) + ' of ' + state.rows.length;

            renderPageButtons();
            wrap.hidden = state.rows.length <= PAGE_SIZE_OPTIONS[PAGE_SIZE_OPTIONS.length - 1]
                && total <= 1;
        }

        table._paginationRender = render;
        render();

        return wrap;
    }

    var idCounter = 0;

    function initTable(table) {
        if (table._paginationInit) return;

        var rows = getDataRows(table);
        if (rows.length <= MIN_ROWS_TO_PAGINATE) return;

        table._paginationInit = true;
        idCounter += 1;

        var state = { id: idCounter, page: 1, pageSize: DEFAULT_PAGE_SIZE, rows: rows };
        var controls = buildControls(table, state);

        var shell = table.closest('.admin-table-shell, .table-shell') || table.parentElement;
        shell.parentNode.insertBefore(controls, shell.nextSibling);

        // sortable_tables.js re-appends rows in sorted order, which fires
        // childList mutations here — re-render so the visible page reflects
        // the new order instead of the pre-sort row set.
        var tbody = table.tBodies[0];
        if (tbody && window.MutationObserver) {
            var scheduled = false;
            var observer = new MutationObserver(function () {
                if (scheduled) return;
                scheduled = true;
                requestAnimationFrame(function () {
                    scheduled = false;
                    table._paginationRender();
                });
            });
            observer.observe(tbody, { childList: true });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('table.admin-table, table.data-table').forEach(initTable);
    });
})();

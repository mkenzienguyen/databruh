(function () {
    'use strict';

    function getCellSortText(cell) {
        var select = cell.querySelector('select');
        if (select) {
            var opt = select.options[select.selectedIndex];
            return opt ? opt.text.trim() : '';
        }
        var input = cell.querySelector('input');
        if (input) {
            return String(input.value || '').trim();
        }
        return (cell.textContent || '').trim();
    }

    function hasInteractiveControl(cell) {
        return !!cell.querySelector('select, input, button, form');
    }

    function isEmptyValue(text) {
        return text === '' || text === '—';
    }

    // Only numeric if the ENTIRE value reduces to one after stripping
    // decoration, so codes like "VEH-001" correctly stay text.
    function parseNumeric(text) {
        var stripped = text
            .replace(/^[#$]/, '')
            .replace(/,/g, '')
            .replace(/\s*\((reorder|expired)\)\s*$/i, '')
            .replace(/\s*(km|vnd|days?|hrs?|h|%)$/i, '')
            .trim();
        return /^-?\d+(\.\d+)?$/.test(stripped) ? parseFloat(stripped) : null;
    }

    function compareValues(a, b, numeric, direction) {
        var aEmpty = isEmptyValue(a) || (numeric && parseNumeric(a) === null);
        var bEmpty = isEmptyValue(b) || (numeric && parseNumeric(b) === null);

        // Blanks always sink to the bottom regardless of sort direction.
        if (aEmpty && bEmpty) return 0;
        if (aEmpty) return 1;
        if (bEmpty) return -1;

        var cmp = numeric
            ? parseNumeric(a) - parseNumeric(b)
            : a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });

        return direction === 'asc' ? cmp : -cmp;
    }

    function flashRows(rows) {
        rows.forEach(function (row) {
            row.classList.remove('is-just-sorted');
            // Force reflow so the animation restarts on repeated sorts.
            void row.offsetWidth;
            row.classList.add('is-just-sorted');
        });
    }

    function initTable(table) {
        var thead = table.tHead;
        var tbody = table.tBodies[0];
        if (!thead || !tbody) return;

        var headerRow = thead.rows[thead.rows.length - 1];
        var headers = Array.prototype.slice.call(headerRow.cells);

        headers.forEach(function (th, index) {
            var dataRows = Array.prototype.slice.call(tbody.rows).filter(function (row) {
                return row.cells.length === headers.length;
            });

            if (dataRows.length < 2) return;

            var hasInteractive = dataRows.some(function (row) {
                return hasInteractiveControl(row.cells[index]);
            });
            if (hasInteractive) return;

            th.classList.add('is-sortable');
            th.tabIndex = 0;
            th.setAttribute('role', 'button');
            th.setAttribute('aria-sort', 'none');
            th.setAttribute('aria-label', 'Sort by ' + th.textContent.trim());

            var indicator = document.createElement('span');
            indicator.className = 'sort-indicator';
            indicator.setAttribute('aria-hidden', 'true');
            th.appendChild(indicator);

            var direction = null;

            function runSort() {
                direction = direction === 'asc' ? 'desc' : 'asc';

                headers.forEach(function (otherTh) {
                    if (otherTh !== th) {
                        otherTh.classList.remove('is-sorted-asc', 'is-sorted-desc');
                        otherTh.setAttribute('aria-sort', 'none');
                    }
                });
                th.classList.toggle('is-sorted-asc', direction === 'asc');
                th.classList.toggle('is-sorted-desc', direction === 'desc');
                th.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');

                var rows = Array.prototype.slice.call(tbody.rows).filter(function (row) {
                    return row.cells.length === headers.length;
                });
                var values = rows.map(function (row) {
                    return getCellSortText(row.cells[index]);
                });
                var nonEmpty = values.filter(function (v) { return !isEmptyValue(v); });
                var numeric = nonEmpty.length > 0 && nonEmpty.every(function (v) {
                    return parseNumeric(v) !== null;
                });

                var paired = rows.map(function (row, i) {
                    return { row: row, value: values[i] };
                });
                paired.sort(function (a, b) {
                    return compareValues(a.value, b.value, numeric, direction);
                });

                var fragment = document.createDocumentFragment();
                paired.forEach(function (item) {
                    fragment.appendChild(item.row);
                });
                tbody.appendChild(fragment);

                flashRows(paired.map(function (item) { return item.row; }));
            }

            th.addEventListener('click', runSort);
            th.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    runSort();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('table.admin-table, table.data-table').forEach(initTable);
    });
})();

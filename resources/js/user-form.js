/**
 * Users create/edit form: picking a Company fetches that company's
 * departments and designations and replaces the two selects below it.
 *
 * The department/designation selects are rendered server-side with every
 * company's options (so old()/edit prefill has something to select without
 * an extra request) — this only replaces them once a company is chosen or
 * changed, or on first load if a company is already selected (validation
 * redisplay, or editing an existing user).
 */
document.addEventListener('DOMContentLoaded', () => {
    const fieldset = document.getElementById('user-account-fields');
    const companySelect = document.getElementById('user-company-select');
    const departmentSelect = document.getElementById('user-department-select');
    const designationSelect = document.getElementById('user-designation-select');

    if (!fieldset || !companySelect || !departmentSelect || !designationSelect) {
        return;
    }

    const urlTemplate = fieldset.dataset.companyOptionsTemplate;

    function fillSelect(select, items, selectedId) {
        const previousValue = String(selectedId ?? '');

        select.innerHTML = '';

        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = '—';
        select.appendChild(blank);

        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.name;
            if (String(item.id) === previousValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    function loadOptions(companyId, preserveSelection) {
        if (!companyId) {
            fillSelect(departmentSelect, [], null);
            fillSelect(designationSelect, [], null);
            return;
        }

        const departmentKeep = preserveSelection ? departmentSelect.dataset.selected : null;
        const designationKeep = preserveSelection ? designationSelect.dataset.selected : null;

        fetch(urlTemplate.replace('__ID__', companyId), { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((data) => {
                fillSelect(departmentSelect, data.departments, departmentKeep);
                fillSelect(designationSelect, data.designations, designationKeep);
            })
            .catch(() => {
                // Leave the server-rendered (unfiltered) options in place rather
                // than blanking the fields the admin already sees.
            });
    }

    companySelect.addEventListener('change', () => loadOptions(companySelect.value, false));

    // Validation redisplay or editing an existing user: narrow the two lists
    // down to the company already selected, keeping whichever department/
    // designation was already picked.
    if (companySelect.value) {
        loadOptions(companySelect.value, true);
    }
});

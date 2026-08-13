/**
 * Searchable selects.
 *
 * Every single-choice <select> on the page is progressively enhanced into a
 * combobox with a type-to-filter box. The original <select> stays in the DOM
 * (transparent, behind the trigger) so form submission, `required` validation
 * and any existing `change` handlers keep working untouched.
 *
 * Opt out on a per-field basis with `data-no-search`.
 */

const ENHANCED = 'ssEnhanced';

/** Utility classes that position or size the field rather than skin it. */
const LAYOUT_CLASS = /^(w-|min-w-|max-w-|basis-|flex-1$|flex-auto$|grow|shrink|col-span-|col-start-|order-|self-|justify-self-|m[trblxy]?-)/;

let idCounter = 0;
let openInstance = null;

/** Case- and accent-insensitive haystack used for matching. */
function normalize(value) {
    return (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

function shouldEnhance(select) {
    return ! (
        select.multiple ||
        select.size > 1 ||
        select.hasAttribute('data-no-search') ||
        select.closest('[data-no-search]') ||
        select.dataset[ENHANCED]
    );
}

class SearchableSelect {
    constructor(select) {
        this.select = select;
        this.id = `ss-${++idCounter}`;
        this.items = [];
        this.activeIndex = -1;

        this.build();
        this.syncOptions();
        this.syncValue();
        this.bind();
    }

    build() {
        const select = this.select;

        // The field's own utility classes are split in two: the ones that
        // place and size it move to the wrapper, the rest (border, padding,
        // colours) stay on the visible trigger.
        const layout = [];
        const skin = [];
        Array.from(select.classList).forEach((name) => {
            const base = name.includes(':') ? name.split(':').pop() : name;
            (LAYOUT_CLASS.test(base) ? layout : skin).push(name);
        });

        this.wrapper = document.createElement('div');
        this.wrapper.className = ['ss', ...layout].join(' ');

        select.parentNode.insertBefore(this.wrapper, select);
        this.wrapper.appendChild(select);
        select.classList.add('ss-native');

        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.className = [...skin, 'ss-button'].join(' ');
        this.button.setAttribute('role', 'combobox');
        this.button.setAttribute('aria-haspopup', 'listbox');
        this.button.setAttribute('aria-expanded', 'false');
        this.button.disabled = select.disabled;

        const labelledBy = select.getAttribute('aria-labelledby');
        if (labelledBy) {
            this.button.setAttribute('aria-labelledby', labelledBy);
        } else if (select.id) {
            const label = document.querySelector(`label[for="${CSS.escape(select.id)}"]`);
            if (label) {
                if (! label.id) {
                    label.id = `${this.id}-label`;
                }
                this.button.setAttribute('aria-labelledby', label.id);
            }
        }

        this.label = document.createElement('span');
        this.label.className = 'ss-label';
        this.button.appendChild(this.label);

        const chevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        chevron.setAttribute('class', 'ss-chevron');
        chevron.setAttribute('viewBox', '0 0 20 20');
        chevron.setAttribute('fill', 'none');
        chevron.setAttribute('aria-hidden', 'true');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', 'm6 8 4 4 4-4');
        path.setAttribute('stroke', 'currentColor');
        path.setAttribute('stroke-width', '1.6');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');
        chevron.appendChild(path);
        this.button.appendChild(chevron);

        this.panel = document.createElement('div');
        this.panel.className = 'ss-panel hidden';

        const searchWrap = document.createElement('div');
        searchWrap.className = 'ss-search';

        this.search = document.createElement('input');
        this.search.type = 'text';
        this.search.className = 'ss-search-input';
        this.search.setAttribute('placeholder', select.dataset.searchPlaceholder || 'Search…');
        this.search.setAttribute('autocomplete', 'off');
        this.search.setAttribute('spellcheck', 'false');
        this.search.setAttribute('aria-label', 'Search options');
        searchWrap.appendChild(this.search);

        this.list = document.createElement('ul');
        this.list.className = 'ss-list';
        this.list.id = `${this.id}-list`;
        this.list.setAttribute('role', 'listbox');

        this.empty = document.createElement('p');
        this.empty.className = 'ss-empty hidden';
        this.empty.textContent = 'No matches';

        this.panel.append(searchWrap, this.list, this.empty);
        this.wrapper.append(this.button, this.panel);

        this.button.setAttribute('aria-controls', this.list.id);
    }

    /** Mirror the native <option>s (including <optgroup>s) into the panel. */
    syncOptions() {
        this.list.innerHTML = '';
        this.items = [];

        const addOption = (option, groupLabel) => {
            const item = document.createElement('li');
            item.className = 'ss-option';
            item.id = `${this.id}-opt-${this.items.length}`;
            item.setAttribute('role', 'option');
            item.textContent = option.textContent.trim() || ' ';
            item.title = option.textContent.trim();
            if (option.disabled) {
                item.setAttribute('aria-disabled', 'true');
                item.classList.add('ss-option-disabled');
            }

            const entry = {
                option,
                element: item,
                haystack: normalize(`${option.textContent} ${groupLabel || ''}`),
            };
            this.items.push(entry);
            this.list.appendChild(item);
        };

        Array.from(this.select.children).forEach((child) => {
            if (child.tagName === 'OPTGROUP') {
                const heading = document.createElement('li');
                heading.className = 'ss-group';
                heading.setAttribute('role', 'presentation');
                heading.textContent = child.label;
                this.list.appendChild(heading);
                Array.from(child.children).forEach((option) => addOption(option, child.label));
                this.groups = true;
            } else if (child.tagName === 'OPTION') {
                addOption(child, null);
            }
        });
    }

    syncValue() {
        const selected = this.select.selectedOptions[0];
        const text = selected ? selected.textContent.trim() : '';
        this.label.textContent = text || (this.select.dataset.placeholder || 'Select…');
        this.label.classList.toggle('ss-placeholder', ! text || ! selected?.value);

        this.items.forEach((entry) => {
            const isSelected = entry.option.selected;
            entry.element.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            entry.element.classList.toggle('ss-option-selected', isSelected);
        });

        this.button.disabled = this.select.disabled;
    }

    bind() {
        this.button.addEventListener('click', (event) => {
            event.preventDefault();
            this.isOpen() ? this.close() : this.open();
        });

        this.button.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                this.open();
            }
        });

        this.search.addEventListener('input', () => this.filter(this.search.value));

        this.search.addEventListener('keydown', (event) => {
            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.move(1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    this.move(-1);
                    break;
                case 'Home':
                    event.preventDefault();
                    this.moveTo(0, 1);
                    break;
                case 'End':
                    event.preventDefault();
                    this.moveTo(this.items.length - 1, -1);
                    break;
                case 'Enter':
                    event.preventDefault();
                    if (this.activeIndex >= 0) {
                        this.commit(this.items[this.activeIndex]);
                    }
                    break;
                case 'Escape':
                    event.preventDefault();
                    this.close(true);
                    break;
                case 'Tab':
                    this.close();
                    break;
            }
        });

        this.list.addEventListener('mousedown', (event) => {
            // Keep focus in the search box while clicking.
            event.preventDefault();
        });

        this.list.addEventListener('click', (event) => {
            const target = event.target.closest('.ss-option');
            if (! target) {
                return;
            }
            const entry = this.items.find((item) => item.element === target);
            if (entry) {
                this.commit(entry);
            }
        });

        this.list.addEventListener('mousemove', (event) => {
            const target = event.target.closest('.ss-option');
            if (! target) {
                return;
            }
            const index = this.items.findIndex((item) => item.element === target);
            if (index >= 0 && index !== this.activeIndex) {
                this.setActive(index, false);
            }
        });

        // Keep the trigger in step with code that changes the select directly.
        this.select.addEventListener('change', () => this.syncValue());

        this.observer = new MutationObserver(() => {
            this.syncOptions();
            this.syncValue();
        });
        this.observer.observe(this.select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });
    }

    isOpen() {
        return ! this.panel.classList.contains('hidden');
    }

    open() {
        if (this.isOpen() || this.select.disabled) {
            return;
        }
        if (openInstance && openInstance !== this) {
            openInstance.close();
        }
        openInstance = this;

        this.panel.classList.remove('hidden');
        this.button.setAttribute('aria-expanded', 'true');
        this.search.value = '';
        this.filter('');
        this.placePanel();

        const selectedIndex = this.items.findIndex((entry) => entry.option.selected);
        this.setActive(selectedIndex >= 0 ? selectedIndex : this.firstEnabled(0, 1), true);
        this.search.focus();
    }

    close(refocus = false) {
        if (! this.isOpen()) {
            return;
        }
        this.panel.classList.add('hidden');
        this.panel.classList.remove('ss-panel-up');
        this.button.setAttribute('aria-expanded', 'false');
        this.button.removeAttribute('aria-activedescendant');
        this.activeIndex = -1;
        if (openInstance === this) {
            openInstance = null;
        }
        if (refocus) {
            this.button.focus();
        }
    }

    /** Flip the panel above the field when there is no room below. */
    placePanel() {
        const rect = this.button.getBoundingClientRect();
        const below = window.innerHeight - rect.bottom;
        this.panel.classList.toggle('ss-panel-up', below < 260 && rect.top > below);
    }

    filter(term) {
        const needle = normalize(term);
        const words = needle ? needle.split(' ') : [];
        let visible = 0;

        this.items.forEach((entry) => {
            const match = words.every((word) => entry.haystack.includes(word));
            entry.visible = match;
            entry.element.classList.toggle('hidden', ! match);
            if (match) {
                visible++;
            }
        });

        // Hide group headings whose options all filtered out.
        if (this.groups) {
            Array.from(this.list.querySelectorAll('.ss-group')).forEach((heading) => {
                let node = heading.nextElementSibling;
                let any = false;
                while (node && ! node.classList.contains('ss-group')) {
                    if (! node.classList.contains('hidden')) {
                        any = true;
                        break;
                    }
                    node = node.nextElementSibling;
                }
                heading.classList.toggle('hidden', ! any);
            });
        }

        this.empty.classList.toggle('hidden', visible > 0);
        this.setActive(this.firstEnabled(0, 1), true);
    }

    selectable(index) {
        const entry = this.items[index];
        return !! entry && entry.visible !== false && ! entry.option.disabled;
    }

    firstEnabled(from, step) {
        for (let i = from; i >= 0 && i < this.items.length; i += step) {
            if (this.selectable(i)) {
                return i;
            }
        }
        return -1;
    }

    move(step) {
        const start = this.activeIndex < 0 ? (step > 0 ? -1 : this.items.length) : this.activeIndex;
        const next = this.firstEnabled(start + step, step);
        if (next >= 0) {
            this.setActive(next, true);
        }
    }

    moveTo(from, step) {
        const index = this.firstEnabled(from, step);
        if (index >= 0) {
            this.setActive(index, true);
        }
    }

    setActive(index, scroll) {
        this.items.forEach((entry) => entry.element.classList.remove('ss-option-active'));
        this.activeIndex = index;

        if (index < 0) {
            this.button.removeAttribute('aria-activedescendant');
            return;
        }

        const element = this.items[index].element;
        element.classList.add('ss-option-active');
        this.button.setAttribute('aria-activedescendant', element.id);

        if (scroll) {
            element.scrollIntoView({ block: 'nearest' });
        }
    }

    commit(entry) {
        if (! entry || entry.option.disabled) {
            return;
        }

        const changed = this.select.value !== entry.option.value ||
            this.select.selectedIndex !== Array.from(this.select.options).indexOf(entry.option);

        entry.option.selected = true;
        this.syncValue();
        this.close(true);

        if (changed) {
            // Bubbling native events so `onchange` attributes and any listeners fire.
            this.select.dispatchEvent(new Event('input', { bubbles: true }));
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
}

function enhance(root = document) {
    root.querySelectorAll('select').forEach((select) => {
        if (! shouldEnhance(select)) {
            return;
        }
        select.dataset[ENHANCED] = 'true';
        select.__searchableSelect = new SearchableSelect(select);
    });
}

function boot() {
    enhance();

    // Panels close on an outside click, scroll away or resize.
    document.addEventListener('mousedown', (event) => {
        if (openInstance && ! openInstance.wrapper.contains(event.target)) {
            openInstance.close();
        }
    });

    document.addEventListener('focusin', (event) => {
        if (openInstance && ! openInstance.wrapper.contains(event.target)) {
            openInstance.close();
        }
    });

    window.addEventListener('resize', () => openInstance?.close());
    window.addEventListener('scroll', () => openInstance?.placePanel(), true);

    // Selects rendered later (modals, partial reloads) get enhanced too.
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) {
                    return;
                }
                if (node.tagName === 'SELECT' && shouldEnhance(node)) {
                    node.dataset[ENHANCED] = 'true';
                    node.__searchableSelect = new SearchableSelect(node);
                } else if (node.querySelector?.('select')) {
                    enhance(node);
                }
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

export { enhance };

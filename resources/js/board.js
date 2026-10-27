/**
 * Board drag and drop.
 *
 * The server owns the outcome. A drop sends one move and waits: on success the
 * card stays where it was dropped, on failure it goes back where it came from
 * and the reason is shown. Nothing is assumed to have worked because the card
 * moved on screen — the same move through the ticket screen can be refused, and
 * this must behave identically.
 */

import Sortable from 'sortablejs';

const CARD = '[data-board-card]';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/** A short-lived message under the header — a failed drop needs an explanation. */
function flash(message, tone = 'error') {
    let bar = document.getElementById('board-flash');

    if (! bar) {
        bar = document.createElement('div');
        bar.id = 'board-flash';
        bar.className = 'fixed inset-x-0 bottom-4 z-50 mx-auto w-max max-w-[90vw] rounded-lg px-4 py-2 text-sm shadow-raised';
        document.body.appendChild(bar);
    }

    bar.className = bar.className.replace(/ bg-\S+| text-\S+/g, '');
    bar.classList.add(
        tone === 'error' ? 'bg-red-600' : 'bg-slate-900',
        'text-white'
    );
    bar.textContent = message;
    bar.hidden = false;

    clearTimeout(bar.dataset.timer);
    bar.dataset.timer = setTimeout(() => { bar.hidden = true; }, 4000);
}

/** Ids of the cards immediately above and below the dropped one. */
function neighbours(card) {
    const previous = card.previousElementSibling?.matches(CARD) ? card.previousElementSibling : null;
    const next     = card.nextElementSibling?.matches(CARD) ? card.nextElementSibling : null;

    return {
        before_id: previous ? Number(previous.dataset.id) : null,
        after_id:  next ? Number(next.dataset.id) : null,
    };
}

async function sendMove(board, card, column, undo) {
    const payload = {
        kind:      card.dataset.kind,
        id:        Number(card.dataset.id),
        sprint_id: board.dataset.sprintId ? Number(board.dataset.sprintId) : null,
        ...neighbours(card),
    };

    // Every card dropped in a status column carries a status change — the
    // payload always carries the column's *ticket* status id, and
    // BoardController translates it for a task or development request (see
    // taskStatusIdForColumn() / devStatusIdForColumn()).
    if (column.dataset.statusId) {
        payload.status_id = Number(column.dataset.statusId);
    }

    card.classList.add('opacity-50');

    try {
        const response = await fetch(board.dataset.moveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        // 401/419: the session expired. 423: the account is still gated behind a
        // forced password change. Both render an HTML page outside this fetch, so
        // the only correct recovery is a reload to land the user on it properly.
        if ([401, 419, 423].includes(response.status)) {
            const body = await response.clone().json().catch(() => null);
            flash(body?.message ?? 'You need to do something before continuing — reloading…');
            undo();
            setTimeout(() => window.location.reload(), 1500);
            return;
        }

        if (! response.ok) {
            const body = await response.clone().json().catch(() => null);
            undo();

            if (body?.message) {
                flash(body.message);
            } else if (body?.errors) {
                flash(Object.values(body.errors).flat()[0] ?? 'That move was refused.');
            } else {
                // Not JSON at all — a raw 500 page or similar. Surface enough to
                // report it rather than a bare status code.
                const text = await response.text().catch(() => '');
                console.error('Board move failed', response.status, text.slice(0, 500));
                flash(`That move was refused (${response.status}). Check the console for details.`);
            }

            return;
        }

        flash('Moved.', 'ok');
    } catch (error) {
        console.error('Board move request failed', error);
        undo();
        flash('The move could not be saved — check your connection.');
    } finally {
        card.classList.remove('opacity-50');
    }
}

function boot() {
    const board = document.getElementById('board');

    if (! board || board.dataset.canManage !== '1') {
        return;
    }

    // Under a priority/due-date sort the column order is derived from the cards
    // themselves, so a hand-placed position would vanish on the next load.
    // Cards still move between columns; they just cannot be reordered within one.
    const manualOrder = board.dataset.manualOrder !== '0';

    board.querySelectorAll('.board-column').forEach((column) => {
        Sortable.create(column, {
            // One shared group so a card can be dragged from any column into any
            // other — without this, Sortable gives each column its own implicit
            // group and only in-column reordering (top to bottom) is possible.
            group: 'board',
            sort: manualOrder,
            animation: 150,
            ghostClass: 'opacity-40',
            dragClass: 'rotate-1',
            fallbackOnBody: true,
            // Touch needs a moment of hold, or scrolling the board starts a drag.
            delayOnTouchOnly: true,
            delay: 120,

            onEnd(event) {
                const card = event.item;

                if (event.to === event.from && event.oldIndex === event.newIndex) {
                    return;
                }

                const undo = () => {
                    const sibling = event.from.children[event.oldIndex] ?? null;
                    event.from.insertBefore(card, sibling);
                };

                sendMove(board, card, event.to, undo);
            },
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

// The remove button takes its tile out of the list, which then submits without it. Focus goes to the next tile's
// remove button, or the one before, so the keyboard isn't left on nothing.
document.addEventListener('click', (event) => {
    const tile = event.target.closest('[data-photo-remove]')?.closest('[data-sortable-item]');
    if (!tile) {
        return;
    }
    const next = tile.nextElementSibling ?? tile.previousElementSibling;
    tile.remove();
    next?.querySelector('[data-photo-remove]')?.focus();
});

// window.sortable is available once the sortable script has loaded.
// Moves an item, by its id or element, to a place counted from 0. It's announced, fires sortable:move and is sent to
// move-url, like a move made by hand. False when it can't move.
sortable.move('3', 0);

// Every move, by hand or by script, fires sortable:move on the list (and sortable-move, for wire:sortable-move). after
// is the id of the item it now follows, or null at the top; order is every id, first to last. Ids arrive as strings.
document.addEventListener('sortable:move', (event) => {
    const { item, position, after, order } = event.detail;
    console.log(`${item} is now at ${position}, after ${after ?? 'none'}: ${order.join(', ')}`);
});

// window.kanban is available once the kanban script has loaded.
// Moves a card, by its id or element, to a column by its id, at a place counted from 0 (left out, the end). It's
// announced, fires kanban:move and is sent to move-url, like a move made by hand. False when it can't move there.
kanban.move('1042', 'shipped');
kanban.move('1042', 'packed', 0);

// Every move, by hand or by script, fires kanban:move on the board (and kanban-move, for wire:kanban-move). after is
// the id of the card it now follows, or null at the top; ids arrive as strings.
document.addEventListener('kanban:move', (event) => {
    const { card, from, to, position, after } = event.detail;
    console.log(`${card}: ${from} → ${to}, place ${position}, after ${after ?? 'none'}`);
});

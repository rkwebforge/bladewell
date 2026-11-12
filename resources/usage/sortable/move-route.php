// The URL in move-url answers POST with the move as JSON: item, position (counted from 0), after (the item it now
// follows, or null at the top), order (every id in the list, first to last) and, with version="…", the version this
// page drew. Moves go one at a time, the next one only once this answers, so they arrive in the order they were made.
// Any 2xx keeps the move. Anything else puts the list back in the order last saved, with your answer's message: a
// failed validation's first error, or abort(403, '…'). A 409 also offers the person a reload.
// <x-widget.sortable label="Links" :move-url="route('menus.links.reorder', $menu)" :version="$menu->links_version">
Route::post('/menus/{menu}/links/reorder', function (Request $request, Menu $menu) {
    Gate::authorize('update', $menu);
    $move = $request->validate([
        // max: a request can send any number of ids. Sized for a list a person orders by hand.
        'order' => ['required', 'array', 'max:200'],
        'order.*' => ['integer', 'distinct'],
        'version' => ['required', 'integer'],
    ]);

    $version = DB::transaction(function () use ($menu, $move) {
        // Read with a lock, so two people reordering at once take turns, and the second sees what the first saved.
        $menu = Menu::whereKey($menu->id)->lockForUpdate()->firstOrFail();
        // Someone else reordered since this page drew the list: don't overwrite their order without them seeing it.
        abort_if($move['version'] !== $menu->links_version, 409, 'Someone else has reordered these links. Reload to see their order.');

        // Every id has to be one of this menu's links, and every one of its links has to be there, once: an id from
        // another menu can't be moved into this one, and a link can't be dropped by leaving it out.
        $ids = $menu->links()->pluck('id')->all();
        if (array_diff($ids, $move['order']) !== [] || array_diff($move['order'], $ids) !== []) {
            throw ValidationException::withMessages(['order' => 'These links have changed since this page loaded. Reload it and try again.']);
        }
        // One update per link, all or none. Fine for a list someone orders by hand; for a long one, move only `item`,
        // after `after`, with sort values that leave gaps (1000, 2000, …).
        foreach ($move['order'] as $sort => $id) {
            $menu->links()->whereKey($id)->update(['sort' => $sort]);
        }
        $menu->increment('links_version');

        return $menu->links_version;
    });

    // The version the next move sends.
    return response()->json(['version' => $version]);
})->middleware('throttle:60,1')->name('menus.links.reorder');

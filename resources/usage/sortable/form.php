// With name="links", the form sends links[] with the ids in the order on the page, and any fields inside the items as
// they're named (titles[11] here). Validate the ids as any array, check they are exactly this menu's links, none
// missing and none from another menu, and look each one up through the menu, never as a bare id. Failed validation
// sends the order back as old input, and the list draws itself in that order.
Route::put('/menus/{menu}', function (Request $request, Menu $menu) {
    Gate::authorize('update', $menu);
    $validated = $request->validate([
        'links' => ['required', 'array', 'max:50'],
        'links.*' => ['integer', 'distinct'],
        'titles' => ['required', 'array'],
        'titles.*' => ['required', 'string', 'max:40'],
    ], attributes: ['titles.*' => 'label']);

    DB::transaction(function () use ($menu, $validated): void {
        // Locked, so two saves at once take turns rather than mixing their orders.
        $ids = $menu->links()->lockForUpdate()->pluck('id')->all();
        if (array_diff($ids, $validated['links']) !== [] || array_diff($validated['links'], $ids) !== []) {
            throw ValidationException::withMessages(['links' => 'The links have changed since this page loaded. Reload it and try again.']);
        }
        foreach ($validated['links'] as $sort => $id) {
            $menu->links()->whereKey($id)->update(['sort' => $sort, 'title' => $validated['titles'][$id] ?? null]);
        }
    });

    return back()->with('status', 'Menu saved.');
})->name('menus.update');

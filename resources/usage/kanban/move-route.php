// The URL in move-url answers POST with the move as JSON: card, from, to, position (counted from 0 in the new column)
// and after (the card it now follows, or null at the top). Any 2xx keeps the move. Anything else puts the card back,
// with your answer's message: a failed validation's first error, or abort(403, '…'). Check the person may move this
// order, as for any change.
Route::post('/orders/move', function (Request $request) {
    $move = $request->validate([
        'card' => ['required', 'integer', 'exists:orders,id'],
        'to' => ['required', Rule::in(['new', 'packed', 'shipped'])],
        'position' => ['required', 'integer', 'min:0'],
    ], [
        'to.in' => 'Orders reach Delivered when the courier confirms them.',
    ]);
    // All of it inside one transaction, on the order read with a lock: on a shared board, two people can move the same
    // card at once. The second waits here for the first, then checks against the stage the first one left, not the one
    // it read before. Its own stage and place, too, are read now, for closing the gap it leaves.
    DB::transaction(function () use ($move) {
        $order = Order::whereKey($move['card'])->lockForUpdate()->firstOrFail();
        Gate::authorize('update', $order);

        // Which stage each can move to, from where it is now: the order's own stage, never the `from` the browser
        // sent. Rule::in() above only says where a card may land; this says from where. Reordering within its column
        // is fine.
        $next = ['new' => ['packed'], 'packed' => ['new', 'shipped'], 'shipped' => ['packed']];
        if ($move['to'] !== $order->stage && ! in_array($move['to'], $next[$order->stage] ?? [], true)) {
            throw ValidationException::withMessages(['to' => "A {$order->stage} order can't go straight to {$move['to']}."]);
        }

        // Close the gap it leaves, then make room where it lands.
        Order::where('stage', $order->stage)->where('sort', '>', $order->sort)->decrement('sort');
        Order::where('stage', $move['to'])->where('sort', '>=', $move['position'])->increment('sort');
        $order->update(['stage' => $move['to'], 'sort' => $move['position']]);
    });

    return response()->noContent();
})->middleware('throttle:60,1')->name('orders.move');

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
    $order = Order::findOrFail($move['card']);
    Gate::authorize('update', $order);

    DB::transaction(function () use ($order, $move) {
        // Close the gap it leaves, then make room where it lands.
        Order::where('stage', $order->stage)->where('sort', '>', $order->sort)->decrement('sort');
        Order::where('stage', $move['to'])->where('sort', '>=', $move['position'])->increment('sort');
        $order->update(['stage' => $move['to'], 'sort' => $move['position']]);
    });

    return response()->noContent();
})->middleware('throttle:60,1')->name('orders.move');

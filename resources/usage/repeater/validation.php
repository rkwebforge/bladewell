use Illuminate\Support\Facades\DB;

// Rows arrive keyed: a record's id, or "new-…" for a row added in the browser. Validate them with *, as any array.
// A row only arrives if one of its fields is sent, and a disabled field isn't: a row of nothing but unticked
// checkboxes or disabled fields needs a field that always is, or saving below deletes it.
$validated = $request->validate([
    'lines' => ['required', 'array', 'min:1', 'max:10'],
    // distinct: each product once. Its error is filed under the row's key, so it shows on that row.
    'lines.*.product' => ['required', 'string', 'max:120', 'distinct'],
    'lines.*.quantity' => ['required', 'integer', 'min:1'],
], attributes: [
    // Without these, messages name the field by its key: "The lines.new-k3f9qx.product field is required."
    'lines.*.product' => 'product',
    'lines.*.quantity' => 'quantity',
]);

$isNew = fn (int|string $key): bool => str_starts_with((string) $key, 'new-');
$kept = array_filter(array_keys($validated['lines']), fn (int|string $key): bool => ! $isNew($key));

// In one transaction, so a key that isn't this order's (findOrFail) undoes the deletes before it too.
DB::transaction(function () use ($order, $validated, $kept, $isNew): void {
    // Lines no longer sent were removed on the page.
    $order->lines()->whereNotIn('id', $kept)->delete();

    // The array is in the order the rows were on the page. A key is looked up through the order it belongs to, so a
    // request can't edit someone else's line by sending its id.
    $position = 0;
    foreach ($validated['lines'] as $key => $line) {
        $attributes = [...$line, 'position' => $position++];
        $isNew($key)
            ? $order->lines()->create($attributes)
            : $order->lines()->findOrFail($key)->update($attributes);
    }
});

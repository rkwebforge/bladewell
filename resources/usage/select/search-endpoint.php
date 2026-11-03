// The URL in search-url answers GET ?q=… with JSON: a list, or a resource collection ({"data": [...]}). Each option is
// a string, or value and label, with optional meta and disabled. Pass the chosen record(s) in options so the field shows
// them, and validate the value on submit as usual (exists:customers,id). It answers whoever calls it, not only this
// field, and the meta here is an email: so only signed in, and only the customers this person may see.
Route::get('/customers/search', function (Request $request) {
    $q = $request->validate(['q' => ['required', 'string', 'max:100']])['q'];

    // Their own (or their team's), never Customer::query(), which would list everyone's.
    return $request->user()->customers()
        ->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
        ->orderBy('name')
        ->limit(20)
        ->get()
        ->map(fn (Customer $customer): array => ['value' => $customer->id, 'label' => $customer->name, 'meta' => $customer->email]);
})->middleware(['auth', 'throttle:120,1'])->name('customers.search');

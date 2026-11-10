// The URL in search-url answers GET ?q=… with JSON: a list, or a resource collection ({"data": [...]}). Each option is
// a string, or value and label, with optional meta and disabled. Pass the chosen record(s) in options so the field shows
// them. It answers whoever calls it, not only this field, and the meta here is an email: so only signed in, and only
// the customers this person may see.
Route::get('/customers/search', function (Request $request) {
    $q = $request->validate(['q' => ['required', 'string', 'max:100']])['q'];

    // Their own (or their team's), never Customer::query(), which would list everyone's.
    return $request->user()->customers()
        // % and _ typed match themselves, the same on every database (ESCAPE '!'), and LOWER() ignores case.
        ->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%'])
        ->orderBy('name')
        ->limit(20)
        ->get()
        ->map(fn (Customer $customer): array => ['value' => $customer->id, 'label' => $customer->name, 'meta' => $customer->email]);
})->middleware(['auth', 'throttle:120,1'])->name('customers.search');

// On submit, check the chosen id through that same relationship, in the Form Request's rules(). Not a plain
// exists:customers,id: that takes any customer's id sent straight to the form, though the search never offered it.
'customer_id' => ['required', 'integer', fn (string $attribute, mixed $value, Closure $fail) => $this->user()->customers()->whereKey($value)->exists() || $fail('Choose one of your customers.')],

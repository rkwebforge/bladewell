// In a Livewire component the array property owns the rows. Key them as the plain form does, by a record's id or
// "new-…", so wire:model, errors and focus follow a row when it moves. Every method can be called from the browser with
// any key, so check what it's given, and keep the limits on the server too.
use Illuminate\Support\Str;
use Livewire\Component;

class EditOrder extends Component
{
    public Order $order;

    /** @var array<int|string, array{product: string, quantity: int}> */
    public array $lines = [];

    public function mount(Order $order): void
    {
        $this->order = $order;
        $this->lines = $order->lines()->orderBy('position')->get()
            ->mapWithKeys(fn ($line) => [$line->id => $line->only('product', 'quantity')])->all();
    }

    public function addLine(): void
    {
        if (count($this->lines) < 10) {
            $this->lines['new-'.Str::lower(Str::random(6))] = ['product' => '', 'quantity' => 1];
        }
    }

    public function removeLine(string $key): void
    {
        if (count($this->lines) > 1) {
            unset($this->lines[$key]);
        }
    }

    // $direction is -1 (up) or 1 (down): the row swaps places with its neighbour.
    public function moveLine(string $key, int $direction): void
    {
        $keys = array_keys($this->lines);
        $from = array_search($key, array_map('strval', $keys), true);
        $to = $from === false ? null : $from + $direction;
        if ($to === null || ! isset($keys[$to])) {
            return;
        }
        [$keys[$from], $keys[$to]] = [$keys[$to], $keys[$from]];
        $this->lines = array_replace(array_flip($keys), $this->lines);
    }

    public function save(): void
    {
        // The validated lines, not $this->lines: the browser can set any public property, extra fields included, and
        // validate() returns only the fields it has rules for.
        $validated = $this->validate([
            'lines' => ['required', 'array', 'min:1', 'max:10'],
            'lines.*.product' => ['required', 'string', 'max:120', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'lines.*.product' => 'product',
            'lines.*.quantity' => 'quantity',
        ]);

        // Then save $validated['lines'] as in the validation snippet.
    }
}

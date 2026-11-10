// In a Livewire component, page, sort and rows-per-page links update the component in place. Use WithPagination,
// and name the sort properties after the table's sort-name and direction-name (sort and direction by default).
// Every public property can be set from the browser, so check each one before it reaches a query.
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Invoices extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $sort = null;

    #[Url]
    public string $direction = 'asc';

    // per-page-model="perPage"
    public int $perPage = 10;

    // select-model="selected": the ticked ids, kept across pages. The table counts from it, so the bulk bar
    // always says what deleteSelected() will get.
    public array $selected = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // For a selection per page, clear it when the page changes.
    // public function updatedPaginators(): void { $this->selected = []; }

    public function deleteSelected(): void
    {
        $invoices = auth()->user()->invoices()->whereKey($this->selected)->get();
        $invoices->each(fn (Invoice $invoice) => $this->authorize('delete', $invoice));
        $invoices->each->delete();
        $this->selected = [];
    }

    public function render()
    {
        $sort = in_array($this->sort, ['number', 'date', 'amount'], true) ? $this->sort : 'date';
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;
        // Capped at 100 characters.
        $search = mb_substr(trim($this->search), 0, 100);

        return view('livewire.invoices', [
            'invoices' => auth()->user()->invoices()
                // % and _ typed match themselves, the same on every database (ESCAPE '!'): otherwise a search of %
                // matches every row. LOWER() ignores case.
                ->when($search !== '', fn ($query) => $query->whereRaw("LOWER(number) LIKE LOWER(?) ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']))
                ->orderBy($sort, $this->direction === 'desc' ? 'desc' : 'asc')
                ->paginate($perPage),
        ]);
    }
}

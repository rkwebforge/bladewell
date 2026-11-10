// In a Livewire component, use WithPagination: every style's page links (and the jump form and the page sheet) then
// call gotoPage() on the component, in place, instead of reloading the page, and the URL follows. For rows per page
// (type="footer") or load more, add a property and name it with per-page-model. Every public property can be set
// from the browser, so check the page size before it reaches the query.
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    // per-page-model="perPage": the footer's select sets it; load more raises it by a page each time.
    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.orders', [
            'orders' => auth()->user()->orders()
                // % and _ typed match themselves, the same on every database (ESCAPE '!'), and LOWER() ignores case.
                ->when($this->search, fn ($query) => $query->whereRaw("LOWER(number) LIKE LOWER(?) ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($this->search, 0, 100)).'%']))
                ->latest()
                ->paginate(min(max($this->perPage, 1), 100)),
        ]);
    }
}

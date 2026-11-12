// In a Livewire component, keep the filters in a #[Url] string property: the builder writes its JSON there, the address
// bar keeps it for reloads and links, and read() checks it on every render, as it would a request.
use App\View\Widget\FilterField;
use App\View\Widget\Filters;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    #[Url]
    public string $filters = '';

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $fields = Filters::make([
            FilterField::text('number', 'Order number'),
            FilterField::choice('status', 'Status', ['paid' => 'Paid', 'pending' => 'Pending', 'refunded' => 'Refunded']),
            FilterField::dateTime('placed', 'Placed')->column('created_at'),
            FilterField::number('amount', 'Amount')->column('total'),
        ]);
        // The browser can set the property to anything: read() keeps only what the fields allow.
        $state = $fields->read($this->filters, timezone: auth()->user()->timezone);

        return view('livewire.orders', [
            'state' => $state,
            'orders' => $fields->apply(auth()->user()->orders()->getQuery(), $state)->latest()->paginate(20),
        ]);
    }
}

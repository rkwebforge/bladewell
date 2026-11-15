{{-- A checkbox per row (give each row a :value) and a select-all box. The bar above stays put, and your bulk slot's buttons wake up once any row is ticked; the ticked values submit as selected[] with the form the table builds (POST with CSRF by default, to bulk-action). --}}
<x-widget.table id="invoices" caption="Invoices" selectable :bulk-action="url()->current()" bulk-method="GET" :columns="['Invoice', 'Client', ['label' => 'Amount', 'align' => 'end'], 'Status']" :rows="$invoices">
    <x-slot:bulk>
        <x-widget.button type="submit" name="action" value="export" size="sm" variant="neutral" :submit-guard="false">Export</x-widget.button>
        <x-widget.button type="submit" name="action" value="remind" size="sm" :submit-guard="false">Send reminder</x-widget.button>
    </x-slot:bulk>

    @foreach ($invoices as $invoice)
        <x-widget.table.row :value="$invoice['number']" :select-label="'Select '.$invoice['number']">
            <td>{{ $invoice['number'] }}</td>
            <td>{{ $invoice['client'] }}</td>
            <td>{{ $invoice['amount'] }}</td>
            <td><x-widget.table.badge :tone="$invoice['overdue'] ? 'warning' : 'neutral'">{{ $invoice['overdue'] ? 'Overdue' : 'Sent' }}</x-widget.table.badge></td>
        </x-widget.table.row>
    @endforeach
</x-widget.table>

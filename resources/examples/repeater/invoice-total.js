// The total is each line's quantity times its price, added up, worked out again whenever a field in the invoice changes.
document.addEventListener('input', (event) => {
    const invoice = event.target.closest?.('[data-invoice]');
    if (!invoice) {
        return;
    }
    const total = [...invoice.querySelectorAll('[data-repeater-row]')].reduce((sum, row) => {
        const quantity = Number(row.querySelector('[name$="[quantity]"]')?.value) || 0;
        const price = Number(row.querySelector('[name$="[price]"]')?.value) || 0;

        return sum + quantity * price;
    }, 0);
    // Written as the server writes it, number_format($total, 2): 1,140.00.
    invoice.querySelector('[data-invoice-total]').textContent = `$${total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
});

// A line removed changes the total too, without a field changing: ask for the same sum.
document.addEventListener('repeater:remove', (event) => {
    event.target.dispatchEvent(new Event('input', { bubbles: true }));
});

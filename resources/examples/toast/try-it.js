// A loading toast stays until you update it, e.g. when the request finishes.
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-notify="loading"]')) {
        const id = toast.loading('Processing payment...');
        setTimeout(() => toast.update(id, 'success', 'Payment confirmed.'), 2000);
    }
});

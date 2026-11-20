// window.toast is available once the toast script has loaded.
toast.success('Profile updated.');
toast.error('Something went wrong.');
toast.warning('Your session is about to expire.');
toast.info('New version available.');

// A loading toast stays until you update or dismiss it.
const id = toast.loading('Processing payment...');
toast.update(id, 'success', 'Payment confirmed.');

// The same toast shown again while it's still up (same kind, title and message) isn't repeated: the one showing
// counts it (×2) and returns its id. merge: false always gives a toast of its own, for code that keeps its id.
const saved = toast.success('Saved.', { merge: false });

// An action, like Undo: the toast stays until it's used or dismissed, and goes once it's used.
toast.success('Invoice deleted.', { action: { label: 'Undo', onClick: () => restoreInvoice(42) } });

toast.dismiss(id); // one toast
toast.dismiss();   // all of them

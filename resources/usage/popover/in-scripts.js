// window.popover is available once the popover script has loaded.
// <x-widget.popover id="fees" label="About fees">…</x-widget.popover>
popover.open('fees');
popover.close('fees');
popover.toggle('fees');

// Or any element of the popover: its trigger, its panel, or something inside it.
popover.close(document.querySelector('#fees-panel [data-popover-close]'));

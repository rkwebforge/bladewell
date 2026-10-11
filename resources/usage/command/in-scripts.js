// window.command is available once the command script has loaded.
// <x-widget.command id="palette" …>
command.open('palette');
command.close('palette');

// Choosing an item, for your own analytics or shortcuts.
document.addEventListener('command:chosen', (event) => console.log('Chosen:', event.detail.item.textContent.trim()));

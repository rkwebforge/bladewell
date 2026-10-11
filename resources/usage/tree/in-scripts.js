// window.tree is available once the tree script has loaded.
// <x-widget.tree.item id="docs" label="Docs">…</x-widget.tree.item>
tree.open('docs');
tree.close('docs');

// Or the item element, or anything inside it. Opening an item with children-url fetches them first: await it.
await tree.open(document.querySelector('[data-tree-item][aria-expanded="false"]'));

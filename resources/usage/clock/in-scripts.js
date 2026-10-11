// window.clock is available once the clock script has loaded. It runs stopwatches and timers.
// <x-widget.clock.stopwatch id="lap" />
clock.start('lap');
clock.pause('lap');
clock.reset('lap');

// A timer says when it's done.
document.addEventListener('clock:finished', (event) => console.log('Done:', event.target.id));

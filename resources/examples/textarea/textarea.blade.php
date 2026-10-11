{{-- By default it grows with its content between min-rows and max-rows (5), including when a script sets its value. resizable gives it a drag handle instead: the user sets the height, from min-rows down to max-rows if you set one. Each new height fires a textarea:resize event. --}}
<div class="grid gap-6 sm:grid-cols-2">
    <x-widget.textarea name="notes" label="Notes" placeholder="Grows as you type..." />
    <x-widget.textarea name="message" label="Message" placeholder="Drag the corner to resize" resizable :min-rows="4" />
</div>

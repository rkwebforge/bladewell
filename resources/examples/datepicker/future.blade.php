{{-- For a deadline or an appointment: from today, no upper limit. Without :max="false" the calendar stops at today, its default for dates that have already happened. --}}
<x-widget.datepicker name="due_date" label="Due date" min="today" :max="false" class="max-w-xs" />

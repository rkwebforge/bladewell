{{-- Submits Y-m-d, up to today by default: for dates ahead, see the future example. Validate it with new NotAfterToday, not before_or_equal:today, which uses the server's UTC date. --}}
<x-widget.datepicker name="start_date" label="Start date" class="max-w-xs" />

{{-- A repeater inside a row: each question keeps its own list of answers, named under both keys, questions[{question}][answers][{answer}]. The inner one needs its own placeholder, and sets its own labels, so its rows don't take the question's. Adding a question copies an empty answer list under the new question's key. --}}
<form class="max-w-2xl">
    <x-widget.repeater name="questions" label="Questions" :min="1" :max="5" add-label="Add question" row-label="Question">
        @foreach ((old() ? old('questions', []) : $questions) + ['__row__' => []] as $key => $question)
            <x-widget.repeater.row :key="$key">
                <x-widget.text-input name="questions[{{ $key }}][text]" label="Question" :value="$question['text'] ?? null" required class="sm:col-span-2" />
                <x-widget.repeater name="questions[{{ $key }}][answers]" label="Answers" :max="6" add-label="Add answer" row-label="Answer" placeholder="__answer__" class="sm:col-span-2">
                    @foreach (($question['answers'] ?? []) + ['__answer__' => []] as $answerKey => $answer)
                        <x-widget.repeater.row :key="$answerKey">
                            <x-widget.text-input name="questions[{{ $key }}][answers][{{ $answerKey }}][text]" label="Answer" :value="$answer['text'] ?? null" required class="sm:col-span-2" />
                        </x-widget.repeater.row>
                    @endforeach
                </x-widget.repeater>
            </x-widget.repeater.row>
        @endforeach
    </x-widget.repeater>
</form>

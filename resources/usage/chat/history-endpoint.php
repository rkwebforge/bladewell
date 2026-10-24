// history-url: a route on your own site that answers with the messages before the ones showing, as HTML: a chat
// holding them, and its own history-url for the page before those. Leave history-url off on the last page.
Route::get('/conversations/{conversation}/messages', ConversationMessagesController::class)
    ->middleware('auth')
    ->name('conversations.messages.index');

final class ConversationMessagesController
{
    public function __invoke(Conversation $conversation, Request $request): View
    {
        Gate::authorize('view', $conversation);

        $page = $conversation->messages()->latest()->simplePaginate(20);

        // resources/views/conversations/history.blade.php:
        // <x-widget.chat :history-url="$page->nextPageUrl()">
        //     @foreach ($page->reverse() as $message)
        //         <x-widget.chat.message :mine="$message->user_id === auth()->id()" :name="$message->user->name" :time="$message->created_at">{{ $message->body }}</x-widget.chat.message>
        //     @endforeach
        // </x-widget.chat>
        return view('conversations.history', ['page' => $page]);
    }
}

// On the page itself, start with the newest 20 and point history-url at page 2:
// <x-widget.chat :history-url="route('conversations.messages.index', [$conversation, 'page' => 2])">

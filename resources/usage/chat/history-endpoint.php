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

        // Newest first by id, which is unique and never changes, a cursor at a time: an offset page shifts as new
        // messages arrive, and would send again ones already showing.
        $page = $conversation->messages()->orderByDesc('id')->cursorPaginate(20);

        // resources/views/conversations/history.blade.php:
        // <x-widget.chat :history-url="$page->nextPageUrl()">
        //     @foreach ($page->reverse() as $message)
        //         <x-widget.chat.message :mine="$message->user_id === auth()->id()" :name="$message->user->name" :time="$message->created_at" :text="$message->body" />
        //     @endforeach
        // </x-widget.chat>
        return view('conversations.history', ['page' => $page]);
    }
}

// On the page itself, the newest 20, and history-url from that same batch: it asks for what's older than the oldest
// one showing, however many arrive meanwhile. withPath(): its next page is the history route's, not this page's.
// $first = $conversation->messages()->orderByDesc('id')->cursorPaginate(20)->withPath(route('conversations.messages.index', $conversation));
// <x-widget.chat :history-url="$first->nextPageUrl()">
//     @foreach ($first->reverse() as $message) … @endforeach
// </x-widget.chat>

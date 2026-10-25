// async on the composer: the message shows at once as Sending, posted from the page. Your route answers with the
// message as saved, as HTML: a chat holding it (and anything else to add, such as a reply), which takes its place.
// A plain form post, without the script, still gets the redirect. A failed validation comes back as 422 on its own:
// the message goes back into the box, with why.
Route::post('/conversations/{conversation}/messages', StoreMessageController::class)
    ->middleware(['auth', 'throttle:30,1'])
    ->name('conversations.messages.store');

final class StoreMessageController
{
    public function __invoke(StoreMessageRequest $request, Conversation $conversation): View|RedirectResponse
    {
        Gate::authorize('reply', $conversation);

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('message'),
        ]);

        if ($request->ajax()) {
            // resources/views/conversations/sent.blade.php, in the same variant (and compact) as the page's chat:
            // <x-widget.chat variant="messages">
            //     <x-widget.chat.message mine status="sent" :time="$message->created_at" :text="$message->body" />
            // </x-widget.chat>
            // The request says which, if one route serves more than one: X-Chat-Variant, and X-Chat-Compact when compact.
            return view('conversations.sent', ['message' => $message]);
        }

        return back();
    }
}

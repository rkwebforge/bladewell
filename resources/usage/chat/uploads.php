// The files a message brings are only as safe as how you check, keep and serve them. The paperclip's accept only steers
// the picker: anyone can post any file to your route, so check it here, and authorize the conversation as for a message.
public function rules(): array
{
    return [
        'message' => ['required_without:attachments', 'nullable', 'string', 'max:2000'],
        'attachments' => ['array', 'max:4'],
        // No SVG or HTML: opened from your own site (an image opens full size), either could run script as your site.
        'attachments.*' => ['file', 'mimes:png,jpg,jpeg,webp,gif,pdf,csv', 'max:5120'],
    ];
}

// A form with the paperclip sends an empty file when none was picked (the composer leaves it out when it sends from the
// page, a plain form can't): drop it before validating, in your Form Request's prepareForValidation():
// $files = array_values(array_filter((array) $this->file('attachments', [])));
// $files === [] ? $this->files->remove('attachments') : $this->files->set('attachments', $files);

// Keep them under a name of your own, never the one they came with, on a disk the public can't browse.
foreach ($request->file('attachments', []) as $file) {
    $message->attachments()->create([
        'path' => $file->store("conversations/{$conversation->id}", 'local'),
        'name' => $file->getClientOriginalName(),
        'size' => $file->getSize(),
    ]);
}

// Send them back through a route that checks who's asking, as a download, so the browser never runs one as a page.
Route::get('/attachments/{attachment}', function (Attachment $attachment) {
    Gate::authorize('view', $attachment->message->conversation);

    return Storage::disk('local')->download($attachment->path, $attachment->name, ['X-Content-Type-Options' => 'nosniff']);
})->middleware('auth')->name('attachments.show');

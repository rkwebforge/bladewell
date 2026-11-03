// upload-url: a route that stores one file (the request field is "file") and answers with its id. A 422 with
// errors.file shows that message on the file's row. Authorize it like any other write.
Route::post('/uploads', StoreUploadController::class)->middleware('auth')->name('uploads.store');

final class StoreUploadController
{
    // StoreUploadRequest: ['file' => ['required', File::default()->max('10mb')]], and authorize() for who may upload.
    public function __invoke(StoreUploadRequest $request): JsonResponse
    {
        $upload = $request->user()->uploads()->create([
            'path' => $request->file('file')->store('uploads'),
            'name' => $request->file('file')->getClientOriginalName(),
            'size' => $request->file('file')->getSize(),
        ]);

        return response()->json(['id' => $upload->id]);
    }
}

// The form then submits documents[] with those ids (its Form Request: 'documents.*' => ['integer', 'distinct']). Load
// them through the person's own uploads, so someone else's id finds nothing, and refuse the form if any did.
$ids = $request->validated('documents', []);
$uploads = $request->user()->uploads()->whereKey($ids)->get();
if ($uploads->count() !== count($ids)) {
    throw ValidationException::withMessages(['documents' => 'One of these files is no longer available. Upload it again.']);
}
$application->attachments()->saveMany($uploads);

// And delete uploads nobody claimed after a day (a scheduled job), since a picked file may never be submitted.

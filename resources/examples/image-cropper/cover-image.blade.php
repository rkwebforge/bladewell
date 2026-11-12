{{-- A wide banner from a drop zone: aspect="16/9", made at most 1920 × 1080 and as webp, so a 48-megapixel phone photo goes up as a few hundred kilobytes. With upload-url on the file upload, only the cropped image is uploaded. --}}
<form method="POST" enctype="multipart/form-data" class="grid max-w-2xl gap-6">
    @csrf
    <x-widget.file-upload id="cover" name="cover" label="Cover image" accept="image/*" max-size="25MB" />
    <x-widget.image-cropper for="cover" label="Crop the cover" aspect="16/9" :min-width="960" :max-width="1920" :max-height="1080" format="webp" class="max-w-2xl" />
</form>

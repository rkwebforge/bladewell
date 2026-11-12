{{-- A profile photo, cropped square before it's sent. Picking one opens it here instead of sending it; Apply puts the cropped photo in the file upload, which previews and sends that one. A phone photo stored sideways comes in upright, and the cropped file leaves its EXIF data, location included, behind. The preview needs img-src blob: in a Content Security Policy. --}}
<form method="POST" enctype="multipart/form-data" class="grid gap-6">
    @csrf
    <x-widget.file-upload.image id="profile-photo" name="profile_photo" label="Profile photo" accept="image/jpeg,image/png,image/webp" max-size="10MB" />
    <x-widget.image-cropper for="profile-photo" label="Crop your photo" shape="circle" :min-width="200" :max-width="512" :max-height="512" />
</form>

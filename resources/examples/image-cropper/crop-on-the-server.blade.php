{{-- An image you already store, cropped on the server: src is its URL, and the crop is sent as crop[x], crop[y], crop[width], crop[height] and crop[rotate], in the image's own pixels after it's turned upright and rotated. The original stays as it was, so the crop can be changed later. --}}
<form method="POST" class="grid gap-6">
    @csrf
    <x-widget.image-cropper :src="$photoUrl" name="crop" label="Crop the photo" aspect="4/3" />
</form>

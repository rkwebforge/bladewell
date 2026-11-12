{{-- In a Livewire component, bind the file upload with wire:model as usual: the picked photo opens in the cropper first, and only the cropped one is uploaded to the property. $this->photo is that file, ready for its validation rules and store(). --}}
<x-widget.file-upload.image id="photo" wire:model="photo" label="Profile photo" accept="image/jpeg,image/png,image/webp" max-size="10MB" />
<x-widget.image-cropper for="photo" label="Crop your photo" shape="circle" :max-width="512" :max-height="512" />

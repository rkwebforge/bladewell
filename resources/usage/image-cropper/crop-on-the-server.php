// With src, the crop arrives as numbers in the pixels of the image as shown, once it's upright (as its EXIF orientation
// says) and turned clockwise by rotate. Cut the stored file the same way: upright, then turned, then cropped, scaled by
// its own size over image_width, in case src was a resized copy. GD ignores EXIF, so the orientation is read with PHP's
// exif extension, which reads it from JPEG only: browsers also turn a WebP, PNG or HEIC by its EXIF, so store src as
// JPEG, or upright already. The crop is a new JPEG with no EXIF: no location, no date taken.
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

// 65,535 pixels is the most a JPEG, and GD, can be across: anything past it is no crop of a real image.
$crop = $request->validate([
    'crop.rotate' => ['required', 'integer', Rule::in([0, 90, 180, 270])],
    'crop.x' => ['required', 'integer', 'min:0', 'max:65535'],
    'crop.y' => ['required', 'integer', 'min:0', 'max:65535'],
    'crop.width' => ['required', 'integer', 'min:1', 'max:65535'],
    'crop.height' => ['required', 'integer', 'min:1', 'max:65535'],
    'crop.image_width' => ['required', 'integer', 'min:1', 'max:65535'],
    'crop.image_height' => ['required', 'integer', 'min:1', 'max:65535'],
])['crop'];

$path = Storage::disk('local')->path($photo->path);
// Check the size before decoding: a small file can open to a huge image and run out of memory. GD takes 4 bytes a
// pixel, twice over while it turns one: 25 megapixels needs a memory_limit of about 256M. Raise both together.
[$width, $height] = getimagesize($path) ?: [0, 0];
abort_if($width * $height === 0 || $width * $height > 25_000_000, 422, 'That image is too large to crop.');

$image = imagecreatefromstring((string) file_get_contents($path));
// A format getimagesize knows and GD can't open (HEIC), or a damaged file.
abort_if($image === false, 422, 'That image can\'t be cropped.');

// Upright first. imagerotate turns counterclockwise; the mirrored orientations are rare, from some scanners and apps.
$orientation = function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;
$image = match ($orientation) {
    3, 4 => imagerotate($image, 180, 0),
    5, 6 => imagerotate($image, -90, 0),
    7, 8 => imagerotate($image, 90, 0),
    default => $image,
};
if (in_array($orientation, [2, 4, 5, 7], true)) {
    imageflip($image, IMG_FLIP_HORIZONTAL);
}

// The stored file can be larger than what was shown (a thumbnail URL): scale the crop by the upright widths. Its shape
// must match what was shown, or the crop was made on a picture turned differently (a copy whose EXIF was dropped).
$shown = $crop['image_width'] / $crop['image_height'];
abort_if(abs(imagesx($image) / imagesy($image) - $shown) > 0.03 * $shown, 422, 'The crop was made on a different picture.');
$scale = imagesx($image) / $crop['image_width'];

// Then turned as the person turned it, clockwise, and cut, within the image whatever was sent.
$image = (int) $crop['rotate'] === 0 ? $image : imagerotate($image, -(int) $crop['rotate'], 0);
$x = min((int) round($crop['x'] * $scale), imagesx($image) - 1);
$y = min((int) round($crop['y'] * $scale), imagesy($image) - 1);
$image = imagecrop($image, [
    'x' => $x,
    'y' => $y,
    'width' => min((int) round($crop['width'] * $scale), imagesx($image) - $x),
    'height' => min((int) round($crop['height'] * $scale), imagesy($image) - $y),
]);
abort_if($image === false, 422, 'That image can\'t be cropped.');

// JPEG has no transparency: what's see-through goes on white, as the browser's crop does, not black.
$flat = imagecreatetruecolor(imagesx($image), imagesy($image));
imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
$image = $flat;

// A new file, never over the original, so the crop can be changed later.
ob_start();
imagejpeg($image, null, 85);
Storage::disk('local')->put($cropped = "photos/{$photo->id}-cropped.jpg", (string) ob_get_clean());

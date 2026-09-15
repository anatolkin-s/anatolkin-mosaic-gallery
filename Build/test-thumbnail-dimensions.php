<?php
declare(strict_types=1);

// Real extension services with a controlled Core processing boundary. ImageMagick
// scaling itself belongs to TYPO3; verify the exact Uri/ImageViewHelper instructions.
namespace TYPO3\CMS\Core\Resource {
    class File {
        public function __construct(public array $properties = []) {}
        public function hasProperty(string $key): bool { return array_key_exists($key, $this->properties); }
        public function getProperty(string $key): mixed { return $this->properties[$key] ?? null; }
        public function getUid(): int { return 1; }
        public function getMetaData(): object { return new class { public function get(): array { return ['title' => 'Caption', 'alternative' => 'Alt']; } }; }
    }
    class FileReference extends File {
        public function __construct(private File $original, array $properties) { parent::__construct($properties + $original->properties); }
        public function getOriginalFile(): File { return $this->original; }
    }
}
namespace TYPO3\CMS\Core\Imaging\ImageManipulation {
    class CropVariantCollection {
        public function __construct(private array $crop) {}
        public static function create(string $json): self { return new self($json === '' ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR)); }
        public function getCropArea(string $variant): object {
            if ($variant !== 'default') throw new \RuntimeException('Unexpected variant');
            return new class($this->crop['default']['cropArea'] ?? []) {
                public function __construct(private array $area) {}
                public function isEmpty(): bool { return $this->area === []; }
                public function makeAbsoluteBasedOnFile($file): array {
                    return ['x' => ($this->area['x'] ?? 0) * $file->getProperty('width'),
                        'y' => ($this->area['y'] ?? 0) * $file->getProperty('height'),
                        'width' => $this->makeCropBasedOnWidth($file->getProperty('width')),
                        'height' => $this->makeCropBasedOnHeight($file->getProperty('height'))];
                }
                public function makeCropBasedOnWidth($width): float { return $width * ($this->area['width'] ?? 1); }
                public function makeCropBasedOnHeight($height): float { return $height * ($this->area['height'] ?? 1); }
            };
        }
        public function getDefaultCropArea(): object { return $this->getCropArea('default'); }
    }
}
namespace TYPO3\CMS\Extbase\Service {
    class ImageService {
        public array $calls = [];
        public array $result = [];
        public bool $fail = false;
        public function applyProcessingInstructions($image, array $instructions): \TYPO3\CMS\Core\Resource\File {
            $this->calls[] = [$image, $instructions];
            if ($this->fail) throw new \RuntimeException('Processing unavailable');
            return new \TYPO3\CMS\Core\Resource\File($this->result);
        }
    }
}
namespace {
    use Anatolkin\MosaicGallery\Service\GalleryImageDimensionsResolver;
    use Anatolkin\MosaicGallery\Service\GalleryItemAssembler;
    use TYPO3\CMS\Core\Resource\File;
    use TYPO3\CMS\Core\Resource\FileReference;
    use TYPO3\CMS\Extbase\Service\ImageService;
    $root = dirname(__DIR__);
    require $root . '/Classes/Service/GalleryImageDimensionsResolver.php';
    require $root . '/Classes/Service/GalleryItemAssembler.php';
    require $root . '/Classes/Service/GalleryInheritedMetadataResolver.php';
    $failures = [];
    $assert = static function (bool $ok, string $message) use (&$failures): void { if (!$ok) $failures[] = $message; };
    $processor = new ImageService();
    $resolver = new GalleryImageDimensionsResolver($processor);
    foreach ([[2000, 1000, 1800, 1800, 900], [800, 600, 1800, 800, 600], [2000, 1333, 600, 600, 400]] as [$w, $h, $limit, $pw, $ph]) {
        $file = new File(['width' => $w, 'height' => $h]);
        $processor->result = ['width' => $pw, 'height' => $ph];
        $actual = $resolver->resolvePreviewDimensions($file, $limit);
        $assert($actual === ['previewWidth' => $pw, 'previewHeight' => $ph], 'Processed preview dimensions, including Core pixel rounding');
        [$image, $instructions] = end($processor->calls);
        $assert($image === $file && $instructions === ['width' => null, 'height' => null, 'minWidth' => null, 'minHeight' => null, 'maxWidth' => $limit, 'maxHeight' => null, 'crop' => null], 'Only maxWidth requested; no fixed/minimum size that could upscale');
    }
    $file = new File(['width' => 2000, 'height' => 1000]);
    $reference = new FileReference($file, ['title' => 'Reference caption', 'alternative' => 'Reference alt',
        'crop' => '{"default":{"cropArea":{"x":0.25,"y":0,"width":0.5,"height":1}}}']);
    $processor->result = ['width' => 600, 'height' => 600];
    $assert($resolver->resolvePreviewDimensions($file, 600, $reference) === ['previewWidth' => 600, 'previewHeight' => 600], 'Reference uses cropped square preview');
    [$image, $instructions] = end($processor->calls);
    $assert($image === $reference && $instructions['crop'] === ['x' => 500.0, 'y' => 0, 'width' => 1000.0, 'height' => 1000.0], 'Default crop converted to absolute source pixels');
    $assert($resolver->resolveAspectRatio($file, $reference) === 1.0, 'Crop-aware layout ratio retained');
    $processor->result = ['width' => 1000, 'height' => 1000];
    $assert($resolver->resolvePreviewDimensions($file, 1800, $reference) === ['previewWidth' => 1000, 'previewHeight' => 1000], 'Cropped source below maxWidth retains its processed size');
    $assembler = new GalleryItemAssembler($resolver);
    $processor->result = ['width' => 600, 'height' => 300];
    $folder = $assembler->assembleFromFiles([$file], [], [], 'masonry', false, 12, 600)[0];
    $processor->result = ['width' => 600, 'height' => 600];
    $manual = $assembler->assembleFromFileReferences([$reference], [], [], 'masonry', false, 12, 600)[0];
    $assert($folder['previewWidth'] === 600 && $folder['previewHeight'] === 300, 'Folder assembler exposes uncropped processed preview');
    $assert($manual['previewWidth'] === 600 && $manual['previewHeight'] === 600, 'Manual assembler exposes cropped processed preview');
    $assert($folder['alt'] === 'Alt' && $manual['alt'] === 'Reference alt', 'Metadata preserved');
    $assert($manual['renderFile'] === $reference, 'Rendering/lightbox retains FileReference');
    foreach ([[], ['width' => 0, 'height' => 600], ['width' => 600, 'height' => -1]] as $invalid) {
        $processor->result = $invalid;
        $assert($resolver->resolvePreviewDimensions(new File(), 1800) === [], 'Unresolved derivative emits no dimensions');
    }
    $processor->fail = true;
    $assert($resolver->resolvePreviewDimensions($file, 600) === [], 'Processing exception does not fabricate dimensions');
    $unknown = $assembler->assembleFromFiles([new File()], [], [], 'grid', false, 12, 600)[0];
    $assert(!isset($unknown['previewWidth'], $unknown['previewHeight']), 'Assembler omits unavailable geometry');
    $template = file_get_contents($root . '/Resources/Private/Templates/Gallery/List.html');
    $assert(substr_count($template, 'loading="lazy"') === 2 && !str_contains($template, "else: 'eager'"), 'Both dimension/fallback branches use native lazy loading');
    $assert(str_contains($template, '<f:if condition="{it.previewWidth} && {it.previewHeight}">') && str_contains($template, 'width="{it.previewWidth}" height="{it.previewHeight}"'), 'Intrinsic attributes guarded and use preview geometry');
    $assert(substr_count($template, 'data-src="{f:if(condition: it.hidden, then: previewUrl)}"') === 2, 'Deferred Load More URLs retained');
    $assert(str_contains($template, 'f:uri.image(image: it.renderFile, maxWidth: maxWidth)') && str_contains($template, 'f:uri.image(image: it.renderFile)}'), 'Preview and lightbox processing remain distinct');
    $controller = file_get_contents($root . '/Classes/Controller/GalleryController.php');
    $assert(!str_contains(strtolower($controller), 'imagesloaded'), 'AssetCollector does not register imagesLoaded');
    if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
    echo "Thumbnail processing boundary, assembler and HTML contracts: PASS\n";
}

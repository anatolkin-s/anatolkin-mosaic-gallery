<?php
declare(strict_types=1);

/**
 * Executable regression tests for GalleryFlexFormSourceReader FormEngine shapes.
 * Run: php Build/test-flexform-source-reader.php
 */

$root = dirname(__DIR__);
require_once $root . '/Classes/Service/GalleryFlexFormSourceReader.php';

use Anatolkin\MosaicGallery\Service\GalleryFlexFormSourceReader;

$reader = new GalleryFlexFormSourceReader();
$failures = [];

set_error_handler(
    static function (int $severity, string $message, string $file, int $line): bool {
        if (($severity & (E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE)) !== 0) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }

        return false;
    },
);

try {
    $assertSource = static function (
        string $case,
        mixed $flexForm,
        string $expected,
    ) use ($reader, &$failures): void {
        try {
            $actual = $reader->readSource($flexForm);
            if ($actual !== $expected) {
                $failures[] = $case . ': expected readSource=' . $expected . ', got ' . $actual;
            }
        } catch (\ErrorException $exception) {
            $failures[] = $case . ': unexpected PHP warning/notice: ' . $exception->getMessage();
        }
    };

    $assertSettings = static function (
        string $case,
        mixed $flexForm,
        array $expected,
    ) use ($reader, &$failures): void {
        try {
            $actual = $reader->readSettings($flexForm);
            foreach ($expected as $key => $value) {
                if (!\array_key_exists($key, $actual)) {
                    $failures[] = $case . ': missing settings key ' . $key;
                    continue;
                }
                if ($actual[$key] !== $value) {
                    $failures[] = $case . ': expected ' . $key . '=' . var_export($value, true)
                        . ', got ' . var_export($actual[$key], true);
                }
            }
        } catch (\ErrorException $exception) {
            $failures[] = $case . ': unexpected PHP warning/notice: ' . $exception->getMessage();
        }
    };

    $scalarSettings = [
        'source' => 'manual',
        'folder' => '1:/gallery/',
        'recursive' => '1',
        'sortBy' => 'name',
        'sortDir' => 'asc',
        'captions' => '',
        'useFalCaptions' => '1',
    ];

    $arraySettings = [
        'source' => ['manual'],
        'folder' => ['1:/gallery/'],
        'recursive' => ['1'],
        'sortBy' => ['name'],
        'sortDir' => ['asc'],
        'captions' => [''],
        'useFalCaptions' => ['1'],
    ];

    $vDefSettings = [
        'source' => ['vDEF' => 'manual'],
        'folder' => ['vDEF' => '1:/gallery/'],
        'recursive' => ['vDEF' => '1'],
        'sortBy' => ['vDEF' => 'name'],
        'sortDir' => ['vDEF' => 'asc'],
        'captions' => ['vDEF' => ''],
        'useFalCaptions' => ['vDEF' => '1'],
    ];

    // CASE A — scalar manual
    $assertSource('CASE A', ['settings' => ['source' => 'manual']], GalleryFlexFormSourceReader::SOURCE_MANUAL);

    // CASE B — ['manual']
    $assertSource('CASE B', ['settings' => ['source' => ['manual']]], GalleryFlexFormSourceReader::SOURCE_MANUAL);

    // CASE C — ['vDEF' => 'manual']
    $assertSource('CASE C', ['settings' => ['source' => ['vDEF' => 'manual']]], GalleryFlexFormSourceReader::SOURCE_MANUAL);

    // CASE D — missing source
    $assertSource('CASE D', ['settings' => []], GalleryFlexFormSourceReader::SOURCE_FOLDER);

    // CASE E — invalid/ambiguous source array
    $assertSource('CASE E', ['settings' => ['source' => ['manual', 'folder']]], GalleryFlexFormSourceReader::SOURCE_FOLDER);

    // CASE F — full FormEngine-shaped readSettings()
    $assertSettings('CASE F scalar', ['settings' => $scalarSettings], [
        'source' => GalleryFlexFormSourceReader::SOURCE_MANUAL,
        'folder' => '1:/gallery/',
        'recursive' => true,
        'sortBy' => 'name',
        'sortDir' => 'asc',
        'captions' => '',
        'useFalCaptions' => true,
    ]);
    $assertSettings('CASE F array', ['settings' => $arraySettings], [
        'source' => GalleryFlexFormSourceReader::SOURCE_MANUAL,
        'folder' => '1:/gallery/',
        'recursive' => true,
        'sortBy' => 'name',
        'sortDir' => 'asc',
        'captions' => '',
        'useFalCaptions' => true,
    ]);
    $assertSettings('CASE F vDEF', ['settings' => $vDefSettings], [
        'source' => GalleryFlexFormSourceReader::SOURCE_MANUAL,
        'folder' => '1:/gallery/',
        'recursive' => true,
        'sortBy' => 'name',
        'sortDir' => 'asc',
        'captions' => '',
        'useFalCaptions' => true,
    ]);

    // CASE G — folder mode
    $assertSource(
        'CASE G',
        ['settings' => ['source' => 'folder', 'folder' => '1:/gallery/']],
        GalleryFlexFormSourceReader::SOURCE_FOLDER,
    );
    $assertSettings('CASE G settings', ['settings' => ['source' => ['folder'], 'folder' => ['1:/gallery/']]], [
        'source' => GalleryFlexFormSourceReader::SOURCE_FOLDER,
        'folder' => '1:/gallery/',
    ]);

    // Raw FlexForm vDEF structure must continue working.
    $rawFlexForm = [
        'data' => [
            'sDEF' => [
                'lDEF' => [
                    'settings.source' => ['vDEF' => 'manual'],
                    'settings.folder' => ['vDEF' => '1:/gallery/'],
                ],
            ],
        ],
    ];
    $assertSource('CASE raw FlexForm', $rawFlexForm, GalleryFlexFormSourceReader::SOURCE_MANUAL);
} finally {
    restore_error_handler();
}

if ($failures !== []) {
    fwrite(STDERR, "FlexForm source reader regression tests failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "FlexForm source reader regression tests passed.\n");
exit(0);

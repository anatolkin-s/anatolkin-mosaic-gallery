<?php
declare(strict_types=1);

/**
 * Contract check: every FlexForm field under <el> must declare exclude=true.
 * Run: php Build/check-flexform-exclude-fields.php
 */

$root = dirname(__DIR__);
$flexFormPath = $root . '/Configuration/FlexForms/MosaicGallery.xml';

if (!is_file($flexFormPath)) {
    fwrite(STDERR, "FLEXFORM_EXCLUDE_FIELDS: FAIL\n");
    fwrite(STDERR, "Missing file: {$flexFormPath}\n");
    exit(1);
}

$dom = new DOMDocument();
$dom->preserveWhiteSpace = true;
if (!@$dom->load($flexFormPath)) {
    fwrite(STDERR, "FLEXFORM_EXCLUDE_FIELDS: FAIL\n");
    fwrite(STDERR, "XML parse failed: {$flexFormPath}\n");
    exit(1);
}

$xpath = new DOMXPath($dom);
$sheetNodes = $xpath->query('/T3DataStructure/sheets/*');
if ($sheetNodes === false) {
    fwrite(STDERR, "FLEXFORM_EXCLUDE_FIELDS: FAIL\n");
    fwrite(STDERR, "Unable to query FlexForm sheets\n");
    exit(1);
}

$sheetCount = 0;
$fieldCount = 0;
$excludedCount = 0;
$failures = [];

foreach ($sheetNodes as $sheetNode) {
    if (!$sheetNode instanceof DOMElement) {
        continue;
    }
    $sheetCount++;

    $elNodes = $xpath->query('.//el', $sheetNode);
    if ($elNodes === false) {
        $failures[] = 'Unable to query <el> nodes in sheet ' . $sheetNode->nodeName;
        continue;
    }

    foreach ($elNodes as $elNode) {
        if (!$elNode instanceof DOMElement) {
            continue;
        }

        foreach ($elNode->childNodes as $fieldNode) {
            if (!$fieldNode instanceof DOMElement) {
                continue;
            }

            $configNodes = [];
            foreach ($fieldNode->childNodes as $child) {
                if ($child instanceof DOMElement && $child->nodeName === 'config') {
                    $configNodes[] = $child;
                }
            }

            if ($configNodes === []) {
                continue;
            }

            $fieldCount++;
            $fieldName = $fieldNode->nodeName;
            $excludeNodes = [];
            foreach ($fieldNode->childNodes as $child) {
                if ($child instanceof DOMElement && $child->nodeName === 'exclude') {
                    $excludeNodes[] = $child;
                }
            }

            if ($excludeNodes === []) {
                $failures[] = "Field {$fieldName} is missing <exclude>";
                continue;
            }

            if (count($excludeNodes) > 1) {
                $failures[] = "Field {$fieldName} has more than one <exclude>";
                continue;
            }

            $excludeValue = trim($excludeNodes[0]->textContent);
            if ($excludeValue !== 'true') {
                $failures[] = "Field {$fieldName} has invalid exclude value: {$excludeValue}";
                continue;
            }

            $excludedCount++;
        }
    }
}

if ($fieldCount === 0) {
    $failures[] = 'No FlexForm fields found under <el>';
}

if ($failures !== []) {
    fwrite(STDERR, "FLEXFORM_EXCLUDE_FIELDS: FAIL\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
    fwrite(STDERR, "SHEETS={$sheetCount}\n");
    fwrite(STDERR, "FIELDS={$fieldCount}\n");
    fwrite(STDERR, "EXCLUDED_FIELDS={$excludedCount}\n");
    exit(1);
}

fwrite(STDOUT, "FLEXFORM_EXCLUDE_FIELDS: PASS\n");
fwrite(STDOUT, "SHEETS={$sheetCount}\n");
fwrite(STDOUT, "FIELDS={$fieldCount}\n");
fwrite(STDOUT, "EXCLUDED_FIELDS={$excludedCount}\n");
exit(0);

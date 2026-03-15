<?php
/**
 * Compress a PDF to a desired quality level using Ghostscript.
 *
 * Usage (CLI):
 *   php compress-pdf.php input.pdf output.pdf [quality] [dpi] [jpegQ]
 *   php compress-pdf.php in.pdf out.pdf ebook 110 80
 *
 * Quality presets: screen | ebook | printer | prepress | default
 * If you pass dpi/jpegQ, they override the preset's downsampling and JPEG quality.
 */

function compressPdf(
    string $input,
    string $output,
    string $quality = 'ebook',
    ?int $dpi = null,
    ?int $jpegQ = null,
    ?string $gsBinary = null // set to 'C:\\Program Files\\gs\\10.03.1\\bin\\gswin64c.exe' on Windows if PATH not set
): array {
    if (!is_readable($input)) {
        return [false, "Input file not readable: $input"];
    }
    $outDir = dirname($output);
    if (!is_dir($outDir) || !is_writable($outDir)) {
        return [false, "Output directory not writable: $outDir"];
    }

    // Resolve Ghostscript binary
    if ($gsBinary === null) {
        // Linux/macOS default
        $gsBinary = stripos(PHP_OS, 'WIN') === 0 ? 'gswin64c' : 'gs';
    }

    // Map quality to Ghostscript PDFSETTINGS and default DPIs
    $quality = strtolower($quality);
    $presetMap = [
        'screen'   => ['/screen',   72],
        'ebook'    => ['/ebook',   120],
        'printer'  => ['/printer', 300],
        'prepress' => ['/prepress',300],
        'default'  => ['/default', 150],
    ];
    if (!isset($presetMap[$quality])) {
        return [false, "Unknown quality preset '$quality'. Use screen|ebook|printer|prepress|default."];
    }
    [$pdfSettings, $defaultDpi] = $presetMap[$quality];

    // Effective parameters
    $effectiveDpi  = $dpi   ?? $defaultDpi;
    $effectiveJPEG = $jpegQ ?? 85; // 1..100, higher = better quality/larger size

    // Build Ghostscript command
    // Notes:
    // -dPDFSETTINGS controls a bundle of settings, but we override image downsampling + JPEG quality explicitly.
    // -dAutoFilter* = false + *Filter=/DCTEncode makes JPEG quality honored.
    // Vector/text objects are kept; images are recompressed/downsampled.
    $cmd = [
        $gsBinary,
        '-sDEVICE=pdfwrite',
        '-dCompatibilityLevel=1.5',
        '-dPDFSETTINGS=' . $pdfSettings,
        '-dDetectDuplicateImages=true',
        '-dCompressFonts=true',
        '-dSubsetFonts=true',
        '-dFastWebView=true',          // linearize when possible
        '-dColorImageDownsampleType=/Bicubic',
        '-dGrayImageDownsampleType=/Bicubic',
        '-dMonoImageDownsampleType=/Subsample',
        '-dColorImageResolution=' . (int)$effectiveDpi,
        '-dGrayImageResolution='  . (int)$effectiveDpi,
        '-dMonoImageResolution='  . max(300, (int)$effectiveDpi), // keep mono higher for readability
        '-dAutoFilterColorImages=false',
        '-dAutoFilterGrayImages=false',
        '-dColorImageFilter=/DCTEncode',
        '-dGrayImageFilter=/DCTEncode',
        '-dJPEGQ=' . (int)$effectiveJPEG,
        '-dDownsampleColorImages=true',
        '-dDownsampleGrayImages=true',
        '-dDownsampleMonoImages=true',
        '-dNOPAUSE',
        '-dQUIET',
        '-dBATCH',
        '-sOutputFile=' . escapeshellarg($output),
        escapeshellarg($input)
    ];

    // On Windows, add .exe if needed
    if (stripos(PHP_OS, 'WIN') === 0 && substr($cmd[0], -4) !== '.exe') {
        $cmd[0] .= '.exe';
    }

    // Verify Ghostscript is available
    $checkCmd = escapeshellcmd($cmd[0]) . ' -v';
    @exec($checkCmd . ' 2>&1', $vout, $vcode);
    if ($vcode !== 0) {
        return [false, "Ghostscript not found or not executable. Tried: {$cmd[0]}"];
    }

    // Execute
    $full = implode(' ', array_map(function ($part) {
        // Already shell-safe for output & input; others don't need quoting.
        return $part;
    }, $cmd));

    exec($full . ' 2>&1', $outputLines, $code);

    if ($code !== 0 || !file_exists($output) || filesize($output) === 0) {
        $msg = "Ghostscript failed (code $code). Output:\n" . implode("\n", $outputLines);
        return [false, $msg];
    }

    // Return success + before/after sizes
    $before = filesize($input);
    $after  = filesize($output);
    $ratio  = $before > 0 ? round(($after / $before) * 100, 1) : null;

    return [true, "OK: $before bytes → $after bytes (" . $ratio . "%) using $quality @ {$effectiveDpi}dpi, JPEGQ {$effectiveJPEG}"];
}

// ---------- CLI usage ----------
if (PHP_SAPI === 'cli' && realpath($argv[0]) === __FILE__) {
    if ($argc < 3) {
        fwrite(STDERR, "Usage: php {$argv[0]} input.pdf output.pdf [quality] [dpi] [jpegQ]\n");
        exit(2);
    }
    $input  = $argv[1];
    $output = $argv[2];
    $quality = $argv[3] ?? 'ebook';
    $dpi     = isset($argv[4]) ? (int)$argv[4] : null;
    $jpegQ   = isset($argv[5]) ? (int)$argv[5] : null;

    [$ok, $msg] = compressPdf($input, $output, $quality, $dpi, $jpegQ);

    if (!$ok) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    echo $msg . PHP_EOL;
}
?>
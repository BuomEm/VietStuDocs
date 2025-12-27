<?php
/**
 * Test script to check available PDF processing tools
 * Run this to see which tools are available on your system
 */

echo "<h2>PDF Processing Tools Check</h2>";

// Check Imagick
echo "<h3>1. Imagick Extension</h3>";
if (extension_loaded('imagick')) {
    echo "✅ Imagick extension is loaded<br>";
    if (class_exists('Imagick')) {
        echo "✅ Imagick class is available<br>";
        try {
            $imagick = new Imagick();
            $version = $imagick->getVersion();
            echo "✅ Imagick version: " . $version['versionString'] . "<br>";
        } catch (Exception $e) {
            echo "❌ Error creating Imagick: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Imagick class not found<br>";
    }
} else {
    echo "❌ Imagick extension is NOT loaded<br>";
    echo "   To install: Enable imagick extension in php.ini<br>";
}

// Check Ghostscript
echo "<h3>2. Ghostscript</h3>";
$gs_commands = [
    "gswin64c -v 2>&1",
    "gswin32c -v 2>&1",
    "C:\\Program Files\\gs\\gs10.00.0\\bin\\gswin64c.exe -v 2>&1"
];

$gs_found = false;
foreach ($gs_commands as $cmd) {
    $output = @shell_exec($cmd);
    if ($output && (strpos($output, 'GPL Ghostscript') !== false || strpos($output, 'version') !== false)) {
        echo "✅ Ghostscript found: $cmd<br>";
        echo "   Output: " . htmlspecialchars(substr($output, 0, 100)) . "<br>";
        $gs_found = true;
        break;
    }
}
if (!$gs_found) {
    echo "❌ Ghostscript not found<br>";
    echo "   To install: Download from https://www.ghostscript.com/download/gsdnld.html<br>";
}

// Check pdfinfo (poppler-utils)
echo "<h3>3. pdfinfo (Poppler-utils)</h3>";
$pdfinfo_commands = [
    "pdfinfo -v 2>&1",
    "C:\\Program Files\\poppler\\bin\\pdfinfo.exe -v 2>&1",
    "C:\\poppler\\bin\\pdfinfo.exe -v 2>&1"
];

$pdfinfo_found = false;
foreach ($pdfinfo_commands as $cmd) {
    $output = @shell_exec($cmd);
    if ($output && (strpos($output, 'pdfinfo') !== false || strpos($output, 'version') !== false)) {
        echo "✅ pdfinfo found: $cmd<br>";
        echo "   Output: " . htmlspecialchars(substr($output, 0, 100)) . "<br>";
        $pdfinfo_found = true;
        break;
    }
}
if (!$pdfinfo_found) {
    echo "❌ pdfinfo not found<br>";
    echo "   To install: Download Poppler for Windows from https://github.com/oschwartz10612/poppler-windows/releases/<br>";
}

// Check pdftoppm (poppler-utils)
echo "<h3>4. pdftoppm (Poppler-utils)</h3>";
$pdftoppm_commands = [
    "pdftoppm -v 2>&1",
    "C:\\Program Files\\poppler\\bin\\pdftoppm.exe -v 2>&1",
    "C:\\poppler\\bin\\pdftoppm.exe -v 2>&1"
];

$pdftoppm_found = false;
foreach ($pdftoppm_commands as $cmd) {
    $output = @shell_exec($cmd);
    if ($output && (strpos($output, 'pdftoppm') !== false || strpos($output, 'version') !== false)) {
        echo "✅ pdftoppm found: $cmd<br>";
        echo "   Output: " . htmlspecialchars(substr($output, 0, 100)) . "<br>";
        $pdftoppm_found = true;
        break;
    }
}
if (!$pdftoppm_found) {
    echo "❌ pdftoppm not found<br>";
    echo "   To install: Download Poppler for Windows from https://github.com/oschwartz10612/poppler-windows/releases/<br>";
}

// Check GD extension (for image thumbnails)
echo "<h3>5. GD Extension (for image thumbnails)</h3>";
if (extension_loaded('gd')) {
    echo "✅ GD extension is loaded<br>";
    $gd_info = gd_info();
    echo "   Version: " . $gd_info['GD Version'] . "<br>";
} else {
    echo "❌ GD extension is NOT loaded<br>";
    echo "   To install: Enable gd extension in php.ini<br>";
}

// Summary
echo "<h3>Summary</h3>";
$tools_available = [];
if (extension_loaded('imagick') && class_exists('Imagick')) {
    $tools_available[] = "Imagick";
}
if ($gs_found) {
    $tools_available[] = "Ghostscript";
}
if ($pdfinfo_found) {
    $tools_available[] = "pdfinfo";
}
if ($pdftoppm_found) {
    $tools_available[] = "pdftoppm";
}

if (empty($tools_available)) {
    echo "⚠️ <strong>No PDF processing tools found!</strong><br>";
    echo "You need to install at least one of the following:<br>";
    echo "- Imagick PHP extension (recommended)<br>";
    echo "- Ghostscript<br>";
    echo "- Poppler-utils (pdfinfo + pdftoppm)<br>";
} else {
    echo "✅ Available tools: " . implode(", ", $tools_available) . "<br>";
    echo "Your PDF processing should work!<br>";
}
?>

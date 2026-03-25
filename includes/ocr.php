<?php
/**
 * Swappable OCR Layer for E-Doc School Order generation.
 *
 * Current implementation: Tesseract CLI (local).
 * To switch to Google Vision: replace the body of extract_fields()
 * with a Google Vision API call — same signature, same return format.
 */

// Tesseract path (UB Mannheim Windows build)
define('TESSERACT_PATH', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe');

// Ghostscript path (for PDF-to-image conversion)
define('GHOSTSCRIPT_PATH', 'C:\\Program Files\\gs\\gs10.04.0\\bin\\gswin64c.exe');

/**
 * Extract structured fields from an uploaded file using OCR.
 *
 * @param  string $file_path  Absolute path to a PDF or image file.
 * @return array  ['raw_text' => string, 'fields' => [...], 'error' => string|null]
 */
function extract_fields(string $file_path): array
{
    $result = [
        'raw_text' => '',
        'fields'   => [
            'student_name'      => ['value' => '', 'confidence' => 'low'],
            'student_id'        => ['value' => '', 'confidence' => 'low'],
            'course_program'    => ['value' => '', 'confidence' => 'low'],
            'date_of_graduation'=> ['value' => '', 'confidence' => 'low'],
        ],
        'error' => null,
    ];

    if (!file_exists($file_path)) {
        $result['error'] = 'Source file not found.';
        return $result;
    }

    // Check Tesseract is installed
    if (!file_exists(TESSERACT_PATH)) {
        $result['error'] = 'Tesseract OCR is not installed at the configured path. You may enter the fields manually.';
        return $result;
    }

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    try {
        if ($ext === 'pdf') {
            $raw = ocr_pdf($file_path);
        } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'])) {
            $raw = ocr_image($file_path);
        } else {
            $result['error'] = 'Unsupported file type for OCR: ' . $ext;
            return $result;
        }
    } catch (\Exception $e) {
        $result['error'] = 'OCR processing failed: ' . $e->getMessage();
        return $result;
    }

    $result['raw_text'] = $raw;

    if (trim($raw) === '') {
        $result['error'] = 'OCR produced no text. The file may be blank or unreadable.';
        return $result;
    }

    // Parse structured fields from raw text
    $result['fields'] = parse_ocr_fields($raw);

    return $result;
}

/**
 * Run OCR on a PDF by converting pages to images first via Ghostscript.
 */
function ocr_pdf(string $pdf_path): string
{
    if (!file_exists(GHOSTSCRIPT_PATH)) {
        throw new \RuntimeException('Ghostscript is not installed. Cannot process PDF files for OCR.');
    }

    $tmp_dir = sys_get_temp_dir() . '/edoc_ocr_' . bin2hex(random_bytes(8));
    if (!mkdir($tmp_dir, 0755, true)) {
        throw new \RuntimeException('Could not create temp directory for OCR.');
    }

    try {
        // Convert PDF pages to PNG images
        $gs_cmd = sprintf(
            '%s -dNOPAUSE -dBATCH -sDEVICE=png16m -r300 -sOutputFile=%s %s 2>&1',
            escapeshellarg(GHOSTSCRIPT_PATH),
            escapeshellarg($tmp_dir . '/page_%03d.png'),
            escapeshellarg($pdf_path)
        );

        exec($gs_cmd, $gs_output, $gs_code);
        if ($gs_code !== 0) {
            throw new \RuntimeException('Ghostscript failed: ' . implode("\n", $gs_output));
        }

        // OCR each page image and concatenate
        $pages = glob($tmp_dir . '/page_*.png');
        sort($pages);

        $all_text = '';
        foreach ($pages as $page_img) {
            $all_text .= ocr_image($page_img) . "\n\n";
        }

        return trim($all_text);
    } finally {
        // Cleanup temp files
        $files = glob($tmp_dir . '/*');
        if ($files) {
            foreach ($files as $f) @unlink($f);
        }
        @rmdir($tmp_dir);
    }
}

/**
 * Run Tesseract OCR on a single image file.
 */
function ocr_image(string $image_path): string
{
    $cmd = sprintf(
        '%s %s stdout 2>&1',
        escapeshellarg(TESSERACT_PATH),
        escapeshellarg($image_path)
    );

    exec($cmd, $output, $code);

    if ($code !== 0) {
        throw new \RuntimeException('Tesseract failed (exit ' . $code . '): ' . implode("\n", $output));
    }

    return implode("\n", $output);
}

/**
 * Parse raw OCR text to extract structured fields with confidence levels.
 */
function parse_ocr_fields(string $raw): array
{
    $fields = [
        'student_name'       => ['value' => '', 'confidence' => 'low'],
        'student_id'         => ['value' => '', 'confidence' => 'low'],
        'course_program'     => ['value' => '', 'confidence' => 'low'],
        'date_of_graduation' => ['value' => '', 'confidence' => 'low'],
    ];

    // Student Name — look for "Name:" or "Student Name:" patterns
    if (preg_match('/(?:student\s*)?name\s*[:\-]\s*(.+)/i', $raw, $m)) {
        $fields['student_name'] = ['value' => trim($m[1]), 'confidence' => 'high'];
    } elseif (preg_match('/(?:full\s*name|nombre)\s*[:\-]\s*(.+)/i', $raw, $m)) {
        $fields['student_name'] = ['value' => trim($m[1]), 'confidence' => 'medium'];
    }

    // Student ID — look for patterns like "ID: 2022-10042" or "Student No."
    if (preg_match('/(?:student\s*(?:id|no|number)|id\s*(?:no|number|num))\s*[:\.\-]\s*([\d\-]+)/i', $raw, $m)) {
        $fields['student_id'] = ['value' => trim($m[1]), 'confidence' => 'high'];
    } elseif (preg_match('/\b(\d{4}[\-]\d{4,6})\b/', $raw, $m)) {
        $fields['student_id'] = ['value' => trim($m[1]), 'confidence' => 'medium'];
    }

    // Course/Program — look for "Course:", "Program:", "Degree:"
    if (preg_match('/(?:course|program|degree|major)\s*[:\-]\s*(.+)/i', $raw, $m)) {
        $fields['course_program'] = ['value' => trim($m[1]), 'confidence' => 'high'];
    } elseif (preg_match('/\b(B\.?S\.?\s+\w[\w\s]{3,40})/i', $raw, $m)) {
        $fields['course_program'] = ['value' => trim($m[1]), 'confidence' => 'medium'];
    }

    // Date of Graduation — look for "Graduated:", "Date of Graduation:", year patterns
    if (preg_match('/(?:date\s*of\s*graduation|graduated|graduation\s*date)\s*[:\-]\s*(.+)/i', $raw, $m)) {
        $fields['date_of_graduation'] = ['value' => trim($m[1]), 'confidence' => 'high'];
    } elseif (preg_match('/(?:S\.?Y\.?|school\s*year|A\.?Y\.?)\s*[:\-]?\s*([\d]{4}\s*[\-–]\s*[\d]{4})/i', $raw, $m)) {
        $fields['date_of_graduation'] = ['value' => trim($m[1]), 'confidence' => 'medium'];
    }

    return $fields;
}

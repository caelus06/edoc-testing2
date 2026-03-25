<?php
/**
 * One-time script: generates templates/so_template.docx with PHPWord placeholders.
 * Run from project root: php templates/create_so_template.php
 */
require_once __DIR__ . '/../lib/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

$phpWord = new PhpWord();

// Default font
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(12);

$section = $phpWord->addSection([
    'marginTop'    => 1440,  // 1 inch
    'marginBottom' => 1440,
    'marginLeft'   => 1440,
    'marginRight'  => 1440,
]);

// Header — School Name
$section->addText(
    'Universidad de Dagupan',
    ['bold' => true, 'size' => 18],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
);
$section->addText(
    'Office of the Registrar',
    ['bold' => true, 'size' => 14],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
);

$section->addText('');

// SO Number
$section->addText(
    'SCHOOL ORDER NO. ${so_number}',
    ['bold' => true, 'size' => 14],
    ['alignment' => Jc::CENTER, 'spaceAfter' => 400]
);

$section->addText('');

// Body
$section->addText(
    'By the order of the Board of Trustees, ${student_name}, with Student ID ${student_id}, of the ${course_program} program, is hereby granted the issuance of the requested ${document_type}.',
    ['size' => 12],
    ['alignment' => Jc::BOTH, 'spaceAfter' => 200]
);

$section->addText('');

// Details
$section->addText('Date of Graduation: ${date_of_graduation}', ['size' => 12]);
$section->addText('Date Issued: ${date_issued}', ['size' => 12]);

$section->addText('');
$section->addText('');

// Additional notes (if any)
$section->addText('${additional_notes}', ['size' => 11, 'italic' => true]);

$section->addText('');
$section->addText('');
$section->addText('');

// Signature block
$section->addText(
    '________________________________',
    ['size' => 12],
    ['alignment' => Jc::RIGHT]
);
$section->addText(
    '${registrar_name}',
    ['bold' => true, 'size' => 12],
    ['alignment' => Jc::RIGHT]
);
$section->addText(
    'Registrar',
    ['size' => 11],
    ['alignment' => Jc::RIGHT]
);
$section->addText(
    'Universidad de Dagupan',
    ['size' => 11],
    ['alignment' => Jc::RIGHT]
);

$output = __DIR__ . '/so_template.docx';
$phpWord->save($output, 'Word2007');

echo "Template created: {$output}\n";

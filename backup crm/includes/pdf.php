<?php
declare(strict_types=1);

function pdf_literal(string $value): string {
    $ascii = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
    if ($ascii === false) $ascii = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $ascii);
}

function pdf_text(array &$commands, float $x, float $y, string $text, int $size=10, bool $bold=false): void {
    $font = $bold ? 'F2' : 'F1';
    $commands[] = "BT /$font $size Tf 1 0 0 1 $x $y Tm (" . pdf_literal($text) . ") Tj ET";
}

function pdf_rule(array &$commands, float $y): void { $commands[] = "0.82 G 50 $y m 545 $y l S 0 G"; }

function build_document_pdf(array $pages): string {
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
    $pageRefs = [];
    $nextId = 5;
    foreach ($pages as $commands) {
        $pageId = $nextId++;
        $contentId = $nextId++;
        $stream = implode("\n", $commands) . "\n";
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        $objects[$contentId] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . 'endstream';
        $pageRefs[] = $pageId . ' 0 R';
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pages) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n%BUZNET\n";
    $offsets = [0];
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $size = max(array_keys($objects)) + 1;
    $pdf .= "xref\n0 $size\n0000000000 65535 f \n";
    for ($id=1; $id<$size; $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
    $pdf .= "trailer\n<< /Size $size /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF";
    return $pdf;
}

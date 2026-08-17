<?php
declare(strict_types=1);

/**
 * Soma Cashflow - Minimal PDF writer (Phase 4)
 *
 * A tiny, dependency-free PDF generator built directly on PDF primitives
 * (standard Helvetica fonts, no embedding needed). No Composer, no
 * external library, no network access required - works on any plain
 * PHP/XAMPP setup.
 *
 * Only supports what a financial statement needs: titles, section
 * headers, two-column label/value rows (with optional indent/bold/color),
 * horizontal rules, and automatic page breaks.
 */
class SimplePdf
{
    private const PAGE_WIDTH = 595.28;  // A4 in points
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN_X = 50;
    private const MARGIN_TOP = 60;
    private const MARGIN_BOTTOM = 50;

    private array $pages = [];      // array of content strings, one per page
    private string $buffer = '';
    private float $y;
    private string $title = 'Statement';

    public function __construct()
    {
        $this->y = self::PAGE_HEIGHT - self::MARGIN_TOP;
        $this->buffer = '';
    }

    private function esc(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->y - $needed < self::MARGIN_BOTTOM) {
            $this->pages[] = $this->buffer;
            $this->buffer = '';
            $this->y = self::PAGE_HEIGHT - self::MARGIN_TOP;
        }
    }

    private function text(float $x, string $text, float $size, string $font = 'F1'): void
    {
        $this->buffer .= sprintf(
            "BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET\n",
            $font, $size, $x, $this->y, $this->esc($text)
        );
    }

    private function textRight(float $rightX, string $text, float $size, string $font = 'F1'): void
    {
        // Approximate width for right-alignment (Helvetica average glyph width ~0.5em, bold ~0.55em)
        $avgWidth = ($font === 'F2') ? 0.56 : 0.5;
        $width = strlen($text) * $size * $avgWidth;
        $this->text($rightX - $width, $text, $size, $font);
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function addTitle(string $text): void
    {
        $this->ensureSpace(30);
        $this->text(self::MARGIN_X, $text, 16, 'F2');
        $this->y -= 24;
    }

    public function addSubtitle(string $text): void
    {
        $this->ensureSpace(18);
        $this->text(self::MARGIN_X, $text, 10, 'F1');
        $this->y -= 16;
    }

    public function addSpacer(float $h = 10): void
    {
        $this->ensureSpace($h);
        $this->y -= $h;
    }

    public function addSectionHeader(string $text): void
    {
        $this->ensureSpace(22);
        $this->y -= 4;
        $this->text(self::MARGIN_X, $text, 11.5, 'F2');
        $this->y -= 6;
        $this->hr();
        $this->y -= 10;
    }

    public function hr(): void
    {
        $this->buffer .= sprintf(
            "%.2f %.2f m %.2f %.2f l S\n",
            self::MARGIN_X, $this->y, self::PAGE_WIDTH - self::MARGIN_X, $this->y
        );
    }

    /**
     * Two-column row: label on the left, value right-aligned.
     */
    public function addRow(string $label, string $value, bool $bold = false, int $indent = 0, bool $topRule = false): void
    {
        $this->ensureSpace(16 + ($topRule ? 6 : 0));
        if ($topRule) {
            $this->y -= 4;
            $this->hr();
            $this->y -= 8;
        }
        $font = $bold ? 'F2' : 'F1';
        $this->text(self::MARGIN_X + $indent, $label, 10, $font);
        $this->textRight(self::PAGE_WIDTH - self::MARGIN_X, $value, 10, $font);
        $this->y -= 15;
    }

    /**
     * Table with columns: entity/category label + up to 3 right-aligned numeric columns.
     * $headers = ['Entity','Income','Expenses','Net']; $rows = [['Personal','50,000.00','12,000.00','38,000.00'], ...]
     */
    public function addTable(array $headers, array $rows, ?array $totalRow = null): void
    {
        $colX = [self::MARGIN_X, self::PAGE_WIDTH - self::MARGIN_X - 260, self::PAGE_WIDTH - self::MARGIN_X - 150, self::PAGE_WIDTH - self::MARGIN_X];

        $this->ensureSpace(20);
        foreach ($headers as $i => $h) {
            if ($i === 0) {
                $this->text($colX[0], $h, 9, 'F2');
            } else {
                $this->textRight($colX[$i], $h, 9, 'F2');
            }
        }
        $this->y -= 6;
        $this->hr();
        $this->y -= 14;

        foreach ($rows as $row) {
            $this->ensureSpace(16);
            $this->text($colX[0], (string) $row[0], 10, 'F1');
            for ($i = 1; $i < count($row); $i++) {
                $this->textRight($colX[$i], (string) $row[$i], 10, 'F1');
            }
            $this->y -= 15;
        }

        if ($totalRow) {
            $this->y -= 2;
            $this->hr();
            $this->y -= 12;
            $this->text($colX[0], (string) $totalRow[0], 10, 'F2');
            for ($i = 1; $i < count($totalRow); $i++) {
                $this->textRight($colX[$i], (string) $totalRow[$i], 10, 'F2');
            }
            $this->y -= 15;
        }
    }

    /**
     * Renders the PDF and sends it to the browser with the given filename, then exits.
     */
    public function output(string $filename): void
    {
        $this->pages[] = $this->buffer;

        $objects = [];
        $pageObjIds = [];
        $contentObjIds = [];

        // Object 1: Catalog, Object 2: Pages (filled in after we know kids)
        $nextId = 3;
        $fontRegularId = $nextId++;
        $fontBoldId = $nextId++;

        foreach ($this->pages as $content) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $contentObjIds[] = $contentId;
            $pageObjIds[] = $pageId;
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        foreach ($pageObjIds as $i => $pageId) {
            $objects[$pageId] =
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::PAGE_WIDTH . " " . self::PAGE_HEIGHT . "] " .
                "/Resources << /Font << /F1 {$fontRegularId} 0 R /F2 {$fontBoldId} 0 R >> >> " .
                "/Contents {$contentObjIds[$i]} 0 R >>";
        }

        $kids = implode(' ', array_map(fn($id) => "{$id} 0 R", $pageObjIds));
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageObjIds) . " >>";
        $objects[$fontRegularId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[$fontBoldId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $offset = $offsets[$id] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
}

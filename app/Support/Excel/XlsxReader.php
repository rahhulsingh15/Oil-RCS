<?php

namespace App\Support\Excel;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal .xlsx reader (no extra Composer package needed).
 * Returns cell values as strings, keyed by row number and column letter.
 */
class XlsxReader
{
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private ZipArchive $zip;

    /** @var array<int, string> */
    private array $sharedStrings = [];

    /** @var array<string, string> sheet name => xml path inside the zip */
    private array $sheets = [];

    public function __construct(private string $path)
    {
        if (! is_file($path)) {
            throw new RuntimeException("Excel file not found: {$path}");
        }

        $this->zip = new ZipArchive;
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Could not open Excel file: {$path}");
        }

        $this->loadSharedStrings();
        $this->loadSheetIndex();
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheets);
    }

    public function hasSheet(string $name): bool
    {
        return $this->findSheet($name) !== null;
    }

    /**
     * @return array<int, array<string, string>> [rowNumber => [columnLetter => value]]
     */
    public function rows(string $sheetName): array
    {
        $key = $this->findSheet($sheetName)
            ?? throw new RuntimeException("Sheet \"{$sheetName}\" not found in ".basename($this->path));

        $xml = $this->xml($this->sheets[$key]);
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $cells[$col] = $this->cellValue($cell);
            }
            $rows[(int) $row['r']] = $cells;
        }

        return $rows;
    }

    private function cellValue(SimpleXMLElement $cell): string
    {
        $type = (string) $cell['t'];

        return match ($type) {
            's' => $this->sharedStrings[(int) $cell->v] ?? '',
            'inlineStr' => $this->richText($cell->is),
            'b' => ((string) $cell->v) === '1' ? 'TRUE' : 'FALSE',
            default => (string) $cell->v,
        };
    }

    private function richText(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        if (isset($node->t)) {
            return (string) $node->t;
        }

        $text = '';
        foreach ($node->r ?? [] as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private function loadSharedStrings(): void
    {
        if ($this->zip->locateName('xl/sharedStrings.xml') === false) {
            return;
        }

        foreach ($this->xml('xl/sharedStrings.xml')->si as $si) {
            $this->sharedStrings[] = $this->richText($si);
        }
    }

    private function loadSheetIndex(): void
    {
        $targets = [];
        foreach ($this->xml('xl/_rels/workbook.xml.rels')->Relationship as $rel) {
            $target = ltrim((string) $rel['Target'], '/');
            $targets[(string) $rel['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        foreach ($this->xml('xl/workbook.xml')->sheets->sheet as $sheet) {
            $relId = (string) $sheet->attributes(self::NS_REL)['id'];
            if (isset($targets[$relId])) {
                $this->sheets[(string) $sheet['name']] = $targets[$relId];
            }
        }
    }

    /** Sheet lookup that ignores case and extra spaces ("PEL _PML_PL validity"). */
    private function findSheet(string $name): ?string
    {
        $normalize = fn (string $s) => strtolower(preg_replace('/\s+/', '', $s));

        foreach (array_keys($this->sheets) as $sheet) {
            if ($normalize($sheet) === $normalize($name)) {
                return $sheet;
            }
        }

        return null;
    }

    private function xml(string $entry): SimpleXMLElement
    {
        $content = $this->zip->getFromName($entry);
        if ($content === false) {
            throw new RuntimeException("Corrupt Excel file, missing {$entry}");
        }

        return new SimpleXMLElement($content);
    }
}

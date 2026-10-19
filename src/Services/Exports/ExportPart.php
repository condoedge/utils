<?php

namespace Condoedge\Utils\Services\Exports;

use Illuminate\Database\Eloquent\Relations\Relation;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Some pages (of chunkSize() rows) of a Maatwebsite query export, read as Maatwebsite reads them, with the export's
 * headings, title, formats, styles and autosize: its parts read as the whole file. An export with any other concern
 * isn't split (supports()); nor are array or collection ones, often reports whose layout depends on row positions.
 */
class ExportPart implements FromGenerator, WithHeadings, WithTitle, WithColumnFormatting, WithStyles
{
    public const CARRIED_CONCERNS = [
        FromQuery::class, WithHeadings::class, WithMapping::class, WithTitle::class, WithColumnFormatting::class,
        WithStyles::class, ShouldAutoSize::class, WithCustomChunkSize::class,
    ];

    public function __construct(protected $export, protected int $firstPage, protected ?int $lastPage)
    {
    }

    public static function supports($export): bool
    {
        $concerns = array_filter(class_implements($export), fn ($interface) => str_starts_with($interface, 'Maatwebsite\\Excel\\Concerns\\'));

        return $export instanceof FromQuery && !array_diff($concerns, static::CARRIED_CONCERNS);
    }

    // A copy of the export per part: what it works out while writing its rows stays the part's. ShouldAutoSize is a
    // marker: a part has it when its export does.
    public static function of($export, int $firstPage, ?int $lastPage): self
    {
        return $export instanceof ShouldAutoSize
            ? new class(clone $export, $firstPage, $lastPage) extends ExportPart implements ShouldAutoSize {}
            : new static(clone $export, $firstPage, $lastPage);
    }

    // As Maatwebsite pages a query export.
    public static function chunkSize($export): int
    {
        return $export instanceof WithCustomChunkSize ? $export->chunkSize() : config('excel.exports.chunk_size', 100);
    }

    // As chunk() reads them (ordered by the key when the query has no order), from the part's first page to its last;
    // each chunk through prepareRows() then map(), as Maatwebsite does.
    public function generator(): \Generator
    {
        $chunkSize = static::chunkSize($this->export);

        $query = clone $this->export->query();
        callPrivateMethod($query instanceof Relation ? $query->getQuery() : $query, 'enforceOrderBy');

        for ($page = $this->firstPage; $this->lastPage === null || $page <= $this->lastPage; $page++) {
            $rows = $query->forPage($page, $chunkSize)->get();

            foreach (method_exists($this->export, 'prepareRows') ? $this->export->prepareRows($rows) : $rows as $row) {
                yield $this->export instanceof WithMapping ? $this->export->map($row) : $row;
            }

            if ($rows->count() < $chunkSize) {
                break;
            }
        }
    }

    public function headings(): array
    {
        return $this->export instanceof WithHeadings ? $this->export->headings() : [];
    }

    // PhpSpreadsheet's own title when the export has none.
    public function title(): string
    {
        return $this->export instanceof WithTitle ? $this->export->title() : 'Worksheet';
    }

    public function columnFormats(): array
    {
        return $this->export instanceof WithColumnFormatting ? $this->export->columnFormats() : [];
    }

    public function styles($sheet)
    {
        return $this->export instanceof WithStyles ? $this->export->styles($sheet) : [];
    }
}

<?php

namespace Condoedge\Utils\Services\Exports;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use Condoedge\Utils\Services\Exports\Traits\ExportableUtilsTrait;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ComponentToExportableToExcel implements FromArray, WithHeadings, ShouldAutoSize, WithColumnFormatting, WithTitle, WithStyles, WithChunkReading
{
    use ExportableUtilsTrait;

    public const REGEX_CURRENCY = '/^\$\s*-?\d{1,3}(,\d{3})*(\.\d{2})?$/';
    public const REGEX_CURRENCY_FR = '/^-?\d{1,3}(.\d{3})*(\,\d{2})?\s*\$$/';

    protected $component;
    protected $filename;
    protected $title;    
    protected $columnFormats = [];
    protected $boldColumns = [1];
    protected $pastCountOfItems = 1; // Just needed to child classes
    protected $chunkSize = 1000; // Default chunk size

    public function __construct($component)
    {
        $this->component = $component;
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }    
    
    public function title(): string
    {
        if (method_exists($this->component, 'title')) {
            return callPrivateMethod($this->component, 'title');
        }

        if (property_exists($this->component, 'title')) {
            return getPrivateProperty($this->component, 'title');
        }

        return $this->title ?: 'Worksheet';
    }

    public function styles($sheet)
    {
        // Rows height adjustment when we use break lines in cells
        $sheet->getStyle('A1:Z' . $sheet->getHighestRow())
            ->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        return collect($this->boldColumns)
            ->mapWithKeys(fn($col) => [$col => ['font' => ['bold' => true]]])
            ->all();
    }

    // Excel export methods
    public function columnFormats(): array
    {
        if (property_exists($this->component, 'columnFormats')) {
            return getPrivateProperty($this->component, 'columnFormats');
        }

        return $this->columnFormats;
    }

    public function headings(): array
    {
        if ($this->getExportChildClass()) {
            $childInstance = $this->component->render(collect($this->getItems(null, 1))->first())->findByComponent($this->getExportChildClass());
            $childInstance->bootForAction();

            return $this->parseHeaders($childInstance->headers());
        }

        try {
            return $this->parseHeaders($this->component->headers());
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['class' => static::class, 'trace' => $e->getTraceAsString(), 'user' => auth()->user()]);
            abort(500);
        }
        
    }

    public function array(): array
    {
        if ($this->getExportChildClass()) {
            $itemsGroups = [];
            $boldColumn = collect($this->boldColumns)->last();
              // Process one item at a time to save memory
            foreach ($this->getItems() as $index => $item) {
                $childInstance = $this->component->render($item)->findByComponent($this->getExportChildClass());
                $childInstance->bootForAction();

                if (!$childInstance) {
                    continue;
                }
                
                $childItems = $this->getItems($childInstance);
                  // Add separator
                $boldColumn += $this->pastCountOfItems;
                $this->pastCountOfItems = (count($childItems) ?: 1) + 1;
                $this->boldColumns[] = $boldColumn;
                
                // Use native array instead of collection to reduce overhead
                $groupItems = [[$childInstance->exportableSeparator()]];
                
                if (empty($childItems)) {
                    $groupItems[] = ['No items found.'];
                } else {
                    foreach ($childItems as $childItem) {
                        $formattedItem = $this->formatItemToExport($childItem, $childInstance);
                        if ($formattedItem) {
                            $groupItems[] = $formattedItem;
                        }
                    }
                }
                
                foreach ($groupItems as $groupItem) {
                    $itemsGroups[] = $groupItem;
                }
                  // Free memory
                unset($childItems);
                unset($groupItems);
                
                // Force memory cleanup if needed in large batches
                if ($index > 0 && $index % 500 === 0) {
                    gc_collect_cycles();
                }
            }
            
            return $itemsGroups;
        }

        $result = [];
          // Process elements in batches to save memory
        $items = $this->getItems();
        foreach ($items as $index => $item) {
            $formattedItem = $this->formatItemToExport($item);
            if ($formattedItem) {
                $result[] = $formattedItem;
            }
            
            // Free memory periodically
            if ($index > 0 && $index % 500 === 0) {
                gc_collect_cycles();
            }
        }

        unset($items); // Free memory
        
        return $result;
    }

    /* PARSING METHODS */
    public function formatItemToExport($item, $fromInstance = null)
    {
        $fromInstance = $fromInstance ?? $this->component;

        $renderedItem = $fromInstance->render($item);

        if (!$renderedItem) {
            return [];
        }

        $result = [];
        // Using foreach instead of collect to reduce overhead
        foreach ($renderedItem->elements as $i => $element) {
            if (!$element || (property_exists($element, 'class') && str_contains($element->class, 'exclude-export'))) {
                continue;
            }
            
            $letter = chr(65 + $i);
            $text = $this->getLabelsFromComponent($element);

            $format = $this->getCurrencyFormat($text);

            if ($format) {
                $this->columnFormats[strtoupper($letter)] = $format;
            }

            $result[] = $this->sanatizeText($text);
        }
        
        unset($renderedItem); // Free memory
        
        return $result;
    }

    protected function parseHeaders($ths)
    {
        return collect($ths)->map(fn($th) => ($this->isExcludedHeader($th) || !$th) ? null : ($th?->label ?: '-'))->filter()->all();
    }

    protected function isExcludedHeader($th)
    {
        return $th && property_exists($th, 'class') && str_contains($th->class, 'exclude-export');
    }

    protected function getLabelsFromComponent($el)
    {
        if (property_exists($el, 'elements') && !empty($el->elements)) {
            $implodeUnion = (str_contains($el->bladeComponent, 'Flex') || str_contains($el->bladeComponent, 'Columns')) ? ' | ' : "\r\n ";

            $labels = [];
            foreach ($el->elements as $childElement) {
                $label = $this->getLabelsFromComponent($childElement);
                if (!empty($label)) {
                    $labels[] = $label;
                }
            }
            
            return implode($implodeUnion, $labels);
        }

        if (property_exists($el, 'label')) {
            if (preg_match('/<[^>]*>/', $el->label)) {
                return $this->convertHtmlToPlainText($el->label);
            }

            return \Lang::has($el->label) ? __($el->label) : $el->label;
        }

        return "";
    }

    protected function getItems($fromInstance = null, $perPage = 1000)
    {
        $fromInstance = $fromInstance ?? $this->component;

        $prevPerPage = $fromInstance->perPage;
        $fromInstance->perPage = $perPage ?? $this->chunkSize();

        $items = $fromInstance->query();

        if ($items instanceof Builder) {
            if ($perPage > $this->chunkSize()) {
                // Batch processing for large datasets
                $result = [];
                $page = 1;
                $chunkSize = $this->chunkSize();
                
                do {
                    $chunk = $items->forPage($page, $chunkSize)->get();
                    if ($chunk->isEmpty()) {
                        break;
                    }
                    
                    foreach ($chunk as $item) {
                        $result[] = $item;
                    }
                      unset($chunk); // Free memory
                    $page++;
                    
                    // Exit if we have enough records
                    if (count($result) >= $perPage) {
                        break;
                    }
                } while (true);
                  $items = collect($result);
                    unset($result); // Free memory
            } else {
                $items = $items->take($perPage)->get();
            }
        }

        $fromInstance->perPage = $prevPerPage;

        return $items;
    }

    /** FORMATS */
    protected function currencyFormat()
    {
        return NumberFormat::FORMAT_CURRENCY_USD;
    }

    protected function getCurrencyFormat($text)
    {
        if (preg_match(static::REGEX_CURRENCY, $text) || preg_match(static::REGEX_CURRENCY_FR, $text)) {
            return $this->currencyFormat();
        }

        return null;
    }

    /* SANATIZE */
    protected function sanatizeText($text)
    {
        if (preg_match(static::REGEX_CURRENCY, $text)) {
            return floatval(preg_replace('/[^0-9.-]/', '', $text));
        }

        if (preg_match(static::REGEX_CURRENCY_FR, $text)) {
            return floatval(str_replace(',', '.', preg_replace('/[^0-9.,-]/', '', $text)));
        }

        return  html_entity_decode(trim($text));
        // return mb_convert_encoding(trim($text), 'ISO-8859-1', 'UTF-8');
    }

    protected function convertHtmlToPlainText($html)
    {   
        $dom = new \DOMDocument;
        $html = preg_replace('/&(?!amp)/', '&amp;', $html);
        
        try {
            $internalErrors = libxml_use_internal_errors(true);
            $encodingMetaHtml = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
            $dom->loadHTML($encodingMetaHtml . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['class' => static::class, 'html' => $html, 'trace' => $e->getTraceAsString(), 'user' => auth()->user()]);

            return preg_replace("/\n+/", "\n", strip_tags($html));
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//text()');

        $texts = [];
        foreach ($nodes as $node) {
            $text = trim($node->nodeValue);
            if (!empty($text)) {
                $texts[] = $text;
            }
        }

        $text = implode(" \n", $texts);
        
        // Free memory
        unset($dom);
        unset($xpath);
        unset($nodes);
        unset($texts);
        
        return \Lang::has($text) ? __($text) : $text;
    }

    // GETTERS
    public function getTitle()
    {
        if (property_exists($this->component, 'title')) {
            return getPrivateProperty($this->component, 'title');
        }

        return $this->title;
    }

    public function getExportChildClass()
    {
        if (property_exists($this->component, 'exportChildClass')) {
            return getPrivateProperty($this->component, 'exportChildClass');
        }
    }

    public function getHeavinessLevel()
    {
        if (property_exists($this->component, 'heavinessLevel')) {
            return getPrivateProperty($this->component, 'heavinessLevel');
        }

        // Very complicated to know the quantity of total records if it has a child class so we just put a intermediate level
        if ($this->getExportChildClass()) {
            return ExportableHeaviness::MEDIUM;
        }

        $count = $this->component->query()->count();

        if ($count < 300) {
            return ExportableHeaviness::LIGHT;
        }

        if ($count < 5000) {
            return ExportableHeaviness::MEDIUM;
        }

        return ExportableHeaviness::HEAVY;
    }
}

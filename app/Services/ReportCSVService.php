<?php

namespace App\Services;

use App\Models\ContractorPrice;
use App\Models\LaraPolcarItem;

class ReportCSVService
{
    const CSV_FIELDS = [
        'id',
        'oem',
        'title',
        'part_title',
        'polcar_car_id',
        'created_at',
        'updated_at',
    ];
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function generateCSV(): string
    {
        $importedOems = ContractorPrice::pluck('article_id');

        $csvData =  LaraPolcarItem::whereIn('oem', $importedOems->toArray())
            ->select(self::CSV_FIELDS)
            ->get()
            ->toArray();

        if (empty($csvData)) {
            // Если нет данных, создаем пустой CSV с заголовками
            return $this->createEmptyCsv();
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'import_') . '.csv';
        $handle = fopen($tempFile, 'w');

        fwrite($handle, "\xEF\xBB\xBF");

        $headers = array_keys($csvData[0]);
        fputcsv($handle, $headers, ';');

        foreach ($csvData as $row) {

            fputcsv($handle, $row, ';');
        }

        fclose($handle);

        return $tempFile;
    }

    public function createEmptyCsv()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_import_') . '.csv';
        $handle = fopen($tempFile, 'w');

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, self::CSV_FIELDS, ';');
        fputcsv($handle, ['Нет данных для выбранных OEM кодов'], ';');

        fclose($handle);

        return $tempFile;
    }
}

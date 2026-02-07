<?php

namespace App\Services;

use App\Imports\SparePartImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ContractorPrice;
use App\Models\LaraPolcarItem;
use Illuminate\Support\Facades\Log;

class ExcelImportService
{
    public function __construct() {}

    public function processFile(string $path)
    {

        try {
            if (!file_exists($path)) {

                throw new \Error("Файл импорта не найден");
                return;
            }

            $excelData = Excel::toCollection(new SparePartImport(), $path);

            $worksheet = $excelData->first();

            if (!$worksheet) {
                throw new \Error("Рабочий лист в файл");
                return;
            }

            // удаляем contractor  с id = 11
            ContractorPrice::where('contractor_id', 11)->delete();

            $existingPartsOems = LaraPolcarItem::pluck('oem');

            // if ($existingPartsOems->contains('6920516M')) {
            //     dd('yes');
            // } else {
            //     dd('no way');
            // }

            // dd($existingPartsOems);

            $rowNum = 0;

            foreach ($worksheet as $row) {
                $rowNum++;
                if ($rowNum === 1) continue;
                dump($row);

                $exists = $existingPartsOems->contains(function ($oem) use ($row) {
                    return $oem === $row['pin'];
                });

                if ($exists) {

                    ContractorPrice::create([
                        'contractor_id' => 11,
                        'article_id' => $row['pin'],
                        'price' => (float) $row['price'],
                        'amount' => (int) $row['count'],
                        'delivery_date' => '2026-02-07 16:41:23'
                    ]);
                }
            }
        } catch (\Error $e) {
            Log::error('Ошибка обработки Excel файла: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

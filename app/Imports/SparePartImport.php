<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Concerns\WithMapping;

class SparePartImport implements ToCollection, WithHeadingRow, WithMapping
{
    public function __construct()
    {
        HeadingRowFormatter::default('none');
    }

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        return $rows;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function map($row): array
    {
        return [
            'pin' => $row['PIN'],
            'gtin' => $row['GTIN'],
            'name' => $row['Наименование'],
            'price' => $row['Цена'],
            'currency' => $row['Валюта'],
            'brand' => $row['Бренд'],
            'count' => $row['Количество'],
            'multiplicity' => $row['Кратность'],
            'prc' => $row['ПРЦ'],
            'marked' => $row['Маркированный товар']
        ];
    }
}

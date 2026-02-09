<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class LaraPolcarItemsSeeder extends Seeder
{

    public function run()
    {

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $sqlFile = database_path('import/lara_polcar_items.sql');

            if (file_exists($sqlFile)) {

                $sql = file_get_contents($sqlFile);
                DB::unprepared($sql);
            }
            
            $this->command->info('Данные успешно загружены');
        } catch (\Exception $e) {
            $this->command->warn("Ошибка при выполнении запроса: " . $e->getMessage());
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
    /**
     * 
     * Run the database seeds.
     */
    public function run_foo(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Полный путь к вашему SQL-файлу
        $sqlPath = database_path('import/lara_polcar_items.sql');

        // Если файл существует
        if (File::exists($sqlPath)) {
            // Получаем содержимое файла
            $sql = File::get($sqlPath);

            // Разбиваем на отдельные запросы (разделитель ';')
            $queries = explode("');", $sql);

            foreach ($queries as $query) {

                $trimmedQuery = trim($query);
                // dd($trimmedQuery);
                if (!empty($trimmedQuery) && !str_starts_with($trimmedQuery, '--')) {
                    try {
                        DB::statement($trimmedQuery . ';');
                    } catch (\Exception $e) {

                        $this->command->warn("Ошибка при выполнении запроса: " . $e->getMessage());
                        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                    }
                }
            }

            $this->command->info('Таблица lara_polcar_items успешно заполнена из SQL-дампа');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            $this->command->error("SQL файл не найден: {$sqlPath}");
        }
    }
}

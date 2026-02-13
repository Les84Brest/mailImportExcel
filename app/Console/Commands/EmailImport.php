<?php

namespace App\Console\Commands;

use App\Mail\ImportReportMail;
use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Log;
use App\Models\ImportHistory;
use App\Services\ExcelImportService;
use App\Services\ReportCSVService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Webklex\PHPIMAP\Support\FolderCollection;

class EmailImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:email';


    /**
     *
     * @var string
     */
    protected $description = 'Импорт Excel файлов из почты по IMAP ';

    /**
     * Папка для сохранения файлов
     */
    protected string $storagePath = 'app/contractor_11';

    /**
     * Допустимые расширения Excel файлов
     */
    protected array $allowedExtensions = ['xlsx', 'xls'];

    private array $importStatistics = [
        'processed_count' => 0,
        'created_count' => 0,
        'file_name' => '',
        'import_date' => '',
        'message_uid' => ''
    ];

    private string $csvFilePath = '';
    private string $attachmentFilePath = '';


    public function __construct(
        private ExcelImportService $excelImportService,
        private ReportCSVService $csvService
    ) {
        parent::__construct();
    }

    /**
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Начало импорта...');

        try {

            $this->createStorageDirectory();
            $messages = $this->connectEmailByImap();

            if ($messages->count() === 0) {
                $this->warn('Нет новых писем для обработки');
                return Command::SUCCESS;
            }

            $this->info("Найдено {$messages->count()} писем для проверки");

            $this->processMessages($messages);

            $this->info("Обработка завершена. Обработано позиций - {$this->importStatistics['processed_count']}. Импортировано позиций - {$this->importStatistics['created_count']}");

            //отпраляем отчет
            $this->csvFilePath = $this->importStatistics['processed_count'] > 0
                ? $this->csvService->generateCSV()
                : $this->csvService->createEmptyCsv();

            $reportMail = new ImportReportMail(
                $this->importStatistics['processed_count'],
                $this->importStatistics['created_count'],
                $this->importStatistics['import_date'],
                $this->csvFilePath
            );

            Mail::to(env('MAIL_IMPORT_REPORT_RECIEVER_EMAIL'),)->send($reportMail);

            $this->info("Отчет о импорте отправлен на email " . env('MAIL_IMPORT_REPORT_RECIEVER_EMAIL'));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            Log::error('ImportExcelPrice Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Создание папки для хранения файлов
     */
    private function createStorageDirectory(): void
    {
        $fullPath = storage_path($this->storagePath);

        if (!file_exists($fullPath)) {
            if (mkdir($fullPath, 0755, true)) {
                $this->info("Создана директория: {$fullPath}");
            } else {
                throw new \Exception("Не удалось создать директорию: {$fullPath}");
            }
        }
    }

    private function connectEmailByImap()
    {

        /** @var \Webklex\PHPIMAP\Client $client */
        $client = Client::account();

        $client->connect();

        /** @var FolderCollection $folders */
        $folders = $client->getFolders();

        $inboxFolder = $this->getInboxFolder($folders);
        $msgs = $inboxFolder->messages()->unseen()->get();

        return $msgs;
    }

    private function getInboxFolder(FolderCollection $folders)
    {
        foreach ($folders as $folder) {
            if (strtolower($folder->full_name) === 'inbox') {
                return $folder;
            }
        }
    }


    /**
     * Обработка писем
     */
    private function processMessages($messages)
    {
        $messagesWithExcelAttachments = array_filter(
            $messages->toArray(),
            function ($msg) {
                $attachments = $msg->getAttachments();
                $excelAttachments = $this->filterExcelAttachments($attachments);

                return count($excelAttachments) > 0;
            }
        );

        if (!count($messagesWithExcelAttachments)) {
            $this->info("Нет писем для импорта");
            exit();
        }

        $actualMessage = array_reduce($messagesWithExcelAttachments, function ($acc, $current) {
            if (!isset($acc)) {
                return $current;
            }

            return $current->getDate() > $acc->getDate() ? $current : $acc;
        }, null);

        if (!isset($actualMessage)) {
            $this->info("Нет писем для импорта");
            exit();
        }

        try {
            $attachments = $actualMessage->getAttachments();
            $excelAttachments = $this->filterExcelAttachments($attachments);
            $this->downloadAndProcessAttachments($excelAttachments, $actualMessage);

            $this->info("  ✓ Письмо успешно обработано");
        } catch (\Exception $e) {
            $this->error("  ✗ Ошибка обработки письма: " . $e->getMessage());
            Log::error('Ошибка обработки письма', [
                'mail_id' => $mailId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Фильтрация Excel вложений
     */
    private function filterExcelAttachments($attachments)
    {
        return $attachments->filter(function ($attachment) {
            return $this->isExcelFile($attachment);
        });
    }

    /**
     * Проверка, является ли файл Excel
     */
    private function isExcelFile($attachment): bool
    {
        $filename = $attachment->getName();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, $this->allowedExtensions);
    }

    /**
     * Скачивание и обработка вложений
     */
    private function downloadAndProcessAttachments($attachments, $message)
    {

        $isProcessed = ImportHistory::isMessageProcessed($message->getMessageId());

        if ($isProcessed) {
            return;
        }

        foreach ($attachments as $attachment) {
            try {
                $originalName = $attachment->getName();
                $this->info(" Обработка файла: {$originalName}");

                // Сохраняем файл
                $savedFilePath = $this->saveAttachment($attachment, $message);

                if (!$savedFilePath) {
                    $this->warn("Не удалось сохранить файл");
                    continue;
                }

                // Обрабатываем файл через сервис
                $processedData = $this->excelImportService->processFile($savedFilePath);

                // Обновляем счетчик обработанных строк
                $processedFiles[] = [
                    'original_name' => $originalName,
                    'saved_path' => $savedFilePath,
                    'processed_rows' => $processedData['processed_rows'] ?? 0,
                    'imported_rows' => $processedData['imported_rows'] ?? 0,
                ];

                //сохраняем id письма и статистику по импорту

                $this->importStatistics['processed_count'] = $processedData['processed_rows'] ?? 0;
                $this->importStatistics['created_count'] = $processedData['imported_rows'] ?? 0;
                $this->importStatistics['file_name'] = $originalName;
                $this->importStatistics['import_date'] = now();
                $this->importStatistics['message_uid'] = $message->getMessageId();

                ImportHistory::create([
                    'mail_id' => $message->getMessageId(),
                    'created_count' =>  $processedData['imported_rows'] ?? 0,
                    'total_items' => $processedData['processed_rows'] ?? 0,
                    'filename' => $originalName,
                    'completed_at' => now(),
                ]);

                $this->info("  Файл обработан. Строк: " . ($processedData['processed_rows'] ?? 0));
            } catch (\Exception $e) {
                $this->error(" Ошибка обработки файла: " . $e->getMessage());
                Log::error('Ошибка обработки вложения', [
                    'file' => $originalName ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
    }


    private function saveAttachment($attachment, $message): ?string
    {
        try {

            $originalName = $attachment->getName();
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $originalFileName = pathinfo($originalName, PATHINFO_FILENAME);

            $hash = substr(Hash::make(now()), 0, 15);
            $date = now()->format('Y-m-d_His');
            $saveFolder = storage_path($this->storagePath);
            $saveName = "{$originalFileName}-{$hash}-{$date}.$extension";

            $attachment->save($saveFolder, $saveName);
            $this->attachmentFilePath = "$saveFolder/$saveName";

            Log::info('Excel файл скачан из почты', [
                'original_name' => $originalName,
                'saved_as' => $saveName,
                'path' =>  $saveFolder,
                'message_id' => $message->getMessageId(),
            ]);

            return "$saveFolder/$saveName";
        } catch (\Exception $e) {
            $this->error("Ошибка сохранения файла {$originalName}: " . $e->getMessage());
            Log::error('Ошибка сохранения вложения', [
                'file' => $originalName ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function __destruct()
    {
        if (file_exists($this->csvFilePath)) {
            unlink($this->csvFilePath);
        }

        if (file_exists($this->attachmentFilePath)) {
            unlink($this->attachmentFilePath);
        }
    }
}

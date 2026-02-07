<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Log;
use App\Models\ImportHistory;
use App\Services\ExcelImportService;
use Illuminate\Support\Facades\Hash;
use Webklex\PHPIMAP\Support\FolderCollection;

class EmailImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:email
    // {--limit=20 : Максимальное количество писем для проверки}
    // {--only-new : Только новые письма (непросмотренные)}
    ';


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

    /**
     * Сервис импорта Excel
     */
    public function __construct(private ExcelImportService $excelImportService)
    {
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

            $processedCount = $this->processMessages($messages);

            $this->info("Обработка завершена. Обработано писем: {$processedCount}");

            dd('stop');
            $client = $this->connectToMailbox();

            if (!$client) {
                $this->error('Не удалось подключиться к почтовому ящику');
                return Command::FAILURE;
            }

            $messages = $this->fetchLatestEmails($client);
            if ($messages->count() === 0) {
                $this->warn('Нет новых писем для обработки');
                return Command::SUCCESS;
            }

            $this->info("Найдено {$messages->count()} писем для проверки");

            dd($messages);

            if ($messages->count() === 0) {
                $this->warn('Нет новых писем для обработки');
                return Command::SUCCESS;
            }


            $this->info("Найдено {$messages->count()} писем для проверки");

            $processedCount = $this->processMessages($messages);

            $this->info("Обработка завершена. Обработано писем: {$processedCount}");

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
        // $this->info("ВСЕ ПАПКИ");

        // foreach ($folders as $folder) {
        //     $this->info("Имя папки: " . $folder->full_name);
        //     $this->info("Путь: " . $folder->path);
        //     $this->info("Разделитель: " . $folder->delimiter);
        //     // $this->info("Атрибуты: " . implode(', ', $folder->attributes));
        //     $this->info("...");
        // }

        // $this->info("КОНЕЦ ВСЕ ПАПКИ");

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
     * Подключение к почтовому ящику
     */
    private function connectToMailbox()
    {
        $this->info('Подключение к почтовому ящику...');

        $cm = new ClientManager();
        $settings = [
            'host' => env('IMAP_HOST', 'imap.gmail.com'),
            'port' => env('IMAP_PORT', 993),
            'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
            'validate_cert' => env('IMAP_VALIDATE_CERT', true),
            'username' => env('IMAP_USERNAME'),
            'password' => env('IMAP_PASSWORD'),
            'protocol' => env('IMAP_PROTOCOL', 'imap'),
            'options' => [
                'debug' => true, // Включаем отладку
            ],
        ];


        $client = $cm->make($settings);

        $client->connect();

        if (!$client->isConnected()) {
            throw new \Exception('Не удалось подключиться к почтовому серверу');
        }

        $this->info('Успешное подключение к почтовому ящику');
        return $client;
    }

    /**
     * Получение последних писем
     */
    private function fetchLatestEmails($client)
    {
        // Подключаемся к папке INBOX

        $folder = $client->getFolder('INBOX');
        dump($folder);

        $query = $folder->query()
            ->leaveUnread();

        $foo = $query->get();

        return $foo;
    }

    /**
     * Обработка писем
     */
    private function processMessages($messages): int
    {
        $processedCount = 0;

        foreach ($messages as $message) {
            try {
                if ($message->getAttachments()->count()) {
                    $attachments = $message->getAttachments();
                    $excelAttachments = $this->filterExcelAttachments($attachments);

                    if (count($excelAttachments) > 0) {
                        $isProcessed = ImportHistory::isMessageProcessed($message->getMessageId());
                        if ($isProcessed) continue;

                        $processedFiles = $this->downloadAndProcessAttachments($excelAttachments, $message);
                    }
                }

                $processedCount++;
                $this->info("  ✓ Письмо успешно обработано");
            } catch (\Exception $e) {
                $this->error("  ✗ Ошибка обработки письма: " . $e->getMessage());
                Log::error('Ошибка обработки письма', [
                    'mail_id' => $mailId ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Создаем запись об ошибке если не создана
                if (isset($mailId) && !ImportHistory::where('mail_id', $mailId)->exists()) {
                    ImportHistory::createMailRecord([
                        'mail_id' => $mailId,
                        'from_email' => $fromEmail ?? 'unknown',
                        'subject' => $subject ?? 'unknown',
                        'received_date' => $receivedDate ?? now()->toDateString(),
                        'attachments_count' => $attachments->count() ?? 0,
                    ])->markAsFailed($e->getMessage());
                }
            }
        }

        return $processedCount;
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
    private function downloadAndProcessAttachments($attachments, $message): array
    {
        $processedFiles = [];

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

                $this->info("  Файл обработан. Строк: " . ($processedData['processed_rows'] ?? 0));
            } catch (\Exception $e) {
                $this->error(" Ошибка обработки файла: " . $e->getMessage());
                Log::error('Ошибка обработки вложения', [
                    'file' => $originalName ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processedFiles;
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
}

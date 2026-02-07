<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ImportHistory;
use App\Services\ExcelImportService;

class ImportExcelPrice extends Command
{
    /**
     *
     * @var string
     */
    protected $signature = 'process:excelprice';
                            // {--limit=20 : Максимальное количество писем для проверки}
                            // {--force : Обработать даже если письмо уже в истории}
                            // {--only-new : Только новые письма (непросмотренные)}';

    /**
     *
     * @var string
     */
    protected $description = 'Импорт Excel файлов из почты по IMAP ';

    /**
     * Папка для сохранения файлов
     */
    protected string $storagePath = 'import/contractor_11';

    /**
     * Допустимые расширения Excel файлов
     */
    protected array $allowedExtensions = ['xlsx', 'xls'];

    /**
     * Сервис импорта Excel
     */
    public function __construct(private ExcelImportService $excelImportService) {}

    /**
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Начало импорта');

        try {

            $this->createStorageDirectory();

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
            dd($messages);

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
        $fullPath = storage_path('app/' . $this->storagePath);

        if (!file_exists($fullPath)) {
            if (mkdir($fullPath, 0755, true)) {
                $this->info("Создана директория: {$fullPath}");
            } else {
                throw new \Exception("Не удалось создать директорию: {$fullPath}");
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

        $client = $cm->make([
            'host' => env('IMAP_HOST', 'imap.gmail.com'),
            'port' => env('IMAP_PORT', 993),
            'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
            'validate_cert' => env('IMAP_VALIDATE_CERT', true),
            'username' => env('IMAP_USERNAME'),
            'password' => env('IMAP_PASSWORD'),
            'protocol' => env('IMAP_PROTOCOL', 'imap'),
        ]);

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
        $limit = (int) $this->option('limit');
        $onlyNew = $this->option('only-new');
        $fromEmail = $this->option('from');

        // Подключаемся к папке INBOX
        $folder = $client->getFolder('INBOX');

        $query = $folder->query()
            ->setFetchBody(false)
            ->setFetchAttachment(true)
            ->leaveUnread();

        if ($onlyNew) {
            $query->where('UNSEEN');
        }

        // Ограничиваем количество
        $query->limit($limit);

        return $query->get();
    }

    /**
     * Обработка писем
     */
    private function processMessages($messages): int
    {
        $processedCount = 0;

        foreach ($messages as $message) {
            try {
                $mailId = $message->getMessageId();
                $fromEmail = $message->getFrom()[0]->mail ?? 'unknown';
                $subject = $message->getSubject();
                $receivedDate = $message->getDate()->format('Y-m-d');

                $this->info("\nПроверка письма:");
                $this->line("  ID: {$mailId}");
                $this->line("  От: {$fromEmail}");
                $this->line("  Тема: {$subject}");
                $this->line("  Дата: {$receivedDate}");

                // Проверяем, есть ли письмо в истории
                if (!$this->option('force') && ImportHistory::mailExists($mailId)) {
                    $this->warn("  Письмо уже обработано ранее. Пропускаем...");

                    // Создаем запись о пропуске если ее нет
                    if (!ImportHistory::where('mail_id', $mailId)->exists()) {
                        ImportHistory::createMailRecord([
                            'mail_id' => $mailId,
                            'from_email' => $fromEmail,
                            'subject' => $subject,
                            'received_date' => $receivedDate,
                            'attachments_count' => $message->getAttachments()->count(),
                        ])->markAsSkipped('Уже обработано ранее');
                    }

                    continue;
                }

                // Проверяем вложения
                $attachments = $message->getAttachments();

                if ($attachments->count() === 0) {
                    $this->warn("  В письме нет вложений. Пропускаем...");

                    ImportHistory::createMailRecord([
                        'mail_id' => $mailId,
                        'from_email' => $fromEmail,
                        'subject' => $subject,
                        'received_date' => $receivedDate,
                        'attachments_count' => 0,
                    ])->markAsSkipped('Нет вложений');

                    continue;
                }

                $this->info("  Найдено вложений: " . $attachments->count());

                // Фильтруем Excel файлы
                $excelAttachments = $this->filterExcelAttachments($attachments);

                if (count($excelAttachments) === 0) {
                    $this->warn("  Нет Excel файлов во вложениях. Пропускаем...");

                    ImportHistory::createMailRecord([
                        'mail_id' => $mailId,
                        'from_email' => $fromEmail,
                        'subject' => $subject,
                        'received_date' => $receivedDate,
                        'attachments_count' => $attachments->count(),
                    ])->markAsSkipped('Нет Excel файлов');

                    continue;
                }

                // Создаем запись в истории
                $historyRecord = ImportHistory::createMailRecord([
                    'mail_id' => $mailId,
                    'from_email' => $fromEmail,
                    'subject' => $subject,
                    'received_date' => $receivedDate,
                    'attachments_count' => $excelAttachments->count(),
                ]);

                // Помечаем как "в обработке"
                $historyRecord->markAsProcessing();

                // Скачиваем и обрабатываем файлы
                $processedFiles = $this->downloadAndProcessAttachments($excelAttachments, $message, $historyRecord);

                if (empty($processedFiles)) {
                    $historyRecord->markAsFailed('Не удалось обработать файлы');
                    continue;
                }

                // Обновляем историю
                $historyRecord->markAsCompleted(
                    $processedFiles,
                    $historyRecord->processed_rows
                );

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
    private function downloadAndProcessAttachments($attachments, $message, $historyRecord): array
    {
        $processedFiles = [];

        foreach ($attachments as $attachment) {
            try {
                $originalName = $attachment->getName();
                $this->info("  Обработка файла: {$originalName}");

                // Сохраняем файл
                $savedFilePath = $this->saveAttachment($attachment, $message);

                if (!$savedFilePath) {
                    $this->warn("    Не удалось сохранить файл");
                    continue;
                }

                // // Обрабатываем файл через сервис
                // $processedData = $this->excelImportService->processFile(
                //     storage_path('app/' . $savedFilePath),
                //     $originalName
                // );

                // Обновляем счетчик обработанных строк
                $historyRecord->increment('processed_rows', $processedData['processed_rows'] ?? 0);

                $processedFiles[] = [
                    'original_name' => $originalName,
                    'saved_path' => $savedFilePath,
                    'processed_rows' => $processedData['processed_rows'] ?? 0,
                    'imported_rows' => $processedData['imported_rows'] ?? 0,
                ];

                $this->info("    ✓ Файл обработан. Строк: " . ($processedData['processed_rows'] ?? 0));
            } catch (\Exception $e) {
                $this->error("    ✗ Ошибка обработки файла: " . $e->getMessage());
                Log::error('Ошибка обработки вложения', [
                    'file' => $originalName ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processedFiles;
    }

    /**
     * Сохранение вложения на диск
     */
    private function saveAttachment($attachment, $message): ?string
    {
        try {
            $originalName = $attachment->getName();
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);

            // Генерируем уникальное имя файла
            $date = now()->format('Y-m-d_His');
            $messageId = Str::slug($message->getSubject(), '_');
            $messageId = substr($messageId, 0, 50);

            $filename = "contractor_11_{$date}_{$messageId}.{$extension}";
            $filepath = $this->storagePath . '/' . $filename;
            $fullPath = storage_path('app/' . $filepath);

            // Сохраняем файл
            $attachment->save($fullPath);

            // Логируем действие
            Log::info('Excel файл скачан из почты', [
                'original_name' => $originalName,
                'saved_as' => $filename,
                'path' => $filepath,
                'message_id' => $message->getMessageId(),
            ]);

            return $filepath;
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

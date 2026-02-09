# MailExcelImport - Импорт прайс-листов из почты
*MailaExcelImport* — это система автоматического импорта прайс-листов из почтовых ящиков, разработанная на Laravel 11. Проект предназначен для автоматической обработки входящих Excel файлов и загрузки данных в базу.

```
MailExcelImport/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── EmailImport.php      # Основная команда импорта
│   ├── Imports/
│   │   └── SparePartImport.php           # Импорт запчастей Excel
│   ├── Mail/
│   │   └── ImportReportMail.php          # Отправка отчетов об импорте
│   ├── Models/
│   │   ├── LaraPolcarItem.php            # Модель деталей
│   │   ├── ImportHistory.php             # История импортов
│   │   └── ContractorPrice.php           
│   ├── Services/
│   │   ├── ReportCSVService.php             # Подготовка CSV файла
│   │   └── ExcelImportService.php        # Сервис обработки Excel
│   └── Providers/
├── config/
│   ├── excel.php                         # Конфигурация Excel
├── database/
│   ├── migrations/
│   │   ...
│   └── seeders/
├── resources/
│   └── views/
│       └── emails/
│           └── import-report.blade.php   # Шаблон письма-отчета
├── storage/
│   └── app/
│       └── import/
│           └── contractor_11/            # Папка для загруженных файлов
├── docker/
│   ├── nginx/
│   ├── php/
│   └── mysql/
├── docker-compose.yml
├── Dockerfile
└── .env.example
```

## Основные файлы и папки
Ключевые файлы:
`app/Console/Commands/EmailImport.php` - Основная команда импорта
- Подключение к IMAP серверу
- Поиск новых писем с вложениями
- Загрузка и обработка Excel файлов
- Отправка отчетов
  
`app/Services/ExcelImportService.php `- Сервис парсинга Excel
- Парсинг Excel файлов
- Валидация данных
- Импорт в базу данных
  
`app/Mail/ImportReportMail.php` - Почтовый отчет
- Прикрепление CSV файлов
- Статистика выполнения

`database/migrations/ `- Миграции базы данных
`database/import/ `- папка для дампа таблицы lara_polcar_items

## Разворачивание проекта

## Шаг 1: Клонирование проекта
```bash
git clone git@github.com:Les84Brest/mailImportExcel.git MailExcelImport
cd MailExcelImport
```
## Шаг 2: Настройка окружения
```bash
# Копируем файл окружения
cp .env.example .env

# Редактируем .env файл
nano .env
```

## Шаг 3: Запуск контейнеров
```bash
# Сборка и запуск контейнеров
docker-compose up -d --build
```

## Шаг 4: Установка зависимостей и настройка
```bash
# Вход в контейнер приложения
make cli

# Установка зависимостей внутри контейнера
composer install  

# Генерация ключа приложения
php artisan key:generate

# Запуск миграций
php artisan migrate 

# Заполнение таблицы lara_polcar_items
php artisan db:seed --class=LaraPolcarItemsSeeder
```

## Использование
Указываем в .env свои данные по ящикам 
В переменную MAIL_IMPORT_REPORT_RECIEVER_EMAIL прописываем email, на который пойдет отчет
Отсылаем файл для импорта на ящик semalexnik@yandex.ru
Запускаем команду Artisan
```bash

php artisan import:email
```

Доступ в phpMyAdmin http://localhost:8085
root
secret
Смотрим что и как сформировалось
Также на письмо из .env MAIL_IMPORT_REPORT_RECIEVER_EMAIL придет отчет с csv файлом
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчет об импорте</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }

        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            border: 1px solid #eaeaea;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #4a5568;
        }

        .stat-label {
            font-size: 0.9em;
            color: #718096;
            text-transform: uppercase;
        }

        .success {
            color: #38a169;
        }

        .warning {
            color: #d69e2e;
        }

        .error {
            color: #e53e3e;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            font-size: 0.9em;
            color: #a0aec0;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th {
            background-color: #edf2f7;
            text-align: left;
            padding: 12px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Отчет об импорте прайс-листов</h1>
        <p>Дата импорта: {{ $importDate }}</p>
    </div>

    <div class="content">
        <h2>Общая статистика</h2>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-value">{{ $createdPartsCount }}</div>
                <div class="stat-label">Обновлено строк из прайса </div>
            </div>
            <div class="stat-card success">
                <div class="stat-value">{{ $linesCount }}</div>
                <div class="stat-label">Всего строк</div>
            </div>
        </div>

    </div>
</body>

</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Актуализация справочника сотрудников МОН</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 400px;
            max-height: 80vh; /* Ограничение высоты контейнера */
            overflow-y: auto; /* Прокрутка по вертикали */
        }
        h2, h3 {
            text-align: center;
        }
        label {
            display: block;
            margin-bottom: 8px;
        }
        input[type="file"],
        input[type="number"],
        input[type="text"],
        input[type="submit"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        input[type="submit"] {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #218838;
        }
        ul {
            list-style-type: none;
            padding: 0;
            max-height: 200px; /* Ограничение высоты списка */
            overflow-y: auto; /* Прокрутка по вертикали для списка */
        }
        li {
            background: #e9ecef;
            margin: 5px 0;
            padding: 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <form action="/upload" method="post" enctype="multipart/form-data">
            @csrf
            <label for="document">Выберите документ для загрузки (Excel, CSV и т.д.):</label>
            <input type="file" id="document" name="document" accept=".xls,.xlsx,.csv">

            <label for="start">Укажите номер строки откуда начинаются полезные данные:</label>
            <input type="number" step="1" min="1" max="10" value="4" id="start" name="start">

            <label for="path">Введите путь для сохранения файлов:</label>
            <input type="text" id="path" name="path">

            <input type="submit" value="Загрузить">
        </form>

        <h2>Уточнить данные по этим лицам:</h2>
        
        <h3>Данные из нового справочника</h3>
        @if(isset($dataNew) && count($dataNew) > 0)
            <ul>
                @foreach($dataNew as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
        
        <h3>Данные из старого справочника</h3>
        @if(isset($dataOld) && count($dataOld) > 0)
            <ul>
                @foreach($dataOld as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</body>
</html>


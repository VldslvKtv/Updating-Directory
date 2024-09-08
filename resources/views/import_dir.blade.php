<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Актуализация спрвочника сотрудников МОН</title>
</head>
<body>
    <form action="/upload" method="post" enctype="multipart/form-data">
        @csrf
        <label for="document">Выберите документ для загрузки (Excel, CSV и т.д.):</label><br>
        <input type="file" id="document" name="document" accept=".xls,.xlsx,.csv"><br>
        <input type="submit" value="Загрузить">
    </form>
</body>
</html>
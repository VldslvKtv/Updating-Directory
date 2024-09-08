<?php
namespace App\Exports;

use App\Models\ImportingDirectory;
use App\Http\Controllers;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class DirectoryExport implements FromCollection, WithHeadings
{

    public function headings(): array
    {
        return [
           ['Таблица для сравнения']
        ];
    }

    public function comparisonTable() {
        // Получаем данные из старого справочника
        $dataOld = DB::select('EXEC GetUniqueRecordsOld');
        
        // Извлекаем FIO из массива объектов
        $fioList = array_map(function($record) {
            return "'" . $record->FIO . "'"; 
        }, $dataOld);
    
        // Превращаем массив в строку
        $fioString = implode(',', $fioList);
    
        // новые записи
        $addedRecords = DB::select("SELECT [FIO], 
                   [DepartmentMOName], 
                   [Division], 
                   [Post], 
                   [ExternalPhone], 
                   [InternalPhone], 
                   [Address], 
                   [Room]
            FROM [UVBA].[dbo].[ImportingDirectory]
            WHERE [FIO] NOT IN ($fioString)
            ORDER BY FIO
        ");
        
        // удаленные записи
        // $deletedRecords = DB::select("SELECT CONCAT(RTRIM(LTRIM(W.Family)), ' ', RTRIM(LTRIM(W.Name)), ' ', RTRIM(LTRIM(W.Otchestvo))) AS FIO,
        //     CONCAT(Dep.KodEvents, '. ', Dep.Name) AS DepartmentMOName, Div.Name AS Division, W.Post, W.ExternalPhone, W.InternalPhone, W.Address,
		// 	W.Room
        //     FROM Worker W 
        //     LEFT JOIN DepartmentMO Dep ON W.IdDepartmentMO = Dep.IdDepartmentMO
        //     LEFT JOIN Division Div ON W.IdDivision = Div.IdDivision
        //     LEFT JOIN [User] Us ON W.IdUser = Us.IdUser
		// 	WHERE CONCAT(RTRIM(LTRIM(W.Family)), ' ', RTRIM(LTRIM(W.Name)), ' ', RTRIM(LTRIM(W.Otchestvo))) NOT IN (SELECT [FIO] FROM [UVBA].[dbo].[ImportingDirectory])
		// 	ORDER BY FIO;");

        // Создаем временную таблицу для удаленных
        DB::statement('CREATE TABLE DeletedRecords (
            FIO NVARCHAR(255),
            DepartmentMOName NVARCHAR(255),
            Division NVARCHAR(255),
            Post NVARCHAR(255),
            ExternalPhone NVARCHAR(50),
            InternalPhone NVARCHAR(50),
            Address NVARCHAR(255),
            Room NVARCHAR(50)
        )');

        // Вставляем данные из $dataOld во временную таблицу
        foreach ($dataOld as $record) {
            DB::table('DeletedRecords')->insert((array)$record);
        }

        // Выполняем запрос для нахождения записей, которые есть в старом справочнике, но отсутствуют в новом
        $deletedRecords = DB::select("
            SELECT 
                old.[FIO], 
                old.[DepartmentMOName], 
                old.[Division], 
                old.[Post], 
                old.[ExternalPhone], 
                old.[InternalPhone], 
                old.[Address], 
                old.[Room]
            FROM 
                DeletedRecords AS old
            LEFT JOIN 
                [UVBA].[dbo].[ImportingDirectory] AS new
            ON 
                old.[FIO] = new.[FIO]
            WHERE 
                new.[FIO] IS NULL AND old.[FIO] IS NOT NULL
        ");

        // Удаляем временную таблицу
        DB::statement('DROP TABLE DeletedRecords');
        
        
        // изменные или НЕ изменные записи
        
        // Создаем временную таблицу для Не изменных
        DB::statement('CREATE TABLE TempOldRecords (
            FIO NVARCHAR(255),
            DepartmentMOName NVARCHAR(255),
            Division NVARCHAR(255),
            Post NVARCHAR(255),
            ExternalPhone NVARCHAR(50),
            InternalPhone NVARCHAR(50),
            Address NVARCHAR(255),
            Room NVARCHAR(50)
        )');

        // Вставляем данные из $dataOld во временную таблицу
        foreach ($dataOld as $record) {
            DB::table('TempOldRecords')->insert((array)$record);
        }

        // Выполняем запрос для нахождения одинаковых записей
        $remainingRecords = DB::select("
            SELECT 
                old.[FIO], 
                old.[DepartmentMOName], 
                old.[Division], 
                old.[Post], 
                old.[ExternalPhone], 
                old.[InternalPhone], 
                old.[Address], 
                old.[Room]
            FROM 
                TempOldRecords AS old
                
            INNER JOIN 
                [UVBA].[dbo].[ImportingDirectory] AS new
            ON 
                new.[FIO] = old.[FIO]
                AND new.[DepartmentMOName] = old.[DepartmentMOName]
                AND new.[Division] = old.[Division]
                AND new.[Post] = old.[Post]
                AND new.[ExternalPhone] = old.[ExternalPhone]
                AND new.[InternalPhone] = old.[InternalPhone]
                AND new.[Address] = old.[Address]
                AND new.[Room] = old.[Room]
        ");

        // Удаляем временную таблицу
        DB::statement('DROP TABLE TempOldRecords');

        // Создаем временную таблицу для изменных
        DB::statement('CREATE TABLE TempChangeRecords (
            FIO NVARCHAR(255),
            DepartmentMOName NVARCHAR(255),
            Division NVARCHAR(255),
            Post NVARCHAR(255),
            ExternalPhone NVARCHAR(50),
            InternalPhone NVARCHAR(50),
            Address NVARCHAR(255),
            Room NVARCHAR(50)
        )');

        // Вставляем данные из $dataOld во временную таблицу
        foreach ($dataOld as $record) {
            DB::table('TempChangeRecords')->insert((array)$record);
        }

        // Выполняем запрос для нахождения записей с совпадающим FIO и хотя бы одним другим полем, которое не совпадает
        $changeRecords = DB::select("
            SELECT 
                old.[FIO], 
                old.[DepartmentMOName], 
                old.[Division], 
                old.[Post], 
                old.[ExternalPhone], 
                old.[InternalPhone], 
                old.[Address], 
                old.[Room]
            FROM 
                TempChangeRecords AS old
                
            INNER JOIN 
                [UVBA].[dbo].[ImportingDirectory] AS new
            ON 
                new.[FIO] = old.[FIO]
            WHERE 
                new.[DepartmentMOName] <> old.[DepartmentMOName] OR
                new.[Division] <> old.[Division] OR
                new.[Post] <> old.[Post] OR
                new.[ExternalPhone] <> old.[ExternalPhone] OR
                new.[InternalPhone] <> old.[InternalPhone] OR
                new.[Address] <> old.[Address] OR
                new.[Room] <> old.[Room]
        ");

        // Удаляем временную таблицу
        DB::statement('DROP TABLE TempChangeRecords');

        //-------------------------------------------------------------------------------------------------------
        $dataNew = DB::select('EXEC GetUniqueRecords');
        // Преобразуем в массивы для удобства
        $oldRecords = [];
        $newRecords = [];
    
        foreach ($dataOld as $record) {
            $oldRecords[$record->FIO] = (array)$record;
        }
    
        foreach ($dataNew as $record) {
            $newRecords[$record->FIO] = (array)$record;
        }
    
        // Инициализируем массивы для результатов
        $added = [];
        $removed = [];
        $unchanged = [];
        $changed = [];
    
        // Сравниваем записи
        foreach ($oldRecords as $fio => $oldRecord) {
            if (!isset($newRecords[$fio])) {
                // Удаленная запись
                $removed[] = $oldRecord;
            } else {
                // Запись есть в обоих справочниках
                $newRecord = $newRecords[$fio];
                if ($oldRecord == $newRecord) {
                    // Не измененная запись
                    $unchanged[] = $oldRecord;
                } else {
                   // Измененная запись
                    $changedRecord = [];
                    foreach ($oldRecord as $key => $oldValue) {
                        // Объединяем старое и новое значение через пробел
                        $newValue = isset($newRecord[$key]) ? $newRecord[$key] : '';
                        if ($key != 'FIO'){
                            $changedRecord[$key] = trim('БЫЛО: '. $oldValue . ' СТАЛО: ' . $newValue);
                        }
                        else{
                            $changedRecord[$key] = trim($newValue);
                        }
                    }
                    $changed[] = $changedRecord;
                }
            }
        }
    
        // Находим добавленные записи
        foreach ($newRecords as $fio => $newRecord) {
            if (!isset($oldRecords[$fio])) {
                // Добавленная запись
                $added[] = $newRecord;
            }
        }
        // Формируем итоговый массив
    $result = [];
    $result[] = ['Тип', 'FIO', 'DepartmentMOName', 'Division', 'Post', 'ExternalPhone', 'InternalPhone', 'Address', 'Room'];
    
    foreach ($added as $record) {
        $result[] = array_merge(['ДОБАВЛЕННЫЕ'], (array)$record);
    }
    $result[] = array_merge([' ']);

    foreach ($removed as $record) {
        $result[] = array_merge(['УДАЛЕННЫЕ'], (array)$record);
    }
    $result[] = array_merge([' ']);

    foreach ($unchanged as $record) {
        $result[] = array_merge(['НЕ ИЗМЕНЕННЫЕ'], (array)$record);
    }
    $result[] = array_merge([' ']);

    foreach ($changed as $record) {
        $result[] = array_merge(['ИЗМЕНЕННЫЕ'], (array)$record);
    }

    // $result[] = array_merge([count($dataOld), count($addedRecords), count($deletedRecords), count($remainingRecords), count($changeRecords)]);
    $result[] = array_merge([count($added), count($removed), count($unchanged), count($changed)]);

    return collect($result);
    }
    

    public function collection()
    {
        return $this->comparisonTable();
    }

    
    
}
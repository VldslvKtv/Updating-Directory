<?php

namespace App\Imports;

use App\Models\ImportingDirectory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class AllConstants{
    public const START_ROW  = 4;
    public const COUNT_COLUMN  = 5;
}

class DirectoryImport implements ToCollection, WithStartRow
{
    /**
     * Summary of startRow
     * @return int
     */
    public function startRow(): int
    {
        return AllConstants::START_ROW; // Номер строки, с которой начинается чтение данных
    }

    /**
     * @param mixed $row
     * @return bool
     */
    public function isDepartmentRow($row){
        if (preg_match('/\bд[е]п[а]рт[а]м[е]нт\b/ui', $row[0])){
            return true;
        }
        return false;
    }

     /**
     * @param mixed $row
     * @return bool
     */
    public function isDivisionRow($row){
        if (preg_match('/\bотдел\b/ui', $row[0])){
            return true;
        }
        return false;
    }

     /**
     * @param mixed $row
     * @return bool
     */
    public function isOther($row){
        if (preg_match('/\b(?:Помощники|Приемная|Профком)\b/ui', $row[0])){
            return true;
        }
        return false;
    }
    /**
     * /Общие сервисы вроде как не переносятся, поэтому на них заканчиваю импорт
     * Если это изменится, то нужно менять регулярное выражение
     * @param mixed $row
     * @return bool
     */
    public function checkBreak($row){
        if (preg_match('/\b(?:Общие сервисы)\b/ui', $row[0])){
            return true;
        }
        return false;
    }

    /**
     * @param mixed $row
     * @return mixed
     */
    public function checkIsEmpty($row){
        for($i = 0; $i <= AllConstants::COUNT_COLUMN; $i++){
            if ($i == 0){
                $row[$i] = empty($row[$i]) ? '-' : trim(preg_replace('/\s+/', ' ',strval($row[$i])));
            }
            else if ($i == 2){
                $row[$i] = empty($row[$i]) ? null : sprintf('%s%s-%s', substr($row[$i], 0, 3), substr($row[$i], 3, 3), substr($row[$i], 6));
            }
            else{
                $row[$i] = empty($row[$i]) ? null : strval($row[$i]);
            }
        }
        return $row;
    }

    /**
     * @param \Illuminate\Support\Collection $rows
     * @return void
     */
    public function collection(Collection $rows)
    {
        $department = null;
        $division = null;

        foreach ($rows as $row) {
            if ($this->isDepartmentRow($row)) {
                $department = $row[0];
                $division = null;
                continue;
            } else if ($this->isDivisionRow($row)){
                $division = $row[0];
                continue;
            }
            else if ($this->isOther($row)){
                continue;
            }
            else if ($this->checkBreak($row)){
                break;
            }
            else{
                $row = $this->checkIsEmpty($row);
                if ($row[0] != '-'){
                    ImportingDirectory::create([
                        'FIO' => $row[0],
                        'DepartmentMOName' => $department,
                        'Division' => $division,
                        'Post' => $row[1],
                        'ExternalPhone' => $row[2],
                        'InternalPhone' => $row[3],
                        'Address' => $row[4],
                        'Room' => $row[5]
                    ]);
                }
                
            }
        }
    }

}

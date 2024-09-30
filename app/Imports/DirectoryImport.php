<?php

namespace App\Imports;

use App\Models\ImportingDirectory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\DB;


class AllConstants{
    public const COUNT_COLUMN  = 5;
}

/**
 * Данные о министрах/зам. министра тк их отслеживать лучше вручную
 */
class DopDataNew {
    private static $instance = null;
    public $data = [];

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DopDataNew();
        }
        return self::$instance;
    }

    public function addPost($post) {
        if ($post != '' && !in_array($post, $this->data)){array_push($this->data, $post);}
    }
}

class DirectoryImport implements ToCollection, WithStartRow
{
    protected $startRow;

    public function __construct($startRow)
    {
        $this->startRow = $startRow;
    }
    /**
     * Summary of startRow
     * @return int
     */
    public function startRow(): int
    {
        return  $this->startRow; // Номер строки, с которой начинается чтение данных
    }

    /**
     * @param mixed $row
     * @return bool
     */
    public function isDepartmentRow($row){
        if (preg_match('/\b(?:\d+|департамент)\b/ui', $row[0])){
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
     * @return bool[]
     */
    public function isOther($row){
        if (preg_match('/\b(?:Помощники)\b/ui', $row[0])){
            return [true, trim($row[0])];
        }
        elseif (preg_match('/\b(?:Приемная|Профком)\b/ui', $row[0])){
            return [true, ''];
        }
        return [false, ''];
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
    public function checkIsEmpty($row, $dop){
        $dataIn = DopDataNew::getInstance();
        $dataIn->addPost($dop);
        for($i = 0; $i <= AllConstants::COUNT_COLUMN; $i++){
            if ($i == 0){
                $row[$i] = empty($row[$i]) ? '-' : trim(preg_replace('/\s+/', ' ', str_replace("\xC2\xA0", ' ', strval($row[$i]))));
            }
            else if ($i == 2){
                $row[$i] = empty($row[$i]) ? null : sprintf('%s%s-%s', substr($row[$i], 0, 3), substr($row[$i], 3, 3), substr($row[$i], 6));
            }
            else{
                $row[$i] = empty($row[$i]) ? null : trim(strval($row[$i]));
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
        $onlyPriemnaya = '';


        foreach ($rows as $row) {
            if ($this->isDepartmentRow($row)) {
                $department = $row[0];
                $division = null;
                continue;
            } else if ($this->isDivisionRow($row)){
                $division = $row[0];
                continue;
            }
            else if ($this->isOther($row)[0]){
                $department = null;
                $division = $this->isOther($row)[1];
                $onlyPriemnaya = preg_replace("/^(\w+\s)/", "", $division);
                continue;
            }
            else if ($this->checkBreak($row)){
                break;
            } 
            else{
                $row = $this->checkIsEmpty($row, $onlyPriemnaya);
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

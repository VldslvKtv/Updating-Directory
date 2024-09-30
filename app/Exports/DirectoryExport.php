<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;

/**
 * Данные о министрах/зам. министра тк их отслеживать лучше вручную
 */
class DopDataOld {
    public static $data = [];
    public function addPost($post) {
        array_push(self::$data, $post);
    }
}

class DirectoryExport implements FromCollection, WithHeadings, WithEvents
{

    public function headings(): array
    {
        return [
           ['Таблица для сравнения']
        ];
    }

    /**
     * Форматирование столбцов
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A:I')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(25);
                $event->sheet->getDelegate()->getStyle('A')->getAlignment()->setWrapText(true);

                $event->sheet->getDelegate()->getColumnDimension('B')->setWidth(40);
                $event->sheet->getDelegate()->getStyle('B')->getAlignment()->setWrapText(true);

                $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(40);
                $event->sheet->getDelegate()->getStyle('C')->getAlignment()->setWrapText(true);

                $event->sheet->getDelegate()->getColumnDimension('D')->setWidth(60);
                $event->sheet->getDelegate()->getStyle('D')->getAlignment()->setWrapText(true);

                $event->sheet->getDelegate()->getColumnDimension('E')->setWidth(40);
                $event->sheet->getDelegate()->getStyle('E')->getAlignment()->setWrapText(true);

                $event->sheet->getDelegate()->getColumnDimension('F')->setWidth(30);
                $event->sheet->getDelegate()->getStyle('F')->getAlignment()->setWrapText(true);

                $event->sheet->getDelegate()->getColumnDimension('G')->setWidth(20);
                $event->sheet->getDelegate()->getStyle('G')->getAlignment()->setWrapText(true);

                $event->sheet->getDelegate()->getColumnDimension('H')->setWidth(20);
                $event->sheet->getDelegate()->getStyle('H')->getAlignment()->setWrapText(true);

                $event->sheet->getDelegate()->getColumnDimension('I')->setWidth(20);
                $event->sheet->getDelegate()->getStyle('I')->getAlignment()->setWrapText(true);
            },
        ];
    }

    /**
     * Массив сотрудников, являющихся ЧК (у них должность не менять по просьбе Дарьи)
     * @return array
     */
    public function getCommission() {
        $dataCommission = DB::select("SELECT CONCAT(RTRIM(LTRIM(W.Family)), ' ', RTRIM(LTRIM(W.Name)), ' ', RTRIM(LTRIM(W.Otchestvo))) AS FIO
            FROM Worker W 
            LEFT JOIN DepartmentMO Dep ON W.IdDepartmentMO = Dep.IdDepartmentMO
            LEFT JOIN Division Div ON W.IdDivision = Div.IdDivision
            LEFT JOIN [User] Us ON W.IdUser = Us.IdUser
            WHERE W.IsChairman = 1 OR W.IsViceChairman = 1 OR W.IsSecretary = 1 OR 
                  W.IsComission = 1 OR W.IsBKGuest = 1;");
    
        $massivCommission = [];
        foreach ($dataCommission as $record) {
            $massivCommission[] = $record->FIO; 
        }
        return $massivCommission;
    }

    public function checkCommission($checkingPost){
        $posts = ['Министр', 'Заместитель Министра'];
        $found = false;

        foreach ($posts as $post) {
            if (stripos(trim($checkingPost), $post) !== false) {
                $found = true;
                break;
            }
        }
        return $found;
    }
    

    /**
     * Перед экспортом преобразуем данные по группам
     * @return \Illuminate\Support\Collection
     */
    public function comparisonTable() {
        // Получаем данные из справочников
        $dopData = new DopDataOld;
        $dataOld = DB::select('EXEC GetUniqueRecordsOld');
        $dataNew = DB::select('EXEC GetUniqueRecords');


        // Преобразуем в массивы для удобства
        $oldRecords = [];
        $newRecords = [];

        $commission = $this->getCommission();
        foreach ($dataOld as $record) {
            $record->DepartmentMOName = ($record->DepartmentMOName == '. ') ? '' : $record->DepartmentMOName;
            if ($this->checkCommission($record->Post))
                {$dopData->addPost($record->FIO);}
            $oldRecords[$record->FIO] = (array)$record;
        }
        // Log::info('DopDataOld::$data', DopDataOld::$data);

        foreach ($dataNew as $record) {
            $newRecords[$record->FIO] = (array)$record;
        }
        // Log::info('Старый справочник: ', $oldRecords);
        // Инициализируем массивы для результатов
        $added = [];
        $removed = [];
        $unchanged = [];
        $changed = [];
    
        // Сравниваем записи
        foreach ($oldRecords as $fio => $oldRecord) {
            if (($oldRecord['Division'] == 'Группа поддержки') || ($oldRecord['DepartmentMOName'] == '. Группа поддержки')){
                continue;
            }
            else if (!isset($newRecords[$fio]) ) {
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
                        $newValue = isset($newRecord[$key]) ? $newRecord[$key] : '';
                        // Проверяем условия для объединения значений
                        if ($key != 'FIO' && !($key == 'Post' && in_array($oldRecord['FIO'], $commission)) && (trim($oldValue) !== trim($newValue))) {
                            // Объединяем старое и новое значение через пробел
                            $changedRecord[$key] = trim('БЫЛО: ' . $oldValue . ' СТАЛО: ' . $newValue);
                        } else {
                            // Если это FIO или Post для commission, просто берем новое значение
                            $changedRecord[$key] = trim($oldValue);
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
        $result[] = array_merge(['ДОБАВЛЕНО'], (array)$record);
    }
    $result[] = array_merge([' ']);

    foreach ($removed as $record) {
        $result[] = array_merge(['УДАЛЕНО'], (array)$record);
    }
    $result[] = array_merge([' ']);

    foreach ($unchanged as $record) {
        $result[] = array_merge(['НЕ ИЗМЕНЕНО'], (array)$record);
    }
    $result[] = array_merge([' ']);

    foreach ($changed as $record) {
        $result[] = array_merge(['ИЗМЕНЕНО'], (array)$record);
    }

    // $result[] = array_merge([count($added), count($removed), count($unchanged), count($changed)]); //это проверка кол-ва записей
    // Log::info('Данные для экспорта', $result);
    session(['exported_data' => $result]);

    return collect($result);
    }
    

    public function collection()
    {
        return $this->comparisonTable();
    }   
}
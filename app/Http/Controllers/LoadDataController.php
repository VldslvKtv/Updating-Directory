<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LoadDataController extends Controller
{
    public function loading(Request $request){
        // Получаем данные из сессии
        $data = session('exported_data');
        if (empty($data)) {
            return response()->json(['message' => 'Нет данных для обработки'], 400);
        }
        // else{
        //     return response()->json(['message' => 'Есть данные', 'data' => $data], 200);
        // }

        if ($data) {
            // Заголовки
            $headers = $data[0];

            // Каждый раз новый столбец с комментарием
            $date = Carbon::parse($request->input('date'))->format('Y-m-d');
            $nameStolb = 'Comments_'.str_replace('-','_',$date);

            $workerTable = 'Worker_Copy'; // копии настоящих таблиц
            $departmentTable = 'DepartmentMO_Copy';
            $divisionTable = 'Division_Copy';

            DB::statement("ALTER TABLE $workerTable ADD $nameStolb TEXT NULL");

            // Проходим по записям
            foreach ($data as $key => $record) {
                if ($key == 0 || $record == [" "]) continue; // Пропускаем заголовки и пробелы

                $fio = trim($record['FIO']);
                $departmentMOName = trim(preg_replace('/\d+\.?/', '', $record['DepartmentMOName']));
                $divisionName = trim($record['Division']);
                $post = trim($record['Post']);    
                $externalPhone = trim($record['ExternalPhone']);
                $internalPhone = trim($record['InternalPhone']);
                $address = trim($record['Address']);
                $room = trim($record['Room']);
                $type = trim($record[0]); // "Тип"

                $changes = [];
                $changesColumn = [];
            
                // Проверяем и добавляем DepartmentMO, если его нет
                if ($type === 'ДОБАВЛЕНО'){
                    if ($departmentMOName != null && $departmentMOName != '' ){
                            $departmentMO = DB::table($departmentTable)->where('Name', $departmentMOName)->first();
                        if (!$departmentMO) {
                            DB::table($departmentTable)->insert(['Name' => $departmentMOName, 'NameRP' => $departmentMOName, 'NameDP' => $departmentMOName, 
                            'NameVP' => $departmentMOName,'NameTP' => $departmentMOName,'NamePP' => $departmentMOName, 'UpdatedByUser' => 0]);
                            $departmentMOId = DB::getPdo()->lastInsertId(); // Получаем id нового DepartmentMO
                        } else {
                            $departmentMOId = $departmentMO->IdDepartmentMO; // Получаем id существующего DepartmentMO
                        }
                    }
                    else{
                        $departmentMOId = null;
                    }  

                    // Проверяем и добавляем Division, если его нет
                    if (trim($divisionName) != '' && $departmentMOId != null){
                        $division = DB::table($divisionTable)->where('Name', $divisionName)->first();
                        if (!$division) {
                            DB::table($divisionTable)->insert(['Name' => $divisionName, 'idDepartmentMO' => $departmentMOId, 'UpdateStatus' => 'Добавлен', 'UpdateDate' => $date]);
                            $divisionId = DB::getPdo()->lastInsertId(); // Получаем id нового Division
                        } else {
                            $divisionId = $division->IdDivision; // Получаем id существующего Division  
                        }
                    }
                    else{
                        $divisionId = null;
                    }
                }
                elseif ($type === 'ИЗМЕНЕНО'){
                    $departmentMOId = null;
                    $departmentMO_ = '';
                    $divisionId = null;
                    foreach ($record as $column => $value) {
                        if ($column !== 0 && $column !== 'FIO' && (strpos($value, "БЫЛО:") !== false)) {
                            $changesColumn[$column] = $value;
                            if ($column == 'DepartmentMOName'){
                                $departmentMO_ = preg_match('/СТАЛО:\s*(.+)/', $departmentMOName, $matches) ? trim($matches[1]) : '';
                                if ($departmentMO_ != ''){
                                        $departmentMO = DB::table($departmentTable)->where('Name', $departmentMO_)->first();
                                    if (!$departmentMO) {
                                        DB::table($departmentTable)->insert(['Name' => $departmentMO_, 'UpdatedByUser' => 0]);
                                        $departmentMOId = DB::getPdo()->lastInsertId(); // Получаем id нового DepartmentMO
                                    } else {
                                        $departmentMOId = $departmentMO->IdDepartmentMO; // Получаем id существующего DepartmentMO
                                    }
                                    $changes['idDepartmentMO'] = $departmentMOId;
                                }
                            }
                            elseif ($column == 'Division'){
                                if ($departmentMOName != '' && $departmentMO_ != ''){
                                    if ($departmentMOId == null){
                                        $department = DB::table($departmentTable)->where('Name', $departmentMOName)->first();
                                        $departmentMOId = $department->IdDepartmentMO;
                                    }
                                    $division_ = preg_match('/СТАЛО:\s*(.+)/', $divisionName, $matches) ? trim($matches[1]) : '';
                                    if (trim($division_) != ''){
                                        $division = DB::table($divisionTable)->where('Name', $division_)->first();
                                        if (!$division) {
                                            DB::table($divisionTable)->insert(['Name' => $division_, 'idDepartmentMO' => $departmentMOId, 'UpdateStatus' => 'Добавлен', 'UpdateDate' => $date]);
                                            $divisionId = DB::getPdo()->lastInsertId(); // Получаем id нового Division
                                        } else {
                                            $divisionId = $division->IdDivision; // Получаем id существующего Division  
                                        }
                                    }
                                    else{
                                        $divisionId = null;
                                    }
                                }
                                $changes['idDivision'] = $divisionId;
                            }
                            else{
                                $newValue = preg_match('/СТАЛО:\s*(.+)/', $value, $matches) ? trim($matches[1]) : '';
                                $changes[$column] = $newValue;
                            }
                        }
                    } 
                }

                // Проверяем существование работника
                [$family, $name, $otchestvo] = array_pad(explode(" ", $fio), 3, null);
                $worker = DB::table($workerTable)->where([['Family','=', $family], 
                                                                    ['Name','=', $name], 
                                                                    ['Otchestvo','=', $otchestvo]])->first();
                if ($worker) {
                    // Если работник существует
                    if ($type === 'УДАЛЕНО') {
                        DB::table($workerTable)->where([['Family','=', $family], 
                        ['Name','=', $name], 
                        ['Otchestvo','=', $otchestvo]])->update([$nameStolb => 'Должно быть удалено']);
                    } elseif ($type === 'НЕ ИЗМЕНЕНО') {
                        DB::table($workerTable)->where([['Family','=', $family], 
                        ['Name','=', $name], 
                        ['Otchestvo','=', $otchestvo]])->update([$nameStolb => 'Не изменено']);
                    } elseif ($type === 'ИЗМЕНЕНО') {
                            if (!empty($changes)){
                                foreach ($changes as $column => $value){
                                    DB::table($workerTable)->where([['Family','=', $family], 
                                    ['Name','=', $name], 
                                    ['Otchestvo','=', $otchestvo]])->update([$column => $value]);
                                }
                                DB::table($workerTable)->where([['Family','=', $family], 
                                ['Name','=', $name], 
                                ['Otchestvo','=', $otchestvo]])->update([$nameStolb => 'Изменения: '.implode(', ', $changesColumn)]);
                            
                            }
                            else{
                                DB::table($workerTable)->where([['Family','=', $family], 
                                    ['Name','=', $name], 
                                    ['Otchestvo','=', $otchestvo]])->update([$nameStolb => 'Не изменено']);
                            }
                    }
                } else {
                    // Если работник не существует, добавляем его
                    DB::table($workerTable)->insert([
                        'Family' => $family,
                        'Name' => $name,
                        'Otchestvo' => $otchestvo,
                        'idDepartmentMO' => $departmentMOId,
                        'IsResponsible' => 0,
                        'idDivision' => $divisionId,
                        'Post' => $post,  
                        'ExternalPhone' => $externalPhone,
                        'InternalPhone' => $internalPhone,
                        'Address' => $address,
                        'Room' => $room,
                        'UpdatedByUser' => 0,
                         "{$nameStolb}" => 'Добавлено'
                    ]);
                    }
                }
            }
        return view('import_dir');
    }
        
}

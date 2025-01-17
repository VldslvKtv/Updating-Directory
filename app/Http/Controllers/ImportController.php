<?php

namespace App\Http\Controllers;

use App\Exports\CookieData;
use App\Exports\DopDataOld;
use App\Imports\DirectoryImport;
use App\Imports\DopDataNew;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ImportController extends Controller
{
    public function home(){
        return view('import_dir');
    }

    /**
     * Манипуляции с новыми данными для справочника (sql + orm)
     * @param mixed $connection
     * @return array
     */
    public function sqlAll($connection){
        DB::connection($connection)->table('ImportingDirectory') // форматируем номер телефона
            ->update(['ExternalPhone' => DB::raw(" '(' + LEFT(ExternalPhone, 3) + ') ' + SUBSTRING(ExternalPhone, 4, 3) + '-' + RIGHT(ExternalPhone, 4) ")
            ]);
        $masDublicate = [];
        $dublicate = DB::select('SELECT [FIO], COUNT(*) AS CountOfDuplicates -- ПОИСК ДУБЛИКАТОВ
                        FROM [UVBA].[dbo].[ImportingDirectory]
                        GROUP BY [FIO]
                        HAVING COUNT(*) > 1');
        foreach ($dublicate as $record) {
            array_push($masDublicate, $record->FIO);
            }
        $data = DB::select('EXEC GetUniqueRecords');
        
        // Удаление всех записей из таблицы ImportingDirectory
        DB::table('ImportingDirectory')->truncate();
        
        // Вставка преобразованных массивов в таблицу ImportingDirectory
        foreach ($data as $record) {
            DB::statement("INSERT INTO ImportingDirectory (FIO, DepartmentMOName, Division, Post, ExternalPhone, InternalPhone, Address, Room) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
                $record->FIO,
                $record->DepartmentMOName,
                $record->Division,
                $record->Post,
                $record->ExternalPhone,
                $record->InternalPhone,
                $record->Address,
                $record->Room
            ]);
        }
        return $masDublicate;
        
    }

    /**
     * Импорт нового справочника
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\RedirectResponse
     */
    public function upload(Request $request){
        DB::table('ImportingDirectory')->truncate();
        $file = $request->file('document');
        $path = $file->store('uploads'); // Сохраняем файл в папку storage/app/uploads
        Excel::import(new DirectoryImport($request->input('start')), $path);

        $connection = config('database.default'); // Получаем значение из .env файла
        $dataDublicate = $this->sqlAll($connection);

        $exportController = new ExportController();

        $dopNew = DopDataNew::getInstance();
        $dataNew = $dopNew->data;
        
        $exportController->export($request->input('path'));

        $dataOld = DopDataOld::$data;
        $export_records = session('exported_data',[]);

        $projectPath = base_path();
        $uploadsPath = $projectPath . '/storage/app/uploads/';
        File::cleanDirectory($uploadsPath);  // чтобы в проекте не оставалось этих файлов

        // return redirect()->back();
        return response()->view('import_dir', compact('dataNew', 'dataOld', 'export_records', 'dataDublicate'));
    }
}

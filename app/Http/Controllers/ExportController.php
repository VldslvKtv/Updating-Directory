<?php

namespace App\Http\Controllers;

use App\Exports\DirectoryExport;
use Maatwebsite\Excel\Facades\Excel;


class ExportController extends Controller 
{
    public function export($path) 
    {
        // Добавляем новый диск динамически
        config()->set('filesystems.disks.new_path', [
            'driver' => 'local',
            'root' => $path,
        ]);

        // Excel::download(new DirectoryExport, 'newDirectory.xlsx');
        return Excel::store(new DirectoryExport, 'newDirectory.xlsx', 'new_path');  // в 'my_path' указать папку для сохранения таблицы
    }

    
}
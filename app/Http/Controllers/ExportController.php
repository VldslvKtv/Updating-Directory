<?php

namespace App\Http\Controllers;

use App\Exports\DirectoryExport;
use Maatwebsite\Excel\Facades\Excel;


class ExportController extends Controller 
{
    public function export() 
    {
        Excel::store(new DirectoryExport, 'newDirectory.xlsx', 'my_path');  // в 'my_path' указать папку для сохранения таблицы
        return Excel::download(new DirectoryExport, 'newDirectory.xlsx');
    }

    
}
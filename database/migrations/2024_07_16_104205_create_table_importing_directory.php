<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ImportingDirectory', function (Blueprint $table) {
            $table->string('FIO', 64)->nullable(false);
	        $table->string('DepartmentMOName', 128)->nullable(true);
	        $table->string('Division', 256)->nullable(true);
	        $table->string('Post', 256)->nullable(true);
		    $table->string('ExternalPhone', 15)->nullable(true);
		    $table->string('InternalPhone', 4)->nullable(true);
		    $table->string('Address', 32)->nullable(true);
		    $table->string('Room', 8)->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ImportingDirectory');
    }
};

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUniqueIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cerpus_contents_shares', function ($table) {
            $table->string('email', 100)->change();
            $table->unique(array('h5p_id', 'email'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cerpus_contents_shares', function ($table) {
            $table->text('email')->change();
            $table->dropUnique(array('h5p_id', 'email'));
        });
    }
}

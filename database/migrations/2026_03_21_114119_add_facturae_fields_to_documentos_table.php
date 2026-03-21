<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('documentos', function (Blueprint $xml) {
            $xml->string('facturae_face_id')->nullable()->after('verifactu_signature');
            $xml->string('facturae_status')->nullable()->after('facturae_face_id');
            $xml->text('facturae_last_error')->nullable()->after('facturae_status');
        });
    }

    public function down()
    {
        Schema::table('documentos', function (Blueprint $xml) {
            $xml->dropColumn(['facturae_face_id', 'facturae_status', 'facturae_last_error']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->unique();
        });

        DB::table('forms')->select('id')->chunkById(100, function (Collection $forms): void {
            foreach ($forms as $form) {
                DB::table('forms')->where('id', $form->id)->update(['public_id' => (string) Str::uuid()]);
            }
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->uuid('public_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};

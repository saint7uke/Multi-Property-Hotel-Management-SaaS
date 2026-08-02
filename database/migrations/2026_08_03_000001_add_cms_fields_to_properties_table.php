<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('tagline', 240)->nullable()->after('country');
            $table->text('description')->nullable()->after('tagline');
            $table->string('hero_image_path')->nullable()->after('description');
            $table->json('gallery_images')->nullable()->after('hero_image_path');
            $table->json('amenities')->nullable()->after('gallery_images');
            $table->json('highlights')->nullable()->after('amenities');
            $table->string('contact_email')->nullable()->after('highlights');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
            $table->time('check_in_time')->nullable()->after('contact_phone');
            $table->time('check_out_time')->nullable()->after('check_in_time');
            $table->string('meta_title', 70)->nullable()->after('check_out_time');
            $table->string('meta_description', 170)->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'tagline',
                'description',
                'hero_image_path',
                'gallery_images',
                'amenities',
                'highlights',
                'contact_email',
                'contact_phone',
                'check_in_time',
                'check_out_time',
                'meta_title',
                'meta_description',
            ]);
        });
    }
};

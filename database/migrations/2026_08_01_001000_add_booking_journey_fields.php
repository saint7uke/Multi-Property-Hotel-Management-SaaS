<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'offers_breakfast')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->boolean('offers_breakfast')->default(true)->after('country');
            });
        }

        if (! Schema::hasColumn('guests', 'address')) {
            Schema::table('guests', function (Blueprint $table) {
                $table->string('address')->nullable()->after('phone');
            });
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'room_count')) {
                $table->unsignedSmallInteger('room_count')->default(1)->after('children');
            }
            if (! Schema::hasColumn('reservations', 'preferred_area')) {
                $table->string('preferred_area')->nullable()->after('room_count');
            }
            if (! Schema::hasColumn('reservations', 'wants_breakfast')) {
                $table->boolean('wants_breakfast')->default(false)->after('preferred_area');
            }
            if (! Schema::hasColumn('reservations', 'addons')) {
                $table->json('addons')->nullable()->after('wants_breakfast');
            }
            if (! Schema::hasColumn('reservations', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('addons');
            }
            if (! Schema::hasColumn('reservations', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'room_count',
                'preferred_area',
                'wants_breakfast',
                'addons',
                'payment_method',
                'terms_accepted_at',
            ]);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('address');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('offers_breakfast');
        });
    }
};

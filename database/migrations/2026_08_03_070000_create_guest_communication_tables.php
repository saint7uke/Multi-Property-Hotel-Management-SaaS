<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name', 160);
            $table->string('email', 160)->index();
            $table->string('phone', 40)->nullable();
            $table->string('inquiry_type', 40)->index();
            $table->text('message');
            $table->string('status', 30)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email', 160)->unique();
            $table->string('status', 20)->default('subscribed')->index();
            $table->timestamp('subscribed_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('source', 40)->default('website_footer');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $inquiries = Permission::findOrCreate('inquiries.manage', 'web');
        $newsletter = Permission::findOrCreate('newsletter.manage', 'web');
        Role::findOrCreate('admin', 'web')->givePermissionTo([$inquiries, $newsletter]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', ['inquiries.manage', 'newsletter.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('newsletter_subscriptions');
        Schema::dropIfExists('contact_inquiries');
    }
};

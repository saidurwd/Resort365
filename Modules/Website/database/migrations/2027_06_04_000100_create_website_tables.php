<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public website (Step 8.2a): a site per property, and how each room type or cottage type appears on it. Texts are json with
 * one key per website language ({"en": …, "bn": …}). Rooms, prices, offers and policies come from their modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 60);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('theme', 20)->default('forest');
            $table->string('name', 150);
            foreach (['tagline', 'intro_title', 'intro_body', 'highlights', 'dining_intro', 'house_rules', 'seo_title', 'seo_description'] as $text) {
                $table->json($text)->nullable();
            }
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('map_url', 500)->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->timestamps();

            // One site per property is kept by the application (Website → Site settings works on the property's site).
            $table->index(['tenant_id', 'property_id']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_published']);
        });

        Schema::create('website_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_site_id')->constrained()->cascadeOnDelete();
            $table->string('unit_key', 40);
            $table->json('headline')->nullable();
            $table->json('description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'website_site_id', 'unit_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_units');
        Schema::dropIfExists('website_sites');
    }
};

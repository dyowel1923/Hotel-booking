<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  PALAWAN TOURISM PORTAL — Laravel 11 Database Migrations
 *  Provincial Tourism Department of Palawan
 *  Stack: Laravel 11 | PHP 8.2 | MySQL 8
 * ═══════════════════════════════════════════════════════════════════
 *
 *  FILE: database/migrations/ (run in numeric order)
 *  Run all:  php artisan migrate --seed
 * ═══════════════════════════════════════════════════════════════════
 */

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000001_create_users_table.php
// ──────────────────────────────────────────────────────────────────
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 200)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 20)->nullable();
            $table->string('nationality', 60)->nullable()->default('Filipino');
            $table->text('address')->nullable();
            $table->string('profile_photo', 255)->nullable();
            $table->enum('role', ['tourist', 'establishment', 'admin'])->default('tourist');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
            $table->index('email');
        });
    }

    public function down(): void { Schema::dropIfExists('users'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000002_create_establishments_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->enum('type', [
                'hotel', 'resort', 'restaurant', 'tourist_spot', 'event_venue', 'lodge', 'pension_house'
            ]);
            $table->text('description')->nullable();
            $table->string('short_desc', 500)->nullable();
            $table->text('address');
            $table->string('municipality', 100); // El Nido, Coron, Puerto Princesa, etc.
            $table->string('barangay', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('facebook_url', 255)->nullable();
            $table->string('cover_photo', 255)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('accreditation_no', 60)->nullable(); // DOT accreditation
            $table->json('amenities')->nullable(); // ['wifi','pool','parking','restaurant',...]
            $table->json('tags')->nullable();       // ['beachfront','family-friendly','dive-resort']
            $table->integer('star_rating')->nullable()->comment('1-5 stars (for hotels)');
            $table->enum('status', ['pending', 'approved', 'suspended', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('avg_rating', 3, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('type');
            $table->index('municipality');
            $table->index('status');
            $table->index('is_featured');
        });
    }

    public function down(): void { Schema::dropIfExists('establishments'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000003_create_establishment_photos_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('establishment_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->onDelete('cascade');
            $table->string('photo_path', 255);
            $table->string('caption', 300)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();

            $table->index('establishment_id');
        });
    }

    public function down(): void { Schema::dropIfExists('establishment_photos'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000004_create_rooms_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->onDelete('cascade');
            $table->string('name', 150);             // "Deluxe Ocean View Room"
            $table->string('room_number', 20)->nullable(); // "101", "Villa 3"
            $table->enum('type', ['standard', 'deluxe', 'suite', 'villa', 'dormitory', 'cottage', 'cabana']);
            $table->text('description')->nullable();
            $table->tinyInteger('capacity')->default(2); // max guests
            $table->tinyInteger('bed_count')->default(1);
            $table->enum('bed_type', ['single', 'double', 'queen', 'king', 'bunk', 'mixed'])->default('double');
            $table->decimal('price_per_night', 10, 2);
            $table->decimal('price_weekend', 10, 2)->nullable(); // Friday–Sunday rate
            $table->decimal('extra_person_fee', 8, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->json('amenities')->nullable(); // ['wifi','aircon','hot_shower','tv','balcony',...]
            $table->json('photos')->nullable();    // Array of image paths
            $table->integer('floor_number')->nullable();
            $table->decimal('room_size_sqm', 6, 2)->nullable();
            $table->timestamps();

            $table->index(['establishment_id', 'is_available']);
        });
    }

    public function down(): void { Schema::dropIfExists('rooms'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000005_create_room_availability_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('room_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->date('blocked_date');
            $table->enum('reason', ['booked', 'maintenance', 'reserved', 'owner_block'])->default('booked');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'blocked_date']);
            $table->index('blocked_date');
        });
    }

    public function down(): void { Schema::dropIfExists('room_availability'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000006_create_services_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->onDelete('cascade');
            $table->string('name', 200);
            $table->enum('category', [
                'tour', 'dining', 'transport', 'activity', 'event', 'spa', 'diving', 'snorkeling', 'other'
            ]);
            $table->text('description')->nullable();
            $table->text('inclusions')->nullable();  // What's included
            $table->text('exclusions')->nullable();  // What's NOT included
            $table->decimal('price', 10, 2);
            $table->enum('price_type', [
                'per_person', 'per_group', 'flat_rate', 'per_night', 'per_hour'
            ])->default('per_person');
            $table->integer('max_capacity')->nullable();
            $table->integer('min_booking_pax')->default(1);
            $table->decimal('duration_hours', 4, 1)->nullable();
            $table->json('available_days')->nullable(); // ['monday','tuesday',...] or null = all days
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('photos')->nullable();
            $table->boolean('requires_confirmation')->default(false); // auto-confirm or manual
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['establishment_id', 'category', 'is_active']);
        });
    }

    public function down(): void { Schema::dropIfExists('services'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000007_create_availability_schedules_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('availability_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->date('schedule_date');
            $table->time('start_time');
            $table->integer('slots_available')->default(0);
            $table->integer('slots_booked')->default(0);
            $table->boolean('is_closed')->default(false); // manually closed
            $table->timestamps();

            $table->unique(['service_id', 'schedule_date', 'start_time']);
            $table->index('schedule_date');
        });
    }

    public function down(): void { Schema::dropIfExists('availability_schedules'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000008_create_bookings_table.php  ← CORE TABLE
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_ref', 20)->unique(); // PLW-2025-00001
            $table->foreignId('tourist_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('establishment_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('booking_type', [
                'room', 'service', 'event', 'tour', 'table_reservation', 'package'
            ]);
            // Dates
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->datetime('event_date')->nullable();    // Tours, events, reservations
            $table->integer('num_nights')->nullable();
            // Guests
            $table->tinyInteger('num_guests')->default(1);
            $table->tinyInteger('num_children')->default(0);
            $table->text('special_requests')->nullable();
            // Financials
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tourism_fee', 10, 2)->default(0.00); // Govt fee (configurable %)
            $table->decimal('service_fee', 10, 2)->default(0.00); // Platform fee
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->string('promo_code', 50)->nullable();
            $table->decimal('total_amount', 12, 2);
            // Status workflow
            $table->enum('status', [
                'pending',       // Tourist submitted, awaiting confirmation
                'confirmed',     // Establishment confirmed
                'checked_in',    // Tourist arrived
                'checked_out',   // Stay completed
                'cancelled',     // Cancelled by tourist or establishment
                'no_show',       // Tourist did not arrive
                'rejected'       // Rejected by establishment
            ])->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->datetime('checked_in_at')->nullable();
            $table->datetime('checked_out_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('cancelled_by', 20)->nullable(); // 'tourist', 'establishment', 'admin'
            $table->datetime('cancelled_at')->nullable();
            // QR Code
            $table->string('qr_code_path', 255)->nullable();
            $table->string('qr_token', 64)->unique()->nullable(); // For QR scan verification
            // Source
            $table->enum('source', ['web', 'mobile', 'walk_in'])->default('web');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['tourist_id', 'status']);
            $table->index(['establishment_id', 'status']);
            $table->index('booking_ref');
            $table->index('check_in_date');
            $table->index('event_date');
        });
    }

    public function down(): void { Schema::dropIfExists('bookings'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000009_create_booking_services_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 12, 2);
            $table->datetime('scheduled_at')->nullable(); // When the service is scheduled
            $table->string('notes', 300)->nullable();
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void { Schema::dropIfExists('booking_services'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000010_create_payments_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->string('payment_ref', 80)->unique(); // PAY-PLW-20250001
            $table->enum('method', ['gcash', 'credit_card', 'debit_card', 'bank_transfer', 'cash', 'paymaya']);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 5)->default('PHP');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'partial_refund'])->default('pending');
            $table->string('gateway', 30)->nullable(); // 'paymongo', 'gcash', 'manual'
            $table->string('gateway_txn_id', 150)->nullable(); // Gateway transaction ID
            $table->json('gateway_response')->nullable(); // Full API response
            $table->string('receipt_path', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason', 300)->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index('payment_ref');
        });
    }

    public function down(): void { Schema::dropIfExists('payments'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000011_create_reviews_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourist_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('establishment_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating'); // 1–5
            $table->string('title', 200)->nullable();
            $table->text('body');
            // Sub-ratings
            $table->tinyInteger('cleanliness_rating')->nullable();
            $table->tinyInteger('service_rating')->nullable();
            $table->tinyInteger('value_rating')->nullable();
            $table->tinyInteger('location_rating')->nullable();
            // Establishment reply
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            // Moderation
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->unique('booking_id'); // One review per booking
            $table->index(['establishment_id', 'is_approved']);
            $table->index('rating');
        });
    }

    public function down(): void { Schema::dropIfExists('reviews'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000012_create_tourist_spots_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('tourist_spots', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->enum('category', [
                'island', 'beach', 'cave', 'reef', 'river', 'national_park', 'cultural', 'wildlife', 'waterfall'
            ]);
            $table->string('municipality', 100);
            $table->text('description')->nullable();
            $table->text('how_to_get_there')->nullable();
            $table->text('best_time_to_visit')->nullable();
            $table->string('cover_photo', 255)->nullable();
            $table->json('gallery')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('entrance_fee', 8, 2)->nullable()->default(0);
            $table->string('operating_hours', 100)->nullable(); // e.g. "6:00 AM – 5:00 PM"
            $table->integer('estimated_duration_hours')->nullable();
            $table->boolean('requires_booking')->default(false); // e.g. Underground River
            $table->integer('daily_visitor_limit')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->foreignId('managed_by')->nullable()->constrained('users')->nullOnDelete(); // admin
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['municipality', 'is_published']);
            $table->index('is_featured');
        });
    }

    public function down(): void { Schema::dropIfExists('tourist_spots'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000013_create_messages_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('attachment_path', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['sender_id', 'receiver_id']);
            $table->index('booking_id');
            $table->index('is_read');
        });
    }

    public function down(): void { Schema::dropIfExists('messages'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000014_create_notifications_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable'); // polymorphic (user)
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_id', 'notifiable_type', 'read_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('notifications'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000015_create_announcements_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('title', 300);
            $table->text('body');
            $table->string('cover_photo', 255)->nullable();
            $table->enum('type', ['news', 'event', 'promo', 'alert', 'maintenance'])->default('news');
            $table->enum('target_audience', ['all', 'tourists', 'establishments'])->default('all');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['type', 'published_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('announcements'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000016_create_wishlists_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourist_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('establishment_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['tourist_id', 'establishment_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('wishlists'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000017_create_tourism_fees_log_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('tourism_fees_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('establishment_id')->constrained()->onDelete('cascade');
            $table->decimal('fee_amount', 10, 2);
            $table->decimal('fee_percentage', 5, 2); // e.g. 2.00 = 2%
            $table->enum('status', ['collected', 'pending_remittance', 'remitted'])->default('collected');
            $table->timestamp('remitted_at')->nullable();
            $table->foreignId('remitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remittance_ref', 100)->nullable();
            $table->timestamps();

            $table->index(['establishment_id', 'status']);
            $table->index('remitted_at');
        });
    }

    public function down(): void { Schema::dropIfExists('tourism_fees_log'); }
};

// ──────────────────────────────────────────────────────────────────
// 2025_01_01_000018_create_system_settings_table.php
// ──────────────────────────────────────────────────────────────────
return new class extends Migration {
    public function up(): void {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, int, bool, json
            $table->string('label', 200)->nullable();
            $table->string('group', 60)->default('general'); // general, payment, booking, tourism_fee
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('system_settings'); }
};
// Default settings seeder values:
// tourism_fee_percentage = 2.00
// booking_auto_confirm_hours = 24
// site_name = "Palawan Tourism Portal"
// max_advance_booking_days = 365
// min_advance_booking_hours = 24
// cancellation_window_hours = 48

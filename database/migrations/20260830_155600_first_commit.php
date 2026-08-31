<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | permissions & departments
        |--------------------------------------------------------------------------
        */
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->json('constraints');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('departments')
                ->restrictOnUpdate()
                ->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | person_types & persons & users
        |--------------------------------------------------------------------------
        */
        Schema::create('person_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('description', 255)->nullable();

            $table->foreignId('permission_id')
                ->nullable()
                ->constrained('permissions')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('full_name', 150);
            $table->string('identity_number', 12)->nullable();
            $table->string('phone', 20);

            $table->foreignId('person_type_id')
                ->constrained('person_types')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->tinyInteger('status')->default(1);
            $table->text('note')->nullable();

            $table->json('special_permission')
                ->nullable();

            $table->timestamps();
        });

        Schema::create('users-custom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->foreignId('person_id')
                ->nullable()
                ->constrained('persons')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->string('phone',20);
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | vehicles & types & colors
        |--------------------------------------------------------------------------
        */
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('description', 255)->nullable();
        });

        Schema::create('vehicle_colors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 50)->unique();
            $table->string('description', 255)->nullable();
        });

        Schema::create('vehicles_personal', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate', 20);

            $table->foreignId('vehicle_type_id')
                ->constrained('vehicle_types')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('vehicle_color_id')
                ->constrained('vehicle_colors')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();

            $table->foreignId('owner_person_id')
                ->constrained('persons')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->tinyInteger('status')->default(1);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | gates & logs
        |--------------------------------------------------------------------------
        */
        Schema::create('gates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('location', 255)->nullable();
            $table->enum('direction', ['IN', 'OUT', 'BOTH'])->default('BOTH');
            $table->tinyInteger('status')->default(1);

            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->timestamps();
        });

        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('person_id')
                ->nullable()
                ->constrained('persons')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('vehicle_id')
                ->nullable()
                ->constrained('vehicles_personal')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('gate_id')
                ->constrained('gates')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->enum('direction', ['IN', 'OUT']);
            $table->string('image_path', 500)->nullable();
            $table->text('note')->nullable();

            $table->timestamp('created_at')->useCurrent()->index();
        });

        /*
        |--------------------------------------------------------------------------
        | vehicles_official & vehicles_guest
        |--------------------------------------------------------------------------
        */
        Schema::create('vehicles_official', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate', 20);

            $table->foreignId('owner_department_id')
                ->constrained('departments')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('vehicle_type_id')
                ->constrained('vehicle_types')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('vehicle_color_id')
                ->nullable()
                ->constrained('vehicle_colors')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles_guest', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate', 20);

            $table->foreignId('vehicle_type_id')
                ->nullable()
                ->constrained('vehicle_types')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('vehicle_color_id')
                ->nullable()
                ->constrained('vehicle_colors')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();

            $table->foreignId('owner_department_id')
                ->nullable()
                ->constrained('departments')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('owner_person_id')
                ->nullable()
                ->constrained('persons')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('permission_id')
                ->nullable()
                ->constrained('permissions')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table->tinyInteger('status')->default(1);
            $table->text('note')->nullable();
            $table->timestamps();
            // $table->check('owner_person_id IS NOT NULL OR owner_department_id IS NOT NULL');
            });
        DB::statement('
            ALTER TABLE vehicles_guest
            ADD CONSTRAINT chk_owner
            CHECK (
                owner_person_id IS NOT NULL
                OR owner_department_id IS NOT NULL
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles_guest');
        Schema::dropIfExists('vehicles_official');
        Schema::dropIfExists('access_logs');
        Schema::dropIfExists('gates');
        Schema::dropIfExists('vehicles_personal');
        Schema::dropIfExists('vehicle_colors');
        Schema::dropIfExists('vehicle_types');
        Schema::dropIfExists('users-custom');
        Schema::dropIfExists('persons');
        Schema::dropIfExists('person_types');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('permissions');
    }
};

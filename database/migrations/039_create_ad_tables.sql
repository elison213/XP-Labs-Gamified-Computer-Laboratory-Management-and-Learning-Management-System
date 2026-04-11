<?php
/**
 * Migration: Create AD tables for Active Directory integration
 * Adds tables for AD computers, users, and groups
 */
$table = 'ad_computers';
if (!Schema::hasTable($table)) {
    Schema::create($table, function (Blueprint $table) {
        $table->id();
        $table->string('ad_dn', 255)->unique();
        $table->string('hostname');
        $table->string('os')->nullable();
        $table->bigInteger('last_logon')->nullable(); // Windows filetime
        $table->timestamp('last_sync')->useCurrent();
        $table->timestamps();
    });
}

$table = 'ad_users';
if (!Schema::hasTable($table)) {
    Schema::create($table, function (Blueprint $table) {
        $table->id();
        $table->string('ad_dn', 255)->unique();
        $table->string('samaccountname');
        $table->string('displayname')->nullable();
        $table->timestamp('last_sync')->useCurrent();
        $table->timestamps();
    });
}

$table = 'ad_groups';
if (!Schema::hasTable($table)) {
    Schema::create($table, function (Blueprint $table) {
        $table->id();
        $table->string('ad_dn', 255)->unique();
        $table->string('cn');
        $table->timestamp('last_sync')->useCurrent();
        $table->timestamps();
    });
}

$table = 'ad_computer_metrics';
if (!Schema::hasTable($table)) {
    Schema::create($table, function (Blueprint $table) {
        $table->id();
        $table->foreignId('computer_id')->constrained('ad_computers')->onDelete('CASCADE');
        $table->integer('cpu_usage')->nullable();
        $table->bigInteger('memory_free')->nullable(); // bytes
        $table->bigInteger('disk_free')->nullable();   // bytes
        $table->timestamp('polled_at')->useCurrent();
        $table->timestamps();
    });
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InitTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('users', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->string('email')->unique();
        //     $table->timestamp('email_verified_at')->nullable();
        //     $table->string('password');
        //     $table->rememberToken();
        //     $table->timestamps();
        // });
        DB::unprepared('CREATE TABLE IF NOT EXISTS `channels` (
            `id`	VARCHAR(50) UNIQUE,
            `playlist_id`	VARCHAR(50) NOT NULL UNIQUE,
            `my_channel_id`	VARCHAR(50),
            `name`	VARCHAR(200),
            `date_last_upload`	VARCHAR(50),
            `date_checked`	INTEGER(50),
            `status`	INTEGER(50),
            `sort`	INTEGER(0),
            PRIMARY KEY(`id`)
        );
        CREATE TABLE IF NOT EXISTS `my_playlists` (
            `id`	VARCHAR(50) NOT NULL UNIQUE,
            `my_channel_id`	VARCHAR(50),
            `name`	VARCHAR(200),
            `status`	INTEGER(50),
            `sort`	INTEGER(0),
            PRIMARY KEY(`id`)
        );
        CREATE TABLE IF NOT EXISTS `videos` (
            `id`	VARCHAR(50) UNIQUE,
            `channel_playlist_id`	VARCHAR(50),
            `my_playlist_id`	VARCHAR(50),
            `title`	VARCHAR(200),
            `date_checked`	VARCHAR(50),
            `date_published`	VARCHAR(50),
            `duration`	VARCHAR(50),
            `status`	INTEGER(50),
            PRIMARY KEY(`id`),
            FOREIGN KEY(`my_playlist_id`) REFERENCES `my_playlists`(`id`),
            FOREIGN KEY(`channel_playlist_id`) REFERENCES `channels`(`playlist_id`)
        );');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}

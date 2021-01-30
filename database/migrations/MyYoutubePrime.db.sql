BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS "channels" (
	"id"	TEXT UNIQUE,
	"playlist_id"	TEXT,
	"my_channel_id"	INTEGER,
	"name"	TEXT,
	"date_last_upload"	TEXT,
	"date_checked"	INTEGER,
	"status"	INTEGER,
	"sort"	INTEGER,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "my_playlists" (
	"id"	TEXT NOT NULL UNIQUE,
	"my_channel_id"	TEXT,
	"name"	TEXT,
	"sort"	INTEGER,
	"status"	INTEGER,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "videos" (
	"id"	TEXT UNIQUE,
	"playlist_id"	TEXT,
	"my_playlist_id"	TEXT,
	"title"	TEXT,
	"date_checked"	TEXT,
	"date_published"	TEXT,
	"duration"	TEXT,
	"status"	INTEGER,
	PRIMARY KEY("id"),
	FOREIGN KEY("my_playlist_id") REFERENCES "my_playlists"("id"),
	FOREIGN KEY("playlist_id") REFERENCES "channels"("playlist_id")
);
COMMIT;

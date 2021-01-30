BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS "playlists" (
	"id"	TEXT NOT NULL UNIQUE,
	"weight"	INTEGER UNIQUE,
	"name"	TEXT,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "channels" (
	"id"	TEXT UNIQUE,
	"name"	TEXT,
	"playlist_id"	TEXT,
	"date_last_upload"	TEXT,
	"date_checked"	TEXT,
	"status"	INTEGER,
	"sort"	INTEGER,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "videos" (
	"id"	TEXT UNIQUE,
	"playlist_id"	TEXT,
	"my_playlist_id"	TEXT,
	"date_checked"	TEXT,
	"title"	TEXT,
	"date_published"	TEXT,
	"duration"	TEXT,
	"status"	INTEGER,
	PRIMARY KEY("id")
);
COMMIT;

BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS "channel_types" (
	"id"	INTEGER UNIQUE,
	"label"	TEXT,
	"account"	TEXT,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "playlists" (
	"id"	TEXT NOT NULL UNIQUE,
	"account"	TEXT,
	"name"	TEXT,
	"sort"	INTEGER,
	"status"	INTEGER,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "filters" (
	"id"	INTEGER UNIQUE,
	"rules"	TEXT,
	"playlist"	TEXT,
	"account"	TEXT,
	"label"	TEXT,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "videos" (
	"id"	TEXT UNIQUE,
	"playlist_id"	TEXT,
	"my_playlist_id"	TEXT,
	"title"	TEXT,
	"date_checked"	TEXT,
	"date_published"	TEXT,
	"duration"	INTEGER,
	"status"	INTEGER,
	PRIMARY KEY("id")
);
CREATE TABLE IF NOT EXISTS "channels" (
	"id"	TEXT UNIQUE,
	"playlist_id"	TEXT,
	"account"	INTEGER,
	"name"	TEXT,
	"date_last_upload"	TEXT,
	"date_checked"	INTEGER,
	"status"	INTEGER,
	"sort"	INTEGER,
	"type"	INTEGER,
	PRIMARY KEY("id")
);
COMMIT;

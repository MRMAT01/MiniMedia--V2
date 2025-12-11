<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbPath = __DIR__ . '/database/mmedia.sqlite';
$newDB = !file_exists($dbPath);

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA foreign_keys = ON");

    if ($newDB) {
        // Create all tables
        $pdo->exec("
        -- Activity Log
        CREATE TABLE activity_log (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL,
          action VARCHAR(50) NOT NULL,
          media_id INTEGER DEFAULT NULL,
          music_id INTEGER DEFAULT NULL,
          details TEXT DEFAULT NULL,
          ip_address VARCHAR(45) DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE INDEX idx_user_action ON activity_log(user_id, action);
        CREATE INDEX idx_date_activity ON activity_log(created_at);

        -- Categories
        CREATE TABLE categories (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name VARCHAR(100) UNIQUE
        );

        -- Download Log
        CREATE TABLE download_log (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL,
          media_id INTEGER DEFAULT NULL,
          music_id INTEGER DEFAULT NULL,
          media_type TEXT CHECK(media_type IN ('media','music')) NOT NULL,
          ip_address VARCHAR(45) DEFAULT NULL,
          user_agent TEXT DEFAULT NULL,
          download_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE SET NULL,
          FOREIGN KEY (music_id) REFERENCES music(id) ON DELETE SET NULL
        );
        CREATE INDEX idx_user_download ON download_log(user_id);
        CREATE INDEX idx_date_download ON download_log(download_at);

        -- Genres
        CREATE TABLE genres (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name VARCHAR(100) UNIQUE NOT NULL
        );

        -- Index Images
        CREATE TABLE index_images (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name VARCHAR(50) NOT NULL,
          link VARCHAR(100) NOT NULL,
          image VARCHAR(255) NOT NULL
        );

        -- Media
        CREATE TABLE media (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          title VARCHAR(255) DEFAULT NULL,
          type VARCHAR(10) DEFAULT NULL,
          path TEXT DEFAULT NULL,
          cover TEXT DEFAULT NULL,
          short_url VARCHAR(16) UNIQUE,
          season INTEGER DEFAULT NULL,
          episode INTEGER DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          backdrop VARCHAR(255) DEFAULT NULL
        );
        CREATE INDEX idx_created_media ON media(created_at);
        CREATE INDEX idx_short_url_media ON media(short_url);
        CREATE INDEX idx_type_media ON media(type);

        -- Media Categories
        CREATE TABLE media_categories (
          media_id INTEGER NOT NULL,
          category_id INTEGER NOT NULL,
          PRIMARY KEY (media_id, category_id),
          FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
          FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        );

        -- Media Genres
        CREATE TABLE media_genres (
          media_id INTEGER NOT NULL,
          genre_id INTEGER NOT NULL,
          PRIMARY KEY (media_id, genre_id),
          FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
          FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
        );

        -- Media Views
        CREATE TABLE media_views (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          media_id INTEGER NOT NULL,
          type TEXT NOT NULL,
          title TEXT NOT NULL,
          viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Music
        CREATE TABLE music (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          title VARCHAR(255) NOT NULL,
          artist VARCHAR(255) DEFAULT NULL,
          album VARCHAR(255) DEFAULT NULL,
          path VARCHAR(500) NOT NULL,
          cover VARCHAR(500) DEFAULT NULL,
          short_url VARCHAR(20) UNIQUE NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX idx_created_music ON music(created_at);
        CREATE INDEX idx_short_url_music ON music(short_url);
        CREATE INDEX idx_artist_music ON music(artist);

        -- Playback Progress
        CREATE TABLE playback_progress (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL,
          media_id INTEGER NOT NULL,
          position INTEGER NOT NULL,
          duration INTEGER NOT NULL,
          percentage REAL GENERATED ALWAYS AS (CAST(position AS REAL) / duration * 100) STORED,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE(user_id, media_id),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
        );
        CREATE INDEX idx_user_updated ON playback_progress(user_id, updated_at);
        CREATE INDEX idx_percentage ON playback_progress(percentage);

        -- Ratings
        CREATE TABLE ratings (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL,
          media_id INTEGER DEFAULT NULL,
          music_id INTEGER DEFAULT NULL,
          media_type TEXT CHECK(media_type IN ('media','music')) NOT NULL,
          rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
          review TEXT DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE(user_id, media_id, music_id),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
          FOREIGN KEY (music_id) REFERENCES music(id) ON DELETE CASCADE
        );
        CREATE INDEX idx_user_rating ON ratings(user_id);
        CREATE INDEX idx_media_rating ON ratings(media_id);
        CREATE INDEX idx_music_rating ON ratings(music_id);
        CREATE INDEX idx_rating_value ON ratings(rating);

        -- Users
        CREATE TABLE users (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          username VARCHAR(50) UNIQUE NOT NULL,
          email VARCHAR(100) UNIQUE NOT NULL,
          password VARCHAR(255) NOT NULL,
          role TEXT CHECK(role IN ('admin','member')) DEFAULT 'member',
          profile_image VARCHAR(255) DEFAULT 'profile/guest.png',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        -- Watchlist
        CREATE TABLE watchlist (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL,
          media_id INTEGER DEFAULT NULL,
          music_id INTEGER DEFAULT NULL,
          media_type TEXT CHECK(media_type IN ('movie','tv','featured','music')) NOT NULL,
          added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE(user_id, media_id, music_id),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
          FOREIGN KEY (music_id) REFERENCES music(id) ON DELETE CASCADE
        );
        CREATE INDEX idx_user_watchlist ON watchlist(user_id);
        CREATE INDEX idx_media_watchlist ON watchlist(media_id);
        CREATE INDEX idx_music_watchlist ON watchlist(music_id);

        -- Media Ratings View (SQLite doesn't support views with UNION in the same way, so we'll create a simple view)
        CREATE VIEW media_ratings AS
        SELECT 
          media_id,
          'media' as type,
          COUNT(*) as rating_count,
          AVG(rating) as avg_rating,
          SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
          SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
          SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
          SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
          SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM ratings
        WHERE media_id IS NOT NULL
        GROUP BY media_id
        UNION ALL
        SELECT 
          music_id as media_id,
          'music' as type,
          COUNT(*) as rating_count,
          AVG(rating) as avg_rating,
          SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
          SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
          SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
          SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
          SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM ratings
        WHERE music_id IS NOT NULL
        GROUP BY music_id;
        ");

        // Insert default data
        $pdo->exec("
        -- Categories
        INSERT INTO categories (id, name) VALUES
        (1, 'Top Rated'),
        (2, 'Trending Now'),
        (4, 'New Releases'),
        (5, 'Tv Series'),
        (6, 'All Movies'),
        (8, 'Featured');

        -- Genres (all 61 genres)
        INSERT INTO genres (id, name) VALUES
        (1, 'Action'), (2, 'Action Thriller'), (3, 'Action-Comedy'),
        (4, 'Adventure'), (5, 'Animation'), (6, 'Biography'),
        (7, 'Children'), (8, 'Comedy'), (9, 'Coming-of-Age'),
        (10, 'Crime'), (11, 'Cyberpunk'), (12, 'Documentary'),
        (13, 'Drama'), (14, 'Dystopian'), (15, 'Eastern'),
        (16, 'Epic'), (17, 'Experimental'), (18, 'Experimental Horror'),
        (19, 'Family'), (20, 'Fantasy'), (21, 'Fantasy Adventure'),
        (22, 'Fantasy Comedy'), (23, 'Film-Noir'), (24, 'Historical'),
        (25, 'Historical Drama'), (26, 'Horror'), (27, 'Independent'),
        (28, 'Live Action'), (29, 'Martial Arts'), (30, 'Medical'),
        (31, 'Military'), (32, 'Mockumentary'), (33, 'Music'),
        (34, 'Musical'), (35, 'Mystery'), (36, 'Noir Thriller'),
        (37, 'Parody'), (38, 'Political Drama'), (39, 'Post-Apocalyptic'),
        (40, 'Psychological Thriller'), (41, 'Romance'), (42, 'Romantic Comedy'),
        (43, 'Romantic Drama'), (44, 'Sci-Fi'), (45, 'Sci-Fi Thriller'),
        (46, 'Science Fiction'), (47, 'Short'), (48, 'Slasher'),
        (49, 'Space Opera'), (50, 'Sport'), (51, 'Steampunk'),
        (52, 'Superhero'), (53, 'Surreal'), (54, 'Suspense'),
        (55, 'Teen'), (56, 'Thriller'), (57, 'Time Travel'),
        (58, 'Urban'), (59, 'War'), (60, 'Western'),
        (61, 'Zombie'), (62, 'All');

        -- Index Images
        INSERT INTO index_images (id, name, link, image) VALUES
        (1, 'Movies', 'home.php?type=movie', 'images/movies_default.png'),
        (2, 'TV Shows', 'tv.php?type=tv', 'images/tv_default.png'),
        (3, 'Music', 'music.php?type=music', 'images/music_default.png'),
        (4, 'Featured', 'featured.php?type=featured', 'images/featured_default.png');

        -- Default Admin User (password: admin)
        INSERT INTO users (id, username, email, password, role, profile_image) VALUES
        (1, 'admin', 'admin@admin.com', '\$2y\$10\$FY8zmQ3/tWtFlc/FhUiPA.2SGon3tgPI4Yxb9pxjsh08sfi6PJ6hG', 'admin', 'profile/guest.png');
        ");

        echo "<!DOCTYPE html>
<html>
<head>
    <title>Installation Complete</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin-top: 20px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class='success'>
        <h2>✅ SQLite Database Created Successfully!</h2>
        <p>Your MiniMediaServer database has been initialized with:</p>
        <ul>
            <li>All required tables</li>
            <li>62 genres</li>
            <li>6 categories</li>
            <li>4 index images</li>
            <li>Default admin user</li>
        </ul>
    </div>
    <div class='info'>
        <h3>Login Credentials:</h3>
        <p><strong>Username:</strong> admin<br>
        <strong>Password:</strong> admin</p>
        <p><a href='index.php'>→ Go to MiniMediaServer</a></p>
    </div>
</body>
</html>";
    } else {
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Exists</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class='warning'>
        <h2>⚠️ Database Already Exists</h2>
        <p>The database file already exists. Installation skipped.</p>
        <p><a href='index.php'>→ Go to MiniMediaServer</a></p>
    </div>
</body>
</html>";
    }
} catch (PDOException $e) {
    echo "<!DOCTYPE html>
<html>
<head>
    <title>Installation Error</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='error'>
        <h2>❌ Database Installation Failed</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
    </div>
</body>
</html>";
}
?>
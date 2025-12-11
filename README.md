# 🎬 MiniMedia V2

**MiniMedia V2** — Next step toward a standalone desktop media server app.  
Currently built with **PHP**, **MySQL**, **JavaScript**, **CSS**, **JSON**, **FFmpeg**, and **getID3**.  
The standalone version transitions to **PHP + SQLite** for full portability.  

If I’ve missed anything — it’s been a massive build already!

---

## 🚀 Features
<ul>
  <li>📚 <b>Library Manager</b> for local media collections — Movies, TV, Music, and Featured.</li>
  <li>💾 Local file storage and caching.</li>
  <li>🔐 Admin-controlled library management.</li>
  <li>🎞️ <b>TMDb API Integration</b> for automatic metadata fetching.<br>
      (Sign up for a free API key: <a href="https://www.themoviedb.org/" target="_blank">https://www.themoviedb.org/</a>)</li>
  <li>📺 TV show episode tracking.</li>
  <li>🖼️ Manual override for covers and backdrops.</li>
  <li>🎯 User filtering by type, category, and genre.</li>
  <li>⚡ Responsive and modern UI (Bootstrap 5).</li>
  <li>👥 Role-based security (Admin/User).</li>
  <li>🪵 Server and delete logs (viewable from Admin panel).</li>
  <li>🧹 Frontend and cache cleanup improvements.</li>
  <li>🔁 Auto cache-busting with <code>?v=timestamp</code>.</li>
  <li>📦 Batch-import from <code>import/</code> folder (experimental).</li>
</ul>

---

## 🗄️ Database
<ul>
  <li>Stores users, media, genres, categories, and front images.</li>
  <li>Uses normalized <code>media_cache/</code> for covers, backdrops, and JSON metadata.</li>
  <li>Raw media files organized in:
    <ul>
      <li><code>movies/</code></li>
      <li><code>tv/&lt;ShowName&gt;/Seasons/</code></li>
      <li><code>music/</code></li>
    </ul>
  </li>
</ul>

---

## 👤 User & Admin
<ul>
  <li>Registration with profile and avatar.</li>
  <li>Secure login and session control.</li>
</ul>

---

## 🛠️ Admin Panel
<ul>
  <li>Full CRUD for users, media, categories, genres, and images.</li>
  <li>TMDb API integration for auto metadata.</li>
  <li>Manual override for covers/backdrops.</li>
  <li>Rescan feature to sync and rebuild cache.</li>
  <li>View/delete server logs.</li>
</ul>

---

## 🎥 User Panel
<ul>
  <li>Browse library with covers and backdrops.</li>
  <li>Filter by type, genre, or category.</li>
  <li>Optional episode tracking for TV shows.</li>
  <li>Personal profile settings.</li>
</ul>

---

## 🧱 Work In Progress
<ul>
  <li>Transition to fully standalone app (EXE + embedded PHP + SQLite).</li>
  <li>System tray control for start/stop/restart With icon.</li>
  <li>Installer improvements for Windows. Not yet for other os</li>
  <li>Maybe Next Theme packs and UI polish soon.</li>
</ul>

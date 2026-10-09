<style>
    :root { --primary:#4f46e5; --dark:#1e1b4b; --bg:#f8fafc; --card:#fff; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; --success:#16a34a; --warning:#f59e0b; --danger:#dc2626; }
    body { background:var(--bg)!important; color:var(--text)!important; }
    .navbar { display:none!important; }
    .app-sidebar { position:fixed; inset:0 auto 0 0; z-index:20; display:flex; flex-direction:column; width:260px; padding:22px 14px; background:var(--dark); color:#fff; box-shadow:4px 0 20px rgba(15,23,42,.12); transition:transform .2s ease; }
    .app-brand { padding:0 10px 23px; font-size:18px; font-weight:800; letter-spacing:.08em; }
    .app-nav { display:grid; gap:4px; }
    .app-nav a { display:flex; align-items:center; gap:11px; padding:11px 12px; border-radius:9px; color:#c7d2fe; font-size:14px; font-weight:700; text-decoration:none; }
    .app-nav a:hover,.app-nav a.active { color:#fff; background:rgba(129,140,248,.28); }
    .app-nav .sidebar-logout { color:#fecaca; } .app-nav .sidebar-logout:hover { background:rgba(220,38,38,.25); color:#fff; }
    .sidebar-spacer { flex:1; } .sidebar-divider { height:1px; margin:16px 8px; background:rgba(255,255,255,.15); }
    .sidebar-user { display:flex; align-items:center; gap:10px; padding:10px; color:#fff; text-decoration:none; border-radius:10px; }
    .sidebar-user:hover { background:rgba(255,255,255,.1); }
    .sidebar-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; background:#c7d2fe; color:var(--dark); display:grid; place-items:center; font-weight:800; }
    .sidebar-user strong,.sidebar-user small { display:block; max-width:165px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; } .sidebar-user small { margin-top:3px; color:#c7d2fe; }
    .sidebar-toggle { position:fixed; top:12px; left:12px; z-index:30; display:none; padding:9px 11px; border:0; border-radius:8px; background:var(--dark); color:#fff; font-size:20px; cursor:pointer; }
    .app-main { width:auto!important; min-height:100vh; margin:32px 5% 32px 292px!important; }
    .card,.table-card,.book-card,.profile-card,.section-card { border:1px solid var(--border); box-shadow:0 8px 24px rgba(15,23,42,.06)!important; }
    @media (max-width:800px) { .app-sidebar { transform:translateX(-100%); } .app-sidebar.open { transform:translateX(0); } .sidebar-toggle { display:block; } .app-main { margin:66px 5% 28px!important; } }
</style>

<link rel="stylesheet" href="assets/app.css">

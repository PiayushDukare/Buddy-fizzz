<?php
require __DIR__ . '/../api/config.php';
page_guard('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin — Buddy Fizz</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../assets/portal.css?v=21" />
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="sidebar__logo">Buddy<span>Fizz</span></div>
      <div class="sidebar__role">Admin Panel</div>
      <nav class="nav" id="nav">
        <a href="#" class="active" data-view="dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <a href="#" data-view="applications"><i class="fa-solid fa-inbox"></i> Applications <span class="badge-count" id="nav-apps" hidden>0</span></a>
        <a href="#" data-view="retailers"><i class="fa-solid fa-store"></i> Retailers</a>
        <a href="#" data-view="partners"><i class="fa-solid fa-handshake"></i> Partners</a>
        <a href="#" data-view="terminations"><i class="fa-solid fa-user-slash"></i> Terminations <span class="badge-count" id="nav-terms" hidden>0</span></a>
        <a href="#" data-view="products"><i class="fa-solid fa-bottle-water"></i> Products</a>
        <a href="#" data-view="orders"><i class="fa-solid fa-box"></i> Orders</a>
        <a href="#" data-view="stock"><i class="fa-solid fa-layer-group"></i> Stock</a>
        <a href="#" data-view="tickets"><i class="fa-solid fa-headset"></i> Tickets</a>
        <a href="#" data-view="revenue"><i class="fa-solid fa-sack-dollar"></i> Revenue</a>
        <a href="#" data-view="payouts"><i class="fa-solid fa-hand-holding-dollar"></i> Partner Payouts</a>
        <a href="#" data-view="analytics"><i class="fa-solid fa-chart-line"></i> Analytics</a>
        <a href="#" data-view="insights"><i class="fa-solid fa-lightbulb"></i> Insights</a>
        <a href="#" data-view="map"><i class="fa-solid fa-map-location-dot"></i> Store Map</a>
        <a href="#" data-view="marketing"><i class="fa-solid fa-bullhorn"></i> Marketing</a>
        <a href="#" data-view="settings"><i class="fa-solid fa-gear"></i> Settings</a>
        <a href="#" data-view="logs"><i class="fa-solid fa-triangle-exclamation"></i> Error Logs</a>
      </nav>
      <div class="sidebar__foot">
        <a href="#" data-logout><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div style="display:flex;align-items:center;gap:.8rem">
          <button class="burger-btn"><i class="fa-solid fa-bars"></i></button>
          <h1 id="page-title">Dashboard</h1>
        </div>
        <div class="topbar__user">
          <button class="icon-btn" id="theme-btn" aria-label="Toggle theme"><i class="fa-solid fa-moon"></i></button>
          <button class="icon-btn" id="notif-bell" aria-label="Notifications"><i class="fa-solid fa-bell"></i><span class="notif-dot" id="notif-dot"></span></button>
          <div class="notif-panel" id="notif-panel"><div class="notif-head">Notifications</div><div class="notif-list"></div></div>
          <div style="text-align:right">
            <div style="font-weight:600" data-user-name>Admin</div>
            <div style="font-size:.75rem;color:var(--muted)" data-user-email></div>
          </div>
          <button class="topbar__avatar" id="profile-btn" data-user-initial aria-label="Profile">A</button>
        </div>
      </header>
      <div class="content" id="view"><div class="spinner"></div></div>
    </div>
  </div>

  <div class="modal-back" id="modal-back">
    <div class="modal" id="modal"></div>
  </div>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="../assets/portal.js?v=21"></script>
  <script src="../assets/admin.js?v=21"></script>
</body>
</html>

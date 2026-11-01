<?php
require __DIR__ . '/../api/config.php';
page_guard('retailer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Retailer — Buddy Fizz</title>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="../assets/portal.css?v=21" />
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="sidebar__logo">Buddy<span>Fizz</span></div>
      <div class="sidebar__role">Retailer Portal</div>
      <nav class="nav" id="nav">
        <a href="#" class="active" data-view="dashboard"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
        <a href="#" data-view="order"><i class="fa-solid fa-cart-plus"></i> Place Order</a>
        <a href="#" data-view="orders"><i class="fa-solid fa-box"></i> My Orders</a>
        <a href="#" data-view="wallet"><i class="fa-solid fa-wallet"></i> Wallet</a>
        <a href="#" data-view="stock"><i class="fa-solid fa-layer-group"></i> My Stock</a>
        <a href="#" data-view="support"><i class="fa-solid fa-headset"></i> Support</a>
      </nav>
      <div class="sidebar__foot"><a href="#" data-logout><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a></div>
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
          <div style="text-align:right"><div style="font-weight:600" data-user-name></div><div style="font-size:.75rem;color:var(--muted)" data-user-email></div></div>
          <button class="topbar__avatar" id="profile-btn" data-user-initial aria-label="Profile"></button>
        </div>
      </header>
      <div class="content" id="view"><div class="spinner"></div></div>
    </div>
  </div>
  <div class="modal-back" id="modal-back"><div class="modal" id="modal"></div></div>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script src="../assets/portal.js?v=21"></script>
  <script src="../assets/retailer.js?v=21"></script>
</body>
</html>

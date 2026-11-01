(function () {
  const finder = document.getElementById("finder");
  const listEl = document.getElementById("finder-list");
  const mapEl = document.getElementById("finder-map");
  const searchEl = document.getElementById("finder-search");
  let map = null;
  let layer = null;
  let userMarker = null;
  let userPos = null;
  let userLabel = "";
  let stores = [];

  const PIN = L.divIcon({
    className: "",
    iconSize: [26, 26],
    iconAnchor: [13, 26],
    html: '<div class="lpin"></div>',
  });
  const USER_PIN = L.divIcon({
    className: "",
    iconSize: [18, 18],
    iconAnchor: [9, 9],
    html: '<div class="lpin lpin--me"></div>',
  });

  function open() {
    finder.classList.add("open");
    document.body.style.overflow = "hidden";
    const seed =
      (document.getElementById("finder-open-input") || {}).value || "";
    searchEl.value = seed;
    ensureMap();
    setTimeout(() => map && map.invalidateSize(), 200);
    load(seed.trim());
  }
  function close() {
    finder.classList.remove("open");
    document.body.style.overflow = "";
  }

  document.getElementById("finder-open").addEventListener("click", (e) => {
    e.preventDefault();
    open();
  });
  document
    .getElementById("finder-open-form")
    .addEventListener("submit", (e) => {
      e.preventDefault();
      open();
    });
  document.getElementById("finder-close").addEventListener("click", close);
  finder.addEventListener("click", (e) => {
    if (e.target === finder) close();
  });

  let t;
  searchEl.addEventListener("input", () => {
    clearTimeout(t);
    const v = searchEl.value.trim();
    t = setTimeout(() => {
      if (v.length >= 3) geocodeNear(v);
      else {
        userPos = null;
        render();
      }
    }, 500);
  });
  searchEl.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      clearTimeout(t);
      const v = searchEl.value.trim();
      if (v.length >= 2) geocodeNear(v);
    }
  });
  document.getElementById("finder-locate").addEventListener("click", locate);

  function ensureMap() {
    if (map) return;
    map = L.map(mapEl, { zoomControl: true }).setView([15.4909, 73.8278], 9);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap",
    }).addTo(map);
    layer = L.layerGroup().addTo(map);
  }

  async function load(seed) {
    listEl.innerHTML = '<div class="finder__loading">Loading stores…</div>';
    try {
      const res = await fetch("api/stores_public.php", {
        credentials: "same-origin",
      });
      const data = await res.json();
      stores = data.stores || [];
      if (seed && seed.length >= 2) geocodeNear(seed);
      else render();
    } catch (e) {
      listEl.innerHTML =
        '<div class="finder__empty">Could not load stores.</div>';
    }
  }

  async function geocodeNear(text) {
    listEl.innerHTML =
      '<div class="finder__loading">Finding stores near “' +
      esc(text) +
      "”…</div>";
    try {
      const url =
        "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=in&q=" +
        encodeURIComponent(text);
      const r = await fetch(url, { headers: { Accept: "application/json" } });
      const d = await r.json();
      if (d && d[0]) {
        userPos = { lat: parseFloat(d[0].lat), lng: parseFloat(d[0].lon) };
        userLabel = d[0].display_name ? d[0].display_name.split(",")[0] : text;
      } else {
        userPos = null;
        userLabel = "";
        listEl.innerHTML =
          '<div class="finder__empty"><i class="fa-solid fa-location-dot"></i><p>Couldn’t find that place. Try a nearby town or pincode.</p></div>';
        return;
      }
    } catch (e) {
      userPos = null;
    }
    render();
  }

  function render() {
    layer.clearLayers();
    let list = stores.slice();
    if (userPos) {
      list.forEach(
        (s) => (s._d = dist(userPos.lat, userPos.lng, +s.lat, +s.lng)),
      );
      list.sort((a, b) => a._d - b._d);
    }

    if (!list.length) {
      listEl.innerHTML =
        '<div class="finder__empty"><i class="fa-solid fa-store-slash"></i><p>No stores found.</p></div>';
      return;
    }

    const banner = userPos
      ? `<div class="finder__near"><i class="fa-solid fa-location-crosshairs"></i> Nearest stores${userLabel ? " to " + esc(userLabel) : ""} — closest is <b>${list[0]._d.toFixed(1)} km</b> away</div>`
      : "";
    listEl.innerHTML =
      banner +
      list
        .map((s, i) => {
          const area = [s.village, s.taluka, s.district, s.state]
            .filter(Boolean)
            .join(", ");
          return `<button class="finder__item" data-i="${i}">
        <span class="finder__pin"><i class="fa-solid fa-location-dot"></i></span>
        <span class="finder__meta"><b>${esc(s.name)}</b><small>${esc(area || "")}${s.pincode ? " · " + esc(s.pincode) : ""}</small><em>${esc(labelType(s.type))}</em></span>
        ${s._d != null ? `<span class="finder__dist">${s._d.toFixed(1)} km</span>` : ""}</button>`;
        })
        .join("");
    listEl
      .querySelectorAll(".finder__item")
      .forEach((b) =>
        b.addEventListener("click", () => focusStore(list[+b.dataset.i])),
      );

    const pts = [];
    list.forEach((s) => {
      const ll = [+s.lat, +s.lng];
      L.marker(ll, { icon: PIN, title: s.name })
        .addTo(layer)
        .bindPopup(popupHtml(s));
      pts.push(ll);
    });
    if (userPos) {
      L.marker([userPos.lat, userPos.lng], { icon: USER_PIN }).addTo(layer);
      pts.push([userPos.lat, userPos.lng]);
    }
    if (pts.length)
      map.fitBounds(L.latLngBounds(pts).pad(0.25), { maxZoom: 15 });
  }

  function popupHtml(s) {
    const area = [s.village, s.taluka, s.district, s.state, s.pincode]
      .filter(Boolean)
      .join(", ");
    return `<b>${esc(s.name)}</b><br><small>${esc(area)}</small><br><a href="https://www.openstreetmap.org/directions?to=${s.lat}%2C${s.lng}" target="_blank" rel="noopener" style="color:#2e7d32;font-weight:600">Directions →</a>`;
  }
  function focusStore(s) {
    map.setView([+s.lat, +s.lng], 15);
    layer.eachLayer((l) => {
      if (l.options && l.options.title === s.name) l.openPopup();
    });
  }
  function locate() {
    if (!navigator.geolocation) return;
    listEl.innerHTML =
      '<div class="finder__loading">Getting your location…</div>';
    navigator.geolocation.getCurrentPosition(
      (p) => {
        userPos = { lat: p.coords.latitude, lng: p.coords.longitude };
        userLabel = "your current location";
        render();
      },
      () => {
        listEl.innerHTML =
          '<div class="finder__empty"><i class="fa-solid fa-location-dot"></i><p>Location access denied. Type your area or pincode instead.</p></div>';
      },
      { enableHighAccuracy: true, timeout: 8000 },
    );
  }

  function dist(a, b, c, d) {
    const R = 6371,
      dLat = rad(c - a),
      dLng = rad(d - b);
    const x =
      Math.sin(dLat / 2) ** 2 +
      Math.cos(rad(a)) * Math.cos(rad(c)) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
  }
  const rad = (d) => (d * Math.PI) / 180;
  const esc = (s) =>
    String(s ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  const labelType = (t) =>
    ({
      retail: "Retail Store",
      supermarket: "Supermarket",
      distributor: "Distributor",
      online: "Online Store",
    })[t] || t;
})();

const view = document.getElementById("view");
const pageTitle = document.getElementById("page-title");
const modalBack = document.getElementById("modal-back");
const modal = document.getElementById("modal");
let cart = {};
let productCache = [];
let reportLevel = "taluka";

function openModal(h) {
  modal.innerHTML = h;
  modalBack.classList.add("open");
}
function closeModal() {
  if (typeof stopThreadPolling === "function") stopThreadPolling();
  modalBack.classList.remove("open");
  modal.innerHTML = "";
}
modalBack.addEventListener("click", (e) => {
  if (e.target === modalBack) closeModal();
});

const badge = (s) => `<span class="badge badge--${s}">${esc(s)}</span>`;
const errBox = (m) =>
  `<div class="empty"><i class="fa-solid fa-triangle-exclamation"></i><p>${esc(m)}</p></div>`;
const emptyBox = (i, m) =>
  `<div class="empty"><i class="fa-solid ${i}"></i><p>${esc(m)}</p></div>`;
const stat = (l, val, i) =>
  `<div class="stat"><div class="stat__icon"><i class="fa-solid ${i}"></i></div><div class="stat__label">${esc(l)}</div><div class="stat__value">${esc(val)}</div></div>`;

const views = {
  dashboard,
  retailers,
  orders,
  orderstock,
  mystock,
  reports,
  support,
  map: renderMap,
};
const STATUS_COLORS = {
  pending: "#f2b705",
  approved: "#7cb342",
  processing: "#3d8bfd",
  dispatched: "#17a2b8",
  delivered: "#2e7d32",
  cancelled: "#e5533c",
  rejected: "#9aa7a0",
};
const GRID = { color: "#eef2ea" };
const _charts = {};
let _pMap = null;
function mkChart(id, cfg) {
  if (_charts[id]) _charts[id].destroy();
  _charts[id] = new Chart(document.getElementById(id), cfg);
}
function heatmap(el, heat) {
  heat = heat || [];
  if (!heat.length) {
    el.innerHTML = emptyBox("fa-table-cells", "No demand data yet.");
    return;
  }
  const areas = [...new Set(heat.map((h) => h.area))];
  const products = [...new Set(heat.map((h) => h.product))];
  const grid = {};
  let max = 0;
  heat.forEach((h) => {
    grid[h.area + "|" + h.product] = +h.units;
    max = Math.max(max, +h.units);
  });
  let html = `<div class="heat"><table class="heat__t"><thead><tr><th></th>${products.map((p) => `<th>${esc(p)}</th>`).join("")}</tr></thead><tbody>`;
  areas.forEach((a) => {
    html +=
      `<tr><th>${esc(a)}</th>` +
      products
        .map((p) => {
          const u = grid[a + "|" + p] || 0;
          const t = max ? u / max : 0;
          return `<td style="background:rgba(46,125,50,${(0.06 + t * 0.88).toFixed(2)});color:${t > 0.5 ? "#fff" : "#1f2a22"}" title="${esc(a)} · ${esc(p)}: ${u}">${u || ""}</td>`;
        })
        .join("") +
      "</tr>";
  });
  el.innerHTML = html + "</tbody></table></div>";
}
function setView(name) {
  document
    .querySelectorAll("#nav a")
    .forEach((a) => a.classList.toggle("active", a.dataset.view === name));
  pageTitle.textContent = document
    .querySelector(`#nav a[data-view="${name}"]`)
    .textContent.trim();
  view.innerHTML = '<div class="spinner"></div>';
  (views[name] || dashboard)();
  stopPolling();
  const live = { dashboard, orders, support }[name];
  if (live) startPolling(live);
}
document.querySelectorAll("#nav a").forEach((a) =>
  a.addEventListener("click", (e) => {
    e.preventDefault();
    setView(a.dataset.view);
    document.querySelector(".sidebar").classList.remove("open");
  }),
);

function regionTable(rows) {
  if (!rows || !rows.length) return emptyBox("fa-map", "No sales data yet.");
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Area</th><th>Orders</th><th>Value</th></tr></thead>
    <tbody>${rows.map((r) => `<tr><td><b>${esc(r.area)}</b></td><td>${r.orders}</td><td>${money(r.value)}</td></tr>`).join("")}</tbody></table></div>`;
}
function topTable(rows) {
  if (!rows || !rows.length)
    return emptyBox("fa-bottle-water", "No product sales yet.");
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>Units</th><th>Value</th></tr></thead>
    <tbody>${rows.map((r) => `<tr><td>${esc(r.name)}</td><td>${r.units}</td><td>${money(r.value)}</td></tr>`).join("")}</tbody></table></div>`;
}

async function dashboard() {
  try {
    const d = await api("partner/stats.php?level=taluka&days=30");
    const k = d.kpi || {};
    view.innerHTML = `<div class="stat-row">
        ${stat("My Retailers", k.retailers || 0, "fa-store")}
        ${stat("Their Orders", k.retailer_orders || 0, "fa-box")}
        ${stat("Sales Revenue", money(k.revenue), "fa-indian-rupee-sign")}
        ${stat("Collected", money(k.collected), "fa-circle-check")}
        ${stat("Outstanding", money(k.outstanding), "fa-hourglass-half")}
        ${stat("Pending to Fulfil", k.pending_orders || 0, "fa-clock")}
      </div>
      <div class="card" style="margin-top:0"><div class="card__head"><h2>My Profit (per-bottle earnings)</h2></div><div class="card__body" id="p-earnings"><div class="spinner"></div></div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Revenue (30 days)</h2></div><div class="card__body"><div class="chart-box" style="height:16rem"><canvas id="p-trend"></canvas></div></div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>My Territory</h2></div><div class="card__body" id="p-territory"><div class="spinner"></div></div></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-top:1.2rem">
        <div class="card"><div class="card__head"><h2>Top Retailers</h2></div><div class="card__body"><div class="chart-box" style="height:15rem"><canvas id="p-top"></canvas></div></div></div>
        <div class="card"><div class="card__head"><h2>Order Status</h2></div><div class="card__body"><div class="chart-box" style="height:15rem"><canvas id="p-funnel"></canvas></div></div></div>
      </div>`;
    view.insertAdjacentHTML("afterbegin", await announcementBanner());
    drawTrend(d.trend);
    drawTop(d.top_retailers);
    drawFunnel(d.funnel);
    loadTerritory();
    loadEarnings();
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
async function loadEarnings() {
  const box = document.getElementById("p-earnings");
  if (!box) return;
  try {
    const e = await api("partner/earnings.php");
    const prod = (e.by_product || [])
      .map(
        (x) =>
          `<tr><td>${esc(x.name)}</td><td>${Number(x.bottles).toLocaleString("en-IN")}</td><td><b>${money(x.profit)}</b></td></tr>`,
      )
      .join("");
    box.innerHTML = `
      <div class="stat-row" style="grid-template-columns:repeat(3,1fr)">
        ${stat("Total Profit Earned", money(e.earned), "fa-coins")}
        ${stat("Paid Out by Company", money(e.settled), "fa-hand-holding-dollar")}
        ${stat("Pending Payout", money(e.outstanding), "fa-hourglass-half")}
      </div>
      <p class="muted" style="margin:.4rem 0 .8rem">You earn a fixed profit per bottle your retailers buy (delivered orders). The company settles your pending profit to you.</p>
      ${
        prod
          ? `<div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>Bottles Sold</th><th>Your Profit</th></tr></thead><tbody>${prod}</tbody></table></div>`
          : '<p class="muted">No delivered sales yet.</p>'
      }
      ${
        (e.history || []).length
          ? `<h4 style="margin:1rem 0 .5rem;color:var(--heading);font-family:'Baloo 2',cursive">Payout history</h4><div class="table-wrap"><table class="data"><thead><tr><th>Date</th><th>Amount</th><th>Note</th></tr></thead><tbody>${e.history.map((h) => `<tr><td class="muted">${fmtDate(h.created_at)}</td><td><b>${money(h.amount)}</b></td><td class="muted">${esc(h.note || "—")}</td></tr>`).join("")}</tbody></table></div>`
          : ""
      }`;
  } catch (err) {
    box.innerHTML = '<p class="muted">Could not load earnings.</p>';
  }
}
const TERR_ICON = {
  state: "fa-map",
  district: "fa-location-dot",
  taluka: "fa-map-pin",
};
async function loadTerritory() {
  const box = document.getElementById("p-territory");
  if (!box) return;
  try {
    const t = (await api("partner/territories.php")).territories || [];
    box.innerHTML = t.length
      ? `<div style="display:flex;flex-wrap:wrap;gap:.5rem">${t
          .map(
            (x) =>
              `<span class="terr-chip"><i class="fa-solid ${TERR_ICON[x.level] || "fa-location-dot"}"></i> ${esc(x.value)} <em>${esc(x.level)}</em></span>`,
          )
          .join("")}</div>`
      : `<p class="muted">No territory assigned yet. Contact the company to get your area set up.</p>`;
  } catch (e) {
    box.innerHTML = `<p class="muted">Could not load territory.</p>`;
  }
}
function drawTrend(rows) {
  rows = rows || [];
  mkChart("p-trend", {
    type: "line",
    data: {
      labels: rows.map((r) => r.d.slice(5)),
      datasets: [
        {
          label: "Revenue",
          data: rows.map((r) => +r.revenue),
          borderColor: "#2e7d32",
          backgroundColor: "rgba(46,125,50,.12)",
          fill: true,
          tension: 0.3,
          borderWidth: 2,
          pointRadius: 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (c) => money(c.raw) } },
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: GRID,
          ticks: { callback: (v) => "₹" + v },
        },
        x: { grid: { display: false } },
      },
    },
  });
}
function drawTop(rows) {
  rows = (rows || []).slice(0, 8);
  mkChart("p-top", {
    type: "bar",
    data: {
      labels: rows.map((r) => r.name),
      datasets: [
        {
          data: rows.map((r) => +r.revenue),
          backgroundColor: "#7cb342",
          borderRadius: 4,
          maxBarThickness: 24,
        },
      ],
    },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (c) => money(c.raw) } },
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: GRID,
          ticks: { callback: (v) => "₹" + v },
        },
        y: { grid: { display: false } },
      },
    },
  });
}
function drawFunnel(rows) {
  rows = rows || [];
  mkChart("p-funnel", {
    type: "doughnut",
    data: {
      labels: rows.map((r) => r.status),
      datasets: [
        {
          data: rows.map((r) => +r.n),
          backgroundColor: rows.map(
            (r) => STATUS_COLORS[r.status] || "#9aa7a0",
          ),
          borderColor: "#fff",
          borderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "56%",
      plugins: {
        legend: {
          position: "right",
          labels: { boxWidth: 12, font: { size: 11 } },
        },
      },
    },
  });
}

async function retailers() {
  try {
    const d = await api("partner/retailers.php");
    const rows = d.retailers || [];
    view.innerHTML = `<div class="card"><div class="card__head"><h2>My Retailers</h2>
      <div style="display:flex;gap:.5rem;align-items:center"><span class="muted">${rows.length} stores</span>${rows.length ? '<button class="btn btn--green btn--sm" id="email-all"><i class="fa-solid fa-paper-plane"></i> Email All</button>' : ""}</div></div>
      <div class="card__body">${
        rows.length
          ? `<div class="table-wrap"><table class="data"><thead><tr><th>Store</th><th>Owner</th><th>Location</th><th>GSTIN</th><th>Orders</th><th>Last Order</th><th>Status</th><th></th></tr></thead>
        <tbody>${rows
          .map(
            (
              s,
            ) => `<tr><td><b>${esc(s.name)}</b><div class="muted">${esc(s.email || "")}</div></td><td>${esc(s.owner_name || "—")}<div class="muted">${esc(s.phone || "")}</div></td>
          <td>${esc([s.taluka, s.district].filter(Boolean).join(", ") || "—")}<div class="muted">${esc(s.pincode || "")}</div></td>
          <td>${s.gstin ? `<span class="mono">${esc(s.gstin)}</span>` : '<span class="muted">—</span>'}</td><td>${s.order_count}</td>
          <td class="muted">${s.last_order ? fmtDate(s.last_order) : "—"}</td><td>${badge(s.account_status || s.status)}</td>
          <td style="white-space:nowrap"><button class="btn btn--ghost btn--sm" data-store='${encodeURIComponent(JSON.stringify(s))}' title="Edit details"><i class="fa-solid fa-pen"></i></button>${s.email ? ` <button class="btn btn--ghost btn--sm" data-email="${s.id}" data-email-name="${esc(s.name)}" title="Email this retailer"><i class="fa-solid fa-envelope"></i></button>` : ""}${
            s.account_status === "terminated"
              ? ""
              : Number(s.term_pending)
                ? ` <button class="btn btn--ghost btn--sm" disabled title="Termination request pending admin review"><i class="fa-solid fa-hourglass-half"></i> Requested</button>`
                : ` <button class="btn btn--danger btn--sm" data-term-req="${s.id}" data-name="${esc(s.name)}" title="Request termination"><i class="fa-solid fa-user-slash"></i></button>`
          }</td></tr>`,
          )
          .join("")}</tbody></table></div>`
          : emptyBox("fa-store", "No retailers assigned yet.")
      }</div></div>`;
    view
      .querySelectorAll("[data-store]")
      .forEach((b) =>
        b.addEventListener("click", () =>
          retailerForm(JSON.parse(decodeURIComponent(b.dataset.store))),
        ),
      );
    const emAll = document.getElementById("email-all");
    if (emAll)
      emAll.addEventListener("click", () =>
        emailCompose(0, "All my retailers"),
      );
    view
      .querySelectorAll("[data-email]")
      .forEach((b) =>
        b.addEventListener("click", () =>
          emailCompose(Number(b.dataset.email), b.dataset.emailName || ""),
        ),
      );
    view.querySelectorAll("[data-term-req]").forEach((b) =>
      b.addEventListener("click", async () => {
        const reason = await uiPrompt(
          "Ask the admin to terminate " +
            (b.dataset.name || "this retailer") +
            "? The admin reviews and decides. Please give a reason:",
          {
            title: "Request termination",
            confirmText: "Send request",
            placeholder: "e.g. Store closed / repeated defaults",
            validate: (v) =>
              String(v).trim().length >= 3 ? "" : "Please enter a brief reason.",
          },
        );
        if (reason === null) return;
        try {
          const r = await api("partner/terminate_request.php", {
            method: "POST",
            body: { store_id: b.dataset.termReq, reason },
          });
          toast(r.message);
          retailers();
        } catch (e) {
          toast(e.message, "error");
        }
      }),
    );
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function emailCompose(storeId, targetName) {
  openModal(`<div class="modal__head"><h3>Email ${storeId ? esc(targetName) : "All Retailers"}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <p class="muted" style="margin-top:0">Sending to <b>${storeId ? esc(targetName) : "all your retailers"}</b>. Replies come back to your email.</p>
      <div class="field"><label>Subject</label><input class="input" id="em-subject" maxlength="200" placeholder="e.g. New stock available this week"></div>
      <div class="field"><label>Message</label><textarea id="em-message" rows="7" placeholder="Write your message…"></textarea></div>
      <div class="field"><label>Attachments <span class="muted" style="font-weight:400">(images / PDF, up to 6)</span></label><input type="file" id="em-files" class="file-input" multiple accept="image/*,application/pdf"></div>
    </div>
    <div class="modal__foot"><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="em-send"><i class="fa-solid fa-paper-plane"></i> Send</button></div>`);
  document.getElementById("em-send").addEventListener("click", async () => {
    const subject = document.getElementById("em-subject").value.trim();
    const message = document.getElementById("em-message").value.trim();
    if (!subject || !message) {
      toast("Subject and message are required", "error");
      return;
    }
    const fd = new FormData();
    fd.append("subject", subject);
    fd.append("message", message);
    if (storeId) fd.append("store_id", storeId);
    const files = document.getElementById("em-files").files;
    for (let i = 0; i < files.length; i++) fd.append("attachments[]", files[i]);
    const btn = document.getElementById("em-send");
    btn.disabled = true;
    btn.innerHTML = "Sending…";
    try {
      const r = await apiForm("partner/email_send.php", fd);
      toast(r.message);
      closeModal();
    } catch (e) {
      toast(e.message, "error");
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send';
    }
  });
}
function retailerForm(s) {
  openModal(`<div class="modal__head"><h3>Business Details — ${esc(s.name)}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label>Owner Name</label><input class="input" id="rt-owner" value="${esc(s.owner_name || "")}"></div>
      <div class="field"><label>Phone</label><input class="input" id="rt-phone" value="${esc(s.phone || "")}"></div>
      <div class="field"><label>GSTIN <span class="muted" style="font-weight:400">(optional — shown on invoices)</span></label><input class="input" id="rt-gstin" maxlength="15" style="text-transform:uppercase" placeholder="15-character GSTIN" value="${esc(s.gstin || "")}"></div>
      <div class="field"><label>Billing Address</label><textarea class="input" id="rt-addr" rows="2">${esc(s.address || "")}</textarea></div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1.1rem 0">
      <h4 style="color:var(--heading);margin-bottom:.6rem;font-family:'Baloo 2',cursive">Wallet &amp; Credit</h4>
      <div style="display:flex;justify-content:space-between;align-items:center;background:var(--soft);border-radius:.5rem;padding:.6rem .8rem;margin-bottom:.7rem"><span>Wallet balance</span><b style="color:var(--heading)">${money(s.wallet_balance || 0)}</b></div>
      <div style="display:flex;gap:.4rem;margin-bottom:.9rem"><input class="input" id="rt-wamt" type="number" step="0.01" placeholder="Amount (− to deduct)" style="flex:1"><input class="input" id="rt-wnote" placeholder="Note" style="flex:1"><button class="btn btn--lime btn--sm" id="rt-wallet">Update</button></div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.88rem;margin-bottom:.6rem"><input type="checkbox" id="rt-credit" ${Number(s.credit_allowed) ? "checked" : ""}> Allow <b>Pay Later</b> (credit) for this retailer</label>
      <div class="field"><label>Credit Limit (₹) <span class="muted" style="font-weight:400">(0 = unlimited)</span></label><input class="input" id="rt-climit" type="number" step="0.01" value="${esc(s.credit_limit || 0)}"></div>
    </div>
    <div class="modal__foot"><a class="btn btn--ghost" href="../api/statement.php?store_id=${s.id}" target="_blank"><i class="fa-solid fa-file-lines"></i> Statement</a><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="rt-save">Save</button></div>`);
  document.getElementById("rt-wallet").addEventListener("click", async () => {
    try {
      const r = await api("admin/wallet_adjust.php", {
        method: "POST",
        body: {
          store_id: s.id,
          amount: document.getElementById("rt-wamt").value,
          note: document.getElementById("rt-wnote").value.trim(),
        },
      });
      toast(r.message);
      closeModal();
      retailers();
    } catch (e) {
      toast(e.message, "error");
    }
  });
  document.getElementById("rt-save").addEventListener("click", async () => {
    try {
      const r = await api("partner/retailer_save.php", {
        method: "POST",
        body: {
          id: s.id,
          owner_name: document.getElementById("rt-owner").value.trim(),
          phone: document.getElementById("rt-phone").value.trim(),
          gstin: document.getElementById("rt-gstin").value.trim(),
          address: document.getElementById("rt-addr").value.trim(),
          credit_allowed: document.getElementById("rt-credit").checked ? 1 : 0,
          credit_limit: document.getElementById("rt-climit").value,
        },
      });
      toast(r.message);
      closeModal();
      retailers();
    } catch (e) {
      toast(e.message, "error");
    }
  });
}

async function orders() {
  try {
    const d = await api("orders/list.php");
    const rows = d.orders || [];
    const me = String(window.__user.id);
    const toFulfil = rows.filter(
      (o) =>
        String(o.supplier_id) === me &&
        ["pending", "approved", "processing"].includes(o.status),
    ).length;
    view.innerHTML = `<div class="card"><div class="card__head"><h2>Orders</h2><span class="muted">Retailer orders to fulfil &amp; your own purchases${toFulfil ? ` · <b style="color:var(--heading)">${toFulfil} need action</b>` : ""}</span></div>
      <div class="card__body">${
        rows.length
          ? `<div class="table-wrap"><table class="data"><thead><tr><th>Order</th><th>Type</th><th>Store</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>${rows
          .map((o) => {
            const incoming = String(o.supplier_id) === me;
            const type = incoming
              ? '<span class="badge badge--retailer">Retailer order</span>'
              : '<span class="muted">My purchase</span>';
            const act =
              incoming &&
              ["pending", "approved", "processing"].includes(o.status);
            return `<tr${act ? ' style="background:#fbfced"' : ""}><td><b>#${o.id}</b></td><td>${type}</td><td>${esc(o.store_name)}</td><td>${o.item_count}</td><td>${money(o.total)}</td><td>${badge(o.status)}</td><td class="muted">${fmtDate(o.created_at)}</td>
          <td><button class="btn btn--ghost btn--sm" data-order="${o.id}"><i class="fa-solid fa-eye"></i></button></td></tr>`;
          })
          .join("")}</tbody></table></div>`
          : emptyBox("fa-box-open", "No orders yet.")
      }</div></div>`;
    view
      .querySelectorAll("[data-order]")
      .forEach((b) =>
        b.addEventListener("click", () => openOrder(b.dataset.order)),
      );
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
async function openOrder(id) {
  try {
    const data = await api(`orders/detail.php?id=${id}`);
    const o = data.order;
    const items = data.items || [];
    const mine = String(o.supplier_id) === String(window.__user.id);
    const pflow = [
      "pending",
      "approved",
      "processing",
      "dispatched",
      "delivered",
    ];
    const steps = pflow
      .map((s) =>
        s === o.status
          ? `<span class="status-step status-step--on" aria-current="true">${s}</span>`
          : s === "pending"
            ? `<span class="status-step status-step--done">${s}</span>`
            : `<button class="status-step" data-status="${s}">${s}</button>`,
      )
      .join("");
    openModal(`<div class="modal__head"><h3>Order #${o.id}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
      <div class="modal__body"><div style="margin-bottom:.7rem">${badge(o.status)} <span class="muted">${fmtDate(o.created_at)}</span></div>
        <dl class="kv"><dt>Store</dt><dd>${esc(o.store_name)}</dd><dt>Location</dt><dd>${esc([o.taluka, o.district, o.state].filter(Boolean).join(", "))}</dd></dl>
        <table class="data" style="width:100%;margin-top:.8rem"><thead><tr><th>Product</th><th>Qty</th><th>Total</th></tr></thead>
        <tbody>${items.map((i) => `<tr><td><div class="item-cell">${itemThumb(i.image)}<span>${esc(i.name)} <span class="muted">${esc(i.size || "")}</span></span></div></td><td>${i.qty}</td><td>${money(i.line_total)}</td></tr>`).join("")}</tbody></table>
        <div style="text-align:right;margin-top:.6rem"><b>Total: ${money(o.total)}</b></div>
        <div style="margin-top:.6rem">Payment: <span class="pay pay--${o.payment_status}">${esc(o.payment_status)}</span> <span class="muted">— ${money(o.amount_paid)} of ${money(o.total)}</span></div>
        ${o.delivery_date ? `<div style="margin-top:.5rem"><i class="fa-solid fa-truck-fast" style="color:var(--green)"></i> Delivery: <b>${fmtDate(o.delivery_date)}</b></div>` : ""}
        ${
          mine
            ? `<div style="margin-top:.8rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap"><select class="select" id="pay-status"><option value="unpaid">Unpaid</option><option value="partial">Partial</option><option value="paid">Paid</option></select><input class="input" id="pay-amt" type="number" step="0.01" placeholder="Amount paid" value="${o.amount_paid || ""}" style="max-width:9rem;display:none"><button class="btn btn--lime btn--sm" id="rec-pay">Update Payment</button></div>
        <div style="margin-top:1rem"><label style="font-size:.8rem;font-weight:600;color:var(--heading)">Schedule delivery</label><div style="display:flex;gap:.4rem;margin-top:.4rem"><input class="input" type="date" id="sched-date" value="${o.delivery_date || ""}" style="max-width:11rem"><button class="btn btn--ghost btn--sm" id="sched-btn">Set date</button></div></div>
        <div style="margin-top:1rem"><label style="font-size:.8rem;font-weight:600;color:var(--heading)">Order status</label>
          <div class="status-steps">${steps}</div>
          ${o.status === "cancelled" || o.status === "delivered" ? "" : `<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.6rem"><button class="btn btn--danger btn--sm" data-status="cancelled">cancel</button></div>`}</div>`
            : `<p class="muted" style="margin-top:.8rem">This is your order to the company. The company reviews and approves it — no online payment needed.</p>`
        }
      </div><div class="modal__foot"><a class="btn btn--ghost" href="../api/invoice.php?id=${o.id}" target="_blank"><i class="fa-solid fa-file-invoice"></i> Invoice</a><button class="btn btn--ghost" onclick="closeModal()">Close</button></div>`);
    const ps = document.getElementById("pay-status");
    const pa = document.getElementById("pay-amt");
    if (ps) {
      ps.value = o.payment_status;
      pa.style.display = o.payment_status === "partial" ? "" : "none";
      ps.addEventListener("change", () => {
        pa.style.display = ps.value === "partial" ? "" : "none";
      });
    }
    const sb = document.getElementById("sched-btn");
    if (sb)
      sb.addEventListener("click", async () => {
        try {
          const r = await api("orders/schedule.php", {
            method: "POST",
            body: {
              id: o.id,
              delivery_date: document.getElementById("sched-date").value,
            },
          });
          toast(r.message);
          closeModal();
          orders();
        } catch (e) {
          toast(e.message, "error");
        }
      });
    const rp = document.getElementById("rec-pay");
    if (rp)
      rp.addEventListener("click", async () => {
        try {
          const r = await api("orders/payment.php", {
            method: "POST",
            body: { id: o.id, status: ps.value, amount: pa.value },
          });
          toast(r.message);
          closeModal();
          orders();
        } catch (e) {
          toast(e.message, "error");
        }
      });
    modal.querySelectorAll("[data-status]").forEach((b) =>
      b.addEventListener("click", async () => {
        try {
          const r = await api("orders/status.php", {
            method: "POST",
            body: { id: o.id, status: b.dataset.status },
          });
          toast(r.message);
          closeModal();
          orders();
        } catch (e) {
          toast(e.message, "error");
        }
      }),
    );
  } catch (e) {
    toast(e.message, "error");
  }
}

async function mystock() {
  try {
    const rows = (await api("stock.php")).stock || [];
    const low = rows.filter((r) => Number(r.quantity) <= 0).length;
    view.innerHTML = `<div class="card"><div class="card__head"><h2>My Warehouse Stock</h2>
        <span class="muted">On-hand inventory you fulfil retailer orders from${low ? ` · <b style="color:var(--danger)">${low} out of stock</b>` : ""}</span></div>
      <div class="card__body">
        <p class="muted" style="margin-bottom:.9rem">Stock rises when the Company delivers your purchase orders, and falls when you deliver to your retailers.</p>
        <div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>SKU</th><th>On Hand</th><th>Updated</th></tr></thead>
        <tbody>${rows
          .map(
            (r) =>
              `<tr><td><b>${esc(r.name)}</b> <span class="muted">${esc(r.size || "")}</span></td><td class="muted">${esc(r.sku)}</td>
          <td><b style="color:${Number(r.quantity) > 0 ? "var(--green)" : "var(--danger)"}">${r.quantity}</b></td><td class="muted">${r.updated_at ? fmtDate(r.updated_at) : "—"}</td></tr>`,
          )
          .join("")}</tbody></table></div></div></div>`;
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

let retailerList = [];
let orderForStore = "";
let pAvailMap = {};
async function loadAvailability() {
  try {
    const av = await api(
      "availability.php" + (orderForStore ? "?store_id=" + orderForStore : ""),
    );
    pAvailMap = av.availability || {};
  } catch (e) {
    pAvailMap = {};
  }
}
async function orderstock() {
  try {
    const [prod, ret] = await Promise.all([
      api("products.php"),
      api("partner/retailers.php"),
    ]);
    productCache = prod.products || [];
    retailerList = ret.retailers || [];
    await loadAvailability();
    renderCatalog();
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function renderCatalog() {
  const items = productCache
    .map((p) => {
      const avail = Number(pAvailMap[p.id] ?? 0);
      const out = avail <= 0;
      if (out && cart[p.id]) delete cart[p.id];
      return `<div class="prod-row${out ? " prod-row--out" : ""}">
    <div class="prod-row__img">${p.image ? `<img src="../${esc(p.image)}">` : '<i class="fa-solid fa-bottle-water"></i>'}</div>
    <div class="prod-row__info"><b>${esc(p.name)}</b><div class="muted">${esc(p.size || "")} · ${money(p.price)}</div>
      ${out ? '<div class="stock-tag stock-tag--out"><i class="fa-solid fa-circle-xmark"></i> Out of stock</div>' : avail <= 20 ? `<div class="stock-tag stock-tag--low">Only ${avail} left</div>` : `<div class="stock-tag">${avail} available</div>`}</div>
    ${out ? '<span class="muted" style="font-size:.8rem">Unavailable</span>' : `<div class="qty-ctl"><button type="button" class="qty-btn" data-dec="${p.id}">−</button><input type="number" class="qty-in" id="q-${p.id}" data-qid="${p.id}" min="0" max="${avail}" step="1" inputmode="numeric" value="${cart[p.id] || 0}"><button type="button" class="qty-btn" data-inc="${p.id}">+</button></div>`}</div>`;
    })
    .join("");
  const picker = `<div class="order-for">
    <label><i class="fa-solid fa-user-tag"></i> Placing this order for</label>
    <select class="select" id="order-for">
      <option value="">My own stock — buy from Company</option>
      ${retailerList.map((r) => `<option value="${r.id}"${orderForStore === String(r.id) ? " selected" : ""}>${esc(r.name)}${r.district ? " — " + esc(r.district) : ""}</option>`).join("")}
    </select>
    <div class="muted" style="font-size:.78rem;margin-top:.4rem">${orderForStore ? "Fulfilled from your own warehouse stock." : "Bought from the company warehouse."}</div></div>`;
  view.innerHTML = `<div class="order-layout">
    <div>${picker}<div class="catalog">${items || emptyBox("fa-bottle-water", "No products")}</div></div>
    <div class="card cart-panel"><div class="card__head"><h2>Order</h2></div><div class="card__body" id="cart-body"></div></div></div>`;
  const ofor = document.getElementById("order-for");
  if (ofor)
    ofor.addEventListener("change", async () => {
      orderForStore = ofor.value;
      await loadAvailability();
      renderCatalog();
    });
  const capOf = (id) => Number(pAvailMap[id] ?? 0);
  view.querySelectorAll("[data-inc]").forEach((b) =>
    b.addEventListener("click", () => {
      const id = b.dataset.inc;
      const next = (cart[id] || 0) + 1;
      if (next > capOf(id)) {
        toast("Only " + capOf(id) + " available", "error");
        return;
      }
      cart[id] = next;
      syncCart();
    }),
  );
  view.querySelectorAll("[data-dec]").forEach((b) =>
    b.addEventListener("click", () => {
      const id = b.dataset.dec;
      if (cart[id]) {
        cart[id]--;
        if (!cart[id]) delete cart[id];
      }
      syncCart();
    }),
  );
  view.querySelectorAll(".qty-in").forEach((inp) =>
    inp.addEventListener("input", () => {
      const id = inp.dataset.qid;
      let n = Math.max(0, Math.floor(Number(inp.value) || 0));
      if (n > capOf(id)) {
        n = capOf(id);
        inp.value = n;
        toast("Only " + capOf(id) + " available", "error");
      }
      if (n) cart[id] = n;
      else delete cart[id];
      syncCartBody();
    }),
  );
  syncCart();
}
function syncCart() {
  productCache.forEach((p) => {
    const el = document.getElementById("q-" + p.id);
    if (el && document.activeElement !== el) el.value = cart[p.id] || 0;
  });
  syncCartBody();
}
function syncCartBody() {
  const body = document.getElementById("cart-body");
  if (!body) return;
  const lines = Object.keys(cart).map((id) => ({
    p: productCache.find((x) => x.id == id),
    q: cart[id],
  }));
  if (!lines.length) {
    body.innerHTML = emptyBox("fa-cart-shopping", "Cart is empty");
    return;
  }
  const total = lines.reduce((s, l) => s + l.p.price * l.q, 0);
  const forRetailer = !!orderForStore;
  const label = forRetailer ? "Place Order for Retailer" : "Order from Company";
  body.innerHTML = `${lines.map((l) => `<div style="display:flex;justify-content:space-between;font-size:.86rem;margin-bottom:.5rem"><span>${esc(l.p.name)} × ${l.q}</span><b>${money(l.p.price * l.q)}</b></div>`).join("")}
    <hr style="border:none;border-top:1px solid var(--line);margin:.6rem 0"><div style="display:flex;justify-content:space-between;margin-bottom:1rem"><b>Total</b><b style="color:var(--heading)">${money(total)}</b></div>
    <div class="muted" style="font-size:.78rem;margin-bottom:.7rem">${forRetailer ? "The retailer settles payment; the company approves fulfilment." : "The company reviews and approves your order."}</div>
    <button class="btn btn--green" style="width:100%;justify-content:center" id="submit-order"><i class="fa-solid fa-check"></i> ${label}</button>`;
  document
    .getElementById("submit-order")
    .addEventListener("click", async () => {
      const items = Object.keys(cart).map((id) => ({
        product_id: Number(id),
        qty: cart[id],
      }));
      if (!items.length) return;
      const payload = { items };
      if (orderForStore) payload.store_id = Number(orderForStore);
      const sb = document.getElementById("submit-order");
      sb.disabled = true;
      try {
        const r = await api("orders/create.php", {
          method: "POST",
          body: payload,
        });
        toast(r.message);
        cart = {};
        orderForStore = "";
        setView("orders");
      } catch (e) {
        toast(e.message, "error");
        sb.disabled = false;
      }
    });
}

async function reports() {
  try {
    const d = await api(`partner/stats.php?level=${reportLevel}&days=30`);
    view.innerHTML = `
      <div class="card"><div class="card__head"><h2>Sales by Area</h2>
        <select class="select" id="lvl">${["district", "taluka", "village", "pincode"].map((l) => `<option value="${l}" ${l === reportLevel ? "selected" : ""}>${l[0].toUpperCase() + l.slice(1)}</option>`).join("")}</select></div>
        <div class="card__body"><div class="chart-box" style="height:17rem"><canvas id="pr-bar"></canvas></div></div></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-top:1.2rem">
        <div class="card"><div class="card__head"><h2>Top Products</h2></div><div class="card__body"><div class="chart-box" style="height:15rem"><canvas id="pr-prod"></canvas></div></div></div>
        <div class="card"><div class="card__head"><h2>Payment Status</h2></div><div class="card__body">${payTable(d.payment_split)}</div></div>
      </div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Product Demand Heatmap <span class="muted">(district × product)</span></h2></div><div class="card__body" id="pr-heat"></div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Outstanding Dues</h2></div><div class="card__body">${duesTable(d.dues)}</div></div>`;
    document.getElementById("lvl").addEventListener("change", (e) => {
      reportLevel = e.target.value;
      reports();
    });
    drawBar(d.region);
    drawProd(d.top_products);
    heatmap(document.getElementById("pr-heat"), d.heat);
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function drawBar(rows) {
  rows = (rows || []).slice(0, 12);
  mkChart("pr-bar", {
    type: "bar",
    data: {
      labels: rows.map((r) => r.area),
      datasets: [
        {
          data: rows.map((r) => +r.value),
          backgroundColor: "#2e7d32",
          borderRadius: 4,
          maxBarThickness: 34,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (c) => money(c.raw) } },
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: GRID,
          ticks: { callback: (v) => "₹" + v },
        },
        x: { grid: { display: false } },
      },
    },
  });
}
function drawProd(rows) {
  rows = rows || [];
  mkChart("pr-prod", {
    type: "bar",
    data: {
      labels: rows.map((r) => r.name),
      datasets: [
        {
          data: rows.map((r) => +r.units),
          backgroundColor: "#7cb342",
          borderRadius: 4,
          maxBarThickness: 28,
        },
      ],
    },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { beginAtZero: true, grid: GRID },
        y: { grid: { display: false } },
      },
    },
  });
}
function payTable(rows) {
  if (!rows || !rows.length) return emptyBox("fa-wallet", "No data.");
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Status</th><th>Orders</th><th>Amount Due</th></tr></thead>
    <tbody>${rows.map((r) => `<tr><td><span class="pay pay--${r.payment_status}">${esc(r.payment_status)}</span></td><td>${r.n}</td><td>${money(r.due)}</td></tr>`).join("")}</tbody></table></div>`;
}
function duesTable(rows) {
  if (!rows || !rows.length)
    return emptyBox("fa-circle-check", "No outstanding dues 🎉");
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Order</th><th>Store</th><th>Area</th><th>Total</th><th>Due</th><th>Date</th></tr></thead>
    <tbody>${rows.map((r) => `<tr><td><b>#${r.id}</b></td><td>${esc(r.store_name)}</td><td class="muted">${esc(r.district || "—")}</td><td>${money(r.total)}</td><td><b style="color:var(--danger)">${money(r.due)}</b></td><td class="muted">${fmtDate(r.created_at)}</td></tr>`).join("")}</tbody></table></div>`;
}

async function renderMap() {
  if (_pMap) {
    _pMap.remove();
    _pMap = null;
  }
  view.innerHTML = `<div class="card"><div class="card__head"><h2>My Retailers on the Map</h2></div><div class="card__body"><div id="p-map" style="height:32rem;border-radius:.6rem;overflow:hidden;background:#e9efe2"></div></div></div>`;
  try {
    const stores = (await api("partner/store_locations.php")).stores || [];
    const el = document.getElementById("p-map");
    _pMap = L.map(el).setView([15.49, 73.83], 8);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap",
    }).addTo(_pMap);
    setTimeout(() => _pMap.invalidateSize(), 200);
    const pts = [];
    stores.forEach((s) => {
      const ll = [+s.lat, +s.lng];
      const icon = L.divIcon({
        className: "",
        iconSize: [24, 24],
        iconAnchor: [12, 24],
        html: `<div class="lpin" style="--pc:#2e7d32"></div>`,
      });
      L.marker(ll, { icon, title: s.name })
        .addTo(_pMap)
        .bindPopup(
          `<b>${esc(s.name)}</b><br><small>${esc([s.taluka, s.district, s.state].filter(Boolean).join(", "))}</small><br><small>${s.orders} orders</small>`,
        );
      pts.push(ll);
    });
    if (pts.length) _pMap.fitBounds(L.latLngBounds(pts).pad(0.2));
    else
      el.innerHTML = emptyBox(
        "fa-map-location-dot",
        "No retailer locations yet.",
      );
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

async function support() {
  try {
    const d = await api("tickets/list.php");
    const t = d.tickets || [];
    view.innerHTML = `<div class="card"><div class="card__head"><h2>Support</h2><button class="btn btn--green btn--sm" id="new-ticket"><i class="fa-solid fa-plus"></i> New Ticket</button></div>
      <div class="card__body">${
        t.length
          ? `<div class="table-wrap"><table class="data"><thead><tr><th>Subject</th><th>From</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody>${t
          .map(
            (
              x,
            ) => `<tr><td><b>${esc(x.subject)}</b><div class="muted">${esc(x.category)}</div></td><td>${esc(x.opener)}<div class="muted">${esc(x.store_name || "")}</div></td><td>${badge(x.status)}</td><td class="muted">${fmtDate(x.updated_at)}</td>
          <td><button class="btn btn--ghost btn--sm" data-ticket="${x.id}"><i class="fa-solid fa-comments"></i></button></td></tr>`,
          )
          .join("")}</tbody></table></div>`
          : emptyBox("fa-headset", "No tickets.")
      }</div></div>`;
    document.getElementById("new-ticket").addEventListener("click", newTicket);
    view
      .querySelectorAll("[data-ticket]")
      .forEach((b) =>
        b.addEventListener("click", () => openTicket(b.dataset.ticket)),
      );
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function newTicket() {
  openModal(`<div class="modal__head"><h3>New Ticket</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body"><div class="field"><label>Subject</label><input class="input" id="t-subject"></div>
      <div class="field"><label>Priority</label><select class="select" id="t-priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
      <div class="field"><label>Message</label><textarea id="t-message" rows="4"></textarea></div>
      <div class="field"><label>Attachment (optional)</label><input type="file" id="t-file" class="file-input" /></div></div>
    <div class="modal__foot"><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="t-submit">Create</button></div>`);
  document.getElementById("t-submit").addEventListener("click", async () => {
    const fd = new FormData();
    fd.append("subject", document.getElementById("t-subject").value.trim());
    fd.append("priority", document.getElementById("t-priority").value);
    fd.append("message", document.getElementById("t-message").value.trim());
    const file = document.getElementById("t-file").files[0];
    if (file) fd.append("attachment", file);
    try {
      await apiForm("tickets/create.php", fd);
      toast("Ticket created");
      closeModal();
      support();
    } catch (e) {
      toast(e.message, "error");
    }
  });
}
async function openTicket(id) {
  try {
    const d = await api(`tickets/thread.php?id=${id}`);
    const t = d.ticket;
    openModal(`<div class="modal__head"><h3>${esc(t.subject)}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
      <div class="modal__body"><div style="margin-bottom:.8rem"><span id="t-status">${badge(t.status)}</span> <span class="muted">${esc(t.opener)}</span></div>
        <div id="thread" style="display:flex;flex-direction:column;gap:.7rem;max-height:40vh;overflow:auto"></div>
        <div id="reply-area"></div></div>`);
    paintThread(d);
    startThreadPolling(async () => {
      try {
        paintThread(await api(`tickets/thread.php?id=${id}`));
      } catch (e) {}
    });
  } catch (e) {
    toast(e.message, "error");
  }
}
function paintThread(data) {
  const t = data.ticket;
  const msgs = data.messages || [];
  const threadEl = document.getElementById("thread");
  if (!threadEl) return;
  if (threadEl.childElementCount !== msgs.length) {
    threadEl.innerHTML = msgs.map(msgBubble).join("");
    threadEl.scrollTop = threadEl.scrollHeight;
  }
  const st = document.getElementById("t-status");
  if (st) st.innerHTML = badge(t.status);
  const area = document.getElementById("reply-area");
  if (!area) return;
  const closed = t.status === "closed";
  const hasReply = !!document.getElementById("reply-send");
  if (closed && !area.dataset.closed) {
    area.dataset.closed = "1";
    area.innerHTML = '<p class="muted" style="margin-top:1rem">Closed.</p>';
  } else if (!closed && !hasReply) {
    area.dataset.closed = "";
    area.innerHTML = `<div style="margin-top:1rem"><div style="display:flex;gap:.5rem"><input class="input" id="reply-msg" placeholder="Reply…" style="flex:1"><button class="btn btn--green" id="reply-send">Send</button></div><input type="file" id="reply-file" class="file-input" /></div>`;
    wireReply(t.id);
  }
}
function wireReply(id) {
  const s = document.getElementById("reply-send");
  if (!s) return;
  s.addEventListener("click", async () => {
    const mEl = document.getElementById("reply-msg");
    const fileEl = document.getElementById("reply-file");
    const m = mEl.value.trim();
    const file = fileEl.files[0];
    if (!m && !file) return;
    const fd = new FormData();
    fd.append("ticket_id", id);
    fd.append("message", m);
    if (file) fd.append("attachment", file);
    s.disabled = true;
    try {
      await apiForm("tickets/reply.php", fd);
      mEl.value = "";
      fileEl.value = "";
      paintThread(await api(`tickets/thread.php?id=${id}`));
    } catch (e) {
      toast(e.message, "error");
    }
    s.disabled = false;
  });
}
function msgBubble(m) {
  const mine = m.role === "partner";
  return `<div style="align-self:${mine ? "flex-end" : "flex-start"};max-width:80%;background:${mine ? "#143a24" : "var(--soft)"};color:${mine ? "#fff" : "var(--ink)"};padding:.6rem .9rem;border-radius:.7rem">
    <div style="font-size:.72rem;opacity:.75;margin-bottom:.2rem">${esc(m.name)} (${esc(m.role)}) · ${fmtDate(m.created_at)}</div>${esc(m.message)}${m.attachment ? "<br>" + attachLink(m.attachment) : ""}</div>`;
}

(async function () {
  const u = await bootstrap("partner");
  if (!u) return;
  initShell();
  setView("dashboard");
})();

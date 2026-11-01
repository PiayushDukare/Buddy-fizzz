const view = document.getElementById("view");
const pageTitle = document.getElementById("page-title");
const modalBack = document.getElementById("modal-back");
const modal = document.getElementById("modal");

let appState = { status: "pending", q: "" };
let partnersCache = null;
let orderStatus = "all";
let ticketStatus = "all";
let storeRole = "retailer";
let analyticsLevel = "district";
const STATUS_COLORS = {
  pending: "#f2b705",
  approved: "#7cb342",
  processing: "#3d8bfd",
  dispatched: "#17a2b8",
  delivered: "#2e7d32",
  cancelled: "#e5533c",
  rejected: "#9aa7a0",
};
const _charts = {};

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
const stat = (l, v, i) =>
  `<div class="stat"><div class="stat__icon"><i class="fa-solid ${i}"></i></div><div class="stat__label">${esc(l)}</div><div class="stat__value">${esc(v)}</div></div>`;

const views = {
  dashboard,
  applications: renderApplications,
  retailers: () => storeList("retailer"),
  partners: () => storeList("partner"),
  terminations: renderTerminations,
  products,
  orders,
  stock: stockView,
  tickets,
  analytics,
  insights: renderInsights,
  payouts: renderPayouts,
  revenue: renderRevenue,
  map: renderMap,
  marketing,
  settings,
  logs: systemLogs,
};

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
  const live = {
    dashboard,
    applications: loadApplications,
    orders: loadOrders,
    tickets: loadTickets,
    logs: refreshLogs,
  }[name];
  if (live) startPolling(live);
}
document.querySelectorAll("#nav a").forEach((a) =>
  a.addEventListener("click", (e) => {
    e.preventDefault();
    setView(a.dataset.view);
    document.querySelector(".sidebar").classList.remove("open");
  }),
);
function ph(t, d) {
  view.innerHTML = `<div class="card"><div class="card__body empty"><i class="fa-solid fa-screwdriver-wrench"></i><h2 style="color:var(--heading);font-family:'Baloo 2',cursive">${esc(t)}</h2><p>${esc(d)}</p></div></div>`;
}
const MOV_REASON = {
  order: "Order",
  production: "Production intake",
  manual: "Manual",
  adjustment: "Adjustment",
  return: "Return",
};
async function stockView() {
  try {
    const d = await api("admin/stock.php");
    const prods = d.products || [];
    const stores = d.stores || [];
    const movs = d.movements || [];
    view.innerHTML = `
      <div class="card"><div class="card__head"><h2>Company Warehouse <span class="muted">(factory stock)</span></h2></div>
        <div class="card__body"><p class="muted" style="margin:0 0 .9rem">Stock the company holds. It drops when partners or direct retailers receive company-supplied orders. Use <b>Add / Set</b> to record production.</p>
        <div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>SKU</th><th>On Hand</th><th style="width:16rem">Adjust</th></tr></thead>
        <tbody>${prods
          .map(
            (
              p,
            ) => `<tr><td><b>${esc(p.name)}</b> <span class="muted">${esc(p.size || "")}</span></td><td class="muted">${esc(p.sku)}</td>
          <td><b style="color:${p.company_qty > 0 ? "var(--green)" : "var(--danger)"}">${Number(p.company_qty).toLocaleString("en-IN")}</b></td>
          <td><div style="display:flex;gap:.35rem;align-items:center"><input class="input" type="number" min="0" style="width:6rem" data-qi="${p.id}" placeholder="Qty">
            <button class="btn btn--lime btn--sm" data-add="${p.id}">Add</button>
            <button class="btn btn--ghost btn--sm" data-set="${p.id}">Set</button></div></td></tr>`,
          )
          .join("")}</tbody></table></div></div></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-top:1.2rem">
        <div class="card"><div class="card__head"><h2>Stock by Store</h2></div><div class="card__body">
          ${
            stores.length
              ? `<div class="table-wrap"><table class="data"><thead><tr><th>Store</th><th>Role</th><th>Units on Hand</th></tr></thead>
            <tbody>${stores.map((s) => `<tr><td><b>${esc(s.name)}</b></td><td>${badge(s.role)}</td><td><b>${Number(s.units).toLocaleString("en-IN")}</b></td></tr>`).join("")}</tbody></table></div>`
              : emptyBox("fa-store", "No stores yet.")
          }</div></div>
        <div class="card"><div class="card__head"><h2>Recent Stock Movements</h2></div><div class="card__body">
          ${
            movs.length
              ? `<div class="table-wrap"><table class="data"><thead><tr><th>When</th><th>Where</th><th>Product</th><th>Change</th><th>Reason</th></tr></thead>
            <tbody>${movs
              .map(
                (m) =>
                  `<tr><td class="muted">${fmtDate(m.created_at)}</td><td>${esc(m.place)}</td><td>${esc(m.product)}</td>
                <td><b style="color:${m.change_qty >= 0 ? "var(--green)" : "var(--danger)"}">${m.change_qty >= 0 ? "+" : ""}${m.change_qty}</b></td>
                <td class="muted">${esc(MOV_REASON[m.reason] || m.reason)}${m.order_id ? " #" + m.order_id : ""}</td></tr>`,
              )
              .join("")}</tbody></table></div>`
              : emptyBox("fa-arrow-right-arrow-left", "No movements yet.")
          }</div></div>
      </div>`;
    const adjust = async (id, mode) => {
      const input = view.querySelector(`[data-qi="${id}"]`);
      const q = Number(input.value);
      if (!q && mode === "add") {
        toast("Enter a quantity", "error");
        return;
      }
      try {
        const r = await api("admin/stock_adjust.php", {
          method: "POST",
          body: { product_id: id, mode, quantity: q || 0 },
        });
        toast(r.message);
        stockView();
      } catch (e) {
        toast(e.message, "error");
      }
    };
    view
      .querySelectorAll("[data-add]")
      .forEach((b) =>
        b.addEventListener("click", () => adjust(Number(b.dataset.add), "add")),
      );
    view
      .querySelectorAll("[data-set]")
      .forEach((b) =>
        b.addEventListener("click", () => adjust(Number(b.dataset.set), "set")),
      );
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

async function dashboard() {
  try {
    const d = await api("admin/stats.php?level=district");
    const k = d.kpi || {};
    updateAppBadge(k.pending_apps || 0);
    updateTermBadge(k.pending_terminations || 0);
    view.innerHTML = `
      <div class="stat-row">
        ${stat("Total Revenue", money(k.revenue), "fa-indian-rupee-sign")}
        ${stat("Tax Collected", money(k.tax_collected), "fa-file-invoice-dollar")}
        ${stat("Collected", money(k.collected), "fa-circle-check")}
        ${stat("Outstanding", money(k.outstanding), "fa-hourglass-half")}
        ${stat("Total Orders", k.total_orders || 0, "fa-box")}
        ${stat("Pending Orders", k.pending_orders || 0, "fa-clock")}
        ${stat("Retailers", k.retailers || 0, "fa-store")}
        ${stat("Partners", k.partners || 0, "fa-handshake")}
        ${stat("Pending Apps", k.pending_apps || 0, "fa-inbox")}
        ${stat("Open Tickets", k.open_tickets || 0, "fa-headset")}
      </div>
      <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:1.2rem">
        <div class="card"><div class="card__head"><h2>Top Areas (by District)</h2></div><div class="card__body">${regionTable(d.region)}</div></div>
        <div class="card"><div class="card__head"><h2>Top Products</h2></div><div class="card__body">${topProductsTable(d.top_products)}</div></div>
      </div>`;
    view.insertAdjacentHTML("afterbegin", await announcementBanner());
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

function regionTable(rows) {
  if (!rows || !rows.length) return emptyBox("fa-map", "No sales data yet.");
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Area</th><th>Orders</th><th>Value</th></tr></thead>
    <tbody>${rows.map((r) => `<tr><td><b>${esc(r.area)}</b></td><td>${r.orders}</td><td>${money(r.value)}</td></tr>`).join("")}</tbody></table></div>`;
}
function topProductsTable(rows) {
  if (!rows || !rows.length)
    return emptyBox("fa-bottle-water", "No product sales yet.");
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>Units</th><th>Value</th></tr></thead>
    <tbody>${rows.map((r) => `<tr><td>${esc(r.name)}</td><td>${r.units}</td><td>${money(r.value)}</td></tr>`).join("")}</tbody></table></div>`;
}

async function renderApplications() {
  view.innerHTML = `<div class="card"><div class="card__head">
    <div class="tabs" id="app-tabs">${["pending", "approved", "rejected", "all"].map((s) => `<button data-s="${s}" class="${s === appState.status ? "active" : ""}">${s[0].toUpperCase() + s.slice(1)}</button>`).join("")}</div>
    <input class="input" id="app-search" placeholder="Search name, email, area…"></div>
    <div class="card__body" id="app-list"><div class="spinner"></div></div></div>`;
  document.querySelectorAll("#app-tabs button").forEach((b) =>
    b.addEventListener("click", () => {
      document
        .querySelectorAll("#app-tabs button")
        .forEach((x) => x.classList.remove("active"));
      b.classList.add("active");
      appState.status = b.dataset.s;
      loadApplications();
    }),
  );
  let t;
  document.getElementById("app-search").addEventListener("input", (e) => {
    clearTimeout(t);
    t = setTimeout(() => {
      appState.q = e.target.value.trim();
      loadApplications();
    }, 300);
  });
  loadApplications();
}
async function loadApplications() {
  const box = document.getElementById("app-list");
  box.innerHTML = '<div class="spinner"></div>';
  try {
    const data = await api(
      `admin/applications.php?status=${appState.status}&q=${encodeURIComponent(appState.q)}`,
    );
    updateAppBadge(data.counts?.pending || 0);
    const apps = data.applications || [];
    if (!apps.length) {
      box.innerHTML = emptyBox(
        "fa-inbox",
        `No ${appState.status} applications.`,
      );
      return;
    }
    box.innerHTML = `<div class="table-wrap"><table class="data"><thead><tr><th>Business</th><th>Type</th><th>Contact</th><th>Location</th><th>Applied</th><th>Status</th><th></th></tr></thead>
      <tbody>${apps.map(rowApp).join("")}</tbody></table></div>`;
    box
      .querySelectorAll("[data-view-app]")
      .forEach((b) =>
        b.addEventListener("click", () =>
          openAppModal(apps.find((a) => a.id == b.dataset.viewApp)),
        ),
      );
  } catch (e) {
    box.innerHTML = errBox(e.message);
  }
}
function rowApp(a) {
  const loc =
    [a.village, a.taluka, a.district, a.state].filter(Boolean).join(", ") ||
    "—";
  return `<tr><td><b>${esc(a.business_name)}</b><div class="muted">${esc(a.store_type)}</div></td><td><span class="badge badge--${a.type}">${esc(a.type)}</span></td>
    <td>${esc(a.contact_name)}<div class="muted">${esc(a.email)}</div></td><td style="max-width:16rem">${esc(loc)}<div class="muted">${esc(a.pincode || "")}</div></td>
    <td class="muted">${fmtDate(a.created_at)}</td><td>${badge(a.status)}</td>
    <td><button class="btn btn--ghost btn--sm" data-view-app="${a.id}"><i class="fa-solid fa-eye"></i> View</button></td></tr>`;
}
async function openAppModal(a) {
  const loc =
    [a.address, a.village, a.taluka, a.district, a.state, a.pincode]
      .filter(Boolean)
      .join(", ") || "—";
  let controls = "";
  if (a.status === "pending") {
    let assignField = "";
    if (a.type === "retailer") {
      if (!partnersCache) {
        try {
          partnersCache = (await api("admin/partners.php")).partners || [];
        } catch (e) {
          partnersCache = [];
        }
      }
      const sug = suggestPartner(a, partnersCache);
      const hint = sug
        ? `<div class="terr-hint"><i class="fa-solid fa-wand-magic-sparkles"></i> Suggested: <b>${esc(sug.name)}</b> covers ${esc(sug.reason)}</div>`
        : "";
      assignField = `<div class="field"><label>Assign this retailer to</label><select class="select" id="assign-partner"><option value="company">Company (Direct — no partner)</option>
        ${partnersCache.map((p) => `<option value="${p.id}"${sug && sug.id === p.id ? " selected" : ""}>${esc(p.name)}${p.district ? " — " + esc(p.district) : ""}</option>`).join("")}</select>${hint}</div>`;
    }
    controls = `${assignField}<div class="field"><label>Rejection note (optional)</label><textarea id="reject-note" rows="2" placeholder="Reason, if rejecting…"></textarea></div>`;
  }
  const footer =
    a.status === "pending"
      ? `<button class="btn btn--danger" id="btn-reject"><i class="fa-solid fa-xmark"></i> Reject</button><button class="btn btn--green" id="btn-approve"><i class="fa-solid fa-check"></i> Approve &amp; Create Account</button>`
      : `<button class="btn btn--ghost" onclick="closeModal()">Close</button>`;
  openModal(`<div class="modal__head"><h3>${esc(a.business_name)}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body"><div style="margin-bottom:1rem"><span class="badge badge--${a.type}">${esc(a.type)}</span> ${badge(a.status)}</div>
      <dl class="kv"><dt>Contact</dt><dd>${esc(a.contact_name)}</dd><dt>Email</dt><dd>${esc(a.email)}</dd><dt>Phone</dt><dd>${esc(a.phone)}</dd>
      <dt>Store type</dt><dd>${esc(a.store_type)}</dd><dt>Location</dt><dd>${esc(loc)}</dd><dt>Applied</dt><dd>${fmtDate(a.created_at)}</dd>
      ${a.message ? `<dt>Message</dt><dd>${esc(a.message)}</dd>` : ""}${a.review_notes ? `<dt>Review note</dt><dd>${esc(a.review_notes)}</dd>` : ""}</dl>
      <div style="margin-top:1.1rem">${controls}</div></div><div class="modal__foot">${footer}</div>`);
  if (a.status === "pending") {
    document
      .getElementById("btn-approve")
      .addEventListener("click", () => actOnApp(a, "approve"));
    document
      .getElementById("btn-reject")
      .addEventListener("click", () => actOnApp(a, "reject"));
  }
}
function suggestPartner(app, partners) {
  const norm = (s) =>
    String(s || "")
      .trim()
      .toLowerCase();
  const want = {
    taluka: norm(app.taluka),
    district: norm(app.district),
    state: norm(app.state),
  };
  for (const level of ["taluka", "district", "state"]) {
    if (!want[level]) continue;
    for (const p of partners) {
      for (const t of p.territories || []) {
        if (t.level === level && norm(t.value) === want[level])
          return { id: p.id, name: p.name, reason: `${app[level]} (${level})` };
      }
    }
  }
  return null;
}
async function actOnApp(a, action) {
  const payload = { id: a.id, action };
  if (action === "approve" && a.type === "retailer")
    payload.partner_id = document.getElementById("assign-partner").value;
  if (action === "reject")
    payload.notes = document.getElementById("reject-note").value.trim();
  const btn = document.getElementById(
    action === "approve" ? "btn-approve" : "btn-reject",
  );
  btn.disabled = true;
  btn.textContent = "Working…";
  try {
    const res = await api("admin/application_action.php", {
      method: "POST",
      body: payload,
    });
    toast(
      res.message +
        (res.assigned && res.assigned !== "n/a" ? " → " + res.assigned : ""),
    );
    closeModal();
    loadApplications();
    partnersCache = null;
  } catch (e) {
    toast(e.message, "error");
    btn.disabled = false;
    btn.textContent =
      action === "approve" ? "Approve & Create Account" : "Reject";
  }
}

async function storeList(role) {
  storeRole = role;
  try {
    const data = await api(`admin/stores.php?role=${role}`);
    const rows = data.stores || [];
    view.innerHTML = `<div class="card"><div class="card__head"><h2>${role === "partner" ? "Partners / Distributors" : "Retailers"}</h2>
      <input class="input" id="store-q" placeholder="Search…"></div>
      <div class="card__body">${rows.length ? storeTable(rows) : emptyBox("fa-store", "None yet.")}</div></div>`;
    let t;
    document.getElementById("store-q").addEventListener("input", async (e) => {
      clearTimeout(t);
      t = setTimeout(async () => {
        const d = await api(
          `admin/stores.php?role=${role}&q=${encodeURIComponent(e.target.value.trim())}`,
        );
        view.querySelector(".card__body").innerHTML = (d.stores || []).length
          ? storeTable(d.stores)
          : emptyBox("fa-store", "No match.");
        wireStoreEdit();
      }, 300);
    });
    wireStoreEdit();
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function wireStoreEdit() {
  view
    .querySelectorAll("[data-store]")
    .forEach((b) =>
      b.addEventListener("click", () =>
        storeForm(JSON.parse(decodeURIComponent(b.dataset.store))),
      ),
    );
  view
    .querySelectorAll("[data-terr]")
    .forEach((b) =>
      b.addEventListener("click", () =>
        territoryModal(Number(b.dataset.terr), b.dataset.terrName || ""),
      ),
    );
  view.querySelectorAll("[data-term]").forEach((b) =>
    b.addEventListener("click", async () => {
      const reason = await uiPrompt(
        "Terminating blocks this account from logging in. Enter a reason (shared with them).",
        {
          title: "Terminate " + (b.dataset.termName || "account"),
          placeholder: "e.g. Repeated payment defaults",
          confirmText: "Terminate account",
        },
      );
      if (reason === null) return;
      try {
        const r = await api("admin/user_status.php", {
          method: "POST",
          body: { user_id: b.dataset.term, action: "terminate", reason },
        });
        toast(r.message);
        storeList(storeRole);
      } catch (e) {
        toast(e.message, "error");
      }
    }),
  );
  view.querySelectorAll("[data-react]").forEach((b) =>
    b.addEventListener("click", async () => {
      if (
        !(await uiConfirm(
          "Reactivate " +
            (b.dataset.reactName || "this account") +
            "? They will be able to log in again.",
          { title: "Reactivate account", confirmText: "Reactivate" },
        ))
      )
        return;
      try {
        const r = await api("admin/user_status.php", {
          method: "POST",
          body: { user_id: b.dataset.react, action: "activate" },
        });
        toast(r.message);
        storeList(storeRole);
      } catch (e) {
        toast(e.message, "error");
      }
    }),
  );
}
function storeTable(rows) {
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Store</th><th>Owner</th><th>Location</th><th>GSTIN</th>${storeRole === "retailer" ? "<th>Partner</th>" : ""}<th>Status</th><th></th></tr></thead>
    <tbody>${rows
      .map(
        (
          s,
        ) => `<tr><td><b>${esc(s.name)}</b><div class="muted">${esc(s.email || "")}</div></td><td>${esc(s.owner_name || "—")}<div class="muted">${esc(s.phone || "")}</div></td>
      <td>${esc([s.taluka, s.district, s.state].filter(Boolean).join(", ") || "—")}<div class="muted">${esc(s.pincode || "")}</div></td>
      <td>${s.gstin ? `<span class="mono">${esc(s.gstin)}</span>` : '<span class="muted">—</span>'}</td>
      ${storeRole === "retailer" ? `<td>${s.partner_name ? esc(s.partner_name) : '<span class="muted">Company</span>'}</td>` : ""}<td>${badge(s.user_status || s.status)}</td>
      <td style="white-space:nowrap"><button class="btn btn--ghost btn--sm" data-store='${encodeURIComponent(JSON.stringify(s))}' title="Edit details"><i class="fa-solid fa-pen"></i></button>${storeRole === "partner" && s.user_id ? ` <button class="btn btn--ghost btn--sm" data-terr="${s.user_id}" data-terr-name="${esc(s.name)}" title="Manage territory"><i class="fa-solid fa-map-location-dot"></i></button>` : ""}${
        s.user_id
          ? (s.user_status || s.status) === "active"
            ? ` <button class="btn btn--danger btn--sm" data-term="${s.user_id}" data-term-name="${esc(s.name)}" title="Terminate account"><i class="fa-solid fa-user-slash"></i></button>`
            : ` <button class="btn btn--green btn--sm" data-react="${s.user_id}" data-react-name="${esc(s.name)}" title="Reactivate account"><i class="fa-solid fa-user-check"></i></button>`
          : ""
      }</td></tr>`,
      )
      .join("")}</tbody></table></div>`;
}
async function storeForm(s) {
  let assignField = "";
  if (s.role === "retailer" || storeRole === "retailer") {
    if (!partnersCache) {
      try {
        partnersCache = (await api("admin/partners.php")).partners || [];
      } catch (e) {
        partnersCache = [];
      }
    }
    const cur = s.partner_id ? String(s.partner_id) : "company";
    assignField = `<div class="field"><label>Assigned Partner</label><select class="select" id="st-partner">
      <option value="company"${cur === "company" ? " selected" : ""}>Company (Direct — no partner)</option>
      ${partnersCache.map((p) => `<option value="${p.id}"${cur === String(p.id) ? " selected" : ""}>${esc(p.name)}${p.district ? " — " + esc(p.district) : ""}</option>`).join("")}
    </select></div>`;
  }
  const isRetailer = s.role === "retailer" || storeRole === "retailer";
  const creditBlock = isRetailer
    ? `<hr style="border:none;border-top:1px solid var(--line);margin:1.1rem 0">
      <h4 style="color:var(--heading);margin-bottom:.6rem;font-family:'Baloo 2',cursive">Wallet &amp; Credit</h4>
      <div style="display:flex;justify-content:space-between;align-items:center;background:var(--soft);border-radius:.5rem;padding:.6rem .8rem;margin-bottom:.7rem"><span>Wallet balance</span><b style="color:var(--heading)">${money(s.wallet_balance || 0)}</b></div>
      <div style="display:flex;gap:.4rem;margin-bottom:.9rem"><input class="input" id="st-wamt" type="number" step="0.01" placeholder="Amount (− to deduct)" style="flex:1"><input class="input" id="st-wnote" placeholder="Note" style="flex:1"><button class="btn btn--lime btn--sm" id="st-wallet">Update Wallet</button></div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.88rem;margin-bottom:.6rem"><input type="checkbox" id="st-credit" ${Number(s.credit_allowed) ? "checked" : ""}> Allow <b>Pay Later</b> (credit) for this store</label>
      <div class="field"><label>Credit Limit (₹) <span class="muted" style="font-weight:400">(0 = unlimited)</span></label><input class="input" id="st-climit" type="number" step="0.01" value="${esc(s.credit_limit || 0)}"></div>`
    : "";
  openModal(`<div class="modal__head"><h3>Business Details — ${esc(s.name)}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label>Owner Name</label><input class="input" id="st-owner" value="${esc(s.owner_name || "")}"></div>
      <div class="field"><label>Phone</label><input class="input" id="st-phone" value="${esc(s.phone || "")}"></div>
      <div class="field"><label>GSTIN <span class="muted" style="font-weight:400">(optional — shown on invoices)</span></label><input class="input" id="st-gstin" maxlength="15" style="text-transform:uppercase" placeholder="15-character GSTIN" value="${esc(s.gstin || "")}"></div>
      <div class="field"><label>Billing Address</label><textarea class="input" id="st-addr" rows="2">${esc(s.address || "")}</textarea></div>
      ${assignField}
      ${creditBlock}
    </div>
    <div class="modal__foot"><a class="btn btn--ghost" href="../api/statement.php?store_id=${s.id}" target="_blank"><i class="fa-solid fa-file-lines"></i> Statement</a><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="st-save">Save</button></div>`);
  const walletBtn = document.getElementById("st-wallet");
  if (walletBtn)
    walletBtn.addEventListener("click", async () => {
      try {
        const r = await api("admin/wallet_adjust.php", {
          method: "POST",
          body: { store_id: s.id, amount: v("st-wamt"), note: v("st-wnote") },
        });
        toast(r.message);
        closeModal();
        storeList(storeRole);
      } catch (e) {
        toast(e.message, "error");
      }
    });
  document.getElementById("st-save").addEventListener("click", async () => {
    const body = {
      id: s.id,
      owner_name: v("st-owner"),
      phone: v("st-phone"),
      gstin: v("st-gstin"),
      address: v("st-addr"),
    };
    const psel = document.getElementById("st-partner");
    if (psel) body.partner_id = psel.value;
    if (isRetailer) {
      body.credit_allowed = document.getElementById("st-credit").checked
        ? 1
        : 0;
      body.credit_limit = v("st-climit");
    }
    try {
      const r = await api("admin/store_save.php", { method: "POST", body });
      toast(r.message);
      closeModal();
      storeList(storeRole);
    } catch (e) {
      toast(e.message, "error");
    }
  });
}

const TERR_ICON = {
  state: "fa-map",
  district: "fa-location-dot",
  taluka: "fa-map-pin",
};
async function territoryModal(partnerId, partnerName) {
  openModal(
    `<div class="modal__head"><h3>Territory — ${esc(partnerName)}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div><div class="modal__body"><div class="spinner"></div></div>`,
  );
  let geo = { state: [], district: [], taluka: [] };
  const body = () => document.querySelector("#modal .modal__body");
  async function refresh() {
    const d = await api(`admin/territories.php?partner_id=${partnerId}`);
    geo = d.geo || geo;
    const list = d.territories || [];
    body().innerHTML = `
      <p class="muted" style="margin-bottom:.8rem">Areas this partner covers. New retailers in these areas are auto-suggested to this partner on approval.</p>
      <div id="terr-list" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.1rem">${
        list.length
          ? list
              .map(
                (t) =>
                  `<span class="terr-chip"><i class="fa-solid ${TERR_ICON[t.level] || "fa-location-dot"}"></i> ${esc(t.value)} <em>${esc(t.level)}</em> <button data-del="${t.id}" title="Remove">&times;</button></span>`,
              )
              .join("")
          : '<span class="muted">No areas assigned yet.</span>'
      }</div>
      <div style="display:grid;grid-template-columns:9rem 1fr auto;gap:.6rem;align-items:end">
        <div class="field" style="margin:0"><label>Level</label><select class="select" id="terr-level"><option value="state">State</option><option value="district" selected>District</option><option value="taluka">Taluka</option></select></div>
        <div class="field" style="margin:0"><label>Area name</label><input class="input" id="terr-value" list="terr-geo" placeholder="e.g. North Goa" autocomplete="off"><datalist id="terr-geo"></datalist></div>
        <button class="btn btn--green" id="terr-add"><i class="fa-solid fa-plus"></i> Add</button>
      </div>`;
    syncDatalist();
    document
      .getElementById("terr-level")
      .addEventListener("change", syncDatalist);
    document.getElementById("terr-add").addEventListener("click", add);
    document.getElementById("terr-value").addEventListener("keydown", (e) => {
      if (e.key === "Enter") add();
    });
    body()
      .querySelectorAll("[data-del]")
      .forEach((b) =>
        b.addEventListener("click", () => del(Number(b.dataset.del))),
      );
  }
  function syncDatalist() {
    const lvl = document.getElementById("terr-level").value;
    document.getElementById("terr-geo").innerHTML = (geo[lvl] || [])
      .map((x) => `<option value="${esc(x)}">`)
      .join("");
  }
  async function add() {
    const value = document.getElementById("terr-value").value.trim();
    if (!value) return;
    try {
      const r = await api("admin/territory_save.php", {
        method: "POST",
        body: {
          partner_id: partnerId,
          level: document.getElementById("terr-level").value,
          value,
        },
      });
      toast(r.message);
      partnersCache = null;
      await refresh();
    } catch (e) {
      toast(e.message, "error");
    }
  }
  async function del(id) {
    try {
      const r = await api("admin/territory_delete.php", {
        method: "POST",
        body: { id },
      });
      toast(r.message);
      partnersCache = null;
      await refresh();
    } catch (e) {
      toast(e.message, "error");
    }
  }
  try {
    await refresh();
  } catch (e) {
    body().innerHTML = errBox(e.message);
  }
}

async function products() {
  try {
    const data = await api("products.php");
    const rows = data.products || [];
    view.innerHTML = `<div class="card"><div class="card__head"><h2>Products &amp; Pricing</h2>
      <button class="btn btn--green btn--sm" id="add-product"><i class="fa-solid fa-plus"></i> Add Product</button></div>
      <div class="card__body"><div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>SKU</th><th>Size</th><th>Price</th><th>MRP</th><th>Status</th><th></th></tr></thead>
      <tbody>${rows
        .map(
          (
            p,
          ) => `<tr><td><b>${esc(p.name)}</b><div class="muted">${esc(p.flavour || "")}</div></td><td class="muted">${esc(p.sku)}</td><td>${esc(p.size || "—")}</td>
        <td><b>${money(p.price)}</b></td><td class="muted">${p.mrp ? money(p.mrp) : "—"}</td><td>${badge(Number(p.is_active) ? "active" : "suspended")}</td>
        <td style="white-space:nowrap"><button class="btn btn--ghost btn--sm" data-edit='${encodeURIComponent(JSON.stringify(p))}'><i class="fa-solid fa-pen"></i></button>
          <button class="btn btn--ghost btn--sm" data-qr="${esc(p.sku)}" data-qr-name="${esc(p.name)}" title="QR code"><i class="fa-solid fa-qrcode"></i></button></td></tr>`,
        )
        .join("")}</tbody></table></div></div></div>`;
    document
      .getElementById("add-product")
      .addEventListener("click", () => productForm({}));
    view
      .querySelectorAll("[data-edit]")
      .forEach((b) =>
        b.addEventListener("click", () =>
          productForm(JSON.parse(decodeURIComponent(b.dataset.edit))),
        ),
      );
    view
      .querySelectorAll("[data-qr]")
      .forEach((b) =>
        b.addEventListener("click", () =>
          qrModal(b.dataset.qr, b.dataset.qrName),
        ),
      );
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function qrModal(sku, name) {
  openModal(`<div class="modal__head"><h3>QR Code — ${esc(name)}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body" style="text-align:center">
      <p class="muted" style="margin-top:0">Print this on shelf labels. Scanning shows the product code <b>${esc(sku)}</b> for quick reorder.</p>
      <div id="qr-box" style="display:inline-block;padding:1rem;background:#fff;border:1px solid var(--line);border-radius:.6rem;margin:.5rem 0"></div>
      <div style="font-weight:700;color:var(--heading);letter-spacing:.05em">${esc(sku)}</div>
    </div>
    <div class="modal__foot"><button class="btn btn--ghost" onclick="closeModal()">Close</button><button class="btn btn--green" id="qr-dl"><i class="fa-solid fa-download"></i> Download PNG</button></div>`);
  const box = document.getElementById("qr-box");
  if (typeof QRCode === "undefined") {
    box.innerHTML =
      '<span class="muted">QR library not loaded — hard-refresh and retry.</span>';
    return;
  }
  new QRCode(box, {
    text: sku,
    width: 200,
    height: 200,
    colorDark: "#143a24",
    colorLight: "#ffffff",
  });
  document.getElementById("qr-dl").addEventListener("click", () => {
    const img = box.querySelector("img") || box.querySelector("canvas");
    if (!img) return;
    const src = img.tagName === "IMG" ? img.src : img.toDataURL("image/png");
    const a = document.createElement("a");
    a.href = src;
    a.download = "qr-" + sku + ".png";
    a.click();
  });
}
function productForm(p) {
  openModal(`<div class="modal__head"><h3>${p.id ? "Edit" : "Add"} Product</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label>Name</label><input class="input" id="p-name" value="${esc(p.name || "")}"></div>
      <div class="field"><label>Flavour</label><input class="input" id="p-flavour" value="${esc(p.flavour || "")}"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
        <div class="field"><label>SKU</label><input class="input" id="p-sku" value="${esc(p.sku || "")}"></div>
        <div class="field"><label>Size</label><input class="input" id="p-size" value="${esc(p.size || "")}"></div>
        <div class="field"><label>Cost (₹) <span class="muted" style="font-weight:400">(for margins)</span></label><input class="input" id="p-cost" type="number" step="0.01" value="${esc(p.cost || 0)}"></div>
        <div class="field"><label>Retailer Price (₹)</label><input class="input" id="p-price" type="number" step="0.01" value="${esc(p.price || 0)}"></div>
        <div class="field"><label>Distributor Profit / bottle (₹)</label><input class="input" id="p-profit" type="number" step="0.01" value="${esc(p.partner_profit || 0)}"></div>
        <div class="field"><label>MRP (₹)</label><input class="input" id="p-mrp" type="number" step="0.01" value="${esc(p.mrp || "")}"></div>
      </div>
      <div class="field"><label>Product Image</label>
        <div style="display:flex;align-items:center;gap:.8rem">
          ${p.image ? `<img src="../${esc(p.image)}" id="p-preview" style="width:2.6rem;height:3.2rem;object-fit:contain;background:var(--soft);border-radius:.4rem">` : '<span id="p-preview" style="width:2.6rem;height:3.2rem;background:var(--soft);border-radius:.4rem;display:grid;place-items:center;color:#9cb"><i class="fa-solid fa-bottle-water"></i></span>'}
          <input type="file" id="p-file" class="file-input" accept="image/png,image/jpeg,image/webp,image/gif">
        </div>
        <input class="input" id="p-image" value="${esc(p.image || "")}" placeholder="…or a path e.g. frames/img/lemon.webp" style="margin-top:.5rem">
      </div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.88rem"><input type="checkbox" id="p-active" ${p.id ? (Number(p.is_active) ? "checked" : "") : "checked"}> Active</label>
    </div>
    <div class="modal__foot"><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="p-save">Save</button></div>`);
  const pf = document.getElementById("p-file");
  pf.addEventListener("change", () => {
    const file = pf.files[0];
    const prev = document.getElementById("p-preview");
    if (file && prev && prev.tagName === "IMG")
      prev.src = URL.createObjectURL(file);
  });
  document.getElementById("p-save").addEventListener("click", async () => {
    const fd = new FormData();
    fd.append("id", p.id || 0);
    fd.append("name", v("p-name"));
    fd.append("flavour", v("p-flavour"));
    fd.append("sku", v("p-sku"));
    fd.append("size", v("p-size"));
    fd.append("cost", v("p-cost"));
    fd.append("price", v("p-price"));
    fd.append("partner_profit", v("p-profit"));
    fd.append("mrp", v("p-mrp"));
    fd.append("image", v("p-image"));
    fd.append("is_active", document.getElementById("p-active").checked ? 1 : 0);
    const file = pf.files[0];
    if (file) fd.append("image_file", file);
    try {
      const r = await apiForm("admin/product_save.php", fd);
      toast(r.message);
      closeModal();
      products();
    } catch (e) {
      toast(e.message, "error");
    }
  });
}
const v = (id) => document.getElementById(id).value.trim();

async function orders() {
  view.innerHTML = `<div class="card"><div class="card__head"><h2>Orders</h2>
    <div class="tabs" id="ord-tabs">${["all", "pending", "approved", "processing", "dispatched", "delivered", "cancelled"].map((s) => `<button data-s="${s}" class="${s === orderStatus ? "active" : ""}">${s[0].toUpperCase() + s.slice(1)}</button>`).join("")}</div></div>
    <div class="card__body" id="ord-list"><div class="spinner"></div></div></div>`;
  document.querySelectorAll("#ord-tabs button").forEach((b) =>
    b.addEventListener("click", () => {
      document
        .querySelectorAll("#ord-tabs button")
        .forEach((x) => x.classList.remove("active"));
      b.classList.add("active");
      orderStatus = b.dataset.s;
      loadOrders();
    }),
  );
  loadOrders();
}
async function loadOrders() {
  const box = document.getElementById("ord-list");
  box.innerHTML = '<div class="spinner"></div>';
  try {
    const data = await api(`orders/list.php?status=${orderStatus}`);
    const rows = data.orders || [];
    if (!rows.length) {
      box.innerHTML = emptyBox("fa-box-open", "No orders.");
      return;
    }
    box.innerHTML = `<div class="table-wrap"><table class="data"><thead><tr><th>Order</th><th>Store</th><th>Area</th><th>Supplier</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>${rows
        .map(
          (
            o,
          ) => `<tr><td><b>#${o.id}</b></td><td>${esc(o.store_name)}</td><td class="muted">${esc([o.district, o.state].filter(Boolean).join(", ") || "—")}</td>
        <td>${o.supplier_type === "partner" ? '<span class="badge badge--partner">partner</span>' : '<span class="muted">company</span>'}</td>
        <td>${o.item_count}</td><td>${money(o.total)}</td><td>${badge(o.status)}</td><td class="muted">${fmtDate(o.created_at)}</td>
        <td><button class="btn btn--ghost btn--sm" data-order="${o.id}"><i class="fa-solid fa-eye"></i></button></td></tr>`,
        )
        .join("")}</tbody></table></div>`;
    box
      .querySelectorAll("[data-order]")
      .forEach((b) =>
        b.addEventListener("click", () => openOrder(b.dataset.order)),
      );
  } catch (e) {
    box.innerHTML = errBox(e.message);
  }
}
async function openOrder(id) {
  try {
    const data = await api(`orders/detail.php?id=${id}`);
    const o = data.order;
    const items = data.items || [];
    const flow = [
      "pending",
      "approved",
      "processing",
      "dispatched",
      "delivered",
    ];
    const steps = flow
      .map((s) =>
        s === o.status
          ? `<span class="status-step status-step--on" aria-current="true">${s}</span>`
          : `<button class="status-step" data-status="${s}">${s}</button>`,
      )
      .join("");
    const dangerBtns =
      (o.status === "pending"
        ? `<button class="btn btn--danger btn--sm" data-status="rejected">reject</button>`
        : "") +
      (o.status === "cancelled"
        ? ""
        : `<button class="btn btn--danger btn--sm" data-status="cancelled">cancel</button>`);
    openModal(`<div class="modal__head"><h3>Order #${o.id}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
      <div class="modal__body"><div style="margin-bottom:.8rem">${badge(o.status)} <span class="muted">${fmtDate(o.created_at)}</span></div>
        <dl class="kv"><dt>Store</dt><dd>${esc(o.store_name)}</dd><dt>Owner</dt><dd>${esc(o.owner_name || "—")} · ${esc(o.store_phone || "")}</dd>
        <dt>Location</dt><dd>${esc([o.village, o.taluka, o.district, o.state, o.pincode].filter(Boolean).join(", "))}</dd></dl>
        <table class="data" style="width:100%;margin-top:.8rem"><thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
        <tbody>${items.map((i) => `<tr><td><div class="item-cell">${itemThumb(i.image)}<span>${esc(i.name)} <span class="muted">${esc(i.size || "")}</span></span></div></td><td>${i.qty}</td><td>${money(i.unit_price)}</td><td>${money(i.line_total)}</td></tr>`).join("")}</tbody></table>
        <div style="text-align:right;margin-top:.6rem"><b>Total: ${money(o.total)}</b></div>
        <div style="margin-top:.9rem">
          <div>Payment: <span class="pay pay--${o.payment_status}">${esc(o.payment_status)}</span> <span class="muted">— ${money(o.amount_paid)} of ${money(o.total)}</span></div>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.6rem;align-items:center">
            <select class="select" id="pay-status"><option value="unpaid">Unpaid</option><option value="partial">Partial</option><option value="paid">Paid</option></select>
            <input class="input" id="pay-amt" type="number" step="0.01" placeholder="Amount paid" value="${o.amount_paid || ""}" style="max-width:9rem;display:none">
            <button class="btn btn--lime btn--sm" id="rec-pay">Update Payment</button>
          </div>
        </div>
        <div style="margin-top:1rem"><label style="font-size:.8rem;font-weight:600;color:var(--heading)">Schedule delivery</label><div style="display:flex;gap:.4rem;margin-top:.4rem"><input class="input" type="date" id="sched-date" value="${o.delivery_date || ""}" style="max-width:11rem"><button class="btn btn--ghost btn--sm" id="sched-btn">Set date</button></div></div>
        <div style="margin-top:1rem"><label style="font-size:.8rem;font-weight:600;color:var(--heading)">Order status</label>
          <div class="status-steps">${steps}</div>
          ${dangerBtns ? `<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.6rem">${dangerBtns}</div>` : ""}</div>
      </div><div class="modal__foot"><a class="btn btn--ghost" href="../api/invoice.php?id=${o.id}" target="_blank"><i class="fa-solid fa-file-invoice"></i> Invoice</a><button class="btn btn--green" onclick="closeModal()">Close</button></div>`);
    document.getElementById("sched-btn").addEventListener("click", async () => {
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
        loadOrders();
      } catch (e) {
        toast(e.message, "error");
      }
    });
    const ps = document.getElementById("pay-status");
    const pa = document.getElementById("pay-amt");
    ps.value = o.payment_status;
    pa.style.display = o.payment_status === "partial" ? "" : "none";
    ps.addEventListener("change", () => {
      pa.style.display = ps.value === "partial" ? "" : "none";
    });
    document.getElementById("rec-pay").addEventListener("click", async () => {
      try {
        const r = await api("orders/payment.php", {
          method: "POST",
          body: { id: o.id, status: ps.value, amount: pa.value },
        });
        toast(r.message);
        closeModal();
        loadOrders();
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
          loadOrders();
        } catch (e) {
          toast(e.message, "error");
        }
      }),
    );
  } catch (e) {
    toast(e.message, "error");
  }
}

async function tickets() {
  view.innerHTML = `<div class="card"><div class="card__head"><h2>Support Tickets</h2>
    <div class="tabs" id="tk-tabs">${["all", "open", "pending", "resolved", "closed"].map((s) => `<button data-s="${s}" class="${s === ticketStatus ? "active" : ""}">${s[0].toUpperCase() + s.slice(1)}</button>`).join("")}</div></div>
    <div class="card__body" id="tk-list"><div class="spinner"></div></div></div>`;
  document.querySelectorAll("#tk-tabs button").forEach((b) =>
    b.addEventListener("click", () => {
      document
        .querySelectorAll("#tk-tabs button")
        .forEach((x) => x.classList.remove("active"));
      b.classList.add("active");
      ticketStatus = b.dataset.s;
      loadTickets();
    }),
  );
  loadTickets();
}
async function loadTickets() {
  const box = document.getElementById("tk-list");
  box.innerHTML = '<div class="spinner"></div>';
  try {
    const data = await api(`tickets/list.php?status=${ticketStatus}`);
    const rows = data.tickets || [];
    if (!rows.length) {
      box.innerHTML = emptyBox("fa-headset", "No tickets.");
      return;
    }
    box.innerHTML = `<div class="table-wrap"><table class="data"><thead><tr><th>Subject</th><th>From</th><th>Priority</th><th>Status</th><th>Updated</th><th></th></tr></thead>
      <tbody>${rows
        .map(
          (
            t,
          ) => `<tr><td><b>${esc(t.subject)}</b><div class="muted">${esc(t.category)}</div></td><td>${esc(t.opener)}<div class="muted">${esc(t.store_name || "")}</div></td>
        <td>${esc(t.priority)}</td><td>${badge(t.status)}</td><td class="muted">${fmtDate(t.updated_at)}</td>
        <td><button class="btn btn--ghost btn--sm" data-ticket="${t.id}"><i class="fa-solid fa-comments"></i></button></td></tr>`,
        )
        .join("")}</tbody></table></div>`;
    box
      .querySelectorAll("[data-ticket]")
      .forEach((b) =>
        b.addEventListener("click", () => openTicket(b.dataset.ticket)),
      );
  } catch (e) {
    box.innerHTML = errBox(e.message);
  }
}
async function openTicket(id) {
  try {
    const data = await api(`tickets/thread.php?id=${id}`);
    const t = data.ticket;
    openModal(`<div class="modal__head"><h3>${esc(t.subject)}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
      <div class="modal__body"><div style="margin-bottom:.7rem"><span id="t-status">${badge(t.status)}</span> <span class="muted">${esc(t.opener)} · ${esc(t.priority)}</span></div>
        <div id="t-actions" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.9rem"></div>
        <div id="thread" style="display:flex;flex-direction:column;gap:.7rem;max-height:38vh;overflow:auto"></div>
        <div id="reply-area"></div>
      </div>`);
    paintThread(data);
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
  const actions = document.getElementById("t-actions");
  if (actions && actions.dataset.forStatus !== t.status) {
    actions.dataset.forStatus = t.status;
    actions.innerHTML = ["open", "pending", "resolved", "closed"]
      .filter((s) => s !== t.status)
      .map(
        (s) =>
          `<button class="btn btn--ghost btn--sm" data-tstatus="${s}">${s}</button>`,
      )
      .join("");
    actions.querySelectorAll("[data-tstatus]").forEach((b) =>
      b.addEventListener("click", async () => {
        try {
          await api("tickets/status.php", {
            method: "POST",
            body: { ticket_id: t.id, status: b.dataset.tstatus },
          });
          paintThread(await api(`tickets/thread.php?id=${t.id}`));
          loadTickets();
        } catch (e) {
          toast(e.message, "error");
        }
      }),
    );
  }
  const area = document.getElementById("reply-area");
  if (area && !document.getElementById("reply-send")) {
    area.innerHTML = `<div style="margin-top:1rem"><div style="display:flex;gap:.5rem"><input class="input" id="reply-msg" placeholder="Type a reply…" style="flex:1"><button class="btn btn--green" id="reply-send">Send</button></div>
      <input type="file" id="reply-file" class="file-input" /></div>`;
    wireReply(t.id);
  }
}
function wireReply(id) {
  const send = document.getElementById("reply-send");
  if (!send) return;
  send.addEventListener("click", async () => {
    const mEl = document.getElementById("reply-msg");
    const fileEl = document.getElementById("reply-file");
    const m = mEl.value.trim();
    const file = fileEl.files[0];
    if (!m && !file) return;
    const fd = new FormData();
    fd.append("ticket_id", id);
    fd.append("message", m);
    if (file) fd.append("attachment", file);
    send.disabled = true;
    try {
      await apiForm("tickets/reply.php", fd);
      mEl.value = "";
      fileEl.value = "";
      paintThread(await api(`tickets/thread.php?id=${id}`));
    } catch (e) {
      toast(e.message, "error");
    }
    send.disabled = false;
  });
}
function msgBubble(m) {
  const staff = m.role === "admin" || m.role === "partner";
  return `<div style="align-self:${staff ? "flex-end" : "flex-start"};max-width:80%;background:${staff ? "#143a24" : "var(--soft)"};color:${staff ? "#fff" : "var(--ink)"};padding:.6rem .9rem;border-radius:.7rem">
    <div style="font-size:.72rem;opacity:.75;margin-bottom:.2rem">${esc(m.name)} (${esc(m.role)}) · ${fmtDate(m.created_at)}</div>${esc(m.message)}${m.attachment ? "<br>" + attachLink(m.attachment) : ""}</div>`;
}

async function analytics() {
  try {
    const d = await api(`admin/stats.php?level=${analyticsLevel}`);
    view.innerHTML = `
      <div class="card"><div class="card__head"><h2>Sales by Area</h2>
        <select class="select" id="lvl">${["state", "district", "taluka", "village", "pincode"].map((l) => `<option value="${l}" ${l === analyticsLevel ? "selected" : ""}>${l[0].toUpperCase() + l.slice(1)}</option>`).join("")}</select></div>
        <div class="card__body"><div class="chart-box"><canvas id="c-bar"></canvas></div></div></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-top:1.2rem">
        <div class="card"><div class="card__head"><h2>Orders by Status</h2></div><div class="card__body"><div class="chart-box"><canvas id="c-pie"></canvas></div></div></div>
        <div class="card"><div class="card__head"><h2>Stores — Orders vs Revenue</h2></div><div class="card__body"><div class="chart-box"><canvas id="c-scatter"></canvas></div></div></div>
      </div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Product Demand Heatmap <span class="muted">(district × product, units)</span></h2></div>
        <div class="card__body" id="heatmap"></div></div>`;
    document.getElementById("lvl").addEventListener("change", (e) => {
      analyticsLevel = e.target.value;
      analytics();
    });
    drawBar(d.region);
    drawPie(d.by_status);
    drawScatter(d.scatter);
    drawHeatmap(d.heat);
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

function mkChart(id, cfg) {
  if (_charts[id]) _charts[id].destroy();
  _charts[id] = new Chart(document.getElementById(id), cfg);
}
const GRID = { color: "#eef2ea" };
const CAT_COLORS = [
  "#2e7d32",
  "#3d8bfd",
  "#f2b705",
  "#8e5bd0",
  "#17a2b8",
  "#e57a44",
];

async function renderPayouts() {
  try {
    const d = await api("admin/payouts.php");
    const t = d.totals || {};
    view.innerHTML = `
      <div class="card"><div class="card__head"><h2>Distributor Profit Payouts</h2></div>
        <div class="card__body">
          <p class="muted" style="margin:0 0 1rem">Each distributor earns a set profit per bottle their retailers buy. Settle a payout to record that the company has paid them — their pending amount then resets to zero.</p>
          <div class="stat-row" style="grid-template-columns:repeat(3,1fr)">
            ${stat("Total Profit Earned", money(t.earned), "fa-coins")}
            ${stat("Total Paid Out", money(t.settled), "fa-hand-holding-dollar")}
            ${stat("Pending to Pay", money(t.outstanding), "fa-hourglass-half")}
          </div>
          <div class="table-wrap" style="margin-top:.5rem"><table class="data"><thead><tr><th>Distributor</th><th>Profit Earned</th><th>Paid Out</th><th>Pending</th><th>Last Settled</th><th></th></tr></thead>
          <tbody>${
            (d.partners || []).length
              ? d.partners
                  .map(
                    (p) =>
                      `<tr><td><b>${esc(p.name)}</b><div class="muted">${esc(p.email)}</div></td>
                      <td>${money(p.earned)}</td><td class="muted">${money(p.settled)}</td>
                      <td><b style="color:${p.outstanding > 0 ? "var(--green-dark)" : "var(--muted)"}">${money(p.outstanding)}</b></td>
                      <td class="muted">${p.last_settled ? fmtDate(p.last_settled) : "—"}</td>
                      <td>${p.outstanding > 0 ? `<button class="btn btn--green btn--sm" data-settle="${p.id}" data-name="${esc(p.name)}" data-out="${p.outstanding}">Settle</button>` : '<span class="muted">Settled</span>'}</td></tr>`,
                  )
                  .join("")
              : '<tr><td colspan="6"><div class="empty"><i class="fa-solid fa-handshake"></i><p>No active distributors.</p></div></td></tr>'
          }</tbody></table></div>
        </div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Payout History</h2></div><div class="card__body">
        ${
          (d.history || []).length
            ? `<div class="table-wrap"><table class="data"><thead><tr><th>Date</th><th>Distributor</th><th>Amount</th><th>Note</th><th>By</th></tr></thead>
          <tbody>${d.history.map((h) => `<tr><td class="muted">${fmtDate(h.created_at)}</td><td><b>${esc(h.partner_name)}</b></td><td>${money(h.amount)}</td><td class="muted">${esc(h.note || "—")}</td><td class="muted">${esc(h.settled_by_name || "—")}</td></tr>`).join("")}</tbody></table></div>`
            : emptyBox("fa-receipt", "No payouts settled yet.")
        }</div></div>`;
    view
      .querySelectorAll("[data-settle]")
      .forEach((b) =>
        b.addEventListener("click", () =>
          settleModal(
            Number(b.dataset.settle),
            b.dataset.name,
            Number(b.dataset.out),
          ),
        ),
      );
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function settleModal(id, name, outstanding) {
  openModal(`<div class="modal__head"><h3>Settle payout — ${esc(name)}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <p class="muted" style="margin-top:0">Pending profit: <b style="color:var(--heading)">${money(outstanding)}</b>. Record the amount the company has paid this distributor.</p>
      <div class="field"><label>Amount to settle (₹)</label><input class="input" id="st-amt" type="number" step="0.01" max="${outstanding}" value="${outstanding.toFixed(2)}"></div>
      <div class="field"><label>Note <span class="muted" style="font-weight:400">(optional — e.g. UPI ref)</span></label><input class="input" id="st-note" placeholder="Payment reference"></div>
    </div>
    <div class="modal__foot"><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="st-do">Record Payout</button></div>`);
  document.getElementById("st-do").addEventListener("click", async () => {
    try {
      const r = await api("admin/settle_partner.php", {
        method: "POST",
        body: { partner_id: id, amount: v("st-amt"), note: v("st-note") },
      });
      toast(r.message);
      closeModal();
      renderPayouts();
    } catch (e) {
      toast(e.message, "error");
    }
  });
}
let insightsDays = 30;
let insightsFrom = "";
let insightsTo = "";
const INS_PRESETS = [
  ["Today", 1],
  ["7 days", 7],
  ["15 days", 15],
  ["Month", 30],
  ["90 days", 90],
  ["Year", 365],
];
function insightsQuery() {
  return insightsFrom && insightsTo
    ? `from=${insightsFrom}&to=${insightsTo}`
    : `days=${insightsDays}`;
}
async function renderInsights() {
  try {
    const d = await api(`admin/insights.php?${insightsQuery()}`);
    const p = d.profit || {};
    const r = d.retention || {};
    const inv = d.inventory || {};
    const prods = d.by_product || [];
    const custom = !!(insightsFrom && insightsTo);
    view.innerHTML = `
      <div class="card"><div class="card__head"><h2>Profit &amp; Margins</h2>
        <span class="muted" style="font-size:.85rem">${esc(d.range_label || "")}</span></div>
        <div class="card__body">
          <div class="filter-bar">
            ${INS_PRESETS.map(([lbl, n]) => `<button class="chip-btn ${!custom && n === insightsDays ? "chip-btn--on" : ""}" data-days="${n}">${lbl}</button>`).join("")}
            <span class="filter-bar__sep"></span>
            <input type="date" class="input input--sm" id="ins-from" value="${insightsFrom}" aria-label="From date">
            <span class="muted">to</span>
            <input type="date" class="input input--sm" id="ins-to" value="${insightsTo}" aria-label="To date">
            <button class="btn btn--sm btn--green" id="ins-apply">Apply</button>
            ${custom ? '<button class="btn btn--sm btn--ghost" id="ins-clear">Clear</button>' : ""}
          </div>
          <div class="stat-row">
            ${stat("Net Sales", money(p.revenue), "fa-indian-rupee-sign")}
            ${stat("Cost of Goods", money(p.cogs), "fa-boxes-stacked")}
            ${stat("Partner Payout", money(p.partner_payout), "fa-handshake")}
            ${stat("Company Profit", money(p.net_profit), "fa-sack-dollar")}
            ${stat("Profit / Bottle", money(p.net_per_bottle), "fa-bottle-water")}
            ${stat("Margin", (p.margin || 0) + "%", "fa-percent")}
          </div>
          <div class="chart-box" style="height:17rem;margin-top:.4rem"><canvas id="ins-trend"></canvas></div>
        </div></div>

      <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:1.2rem;margin-top:1.2rem">
        <div class="card"><div class="card__head"><h2>Profit by Product</h2></div><div class="card__body"><div class="chart-box" style="height:16rem"><canvas id="ins-prodprofit"></canvas></div></div></div>
        <div class="card"><div class="card__head"><h2>Revenue Mix</h2></div><div class="card__body"><div class="chart-box" style="height:16rem"><canvas id="ins-mix"></canvas></div></div></div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1.3fr;gap:1.2rem;margin-top:1.2rem">
        <div class="card"><div class="card__head"><h2>Customer Retention</h2></div><div class="card__body">
          <div class="chart-box" style="height:13rem"><canvas id="ins-retention"></canvas></div>
          <p class="muted" style="text-align:center;margin-top:.6rem"><b>${r.repeat || 0}</b> of ${r.buyers || 0} stores ordered more than once</p>
        </div></div>
        <div class="card"><div class="card__head"><h2>New Customers Acquired</h2></div><div class="card__body"><div class="chart-box" style="height:15rem"><canvas id="ins-newcust"></canvas></div></div></div>
      </div>

      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Per-Bottle Profit by Product</h2></div><div class="card__body">
        <p class="muted" style="margin:0 0 .8rem">Company profit = sale price − bottle cost − distributor commission (only on partner-supplied orders).</p>
        <div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>Units</th><th>Revenue</th><th>Cost</th><th>Partner Payout</th><th>Profit</th><th>Profit / Bottle</th><th>Margin</th></tr></thead>
        <tbody>${prods
          .map(
            (x) =>
              `<tr><td><b>${esc(x.name)}</b></td><td>${Number(x.units).toLocaleString("en-IN")}</td><td>${money(x.revenue)}</td><td class="muted">${money(x.cogs)}</td><td class="muted">${money(x.partner_payout)}</td>
              <td><b style="color:var(--heading)">${money(x.profit)}</b></td><td><b>${money(x.per_bottle)}</b></td><td><span class="pay ${x.margin >= 40 ? "pay--paid" : x.margin >= 20 ? "pay--partial" : "pay--unpaid"}">${x.margin || 0}%</span></td></tr>`,
          )
          .join("")}</tbody></table></div></div></div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-top:1.2rem">
        <div class="card"><div class="card__head"><h2>Inventory Value</h2></div><div class="card__body">
          <div class="stat-row" style="grid-template-columns:1fr 1fr">
            ${stat("Company Stock (cost)", money(inv.company_cost_value), "fa-warehouse")}
            ${stat("Company Stock (retail)", money(inv.company_retail_value), "fa-tags")}
            ${stat("Units in Company WH", Number(inv.company_units || 0).toLocaleString("en-IN"), "fa-boxes-stacked")}
            ${stat("Stock Turnover (90d)", (inv.turnover_90d || 0) + "×", "fa-rotate")}
          </div></div></div>
        <div class="card"><div class="card__head"><h2>Low Company Stock</h2></div><div class="card__body">
          ${
            (d.low_stock || []).length
              ? `<div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>SKU</th><th>On Hand</th></tr></thead>
            <tbody>${d.low_stock.map((l) => `<tr><td><b>${esc(l.name)}</b></td><td class="muted">${esc(l.sku)}</td><td><b style="color:${l.qty <= 0 ? "var(--danger)" : "#c47d1a"}">${Number(l.qty).toLocaleString("en-IN")}</b></td></tr>`).join("")}</tbody></table></div>`
              : emptyBox("fa-circle-check", "All products well stocked 🎉")
          }</div></div>
      </div>

      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Most Loyal Retailers</h2></div><div class="card__body">
        ${
          (d.top_retainers || []).length
            ? `<div class="table-wrap"><table class="data"><thead><tr><th>Store</th><th>Area</th><th>Orders</th><th>Revenue</th><th>Last Order</th></tr></thead>
          <tbody>${d.top_retainers.map((s) => `<tr><td><b>${esc(s.name)}</b></td><td class="muted">${esc(s.district || "—")}</td><td>${s.orders}</td><td>${money(s.revenue)}</td><td class="muted">${fmtDate(s.last_order)}</td></tr>`).join("")}</tbody></table></div>`
            : emptyBox("fa-users", "No repeat customers yet.")
        }</div></div>`;

    view.querySelectorAll("[data-days]").forEach((b) =>
      b.addEventListener("click", () => {
        insightsDays = Number(b.dataset.days);
        insightsFrom = "";
        insightsTo = "";
        renderInsights();
      }),
    );
    const insApply = document.getElementById("ins-apply");
    if (insApply)
      insApply.addEventListener("click", () => {
        const f = document.getElementById("ins-from").value;
        const t = document.getElementById("ins-to").value;
        if (!f || !t) return toast("Pick both a start and end date", "warn");
        insightsFrom = f;
        insightsTo = t;
        renderInsights();
      });
    const insClear = document.getElementById("ins-clear");
    if (insClear)
      insClear.addEventListener("click", () => {
        insightsFrom = "";
        insightsTo = "";
        insightsDays = 30;
        renderInsights();
      });

    drawInsTrend(d.trend || []);
    drawInsProdProfit(prods);
    drawInsMix(prods);
    drawInsRetention(r);
    drawInsNewCust(d.new_customers || []);
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function drawInsTrend(rows) {
  mkChart("ins-trend", {
    type: "line",
    data: {
      labels: rows.map((r) => r.d.slice(5)),
      datasets: [
        {
          label: "Revenue",
          data: rows.map((r) => +r.revenue),
          borderColor: "#2e7d32",
          backgroundColor: "rgba(46,125,50,.10)",
          fill: true,
          tension: 0.3,
          borderWidth: 2,
          pointRadius: 0,
        },
        {
          label: "Gross Profit",
          data: rows.map((r) => +r.profit),
          borderColor: "#3d8bfd",
          backgroundColor: "rgba(61,139,253,.08)",
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
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: {
          position: "top",
          labels: { boxWidth: 12, font: { size: 11 } },
        },
        tooltip: {
          callbacks: { label: (c) => c.dataset.label + ": " + money(c.raw) },
        },
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
function drawInsProdProfit(rows) {
  rows = rows.slice().sort((a, b) => b.profit - a.profit);
  mkChart("ins-prodprofit", {
    type: "bar",
    data: {
      labels: rows.map((r) => r.name),
      datasets: [
        {
          data: rows.map((r) => +r.profit),
          backgroundColor: "#2e7d32",
          borderRadius: 4,
          maxBarThickness: 30,
        },
      ],
    },
    options: {
      indexAxis: "y",
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (c) => "Profit: " + money(c.raw) } },
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
function drawInsMix(rows) {
  mkChart("ins-mix", {
    type: "doughnut",
    data: {
      labels: rows.map((r) => r.name),
      datasets: [
        {
          data: rows.map((r) => +r.revenue),
          backgroundColor: rows.map(
            (_, i) => CAT_COLORS[i % CAT_COLORS.length],
          ),
          borderColor: "#fff",
          borderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "58%",
      plugins: {
        legend: {
          position: "right",
          labels: { boxWidth: 12, font: { size: 11 } },
        },
        tooltip: { callbacks: { label: (c) => c.label + ": " + money(c.raw) } },
      },
    },
  });
}
function drawInsRetention(r) {
  mkChart("ins-retention", {
    type: "doughnut",
    data: {
      labels: ["Repeat buyers", "One-time"],
      datasets: [
        {
          data: [r.repeat || 0, r.one_time || 0],
          backgroundColor: ["#2e7d32", "#d7e2cd"],
          borderColor: "#fff",
          borderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "62%",
      plugins: {
        legend: {
          position: "bottom",
          labels: { boxWidth: 12, font: { size: 11 } },
        },
      },
    },
  });
}
function drawInsNewCust(rows) {
  mkChart("ins-newcust", {
    type: "bar",
    data: {
      labels: rows.map((r) => r.m),
      datasets: [
        {
          data: rows.map((r) => +r.n),
          backgroundColor: "#3d8bfd",
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
        tooltip: { callbacks: { label: (c) => c.raw + " new stores" } },
      },
      scales: {
        y: { beginAtZero: true, grid: GRID, ticks: { precision: 0 } },
        x: { grid: { display: false } },
      },
    },
  });
}

function drawBar(region) {
  const rows = (region || []).slice(0, 12);
  mkChart("c-bar", {
    type: "bar",
    data: {
      labels: rows.map((r) => r.area),
      datasets: [
        {
          label: "Sales value",
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
function drawPie(bs) {
  const rows = bs || [];
  mkChart("c-pie", {
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
      cutout: "58%",
      plugins: {
        legend: {
          position: "right",
          labels: { boxWidth: 12, font: { size: 11 } },
        },
      },
    },
  });
}
function drawScatter(sc) {
  const rows = sc || [];
  mkChart("c-scatter", {
    type: "scatter",
    data: {
      datasets: [
        {
          label: "Stores",
          data: rows.map((r) => ({
            x: +r.orders,
            y: +r.value,
            store: r.store,
          })),
          backgroundColor: "#7cb342",
          pointRadius: 5,
          pointHoverRadius: 8,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (c) =>
              `${c.raw.store}: ${c.raw.x} orders · ${money(c.raw.y)}`,
          },
        },
      },
      scales: {
        x: {
          title: { display: true, text: "Orders" },
          beginAtZero: true,
          grid: GRID,
        },
        y: {
          title: { display: true, text: "Revenue" },
          beginAtZero: true,
          grid: GRID,
          ticks: { callback: (v) => "₹" + v },
        },
      },
    },
  });
}
function drawHeatmap(heat) {
  const el = document.getElementById("heatmap");
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

let _adminMap = null;
async function renderMap() {
  if (_adminMap) {
    _adminMap.remove();
    _adminMap = null;
  }
  view.innerHTML = `<div class="card"><div class="card__head"><h2>All Store Locations</h2><span class="muted">🟢 Retailer · 🟣 Partner</span></div><div class="card__body"><div id="admin-map" style="height:32rem;border-radius:.6rem;overflow:hidden;background:#e9efe2"></div></div></div>`;
  try {
    const stores = (await api("admin/store_locations.php")).stores || [];
    const el = document.getElementById("admin-map");
    _adminMap = L.map(el).setView([15.49, 73.83], 7);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap",
    }).addTo(_adminMap);
    setTimeout(() => _adminMap.invalidateSize(), 200);
    const pts = [];
    stores.forEach((s) => {
      const ll = [+s.lat, +s.lng];
      const color = s.role === "partner" ? "#6a35b0" : "#2e7d32";
      const icon = L.divIcon({
        className: "",
        iconSize: [24, 24],
        iconAnchor: [12, 24],
        html: `<div class="lpin" style="--pc:${color}"></div>`,
      });
      L.marker(ll, { icon, title: s.name })
        .addTo(_adminMap)
        .bindPopup(
          `<b>${esc(s.name)}</b><br><small>${esc([s.taluka, s.district, s.state].filter(Boolean).join(", "))}</small><br><small>${esc(s.role)}${s.partner_name ? " · under " + esc(s.partner_name) : ""}</small>`,
        );
      pts.push(ll);
    });
    if (pts.length) _adminMap.fitBounds(L.latLngBounds(pts).pad(0.2));
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

function updateAppBadge(n) {
  const b = document.getElementById("nav-apps");
  if (!b) return;
  b.textContent = n;
  b.hidden = !n;
}
function updateTermBadge(n) {
  const b = document.getElementById("nav-terms");
  if (!b) return;
  b.textContent = n;
  b.hidden = !n;
}
let termState = { status: "pending" };
async function renderTerminations() {
  try {
    const d = await api(
      `admin/termination_requests.php?status=${termState.status}`,
    );
    const rows = d.requests || [];
    view.innerHTML = `<div class="card"><div class="card__head"><h2>Retailer Termination Requests</h2>
      <div class="tabs" id="term-tabs">${["pending", "approved", "rejected", "all"]
        .map(
          (s) =>
            `<button data-s="${s}" class="${s === termState.status ? "active" : ""}">${s[0].toUpperCase() + s.slice(1)}</button>`,
        )
        .join("")}</div></div>
      <div class="card__body">${
        rows.length
          ? `<div class="table-wrap"><table class="data"><thead><tr><th>Retailer</th><th>Requested by</th><th>Reason</th><th>Status</th><th>When</th><th></th></tr></thead>
            <tbody>${rows
              .map(
                (r) => `<tr>
              <td><b>${esc(r.retailer_name)}</b><div class="muted">${esc(r.store_name)}${r.district ? " · " + esc(r.district) : ""}</div></td>
              <td>${esc(r.partner_name)}</td>
              <td class="muted">${esc(r.reason || "—")}${r.review_notes ? `<div class="muted" style="margin-top:.2rem">Note: ${esc(r.review_notes)}</div>` : ""}</td>
              <td>${badge(r.status)}</td>
              <td class="muted">${fmtDate(r.created_at)}</td>
              <td style="white-space:nowrap">${
                r.status === "pending"
                  ? `<button class="btn btn--green btn--sm" data-term-approve="${r.id}" data-name="${esc(r.retailer_name)}">Approve</button> <button class="btn btn--ghost btn--sm" data-term-reject="${r.id}" data-name="${esc(r.retailer_name)}">Decline</button>`
                  : `<span class="muted">${r.reviewed_at ? fmtDate(r.reviewed_at) : ""}</span>`
              }</td></tr>`,
              )
              .join("")}</tbody></table></div>`
          : emptyBox(
              "fa-user-slash",
              `No ${termState.status === "all" ? "" : termState.status + " "}termination requests.`,
            )
      }</div></div>`;
    document.querySelectorAll("#term-tabs button").forEach((b) =>
      b.addEventListener("click", () => {
        termState.status = b.dataset.s;
        renderTerminations();
      }),
    );
    view.querySelectorAll("[data-term-approve]").forEach((b) =>
      b.addEventListener("click", async () => {
        const notes = await uiPrompt(
          "Approving will terminate " +
            (b.dataset.name || "this retailer") +
            " — they won't be able to log in. Optional note (shared with them):",
          {
            title: "Approve termination",
            confirmText: "Approve & terminate",
            placeholder: "Optional note",
          },
        );
        if (notes === null) return;
        try {
          const r = await api("admin/termination_action.php", {
            method: "POST",
            body: { id: b.dataset.termApprove, action: "approve", notes },
          });
          toast(r.message);
          renderTerminations();
        } catch (e) {
          toast(e.message, "error");
        }
      }),
    );
    view.querySelectorAll("[data-term-reject]").forEach((b) =>
      b.addEventListener("click", async () => {
        const notes = await uiPrompt(
          "Decline this termination request? Optional reason for the partner:",
          {
            title: "Decline request",
            confirmText: "Decline request",
            placeholder: "Optional reason",
          },
        );
        if (notes === null) return;
        try {
          const r = await api("admin/termination_action.php", {
            method: "POST",
            body: { id: b.dataset.termReject, action: "reject", notes },
          });
          toast(r.message);
          renderTerminations();
        } catch (e) {
          toast(e.message, "error");
        }
      }),
    );
    api("admin/termination_requests.php?status=pending")
      .then((x) => updateTermBadge((x.requests || []).length))
      .catch(() => {});
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

async function renderRevenue() {
  try {
    const d = await api("admin/analytics.php?days=30");
    const k = d.kpi || {};
    const exp = (t, label) =>
      `<a class="btn btn--ghost btn--sm" href="../api/admin/export.php?type=${t}" target="_blank"><i class="fa-solid fa-file-csv"></i> ${label}</a>`;
    const today = new Date().toISOString().slice(0, 10);
    const monthStart = today.slice(0, 8) + "01";
    view.innerHTML = `
      <div class="card"><div class="card__head"><h2>Reports &amp; Exports</h2></div>
        <div class="card__body">
          <div style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap">
            <div class="field" style="margin:0"><label>From</label><input class="input" type="date" id="rep-from" value="${monthStart}"></div>
            <div class="field" style="margin:0"><label>To</label><input class="input" type="date" id="rep-to" value="${today}"></div>
            <button class="btn btn--green btn--sm" id="rep-sales"><i class="fa-solid fa-chart-column"></i> Sales Report</button>
            <button class="btn btn--green btn--sm" id="rep-gst"><i class="fa-solid fa-file-invoice-dollar"></i> GST Report</button>
            <span style="width:1px;height:2rem;background:var(--line)"></span>
            ${exp("orders", "Orders CSV")}${exp("dues", "Dues CSV")}${exp("retailers", "Retailers CSV")}
          </div>
          <p class="muted" style="font-size:.78rem;margin:.7rem 0 0">Reports open in a new tab — print to PDF or download CSV from there.</p>
        </div></div>
      <div class="stat-row" style="margin-top:1.2rem">
        ${stat("Total Revenue", money(k.revenue), "fa-indian-rupee-sign")}
        ${stat("Collected", money(k.collected), "fa-circle-check")}
        ${stat("Outstanding", money(k.outstanding), "fa-hourglass-half")}
        ${stat("Avg Order Value", money(k.aov), "fa-receipt")}
        ${stat("Total Orders", k.orders || 0, "fa-box")}
        ${stat("Delivered", k.delivered || 0, "fa-truck")}
      </div>
      <div class="card"><div class="card__head"><h2>Revenue &amp; Collections (30 days)</h2></div><div class="card__body"><div class="chart-box" style="height:18rem"><canvas id="r-trend"></canvas></div></div></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-top:1.2rem">
        <div class="card"><div class="card__head"><h2>Order Fulfilment Funnel</h2></div><div class="card__body"><div class="chart-box" style="height:16rem"><canvas id="r-funnel"></canvas></div></div></div>
        <div class="card"><div class="card__head"><h2>Revenue by Channel</h2></div><div class="card__body"><div class="chart-box" style="height:16rem"><canvas id="r-supplier"></canvas></div></div></div>
        <div class="card"><div class="card__head"><h2>Top Retailers by Revenue</h2></div><div class="card__body"><div class="chart-box" style="height:16rem"><canvas id="r-top"></canvas></div></div></div>
        <div class="card"><div class="card__head"><h2>Subscriber Growth</h2></div><div class="card__body"><div class="chart-box" style="height:16rem"><canvas id="r-subs"></canvas></div></div></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-top:1.2rem">
        <div class="card"><div class="card__head"><h2>Payment Status</h2></div><div class="card__body">${payTable(d.payment_split)}</div></div>
        <div class="card"><div class="card__head"><h2>Application Conversion</h2></div><div class="card__body">${convTable(d.app_conversion)}</div></div>
      </div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Outstanding Dues</h2><span class="muted">Top unpaid orders</span></div><div class="card__body">${duesTable(d.dues)}</div></div>`;
    const range = () =>
      `from=${document.getElementById("rep-from").value}&to=${document.getElementById("rep-to").value}`;
    document
      .getElementById("rep-sales")
      .addEventListener("click", () =>
        window.open("../api/admin/report_sales.php?" + range(), "_blank"),
      );
    document
      .getElementById("rep-gst")
      .addEventListener("click", () =>
        window.open("../api/admin/report_gst.php?" + range(), "_blank"),
      );
    drawTrend(d.revenue_trend);
    drawFunnel(d.funnel);
    drawSupplier(d.by_supplier);
    drawTopRetailers(d.top_retailers);
    drawSubs(d.sub_growth);
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function payTable(rows) {
  if (!rows || !rows.length) return emptyBox("fa-wallet", "No data.");
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Status</th><th>Orders</th><th>Amount Due</th></tr></thead>
    <tbody>${rows.map((r) => `<tr><td><span class="pay pay--${r.payment_status}">${esc(r.payment_status)}</span></td><td>${r.n}</td><td>${money(r.due)}</td></tr>`).join("")}</tbody></table></div>`;
}
function convTable(rows) {
  if (!rows || !rows.length)
    return emptyBox("fa-inbox", "No applications yet.");
  const total = rows.reduce((s, r) => s + Number(r.n), 0);
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Stage</th><th>Count</th><th>Share</th></tr></thead>
    <tbody>${rows.map((r) => `<tr><td>${badge(r.status)}</td><td>${r.n}</td><td>${total ? Math.round((r.n / total) * 100) : 0}%</td></tr>`).join("")}</tbody></table></div>`;
}
function duesTable(rows) {
  if (!rows || !rows.length)
    return emptyBox("fa-circle-check", "No outstanding dues 🎉");
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Order</th><th>Store</th><th>Area</th><th>Total</th><th>Paid</th><th>Due</th><th>Date</th></tr></thead>
    <tbody>${rows.map((r) => `<tr><td><b>#${r.id}</b></td><td>${esc(r.store_name)}</td><td class="muted">${esc(r.district || "—")}</td><td>${money(r.total)}</td><td>${money(r.amount_paid)}</td><td><b style="color:var(--danger)">${money(r.due)}</b></td><td class="muted">${fmtDate(r.created_at)}</td></tr>`).join("")}</tbody></table></div>`;
}
function drawTrend(rows) {
  rows = rows || [];
  mkChart("r-trend", {
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
        {
          label: "Collected",
          data: rows.map((r) => +r.collected),
          borderColor: "#ffca28",
          backgroundColor: "transparent",
          tension: 0.3,
          borderWidth: 2,
          pointRadius: 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: { position: "top" },
        tooltip: {
          callbacks: { label: (c) => c.dataset.label + ": " + money(c.raw) },
        },
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
function drawFunnel(rows) {
  rows = rows || [];
  mkChart("r-funnel", {
    type: "bar",
    data: {
      labels: rows.map((r) => r.status),
      datasets: [
        {
          data: rows.map((r) => +r.n),
          backgroundColor: rows.map(
            (r) => STATUS_COLORS[r.status] || "#7cb342",
          ),
          borderRadius: 4,
          maxBarThickness: 40,
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
function drawSupplier(rows) {
  rows = rows || [];
  mkChart("r-supplier", {
    type: "doughnut",
    data: {
      labels: rows.map((r) =>
        r.supplier_type === "partner" ? "Via Partners" : "Direct (Company)",
      ),
      datasets: [
        {
          data: rows.map((r) => +r.revenue),
          backgroundColor: ["#2e7d32", "#6a35b0"],
          borderColor: "#fff",
          borderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "58%",
      plugins: {
        legend: { position: "bottom" },
        tooltip: { callbacks: { label: (c) => c.label + ": " + money(c.raw) } },
      },
    },
  });
}
function drawTopRetailers(rows) {
  rows = (rows || []).slice(0, 8);
  mkChart("r-top", {
    type: "bar",
    data: {
      labels: rows.map((r) => r.name),
      datasets: [
        {
          data: rows.map((r) => +r.revenue),
          backgroundColor: "#7cb342",
          borderRadius: 4,
          maxBarThickness: 26,
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
function drawSubs(rows) {
  rows = rows || [];
  mkChart("r-subs", {
    type: "line",
    data: {
      labels: rows.map((r) => r.d.slice(5)),
      datasets: [
        {
          label: "New subscribers",
          data: rows.map((r) => +r.n),
          borderColor: "#f57c00",
          backgroundColor: "rgba(245,124,0,.12)",
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
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: GRID },
        x: { grid: { display: false } },
      },
    },
  });
}

let logFile = "app";
function fmtBytes(n) {
  n = Number(n || 0);
  if (n < 1024) return n + " B";
  if (n < 1048576) return (n / 1024).toFixed(1) + " KB";
  return (n / 1048576).toFixed(1) + " MB";
}
function logSeverity(line) {
  const l = line.toLowerCase();
  if (/fatal|uncaught|\berror\b|exception/.test(l)) return "err";
  if (/warning|deprecated|notice/.test(l)) return "warn";
  return "info";
}
async function systemLogs() {
  view.innerHTML = `
    <div class="card"><div class="card__head"><h2>System Error Logs</h2>
      <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <div class="seg" id="log-tabs">
          <button class="seg__btn" data-log="app">App Errors</button>
          <button class="seg__btn" data-log="php">PHP Errors</button>
        </div>
        <button class="btn btn--ghost btn--sm" id="log-refresh"><i class="fa-solid fa-rotate"></i></button>
        <button class="btn btn--danger btn--sm" id="log-clear"><i class="fa-solid fa-trash"></i> Clear</button>
      </div></div>
      <div class="card__body">
        <p class="muted" id="log-meta" style="margin:0 0 .8rem">Loading…</p>
        <div id="log-body"></div>
      </div></div>`;
  document.querySelectorAll("#log-tabs .seg__btn").forEach((b) =>
    b.addEventListener("click", () => {
      logFile = b.dataset.log;
      refreshLogs();
    }),
  );
  document.getElementById("log-refresh").addEventListener("click", refreshLogs);
  document.getElementById("log-clear").addEventListener("click", async () => {
    if (
      !(await uiConfirm("Clear the " + logFile + " log? This cannot be undone.", {
        title: "Clear log",
        confirmText: "Clear log",
        danger: true,
      }))
    )
      return;
    try {
      const r = await api("admin/logs_clear.php", {
        method: "POST",
        body: { file: logFile },
      });
      toast(r.message);
      refreshLogs();
    } catch (e) {
      toast(e.message, "error");
    }
  });
  refreshLogs();
}
async function refreshLogs() {
  const body = document.getElementById("log-body");
  if (!body) return;
  try {
    const d = await api(`admin/logs.php?file=${logFile}&lines=400`);
    document.querySelectorAll("#log-tabs .seg__btn").forEach((b) => {
      const m = (d.meta || {})[b.dataset.log] || {};
      b.classList.toggle("seg__btn--on", b.dataset.log === d.file);
      b.innerHTML =
        (b.dataset.log === "app" ? "App Errors" : "PHP Errors") +
        ` <span class="muted">(${fmtBytes(m.size)})</span>`;
    });
    const meta = (d.meta || {})[d.file] || {};
    const metaEl = document.getElementById("log-meta");
    if (metaEl)
      metaEl.innerHTML = meta.exists
        ? `${d.lines.length} most-recent lines · ${fmtBytes(meta.size)} · updated ${meta.modified ? fmtDate(meta.modified) : "—"} · newest first`
        : "No entries yet — this log is empty. 🎉";
    body.innerHTML = d.lines.length
      ? `<div class="logbox">${d.lines
          .map(
            (l) =>
              `<div class="logline logline--${logSeverity(l)}">${esc(l)}</div>`,
          )
          .join("")}</div>`
      : emptyBox(
          "fa-clipboard-check",
          "No errors logged. Everything's healthy.",
        );
  } catch (e) {
    body.innerHTML = errBox(e.message);
  }
}
async function settings() {
  try {
    const s = (await api("admin/settings.php")).settings || {};
    const g = (k) => esc(s[k] ?? "");
    view.innerHTML = `
      <div class="card"><div class="card__head"><h2>Company Details <span class="muted">(shown on invoices)</span></h2></div>
        <div class="card__body">
          <div class="field"><label>Company Name</label><input class="input" id="set-company_name" value="${g("company_name")}"></div>
          <div class="field"><label>Address</label><textarea id="set-company_address" rows="2">${g("company_address")}</textarea></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
            <div class="field"><label>Phone</label><input class="input" id="set-company_phone" value="${g("company_phone")}"></div>
            <div class="field"><label>Email</label><input class="input" id="set-company_email" value="${g("company_email")}"></div>
            <div class="field"><label>GSTIN</label><input class="input" id="set-company_gstin" value="${g("company_gstin")}" placeholder="e.g. 30ABCDE1234F1Z5"></div>
            <div class="field"><label>State</label><input class="input" id="set-company_state" value="${g("company_state")}" placeholder="Used for CGST/SGST vs IGST"></div>
          </div>
        </div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Tax &amp; Orders <span class="muted">(auto-applied to every new order)</span></h2></div>
        <div class="card__body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
            <div class="field"><label>GST %</label><input class="input" id="set-gst_percent" type="number" step="0.01" value="${g("gst_percent")}"></div>
            <div class="field"><label>Compensation Cess %</label><input class="input" id="set-cess_percent" type="number" step="0.01" value="${g("cess_percent")}"></div>
            <div class="field"><label>Invoice Prefix</label><input class="input" id="set-invoice_prefix" value="${g("invoice_prefix")}"></div>
            <div class="field"><label>Minimum Order Qty</label><input class="input" id="set-order_min_qty" type="number" value="${g("order_min_qty")}"></div>
          </div>
          <p class="muted" style="font-size:.82rem;margin-top:.3rem">India (carbonated / fizzy beverages): typically 28% GST + 12% cess. Adjust if your product is classified differently.</p>
          <button class="btn btn--green" id="set-save" style="margin-top:1rem"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
        </div></div>`;
    document.getElementById("set-save").addEventListener("click", async () => {
      const keys = [
        "company_name",
        "company_address",
        "company_phone",
        "company_email",
        "company_gstin",
        "company_state",
        "gst_percent",
        "cess_percent",
        "invoice_prefix",
        "order_min_qty",
      ];
      const body = {};
      keys.forEach((k) => {
        const el = document.getElementById("set-" + k);
        if (el) body[k] = el.value.trim();
      });
      try {
        const r = await api("admin/settings_save.php", {
          method: "POST",
          body,
        });
        toast(r.message);
      } catch (e) {
        toast(e.message, "error");
      }
    });
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

let emailTargets = null;
let mailLogStatus = "all";
const MAIL_CTX = {
  approval: "Approval",
  order_placed: "Order placed",
  order_new_partner: "New order → partner",
  order_status: "Order status",
  payment: "Payment",
  admin_compose: "Admin email",
  admin_broadcast: "Broadcast",
  partner_compose: "Partner email",
};
async function renderEmailLog() {
  const box = document.getElementById("mail-log");
  if (!box) return;
  try {
    const d = await api(
      `admin/email_log.php?status=${mailLogStatus}&limit=150`,
    );
    const cn = d.counts || {};
    document.querySelectorAll("#mail-tabs .seg__btn").forEach((b) => {
      const k = b.dataset.mail;
      b.classList.toggle("seg__btn--on", k === mailLogStatus);
      const label = {
        all: "All",
        sent: "Sent",
        failed: "Failed",
        skipped: "Skipped",
      }[k];
      b.innerHTML = `${label} <span class="muted">${k === "all" ? cn.total || 0 : cn[k] || 0}</span>`;
    });
    const rows = d.logs || [];
    box.innerHTML = rows.length
      ? `<div class="table-wrap"><table class="data"><thead><tr><th>When</th><th>To</th><th>Subject</th><th>Type</th><th>Status</th></tr></thead>
        <tbody>${rows
          .map((r) => {
            const st =
              r.status === "sent"
                ? '<span class="pay pay--paid">sent</span>'
                : r.status === "failed"
                  ? `<span class="pay pay--unpaid" title="${esc(r.error || "")}">failed</span>`
                  : '<span class="pay pay--partial">skipped</span>';
            return `<tr><td class="muted">${fmtDate(r.created_at)}</td><td>${esc(r.to_email)}</td>
              <td><b>${esc(r.subject)}</b>${r.status === "failed" && r.error ? `<div class="muted">${esc(r.error)}</div>` : ""}</td>
              <td class="muted">${esc(MAIL_CTX[r.context] || r.context || "—")}</td><td>${st}</td></tr>`;
          })
          .join("")}</tbody></table></div>`
      : emptyBox("fa-envelope-open", "No emails logged yet.");
  } catch (e) {
    box.innerHTML = errBox(e.message);
  }
}
async function marketing() {
  try {
    const [ann, subs, tg, cpn] = await Promise.all([
      api("announcements.php?all=1"),
      api("admin/subscribers.php"),
      api("admin/email_targets.php"),
      api("admin/coupons.php"),
    ]);
    const a = ann.announcements || [];
    const s = subs.subscribers || [];
    const coupons = cpn.coupons || [];
    emailTargets = tg;
    const c = tg.counts || {};
    view.innerHTML = `
      <div class="card"><div class="card__head"><h2>Email Campaign</h2>
        <button class="btn btn--green btn--sm" id="compose-email"><i class="fa-solid fa-paper-plane"></i> Compose Email</button></div>
        <div class="card__body"><p class="muted" style="margin:0">Send offers, invoices notices or any custom message with image/PDF attachments. Reach <b>${c.retailers || 0}</b> retailers, <b>${c.partners || 0}</b> partners or <b>${c.subscribers || 0}</b> newsletter subscribers. A copy is BCC'd to your business inbox.</p></div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Discount Coupons</h2>
        <button class="btn btn--green btn--sm" id="new-coupon"><i class="fa-solid fa-ticket"></i> New Coupon</button></div>
        <div class="card__body">${
          coupons.length
            ? `<div class="table-wrap"><table class="data"><thead><tr><th>Code</th><th>Discount</th><th>Min Order</th><th>Used</th><th>Expires</th><th>Active</th><th></th></tr></thead>
          <tbody>${coupons
            .map(
              (x) =>
                `<tr><td><b>${esc(x.code)}</b><div class="muted">${esc(x.description || "")}</div></td>
                <td>${x.type === "percent" ? Number(x.value) + "%" + (x.max_discount ? " (max " + money(x.max_discount) + ")" : "") : money(x.value)}</td>
                <td class="muted">${x.min_order > 0 ? money(x.min_order) : "—"}</td>
                <td>${x.used_count}${x.usage_limit ? " / " + x.usage_limit : ""}</td>
                <td class="muted">${x.expires_at || "—"}</td><td>${badge(Number(x.is_active) ? "active" : "suspended")}</td>
                <td style="white-space:nowrap"><button class="btn btn--ghost btn--sm" data-edit-cpn='${encodeURIComponent(JSON.stringify(x))}'><i class="fa-solid fa-pen"></i></button>
                  <button class="btn btn--danger btn--sm" data-del-cpn="${x.id}"><i class="fa-solid fa-trash"></i></button></td></tr>`,
            )
            .join("")}</tbody></table></div>`
            : emptyBox(
                "fa-ticket",
                "No coupons yet. Create one to run a discount scheme.",
              )
        }</div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Announcements &amp; Offers</h2>
        <button class="btn btn--green btn--sm" id="new-ann"><i class="fa-solid fa-plus"></i> New Announcement</button></div>
        <div class="card__body">${
          a.length
            ? `<div class="table-wrap"><table class="data"><thead><tr><th>Title</th><th>Audience</th><th>Active</th><th>Window</th><th></th></tr></thead>
          <tbody>${a
            .map(
              (
                x,
              ) => `<tr><td><b>${esc(x.title)}</b><div class="muted">${esc((x.body || "").slice(0, 60))}</div></td><td>${esc(x.audience)}</td>
            <td>${badge(Number(x.is_active) ? "active" : "suspended")}</td><td class="muted">${x.starts_at || "—"} → ${x.ends_at || "—"}</td>
            <td><button class="btn btn--ghost btn--sm" data-edit-ann='${encodeURIComponent(JSON.stringify(x))}'><i class="fa-solid fa-pen"></i></button>
              <button class="btn btn--danger btn--sm" data-del-ann="${x.id}"><i class="fa-solid fa-trash"></i></button></td></tr>`,
            )
            .join("")}</tbody></table></div>`
            : emptyBox("fa-bullhorn", "No announcements yet.")
        }</div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Email Log</h2>
        <div style="display:flex;gap:.5rem;align-items:center"><div class="seg" id="mail-tabs">
          <button class="seg__btn" data-mail="all">All</button>
          <button class="seg__btn" data-mail="sent">Sent</button>
          <button class="seg__btn" data-mail="failed">Failed</button>
          <button class="seg__btn" data-mail="skipped">Skipped</button>
        </div><button class="btn btn--ghost btn--sm" id="mail-refresh"><i class="fa-solid fa-rotate"></i></button></div></div>
        <div class="card__body" id="mail-log"><div class="spinner"></div></div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Newsletter Subscribers</h2><span class="muted">${subs.total || 0} total</span></div>
        <div class="card__body">${
          s.length
            ? `<div class="table-wrap"><table class="data"><thead><tr><th>Email</th><th>Subscribed</th></tr></thead>
          <tbody>${s.map((x) => `<tr><td>${esc(x.email)}</td><td class="muted">${fmtDate(x.created_at)}</td></tr>`).join("")}</tbody></table></div>`
            : emptyBox("fa-envelope", "No subscribers yet.")
        }</div></div>`;
    document
      .getElementById("compose-email")
      .addEventListener("click", () => emailCompose());
    document
      .getElementById("new-coupon")
      .addEventListener("click", () => couponForm({}));
    view
      .querySelectorAll("[data-edit-cpn]")
      .forEach((b) =>
        b.addEventListener("click", () =>
          couponForm(JSON.parse(decodeURIComponent(b.dataset.editCpn))),
        ),
      );
    view.querySelectorAll("[data-del-cpn]").forEach((b) =>
      b.addEventListener("click", async () => {
        if (
          !(await uiConfirm("Delete this coupon? This can't be undone.", {
            title: "Delete coupon",
            confirmText: "Delete",
            danger: true,
          }))
        )
          return;
        try {
          await api("admin/coupon_delete.php", {
            method: "POST",
            body: { id: b.dataset.delCpn },
          });
          toast("Coupon deleted");
          marketing();
        } catch (e) {
          toast(e.message, "error");
        }
      }),
    );
    document.querySelectorAll("#mail-tabs .seg__btn").forEach((b) =>
      b.addEventListener("click", () => {
        mailLogStatus = b.dataset.mail;
        renderEmailLog();
      }),
    );
    document
      .getElementById("mail-refresh")
      .addEventListener("click", renderEmailLog);
    renderEmailLog();
    document
      .getElementById("new-ann")
      .addEventListener("click", () => annForm({}));
    view
      .querySelectorAll("[data-edit-ann]")
      .forEach((b) =>
        b.addEventListener("click", () =>
          annForm(JSON.parse(decodeURIComponent(b.dataset.editAnn))),
        ),
      );
    view.querySelectorAll("[data-del-ann]").forEach((b) =>
      b.addEventListener("click", async () => {
        if (
          !(await uiConfirm("Delete this announcement?", {
            title: "Delete announcement",
            confirmText: "Delete",
            danger: true,
          }))
        )
          return;
        try {
          await api("admin/announcement_delete.php", {
            method: "POST",
            body: { id: b.dataset.delAnn },
          });
          toast("Deleted");
          marketing();
        } catch (e) {
          toast(e.message, "error");
        }
      }),
    );
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
function emailCompose() {
  const c = (emailTargets && emailTargets.counts) || {};
  const stores = (emailTargets && emailTargets.stores) || [];
  const people = (emailTargets && emailTargets.people) || [];
  openModal(`<div class="modal__head"><h3>Compose Email</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label>Send to</label>
        <select class="select" id="em-aud">
          <option value="retailers">All Retailers (${c.retailers || 0})</option>
          <option value="partners">All Partners (${c.partners || 0})</option>
          <option value="all">All Retailers &amp; Partners (${(c.retailers || 0) + (c.partners || 0)})</option>
          <option value="subscribers">Newsletter Subscribers (${c.subscribers || 0})</option>
          <option value="user">A specific retailer / partner…</option>
          <option value="store">A specific store…</option>
          <option value="custom">Custom email address…</option>
        </select></div>
      <div class="field" id="em-store-wrap" style="display:none"><label>Store</label>
        <select class="select" id="em-store">${stores.map((s) => `<option value="${s.id}">${esc(s.name)} — ${esc(s.role)} (${esc(s.email)})</option>`).join("")}</select></div>
      <div class="field" id="em-user-wrap" style="display:none"><label>Person</label>
        <select class="select" id="em-user">${people.map((p) => `<option value="${p.id}">${esc(p.role === "partner" ? "Partner" : "Retailer")}: ${esc(p.name)} (${esc(p.email)})</option>`).join("")}</select></div>
      <div class="field" id="em-custom-wrap" style="display:none"><label>Email address(es) <span class="muted" style="font-weight:400">— separate multiple with commas</span></label>
        <input class="input" id="em-custom" placeholder="someone@example.com, another@example.com"></div>
      <div class="field"><label>Subject</label><input class="input" id="em-subject" maxlength="200" placeholder="e.g. Monsoon offer — 5% extra stock"></div>
      <div class="field"><label>Message</label><textarea id="em-message" rows="7" placeholder="Write your message… line breaks are kept."></textarea></div>
      <div class="field"><label>Attachments <span class="muted" style="font-weight:400">(images / PDF, up to 6)</span></label><input type="file" id="em-files" class="file-input" multiple accept="image/*,application/pdf"></div>
      <p class="muted" style="font-size:.8rem">A copy is automatically BCC'd to your business inbox. Recipients don't see each other.</p>
    </div>
    <div class="modal__foot"><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="em-send"><i class="fa-solid fa-paper-plane"></i> Send</button></div>`);
  const aud = document.getElementById("em-aud");
  aud.addEventListener("change", () => {
    document.getElementById("em-store-wrap").style.display =
      aud.value === "store" ? "" : "none";
    document.getElementById("em-user-wrap").style.display =
      aud.value === "user" ? "" : "none";
    document.getElementById("em-custom-wrap").style.display =
      aud.value === "custom" ? "" : "none";
  });
  document.getElementById("em-send").addEventListener("click", async () => {
    const subject = v("em-subject");
    const message = v("em-message");
    if (!subject || !message) {
      toast("Subject and message are required", "error");
      return;
    }
    const fd = new FormData();
    fd.append("audience", aud.value);
    fd.append("subject", subject);
    fd.append("message", message);
    if (aud.value === "store")
      fd.append("store_id", document.getElementById("em-store").value);
    if (aud.value === "user")
      fd.append("user_id", document.getElementById("em-user").value);
    if (aud.value === "custom") {
      const ce = v("em-custom");
      if (!ce) {
        toast("Enter at least one email address", "error");
        return;
      }
      fd.append("custom_emails", ce);
    }
    const files = document.getElementById("em-files").files;
    for (let i = 0; i < files.length; i++) fd.append("attachments[]", files[i]);
    const btn = document.getElementById("em-send");
    btn.disabled = true;
    btn.innerHTML = "Sending…";
    try {
      const r = await apiForm("admin/email_send.php", fd);
      toast(r.message);
      closeModal();
    } catch (e) {
      toast(e.message, "error");
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send';
    }
  });
}
function couponForm(x) {
  openModal(`<div class="modal__head"><h3>${x.id ? "Edit" : "New"} Coupon</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label>Code</label><input class="input" id="c-code" style="text-transform:uppercase" value="${esc(x.code || "")}" placeholder="e.g. WELCOME10"></div>
      <div class="field"><label>Description</label><input class="input" id="c-desc" value="${esc(x.description || "")}" placeholder="Shown internally"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
        <div class="field"><label>Type</label><select class="select" id="c-type"><option value="percent">Percentage %</option><option value="flat">Flat ₹</option></select></div>
        <div class="field"><label>Value</label><input class="input" id="c-value" type="number" step="0.01" value="${esc(x.value || "")}"></div>
        <div class="field"><label>Min Order (₹)</label><input class="input" id="c-min" type="number" step="0.01" value="${esc(x.min_order || 0)}"></div>
        <div class="field"><label>Max Discount (₹) <span class="muted" style="font-weight:400">(percent only)</span></label><input class="input" id="c-max" type="number" step="0.01" value="${esc(x.max_discount || "")}"></div>
        <div class="field"><label>Usage Limit</label><input class="input" id="c-limit" type="number" value="${esc(x.usage_limit || "")}" placeholder="Unlimited"></div>
        <div class="field"><label>Expires</label><input class="input" id="c-exp" type="date" value="${x.expires_at || ""}"></div>
      </div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.88rem"><input type="checkbox" id="c-active" ${x.id ? (Number(x.is_active) ? "checked" : "") : "checked"}> Active</label>
    </div>
    <div class="modal__foot"><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="c-save">Save</button></div>`);
  if (x.type) document.getElementById("c-type").value = x.type;
  document.getElementById("c-save").addEventListener("click", async () => {
    try {
      const r = await api("admin/coupon_save.php", {
        method: "POST",
        body: {
          id: x.id || 0,
          code: v("c-code"),
          description: v("c-desc"),
          type: document.getElementById("c-type").value,
          value: v("c-value"),
          min_order: v("c-min"),
          max_discount: v("c-max"),
          usage_limit: v("c-limit"),
          expires_at: v("c-exp"),
          is_active: document.getElementById("c-active").checked ? 1 : 0,
        },
      });
      toast(r.message);
      closeModal();
      marketing();
    } catch (e) {
      toast(e.message, "error");
    }
  });
}
function annForm(x) {
  openModal(`<div class="modal__head"><h3>${x.id ? "Edit" : "New"} Announcement</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label>Title</label><input class="input" id="an-title" value="${esc(x.title || "")}"></div>
      <div class="field"><label>Message</label><textarea id="an-body" rows="3">${esc(x.body || "")}</textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem">
        <div class="field"><label>Audience</label><select class="select" id="an-aud"><option value="all">Everyone</option><option value="retailer">Retailers</option><option value="partner">Partners</option></select></div>
        <div class="field"><label>Active</label><select class="select" id="an-active"><option value="1">Yes</option><option value="0">No</option></select></div>
        <div class="field"><label>Start date</label><input class="input" type="date" id="an-start" value="${x.starts_at || ""}"></div>
        <div class="field"><label>End date</label><input class="input" type="date" id="an-end" value="${x.ends_at || ""}"></div>
      </div>
    </div>
    <div class="modal__foot"><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="an-save">Save</button></div>`);
  if (x.audience) document.getElementById("an-aud").value = x.audience;
  document.getElementById("an-active").value = x.id
    ? Number(x.is_active)
      ? "1"
      : "0"
    : "1";
  document.getElementById("an-save").addEventListener("click", async () => {
    const body = {
      id: x.id || 0,
      title: v("an-title"),
      body: v("an-body"),
      audience: document.getElementById("an-aud").value,
      is_active: document.getElementById("an-active").value,
      starts_at: v("an-start"),
      ends_at: v("an-end"),
    };
    try {
      const r = await api("admin/announcement_save.php", {
        method: "POST",
        body,
      });
      toast(r.message);
      closeModal();
      marketing();
    } catch (e) {
      toast(e.message, "error");
    }
  });
}

(async function () {
  const user = await bootstrap("admin");
  if (!user) return;
  initShell();
  setView("dashboard");
})();

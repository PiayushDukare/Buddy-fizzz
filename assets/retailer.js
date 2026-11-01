const view = document.getElementById("view");
const pageTitle = document.getElementById("page-title");
const modalBack = document.getElementById("modal-back");
const modal = document.getElementById("modal");
let cart = {};
let productCache = [];
let availMap = {};
let appliedCoupon = null;
let couponDiscount = 0;
let walletInfo = {
  balance: 0,
  credit_allowed: 0,
  credit_limit: 0,
  outstanding: 0,
};
let payChoice = "";

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

const views = {
  dashboard,
  order: placeOrder,
  orders: myOrders,
  wallet: walletView,
  stock: myStock,
  support,
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
  const live = { dashboard, orders: myOrders, support }[name];
  if (live) startPolling(live);
}
document.querySelectorAll("#nav a").forEach((a) =>
  a.addEventListener("click", (e) => {
    e.preventDefault();
    setView(a.dataset.view);
    document.querySelector(".sidebar").classList.remove("open");
  }),
);

async function dashboard() {
  try {
    const [o, st] = await Promise.all([
      api("orders/list.php"),
      api("stock.php"),
    ]);
    const orders = o.orders || [];
    const pending = orders.filter((x) =>
      ["pending", "approved", "processing", "dispatched"].includes(x.status),
    ).length;
    const units = (st.stock || []).reduce(
      (s, r) => s + Number(r.quantity || 0),
      0,
    );
    const dues = orders
      .filter(
        (x) =>
          x.payment_status !== "paid" &&
          !["cancelled", "rejected"].includes(x.status),
      )
      .reduce((s, x) => s + (Number(x.total) - Number(x.amount_paid || 0)), 0);
    view.innerHTML = `
      <div class="stat-row">
        ${stat("Total Orders", orders.length, "fa-box")}
        ${stat("In Progress", pending, "fa-truck-fast")}
        ${stat("Stock Units", units, "fa-layer-group")}
        ${stat("Outstanding Dues", money(dues), "fa-indian-rupee-sign")}
      </div>
      <div class="card"><div class="card__head"><h2>Recent Orders</h2>
        <button class="btn btn--green btn--sm" onclick="setView('order')"><i class="fa-solid fa-cart-plus"></i> Place Order</button></div>
        <div class="card__body">${orders.length ? ordersTable(orders.slice(0, 6)) : '<div class="empty"><i class="fa-solid fa-box-open"></i><p>No orders yet. Place your first order!</p></div>'}</div>
      </div>`;
    view.insertAdjacentHTML("afterbegin", await announcementBanner());
    bindOrderRows();
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

async function placeOrder() {
  try {
    const [data, av, w] = await Promise.all([
      api("products.php"),
      api("availability.php"),
      api("wallet.php"),
    ]);
    productCache = data.products || [];
    availMap = av.availability || {};
    walletInfo = w;
    payChoice = "";
    renderCatalog();
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

function renderCatalog() {
  const items = productCache
    .map((p) => {
      const avail = Number(availMap[p.id] ?? 0);
      const out = avail <= 0;
      if (out && cart[p.id]) delete cart[p.id];
      const q = cart[p.id] || 0;
      return `<div class="prod-row${out ? " prod-row--out" : ""}">
      <div class="prod-row__img">${p.image ? `<img src="../${esc(p.image)}" alt="${esc(p.name)}" loading="lazy" onerror="this.parentNode.innerHTML='<i class=\\'fa-solid fa-bottle-water\\'></i>'">` : '<i class="fa-solid fa-bottle-water"></i>'}</div>
      <div class="prod-row__info"><b>${esc(p.name)}</b><div class="muted">${esc(p.size || "")} · ${money(p.price)}</div>
        ${out ? '<div class="stock-tag stock-tag--out"><i class="fa-solid fa-circle-xmark"></i> Out of stock</div>' : avail <= 20 ? `<div class="stock-tag stock-tag--low">Only ${avail} left</div>` : `<div class="stock-tag">${avail} in stock</div>`}</div>
      ${
        out
          ? '<span class="muted" style="font-size:.8rem">Unavailable</span>'
          : `<div class="qty-ctl">
        <button type="button" class="qty-btn" data-dec="${p.id}">−</button>
        <input type="number" class="qty-in" id="q-${p.id}" data-qid="${p.id}" min="0" max="${avail}" step="1" inputmode="numeric" value="${q}">
        <button type="button" class="qty-btn" data-inc="${p.id}">+</button>
      </div>`
      }</div>`;
    })
    .join("");

  view.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.7rem">
      <div>${
        Number(walletInfo.outstanding) > 0 && walletInfo.razorpay
          ? `<button class="btn btn--lime btn--sm" id="paydues-cat"><i class="fa-solid fa-money-bill-wave"></i> Pay ${money(walletInfo.outstanding)} dues</button>`
          : ""
      }</div>
      <div>
        <button class="btn btn--ghost btn--sm" id="bulk-btn"><i class="fa-solid fa-file-csv"></i> Bulk upload (CSV)</button>
        <input type="file" id="bulk-file" accept=".csv,text/csv" style="display:none">
      </div>
    </div>
    <div class="order-layout">
      <div class="catalog">${items || errBox("No products available")}</div>
      <div class="card cart-panel">
        <div class="card__head"><h2>Your Order</h2></div>
        <div class="card__body" id="cart-body"></div>
      </div>
    </div>`;
  const payduesCat = document.getElementById("paydues-cat");
  if (payduesCat) payduesCat.addEventListener("click", () => payDues(placeOrder));
  const bulkBtn = document.getElementById("bulk-btn");
  const bulkFile = document.getElementById("bulk-file");
  bulkBtn.addEventListener("click", () => bulkFile.click());
  bulkFile.addEventListener("change", () => {
    const f = bulkFile.files[0];
    if (!f) return;
    const reader = new FileReader();
    reader.onload = () => {
      bulkAddFromCsv(String(reader.result));
      bulkFile.value = "";
    };
    reader.readAsText(f);
  });
  const capOf = (id) => Number(availMap[id] ?? 0);
  view.querySelectorAll("[data-inc]").forEach((b) =>
    b.addEventListener("click", () => {
      const id = b.dataset.inc;
      const next = (cart[id] || 0) + 1;
      if (next > capOf(id)) {
        toast("Only " + capOf(id) + " in stock", "error");
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
        toast("Only " + capOf(id) + " in stock", "error");
      }
      if (n) cart[id] = n;
      else delete cart[id];
      syncCartBody();
    }),
  );
  syncCart();
}

function bulkAddFromCsv(text) {
  const bySku = {};
  productCache.forEach((p) => (bySku[String(p.sku).toUpperCase()] = p));
  let added = 0;
  let skipped = 0;
  let capped = 0;
  text.split(/\r?\n/).forEach((line) => {
    const parts = line.split(/[,;\t]/).map((x) => x.trim());
    if (parts.length < 2) return;
    const sku = parts[0].toUpperCase();
    const qty = Math.floor(Number(parts[1]) || 0);
    if (sku === "SKU" || !sku || qty <= 0) return;
    const p = bySku[sku];
    if (!p) {
      skipped++;
      return;
    }
    const avail = Number(availMap[p.id] ?? 0);
    let q = qty;
    if (q > avail) {
      q = avail;
      capped++;
    }
    if (q > 0) {
      cart[p.id] = q;
      added++;
    } else {
      skipped++;
    }
  });
  renderCatalog();
  toast(
    `Added ${added} item(s)${capped ? ", " + capped + " capped to stock" : ""}${skipped ? ", " + skipped + " skipped" : ""}`,
    skipped && !added ? "error" : "ok",
  );
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
  const lines = Object.keys(cart).map((id) => {
    const p = productCache.find((x) => x.id == id);
    return { p, q: cart[id] };
  });
  if (!lines.length) {
    body.innerHTML =
      '<div class="empty" style="padding:1.5rem"><i class="fa-solid fa-cart-shopping"></i><p>Cart is empty</p></div>';
    return;
  }
  const total = lines.reduce((s, l) => s + l.p.price * l.q, 0);
  if (appliedCoupon && couponDiscount > total) couponDiscount = total;
  const net = Math.max(0, total - (appliedCoupon ? couponDiscount : 0));
  const notesEl = document.getElementById("order-notes");
  const notes = notesEl ? notesEl.value : "";
  const dateEl = document.getElementById("order-date");
  const orderDate = dateEl ? dateEl.value : "";
  const couponUI = appliedCoupon
    ? `<div style="display:flex;justify-content:space-between;align-items:center;font-size:.86rem;margin-bottom:.5rem;color:var(--green)"><span><i class="fa-solid fa-ticket"></i> ${esc(appliedCoupon)} <a id="remove-coupon" style="color:var(--danger);cursor:pointer;font-size:.78rem">remove</a></span><b>− ${money(couponDiscount)}</b></div>`
    : `<div style="display:flex;gap:.4rem;margin-bottom:.7rem"><input class="input" id="coupon-code" placeholder="Coupon code" style="flex:1;text-transform:uppercase"><button class="btn btn--ghost btn--sm" id="apply-coupon">Apply</button></div><div class="muted" id="coupon-msg" style="font-size:.75rem;margin-bottom:.5rem"></div>`;
  const taxRate = (Number(window.__tax.gst) + Number(window.__tax.cess)) / 100;
  const taxAmt = net * taxRate;
  const gross = net + taxAmt;
  const methods = [];
  if (Number(walletInfo.balance) + 0.001 >= gross && gross > 0)
    methods.push({
      v: "wallet",
      icon: "fa-wallet",
      label: `Pay from Wallet (${money(walletInfo.balance)})`,
    });
  if (window.__pay && window.__pay.razorpay)
    methods.push({
      v: "online",
      icon: "fa-credit-card",
      label: "Pay Online (UPI / Card)",
    });
  if (Number(walletInfo.credit_allowed))
    methods.push({
      v: "credit",
      icon: "fa-hourglass-half",
      label: "Pay Later (on credit)",
    });
  if (!payChoice || !methods.some((m) => m.v === payChoice))
    payChoice = methods.length ? methods[0].v : "";
  const payMethodUI = methods.length
    ? `<div style="margin-bottom:.8rem"><div style="font-size:.78rem;font-weight:600;color:var(--heading);margin-bottom:.35rem">Payment</div>${methods.map((m) => `<label style="display:flex;align-items:center;gap:.5rem;padding:.5rem .6rem;border:1px solid ${payChoice === m.v ? "var(--green)" : "var(--line)"};border-radius:.5rem;margin-bottom:.4rem;cursor:pointer;font-size:.86rem"><input type="radio" name="paychoice" value="${m.v}" ${payChoice === m.v ? "checked" : ""}><i class="fa-solid ${m.icon}" style="color:var(--green)"></i> ${m.label}</label>`).join("")}</div>`
    : "";
  const submitLabel =
    payChoice === "online"
      ? `<i class="fa-solid fa-credit-card"></i> Pay ${money(gross)} & Place`
      : payChoice === "wallet"
        ? `<i class="fa-solid fa-wallet"></i> Pay ${money(gross)} from Wallet`
        : payChoice === "credit"
          ? `<i class="fa-solid fa-hourglass-half"></i> Place Order (Pay Later)`
          : `<i class="fa-solid fa-check"></i> Place Order`;
  body.innerHTML = `${lines.map((l) => `<div style="display:flex;justify-content:space-between;font-size:.86rem;margin-bottom:.5rem"><span>${esc(l.p.name)} × ${l.q}</span><b>${money(l.p.price * l.q)}</b></div>`).join("")}
    <hr style="border:none;border-top:1px solid var(--line);margin:.6rem 0">
    <div style="display:flex;justify-content:space-between;font-size:.86rem;margin-bottom:.5rem"><span class="muted">Subtotal</span><span>${money(total)}</span></div>
    ${couponUI}
    <div style="display:flex;justify-content:space-between;font-size:.86rem;margin-bottom:.3rem"><span class="muted">GST + cess (${(Number(window.__tax.gst) + Number(window.__tax.cess)).toFixed(0)}%)</span><span>${money(taxAmt)}</span></div>
    <div style="display:flex;justify-content:space-between;font-size:1rem;margin-bottom:1rem"><b>Total payable</b><b style="color:var(--heading)">${money(gross)}</b></div>
    <label style="font-size:.78rem;font-weight:600;color:var(--heading);display:block;margin-bottom:.25rem">Preferred delivery date (optional)</label>
    <input type="date" id="order-date" min="${new Date().toISOString().slice(0, 10)}" value="${orderDate}" class="input" style="margin-bottom:.6rem">
    <textarea id="order-notes" placeholder="Delivery notes (optional)" rows="2" style="width:100%;border:1px solid var(--line);border-radius:.5rem;padding:.5rem;margin-bottom:.7rem;font-family:inherit">${esc(notes)}</textarea>
    ${payMethodUI}
    <button class="btn btn--green" style="width:100%;justify-content:center" id="submit-order">${submitLabel}</button>`;
  document
    .getElementById("submit-order")
    .addEventListener("click", submitOrder);
  const payRadios = body.querySelectorAll("[name='paychoice']");
  payRadios.forEach((r) =>
    r.addEventListener("change", () => {
      payChoice = r.value;
      syncCartBody();
    }),
  );
  const applyBtn = document.getElementById("apply-coupon");
  if (applyBtn) applyBtn.addEventListener("click", applyCoupon);
  const removeBtn = document.getElementById("remove-coupon");
  if (removeBtn)
    removeBtn.addEventListener("click", () => {
      appliedCoupon = null;
      couponDiscount = 0;
      syncCartBody();
    });
}

async function applyCoupon() {
  const codeEl = document.getElementById("coupon-code");
  const msg = document.getElementById("coupon-msg");
  const code = (codeEl.value || "").trim().toUpperCase();
  if (!code) return;
  const items = Object.keys(cart).map((id) => ({
    product_id: Number(id),
    qty: cart[id],
  }));
  try {
    const r = await api("coupon_check.php", {
      method: "POST",
      body: { code, items },
    });
    appliedCoupon = r.code;
    couponDiscount = Number(r.discount) || 0;
    toast("Coupon applied — you save " + money(couponDiscount));
    syncCartBody();
  } catch (e) {
    if (msg) msg.textContent = e.message;
    msg && (msg.style.color = "var(--danger)");
  }
}

function payMode() {
  return !!(window.__pay && window.__pay.razorpay);
}

async function submitOrder() {
  const items = Object.keys(cart).map((id) => ({
    product_id: Number(id),
    qty: cart[id],
  }));
  if (!items.length) return;
  const notes = document.getElementById("order-notes").value.trim();
  const dateInput = document.getElementById("order-date");
  const delivery_date = dateInput ? dateInput.value : "";
  const coupon_code = appliedCoupon || "";
  const done = () => {
    cart = {};
    appliedCoupon = null;
    couponDiscount = 0;
    payChoice = "";
    setView("orders");
  };

  if (payChoice === "online") {
    payForCart({ items, notes, coupon_code, delivery_date }, done);
    return;
  }

  const pay_method =
    payChoice === "wallet" ? "wallet" : payChoice === "credit" ? "credit" : "";
  const btn = document.getElementById("submit-order");
  btn.disabled = true;
  btn.textContent = "Placing…";
  try {
    const res = await api("orders/create.php", {
      method: "POST",
      body: { items, notes, coupon_code, delivery_date, pay_method },
    });
    toast(res.message);
    done();
  } catch (e) {
    toast(e.message, "error");
    btn.disabled = false;
    syncCartBody();
  }
}

const WTX = {
  topup: "Top-up",
  order: "Order payment",
  refund: "Refund",
  adjustment: "Adjustment",
};
async function walletView() {
  try {
    const w = await api("wallet.php");
    walletInfo = w;
    const tx = w.transactions || [];
    view.innerHTML = `
      <div class="card"><div class="card__head"><h2>My Wallet</h2>
        ${w.razorpay ? '<button class="btn btn--green btn--sm" id="topup-btn"><i class="fa-solid fa-plus"></i> Add Money</button>' : ""}</div>
        <div class="card__body">
          <div class="stat-row" style="grid-template-columns:repeat(3,1fr)">
            ${stat("Wallet Balance", money(w.balance), "fa-wallet")}
            ${w.credit_allowed ? stat("Credit Limit", w.credit_limit > 0 ? money(w.credit_limit) : "Unlimited", "fa-hand-holding-dollar") : stat("Pay Later", "Not enabled", "fa-hand-holding-dollar")}
            ${stat("Outstanding Dues", money(w.outstanding), "fa-hourglass-half")}
          </div>
          ${
            Number(w.outstanding) > 0 && w.razorpay
              ? `<div style="margin-top:.6rem"><button class="btn btn--lime btn--sm" id="paydues-btn"><i class="fa-solid fa-money-bill-wave"></i> Pay ${money(w.outstanding)} dues now</button></div>`
              : ""
          }
          <p class="muted" style="margin:.6rem 0 0">Use your wallet balance to pay for orders instantly.${w.credit_allowed ? " Your store can also place orders on <b>pay-later</b> credit." : ""}${Number(w.outstanding) > 0 && w.razorpay ? " Paying dues settles your oldest unpaid orders first." : ""}</p>
        </div></div>
      <div class="card" style="margin-top:1.2rem"><div class="card__head"><h2>Transactions</h2></div><div class="card__body">
        ${
          tx.length
            ? `<div class="table-wrap"><table class="data"><thead><tr><th>Date</th><th>Type</th><th>Note</th><th>Amount</th><th>Balance</th></tr></thead>
          <tbody>${tx
            .map(
              (t) =>
                `<tr><td class="muted">${fmtDate(t.created_at)}</td><td>${esc(WTX[t.type] || t.type)}${t.order_id ? " #" + t.order_id : ""}</td><td class="muted">${esc(t.note || "—")}</td>
                <td><b style="color:${Number(t.amount) >= 0 ? "var(--green)" : "var(--danger)"}">${Number(t.amount) >= 0 ? "+" : ""}${money(t.amount)}</b></td><td>${money(t.balance_after)}</td></tr>`,
            )
            .join("")}</tbody></table></div>`
            : emptyBox("fa-receipt", "No wallet transactions yet.")
        }</div></div>`;
    const tb = document.getElementById("topup-btn");
    if (tb) tb.addEventListener("click", topUpWallet);
    const pd = document.getElementById("paydues-btn");
    if (pd) pd.addEventListener("click", () => payDues(walletView));
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}
async function payDues(onDone) {
  if (typeof Razorpay === "undefined") {
    toast("Payment library not loaded. Refresh and try again.", "error");
    return;
  }
  let d;
  try {
    d = await api("pay/dues_create.php", { method: "POST", body: {} });
  } catch (e) {
    toast(e.message, "error");
    return;
  }
  const rzp = new Razorpay({
    key: d.key_id,
    amount: d.amount,
    currency: d.currency,
    name: d.name,
    description: d.description,
    order_id: d.razorpay_order_id,
    prefill: d.prefill || {},
    theme: { color: "#2e7d32" },
    handler: async (resp) => {
      try {
        const v = await api("pay/dues_confirm.php", {
          method: "POST",
          body: {
            razorpay_payment_id: resp.razorpay_payment_id,
            razorpay_order_id: resp.razorpay_order_id,
            razorpay_signature: resp.razorpay_signature,
          },
        });
        toast(v.message);
        if (typeof onDone === "function") onDone();
      } catch (e) {
        toast(e.message, "error");
      }
    },
  });
  rzp.on("payment.failed", (r) =>
    toast((r.error && r.error.description) || "Payment failed", "error"),
  );
  rzp.open();
}
async function topUpWallet() {
  const amt = await uiPrompt("Enter the amount you'd like to add to your wallet.", {
    title: "Add money to wallet",
    type: "number",
    prefix: "₹",
    min: 1,
    step: 1,
    placeholder: "e.g. 5000",
    confirmText: "Continue to pay",
    validate: (v) =>
      Math.floor(Number(v) || 0) >= 1 ? "" : "Enter an amount of ₹1 or more.",
  });
  if (amt === null) return;
  const amount = Math.floor(Number(amt) || 0);
  if (!amount || amount < 1) return;
  if (typeof Razorpay === "undefined") {
    toast("Payment library not loaded. Refresh and try again.", "error");
    return;
  }
  let d;
  try {
    d = await api("pay/wallet_checkout.php", {
      method: "POST",
      body: { amount },
    });
  } catch (e) {
    toast(e.message, "error");
    return;
  }
  const rzp = new Razorpay({
    key: d.key_id,
    amount: d.amount,
    currency: d.currency,
    name: d.name,
    description: d.description,
    order_id: d.razorpay_order_id,
    prefill: d.prefill || {},
    theme: { color: "#2e7d32" },
    handler: async (resp) => {
      try {
        const v = await api("pay/wallet_confirm.php", {
          method: "POST",
          body: {
            razorpay_payment_id: resp.razorpay_payment_id,
            razorpay_order_id: resp.razorpay_order_id,
            razorpay_signature: resp.razorpay_signature,
          },
        });
        toast(v.message);
        walletView();
      } catch (e) {
        toast(e.message, "error");
      }
    },
  });
  rzp.on("payment.failed", (r) =>
    toast((r.error && r.error.description) || "Payment failed", "error"),
  );
  rzp.open();
}
async function myOrders() {
  try {
    const data = await api("orders/list.php");
    const orders = data.orders || [];
    view.innerHTML = `<div class="card"><div class="card__head"><h2>My Orders</h2>
      <a class="btn btn--ghost btn--sm" href="../api/statement.php" target="_blank"><i class="fa-solid fa-file-lines"></i> Account Statement</a></div>
      <div class="card__body">${orders.length ? ordersTable(orders) : '<div class="empty"><i class="fa-solid fa-box-open"></i><p>No orders yet.</p></div>'}</div></div>`;
    bindOrderRows();
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

function ordersTable(orders) {
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Order</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>${orders
      .map(
        (o) => `<tr>
      <td><b>#${o.id}</b></td><td>${o.item_count}</td><td>${money(o.total)}</td>
      <td><span class="pay pay--${o.payment_status || "unpaid"}">${esc(o.payment_status || "unpaid")}</span></td><td>${badge(o.status)}</td>
      <td class="muted">${fmtDate(o.created_at)}</td>
      <td><button class="btn btn--ghost btn--sm" data-order="${o.id}"><i class="fa-solid fa-eye"></i></button></td>
    </tr>`,
      )
      .join("")}</tbody></table></div>`;
}
function bindOrderRows() {
  view
    .querySelectorAll("[data-order]")
    .forEach((b) =>
      b.addEventListener("click", () => openOrder(b.dataset.order)),
    );
}

async function openOrder(id) {
  try {
    const data = await api(`orders/detail.php?id=${id}`);
    const o = data.order;
    const items = data.items || [];
    const canCancel = o.status === "pending";
    const balance = Number(o.total) - Number(o.amount_paid || 0);
    const canPay =
      window.__pay &&
      window.__pay.razorpay &&
      o.payment_status !== "paid" &&
      !["cancelled", "rejected"].includes(o.status) &&
      balance > 0;
    openModal(`<div class="modal__head"><h3>Order #${o.id}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
      <div class="modal__body">
        <div style="margin-bottom:1rem">${badge(o.status)} <span class="muted">${fmtDate(o.created_at)}</span></div>
        <table class="data" style="width:100%"><thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
          <tbody>${items.map((i) => `<tr><td><div class="item-cell">${itemThumb(i.image)}<span>${esc(i.name)} <span class="muted">${esc(i.size || "")}</span></span></div></td><td>${i.qty}</td><td>${money(i.unit_price)}</td><td>${money(i.line_total)}</td></tr>`).join("")}</tbody></table>
        <div style="text-align:right;margin-top:.8rem;font-size:1.05rem"><b>Total: ${money(o.total)}</b></div>
        <div style="margin-top:.5rem">Payment: <span class="pay pay--${o.payment_status || "unpaid"}">${esc(o.payment_status || "unpaid")}</span> <span class="muted">— ${money(o.amount_paid)} of ${money(o.total)}</span></div>
        ${canPay ? `<div style="margin-top:.5rem;color:var(--heading);font-weight:600">Balance due: ${money(balance)}</div>` : ""}
        ${o.delivery_date ? `<div style="margin-top:.5rem"><i class="fa-solid fa-truck-fast" style="color:var(--green)"></i> Delivery scheduled: <b>${fmtDate(o.delivery_date)}</b></div>` : ""}
        ${o.notes ? `<p class="muted" style="margin-top:.6rem">Note: ${esc(o.notes)}</p>` : ""}
      </div>
      <div class="modal__foot"><a class="btn btn--ghost" href="../api/invoice.php?id=${o.id}" target="_blank"><i class="fa-solid fa-file-invoice"></i> Invoice</a><button class="btn btn--lime" id="reorder-btn"><i class="fa-solid fa-rotate-right"></i> Reorder</button>${canCancel ? '<button class="btn btn--danger" id="cancel-order">Cancel Order</button>' : ""}${canPay ? `<button class="btn btn--green" id="pay-order"><i class="fa-solid fa-credit-card"></i> Pay ${money(balance)}</button>` : ""}<button class="btn btn--ghost" onclick="closeModal()">Close</button></div>`);
    document.getElementById("reorder-btn").addEventListener("click", () => {
      cart = {};
      appliedCoupon = null;
      couponDiscount = 0;
      items.forEach((i) => {
        if (Number(i.is_active) && i.product_id)
          cart[i.product_id] = Number(i.qty);
      });
      closeModal();
      toast("Items added to your cart");
      setView("order");
    });
    const payBtn = document.getElementById("pay-order");
    if (payBtn)
      payBtn.addEventListener("click", () =>
        payOrder(o.id, () => {
          closeModal();
          myOrders();
        }),
      );
    if (canCancel)
      document
        .getElementById("cancel-order")
        .addEventListener("click", async () => {
          try {
            await api("orders/status.php", {
              method: "POST",
              body: { id: o.id, status: "cancelled" },
            });
            toast("Order cancelled");
            closeModal();
            myOrders();
          } catch (e) {
            toast(e.message, "error");
          }
        });
  } catch (e) {
    toast(e.message, "error");
  }
}

async function myStock() {
  try {
    const data = await api("stock.php");
    const rows = data.stock || [];
    view.innerHTML = `<div class="card"><div class="card__head"><h2>My Stock</h2><span class="muted">Updated on delivery</span></div>
      <div class="card__body"><div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>SKU</th><th>Quantity</th><th>Updated</th></tr></thead>
      <tbody>${rows
        .map(
          (
            r,
          ) => `<tr><td><b>${esc(r.name)}</b> <span class="muted">${esc(r.size || "")}</span></td><td class="muted">${esc(r.sku)}</td>
        <td><b style="color:${r.quantity > 0 ? "var(--green)" : "var(--danger)"}">${r.quantity}</b></td><td class="muted">${r.updated_at ? fmtDate(r.updated_at) : "—"}</td></tr>`,
        )
        .join("")}</tbody></table></div></div></div>`;
  } catch (e) {
    view.innerHTML = errBox(e.message);
  }
}

async function support() {
  try {
    const data = await api("tickets/list.php");
    const t = data.tickets || [];
    view.innerHTML = `<div class="card"><div class="card__head"><h2>Support Tickets</h2>
      <button class="btn btn--green btn--sm" id="new-ticket"><i class="fa-solid fa-plus"></i> New Ticket</button></div>
      <div class="card__body">${t.length ? ticketTable(t) : '<div class="empty"><i class="fa-solid fa-headset"></i><p>No tickets yet.</p></div>'}</div></div>`;
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

function ticketTable(t) {
  return `<div class="table-wrap"><table class="data"><thead><tr><th>Subject</th><th>Priority</th><th>Status</th><th>Updated</th><th></th></tr></thead>
    <tbody>${t
      .map(
        (
          x,
        ) => `<tr><td><b>${esc(x.subject)}</b><div class="muted">${esc(x.category)}</div></td><td>${esc(x.priority)}</td>
      <td>${badge(x.status)}</td><td class="muted">${fmtDate(x.updated_at)}</td>
      <td><button class="btn btn--ghost btn--sm" data-ticket="${x.id}"><i class="fa-solid fa-comments"></i></button></td></tr>`,
      )
      .join("")}</tbody></table></div>`;
}

function newTicket() {
  openModal(`<div class="modal__head"><h3>New Support Ticket</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label>Subject</label><input class="input" id="t-subject" placeholder="Brief summary"></div>
      <div class="field"><label>Category</label><select class="select" id="t-category"><option>general</option><option>order</option><option>delivery</option><option>payment</option><option>stock</option></select></div>
      <div class="field"><label>Priority</label><select class="select" id="t-priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
      <div class="field"><label>Message</label><textarea id="t-message" rows="4" placeholder="Describe your issue…"></textarea></div>
      <div class="field"><label>Attachment (optional)</label><input type="file" id="t-file" class="file-input" /></div>
    </div>
    <div class="modal__foot"><button class="btn btn--ghost" onclick="closeModal()">Cancel</button><button class="btn btn--green" id="t-submit">Create Ticket</button></div>`);
  document.getElementById("t-submit").addEventListener("click", async () => {
    const fd = new FormData();
    fd.append("subject", document.getElementById("t-subject").value.trim());
    fd.append("category", document.getElementById("t-category").value);
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
    const data = await api(`tickets/thread.php?id=${id}`);
    const t = data.ticket;
    openModal(`<div class="modal__head"><h3>${esc(t.subject)}</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
      <div class="modal__body">
        <div style="margin-bottom:.8rem"><span id="t-status">${badge(t.status)}</span> <span class="muted">${esc(t.category)} · ${esc(t.priority)}</span></div>
        <div id="thread" style="display:flex;flex-direction:column;gap:.7rem;max-height:40vh;overflow:auto"></div>
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
    threadEl.innerHTML = msgs.map((m) => msgBubble(m)).join("");
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
    area.innerHTML =
      '<p class="muted" style="margin-top:1rem">This ticket is closed.</p>';
  } else if (!closed && !hasReply) {
    area.dataset.closed = "";
    area.innerHTML = `<div style="margin-top:1rem"><div style="display:flex;gap:.5rem"><input class="input" id="reply-msg" placeholder="Type a reply…" style="flex:1"><button class="btn btn--green" id="reply-send">Send</button></div>
      <input type="file" id="reply-file" class="file-input" /></div>`;
    wireReply(t.id);
  }
}

function wireReply(id) {
  const send = document.getElementById("reply-send");
  if (!send) return;
  send.addEventListener("click", async () => {
    const msgEl = document.getElementById("reply-msg");
    const fileEl = document.getElementById("reply-file");
    const msg = msgEl.value.trim();
    const file = fileEl.files[0];
    if (!msg && !file) return;
    const fd = new FormData();
    fd.append("ticket_id", id);
    fd.append("message", msg);
    if (file) fd.append("attachment", file);
    send.disabled = true;
    try {
      await apiForm("tickets/reply.php", fd);
      msgEl.value = "";
      fileEl.value = "";
      paintThread(await api(`tickets/thread.php?id=${id}`));
    } catch (e) {
      toast(e.message, "error");
    }
    send.disabled = false;
  });
}

function msgBubble(m) {
  const staff = m.role !== "retailer";
  return `<div style="align-self:${staff ? "flex-start" : "flex-end"};max-width:80%;background:${staff ? "var(--soft)" : "#143a24"};color:${staff ? "var(--ink)" : "#fff"};padding:.6rem .9rem;border-radius:.7rem">
    <div style="font-size:.72rem;opacity:.7;margin-bottom:.2rem">${esc(m.name)} · ${fmtDate(m.created_at)}</div>${esc(m.message)}${m.attachment ? "<br>" + attachLink(m.attachment) : ""}</div>`;
}

function stat(label, value, icon) {
  return `<div class="stat"><div class="stat__icon"><i class="fa-solid ${icon}"></i></div><div class="stat__label">${esc(label)}</div><div class="stat__value">${esc(value)}</div></div>`;
}

(async function () {
  const u = await bootstrap("retailer");
  if (!u) return;
  initShell();
  setView("dashboard");
})();

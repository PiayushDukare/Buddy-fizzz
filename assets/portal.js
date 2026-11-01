const API_BASE = "../api/";
window.__csrf = null;
window.__user = null;
window.__store = null;
window.__pay = { razorpay: false };
window.__tax = { gst: 0, cess: 0 };

async function api(path, opts = {}) {
  const headers = {
    "Content-Type": "application/json",
    ...(opts.headers || {}),
  };
  if (window.__csrf && (opts.method || "GET") !== "GET")
    headers["X-CSRF-Token"] = window.__csrf;
  const res = await fetch(API_BASE + path, {
    method: opts.method || "GET",
    headers,
    credentials: "same-origin",
    cache: "no-store",
    body: opts.body ? JSON.stringify(opts.body) : undefined,
  });
  let data = {};
  try {
    data = await res.json();
  } catch (e) {}
  if (res.status === 401) {
    location.href = "../login.html";
    throw new Error("Not authenticated");
  }
  if (!res.ok) throw new Error(data.error || "Something went wrong");
  return data;
}

async function apiForm(path, formData) {
  const headers = {};
  if (window.__csrf) headers["X-CSRF-Token"] = window.__csrf;
  const res = await fetch(API_BASE + path, {
    method: "POST",
    headers,
    credentials: "same-origin",
    cache: "no-store",
    body: formData,
  });
  let data = {};
  try {
    data = await res.json();
  } catch (e) {}
  if (res.status === 401) {
    location.href = "../login.html";
    throw new Error("Not authenticated");
  }
  if (!res.ok) throw new Error(data.error || "Something went wrong");
  return data;
}

let _poll = null;
let _pollFn = null;
function startPolling(fn, ms = 10000) {
  stopPolling();
  _pollFn = fn;
  _poll = setInterval(() => {
    if (
      document.visibilityState === "visible" &&
      !document.querySelector(".modal-back.open, .finder.open")
    )
      fn();
  }, ms);
}
function stopPolling() {
  if (_poll) clearInterval(_poll);
  _poll = null;
  _pollFn = null;
}
function pollNow() {
  if (
    _pollFn &&
    document.visibilityState === "visible" &&
    !document.querySelector(".modal-back.open, .finder.open")
  )
    _pollFn();
}
document.addEventListener("visibilitychange", pollNow);
window.addEventListener("focus", pollNow);

let _threadPoll = null;
function startThreadPolling(fn, ms = 7000) {
  stopThreadPolling();
  _threadPoll = setInterval(() => {
    if (!document.querySelector(".modal-back.open")) {
      stopThreadPolling();
      return;
    }
    if (document.visibilityState === "visible") fn();
  }, ms);
}
function stopThreadPolling() {
  if (_threadPoll) clearInterval(_threadPoll);
  _threadPoll = null;
}

async function payOrder(orderId, onDone) {
  if (typeof Razorpay === "undefined") {
    toast("Payment library not loaded. Refresh and try again.", "error");
    return;
  }
  let d;
  try {
    d = await api("pay/create.php", {
      method: "POST",
      body: { order_id: orderId },
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
        const v = await api("pay/verify.php", {
          method: "POST",
          body: {
            order_id: orderId,
            razorpay_payment_id: resp.razorpay_payment_id,
            razorpay_order_id: resp.razorpay_order_id,
            razorpay_signature: resp.razorpay_signature,
          },
        });
        toast(v.message);
        if (onDone) onDone();
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

async function payForCart(payload, onDone) {
  if (typeof Razorpay === "undefined") {
    toast("Payment library not loaded. Refresh and try again.", "error");
    return;
  }
  let d;
  try {
    d = await api("pay/checkout.php", { method: "POST", body: payload });
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
        const v = await api("pay/confirm.php", {
          method: "POST",
          body: {
            razorpay_payment_id: resp.razorpay_payment_id,
            razorpay_order_id: resp.razorpay_order_id,
            razorpay_signature: resp.razorpay_signature,
          },
        });
        toast(v.message);
        if (onDone) onDone();
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

function attachLink(path) {
  if (!path) return "";
  const name = path.split("/").pop();
  return `<a href="../api/file.php?f=${encodeURIComponent(path)}" target="_blank" rel="noopener" class="attach"><i class="fa-solid fa-paperclip"></i> ${esc(name)}</a>`;
}

async function bootstrap(requiredRole) {
  try {
    const me = await api("auth/me.php");
    if (!me.authenticated) {
      location.href = "../login.html";
      return null;
    }
    if (requiredRole && me.user.role !== requiredRole) {
      location.href = "../" + me.user.role + "/";
      return null;
    }
    window.__csrf = me.csrf;
    window.__user = me.user;
    window.__store = me.store || null;
    window.__pay = me.pay || { razorpay: false };
    window.__tax = me.tax || { gst: 0, cess: 0 };
    paintUser(me.user);
    return me.user;
  } catch (e) {
    location.href = "../login.html";
    return null;
  }
}

function paintUser(user) {
  document
    .querySelectorAll("[data-user-name]")
    .forEach((el) => (el.textContent = user.name));
  document
    .querySelectorAll("[data-user-email]")
    .forEach((el) => (el.textContent = user.email));
  document
    .querySelectorAll("[data-user-initial]")
    .forEach(
      (el) => (el.textContent = (user.name || "?").charAt(0).toUpperCase()),
    );
}

async function logout() {
  try {
    await api("auth/logout.php", { method: "POST" });
  } catch (e) {}
  location.href = "../login.html";
}

function esc(s) {
  return String(s ?? "").replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
}

function uiDialog(cfg) {
  return new Promise((resolve) => {
    const back = document.createElement("div");
    back.className = "ui-dialog-back";
    back.innerHTML = `<div class="ui-dialog" role="dialog" aria-modal="true">
      <div class="ui-dialog__head"><h3>${esc(cfg.title || "")}</h3></div>
      <div class="ui-dialog__body">${cfg.bodyHtml || ""}</div>
      <div class="ui-dialog__foot">
        <button type="button" class="btn btn--ghost" data-cancel>${esc(cfg.cancelText || "Cancel")}</button>
        <button type="button" class="btn ${cfg.danger ? "btn--danger" : "btn--green"}" data-ok>${esc(cfg.confirmText || "OK")}</button>
      </div></div>`;
    document.body.appendChild(back);
    requestAnimationFrame(() => back.classList.add("open"));
    const done = (val) => {
      back.classList.remove("open");
      document.removeEventListener("keydown", onKey);
      setTimeout(() => back.remove(), 180);
      resolve(val);
    };
    const showErr = (m) => {
      let e = back.querySelector(".ui-dialog__err");
      if (!e) {
        e = document.createElement("div");
        e.className = "ui-dialog__err";
        back.querySelector(".ui-dialog__body").appendChild(e);
      }
      e.textContent = m;
    };
    const okFn = () => {
      const val = cfg.getValue ? cfg.getValue() : true;
      if (cfg.validate) {
        const err = cfg.validate(val);
        if (err) {
          showErr(err);
          return;
        }
      }
      done(val);
    };
    const cancelFn = () => done(null);
    back.querySelector("[data-ok]").addEventListener("click", okFn);
    back.querySelector("[data-cancel]").addEventListener("click", cancelFn);
    back.addEventListener("mousedown", (e) => {
      if (e.target === back) cancelFn();
    });
    function onKey(e) {
      if (e.key === "Escape") cancelFn();
      else if (e.key === "Enter" && cfg.getValue) {
        e.preventDefault();
        okFn();
      }
    }
    document.addEventListener("keydown", onKey);
    const f = cfg.focus && document.getElementById(cfg.focus);
    if (f) {
      f.focus();
      try {
        f.select();
      } catch (e) {}
    }
  });
}
function uiConfirm(message, opts = {}) {
  return uiDialog({
    title: opts.title || "Please confirm",
    bodyHtml: `<p class="ui-dialog__text">${esc(message)}</p>`,
    confirmText: opts.confirmText || "Confirm",
    cancelText: opts.cancelText || "Cancel",
    danger: opts.danger || false,
  }).then((r) => r !== null);
}
function uiPrompt(message, opts = {}) {
  const id = "uid_" + Math.random().toString(36).slice(2);
  const type = opts.type || "text";
  const attrs = [
    `id="${id}"`,
    `class="input"`,
    `type="${type}"`,
    opts.placeholder ? `placeholder="${esc(opts.placeholder)}"` : "",
    opts.value != null ? `value="${esc(opts.value)}"` : "",
    opts.min != null ? `min="${opts.min}"` : "",
    opts.step != null ? `step="${opts.step}"` : "",
    type === "number" ? `inputmode="decimal"` : "",
  ]
    .filter(Boolean)
    .join(" ");
  const input = opts.prefix
    ? `<div class="ui-dialog__inputwrap"><span>${esc(opts.prefix)}</span><input ${attrs}></div>`
    : `<input ${attrs}>`;
  return uiDialog({
    title: opts.title || "Enter a value",
    bodyHtml: `${message ? `<p class="ui-dialog__text">${esc(message)}</p>` : ""}${input}`,
    confirmText: opts.confirmText || "OK",
    cancelText: opts.cancelText || "Cancel",
    focus: id,
    getValue: () => {
      const el = document.getElementById(id);
      return el ? el.value : null;
    },
    validate: opts.validate,
  });
}

function itemThumb(img) {
  return img
    ? `<img src="../${esc(img)}" alt="" class="item-thumb" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'item-thumb item-thumb--ph',innerHTML:'<i class=\\'fa-solid fa-bottle-water\\'></i>'}))">`
    : '<span class="item-thumb item-thumb--ph"><i class="fa-solid fa-bottle-water"></i></span>';
}

function toast(message, type = "ok") {
  let wrap = document.querySelector(".toast-wrap");
  if (!wrap) {
    wrap = document.createElement("div");
    wrap.className = "toast-wrap";
    document.body.appendChild(wrap);
  }
  const t = document.createElement("div");
  t.className = "toast" + (type === "error" ? " toast--error" : "");
  t.textContent = message;
  wrap.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

function fmtDate(s) {
  if (!s) return "—";
  const d = new Date(s.replace(" ", "T"));
  return d.toLocaleDateString("en-IN", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

function money(n) {
  return (
    "₹" +
    Number(n || 0).toLocaleString("en-IN", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

function openProfile() {
  const u = window.__user || {};
  const s = window.__store;
  const bizBlock = s
    ? `<hr style="border:none;border-top:1px solid var(--line);margin:1.3rem 0">
      <h4 style="color:var(--green-dark);margin-bottom:.7rem;font-family:'Baloo 2',cursive">Business Details</h4>
      <div class="field"><label>GSTIN <span style="color:var(--muted);font-weight:400">(optional — shown on invoices)</span></label><input class="input" id="pf-gstin" maxlength="15" style="text-transform:uppercase" placeholder="15-character GSTIN" value="${esc(s.gstin || "")}"></div>
      <div class="field"><label>Business Phone</label><input class="input" id="pf-bphone" value="${esc(s.phone || "")}"></div>
      <div class="field"><label>Billing Address</label><textarea class="input" id="pf-baddr" rows="2">${esc(s.address || "")}</textarea></div>
      <button class="btn btn--green" id="pf-biz">Save Business Details</button>`
    : "";
  openModal(`<div class="modal__head"><h3>My Profile</h3><button class="modal__close" onclick="closeModal()">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label>Name</label><input class="input" id="pf-name" value="${esc(u.name || "")}"></div>
      <div class="field"><label>Email</label><input class="input" value="${esc(u.email || "")}" disabled></div>
      <div class="field"><label>Phone</label><input class="input" id="pf-phone" value="${esc(u.phone || "")}"></div>
      <button class="btn btn--green" id="pf-save">Save Profile</button>
      ${bizBlock}
      <hr style="border:none;border-top:1px solid var(--line);margin:1.3rem 0">
      <h4 style="color:var(--green-dark);margin-bottom:.7rem;font-family:'Baloo 2',cursive">Change Password</h4>
      <div class="field"><label>Current Password</label><input class="input" type="password" id="pf-cur"></div>
      <div class="field"><label>New Password</label><input class="input" type="password" id="pf-new" placeholder="Min 8 characters"></div>
      <button class="btn btn--ghost" id="pf-pass">Update Password</button>
    </div>`);
  document.getElementById("pf-save").addEventListener("click", async () => {
    try {
      const r = await api("profile/update.php", {
        method: "POST",
        body: {
          name: document.getElementById("pf-name").value.trim(),
          phone: document.getElementById("pf-phone").value.trim(),
        },
      });
      toast(r.message);
      window.__user.name = r.name;
      paintUser(window.__user);
    } catch (e) {
      toast(e.message, "error");
    }
  });
  const bizBtn = document.getElementById("pf-biz");
  if (bizBtn)
    bizBtn.addEventListener("click", async () => {
      try {
        const r = await api("store/update.php", {
          method: "POST",
          body: {
            gstin: document.getElementById("pf-gstin").value.trim(),
            phone: document.getElementById("pf-bphone").value.trim(),
            address: document.getElementById("pf-baddr").value.trim(),
          },
        });
        toast(r.message);
        if (window.__store) {
          window.__store.gstin = document
            .getElementById("pf-gstin")
            .value.trim();
          window.__store.phone = document
            .getElementById("pf-bphone")
            .value.trim();
          window.__store.address = document
            .getElementById("pf-baddr")
            .value.trim();
        }
      } catch (e) {
        toast(e.message, "error");
      }
    });
  document.getElementById("pf-pass").addEventListener("click", async () => {
    try {
      const r = await api("profile/password.php", {
        method: "POST",
        body: {
          current: document.getElementById("pf-cur").value,
          new: document.getElementById("pf-new").value,
        },
      });
      toast(r.message);
      document.getElementById("pf-cur").value = "";
      document.getElementById("pf-new").value = "";
    } catch (e) {
      toast(e.message, "error");
    }
  });
}

async function announcementBanner() {
  try {
    const a = (await api("announcements.php")).announcements || [];
    if (!a.length) return "";
    return `<div class="ann-strip">${a.map((x) => `<div class="ann"><i class="fa-solid fa-bullhorn"></i><div><b>${esc(x.title)}</b>${x.body ? `<span>${esc(x.body)}</span>` : ""}</div></div>`).join("")}</div>`;
  } catch (e) {
    return "";
  }
}

function initNotifications() {
  const bell = document.getElementById("notif-bell");
  if (!bell) return;
  const panel = document.getElementById("notif-panel");
  const dot = document.getElementById("notif-dot");
  const listEl = panel.querySelector(".notif-list");

  async function refresh(markRead) {
    try {
      const d = await api("notifications/list.php");
      dot.textContent = d.unread > 9 ? "9+" : d.unread;
      dot.style.display = d.unread > 0 ? "grid" : "none";
      listEl.innerHTML = (d.notifications || []).length
        ? d.notifications
            .map(
              (n) =>
                `<div class="notif-item ${n.is_read == 0 ? "unread" : ""}"><b>${esc(n.title)}</b>${n.body ? `<small>${esc(n.body)}</small>` : ""}<time>${fmtDate(n.created_at)}</time></div>`,
            )
            .join("")
        : '<div class="notif-empty">No notifications yet.</div>';
      if (markRead && d.unread > 0) {
        await api("notifications/read.php", { method: "POST", body: {} });
        dot.style.display = "none";
      }
    } catch (e) {}
  }
  bell.addEventListener("click", (e) => {
    e.stopPropagation();
    panel.classList.toggle("open");
    if (panel.classList.contains("open")) refresh(true);
  });
  panel.addEventListener("click", (e) => e.stopPropagation());
  document.addEventListener("click", () => panel.classList.remove("open"));
  refresh(false);
  setInterval(() => refresh(false), 20000);
}

function applyTheme(t) {
  document.documentElement.setAttribute("data-theme", t);
  const btn = document.getElementById("theme-btn");
  if (btn)
    btn.innerHTML =
      t === "dark"
        ? '<i class="fa-solid fa-sun"></i>'
        : '<i class="fa-solid fa-moon"></i>';
}
function initTheme() {
  let t = localStorage.getItem("bf-theme");
  if (t !== "dark" && t !== "light") t = "light";
  applyTheme(t);
}
function toggleTheme() {
  const next =
    document.documentElement.getAttribute("data-theme") === "dark"
      ? "light"
      : "dark";
  localStorage.setItem("bf-theme", next);
  applyTheme(next);
}
initTheme();

function initShell() {
  const burger = document.querySelector(".burger-btn");
  const sidebar = document.querySelector(".sidebar");
  if (burger && sidebar)
    burger.addEventListener("click", () => sidebar.classList.toggle("open"));
  document
    .querySelectorAll("[data-logout]")
    .forEach((b) => b.addEventListener("click", logout));
  const pb = document.getElementById("profile-btn");
  if (pb) pb.addEventListener("click", openProfile);
  const tb = document.getElementById("theme-btn");
  if (tb) tb.addEventListener("click", toggleTheme);
  initTheme();
  initNotifications();
}

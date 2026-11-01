const FRAME_COUNT = 240;
const HI_PATH = "frames/hi/frame-";
const SMOOTHING = 0.14;
const MAX_CONCURRENT = 8;
const AUTO_FPS = 30;

const TOUCH =
  window.matchMedia("(hover: none) and (pointer: coarse)").matches ||
  window.matchMedia("(max-width: 900px)").matches;
const LO_PATH = TOUCH ? "frames/mobile/frame-" : "frames/lo/frame-";

const hiSrc = (i) => `${HI_PATH}${String(i + 1).padStart(3, "0")}.webp`;
const loSrc = (i) => `${LO_PATH}${String(i + 1).padStart(3, "0")}.webp`;

const canvas = document.getElementById("hero-canvas");
const ctx = canvas.getContext("2d", { alpha: false });
const hero = document.getElementById("home");
const heroContent = document.getElementById("hero-content");
const dots = document.querySelectorAll("#hero-dots span");

const loImg = new Array(FRAME_COUNT);
const hiImg = new Array(FRAME_COUNT);
const loReady = new Uint8Array(FRAME_COUNT);
const hiState = new Uint8Array(FRAME_COUNT);

let targetFrame = 0;
let currentFrame = 0;
let lastImg = null;
let running = false;
let heroVisible = true;
let inFlight = 0;
let dpr = 1;
let headerPx = 0;
let backdrop = null;
let lastTime = 0;

function loadImage(src, onDone) {
  const img = new Image();
  img.decoding = "async";
  img.src = src;
  const finish = () => onDone(img);
  if (img.decode) img.decode().then(finish).catch(finish);
  else img.onload = finish;
  return img;
}

function preloadLo() {
  for (let i = 0; i < FRAME_COUNT; i++) {
    loImg[i] = loadImage(loSrc(i), () => {
      loReady[i] = 1;
      if (i === 0) resize();
      lastImg = null;
      play();
    });
  }
}

function nextHiIndex() {
  const center = Math.round(currentFrame);
  let best = -1;
  let bestDist = Infinity;
  for (let i = 0; i < FRAME_COUNT; i++) {
    if (hiState[i] !== 0) continue;
    const dist = Math.abs(i - center);
    if (dist < bestDist) {
      bestDist = dist;
      best = i;
    }
  }
  return best;
}

function pumpHi() {
  while (inFlight < MAX_CONCURRENT) {
    const i = nextHiIndex();
    if (i < 0) break;
    hiState[i] = 1;
    inFlight++;
    hiImg[i] = loadImage(hiSrc(i), () => {
      hiState[i] = 2;
      inFlight--;
      lastImg = null;
      play();
      pumpHi();
    });
  }
}

function pickImage(index) {
  if (hiState[index] === 2) return hiImg[index];
  if (loReady[index]) return loImg[index];
  for (let d = 1; d < FRAME_COUNT; d++) {
    const a = index - d;
    const b = index + d;
    if (a >= 0) {
      if (hiState[a] === 2) return hiImg[a];
      if (loReady[a]) return loImg[a];
    }
    if (b < FRAME_COUNT) {
      if (hiState[b] === 2) return hiImg[b];
      if (loReady[b]) return loImg[b];
    }
  }
  return null;
}

function buildBackdrop(cw, ch) {
  const g = ctx.createRadialGradient(
    cw / 2,
    ch * 0.42,
    ch * 0.04,
    cw / 2,
    ch * 0.55,
    Math.max(cw, ch) * 0.82,
  );
  g.addColorStop(0, "#ffde79");
  g.addColorStop(0.5, "#efb524");
  g.addColorStop(1, "#a9760d");
  backdrop = g;
}

function resize() {
  dpr = Math.min(window.devicePixelRatio || 1, 2);
  canvas.width = Math.round(canvas.clientWidth * dpr);
  canvas.height = Math.round(canvas.clientHeight * dpr);
  headerPx = (header.offsetHeight || 0) * dpr;
  hero.style.marginTop = `-${header.offsetHeight || 0}px`;
  buildBackdrop(canvas.width, canvas.height);
  lastImg = null;
  if (!draw(Math.round(currentFrame))) {
    ctx.fillStyle = backdrop;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
  }
}

function draw(index) {
  const img = pickImage(index);
  if (!img || img === lastImg) return false;
  const cw = canvas.width;
  const ch = canvas.height;
  const iw = img.naturalWidth;
  const ih = img.naturalHeight;
  const ratio = Math.max(cw / iw, ch / ih);
  const w = iw * ratio;
  const h = ih * ratio;
  const shift = Math.min(headerPx, Math.max(h - ch, 0) / 2);
  ctx.drawImage(img, (cw - w) / 2, (ch - h) / 2 + shift, w, h);
  lastImg = img;
  return true;
}

function updateTarget() {
  if (TOUCH) return;
  const scrolled = -hero.getBoundingClientRect().top;
  const total = hero.offsetHeight - window.innerHeight;
  const progress = total > 0 ? Math.min(Math.max(scrolled / total, 0), 1) : 0;
  targetFrame = progress * (FRAME_COUNT - 1);

  const fade = Math.min(Math.max((progress - 0.12) / 0.32, 0), 1);
  heroContent.style.opacity = `${1 - fade}`;
  heroContent.style.transform = `translateY(${fade * -3}rem)`;

  const active = Math.min(dots.length - 1, Math.floor(progress * dots.length));
  dots.forEach((d, i) => d.classList.toggle("on", i === active));
}

function scrubTick() {
  currentFrame += (targetFrame - currentFrame) * SMOOTHING;
  const settled = Math.abs(targetFrame - currentFrame) < 0.02;
  if (settled) currentFrame = targetFrame;
  draw(Math.round(currentFrame));
  if (settled || !heroVisible) {
    running = false;
    return;
  }
  requestAnimationFrame(scrubTick);
}

function autoTick(t) {
  if (!heroVisible) {
    running = false;
    lastTime = 0;
    return;
  }
  if (!lastTime) lastTime = t;
  currentFrame += ((t - lastTime) / 1000) * AUTO_FPS;
  lastTime = t;
  while (currentFrame >= FRAME_COUNT) currentFrame -= FRAME_COUNT;
  draw(Math.round(currentFrame) % FRAME_COUNT);
  requestAnimationFrame(autoTick);
}

function play() {
  if (running || !heroVisible) return;
  running = true;
  if (TOUCH) {
    lastTime = 0;
    requestAnimationFrame(autoTick);
  } else {
    requestAnimationFrame(scrubTick);
  }
}

function setBackgroundFrames() {
  document.querySelectorAll("[data-frame]").forEach((el) => {
    el.style.backgroundImage = `url("${hiSrc(parseInt(el.dataset.frame, 10) - 1)}")`;
  });
}

const header = document.getElementById("header");
const burger = document.getElementById("burger");
const nav = document.getElementById("nav");
const navLinks = nav.querySelectorAll("a");
const sections = document.querySelectorAll("section[id]");

burger.addEventListener("click", () => {
  burger.classList.toggle("open");
  nav.classList.toggle("open");
});
navLinks.forEach((link) =>
  link.addEventListener("click", () => {
    burger.classList.remove("open");
    nav.classList.remove("open");
  }),
);

function onScroll() {
  updateTarget();
  play();
  header.classList.toggle(
    "scrolled",
    hero.getBoundingClientRect().bottom <= (header.offsetHeight || 0) + 4,
  );
  let active = "home";
  sections.forEach((sec) => {
    if (window.scrollY >= sec.offsetTop - 140) active = sec.id;
  });
  navLinks.forEach((link) =>
    link.classList.toggle("active", link.getAttribute("href") === `#${active}`),
  );
}

let ticking = false;
window.addEventListener(
  "scroll",
  () => {
    if (!ticking) {
      requestAnimationFrame(() => {
        onScroll();
        ticking = false;
      });
      ticking = true;
    }
  },
  { passive: true },
);

let lastWidth = window.innerWidth;
window.addEventListener("resize", () => {
  if (window.innerWidth === lastWidth) return;
  lastWidth = window.innerWidth;
  resize();
});
window.addEventListener("orientationchange", () => {
  lastWidth = window.innerWidth;
  setTimeout(resize, 250);
});

new IntersectionObserver(
  ([entry]) => {
    heroVisible = entry.isIntersecting;
    if (heroVisible) play();
  },
  { threshold: 0 },
).observe(hero);

const revealIO = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("in");
        revealIO.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.15 },
);
document.querySelectorAll(".reveal").forEach((el) => revealIO.observe(el));

const PRODUCTS = {
  lemon: {
    name: "Lemon Lime Soda",
    tag: "Zesty & Cool",
    img: "frames/img/lemon.webp",
    accent: "linear-gradient(160deg, #9fce4e, #4c8a2b)",
    solid: "#4c8a2b",
    desc: "A crisp, bubbly rush of real lemon and lime that wakes up your senses. Bright, tangy and endlessly refreshing — the perfect everyday cooler.",
    features: [
      "Made with Real Lemon & Lime Juice",
      "No Artificial Colours",
      "Perfectly Carbonated",
      "Crafted in Goa",
    ],
    meta: [
      ["Volume", "250 ml"],
      ["Best served", "Chilled"],
      ["Style", "Sparkling"],
    ],
  },
  ginger: {
    name: "Ginger Lemon Soda",
    tag: "Spicy Zing",
    img: "frames/img/ginger.webp",
    accent: "linear-gradient(160deg, #ffc542, #e5870a)",
    solid: "#e5870a",
    desc: "A fiery burst of real ginger meets the zing of fresh lemon. Warm, punchy and lively — refreshment with real character for every season.",
    features: [
      "Made with Real Ginger Extract",
      "Real Lemon Juice",
      "No Artificial Colours",
      "Perfect for every season",
    ],
    meta: [
      ["Volume", "250 ml"],
      ["Best served", "Chilled"],
      ["Style", "Sparkling"],
    ],
  },
  orange: {
    name: "Orange Soda",
    tag: "Citrus Burst",
    img: "frames/img/orange.webp",
    accent: "linear-gradient(160deg, #ff9330, #db4708)",
    solid: "#db4708",
    desc: "Sun-ripened orange in a fizzy, juicy splash. Sweet, tangy and full of sunshine — a burst of citrus joy in every single sip.",
    features: [
      "Made with Real Orange Juice",
      "No Artificial Colours",
      "Perfectly Carbonated",
      "Crafted in Goa",
    ],
    meta: [
      ["Volume", "250 ml"],
      ["Best served", "Chilled"],
      ["Style", "Sparkling"],
    ],
  },
  masala: {
    name: "Masala Soda",
    tag: "Spiced & Bold",
    img: "frames/img/masala.webp",
    accent: "linear-gradient(160deg, #a5602c, #57280f)",
    solid: "#8a4b1e",
    desc: "A bold Indian classic — a spiced masala kick with a fizzy twist. Tangy, aromatic and unmistakably desi. Refreshment with attitude.",
    features: [
      "Authentic Masala Spice Blend",
      "No Artificial Colours",
      "Perfectly Carbonated",
      "Crafted in Goa",
    ],
    meta: [
      ["Volume", "250 ml"],
      ["Best served", "Chilled"],
      ["Style", "Sparkling"],
    ],
  },
  grape: {
    name: "Grape Soda",
    tag: "Sweet & Juicy",
    img: "frames/img/grape.webp",
    accent: "linear-gradient(160deg, #a35fc7, #5a2782)",
    solid: "#7a3bab",
    desc: "Plump, juicy grapes in a smooth, sparkling pour. Rich, sweet and irresistibly fruity — a purple splash of pure delight.",
    features: [
      "Made with Real Grape Juice",
      "No Artificial Colours",
      "Perfectly Carbonated",
      "Crafted in Goa",
    ],
    meta: [
      ["Volume", "250 ml"],
      ["Best served", "Chilled"],
      ["Style", "Sparkling"],
    ],
  },
};

const modal = document.getElementById("modal");
const modalCard = modal.querySelector(".modal__card");
const modalMedia = document.getElementById("modal-media");
const modalTag = document.getElementById("modal-tag");
const modalTitle = document.getElementById("modal-title");
const modalDesc = document.getElementById("modal-desc");
const modalFeatures = document.getElementById("modal-features");
const modalMeta = document.getElementById("modal-meta");

function openModal(key) {
  const p = PRODUCTS[key];
  if (!p) return;
  modalCard.style.setProperty("--accent-solid", p.solid);
  modalMedia.style.backgroundImage = `url("${p.img}"), ${p.accent}`;
  modalMedia.setAttribute("aria-label", `Buddy Fizz ${p.name}`);
  modalTag.textContent = p.tag;
  modalTitle.textContent = p.name;
  modalDesc.textContent = p.desc;
  modalFeatures.innerHTML = p.features
    .map((f) => `<li><i class="fa-solid fa-circle-check"></i> ${f}</li>`)
    .join("");
  modalMeta.innerHTML = p.meta
    .map(([label, value]) => `<span>${label}<strong>${value}</strong></span>`)
    .join("");
  modal.classList.add("open");
  modal.setAttribute("aria-hidden", "false");
  document.body.style.overflow = "hidden";
}

function closeModal() {
  modal.classList.remove("open");
  modal.setAttribute("aria-hidden", "true");
  document.body.style.overflow = "";
}

document.querySelectorAll(".flavour").forEach((card) => {
  const trigger = card.querySelector("[data-product]");
  if (trigger)
    card.addEventListener("click", () => openModal(trigger.dataset.product));
});
modal
  .querySelectorAll("[data-close]")
  .forEach((el) => el.addEventListener("click", closeModal));

const storyModal = document.getElementById("story-modal");
const openStoryBtn = document.getElementById("open-story");

function openStory() {
  storyModal.classList.add("open");
  storyModal.setAttribute("aria-hidden", "false");
  document.body.style.overflow = "hidden";
}
function closeStory() {
  storyModal.classList.remove("open");
  storyModal.setAttribute("aria-hidden", "true");
  document.body.style.overflow = "";
}
if (openStoryBtn) {
  openStoryBtn.addEventListener("click", (e) => {
    e.preventDefault();
    openStory();
  });
}
storyModal
  .querySelectorAll("[data-close]")
  .forEach((el) => el.addEventListener("click", closeStory));

document.addEventListener("keydown", (e) => {
  if (e.key !== "Escape") return;
  if (modal.classList.contains("open")) closeModal();
  if (storyModal.classList.contains("open")) closeStory();
});

setBackgroundFrames();
resize();
updateTarget();
preloadLo();
if (!TOUCH) pumpHi();

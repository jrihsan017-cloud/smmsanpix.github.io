// ============ KONFIGURASI (edit bagian ini) ============
const CONFIG = {
  SITE: "FB Pro Booster",
  WA: "6281234567890",      // nomor WhatsApp admin, format 62xxx tanpa + atau 0
  FREE: true,               // true = gratis, false = tampilkan harga
  MAX_QTY: 500,             // batas jumlah per pesanan (mode gratis)
  PER_DAY: 3,               // batas pesanan per perangkat per 24 jam (mode gratis)
  SERVICES: [
    { id: 1, name: "FB Pro Followers - Hemat", price: 18000, min: 100, max: 50000, info: "Estimasi 1-3 hari" },
    { id: 2, name: "FB Pro Followers - Standar", price: 28000, min: 100, max: 100000, info: "Garansi 30 hari" },
    { id: 3, name: "FB Pro Followers - Premium", price: 45000, min: 50, max: 30000, info: "Garansi refill 90 hari" }
  ]
};
// =======================================================

const $ = (id) => document.getElementById(id);
const rp = (n) => "Rp " + Math.round(n).toLocaleString("id-ID");
let orders = [];
try { orders = JSON.parse(localStorage.getItem("orders") || "[]"); } catch (e) { orders = []; }
const save = () => { try { localStorage.setItem("orders", JSON.stringify(orders)); } catch (e) {} };
const cur = () => CONFIG.SERVICES.find((s) => s.id == $("sid").value);

function cell(tr, text) { const td = document.createElement("td"); td.textContent = text; tr.appendChild(td); return td; }

function waLink(o) {
  const t = "Halo admin, saya pesan:\nID: " + o.id + "\nLayanan: " + o.name + "\nLink: " + o.link + "\nJumlah: " + o.qty;
  return "https://wa.me/" + CONFIG.WA + "?text=" + encodeURIComponent(t);
}

function calc() {
  const s = cur(), q = +$("qty").value || 0;
  $("inf").textContent = s.info + (CONFIG.FREE ? "" : " · " + rp(s.price) + " / 1000");
  const mx = CONFIG.FREE ? Math.min(s.max, CONFIG.MAX_QTY) : s.max;
  $("rng").textContent = "Min " + s.min.toLocaleString("id-ID") + " · Max " + mx.toLocaleString("id-ID");
  $("tot").textContent = CONFIG.FREE ? "GRATIS" : rp(q / 1000 * s.price);
}

function render() {
  const hb = $("hb"); hb.textContent = "";
  orders.forEach((o) => {
    const tr = document.createElement("tr");
    cell(tr, o.id);
    cell(tr, new Date(o.t).toLocaleString("id-ID", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }));
    cell(tr, o.name);
    cell(tr, o.qty.toLocaleString("id-ID"));
    cell(tr, "Menunggu admin");
    const td = document.createElement("td");
    const a = document.createElement("a"); a.href = waLink(o); a.target = "_blank"; a.rel = "noopener"; a.textContent = "Kirim WA";
    td.appendChild(a); tr.appendChild(td); hb.appendChild(tr);
  });
  $("he").classList.toggle("h", orders.length > 0);
}

function init() {
  document.title = CONFIG.SITE; $("site").textContent = "📈 " + CONFIG.SITE;
  CONFIG.SERVICES.forEach((s) => {
    const o = document.createElement("option"); o.value = s.id; o.textContent = s.name; $("sid").appendChild(o);
    const tr = document.createElement("tr");
    cell(tr, s.name); cell(tr, CONFIG.FREE ? "GRATIS" : rp(s.price)); cell(tr, s.min); cell(tr, s.max); cell(tr, s.info);
    $("sb").appendChild(tr);
  });
  $("sid").onchange = $("qty").oninput = calc;
  document.querySelectorAll("nav button").forEach((b) => {
    b.onclick = () => {
      document.querySelectorAll("nav button").forEach((x) => x.classList.toggle("on", x === b));
      ["order", "hist", "svc"].forEach((id) => $(id).classList.toggle("h", id !== b.dataset.t));
    };
  });
  $("go").onclick = submit;
  calc(); render();
}

function submit() {
  const s = cur(), q = +$("qty").value, link = $("link").value.trim(), m = $("msg");
  const max = CONFIG.FREE ? Math.min(s.max, CONFIG.MAX_QTY) : s.max;
  if (!/^https?:\/\/(www\.|m\.|web\.)?(facebook|fb)\.com\/\S+$/i.test(link)) { m.textContent = "Masukkan link Facebook yang valid."; return; }
  if (!q || q < s.min || q > max) { m.textContent = "Jumlah harus antara " + s.min + " dan " + max + "."; return; }
  const recent = orders.filter((o) => Date.now() - o.t < 864e5).length;
  if (CONFIG.FREE && recent >= CONFIG.PER_DAY) { m.textContent = "Batas gratis " + CONFIG.PER_DAY + " pesanan per hari tercapai."; return; }
  const o = { id: "ORD" + Date.now().toString(36).toUpperCase(), t: Date.now(), name: s.name, link: link, qty: q };
  orders.unshift(o); save(); render();
  m.textContent = "Pesanan dicatat ✓ Lanjutkan konfirmasi ke admin lewat WhatsApp.";
  window.open(waLink(o), "_blank");
}
init();

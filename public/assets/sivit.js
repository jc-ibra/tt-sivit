/* SIVIT · comodidades del navegador. Los datos y las reglas viven en el servidor (CodeIgniter + MySQL):
   aquí solo hay ventanas, vista previa del formulario, armado del correo y llamadas AJAX de depósito. */
(() => {
"use strict";
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || "";
const csrfName = () => meta("csrf-name"), csrfHash = () => meta("csrf-hash");
const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const fmt = n => "$" + (Number(n) || 0).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dmy = iso => iso ? iso.split("-").reverse().join("/") : "";
const todayISO = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };
const json = id => { try { return JSON.parse($(id)?.textContent || "null"); } catch (e) { return null; } };
const csrfField = () => `<input type="hidden" name="${esc(csrfName())}" value="${esc(csrfHash())}">`;
const base = meta("base-url") || "/";
const siteUrl = p => base + p;

function toast(msg) { const t = $("#toast"); if (!t) return; t.textContent = msg; t.hidden = false; clearTimeout(toast.h); toast.h = setTimeout(() => t.hidden = true, 2600); }

async function post(url, datos) {
  const fd = new FormData(); fd.append(csrfName(), csrfHash());
  for (const [k, v] of Object.entries(datos || {})) Array.isArray(v) ? v.forEach(x => fd.append(k + "[]", x)) : fd.append(k, v);
  const r = await fetch(url, { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" }, credentials: "same-origin" });
  let j = null; try { j = await r.json(); } catch (e) {}
  if (r.status === 401) { location.href = base; throw new Error("Sesión vencida"); }
  if (!r.ok) throw new Error(j?.error || "No se pudo guardar");
  return j;
}

/* ---------- ventanas ---------- */
function modal(inner) {
  const sc = document.createElement("div"); sc.className = "scrim";
  sc.innerHTML = `<div class="mdlg" role="dialog" aria-modal="true">${inner}</div>`;
  document.body.appendChild(sc);
  const k = e => { if (e.key === "Escape") close(); };
  const close = () => { sc.remove(); document.removeEventListener("keydown", k); };
  document.addEventListener("keydown", k);
  sc.addEventListener("mousedown", e => { if (e.target === sc) close(); });
  $$("[data-x]", sc).forEach(b => b.onclick = close);
  return { el: sc, q: s => sc.querySelector(s), close };
}
function shake(m, msg, inp) { m.q(".err").textContent = msg; const d = m.q(".mdlg"); d.classList.remove("shake"); void d.offsetWidth; d.classList.add("shake"); if (inp) { inp.focus(); inp.select?.(); } }
const avatarEl = card => $(".av", card)?.innerHTML || "";

/* ---------- panel de usuarios ---------- */
const minClave = json("#cfg-min") || 4;
function abrirLogin(b) {
  if (b.dataset.on === "1") return;
  const id = b.dataset.u, nombre = b.dataset.nombre, rol = b.dataset.rol, rolTxt = b.dataset.rolTxt, av = avatarEl(b);
  if (b.dataset.clave !== "1") {
    if (rol !== "operador") { toast("Este perfil aún no tiene contraseña: el operador debe asignarla"); return; }
    const m = modal(`<form class="lg" method="post" action="${siteUrl("login/crear-operador")}">${csrfField()}<div class="av xl">${av}</div><h2>Bienvenido</h2>
      <p class="rl">Crea la contraseña del <b>${esc(nombre)}</b>. Con ella podrás dar de alta a los coordinadores y asignar sus contraseñas.</p>
      <label class="f"><span>Contraseña nueva</span><input type="password" name="clave" id="s1" autocomplete="new-password"></label>
      <label class="f"><span>Repite la contraseña</span><input type="password" name="clave2" id="s2" autocomplete="new-password"></label>
      <div class="err"></div>
      <div class="mbtns"><button type="button" class="btn ghost" data-x>Cancelar</button><button type="submit" class="btn">Crear y entrar</button></div>
      <p class="hint">Guárdala bien: si se olvida, solo se puede restablecer desde la base de datos.</p></form>`);
    m.q("form").addEventListener("submit", e => {
      const a = m.q("#s1").value, c = m.q("#s2").value;
      if (a.length < minClave) { e.preventDefault(); shake(m, `Usa al menos ${minClave} caracteres`, m.q("#s1")); }
      else if (a !== c) { e.preventDefault(); shake(m, "Las contraseñas no coinciden", m.q("#s2")); }
    });
    setTimeout(() => m.q("#s1").focus(), 30); return;
  }
  const m = modal(`<form class="lg" method="post" action="${siteUrl("login")}">${csrfField()}<input type="hidden" name="usuario_id" value="${esc(id)}"><div class="av xl">${av}</div><h2>${esc(nombre)}</h2><p class="rl">${esc(rolTxt)}</p>
    <label class="f"><span>Contraseña</span><input type="password" name="clave" id="lg-p" autocomplete="current-password" required></label>
    <div class="err"></div>
    <div class="mbtns"><button type="button" class="btn ghost" data-x>Cancelar</button><button type="submit" class="btn">Entrar</button></div></form>`);
  setTimeout(() => m.q("#lg-p").focus(), 30);
  return m;
}

function abrirEdicion(b) {
  const esOp = document.body.dataset.rol === "operador";
  const nuevo = !b || !b.dataset.e;
  const t = nuevo ? { e: "", nombre: "", rol: "coordinador", tema: "", foto: "0", nsol: "0" } : { e: b.dataset.e, nombre: b.dataset.nombre || $(".hdr-user b")?.textContent || "", rol: b.dataset.rol || document.body.dataset.rol, tema: b.dataset.tema || "", foto: b.dataset.foto || "0", nsol: b.dataset.nsol || "0" };
  const temas = json("#cfg-temas") || [];
  const action = nuevo ? siteUrl("usuarios") : siteUrl("usuarios/" + t.e);
  const esMio = !nuevo && $(".hdr-user [data-e]")?.dataset.e === t.e;
  const m = modal(`<form class="ed" method="post" action="${action}" enctype="multipart/form-data">${csrfField()}
    <h2>${nuevo ? "Adicionar coordinador" : esMio && !esOp ? "Mi perfil" : "Editar perfil"}</h2>
    <div class="ed-top"><button type="button" class="av xl av-pick" id="e-av" title="Elegir foto"></button>
      <div class="ed-ph"><button type="button" class="btn ghost sm" id="e-pick">Elegir foto</button>
      <label class="btn ghost sm" id="e-nophoto-l" ${t.foto === "1" ? "" : "hidden"}><input type="checkbox" name="quitar_foto" value="1" id="e-nophoto"> Quitar foto</label>
      <input type="file" name="foto" id="e-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
      <p class="hint">Da clic en el círculo o en “Elegir foto”. Cualquier imagen; se recorta en círculo.</p></div></div>
    <div class="f"><span>Color del perfil</span><div class="sw" id="e-sw" role="radiogroup" aria-label="Color del perfil">
      ${temas.map(([n, c]) => `<button type="button" role="radio" data-c="${esc(c)}" title="${esc(n)}" aria-label="${esc(n)}" style="--c:${esc(c)}"></button>`).join("")}</div>
      <small class="swn" id="e-swn"></small><input type="hidden" name="tema" id="e-tema" value="${esc(t.tema)}"></div>
    ${esOp ? `<label class="f"><span>Nombre</span><input name="nombre" id="e-nom" value="${esc(t.nombre)}" placeholder="Ej. JULIO GALINDO"></label>
    ${t.rol !== "operador" ? `<label class="f"><span>Tipo de perfil</span><select name="rol" id="e-rol"><option value="coordinador" ${t.rol !== "viatico" ? "selected" : ""}>Coordinador (captura, ve y elimina solo lo suyo)</option><option value="viatico" ${t.rol === "viatico" ? "selected" : ""}>Coordinador viático (ve y edita todo, no elimina)</option></select></label>` : ""}
    <label class="f"><span>${nuevo ? "Contraseña" : "Nueva contraseña"}</span><input type="password" name="clave" id="e-p1" autocomplete="new-password" placeholder="${nuevo ? "" : "Déjala vacía para no cambiarla"}"></label>
    <label class="f"><span>Repite la contraseña</span><input type="password" name="clave2" id="e-p2" autocomplete="new-password"></label>`
      : `<p class="rl"><b>${esc(t.nombre)}</b><br>Tu contraseña solo la puede cambiar el operador.</p>`}
    <div class="err"></div>
    <div class="mbtns">${esOp && !nuevo && t.rol !== "operador" ? `<button type="button" class="btn ghost danger-t" id="e-del">Eliminar perfil</button><span class="sp"></span>` : ""}
      <button type="button" class="btn ghost" data-x>Cancelar</button><button type="submit" class="btn">${nuevo ? "Dar de alta" : "Guardar"}</button></div></form>`);
  let tema = t.tema || "", fotoNueva = null;
  const pintar = () => {
    const sel = tema || (temas[0] || [])[1];
    $$("#e-sw button", m.el).forEach(x => x.setAttribute("aria-checked", x.dataset.c === sel));
    m.q("#e-swn").textContent = ((temas.find(x => x[1] === sel) || temas[0] || [""])[0]) + (tema ? "" : " (predeterminado)");
    m.q("#e-tema").value = tema;
    const nph = m.q("#e-nophoto");
    m.q("#e-av").innerHTML = fotoNueva ? `<img src="${fotoNueva}" alt="">` : (nph?.checked ? `<span class="pi-w"></span>` : (b ? avatarEl(b.closest(".ucard") || b) || $("#side-me .av")?.innerHTML : ""));
  };
  m.q("#e-sw").addEventListener("click", ev => { const x = ev.target.closest("[data-c]"); if (x) { tema = x.dataset.c; pintar(); } });
  m.q("#e-av").onclick = m.q("#e-pick").onclick = () => m.q("#e-file").click();
  m.q("#e-file").onchange = ev => { const f = ev.target.files[0]; if (!f) return;
    if (f.size > 5 * 1024 * 1024) { shake(m, "La imagen pesa más de 5 MB"); ev.target.value = ""; return; }
    const fr = new FileReader(); fr.onload = () => { fotoNueva = fr.result; const n = m.q("#e-nophoto"); if (n) n.checked = false; pintar(); }; fr.readAsDataURL(f); };
  m.q("#e-nophoto")?.addEventListener("change", () => { fotoNueva = null; pintar(); });
  pintar();
  const del = m.q("#e-del");
  if (del) del.onclick = () => {
    if (!del.dataset.arm) { del.dataset.arm = 1; del.textContent = "¿Seguro? Clic otra vez"; del.classList.add("arm");
      m.q(".err").textContent = +t.nsol ? `Sus ${t.nsol} solicitudes NO se borran: seguirán visibles para el operador.` : ""; return; }
    const f = document.createElement("form"); f.method = "post"; f.action = siteUrl(`usuarios/${t.e}/eliminar`); f.innerHTML = csrfField(); document.body.appendChild(f); f.submit();
  };
  m.q("form").addEventListener("submit", e => {
    if (!esOp) return;
    const nom = m.q("#e-nom").value.trim(), a = m.q("#e-p1").value, c = m.q("#e-p2").value;
    if (!nom) { e.preventDefault(); return shake(m, "Escribe el nombre", m.q("#e-nom")); }
    if (nuevo && !a) { e.preventDefault(); return shake(m, "Asigna una contraseña", m.q("#e-p1")); }
    if (a && a.length < minClave) { e.preventDefault(); return shake(m, `La contraseña debe tener al menos ${minClave} caracteres`, m.q("#e-p1")); }
    if (a !== c) { e.preventDefault(); return shake(m, "Las contraseñas no coinciden", m.q("#e-p2")); }
  });
  setTimeout(() => (m.q("#e-nom") || m.q("form button[type=submit]")).focus(), 30);
}

document.addEventListener("click", ev => {
  const u = ev.target.closest(".ubtn"); if (u) { abrirLogin(u); return; }
  const e = ev.target.closest("[data-e]"); if (e && !e.closest("form")) { abrirEdicion(e); return; }
  if (ev.target.closest("#side-add")) { abrirEdicion(null); return; }
  if (ev.target.closest("#side-close")) closeDrawer();
  if (ev.target.closest(".hdr-user .av")) openDrawer();
});
function openDrawer() { if (document.body.classList.contains("drawer") || document.body.classList.contains("op")) return; document.body.classList.add("drawer");
  const sc = document.createElement("div"); sc.className = "drawer-scrim"; sc.id = "drawer-scrim"; sc.onclick = closeDrawer; document.body.appendChild(sc); }
function closeDrawer() { document.body.classList.remove("drawer"); $("#drawer-scrim")?.remove(); }
document.addEventListener("keydown", e => { if (e.key === "Escape" && document.body.classList.contains("drawer") && !$(".scrim")) closeDrawer(); });

/* Eliminar: se pide confirmar con un segundo clic */
document.addEventListener("submit", ev => {
  const f = ev.target.closest("form[data-confirmar]"); if (!f) return;
  const b = $("button", f);
  if (!f.dataset.arm) { ev.preventDefault(); f.dataset.arm = 1; b.textContent = "¿Confirmar?"; b.className = "btn danger sm"; setTimeout(() => { delete f.dataset.arm; b.textContent = "Eliminar"; b.className = "btn ghost sm"; }, 4000); }
});

/* ---------- mensajes del servidor ---------- */
const fl = $("#flash"); if (fl) toast(fl.dataset.msg);
const fe = $("#flash-error");
if (fe) { toast(fe.dataset.msg); const b = $(`.ubtn[data-u="${fe.dataset.abrir}"]`); if (b) { const m = abrirLogin(b); m?.q(".err") && (m.q(".err").textContent = fe.dataset.msg); } }

/* ---------- formulario: vista previa (el servidor valida de nuevo al guardar) ---------- */
const frm = $("#frm");
if (frm) {
  const cat = json("#catalogo-data") || { emp: [], cc: {} }, cfg = json("#cfg-form") || {};
  const byNum = new Map(cat.emp.map(([nombre, num, est, coord, reg]) => [String(num), { nombre, num: String(num), est, coord, reg }]));
  const sinAcento = t => String(t || "").normalize("NFD").replace(/[̀-ͯ]/g, "").trim().toUpperCase();
  const deLista = (v, lista) => { const k = sinAcento(v); return k ? (lista.find(x => sinAcento(x) === k) || "") : ""; };
  const MONEY = ["tranPublico", "taxis", "autobus", "comida", "hotel", "otros"];
  const num = id => { const v = parseFloat(String($("#" + id).value).replace(/[$,\s]/g, "")); return isNaN(v) ? 0 : v; };
  $("#lista-nominas").innerHTML = cat.emp.map(([n, c, est]) => `<option value="${esc(n + " - " + c)}"${est === "BAJA" ? ' label="BAJA"' : ""}>`).join("");
  $("#lista-clientes").innerHTML = Object.entries(cat.cc).map(([d, n]) => `<option value="${esc(d)}" label="CC ${esc(n)}">`).join("");
  const lookup = () => {
    const raw = $("#nomina").value.trim().toUpperCase(); if (!raw) return null;
    const m = raw.match(/-\s*(\S+)\s*$/), key = (m ? m[1] : raw).trim(), e = byNum.get(key);
    if (e) return { ...e, tipo: "INTERNO" };
    return { nombre: m ? raw.replace(/\s*-\s*\S+\s*$/, "") : "NO ESTA EN BASE", num: key, est: "", coord: "", reg: "", tipo: "EXTERNO (PROVEEDOR)" };
  };
  const setAuto = (id, v) => { const el = $(id); el.textContent = v || "—"; el.title = v || ""; el.classList.toggle("empty", !v); };
  const recalc = () => {
    const e = lookup();
    setAuto("#a-nombre", e?.nombre); setAuto("#a-coord", e?.coord); setAuto("#a-reg", e?.reg); setAuto("#a-est", e?.est); setAuto("#a-tipo", e?.tipo);
    const rev = (cfg.estatusRevisar || []).includes(e?.est);
    $("#a-est").classList.toggle("baja", rev);
    if (rev && recalc.aviso !== e.num) { recalc.aviso = e.num; toast(`Atención: ${e.nombre} está ${e.est === "BAJA" ? "dado de BAJA" : "en INCAPACIDAD"}. La solicitud se marcará REVISAR.`); }
    if (!rev) recalc.aviso = null;
    const c = deLista($("#cliente").value, Object.keys(cat.cc));
    setAuto("#a-cc", c ? String(cat.cc[c] ?? "SIN CC") : ($("#cliente").value.trim() ? "NO ESTÁ EN LISTA" : ""));
    $("#total").textContent = fmt(MONEY.reduce((s, k) => s + num(k), 0)); $("#a-total").textContent = $("#total").textContent;
    $("#wrap-just span").innerHTML = "JUSTIFICACION DE USO DE TAXI" + (num("taxis") > 0 ? ' <b class="req">*</b>' : "");
    $("#wrap-concepto span").innerHTML = "CONCEPTO DE OTROS" + (num("otros") > 0 ? ' <b class="req">*</b>' : "");
    $("#wrap-coment span").innerHTML = "COMENTARIOS" + (num("otros") > 0 ? ' <b class="req">*</b>' : "");
    const badJ = num("taxis") <= 0 && $("#justTaxi").value.trim() !== "";
    $("#justTaxi").style.borderColor = badJ ? "var(--req)" : ""; $("#justTaxi").title = badJ ? "No hay cantidad en TAXIS ($)" : "";
    setAuto("#a-ok", e ? (e.tipo === "INTERNO" && !(cfg.estatusRevisar || []).includes(e.est) ? "OK" : "REVISAR") : "");
  };
  frm.addEventListener("input", recalc); $("#cliente").addEventListener("change", recalc);
  $("#lf").addEventListener("click", ev => { const b = ev.target.closest("button"); if (!b) return; $("#lf-val").value = b.dataset.v; $$("#lf button").forEach(x => x.setAttribute("aria-pressed", x === b)); });
  ["traslado", "folio", "comentarios"].forEach(id => { const el = $("#" + id); if (!el) return;
    el.setAttribute("spellcheck", "true"); el.setAttribute("lang", "es-MX"); el.setAttribute("autocapitalize", "characters");
    el.addEventListener("input", () => { const a = el.selectionStart, b = el.selectionEnd, u = el.value.toUpperCase(); if (u !== el.value) { el.value = u; try { el.setSelectionRange(a, b); } catch (e) {} } }); });
  const box = $(".linebox");
  box?.addEventListener("focusin", ev => { const f = ev.target.closest(".f"); if (!f) return; const b = box.getBoundingClientRect(), r = f.getBoundingClientRect(), m = 60;
    if (r.right > b.right - m) box.scrollTo({ left: box.scrollLeft + (r.right - b.right) + m * 2, behavior: "smooth" });
    else if (r.left < b.left + m) box.scrollTo({ left: box.scrollLeft - (b.left - r.left) - m * 2, behavior: "smooth" }); });
  recalc();
}

/* ---------- correo ---------- */
const MAILCOLS = [["#", "n"], ["FECHA", "fecha"], ["CORDINADOR", "coordinador"], ["REGIONAL", "regional"], ["INGENIERO", "nombre"], ["NUMERO DE NOMINA", "nomina"], ["ESTATUS IDC", "estatus"], ["INTERNO O EXTERNO", "tipo"], ["FOLIO GP", "folioGP"], ["CLIENTE", "cliente"], ["CC", "cc"], ["L/F", "lf"], ["TRASLADO", "traslado"], ["TRAN PUBLICO ($)", "tranPublico", 1], ["TAXIS ($)", "taxis", 1], ["JUSTIFICACION DE USO DE TAXI", "justTaxi"], ["AUTOBÚS ($)", "autobus", 1], ["COMIDA ($)", "comida", 1], ["HOTEL ($)", "hotel", 1], ["OTROS ($)", "otros", 1], ["CONCEPTO DE OTROS", "conceptoOtros"], ["COMENTARIOS", "comentarios"], ["VIATICO $", "total", 1], ["OK", "ok"]];
const YEL = new Set(["lf", "traslado", "tranPublico", "taxis", "autobus", "comida", "hotel", "otros", "comentarios"]);
function mailTable(list) {
  const th = 'nowrap style="background:#1F4E79;color:#ffffff;font-family:Tahoma,Arial,sans-serif;font-size:9pt;font-weight:bold;padding:5px 8px;border:1px solid #163a5c;text-align:center;white-space:nowrap"';
  const td = r => `nowrap style="font-family:Tahoma,Arial,sans-serif;font-size:9pt;padding:4px 8px;border:1px solid #bfbfbf;white-space:nowrap;${r ? "text-align:right;" : ""}"`;
  const val = (r, k, m) => k === "fecha" ? dmy(r.fecha) : m ? (r[k] ? fmt(Number(r[k])) : "") : esc(r[k] ?? "");
  const tot = list.reduce((s, r) => s + (Number(r.total) || 0), 0);
  return `<table cellspacing="0" cellpadding="0" style="border-collapse:collapse"><thead><tr>${MAILCOLS.map(c => `<th ${YEL.has(c[1]) ? th.replace("background:#1F4E79;color:#ffffff", "background:#FFFF99;color:#000000") : th}>${c[0]}</th>`).join("")}</tr></thead><tbody>` +
    list.map(r => `<tr>${MAILCOLS.map(([, k, m]) => `<td ${td(m || k === "n")}>${val(r, k, m)}</td>`).join("")}</tr>`).join("") +
    `<tr><td colspan="${MAILCOLS.length - 2}" ${td(1)}><b>TOTAL (${list.length})</b></td><td ${td(1)}><b>${fmt(tot)}</b></td><td ${td()}></td></tr></tbody></table>`;
}
const mailText = list => list.map(r => `#${r.n} | ${dmy(r.fecha)} | ${r.nombre} - ${r.nomina} | ${r.cliente} (CC ${r.cc}) | ${r.lf} | ${fmt(Number(r.total) || 0)} | ${r.ok}`).join("\n");
async function copyHtml(html, text) {
  try { if (window.ClipboardItem && navigator.clipboard?.write) { await navigator.clipboard.write([new ClipboardItem({ "text/html": new Blob([html], { type: "text/html" }), "text/plain": new Blob([text], { type: "text/plain" }) })]); return true; } } catch (e) {}
  try { const box = document.createElement("div"); box.contentEditable = "true"; box.style.cssText = "position:fixed;left:-9999px;top:0"; box.innerHTML = html; document.body.appendChild(box);
    const rg = document.createRange(); rg.selectNodeContents(box); const s = getSelection(); s.removeAllRanges(); s.addRange(rg);
    const ok = document.execCommand("copy"); s.removeAllRanges(); box.remove(); return ok; } catch (e) { return false; }
}
async function enviarCorreo(list, to, msgEl) {
  to = to.trim();
  if (!to) { toast("Escribe el correo destino"); return false; }
  if (!list.length) { toast("No hay solicitudes para enviar"); return false; }
  const fechas = [...new Set(list.map(r => r.fecha))].sort(), tot = list.reduce((s, r) => s + (Number(r.total) || 0), 0);
  const rango = fechas.length === 1 ? dmy(fechas[0]) : dmy(fechas[0]) + " al " + dmy(fechas[fechas.length - 1]);
  const n = list.length, s = n > 1 ? "es" : "";
  const subject = `Solicitud de viáticos ${rango} (${n} solicitud${s})`;
  const html = `<p style="font-family:Tahoma,Arial,sans-serif;font-size:10pt">Buen día,<br><br>Se envían las solicitudes de viáticos del ${rango}: ${n} solicitud${s}, total ${fmt(tot)}.</p>` + mailTable(list) + `<p style="font-family:Tahoma,Arial,sans-serif;font-size:10pt">Saludos.</p>`;
  const copied = await copyHtml(html, mailText(list));
  const body = copied ? "" : `Buen día,\n\nSe envían las solicitudes de viáticos del ${rango}: ${n} solicitud${s}, total ${fmt(tot)}.\n\n${mailText(list)}\n\nSaludos.`;
  const a = document.createElement("a");
  a.href = `mailto:${to.split(/[,;\s]+/).filter(Boolean).join(",")}?subject=${encodeURIComponent(subject)}` + (body ? `&body=${encodeURIComponent(body)}` : "");
  document.body.appendChild(a); a.click(); a.remove();
  msgEl.hidden = false;
  msgEl.textContent = copied ? "Se abrió tu correo con el destinatario y el asunto. Haz clic en el cuerpo del correo y pega con Ctrl+V: se pega el saludo, la tabla y la despedida." : "Se abrió tu correo con las solicitudes en el cuerpo. No se pudo copiar la tabla con formato; si falta algo, pégala desde el portapapeles.";
  return true;
}
function ofrecerMarcar(msgEl, list, to) {
  const ids = list.filter(r => !r.enviado).map(r => r.id); if (!ids.length) return;
  const b = document.createElement("button"); b.type = "button"; b.className = "btn sm";
  b.textContent = `✓ Ya lo envié: pasar ${ids.length === 1 ? "la solicitud" : "las " + ids.length + " solicitudes"} al concentrado`;
  b.onclick = async () => { b.disabled = true;
    try { await post(siteUrl("solicitudes/marcar-enviadas"), { ids, enviado_a: to.trim() }); location.href = siteUrl("solicitudes"); }
    catch (e) { b.disabled = false; toast(e.message); } };
  msgEl.append(document.createElement("br"), b);
}
const mailData = json("#mail-data") || [];
const guardarCorreo = inp => { const v = inp.value.trim();
  $$("[data-guardar-correo],#mail-to").forEach(x => { if (x !== inp) x.value = v; });
  post(siteUrl("usuarios/correo"), { correo: v }).catch(() => {}); };
$$("[data-guardar-correo]").forEach(i => i.addEventListener("change", () => guardarCorreo(i)));

/* "Por enviar" (pantalla Nueva solicitud) */
const hBody = $("#h-body");
if (hBody) {
  const off = new Set();
  const marcadas = () => mailData.filter(r => !off.has(r.id));
  const info = () => { const mk = marcadas(), t = mk.reduce((s, r) => s + (Number(r.total) || 0), 0);
    $("#h-info").innerHTML = !mailData.length ? "No hay solicitudes por enviar." : !mk.length ? "No hay ninguna marcada: marca las que quieras enviar."
      : `Se enviarán <b>${mk.length}</b> de ${mailData.length} solicitud${mailData.length > 1 ? "es" : ""} (marcadas) · Total <b>${fmt(t)}</b>`;
    $("#h-send").disabled = !mk.length;
    const all = $("#h-ck-all"); all.checked = mailData.length > 0 && mk.length === mailData.length; all.indeterminate = mk.length > 0 && mk.length < mailData.length;
    $$("tr", hBody).forEach(tr => tr.classList.toggle("off", off.has(+tr.dataset.id))); };
  hBody.addEventListener("change", ev => { const c = ev.target.closest("[data-hck]"); if (!c) return; c.checked ? off.delete(+c.dataset.hck) : off.add(+c.dataset.hck); info(); });
  $("#h-ck-all").addEventListener("change", ev => { off.clear(); if (!ev.target.checked) mailData.forEach(r => off.add(r.id)); $$("[data-hck]", hBody).forEach(c => c.checked = ev.target.checked); info(); });
  $("#h-send").onclick = async () => { const l = marcadas(), to = $("#h-to").value; if (await enviarCorreo(l, to, $("#h-msg"))) ofrecerMarcar($("#h-msg"), l, to); };
  $("#btn-gomail").onclick = () => { $("#h-send").click(); $("#h-msg").scrollIntoView({ block: "center", behavior: "smooth" }); };
  info();
}

/* ---------- concentrado ---------- */
const tbody = $("#tbody");
if (tbody) {
  const filtros = $("#filtros");
  $$("input,select", filtros).forEach(el => el.addEventListener("change", () => filtros.submit()));
  let tq; $("#f-q")?.addEventListener("input", () => { clearTimeout(tq); tq = setTimeout(() => filtros.submit(), 600); });
  $("#quick").addEventListener("click", ev => { const b = ev.target.closest("button.chip"); if (!b) return;
    const t = new Date(todayISO() + "T12:00:00"), iso = d => d.toISOString().slice(0, 10); let a = "", z = "";
    if (b.dataset.q === "hoy") a = z = iso(t); else if (b.dataset.q === "ayer") { t.setDate(t.getDate() - 1); a = z = iso(t); }
    else if (b.dataset.q === "semana") { const d = new Date(t); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); a = iso(d); z = iso(t); }
    else if (b.dataset.q === "mes") { a = iso(t).slice(0, 8) + "01"; z = iso(t); }
    $("#f-desde").value = a; $("#f-hasta").value = z; filtros.submit(); });
  const desde = $("#f-desde").value, hasta = $("#f-hasta").value; if (desde || hasta) {
    const t = new Date(todayISO() + "T12:00:00"), iso = d => d.toISOString().slice(0, 10), y = new Date(t); y.setDate(y.getDate() - 1);
    const sem = new Date(t); sem.setDate(sem.getDate() - ((sem.getDay() + 6) % 7));
    const mapa = { hoy: [iso(t), iso(t)], ayer: [iso(y), iso(y)], semana: [iso(sem), iso(t)], mes: [iso(t).slice(0, 8) + "01", iso(t)] };
    for (const [q, [a, z]] of Object.entries(mapa)) if (a === desde && z === hasta) $(`.chip[data-q="${q}"]`)?.setAttribute("aria-pressed", "true"); }

  const sel = new Set();
  const lista = () => sel.size ? mailData.filter(r => sel.has(r.id)) : mailData;
  const infoMail = () => { const l = lista(), t = l.reduce((s, r) => s + (Number(r.total) || 0), 0);
    $("#mail-info").innerHTML = l.length ? `Se enviarán <b>${l.length}</b> solicitud${l.length > 1 ? "es" : ""} (${sel.size ? "marcadas" : "del filtro actual"}) · Total <b>${fmt(t)}</b>` : "Marca solicitudes o usa los filtros (por ejemplo “Hoy”) para elegir qué enviar."; };
  tbody.addEventListener("change", ev => { const c = ev.target.closest("[data-ck]"); if (!c) return; c.checked ? sel.add(+c.dataset.ck) : sel.delete(+c.dataset.ck); c.closest("tr").classList.toggle("sel", c.checked);
    const ca = $("#ck-all"); ca.checked = mailData.length > 0 && mailData.every(r => sel.has(r.id)); ca.indeterminate = !ca.checked && sel.size > 0; infoMail(); });
  $("#ck-all").addEventListener("change", ev => { mailData.forEach(r => ev.target.checked ? sel.add(r.id) : sel.delete(r.id)); $$("[data-ck]", tbody).forEach(c => { c.checked = ev.target.checked; c.closest("tr").classList.toggle("sel", c.checked); }); infoMail(); });
  $("#btn-mail").onclick = async () => { const l = lista(), to = $("#mail-to").value; if (await enviarCorreo(l, to, $("#mail-msg"))) ofrecerMarcar($("#mail-msg"), l, to); };
  infoMail();

  /* depósito real: se guarda en el servidor y se actualiza solo esa fila */
  const pctClase = p => p == null ? "" : p < 99.95 ? "bajo" : p > 100.05 ? "alto" : "exacto";
  const pctTxt = p => p == null ? "" : (Math.round(p * 10) / 10).toLocaleString("es-MX") + "%";
  function pintarFila(tr, d) {
    tr.dataset.est = d.estado; tr.dataset.real = d.real_dep == null ? "" : Number(d.real_dep).toFixed(2);
    $$("[data-dc]", tr).forEach(b => b.setAttribute("aria-pressed", b.dataset.dc === d.estado));
    const i = $("[data-rd]", tr), falta = d.estado === "no" && tr.dataset.real === "";
    i.disabled = d.estado !== "no"; i.value = tr.dataset.real; i.placeholder = d.estado === "no" ? "Obligatorio" : "—"; i.classList.toggle("falta", falta);
    i.title = d.estado ? "" : "Primero elige Sí o No"; falta ? i.setAttribute("aria-invalid", "true") : i.removeAttribute("aria-invalid");
    i.parentElement.querySelector(".falta-t")?.remove(); if (falta) i.insertAdjacentHTML("afterend", '<div class="falta-t">Escribe la cantidad</div>');
    const p = $(".pct", tr); p.className = "num mono pct " + pctClase(d.pct); p.textContent = pctTxt(d.pct);
    pie();
  }
  function pie() {
    const rows = $$("tr[data-id]", tbody); let si = 0, no = 0, sd = 0, st = 0, sumReal = 0;
    rows.forEach(tr => { if (tr.dataset.est === "si") si++; if (tr.dataset.est === "no") no++;
      if (tr.dataset.real !== "") { sd += +tr.dataset.real; st += +tr.dataset.total; sumReal += +tr.dataset.real; } });
    if ($("#pf-sino")) { $("#pf-sino").textContent = si || no ? `Sí ${si} · No ${no}` : ""; $("#pf-real").textContent = fmt(sumReal);
      const pp = st ? sd / st * 100 : null; $("#pf-pct").className = "num mono pct " + pctClase(pp); $("#pf-pct").textContent = pctTxt(pp); }
    const faltan = rows.filter(tr => tr.dataset.est === "no" && tr.dataset.real === "").length, s = $("#save-st");
    s.textContent = faltan ? `Faltan ${faltan} cantidad${faltan > 1 ? "es" : ""}` : s.textContent; s.classList.toggle("warn", faltan > 0);
  }
  tbody.addEventListener("click", async ev => { const b = ev.target.closest("[data-dc]"); if (!b) return; const tr = b.closest("tr");
    try { const d = await post(siteUrl(`solicitudes/${tr.dataset.id}/deposito`), { accion: b.dataset.dc }); pintarFila(tr, d);
      if (b.dataset.dc === "no") { const i = $("[data-rd]", tr); i.focus(); i.select(); toast("Escribe la cantidad que sí se depositó"); } }
    catch (e) { toast(e.message); } });
  tbody.addEventListener("change", async ev => { const i = ev.target.closest("[data-rd]"); if (!i) return; const tr = i.closest("tr");
    const t = i.value.trim().replace(/[$,\s]/g, ""); if (t !== "" && (isNaN(parseFloat(t)) || parseFloat(t) < 0)) { toast("Escribe una cantidad válida"); i.value = tr.dataset.real; return; }
    try { const d = await post(siteUrl(`solicitudes/${tr.dataset.id}/deposito`), { accion: "monto", valor: t }); pintarFila(tr, d);
      if (t === "" && d.estado === "no") toast("Marcaste que NO se depositó completo: falta escribir la cantidad"); }
    catch (e) { toast(e.message); i.value = tr.dataset.real; } });
  tbody.addEventListener("keydown", ev => { const i = ev.target.closest("[data-rd]"); if (!i || (ev.key !== "Enter" && ev.key !== "Tab")) return; ev.preventDefault();
    const all = $$("[data-rd]:not(:disabled)", tbody), nx = all[all.indexOf(i) + (ev.shiftKey ? -1 : 1)];
    i.blur(); if (nx) setTimeout(() => { nx.focus(); nx.select(); }, 80); });
  $("#btn-guardar").onclick = () => { const a = document.activeElement; if (a?.matches?.("[data-rd]")) a.blur();
    setTimeout(() => { const f = $$("tr[data-id]", tbody).filter(tr => tr.dataset.est === "no" && tr.dataset.real === ""), s = $("#save-st");
      if (f.length) { s.textContent = `Faltan ${f.length} cantidad${f.length > 1 ? "es" : ""}`; s.classList.add("warn");
        toast(`Guardado, pero falta${f.length > 1 ? "n" : ""} ${f.length} cantidad${f.length > 1 ? "es" : ""} depositada${f.length > 1 ? "s" : ""} (marcadas en rojo)`);
        const i = $("[data-rd]", f[0]); i.scrollIntoView({ block: "center", inline: "center", behavior: "smooth" }); setTimeout(() => i.focus({ preventScroll: true }), 300); }
      else { s.textContent = "✓ Guardado " + new Date().toLocaleTimeString("es-MX", { hour: "2-digit", minute: "2-digit" }); s.classList.remove("warn"); toast("Datos guardados"); } }, 300); };
}

/* ---------- indicadores: las fechas aplican al cambiar ---------- */
const kd = $("#k-desde"); if (kd) $$("#k-desde,#k-hasta").forEach(i => i.addEventListener("change", () => i.form.submit()));
})();

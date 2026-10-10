import { createClient } from '@supabase/supabase-js';

const supabase = createClient(
  import.meta.env.VITE_SUPABASE_URL,
  import.meta.env.VITE_SUPABASE_ANON_KEY
);

const root = document.getElementById('app');
let session = null;
let profile = null;

const esc = (v = '') => String(v).replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
const money = (v) => `₱${Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const todayStr = () => new Date().toISOString().slice(0, 10);
const fmtDate = (d) => { if (!d) return '—'; const dt = new Date(d); return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); };
const fmtDateTime = (d) => { if (!d) return '—'; const dt = new Date(d); return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' · ' + dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }); };
const daysLeft = (expires) => { const diff = Math.ceil((new Date(expires) - new Date(todayStr() + 'T00:00:00')) / 86400000); return diff; };
const membershipStatus = (starts, expires) => {
  const t = todayStr();
  if (starts > t) return 'Upcoming';
  if (expires < t) return 'Expired';
  const d = daysLeft(expires);
  if (d <= 3) return 'Expiring';
  return 'Active';
};

function layout(content) {
  root.innerHTML = `
    <header class="site-header">
      <a class="brand" href="#"><img class="brand-logo" src="/public/images/dfitness-logo.png" alt="D'fitness Gym" width="46" height="46"><span>D'fitness <strong>Gym</strong></span></a>
      <nav class="header-actions">
        ${session ? `<span class="header-note">${esc(profile?.name || '')}</span><button class="header-link header-logout" id="logout-btn">Logout</button>` : ''}
      </nav>
    </header>
    <main>${content}</main>
    <footer class="site-footer">D'fitness Gym <span>·</span> Stronger together</footer>`;
  document.getElementById('logout-btn')?.addEventListener('click', async () => {
    await supabase.auth.signOut();
    session = null; profile = null;
    renderAuth();
  });
}

function notice(msg) {
  if (!msg) return '';
  return `<div class="notice ${msg.type}" role="${msg.type === 'error' ? 'alert' : 'status'}">${esc(msg.text)}</div>`;
}

function tableWrap(headers, rows, emptyMsg) {
  return `<div class="table-wrap"><table><thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.length ? rows.join('') : `<tr><td colspan="${headers.length}" style="color:var(--muted)">${emptyMsg}</td></tr>`}</tbody></table></div>`;
}

function statusPill(status) {
  const cls = status.toLowerCase().replace(/[^a-z]/g, '');
  return `<span class="status-pill ${cls}">${esc(status)}</span>`;
}

// ===== AUTH =====
function renderAuth(msg = '') {
  layout(`
    <section class="auth-layout">
      <div class="intro-panel login-intro">
        <div class="eyebrow"><span class="line"></span> YOUR SPACE TO GROW</div>
        <h1>DARIUS<br>FITNESS GYM<span class="accent">.</span></h1>
        <p>A fitness place where you can truly experience physical transformation after just a few months</p>
        <div class="intro-rule"></div>
        <div class="intro-caption"><span class="caption-number">01 / 03</span><span>SHOW UP. PUT IN THE WORK. GROW STRONGER.</span></div>
      </div>
      <div class="form-panel">
        <div class="form-topline">ACCOUNT ACCESS <span>01</span></div>
        <div class="form-heading"><p class="kicker">D'FITNESS GYM</p><h2 id="auth-title">Sign in</h2><p id="auth-sub">Enter your account details to continue.</p></div>
        ${notice(msg)}
        <form id="auth-form">
          <label for="email">Email address</label>
          <input id="email" type="email" name="email" required autocomplete="email" placeholder="you@example.com">
          <div id="reg-fields" hidden>
            <label for="name">Full name</label>
            <input id="name" name="name" autocomplete="name" placeholder="Your full name">
            <label for="phone">Mobile number (optional)</label>
            <input id="phone" type="tel" name="phone" placeholder="09XXXXXXXXX" autocomplete="tel">
            <label class="check-row"><input type="checkbox" name="sms_consent" value="1"><span>I agree to receive membership renewal reminders by SMS.</span></label>
          </div>
          <label for="password">Password</label>
          <input id="password" type="password" name="password" required minlength="8" autocomplete="current-password" placeholder="At least 8 characters">
          <button class="primary-button" type="submit" id="auth-submit">Sign in <span>→</span></button>
        </form>
        <div class="form-divider"><span>NEW TO D'FITNESS?</span></div>
        <button class="secondary-button" id="auth-switch" style="width:100%">Create a member account <span>→</span></button>
        <p class="fine-print">Member registrations are reviewed by the gym admin before access is granted.</p>
        <p class="fine-print">Forgot your password? <a href="#" id="reset-link">Request a reset from the gym admin</a></p>
      </div>
    </section>`);
  let isSignUp = false;
  const form = document.getElementById('auth-form');
  const sw = document.getElementById('auth-switch');
  const title = document.getElementById('auth-title');
  const sub = document.getElementById('auth-sub');
  const regFields = document.getElementById('reg-fields');
  const submit = document.getElementById('auth-submit');
  sw.addEventListener('click', () => {
    isSignUp = !isSignUp;
    title.textContent = isSignUp ? 'Create account' : 'Sign in';
    sub.textContent = isSignUp ? 'Complete the details below to request access.' : 'Enter your account details to continue.';
    submit.innerHTML = isSignUp ? 'Submit registration <span>→</span>' : 'Sign in <span>→</span>';
    sw.innerHTML = isSignUp ? 'Already have an account? Sign in <span>→</span>' : 'Create a member account <span>→</span>';
    regFields.hidden = !isSignUp;
    document.getElementById('name').required = isSignUp;
    document.getElementById('password').autocomplete = isSignUp ? 'new-password' : 'current-password';
  });
  document.getElementById('reset-link').addEventListener('click', (e) => { e.preventDefault(); renderResetRequest(); });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    submit.disabled = true;
    if (isSignUp) {
      const { error } = await supabase.auth.signUp({
        email: fd.get('email'),
        password: fd.get('password'),
        options: { data: { name: fd.get('name') || 'New member', phone: fd.get('phone') || '', sms_consent: fd.get('sms_consent') ? true : false } }
      });
      submit.disabled = false;
      if (error) renderAuth({ type: 'error', text: error.code === '23505' ? 'That email is already registered.' : 'Could not create the account. Please try again.' });
      else renderAuth({ type: 'success', text: 'Registration submitted. You can sign in after an admin approves your account.' });
    } else {
      const { data, error } = await supabase.auth.signInWithPassword({ email: fd.get('email'), password: fd.get('password') });
      submit.disabled = false;
      if (error) renderAuth({ type: 'error', text: 'The email or password is incorrect.' });
      else { session = data.session; await loadProfile(); }
    }
  });
}

// ===== PASSWORD RESET REQUEST =====
function renderResetRequest() {
  layout(`
    <section class="auth-layout">
      <div class="intro-panel"><div class="eyebrow"><span class="line"></span> ACCOUNT RECOVERY</div><h1>Request a reset<span class="accent">.</span></h1><p>Submit a request for the gym admin to review. If approved, you will receive a one-time code.</p></div>
      <div class="form-panel">
        <div class="form-heading"><p class="kicker">D'FITNESS GYM</p><h2>Password reset request</h2><p>Enter your username and a message for the admin.</p></div>
        <div id="reset-msg"></div>
        <form id="reset-form">
          <label for="username">Username</label>
          <input id="username" name="username" required placeholder="Your username">
          <label for="message">Message</label>
          <textarea name="message" required minlength="10" placeholder="Explain your situation so the admin can verify your identity" style="width:100%;min-height:120px;border:1px solid #ded1c5;border-radius:5px;padding:14px 15px;font:inherit;resize:vertical;margin-bottom:22px"></textarea>
          <button class="primary-button" type="submit">Submit request <span>→</span></button>
        </form>
        <p class="fine-print"><a href="#" id="back-to-login">Back to sign in</a></p>
      </div>`);
  document.getElementById('back-to-login').addEventListener('click', (e) => { e.preventDefault(); renderAuth(); });
  document.getElementById('reset-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const { error } = await supabase.from('appeals').insert({ username: fd.get('username'), message: fd.get('message') });
    const msg = document.getElementById('reset-msg');
    if (error) msg.innerHTML = notice({ type: 'error', text: 'Could not submit the request. Please try again.' });
    else msg.innerHTML = notice({ type: 'success', text: 'Password reset request submitted. Ask the gym admin to review it.' });
  });
}

// ===== LOAD PROFILE =====
async function loadProfile() {
  if (!session) return renderAuth();
  const { data, error } = await supabase.from('profiles').select('*').eq('id', session.user.id).maybeSingle();
  if (error || !data) { await supabase.auth.signOut(); session = null; profile = null; return renderAuth({ type: 'error', text: 'Your account profile is still being prepared. Please try again.' }); }
  profile = data;
  if (profile.approval_status !== 'approved' && profile.role !== 'admin') {
    const msg = profile.approval_status === 'rejected' ? 'Your registration was rejected. Contact the gym admin.' : 'Your account is awaiting gym admin approval.';
    await supabase.auth.signOut(); session = null; profile = null;
    return renderAuth({ type: 'error', text: msg });
  }
  renderDashboard();
}

// ===== DASHBOARD ROUTER =====
async function renderDashboard() {
  if (profile.role === 'admin') await renderAdmin();
  else await renderMember();
}

// ===== MEMBER DASHBOARD =====
async function renderMember() {
  const [memRes, attRes, payRes] = await Promise.all([
    supabase.from('memberships').select('*, membership_plans(name,price,duration_days)').eq('user_id', profile.id).order('expires_at', { ascending: false }),
    supabase.from('attendance').select('*').eq('user_id', profile.id).order('checked_in_at', { ascending: false }).limit(20),
    supabase.from('payments').select('*, memberships(membership_plans(name))').eq('user_id', profile.id).order('paid_at', { ascending: false }).limit(20),
  ]);
  const memberships = memRes.data || [];
  const attendance = attRes.data || [];
  const payments = payRes.data || [];
  const current = memberships.find(m => { const s = membershipStatus(m.starts_at, m.expires_at); return s === 'Active' || s === 'Expiring'; });
  const latest = memberships[0];
  const status = current ? membershipStatus(current.starts_at, current.expires_at) : (latest ? membershipStatus(latest.starts_at, latest.expires_at) : 'No membership');
  const dLeft = current ? daysLeft(current.expires_at) : null;

  layout(`
    <section class="dashboard">
      <div class="dashboard-head"><div><p class="kicker">MEMBER AREA</p><h1>Welcome, ${esc(profile.name)}.</h1><p>Your D'fitness Gym account is ready.</p></div></div>

      <h2>Membership status</h2>
      <div class="membership-summary">
        ${statusPill(status)}
        <h3>${esc(current?.membership_plans?.name || latest?.membership_plans?.name || 'Get started')}</h3>
        ${current ? `<p>Expires on ${fmtDate(current.expires_at)}.</p><strong class="membership-days-left">${dLeft === 0 ? 'Expires today' : dLeft + ' day' + (dLeft === 1 ? '' : 's') + ' left'}</strong>` : latest ? `<p>${status === 'Expired' ? 'Expired on' : 'Starts on'} ${fmtDate(status === 'Expired' ? latest.expires_at : latest.starts_at)}.</p>` : '<p>Ask the gym admin to assign a membership plan to your account.</p>'}
      </div>

      <h2>Your member QR code</h2>
      <div class="membership-summary member-qr-card">
        <div class="member-qr-image"><img src="/public/images/dfitness-logo.png" alt="Member QR" width="260" height="260"></div>
        <div class="member-qr-details">
          <p class="kicker">GYM MEMBER ID</p>
          <h3>${esc(profile.name)}</h3>
          <p class="member-qr-username">@${esc(profile.username)}</p>
          <p class="member-qr-instructions">Show this QR code to the gym admin when you arrive and when you leave. Each scan records your check-in or check-out.</p>
          <p style="margin:12px 0;font-size:11px;color:var(--muted);word-break:break-all">DG:${esc(profile.qr_token)}</p>
          <div class="member-qr-meta"><span>Membership</span><strong>${esc(current?.membership_plans?.name || latest?.membership_plans?.name || 'Not assigned')}</strong></div>
          <div class="member-qr-meta"><span>Status</span><strong>${esc(status)}</strong></div>
          ${current ? `<div class="member-qr-meta"><span>Expires</span><strong>${fmtDate(current.expires_at)} · ${dLeft === 0 ? 'today' : dLeft + ' day' + (dLeft === 1 ? '' : 's') + ' left'}</strong></div>` : ''}
        </div>
      </div>

      <h2>Attendance history</h2>
      ${tableWrap(['Date', 'Checked in', 'Checked out'], attendance.map(a => `<tr><td>${fmtDate(a.checked_in_at)}</td><td>${fmtDateTime(a.checked_in_at)}</td><td>${a.checked_out_at ? fmtDateTime(a.checked_out_at) : '—'}</td></tr>`), 'No attendance yet.')}

      <h2>Payment history</h2>
      ${tableWrap(['Date', 'Plan', 'Amount', 'Status'], payments.map(p => `<tr><td>${fmtDate(p.paid_at)}</td><td>${esc(p.memberships?.membership_plans?.name || '—')}</td><td>${money(p.amount)}</td><td>Received</td></tr>`), 'No payments recorded yet.')}

      <h2>SMS renewal reminders</h2>
      <div class="management-card contact-card">
        <p class="card-help">Add a Philippine mobile number and choose whether to receive a reminder three days before your membership expires.</p>
        <div id="contact-msg"></div>
        <form id="contact-form">
          <label for="contact-phone">Mobile number</label>
          <input id="contact-phone" type="tel" name="phone" value="${esc(profile.phone || '')}" placeholder="09XXXXXXXXX">
          <label class="check-row"><input type="checkbox" name="sms_consent" value="1" ${profile.sms_consent ? 'checked' : ''}><span>I agree to receive membership renewal reminders by SMS.</span></label>
          <button class="primary-button" type="submit">Save preferences</button>
        </form>
      </div>
    </section>`);

  document.getElementById('contact-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const phone = fd.get('phone') || null;
    const consent = !!fd.get('sms_consent');
    const { error } = await supabase.from('profiles').update({ phone, sms_consent: consent }).eq('id', profile.id);
    const msg = document.getElementById('contact-msg');
    if (error) msg.innerHTML = notice({ type: 'error', text: 'Could not save preferences. Please try again.' });
    else { msg.innerHTML = notice({ type: 'success', text: 'Preferences saved.' }); profile.phone = phone; profile.sms_consent = consent; }
  });
}

// ===== ADMIN DASHBOARD =====
async function renderAdmin() {
  const [memRes, planRes, attRes, payRes, profileRes] = await Promise.all([
    supabase.from('memberships').select('*, profiles(name,username), membership_plans(name,price)').order('expires_at', { ascending: false }).limit(20),
    supabase.from('membership_plans').select('*').order('price'),
    supabase.from('attendance').select('*, profiles(name,username)').order('checked_in_at', { ascending: false }).limit(20),
    supabase.from('payments').select('*, profiles(name,username), memberships(membership_plans(name))').order('paid_at', { ascending: false }).limit(20),
    supabase.from('profiles').select('*').eq('role', 'member').order('created_at', { ascending: false }),
  ]);
  const memberships = memRes.data || [];
  const plans = planRes.data || [];
  const attendance = attRes.data || [];
  const payments = payRes.data || [];
  const allProfiles = profileRes.data || [];
  const pending = allProfiles.filter(p => p.approval_status === 'pending');
  const approved = allProfiles.filter(p => p.approval_status === 'approved');
  const todayCount = attendance.filter(a => a.checked_in_at.slice(0, 10) === todayStr()).length;
  const todayTotal = payments.filter(p => p.paid_at.slice(0, 10) === todayStr()).reduce((s, p) => s + Number(p.amount), 0);
  const weekStart = new Date(); weekStart.setDate(weekStart.getDate() - weekStart.getDay());
  const weekTotal = payments.filter(p => new Date(p.paid_at) >= weekStart).reduce((s, p) => s + Number(p.amount), 0);
  const monthStart = new Date(); monthStart.setDate(1);
  const monthTotal = payments.filter(p => new Date(p.paid_at) >= monthStart).reduce((s, p) => s + Number(p.amount), 0);

  layout(`
    <section class="dashboard admin-dashboard">
      <div class="admin-dashboard-top">
        <div><p class="kicker">ADMIN WORKSPACE</p><h1>Gym dashboard</h1><p>Welcome, ${esc(profile.name)}. Manage your gym from one place.</p></div>
      </div>
      <div id="admin-msg"></div>

      <div class="admin-overview">
        <article class="admin-overview-card"><span>REGISTERED MEMBERS</span><strong>${approved.length}</strong><p>Members with access to the gym system</p></article>
        <section class="admin-scanner-card">
          <div class="admin-scanner-heading"><div><p class="kicker">MEMBER ATTENDANCE</p><p class="admin-scanner-help">Scan a member's QR code to record check-in or check-out.</p></div><p><strong>${todayCount}</strong> check-ins today</p></div>
          <button class="primary-button admin-scanner-launch" id="open-scanner">Open QR code scanner</button>
        </section>
      </div>

      <div class="stats" style="grid-template-columns:repeat(4,1fr)">
        <article><span>PENDING</span><strong>${pending.length}</strong></article>
        <article><span>TODAY</span><strong>${money(todayTotal)}</strong></article>
        <article><span>THIS WEEK</span><strong>${money(weekTotal)}</strong></article>
        <article><span>THIS MONTH</span><strong>${money(monthTotal)}</strong></article>
      </div>

      <h2>Pending registrations</h2>
      ${tableWrap(['Member', 'Username', 'Registered', 'Action'], pending.map(m => `<tr><td><strong>${esc(m.name)}</strong></td><td>@${esc(m.username)}</td><td>${fmtDate(m.created_at)}</td><td><div class="registration-actions"><button class="approve-button" data-approve="${m.id}">Approve</button><button class="reject-button" data-reject="${m.id}">Reject</button></div></td></tr>`), 'No registrations are awaiting approval.')}

      <h2>Assign membership</h2>
      <div class="management-card">
        <form id="membership-form">
          <div class="management-grid membership-management-grid">
            <div>
              <label for="ms-user">Approved member</label>
              <select id="ms-user" name="user_id" required>
                <option value="">Select member</option>
                ${approved.map(m => `<option value="${m.id}">${esc(m.name)} (@${esc(m.username)})</option>`).join('')}
              </select>
              <label for="ms-plan">Plan</label>
              <select id="ms-plan" name="plan_id" required>
                ${plans.map(p => `<option value="${p.id}">${esc(p.name)} · ${money(p.price)}</option>`).join('')}
              </select>
              <label for="ms-start">Start date</label>
              <input id="ms-start" type="date" name="starts_at" value="${todayStr()}" required>
            </div>
            <div class="membership-records-card">
              <label>Recent memberships</label>
              ${tableWrap(['Member', 'Plan', 'Dates', 'Payment'], memberships.map(m => `<tr><td>${esc(m.profiles?.name || 'Former member')}</td><td>${esc(m.membership_plans?.name || '')}</td><td>${esc(m.starts_at)} – ${esc(m.expires_at)}</td><td>${money(m.membership_plans?.price)}</td></tr>`), 'No memberships yet.')}
            </div>
          </div>
          <button class="primary-button" type="submit" style="margin-top:20px;max-width:300px">Save membership and payment</button>
        </form>
      </div>

      <h2>Recent payments</h2>
      ${tableWrap(['Member', 'Amount', 'Date', 'Plan'], payments.map(p => `<tr><td>${esc(p.profiles?.name || 'Former member')}</td><td>${money(p.amount)}</td><td>${fmtDate(p.paid_at)}</td><td>${esc(p.memberships?.membership_plans?.name || 'Walk-in')}</td></tr>`), 'No payments recorded yet.')}

      <h2>Recent attendance</h2>
      ${tableWrap(['Member', 'Checked in', 'Checked out'], attendance.map(a => `<tr><td>${esc(a.profiles?.name || '—')}</td><td>${fmtDateTime(a.checked_in_at)}</td><td>${a.checked_out_at ? fmtDateTime(a.checked_out_at) : 'Still inside'}</td></tr>`), 'No attendance records yet.')}

      <h2>Member accounts</h2>
      ${tableWrap(['Name', 'Username', 'Status', 'Phone'], allProfiles.filter(p => p.approval_status !== 'pending').map(m => `<tr><td><strong>${esc(m.name)}</strong></td><td>@${esc(m.username)}</td><td>${statusPill(m.approval_status)}</td><td>${esc(m.phone || '—')}</td></tr>`), 'No member accounts yet.')}
    </section>`);

  const adminMsg = document.getElementById('admin-msg');
  document.querySelectorAll('[data-approve]').forEach(btn => btn.addEventListener('click', async () => {
    const { error } = await supabase.rpc('set_member_approval', { p_member: btn.dataset.approve, p_status: 'approved' });
    if (error) adminMsg.innerHTML = notice({ type: 'error', text: 'Could not approve this member.' });
    else { adminMsg.innerHTML = notice({ type: 'success', text: 'Member approved.' }); await loadProfile(); }
  }));
  document.querySelectorAll('[data-reject]').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Reject this registration?')) return;
    const { error } = await supabase.rpc('set_member_approval', { p_member: btn.dataset.reject, p_status: 'rejected' });
    if (error) adminMsg.innerHTML = notice({ type: 'error', text: 'Could not reject this member.' });
    else { adminMsg.innerHTML = notice({ type: 'success', text: 'Registration rejected.' }); await loadProfile(); }
  }));
  document.getElementById('membership-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const plan = plans.find(p => String(p.id) === fd.get('plan_id'));
    const end = new Date(`${fd.get('starts_at')}T00:00:00`);
    end.setDate(end.getDate() + plan.duration_days - 1);
    const { data: mem, error: memErr } = await supabase.from('memberships').insert({ user_id: fd.get('user_id'), plan_id: fd.get('plan_id'), starts_at: fd.get('starts_at'), expires_at: end.toISOString().slice(0, 10) }).select().maybeSingle();
    if (memErr) { adminMsg.innerHTML = notice({ type: 'error', text: 'Could not save this membership.' }); return; }
    const { error: payErr } = await supabase.from('payments').insert({ user_id: fd.get('user_id'), membership_id: mem.id, amount: plan.price });
    if (payErr) adminMsg.innerHTML = notice({ type: 'error', text: 'Membership saved, but payment could not be recorded.' });
    else { adminMsg.innerHTML = notice({ type: 'success', text: `Membership saved. ${money(plan.price)} payment recorded automatically.` }); await loadProfile(); }
  });
  document.getElementById('open-scanner').addEventListener('click', () => renderScanner());
}

// ===== QR SCANNER =====
async function renderScanner() {
  layout(`
    <section class="dashboard scanner-page">
      <div class="dashboard-head">
        <div><p class="kicker">ADMIN ATTENDANCE</p><h1>QR code scanner</h1><p>Scan a member's QR code to record check-in or check-out.</p></div>
        <div class="scanner-page-count"><strong id="scanner-count">0</strong><span>check-ins today</span></div>
      </div>
      <div id="scanner-msg"></div>
      <div class="management-card scanner-page-card">
        <div class="camera-area" id="camera-area"><p>Camera will appear here when started.</p></div>
        <div class="camera-actions" style="margin-top:12px">
          <button class="secondary-button" id="start-camera">Start camera</button>
          <button class="secondary-button" id="stop-camera" hidden>Stop camera</button>
          <button class="secondary-button" id="back-to-admin">Back to dashboard</button>
        </div>
        <p class="scanner-status" id="scanner-status"></p>
        <div style="margin-top:20px">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:8px">Or enter token manually</label>
          <div style="display:flex;gap:8px">
            <input type="text" id="manual-token" placeholder="DG:token" style="flex:1;height:42px;border:1px solid #ded1c5;border-radius:5px;padding:0 12px;font:inherit">
            <button class="primary-button" id="manual-submit" style="width:auto;padding:0 20px">Submit</button>
          </div>
        </div>
      </div>
    </section>`);

  document.getElementById('back-to-admin').addEventListener('click', () => renderDashboard());
  document.getElementById('manual-submit').addEventListener('click', () => processQrToken(document.getElementById('manual-token').value));

  const startBtn = document.getElementById('start-camera');
  const stopBtn = document.getElementById('stop-camera');
  const cameraArea = document.getElementById('camera-area');
  let stream = null;
  let scanning = false;

  startBtn.addEventListener('click', async () => {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      const video = document.createElement('video');
      video.srcObject = stream;
      video.autoplay = true;
      video.playsInline = true;
      cameraArea.innerHTML = '';
      cameraArea.appendChild(video);
      startBtn.hidden = true;
      stopBtn.hidden = false;
      scanning = true;
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      const { default: jsQR } = await import('jsqr');
      const scan = () => {
        if (!scanning) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
          ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
          const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
          const code = jsQR(imgData.data, imgData.width, imgData.height);
          if (code) { processQrToken(code.data); scanning = false; }
        }
        if (scanning) requestAnimationFrame(scan);
      };
      scan();
    } catch (err) {
      document.getElementById('scanner-msg').innerHTML = notice({ type: 'error', text: 'Could not access the camera. Use manual entry instead.' });
    }
  });
  stopBtn.addEventListener('click', () => {
    scanning = false;
    if (stream) stream.getTracks().forEach(t => t.stop());
    cameraArea.innerHTML = '<p>Camera stopped.</p>';
    startBtn.hidden = false;
    stopBtn.hidden = true;
  });
}

async function processQrToken(token) {
  const clean = token.startsWith('DG:') ? token.slice(3) : token;
  const { data: member, error } = await supabase.from('profiles').select('*').eq('qr_token', clean).eq('role', 'member').eq('approval_status', 'approved').maybeSingle();
  const msg = document.getElementById('scanner-msg');
  if (error || !member) { if (msg) msg.innerHTML = notice({ type: 'error', text: 'Member QR code was not found.' }); return; }
  const { data: openVisit } = await supabase.from('attendance').select('*').eq('user_id', member.id).is('checked_out_at', null).order('checked_in_at', { ascending: false }).limit(1).maybeSingle();
  if (openVisit) {
    const { error: e } = await supabase.from('attendance').update({ checked_out_at: new Date().toISOString() }).eq('id', openVisit.id);
    if (e) { if (msg) msg.innerHTML = notice({ type: 'error', text: 'Could not record check-out.' }); return; }
    if (msg) msg.innerHTML = notice({ type: 'success', text: `${member.name} checked out.` });
  } else {
    const { data: activeMem } = await supabase.from('memberships').select('*').eq('user_id', member.id).lte('starts_at', todayStr()).gte('expires_at', todayStr()).maybeSingle();
    if (!activeMem) { if (msg) msg.innerHTML = notice({ type: 'error', text: 'This member has no active membership.' }); return; }
    const { error: e } = await supabase.from('attendance').insert({ user_id: member.id });
    if (e) { if (msg) msg.innerHTML = notice({ type: 'error', text: 'Could not record check-in.' }); return; }
    if (msg) msg.innerHTML = notice({ type: 'success', text: `${member.name} checked in.` });
  }
  setTimeout(() => renderScanner(), 1500);
}

// ===== INIT =====
supabase.auth.getSession().then(async ({ data }) => {
  if (data.session) { session = data.session; await loadProfile(); }
  else renderAuth();
});
supabase.auth.onAuthStateChange((_event, next) => {
  session = next;
  if (!next) { profile = null; renderAuth(); }
});

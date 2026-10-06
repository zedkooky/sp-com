/**
 * POST /api/contact
 *
 * Sends enquiries from siddharthaparmar.com to your inbox.
 *
 * Required environment variable (set in Vercel > Settings > Environment Variables):
 *   RESEND_API_KEY   your key from resend.com  (free tier is plenty)
 * Optional:
 *   CONTACT_TO       destination inbox   (default hello@siddharthaparmar.com)
 *   CONTACT_FROM     verified sender     (default onboarding@resend.dev)
 *
 * Until RESEND_API_KEY is set this returns 501 and the site falls back
 * to opening the visitor's email client. Nothing breaks either way.
 */

const LABELS = {
  investor: 'Investor',
  partner:  'Partner or client',
  press:    'Press',
  speaking: 'Speaking',
  'aurum-sell-mine':   'Aurum: sell a mine',
  'aurum-buy-mine':    'Aurum: buy a mine',
  'aurum-buy-copper':  'Aurum: buy copper',
  'aurum-sell-copper': 'Aurum: sell copper',
  'aurum-investor':    'Aurum: investor brief'
};

const esc = (s) => String(s || '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

module.exports = async (req, res) => {
  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    return res.status(405).json({ error: 'Method not allowed' });
  }

  let data = req.body;
  if (typeof data === 'string') {
    try { data = JSON.parse(data); } catch { data = {}; }
  }
  data = data || {};

  // honeypot: real people never fill this
  if (data.company_url) return res.status(200).json({ ok: true });

  const name    = String(data.name    || '').trim().slice(0, 200);
  const email   = String(data.email   || '').trim().slice(0, 200);
  const org     = String(data.org     || '').trim().slice(0, 200);
  const extra   = String(data.extra   || '').trim().slice(0, 300);
  const message = String(data.message || '').trim().slice(0, 5000);
  const enquiry = LABELS[data.enquiry] || 'General';

  if (!name || !message || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
    return res.status(400).json({ error: 'Missing or invalid fields' });
  }

  const key = process.env.RESEND_API_KEY;
  if (!key) {
    return res.status(501).json({ error: 'Mail service not configured' });
  }

  const to   = process.env.CONTACT_TO   || 'hello@siddharthaparmar.com';
  const from = process.env.CONTACT_FROM || 'Website <onboarding@resend.dev>';

  const html = `
    <h2 style="font:600 18px system-ui;margin:0 0 14px">${esc(enquiry)} enquiry</h2>
    <table style="font:14px system-ui;border-collapse:collapse">
      <tr><td style="padding:4px 14px 4px 0;color:#666">Name</td><td>${esc(name)}</td></tr>
      <tr><td style="padding:4px 14px 4px 0;color:#666">Email</td><td>${esc(email)}</td></tr>
      <tr><td style="padding:4px 14px 4px 0;color:#666">Organisation</td><td>${esc(org) || '&mdash;'}</td></tr>
      <tr><td style="padding:4px 14px 4px 0;color:#666">Detail</td><td>${esc(extra) || '&mdash;'}</td></tr>
    </table>
    <p style="font:14px/1.6 system-ui;white-space:pre-wrap;margin-top:18px">${esc(message)}</p>
  `;

  try {
    const r = await fetch('https://api.resend.com/emails', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${key}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        from, to: [to], reply_to: email,
        subject: `[${enquiry}] ${name}${org ? ' — ' + org : ''}`,
        html
      })
    });
    if (!r.ok) {
      const detail = await r.text();
      console.error('resend error', r.status, detail);
      return res.status(502).json({ error: 'Mail provider rejected the message' });
    }
    return res.status(200).json({ ok: true });
  } catch (err) {
    console.error('contact handler', err);
    return res.status(500).json({ error: 'Send failed' });
  }
};

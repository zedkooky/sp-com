/**
 * Optional Node alternative to contact.php, for a VPS with no PHP.
 *
 *   npm i -g pm2
 *   RESEND_API_KEY=re_xxx CONTACT_TO=hello@siddharthaparmar.com \
 *     pm2 start server/contact-server.js --name contact
 *   pm2 save && pm2 startup
 *
 * Then in index.html set: const FORM_ENDPOINT='/api/contact';
 * and proxy /api/ to http://127.0.0.1:8787 in nginx:
 *
 *   location /api/ { proxy_pass http://127.0.0.1:8787/; }
 */
const http = require('http');
const handler = require('../api/contact.js');

const PORT = process.env.PORT || 8787;

http.createServer((req, res) => {
  if (req.url.replace(/\/$/, '') !== '/contact' && req.url !== '/') {
    res.writeHead(404, {'Content-Type':'application/json'});
    return res.end('{"error":"Not found"}');
  }
  let body = '';
  req.on('data', c => { body += c; if (body.length > 1e6) req.destroy(); });
  req.on('end', () => {
    req.body = body;
    res.status = (c) => { res.statusCode = c; return res; };
    res.json = (o) => { res.setHeader('Content-Type','application/json'); res.end(JSON.stringify(o)); return res; };
    handler(req, res);
  });
}).listen(PORT, '127.0.0.1', () => console.log('contact handler on 127.0.0.1:' + PORT));

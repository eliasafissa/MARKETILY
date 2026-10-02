import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 5173;
const HOST = '0.0.0.0';

// Serve static files from dist
app.use(express.static(path.join(__dirname, 'dist'), {
  setHeaders: (res, filePath) => {
    // Prevent mobile browsers from caching stale index.html
    if (filePath.endsWith('index.html')) {
      res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
  },
}));

// SPA fallback - return index.html only for routes that look like SPA paths
// (no file extension). Requests for missing .js/.css/.map assets should 404,
// otherwise the browser tries to parse index.html as JavaScript and breaks.
app.get('*', (req, res, next) => {
  // If the path looks like a file (has an extension), skip the SPA fallback
  // and let express.static's 404 propagate so the browser sees a real 404.
  if (/\.[a-zA-Z0-9]+$/.test(req.path)) {
    return next();
  }
  res.sendFile(path.join(__dirname, 'dist', 'index.html'));
});

app.listen(PORT, HOST, () => {
  console.log(`Frontend server running on http://${HOST}:${PORT}`);
});

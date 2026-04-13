// Embedded base64 logo for thermal printer
// This is WD-logo.jpg converted to base64
// Generated automatically - do not edit manually

export const WD_LOGO_BASE64 = require('./WD-logo-base64.txt').default || 
  (() => {
    // Fallback: try to read from file system if require doesn't work
    try {
      const fs = require('fs');
      const path = require('path');
      const logoPath = path.join(__dirname, 'WD-logo-base64.txt');
      return fs.readFileSync(logoPath, 'utf8').trim();
    } catch (e) {
      console.error('Could not load embedded logo base64');
      return null;
    }
  })();

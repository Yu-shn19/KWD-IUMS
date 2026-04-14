const https = require('https');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const os = require('os');

// Get the build ID from command line argument or use the latest
const buildId = process.argv[2] || '372c7ae2-7798-4d2d-9463-caccef48633b';

console.log('📱 Downloading APK from EAS Build...');
console.log(`Build ID: ${buildId}`);

// Get EAS access token from EAS CLI
let accessToken;
try {
  // Try to get token from EAS CLI config
  const easConfigPath = path.join(os.homedir(), '.expo', 'state.json');
  const easConfig = JSON.parse(fs.readFileSync(easConfigPath, 'utf8'));
  accessToken = easConfig?.auth?.sessionSecret;
  
  if (!accessToken) {
    console.log('⚠️  Could not find access token. Trying alternative method...');
    // Alternative: use EAS CLI to get build info and download URL
    console.log('📥 Please download manually from:');
    console.log(`https://expo.dev/accounts/aj_2004/projects/learningrn/builds/${buildId}`);
    process.exit(0);
  }
} catch (error) {
  console.log('⚠️  Could not access EAS config. Using web interface method...');
  console.log('📥 Please download manually from:');
  console.log(`https://expo.dev/accounts/aj_2004/projects/learningrn/builds/${buildId}`);
  process.exit(0);
}

// Fetch build details to get download URL
const options = {
  hostname: 'expo.dev',
  path: `/api/v2/builds/${buildId}`,
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${accessToken}`,
    'Content-Type': 'application/json'
  }
};

const req = https.request(options, (res) => {
  let data = '';
  
  res.on('data', (chunk) => {
    data += chunk;
  });
  
  res.on('end', () => {
    try {
      const buildData = JSON.parse(data);
      const downloadUrl = buildData?.artifacts?.applicationArchiveUrl || buildData?.artifacts?.buildUrl;
      
      if (!downloadUrl) {
        console.error('❌ Could not find download URL in build data');
        console.log('📥 Please download manually from:');
        console.log(`https://expo.dev/accounts/aj_2004/projects/learningrn/builds/${buildId}`);
        process.exit(1);
      }
      
      console.log(`✅ Found download URL: ${downloadUrl}`);
      downloadFile(downloadUrl, accessToken);
    } catch (error) {
      console.error('❌ Error parsing build data:', error.message);
      console.log('📥 Please download manually from:');
      console.log(`https://expo.dev/accounts/aj_2004/projects/learningrn/builds/${buildId}`);
      process.exit(1);
    }
  });
});

req.on('error', (error) => {
  console.error('❌ Error fetching build:', error.message);
  console.log('📥 Please download manually from:');
  console.log(`https://expo.dev/accounts/aj_2004/projects/learningrn/builds/${buildId}`);
  process.exit(1);
});

req.end();

function downloadFile(url, token) {
  const urlObj = new URL(url);
  const filename = 'ReaderWD.apk';
  const filepath = path.join(process.cwd(), filename);
  
  console.log(`⬇️  Downloading to: ${filepath}`);
  
  const options = {
    hostname: urlObj.hostname,
    path: urlObj.pathname + urlObj.search,
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'User-Agent': 'eas-cli'
    }
  };
  
  const file = fs.createWriteStream(filepath);
  
  const req = https.request(options, (res) => {
    if (res.statusCode === 200 || res.statusCode === 302) {
      const totalSize = parseInt(res.headers['content-length'], 10);
      let downloadedSize = 0;
      
      res.on('data', (chunk) => {
        downloadedSize += chunk.length;
        const percent = ((downloadedSize / totalSize) * 100).toFixed(2);
        process.stdout.write(`\r⬇️  Downloading: ${percent}% (${(downloadedSize / 1024 / 1024).toFixed(2)} MB)`);
      });
      
      res.on('end', () => {
        console.log('\n✅ Download complete!');
        console.log(`📦 APK saved to: ${filepath}`);
      });
    } else if (res.statusCode === 302 || res.statusCode === 301) {
      // Handle redirect
      const redirectUrl = res.headers.location;
      console.log(`🔄 Redirecting to: ${redirectUrl}`);
      downloadFile(redirectUrl, token);
      return;
    } else {
      console.error(`\n❌ Download failed with status: ${res.statusCode}`);
      console.log('📥 Please download manually from:');
      console.log(`https://expo.dev/accounts/aj_2004/projects/learningrn/builds/${buildId}`);
      file.close();
      fs.unlinkSync(filepath);
    }
    
    res.pipe(file);
  });
  
  req.on('error', (error) => {
    console.error(`\n❌ Download error: ${error.message}`);
    file.close();
    if (fs.existsSync(filepath)) {
      fs.unlinkSync(filepath);
    }
  });
  
  req.end();
}

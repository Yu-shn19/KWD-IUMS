const fs = require('fs');
const path = require('path');
const { generateImageAsync } = require('@expo/image-utils');

const root = __dirname.replace(/[\\/]scripts$/, '');
const src = path.join(root, 'assets', 'KlogoC.png');
const res = path.join(root, 'android', 'app', 'src', 'main', 'res');

async function gen(outPath, width, height, options = {}) {
  const result = await generateImageAsync(
    { projectRoot: root, cacheType: 'kwd-icons' },
    {
      src,
      width,
      height,
      resizeMode: 'contain',
      backgroundColor: '#ffffff',
      ...options,
    }
  );
  fs.mkdirSync(path.dirname(outPath), { recursive: true });
  const buf = Buffer.isBuffer(result.source)
    ? result.source
    : fs.readFileSync(result.source);
  fs.writeFileSync(outPath, buf);
  console.log('wrote', path.relative(root, outPath), buf.length);
}

(async () => {
  if (!fs.existsSync(src)) {
    throw new Error('Missing logo: ' + src);
  }

  const fg = [
    ['mipmap-mdpi', 108],
    ['mipmap-hdpi', 162],
    ['mipmap-xhdpi', 216],
    ['mipmap-xxhdpi', 324],
    ['mipmap-xxxhdpi', 432],
  ];
  for (const [folder, size] of fg) {
    await gen(path.join(res, folder, 'ic_launcher_foreground.webp'), size, size, { format: 'webp' });
    await gen(path.join(res, folder, 'ic_launcher.webp'), size, size, { format: 'webp' });
    await gen(path.join(res, folder, 'ic_launcher_round.webp'), size, size, { format: 'webp' });
  }

  const splash = [
    ['drawable-mdpi', 200],
    ['drawable-hdpi', 300],
    ['drawable-xhdpi', 400],
    ['drawable-xxhdpi', 600],
    ['drawable-xxxhdpi', 800],
  ];
  for (const [folder, size] of splash) {
    await gen(path.join(res, folder, 'splashscreen_logo.png'), size, size, { format: 'png' });
  }

  await gen(path.join(root, 'assets', 'adaptive-icon.png'), 1024, 1024, { format: 'png' });
  await gen(path.join(root, 'assets', 'icon.png'), 1024, 1024, { format: 'png' });
  await gen(path.join(root, 'assets', 'favicon.png'), 48, 48, { format: 'png' });
  await gen(path.join(root, 'assets', 'splash-icon.png'), 512, 512, { format: 'png' });

  console.log('done');
})().catch((e) => {
  console.error(e);
  process.exit(1);
});

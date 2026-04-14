const fs = require('fs');
const path = require('path');

// Convert WD-logo.jpg to base64
const logoPath = path.join(__dirname, '../assets/WD-logo.jpg');

if (!fs.existsSync(logoPath)) {
  console.error('Logo file not found at:', logoPath);
  process.exit(1);
}

const imageBuffer = fs.readFileSync(logoPath);
const base64String = imageBuffer.toString('base64');

console.log('Logo converted to base64 successfully!');
console.log('Length:', base64String.length);
console.log('\n--- Copy this base64 string ---\n');
console.log(base64String);
console.log('\n--- End of base64 string ---\n');

// Also save to a file for easy copy-paste
const outputPath = path.join(__dirname, '../assets/WD-logo-base64.txt');
fs.writeFileSync(outputPath, base64String);
console.log(`\nBase64 string also saved to: ${outputPath}`);

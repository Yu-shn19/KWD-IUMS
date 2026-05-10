#!/usr/bin/env node

/**
 * Bluetooth Printing Setup Verification Script
 * Checks if everything is ready for Bluetooth printing
 */

const fs = require('fs');
const path = require('path');

console.log('🔍 Checking Bluetooth Printing Setup...\n');

let allGood = true;

// Check 1: Package installed
console.log('1. Checking package installation...');
const packageJsonPath = path.join(__dirname, '../package.json');
const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));

if (packageJson.dependencies['react-native-thermal-receipt-printer']) {
  console.log('   ✅ react-native-thermal-receipt-printer is installed');
} else {
  console.log('   ❌ react-native-thermal-receipt-printer is NOT installed');
  console.log('   Run: npm install react-native-thermal-receipt-printer');
  allGood = false;
}

// Check 2: Permissions in app.json
console.log('\n2. Checking app.json permissions...');
const appJsonPath = path.join(__dirname, '../app.json');
const appJson = JSON.parse(fs.readFileSync(appJsonPath, 'utf8'));

const requiredPermissions = [
  'BLUETOOTH',
  'BLUETOOTH_ADMIN',
  'BLUETOOTH_CONNECT',
  'BLUETOOTH_SCAN',
  'ACCESS_FINE_LOCATION'
];

const permissions = appJson.expo.android?.permissions || [];
const missingPermissions = requiredPermissions.filter(p => !permissions.includes(p));

if (missingPermissions.length === 0) {
  console.log('   ✅ All required permissions are configured');
} else {
  console.log('   ❌ Missing permissions:', missingPermissions.join(', '));
  allGood = false;
}

// Check 3: Native code exists
console.log('\n3. Checking native code...');
const androidPath = path.join(__dirname, '../android');
const iosPath = path.join(__dirname, '../ios');

if (fs.existsSync(androidPath)) {
  console.log('   ✅ Android native code exists (prebuild done)');
} else {
  console.log('   ⚠️  Android native code not found');
  console.log('   Run: npx expo prebuild');
  allGood = false;
}

if (fs.existsSync(iosPath)) {
  console.log('   ✅ iOS native code exists (prebuild done)');
} else {
  console.log('   ⚠️  iOS native code not found (optional for Android)');
}

// Check 4: Bluetooth service file exists
console.log('\n4. Checking Bluetooth service...');
const bluetoothServicePath = path.join(__dirname, '../services/bluetoothPrinter.js');
if (fs.existsSync(bluetoothServicePath)) {
  console.log('   ✅ Bluetooth printer service exists');
} else {
  console.log('   ❌ Bluetooth printer service not found');
  allGood = false;
}

// Summary
console.log('\n' + '='.repeat(50));
if (allGood) {
  console.log('✅ All checks passed! Bluetooth printing is ready.');
  console.log('\nNext steps:');
  console.log('1. Rebuild the app: npx expo run:android');
  console.log('2. Pair your Bluetooth printer in Android Settings');
  console.log('3. Grant permissions when prompted in the app');
  console.log('4. Test printing by submitting a meter reading');
} else {
  console.log('❌ Some checks failed. Please fix the issues above.');
  console.log('\nQuick setup:');
  console.log('1. npm install react-native-thermal-receipt-printer');
  console.log('2. npx expo prebuild');
  console.log('3. npx expo run:android');
}
console.log('='.repeat(50));

process.exit(allGood ? 0 : 1);


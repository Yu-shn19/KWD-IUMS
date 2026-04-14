/* Bluetooth printing utility with safe fallback.
   Uses BLEPrinter from `react-native-thermal-receipt-printer` when available.
   If not present, functions will alert with setup instructions.
*/

import { Alert, Platform, PermissionsAndroid, NativeModules, Linking } from 'react-native';
import { receiptLogoStorage, receiptFormatStorage } from './storage';
import * as FileSystem from 'expo-file-system';
import { Asset } from 'expo-asset';

let BLEPrinter;
let BLEPrinterModule = null;

// Lazy load the module only when needed and if native module is properly set up
function loadBLEPrinterModule() {
	if (BLEPrinterModule !== null) {
		return BLEPrinterModule;
	}
	
	try {
		// Log available native modules for debugging
		const availableModules = Object.keys(NativeModules || {});
		const printerModules = availableModules.filter(m => 
			m.toLowerCase().includes('bluetooth') || 
			m.toLowerCase().includes('thermal') || 
			m.toLowerCase().includes('printer')
		);
		console.log('Available native modules:', printerModules);
		console.log('All native modules:', availableModules);
		
		// Check if native module exists - the correct name is RNBLEPrinter
		let nativeModule = NativeModules?.RNBLEPrinter || 
		                   NativeModules?.BLEPrinter || 
		                   NativeModules?.ThermalReceiptPrinter ||
		                   NativeModules?.RNThermalReceiptPrinter;
		
		// If native module doesn't exist, don't try to load the JS module
		if (!nativeModule) {
			console.warn('Bluetooth native module not found. Available modules:', Object.keys(NativeModules || {}));
			BLEPrinterModule = null;
			return null;
		}
		
		// Add stub event emitter methods to prevent NativeEventEmitter errors
		// This module uses callbacks, not events, but React Native expects these methods
		if (typeof nativeModule.addListener !== 'function') {
			nativeModule.addListener = function(eventType, listener) {
				// Stub method - this module uses callbacks, not events
				console.log('addListener called (stub):', eventType);
				return { remove: () => {} };
			};
		}
		if (typeof nativeModule.removeListeners !== 'function') {
			nativeModule.removeListeners = function(count) {
				// Stub method - this module uses callbacks, not events
				console.log('removeListeners called (stub):', count);
				return 0;
			};
		}
		if (typeof nativeModule.removeAllListeners !== 'function') {
			nativeModule.removeAllListeners = function(eventType) {
				// Stub method - this module uses callbacks, not events
				console.log('removeAllListeners called (stub):', eventType);
				return 0;
			};
		}
		
		// Also patch RNNetPrinter if it exists (the library creates an event emitter for it)
		if (NativeModules?.RNNetPrinter) {
			const netPrinter = NativeModules.RNNetPrinter;
			if (typeof netPrinter.addListener !== 'function') {
				netPrinter.addListener = function(eventType, listener) {
					return { remove: () => {} };
				};
			}
			if (typeof netPrinter.removeListeners !== 'function') {
				netPrinter.removeListeners = function(count) {
					return 0;
				};
			}
		}
		
		// Try to require a JS module that supports image printing well
		// Prefer the image-capable fork if installed, then fallback to the original
		let mod = null;
		let usedModule = null;
		try {
			// eslint-disable-next-line @typescript-eslint/no-var-requires
			mod = require('react-native-thermal-receipt-printer-image-qr');
			usedModule = 'react-native-thermal-receipt-printer-image-qr';
			console.log('Loaded printer module: react-native-thermal-receipt-printer-image-qr');
		} catch (e1) {
			console.warn('Printer module not found: react-native-thermal-receipt-printer-image-qr', e1?.message);
			try {
		// eslint-disable-next-line @typescript-eslint/no-var-requires
				mod = require('react-native-thermal-receipt-printer');
				usedModule = 'react-native-thermal-receipt-printer';
				console.log('Loaded printer module: react-native-thermal-receipt-printer');
			} catch (e2) {
				console.warn('No thermal printer JS module could be loaded', e2?.message);
				BLEPrinterModule = null;
				return null;
			}
		}

		// The module exports BLEPrinter directly
		const printer = mod?.BLEPrinter || mod?.default?.BLEPrinter || mod;
		
		// Verify BLEPrinter was actually loaded and is usable
		if (printer && typeof printer.init === 'function') {
			// Check if the underlying native module exists by trying to access it
			// The JS module accesses NativeModules.RNBLEPrinter internally
			const underlyingNativeModule = NativeModules?.RNBLEPrinter;
			if (!underlyingNativeModule) {
				console.warn('JS module loaded but native module RNBLEPrinter not found. Available:', Object.keys(NativeModules || {}));
				// Still return the printer - it might work if the module is lazy-loaded
			} else {
				console.log('Bluetooth printer module loaded successfully with native module');
			}
			BLEPrinterModule = printer;
			return printer;
		} else {
			console.warn('Bluetooth printer module loaded but init method not found');
			BLEPrinterModule = null;
			return null;
		}
	} catch (e) {
		// Catch any errors during module loading (including NativeEventEmitter errors)
		// This will catch the error: "new NativeEventEmitter() was called with a non-null argument without the required addListener method"
		console.error('Failed to load Bluetooth printer module:', e.message, e.stack);
		BLEPrinterModule = null;
		return null;
	}
}

// Initialize BLEPrinter on module load (will be null if native module not properly set up)
BLEPrinter = loadBLEPrinterModule();

const notSupported = () => {
	Alert.alert(
		'Bluetooth Printing Not Ready',
		[
			'Install and rebuild to enable Bluetooth printing:',
			'1) npm i react-native-thermal-receipt-printer',
			"2) Expo: npx expo prebuild, then make a dev build",
			'3) Open the app in that build and try again.'
		].join('\n')
	);
};

export const isSupported = () => Boolean(BLEPrinter);

async function requestAndroidBtPermissions() {
	if (Platform.OS !== 'android') return true;
	try {
		const needs = [];
		// Android 12+ specific runtime permissions
		if (Platform.Version >= 31) {
			needs.push(PermissionsAndroid.PERMISSIONS.BLUETOOTH_CONNECT);
			needs.push(PermissionsAndroid.PERMISSIONS.BLUETOOTH_SCAN);
		}
		// Many devices still require location to scan BLE
		needs.push(PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION);
		const results = await PermissionsAndroid.requestMultiple(needs);
		return Object.values(results).every((r) => r === PermissionsAndroid.RESULTS.GRANTED);
	} catch (e) {
		return false;
	}
}

let isInitialized = false;
let currentConnection = null;

async function ensureInit() {
	if (!BLEPrinter) return false;
	if (isInitialized) return true;
	const granted = await requestAndroidBtPermissions();
	if (!granted) {
		Alert.alert('Permissions Required', 'Enable Bluetooth and Location permissions for this app in Settings.');
		return false;
	}
	try {
		console.log('Initializing Bluetooth printer...');
		await BLEPrinter.init();
		isInitialized = true;
		// Wait a moment for initialization to complete
		await new Promise(resolve => setTimeout(resolve, 500));
		console.log('Bluetooth printer initialized');
		return true;
	} catch (e) {
		console.error('Init error:', e);
		Alert.alert('Bluetooth Error', 'Failed to initialize Bluetooth printer. Ensure Bluetooth is ON and try again.');
		return false;
	}
}

async function closeExistingConnection() {
	if (!BLEPrinter) return;
	try {
		console.log('Closing any existing printer connection...');
		await BLEPrinter.closeConn();
		currentConnection = null;
		// Wait a moment for connection to close
		await new Promise(resolve => setTimeout(resolve, 500));
	} catch (e) {
		console.log('No existing connection to close or error closing:', e.message);
	}
}

// Open Bluetooth settings to pair devices
export const openBluetoothSettings = async () => {
	try {
		if (Platform.OS === 'android') {
			await Linking.openSettings();
		} else {
			Alert.alert('Not Supported', 'Opening Bluetooth settings is only supported on Android.');
		}
	} catch (e) {
		console.error('Error opening Bluetooth settings:', e);
		Alert.alert('Error', 'Could not open Bluetooth settings. Please go to Android Settings > Bluetooth manually.');
	}
};

export const listBluetoothPrinters = async () => {
	// Show all paired devices (even if not currently connected)
	console.log('=== listBluetoothPrinters called ===');
	console.log('BLEPrinter available:', !!BLEPrinter);
	
	if (!BLEPrinter) {
		console.error('BLEPrinter module not available');
		Alert.alert(
			'Bluetooth Not Available',
			'Bluetooth printing module is not available. Please rebuild the app with:\n\nnpx expo run:android'
		);
		notSupported();
		return [];
	}
	
	console.log('Initializing Bluetooth...');
	const ok = await ensureInit();
	if (!ok) {
		console.error('Bluetooth initialization failed');
		Alert.alert(
			'Bluetooth Error',
			'Failed to initiali  ze Bluetooth. Please:\n1. Enable Bluetooth on your device\n2. Grant Location permission\n3. Try again'
		);
		return [];
	}
	
	console.log('Bluetooth initialized successfully');
	
	try {
		console.log('Calling BLEPrinter.getDeviceList()...');
		
		// The library returns a Promise
		const printers = await BLEPrinter.getDeviceList();
		
		console.log('Raw printer list response:', printers);
		console.log('Printers type:', typeof printers);
		console.log('Is array:', Array.isArray(printers));
		console.log('Printers length:', printers?.length);
		
		if (printers && Array.isArray(printers)) {
			console.log(`✓ Found ${printers.length} paired device(s)`);
			
			if (printers.length > 0) {
				printers.forEach((printer, index) => {
					console.log(`Device ${index + 1}:`, {
						name: printer?.device_name || printer?.name,
						mac: printer?.inner_mac_address || printer?.macAddress,
						full: JSON.stringify(printer)
					});
				});
			} else {
				console.warn('⚠ No paired devices found. Pair devices in Android Settings first.');
			}
			
			// Return all paired devices (connection status doesn't matter)
			return printers;
		} else {
			console.error('✗ Invalid printer list format. Expected array, got:', typeof printers);
			console.error('Value:', printers);
			return [];
		}
	} catch (e) {
		console.error('✗ Error listing printers:', e);
		console.error('Error details:', {
			message: e?.message,
			stack: e?.stack,
			toString: e?.toString()
		});
		
		Alert.alert(
			'Bluetooth Error',
			`Failed to get Bluetooth devices:\n\n${e?.message || e}\n\nPlease:\n1. Check Bluetooth is enabled\n2. Pair devices in Android Settings\n3. Grant Location permission`
		);
		
		return [];
	}
};

export const connectToPrinter = async (target, retryCount = 0) => {
	if (!BLEPrinter) {
		notSupported();
		return false;
	}
	
	const ok = await ensureInit();
	if (!ok) return false;
	
	try {
		// The library uses inner_mac_address - use it exactly as returned
		const macAddress = target?.inner_mac_address || target?.macAddress || target?.address;
		
		if (!macAddress) {
			console.error('No MAC address found in printer object:', target);
			Alert.alert('Select Printer', 'No printer address provided. Printer object: ' + JSON.stringify(target));
			return false;
		}
		
		// Close any existing connection first
		await closeExistingConnection();
		
		// Use MAC address exactly as provided by the library (don't reformat)
		console.log('Connecting to printer:');
		console.log('  Name:', target?.device_name || target?.name || 'Unknown');
		console.log('  MAC:', macAddress);
		console.log('  Full object:', JSON.stringify(target));
		
		// Try connecting with retry logic
		const maxRetries = 2;
		let lastError = null;
		
		for (let attempt = 0; attempt <= maxRetries; attempt++) {
			try {
				if (attempt > 0) {
					console.log(`Retry attempt ${attempt}/${maxRetries}...`);
					// Close connection before retry
					await closeExistingConnection();
					// Wait longer between retries
					await new Promise(resolve => setTimeout(resolve, 1500));
				}
				
				console.log(`Connection attempt ${attempt + 1}: Using MAC ${macAddress}`);
				
				// Use the MAC address directly as provided by the library
				await BLEPrinter.connectPrinter(macAddress);
				
				currentConnection = macAddress;
				console.log('Successfully connected to printer');
				// Wait a moment for connection to stabilize
				await new Promise(resolve => setTimeout(resolve, 500));
				return true;
			} catch (e) {
				lastError = e;
				const errorMsg = e?.message || e?.toString() || 'Unknown error';
				console.error(`Connection attempt ${attempt + 1} failed:`, errorMsg);
				
				// If it's a socket error and we haven't exhausted retries, try again
				if (attempt < maxRetries && (
					errorMsg.includes('socket') || 
					errorMsg.includes('timeout') ||
					errorMsg.includes('closed') ||
					errorMsg.includes('read failed')
				)) {
					continue;
				} else {
					break;
				}
			}
		}
		
		// All retries failed
		throw lastError || new Error('Connection failed after retries');
		
	} catch (e) {
		console.error('Connect printer error:', e);
		const errorMsg = e?.message || e?.toString() || 'Unknown error';
		
		// Provide helpful error messages
		let userMessage = 'Could not connect to the Bluetooth printer.\n\n';
		if (errorMsg.includes('socket') || errorMsg.includes('timeout') || errorMsg.includes('closed') || errorMsg.includes('read failed')) {
			userMessage += 'Connection failed. Please:\n';
			userMessage += '1. Ensure printer is turned ON and in range\n';
			userMessage += '2. Pair printer in Android Settings first\n';
			userMessage += '3. Disconnect printer from other devices\n';
			userMessage += '4. Restart printer and try again\n';
			userMessage += '5. Try selecting a different printer';
		} else {
			userMessage += `Error: ${errorMsg}\n\n`;
			userMessage += 'Please check:\n';
			userMessage += '1. Printer is paired in Android Settings\n';
			userMessage += '2. Bluetooth is enabled\n';
			userMessage += '3. Printer is turned on';
		}
		
		Alert.alert('Connect Failed', userMessage);
		return false;
	}
};

function buildEscPosReceipt(receipt, options = {}) {
	// ESC/POS simple text layout for 80mm thermal paper
	// 80mm paper = 48 characters at 12 CPI (standard font size)
	const lines = [];
	const push = (s = '') => lines.push(s);
	const center = (s = '') => push(s.padStart(((48 - s.length) / 2 + s.length) | 0));
	const sep = () => push('------------------------------------------------');

	// Company info from saved format (options.format) or defaults
	const fmt = options.format || {};
	const companyName = fmt.companyName || 'HAGONOY WATER DISTRICT';
	const address = fmt.address || 'Guihing Hagonoy, Davao Del Sur';
	const vatId = fmt.vatId || 'VATS ID DBN 001-437-440';
	const contact = fmt.contact || 'Contact Number: 09073814037';
	const email = fmt.email || 'Email: hagonoywaterdistrict@yahoo.com';

	// ESC/POS commands for bold formatting
	// ESC E 1 = Bold ON, ESC E 0 = Bold OFF
	const BOLD_ON = '\x1B\x45\x01';  // ESC E 1
	const BOLD_OFF = '\x1B\x45\x00';  // ESC E 0
	
	// ESC/POS commands for font size
	// ESC ! n - Select character size
	// Bit 0: Character width (0=normal, 1=double width)
	// Bit 3: Character height (0=normal, 1=double height)
	// 0x11 (17) = double width and double height
	// 0x01 (1) = double height only (slightly bigger)
	// 0x00 = normal size
	const LARGE_FONT_ON = '\x1B\x21\x11';  // ESC ! 0x11 = double width + double height
	const MEDIUM_FONT_ON = '\x1B\x21\x01';  // ESC ! 0x01 = double height only (slightly bigger)
	const LARGE_FONT_OFF = '\x1B\x21\x00';  // ESC ! 0x00 = normal size
	
	// Helper function to center text with bold and large font formatting
	const centerBoldLarge = (s = '') => {
		const centered = s.padStart(((48 - s.length) / 2 + s.length) | 0);
		return LARGE_FONT_ON + BOLD_ON + centered + BOLD_OFF + LARGE_FONT_OFF;
	};
	
	// Helper function to center text with bold formatting (normal size)
	const centerBold = (s = '') => {
		const centered = s.padStart(((48 - s.length) / 2 + s.length) | 0);
		return BOLD_ON + centered + BOLD_OFF;
	};

	// Helper for receipt-style amount rows: label left, value right (no colon)
	const formatAmountLine = (label, value) => {
		const totalWidth = 48;
		const safeValue = String(value ?? '');
		const labelWidth = Math.max(0, totalWidth - safeValue.length);
		return `${String(label).padEnd(labelWidth)}${safeValue}`;
	};

	// Same as formatAmountLine, but allows ESC/POS styling on value while
	// keeping right alignment based on plain text length.
	const formatAmountLineStyledValue = (label, plainValue, styledValue) => {
		const totalWidth = 48;
		const safePlain = String(plainValue ?? '');
		const labelWidth = Math.max(0, totalWidth - safePlain.length);
		return `${String(label).padEnd(labelWidth)}${styledValue}`;
	};

	// Header text (skip when the logo was printed successfully) - use saved format values
	if (!options.skipHeader) {
		push(centerBoldLarge(companyName));
		center(address);
		center(vatId);
		center(contact);
		center(email);
		push('');
	}
	push(centerBoldLarge('NOTICE OF COLLECTION BILL'));
	sep();
	
	// Format fields with better spacing for 80mm paper
	const formatField = (label, value, labelWidth = 20) => {
		const paddedLabel = label.padEnd(labelWidth);
		return `${paddedLabel}: ${value}`;
	};
	
	// Format field with bold value
	const formatFieldBold = (label, value, labelWidth = 20) => {
		const paddedLabel = label.padEnd(labelWidth);
		return `${paddedLabel}: ${BOLD_ON}${value}${BOLD_OFF}`;
	};
	
	// Format name with proper wrapping and bigger bold value (label on first line, name wraps below)
	const formatNameBold = (label, name, labelWidth = 20) => {
		const maxLineWidth = 48; // 80mm paper width
		const nameStartCol = labelWidth + 2; // Label + ": "
		const nameMaxWidth = maxLineWidth - nameStartCol;
		
		// If name fits on one line, use large bold format (size 25-like emphasis)
		if (name.length <= nameMaxWidth) {
			const paddedLabel = label.padEnd(labelWidth);
			return `${paddedLabel}: ${LARGE_FONT_ON}${BOLD_ON}${name}${BOLD_OFF}${LARGE_FONT_OFF}`;
		}
		
		// Otherwise, wrap the name with bold formatting
		const paddedLabel = label.padEnd(labelWidth);
		const lines = [];
		let remaining = name;
		
		// First line: label and first part of name (bold)
		const firstLineName = remaining.substring(0, nameMaxWidth);
		lines.push(`${paddedLabel}: ${LARGE_FONT_ON}${BOLD_ON}${firstLineName}${BOLD_OFF}${LARGE_FONT_OFF}`);
		remaining = remaining.substring(nameMaxWidth);
		
		// Subsequent lines: wrap remaining name (bold, indented)
		while (remaining.length > 0) {
			const lineName = remaining.substring(0, nameMaxWidth);
			lines.push(' '.repeat(nameStartCol) + LARGE_FONT_ON + BOLD_ON + lineName + BOLD_OFF + LARGE_FONT_OFF);
			remaining = remaining.substring(nameMaxWidth);
		}
		
		return lines;
	};
	
	// Format address with proper wrapping (label on first line, address wraps below)
	const formatAddress = (label, address, labelWidth = 20) => {
		const maxLineWidth = 48; // 80mm paper width
		const addressStartCol = labelWidth + 2; // Label + ": "
		const addressMaxWidth = maxLineWidth - addressStartCol;
		
		// If address fits on one line, use normal format
		if (address.length <= addressMaxWidth) {
			return formatField(label, address);
		}
		
		// Otherwise, wrap the address
		const paddedLabel = label.padEnd(labelWidth);
		const lines = [];
		let remaining = address;
		
		// First line: label and first part of address
		const firstLineAddress = remaining.substring(0, addressMaxWidth);
		lines.push(`${paddedLabel}: ${firstLineAddress}`);
		remaining = remaining.substring(addressMaxWidth);
		
		// Subsequent lines: wrap remaining address
		while (remaining.length > 0) {
			const lineAddress = remaining.substring(0, addressMaxWidth);
			lines.push(' '.repeat(addressStartCol) + lineAddress);
			remaining = remaining.substring(addressMaxWidth);
		}
		
		return lines;
	};
	
	push(formatField('Period', receipt.periodCovered));
	push(formatField('Zone', receipt.zone) + `  Type: ${receipt.consumerType}`);
	push(formatField('Sequence', receipt.sequence));
	push(formatField('Account Number', receipt.accountNumber));
	
	// Handle name wrapping with bold formatting
	const nameLines = formatNameBold('Name', receipt.customer.name);
	if (Array.isArray(nameLines)) {
		nameLines.forEach(line => push(line));
	} else {
		push(nameLines);
	}
	
	// Handle address wrapping
	const addressLines = formatAddress('Address', receipt.customer.address);
	if (Array.isArray(addressLines)) {
		addressLines.forEach(line => push(line));
	} else {
		push(addressLines);
	}
	
	push(formatField('Meter Number/Size', receipt.customer.meterNumber));
	sep();
	push(formatField('Reading Date', receipt.readingDate));
	push(formatField('Due Date', receipt.dueDate));
	sep();
	push(formatField('Present Reading', receipt.readings.current));
	push(formatField('Previous Reading', receipt.readings.previous));
	// Format consumption line with bold and larger "High Consumption" if applicable
	if (receipt.readings.isHighConsumption) {
		const paddedLabel = 'Cubic Meter Used'.padEnd(20);
		// Use medium font size (double height only) and bold for "High Consumption"
		const consumptionLine = `${paddedLabel}: ${receipt.readings.consumption} ${MEDIUM_FONT_ON}${BOLD_ON}High Consumption${BOLD_OFF}${LARGE_FONT_OFF}`;
	push(consumptionLine);
	} else {
		push(formatField('Cubic Meter Used', receipt.readings.consumption));
	}
	sep();
	push(formatAmountLine('Current Bill:', receipt.billing.currentBill));
	push(formatAmountLine('Water Maintenance Charge:', receipt.billing.meterMaintenanceCharge));
	push(formatAmountLine('TOTAL CURRENT:', receipt.billing.totalCurrent));
	push('');
	push(formatAmountLine('Arrears:', receipt.billing.arrears));
	push(formatAmountLine('Others:', receipt.billing.others));
	sep();
	push(LARGE_FONT_ON + BOLD_ON + formatAmountLine('TOTAL BILL:', receipt.billing.totalBill) + BOLD_OFF + LARGE_FONT_OFF);
	push('');
	center('IF UNPAID AT HWD OFFICE');
	push('\x1B\x61\x01' + `After: ${LARGE_FONT_ON}${BOLD_ON}${receipt.dueDate}${BOLD_OFF}${LARGE_FONT_OFF}` + '\x1B\x61\x00');
	push(formatField('Surcharge', receipt.billing.surcharge));
	center(`TOTAL W/ SUR: ${receipt.billing.totalWithSurcharge}`);
	sep();
	push('Notice:');
	push('1) Failure to pay may lead to cut-off.');
	push('2) Disregard if already paid.');
	push('3) If service is discontinued, total amount due plus P200.00 reconnection fee.');
	sep();
	push(formatField('Reader', receipt.meterReader));
	push(''); // Add spacing before QR code
	
	// Add QR code ESC/POS commands (centered)
	const facebookUrl = 'https://www.facebook.com/HagonoyWD';
	const qrSize = 6; // Module size (1-8, 6 is medium)
	const qrErrorLevel = 1; // Error correction level (0=L, 1=M, 2=Q, 3=H)
	
	// Build ESC/POS QR code command sequence with centering
	let qrCommand = '';
	
	// Center alignment command (ESC a 1 = center)
	qrCommand += '\x1B\x61\x01'; // ESC a 1 = center align
	
	// Select QR code model (Model 2)
	qrCommand += '\x1D\x28\x6B\x04\x00\x31\x41\x32\x00';
	
	// Set QR code size
	qrCommand += '\x1D\x28\x6B\x03\x00\x31\x43' + String.fromCharCode(qrSize);
	
	// Set error correction level
	qrCommand += '\x1D\x28\x6B\x03\x00\x31\x45' + String.fromCharCode(qrErrorLevel + 48);
	
	// Store QR code data
	const dataLength = facebookUrl.length + 3;
	qrCommand += '\x1D\x28\x6B' + String.fromCharCode(dataLength & 0xFF) + String.fromCharCode((dataLength >> 8) & 0xFF) + '\x31\x50\x30' + facebookUrl;
	
	// Print QR code
	qrCommand += '\x1D\x28\x6B\x03\x00\x31\x51\x30';
	
	// Keep center alignment active (don't reset to left yet)
	// Add spacing after QR code
	qrCommand += '\n';
	
	// Push QR code commands as a line (center alignment is still active)
	push(qrCommand);
	
	// Add text below QR code (centered) - center alignment is already active from QR code
	const qrText = 'Scan This QR Code If You Have Concern';
	// Explicitly ensure center alignment for the text (in case it was reset)
	push('\x1B\x61\x01' + qrText);
	
	// Reset alignment to left after text
	push('\x1B\x61\x00');
	push('\n\n');

	return lines.join('\n');
}

// Embedded base64 logo - WD-logo.jpg converted to base64
// This ensures the logo always prints without file system issues
const EMBEDDED_LOGO_BASE64 = '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';

// Load and convert logo image to base64 for printing (supports JPG/PNG/BMP)
// This follows the recommended pattern for react-native-thermal-receipt-printer-image-qr:
//   BLEPrinter.printImageBase64(base64, { imageWidth })
async function loadLogoBase64ForPrinting() {
	try {
		// Check if logo should be shown based on format settings
		// Default to true (show logo) if not explicitly set to false
		let format = await receiptFormatStorage.getFormat();
		
		// Log format status for debugging
		console.log('=== Logo Loading Debug ===');
		console.log('Format settings:', format ? JSON.stringify(format) : 'null (using defaults)');
		console.log('showLogo value:', format?.showLogo);
		
		// Ensure showLogo defaults to true if format exists but showLogo is not set
		if (format && format.showLogo === undefined) {
			console.log('⚠ showLogo not set in format, defaulting to true');
			format.showLogo = true;
			// Optionally save the updated format (but don't block on it)
			receiptFormatStorage.saveFormat(format).catch(e => {
				console.warn('Failed to update format with showLogo default:', e);
			});
		}
		
		// Only disable logo if explicitly set to false
		// If format is null or showLogo is undefined/true, enable logo
		if (format && format.showLogo === false) {
			console.log('⚠ Logo printing DISABLED in format settings (showLogo=false)');
			console.log('To enable logo printing, go to Edit Receipt Format and enable "Show Logo"');
			return null;
		}
		
		console.log('✓ Logo printing ENABLED (showLogo is true or not set)');

		// 1) Try custom logo if the user saved one (already a file/URI)
		const savedLogo = await receiptLogoStorage.getLogo();
		if (savedLogo) {
			console.log('Using saved receipt logo URI:', savedLogo);
			try {
				const base64 = await FileSystem.readAsStringAsync(savedLogo, {
					encoding: FileSystem.EncodingType.Base64,
				});
				if (base64 && base64.length > 100) {
					console.log('Custom logo converted to base64, length:', base64.length);
					return base64;
				} else {
					console.warn('Custom logo base64 too short, trying default logo');
				}
			} catch (e) {
				console.warn('Failed to read custom logo, falling back to default logo:', e.message);
			}
		}

		// 2) Load WD-logo.jpg from assets as base64 - this ensures logo always prints automatically
		console.log('Loading WD-logo.jpg from assets...');
		let assetBase64 = null;
		
		try {
			const asset = Asset.fromModule(require('../assets/WD-logo.jpg'));
			console.log('Asset module loaded, downloading...');
			await asset.downloadAsync();
			const uri = asset.localUri || asset.uri;
			
			console.log('Asset URI resolved:', uri);
			if (uri) {
				console.log('Reading logo file as base64 from:', uri);
				assetBase64 = await FileSystem.readAsStringAsync(uri, {
					encoding: FileSystem.EncodingType.Base64,
				});
				
				if (assetBase64 && assetBase64.length > 100) {
					console.log('SUCCESS: WD-logo.jpg converted to base64, length:', assetBase64.length);
					return assetBase64;
				} else {
					console.warn('WARNING: Logo base64 conversion returned invalid result. Length:', assetBase64?.length || 0);
				}
			} else {
				console.warn('WARNING: Could not resolve logo asset URI');
			}
		} catch (e) {
			console.warn('WARNING: Failed to load WD-logo.jpg from assets:', e.message);
			console.warn('Error details:', e);
		}
		
		// 3) Fallback: Try to read from base64 text file if asset loading failed
		if (!assetBase64 || assetBase64.length <= 100) {
			console.log('Attempting fallback: Loading from WD-logo-base64.txt...');
			try {
				const base64Asset = Asset.fromModule(require('../assets/WD-logo-base64.txt'));
				await base64Asset.downloadAsync();
				const base64Uri = base64Asset.localUri || base64Asset.uri;
				
				if (base64Uri) {
					const base64Text = await FileSystem.readAsStringAsync(base64Uri, {
						encoding: FileSystem.EncodingType.UTF8,
					});
					const trimmed = base64Text.trim();
					if (trimmed && trimmed.length > 100) {
						console.log('SUCCESS: Loaded logo from base64 text file, length:', trimmed.length);
						return trimmed;
					}
				}
			} catch (e2) {
				console.warn('Fallback also failed:', e2.message);
			}
		}
		
		console.error('ERROR: All logo loading methods failed. Logo will not be printed.');
		return null;
	} catch (e) {
		console.error('ERROR: Could not load logo for printing:', e);
		console.error('Error stack:', e.stack);
		return null;
	}
}

export const printReceiptEscPos = async (receiptData, targetPrinter) => {
	if (!BLEPrinter) {
		notSupported();
		return false;
	}
	
	const ok = await connectToPrinter(targetPrinter);
	if (!ok) {
		console.error('Failed to connect to printer');
		return false;
	}
	
	try {
		// Debug: Log available methods on BLEPrinter
		console.log(
			'Available BLEPrinter methods:',
			Object.keys(BLEPrinter).filter(key => typeof BLEPrinter[key] === 'function')
		);
		
		// Try to print logo first using the officially supported image API
		let logoPrinted = false;
		try {
			console.log('=== Starting logo loading process ===');
			const base64Logo = await loadLogoBase64ForPrinting();
			console.log('Logo loading result:', base64Logo ? `Success (${base64Logo.length} chars)` : 'Failed/Disabled');
			
			if (base64Logo && base64Logo.length > 100 && BLEPrinter.printImageBase64) {
				// Logo size: 1x1 inch (approximately 203x203 pixels at 203 DPI)
				const logoSize = 203;
				console.log(`=== Attempting to print logo ===`);
				console.log(`Method: BLEPrinter.printImageBase64`);
				console.log(`Size: ${logoSize}x${logoSize} pixels (1x1 inch)`);
				console.log(`Base64 length: ${base64Logo.length} characters`);
				
				// Wrap in try-catch to prevent crashes
				try {
					await BLEPrinter.printImageBase64(base64Logo, {
						imageWidth: logoSize,
						imageHeight: logoSize,
					});
									logoPrinted = true;
					console.log('✓ Logo printed successfully!');
					// Small delay so printer can finish image before text
									await new Promise(resolve => setTimeout(resolve, 800));
				} catch (printError) {
					console.error('✗ printImageBase64 failed:', printError?.message || printError);
					console.error('Error details:', printError);
					// Don't crash - just skip logo
					logoPrinted = false;
								}
							} else {
				if (!base64Logo) {
					console.warn('✗ No base64 logo available - check format settings (showLogo should be true)');
				} else if (base64Logo.length <= 100) {
					console.warn(`✗ Logo base64 too short (${base64Logo.length} chars), expected >100`);
				} else if (!BLEPrinter.printImageBase64) {
					console.warn('✗ BLEPrinter.printImageBase64 is not available on this device/module');
					console.log('Available methods:', Object.keys(BLEPrinter).filter(k => typeof BLEPrinter[k] === 'function'));
				}
			}
		} catch (logoError) {
			console.error('✗ Logo loading/printing failed:', logoError?.message || logoError);
			console.error('Error stack:', logoError?.stack);
			// Continue printing even if logo fails - don't crash the app
			logoPrinted = false;
		}
		
		console.log(`=== Logo printing status: ${logoPrinted ? 'SUCCESS' : 'SKIPPED'} ===`);
		
		// Load saved receipt format (company name, address, VAT ID, contact, email) so printed receipt matches Edit Receipt Format
		let format = null;
		try {
			format = await receiptFormatStorage.getFormat();
		} catch (e) {
			console.warn('Could not load receipt format for print, using defaults:', e?.message);
		}
		
		// Always print company information header, even when logo is printed (use saved format values)
		const text = buildEscPosReceipt(receiptData, { skipHeader: false, format });
		console.log('Printing receipt, text length:', text.length);
		
		// Wrap print methods in promises since they use callbacks internally
		const printPromise = new Promise((resolve, reject) => {
			// Set a timeout to detect if print fails silently
			const timeout = setTimeout(() => {
				reject(new Error('Print timeout - printer may not be responding'));
			}, 15000); // 15 second timeout (increased for reliability)
			
			try {
			// Try printBill first (formatted for receipts), then printText as fallback
			if (BLEPrinter.printBill) {
				console.log('Using printBill method');
					BLEPrinter.printBill(text);
					// Give it a moment to process, then resolve
					setTimeout(() => {
						clearTimeout(timeout);
						console.log('Print command sent successfully');
						resolve(true);
					}, 1500);
			} else if (BLEPrinter.printText) {
				console.log('Using printText method');
					BLEPrinter.printText(text);
					// Give it a moment to process, then resolve
					setTimeout(() => {
						clearTimeout(timeout);
						console.log('Print command sent successfully');
						resolve(true);
					}, 1500);
				} else {
					clearTimeout(timeout);
					console.error('No print methods available on BLEPrinter:', Object.keys(BLEPrinter));
					reject(new Error('No supported print method available'));
				}
			} catch (e) {
				clearTimeout(timeout);
				console.error('Print method error:', e);
				reject(e);
			}
		});
		
		await printPromise;
		console.log('Print completed successfully', logoPrinted ? '(with logo)' : '(text only)');
		// QR code is now embedded in the receipt text and will print automatically
		return true;
	} catch (e) {
		console.error('Print error:', e);
		const errorMessage = e?.message || e?.toString() || 'Unknown error';
		console.error('Error stack:', e?.stack);
		
		// Don't show alert if app is closing - it might cause issues
		try {
		Alert.alert('Print Failed', `Could not print to the Bluetooth printer.\n\nError: ${errorMessage}\n\nPlease check:\n1. Printer is turned on\n2. Printer has paper\n3. Printer is connected\n4. Try reconnecting the printer`);
		} catch (alertError) {
			console.error('Failed to show alert:', alertError);
		}
		return false;
	} finally {
		// Always try to disconnect gracefully
		try {
			if (BLEPrinter && BLEPrinter.closeConn) {
				BLEPrinter.closeConn();
			}
		} catch (disconnectError) {
			console.warn('Error disconnecting printer:', disconnectError);
		}
	}
};

// Legacy helper kept for reference (no longer used by the main flow)
async function convertImageToBase64(uri) {
	console.log('convertImageToBase64 is deprecated in favor of loadLogoBase64ForPrinting');
			return null;
}

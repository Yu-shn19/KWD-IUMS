import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Modal, ScrollView, ActivityIndicator, Alert, Linking, Platform } from 'react-native';
import { listBluetoothPrinters, openBluetoothSettings } from '../services/bluetoothPrinter';

export default function PrinterSelector({ visible, onSelect, onCancel }) {
  const [printers, setPrinters] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isScanning, setIsScanning] = useState(false);

  // Debug: Log state changes
  useEffect(() => {
    console.log('=== PrinterSelector Render ===');
    console.log('visible:', visible);
    console.log('isLoading:', isLoading);
    console.log('printers.length:', printers.length);
    console.log('printers:', printers);
  }, [visible, isLoading, printers]);

  const scanForPrinters = async () => {
    setIsScanning(true);
    setIsLoading(true);
    setPrinters([]); // Clear previous results
    
    try {
      console.log('=== PrinterSelector: Starting scan ===');
      const foundPrinters = await listBluetoothPrinters();
      console.log('=== PrinterSelector: Scan complete ===');
      console.log('Found printers count:', foundPrinters?.length || 0);
      console.log('Printers data:', JSON.stringify(foundPrinters, null, 2));
      
      if (foundPrinters && Array.isArray(foundPrinters)) {
        console.log('Setting printers state with', foundPrinters.length, 'devices');
        console.log('Current printers state before update:', printers.length);
        
        // Force state update
        setPrinters([...foundPrinters]);
        
        // Verify state was set
        setTimeout(() => {
          console.log('State after update should have', foundPrinters.length, 'devices');
        }, 100);
        
        if (foundPrinters.length === 0) {
          console.warn('No printers found - showing empty state');
          // Don't show alert - let the empty state UI handle it
        } else {
          console.log('✓ Successfully loaded', foundPrinters.length, 'paired device(s)');
          console.log('First device:', JSON.stringify(foundPrinters[0]));
        }
      } else {
        console.error('✗ Invalid printer list format:', typeof foundPrinters);
        console.error('Value received:', foundPrinters);
        setPrinters([]);
        Alert.alert(
          'Scan Error',
          `Invalid response from printer scan.\n\nReceived: ${typeof foundPrinters}\n\nPlease check console logs for details.`
        );
      }
    } catch (error) {
      console.error('✗ PrinterSelector: Error scanning for printers:', error);
      console.error('Error message:', error?.message);
      console.error('Error stack:', error?.stack);
      setPrinters([]);
      Alert.alert(
        'Scan Error', 
        `Failed to scan for printers:\n\n${error?.message || error}\n\nPlease:\n1. Check Bluetooth is enabled\n2. Grant Location permission\n3. Pair printer in Android Settings\n4. Try again`
      );
    } finally {
      setIsLoading(false);
      setIsScanning(false);
      console.log('=== PrinterSelector: Scan finished ===');
    }
  };

  useEffect(() => {
    if (visible) {
      scanForPrinters();
    } else {
      setPrinters([]);
    }
  }, [visible]);

  const handleSelect = (printer) => {
    console.log('Selected printer:', printer);
    onSelect(printer);
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent={true}
      onRequestClose={onCancel}
    >
      <View style={styles.modalOverlay}>
        <View style={styles.modalContent}>
          <View style={styles.header}>
            <Text style={styles.title}>Select Bluetooth Printer</Text>
            <TouchableOpacity onPress={onCancel} style={styles.closeButton}>
              <Text style={styles.closeButtonText}>✕</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.scanSection}>
            <View style={styles.buttonRow}>
              <TouchableOpacity
                onPress={scanForPrinters}
                disabled={isScanning}
                style={[styles.scanButton, isScanning && styles.scanButtonDisabled, { flex: 1, marginRight: 8 }]}
              >
                {isScanning ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text style={styles.scanButtonText}>🔄 Scan</Text>
                )}
              </TouchableOpacity>
              <TouchableOpacity
                onPress={async () => {
                  try {
                    await openBluetoothSettings();
                    Alert.alert(
                      'Bluetooth Settings',
                      'After pairing your printer in Settings, return here and tap "Scan" to refresh the list.',
                      [{ text: 'OK' }]
                    );
                  } catch (e) {
                    Alert.alert('Error', 'Could not open Bluetooth settings. Please go to Android Settings > Bluetooth manually.');
                  }
                }}
                style={[styles.settingsButton, { flex: 1, marginLeft: 8 }]}
              >
                <Text style={styles.settingsButtonText}>⚙️ Settings</Text>
              </TouchableOpacity>
            </View>
            <Text style={styles.scanHint}>
              Shows paired Bluetooth devices. Tap "Settings" to pair new devices.
            </Text>
          </View>

          {isLoading ? (
            <View style={styles.loadingContainer}>
              <ActivityIndicator size="large" color="#2196F3" />
              <Text style={styles.loadingText}>Loading paired devices...</Text>
              <Text style={styles.loadingSubtext}>Checking Bluetooth devices...</Text>
              <Text style={styles.loadingSubtext}>Please wait...</Text>
            </View>
          ) : printers.length === 0 ? (
            <View style={styles.emptyContainer}>
              <Text style={styles.emptyIcon}>📱</Text>
              <Text style={styles.emptyText}>No Paired Devices Found</Text>
              <Text style={styles.emptySubtext}>
                This app only shows devices that are already paired in Android Settings.
              </Text>
              <View style={styles.instructionsBox}>
                <Text style={styles.instructionsTitle}>To pair your printer:</Text>
                <Text style={styles.instructionItem}>1. Tap "Settings" button above</Text>
                <Text style={styles.instructionItem}>2. Turn ON your printer</Text>
                <Text style={styles.instructionItem}>3. Put printer in pairing mode</Text>
                <Text style={styles.instructionItem}>4. Find your printer in Settings</Text>
                <Text style={styles.instructionItem}>5. Tap to pair</Text>
                <Text style={styles.instructionItem}>6. Return here and tap "Scan"</Text>
              </View>
              <Text style={styles.debugText}>
                Debug: printers.length = {printers.length}
              </Text>
            </View>
          ) : (
            <View style={styles.listContainer}>
              <View style={styles.printerListHeader}>
                <Text style={styles.printerListHeaderText}>
                  Found {printers.length} paired device{printers.length !== 1 ? 's' : ''}
                </Text>
              </View>
              <ScrollView 
                style={styles.printerList}
                contentContainerStyle={styles.printerListContent}
                showsVerticalScrollIndicator={true}
              >
                {printers.map((printer, index) => {
                  const macAddress = printer?.inner_mac_address || printer?.macAddress || printer?.address || 'Unknown';
                  const deviceName = printer?.device_name || printer?.name || `Device ${index + 1}`;
                  
                  console.log(`PrinterSelector: Rendering device ${index}:`, {
                    name: deviceName,
                    mac: macAddress,
                    full: printer
                  });
                  
                  return (
                    <TouchableOpacity
                      key={`device-${index}-${macAddress}`}
                      style={styles.printerItem}
                      onPress={() => handleSelect(printer)}
                    >
                      <View style={styles.printerInfo}>
                        <View style={styles.deviceNameRow}>
                          <Text style={styles.printerName}>{deviceName}</Text>
                          <View style={styles.pairedBadge}>
                            <Text style={styles.pairedBadgeText}>Paired</Text>
                          </View>
                        </View>
                        <Text style={styles.printerMac}>MAC: {macAddress}</Text>
                      </View>
                      <Text style={styles.selectArrow}>→</Text>
                    </TouchableOpacity>
                  );
                })}
              </ScrollView>
            </View>
          )}

          <View style={styles.footer}>
            <TouchableOpacity onPress={onCancel} style={styles.cancelButton}>
              <Text style={styles.cancelButtonText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: '90%',
    paddingBottom: 20,
    minHeight: 300,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
  },
  closeButton: {
    padding: 5,
  },
  closeButtonText: {
    fontSize: 24,
    color: '#666',
  },
  scanSection: {
    padding: 15,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  buttonRow: {
    flexDirection: 'row',
    marginBottom: 10,
  },
  scanButton: {
    backgroundColor: '#2196F3',
    padding: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  scanButtonDisabled: {
    backgroundColor: '#ccc',
  },
  scanButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  settingsButton: {
    backgroundColor: '#4CAF50',
    padding: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  settingsButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  scanHint: {
    fontSize: 12,
    color: '#666',
    textAlign: 'center',
    marginTop: 5,
  },
  loadingContainer: {
    padding: 40,
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 10,
    color: '#666',
    fontSize: 14,
  },
  loadingSubtext: {
    marginTop: 5,
    color: '#999',
    fontSize: 12,
  },
  emptyContainer: {
    padding: 30,
    alignItems: 'center',
  },
  emptyIcon: {
    fontSize: 48,
    marginBottom: 15,
  },
  debugText: {
    marginTop: 10,
    fontSize: 11,
    color: '#999',
    fontStyle: 'italic',
  },
  instructionsBox: {
    backgroundColor: '#f0f7ff',
    padding: 15,
    borderRadius: 8,
    width: '100%',
    marginBottom: 15,
  },
  instructionsTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2196F3',
    marginBottom: 10,
  },
  instructionItem: {
    fontSize: 13,
    color: '#555',
    lineHeight: 20,
    marginBottom: 5,
  },
  printerListHeader: {
    padding: 15,
    backgroundColor: '#f5f5f5',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  printerListHeaderText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
  },
  emptyText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 10,
    textAlign: 'center',
  },
  emptySubtext: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 20,
  },
  listContainer: {
    flex: 1,
    minHeight: 300,
  },
  printerList: {
    flex: 1,
  },
  printerListContent: {
    paddingBottom: 10,
    flexGrow: 1,
  },
  printerItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 15,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  printerInfo: {
    flex: 1,
  },
  deviceNameRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  printerName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    flex: 1,
  },
  pairedBadge: {
    backgroundColor: '#4CAF50',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
    marginLeft: 8,
  },
  pairedBadgeText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: 'bold',
  },
  printerMac: {
    fontSize: 12,
    color: '#666',
    fontFamily: 'monospace',
    marginBottom: 2,
  },
  deviceType: {
    fontSize: 11,
    color: '#999',
  },
  selectArrow: {
    fontSize: 20,
    color: '#2196F3',
    marginLeft: 10,
  },
  footer: {
    padding: 15,
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
  },
  cancelButton: {
    backgroundColor: '#f5f5f5',
    padding: 12,
    borderRadius: 8,
    alignItems: 'center',
  },
  cancelButtonText: {
    color: '#333',
    fontSize: 16,
    fontWeight: '600',
  },
});


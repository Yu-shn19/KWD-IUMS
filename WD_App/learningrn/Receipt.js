import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Alert, Image, Modal, TextInput, Switch } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { printReceiptEscPos, isSupported as btSupported } from './services/bluetoothPrinter';
import PrinterSelector from './components/PrinterSelector';
import { printerStorage, receiptFormatStorage, receiptLogoStorage } from './services/storage';

export default function Receipt({ onBack, data }) {
  const [showPrinterSelector, setShowPrinterSelector] = useState(false);
  const [isPrinting, setIsPrinting] = useState(false);
  const [isEditMode, setIsEditMode] = useState(false);
  const [showFormatEditor, setShowFormatEditor] = useState(false);
  const [logoUri, setLogoUri] = useState(null);
  const [isInitialized, setIsInitialized] = useState(false);
  
  // Receipt format settings with safe defaults
  const defaultFormat = {
    companyName: 'HAGONOY WATER DISTRICT',
    address: 'Guihing Hagonoy, Davao Del Sur',
    vatId: 'VATS ID DBN 001-437-440',
    contact: 'Contact Number: 09073814037',
    email: 'Email: hagonoywaterdistrict@yahoo.com',
    companyFontSize: 16,
    metaFontSize: 12,
    sectionTitleFontSize: 14,
    textAlign: 'center', // 'left', 'center', 'right'
    logoSize: 120,
    showLogo: true,
  };
  
  const [format, setFormat] = useState(defaultFormat);

  // Load saved format and logo on mount
  useEffect(() => {
    const initialize = async () => {
      try {
        await loadSavedFormat();
        await loadSavedLogo();
        // Ensure logo is always shown for dummy receipts
        if (!data) {
          setFormat(prevFormat => ({ ...prevFormat, showLogo: true }));
        }
        setIsInitialized(true);
        
        // If data has openFormatEditor flag, open the format editor after initialization
        if (data && data.openFormatEditor) {
          // Use setTimeout to ensure state is ready before opening modal
          setTimeout(() => {
            try {
              setIsEditMode(true);
              setShowFormatEditor(true);
            } catch (e) {
              console.error('Error opening format editor on mount:', e);
              Alert.alert('Error', 'Failed to open format editor. Please try again.');
            }
          }, 300);
        }
      } catch (error) {
        console.error('Error initializing receipt component:', error);
        setIsInitialized(true); // Still mark as initialized to prevent infinite loading
        // Don't crash - continue with defaults
      }
    };
    initialize();
  }, [data]);

  const loadSavedFormat = async () => {
    try {
      const savedFormat = await receiptFormatStorage.getFormat();
      if (savedFormat) {
        // Ensure all required fields exist with safe defaults
        setFormat(prevFormat => ({
          ...prevFormat,
          ...savedFormat,
          companyName: savedFormat.companyName || prevFormat.companyName,
          address: savedFormat.address || prevFormat.address,
          vatId: savedFormat.vatId || prevFormat.vatId,
          contact: savedFormat.contact || prevFormat.contact,
          email: savedFormat.email || prevFormat.email,
          companyFontSize: savedFormat.companyFontSize || prevFormat.companyFontSize,
          metaFontSize: savedFormat.metaFontSize || prevFormat.metaFontSize,
          sectionTitleFontSize: savedFormat.sectionTitleFontSize || prevFormat.sectionTitleFontSize,
          textAlign: savedFormat.textAlign || prevFormat.textAlign,
          logoSize: savedFormat.logoSize || prevFormat.logoSize,
          showLogo: savedFormat.showLogo !== undefined ? savedFormat.showLogo : prevFormat.showLogo,
        }));
      }
    } catch (error) {
      console.error('Error loading receipt format:', error);
      // Don't crash - use defaults
    }
  };

  const loadSavedLogo = async () => {
    try {
      const savedLogo = await receiptLogoStorage.getLogo();
      if (savedLogo) {
        setLogoUri(savedLogo);
      } else {
        // For dummy receipts, always use the bundled logo from assets
        // This ensures the logo is always visible when viewing/editing receipt format
        setLogoUri(null); // null means use require('./assets/WD-logo.jpg')
      }
    } catch (error) {
      console.error('Error loading receipt logo:', error);
      // On error, ensure we still show the bundled logo
      setLogoUri(null);
    }
  };

  const saveFormat = async () => {
    try {
      await receiptFormatStorage.saveFormat(format);
      Alert.alert('Success', 'Receipt format saved successfully!');
      setShowFormatEditor(false);
      setIsEditMode(false);
    } catch (error) {
      Alert.alert('Error', 'Failed to save receipt format.');
      console.error('Error saving format:', error);
    }
  };

  const pickImage = async () => {
    try {
      // Request permissions
      const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert('Permission Required', 'Please grant camera roll permissions to select an image.');
        return;
      }

      // Launch image picker
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.8,
      });

      if (!result.canceled && result.assets && result.assets[0]) {
        const uri = result.assets[0].uri;
        setLogoUri(uri);
        await receiptLogoStorage.saveLogo(uri);
        Alert.alert('Success', 'Logo updated successfully!');
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to pick image.');
      console.error('Error picking image:', error);
    }
  };

  const resetFormat = () => {
    Alert.alert(
      'Reset Format',
      'Are you sure you want to reset to default format?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Reset',
          onPress: async () => {
            try {
              setFormat(defaultFormat);
              await receiptFormatStorage.saveFormat(defaultFormat);
              await receiptLogoStorage.clearLogo();
              setLogoUri(null);
              Alert.alert('Success', 'Format reset to default.');
            } catch (error) {
              console.error('Error resetting format:', error);
              Alert.alert('Error', 'Failed to reset format.');
            }
          },
        },
      ]
    );
  };

  // Dummy receipt data for format editing
  const dummyReceipt = {
    readingDate: '2025-01-15',
    dueDate: '2025-02-15',
    periodCovered: '2025-01-15 / 2025-02-15',
    zone: '081',
    consumerType: 'Residential',
    sequence: '2982',
    accountNumber: '081-12-2982',
    customer: {
      name: 'Juan Dela Cruz',
      address: '123 Main Street, Barangay Sample, Hagonoy, Davao Del Sur',
      meterNumber: 'MTR-12345'
    },
    readings: {
      current: 1250,
      previous: 1200,
      consumption: 50,
      isHighConsumption: false
    },
    billing: {
      currentBill: '195.00',
      meterMaintenanceCharge: '20.00',
      totalCurrent: '215.00',
      arrears: '0.00',
      others: '0.00',
      totalBill: '215.00',
      surcharge: '19.50',
      totalWithSurcharge: '234.50'
    },
    meterReader: 'Sample Reader'
  };

  // Use dummy data if no real data is available
  // If data has openFormatEditor flag, use dummy receipt for preview
  // Always ensure receiptData is defined (fallback to dummyReceipt)
  const receiptData = (data && !data.openFormatEditor) ? data : dummyReceipt;
  const isDummyReceipt = !data || data.openFormatEditor;

  const handlePrint = async () => {
    // Allow printing even when using the dummy receipt so you can test the logo and layout
    if (!receiptData) {
      Alert.alert('No Receipt', 'There is no receipt data to print.');
      return;
    }
    if (!btSupported()) {
      Alert.alert(
        'Bluetooth Printing Not Ready',
        [
          'Install and rebuild to enable Bluetooth printing:',
          '1) npm i react-native-thermal-receipt-printer',
          '2) Expo: npx expo prebuild, then make a dev build',
          '3) Open the app in that build and try again.'
        ].join('\n')
      );
      return;
    }

    // Check if we have a saved printer
    const savedPrinter = await printerStorage.getPrinter();
    
    if (savedPrinter && savedPrinter.inner_mac_address) {
      // Auto-print with saved printer
      setIsPrinting(true);
      try {
        const ok = await printReceiptEscPos(receiptData, savedPrinter);
        if (!ok) {
          Alert.alert(
            'Print Failed',
            'Could not print to the saved printer. Would you like to select a different printer?',
            [
              { text: 'Cancel', style: 'cancel' },
              { 
                text: 'Select Printer', 
                onPress: () => setShowPrinterSelector(true) 
              }
            ]
          );
        }
      } catch (e) {
        Alert.alert(
          'Print Error',
          e.message || 'Failed to print receipt. Would you like to select a different printer?',
          [
            { text: 'Cancel', style: 'cancel' },
            { 
              text: 'Select Printer', 
              onPress: () => setShowPrinterSelector(true) 
            }
          ]
        );
      } finally {
        setIsPrinting(false);
      }
    } else {
      // No saved printer, show selector
      setShowPrinterSelector(true);
    }
  };

  // Quick entry point to open the format editor from the receipt view
  const openFormatEditor = () => {
    try {
      if (!isInitialized) {
        console.warn('Component not initialized yet, waiting...');
        setTimeout(() => openFormatEditor(), 100);
        return;
      }
      console.log('Opening format editor...');
      setIsEditMode(true);
      setShowFormatEditor(true);
      console.log('Format editor opened successfully');
    } catch (error) {
      console.error('Error opening format editor:', error);
      Alert.alert('Error', 'Failed to open format editor. Please try again.');
    }
  };

  // Ensure format is always defined to prevent crashes
  const safeFormat = format || defaultFormat;

  // Don't render until initialized to prevent crashes
  if (!isInitialized) {
    return (
      <View style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={onBack}>
            <Text style={styles.backButtonText}>← Back</Text>
          </TouchableOpacity>
          <Text style={styles.title}>Loading...</Text>
        </View>
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', padding: 20 }}>
          <Text>Loading receipt format...</Text>
        </View>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={onBack}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Receipt {isDummyReceipt && '(Sample)'}</Text>
        <View style={styles.headerButtons}>
          <TouchableOpacity 
            style={[styles.changePrinterButton]} 
            onPress={() => setShowPrinterSelector(true)}
          >
            <Text style={styles.changePrinterText}>Change Printer</Text>
          </TouchableOpacity>
          <TouchableOpacity 
            style={[styles.printButton, isPrinting && styles.printButtonDisabled]} 
            onPress={handlePrint}
            disabled={isPrinting}
          >
            <Text style={styles.printText}>{isPrinting ? 'Printing...' : 'Print'}</Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.card}>
        {/* Logo Section - Always show logo at the top for dummy receipts */}
        {safeFormat && safeFormat.showLogo !== false && (
          <View style={styles.logoContainer}>
            <TouchableOpacity 
              onPress={() => {
                try {
                  if (isEditMode || showFormatEditor) {
                    pickImage();
                  }
                } catch (e) {
                  console.error('Error in logo touch handler:', e);
                }
              }}
              style={styles.logoTouchable}
            >
              {logoUri ? (
                <Image 
                  source={{ uri: logoUri }} 
                  style={[styles.logoImage, { width: safeFormat.logoSize || 120, height: safeFormat.logoSize || 120 }]}
                  resizeMode="contain"
                  onError={(error) => {
                    console.warn('Error loading custom logo, using default:', error);
                    try {
                      setLogoUri(null);
                    } catch (e) {
                      console.error('Error resetting logo URI:', e);
                    }
                  }}
                />
              ) : (
                <Image 
                  source={require('./assets/WD-logo.jpg')} 
                  style={[styles.logoImage, { width: safeFormat.logoSize || 120, height: safeFormat.logoSize || 120 }]}
                  resizeMode="contain"
                  onError={(error) => {
                    console.error('Error loading default logo:', error);
                    // Don't crash - just log the error
                  }}
                />
              )}
              {(isEditMode || showFormatEditor) && (
                <View style={styles.logoEditOverlay}>
                  <Text style={styles.logoEditText}>Tap to Change</Text>
                </View>
              )}
            </TouchableOpacity>
          </View>
        )}
        
        <Text style={[styles.company, { 
          fontSize: safeFormat.companyFontSize || 16, 
          textAlign: safeFormat.textAlign || 'center'
        }]}>
          {safeFormat.companyName || 'HAGONOY WATER DISTRICT'}
        </Text>
        <Text style={[styles.meta, { 
          fontSize: safeFormat.metaFontSize || 12, 
          textAlign: safeFormat.textAlign || 'center'
        }]}>
          {safeFormat.address || 'Guihing Hagonoy, Davao Del Sur'}
        </Text>
        <Text style={[styles.meta, { 
          fontSize: safeFormat.metaFontSize || 12, 
          textAlign: safeFormat.textAlign || 'center'
        }]}>
          {safeFormat.vatId || 'VATS ID DBN 001-437-440'}
        </Text>
        <Text style={[styles.meta, { 
          fontSize: safeFormat.metaFontSize || 12, 
          textAlign: safeFormat.textAlign || 'center'
        }]}>
          {safeFormat.contact || 'Contact Number: 09073814037'}
        </Text>
        <Text style={[styles.meta, { 
          fontSize: safeFormat.metaFontSize || 12, 
          textAlign: safeFormat.textAlign || 'center'
        }]}>
          {safeFormat.email || 'Email: hagonoywaterdistrict@yahoo.com'}
        </Text>

        <Text style={[styles.sectionTitle, { 
          fontSize: safeFormat.sectionTitleFontSize || 14,
          textAlign: safeFormat.textAlign || 'center'
        }]}>
          NOTICE OF COLLECTION BILL
        </Text>

        {isDummyReceipt && (
          <View style={styles.dummyNotice}>
            <Text style={styles.dummyNoticeText}>
              📝 This is a sample receipt for format editing. Your changes will apply to all receipts.
            </Text>
          </View>
        )}

        <View style={styles.row}><Text>Period Covered: {receiptData.periodCovered}</Text></View>
        <View style={styles.row}><Text>Zone: {receiptData.zone}    Consumer type: {receiptData.consumerType}</Text></View>
        <View style={styles.row}><Text>Sequence: {receiptData.sequence}</Text></View>
        <View style={styles.row}><Text>Acct No.: {receiptData.accountNumber}</Text></View>
        <View style={styles.row}>
          <Text style={styles.customerName}>Name: {receiptData.customer?.name}</Text>
        </View>
        <View style={styles.row}><Text>Address: {receiptData.customer?.address}</Text></View>
        <View style={styles.row}><Text>Meter Number/Size: {receiptData.customer?.meterNumber}</Text></View>

        <Text style={[styles.sectionTitle, { 
          fontSize: safeFormat.sectionTitleFontSize || 14,
          textAlign: safeFormat.textAlign || 'center'
        }]}>
          Readings
        </Text>
        <View style={styles.row}><Text>Reading Date: {receiptData.readingDate}</Text></View>
        <View style={styles.row}><Text>Due Date: {receiptData.dueDate}</Text></View>
        <View style={styles.row}><Text>Present: {receiptData.readings?.current}</Text></View>
        <View style={styles.row}><Text>Previous: {receiptData.readings?.previous}</Text></View>
        <View style={styles.row}>
          <Text>Used: {receiptData.readings?.consumption}
            {receiptData.readings?.isHighConsumption && <Text style={styles.highConsumption}> High Consumption</Text>}
          </Text>
        </View>

        <Text style={[styles.sectionTitle, { 
          fontSize: safeFormat.sectionTitleFontSize || 14,
          textAlign: safeFormat.textAlign || 'center'
        }]}>
          Billing
        </Text>
        <View style={styles.billingLine}>
          <Text style={styles.billingLabel}>Current Bill:</Text>
          <Text style={styles.billingValue}>{receiptData.billing?.currentBill}</Text>
        </View>
        <View style={styles.billingLine}>
          <Text style={styles.billingLabel}>Water Maintenance Charge:</Text>
          <Text style={styles.billingValue}>{receiptData.billing?.meterMaintenanceCharge}</Text>
        </View>
        <View style={[styles.billingLine, styles.totalCurrentLine]}>
          <Text style={styles.billingLabel}>TOTAL CURRENT</Text>
          <Text style={styles.billingValue}>{receiptData.billing?.totalCurrent}</Text>
        </View>
        <View style={[styles.billingLine, styles.compactBillingLine]}>
          <Text style={styles.billingLabel}>Arrears</Text>
          <Text style={styles.billingValue}>{receiptData.billing?.arrears}</Text>
        </View>
        <View style={[styles.billingLine, styles.compactBillingLine]}>
          <Text style={styles.billingLabel}>Others</Text>
          <Text style={styles.billingValue}>{receiptData.billing?.others}</Text>
        </View>
        <View style={styles.totalRow}><Text style={styles.total}>TOTAL BILL: {receiptData.billing?.totalBill}</Text></View>
        
        <Text style={[styles.meta, { marginTop: 10, marginBottom: 4, textAlign: 'left' }]}>IF UNPAID AT HWD OFFICE</Text>
        <View style={styles.afterLine}>
          <Text style={styles.afterLabel}>After:</Text>
          <Text style={styles.afterDateInline}>{receiptData.dueDate}</Text>
        </View>
        <View style={styles.row}><Text>Surcharge: {receiptData.billing?.surcharge}</Text></View>
        <View style={styles.totalRow}><Text style={styles.total}>TOTAL W/ SUR: {receiptData.billing?.totalWithSurcharge}</Text></View>

        <Text style={[styles.meta, { marginTop: 10 }]}>Reader: {receiptData.meterReader}</Text>
        <Text style={[styles.meta, { textAlign: 'center', marginTop: 6 }]}>{receiptData.accountNumber}</Text>
      </View>

      {/* Format Editor Modal */}
      <Modal
        visible={showFormatEditor && isInitialized}
        animationType="slide"
        transparent={true}
        onRequestClose={() => {
          try {
            setShowFormatEditor(false);
            setIsEditMode(false);
          } catch (e) {
            console.error('Error closing modal:', e);
          }
        }}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Edit Receipt Format</Text>
              <TouchableOpacity onPress={() => {
                try {
                  setShowFormatEditor(false);
                  setIsEditMode(false);
                } catch (e) {
                  console.error('Error closing modal:', e);
                }
              }}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              {/* Logo Settings */}
              <View style={styles.formatSection}>
                <Text style={styles.formatSectionTitle}>Logo Settings</Text>
                <View style={styles.formatRow}>
                  <Text>Show Logo</Text>
                  <Switch
                    value={safeFormat.showLogo !== false}
                    onValueChange={(value) => {
                      try {
                        setFormat({ ...safeFormat, showLogo: value });
                      } catch (e) {
                        console.error('Error updating showLogo:', e);
                        Alert.alert('Error', 'Failed to update logo setting.');
                      }
                    }}
                  />
                </View>
                {safeFormat.showLogo !== false && (
                  <>
                    <TouchableOpacity 
                      style={styles.imagePickerButton} 
                      onPress={() => {
                        try {
                          pickImage();
                        } catch (e) {
                          console.error('Error opening image picker:', e);
                          Alert.alert('Error', 'Failed to open image picker.');
                        }
                      }}
                    >
                      <Text style={styles.imagePickerText}>
                        {logoUri ? 'Change Logo Image' : 'Select Logo Image'}
                      </Text>
                    </TouchableOpacity>
                    <View style={styles.formatRow}>
                      <Text>Logo Size: {safeFormat.logoSize || 120}px</Text>
                      <View style={styles.sliderContainer}>
                        <TextInput
                          style={styles.sliderInput}
                          value={(safeFormat.logoSize || 120).toString()}
                          onChangeText={(text) => {
                            try {
                              const size = parseInt(text) || 120;
                              if (size >= 40 && size <= 200) {
                                setFormat({ ...safeFormat, logoSize: size });
                              }
                            } catch (e) {
                              console.error('Error updating logo size:', e);
                            }
                          }}
                          keyboardType="numeric"
                        />
                      </View>
                    </View>
                  </>
                )}
              </View>

              {/* Company Information */}
              <View style={styles.formatSection}>
                <Text style={styles.formatSectionTitle}>Company Information</Text>
                <View style={styles.inputGroup}>
                  <Text style={styles.inputLabel}>Company Name</Text>
                    <TextInput
                      style={styles.textInput}
                      value={safeFormat.companyName || ''}
                      onChangeText={(text) => {
                        try {
                          setFormat({ ...safeFormat, companyName: text });
                        } catch (e) {
                          console.error('Error updating company name:', e);
                        }
                      }}
                      placeholder="Company Name"
                    />
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.inputLabel}>Address</Text>
                    <TextInput
                      style={styles.textInput}
                      value={safeFormat.address || ''}
                      onChangeText={(text) => {
                        try {
                          setFormat({ ...safeFormat, address: text });
                        } catch (e) {
                          console.error('Error updating address:', e);
                        }
                      }}
                      placeholder="Address"
                    />
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.inputLabel}>VAT ID</Text>
                  <TextInput
                      style={styles.textInput}
                      value={safeFormat.vatId || ''}
                      onChangeText={(text) => {
                        try {
                          setFormat({ ...safeFormat, vatId: text });
                        } catch (e) {
                          console.error('Error updating VAT ID:', e);
                        }
                      }}
                      placeholder="VAT ID"
                    />
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.inputLabel}>Contact Number</Text>
                  <TextInput
                      style={styles.textInput}
                      value={safeFormat.contact || ''}
                      onChangeText={(text) => {
                        try {
                          setFormat({ ...safeFormat, contact: text });
                        } catch (e) {
                          console.error('Error updating contact:', e);
                        }
                      }}
                      placeholder="Contact Number"
                    />
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.inputLabel}>Email</Text>
                  <TextInput
                      style={styles.textInput}
                      value={safeFormat.email || ''}
                      onChangeText={(text) => {
                        try {
                          setFormat({ ...safeFormat, email: text });
                        } catch (e) {
                          console.error('Error updating email:', e);
                        }
                      }}
                      placeholder="Email"
                    />
                </View>
              </View>

              {/* Font Settings */}
              <View style={styles.formatSection}>
                <Text style={styles.formatSectionTitle}>Font Settings</Text>
                <View style={styles.formatRow}>
                  <Text>Company Font Size: {safeFormat.companyFontSize || 16}px</Text>
                  <View style={styles.sliderContainer}>
                    <TextInput
                      style={styles.sliderInput}
                      value={(safeFormat.companyFontSize || 16).toString()}
                      onChangeText={(text) => {
                        try {
                          const size = parseInt(text) || 16;
                          if (size >= 10 && size <= 30) {
                            setFormat({ ...safeFormat, companyFontSize: size });
                          }
                        } catch (e) {
                          console.error('Error updating company font size:', e);
                        }
                      }}
                      keyboardType="numeric"
                    />
                  </View>
                </View>
                <View style={styles.formatRow}>
                  <Text>Meta Font Size: {safeFormat.metaFontSize || 12}px</Text>
                  <View style={styles.sliderContainer}>
                    <TextInput
                      style={styles.sliderInput}
                      value={(safeFormat.metaFontSize || 12).toString()}
                      onChangeText={(text) => {
                        try {
                          const size = parseInt(text) || 12;
                          if (size >= 8 && size <= 20) {
                            setFormat({ ...safeFormat, metaFontSize: size });
                          }
                        } catch (e) {
                          console.error('Error updating meta font size:', e);
                        }
                      }}
                      keyboardType="numeric"
                    />
                  </View>
                </View>
                <View style={styles.formatRow}>
                  <Text>Section Title Font Size: {safeFormat.sectionTitleFontSize || 14}px</Text>
                  <View style={styles.sliderContainer}>
                    <TextInput
                      style={styles.sliderInput}
                      value={(safeFormat.sectionTitleFontSize || 14).toString()}
                      onChangeText={(text) => {
                        try {
                          const size = parseInt(text) || 14;
                          if (size >= 10 && size <= 24) {
                            setFormat({ ...safeFormat, sectionTitleFontSize: size });
                          }
                        } catch (e) {
                          console.error('Error updating section title font size:', e);
                        }
                      }}
                      keyboardType="numeric"
                    />
                  </View>
                </View>
              </View>

              {/* Text Alignment */}
              <View style={styles.formatSection}>
                <Text style={styles.formatSectionTitle}>Text Alignment</Text>
                <View style={styles.alignmentButtons}>
                  <TouchableOpacity
                    style={[
                      styles.alignmentButton,
                      (safeFormat.textAlign || 'center') === 'left' && styles.alignmentButtonActive
                    ]}
                    onPress={() => {
                      try {
                        setFormat({ ...safeFormat, textAlign: 'left' });
                      } catch (e) {
                        console.error('Error updating text align:', e);
                      }
                    }}
                  >
                    <Text style={[
                      styles.alignmentButtonText,
                      (safeFormat.textAlign || 'center') === 'left' && styles.alignmentButtonTextActive
                    ]}>
                      Left
                    </Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[
                      styles.alignmentButton,
                      (safeFormat.textAlign || 'center') === 'center' && styles.alignmentButtonActive
                    ]}
                    onPress={() => {
                      try {
                        setFormat({ ...safeFormat, textAlign: 'center' });
                      } catch (e) {
                        console.error('Error updating text align:', e);
                      }
                    }}
                  >
                    <Text style={[
                      styles.alignmentButtonText,
                      (safeFormat.textAlign || 'center') === 'center' && styles.alignmentButtonTextActive
                    ]}>
                      Center
                    </Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[
                      styles.alignmentButton,
                      (safeFormat.textAlign || 'center') === 'right' && styles.alignmentButtonActive
                    ]}
                    onPress={() => {
                      try {
                        setFormat({ ...safeFormat, textAlign: 'right' });
                      } catch (e) {
                        console.error('Error updating text align:', e);
                      }
                    }}
                  >
                    <Text style={[
                      styles.alignmentButtonText,
                      (safeFormat.textAlign || 'center') === 'right' && styles.alignmentButtonTextActive
                    ]}>
                      Right
                    </Text>
                  </TouchableOpacity>
                </View>
              </View>

              {/* Action Buttons */}
              <View style={styles.modalActions}>
                <TouchableOpacity 
                  style={styles.resetButton} 
                  onPress={() => {
                    try {
                      resetFormat();
                    } catch (e) {
                      console.error('Error resetting format:', e);
                      Alert.alert('Error', 'Failed to reset format.');
                    }
                  }}
                >
                  <Text style={styles.resetButtonText}>Reset to Default</Text>
                </TouchableOpacity>
                <TouchableOpacity 
                  style={styles.saveButton} 
                  onPress={() => {
                    try {
                      saveFormat();
                    } catch (e) {
                      console.error('Error saving format:', e);
                      Alert.alert('Error', 'Failed to save format.');
                    }
                  }}
                >
                  <Text style={styles.saveButtonText}>Save Format</Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      <PrinterSelector
        visible={showPrinterSelector}
        onSelect={async (printer) => {
          setShowPrinterSelector(false);
          setIsPrinting(true);
          try {
            // Save the selected printer for future use
            await printerStorage.savePrinter(printer);
            
            const ok = await printReceiptEscPos(receiptData, printer);
            if (!ok) {
              Alert.alert('Print Failed', 'Could not print to the selected printer. Please try again.');
            }
          } catch (e) {
            Alert.alert('Print Error', e.message || 'Failed to print receipt.');
          } finally {
            setIsPrinting(false);
          }
        }}
        onCancel={() => setShowPrinterSelector(false)}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    backgroundColor: 'white',
    paddingTop: 50,
    paddingHorizontal: 20,
    paddingBottom: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    elevation: 4,
  },
  backButton: { },
  backButtonText: { color: '#2196F3', fontWeight: '600', fontSize: 16 },
  title: { fontSize: 18, fontWeight: 'bold', color: '#333' },
  headerButtons: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  changePrinterButton: { backgroundColor: '#FF9800', paddingHorizontal: 10, paddingVertical: 6, borderRadius: 6 },
  changePrinterText: { color: '#fff', fontWeight: 'bold', fontSize: 12 },
  printButton: { backgroundColor: '#4CAF50', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 6 },
  printButtonDisabled: { backgroundColor: '#ccc' },
  printText: { color: '#fff', fontWeight: 'bold' },
  card: {
    backgroundColor: 'white',
    margin: 16,
    padding: 16,
    borderRadius: 10,
    elevation: 2,
  },
  logoContainer: {
    alignItems: 'center',
    marginBottom: 10,
    paddingVertical: 10,
  },
  logoImage: {
    width: 80,
    height: 80,
    borderRadius: 8,
  },
  company: { textAlign: 'center', fontSize: 16, fontWeight: '700', marginBottom: 4 },
  meta: { textAlign: 'center', fontSize: 12, color: '#666' },
  sectionTitle: { fontSize: 14, fontWeight: '700', marginTop: 14, marginBottom: 6, color: '#333' },
  row: { marginVertical: 2 },
  totalRow: { marginTop: 8 },
  total: { fontSize: 25, fontWeight: '800', color: '#2c3e50' },
  customerName: { fontSize: 25, fontWeight: '800', color: '#111' },
  billingLine: {
    marginVertical: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  totalCurrentLine: {
    marginBottom: 10,
  },
  compactBillingLine: {
    marginVertical: 0,
  },
  billingLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#222',
  },
  billingValue: {
    fontSize: 14,
    color: '#222',
  },
  afterDate: {
    fontSize: 25,
    fontWeight: '800',
    color: '#111',
  },
  afterDateCentered: {
    fontSize: 25,
    fontWeight: '800',
    color: '#111',
    textAlign: 'center',
    marginTop: 2,
    marginBottom: 2,
  },
  afterLine: {
    marginVertical: 2,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 12,
  },
  afterLabel: {
    fontSize: 14,
    color: '#222',
  },
  afterDateInline: {
    fontSize: 25,
    fontWeight: '800',
    color: '#111',
  },
  highConsumption: { color: '#e74c3c', fontWeight: '700' },
  emptyBox: { margin: 20, padding: 20, backgroundColor: 'white', borderRadius: 10, alignItems: 'center' },
  emptyText: { fontSize: 16, fontWeight: '600', color: '#333' },
  emptySub: { fontSize: 13, color: '#666', marginTop: 6, textAlign: 'center' },
  dummyNotice: {
    backgroundColor: '#E3F2FD',
    padding: 12,
    borderRadius: 8,
    marginBottom: 16,
    borderLeftWidth: 4,
    borderLeftColor: '#2196F3',
  },
  dummyNoticeText: {
    fontSize: 13,
    color: '#1976D2',
    textAlign: 'center',
    fontWeight: '500',
  },
  editButton: { backgroundColor: '#2196F3', paddingHorizontal: 10, paddingVertical: 6, borderRadius: 6 },
  editButtonActive: { backgroundColor: '#4CAF50' },
  editButtonText: { color: '#fff', fontWeight: 'bold', fontSize: 12 },
  logoTouchable: { position: 'relative' },
  logoEditOverlay: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(0,0,0,0.6)',
    padding: 4,
    borderRadius: 4,
  },
  logoEditText: { color: '#fff', fontSize: 10, textAlign: 'center', fontWeight: '600' },
  // Modal Styles
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: 'white',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: '90%',
    paddingBottom: 20,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  modalTitle: { fontSize: 18, fontWeight: 'bold', color: '#333' },
  modalClose: { fontSize: 24, color: '#666', fontWeight: '300' },
  modalBody: { padding: 20 },
  formatSection: {
    marginBottom: 24,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  formatSectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#333',
    marginBottom: 12,
  },
  formatRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginVertical: 8,
  },
  inputGroup: {
    marginBottom: 12,
  },
  inputLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
    marginBottom: 6,
  },
  textInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    fontSize: 14,
    backgroundColor: '#f9f9f9',
  },
  sliderContainer: {
    width: 80,
  },
  sliderInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 6,
    padding: 6,
    fontSize: 14,
    textAlign: 'center',
    backgroundColor: '#f9f9f9',
  },
  imagePickerButton: {
    backgroundColor: '#2196F3',
    padding: 12,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 8,
  },
  imagePickerText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 14,
  },
  alignmentButtons: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    marginTop: 8,
  },
  alignmentButton: {
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 8,
    borderWidth: 2,
    borderColor: '#ddd',
    backgroundColor: '#f9f9f9',
  },
  alignmentButtonActive: {
    borderColor: '#2196F3',
    backgroundColor: '#E3F2FD',
  },
  alignmentButtonText: {
    fontSize: 14,
    color: '#666',
    fontWeight: '600',
  },
  alignmentButtonTextActive: {
    color: '#2196F3',
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 20,
    gap: 12,
  },
  resetButton: {
    flex: 1,
    backgroundColor: '#FF9800',
    padding: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  resetButtonText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 14,
  },
  saveButton: {
    flex: 1,
    backgroundColor: '#4CAF50',
    padding: 14,
    borderRadius: 8,
    alignItems: 'center',
  },
  saveButtonText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 14,
  },
  editFormatBar: {
    marginHorizontal: 16,
    marginBottom: 16,
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: '#E3F2FD',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#BBDEFB',
  },
  editFormatText: {
    color: '#1976D2',
    fontWeight: '600',
    fontSize: 14,
  },
});



import React, { useState } from 'react';
import { StyleSheet, Text, View, TouchableOpacity, Image, ScrollView, Alert, TextInput, Linking, Platform, ActivityIndicator } from 'react-native';
import { paymentAPI, handleAPIError, handleAPISuccess } from './services/api';
import { tokenStorage } from './services/storage';

export default function MobilePayment({ onBack }) {
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState(null);
  const [isProcessing, setIsProcessing] = useState(false);

  const paymentMethods = [
    { id: 'gcash', name: 'GCash', icon: require('./assets/GCash-Logo.png'), color: '#00D4AA' },
    { id: 'palawan', name: 'Palawan Pay', icon: require('./assets/Plogo.png'), color: '#FF6B35' },
  ];

  const handlePaymentMethodSelect = (method) => {
    setSelectedPaymentMethod(method);
  };

  const handleProcessPayment = async () => {
    if (!selectedPaymentMethod) {
      Alert.alert('Error', 'Please select a payment method');
      return;
    }

    setIsProcessing(true);

    try {
      const token = await tokenStorage.getToken();
      
      if (selectedPaymentMethod.id === 'gcash') {
        // Process GCash payment via API
        const paymentData = {
          method: 'gcash',
          amount: 0, // You can add amount input back if needed
          timestamp: new Date().toISOString(),
        };

        const response = await paymentAPI.processMobilePayment(paymentData, token);
        
        if (response.success) {
          // Open GCash app after successful API call
          const gcashUrl = 'gcash://';
          const gcashAppStoreUrl = 'https://apps.apple.com/app/gcash/id519479866';
          const gcashPlayStoreUrl = 'https://play.google.com/store/apps/details?id=com.globe.gcash.android';
          
          Linking.canOpenURL(gcashUrl)
            .then((supported) => {
              if (supported) {
                return Linking.openURL(gcashUrl);
              } else {
                Alert.alert(
                  'GCash App Not Found',
                  'GCash app is not installed on your device. Would you like to install it?',
                  [
                    { text: 'Cancel', style: 'cancel' },
                    { 
                      text: 'Install GCash', 
                      onPress: () => {
                        const storeUrl = Platform.OS === 'ios' ? gcashAppStoreUrl : gcashPlayStoreUrl;
                        Linking.openURL(storeUrl);
                      }
                    }
                  ]
                );
              }
            })
            .catch((err) => {
              console.error('An error occurred', err);
              Alert.alert('Error', 'Unable to open GCash app');
            });
        }
      } else if (selectedPaymentMethod.id === 'palawan') {
        
        const paymentData = {
          method: 'palawan',
          amount: 0, 
          timestamp: new Date().toISOString(),
        };

        const response = await paymentAPI.processMobilePayment(paymentData, token);
        
        if (response.success) {
          const palawanUrls = [
            'palawanpay://',
            'palawan://',
            'palawanexpress://',
            'palawan-express://'
          ];
          
          const tryOpenPalawan = async () => {
            for (const url of palawanUrls) {
              try {
                const supported = await Linking.canOpenURL(url);
                if (supported) {
                  return Linking.openURL(url);
                }
              } catch (err) {
                console.log(`URL ${url} not supported`);
              }
            }
            
            Alert.alert(
              'Palawan Pay',
              'Palawan Pay app is not installed or not accessible. Please install Palawan Pay from your app store.',
              [
                { text: 'Cancel', style: 'cancel' },
                { 
                  text: 'Open App Store', 
                  onPress: () => {
                    const storeUrl = Platform.OS === 'ios' 
                      ? 'https://apps.apple.com/search?term=palawan%20pay'
                      : 'https://play.google.com/store/search?q=palawan%20pay';
                    Linking.openURL(storeUrl);
                  }
                }
              ]
            );
          };
          
          tryOpenPalawan();
        }
      }
    } catch (error) {
      handleAPIError(error, 'Payment processing failed. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <ScrollView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={onBack}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <View style={styles.headerContent}>
          <View style={styles.logoContainer}>
            <Image 
              source={require('./assets/HWD New Logo.png')} 
              style={styles.logoImage}
              resizeMode="contain"
            />
          </View>
          <View style={styles.headerText}>
            <Text style={styles.headerTitle}>Mobile Payment</Text>
            <Text style={styles.headerSubtitle}>Process customer payments</Text>
          </View>
        </View>
      </View>
      
      {/* Payment Method Selection */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Select Payment Method</Text>
        <View style={styles.paymentMethods}>
          {paymentMethods.map((method) => (
            <TouchableOpacity
              key={method.id}
              style={[
                styles.paymentMethodCard,
                selectedPaymentMethod?.id === method.id && styles.selectedPaymentMethodCard
              ]}
              onPress={() => handlePaymentMethodSelect(method)}
            >
                             <View style={styles.paymentMethodInfo}>
                 <Image 
                   source={method.icon} 
                   style={styles.paymentMethodIcon}
                   resizeMode="contain"
                   onError={(error) => console.log('Image loading error:', error)}
                 />
                 <Text style={styles.paymentMethodName}>{method.name}</Text>
               </View>
              {selectedPaymentMethod?.id === method.id && (
                <View style={styles.checkmark}>
                  <Text style={styles.checkmarkText}>✓</Text>
                </View>
              )}
            </TouchableOpacity>
          ))}
        </View>
      </View>

      

      {/* Process Payment Button */}
      <View style={styles.section}>
                          <TouchableOpacity 
           style={[
             styles.processButton,
             (!selectedPaymentMethod || isProcessing) && styles.disabledButton
           ]} 
           onPress={handleProcessPayment}
           disabled={!selectedPaymentMethod || isProcessing}
         >
           {isProcessing ? (
             <ActivityIndicator size="small" color="white" />
           ) : (
             <Text style={styles.processButtonText}>Process Payment</Text>
           )}
         </TouchableOpacity>
      </View>
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
    paddingBottom: 20,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  backButton: {
    marginBottom: 15,
  },
  backButtonText: {
    fontSize: 16,
    color: '#2196F3',
    fontWeight: '600',
  },
  headerContent: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  logoContainer: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: '#2196F3',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 15,
  },
  logoImage: {
    width: 30,
    height: 30,
  },
  headerText: {
    flex: 1,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
  },
  headerSubtitle: {
    fontSize: 14,
    color: '#666',
  },
  section: {
    paddingHorizontal: 20,
    paddingVertical: 20,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 15,
  },

  paymentMethods: {
    flexDirection: 'row',
    gap: 10,
  },
  paymentMethodCard: {
    flex: 1,
    backgroundColor: 'white',
    borderRadius: 10,
    padding: 20,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 1,
    },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  selectedPaymentMethodCard: {
    borderColor: '#2196F3',
    borderWidth: 2,
  },
  paymentMethodInfo: {
    alignItems: 'center',
  },
  paymentMethodIcon: {
    width: 50,
    height: 50,
    marginBottom: 10,
  },
  paymentMethodName: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#333',
  },
  checkmark: {
    position: 'absolute',
    top: 10,
    right: 10,
    backgroundColor: '#2196F3',
    borderRadius: 12,
    width: 24,
    height: 24,
    justifyContent: 'center',
    alignItems: 'center',
  },
  checkmarkText: {
    color: 'white',
    fontSize: 14,
    fontWeight: 'bold',
  },
  
  processButton: {
    backgroundColor: '#2196F3',
    borderRadius: 10,
    paddingVertical: 15,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
    elevation: 5,
  },
  disabledButton: {
    backgroundColor: '#ccc',
  },
  processButtonText: {
    color: 'white',
    fontSize: 18,
    fontWeight: 'bold',
  },
});

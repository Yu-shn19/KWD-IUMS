import React, { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View, TouchableOpacity, Image, ScrollView, Alert, Linking, ActivityIndicator, Modal, Platform } from 'react-native';
import { WebView } from 'react-native-webview';
import * as Location from 'expo-location';
import { routesAPI, consumerAPI } from './services/api';
import { routesStorage, tokenStorage, userStorage } from './services/storage';

export default function Routes({ onBack }) {
  const [customers, setCustomers] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [showMapModal, setShowMapModal] = useState(false);
  const [customerLocation, setCustomerLocation] = useState(null);
  /** Row busy state: which customer key and which action (view saved map vs capture GPS). */
  const [locationAction, setLocationAction] = useState(null);

  const loadRoutes = async (showAlerts = false) => {
    setIsLoading(true);
    
    try {
      const token = await tokenStorage.getToken();
      const userData = await userStorage.getUserData();
      const readerId = userData?.id;
      
      if (!readerId) {
        setCustomers([]);
        if (showAlerts) Alert.alert('No Reader', 'Reader profile not found. Please re-login.');
        return;
      }

      const response = await routesAPI.getRoutes({ reader_id: readerId }, token);
      const list = Array.isArray(response?.schedules)
        ? response.schedules
        : Array.isArray(response?.data)
          ? response.data
          : [];

      await routesStorage.saveRoutes([]);
      
      if (list.length > 0) {
        await routesStorage.saveRoutes(list);
        setCustomers(list);
        if (showAlerts) {
          Alert.alert('✅ Loaded', `${list.length} route(s) assigned.\n\nShowing latest assignments from the server.`);
        }
      } else {
        setCustomers([]);
        if (showAlerts) {
          Alert.alert('No Routes', 'No routes assigned to you. Contact admin to assign schedules.');
        }
      }
    } catch (error) {
      console.error('Error loading routes:', error);
      setCustomers([]);
      if (showAlerts) {
        Alert.alert('Connection Error', error.message || 'Unable to load routes from server.');
      }
    } finally {
      setIsLoading(false);
    }
  };

  const clearAllRoutes = async () => {
    Alert.alert(
      'Clear All Routes',
      'This will delete all routes from the app.\nUse this before downloading NEW assignments from the website.\n\nAre you sure?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Clear All',
          style: 'destructive',
          onPress: async () => {
            try {
              await routesStorage.saveRoutes([]);
              setCustomers([]);
              Alert.alert(
                '✅ Cleared', 
                'All routes deleted.\n\nYou can now ask the admin to assign new routes and then tap Refresh.',
                [{ text: 'OK' }]
              );
            } catch (error) {
              Alert.alert('Error', 'Failed to clear routes: ' + error.message);
            }
          }
        }
      ]
    );
  };

  useEffect(() => {
    loadRoutes(false);
  }, []);

  const formatCustomerName = (item, fallback) => {
    return item.account_name || item.name || fallback;
  };

  const customerRowKey = (customer, index) =>
    String(customer?.id ?? customer?.account_number ?? customer?.accountNumber ?? customer?.account_no ?? index);

  /** Load saved lat/lng from consumer_zone (via API). */
  const fetchSavedCoordinatesForCustomer = async (customer) => {
    const accountNumber = customer.account_number || customer.accountNumber || customer.account_no;
    const name = formatCustomerName(customer, 'Customer');
    const address = customer.address || customer.customer_address || customer.address1 || '';

    if (!accountNumber || accountNumber === '-' || accountNumber === 'N/A' || accountNumber === 'undefined') {
      return null;
    }

    const token = await tokenStorage.getToken();
    if (!token) {
      return null;
    }

    try {
      const fetchPromise = routesAPI.getConsumerZoneByAccount(accountNumber, token);
      const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('Request timed out')), 8000)
      );
      const consumerZone = await Promise.race([fetchPromise, timeoutPromise]);
      const lat = parseFloat(consumerZone?.latitude);
      const lng = parseFloat(consumerZone?.longitude);
      if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
        return { latitude: lat, longitude: lng, accountNumber, name, address };
      }
    } catch (e) {
      console.warn('fetchSavedCoordinatesForCustomer:', e?.message || e);
    }
    return null;
  };

  const handleViewLocation = async (customer, index) => {
    const key = customerRowKey(customer, index);
    setLocationAction({ key, mode: 'view' });
    try {
      const loc = await fetchSavedCoordinatesForCustomer(customer);
      if (!loc) {
        const acc = customer.account_number || customer.accountNumber || customer.account_no || 'N/A';
        Alert.alert(
          'Location not available',
          `No saved coordinates in consumer_zone for account ${acc}.\n\nUse Get location while at the meter to save GPS, or ask an admin to enter coordinates.`
        );
        return;
      }
      setCustomerLocation(loc);
      setShowMapModal(true);
    } catch (error) {
      Alert.alert('Error', error?.message || 'Could not load saved location.');
    } finally {
      setLocationAction(null);
    }
  };

  const handleOpenDirections = useCallback(async () => {
    if (!customerLocation?.latitude || !customerLocation?.longitude) return;
    const lat = customerLocation.latitude;
    const lng = customerLocation.longitude;
    const urlsToTry = [
      `https://www.google.com/maps?q=${lat},${lng}&z=17`,
      `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`,
      `geo:${lat},${lng}?q=${lat},${lng}`,
      `google.navigation:q=${lat},${lng}&mode=d`,
    ];
    for (const url of urlsToTry) {
      try {
        await Linking.openURL(url);
        return;
      } catch (err) {
        console.warn('Linking.openURL failed:', url, err?.message);
      }
    }
    Alert.alert('Open map', 'Could not open Google Maps. Try opening a browser or Maps manually.');
  }, [customerLocation]);

  /** Capture device GPS and POST to /api/consumer/coordinates for this account. */
  const handleGetLocation = async (customer, index) => {
    const accountNumber = String(
      customer.account_number || customer.accountNumber || customer.account_no || ''
    ).trim();
    const name = formatCustomerName(customer, 'Customer');
    if (!accountNumber) {
      Alert.alert('Error', 'This route has no account number.');
      return;
    }

    const key = customerRowKey(customer, index);
    setLocationAction({ key, mode: 'get' });
    try {
      const servicesOn = await Location.hasServicesEnabledAsync();
      if (!servicesOn) {
        Alert.alert(
          'Location services off',
          'Turn on GPS/location in your device settings, then try again.'
        );
        return;
      }
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert('Permission needed', 'Allow location access to save your current position for this consumer.');
        return;
      }

      const timeoutMs = Platform.OS === 'android' ? 35000 : 25000;
      const positionOptions = {
        accuracy: Location.Accuracy.High,
        ...(Platform.OS === 'android' ? { mayShowUserSettingsDialog: true } : {}),
      };

      let latitude;
      let longitude;
      try {
        const location = await Promise.race([
          Location.getCurrentPositionAsync(positionOptions),
          new Promise((_, reject) =>
            setTimeout(
              () => reject(new Error('Location request timed out. Try again outdoors or near a window.')),
              timeoutMs
            )
          ),
        ]);
        latitude = location?.coords?.latitude;
        longitude = location?.coords?.longitude;
      } catch (primaryErr) {
        const last = await Location.getLastKnownPositionAsync({ maxAge: 10 * 60 * 1000 }).catch(() => null);
        latitude = last?.coords?.latitude;
        longitude = last?.coords?.longitude;
        if (
          latitude == null ||
          longitude == null ||
          typeof latitude !== 'number' ||
          typeof longitude !== 'number'
        ) {
          throw primaryErr;
        }
      }

      if (
        latitude == null ||
        longitude == null ||
        typeof latitude !== 'number' ||
        typeof longitude !== 'number'
      ) {
        Alert.alert('Location error', 'Could not read valid coordinates from this device.');
        return;
      }

      const token = await tokenStorage.getToken();
      if (!token) {
        Alert.alert('Session', 'Please log in again to save coordinates.');
        return;
      }

      try {
        const res = await consumerAPI.saveConsumerCoordinates(accountNumber, latitude, longitude, token);
        if (res?.success) {
          Alert.alert('Saved', res.message || `Coordinates saved for ${name}.`);
        } else {
          Alert.alert('Error', res?.message || 'Failed to save coordinates.');
        }
      } catch (apiErr) {
        Alert.alert('Error', apiErr?.message || 'Failed to save coordinates.');
      }
    } catch (error) {
      Alert.alert('Location error', error?.message || 'Could not get location.');
    } finally {
      setLocationAction(null);
    }
  };

  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={onBack}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <View style={styles.headerContent}>
          <View style={styles.logoContainer}>
            <Image 
              source={require('./assets/KlogoC.png')} 
              style={styles.logoImage}
              resizeMode="contain"
            />
          </View>
          <View style={styles.headerText}>
            <Text style={styles.headerTitle}>Reading Routes</Text>
            <Text style={styles.headerSubtitle}>Customer list ({customers.length})</Text>
          </View>
        </View>
      </View>

      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Customers</Text>
          <View style={styles.buttonGroup}>
            <TouchableOpacity style={styles.refreshBtn} onPress={() => loadRoutes(true)} disabled={isLoading}>
              <Text style={styles.refreshText}>{isLoading ? 'Loading...' : 'Refresh'}</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.deleteBtn}
              onPress={clearAllRoutes}
              disabled={isLoading || customers.length === 0}
            >
              <Text style={styles.deleteText}>Clear All</Text>
            </TouchableOpacity>
          </View>
        </View>
        <View style={styles.customerList}>
          {customers.map((customer, index) => {
            const rowKey = customerRowKey(customer, index);
            const rowBusy = locationAction?.key === rowKey;
            const viewLoading = rowBusy && locationAction.mode === 'view';
            const getLoading = rowBusy && locationAction.mode === 'get';
            return (
              <View key={customer.id ?? index} style={styles.customerCard}>
                <View style={styles.customerHeader}>
                  <Text style={styles.routeNumber}>#{customer.sedr_number || customer.sedrNumber || index + 1}</Text>
                  <Text style={styles.customerName}>{formatCustomerName(customer, `Route ${index + 1}`)}</Text>
                </View>
                <Text style={styles.customerAccount}>Account: {customer.account_number || customer.accountNumber || '-'}</Text>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 3 }}>
                  <Text style={styles.customerAddress}>Category: {customer.category ?? '-'}</Text>
                  {customer.zone && <Text style={styles.customerZone}>Zone: {customer.zone}</Text>}
                </View>
                {customer.address && customer.address !== '-' && (
                  <Text style={styles.customerAddressText} numberOfLines={2}>
                    📍 {customer.address}
                  </Text>
                )}
                {customer.status && <Text style={styles.customerStatus}>Status: {customer.status}</Text>}
                <View style={styles.locationActions}>
                  <TouchableOpacity
                    style={[styles.locationBtn, styles.locationBtnView, rowBusy && styles.locationBtnDisabled]}
                    onPress={() => handleViewLocation(customer, index)}
                    disabled={rowBusy}
                    activeOpacity={0.7}
                  >
                    {viewLoading ? (
                      <ActivityIndicator color="#fff" size="small" />
                    ) : (
                      <Text style={styles.locationBtnText}>View location</Text>
                    )}
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.locationBtn, styles.locationBtnGet, rowBusy && styles.locationBtnDisabled]}
                    onPress={() => handleGetLocation(customer, index)}
                    disabled={rowBusy}
                    activeOpacity={0.7}
                  >
                    {getLoading ? (
                      <ActivityIndicator color="#fff" size="small" />
                    ) : (
                      <Text style={styles.locationBtnText}>Get location</Text>
                    )}
                  </TouchableOpacity>
                </View>
              </View>
            );
          })}
        </View>
      </View>

      {/* Map Modal */}
      <Modal
        visible={showMapModal}
        animationType="slide"
        transparent={false}
        onRequestClose={() => {
          setShowMapModal(false);
          setCustomerLocation(null);
        }}
      >
        <View style={styles.mapContainer}>
          <View style={styles.mapHeader}>
            <TouchableOpacity 
              style={styles.mapBackButton} 
              onPress={() => {
                setShowMapModal(false);
                setCustomerLocation(null);
              }}
            >
              <Text style={styles.mapBackButtonText}>← Back</Text>
            </TouchableOpacity>
            <View style={styles.mapHeaderContent}>
              <Text style={styles.mapHeaderTitle}>
                {customerLocation?.name || 'Customer Location'}
              </Text>
              {customerLocation?.address && (
                <Text style={styles.mapHeaderSubtitle} numberOfLines={2}>
                  {customerLocation.address}
                </Text>
              )}
            </View>
          </View>

          {customerLocation ? (
            <>
              <WebView
                style={styles.map}
                source={{
                  html: `
                    <!DOCTYPE html>
                    <html>
                      <head>
                        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
                        <style>
                          * { margin: 0; padding: 0; box-sizing: border-box; }
                          html, body { width: 100%; height: 100%; overflow: hidden; }
                          #map { width: 100%; height: 100vh; }
                        </style>
                      </head>
                      <body>
                        <iframe 
                          id="map"
                          frameborder="0" 
                          style="border:0" 
                          src="https://www.google.com/maps?q=${customerLocation.latitude},${customerLocation.longitude}&z=16&output=embed"
                          allowfullscreen>
                        </iframe>
                      </body>
                    </html>
                  `
                }}
                javaScriptEnabled={true}
                domStorageEnabled={true}
                startInLoadingState={true}
                renderLoading={() => (
                  <View style={styles.mapLoadingContainer}>
                    <ActivityIndicator size="large" color="#2196F3" />
                    <Text style={styles.mapLoadingText}>Loading Google Maps...</Text>
                    <Text style={styles.mapLoadingSubtext}>Using coordinates from consumer_zone table</Text>
                  </View>
                )}
              />
              <View style={styles.mapInfo}>
                <Text style={styles.mapInfoText}>
                  📍 Location from consumer_zone table
                </Text>
                <Text style={styles.mapCoordinatesText}>
                  Lat: {customerLocation.latitude.toFixed(6)}, Lng: {customerLocation.longitude.toFixed(6)}
                </Text>
              </View>
              <View style={styles.mapActions}>
                <TouchableOpacity 
                  style={styles.directionsButton}
                  onPress={handleOpenDirections}
                >
                  <Text style={styles.directionsButtonText}>🗺️ Get Directions in Google Maps</Text>
                </TouchableOpacity>
              </View>
            </>
          ) : (
            <View style={styles.mapLoadingContainer}>
              <Text style={styles.mapLoadingText}>No location data available</Text>
            </View>
          )}
        </View>
      </Modal>
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
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 10,
  },
  buttonGroup: {
    flexDirection: 'row',
    gap: 8,
  },
  refreshBtn: {
    backgroundColor: '#2196F3',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
  },
  refreshText: {
    color: 'white',
    fontWeight: 'bold',
  },
  deleteBtn: {
    backgroundColor: '#dc3545',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
  },
  deleteText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 12,
  },
  customerList: {
    gap: 10,
  },
  customerCard: {
    backgroundColor: 'white',
    borderRadius: 10,
    padding: 15,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 1,
    },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  customerHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 5,
  },
  routeNumber: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2196F3',
    marginRight: 10,
    backgroundColor: '#e3f2fd',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  customerName: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#333',
  },
  customerAccount: {
    fontSize: 14,
    color: '#666',
    marginBottom: 3,
  },
  customerAddress: {
    fontSize: 12,
    color: '#999',
  },
  customerZone: {
    fontSize: 13,
    color: '#2196F3',
    fontWeight: '600',
    marginTop: 2,
  },
  customerStatus: {
    fontSize: 12,
    color: '#28a745',
    fontWeight: '600',
    marginTop: 5,
    fontStyle: 'italic',
  },
  customerAddressText: {
    fontSize: 12,
    color: '#666',
    marginTop: 5,
    fontStyle: 'italic',
  },
  locationActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
  },
  locationBtn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 44,
  },
  locationBtnView: {
    backgroundColor: '#2196F3',
  },
  locationBtnGet: {
    backgroundColor: '#1565C0',
  },
  locationBtnDisabled: {
    opacity: 0.65,
  },
  locationBtnText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  mapContainer: {
    flex: 1,
    backgroundColor: '#fff',
  },
  mapHeader: {
    backgroundColor: 'white',
    paddingTop: 50,
    paddingHorizontal: 20,
    paddingBottom: 15,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  mapBackButton: {
    marginBottom: 10,
  },
  mapBackButtonText: {
    fontSize: 16,
    color: '#2196F3',
    fontWeight: '600',
  },
  mapHeaderContent: {
    flex: 1,
  },
  mapHeaderTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 4,
  },
  mapHeaderSubtitle: {
    fontSize: 14,
    color: '#666',
  },
  map: {
    flex: 1,
  },
  mapLoadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f5f5f5',
  },
  mapLoadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  mapActions: {
    padding: 20,
    backgroundColor: 'white',
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
  },
  directionsButton: {
    backgroundColor: '#2196F3',
    paddingVertical: 15,
    paddingHorizontal: 20,
    borderRadius: 8,
    alignItems: 'center',
  },
  directionsButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
  },
  mapInfo: {
    padding: 15,
    backgroundColor: '#f5f5f5',
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
  },
  mapInfoText: {
    fontSize: 14,
    color: '#2196F3',
    fontWeight: '600',
    marginBottom: 5,
  },
  mapCoordinatesText: {
    fontSize: 12,
    color: '#666',
    fontFamily: 'monospace',
  },
  mapLoadingSubtext: {
    marginTop: 5,
    fontSize: 12,
    color: '#999',
  },
});

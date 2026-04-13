import React, { useState, useRef, useCallback } from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  ScrollView,
  Alert,
  TextInput,
  ActivityIndicator,
  FlatList,
  Pressable,
} from 'react-native';
import * as Location from 'expo-location';
import { consumerAPI } from './services/api';
import { tokenStorage, routesStorage } from './services/storage';

// Normalize a route item to { account_no, account_name, display, id } for display and selection
const routeToSuggestion = (r, idx) => {
  const accountNo = r.account_number ?? r.accountNumber ?? '';
  const accountName = r.account_name ?? r.name ?? '';
  return {
    id: r.id ?? idx + 1,
    account_no: accountNo,
    account_name: accountName,
    display: accountNo && accountName ? `${accountNo} - ${accountName}` : (accountNo || accountName || '—'),
  };
};

// Filter routes by search query (account number, name, meter number)
const filterRoutesByQuery = (routes, query) => {
  if (!routes || routes.length === 0 || !query || query.length < 2) return [];
  const q = query.trim().toUpperCase();
  const normalizedQ = q.replace(/-/g, '');
  return routes.filter((r) => {
    const accountNo = (r.account_number ?? r.accountNumber ?? '').toString().toUpperCase();
    const accountName = (r.account_name ?? r.name ?? '').toString().toUpperCase();
    const meterNo = (r.meter_number ?? r.meterNumber ?? '').toString().toUpperCase();
    const accountNoNorm = accountNo.replace(/-/g, '');
    return (
      accountNo.includes(q) ||
      accountNoNorm.includes(normalizedQ) ||
      accountName.includes(q) ||
      meterNo.includes(q)
    );
  });
};

export default function Coordinates({ onBack }) {
  const [accountSearch, setAccountSearch] = useState('');
  const [accountNo, setAccountNo] = useState('');
  const [accountName, setAccountName] = useState('');
  const [suggestions, setSuggestions] = useState([]);
  const [suggestionsVisible, setSuggestionsVisible] = useState(false);
  const [suggestionsLoading, setSuggestionsLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [lastCoords, setLastCoords] = useState(null);
  const [manualLat, setManualLat] = useState('');
  const [manualLong, setManualLong] = useState('');
  const searchTimeout = useRef(null);

  const searchConsumers = useCallback(async (q) => {
    const trimmed = (q || '').trim();
    if (trimmed.length < 2) {
      setSuggestions([]);
      setSuggestionsVisible(false);
      return;
    }
    setSuggestionsLoading(true);
    try {
      const routes = await routesStorage.getRoutes();
      const list = Array.isArray(routes) ? routes : [];
      const filtered = filterRoutesByQuery(list, trimmed);
      const mapped = filtered.slice(0, 50).map((r, idx) => routeToSuggestion(r, idx));
      setSuggestions(mapped);
      setSuggestionsVisible(mapped.length > 0);
    } catch (err) {
      setSuggestions([]);
      setSuggestionsVisible(false);
    } finally {
      setSuggestionsLoading(false);
    }
  }, []);

  const onSearchChange = (text) => {
    setAccountSearch(text);
    if (searchTimeout.current) clearTimeout(searchTimeout.current);
    if ((text || '').trim().length < 2) {
      setSuggestions([]);
      setSuggestionsVisible(false);
      return;
    }
    searchTimeout.current = setTimeout(() => searchConsumers(text), 300);
  };

  const onSelectConsumer = (item) => {
    setAccountNo(item.account_no || '');
    setAccountName(item.account_name || '');
    setAccountSearch(item.display || `${item.account_no || ''} - ${item.account_name || ''}`);
    setSuggestions([]);
    setSuggestionsVisible(false);
  };

  const saveCoordinatesToApi = async (latitude, longitude) => {
    const acc = (accountNo || '').trim();
    if (!acc) {
      Alert.alert('Select consumer', 'Search and select a consumer first.');
      return false;
    }
    const token = await tokenStorage.getToken();
    const res = await consumerAPI.saveConsumerCoordinates(acc, latitude, longitude, token);
    if (res?.success) {
      setLastCoords({ latitude, longitude });
      Alert.alert('Saved', res.message || `Coordinates saved for ${accountName || acc}.`);
      return true;
    }
    Alert.alert('Error', res?.message || 'Failed to save coordinates.');
    return false;
  };

  // Get current location with expo-location and save (no WebView to avoid crashes)
  const handleGetLocationViaGoogleMaps = async () => {
    const acc = (accountNo || '').trim();
    if (!acc) {
      Alert.alert('Select consumer', 'Search and select a consumer first.');
      return;
    }
    setSaving(true);
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert(
          'Permission needed',
          'Location permission is required. You can enter coordinates manually below.'
        );
        return;
      }
      // Timeout so we don't hang if GPS is slow/unavailable
      const timeoutMs = 20000;
      const locationPromise = Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
        maximumAge: 60000,
      });
      const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('Location request timed out. Please try again or enter coordinates manually.')), timeoutMs)
      );
      const location = await Promise.race([locationPromise, timeoutPromise]);
      const latitude = location?.coords?.latitude;
      const longitude = location?.coords?.longitude;
      if (latitude == null || longitude == null || typeof latitude !== 'number' || typeof longitude !== 'number') {
        Alert.alert('Location error', 'Could not get valid coordinates. Try again or enter manually.');
        return;
      }
      const saved = await saveCoordinatesToApi(latitude, longitude);
      if (saved) {
        setManualLat(String(latitude));
        setManualLong(String(longitude));
      }
    } catch (err) {
      const message = err?.message || 'Could not get location. Try entering coordinates manually.';
      Alert.alert('Location error', message);
    } finally {
      setSaving(false);
    }
  };

  const handleSaveManual = async () => {
    const lat = parseFloat(manualLat);
    const long = parseFloat(manualLong);
    if (Number.isNaN(lat) || Number.isNaN(long) || lat < -90 || lat > 90 || long < -180 || long > 180) {
      Alert.alert('Invalid coordinates', 'Enter valid latitude (-90 to 90) and longitude (-180 to 180).');
      return;
    }
    setSaving(true);
    setLastCoords(null);
    try {
      await saveCoordinatesToApi(lat, long);
    } catch (err) {
      Alert.alert('Error', err.message || 'Failed to save coordinates.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={onBack}>
          <Text style={styles.backButton}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.formTitle}>Save consumer coordinates</Text>
        <View style={{ width: 50 }} />
      </View>

      <View style={styles.formContainer}>
        <Text style={styles.formLabel}>Consumer (search by account or name)</Text>
        <TextInput
          style={styles.textInput}
          placeholder="Type account number or name..."
          value={accountSearch}
          onChangeText={onSearchChange}
          onFocus={() => suggestions.length > 0 && setSuggestionsVisible(true)}
          placeholderTextColor="#999"
        />
        {suggestionsLoading && (
          <View style={styles.loadingRow}>
            <ActivityIndicator size="small" color="#2196F3" />
            <Text style={styles.loadingText}>Searching...</Text>
          </View>
        )}
        {suggestionsVisible && suggestions.length > 0 && (
          <View style={styles.suggestionsList}>
            <FlatList
              data={suggestions}
              keyExtractor={(item) => String(item.id || item.account_no)}
              renderItem={({ item }) => (
                <Pressable
                  style={({ pressed }) => [styles.suggestionItem, pressed && styles.suggestionItemPressed]}
                  onPress={() => onSelectConsumer(item)}
                >
                  <Text style={styles.suggestionText} numberOfLines={1}>
                    {item.display || `${item.account_no} - ${item.account_name}`}
                  </Text>
                </Pressable>
              )}
              scrollEnabled={false}
            />
          </View>
        )}

        {accountNo ? (
          <View style={styles.selectedBox}>
            <Text style={styles.selectedLabel}>Selected:</Text>
            <Text style={styles.selectedText}>{accountNo} – {accountName || '—'}</Text>
          </View>
        ) : null}

        {lastCoords && (
          <View style={styles.lastCoordsBox}>
            <Text style={styles.formLabel}>Last saved</Text>
            <Text style={styles.coordsText}>
              Lat: {lastCoords.latitude.toFixed(6)}, Long: {lastCoords.longitude.toFixed(6)}
            </Text>
          </View>
        )}

        <TouchableOpacity
          style={[styles.submitButton, saving && styles.submitButtonDisabled]}
          onPress={handleGetLocationViaGoogleMaps}
          disabled={saving || !accountNo}
        >
          {saving ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.submitButtonText}>Get current location & save</Text>
          )}
        </TouchableOpacity>

        <Text style={[styles.formLabel, { marginTop: 24 }]}>Or enter manually</Text>
        <TextInput
          style={styles.textInput}
          placeholder="Latitude (e.g. 14.5995)"
          value={manualLat}
          onChangeText={setManualLat}
          keyboardType="decimal-pad"
          placeholderTextColor="#999"
        />
        <TextInput
          style={[styles.textInput, { marginTop: 10 }]}
          placeholder="Longitude (e.g. 120.9842)"
          value={manualLong}
          onChangeText={setManualLong}
          keyboardType="decimal-pad"
          placeholderTextColor="#999"
        />
        <TouchableOpacity
          style={[styles.submitButtonSecondary, saving && styles.submitButtonDisabled]}
          onPress={handleSaveManual}
          disabled={saving || !accountNo}
        >
          <Text style={styles.submitButtonSecondaryText}>Save entered coordinates</Text>
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
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  backButton: {
    fontSize: 16,
    color: '#2196F3',
    fontWeight: '600',
  },
  formTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
  },
  formContainer: {
    paddingHorizontal: 20,
    paddingVertical: 30,
  },
  formLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 10,
    marginTop: 15,
  },
  textInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    paddingHorizontal: 15,
    paddingVertical: 12,
    fontSize: 16,
    color: '#333',
    backgroundColor: 'white',
  },
  loadingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 8,
    gap: 8,
  },
  loadingText: {
    fontSize: 14,
    color: '#666',
  },
  suggestionsList: {
    marginTop: 4,
    backgroundColor: 'white',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#ddd',
    maxHeight: 200,
  },
  suggestionItem: {
    paddingVertical: 12,
    paddingHorizontal: 15,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  suggestionItemPressed: {
    backgroundColor: '#E8F0FE',
  },
  suggestionText: {
    fontSize: 16,
    color: '#333',
  },
  selectedBox: {
    marginTop: 16,
    padding: 12,
    backgroundColor: '#E3F2FD',
    borderRadius: 10,
    borderLeftWidth: 4,
    borderLeftColor: '#2196F3',
  },
  selectedLabel: {
    fontSize: 12,
    color: '#666',
    marginBottom: 4,
  },
  selectedText: {
    fontSize: 16,
    color: '#333',
    fontWeight: '500',
  },
  lastCoordsBox: {
    marginTop: 16,
    padding: 12,
    backgroundColor: '#f5f5f5',
    borderRadius: 10,
  },
  coordsText: {
    fontSize: 14,
    color: '#666',
    fontFamily: 'monospace',
  },
  submitButton: {
    backgroundColor: '#2196F3',
    borderRadius: 10,
    paddingVertical: 16,
    alignItems: 'center',
    marginTop: 30,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
    elevation: 5,
  },
  submitButtonDisabled: {
    opacity: 0.7,
  },
  submitButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  submitButtonSecondary: {
    backgroundColor: '#fff',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 16,
    borderWidth: 2,
    borderColor: '#2196F3',
  },
  submitButtonSecondaryText: {
    color: '#2196F3',
    fontSize: 16,
    fontWeight: '600',
  },
});

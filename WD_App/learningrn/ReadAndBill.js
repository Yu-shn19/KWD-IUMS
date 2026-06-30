import React, { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import {
  View, 
  Text, 
  TextInput, 
  TouchableOpacity, 
  Pressable,
  StyleSheet, 
  ScrollView, 
  FlatList,
  Alert, 
  ActivityIndicator,
  Modal,
  InteractionManager,
  Linking,
  AppState,
  Platform
} from 'react-native';
import { apiRequest, routesAPI } from './services/api';
import { getApiConfig } from './config/api';
import { tokenStorage, routesStorage, userStorage, receiptStorage, printerStorage, receiptLogoStorage, receiptFormatStorage } from './services/storage';
import { Asset } from 'expo-asset';
import * as FileSystem from 'expo-file-system';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { isSupported as btSupported, printReceiptEscPos } from './services/bluetoothPrinter';
import { networkStatus, syncManager } from './services/offlineQueue';
import * as readingsLocalService from './services/readingsLocalService';
import PrinterSelector from './components/PrinterSelector';
import { getReadingDateFromMeterSchedule } from './utils/dateUtils';

const KEYPAD_KEYS = ['1','2','3','4','5','6','7','8','9','.','0','⌫'];
const SQLITE_EXPORT_DIR_URI_KEY = 'sqlite_export_directory_uri';
const SQLITE_EXPORT_FILE_URIS_KEY = 'sqlite_export_file_uris_map';

/** Last segment of account no. (e.g. 081-12-020 → 020). Preserves leading zeros. */
const getSequenceFromAccountNumber = (accountNo) => {
  const acct = (accountNo ?? '').toString().trim();
  if (!acct.includes('-')) return null;
  const tail = acct.split('-').pop()?.trim();
  return tail || null;
};

/** Receipt "Sequence" = consumer card/series (account tail), not monthly sedr_number. */
const resolveConsumerSequence = (record) => {
  if (!record) return null;
  const acct = record.accountNumber ?? record.account_number ?? record.account_no ?? '';
  const fromAcct = getSequenceFromAccountNumber(acct);
  if (fromAcct) return fromAcct;
  const seq = record.sequence ?? record.series ?? record.series_number ?? null;
  if (seq != null && seq !== '') return String(seq);
  return null;
};

const getConsumerSeriesForReceipt = (customer) => {
  const seq = resolveConsumerSequence(customer);
  if (seq) return seq;
  const sedr = customer?.sedrNumber ?? customer?.sedr_number ?? null;
  if (sedr != null && sedr !== '') return String(sedr);
  return '—';
};

const isCompletedCustomerStatus = (status) => {
  const s = (status ?? '').toString().trim().toLowerCase();
  return s === 'completed' || s === 'verified';
};

const isSavedOfflineCustomerStatus = (status) =>
  (status ?? '').toString().trim().toLowerCase() === 'saved offline';

/** Normalize API/cache status so "Completed" and "completed" both show as completed. */
const normalizeCustomerStatus = (status, currentReading = null) => {
  if (isSavedOfflineCustomerStatus(status)) return 'saved offline';
  if (isCompletedCustomerStatus(status)) return 'completed';
  const hasReading = currentReading != null && currentReading !== '';
  if (hasReading && isCompletedCustomerStatus(status)) return 'completed';
  const s = (status ?? '').toString().trim().toLowerCase();
  if (hasReading && (s === 'completed' || s === 'verified')) return 'completed';
  return status || 'Assigned';
};
const ReadingEntryModal = ({ visible, onClose, initialReading, selectedCustomer, onSave, presentDate, previousDate, styles: modalStyles }) => {
  const [displayReading, setDisplayReading] = React.useState('0');
  const valueRef = React.useRef('');
  React.useEffect(() => {
    if (visible) {
      const v = initialReading ?? '';
      const show = v === '' ? '0' : v;
      valueRef.current = v;
      setDisplayReading(show);
    }
  }, [visible, initialReading]);
  const appendDigit = React.useCallback((d) => {
    const current = valueRef.current === '' || valueRef.current === undefined ? '0' : valueRef.current;
    let next;
    if (d === '.' && current.includes('.')) next = current;
    else next = (current === '0' && d !== '.') ? d : current + d;
    valueRef.current = next;
    setDisplayReading(next === '' ? '0' : next);
  }, []);
  const backspace = React.useCallback(() => {
    const current = valueRef.current === '' || valueRef.current === undefined ? '0' : valueRef.current;
    const next = current.length > 0 ? current.slice(0, -1) : '';
    valueRef.current = next;
    setDisplayReading(next === '' ? '0' : next);
  }, []);
  const clearAll = React.useCallback(() => {
    valueRef.current = '';
    setDisplayReading('0');
  }, []);
  const handleKeyPress = React.useCallback((key) => {
    if (key === '⌫') backspace();
    else appendDigit(key);
  }, [backspace, appendDigit]);
  const calculatedVolume = React.useMemo(() => {
    const readingValue = displayReading || '0';
    if (!selectedCustomer || !readingValue || readingValue === '0') return '0.0';
    const reading = parseFloat(readingValue);
    const last = selectedCustomer.lastReading || 0;
    return isNaN(reading) ? '0.0' : Math.max(reading - last, 0).toFixed(1);
  }, [selectedCustomer, displayReading]);
  const saveReading = React.useCallback(() => {
    const readingValue = valueRef.current || displayReading;
    if (!readingValue) {
      Alert.alert('Invalid', 'Please enter a reading.');
      return;
    }
    const readingNum = parseFloat(readingValue);
    if (Number.isNaN(readingNum)) {
      Alert.alert('Invalid', 'Reading must be a number.');
      return;
    }
    if (selectedCustomer && readingNum < selectedCustomer.lastReading) {
      Alert.alert('Invalid', 'Reading must be ≥ previous reading.');
      return;
    }
    onSave(readingValue);
  }, [displayReading, selectedCustomer, onSave]);
  if (!visible) return null;
  return (
    <Modal visible={visible} animationType="slide" presentationStyle="fullScreen">
      <View style={modalStyles.readingEntryRoot}>
        <View style={modalStyles.readingEntryHeader}>
          <TouchableOpacity onPress={onClose} style={{ paddingHorizontal: 12, paddingVertical: 6 }}>
            <Text style={{ color: '#ecf0f1', fontSize: 18 }}>✕</Text>
          </TouchableOpacity>
          <Text style={modalStyles.readingEntryTitle}>Reading Entry</Text>
          <View style={{ width: 32 }} />
        </View>
        <View style={modalStyles.readingEntryBody}>
          <View style={modalStyles.leftPane}>
            <Text style={modalStyles.leftPaneMuted}>Account No.</Text>
            <Text style={modalStyles.leftPaneStrong}>{selectedCustomer?.accountNumber || '-'}</Text>
            <View style={{ height: 8 }} />
            <Text style={modalStyles.leftPaneMuted}>Account Name</Text>
            <Text style={[modalStyles.leftPaneStrong, { fontSize: 16 }]}> {selectedCustomer?.name || '-'} </Text>
            <View style={{ height: 8 }} />
            <Text style={modalStyles.leftPaneMuted}>Address</Text>
            <Text style={modalStyles.leftPaneStrongSmall}>{selectedCustomer?.address || '-'}</Text>
            <View style={{ height: 8 }} />
            <Text style={modalStyles.leftPaneMuted}>Meter Number</Text>
            <Text style={modalStyles.leftPaneStrongSmall}>{selectedCustomer?.meterNumber || '-'}</Text>
            <View style={{ height: 8 }} />
            <Text style={modalStyles.leftPaneMuted}>Reading Info</Text>
            <Text style={modalStyles.leftPaneStrongSmall}>Date</Text>
            <View style={{ height: 8 }} />
            <Text style={[modalStyles.leftPaneMuted, { color: '#1e88e5' }]}>PRESENT</Text>
            <Text style={modalStyles.leftPaneStrongSmall}>{presentDate}</Text>
            <View style={{ height: 8 }} />
            <Text style={[modalStyles.leftPaneMuted, { color: '#1e88e5' }]}>PREVIOUS</Text>
            <Text style={modalStyles.leftPaneStrongSmall}>{previousDate}</Text>
            <View style={{ height: 16 }} />
            <Text style={[modalStyles.leftPaneMuted, { color: '#1e88e5' }]}>Remarks</Text>
            <Text style={modalStyles.leftPaneStrongSmall}>—</Text>
            <View style={{ height: 8 }} />
            <Text style={[modalStyles.leftPaneMuted, { color: '#1e88e5' }]}>Field Findings</Text>
            <View style={{ height: 8 }} />
            <Text style={modalStyles.leftPaneStrongSmall}>Previous Reading: {selectedCustomer?.lastReading != null && selectedCustomer?.lastReading !== '' ? selectedCustomer.lastReading : '—'}</Text>
          </View>
          <View style={modalStyles.rightPane}>
            <Text style={modalStyles.volumeLabel}>Volume</Text>
            <Text style={modalStyles.volumeValue}>{calculatedVolume}</Text>
            <View style={modalStyles.readingDisplay}>
              <Text style={modalStyles.readingDisplayText}>{displayReading || '0'}</Text>
            </View>
            <View style={modalStyles.datesRow}>
              <View style={{ alignItems: 'center' }}>
                <Text style={modalStyles.dateLabel}>PRESENT</Text>
                <Text style={modalStyles.dateValue}>{presentDate}</Text>
              </View>
              <View style={{ alignItems: 'center' }}>
                <Text style={modalStyles.dateLabel}>PREVIOUS</Text>
                <Text style={modalStyles.dateValue}>{previousDate}</Text>
              </View>
            </View>
            <View style={modalStyles.keypad}>
              {KEYPAD_KEYS.map((k) => (
                <Pressable
                  key={k}
                  style={({ pressed }) => [modalStyles.key, pressed && modalStyles.keyPressed]}
                  onPressIn={() => handleKeyPress(k)}
                >
                  <Text style={modalStyles.keyText}>{k}</Text>
                </Pressable>
              ))}
            </View>
            <View style={modalStyles.saveRow}>
              <TouchableOpacity style={modalStyles.saveBtn} onPress={saveReading}>
                <Text style={modalStyles.saveBtnText}>Save</Text>
              </TouchableOpacity>
              <TouchableOpacity style={modalStyles.clearBtn} onPress={clearAll}>
                <Text style={modalStyles.saveBtnText}>Clear</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </View>
    </Modal>
  );
};

const ReadAndBill = ({ onBack, onViewRoutes }) => {
  const [selectedCustomer, setSelectedCustomer] = useState(null);
  const [meterReading, setMeterReading] = useState('');
  const [customerType, setCustomerType] = useState('residential'); 
  const [isProcessing, setIsProcessing] = useState(false);
  const [isLoadingRoutes, setIsLoadingRoutes] = useState(false);
  const [showReadingModal, setShowReadingModal] = useState(false);
  const [initialReadingForModal, setInitialReadingForModal] = useState('');
  const [customers, setCustomers] = useState([]);
  const [isCleared, setIsCleared] = useState(false);
  const [shouldSortCustomers, setShouldSortCustomers] = useState(false); 
  const [isOnline, setIsOnline] = useState(true);
  const [pendingCount, setPendingCount] = useState(0);
  const [isSyncing, setIsSyncing] = useState(false);
  const [showPrinterSelector, setShowPrinterSelector] = useState(false);
  const [pendingReceiptData, setPendingReceiptData] = useState(null);
  const [showCustomerModal, setShowCustomerModal] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');

  const scrollViewRef = useRef(null);
  const isOnlineRef = useRef(true); 

  
  useEffect(() => {
    const debounceTimer = setTimeout(() => {
      setDebouncedSearchTerm(searchTerm);
    }, 250);

    return () => clearTimeout(debounceTimer);
  }, [searchTerm]);

  useEffect(() => {
    let unsubscribe = null;
    let stopAutoSync = null;
    let statusCheckInterval = null;

    const initializeSync = async () => {
      
      const online = await networkStatus.isOnline(true); 
      setIsOnline(online);
      isOnlineRef.current = online; 
      console.log('🌐 Initial network status:', online ? 'Online' : 'Offline');

      
      const count = await readingsLocalService.getPendingCount();
      setPendingCount(count);
 
      
      unsubscribe = networkStatus.subscribe(async (online) => {
        console.log('📡 Network status automatically updated:', online ? '🌐 Online' : '📴 Offline');
        setIsOnline(online);
        isOnlineRef.current = online;
        
        if (online) {
          const count = await readingsLocalService.getPendingCount();
          setPendingCount(count);
          
          if (count > 0) {
            console.log(`🔄 Network restored with ${count} pending reading(s) - will sync shortly...`);
            setTimeout(async () => {
              const stillOnline = await networkStatus.isOnline(true);
              if (stillOnline) {
                const syncResult = await syncManager.syncAll(uploadReadingToServer);
                const newCount = await readingsLocalService.getPendingCount();
                setPendingCount(newCount);
                console.log('🔄 Auto-sync completed:', syncResult);
              }
            }, 2000);
          }
        }
      });

      statusCheckInterval = setInterval(async () => {
        const online = await networkStatus.isOnline(true);
        const prevOnline = isOnlineRef.current;
        
        if (online !== prevOnline) {
          console.log(`📡 Periodic check - Network status changed: ${prevOnline} → ${online}`);
          isOnlineRef.current = online;
          setIsOnline(online);
          
          if (online && !prevOnline) {
            const count = await readingsLocalService.getPendingCount();
            if (count > 0) {
              console.log('🔄 Network restored - syncing all offline readings...');
              const syncResult = await syncManager.syncAll(uploadReadingToServer);
              const newCount = await readingsLocalService.getPendingCount();
              setPendingCount(newCount);
              console.log('🔄 Sync after network restore:', syncResult);
            }
          }
        } else {
          
          const count = await readingsLocalService.getPendingCount();
          setPendingCount(count);
        }
      }, 10000); 

     
      stopAutoSync = syncManager.setupAutoSync(uploadReadingToServer, 30000);
    };

    initializeSync();

    return () => {
      if (unsubscribe) unsubscribe();
      if (stopAutoSync) stopAutoSync();
      if (statusCheckInterval) clearInterval(statusCheckInterval);
    };
  }, []);

  const refreshNetworkStatus = async () => {
    const online = await networkStatus.isOnline(false); 
    setIsOnline(online);
    
  };

  useEffect(() => {
    const sub = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active') {
        readingsLocalService.getPendingCount().then(setPendingCount);
        // Rehydrate assigned routes from local SQLite cache when returning to app.
        // This keeps routes visible even after accidental exits / offline resumes.
        (async () => {
          try {
            const userData = await userStorage.getUserData();
            const readerId = userData?.id ?? null;
            const cachedCustomers = await loadCustomersFromCache(readerId);
            if (cachedCustomers.length > 0) {
              setCustomers(cachedCustomers);
            }
          } catch (_) {}
        })();
      }
    });
    return () => sub?.remove();
  }, []);

  
  const formatDateMDY = (date) => {
    try {
      return date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });
    } catch {
      
      const mm = String(date.getMonth() + 1).padStart(2, '0');
      const dd = String(date.getDate()).padStart(2, '0');
      const yyyy = date.getFullYear();
      return `${mm}/${dd}/${yyyy}`;
    }
  };

  const withTimeout = async (promise, ms, label = 'operation') => {
    let timeoutId;
    const timeoutPromise = new Promise((_, reject) => {
      timeoutId = setTimeout(() => reject(new Error(`${label} timed out`)), ms);
    });
    try {
      const result = await Promise.race([promise, timeoutPromise]);
      return result;
    } finally {
      clearTimeout(timeoutId);
    }
  };

  const getScheduleIdFromRecord = (record) => {
    if (!record) return null;
    return (
      record.schedule_id ??
      record.scheduleId ??
      record.id ??
      null
    );
  };

  const loadCustomersFromCache = async (readerId) => {
    try {
      const cachedRoutes = await routesStorage.getRoutes();
      console.log('📦 Loading from cache - Total routes:', cachedRoutes?.length || 0);
      
      if (!cachedRoutes || cachedRoutes.length === 0) {
        console.log('⚠️ No cached routes found');
        return [];
      }

      let filteredRoutes = cachedRoutes;
      if (readerId) {
    
        const hasReaderIdField = cachedRoutes.some((r) => r.reader_id || r.readerId);
        
        if (hasReaderIdField) {
          // Filter: include routes that match reader_id OR routes without reader_id (legacy data)
          filteredRoutes = cachedRoutes.filter((r) => {
            const routeReaderId = r.reader_id || r.readerId;
            // Include if matches readerId OR if route has no reader_id (legacy/backward compatibility)
            return routeReaderId === readerId || !routeReaderId;
          });
        }
        // If no routes have reader_id, use all cached routes (backward compatibility)
      }

      console.log('📦 Filtered routes for reader:', filteredRoutes.length);

      // Map cached routes to customer format
      let mapped = filteredRoutes.map((c, idx) => ({
        id: getScheduleIdFromRecord(c) ?? `route-${c.account_number ?? c.accountNumber ?? idx}`,
        sedrNumber: c.sedr_number ?? c.sedrNumber ?? null,
        sequence: resolveConsumerSequence({
          account_number: c.account_number ?? c.accountNumber,
          sequence: c.sequence,
          series: c.series,
        }),
        name: c.account_name || c.name || '-',
        accountNumber: c.account_number ?? c.accountNumber ?? '-',
        category: c.category ?? '-',
        rateCode: c.rate_code ?? c.rateCode ?? null,
        zone: c.zone ?? '-',
        address: c.address ?? '-',
        meterNumber: c.meter_number ?? c.meterNumber ?? '-',
        status: normalizeCustomerStatus(c.status, c.current_reading ?? c.currentReading),
        lastReading: c.previous_reading ?? c.lastReading ?? 0,
        currentReading: c.current_reading ?? c.currentReading ?? null,
        estimatedReading: c.estimatedReading ?? 0,
        consumption: c.consumption ?? 0,
        billMonth: c.bill_month ?? c.billMonth ?? null,
        billDate: c.bill_date ?? c.billDate ?? c.reading_date ?? c.readingDate ?? c.scheduleReadingDate ?? null,
        dueDate: c.due_date ?? c.dueDate ?? null,
        // meter_reading_schedules: period covered on receipt = previous_reading_date / reading_date
        previousReadingDate: c.previous_reading_date ?? c.previousReadingDate ?? null,
        scheduleReadingDate: c.reading_date ?? c.readingDate ?? c.scheduleReadingDate ?? c.bill_date ?? c.billDate ?? null,
        arrears: parseFloat(c.arrears ?? 0),
        readerId: readerId || c.reader_id || c.readerId,
      }));

      try {
        const unsynced = await readingsLocalService.getUnsyncedReadingsForUIMerge();
        if (unsynced.length > 0) {
          const patchBySchedule = new Map();
          unsynced.forEach((row) => {
            const sid = row.data?.schedule_id;
            if (sid == null && sid !== 0) return;
            const cur = row.data?.current_reading;
            if (cur == null && cur !== 0) return;
            const cons =
              row.data?.consumption != null
                ? row.data.consumption
                : 0;
            const patch = {
              status: 'saved offline',
              current_reading: cur,
              currentReading: cur,
              consumption: cons,
            };
            patchBySchedule.set(sid, patch);
            const n = Number(sid);
            if (!Number.isNaN(n)) patchBySchedule.set(n, patch);
          });
          mapped = mapped.map((cust) => {
            const sid = cust.id;
            const p = patchBySchedule.get(sid) ?? patchBySchedule.get(Number(sid));
            return p ? { ...cust, ...p } : cust;
          });
        }
      } catch (e) {
        console.warn('SQLite unsynced overlay for cache:', e?.message || e);
      }

      console.log('✅ Mapped customers from cache:', mapped.length);
      return mapped;
    } catch (error) {
      console.error('❌ Error loading routes from cache:', error);
      return [];
    }
  };

  // Load customers from admin-uploaded routes (from database API, fallback to cache)
  const loadCustomersFromRoutes = async (showAlerts = false) => {
    if (syncManager._syncLock) {
      console.log('⏳ Skipping route refresh while sync is in progress');
      return;
    }
    setIsLoadingRoutes(true);
    
    try {
      const token = await tokenStorage.getToken();
      const userData = await userStorage.getUserData();
      const readerId = userData?.id;
      
      if (!readerId) {
        // Try to load from cache even without readerId
        const cachedCustomers = await loadCustomersFromCache(null);
        setCustomers(cachedCustomers);
        if (showAlerts) Alert.alert('No Reader', 'Reader profile not found. Please re-login.');
        return;
      }

      // Check if online before attempting API call
      const online = await networkStatus.isOnline(true);
      
      if (online) {
        try {
          const response = await routesAPI.getRoutes({ reader_id: readerId }, token);
          const list = Array.isArray(response?.schedules)
            ? response.schedules
            : Array.isArray(response?.data)
              ? response.data
              : [];

          if (list.length > 0) {
            // Preserve local status and current reading from cache so they don't disappear when returning to screen
            let cachedRoutes = [];
            try {
              cachedRoutes = (await routesStorage.getRoutes()) || [];
            } catch (_) {}
            const localByScheduleId = {};
            cachedRoutes.forEach((r) => {
              const id = getScheduleIdFromRecord(r);
              const status = r.status;
              const currentReading = r.current_reading ?? r.currentReading;
              const normalizedStatus = normalizeCustomerStatus(status, currentReading);
              if (id && (normalizedStatus === 'completed' || normalizedStatus === 'saved offline') && (currentReading != null)) {
                localByScheduleId[id] = {
                  status: normalizedStatus,
                  current_reading: currentReading,
                  currentReading,
                  consumption: r.consumption != null ? r.consumption : (currentReading != null && r.previous_reading != null ? currentReading - r.previous_reading : r.lastReading != null ? currentReading - r.lastReading : 0),
                };
              }
            });

            // Any schedule with a local unsynced reading stays "saved offline" after API refresh (includes retry backoff + syncing)
            try {
              const unsyncedLocal = await readingsLocalService.getUnsyncedReadingsForUIMerge();
              unsyncedLocal.forEach((row) => {
                const sid = row.data?.schedule_id;
                if (sid == null && sid !== 0) return;
                const cur = row.data?.current_reading;
                if (cur == null && cur !== 0) return;
                const cons = row.data?.consumption != null ? row.data.consumption : 0;
                const patch = {
                  status: 'saved offline',
                  current_reading: cur,
                  currentReading: cur,
                  consumption: cons,
                };
                const n = Number(sid);
                if (!Number.isNaN(n)) {
                  localByScheduleId[n] = { ...(localByScheduleId[n] || {}), ...patch };
                }
                localByScheduleId[sid] = { ...(localByScheduleId[sid] || {}), ...patch };
              });
            } catch (mergeErr) {
              console.warn('SQLite offline merge for routes:', mergeErr?.message);
            }

            const routesWithReaderId = list.map(route => {
              const routeScheduleId = getScheduleIdFromRecord(route);
              const local =
                localByScheduleId[routeScheduleId] ??
                localByScheduleId[Number(routeScheduleId)];
              return {
                ...route,
                reader_id: readerId,
                readerId: readerId,
                ...(local ? { status: local.status, current_reading: local.current_reading, currentReading: local.current_reading, consumption: local.consumption } : {}),
              };
            });

            await routesStorage.saveRoutes(routesWithReaderId);

            const mapped = routesWithReaderId.map((c, idx) => ({
              id: getScheduleIdFromRecord(c) ?? `route-${c.account_number ?? c.accountNumber ?? idx}`,
              sedrNumber: c.sedr_number ?? c.sedrNumber ?? null,
              sequence: resolveConsumerSequence({
                account_number: c.account_number ?? c.accountNumber,
                sequence: c.sequence,
                series: c.series,
              }),
              name: c.account_name || c.name || '-',
              accountNumber: c.account_number ?? c.accountNumber ?? '-',
              category: c.category ?? '-',
              rateCode: c.rate_code ?? c.rateCode ?? null,
              zone: c.zone ?? '-',
              address: c.address ?? '-',
              meterNumber: c.meter_number ?? c.meterNumber ?? '-',
              status: normalizeCustomerStatus(c.status, c.current_reading ?? c.currentReading),
              lastReading: c.previous_reading ?? c.lastReading ?? 0,
              currentReading: c.current_reading ?? c.currentReading ?? null,
              estimatedReading: c.estimatedReading ?? 0,
              consumption: c.consumption ?? 0,
              billMonth: c.bill_month ?? c.billMonth ?? null,
              billDate: c.bill_date ?? c.billDate ?? c.reading_date ?? c.readingDate ?? c.scheduleReadingDate ?? null,
              dueDate: c.due_date ?? c.dueDate ?? null,
              previousReadingDate: c.previous_reading_date ?? c.previousReadingDate ?? null,
              scheduleReadingDate: c.reading_date ?? c.readingDate ?? c.scheduleReadingDate ?? c.bill_date ?? c.billDate ?? null,
              arrears: parseFloat(c.arrears ?? 0),
              readerId: readerId,
            }));

            setCustomers(mapped);
            setShouldSortCustomers(true); // Enable sorting when refreshing
            if (showAlerts) {
              Alert.alert('✅ Routes Loaded', `${mapped.length} route(s) downloaded from the server.`);
            }
            return;
          } else {
            // API returned empty list, try cache as fallback
            console.log('⚠️ API returned empty list, trying cache...');
            const cachedCustomers = await loadCustomersFromCache(readerId);
            if (cachedCustomers.length > 0) {
              setCustomers(cachedCustomers);
              setShouldSortCustomers(false);
              console.log('✅ Using cached data:', cachedCustomers.length, 'customers');
              if (showAlerts) {
                Alert.alert('Using Cached Data', `Loaded ${cachedCustomers.length} route(s) from cache.`);
              }
              return;
            }
            
            // Only clear if user explicitly requested refresh
            if (showAlerts) {
              if (customers.length === 0) {
                setCustomers([]);
                setShouldSortCustomers(false);
                Alert.alert('No Routes', 'No routes assigned yet. Contact admin to assign schedules.');
              } else {
                Alert.alert('Using Existing Routes', 'No new routes found from server. Keeping your locally saved assigned routes.');
              }
            } else {
              // Silent refresh - keep existing customers if any
              console.log('⚠️ No routes from API or cache, but keeping existing customers (silent refresh)');
            }
            return;
          }
        } catch (apiError) {
          console.error('Error loading routes from server:', apiError);
          // Fall through to cache loading
        }
      }

      // Offline or API failed: load from cache
      console.log('📴 Loading routes from cache (offline or API failed)');
      const cachedCustomers = await loadCustomersFromCache(readerId);
      
      if (cachedCustomers.length > 0) {
        setCustomers(cachedCustomers);
        setShouldSortCustomers(false);
        console.log('✅ Loaded', cachedCustomers.length, 'customers from cache (offline mode)');
        if (showAlerts) {
          Alert.alert('Offline Mode', `Loaded ${cachedCustomers.length} route(s) from cache. Connect to internet to refresh.`);
        }
      } else {
        // Don't clear existing customers if this is a silent refresh
        // Only clear if user explicitly requested refresh
        if (showAlerts) {
          if (customers.length === 0) {
            setCustomers([]);
            setShouldSortCustomers(false);
            Alert.alert('No Cached Data', 'No routes found in cache. Connect to internet and refresh to download routes.');
          } else {
            Alert.alert('Offline Mode', 'No new cached routes found. Keeping your currently displayed assigned routes.');
          }
        } else {
          // Silent refresh failed - keep existing customers if any
          console.log('⚠️ No cached data found, but keeping existing customers (silent refresh)');
        }
      }
    } catch (e) {
      console.error('Error loading routes:', e);
      // Last resort: try to load from cache
      try {
        const userData = await userStorage.getUserData();
        const readerId = userData?.id;
        const cachedCustomers = await loadCustomersFromCache(readerId);
        setCustomers(cachedCustomers);
        if (showAlerts && cachedCustomers.length === 0) {
          Alert.alert('Connection Error', e.message || 'Cannot download routes from server.');
        }
      } catch (cacheError) {
        console.error('Error loading from cache:', cacheError);
        if (customers.length === 0) {
          setCustomers([]);
          setShouldSortCustomers(false);
        }
        if (showAlerts) {
          Alert.alert(
            'Connection Error',
            customers.length > 0
              ? 'Unable to refresh routes right now. Keeping your currently displayed assigned routes.'
              : (e.message || 'Cannot download routes from server.')
          );
        }
      }
    } finally {
      setIsLoadingRoutes(false);
    }
  };

  // Manual sync function
  const handleManualSync = async () => {
    if (!isOnline) {
      Alert.alert('Offline', 'Cannot sync while offline. Connect to internet first.');
      return;
    }

    const count = await readingsLocalService.getPendingCount();
    if (count === 0) {
      Alert.alert('✅ All Completed', 'No offline readings to sync.');
      return;
    }

    Alert.alert(
      'Sync Readings',
      `Sync ${count} offline reading(s) to server?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Sync Now',
          onPress: async () => {
            setIsSyncing(true);
            try {
              const result = await syncManager.syncAll(uploadReadingToServer);
              await autoExportSqliteReadingsToFileManager({ silent: true });
              let newCount = await readingsLocalService.getPendingCount();
              setPendingCount(newCount);
              if (result.synced > 0) {
                setTimeout(async () => {
                  newCount = await readingsLocalService.getPendingCount();
                  setPendingCount(newCount);
                }, 300);
              }
              
              if (result.success) {
                if (result.queued) {
                  Alert.alert(
                    'Sync running',
                    'Another upload was already in progress. An extra pass will run so remaining saved-offline readings are sent in batches until done.',
                    [{ text: 'OK' }]
                  );
                } else {
                  Alert.alert(
                    '✅ Completed',
                    `${result.synced} reading(s) synced and completed!${result.failed > 0 ? `\n${result.failed} failed` : ''}`,
                    [{ text: 'OK' }]
                  );
                }
              } else {
                Alert.alert(
                  '❌ Sync Failed',
                  result.message,
                  [{ text: 'OK' }]
                );
              }
            } catch (error) {
              Alert.alert('Error', 'Failed to sync: ' + error.message);
            } finally {
              setIsSyncing(false);
            }
          }
        }
      ]
    );
  };

  const getOrRequestSqliteExportDirectoryUri = async () => {
    const existingDirectoryUri = await AsyncStorage.getItem(SQLITE_EXPORT_DIR_URI_KEY);
    if (existingDirectoryUri) {
      return existingDirectoryUri;
    }

    const permission = await FileSystem.StorageAccessFramework.requestDirectoryPermissionsAsync();
    if (!permission.granted) {
      return null;
    }

    await AsyncStorage.setItem(SQLITE_EXPORT_DIR_URI_KEY, permission.directoryUri);
    return permission.directoryUri;
  };

  const normalizeZoneForFileName = (value) => {
    const raw = (value ?? '').toString().trim();
    if (!raw) return 'unknown-zone';
    return raw.replace(/[^a-zA-Z0-9_-]/g, '-');
  };

  const normalizeDateForFileName = (value) => {
    const raw = (value ?? '').toString().trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
    return 'unknown-date';
  };

  const groupReadingsByZoneAndDate = (records = []) => {
    const grouped = {};
    records.forEach((r) => {
      const customer = r?.customer || {};
      const zoneRaw = customer.zone_code ?? customer.zone ?? customer.zoneCode ?? r.zone_code ?? r.zone ?? 'unknown-zone';
      const dateRaw = r.reading_date || r.lastAttempt || '';
      const zone = normalizeZoneForFileName(zoneRaw);
      const date = normalizeDateForFileName(dateRaw);
      const key = `${zone}|${date}`;
      if (!grouped[key]) {
        grouped[key] = { zone, date, rows: [] };
      }
      grouped[key].rows.push(r);
    });
    return Object.values(grouped);
  };

  const autoExportSqliteReadingsToFileManager = async ({ silent = true } = {}) => {
    if (Platform.OS !== 'android') {
      if (!silent) {
        Alert.alert('Not supported', 'Export to file manager is currently available on Android only.');
      }
      return { ok: false, skipped: true };
    }

    try {
      const records = await readingsLocalService.getAllReadingsForExport();
      if (!records || records.length === 0) {
        if (!silent) {
          Alert.alert('No data', 'No local SQLite readings found to export.');
        }
        return { ok: false, skipped: true };
      }

      const now = new Date();
      let directoryUri = await getOrRequestSqliteExportDirectoryUri();
      if (!directoryUri) {
        if (!silent) {
          Alert.alert('Cancelled', 'Folder access was not granted.');
        }
        return { ok: false, cancelled: true };
      }

      const groups = groupReadingsByZoneAndDate(records);
      const writtenFiles = [];
      let fileUrisMap = {};
      try {
        const rawMap = await AsyncStorage.getItem(SQLITE_EXPORT_FILE_URIS_KEY);
        fileUrisMap = rawMap ? JSON.parse(rawMap) : {};
      } catch (_) {
        fileUrisMap = {};
      }

      const writeAllGroupFiles = async (dirUri) => {
        for (const g of groups) {
          const fileName = `wd-readings-zone-${g.zone}-date-${g.date}.json`;
          let fileUri = fileUrisMap[fileName];
          if (!fileUri) {
            fileUri = await FileSystem.StorageAccessFramework.createFileAsync(
              dirUri,
              fileName,
              'application/json'
            );
            fileUrisMap[fileName] = fileUri;
          }
          const payload = {
            exported_at: now.toISOString(),
            database: 'wd-database',
            table: 'read_and_bill',
            zone: g.zone,
            reading_date: g.date,
            total_records: g.rows.length,
            pending_records: g.rows.filter(
              (r) =>
                r.status === readingsLocalService.STATUS_SAVED_OFFLINE ||
                r.status === 'syncing' ||
                r.status === 'pending' ||
                r.status === 'failed'
            ).length,
            rows: g.rows,
          };
          await FileSystem.writeAsStringAsync(fileUri, JSON.stringify(payload, null, 2), {
            encoding: FileSystem.EncodingType.UTF8,
          });
          writtenFiles.push(fileName);
        }
        await AsyncStorage.setItem(SQLITE_EXPORT_FILE_URIS_KEY, JSON.stringify(fileUrisMap));
      };

      try {
        await writeAllGroupFiles(directoryUri);
      } catch (writeError) {
        // Directory permission may be stale; re-prompt once and retry.
        await AsyncStorage.removeItem(SQLITE_EXPORT_DIR_URI_KEY);
        await AsyncStorage.removeItem(SQLITE_EXPORT_FILE_URIS_KEY);
        directoryUri = await getOrRequestSqliteExportDirectoryUri();
        if (!directoryUri) {
          if (!silent) {
            Alert.alert('Cancelled', 'Folder access was not granted.');
          }
          return { ok: false, cancelled: true };
        }
        writtenFiles.length = 0;
        await writeAllGroupFiles(directoryUri);
        console.warn('Re-authorized export directory after stale URI:', writeError?.message || writeError);
      }

      if (!silent) {
        Alert.alert(
          'Export complete',
          `${records.length} reading(s) exported.\n\nCreated/updated ${writtenFiles.length} zone+date file(s) in your selected folder.`,
          [{ text: 'OK' }]
        );
      }
      return { ok: true, count: records.length, files: writtenFiles.length };
    } catch (error) {
      console.error('Export SQLite readings error:', error);
      if (!silent) {
        Alert.alert('Export failed', error?.message || 'Could not export SQLite readings.');
      }
      return { ok: false, error };
    }
  };

  const clearAllRoutes = async () => {
    Alert.alert(
      'Clear All Routes',
      'This will delete all routes from the app.\n\nUse this before uploading NEW assignments from website.\n\nAre you sure?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Clear All',
          style: 'destructive',
          onPress: async () => {
            try {
              await routesStorage.saveRoutes([]);
              setCustomers([]);
              setSelectedCustomer(null);
              // DON'T set isCleared flag - allow immediate refresh for new data
              Alert.alert(
                '✅ Cleared', 
                'All routes deleted.\n\nYou can now:\n1. Upload NEW routes from website\n2. Tap Refresh to load them',
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

  React.useEffect(() => {
    // Load from cache first (instant display)
    const loadInitialData = async () => {
      try {
        const userData = await userStorage.getUserData();
        const readerId = userData?.id;
        console.log('🔄 Component mounted - Loading initial data, readerId:', readerId);
        
        const cachedCustomers = await loadCustomersFromCache(readerId);
        if (cachedCustomers.length > 0) {
          setCustomers(cachedCustomers);
          console.log('✅ Loaded', cachedCustomers.length, 'customers from cache on mount');
        } else {
          console.log('⚠️ No cached customers found on mount');
        }
        // Refresh pending count so "saved offline" badge is correct when returning to screen
        const count = await readingsLocalService.getPendingCount();
        setPendingCount(count);
      } catch (error) {
        console.error('❌ Error loading from cache on mount:', error);
      }
      
      // Then try to refresh from API if online (background refresh)
      // This will update the list if online, or keep cache if offline
      loadCustomersFromRoutes(false);
    };
    
    loadInitialData();
  }, []);

  // Disable sorting after it's been applied (after refresh)
  useEffect(() => {
    if (shouldSortCustomers) {
      // Disable sorting after the current render cycle
      // This ensures sorting only happens once after refresh, not on every status change
      const timer = setTimeout(() => {
        setShouldSortCustomers(false);
      }, 0);
      return () => clearTimeout(timer);
    }
  }, [shouldSortCustomers]);

  const METER_RENTAL = 20.0;
  const MINIMUM_CHARGE = 253.0;

  const resolveClassification = (categoryRaw) => {
    const raw = (categoryRaw ?? '').toString().trim().toUpperCase();
    if (!raw) return 'RESIDENTIAL';

    const mapped = {
      'COM-A': 'COMMERCIAL A',
      'COM-B': 'COMMERCIAL B',
      'COM-C': 'COMMERCIAL C',
      'INDUSTRIAL': 'INDUSTRIAL',
      'GOVT-LGU': 'GOVERNMENT',
      'GOVT': 'GOVERNMENT',
      'RES': 'RESIDENTIAL',
      'RESIDENTIAL': 'RESIDENTIAL',
    };
    if (mapped[raw]) return mapped[raw];

    const num = parseInt(raw, 10);
    if (!Number.isNaN(num)) {
      if (num === 12) return 'RESIDENTIAL';
      if (num === 22) return 'GOVERNMENT';
      if (num === 32) return 'INDUSTRIAL';
      if (num === 33) return 'COMMERCIAL A';
      if (num === 34) return 'COMMERCIAL B';
      if (num === 35) return 'COMMERCIAL C';
      if (num === 36) return 'WHOLESALE';
    }

    if (raw.includes('IND')) return 'INDUSTRIAL';
    if (raw.includes('WHOLESALE') || raw.includes('BULK')) return 'WHOLESALE';
    if (raw.includes('COMA') || raw.includes('COMMERCIAL A')) return 'COMMERCIAL A';
    if (raw.includes('COMB') || raw.includes('COMMERCIAL B')) return 'COMMERCIAL B';
    if (raw.includes('COMC') || raw.includes('COMMERCIAL C')) return 'COMMERCIAL C';
    if (raw.includes('COM')) return 'COMMERCIAL';
    if (raw.includes('GOV')) return 'GOVERNMENT';
    if (raw.includes('RES')) return 'RESIDENTIAL';

    return 'RESIDENTIAL';
  };

  const classificationMultiplier = (classification) => {
    const key = (classification ?? '').toString().trim().toUpperCase();
    switch (key) {
      case 'BULK':
      case 'WHOLESALE':
        return 3.0;
      case 'INDUSTRIAL':
      case 'COMMERCIAL':
        return 2.0;
      case 'COMMERCIAL A':
      case 'COMMERCIAL_A':
      case 'A':
        return 1.75;
      case 'COMMERCIAL B':
      case 'COMMERCIAL_B':
      case 'B':
        return 1.5;
      case 'COMMERCIAL C':
      case 'COMMERCIAL_C':
      case 'C':
        return 1.25;
      default:
        return 1.0;
    }
  };

  const computeResidentialBase = (cu) => {
    if (cu <= 10) return MINIMUM_CHARGE;
    if (cu <= 20) return MINIMUM_CHARGE + (cu - 10) * 27.0;
    if (cu <= 30) return MINIMUM_CHARGE + (10 * 27.0) + (cu - 20) * 28.75;
    if (cu <= 40) return MINIMUM_CHARGE + (10 * 27.0) + (10 * 28.75) + (cu - 30) * 30.55;
    return MINIMUM_CHARGE + (10 * 27.0) + (10 * 28.75) + (10 * 30.55) + (cu - 40) * 32.4;
  };

  // Commercial / Industrial (Category 32): residential base × multiplier + meter rental
  const computeCommercialIndustrial = (cu, classification = 'INDUSTRIAL') => {
    const residentialTotal = computeResidentialBase(cu);
    const multiplier = classificationMultiplier(classification);
    return (residentialTotal * multiplier) + METER_RENTAL;
  };

  const calculateBill = (consumption, categoryRaw) => {
    const cu = parseInt(consumption, 10);
    if (Number.isNaN(cu) || cu < 0) return { bill: 0, includesMeterRental: true };

    const classification = resolveClassification(categoryRaw);
    let result = 0;

    if (classification === 'RESIDENTIAL' || classification === 'GOVERNMENT') {
      result = computeResidentialBase(cu) + METER_RENTAL;
    } else {
      result = computeCommercialIndustrial(cu, classification);
    }

    return { bill: parseFloat(result.toFixed(2)), includesMeterRental: true };
  };

  const handleCustomerSelect = (customer) => {
    setSelectedCustomer(customer);
    
    // If customer is completed, load the current reading; otherwise use estimated reading
    if (isCompletedCustomerStatus(customer.status) && customer.currentReading !== null) {
      setMeterReading(customer.currentReading.toString());
    } else {
      setMeterReading(customer.estimatedReading.toString());
    }
    
    // Automatically set customer type based on category id (default residential)
    const classification = resolveClassification(customer.category ?? '');
    setCustomerType(classification === 'RESIDENTIAL' || classification === 'GOVERNMENT' ? 'residential' : 'commercial');

    // Open Reading Entry directly on customer tap.
    const hasCurrent = customer.currentReading != null && customer.currentReading !== undefined;
    const initialReading = (isCompletedCustomerStatus(customer.status) || isSavedOfflineCustomerStatus(customer.status)) && hasCurrent
      ? customer.currentReading.toString()
      : ((customer.estimatedReading ?? meterReading ?? '') || '').toString();
    setInitialReadingForModal(initialReading);
    setShowReadingModal(true);
  };

  const handleOpenCustomerLocation = async (customer) => {
    if (!customer) return;

    try {
      const address = customer.address || customer.customer_address || customer.address1 || '';
      const name = customer.name || 'Customer';

      // Get account number - try multiple field names
      const accountNumber = customer.accountNumber || customer.account_number || customer.account_no;

      console.log('📍 Getting location for customer from ReadAndBill:', {
        name,
        accountNumber,
        address,
      });

      let lat = null;
      let lng = null;

      if (accountNumber && accountNumber !== '-' && accountNumber !== 'N/A' && accountNumber !== 'undefined') {
        try {
          const token = await tokenStorage.getToken();
          if (token) {
            console.log('🔍 Fetching consumer_zone data for account (ReadAndBill):', accountNumber);

            // Add timeout to prevent hanging (5 seconds)
            const fetchPromise = routesAPI.getConsumerZoneByAccount(accountNumber, token);
            const timeoutPromise = new Promise((_, reject) =>
              setTimeout(() => reject(new Error('API timeout after 5 seconds')), 5000)
            );

            const consumerZone = await Promise.race([fetchPromise, timeoutPromise]);

            if (consumerZone && consumerZone.latitude && consumerZone.longitude) {
              lat = parseFloat(consumerZone.latitude);
              lng = parseFloat(consumerZone.longitude);

              console.log('📍 Coordinates from consumer_zone table (ReadAndBill):', {
                lat,
                lng,
                account_no: consumerZone.account_no,
                source: 'consumer_zone database table',
              });

              if (lat && lng && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                console.log('✅ Valid coordinates from consumer_zone table (ReadAndBill):', { lat, lng });
              } else {
                console.warn('⚠️ Invalid coordinates in consumer_zone table (ReadAndBill):', { lat, lng });
                lat = null;
                lng = null;
              }
            } else {
              console.warn('⚠️ No latitude/longitude found in consumer_zone table for account (ReadAndBill):', accountNumber);
            }
          } else {
            console.warn('⚠️ No token available (ReadAndBill)');
          }
        } catch (error) {
          console.error('❌ API error (ReadAndBill handleOpenCustomerLocation):', error.message);
        }
      }

      if (!lat || !lng) {
        Alert.alert(
          'Location Not Available',
          `GPS coordinates not found in consumer_zone table for account: ${accountNumber || 'N/A'}\n\nCustomer: ${name}\n\nPlease ensure the consumer_zone table has latitude and longitude values for this account.`
        );
        return;
      }

      console.log('✅ Opening Google Maps navigation with coordinates from consumer_zone (ReadAndBill):', {
        lat,
        lng,
        accountNumber,
        name,
      });

      const urlsToTry = [
        `google.navigation:q=${lat},${lng}&mode=d`,
        `geo:${lat},${lng}?q=${lat},${lng}`,
        `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`,
      ];

      let opened = false;
      for (const url of urlsToTry) {
        try {
          console.log('🔗 Trying URL (ReadAndBill):', url);
          const canOpen = await Linking.canOpenURL(url);
          if (!canOpen) continue;
          await Linking.openURL(url);
          console.log('✅ Successfully opened (ReadAndBill):', url);
          opened = true;
          break;
        } catch (e) {
          console.warn('⚠️ Failed to open URL (ReadAndBill):', url, e?.message);
        }
      }

      if (!opened) {
        Alert.alert(
          'Unable to Open Maps',
          'Could not open Google Maps. Please make sure Google Maps is installed or try again.'
        );
      }
    } catch (error) {
      console.error('❌ Error in handleOpenCustomerLocation (ReadAndBill):', error);
      Alert.alert('Error', `Failed to load location: ${error.message}`);
    }
  };

  const openReadingEntry = () => {
    if (!selectedCustomer) return;
    const hasCurrent = selectedCustomer.currentReading != null && selectedCustomer.currentReading !== undefined;
    const initialReading = (isCompletedCustomerStatus(selectedCustomer.status) || isSavedOfflineCustomerStatus(selectedCustomer.status)) && hasCurrent
      ? selectedCustomer.currentReading.toString()
      : (meterReading || selectedCustomer.estimatedReading?.toString() || '');
    setInitialReadingForModal(initialReading);
    setShowReadingModal(true);
  };

  const generateReceipt = (customer, reading, userData = null) => {
    const formatScheduleDate = (value) => {
      if (!value) return '—';
      const parsed = new Date(value);
      if (Number.isNaN(parsed.getTime())) return '—';
      return parsed.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    };

    // Use schedule dates directly; fall back to reading_date variants when bill_date is missing.
    const readingDateRaw =
      customer.billDate ??
      customer.bill_date ??
      customer.scheduleReadingDate ??
      customer.reading_date ??
      customer.readingDate ??
      null;
    const readingDate = formatScheduleDate(readingDateRaw);
    const dueDateFormatted = formatScheduleDate(customer.dueDate);

    // Period covered: meter_reading_schedules.previous_reading_date / reading_date (not bill vs due)
    const periodStartRaw =
      customer.previousReadingDate ?? customer.previous_reading_date ?? null;
    const periodEndRaw =
      customer.scheduleReadingDate ??
      customer.reading_date ??
      customer.readingDate ??
      // Fallback to the schedule-derived reading date helper (uses route schedule fields)
      getReadingDateFromMeterSchedule(customer) ??
      null;
    // Period must be based on meter_reading_schedules columns:
    // previous_reading_date - reading_date
    const periodCovered = `${formatScheduleDate(periodStartRaw)} - ${formatScheduleDate(periodEndRaw)}`;

    // Ensure values are numbers
    const currentReading = parseFloat(reading) || 0;
    const previousReading = parseFloat(customer.lastReading) || 0;
    const consumption = currentReading - previousReading;
    
    // Check if consumption is high (must be positive AND >= 10 cubic meters)
    // IMPORTANT: Only flag if current reading is GREATER than previous reading
    const isHighConsumption = currentReading > previousReading && consumption >= 10;
    
    // Debug log to verify logic
    console.log('Receipt Generation:', {
      previous: previousReading,
      current: currentReading,
      consumption: consumption,
      isHigh: isHighConsumption,
      category: customer.category,
      rateCode: customer.rateCode
    });
    
    const { bill: currentBillCalc, includesMeterRental } = calculateBill(consumption, customer.category);
    
    // Debug billing calculation
    console.log('Billing Calculation:', {
      consumption: consumption,
      category: customer.category,
      rateCode: customer.rateCode,
      calculatedBill: currentBillCalc,
      includesMeterRental: includesMeterRental
    });
    
    // Always show 20.00 as maintenance charge separately
    const meterMaintenanceCharge = 20.00;
    
    // If the calculated bill includes the maintenance charge, subtract it to show separately
    const currentBillOnly = includesMeterRental ? Math.max(0, currentBillCalc - 20.00) : currentBillCalc;
    
    const totalCurrent = currentBillOnly + meterMaintenanceCharge;
    // Arrears from API = balance only
    const arrears = Math.max(0, parseFloat(customer.arrears ?? 0));
    const others = 0.00;
    const totalBill = totalCurrent + arrears + others;
    const surcharge = parseFloat((currentBillOnly * 0.10).toFixed(2)); // 10% of current bill (dynamic surcharge)
    const totalWithSurcharge = totalBill + surcharge;

    // Get reader name from userData
    const readerName = userData?.name || userData?.full_name || userData?.username || 'Unknown Reader';

    return {
      readingDate,
      dueDate: dueDateFormatted,
      periodCovered,
      reading_date: periodEndRaw,
      previous_reading_date: periodStartRaw,
      previousReadingDate: periodStartRaw,
      scheduleReadingDate: periodEndRaw,
      zone: customer.zone || '081',
      consumerType: customer.category || (customerType === 'commercial' ? 'Commercial' : 'Residential'),
      sequence: getConsumerSeriesForReceipt(customer),
      accountNumber: customer.accountNumber ?? customer.account_number ?? customer.account_no ?? '—',
      customer: {
        name: customer.name || 'Unknown Customer',
        address: customer.address || 'No Address',
        meterNumber: customer.meterNumber || 'N/A'
      },
      readings: {
        current: currentReading,
        previous: previousReading,
        consumption: consumption,
        isHighConsumption: isHighConsumption
      },
      billing: {
        currentBill: currentBillOnly.toFixed(2),
        meterMaintenanceCharge: meterMaintenanceCharge.toFixed(2),
        totalCurrent: totalCurrent.toFixed(2),
        arrears: arrears.toFixed(2),
        others: others.toFixed(2),
        totalBill: totalBill.toFixed(2),
        surcharge: surcharge.toFixed(2),
        totalWithSurcharge: totalWithSurcharge.toFixed(2)
      },
      meterReader: readerName
    };
  };

  const printReceipt = async (receiptData) => {
    try {
      // Use logo from Edit Receipt Format if set, else fall back to asset
      let logoUri = '';
      try {
        const format = await receiptFormatStorage.getFormat();
        const showLogo = format?.showLogo !== false;
        if (showLogo) {
          const savedLogo = await receiptLogoStorage.getLogo();
          if (savedLogo) {
            const base64 = await FileSystem.readAsStringAsync(savedLogo, {
              encoding: FileSystem.EncodingType.Base64,
            });
            if (base64 && base64.length > 100) {
              const mime = (savedLogo || '').toLowerCase().endsWith('.png') ? 'image/png' : 'image/jpeg';
              logoUri = `data:${mime};base64,${base64}`;
            }
          }
          if (!logoUri) {
            const [logo] = await Asset.loadAsync(require('./assets/WD-logo.jpg'));
            logoUri = logo?.uri || '';
          }
        }
      } catch (_) {}

      // Build HTML mirroring the on-screen receipt formatting
      const html = `
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <style>
      body { font-family: Arial, Helvetica, sans-serif; color: #2c3e50; margin: 16px; }
      .card { padding: 16px; border: 1px solid #ddd; border-radius: 8px; }
      .header { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
      .logo { width: 60px; height: 60px; object-fit: contain; }
      .companyName { font-size: 18px; font-weight: 700; text-align: center; margin: 0 0 4px 0; }
      .companySub { font-size: 13px; margin: 2px 0; text-align: justify; color: #7f8c8d; }
      .companyMeta { font-size: 12px; margin: 2px 0; text-align: justify; color: #95a5a6; }
      .title { text-align: center; font-size: 16px; font-weight: 700; margin: 12px 0; }
      .sep { height: 1px; background: #ddd; margin: 10px 0; }
      .row { font-size: 14px; line-height: 20px; margin: 4px 0; }
      .total { font-size: 16px; font-weight: 700; margin: 4px 0; color: #2c3e50; }
      .account { text-align: center; font-size: 14px; font-weight: 700; margin-top: 10px; }
      .barcode { font-family: monospace; letter-spacing: 2px; text-align: center; }
    </style>
  </head>
  <body>
    <div class="card">
      <div class="header">
        ${logoUri ? `<img class="logo" src="${logoUri}" />` : ''}
        <div style="flex:1">
          <div class="companyName">HAGONOY WATER DISTRICT</div>
          <div class="companySub">Quihing Hagonoy, Davao Del Sur</div>
          <div class="companyMeta">VATS ID DBN 001-437-440</div>
          <div class="companyMeta">Contact Number: 09484242578</div>
          <div class="companyMeta">Email Address: hagonoywaterdistrict@yahoo.com</div>
        </div>
      </div>

      <div class="title">NOTICE OF COLLECTION BILL</div>
      <div class="sep"></div>

      <div class="row">Period Covered: ${receiptData.periodCovered}</div>
      <div class="row">Zone : ${receiptData.zone} &nbsp;&nbsp;&nbsp;&nbsp; Consumer type: ${receiptData.consumerType}</div>
      <div class="row">Sequence : ${receiptData.sequence}</div>
      <div class="row">Acct No. : ${receiptData.accountNumber}</div>
      <div class="row">Name: ${receiptData.customer.name}</div>
      <div class="row">Address : ${receiptData.customer.address}</div>
      <div class="row">Meter Number/Size : ${receiptData.customer.meterNumber}</div>

      <div class="sep"></div>

      <div class="row">Reading Date :${receiptData.readingDate}</div>
      <div class="row">Due Date :${receiptData.dueDate}</div>

      <div class="sep"></div>

      <div class="row">Present Reading : ${receiptData.readings.current}</div>
      <div class="row">Previous Reading : ${receiptData.readings.previous}</div>
      <div class="row">Cubic Meter Used : ${receiptData.readings.consumption} High Consumption</div>

      <div class="sep"></div>

      <div class="row">Current Bill : ${receiptData.billing.currentBill}</div>
      <div class="row">Meter Maintenance Charge : ${receiptData.billing.meterMaintenanceCharge}</div>
      <div class="row">TOTAL CURRENT : ${receiptData.billing.totalCurrent}</div>


      <div class="row">Arrears : ${receiptData.billing.arrears}</div>
      <div class="row">Others : ${receiptData.billing.others}</div>

      <div class="sep"></div>

      <div class="total">TOTAL BILL: ${receiptData.billing.totalBill}</div>
      
      <div class="row">IF UNPAID AT HWD OFFICE</div>
      <div class="row">After: ${receiptData.dueDate}</div>
      <div class="row">Surcharge : ${receiptData.billing.surcharge}</div>
      <div class="total">TOTAL WITH SURCHARGE : ${receiptData.billing.totalWithSurcharge}</div>

      <div class="sep"></div>

      <div class="row">Notice:</div>
      <div class="row">1. Failure to pay on the specified date of Disconnection Date, we will be constrained to cut off your services connection, disconnection of your water service.</div>
      <div class="row">2. Please disregard the Notice of Disconnection if account has been paid in full.</div>
      <div class="row">3. If service is discontinued, total amount due plus P200.00 reconnection fee.</div>

      <div class="sep"></div>

      <div class="row">Meter Reader : ${receiptData.meterReader}</div>
      <div class="account">${receiptData.accountNumber}</div>

      <div class="barcode" style="margin-top:12px;">
        <div>||||| ||||| ||||| ||||| ||||| ||||| ||||| |||||</div>
        <div>||||| ||||| ||||| ||||| ||||| ||||| ||||| |||||</div>
        <div>||||| ||||| ||||| ||||| ||||| ||||| ||||| |||||</div>
      </div>
    </div>
  </body>
  </html>`;

      await Print.printAsync({ html });
    } catch (e) {
      console.error('Print error', e);
      Alert.alert('Print Error', 'Unable to open print dialog.');
    }
  };

  const matchesScheduleId = (record, scheduleId) => {
    if (record == null || scheduleId == null) return false;
    const rid = record.schedule_id ?? record.scheduleId ?? record.id;
    return (
      rid === scheduleId ||
      Number(rid) === Number(scheduleId) ||
      String(rid) === String(scheduleId)
    );
  };

  // Upload reading to server (used by both manual submit and auto-sync)
  const uploadReadingToServer = async (readingData) => {
    try {
      const token = await tokenStorage.getToken();
      const userData = await userStorage.getUserData();
      
      if (!token || !userData) {
        return { success: false, message: 'No authentication token' };
      }

      const apiConfig = getApiConfig();
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 60000); // 60s timeout so slow connections don't hang
      const uploadResponse = await fetch(`${apiConfig.baseURL}/reader/submit-reading`, {
        method: 'POST',
        signal: controller.signal,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          schedule_id: readingData.schedule_id,
          current_reading: readingData.current_reading,
          reading_date: readingData.reading_date,
          reader_notes: readingData.reader_notes || '',
          reader_id: readingData.reader_id
        })
      });
      clearTimeout(timeoutId);

      let uploadResult;
      try {
        uploadResult = await uploadResponse.json();
      } catch (parseErr) {
        console.error('Upload response not JSON:', parseErr);
        return { success: false, message: 'Invalid server response' };
      }
      // Only treat as success when server returns 2xx AND success: true (offline queue removed only then)
      if (!uploadResponse.ok) {
        return {
          success: false,
          message: uploadResult?.message || `Server error ${uploadResponse.status}`,
        };
      }
      if (uploadResult && uploadResult.success) {
        const scheduleId = readingData.schedule_id;
        try {
          const stored = (await routesStorage.getRoutes()) || [];
          const updatedStored = stored.map((r) =>
            matchesScheduleId(r, scheduleId)
              ? {
                  ...r,
                  status: 'completed',
                  current_reading: readingData.current_reading,
                  currentReading: readingData.current_reading,
                  consumption:
                    readingData.consumption != null
                      ? readingData.consumption
                      : r.consumption,
                }
              : r
          );
          await routesStorage.saveRoutes(updatedStored);
        } catch (e) {
          console.warn('Could not update routes cache after sync:', e?.message);
        }
        setCustomers((prev) =>
          prev.map((c) =>
            matchesScheduleId(c, scheduleId)
              ? {
                  ...c,
                  status: 'completed',
                  currentReading: readingData.current_reading,
                  consumption: readingData.consumption,
                }
              : c
          )
        );
      }

      return uploadResult;
    } catch (error) {
      console.error('Upload error:', error);
      return { success: false, message: error.message };
    }
  };

  const handleSubmitReading = async (overrideReading = null) => {
    const effectiveReading = overrideReading != null ? overrideReading : meterReading;

    if (!selectedCustomer || !effectiveReading) {
      Alert.alert('Error', 'Please select a customer and enter meter reading');
      return;
    }

    const reading = parseInt(effectiveReading);
    if (isNaN(reading) || reading < selectedCustomer.lastReading) {
      Alert.alert('Error', 'Invalid meter reading. Must be greater than previous reading.');
      return;
    }

    // Show confirmation dialog first
    const consumption = reading - selectedCustomer.lastReading;
    const isUpdate = isCompletedCustomerStatus(selectedCustomer?.status);
    const currentReadingText = isUpdate && selectedCustomer.currentReading 
      ? `\nPrevious Current Reading: ${selectedCustomer.currentReading}` 
      : '';
    
    Alert.alert(
      isUpdate ? 'Update Reading' : 'Confirm Reading',
      `Please verify the reading:\n\nCustomer: ${selectedCustomer.name}\nAccount: ${selectedCustomer.accountNumber}\n\nPrevious Reading: ${selectedCustomer.lastReading}${currentReadingText}\nNew Current Reading: ${reading}\nConsumption: ${consumption} m³\n\n${isUpdate ? 'Update' : 'Submit'} this reading?`,
      [
        {
          text: 'Cancel',
          style: 'cancel'
        },
        {
          text: isUpdate ? 'Update' : 'Submit',
          onPress: () => confirmSubmitReading(reading, consumption)
        }
      ]
    );
  };

  const confirmSubmitReading = async (reading, consumption) => {
    setIsProcessing(true);

    try {
      const userData = await userStorage.getUserData();

      // Prepare reading data (use schedule_id from API if id is missing)
      const scheduleId = selectedCustomer?.id ?? selectedCustomer?.schedule_id;
      if (scheduleId == null || scheduleId === '') {
        Alert.alert('Error', 'Invalid customer data. Please go back and select the customer again.');
        setIsProcessing(false);
        return;
      }
      const readingData = {
        schedule_id: scheduleId,
        current_reading: reading,
        reading_date: getReadingDateFromMeterSchedule(selectedCustomer),
        reader_notes: '',
        reader_id: userData?.id,
        consumption: consumption,
        customer: selectedCustomer
      };
    
      const existingPendingId = await readingsLocalService.getPendingIdByScheduleId(scheduleId);
      let localId;
      if (existingPendingId != null) {
        await readingsLocalService.updatePendingReading(existingPendingId, readingData);
        localId = existingPendingId;
      } else {
        const saved = await readingsLocalService.saveReadingToLocal(readingData);
        localId = saved.id;
      }
      await autoExportSqliteReadingsToFileManager({ silent: true });

      // Optimistically mark as saved offline immediately so the UI can move on instantly.
      const initialPendingCount = await readingsLocalService.getPendingCount();
      setPendingCount(initialPendingCount);
      const optimisticCustomers = customers.map((c) =>
        matchesScheduleId(c, scheduleId)
          ? { ...c, status: 'saved offline', currentReading: reading, consumption: consumption }
          : c
      );
      setCustomers(optimisticCustomers);
      await routesStorage.saveRoutes(optimisticCustomers);

      // Print-first flow for faster UX: print immediately after local save, then sync/upload.
      const receiptData = generateReceipt(selectedCustomer, reading, userData);
      // Save for View Receipt screen
      await receiptStorage.saveLastReceipt(receiptData);

      // Stop the spinner before lengthy Bluetooth work so UI doesn't look stuck
      setIsProcessing(false);

      // Direct Bluetooth print - check for saved printer first
      if (!btSupported()) {
        Alert.alert(
          'Bluetooth Printing Not Ready',
          [
            'Install and rebuild to enable Bluetooth printing:',
            '1) npm i react-native-thermal-receipt-printer',
            "2) Expo: npx expo prebuild, then make a dev build",
            '3) Open the app in that build and try again.'
          ].join('\n')
        );
      } else {
        // Check if we have a saved printer
        const savedPrinter = await printerStorage.getPrinter();
        
        if (savedPrinter && savedPrinter.inner_mac_address) {
          // Auto-print with saved printer
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
                    onPress: () => {
                      setPendingReceiptData(receiptData);
                      setShowPrinterSelector(true);
                    }
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
                  onPress: () => {
                    setPendingReceiptData(receiptData);
                    setShowPrinterSelector(true);
                  }
                }
              ]
            );
          }
        } else {
          // No saved printer, show selector
          setPendingReceiptData(receiptData);
          setShowPrinterSelector(true);
        }
      }

      // Continue upload in background (non-blocking). No success/fail popups here.
      void (async () => {
        const deviceOnline = await networkStatus.isOnline(true);
        if (!deviceOnline) {
          return;
        }

        let result = { success: false, message: 'Unknown error' };
        try {
          await readingsLocalService.markSyncing(localId);
          result = await uploadReadingToServer(readingData);
        } catch (uploadError) {
          console.error('Background upload threw:', uploadError);
          result = { success: false, message: uploadError?.message || 'Network or server error' };
        }

        if (result.success) {
          await readingsLocalService.markSynced(localId);
          await autoExportSqliteReadingsToFileManager({ silent: true });
        } else {
          await readingsLocalService.markFailed(localId, result?.message);
          await autoExportSqliteReadingsToFileManager({ silent: true });
        }

        const latestCount = await readingsLocalService.getPendingCount();
        setPendingCount(latestCount);
      })();

    } catch (error) {
      console.error('Error submitting reading:', error);
      const message = error?.message || 'Unknown error';
      Alert.alert(
        'Error',
        `Failed to submit meter reading. ${message}\n\nIf the reading was saved on device, it will sync when connection is available.`,
        [{ text: 'OK' }]
      );
    } finally {
      setIsProcessing(false);
    }
  };

  const handleReadingEntrySave = useCallback(
    async (finalReading) => {
      setMeterReading(finalReading);
      setShowReadingModal(false);
      await handleSubmitReading(finalReading);
      setShowCustomerModal(false);
    },
    [handleSubmitReading]
  );

  const headerHeight = 70;
  const normalizedSearch = debouncedSearchTerm.trim().toLowerCase();

  const visibleCustomers = useMemo(() => {
    const filteredCustomers = customers.filter((c) => {
      const name = (c.name || '').toLowerCase();
      const account = (c.accountNumber || c.account_number || '').toLowerCase();
      const meterNumber = (c.meterNumber || c.meter_number || '').toLowerCase();
      return !normalizedSearch || name.includes(normalizedSearch) || account.includes(normalizedSearch) || meterNumber.includes(normalizedSearch);
    });

    if (!shouldSortCustomers) return filteredCustomers;

    return [...filteredCustomers].sort((a, b) => {
      const statusA = a.status || 'Assigned';
      const statusB = b.status || 'Assigned';

      const getStatusPriority = (status) => {
        if (isCompletedCustomerStatus(status)) return 2;
        if (isSavedOfflineCustomerStatus(status)) return 1;
        return 0;
      };

      const priorityA = getStatusPriority(statusA);
      const priorityB = getStatusPriority(statusB);

      if (priorityA !== priorityB) {
        return priorityA - priorityB;
      }

      const accountA = (a.accountNumber || a.account_number || '').toLowerCase();
      const accountB = (b.accountNumber || b.account_number || '').toLowerCase();
      return accountA.localeCompare(accountB, undefined, { numeric: true, sensitivity: 'base' });
    });
  }, [customers, normalizedSearch, shouldSortCustomers]);

  const renderCustomerItem = useCallback(({ item: customer }) => (
    <TouchableOpacity
      style={[
        styles.customerItem,
        selectedCustomer?.id === customer.id && styles.selectedCustomer
      ]}
      onPress={() => handleCustomerSelect(customer)}
    >
      <View style={styles.customerInfo}>
        <Text style={styles.customerName}>{customer.name}</Text>
        {customer.accountNumber ? (<Text style={styles.customerAddress}>Account: {customer.accountNumber}</Text>) : null}
        {customer.category ? (<Text style={styles.customerAddress}>Category: {customer.category}</Text>) : null}
        {customer.zone ? (<Text style={styles.customerZone}>Zone: {customer.zone}</Text>) : null}
        {customer.address ? (<Text style={styles.customerAddress}>{customer.address}</Text>) : null}
        {customer.meterNumber ? (<Text style={styles.customerMeter}>Meter: {customer.meterNumber}</Text>) : null}
        <View style={styles.customerStats}>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
            <Text style={styles.customerStat}>
              Prev.: {customer.lastReading}
            </Text>
            {customer.currentReading !== null && customer.currentReading !== undefined && (
              <>
                <Text style={[styles.customerStat, { color: '#27ae60' }]}>
                  Curr.: {customer.currentReading}
                </Text>
                {customer.consumption !== null && customer.consumption !== undefined && customer.consumption > 0 && (
                  <Text style={[styles.customerStat, { color: '#3498db' }]}>
                    Usage: {customer.consumption} m³
                  </Text>
                )}
              </>
            )}
          </View>
        </View>
      </View>
      <View style={styles.customerStatus}>
        <View style={[
          styles.statusBadge,
          isCompletedCustomerStatus(customer.status) ? styles.completedBadge :
          isSavedOfflineCustomerStatus(customer.status) ? styles.offlineBadge :
          styles.pendingBadge
        ]}>
          <Text style={styles.statusText}>
            {isCompletedCustomerStatus(customer.status) ? '✅ Completed' :
              isSavedOfflineCustomerStatus(customer.status) ? '💾 Saved Offline' :
              '⏳ Pending'}
          </Text>
        </View>
      </View>
    </TouchableOpacity>
  ), [handleCustomerSelect, selectedCustomer?.id]);

  // Memoize date calculations to prevent recalculation on every render
  const presentDate = useMemo(() => formatDateMDY(new Date()), []);
  const previousDate = useMemo(() => {
    const d = new Date();
    d.setDate(d.getDate() - 30);
    return formatDateMDY(d);
  }, []);

  return (
    <View style={styles.container}>
      <View style={[styles.header, { height: headerHeight }]}>
        <TouchableOpacity style={styles.backButton} onPress={onBack}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={[styles.title, { flex: 1, textAlign: 'center' }]}>Read and Bill</Text>
        <View style={{ width: 56 }} />
      </View>

      <ScrollView 
        style={styles.container}
        showsVerticalScrollIndicator={true}
        nestedScrollEnabled={false}
        ref={scrollViewRef}
        contentContainerStyle={{ paddingTop: headerHeight + 8, paddingBottom: 20 }}
      >

      {/* Sync Status Bar */}
      <View style={styles.syncBar}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
          <TouchableOpacity 
            onPress={refreshNetworkStatus}
            style={[styles.statusBadge, { backgroundColor: isOnline ? '#4CAF50' : '#f44336' }]}
          >
            <Text style={styles.statusText}>{isOnline ? '🌐 Online' : '📴 Offline'}</Text>
          </TouchableOpacity>
          {pendingCount > 0 && (
            <View style={styles.pendingBadge}>
              <Text style={styles.pendingText}>
                {isOnline ? `📤 ${pendingCount} saved offline` : `💾 ${pendingCount} saved offline`}
              </Text>
            </View>
          )}
          {isSyncing && (
            <ActivityIndicator size="small" color="#2196F3" />
          )}
        </View>
        <View style={styles.syncActions}>
          {pendingCount > 0 && isOnline && (
            <TouchableOpacity 
              onPress={handleManualSync} 
              disabled={isSyncing}
              style={[styles.syncButton, isSyncing && styles.syncButtonDisabled]}
            >
              <Text style={styles.syncButtonText}>{isSyncing ? 'Syncing...' : 'Sync Now'}</Text>
            </TouchableOpacity>
          )}
        </View>
      </View>

      <View style={styles.section}>
        <View style={{ marginBottom: 12 }}>
          <Text style={{ fontWeight: '700', color: '#2c3e50', marginBottom: 6 }}>Search</Text>
          <TextInput
            style={styles.searchInput}
            placeholder="Search by name, account number, or meter number"
            placeholderTextColor="#7f8c8d"
            value={searchTerm}
            onChangeText={setSearchTerm}
            autoCorrect={false}
            autoCapitalize="none"
          />
        </View>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 15, flexWrap: 'wrap' }}>
          <Text style={styles.sectionTitle}>Select Customer ({visibleCustomers.length})</Text>
          <View style={{ flexDirection: 'row', gap: 8, flexWrap: 'wrap' }}>
            <TouchableOpacity onPress={() => loadCustomersFromRoutes(true)} disabled={isLoadingRoutes} style={{ backgroundColor: '#3498db', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 6 }}>
              <Text style={{ color: '#fff', fontWeight: 'bold', fontSize: 12 }}>{isLoadingRoutes ? 'Loading...' : 'Refresh'}</Text>
            </TouchableOpacity>
            <TouchableOpacity onPress={clearAllRoutes} disabled={isLoadingRoutes || customers.length === 0 || !isOnline} style={{ backgroundColor: '#dc3545', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 6, opacity: (customers.length === 0 || !isOnline) ? 0.5 : 1 }}>
              <Text style={{ color: '#fff', fontWeight: 'bold', fontSize: 12 }}>Clear All</Text>
            </TouchableOpacity>
          </View>
        </View>
        <View style={styles.customerList}>
          <FlatList
            data={visibleCustomers}
            keyExtractor={(item, index) => String(item?.id ?? `${item?.accountNumber ?? 'customer'}-${index}`)}
            renderItem={renderCustomerItem}
            scrollEnabled={false}
            initialNumToRender={12}
            maxToRenderPerBatch={12}
            windowSize={7}
            removeClippedSubviews
          />
        </View>
      </View>
      {/* Removed View Receipt modal. Printing occurs directly over Bluetooth in handleSubmitReading */}

      {/* Customer Detail Modal */}
      <Modal visible={showCustomerModal} animationType="slide" presentationStyle="fullScreen">
        <View style={styles.detailModalRoot}>
          <View style={styles.detailModalHeader}>
            <TouchableOpacity onPress={() => setShowCustomerModal(false)} style={{ paddingHorizontal: 12, paddingVertical: 6 }}>
              <Text style={{ color: '#ecf0f1', fontSize: 18 }}>✕</Text>
            </TouchableOpacity>
            <Text style={styles.detailModalTitle}>Customer Details</Text>
            <View style={{ width: 32 }} />
          </View>

          <ScrollView contentContainerStyle={{ padding: 16 }}>
            {selectedCustomer && (
              <>
                <View style={styles.section}>
                  <Text style={styles.sectionTitle}>Customer Information</Text>
                  <View style={styles.infoContainer}>
                    <View style={styles.infoRow}>
                      <Text style={styles.infoLabel}>Account:</Text>
                      <Text style={styles.infoValue}>{selectedCustomer.accountNumber || selectedCustomer.account_number || '-'}</Text>
                    </View>
                    <View style={styles.infoRow}>
                      <Text style={styles.infoLabel}>Name:</Text>
                      <Text style={styles.infoValue}>{selectedCustomer.name || 'N/A'}</Text>
                    </View>
                    <View style={styles.infoRow}>
                      <Text style={styles.infoLabel}>Category:</Text>
                      <Text style={styles.infoValue}>{selectedCustomer.category || 'N/A'}</Text>
                    </View>
                    <View style={styles.infoRow}>
                      <Text style={styles.infoLabel}>Zone:</Text>
                      <Text style={styles.infoValue}>{selectedCustomer.zone || 'N/A'}</Text>
                    </View>
                    <View style={styles.infoRow}>
                      <Text style={styles.infoLabel}>Address:</Text>
                      <Text style={styles.infoValue}>{selectedCustomer.address || 'N/A'}</Text>
                    </View>
                    <View style={styles.infoRow}>
                      <Text style={styles.infoLabel}>Meter #:</Text>
                      <Text style={styles.infoValue}>{selectedCustomer.meterNumber || 'N/A'}</Text>
                    </View>
                    <View style={styles.infoRow}>
                      <Text style={styles.infoLabel}>Type:</Text>
                      <View style={[
                        styles.typeBadge,
                        customerType === 'commercial' ? styles.commercialBadge : styles.residentialBadge
                      ]}>
                        <Text style={styles.typeBadgeText}>
                          {customerType === 'commercial' ? 'Commercial' : 'Residential'}
                        </Text>
                      </View>
                    </View>
                  </View>
                </View>

                <View style={styles.section}>
                  <Text style={styles.sectionTitle}>Meter Reading</Text>
                  <View style={styles.readingContainer}>
                    <Text style={styles.readingLabel}>Prev.:</Text>
                    <Text style={styles.readingValue}>{selectedCustomer.lastReading}</Text>
                  </View>
                  <View style={styles.readingContainer}>
                    <Text style={styles.readingLabel}>Curr.:</Text>
                    <TouchableOpacity
                      style={[styles.readingInput, { justifyContent: 'center' }]}
                      onPress={openReadingEntry}
                      activeOpacity={0.8}
                    >
                      <Text style={{ 
                        textAlign: 'center', 
                        fontSize: 16, 
                        color: selectedCustomer.currentReading !== null && selectedCustomer.currentReading !== undefined 
                          ? '#27ae60' 
                          : '#2c3e50',
                        fontWeight: selectedCustomer.currentReading !== null && selectedCustomer.currentReading !== undefined 
                          ? 'bold' 
                          : 'normal'
                      }}>
                        {meterReading || (selectedCustomer.currentReading !== null && selectedCustomer.currentReading !== undefined 
                          ? selectedCustomer.currentReading.toString() 
                          : 'Tap to enter')}
                      </Text>
                    </TouchableOpacity>
                  </View>
                  {selectedCustomer.currentReading !== null && selectedCustomer.currentReading !== undefined && (
                    <View style={styles.readingContainer}>
                      <Text style={styles.readingLabel}>Usage:</Text>
                      <Text style={[styles.readingValue, { color: '#3498db' }]}>
                        {selectedCustomer.consumption || (selectedCustomer.currentReading - selectedCustomer.lastReading)} m³
                      </Text>
                    </View>
                  )}
                  {(!selectedCustomer.currentReading || selectedCustomer.currentReading === null) && (
                    <View style={styles.readingContainer}>
                      <Text style={styles.readingLabel}>Volume:</Text>
                      <Text style={styles.readingValue}>
                        {meterReading ? parseInt(meterReading) - selectedCustomer.lastReading : 0} m³
                      </Text>
                    </View>
                  )}
                </View>

                <View style={styles.section}>
                  <TouchableOpacity
                    style={[
                      styles.submitButton,
                      isCompletedCustomerStatus(selectedCustomer?.status) && styles.updateButton,
                      isSavedOfflineCustomerStatus(selectedCustomer?.status) && styles.updateButton,
                      (!meterReading || isProcessing) && styles.disabledButton
                    ]}
                    onPress={async () => {
                      await handleSubmitReading();
                      setShowCustomerModal(false);
                    }}
                    disabled={!meterReading || isProcessing}
                  >
                    <Text style={styles.submitButtonText}>
                      {isProcessing ? 'Processing...' : 
                       isCompletedCustomerStatus(selectedCustomer?.status) ? 'Update Reading' :
                       isSavedOfflineCustomerStatus(selectedCustomer?.status) ? 'Update Reading' :
                       'Submit Reading'}
                    </Text>
                  </TouchableOpacity>
                </View>
              </>
            )}
          </ScrollView>
        </View>
      </Modal>

      {/* Printer Selector Modal */}
      <PrinterSelector
        visible={showPrinterSelector}
        onSelect={async (printer) => {
          setShowPrinterSelector(false);
          if (pendingReceiptData) {
            try {
              // Save the selected printer for future use
              await printerStorage.savePrinter(printer);
              
              const ok = await printReceiptEscPos(pendingReceiptData, printer);
              if (!ok) {
                Alert.alert('Print Failed', 'Could not print to the selected printer. Please try again.');
              }
            } catch (e) {
              Alert.alert('Print Error', e.message || 'Failed to print receipt.');
            }
            setPendingReceiptData(null);
          }
        }}
        onCancel={() => {
          setShowPrinterSelector(false);
          setPendingReceiptData(null);
        }}
      />

      {/* Reading Entry Modal - isolated so only modal re-renders on keypress (no delay) */}
      <ReadingEntryModal
        visible={showReadingModal}
        onClose={() => setShowReadingModal(false)}
        initialReading={initialReadingForModal}
        selectedCustomer={selectedCustomer}
        onSave={handleReadingEntrySave}
        presentDate={presentDate}
        previousDate={previousDate}
        styles={styles}
      />
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#2c3e50',
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    zIndex: 10,
    borderBottomColor: '#1f2a38',
    borderBottomWidth: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 8,
  },
  backButton: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: '#1f2a38',
  },
  backButtonText: {
    color: '#ecf0f1',
    fontSize: 16,
    fontWeight: '700',
  },
  title: {
    fontSize: 20,
    fontWeight: '800',
    color: '#ecf0f1',
  },
  section: {
    backgroundColor: '#fff',
    margin: 15,
    padding: 20,
    borderRadius: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 15,
  },
  customerList: {
    // Removed maxHeight to allow full scrolling within main ScrollView
  },
  customerItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 15,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    marginBottom: 10,
    backgroundColor: '#f9f9f9',
  },
  selectedCustomer: {
    borderColor: '#3498db',
    backgroundColor: '#e3f2fd',
  },
  customerInfo: {
    flex: 1,
  },
  customerName: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 5,
  },
  customerAddress: {
    fontSize: 14,
    color: '#7f8c8d',
    marginBottom: 3,
  },
  customerMeter: {
    fontSize: 14,
    color: '#7f8c8d',
    marginBottom: 5,
  },
  customerZone: {
    fontSize: 14,
    color: '#2196F3',
    fontWeight: '600',
    marginBottom: 3,
  },
  customerStats: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  customerStat: {
    fontSize: 12,
    color: '#95a5a6',
  },
  customerStatus: {
    marginLeft: 10,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  completedBadge: {
    backgroundColor: '#27ae60',
  },
  offlineBadge: {
    backgroundColor: '#FF9800',
  },
  pendingBadge: {
    backgroundColor: '#95a5a6',
  },
  statusText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: 'bold',
  },
  readingContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 15,
  },
  readingLabel: {
    fontSize: 16,
    color: '#2c3e50',
    fontWeight: '500',
  },
  readingValue: {
    fontSize: 16,
    color: '#2c3e50',
    fontWeight: 'bold',
  },
  readingInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    backgroundColor: '#fff',
    minWidth: 120,
    textAlign: 'center',
  },
  submitButton: {
    backgroundColor: '#27ae60',
    padding: 15,
    borderRadius: 8,
    alignItems: 'center',
  },
  updateButton: {
    backgroundColor: '#f39c12', // Orange color for update button
  },
  disabledButton: {
    backgroundColor: '#bdc3c7',
  },
  submitButtonText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: 'bold',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#2c3e50',
  },
  closeButton: {
    marginRight: 15,
  },
  closeButtonText: {
    color: '#ecf0f1',
    fontSize: 24,
    fontWeight: 'bold',
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#ecf0f1',
  },
  receiptContainer: {
    flex: 1,
    padding: 20,
  },
  receiptContent: {
    backgroundColor: '#fff',
    padding: 20,
    borderRadius: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  receiptHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 20,
  },
  receiptLogo: {
    width: 60,
    height: 60,
    marginRight: 15,
  },
  receiptHeaderText: {
    flex: 1,
  },
  companyName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    textAlign: 'center',
    marginBottom: 5,
  },
  companyAddress: {
    fontSize: 14,
    color: '#7f8c8d',
    textAlign: 'justify',
    marginBottom: 3,
  },
  companyDetails: {
    fontSize: 12,
    color: '#95a5a6',
    textAlign: 'justify',
    marginBottom: 2,
  },
  billTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    textAlign: 'center',
    marginBottom: 15,
  },
  separator: {
    height: 1,
    backgroundColor: '#ddd',
    marginVertical: 10,
  },
  receiptText: {
    fontSize: 14,
    color: '#2c3e50',
    marginBottom: 5,
    lineHeight: 20,
  },
  optionsOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  optionsContainer: {
    width: '85%',
    backgroundColor: '#ffffff',
    borderRadius: 12,
    paddingVertical: 20,
    paddingHorizontal: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 6,
    elevation: 8,
  },
  optionsTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#2c3e50',
    textAlign: 'center',
    marginBottom: 4,
  },
  optionsSubtitle: {
    fontSize: 14,
    color: '#7f8c8d',
    textAlign: 'center',
    marginBottom: 16,
  },
  optionButtonPrimary: {
    backgroundColor: '#27ae60',
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    marginBottom: 10,
  },
  optionButtonPrimaryText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '600',
  },
  optionButtonSecondary: {
    backgroundColor: '#2980b9',
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    marginBottom: 10,
  },
  optionButtonSecondaryText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '600',
  },
  optionButtonCancel: {
    paddingVertical: 10,
    borderRadius: 8,
    alignItems: 'center',
  },
  optionButtonCancelText: {
    color: '#7f8c8d',
    fontSize: 14,
    fontWeight: '500',
  },
  totalBill: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 5,
  },
  accountNumber: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2c3e50',
    textAlign: 'center',
    marginTop: 10,
  },
  barcodeContainer: {
    alignItems: 'center',
    marginTop: 15,
  },
  barcodeText: {
    fontSize: 12,
    fontFamily: 'monospace',
    color: '#2c3e50',
    letterSpacing: 2,
  },
  modalActions: {
    flexDirection: 'row',
    padding: 20,
    backgroundColor: '#fff',
    borderTopWidth: 1,
    borderTopColor: '#ddd',
  },
  printButton: {
    flex: 1,
    backgroundColor: '#27ae60',
    padding: 15,
    borderRadius: 8,
    alignItems: 'center',
    marginRight: 10,
  },
  printButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  closeModalButton: {
    flex: 1,
    backgroundColor: '#95a5a6',
    padding: 15,
    borderRadius: 8,
    alignItems: 'center',
  },
  closeModalButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  infoContainer: {
    backgroundColor: '#f8f9fa',
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  infoLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#555',
  },
  infoValue: {
    fontSize: 14,
    color: '#333',
    fontWeight: '500',
  },
  typeBadge: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
  },
  detailModalRoot: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  detailModalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: '#2c3e50',
  },
  detailModalTitle: {
    color: '#ecf0f1',
    fontSize: 18,
    fontWeight: 'bold',
  },
  commercialBadge: {
    backgroundColor: '#9C27B0',
  },
  residentialBadge: {
    backgroundColor: '#2196F3',
  },
  typeBadgeText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: 'bold',
  },
  // Reading Entry styles
  readingEntryRoot: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  readingEntryHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 12,
    backgroundColor: '#2c3e50',
  },
  readingEntryTitle: {
    color: '#ecf0f1',
    fontSize: 18,
    fontWeight: 'bold',
  },
  readingEntryBody: {
    flex: 1,
    flexDirection: 'row',
  },
  leftPane: {
    width: '42%',
    backgroundColor: '#d6f5a1',
    padding: 12,
  },
  leftPaneMuted: {
    color: '#2c3e50',
    fontSize: 12,
    fontWeight: '700',
  },
  leftPaneStrong: {
    color: '#1a237e',
    fontSize: 18,
    fontWeight: '700',
  },
  leftPaneStrongSmall: {
    color: '#1a237e',
    fontSize: 14,
    fontWeight: '700',
  },
  rightPane: {
    width: '58%',
    backgroundColor: '#fff176',
    padding: 12,
    alignItems: 'center',
  },
  volumeLabel: {
    fontSize: 14,
    color: '#2c3e50',
    fontWeight: '700',
  },
  volumeValue: {
    fontSize: 28,
    color: '#1a237e',
    fontWeight: '800',
    marginBottom: 6,
  },
  readingDisplay: {
    width: '100%',
    backgroundColor: '#fff',
    borderRadius: 8,
    paddingVertical: 16,
    alignItems: 'center',
    marginBottom: 8,
  },
  readingDisplayText: {
    fontSize: 36,
    fontWeight: '800',
    color: '#2c3e50',
  },
  datesRow: {
    width: '100%',
    flexDirection: 'row',
    justifyContent: 'space-around',
    marginBottom: 8,
  },
  dateLabel: {
    fontSize: 12,
    color: '#1e88e5',
    fontWeight: '700',
  },
  dateValue: {
    fontSize: 14,
    color: '#2c3e50',
    fontWeight: '700',
  },
  keypad: {
    width: '100%',
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  key: {
    width: '32%',
    aspectRatio: 1,
    backgroundColor: '#fff',
    borderRadius: 8,
    marginBottom: 8,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.08,
    shadowRadius: 2,
    elevation: 1,
    // Optimize for touch responsiveness
    overflow: 'hidden',
    // Improve touch target
    minHeight: 60,
  },
  keyPressed: {
    backgroundColor: '#e0e0e0',
    opacity: 0.8,
  },
  keyText: {
    fontSize: 28,
    fontWeight: '700',
    color: '#2c3e50',
  },
  saveRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 6,
    width: '100%',
  },
  saveBtn: {
    flex: 1,
    backgroundColor: '#27ae60',
    padding: 12,
    borderRadius: 8,
    alignItems: 'center',
    marginRight: 6,
  },
  clearBtn: {
    flex: 1,
    backgroundColor: '#f39c12',
    padding: 12,
    borderRadius: 8,
    alignItems: 'center',
    marginLeft: 6,
  },
  saveBtnText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
  syncBar: {
    backgroundColor: '#fff',
    paddingHorizontal: 16,
    paddingVertical: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  syncActions: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
  },
  statusText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '600',
  },
  pendingBadge: {
    backgroundColor: '#FF9800',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
  },
  pendingText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '600',
  },
  syncButton: {
    backgroundColor: '#2196F3',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 6,
  },
  syncButtonDisabled: {
    backgroundColor: '#ccc',
  },
  syncButtonText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: 'bold',
  },
  searchInput: {
    borderWidth: 1,
    borderColor: '#d0d7de',
    backgroundColor: '#fff',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#2c3e50',
  },
});

export default ReadAndBill;

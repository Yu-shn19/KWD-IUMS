import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { getApiConfig } from '../config/api';
import * as readingsLocalService from './readingsLocalService';

const STORAGE_KEYS = {
  PENDING_READINGS: 'pending_readings_queue',
  PENDING_DISCONNECTOR_ACTIONS: 'pending_disconnector_actions',
  SYNC_STATUS: 'sync_status',
};

const LEGACY_ASYNC_READINGS_MIGRATION_KEY = 'pending_readings_migrated_sqlite_v2';

let legacyReadingsMigrationPromise = null;

async function migrateLegacyAsyncStoragePendingReadingsOnce() {
  if (legacyReadingsMigrationPromise) return legacyReadingsMigrationPromise;
  legacyReadingsMigrationPromise = (async () => {
    try {
      const done = await AsyncStorage.getItem(LEGACY_ASYNC_READINGS_MIGRATION_KEY);
      if (done === '1') return;

      const raw = await AsyncStorage.getItem(STORAGE_KEYS.PENDING_READINGS);
      if (raw == null || raw === '') {
        await AsyncStorage.setItem(LEGACY_ASYNC_READINGS_MIGRATION_KEY, '1');
        return;
      }

      let queue;
      try {
        queue = JSON.parse(raw);
      } catch (_) {
        await AsyncStorage.removeItem(STORAGE_KEYS.PENDING_READINGS);
        await AsyncStorage.setItem(LEGACY_ASYNC_READINGS_MIGRATION_KEY, '1');
        return;
      }

      if (!Array.isArray(queue) || queue.length === 0) {
        await AsyncStorage.removeItem(STORAGE_KEYS.PENDING_READINGS);
        await AsyncStorage.setItem(LEGACY_ASYNC_READINGS_MIGRATION_KEY, '1');
        return;
      }

      for (const item of queue) {
        const data = item?.data;
        if (!data || data.schedule_id == null) continue;
        const st = (item?.status || 'pending').toString().toLowerCase();
        if (st !== 'pending' && st !== 'failed' && st !== 'syncing') continue;

        const existingId = await readingsLocalService.getPendingIdByScheduleId(data.schedule_id);
        const payload = {
          schedule_id: data.schedule_id,
          current_reading: data.current_reading,
          reading_date: data.reading_date,
          reader_notes: data.reader_notes || '',
          reader_id: data.reader_id,
          consumption: data.consumption,
          customer: data.customer || {},
        };

        if (existingId != null) {
          await readingsLocalService.updatePendingReading(existingId, payload);
          if (st === 'failed' && item?.error) {
            await readingsLocalService.markFailed(existingId, String(item.error));
          }
        } else {
          const saved = await readingsLocalService.saveReadingToLocal(payload);
          if (st === 'failed' && saved?.id && item?.error) {
            await readingsLocalService.markFailed(saved.id, String(item.error));
          }
        }
      }

      await AsyncStorage.removeItem(STORAGE_KEYS.PENDING_READINGS);
      await AsyncStorage.setItem(LEGACY_ASYNC_READINGS_MIGRATION_KEY, '1');
      console.log('✅ Migrated legacy AsyncStorage pending_readings_queue into SQLite');
    } catch (e) {
      console.error('Legacy pending readings migration error:', e);
      legacyReadingsMigrationPromise = null;
      throw e;
    }
  })();
  return legacyReadingsMigrationPromise;
}

function mapSqliteRowToLegacyQueueItem(r) {
  return {
    id: String(r.id),
    timestamp: r.lastAttempt || new Date().toISOString(),
    data: r.data,
    status: r.status,
    retryCount: r.retryCount || 0,
  };
}

// Offline queue for readings: backed by SQLite (readingsLocalService). Legacy AsyncStorage is migrated once.
export const offlineQueue = {
  addReading: async (readingData) => {
    try {
      await migrateLegacyAsyncStoragePendingReadingsOnce();
      const sid = readingData?.schedule_id;
      if (sid == null || sid === '') {
        console.error('addReading: missing schedule_id');
        return null;
      }
      const existingId = await readingsLocalService.getPendingIdByScheduleId(sid);
      if (existingId != null) {
        await readingsLocalService.updatePendingReading(existingId, readingData);
        console.log('✅ Reading upserted in SQLite (saved offline):', existingId);
        return String(existingId);
      }
      const saved = await readingsLocalService.saveReadingToLocal(readingData);
      console.log('✅ Reading added to SQLite (saved offline):', saved.id);
      return String(saved.id);
    } catch (error) {
      console.error('Error adding reading to SQLite queue:', error);
      return null;
    }
  },

  getQueue: async () => {
    try {
      await migrateLegacyAsyncStoragePendingReadingsOnce();
      const rows = await readingsLocalService.getUnsyncedReadingsForUIMerge();
      return rows.map(mapSqliteRowToLegacyQueueItem);
    } catch (error) {
      console.error('Error getting readings queue from SQLite:', error);
      return null;
    }
  },

  removeReading: async (readingId) => {
    try {
      await migrateLegacyAsyncStoragePendingReadingsOnce();
      const id = Number(readingId);
      if (!Number.isFinite(id)) return false;
      await readingsLocalService.markSynced(id);
      console.log('✅ Reading marked synced (removed from unsynced queue):', id);
      return true;
    } catch (error) {
      console.error('Error removing reading from SQLite queue:', error);
      return false;
    }
  },

  updateReadingStatus: async (readingId, status, error = null) => {
    try {
      await migrateLegacyAsyncStoragePendingReadingsOnce();
      const id = Number(readingId);
      if (!Number.isFinite(id)) return false;
      const s = (status || '').toString().toLowerCase();
      if (s === 'syncing') {
        await readingsLocalService.markSyncing(id);
      } else if (s === 'failed') {
        await readingsLocalService.markFailed(id, error || 'Upload failed');
      } else if (s === 'pending') {
        await readingsLocalService.markPending(id);
      } else {
        await readingsLocalService.markPending(id);
      }
      return true;
    } catch (err) {
      console.error('Error updating reading status in SQLite:', err);
      return false;
    }
  },

  getPendingCount: async () => {
    try {
      await migrateLegacyAsyncStoragePendingReadingsOnce();
      return await readingsLocalService.getPendingCount();
    } catch (error) {
      console.error('Error getting pending count from SQLite:', error);
      return 0;
    }
  },

  clearQueue: async () => {
    try {
      await migrateLegacyAsyncStoragePendingReadingsOnce();
      await readingsLocalService.deleteAllPendingReadings();
      await AsyncStorage.removeItem(STORAGE_KEYS.PENDING_READINGS);
      return true;
    } catch (error) {
      console.error('Error clearing SQLite readings queue:', error);
      return false;
    }
  },
};

export const disconnectorQueue = {
  _buildActionSignature: (actionLike = {}) => {
    const data = actionLike?.data || {};
    const payload = data?.payload || {};
    const type = (actionLike?.type || '').toString().trim().toLowerCase();
    const orderId = (payload?.order_id ?? payload?.assignment_id ?? payload?.schedule_id ?? '').toString().trim();
    const accountNo = (data?.accountNumber ?? payload?.account_number ?? payload?.account_no ?? '').toString().trim();
    const statusCode = (data?.statusCode ?? payload?.status_code ?? payload?.status ?? '').toString().trim().toUpperCase();
    return `${type}|${orderId}|${accountNo}|${statusCode}`;
  },

  compactQueue: async () => {
    try {
      const queue = await disconnectorQueue.getQueue();
      if (!Array.isArray(queue) || queue.length === 0) return [];

      const bySignature = new Map();
      for (const item of queue) {
        const status = (item?.status || '').toString().trim().toLowerCase();
        // Keep only actionable queue rows
        if (!(status === 'pending' || status === 'failed' || status === 'syncing')) continue;
        const sig = disconnectorQueue._buildActionSignature(item);
        const existing = bySignature.get(sig);
        const existingTs = new Date(existing?.timestamp || 0).getTime();
        const currentTs = new Date(item?.timestamp || 0).getTime();
        if (!existing || currentTs >= existingTs) {
          bySignature.set(sig, item);
        }
      }

      const compacted = Array.from(bySignature.values());
      if (compacted.length !== queue.length) {
        await AsyncStorage.setItem(STORAGE_KEYS.PENDING_DISCONNECTOR_ACTIONS, JSON.stringify(compacted));
        console.log(`🧹 Disconnector queue compacted: ${queue.length} -> ${compacted.length}`);
      }
      return compacted;
    } catch (error) {
      console.error('Error compacting disconnector queue:', error);
      return [];
    }
  },

  addAction: async (actionData) => {
    try {
      const queue = await disconnectorQueue.compactQueue();
      const newAction = {
        id: Date.now().toString(),
        timestamp: new Date().toISOString(),
        type: actionData.type, 
        status: 'pending',
        retryCount: 0,
        data: actionData.data, 
      };

      const newSig = disconnectorQueue._buildActionSignature(newAction);
      const deduped = queue.filter((item) => disconnectorQueue._buildActionSignature(item) !== newSig);
      deduped.push(newAction);
      await AsyncStorage.setItem(STORAGE_KEYS.PENDING_DISCONNECTOR_ACTIONS, JSON.stringify(deduped));
      console.log('✅ Disconnector action added to offline queue:', newAction.id, newAction.type);
      return newAction.id;
    } catch (error) {
      console.error('Error adding disconnector action to queue:', error);
      return null;
    }
  },

  getQueue: async () => {
    try {
      const raw = await AsyncStorage.getItem(STORAGE_KEYS.PENDING_DISCONNECTOR_ACTIONS);
      return raw ? JSON.parse(raw) : [];
    } catch (error) {
      console.error('Error getting disconnector queue:', error);
      return [];
    }
  },

  removeAction: async (actionId) => {
    try {
      const queue = await disconnectorQueue.getQueue();
      const filtered = queue.filter((a) => a.id !== actionId);
      await AsyncStorage.setItem(STORAGE_KEYS.PENDING_DISCONNECTOR_ACTIONS, JSON.stringify(filtered));
      return true;
    } catch (error) {
      console.error('Error removing disconnector action:', error);
      return false;
    }
  },

  updateActionStatus: async (actionId, status, error = null) => {
    try {
      const queue = await disconnectorQueue.getQueue();
      const action = queue.find((a) => a.id === actionId);
      if (action) {
        action.status = status;
        action.lastAttempt = new Date().toISOString();
        if (error) action.error = error;
        if (status === 'failed') action.retryCount = (action.retryCount || 0) + 1;
        await AsyncStorage.setItem(STORAGE_KEYS.PENDING_DISCONNECTOR_ACTIONS, JSON.stringify(queue));
      }
      return true;
    } catch (err) {
      console.error('Error updating disconnector action status:', err);
      return false;
    }
  },

  getPendingCount: async () => {
    try {
      const queue = await disconnectorQueue.compactQueue();
      return queue.filter((a) => a.status === 'pending' || a.status === 'failed').length;
    } catch (error) {
      console.error('Error getting disconnector pending count:', error);
      return 0;
    }
  },

  clearQueue: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.PENDING_DISCONNECTOR_ACTIONS);
      return true;
    } catch (error) {
      console.error('Error clearing disconnector queue:', error);
      return false;
    }
  },
};

// Network Status Detection
export const networkStatus = {

  _apiConnectivityCache: {
    result: null,
    timestamp: 0,
    cacheDuration: 15000, // 15 seconds so a quick retry after connection works
  },

  // Test API server connectivity 
  testAPIConnectivity: async () => {
    try {
      const apiConfig = getApiConfig();
      const testUrl = `${apiConfig.baseURL}/reader/login`; 
      
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000); 
      
      try {
        // Use GET so servers that don't support HEAD still respond
        const response = await fetch(testUrl, {
          method: 'GET',
          signal: controller.signal,
          headers: {
            'Accept': 'application/json',
          },
        });
        clearTimeout(timeoutId);
        
        // Any response (2xx, 3xx, 4xx) means server is reachable; 5xx or network error = false
        const isReachable = response.status >= 200 && response.status < 500;
        return isReachable;
      } catch (fetchError) {
        clearTimeout(timeoutId);
        if (fetchError.name === 'AbortError') {
          console.log('⏱️ API connectivity test timed out');
        }
        return false;
      }
    } catch (error) {
      console.error('Error testing API connectivity:', error);
      return false;
    }
  },

  
  isAPIReachable: async (useCache = true) => {
    const now = Date.now();
    if (useCache && networkStatus._apiConnectivityCache.result !== null &&
        (now - networkStatus._apiConnectivityCache.timestamp) < networkStatus._apiConnectivityCache.cacheDuration) {
      return networkStatus._apiConnectivityCache.result;
    }
    const result = await networkStatus.testAPIConnectivity();
    networkStatus._apiConnectivityCache.result = result;
    networkStatus._apiConnectivityCache.timestamp = now;
    return result;
  },

  canSyncToServer: async () => {
    try {
      const state = await NetInfo.fetch();
      if (state.isConnected !== true) {
        console.log('📴 canSyncToServer: no device connection');
        return false;
      }
      // Allow any connection type when device is connected (wifi, cellular, ethernet, unknown)
      // so emulators and various networks can still sync when API is reachable
      const apiOk = await networkStatus.isAPIReachable(true);
      if (!apiOk) {
        console.log('📴 canSyncToServer: API not reachable');
        return false;
      }
      return true;
    } catch (error) {
      console.error('Error in canSyncToServer:', error);
      return false;
    }
  },

  // Check if online (prioritize device connectivity, API test is optional)
  isOnline: async (skipAPITest = false) => {
    try {
      // First check device network connectivity
      const state = await NetInfo.fetch();
      const isConnected = state.isConnected === true;
      // PRODUCTION FIX: isInternetReachable can be null on some devices/networks
      // Only consider offline if explicitly set to false
      const isInternetReachable = state.isInternetReachable !== false;
      
      // If no device network, definitely offline
      if (!isConnected) {
        console.log('📴 Device has no network connection', {
          isConnected,
          isInternetReachable,
          type: state.type
        });
        networkStatus._apiConnectivityCache.result = false; // Clear cache
        return false;
      }
      
      // Device has network - log for debugging
      console.log('🌐 Device has network connection', {
        type: state.type,
        isConnected,
        isInternetReachable
      });

      // PRODUCTION: If device is connected, assume online
      // isInternetReachable may be null in production, don't block on it
      // If device has network, return true - let API calls handle actual connectivity
      return true;
    } catch (error) {
      console.error('Error checking network status:', error);
      // On error, assume online to avoid blocking functionality
      // The API calls themselves will handle actual connectivity issues
      return true;
    }
  },

  // Subscribe to network changes (automatic detection)
  subscribe: (callback) => {
    // Get initial state first
    NetInfo.fetch().then(initialState => {
      const isConnected = initialState.isConnected === true;
      // PRODUCTION FIX: isInternetReachable can be null, treat as true if device is connected
      const isInternetReachable = initialState.isInternetReachable !== false;
      const deviceOnline = isConnected;  // Only check isConnected for production reliability
      console.log('📡 Initial network state:', deviceOnline ? '🌐 Online' : '📴 Offline');
      callback(deviceOnline, initialState);
    });
    
    let lastKnownStatus = null;
    
    // Listen to device network changes (immediate) - this is the primary indicator
    const unsubscribe = NetInfo.addEventListener(state => {
      const isConnected = state.isConnected === true;
      // PRODUCTION: Only rely on isConnected, not isInternetReachable (often null in production)
      const deviceOnline = isConnected;
      
      console.log('📡 NetInfo state changed:', {
        isConnected,
        isInternetReachable: state.isInternetReachable,
        deviceOnline,
        type: state.type,
        details: state.details
      });
      
      // Trigger callback if status changed
      if (deviceOnline !== lastKnownStatus || lastKnownStatus === null) {
        console.log(`📡 Network status changed: ${lastKnownStatus ?? 'unknown'} → ${deviceOnline ? '🌐 Online' : '📴 Offline'}`);
        lastKnownStatus = deviceOnline;

        migrateLegacyAsyncStoragePendingReadingsOnce()
          .then(() => readingsLocalService.getPendingCount())
          .then((n) => console.log('📦 Unsynced readings (SQLite):', n))
          .catch(() => {});

        // Clear cache when network status changes to allow fresh checks
        if (deviceOnline) {
          networkStatus._apiConnectivityCache.result = null; // Clear cache to force fresh check
          networkStatus._apiConnectivityCache.timestamp = 0;
          console.log('🔄 Network restored - cache cleared');
        } else {
          networkStatus._apiConnectivityCache.result = false;
        }
        
        // Always call callback to ensure UI updates
        callback(deviceOnline, state);
      }
    });
    
    // Return cleanup function
    return () => {
      unsubscribe();
    };
  },
};

/** Max readings to upload per batch before fetching the next pending set from SQLite */
const SYNC_BATCH_SIZE = 10;
const MAX_TRANSIENT_CONNECTIVITY_MISSES = 3;

// Sync Management
export const syncManager = {
  _syncLock: false,
  /** When true, the current holder runs another pass after draining batches (overlapping sync requests). */
  _syncQueued: false,

  // Sync all pending readings from SQLite human upload sa main DB (batches of SYNC_BATCH_SIZE until queue empty)
  syncAll: async (uploadFunction) => {
    if (syncManager._syncLock) {
      syncManager._syncQueued = true;
      console.log('⚠️ Sync already in progress — queued another pass when current run finishes');
      return {
        success: true,
        queued: true,
        message: 'Sync in progress — will run another pass for any remaining readings',
        synced: 0,
        failed: 0,
      };
    }
    syncManager._syncLock = true;
    let synced = 0;
    let failed = 0;
    let transientConnectivityMisses = 0;
    try {
      // Recover rows left in syncing state from crashes/app kills.
      await readingsLocalService.resetStuckSyncingRows();
      await migrateLegacyAsyncStoragePendingReadingsOnce();

      const processOneReading = async (reading) => {
        try {
          await readingsLocalService.markSyncing(reading.id);
          const result = await uploadFunction(reading.data);

          if (result && result.success === true) {
            await readingsLocalService.markSynced(reading.id);
            synced++;
            console.log('✅ Synced reading to main DB:', reading.id);
          } else {
            await readingsLocalService.markFailed(reading.id, result?.message || 'Upload failed');
            failed++;
            console.log('❌ Failed to sync reading:', reading.id, result?.message);
          }
        } catch (error) {
          await readingsLocalService.markFailed(reading.id, error.message);
          failed++;
          console.error('❌ Error syncing reading:', reading.id, error);
        }
      };

      while (true) {
        const canSync = await networkStatus.canSyncToServer();
        if (!canSync) {
          transientConnectivityMisses += 1;
          console.log(
            `⚠️ Cannot sync - device offline or server unreachable (miss ${transientConnectivityMisses}/${MAX_TRANSIENT_CONNECTIVITY_MISSES})`
          );
          if (transientConnectivityMisses < MAX_TRANSIENT_CONNECTIVITY_MISSES) {
            await new Promise((resolve) => setTimeout(resolve, 1500));
            continue;
          }
          if (synced === 0 && failed === 0) {
            return {
              success: false,
              message: 'Device offline or server unreachable',
              synced: 0,
              failed: 0
            };
          }
          break;
        }
        transientConnectivityMisses = 0;

        const pending = await readingsLocalService.getPendingReadings();
        const batch = pending.slice(0, SYNC_BATCH_SIZE);

        if (batch.length === 0) {
          if (syncManager._syncQueued) {
            syncManager._syncQueued = false;
            continue;
          }
          break;
        }

        console.log(
          `🔄 Sync batch: ${batch.length} reading(s) (${SYNC_BATCH_SIZE} max per batch), ${pending.length} total saved offline in queue`
        );

        for (const reading of batch) {
          await processOneReading(reading);
        }

        // Brief yield so the UI thread can breathe between batches
        await new Promise((resolve) => setTimeout(resolve, 50));
      }

      console.log(`✅ Sync complete: ${synced} synced, ${failed} failed`);

      const message =
        synced > 0 || failed > 0
          ? `Synced ${synced} reading(s) in batches of ${SYNC_BATCH_SIZE}${failed > 0 ? `, ${failed} failed` : ''}`
          : 'No pending readings';

      return {
        success: true,
        message,
        synced,
        failed,
      };
    } catch (error) {
      console.error('Error in syncAll:', error);
      return { success: false, message: error.message, synced, failed };
    } finally {
      syncManager._syncLock = false;
      if (syncManager._syncQueued) {
        syncManager._syncQueued = false;
        setTimeout(() => {
          syncManager.syncAll(uploadFunction).catch((e) =>
            console.error('Follow-up sync after queue failed:', e)
          );
        }, 0);
      }
    }
  },

  // Auto-sync when online
  setupAutoSync: (uploadFunction, interval = 30000) => {
    let syncInterval = null;
    let unsubscribe = null;

    const startAutoSync = () => {
      // Ensure interrupted sync rows become retryable when app/session starts.
      readingsLocalService.resetStuckSyncingRows().catch((e) =>
        console.warn('resetStuckSyncingRows (auto-sync start):', e?.message || e)
      );

      // Sync every interval (default 30 seconds) - automatic for production
      syncInterval = setInterval(async () => {
        const isOnline = await networkStatus.isOnline(true); // Quick check for auto-sync
        if (isOnline) {
          const pending = await readingsLocalService.getPendingCount();
          if (pending > 0) {
            console.log('🔄 Auto-sync triggered (automatic)...');
            await syncManager.syncAll(uploadFunction);
          }
        }
      }, interval);

      // Listen for network changes
      unsubscribe = networkStatus.subscribe(async (isOnline) => {
        if (isOnline) {
          console.log('📡 Network restored - syncing...');
          const pending = await readingsLocalService.getPendingCount();
          if (pending > 0) {
            setTimeout(async () => {
              await syncManager.syncAll(uploadFunction);
            }, 2000); // Wait 2 seconds after reconnection
          }
        } else {
          console.log('📡 Network lost - readings will be queued');
        }
      });
    };

    const stopAutoSync = () => {
      if (syncInterval) {
        clearInterval(syncInterval);
        syncInterval = null;
      }
      if (unsubscribe) {
        unsubscribe();
        unsubscribe = null;
      }
    };

    // Start auto-sync
    startAutoSync();

    // Return cleanup function
    return stopAutoSync;
  },

  // Sync all pending disconnector actions (disconnect/reconnect saved offline)
  syncDisconnectorActions: async (syncOneAction) => {
    try {
      const isOnline = await networkStatus.isOnline();
      if (!isOnline) {
        console.log('⚠️ Cannot sync disconnector actions - device is offline');
        return { success: false, message: 'Device is offline', synced: 0, failed: 0 };
      }

      const queue = await disconnectorQueue.getQueue();
      const pending = queue.filter(
        (a) => a.status === 'pending' || (a.status === 'failed' && (a.retryCount || 0) < 5)
      );

      if (pending.length === 0) {
        return { success: true, message: 'No pending disconnector actions', synced: 0, failed: 0 };
      }

      console.log(`🔄 Syncing ${pending.length} disconnector action(s)...`);
      let synced = 0;
      let failed = 0;

      for (const action of pending) {
        try {
          await disconnectorQueue.updateActionStatus(action.id, 'syncing');
          const result = await syncOneAction(action);
          if (result && result.success) {
            await disconnectorQueue.removeAction(action.id);
            synced++;
            console.log('✅ Synced disconnector action:', action.id, action.type);
          } else {
            await disconnectorQueue.updateActionStatus(action.id, 'failed', result?.message || 'Sync failed');
            failed++;
          }
        } catch (error) {
          await disconnectorQueue.updateActionStatus(action.id, 'failed', error.message);
          failed++;
          console.error('❌ Error syncing disconnector action:', action.id, error);
        }
      }

      return { success: true, message: `Synced ${synced} action(s)${failed > 0 ? `, ${failed} failed` : ''}`, synced, failed };
    } catch (error) {
      console.error('Error in syncDisconnectorActions:', error);
      return { success: false, message: error.message, synced: 0, failed: 0 };
    }
  },
};

export default {
  offlineQueue,
  disconnectorQueue,
  networkStatus,
  syncManager,
};


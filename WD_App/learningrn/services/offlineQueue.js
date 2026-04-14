import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { getApiConfig } from '../config/api';
import dbService from './dbService';
import * as readingsLocalService from './readingsLocalService';

const STORAGE_KEYS = {
  PENDING_READINGS: 'pending_readings_queue',
  PENDING_DISCONNECTOR_ACTIONS: 'pending_disconnector_actions',
  SYNC_STATUS: 'sync_status',
};

// Offline Queue Management
export const offlineQueue = {
  // Add reading to offline queue
  addReading: async (readingData) => {
    try {
      const queue = await offlineQueue.getQueue();
      if (queue === null) {
        console.error('Queue unavailable (corrupt or busy) - cannot add reading');
        return null;
      }
      const newReading = {
        id: Date.now().toString(),
        timestamp: new Date().toISOString(),
        data: readingData,
        status: 'pending', // pending, syncing, failed
        retryCount: 0,
      };
      queue.push(newReading);
      await AsyncStorage.setItem(STORAGE_KEYS.PENDING_READINGS, JSON.stringify(queue));
      console.log('✅ Reading added to offline queue:', newReading.id);
      return newReading.id;
    } catch (error) {
      console.error('Error adding reading to queue:', error);
      return null;
    }
  },

  // Get all pending readings returns null on parse/storage error to avoid overwriting queue
  getQueue: async () => {
    try {
      const raw = await AsyncStorage.getItem(STORAGE_KEYS.PENDING_READINGS);
      if (raw === null || raw === '') return [];
      return JSON.parse(raw);
    } catch (error) {
      console.error('Error getting queue:', error);
      return null; 
    }
  },

  // Remove reading from queue after successful sync
  removeReading: async (readingId) => {
    try {
      const queue = await offlineQueue.getQueue();
      if (queue === null || queue.length === 0) return false;
      const filtered = queue.filter(r => r.id !== readingId);
      if (filtered.length >= queue.length) return false; // id not found, do not write
      await AsyncStorage.setItem(STORAGE_KEYS.PENDING_READINGS, JSON.stringify(filtered));
      console.log('✅ Reading removed from queue:', readingId);
      return true;
    } catch (error) {
      console.error('Error removing reading from queue:', error);
      return false;
    }
  },

  // Update reading status
  updateReadingStatus: async (readingId, status, error = null) => {
    try {
      const queue = await offlineQueue.getQueue();
      if (queue === null) return false;
      const reading = queue.find(r => r.id === readingId);
      if (reading) {
        reading.status = status;
        reading.lastAttempt = new Date().toISOString();
        if (error) reading.error = error;
        if (status === 'failed') reading.retryCount = (reading.retryCount || 0) + 1;
        await AsyncStorage.setItem(STORAGE_KEYS.PENDING_READINGS, JSON.stringify(queue));
      }
      return true;
    } catch (error) {
      console.error('Error updating reading status:', error);
      return false;
    }
  },

  // Get pending count 
  getPendingCount: async () => {
    try {
      const queue = await offlineQueue.getQueue();
      if (queue === null) return 0;
      return queue.filter(r => r.status === 'pending' || r.status === 'failed' || r.status === 'syncing').length;
    } catch (error) {
      console.error('Error getting pending count:', error);
      return 0;
    }
  },

  // Clear entire queue
  clearQueue: async () => {
    try {
      await AsyncStorage.removeItem(STORAGE_KEYS.PENDING_READINGS);
      return true;
    } catch (error) {
      console.error('Error clearing queue:', error);
      return false;
    }
  },
};

export const disconnectorQueue = {
  addAction: async (actionData) => {
    try {
      const queue = await disconnectorQueue.getQueue();
      const newAction = {
        id: Date.now().toString(),
        timestamp: new Date().toISOString(),
        type: actionData.type, 
        status: 'pending',
        retryCount: 0,
        data: actionData.data, 
      };
      queue.push(newAction);
      await AsyncStorage.setItem(STORAGE_KEYS.PENDING_DISCONNECTOR_ACTIONS, JSON.stringify(queue));
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
      const queue = await disconnectorQueue.getQueue();
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
      const timeoutId = setTimeout(() => controller.abort(), 5000); 
      
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
        
        console.debug(
            "TEST B BDBDBDB",
            dbService.get(STORAGE_KEYS.PENDING_READINGS).then(queue => console.log('Current offline queue:', queue))
        )
        
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
    try {
      const processOneReading = async (reading) => {
        try {
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
          console.log('⚠️ Cannot sync - device offline or server unreachable');
          if (synced === 0 && failed === 0) {
            return { success: false, message: 'Device offline or server unreachable', synced: 0, failed: 0 };
          }
          break;
        }

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
          `🔄 Sync batch: ${batch.length} reading(s) (${SYNC_BATCH_SIZE} max per batch), ${pending.length} total pending/failed in queue`
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


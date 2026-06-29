import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  AppState,
  Linking,
  Modal,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { disconnectorAPI, routesAPI } from './services/api';
import { routesStorage, tokenStorage, userStorage, disconnectorPaidStorage, ROUTES_BUCKET_DISCONNECTOR } from './services/storage';
import { networkStatus, disconnectorQueue, syncManager } from './services/offlineQueue';
import { getApiConfig } from './config/api';

const normalize = (value) => (value || '').toString().toLowerCase();

const filterDisconnectionAssignments = (records = []) => {
  return records.filter((item) => {
    const assignmentType = normalize(item.assignment_type || item.type);
    const status = normalize(item.status || item.assignment_status || '');
    const rawStatus = item.status || item.assignment_status || '';

    // Only include records that are explicitly for disconnection
    if (assignmentType.includes('disconnect')) {
      return true;
    }

    // Include assignments with status 'X' (disconnected) or 'A' (reconnected/active)
    if (rawStatus === 'X' || rawStatus === 'A') {
      return true;
    }

    // Include assignments with any disconnection-related status text
    if (status) {
      return (
        status.includes('disconnect') ||
        status.includes('for disconnection') ||
        status.includes('reconnected')
      );
    }

    // Otherwise, exclude (these are likely normal Read and Bill routes)
    return false;
  });
};

const formatDate = (value) => {
  if (!value) return '—';
  try {
    return new Date(value).toLocaleDateString();
  } catch (error) {
    return value;
  }
};

const formatMoney = (value) => {
  if (value === null || value === undefined || value === '') return '—';
  const n = Number(value);
  if (Number.isNaN(n)) return '—';
  return n.toFixed(2);
};

const formatNumber = (value) => {
  if (value === null || value === undefined || value === '') return '—';
  const n = Number(value);
  if (Number.isNaN(n)) return '—';
  return String(n);
};

const parseMoney = (value) => {
  const n = Number(value);
  return Number.isNaN(n) ? 0 : n;
};

/** Map AR aging API row to assignment bucket fields (_30, _60, etc. each stay separate). */
const mapAgingRowToBuckets = (aging) => {
  const current = parseMoney(aging?.current ?? aging?.CURRENT);
  const b30 = parseMoney(aging?._30 ?? aging?.thirty_days ?? aging?.arrears_30);
  const b60 = parseMoney(aging?._60 ?? aging?.sixty_days ?? aging?.arrears_60);
  const b90 = parseMoney(aging?._90 ?? aging?.ninety_days ?? aging?.arrears_90);
  const over90 = parseMoney(
    aging?._OVER90 ?? aging?._over90 ?? aging?.over_90 ?? aging?.over90 ?? aging?.arrears_over_90
  );
  const prevYear = parseMoney(aging?.PREV_YEAR ?? aging?.prev_year ?? aging?.previous_year);
  const sumBuckets = current + b30 + b60 + b90 + over90 + prevYear;
  const balance = parseMoney(
    aging?.total_balance ?? aging?.balance ?? aging?.BALANCE ?? sumBuckets
  );

  return {
    current,
    CURRENT: current,
    _30: b30,
    _60: b60,
    _90: b90,
    _over90: over90,
    _OVER90: over90,
    prev_year: prevYear,
    PREV_YEAR: prevYear,
    total_balance: balance,
    BALANCE: balance,
  };
};

/** Full AR balance (sum of aging columns), same as AR Aging Summary report. */
const computeDisconnectorAgingBalance = (item) => {
  if (!item || typeof item !== 'object') return 0;
  const buckets = mapAgingRowToBuckets(item);
  return buckets.BALANCE;
};

const buildAssignmentIdentifier = (item = {}) => {
  const accountNo = (item.account_no || item.account_number || item.accountNumber || '').toString().trim();
  const zone = (item.zone_code || item.zone || '').toString().trim();
  if (accountNo) return `acct:${accountNo}|zone:${zone}`;
  return `id:${item.id || item.schedule_id || item.assignment_id || ''}|zone:${zone}`;
};

const normalizeAccountNo = (value) => {
  const raw = (value ?? '').toString().trim();
  if (!raw) return '';
  // Normalize account numbers across formats:
  // e.g. "091-12-1072", "091121072", "091 12 1072" -> "091121072"
  return raw.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
};
const getAccountMatchKeys = (value) => {
  const normalized = normalizeAccountNo(value);
  if (!normalized) return [];
  const noLeadingZero = normalized.replace(/^0+/, '');
  if (!noLeadingZero || noLeadingZero === normalized) return [normalized];
  return [normalized, noLeadingZero];
};
const normalizeScheduleId = (value) => (value ?? '').toString().trim();
const normalizeAccountName = (value) => (value ?? '')
  .toString()
  .toLowerCase()
  .trim()
  .replace(/[^a-z0-9\s]/g, '')
  .replace(/\s+/g, ' ');
const isCompletedDisconnectionStatus = (rawStatus) => {
  const status = normalize(rawStatus);
  return (
    rawStatus === 'X' ||
    rawStatus === 'A' ||
    (status.includes('disconnected') && !status.includes('reconnected')) ||
    status.includes('reconnected')
  );
};
const isActiveDisconnectionStatus = (rawStatus) => {
  const status = normalize(rawStatus);
  return (
    status === 'pending' ||
    status === 'assigned' ||
    status === 'in-progress' ||
    status === 'in progress'
  );
};

// Normalize account identifier for matching paid list
const getAssignmentAccountNo = (a) => {
  const no = (a.account_no || a.account_number || a.accountNumber || '').toString().trim();
  return no;
};

const getAssignmentOrderId = (a) => {
  const id = a?.id ?? a?.order_id ?? a?.orderId;
  if (id == null || id === '') return '';
  return String(id);
};

const isActiveDisconnectionAssignment = (assignment) => {
  const raw = (assignment?.status || assignment?.assignment_status || '').toString().trim();
  return isActiveDisconnectionStatus(raw);
};

const accountKeysForPaidMatch = (accountNo) => {
  const keys = new Set();
  const no = (accountNo || '').toString().trim();
  if (!no) return keys;
  keys.add(no);
  const norm = normalizeAccountNo(no);
  if (norm) keys.add(norm);
  getAccountMatchKeys(no).forEach((k) => keys.add(k));
  return keys;
};

/** Remove cancelled-paid rows for accounts with a new active assignment (new billing round). */
const filterPaidListForActiveReassignments = (paidList, activeAssignments) => {
  const activeAccountKeys = new Set();
  (activeAssignments || []).forEach((a) => {
    if (!isActiveDisconnectionAssignment(a)) return;
    accountKeysForPaidMatch(getAssignmentAccountNo(a)).forEach((k) => activeAccountKeys.add(k));
  });

  return (paidList || []).filter((cancelledRow) => {
    const keys = accountKeysForPaidMatch(getAssignmentAccountNo(cancelledRow));
    for (const k of keys) {
      if (activeAccountKeys.has(k)) return false;
    }
    return true;
  });
};

/** DB field is zone_code; legacy cached rows may only have zone */
const getZoneCode = (item) => (item?.zone_code ?? item?.zone ?? '').toString().trim();

const uniqueZoneCodesSorted = (list = []) => {
  const set = new Set();
  list.forEach((a) => {
    const z = getZoneCode(a);
    if (z) set.add(z);
  });
  return Array.from(set).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
};

export default function DisconnectorAssignments({ userData, onBack }) {
  const [assignments, setAssignments] = useState([]);
  const [cancelledDueToPayment, setCancelledDueToPayment] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedZone, setSelectedZone] = useState(null);
  const [showZoneDropdown, setShowZoneDropdown] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [selectedAssignmentId, setSelectedAssignmentId] = useState(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [pendingDisconnectorCount, setPendingDisconnectorCount] = useState(0);
  const [isOnline, setIsOnline] = useState(true);

  const zoneCodesForPicker = React.useMemo(
    () => uniqueZoneCodesSorted(assignments),
    [assignments]
  );

  const zoneFilteredAssignments = React.useMemo(() => {
    if (!selectedZone) return assignments;
    return assignments.filter((a) => getZoneCode(a) === selectedZone);
  }, [assignments, selectedZone]);

  const filteredAssignments = React.useMemo(() => {
    const q = (searchQuery || '').trim().toLowerCase();
    if (!q) return zoneFilteredAssignments;
    return zoneFilteredAssignments.filter((a) => {
      const name = (a.account_name || a.name || '').toString().toLowerCase();
      const accountNo = (a.account_number || a.accountNumber || a.account_no || '').toString().toLowerCase();
      return name.includes(q) || accountNo.includes(q);
    });
  }, [zoneFilteredAssignments, searchQuery]);

  const paidCancelledOrderIds = React.useMemo(() => {
    const set = new Set();
    (cancelledDueToPayment || []).forEach((c) => {
      const oid = getAssignmentOrderId(c);
      if (oid) set.add(oid);
    });
    return set;
  }, [cancelledDueToPayment]);

  const isAssignmentPaid = (assignment) => {
    if (isActiveDisconnectionAssignment(assignment)) {
      return false;
    }
    const orderId = getAssignmentOrderId(assignment);
    return orderId !== '' && paidCancelledOrderIds.has(orderId);
  };

  const loadAssignments = async (showAlerts = false, silent = false) => {
    if (!silent) setIsLoading(true);
    setErrorMessage('');

    try {
      const token = await tokenStorage.getToken();
      const storedUser = userData || (await userStorage.getUserData());
      const disconnectorId = storedUser?.id;

      if (!disconnectorId) {
        throw new Error('Unable to identify current disconnector.');
      }

      const online = await networkStatus.isOnline(true);
      setIsOnline(online);

      // When offline: show cached data only
      if (!online) {
        const cachedRoutes = await routesStorage.getRoutes(ROUTES_BUCKET_DISCONNECTOR);
        const cachedAssignments = filterDisconnectionAssignments(cachedRoutes || []);
        const cachedPaidRaw = await disconnectorPaidStorage.getPaidList();
        const cachedPaidFiltered = filterPaidListForActiveReassignments(
          Array.isArray(cachedPaidRaw) ? cachedPaidRaw : [],
          cachedAssignments
        );
        setAssignments(cachedAssignments);
        setCancelledDueToPayment(cachedPaidFiltered);
        setErrorMessage('Offline — showing cached list. Disconnect/reconnect will sync when back online.');
        if (showAlerts && cachedAssignments.length > 0) {
          Alert.alert('Offline', 'Showing cached assignments. Your actions will sync when you\'re back online.');
        }
        if (!silent) setIsLoading(false);
        return;
      }

      let assignmentsList = [];

      try {
        const response = await disconnectorAPI.getAssignments(
          { disconnector_id: disconnectorId },
          token
        );

        assignmentsList =
          response?.assignments ||
          response?.data ||
          response?.schedules ||
          response?.tasks ||
          [];

        // Fallback enrichment: some deployments return null last_reading in assignments
        // while orders payload has disconnection_orders.last_reading.
        try {
          const ordersResponse = await disconnectorAPI.getOrders(
            { disconnector_id: disconnectorId },
            token
          );
          const ordersList = ordersResponse?.orders || [];
          if (Array.isArray(ordersList) && ordersList.length > 0 && Array.isArray(assignmentsList)) {
            const orderByIdentifier = new Map();
            ordersList.forEach((orderItem) => {
              orderByIdentifier.set(buildAssignmentIdentifier(orderItem), orderItem);
            });

            assignmentsList = assignmentsList.map((assignmentItem) => {
              const orderMatch = orderByIdentifier.get(buildAssignmentIdentifier(assignmentItem));
              if (!orderMatch) return assignmentItem;

              const orderConsumer = orderMatch?.consumer;
              const orderLat = orderMatch?.latitude ?? orderConsumer?.latitude;
              const orderLng = orderMatch?.longitude ?? orderConsumer?.longitude;

              return {
                ...assignmentItem,
                latitude: assignmentItem?.latitude ?? orderLat ?? null,
                longitude: assignmentItem?.longitude ?? orderLng ?? null,
                // Always merge richer financial fields from orders when available.
                // Keep assignment values as first priority, then fall back to orders.
                last_reading:
                  assignmentItem?.last_reading ??
                  assignmentItem?.lastReading ??
                  assignmentItem?.previous_reading ??
                  assignmentItem?.current_reading ??
                  orderMatch?.last_reading ??
                  orderMatch?.lastReading ??
                  orderMatch?.previous_reading ??
                  orderMatch?.current_reading ??
                  null,
                meter_number: assignmentItem?.meter_number ?? orderMatch?.meter_number,
                this_month_arrears: assignmentItem?.this_month_arrears ?? orderMatch?.this_month_arrears,
                last_month_arrears: assignmentItem?.last_month_arrears ?? orderMatch?.last_month_arrears,
                total_outstanding: assignmentItem?.total_outstanding ?? orderMatch?.total_outstanding,
              };
            });
          }
        } catch (ordersError) {
          console.warn('Disconnector orders enrichment error:', ordersError);
        }

        // Enrich aging buckets from AR aging summary API (same source used by ar-aging-summary blade report).
        try {
          const agingResponse = await disconnectorAPI.getArAgingSummary(
            { disconnector_id: disconnectorId },
            token
          );
          const agingRows = Array.isArray(agingResponse?.data)
            ? agingResponse.data
            : Array.isArray(agingResponse?.detailRecords)
              ? agingResponse.detailRecords
              : [];

          if (agingRows.length > 0 && Array.isArray(assignmentsList) && assignmentsList.length > 0) {
            const agingByConsumerId = new Map();
            const agingByAccount = new Map();
            agingRows.forEach((row) => {
              const consumerZoneId = (row?.consumer_zone_id ?? row?.consumer_id ?? '').toString().trim();
              if (consumerZoneId) agingByConsumerId.set(consumerZoneId, row);

              const rawAccountNo = (
                row?.account_no ??
                row?.account_number ??
                row?.accountNumber ??
                ''
              ).toString().trim();
              if (!rawAccountNo) return;
              const normalized = normalizeAccountNo(rawAccountNo);
              const keys = new Set([
                rawAccountNo.toUpperCase(),
                normalized,
                ...getAccountMatchKeys(rawAccountNo),
              ]);
              keys.forEach((k) => {
                if (k) agingByAccount.set(k, row);
              });
            });

            assignmentsList = assignmentsList.map((assignmentItem) => {
              const assignmentConsumerId = (assignmentItem?.consumer_id ?? assignmentItem?.consumerId ?? '').toString().trim();
              let aging = assignmentConsumerId ? agingByConsumerId.get(assignmentConsumerId) : null;

              // Fallback only when consumer_id isn't present/matched.
              if (!aging) {
                const rawAccountNo = (
                  assignmentItem?.account_number ??
                  assignmentItem?.account_no ??
                  assignmentItem?.accountNumber ??
                  ''
                ).toString().trim();
                const normalizedAccountNo = normalizeAccountNo(rawAccountNo);
                const keys = [
                  rawAccountNo.toUpperCase(),
                  normalizedAccountNo,
                  ...getAccountMatchKeys(rawAccountNo),
                ].filter(Boolean);
                for (const k of keys) {
                  const v = agingByAccount.get(k);
                  if (v) {
                    aging = v;
                    break;
                  }
                }
              }

              if (!aging) return assignmentItem;

              return {
                ...assignmentItem,
                ...mapAgingRowToBuckets(aging),
              };
            });
          }
        } catch (agingError) {
          const msg = (agingError?.message || '').toLowerCase();
          if (!(msg.includes('could not be found') || msg.includes('not found') || msg.includes('404'))) {
            console.warn('Disconnector AR aging enrichment error:', agingError);
          }
        }
      } catch (apiError) {
        console.warn('Disconnector assignments API error:', apiError);
        setErrorMessage(
          'Using cached data. Server could not be reached or is busy—refresh when online for the latest list.'
        );
      }

      // Use API list as source-of-truth while online for fresh assignments.
      // Do not re-add stale cached completed rows that were not part of latest API response.
      const mergedAssignments = [...assignmentsList];

      // Ensure assignments are unique by schedule ID if available
      const uniqueAssignments = [];
      const seenIndex = new Map();
      mergedAssignments.forEach((item) => {
        const identifier = buildAssignmentIdentifier(item);
        if (!seenIndex.has(identifier)) {
          seenIndex.set(identifier, uniqueAssignments.length);
          uniqueAssignments.push(item);
          return;
        }
        // Merge duplicate entries to preserve richer fields (e.g., financial columns from API)
        const idx = seenIndex.get(identifier);
        const existing = uniqueAssignments[idx];
        const mergedLastReading =
          item.last_reading ??
          item.lastReading ??
          item.previous_reading ??
          item.previousReading ??
          item.current_reading ??
          item.currentReading ??
          item.reading ??
          existing.last_reading ??
          existing.lastReading ??
          existing.previous_reading ??
          existing.previousReading ??
          existing.current_reading ??
          existing.currentReading ??
          existing.reading ??
          null;

        uniqueAssignments[idx] = {
          ...existing,
          ...item,
          last_reading: mergedLastReading,
          this_month_arrears: item.this_month_arrears ?? existing.this_month_arrears,
          last_month_arrears: item.last_month_arrears ?? existing.last_month_arrears,
          total_outstanding: item.total_outstanding ?? existing.total_outstanding,
        };
      });

      // Prefer exact AR-aging buckets from API/backend assignment payload.
      // Do not approximate _30/_60/_90/_OVER90/PREV YEAR from disconnection-order fields.
      const assignmentsWithFallbackAging = uniqueAssignments.map((item) => {
        const hasExactAging =
          item?._30 != null ||
          item?._60 != null ||
          item?._90 != null ||
          item?._over90 != null ||
          item?._OVER90 != null ||
          item?.prev_year != null ||
          item?.PREV_YEAR != null;

        if (hasExactAging) {
          return {
            ...item,
            ...mapAgingRowToBuckets(item),
          };
        }

        const totalOutstanding = Number(item?.total_outstanding ?? item?.balance ?? 0);
        const safeBalance = Number.isNaN(totalOutstanding) ? 0 : totalOutstanding;

        return {
          ...item,
          current: item?.current ?? item?.CURRENT,
          CURRENT: item?.CURRENT ?? item?.current,
          total_balance: safeBalance,
          BALANCE: safeBalance,
        };
      });
      const latestAssignmentMs = (assignmentsWithFallbackAging || []).reduce((max, item) => {
        const rawTs = item?.assigned_at || item?.created_at || item?.updated_at || null;
        if (!rawTs) return max;
        const ms = new Date(rawTs).getTime();
        return Number.isFinite(ms) && ms > max ? ms : max;
      }, 0);

      // Fetch consumers who already paid (do not disconnect – show as "Paid not assign"); cache for offline
      let paidList = [];
      if (disconnectorId && token) {
        try {
          const paidParams = { disconnector_id: disconnectorId };
          if (latestAssignmentMs > 0) {
            paidParams.since = new Date(latestAssignmentMs).toISOString();
          }
          const res = await disconnectorAPI.getCancelledDueToPayment(
            paidParams,
            token
          );
          const list = res?.cancelled_due_to_payment || [];
          paidList = Array.isArray(list) ? list : [];
        } catch (e) {
          console.warn('DisconnectorAssignments cancelled-due-to-payment:', e);
          const cachedPaid = await disconnectorPaidStorage.getPaidList();
          paidList = Array.isArray(cachedPaid) ? cachedPaid : [];
        }
      }

      const activeAssignments = assignmentsWithFallbackAging.filter((item) => {
        const rawStatus = item.status || item.assignment_status || '';
        if (isCompletedDisconnectionStatus(rawStatus)) return false;
        return true;
      });

      const paidListForUi = filterPaidListForActiveReassignments(paidList, activeAssignments);
      setCancelledDueToPayment(paidListForUi);
      await disconnectorPaidStorage.savePaidList(paidListForUi);

      setAssignments(activeAssignments);
      // Persist filtered active list so offline view matches current assignable queue
      await routesStorage.saveRoutes(activeAssignments, ROUTES_BUCKET_DISCONNECTOR);

      if (showAlerts) {
        Alert.alert(
          'Assignments Updated',
          activeAssignments.length
            ? `${activeAssignments.length} assignment(s) loaded.`
            : 'No disconnection assignments found.'
        );
      }
    } catch (error) {
      console.error('DisconnectorAssignments loadAssignments error:', error);
      setErrorMessage(error.message);
      if (showAlerts) {
        Alert.alert('Error', error.message);
      }
    } finally {
      if (!silent) setIsLoading(false);
    }
  };

  useEffect(() => {
    let mounted = true;
    networkStatus.isOnline(true).then((online) => {
      if (mounted) setIsOnline(online);
    });
    loadAssignments();
    return () => { mounted = false; };
  }, []);

  useEffect(() => {
    const codes = new Set(zoneCodesForPicker);
    setSelectedZone((prev) => (prev && !codes.has(prev) ? null : prev));
  }, [zoneCodesForPicker]);

  // Auto-refresh: poll every 60s and when app comes to foreground
  useEffect(() => {
    const REFRESH_INTERVAL_MS = 10000; // 10 seconds
    let intervalId = null;

    const refreshSilent = () => {
      loadAssignments(false, true);
    };

    const handleAppStateChange = (nextState) => {
      if (nextState === 'active') {
        refreshSilent();
      }
    };

    intervalId = setInterval(refreshSilent, REFRESH_INTERVAL_MS);
    const sub = AppState.addEventListener('change', handleAppStateChange);

    return () => {
      if (intervalId) clearInterval(intervalId);
      sub?.remove?.();
    };
  }, []);

  // Load pending disconnector count on mount and when assignments change
  const refreshPendingDisconnectorCount = async () => {
    const count = await disconnectorQueue.getPendingCount();
    setPendingDisconnectorCount(count);
  };

  // Remove stale queued actions for the same order once online API succeeds.
  const clearQueuedActionsForOrder = async (orderId) => {
    if (!orderId) return;
    try {
      const queue = await disconnectorQueue.getQueue();
      const normalizedTarget = String(orderId).trim();
      const matches = queue.filter((item) => {
        const queuedOrderId = item?.data?.payload?.order_id ?? item?.data?.payload?.assignment_id ?? null;
        return queuedOrderId != null && String(queuedOrderId).trim() === normalizedTarget;
      });
      for (const item of matches) {
        await disconnectorQueue.removeAction(item.id);
      }
    } catch (err) {
      console.warn('clearQueuedActionsForOrder:', err?.message || err);
    }
  };

  useEffect(() => {
    const initQueueCount = async () => {
      await disconnectorQueue.compactQueue();
      await refreshPendingDisconnectorCount();
    };
    initQueueCount();
  }, []);

  // Sync one disconnector action (used by syncManager.syncDisconnectorActions)
  const syncOneDisconnectorAction = async (action) => {
    try {
      const token = await tokenStorage.getToken();
      if (!token) return { success: false, message: 'No token' };
      const { payload, accountNumber, accountName, statusCode } = action.data || {};
      if (!payload) return { success: false, message: 'Invalid action data' };
      const res = await disconnectorAPI.updateAssignmentStatus(payload, token);
      if (!res || (res.success === false && res.success !== undefined)) {
        const msg = (res?.message || 'API failed').toString();
        if (msg.toLowerCase().includes('already paid')) {
          return { success: true, skipped_paid: true, message: msg };
        }
        return { success: false, message: msg };
      }
      // Consumer status is updated by backend assignment status endpoint.
      return { success: true };
    } catch (error) {
      const msg = (error?.message || '').toString();
      if (msg.toLowerCase().includes('already paid')) {
        return { success: true, skipped_paid: true, message: msg };
      }
      return { success: false, message: error.message };
    }
  };

  // Subscribe to network: update isOnline, refresh pending count, auto-sync when back online
  useEffect(() => {
    const unsub = networkStatus.subscribe(async (online) => {
      setIsOnline(online);
      const count = await disconnectorQueue.getPendingCount();
      setPendingDisconnectorCount(count);
      if (online && count > 0) {
        console.log('📡 Network restored - syncing', count, 'disconnector action(s)...');
        setTimeout(async () => {
          const result = await syncManager.syncDisconnectorActions(syncOneDisconnectorAction);
          setPendingDisconnectorCount(await disconnectorQueue.getPendingCount());
          if (result.synced > 0) {
            loadAssignments(false, true);
          }
        }, 2000);
      }
    });
    return () => unsub?.();
  }, []);

  const handleRefresh = () => loadAssignments(true);

  const handleDownload = async () => {
    try {
      const token = await tokenStorage.getToken();
      const storedUser = userData || (await userStorage.getUserData());

      if (!storedUser?.id) {
        Alert.alert('Missing User', 'Please re-login to continue.');
        return;
      }

      const response = await routesAPI.getRoutes(
        { reader_id: storedUser.id, assignment_type: 'disconnection' },
        token
      );

      const list =
        response?.assignments ||
        response?.schedules ||
        response?.data ||
        response?.routes ||
        [];

      const filtered = filterDisconnectionAssignments(list);
      await routesStorage.saveRoutes(filtered, ROUTES_BUCKET_DISCONNECTOR);
      setAssignments(filtered);

      Alert.alert(
        'Download Complete',
        filtered.length
          ? `${filtered.length} disconnection assignment(s) downloaded.`
          : 'No disconnection assignments found for download.'
      );
    } catch (error) {
      console.error('Download disconnection assignments error:', error);
      Alert.alert(
        'Download Failed',
        error.message || 'Unable to download assignments. Please try again later.'
      );
    }
  };

  const handleClearAllAssignments = () => {
    Alert.alert(
      'Clear All Assignments',
      'This will remove all currently assigned consumers from this device.\n\nUse this before assigning a new consumer list for disconnection.\n\nAre you sure?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Clear All',
          style: 'destructive',
          onPress: async () => {
            try {
              const online = await networkStatus.isOnline(true);
              const token = await tokenStorage.getToken();
              const storedUser = userData || (await userStorage.getUserData());
              const disconnectorId = storedUser?.id;

              if (!online || !token || !disconnectorId) {
                Alert.alert(
                  'Online Required',
                  'Clear All permanently removes assignments from server. Connect online and try again.'
                );
                return;
              }

              const serverRes = await disconnectorAPI.clearAllAssignments(
                { disconnector_id: disconnectorId },
                token
              );
              if (!serverRes || serverRes.success === false) {
                throw new Error(serverRes?.message || 'Failed to clear assignments on server.');
              }

              await routesStorage.saveRoutes([], ROUTES_BUCKET_DISCONNECTOR);
              await disconnectorPaidStorage.savePaidList([]);
              await disconnectorQueue.clearQueue();
              setAssignments([]);
              setCancelledDueToPayment([]);
              setSelectedAssignmentId(null);
              setSearchQuery('');
              setSelectedZone(null);
              setShowZoneDropdown(false);
              setErrorMessage('');
              await loadAssignments(false, true);
              Alert.alert(
                'Cleared',
                `Assignments were permanently cleared from server (${serverRes?.cleared_count ?? 0}) and removed from this device.`
              );
            } catch (error) {
              Alert.alert('Error', error?.message || 'Failed to clear assignments.');
            }
          },
        },
      ]
    );
  };

  const handleCardPress = (uniqueKey) => {
    // Toggle selection - if already selected, deselect it
    setSelectedAssignmentId(selectedAssignmentId === uniqueKey ? null : uniqueKey);
  };

  const handleViewLocation = async (assignment) => {
    const actualAssignment = assignments.find(a =>
      (assignment.id && a.id === assignment.id) ||
      (assignment.schedule_id && a.schedule_id === assignment.schedule_id) ||
      a === assignment
    ) || assignment;
    const accountNumber = actualAssignment.account_number || actualAssignment.accountNumber ||
      actualAssignment.account_no || actualAssignment.accountNo || '—';
    const name = actualAssignment.account_name || actualAssignment.name || 'Consumer';

    let lat = null;
    let lng = null;
    if (actualAssignment.latitude != null && actualAssignment.longitude != null) {
      lat = parseFloat(actualAssignment.latitude);
      lng = parseFloat(actualAssignment.longitude);
      if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) lat = lng = null;
    }
    if (lat == null || lng == null) {
      try {
        const token = await tokenStorage.getToken();
        if (token && accountNumber && accountNumber !== '—' && accountNumber !== 'N/A') {
          const consumerZone = await routesAPI.getConsumerZoneByAccount(accountNumber, token);
          if (consumerZone?.latitude != null && consumerZone?.longitude != null) {
            lat = parseFloat(consumerZone.latitude);
            lng = parseFloat(consumerZone.longitude);
            if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
              // use lat, lng
            } else lat = lng = null;
          }
        }
      } catch (e) {
        console.warn('View location getConsumerZoneByAccount:', e);
      }
    }
    if (lat == null || lng == null) {
      Alert.alert(
        'Location Not Available',
        `GPS coordinates not found for account: ${accountNumber || 'N/A'}\n\nCustomer: ${name}\n\nSet the consumer location in the admin consumer page (Location tab), then refresh assignments.`,
        [{ text: 'OK' }]
      );
      return;
    }
    // Try opening map: use web URL first (works in browser and often opens Google Maps app on Android)
    // Don't use canOpenURL – on Android it often returns false for geo/google.navigation even when Maps is installed
    const urlsToTry = [
      `https://www.google.com/maps?q=${lat},${lng}&z=17`,
      `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`,
      `geo:${lat},${lng}?q=${lat},${lng}`,
      `google.navigation:q=${lat},${lng}&mode=d`,
    ];
    let opened = false;
    for (const url of urlsToTry) {
      try {
        await Linking.openURL(url);
        opened = true;
        break;
      } catch (err) {
        console.warn('Linking.openURL failed:', url, err.message);
      }
    }
    if (!opened) {
      Alert.alert('Open Map', 'Could not open map. Try opening Google Maps manually or use a browser.', [{ text: 'OK' }]);
    }
  };

  const handleDisconnect = async (assignment) => {
    // Debug: Log assignment object to see available fields
    console.log('Assignment object in handleDisconnect:', JSON.stringify(assignment, null, 2));
    console.log('All assignment fields:', Object.keys(assignment));
    
    // Find the actual assignment from state to ensure we have all fields
    const actualAssignment = assignments.find(a => 
      (assignment.id && a.id === assignment.id) ||
      (assignment.schedule_id && a.schedule_id === assignment.schedule_id) ||
      a === assignment
    ) || assignment;
    
    // Extract account number - try multiple field variations
    const accountNumber = actualAssignment.account_number || 
                         actualAssignment.accountNumber || 
                         actualAssignment.account_no ||
                         actualAssignment.accountNo ||
                         actualAssignment.acct_number ||
                         actualAssignment.acctNumber ||
                         (actualAssignment.customer && (actualAssignment.customer.account_number || actualAssignment.customer.accountNumber)) ||
                         'N/A';
    
    // Extract account name - try multiple field variations
    const accountName = actualAssignment.account_name || 
                       actualAssignment.name || 
                       actualAssignment.accountName ||
                       (actualAssignment.customer && (actualAssignment.customer.account_name || actualAssignment.customer.name)) ||
                       'Unknown Account';
    
    // Ensure we have valid values (not undefined or null)
    const displayAccountNumber = (accountNumber && accountNumber !== 'undefined' && accountNumber !== 'null' && accountNumber !== 'N/A') 
      ? accountNumber 
      : (actualAssignment.account_number || actualAssignment.accountNumber || 'N/A');
    const displayAccountName = (accountName && accountName !== 'undefined' && accountName !== 'null') 
      ? accountName 
      : (actualAssignment.account_name || actualAssignment.name || 'Unknown Account');
    
    console.log('Extracted account number:', displayAccountNumber);
    console.log('Extracted account name:', displayAccountName);
    
    Alert.alert(
      'Confirm Disconnection',
      `Are you sure you want to mark this account as disconnected?\n\nAccount: ${displayAccountName}\nAccount No.: ${displayAccountNumber}`,
      [
        {
          text: 'Cancel',
          style: 'cancel',
        },
        {
          text: 'Disconnect',
          style: 'destructive',
          onPress: async () => {
            setIsProcessing(true);
            try {
              const token = await tokenStorage.getToken();
              const storedUser = userData || (await userStorage.getUserData());

              // Check if device is online
              const isOnlineNow = await networkStatus.isOnline(true);
              console.log('Network status check:', isOnlineNow ? '🌐 Online' : '📴 Offline');

              let apiSuccess = false;
              let skippedBecausePaid = false;
              let queuedForRetry = false;
              let apiError = null;

              // Build payload once for both API and offline queue
              const assignmentId = assignment.id || assignment.schedule_id || assignment.assignment_id;
              const orderId = actualAssignment.order_id || actualAssignment.orderId || assignment.order_id ||
                assignment.orderId || actualAssignment.order_number || actualAssignment.orderNumber ||
                assignment.order_number || assignment.orderNumber || actualAssignment.id || assignmentId;
              const accountNumberForPayload = actualAssignment.account_number || actualAssignment.accountNumber ||
                actualAssignment.account_no || actualAssignment.accountNo || displayAccountNumber;
              const payload = assignmentId ? {
                order_id: orderId,
                assignment_id: assignmentId,
                schedule_id: assignment.schedule_id || assignmentId,
                status: 'disconnected',
                disconnection_date: new Date().toISOString().split('T')[0],
                disconnector_id: storedUser?.id,
                account_number: accountNumberForPayload,
                status_code: 'X',
              } : null;

              // Offline: save to queue and update local state
              if (!isOnlineNow || !token) {
                if (payload) {
                  await disconnectorQueue.addAction({ type: 'disconnect', data: { payload, accountNumber: displayAccountNumber, accountName: displayAccountName, statusCode: 'X' } });
                  setPendingDisconnectorCount(await disconnectorQueue.getPendingCount());
                }
                const statusToSet = 'disconnected';
                const assignmentStatusToSet = 'disconnected';
                const updatedAssignments = assignments.map((a) => {
                  if (assignment.id && a.id === assignment.id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, disconnection_date: new Date().toISOString().split('T')[0] };
                  if (assignment.schedule_id && a.schedule_id === assignment.schedule_id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, disconnection_date: new Date().toISOString().split('T')[0] };
                  if (assignment.assignment_id && a.assignment_id === assignment.assignment_id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, disconnection_date: new Date().toISOString().split('T')[0] };
                  const aActual = a.assignments?.[0] || a;
                  if (assignment.id && aActual.id === assignment.id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, disconnection_date: new Date().toISOString().split('T')[0] };
                  if (assignment.schedule_id && aActual.schedule_id === assignment.schedule_id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, disconnection_date: new Date().toISOString().split('T')[0] };
                  return a;
                });
                setAssignments(updatedAssignments);
                await routesStorage.saveRoutes(updatedAssignments, ROUTES_BUCKET_DISCONNECTOR);
                setIsProcessing(false);
                Alert.alert('Saved offline', 'Will sync when you\'re back online.');
                return;
              }

              // Try to update via API - we're online and have token
              if (payload) {
                try {
                  console.log('Attempting to update disconnection status via API...', { assignmentId, schedule_id: assignment.schedule_id, disconnector_id: storedUser?.id });
                  console.log('API Payload:', JSON.stringify(payload, null, 2));
                  const response = await disconnectorAPI.updateAssignmentStatus(payload, token);
                    
                    console.log('API Response:', JSON.stringify(response, null, 2));
                    
                    // Check if response indicates success - handle various response formats
                    if (response) {
                      // Check for explicit success indicators
                      if (response.success === true || response.success === 1 || 
                          response.status === 'success' || response.status === 'ok' ||
                          (response.message && (response.message.includes('success') || response.message.includes('updated')))) {
                        apiSuccess = true;
                        console.log('✅ Disconnection updated in main system:', response);
                        
                        // Consumer status is updated by backend assignment status endpoint.
                      } 
                      // Check for explicit failure indicators
                      else if (response.success === false || response.success === 0 ||
                               response.status === 'error' || response.status === 'failed') {
                        console.warn('⚠️ API returned unsuccessful response:', response);
                        // Extract error message from various possible response formats
                        apiError = response?.message || 
                                  response?.error || 
                                  response?.errors?.message ||
                                  response?.errors ||
                                  (typeof response === 'string' ? response : 'API returned unsuccessful response');
                      }
                      // If no explicit success/failure, assume success if we got a response
                      else {
                        apiSuccess = true;
                        console.log('✅ Disconnection updated in main system (assuming success):', response);
                      }
                    } else {
                      console.warn('⚠️ API returned empty/null response');
                      apiError = 'API returned empty response';
                    }
                } catch (error) {
                  if (error.message) apiError = error.message;
                  else if (error.response) apiError = error.response.message || error.response.error || 'API request failed';
                  else if (typeof error === 'string') apiError = error;
                  else apiError = 'API request failed. Please check your connection and try again.';

                  if ((apiError || '').toLowerCase().includes('already paid')) {
                    skippedBecausePaid = true;
                    console.log('ℹ️ Skip disconnect: consumer already paid.');
                  } else {
                    console.error('❌ API update failed:', error);
                    const lowerErr = (apiError || '').toLowerCase();
                    const isNetworkFailure =
                      lowerErr.includes('network') ||
                      lowerErr.includes('fetch') ||
                      lowerErr.includes('timeout') ||
                      lowerErr.includes('connection');
                    if (payload && isNetworkFailure) {
                      await disconnectorQueue.addAction({ type: 'disconnect', data: { payload, accountNumber: displayAccountNumber, accountName: displayAccountName, statusCode: 'X' } });
                      setPendingDisconnectorCount(await disconnectorQueue.getPendingCount());
                      queuedForRetry = true;
                    }
                  }
                }
              }
              if (!apiSuccess && !skippedBecausePaid && !queuedForRetry && payload) {
                const lowerErr = (apiError || '').toLowerCase();
                const isNetworkFailure =
                  lowerErr.includes('network') ||
                  lowerErr.includes('fetch') ||
                  lowerErr.includes('timeout') ||
                  lowerErr.includes('connection');
                if (isNetworkFailure) {
                await disconnectorQueue.addAction({ type: 'disconnect', data: { payload, accountNumber: displayAccountNumber, accountName: displayAccountName, statusCode: 'X' } });
                setPendingDisconnectorCount(await disconnectorQueue.getPendingCount());
                  queuedForRetry = true;
                }
              }

              if (skippedBecausePaid) {
                await loadAssignments(false, true);
                setSelectedAssignmentId(null);
                await clearQueuedActionsForOrder(orderId);
                await refreshPendingDisconnectorCount();
                Alert.alert('Already paid', 'This consumer has already paid and cannot be marked as disconnected.');
                return;
              }

              // Update local state - ALWAYS set to 'disconnected' (never 'saved offline')
              // The status should reflect the actual disconnection
              // Note: We display 'X' in UI but store 'disconnected' to match backend expectations
              const statusToSet = 'disconnected'; // Backend expects 'disconnected' for disconnection_orders
              const assignmentStatusToSet = 'disconnected';
              
              console.log('Updating assignment status to:', statusToSet, 'API success:', apiSuccess);
              
              // Match by exact assignment using the most specific identifier available
              const updatedAssignments = assignments.map((a, idx) => {
                // First try to match by ID (most specific)
                if (assignment.id && (a.id === assignment.id)) {
                  const updated = { 
                    ...a, 
                    status: statusToSet, 
                    assignment_status: assignmentStatusToSet,
                    disconnection_date: new Date().toISOString().split('T')[0],
                  };
                  console.log('Matched by ID, updating assignment:', updated);
                  return updated;
                }
                // Then try schedule_id
                if (assignment.schedule_id && (a.schedule_id === assignment.schedule_id)) {
                  const updated = { 
                    ...a, 
                    status: statusToSet, 
                    assignment_status: assignmentStatusToSet,
                    disconnection_date: new Date().toISOString().split('T')[0],
                  };
                  console.log('Matched by schedule_id, updating assignment:', updated);
                  return updated;
                }
                // Finally, match by object reference (same index in array) as last resort
                // This ensures we only update the exact assignment that was clicked
                if (a === assignment) {
                  const updated = { 
                    ...a, 
                    status: statusToSet, 
                    assignment_status: assignmentStatusToSet,
                    disconnection_date: new Date().toISOString().split('T')[0],
                  };
                  console.log('Matched by object reference, updating assignment:', updated);
                  return updated;
                }
                return a;
              });
              setAssignments(updatedAssignments);
              
              // Update local storage
              await routesStorage.saveRoutes(updatedAssignments, ROUTES_BUCKET_DISCONNECTOR);
              
              setSelectedAssignmentId(null);
              
              // Show appropriate message based on API success
              if (apiSuccess) {
                await clearQueuedActionsForOrder(orderId);
                await refreshPendingDisconnectorCount();
                Alert.alert('Success', 'Account marked as disconnected and updated in main system.');
              } else if (queuedForRetry) {
                await refreshPendingDisconnectorCount();
                Alert.alert('Saved offline', 'Will sync when you\'re back online.');
              } else {
                await refreshPendingDisconnectorCount();
                const errorMsg = apiError || 'Unknown error';
                Alert.alert('Warning', `Could not update main system.\n\nError: ${errorMsg}\n\nStatus updated locally. Please check your connection and try refreshing.`);
              }
            } catch (error) {
              console.error('Disconnect error:', error);
              Alert.alert('Error', 'Failed to update assignment status. Please try again.');
            } finally {
              setIsProcessing(false);
            }
          },
        },
      ]
    );
  };

  const handleReconnect = async (assignment) => {
    // Debug: Log assignment object to see available fields
    console.log('Assignment object in handleReconnect:', JSON.stringify(assignment, null, 2));
    console.log('All assignment fields:', Object.keys(assignment));
    
    // Find the actual assignment from state to ensure we have all fields
    const actualAssignment = assignments.find(a => 
      (assignment.id && a.id === assignment.id) ||
      (assignment.schedule_id && a.schedule_id === assignment.schedule_id) ||
      a === assignment
    ) || assignment;
    
    // Extract account number - try multiple field variations
    const accountNumber = actualAssignment.account_number || 
                         actualAssignment.accountNumber || 
                         actualAssignment.account_no ||
                         actualAssignment.accountNo ||
                         actualAssignment.acct_number ||
                         actualAssignment.acctNumber ||
                         (actualAssignment.customer && (actualAssignment.customer.account_number || actualAssignment.customer.accountNumber)) ||
                         'N/A';
    
    // Extract account name - try multiple field variations
    const accountName = actualAssignment.account_name || 
                       actualAssignment.name || 
                       actualAssignment.accountName ||
                       (actualAssignment.customer && (actualAssignment.customer.account_name || actualAssignment.customer.name)) ||
                       'Unknown Account';
    
    // Ensure we have valid values (not undefined or null)
    const displayAccountNumber = (accountNumber && accountNumber !== 'undefined' && accountNumber !== 'null' && accountNumber !== 'N/A') 
      ? accountNumber 
      : (actualAssignment.account_number || actualAssignment.accountNumber || 'N/A');
    const displayAccountName = (accountName && accountName !== 'undefined' && accountName !== 'null') 
      ? accountName 
      : (actualAssignment.account_name || actualAssignment.name || 'Unknown Account');
    
    console.log('Extracted account number:', displayAccountNumber);
    console.log('Extracted account name:', displayAccountName);
    
    Alert.alert(
      'Confirm Reconnection',
      `Are you sure you want to mark this account as reconnected?\n\nAccount: ${displayAccountName}\nAccount No.: ${displayAccountNumber}`,
      [
        {
          text: 'Cancel',
          style: 'cancel',
        },
        {
          text: 'Reconnect',
          onPress: async () => {
            setIsProcessing(true);
            try {
              const token = await tokenStorage.getToken();
              const storedUser = userData || (await userStorage.getUserData());

              // Check if device is online
              const isOnlineNowReconnect = await networkStatus.isOnline(true);
              console.log('Network status check:', isOnlineNowReconnect ? '🌐 Online' : '📴 Offline');

              let apiSuccess = false;
              let apiError = null;

              const assignmentIdReconnect = assignment.id || assignment.schedule_id || assignment.assignment_id;
              const orderIdReconnect = actualAssignment.order_id || actualAssignment.orderId || assignment.order_id ||
                assignment.orderId || actualAssignment.order_number || actualAssignment.orderNumber ||
                assignment.order_number || assignment.orderNumber || actualAssignment.id || assignmentIdReconnect;
              const accountNumberForReconnect = actualAssignment.account_number || actualAssignment.accountNumber ||
                actualAssignment.account_no || actualAssignment.accountNo || displayAccountNumber;
              const payloadReconnect = assignmentIdReconnect ? {
                order_id: orderIdReconnect,
                assignment_id: assignmentIdReconnect,
                schedule_id: assignment.schedule_id || assignmentIdReconnect,
                status: 'reconnected',
                reconnection_date: new Date().toISOString().split('T')[0],
                disconnector_id: storedUser?.id,
                account_number: accountNumberForReconnect,
                status_code: 'A',
              } : null;

              if (!isOnlineNowReconnect || !token) {
                if (payloadReconnect) {
                  await disconnectorQueue.addAction({ type: 'reconnect', data: { payload: payloadReconnect, accountNumber: displayAccountNumber, accountName: displayAccountName, statusCode: 'A' } });
                  setPendingDisconnectorCount(await disconnectorQueue.getPendingCount());
                }
                const statusToSet = 'reconnected';
                const assignmentStatusToSet = 'reconnected';
                const updatedAssignmentsReconnect = assignments.map((a) => {
                  if (assignment.id && a.id === assignment.id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, reconnection_date: new Date().toISOString().split('T')[0] };
                  if (assignment.schedule_id && a.schedule_id === assignment.schedule_id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, reconnection_date: new Date().toISOString().split('T')[0] };
                  if (assignment.assignment_id && a.assignment_id === assignment.assignment_id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, reconnection_date: new Date().toISOString().split('T')[0] };
                  const aActual = a.assignments?.[0] || a;
                  if (assignment.id && aActual.id === assignment.id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, reconnection_date: new Date().toISOString().split('T')[0] };
                  if (assignment.schedule_id && aActual.schedule_id === assignment.schedule_id) return { ...a, status: statusToSet, assignment_status: assignmentStatusToSet, reconnection_date: new Date().toISOString().split('T')[0] };
                  return a;
                });
                setAssignments(updatedAssignmentsReconnect);
                await routesStorage.saveRoutes(updatedAssignmentsReconnect, ROUTES_BUCKET_DISCONNECTOR);
                setIsProcessing(false);
                Alert.alert('Saved offline', 'Will sync when you\'re back online.');
                return;
              }

              if (payloadReconnect) {
                try {
                  console.log('Attempting to update reconnection status via API...', { assignmentId: assignmentIdReconnect, schedule_id: assignment.schedule_id, disconnector_id: storedUser?.id });
                  console.log('API Payload:', JSON.stringify(payloadReconnect, null, 2));
                  const response = await disconnectorAPI.updateAssignmentStatus(payloadReconnect, token);
                    
                    console.log('API Response:', JSON.stringify(response, null, 2));
                    
                    // Check if response indicates success - handle various response formats
                    if (response) {
                      // Check for explicit success indicators
                      if (response.success === true || response.success === 1 || 
                          response.status === 'success' || response.status === 'ok' ||
                          (response.message && (response.message.includes('success') || response.message.includes('updated')))) {
                        apiSuccess = true;
                        console.log('✅ Reconnection updated in main system:', response);
                        
                        // Consumer status is updated by backend assignment status endpoint.
                      } 
                      // Check for explicit failure indicators
                      else if (response.success === false || response.success === 0 ||
                               response.status === 'error' || response.status === 'failed') {
                        console.warn('⚠️ API returned unsuccessful response:', response);
                        // Extract error message from various possible response formats
                        apiError = response?.message || 
                                  response?.error || 
                                  response?.errors?.message ||
                                  response?.errors ||
                                  (typeof response === 'string' ? response : 'API returned unsuccessful response');
                      }
                      // If no explicit success/failure, assume success if we got a response
                      else {
                        apiSuccess = true;
                        console.log('✅ Reconnection updated in main system (assuming success):', response);
                      }
                    } else {
                      console.warn('⚠️ API returned empty/null response');
                      apiError = 'API returned empty response';
                    }
                } catch (error) {
                  console.error('❌ API update failed:', error);
                  if (payloadReconnect) {
                    await disconnectorQueue.addAction({ type: 'reconnect', data: { payload: payloadReconnect, accountNumber: displayAccountNumber, accountName: displayAccountName, statusCode: 'A' } });
                    setPendingDisconnectorCount(await disconnectorQueue.getPendingCount());
                  }
                  if (error.message) apiError = error.message;
                  else if (error.response) apiError = error.response.message || error.response.error || 'API request failed';
                  else if (typeof error === 'string') apiError = error;
                  else apiError = 'API request failed. Please check your connection and try again.';
                }
              }
              if (!apiSuccess && payloadReconnect) {
                await disconnectorQueue.addAction({ type: 'reconnect', data: { payload: payloadReconnect, accountNumber: displayAccountNumber, accountName: displayAccountName, statusCode: 'A' } });
                setPendingDisconnectorCount(await disconnectorQueue.getPendingCount());
              }

              // Update local state - ALWAYS set to 'reconnected'
              // The status should reflect the actual reconnection
              // Note: We display 'A' in UI but store 'reconnected' to match backend expectations
              const statusToSet = 'reconnected'; // Backend expects 'reconnected' for disconnection_orders
              const assignmentStatusToSet = 'reconnected';
              
              console.log('Updating assignment status to:', statusToSet, 'API success:', apiSuccess);
              
              // Match by exact assignment using the most specific identifier available
              const updatedAssignments = assignments.map((a, idx) => {
                // First try to match by ID (most specific)
                if (assignment.id && (a.id === assignment.id)) {
                  const updated = { 
                    ...a, 
                    status: statusToSet, 
                    assignment_status: assignmentStatusToSet,
                    reconnection_date: new Date().toISOString().split('T')[0],
                  };
                  console.log('Matched by ID, updating assignment:', updated);
                  return updated;
                }
                // Then try schedule_id
                if (assignment.schedule_id && (a.schedule_id === assignment.schedule_id)) {
                  const updated = { 
                    ...a, 
                    status: statusToSet, 
                    assignment_status: assignmentStatusToSet,
                    reconnection_date: new Date().toISOString().split('T')[0],
                  };
                  console.log('Matched by schedule_id, updating assignment:', updated);
                  return updated;
                }
                // Finally, match by object reference (same index in array) as last resort
                // This ensures we only update the exact assignment that was clicked
                if (a === assignment) {
                  const updated = { 
                    ...a, 
                    status: statusToSet, 
                    assignment_status: assignmentStatusToSet,
                    reconnection_date: new Date().toISOString().split('T')[0],
                  };
                  console.log('Matched by object reference, updating assignment:', updated);
                  return updated;
                }
                return a;
              });
              setAssignments(updatedAssignments);
              
              // Update local storage
              await routesStorage.saveRoutes(updatedAssignments, ROUTES_BUCKET_DISCONNECTOR);
              
              setSelectedAssignmentId(null);
              
              // Show appropriate message based on API success
              if (apiSuccess) {
                await clearQueuedActionsForOrder(orderIdReconnect);
                await refreshPendingDisconnectorCount();
                Alert.alert('Success', 'Account marked as reconnected and updated in main system.');
              } else if (payloadReconnect) {
                await refreshPendingDisconnectorCount();
                Alert.alert('Saved offline', 'Will sync when you\'re back online.');
              } else {
                await refreshPendingDisconnectorCount();
                const errorMsg = apiError || 'Unknown error';
                Alert.alert('Warning', `Could not update main system.\n\nError: ${errorMsg}\n\nStatus updated locally. Please check your connection and try refreshing.`);
              }
            } catch (error) {
              console.error('Reconnect error:', error);
              Alert.alert('Error', 'Failed to update assignment status. Please try again.');
            } finally {
              setIsProcessing(false);
            }
          },
        },
      ]
    );
  };

  const handleCancel = () => {
    setSelectedAssignmentId(null);
  };

  // Function to retry syncing failed disconnections
  const handleRetryFailedSync = async () => {
    try {
      const token = await tokenStorage.getToken();
      const storedUser = userData || (await userStorage.getUserData());
      const isOnline = await networkStatus.isOnline(true);

      if (!isOnline) {
        Alert.alert('Offline', 'Cannot sync while offline. Please connect to internet first.');
        return;
      }

      if (!token) {
        Alert.alert('Error', 'No authentication token. Please login again.');
        return;
      }

      // First flush the offline queue (disconnect/reconnect actions saved while offline)
      const queueResult = await syncManager.syncDisconnectorActions(syncOneDisconnectorAction);
      await refreshPendingDisconnectorCount();
      if (queueResult.synced > 0) {
        loadAssignments(false, true);
      }

      // Find all disconnected assignments that might need syncing (legacy retry)
      const disconnectedAssignments = assignments.filter(a => {
        const status = normalize(a.status || a.assignment_status || '');
        return status.includes('disconnected') && !status.includes('reconnected');
      });

      if (disconnectedAssignments.length === 0) {
        if (queueResult.synced > 0) {
          Alert.alert('Synced', `${queueResult.synced} offline action(s) synced to server.`);
        } else {
          Alert.alert('Info', 'No disconnected assignments or offline actions to sync.');
        }
        return;
      }

      Alert.alert(
        'Sync Disconnections',
        `Found ${disconnectedAssignments.length} disconnected assignment(s). Retry syncing to server?`,
        [
          { text: 'Cancel', style: 'cancel' },
          {
            text: 'Sync Now',
            onPress: async () => {
              setIsProcessing(true);
              let synced = 0;
              let failed = 0;

              for (const assignment of disconnectedAssignments) {
                try {
                  const assignmentId = assignment.id || assignment.schedule_id || assignment.assignment_id;
                  if (!assignmentId) continue;

                  const orderId = assignment.order_id || assignment.orderId || assignment.id || assignmentId;
                  const accountNumber = assignment.account_number || assignment.accountNumber || assignment.account_no || assignment.accountNo;
                  const accountName = assignment.account_name || assignment.name || 'Unknown';

                  const payload = {
                    order_id: orderId,
                    assignment_id: assignmentId,
                    schedule_id: assignment.schedule_id || assignmentId,
                    status: 'disconnected',
                    disconnection_date: assignment.disconnection_date || new Date().toISOString().split('T')[0],
                    disconnector_id: storedUser?.id,
                    account_number: accountNumber,
                    status_code: 'X',
                  };

                  console.log('Retrying sync for assignment:', assignmentId, payload);

                  const response = await disconnectorAPI.updateAssignmentStatus(payload, token);
                  
                  if (response && (response.success !== false && response.success !== 0)) {
                    synced++;
                    console.log('✅ Successfully synced assignment:', assignmentId);
                    
                    // Consumer status is updated by backend assignment status endpoint.
                  } else {
                    const msg = (response?.message || '').toString().toLowerCase();
                    if (msg.includes('already paid')) {
                      synced++;
                      console.log('ℹ️ Skipped sync for already paid account:', assignmentId);
                    } else {
                      failed++;
                      console.warn('⚠️ Failed to sync assignment:', assignmentId);
                    }
                  }
                } catch (error) {
                  const msg = (error?.message || '').toString().toLowerCase();
                  if (msg.includes('already paid')) {
                    synced++;
                    console.log('ℹ️ Skipped sync for already paid account:', assignment.id);
                  } else {
                    failed++;
                    console.error('❌ Error syncing assignment:', assignment.id, error);
                  }
                }
              }

              setIsProcessing(false);
              Alert.alert(
                'Sync Complete',
                `Synced ${synced} assignment(s)${failed > 0 ? `, ${failed} failed` : ''}.`,
                [{ text: 'OK', onPress: () => loadAssignments() }]
              );
            }
          }
        ]
      );
    } catch (error) {
      setIsProcessing(false);
      console.error('Retry sync error:', error);
      Alert.alert('Error', 'Failed to retry sync: ' + error.message);
    }
  };

  return (
    <>
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={onBack}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Disconnection Assignments</Text>
      </View>

      <View style={[styles.networkBar, isOnline ? styles.networkBarOnline : styles.networkBarOffline]}>
        <Text style={[styles.networkBarText, { color: isOnline ? '#2e7d32' : '#e65100' }]}>
          {isOnline ? '● Online' : '● Offline — changes will sync when back online'}
        </Text>
        {!isOnline && pendingDisconnectorCount > 0 && (
          <Text style={styles.networkBarPending}> ({pendingDisconnectorCount} saved offline)</Text>
        )}
      </View>

      {errorMessage ? (
        <View style={styles.messageBar}>
          <Text style={styles.messageBarText}>{errorMessage}</Text>
        </View>
      ) : null}

      {zoneCodesForPicker.length > 0 && assignments.length > 0 && (
        <View style={styles.zoneFilterSection}>
          <Text style={styles.zoneFilterLabel}>Filter by zone (zone_code)</Text>
          <TouchableOpacity
            style={styles.zoneDropdown}
            onPress={() => setShowZoneDropdown(true)}
            activeOpacity={0.75}
          >
            <Text style={styles.zoneDropdownValue} numberOfLines={1}>
              {selectedZone ? String(selectedZone) : 'All zones'}
            </Text>
            <Text style={styles.zoneDropdownChevron}>▼</Text>
          </TouchableOpacity>
        </View>
      )}

      <View style={styles.actionsRow}>
        <TouchableOpacity style={styles.actionButton} onPress={handleRefresh} disabled={isLoading}>
          <Text style={styles.actionButtonText}>{isLoading ? 'Refreshing...' : 'Refresh'}</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.secondaryButton} onPress={handleDownload} disabled={isLoading}>
          <Text style={styles.secondaryButtonText}>Download Latest</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.secondaryButton, styles.clearButton]}
          onPress={handleClearAllAssignments}
          disabled={isLoading || isProcessing || assignments.length === 0}
        >
          <Text style={styles.clearButtonText}>Clear All</Text>
        </TouchableOpacity>
        <TouchableOpacity 
          style={[styles.secondaryButton, styles.syncButton]} 
          onPress={handleRetryFailedSync} 
          disabled={isProcessing || isLoading}
        >
          <Text style={styles.secondaryButtonText}>
            {isProcessing ? 'Syncing...' : pendingDisconnectorCount > 0 ? `Sync to Server (${pendingDisconnectorCount} saved offline)` : 'Sync to Server'}
          </Text>
        </TouchableOpacity>
      </View>

      {assignments.length > 0 && (
        <View style={styles.searchContainer}>
          <TextInput
            style={styles.searchInput}
            placeholder="Search by consumer name or account no."
            placeholderTextColor="#999"
            value={searchQuery}
            onChangeText={setSearchQuery}
            autoCapitalize="none"
            autoCorrect={false}
          />
          <Text style={styles.searchHint}>
            {searchQuery.trim().length > 0
              ? `${filteredAssignments.length} of ${zoneFilteredAssignments.length} assignment(s)`
              : `${zoneFilteredAssignments.length} assignment(s)`}
          </Text>
        </View>
      )}

      {isLoading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#ff9800" />
          <Text style={styles.loadingText}>Loading assignments...</Text>
        </View>
      ) : assignments.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>No assignments yet</Text>
          <Text style={styles.emptySubtitle}>
            You currently have no disconnection tasks. Pull assignments from the website or refresh
            once new tasks are assigned.
          </Text>
        </View>
      ) : zoneFilteredAssignments.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>No assignments in this zone</Text>
          <Text style={styles.emptySubtitle}>
            There are no disconnection tasks for zone {selectedZone || '—'}. Choose All or another zone.
          </Text>
        </View>
      ) : filteredAssignments.length === 0 ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>No matches</Text>
          <Text style={styles.emptySubtitle}>
            No consumer matches "{searchQuery}". Try a different name or account number.
          </Text>
        </View>
      ) : (
        <View style={styles.listContainer}>
          {filteredAssignments.map((assignment, index) => {
            // Use a more specific unique identifier - use index as part of the ID to ensure uniqueness
            const assignmentId = assignment.id || assignment.schedule_id || assignment.assignment_id || `assignment-${index}`;
            const uniqueKey = `${assignmentId}-${index}`; // Combine ID with index for absolute uniqueness
            const isSelected = selectedAssignmentId === uniqueKey;
            
            return (
              <View key={uniqueKey} style={styles.card}>
                <TouchableOpacity
                  onPress={() => handleCardPress(uniqueKey)}
                  activeOpacity={0.7}
                >
                  <View style={styles.cardHeader}>
                    <View style={styles.headerLeft}>
                      <Text style={styles.accountName}>
                        {assignment.account_name || assignment.name || 'Unnamed account'}
                      </Text>
                      {/* Paid – do not disconnect */}
                      {isAssignmentPaid(assignment) ? (
                        <View style={styles.paidBadge}>
                          <Text style={styles.paidBadgeText}>Paid</Text>
                        </View>
                      ) : (() => {
                        const rawStatus = assignment.status || assignment.assignment_status || '';
                        const status = normalize(rawStatus);
                        const isCompleted = rawStatus === 'X' || rawStatus === 'A' || 
                                           status.includes('disconnected') || status.includes('reconnected');
                        return isCompleted ? (
                          <View style={styles.completedBadge}>
                            <Text style={styles.completedText}>✓ Completed</Text>
                          </View>
                        ) : null;
                      })()}
                    </View>
                    {assignment.zone ? (
                      <Text style={styles.zoneBadge}>Zone {assignment.zone}</Text>
                    ) : null}
                  </View>

                  <Text style={styles.detailText}>
                    Account No.:{' '}
                    <Text style={styles.detailValue}>
                      {assignment.account_no || assignment.account_number || assignment.accountNumber || '—'}
                    </Text>
                  </Text>

                  <Text style={styles.detailText}>
                    Address:{' '}
                    <Text style={styles.detailValue}>{assignment.address || assignment.location || '—'}</Text>
                  </Text>

                  <TouchableOpacity
                    style={styles.viewLocationButton}
                    onPress={(e) => { e?.stopPropagation?.(); handleViewLocation(assignment); }}
                    activeOpacity={0.7}
                  >
                    <Text style={styles.viewLocationText}>📍 View location</Text>
                  </TouchableOpacity>

                  <View style={styles.statusRow}>
                    <Text style={styles.detailText}>
                      Status:{' '}
                      <Text style={[
                        styles.statusValue,
                        (() => {
                          if (isAssignmentPaid(assignment)) return styles.statusPaid;
                          const rawStatus = assignment.status || assignment.assignment_status || '';
                          const status = normalize(rawStatus);
                          if (rawStatus === 'X' || (status.includes('disconnected') && !status.includes('reconnected'))) {
                            return styles.statusDisconnected;
                          } else if (rawStatus === 'A' || status.includes('reconnected')) {
                            return styles.statusReconnected;
                          }
                          return null;
                        })()
                      ]}>
                        {(() => {
                          if (isAssignmentPaid(assignment)) return 'Paid not assign';
                          const rawStatus = assignment.status || assignment.assignment_status || 'Pending';
                          const normalizedStatus = normalize(rawStatus);
                          if (rawStatus === 'X' || (normalizedStatus.includes('disconnected') && !normalizedStatus.includes('reconnected'))) {
                            return 'X';
                          } else if (rawStatus === 'A' || normalizedStatus.includes('reconnected')) {
                            return 'A';
                          }
                          return rawStatus;
                        })()}
                      </Text>
                    </Text>
                  </View>

                  <View style={styles.metaRow}>
                    <Text style={styles.metaTag}>Scheduled: {formatDate(assignment.scheduled_at)}</Text>
                    <Text style={styles.metaTag}>Due: {formatDate(assignment.due_date)}</Text>
                  </View>
                </TouchableOpacity>

                {/* Paid message when card is selected */}
                {isSelected && isAssignmentPaid(assignment) ? (
                  <View style={styles.paidMessageContainer}>
                    <Text style={styles.paidMessageText}>This consumer has paid – do not disconnect.</Text>
                  </View>
                ) : isSelected ? (
                  <>
                    <View style={styles.agingInfoContainer}>
                      <View style={styles.readingMetaRow}>
                        <Text style={styles.readingMetaLabel}>Meter Number:</Text>
                        <Text style={styles.readingMetaValue}>
                          {assignment.meter_number || assignment.meterNumber || assignment.consumer?.meter_number || assignment.consumer?.meterNumber || '—'}
                        </Text>
                      </View>
                      <View style={styles.readingMetaRow}>
                        <Text style={styles.readingMetaLabel}>Last Reading:</Text>
                        <Text style={styles.readingMetaValue}>
                          {formatNumber(
                            assignment.last_reading ??
                            assignment.lastReading ??
                            assignment.previous_reading ??
                            assignment.previousReading ??
                            assignment.current_reading ??
                            assignment.currentReading ??
                            assignment.reading ??
                            assignment.consumer?.last_reading ??
                            assignment.consumer?.lastReading ??
                            assignment.consumer?.previous_reading ??
                            assignment.consumer?.previousReading ??
                            null
                          )}
                        </Text>
                      </View>
                      <View style={styles.agingHeaderRow}>
                        <Text style={styles.agingHeaderCell}>_30</Text>
                        <Text style={styles.agingHeaderCell}>_60</Text>
                        <Text style={styles.agingHeaderCell}>_90</Text>
                        <Text style={styles.agingHeaderCell}>_OVER90</Text>
                        <Text style={styles.agingHeaderCell}>PREV YEAR</Text>
                        <Text style={styles.agingHeaderCell}>BALANCE</Text>
                      </View>
                      <View style={styles.agingValueRow}>
                        <Text style={styles.agingValueCell}>
                          {formatMoney(assignment._30 ?? assignment.thirty_days ?? assignment.arrears_30 ?? 0)}
                        </Text>
                        <Text style={styles.agingValueCell}>
                          {formatMoney(assignment._60 ?? assignment.sixty_days ?? assignment.arrears_60 ?? 0)}
                        </Text>
                        <Text style={styles.agingValueCell}>
                          {formatMoney(assignment._90 ?? assignment.ninety_days ?? assignment.arrears_90 ?? 0)}
                        </Text>
                        <Text style={styles.agingValueCell}>
                          {formatMoney(assignment._OVER90 ?? assignment.over_90 ?? assignment.over90 ?? assignment.arrears_over_90 ?? 0)}
                        </Text>
                        <Text style={styles.agingValueCell}>
                          {formatMoney(assignment.PREV_YEAR ?? assignment.prev_year ?? assignment.previous_year ?? 0)}
                        </Text>
                        <Text style={[styles.agingValueCell, styles.agingBalanceValue]}>
                          {formatMoney(computeDisconnectorAgingBalance(assignment))}
                        </Text>
                      </View>
                    </View>
                    {(() => {
                      const rawStatus = assignment.status || assignment.assignment_status || '';
                      const status = normalize(rawStatus);
                      const isDisconnected = rawStatus === 'X' || (status.includes('disconnected') && !status.includes('reconnected'));
                      
                      return (
                        <View style={styles.actionButtonsContainer}>
                          {isDisconnected ? (
                            // Show Reconnect button if already disconnected
                            <>
                              <TouchableOpacity
                                style={[styles.actionBtn, styles.reconnectBtn]}
                                onPress={() => handleReconnect(assignment)}
                                disabled={isProcessing}
                              >
                                <Text style={styles.actionBtnText}>Reconnect</Text>
                              </TouchableOpacity>
                              <TouchableOpacity
                                style={[styles.actionBtn, styles.cancelBtn]}
                                onPress={handleCancel}
                                disabled={isProcessing}
                              >
                                <Text style={styles.actionBtnText}>Cancel</Text>
                              </TouchableOpacity>
                            </>
                          ) : (
                            // Show Disconnect button if not disconnected
                            <>
                              <TouchableOpacity
                                style={[styles.actionBtn, styles.disconnectBtn]}
                                onPress={() => handleDisconnect(assignment)}
                                disabled={isProcessing}
                              >
                                <Text style={styles.actionBtnText}>Disconnect</Text>
                              </TouchableOpacity>
                              <TouchableOpacity
                                style={[styles.actionBtn, styles.cancelBtn]}
                                onPress={handleCancel}
                                disabled={isProcessing}
                              >
                                <Text style={styles.actionBtnText}>Cancel</Text>
                              </TouchableOpacity>
                            </>
                          )}
                        </View>
                      );
                    })()}
                  </>
                ) : null}
              </View>
            );
          })}
        </View>
      )}
    </ScrollView>
 
    <Modal
      visible={showZoneDropdown}
      transparent
      animationType="slide"
      onRequestClose={() => setShowZoneDropdown(false)}
    >
      <View style={styles.zoneModalRoot}>
        <TouchableOpacity
          style={styles.zoneModalBackdropTouch}
          activeOpacity={1}
          onPress={() => setShowZoneDropdown(false)}
        />
        <View style={styles.zoneModalSheet}>
          <Text style={styles.zoneModalTitle}>Select zone (zone_code)</Text>
          <ScrollView style={styles.zoneModalScroll} keyboardShouldPersistTaps="handled">
            <TouchableOpacity
              style={styles.zoneModalOption}
              onPress={() => {
                setSelectedZone(null);
                setShowZoneDropdown(false);
              }}
            >
              <Text style={styles.zoneModalOptionText}>All zones</Text>
              {selectedZone == null ? <Text style={styles.zoneModalCheck}>✓</Text> : <View style={styles.zoneModalCheckSpacer} />}
            </TouchableOpacity>
            {zoneCodesForPicker.map((code) => (
              <TouchableOpacity
                key={code}
                style={styles.zoneModalOption}
                onPress={() => {
                  setSelectedZone(code);
                  setShowZoneDropdown(false);
                }}
              >
                <Text style={styles.zoneModalOptionText}>{code}</Text>
                {selectedZone === code ? <Text style={styles.zoneModalCheck}>✓</Text> : <View style={styles.zoneModalCheckSpacer} />}
              </TouchableOpacity>
            ))}
          </ScrollView>
          <TouchableOpacity style={styles.zoneModalCancel} onPress={() => setShowZoneDropdown(false)}>
            <Text style={styles.zoneModalCancelText}>Cancel</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    paddingTop: 50,
    paddingHorizontal: 20,
    paddingBottom: 20,
    backgroundColor: 'white',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 4,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
  },
  backButton: {
    paddingVertical: 8,
    paddingHorizontal: 12,
  },
  backButtonText: {
    fontSize: 16,
    color: '#ff9800',
    fontWeight: '600',
  },
  networkBar: {
    paddingVertical: 8,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
  },
  networkBarOnline: {
    backgroundColor: '#e8f5e9',
  },
  networkBarOffline: {
    backgroundColor: '#fff3e0',
  },
  networkBarText: {
    fontSize: 14,
    fontWeight: '600',
  },
  networkBarPending: {
    fontSize: 14,
    color: '#e65100',
    fontWeight: '600',
  },
  messageBar: {
    backgroundColor: '#fffde7',
    paddingVertical: 8,
    paddingHorizontal: 20,
  },
  messageBarText: {
    fontSize: 13,
    color: '#666',
  },
  actionsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 15,
    flexWrap: 'wrap',
    gap: 10,
  },
  actionButton: {
    backgroundColor: '#ff9800',
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 8,
  },
  actionButtonText: {
    color: 'white',
    fontWeight: 'bold',
  },
  secondaryButton: {
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#ff9800',
    flex: 1,
    minWidth: '30%',
    alignItems: 'center',
  },
  secondaryButtonText: {
    color: '#ff9800',
    fontWeight: 'bold',
    fontSize: 12,
  },
  syncButton: {
    borderColor: '#4CAF50',
    backgroundColor: '#f1f8f4',
  },
  clearButton: {
    borderColor: '#dc3545',
    backgroundColor: '#fff5f5',
  },
  clearButtonText: {
    color: '#dc3545',
    fontWeight: 'bold',
    fontSize: 12,
  },
  zoneFilterSection: {
    paddingHorizontal: 20,
    paddingTop: 8,
    paddingBottom: 4,
  },
  zoneFilterLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: '#555',
    marginBottom: 8,
  },
  zoneDropdown: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: 'white',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  zoneDropdownValue: {
    flex: 1,
    fontSize: 16,
    color: '#333',
    marginRight: 8,
  },
  zoneDropdownChevron: {
    fontSize: 12,
    color: '#888',
  },
  zoneModalRoot: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
  },
  zoneModalBackdropTouch: {
    flex: 1,
  },
  zoneModalSheet: {
    backgroundColor: 'white',
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    maxHeight: '70%',
    paddingBottom: 24,
  },
  zoneModalTitle: {
    fontSize: 17,
    fontWeight: 'bold',
    color: '#333',
    textAlign: 'center',
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  zoneModalScroll: {
    maxHeight: 320,
  },
  zoneModalOption: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 14,
    paddingHorizontal: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  zoneModalOptionText: {
    fontSize: 16,
    color: '#333',
    flex: 1,
  },
  zoneModalCheck: {
    fontSize: 18,
    color: '#ff9800',
    fontWeight: 'bold',
    marginLeft: 8,
  },
  zoneModalCheckSpacer: {
    width: 22,
    marginLeft: 8,
  },
  zoneModalCancel: {
    marginTop: 12,
    marginHorizontal: 20,
    paddingVertical: 14,
    borderRadius: 10,
    backgroundColor: '#f5f5f5',
    alignItems: 'center',
  },
  zoneModalCancelText: {
    fontSize: 16,
    color: '#666',
    fontWeight: '600',
  },
  searchContainer: {
    paddingHorizontal: 20,
    paddingVertical: 10,
    paddingBottom: 4,
  },
  searchInput: {
    backgroundColor: 'white',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 16,
    color: '#333',
  },
  searchHint: {
    fontSize: 12,
    color: '#666',
    marginTop: 6,
    marginLeft: 4,
  },
  loadingContainer: {
    paddingVertical: 60,
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 10,
    color: '#888',
  },
  emptyState: {
    padding: 30,
    marginHorizontal: 20,
    marginVertical: 10,
    backgroundColor: 'white',
    borderRadius: 16,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 3,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 14,
    color: '#666',
    textAlign: 'center',
    lineHeight: 20,
  },
  listContainer: {
    paddingHorizontal: 20,
    paddingBottom: 30,
  },
  card: {
    backgroundColor: 'white',
    borderRadius: 16,
    padding: 18,
    marginBottom: 15,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 3,
    elevation: 3,
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  headerLeft: {
    flex: 1,
    flexDirection: 'column',
    marginRight: 8,
  },
  accountName: {
    fontSize: 17,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 4,
  },
  completedBadge: {
    backgroundColor: '#4caf50',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    marginTop: 4,
    alignSelf: 'flex-start',
  },
  completedText: {
    color: 'white',
    fontSize: 11,
    fontWeight: '600',
  },
  zoneBadge: {
    backgroundColor: '#ffe0b2',
    color: '#ff9800',
    fontWeight: 'bold',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    overflow: 'hidden',
  },
  detailText: {
    fontSize: 14,
    color: '#666',
    marginBottom: 6,
  },
  detailValue: {
    color: '#333',
    fontWeight: '600',
  },
  viewLocationButton: {
    alignSelf: 'flex-start',
    marginTop: 4,
    marginBottom: 8,
    paddingVertical: 6,
    paddingHorizontal: 10,
    backgroundColor: '#e3f2fd',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#2196f3',
  },
  viewLocationText: {
    fontSize: 14,
    color: '#1976d2',
    fontWeight: '600',
  },
  statusRow: {
    marginBottom: 6,
  },
  statusValue: {
    color: '#ff9800',
    fontWeight: '700',
  },
  statusDisconnected: {
    color: '#f44336',
  },
  statusReconnected: {
    color: '#4caf50',
  },
  statusPaid: {
    color: '#2e7d32',
    fontWeight: '700',
  },
  paidBadge: {
    backgroundColor: '#2e7d32',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    marginTop: 4,
    alignSelf: 'flex-start',
  },
  paidBadgeText: {
    color: 'white',
    fontSize: 11,
    fontWeight: '600',
  },
  paidMessageContainer: {
    marginTop: 15,
    paddingTop: 15,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
    backgroundColor: '#e8f5e9',
    borderRadius: 8,
  },
  paidMessageText: {
    color: '#2e7d32',
    fontSize: 14,
    fontWeight: '600',
    textAlign: 'center',
  },
  agingInfoContainer: {
    marginTop: 12,
    borderWidth: 1,
    borderColor: '#dbe3ef',
    borderRadius: 8,
    overflow: 'hidden',
    backgroundColor: '#f8fbff',
  },
  readingMetaRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#e6edf7',
    backgroundColor: '#fff',
  },
  readingMetaLabel: {
    fontSize: 13,
    color: '#596170',
    fontWeight: '700',
  },
  readingMetaValue: {
    fontSize: 13,
    color: '#2f3743',
    fontWeight: '700',
  },
  agingHeaderRow: {
    flexDirection: 'row',
    backgroundColor: '#eaf1f9',
  },
  agingValueRow: {
    flexDirection: 'row',
    backgroundColor: '#fff',
  },
  agingHeaderCell: {
    flex: 1,
    textAlign: 'center',
    fontSize: 11,
    color: '#5f6773',
    fontWeight: '700',
    paddingVertical: 8,
    borderRightWidth: 1,
    borderRightColor: '#dbe3ef',
  },
  agingValueCell: {
    flex: 1,
    textAlign: 'center',
    fontSize: 12,
    color: '#2f3743',
    fontWeight: '600',
    paddingVertical: 8,
    borderRightWidth: 1,
    borderRightColor: '#eef2f7',
  },
  agingBalanceValue: {
    color: '#1b5e20',
    fontWeight: '700',
  },
  metaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginTop: 10,
    gap: 10,
  },
  metaTag: {
    backgroundColor: '#f5f5f5',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    fontSize: 12,
    color: '#666',
  },
  errorBanner: {
    marginHorizontal: 20,
    marginBottom: 30,
    padding: 12,
    borderRadius: 10,
    backgroundColor: '#fff3e0',
  },
  errorText: {
    color: '#e65100',
    fontSize: 13,
    textAlign: 'center',
  },
  actionButtonsContainer: {
    flexDirection: 'row',
    marginTop: 15,
    paddingTop: 15,
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
    gap: 10,
  },
  actionBtn: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  disconnectBtn: {
    backgroundColor: '#f44336',
  },
  cancelBtn: {
    backgroundColor: '#9e9e9e',
  },
  reconnectBtn: {
    backgroundColor: '#4CAF50',
  },
  actionBtnText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 14,
  },
});


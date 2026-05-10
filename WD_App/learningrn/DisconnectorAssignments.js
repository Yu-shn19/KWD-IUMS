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
import { routesStorage, tokenStorage, userStorage, disconnectorPaidStorage } from './services/storage';
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

// Normalize account identifier for matching paid list
const getAssignmentAccountNo = (a) => {
  const no = (a.account_no || a.account_number || a.accountNumber || '').toString().trim();
  return no;
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

  const paidAccountNos = React.useMemo(() => {
    const set = new Set();
    (cancelledDueToPayment || []).forEach((c) => {
      const no = (c.account_no || c.account_number || '').toString().trim();
      const num = (c.account_number || c.account_no || '').toString().trim();
      if (no) set.add(no);
      if (num && num !== no) set.add(num);
    });
    return set;
  }, [cancelledDueToPayment]);

  const isAssignmentPaid = (assignment) => {
    const no = getAssignmentAccountNo(assignment);
    return no && paidAccountNos.has(no);
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

      // When offline: show cached data only; use cached paid list so paid consumers stay "Paid not assign"
      if (!online) {
        const cachedRoutes = await routesStorage.getRoutes();
        const cachedAssignments = filterDisconnectionAssignments(cachedRoutes || []);
        const cachedPaidList = await disconnectorPaidStorage.getPaidList();
        setAssignments(cachedAssignments);
        setCancelledDueToPayment(Array.isArray(cachedPaidList) ? cachedPaidList : []);
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
      } catch (apiError) {
        console.warn('Disconnector assignments API error:', apiError);
        setErrorMessage(
          'Using cached data. Server could not be reached or is busy—refresh when online for the latest list.'
        );
      }

      // Always merge with local storage to ensure disconnected assignments remain visible
      const cachedRoutes = await routesStorage.getRoutes();
      const cachedAssignments = filterDisconnectionAssignments(cachedRoutes || []);
      
      // Merge API assignments with cached assignments
      // Priority: API data for new/updated assignments, cached data for disconnected/completed ones
      const mergedAssignments = [...assignmentsList];
      const apiIdentifiers = new Set();
      assignmentsList.forEach((item) => {
        const z = item.zone_code ?? item.zone;
        const identifier = item.id || item.schedule_id || `${item.account_number}-${z}`;
        apiIdentifiers.add(identifier);
      });
      
      // Add cached assignments that aren't in API response (e.g., disconnected ones)
      cachedAssignments.forEach((cachedItem) => {
        const z = cachedItem.zone_code ?? cachedItem.zone;
        const identifier = cachedItem.id || cachedItem.schedule_id || `${cachedItem.account_number}-${z}`;
        if (!apiIdentifiers.has(identifier)) {
          mergedAssignments.push(cachedItem);
        }
      });

      // Ensure assignments are unique by schedule ID if available
      const uniqueAssignments = [];
      const seen = new Set();
      mergedAssignments.forEach((item) => {
        const z = item.zone_code ?? item.zone;
        const identifier = item.id || item.schedule_id || `${item.account_number}-${z}`;
        if (!seen.has(identifier)) {
          seen.add(identifier);
          uniqueAssignments.push(item);
        }
      });

      setAssignments(uniqueAssignments);
      // Persist to cache so when offline we can show this list
      await routesStorage.saveRoutes(uniqueAssignments);

      // Fetch consumers who already paid (do not disconnect – show as "Paid not assign"); cache for offline
      if (disconnectorId && token) {
        try {
          const res = await disconnectorAPI.getCancelledDueToPayment(
            { disconnector_id: disconnectorId },
            token
          );
          const list = res?.cancelled_due_to_payment || [];
          const paidList = Array.isArray(list) ? list : [];
          setCancelledDueToPayment(paidList);
          await disconnectorPaidStorage.savePaidList(paidList);
        } catch (e) {
          console.warn('DisconnectorAssignments cancelled-due-to-payment:', e);
          const cachedPaid = await disconnectorPaidStorage.getPaidList();
          setCancelledDueToPayment(Array.isArray(cachedPaid) ? cachedPaid : []);
        }
      }

      if (showAlerts) {
        Alert.alert(
          'Assignments Updated',
          uniqueAssignments.length
            ? `${uniqueAssignments.length} assignment(s) loaded.`
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

  useEffect(() => {
    refreshPendingDisconnectorCount();
  }, []);

  // Sync one disconnector action (used by syncManager.syncDisconnectorActions)
  const syncOneDisconnectorAction = async (action) => {
    try {
      const token = await tokenStorage.getToken();
      if (!token) return { success: false, message: 'No token' };
      const { payload, accountNumber, accountName, statusCode } = action.data || {};
      if (!payload) return { success: false, message: 'Invalid action data' };
      const res = await disconnectorAPI.updateAssignmentStatus(payload, token);
      if (!res || (res.success === false && res.success !== undefined)) return { success: false, message: res?.message || 'API failed' };
      if (accountNumber && accountNumber !== 'N/A' && statusCode) {
        try {
          await disconnectorAPI.updateConsumerZoneStatus(accountNumber, statusCode, token, accountName);
        } catch (e) {
          console.warn('Consumer zone update failed (action still synced):', e);
        }
      }
      return { success: true };
    } catch (error) {
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
      await routesStorage.saveRoutes(filtered);
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
    if (!lat || !lng) {
      Alert.alert(
        'Location Not Available',
        `GPS coordinates not found in consumer_zone for account: ${accountNumber || 'N/A'}\n\nCustomer: ${name}\n\nEnsure the consumer_zone table has latitude and longitude for this account.`,
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
                await routesStorage.saveRoutes(updatedAssignments);
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
                        
                        // Also update consumer_zone status_code to 'X' if disconnection was successful
                        const accountNumber = actualAssignment.account_number || 
                                            actualAssignment.accountNumber || 
                                            actualAssignment.account_no ||
                                            actualAssignment.accountNo ||
                                            displayAccountNumber;
                        
                        console.log('Attempting to update consumer_zone status_code:', {
                          accountNumber,
                          displayAccountNumber,
                          actualAssignmentAccountNumber: actualAssignment.account_number,
                          assignmentAccountNumber: assignment.account_number
                        });
                        
                        // Always try to update consumer_zone, even if account number is missing
                        // We'll use account name as fallback to find the consumer
                        try {
                          console.log('Updating consumer_zone status_code to X:', {
                            accountNumber,
                            displayAccountNumber,
                            accountName: displayAccountName,
                            actualAssignment: {
                              account_number: actualAssignment.account_number,
                              accountNumber: actualAssignment.accountNumber,
                              account_no: actualAssignment.account_no,
                              accountNo: actualAssignment.accountNo,
                              name: actualAssignment.account_name || actualAssignment.name
                            }
                          });
                          
                          // Pass account number if available, otherwise null (API will try to find by name)
                          const consumerZoneResponse = await disconnectorAPI.updateConsumerZoneStatus(
                            accountNumber && accountNumber !== 'N/A' && accountNumber !== 'undefined' && accountNumber !== 'null' 
                              ? accountNumber 
                              : null, 
                            'X', 
                            token, 
                            displayAccountName
                          );
                          console.log('✅ Consumer zone status_code updated to X:', consumerZoneResponse);
                          
                          // Show success message
                          if (consumerZoneResponse && (consumerZoneResponse.success !== false)) {
                            console.log('✅ Consumer zone status_code successfully updated in database');
                          }
                        } catch (consumerZoneError) {
                          console.error('❌ Failed to update consumer_zone status_code:', consumerZoneError);
                          console.error('Error details:', {
                            message: consumerZoneError.message,
                            accountNumber: accountNumber,
                            accountName: displayAccountName,
                            statusCode: 'X'
                          });
                          // Show warning but don't fail the whole operation
                          Alert.alert(
                            'Warning',
                            `Disconnection saved, but failed to update consumer status in database.\n\nAccount: ${displayAccountName}\nAccount No.: ${accountNumber || 'Not found'}\nError: ${consumerZoneError.message || 'Unknown error'}\n\nPlease update consumer_zone.status_code to 'X' manually in the database for account "${displayAccountName}".`
                          );
                        }
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
                  console.error('❌ API update failed:', error);
                  if (payload) {
                    await disconnectorQueue.addAction({ type: 'disconnect', data: { payload, accountNumber: displayAccountNumber, accountName: displayAccountName, statusCode: 'X' } });
                    setPendingDisconnectorCount(await disconnectorQueue.getPendingCount());
                  }
                  if (error.message) apiError = error.message;
                  else if (error.response) apiError = error.response.message || error.response.error || 'API request failed';
                  else if (typeof error === 'string') apiError = error;
                  else apiError = 'API request failed. Please check your connection and try again.';
                }
              }
              if (!apiSuccess && payload) {
                await disconnectorQueue.addAction({ type: 'disconnect', data: { payload, accountNumber: displayAccountNumber, accountName: displayAccountName, statusCode: 'X' } });
                setPendingDisconnectorCount(await disconnectorQueue.getPendingCount());
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
              await routesStorage.saveRoutes(updatedAssignments);
              
              setSelectedAssignmentId(null);
              
              // Show appropriate message based on API success
              if (apiSuccess) {
                Alert.alert('Success', 'Account marked as disconnected and updated in main system.');
              } else if (payload) {
                Alert.alert('Saved offline', 'Will sync when you\'re back online.');
              } else {
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
                await routesStorage.saveRoutes(updatedAssignmentsReconnect);
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
                        
                        // Also update consumer_zone status_code back to 'A' (Active) if reconnection was successful
                        const accountNumber = actualAssignment.account_number || 
                                            actualAssignment.accountNumber || 
                                            actualAssignment.account_no ||
                                            actualAssignment.accountNo ||
                                            displayAccountNumber;
                        
                        if (accountNumber && accountNumber !== 'N/A' && accountNumber !== 'undefined' && accountNumber !== 'null') {
                          try {
                            console.log('Updating consumer_zone status_code back to A (Active) for account:', accountNumber);
                            await disconnectorAPI.updateConsumerZoneStatus(accountNumber, 'A', token, displayAccountName);
                            console.log('✅ Consumer zone status_code updated to A (Active)');
                          } catch (consumerZoneError) {
                            console.warn('⚠️ Failed to update consumer_zone status_code:', consumerZoneError);
                            // Don't fail the whole operation if consumer_zone update fails
                          }
                        } else {
                          console.warn('⚠️ No valid account number found to update consumer_zone');
                        }
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
              await routesStorage.saveRoutes(updatedAssignments);
              
              setSelectedAssignmentId(null);
              
              // Show appropriate message based on API success
              if (apiSuccess) {
                Alert.alert('Success', 'Account marked as reconnected and updated in main system.');
              } else if (payloadReconnect) {
                Alert.alert('Saved offline', 'Will sync when you\'re back online.');
              } else {
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
                    
                    // Also try to update consumer_zone
                    if (accountNumber && accountNumber !== 'N/A') {
                      try {
                        await disconnectorAPI.updateConsumerZoneStatus(accountNumber, 'X', token, accountName);
                        console.log('✅ Consumer zone updated for:', accountNumber);
                      } catch (czError) {
                        console.warn('⚠️ Consumer zone update failed:', czError);
                      }
                    }
                  } else {
                    failed++;
                    console.warn('⚠️ Failed to sync assignment:', assignmentId);
                  }
                } catch (error) {
                  failed++;
                  console.error('❌ Error syncing assignment:', assignment.id, error);
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
          {searchQuery.trim().length > 0 && (
            <Text style={styles.searchHint}>
              {filteredAssignments.length} of {zoneFilteredAssignments.length} assignment(s)
            </Text>
          )}
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
                      {assignment.account_number || assignment.accountNumber || '—'}
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
                ) : isSelected ? (() => {
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
                })() : null}
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


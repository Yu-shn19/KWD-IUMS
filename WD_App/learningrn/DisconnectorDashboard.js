import React, { useEffect, useState, useRef } from 'react';
import { ScrollView, View, Text, StyleSheet, TouchableOpacity, Alert, AppState, Modal, FlatList } from 'react-native';
import { routesStorage, tokenStorage, userStorage, disconnectorPaidStorage } from './services/storage';
import { disconnectorAPI } from './services/api';
import { networkStatus } from './services/offlineQueue';

const normalize = (value) => (value || '').toString().toLowerCase();

const extractAssignments = (records = []) => {
  return records.filter((item) => {
    const assignmentType = normalize(item.assignment_type || item.type);
    const status = normalize(item.status || item.assignment_status || '');
    const rawStatus = item.status || item.assignment_status || '';

    if (assignmentType.includes('disconnect')) {
      return true;
    }

    // Include assignments with status 'X' (disconnected) or 'A' (reconnected/active)
    if (rawStatus === 'X' || rawStatus === 'A') {
      return true;
    }

    if (!status) {
      return false;
    }

    // Include disconnected, reconnected, and pending disconnection assignments
    return status.includes('disconnect') || 
           status.includes('for disconnection') ||
           status.includes('reconnected');
  });
};

const categorizeAssignments = (assignments) => {
  let pending = 0;
  let completed = 0;
  let inProgress = 0;

  assignments.forEach((assignment) => {
    // Check both status and assignment_status fields
    const status = normalize(assignment.status || assignment.assignment_status || '');
    const rawStatus = assignment.status || assignment.assignment_status || '';
    
    // Debug logging for first few assignments
    if (assignments.indexOf(assignment) < 3) {
      console.log('Categorizing assignment:', {
        account: assignment.account_name || assignment.name,
        status: rawStatus,
        normalized: status,
        isX: rawStatus === 'X',
        isA: rawStatus === 'A',
        hasDisconnected: status.includes('disconnected'),
        hasReconnected: status.includes('reconnected'),
        hasCompleted: status.includes('completed')
      });
    }

    // Count as completed if status is 'X' (disconnected) or 'A' (reconnected/active)
    // Also check for old status values for backward compatibility
    if (rawStatus === 'X' || rawStatus === 'A' ||
        status.includes('completed') || 
        status.includes('disconnected') || 
        status.includes('reconnected')) {
      completed += 1;
    } else if (status.includes('progress') || status.includes('ongoing')) {
      inProgress += 1;
    } else {
      pending += 1;
    }
  });

  return {
    total: assignments.length,
    pending,
    completed,
    inProgress,
  };
};

export default function DisconnectorDashboard({ userData, onNavigate, onLogout }) {
  const [stats, setStats] = useState({
    total: 0,
    pending: 0,
    inProgress: 0,
    completed: 0,
  });
  const [cancelledDueToPayment, setCancelledDueToPayment] = useState([]);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [paidListModalVisible, setPaidListModalVisible] = useState(false);
  const hasShownPaymentNotification = useRef(false);

  const userName =
    userData?.name ||
    `${userData?.first_name ?? ''} ${userData?.last_name ?? ''}`.trim() ||
    'Disconnector';
  const roleDisplay = userData?.role || userData?.role_name || 'Disconnector';

  const loadStats = async (silent = false) => {
    if (!silent) setIsRefreshing(true);
    try {
      const token = await tokenStorage.getToken();
      const storedUser = userData || (await userStorage.getUserData());
      const disconnectorId = storedUser?.id;

      const online = await networkStatus.isOnline(true);

      // When offline: use only cached data so counts still display
      if (!online) {
        const stored = await routesStorage.getRoutes();
        const cachedAssignments = extractAssignments(stored || []);
        setStats(categorizeAssignments(cachedAssignments));
        const cachedPaidList = await disconnectorPaidStorage.getPaidList();
        setCancelledDueToPayment(Array.isArray(cachedPaidList) ? cachedPaidList : []);
        if (!silent) setIsRefreshing(false);
        return;
      }

      let assignmentsList = [];

      // Fetch from API when online
      if (disconnectorId && token) {
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
          console.warn('DisconnectorDashboard API error:', apiError);
          // Fall through to use cached data
        }
      }

      // Always merge with local storage to ensure disconnected assignments are included
      const stored = await routesStorage.getRoutes();
      const cachedAssignments = extractAssignments(stored || []);
      
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

      const assignments = extractAssignments(mergedAssignments);
      const totals = categorizeAssignments(assignments);

      console.log('DisconnectorDashboard Stats:', {
        total: totals.total,
        pending: totals.pending,
        completed: totals.completed,
        inProgress: totals.inProgress,
        assignmentsCount: assignments.length,
        sampleStatuses: assignments.slice(0, 3).map(a => ({
          account: a.account_name || a.name,
          status: a.status || a.assignment_status
        }))
      });

      setStats(totals);

      // Fetch orders cancelled because consumer paid (do not disconnect these); cache for offline
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
          console.warn('DisconnectorDashboard cancelled-due-to-payment:', e);
          const cachedPaid = await disconnectorPaidStorage.getPaidList();
          setCancelledDueToPayment(Array.isArray(cachedPaid) ? cachedPaid : []);
        }
      }
    } catch (error) {
      console.error('DisconnectorDashboard loadStats error:', error);
      // On error, try to load from local storage as fallback
      try {
        const stored = await routesStorage.getRoutes();
        const assignments = extractAssignments(stored || []);
        setStats(categorizeAssignments(assignments));
      } catch (fallbackError) {
        console.error('DisconnectorDashboard fallback error:', fallbackError);
      }
    } finally {
      if (!silent) setIsRefreshing(false);
    }
  };

  useEffect(() => {
    loadStats();
  }, []);

  // Auto-refresh: poll every 60s and when app comes to foreground
  useEffect(() => {
    const REFRESH_INTERVAL_MS = 10000; // 10 seconds
    let intervalId = null;

    const refreshSilent = () => {
      loadStats(true);
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

  // Push-style notification: when there are consumers who paid (do not disconnect), alert the disconnector
  useEffect(() => {
    if (cancelledDueToPayment.length > 0 && !hasShownPaymentNotification.current) {
      hasShownPaymentNotification.current = true;
      const n = cancelledDueToPayment.length;
      const names = cancelledDueToPayment
        .slice(0, 3)
        .map((c) => c.account_no + (c.account_name ? ` (${c.account_name})` : ''))
        .join('\n');
      Alert.alert(
        'Do not disconnect',
        n === 1
          ? `1 consumer has paid and was removed from your list. Do not disconnect them.\n\n${names}`
          : `${n} consumers have paid and were removed from your list. Do not disconnect them.\n\n${names}${n > 3 ? '\n... and more (see list below)' : ''}`,
        [{ text: 'OK' }]
      );
    }
  }, [cancelledDueToPayment]);

  const handleViewAssignments = () => {
    onNavigate('disconnectorAssignments');
  };

  const handleRefresh = async () => {
    await loadStats();
    Alert.alert('Refreshed', 'Dashboard metrics updated.');
  };

  const handleLogoutPress = () => {
    onLogout();
  };

  const handleNotificationPress = () => {
    const pendingMsg =
      stats.pending > 0
        ? `• ${stats.pending} pending disconnection(s) (${stats.total} total assignment(s)).`
        : '';
    const paidCount = cancelledDueToPayment.length;
    const paidMsg =
      paidCount > 0
        ? `• ${paidCount} consumer(s) have paid – do not disconnect them.${paidCount <= 3 ? '\n  ' + cancelledDueToPayment.slice(0, 3).map((c) => c.account_no + (c.account_name ? ` (${c.account_name})` : '')).join('\n  ') : ''}`
        : '';
    const message = [pendingMsg, paidMsg].filter(Boolean).join('\n\n') || 'No new notifications. Check View Assignments for your tasks.';
    Alert.alert('Notifications', message, [{ text: 'OK' }]);
  };

  return (
    <>
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <View style={styles.headerContent}>
          <View style={styles.logoContainer}>
            <Text style={styles.logoText}>{userName.substring(0, 2).toUpperCase()}</Text>
          </View>
          <View style={styles.headerText}>
            <Text style={styles.welcomeText}>Welcome back!</Text>
            <Text style={styles.userText}>{userName}</Text>
            <Text style={styles.roleText}>{roleDisplay}</Text>
          </View>
        </View>
        <TouchableOpacity
          style={styles.notificationIconButton}
          onPress={handleNotificationPress}
          activeOpacity={0.7}
        >
          <Text style={styles.notificationIcon}>🔔</Text>
          {(stats.pending > 0 || cancelledDueToPayment.length > 0) && (
            <View style={styles.notificationBadge}>
              <Text style={styles.notificationBadgeText}>
                {stats.pending + cancelledDueToPayment.length > 99 ? '99+' : stats.pending + cancelledDueToPayment.length}
              </Text>
            </View>
          )}
        </TouchableOpacity>
      </View>

      <View style={styles.titleContainer}>
        <Text style={styles.dashboardTitle}>Disconnector Dashboard</Text>
        <Text style={styles.dashboardSubtitle}>
          Monitor assigned disconnections, progress, and quick actions.
        </Text>
      </View>

      <View style={styles.statsGrid}>
        <View style={[styles.statCard, styles.totalCard]}>
          <Text style={styles.statLabel}>Assigned</Text>
          <Text style={styles.statValue}>{stats.total}</Text>
          <Text style={styles.statHint}>Total disconnection tasks</Text>
        </View>

        <View style={[styles.statCard, styles.pendingCard]}>
          <Text style={styles.statLabel}>Pending</Text>
          <Text style={styles.statValue}>{Math.max(0, stats.pending - cancelledDueToPayment.length)}</Text>
          <Text style={styles.statHint}>Awaiting disconnection</Text>
        </View>

        <View style={[styles.statCard, styles.paidCountCard]}>
          <Text style={styles.statLabel}>Paid</Text>
          <Text style={styles.statValue}>{cancelledDueToPayment.length}</Text>
          <Text style={styles.statHint}>Do not disconnect</Text>
        </View>

        <View style={[styles.statCard, styles.completedCard]}>
          <Text style={styles.statLabel}>Completed</Text>
          <Text style={styles.statValue}>{stats.completed}</Text>
          <Text style={styles.statHint}>Marked disconnected</Text>
        </View>
      </View>

      {cancelledDueToPayment.length > 0 && (
        <View style={styles.noticeContainer}>
          <Text style={styles.noticeTitle}>Do not disconnect – paid</Text>
          <Text style={styles.noticeSubtitle}>
            The following consumers have a payment on record and were removed from your list. Do not disconnect them.
          </Text>
          {cancelledDueToPayment.slice(0, 10).map((item) => (
            <View key={item.id || item.account_no} style={styles.noticeRow}>
              <Text style={styles.noticeAccount}>{item.account_no}</Text>
              <Text style={styles.noticeName} numberOfLines={1}>{item.account_name || '—'}</Text>
            </View>
          ))}
          {cancelledDueToPayment.length > 10 && (
            <TouchableOpacity
              style={styles.noticeSeeAllButton}
              onPress={() => setPaidListModalVisible(true)}
              activeOpacity={0.7}
            >
              <Text style={styles.noticeSeeAllText}>See All</Text>
            </TouchableOpacity>
          )}
        </View>
      )}

      <View style={styles.actionsContainer}>
        <TouchableOpacity style={styles.actionButton} onPress={handleViewAssignments}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>📋</Text>
          </View>
          <View style={styles.buttonTextContainer}>
            <Text style={styles.buttonTitle}>View Assignments</Text>
            <Text style={styles.buttonSubtitle}>
              See all customers scheduled for disconnection.
            </Text>
          </View>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionButton} onPress={handleRefresh} disabled={isRefreshing}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>🔄</Text>
          </View>
          <View style={styles.buttonTextContainer}>
            <Text style={styles.buttonTitle}>{isRefreshing ? 'Refreshing...' : 'Refresh Metrics'}</Text>
            <Text style={styles.buttonSubtitle}>
              Update dashboard figures with the latest assignments.
            </Text>
          </View>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionButton} onPress={() => Alert.alert('Reminder', 'Sync offline reports and submit updates once you have connectivity.')}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>📡</Text>
          </View>
          <View style={styles.buttonTextContainer}>
            <Text style={styles.buttonTitle}>Sync Reminder</Text>
            <Text style={styles.buttonSubtitle}>
              Ensure offline updates are synced when back online.
            </Text>
          </View>
        </TouchableOpacity>
      </View>

      <TouchableOpacity style={styles.logoutButton} onPress={handleLogoutPress}>
        <Text style={styles.logoutText}>Logout</Text>
      </TouchableOpacity>
    </ScrollView>

    <Modal
      visible={paidListModalVisible}
      animationType="slide"
      presentationStyle="fullScreen"
      onRequestClose={() => setPaidListModalVisible(false)}
    >
      <View style={styles.paidModalRoot}>
        <View style={styles.paidModalHeader}>
          <View style={styles.paidModalHeaderText}>
            <Text style={styles.paidModalTitle}>Do not disconnect – paid</Text>
            <Text style={styles.paidModalSubtitle}>
              {cancelledDueToPayment.length} consumer(s) with a payment on record — do not disconnect them.
            </Text>
          </View>
          <TouchableOpacity
            style={styles.paidModalCloseBtn}
            onPress={() => setPaidListModalVisible(false)}
            hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
          >
            <Text style={styles.paidModalCloseText}>Close</Text>
          </TouchableOpacity>
        </View>
        <FlatList
          data={cancelledDueToPayment}
          keyExtractor={(item, index) => `${item?.account_no ?? ''}-${item?.id ?? index}`}
          contentContainerStyle={styles.paidModalListContent}
          renderItem={({ item }) => (
            <View style={styles.paidModalRow}>
              <Text style={styles.noticeAccount}>{item.account_no}</Text>
              <Text style={styles.paidModalName} numberOfLines={3}>
                {item.account_name || '—'}
              </Text>
            </View>
          )}
          ItemSeparatorComponent={() => <View style={styles.paidModalSeparator} />}
        />
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
  headerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  logoContainer: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: '#ff9800',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 15,
  },
  logoText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 18,
  },
  headerText: {
    flex: 1,
  },
  welcomeText: {
    fontSize: 14,
    color: '#666',
  },
  userText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
  },
  roleText: {
    fontSize: 14,
    color: '#888',
  },
  notificationIconButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#f5f5f5',
    justifyContent: 'center',
    alignItems: 'center',
    marginLeft: 12,
  },
  notificationIcon: {
    fontSize: 24,
  },
  notificationBadge: {
    position: 'absolute',
    top: -2,
    right: -2,
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: '#f44336',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 4,
  },
  notificationBadgeText: {
    color: 'white',
    fontSize: 11,
    fontWeight: 'bold',
  },
  titleContainer: {
    paddingHorizontal: 20,
    paddingVertical: 20,
  },
  dashboardTitle: {
    fontSize: 26,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 6,
  },
  dashboardSubtitle: {
    fontSize: 14,
    color: '#666',
    lineHeight: 20,
  },
  statsGrid: {
    paddingHorizontal: 20,
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statCard: {
    width: '48%',
    borderRadius: 16,
    padding: 16,
    marginBottom: 15,
    backgroundColor: 'white',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.08,
    shadowRadius: 3,
    elevation: 3,
  },
  totalCard: {
    borderLeftWidth: 4,
    borderLeftColor: '#ff9800',
  },
  pendingCard: {
    borderLeftWidth: 4,
    borderLeftColor: '#f44336',
  },
  progressCard: {
    borderLeftWidth: 4,
    borderLeftColor: '#2196F3',
  },
  paidCountCard: {
    borderLeftWidth: 4,
    borderLeftColor: '#2e7d32',
  },
  completedCard: {
    borderLeftWidth: 4,
    borderLeftColor: '#4CAF50',
  },
  noticeContainer: {
    marginHorizontal: 20,
    marginBottom: 20,
    padding: 16,
    backgroundColor: '#e8f5e9',
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: '#4CAF50',
  },
  noticeTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2e7d32',
    marginBottom: 6,
  },
  noticeSubtitle: {
    fontSize: 13,
    color: '#555',
    marginBottom: 12,
    lineHeight: 18,
  },
  noticeRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(0,0,0,0.06)',
  },
  noticeAccount: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
  },
  noticeName: {
    fontSize: 13,
    color: '#666',
    flex: 1,
    marginLeft: 8,
  },
  noticeSeeAllButton: {
    marginTop: 10,
    alignSelf: 'flex-start',
    paddingVertical: 10,
    paddingHorizontal: 16,
    backgroundColor: '#c8e6c9',
    borderRadius: 8,
  },
  noticeSeeAllText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1b5e20',
  },
  paidModalRoot: {
    flex: 1,
    backgroundColor: '#f5f5f5',
    paddingTop: 48,
  },
  paidModalHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingBottom: 14,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  paidModalHeaderText: {
    flex: 1,
    marginRight: 12,
  },
  paidModalTitle: {
    fontSize: 17,
    fontWeight: 'bold',
    color: '#2e7d32',
    marginBottom: 4,
  },
  paidModalSubtitle: {
    fontSize: 13,
    color: '#666',
    lineHeight: 18,
  },
  paidModalCloseBtn: {
    paddingVertical: 6,
    paddingHorizontal: 4,
  },
  paidModalCloseText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1976d2',
  },
  paidModalListContent: {
    padding: 16,
    paddingBottom: 32,
  },
  paidModalRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingVertical: 10,
  },
  paidModalName: {
    fontSize: 14,
    color: '#555',
    flex: 1,
    marginLeft: 10,
  },
  paidModalSeparator: {
    height: 1,
    backgroundColor: 'rgba(0,0,0,0.08)',
  },
  statLabel: {
    fontSize: 14,
    color: '#555',
  },
  statValue: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#333',
    marginVertical: 6,
  },
  statHint: {
    fontSize: 12,
    color: '#888',
  },
  actionsContainer: {
    paddingHorizontal: 20,
    paddingBottom: 20,
  },
  actionButton: {
    backgroundColor: 'white',
    borderRadius: 15,
    padding: 20,
    marginBottom: 15,
    flexDirection: 'row',
    alignItems: 'flex-start',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
    minHeight: 80,
  },
  buttonIcon: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: '#ff9800',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 15,
    flexShrink: 0,
  },
  iconText: {
    fontSize: 24,
  },
  buttonTextContainer: {
    flex: 1,
    justifyContent: 'center',
  },
  buttonTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 6,
  },
  buttonSubtitle: {
    fontSize: 14,
    color: '#666',
    lineHeight: 18,
  },
  logoutButton: {
    backgroundColor: '#f44336',
    paddingHorizontal: 20,
    paddingVertical: 14,
    borderRadius: 8,
    alignItems: 'center',
    marginHorizontal: 20,
    marginBottom: 30,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 4,
  },
  logoutText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 16,
  },
});


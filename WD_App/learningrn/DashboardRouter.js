import React, { useEffect, useState } from 'react';
import { StyleSheet, Text, View, TouchableOpacity, Image, ScrollView, Alert } from 'react-native';
import ReadAndBill from './ReadAndBill';
import Routes from './Routes';
import Receipt from './Receipt';
import Coordinates from './Coordinates';
import { receiptStorage } from './services/storage';
import DisconnectorDashboard from './DisconnectorDashboard';
import DisconnectorAssignments from './DisconnectorAssignments';
import EditBilling from './EditBilling';
import RetrieveZone from './RetrieveZone';

export default function DashboardRouter({ onLogout, userData }) {
  const roleRaw = userData?.role || userData?.role_name || '';
  const role = typeof roleRaw === 'string' ? roleRaw.toLowerCase() : '';
  const isDisconnector = role.includes('disconnect');

  const [currentScreen, setCurrentScreen] = useState(() => (isDisconnector ? 'disconnectorDashboard' : 'dashboard'));
  const [receiptData, setReceiptData] = useState(null);

  useEffect(() => {
    setCurrentScreen(isDisconnector ? 'disconnectorDashboard' : 'dashboard');
  }, [isDisconnector]);

  const userName = userData?.name || `${userData?.first_name ?? ''} ${userData?.last_name ?? ''}`.trim() || 'Water District Staff';
  const roleDisplay = roleRaw ? roleRaw.toString() : 'Staff';

  const handleReadAndBill = () => {
    setCurrentScreen('readAndBill');
  };

  const handleRetrieveZone = () => {
    setCurrentScreen('retrieveZone');
  };

  const handleRoutes = () => {
    setCurrentScreen('routes');
  };

  const handleViewReceipt = async () => {
    const last = await receiptStorage.getLastReceipt();
    if (!last) {
      // If there's no real receipt yet, open the dummy receipt
      // so the user can see the layout and test printing with logo
      setReceiptData(null);
      Alert.alert(
        'Sample Receipt',
        'No recent receipt found. Showing a sample receipt so you can test printing with the logo.'
      );
    } else {
      setReceiptData(last);
    }
    setCurrentScreen('receipt');
  };

  const handleEditReceiptFormat = () => {
    // Open the format editor directly without showing receipt preview
    setReceiptData({ openFormatEditor: true });
    setCurrentScreen('receipt');
  };

  const handleEditBilling = () => {
    setCurrentScreen('editBilling');
  };

  const handleCoordinates = () => {
    setCurrentScreen('coordinates');
  };

  const handleBackToDashboard = () => {
    setCurrentScreen(isDisconnector ? 'disconnectorDashboard' : 'dashboard');
  };

  const handleNavigate = (screen) => {
    setCurrentScreen(screen);
  };

  const handleLogout = () => {
    Alert.alert(
      'Logout',
      'Are you sure you want to logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Logout', onPress: onLogout }
      ]
    );
  };

  // Render different screens based on currentScreen state

  if (isDisconnector) {
    if (currentScreen === 'disconnectorAssignments') {
      return (
        <DisconnectorAssignments
          onBack={handleBackToDashboard}
          userData={userData}
        />
      );
    }

    return (
      <DisconnectorDashboard
        userData={userData}
        onNavigate={handleNavigate}
        onLogout={handleLogout}
      />
    );
  }

  if (currentScreen === 'readAndBill') {
    return (
      <ReadAndBill 
        onBack={handleBackToDashboard}
        onViewRoutes={handleRoutes}
      />
    );
  }

  if (currentScreen === 'routes') {
    return <Routes onBack={handleBackToDashboard} />;
  }

  if (currentScreen === 'receipt') {
    return <Receipt onBack={handleBackToDashboard} data={receiptData} />;
  }

  if (currentScreen === 'editBilling') {
    return <EditBilling onBack={handleBackToDashboard} />;
  }

  if (currentScreen === 'coordinates') {
    return <Coordinates onBack={handleBackToDashboard} />;
  }

  if (currentScreen === 'retrieveZone') {
    return <RetrieveZone onBack={handleBackToDashboard} userData={userData} />;
  }

  // Main Dashboard Screen
  return (
    <ScrollView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerContent}>
          <View style={styles.logoContainer}>
            <Image 
              source={require('./assets/KlogoC.png')} 
              style={styles.logoImage}
              resizeMode="contain"
            />
          </View>
          <View style={styles.headerText}>
            <Text style={styles.welcomeText}>Welcome Back!</Text>
            <Text style={styles.userText}>{userName}</Text>
            <Text style={styles.roleText}>{roleDisplay}</Text>
          </View>
        </View>
      </View>

      {/* Dashboard Title */}
      <View style={styles.titleContainer}>
        <Text style={styles.dashboardTitle}>Dashboard</Text>
        <Text style={styles.dashboardSubtitle}>Water District Billing System</Text>
      </View>

      {/* Main Action Buttons */}
      <View style={styles.actionsContainer}>
        <TouchableOpacity style={styles.actionButton} onPress={handleRoutes}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>🗺️</Text>
          </View>
          <View style={styles.buttonTextContainer}>
            <Text style={styles.buttonTitle}>Routes</Text>
            <Text style={styles.buttonSubtitle}>View customer locations and navigation</Text>
          </View>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionButton} onPress={handleReadAndBill}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>📊</Text>
          </View>
          <View style={styles.buttonTextContainer}>
            <Text style={styles.buttonTitle}>Read and Bill</Text>
            <Text style={styles.buttonSubtitle}>Meter reading and billing management</Text>
          </View>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionButton} onPress={handleCoordinates}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>📍</Text>
          </View>
          <View style={styles.buttonTextContainer}>
            <Text style={styles.buttonTitle}>Save Coordinates</Text>
            <Text style={styles.buttonSubtitle}>Save latitude and longitude for a consumer</Text>
          </View>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionButton} onPress={handleRetrieveZone}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>📥</Text>
          </View>
          <View style={styles.buttonTextContainer}>
            <Text style={styles.buttonTitle}>Retrieve Zone</Text>
            <Text style={styles.buttonSubtitle}>View downloaded readings by zone and reading date</Text>
          </View>
        </TouchableOpacity>

        {/* Keep Edit Receipt Format (per user request), but without View Receipt or Edit Billing on reader dashboard */}
        <TouchableOpacity style={styles.actionButton} onPress={handleEditReceiptFormat}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>🛠️</Text>
          </View>
          <View style={styles.buttonTextContainer}>
            <Text style={styles.buttonTitle}>Edit Receipt Format</Text>
            <Text style={styles.buttonSubtitle}>Open sample receipt and adjust layout</Text>
          </View>
        </TouchableOpacity>

        <TouchableOpacity style={styles.logoutButton} onPress={handleLogout}>
          <Text style={styles.logoutText}>Logout</Text>
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
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
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
  welcomeText: {
    fontSize: 16,
    color: '#666',
  },
  userText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
  },
  roleText: {
    fontSize: 14,
    color: '#666',
  },
  logoutButton: {
    backgroundColor: '#f44336',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 5,
  },
  logoutText: {
    color: 'white',
    fontWeight: 'bold',
  },
  titleContainer: {
    paddingHorizontal: 20,
    paddingVertical: 20,
  },
  dashboardTitle: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 5,
  },
  dashboardSubtitle: {
    fontSize: 16,
    color: '#666',
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
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
    minHeight: 80,
  },
  buttonIcon: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: '#2196F3',
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
    marginBottom: 5,
  },
  buttonSubtitle: {
    fontSize: 14,
    color: '#666',
    lineHeight: 18,
  },
});

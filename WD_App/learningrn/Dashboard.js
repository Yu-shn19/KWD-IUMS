import React, { useState } from 'react';
import { StyleSheet, Text, View, TouchableOpacity, Image, ScrollView, Alert } from 'react-native';
import MobilePayment from './MobilePayment';
import Coordinates from './Coordinates';

export default function Dashboard({ onLogout }) {
  const [currentScreen, setCurrentScreen] = useState('dashboard');
  const handleReadAndBill = () => {
    Alert.alert('Read and Bill', 'Opening meter reading and billing system...');
  };

  const handleMobilePayment = () => {
    setCurrentScreen('mobilePayment');
  };

  const handleCoordinates = () => {
    setCurrentScreen('coordinates');
  };

  const handleBackToDashboard = () => {
    setCurrentScreen('dashboard');
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

    if (currentScreen === 'mobilePayment') {
    return <MobilePayment onBack={handleBackToDashboard} />;
  }

  if (currentScreen === 'coordinates') {
    return <Coordinates onBack={handleBackToDashboard} />;
  }

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
            <Text style={styles.userText}>Water District Staff</Text>
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
        <TouchableOpacity style={styles.actionButton} onPress={handleReadAndBill}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>📊</Text>
          </View>
          <Text style={styles.buttonTitle}>Read and Bill</Text>
          <Text style={styles.buttonSubtitle}>Meter reading and billing management</Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionButton} onPress={handleCoordinates}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>📍</Text>
          </View>
          <Text style={styles.buttonTitle}>Save Coordinates</Text>
          <Text style={styles.buttonSubtitle}>Save latitude and longitude for a consumer</Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.actionButton} onPress={handleMobilePayment}>
          <View style={styles.buttonIcon}>
            <Text style={styles.iconText}>💳</Text>
          </View>
          <Text style={styles.buttonTitle}>Mobile Payment</Text>
          <Text style={styles.buttonSubtitle}>Process payments and transactions</Text>
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
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
  },
  userText: {
    fontSize: 14,
    color: '#666',
  },
     logoutButton: {
     backgroundColor: '#ff4757',
     paddingHorizontal: 20,
     paddingVertical: 15,
     borderRadius: 10,
     alignItems: 'center',
     marginTop: 10,
     shadowColor: '#000',
     shadowOffset: {
       width: 0,
       height: 2,
     },
     shadowOpacity: 0.25,
     shadowRadius: 3.84,
     elevation: 5,
   },
  logoutText: {
    color: 'white',
    fontSize: 14,
    fontWeight: '600',
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
    marginBottom: 30,
  },
  actionButton: {
    backgroundColor: 'white',
    borderRadius: 15,
    padding: 20,
    marginBottom: 15,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 3,
  },
  buttonIcon: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: '#2196F3',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 15,
  },
  iconText: {
    fontSize: 24,
  },
  buttonTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 5,
  },
     buttonSubtitle: {
     fontSize: 14,
     color: '#666',
   },
});

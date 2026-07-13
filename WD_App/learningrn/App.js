import {config} from './constant';

import { StatusBar } from 'expo-status-bar';
import { StyleSheet, Text, View, TextInput, TouchableOpacity, Image, Alert, ActivityIndicator, KeyboardAvoidingView, ScrollView, Platform, TouchableWithoutFeedback, Keyboard } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useState, useEffect, useMemo } from 'react';
import DashboardRouter from './DashboardRouter';
import { getApiConfig } from './config/api';
import { tokenStorage, userStorage, clearAllData } from './services/storage';
import { StorageContext } from './context/StorageService';
// import dbService from './services/dbService';

export default function App() {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [userData, setUserData] = useState(null);

  // const storageService = useMemo(() => {
  //   const storage = new dbService(config.storage.ASYNC_STORAGE);

  //   return {storage};
    
  // }, []);


  // Check authentication status on app start
  useEffect(() => {
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const token = await tokenStorage.getToken();
      const user = await userStorage.getUserData();
      
      if (token && user) {
        setUserData(user);
        setIsLoggedIn(true);
      }
    } catch (error) {
      console.error('Error checking auth status:', error);
      // Clear potentially corrupted data
      try {
        await clearAllData();
      } catch (clearError) {
        console.error('Error clearing data:', clearError);
      }
    } finally {
      // Always reset loading state, even if there's an error
      setIsLoading(false);
    }
  };

  const handleLogin = async () => {
    if (username.trim() === '' || password.trim() === '') {
      Alert.alert('Error', 'Please fill in all fields');
      return;
    }

    setIsLoading(true);
    
    try {
      // Use configured API endpoint (uses config/api.js settings)
      const apiConfig = getApiConfig();
      const url = `${apiConfig.baseURL}/reader/login`;
      const timeout = apiConfig.timeout || 15000;
      
      // Create a timeout promise
      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Request timeout. Please check your network connection and try again.')), timeout);
      });
      
      // Create the fetch promise
      const fetchPromise = fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          username: username.trim(), // API accepts both 'username' and 'email'
          password: password.trim()
        })
      });
      
      // Race between fetch and timeout
      const response = await Promise.race([fetchPromise, timeoutPromise]);
      
      const contentType = response.headers.get('content-type');
      let data;
      
      // Try to parse response as JSON first
      if (contentType && contentType.includes('application/json')) {
        data = await response.json();
      } else {
        const text = await response.text();
        console.error('Server returned non-JSON:', text.substring(0, 200));
        throw new Error('Server returned invalid response. Please check the API endpoint.');
      }
      
      // Check if response is ok or if we have an error message
      if (!response.ok) {
        const errorMessage = data?.message || data?.error || `Server error: ${response.status} ${response.statusText}`;
        console.error('Login failed:', {
          status: response.status,
          statusText: response.statusText,
          message: errorMessage,
          data: data
        });
        throw new Error(errorMessage);
      }
      
      if (data.success && data.data) {
        // Save token and user data
        await tokenStorage.saveToken(data.data.token);
        await userStorage.saveUserData(data.data.user);
        
        setUserData(data.data.user);
        setIsLoggedIn(true);
        
        // Don't show success alert - just log in silently
        console.log('Login successful:', data.data.user.name);
      } else {
        Alert.alert('Login Failed', data.message || 'Invalid username or password');
      }
    } catch (error) {
      console.error('Login error:', error);
      console.error('Error details:', {
        message: error.message,
        stack: error.stack,
        name: error.name
      });
      
      // Provide user-friendly error messages
      let errorMessage = error.message || 'Login failed. Please try again.';
      
      if (error.message.includes('timeout')) {
        errorMessage = 'The server took too long to respond. Please check your network connection.';
      } else if (error.message.includes('Network request failed') || error.message.includes('fetch')) {
        const apiConfig = getApiConfig();
        errorMessage = `Cannot connect to server. Please check:\n1. XAMPP Apache is running\n2. Your device is on the same network\n3. The server URL is correct: ${apiConfig.baseURL}`;
      } else if (error.message.includes('401') || error.message.includes('Invalid credentials')) {
        errorMessage = 'Invalid email or password. Please check:\n\nEmail: reader@gmail.com\nPassword: reader123';
      }
      
      Alert.alert('Login Error', errorMessage);
    } finally {
      // Always reset loading state
      setIsLoading(false);
    }
  };

  const handleLogout = async () => {
    try {
      const token = await tokenStorage.getToken();
      
      if (token) {
        // Use configured API endpoint
        const apiConfig = getApiConfig();
        const url = `${apiConfig.baseURL}/auth/logout`;
        
        await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`
          }
        });
      }
    } catch (error) {
      console.error('Logout API error:', error);
      // Ignore errors - we'll clear local data anyway
    } finally {
      // Clear local data regardless of API call success
      await clearAllData();
      setIsLoggedIn(false);
      setUserData(null);
      setUsername('');
      setPassword('');
    }
  };

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2196F3" />
        <Text style={styles.loadingText}>Loading...</Text>
      </View>
    );
  }

  if (isLoggedIn) {
    // return <StorageContext.Provider value={storageService}>
    //           <DashboardRouter onLogout={handleLogout} userData={userData} />
    //        </StorageContext.Provider>;
    
    return <DashboardRouter onLogout={handleLogout} userData={userData} />;
  }

  return (
    <KeyboardAvoidingView 
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 20}
    >
      <StatusBar style="auto" />
      <TouchableWithoutFeedback onPress={Keyboard.dismiss}>
        <ScrollView 
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          {/* Logo Section */}
          <View style={styles.logoContainer}>
            <View style={styles.logoCircle}>
              <Image 
                source={require('./assets/KlogoC.png')} 
                style={styles.logoImage}
                resizeMode="contain"
              />
            </View>
            <Text style={styles.appTitle}>Water District</Text>
            <Text style={styles.appSubtitle}>Billing System</Text>
          </View>

          {/* Login Form */}
          <View style={styles.formContainer}>
            <Text style={styles.loginTitle}>Login</Text>
            
            <View style={styles.inputContainer}>
              <Text style={styles.inputLabel}>Email / Username</Text>
              <TextInput
                style={styles.textInput}
                placeholder="Enter your email"
                placeholderTextColor="#999"
                value={username}
                onChangeText={setUsername}
                keyboardType="email-address"
                autoCapitalize="none"
                autoCorrect={false}
                returnKeyType="next"
                blurOnSubmit={false}
              />
            </View>

            <View style={styles.inputContainer}>
              <Text style={styles.inputLabel}>Password</Text>
              <View style={styles.passwordInputWrapper}>
                <TextInput
                  style={styles.passwordInput}
                  placeholder="Enter your password"
                  placeholderTextColor="#999"
                  value={password}
                  onChangeText={setPassword}
                  secureTextEntry={!showPassword}
                  autoCapitalize="none"
                  autoCorrect={false}
                  returnKeyType="done"
                  onSubmitEditing={handleLogin}
                />
                <TouchableOpacity
                  style={styles.eyeIcon}
                  onPress={() => setShowPassword(!showPassword)}
                  hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                  activeOpacity={0.7}
                >
                  <Ionicons
                    name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                    size={22}
                    color="#666"
                  />
                </TouchableOpacity>
              </View>
            </View>

            <TouchableOpacity 
              style={[styles.loginButton, isLoading && styles.disabledButton]} 
              onPress={handleLogin}
              disabled={isLoading}
              activeOpacity={0.8}
            >
              {isLoading ? (
                <ActivityIndicator size="small" color="white" />
              ) : (
                <Text style={styles.loginButtonText}>Login</Text>
              )}
            </TouchableOpacity>

            <TouchableOpacity style={styles.forgotPassword} activeOpacity={0.7}>
              <Text style={styles.forgotPasswordText}>Forgot Password?</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </TouchableWithoutFeedback>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: 20,
    paddingBottom: 30,
  },
  logoContainer: {
    alignItems: 'center',
    marginTop: 60,
    marginBottom: 40,
  },
  logoCircle: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: '#2196F3',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 4,
    },
    shadowOpacity: 0.3,
    shadowRadius: 4.65,
    elevation: 8,
  },
  logoImage: {
    width: 100,
    height: 100,
    borderRadius: 50,
  },
  appTitle: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 5,
  },
  appSubtitle: {
    fontSize: 16,
    color: '#666',
  },
  formContainer: {
    backgroundColor: 'white',
    borderRadius: 20,
    padding: 30,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
    elevation: 5,
  },
  loginTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
    textAlign: 'center',
    marginBottom: 30,
  },
  inputContainer: {
    marginBottom: 20,
  },
  inputLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  textInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    paddingHorizontal: 15,
    paddingVertical: 12,
    fontSize: 16,
    backgroundColor: '#fafafa',
    minHeight: 48,
  },
  passwordInputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    backgroundColor: '#fafafa',
    minHeight: 48,
  },
  passwordInput: {
    flex: 1,
    paddingHorizontal: 15,
    paddingVertical: 12,
    fontSize: 16,
  },
  eyeIcon: {
    paddingHorizontal: 12,
    paddingVertical: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loginButton: {
    backgroundColor: '#2196F3',
    borderRadius: 10,
    paddingVertical: 15,
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
  loginButtonText: {
    color: 'white',
    fontSize: 18,
    fontWeight: 'bold',
  },
  forgotPassword: {
    alignItems: 'center',
    marginTop: 20,
  },
  forgotPasswordText: {
    color: '#2196F3',
    fontSize: 16,
    textDecorationLine: 'underline',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f5f5f5',
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  disabledButton: {
    backgroundColor: '#ccc',
  },
});

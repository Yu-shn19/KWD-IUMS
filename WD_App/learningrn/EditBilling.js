import React, { useState, useRef, useCallback } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  Alert,
  Modal,
  Pressable,
  ActivityIndicator,
  FlatList,
} from 'react-native';
import { consumerAPI } from './services/api';
import { tokenStorage } from './services/storage';

const INPUT_BG = '#ffffff';
const BORDER_LIGHT = '#dddddd';
const LABEL_COLOR = '#555555';
const TEXT_DARK = '#333333';
const ACCENT_BLUE = '#2196F3';

const TYPE_OPTIONS = ['CM', 'DM', '<auto>'];
const AR_OPTIONS = ['AR', 'LRO'];
const STATUS_OPTIONS = ['Pending', 'Approved', 'Cancelled'];

const ACCT_CODE_OPTIONS = [
  { value: '', label: '— Select Acct Code —' },
  { value: '19901020', label: '19901020 Advances for Payroll' },
  { value: '19901030', label: '19901030 Advances to Special Disbursing Offices' },
  { value: '19901040', label: '19901040 Advances to Officers and Employees' },
  { value: '20102040', label: '20102040 Loans Payable-Domestic - Non-Current' },
  { value: '20401090', label: "20401090 Customer's Deposite Payable" },
  { value: '40201990', label: '40201990 Other Service Income' },
  { value: '40603990', label: '40603990 Miscellineous Income' },
];

export default function EditBilling({ onBack }) {
  const [type, setType] = useState('CM');
  const [typeDropdownVisible, setTypeDropdownVisible] = useState(false);
  const [ar, setAr] = useState('AR');
  const [arDropdownVisible, setArDropdownVisible] = useState(false);
  const [date, setDate] = useState('');
  const [bamNo, setBamNo] = useState('<auto>');
  const [account, setAccount] = useState('');
  const [accountName, setAccountName] = useState('');
  const [accountSuggestions, setAccountSuggestions] = useState([]);
  const [accountSearchLoading, setAccountSearchLoading] = useState(false);
  const [showAccountSuggestions, setShowAccountSuggestions] = useState(false);
  const [accountSearchError, setAccountSearchError] = useState(false);
  const accountSearchTimeout = useRef(null);
  const [amount, setAmount] = useState('0.00');
  const [acctCode, setAcctCode] = useState('');
  const [reference, setReference] = useState('');
  const [remarks, setRemarks] = useState('');
  const [status, setStatus] = useState('Pending');
  const [statusDropdownVisible, setStatusDropdownVisible] = useState(false);
  const [acctCodeDropdownVisible, setAcctCodeDropdownVisible] = useState(false);
  const [correctReading, setCorrectReading] = useState('0');
  const [successModalVisible, setSuccessModalVisible] = useState(false);
  const [successTitle, setSuccessTitle] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [isError, setIsError] = useState(false);

  const handleSave = async () => {
    const accountValue = (account || '').trim();
    
    if (!accountValue || accountValue === '') {
      setIsError(true);
      setSuccessTitle('Validation Error');
      setSuccessMessage('Please select an account before saving.');
      setSuccessModalVisible(true);
      return;
    }
    
    const payload = {
      type: type || 'CM',
      date: date || null,
      account: accountValue,
      account_name: accountName || null,
      bam_no: bamNo || null,
      amount: amount || '0',
      ar_type: ar || 'AR',
      acct_code: acctCode || null,
      reference: reference || null,
      remarks: remarks || null,
      status: status || 'Pending',
      correct_reading: correctReading || '0',
    };
    try {
      const token = await tokenStorage.getToken();
      const res = await consumerAPI.saveLroLedger(payload, token);
      if (res?.success) {
        setIsError(false);
        setSuccessTitle('Billing Adjustment Saved!');
        setSuccessMessage(res.message || 'Billing adjustment information saved successfully.');
        setSuccessModalVisible(true);
      } else {
        setIsError(true);
        setSuccessTitle('Error');
        setSuccessMessage(res?.message || (res?.errors ? Object.values(res.errors).flat().join(' ') : 'Save failed.'));
        setSuccessModalVisible(true);
      }
    } catch (err) {
      setIsError(true);
      setSuccessTitle('Error');
      setSuccessMessage(err.message || 'Save failed. Please try again.');
      setSuccessModalVisible(true);
    }
  };

  const handleCancel = () => {
    onBack();
  };

  const searchConsumers = useCallback(async (q) => {
    const trimmed = (q || '').trim();
    if (trimmed.length < 2) {
      setAccountSuggestions([]);
      setShowAccountSuggestions(false);
      setAccountSearchError(false);
      return;
    }
    setAccountSearchLoading(true);
    setShowAccountSuggestions(true);
    setAccountSearchError(false);
    try {
      const token = await tokenStorage.getToken();
      const res = await consumerAPI.getSuggestions(trimmed, token);
      const list = res?.data && Array.isArray(res.data) ? res.data : [];
      setAccountSuggestions(list);
    } catch (err) {
      setAccountSuggestions([]);
      setAccountSearchError(true);
    } finally {
      setAccountSearchLoading(false);
    }
  }, []);

  const onAccountChangeText = (text) => {
    setAccount(text);
    if (!text || text.trim().length === 0) setAccountName('');
    if (accountSearchTimeout.current) clearTimeout(accountSearchTimeout.current);
    if ((text || '').trim().length < 2) {
      setAccountSuggestions([]);
      setShowAccountSuggestions(false);
      return;
    }
    accountSearchTimeout.current = setTimeout(() => searchConsumers(text), 300);
  };

  const onSelectConsumer = (item) => {
    const accountNo = item.account_no || '';
    setAccount(accountNo);
    setAccountName(item.account_name || '');
    setAccountSuggestions([]);
    setShowAccountSuggestions(false);
  };

  return (
    <View style={styles.container}>
      {/* Header: title + Save / Cancel */}
      <View style={styles.header}>
        <TouchableOpacity style={styles.backBtn} onPress={onBack}>
          <Text style={styles.backBtnText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle} numberOfLines={1}>Billing Adjustment</Text>
        <View style={styles.headerActions}>
          <TouchableOpacity style={styles.cancelBtn} onPress={handleCancel}>
            <Text style={styles.cancelBtnText}>Cancel</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.saveHeaderBtn} onPress={handleSave}>
            <Text style={styles.saveHeaderBtnText}>Save</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Tabs: Billing Adjustment Memo Entry | List | Bank Payment Application */}
      <View style={styles.tabs}>
        <View style={[styles.tab, styles.tabActive]}>
          <Text style={styles.tabTextActive}>Billing Adjustment Memo Entry</Text>
        </View>
        <TouchableOpacity style={styles.tab}>
          <Text style={styles.tabText}>List</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.tab}>
          <Text style={styles.tabText}>Bank Payment Application</Text>
        </TouchableOpacity>
      </View>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled"
      >
        {/* Main form - dark teal background */}
        <View style={styles.formCard}>
          {/* Row 1: Type (dropdown) + Type (dropdown) */}
          <View style={styles.typeArRow}>
            <View style={styles.typeField}>
              <Text style={styles.label}>Type</Text>
              <TouchableOpacity
                style={styles.input}
                onPress={() => setTypeDropdownVisible(true)}
              >
                <Text style={styles.inputText}>{type || 'Select'}</Text>
                <Text style={styles.dropdownArrow}>▼</Text>
              </TouchableOpacity>
            </View>
            <View style={styles.arField}>
              <Text style={styles.label}>Type</Text>
              <TouchableOpacity
                style={styles.input}
                onPress={() => setArDropdownVisible(true)}
              >
                <Text style={styles.inputText}>{ar || 'Select'}</Text>
                <Text style={styles.dropdownArrow}>▼</Text>
              </TouchableOpacity>
            </View>
          </View>

          {/* Left column: Date, Account (below Date), BAM No., Amount */}
          <View style={styles.twoCol}>
            <View style={styles.leftCol}>
              <Row label="Date" value={date} onChangeText={setDate} placeholder="" />
              <View style={styles.row}>
                <Text style={styles.label}>Account</Text>
                <TextInput
                  style={styles.inputField}
                  value={account}
                  onChangeText={onAccountChangeText}
                  onFocus={() => account.trim().length >= 2 && searchConsumers(account)}
                  placeholder="Search by account number or name..."
                  placeholderTextColor="#bbbbbb"
                />
                {accountSearchLoading && (
                  <View style={styles.suggestionLoading}>
                    <ActivityIndicator size="small" color={ACCENT_BLUE} />
                  </View>
                )}
                {showAccountSuggestions && accountSuggestions.length > 0 && (
                  <View style={styles.suggestionList}>
                    <FlatList
                      data={accountSuggestions}
                      keyExtractor={(item) => String(item.id || item.account_no || Math.random())}
                      renderItem={({ item }) => (
                        <TouchableOpacity
                          style={styles.suggestionItem}
                          onPress={() => onSelectConsumer(item)}
                          activeOpacity={0.7}
                        >
                          <Text style={styles.suggestionItemAccount}>{item.account_no}</Text>
                          <Text style={styles.suggestionItemName}> — {item.account_name || ''}</Text>
                        </TouchableOpacity>
                      )}
                      style={styles.suggestionFlatList}
                      keyboardShouldPersistTaps="handled"
                    />
                  </View>
                )}
                {showAccountSuggestions && !accountSearchLoading && accountSuggestions.length === 0 && account.trim().length >= 2 && (
                  <View style={styles.suggestionList}>
                    <Text style={[styles.suggestionEmpty, accountSearchError && { color: '#b71c1c' }]}>
                      {accountSearchError ? 'Search failed. Check connection or API URL.' : 'No consumers found.'}
                    </Text>
                  </View>
                )}
              </View>
              <View style={styles.row}>
                <Text style={styles.label}>Name</Text>
                <TextInput
                  style={[styles.inputField, styles.nameDisplay]}
                  value={accountName}
                  placeholder="Consumer name (from selection)"
                  placeholderTextColor="#bbbbbb"
                  editable={false}
                />
              </View>
              <Row label="BAM No." value={bamNo} onChangeText={setBamNo} placeholder="<auto>" />
              <Row label="Amount" value={amount} onChangeText={setAmount} placeholder="0.00" />
            </View>

            <View style={styles.rightCol}>
              <View style={styles.row}>
                <Text style={styles.label}>Acct Code</Text>
                <TouchableOpacity
                  style={styles.input}
                  onPress={() => setAcctCodeDropdownVisible(true)}
                >
                  <Text style={styles.inputText} numberOfLines={1}>
                    {acctCode ? acctCode : '— Select Acct Code —'}
                  </Text>
                  <Text style={styles.dropdownArrow}>▼</Text>
                </TouchableOpacity>
              </View>
              <View style={styles.refRow}>
                <Text style={styles.label}>Reference</Text>
                <TextInput
                  style={[styles.inputField, styles.referenceInput]}
                  value={reference}
                  onChangeText={setReference}
                  placeholder=""
                  placeholderTextColor="#bbbbbb"
                />
              </View>
            </View>
          </View>

          {/* Bottom full width: Remarks, Status, Correct Reading */}
          <View style={styles.bottomRow}>
            <View style={styles.remarksField}>
              <Text style={styles.label}>Remarks</Text>
              <TextInput
                style={[styles.inputField, styles.remarksInput]}
                value={remarks}
                onChangeText={setRemarks}
                placeholder=""
                placeholderTextColor="#bbbbbb"
              />
            </View>
            <View style={styles.statusField}>
              <Text style={styles.label}>Status</Text>
              <TouchableOpacity
                style={styles.input}
                onPress={() => setStatusDropdownVisible(true)}
              >
                <Text style={styles.inputText}>{status || 'Pending'}</Text>
                <Text style={styles.dropdownArrow}>▼</Text>
              </TouchableOpacity>
            </View>
            <View style={styles.correctReadingField}>
              <Text style={styles.label}>Correct Reading</Text>
              <TextInput
                style={styles.inputField}
                value={correctReading}
                onChangeText={setCorrectReading}
                placeholder="0"
                placeholderTextColor="#bbbbbb"
                keyboardType="numeric"
              />
            </View>
          </View>
        </View>
      </ScrollView>

      {/* Type dropdown modal */}
      <Modal visible={typeDropdownVisible} transparent animationType="fade">
        <Pressable style={styles.modalOverlay} onPress={() => setTypeDropdownVisible(false)}>
          <View style={styles.dropdownModal}>
            {TYPE_OPTIONS.map((opt) => (
              <TouchableOpacity
                key={opt}
                style={[styles.dropdownItem, type === opt && styles.dropdownItemActive]}
                onPress={() => {
                  setType(opt);
                  setTypeDropdownVisible(false);
                }}
              >
                <Text style={[styles.dropdownItemText, type === opt && styles.dropdownItemTextActive]}>{opt}</Text>
              </TouchableOpacity>
            ))}
          </View>
        </Pressable>
      </Modal>

      {/* Status dropdown modal */}
      <Modal visible={statusDropdownVisible} transparent animationType="fade">
        <Pressable style={styles.modalOverlay} onPress={() => setStatusDropdownVisible(false)}>
          <View style={styles.dropdownModal}>
            {STATUS_OPTIONS.map((opt) => (
              <TouchableOpacity
                key={opt}
                style={[styles.dropdownItem, status === opt && styles.dropdownItemActive]}
                onPress={() => {
                  setStatus(opt);
                  setStatusDropdownVisible(false);
                }}
              >
                <Text style={[styles.dropdownItemText, status === opt && styles.dropdownItemTextActive]}>{opt}</Text>
              </TouchableOpacity>
            ))}
          </View>
        </Pressable>
      </Modal>

      {/* AR dropdown modal */}
      <Modal visible={arDropdownVisible} transparent animationType="fade">
        <Pressable style={styles.modalOverlay} onPress={() => setArDropdownVisible(false)}>
          <View style={styles.dropdownModal}>
            {AR_OPTIONS.map((opt) => (
              <TouchableOpacity
                key={opt}
                style={[styles.dropdownItem, ar === opt && styles.dropdownItemActive]}
                onPress={() => {
                  setAr(opt);
                  setArDropdownVisible(false);
                }}
              >
                <Text style={[styles.dropdownItemText, ar === opt && styles.dropdownItemTextActive]}>{opt}</Text>
              </TouchableOpacity>
            ))}
          </View>
        </Pressable>
      </Modal>

      {/* Acct Code dropdown modal */}
      <Modal visible={acctCodeDropdownVisible} transparent animationType="fade">
        <Pressable style={styles.modalOverlay} onPress={() => setAcctCodeDropdownVisible(false)}>
          <Pressable onPress={(e) => e.stopPropagation()}>
            <View style={[styles.dropdownModal, styles.acctCodeDropdownModal]}>
              <ScrollView style={styles.acctCodeDropdownScroll} keyboardShouldPersistTaps="handled">
              {ACCT_CODE_OPTIONS.map((opt) => (
                <TouchableOpacity
                  key={opt.value || 'empty'}
                  style={[styles.dropdownItem, acctCode === opt.value && styles.dropdownItemActive]}
                  onPress={() => {
                    setAcctCode(opt.value);
                    setReference(opt.value ? (opt.label.replace(/^\d+\s*/, '').trim() || '') : '');
                    setAcctCodeDropdownVisible(false);
                  }}
                >
                  <Text style={[styles.dropdownItemText, acctCode === opt.value && styles.dropdownItemTextActive]} numberOfLines={2}>
                    {opt.label}
                  </Text>
                </TouchableOpacity>
              ))}
              </ScrollView>
            </View>
          </Pressable>
        </Pressable>
      </Modal>

      {/* Success Modal */}
      <Modal visible={successModalVisible} transparent animationType="fade">
        <Pressable style={styles.modalOverlay} onPress={() => setSuccessModalVisible(false)}>
          <Pressable onPress={(e) => e.stopPropagation()}>
            <View style={styles.successModalContent}>
              <View style={[styles.successIcon, isError && styles.errorIcon]}>
                <Text style={[styles.successCheckmark, isError && styles.errorCheckmark]}>
                  {isError ? '✕' : '✓'}
                </Text>
              </View>
              <Text style={styles.successTitle}>{successTitle}</Text>
              <Text style={styles.successMessage}>{successMessage}</Text>
              <TouchableOpacity
                style={styles.successButton}
                onPress={() => setSuccessModalVisible(false)}
              >
                <Text style={styles.successButtonText}>OK</Text>
              </TouchableOpacity>
            </View>
          </Pressable>
        </Pressable>
      </Modal>
    </View>
  );
}

function Row({ label, value, onChangeText, placeholder }) {
  return (
    <View style={styles.row}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        style={styles.inputField}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor="#bbbbbb"
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingTop: 50,
    paddingHorizontal: 12,
    paddingBottom: 10,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: BORDER_LIGHT,
  },
  backBtn: {
    paddingVertical: 8,
    paddingRight: 8,
  },
  backBtnText: {
    color: TEXT_DARK,
    fontSize: 16,
    fontWeight: '600',
  },
  headerTitle: {
    flex: 1,
    color: TEXT_DARK,
    fontSize: 18,
    fontWeight: 'bold',
    marginLeft: 8,
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  cancelBtn: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 6,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: BORDER_LIGHT,
  },
  cancelBtnText: {
    color: TEXT_DARK,
    fontSize: 14,
    fontWeight: '600',
  },
  saveHeaderBtn: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 6,
    backgroundColor: ACCENT_BLUE,
  },
  saveHeaderBtnText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
  },
  tabs: {
    flexDirection: 'row',
    backgroundColor: '#ffffff',
    paddingHorizontal: 12,
    paddingBottom: 0,
    borderBottomWidth: 1,
    borderBottomColor: BORDER_LIGHT,
  },
  tab: {
    paddingVertical: 12,
    paddingHorizontal: 14,
    marginRight: 4,
  },
  tabActive: {
    borderBottomWidth: 3,
    borderBottomColor: ACCENT_BLUE,
  },
  tabText: {
    fontSize: 14,
    color: '#666666',
    fontWeight: '500',
  },
  tabTextActive: {
    fontSize: 14,
    color: TEXT_DARK,
    fontWeight: '700',
  },
  scroll: {
    flex: 1,
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 40,
    backgroundColor: '#ffffff',
  },
  formCard: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: BORDER_LIGHT,
    borderRadius: 8,
    padding: 16,
  },
  typeArRow: {
    flexDirection: 'row',
    marginBottom: 14,
    gap: 12,
  },
  typeField: {
    flex: 1,
  },
  arField: {
    flex: 1,
  },
  twoCol: {
    flexDirection: 'row',
    gap: 16,
    alignItems: 'flex-start',
  },
  leftCol: {
    flex: 1,
  },
  rightCol: {
    flex: 1,
  },
  row: {
    marginBottom: 14,
    position: 'relative',
  },
  suggestionLoading: {
    position: 'absolute',
    right: 12,
    top: 38,
  },
  suggestionList: {
    marginTop: 4,
    maxHeight: 220,
    borderWidth: 1,
    borderColor: BORDER_LIGHT,
    borderRadius: 6,
    backgroundColor: '#fff',
    overflow: 'hidden',
  },
  suggestionFlatList: {
    maxHeight: 216,
  },
  suggestionItem: {
    flexDirection: 'row',
    paddingVertical: 12,
    paddingHorizontal: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  suggestionItemAccount: {
    fontWeight: '600',
    color: TEXT_DARK,
    fontSize: 14,
  },
  suggestionItemName: {
    color: '#666',
    fontSize: 14,
    flex: 1,
  },
  suggestionEmpty: {
    padding: 12,
    color: '#666',
    fontSize: 14,
  },
  refRow: {
    marginBottom: 14,
  },
  bottomRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
    flexWrap: 'wrap',
  },
  remarksField: {
    flex: 1,
    minWidth: '45%',
  },
  statusField: {
    width: 120,
  },
  correctReadingField: {
    width: 100,
  },
  remarksInput: {
    minHeight: 44,
  },
  label: {
    fontSize: 14,
    color: LABEL_COLOR,
    marginBottom: 6,
    fontWeight: '500',
  },
  input: {
    backgroundColor: INPUT_BG,
    borderWidth: 1,
    borderColor: BORDER_LIGHT,
    borderRadius: 6,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
    color: TEXT_DARK,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  inputText: {
    fontSize: 15,
    color: TEXT_DARK,
  },
  dropdownArrow: {
    fontSize: 10,
    color: '#666666',
  },
  inputField: {
    backgroundColor: INPUT_BG,
    borderWidth: 1,
    borderColor: BORDER_LIGHT,
    borderRadius: 6,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
    color: TEXT_DARK,
  },
  referenceInput: {
    minHeight: 44,
  },
  nameDisplay: {
    backgroundColor: '#f8f9fa',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  dropdownModal: {
    backgroundColor: '#fff',
    borderRadius: 8,
    minWidth: 200,
    padding: 4,
  },
  acctCodeDropdownModal: {
    maxHeight: '70%',
    minWidth: 280,
  },
  acctCodeDropdownScroll: {
    maxHeight: 400,
  },
  dropdownItem: {
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  dropdownItemActive: {
    backgroundColor: '#E8F0FE',
  },
  dropdownItemText: {
    fontSize: 15,
    color: TEXT_DARK,
  },
  dropdownItemTextActive: {
    color: ACCENT_BLUE,
    fontWeight: '600',
  },
  successModalContent: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 32,
    maxWidth: 400,
    width: '90%',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 20,
    elevation: 8,
  },
  successIcon: {
    width: 80,
    height: 80,
    borderRadius: 40,
    borderWidth: 3,
    borderColor: '#4CAF50',
    backgroundColor: '#ffffff',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
  },
  successCheckmark: {
    fontSize: 50,
    color: '#4CAF50',
    fontWeight: 'bold',
  },
  errorIcon: {
    borderColor: '#f44336',
  },
  errorCheckmark: {
    color: '#f44336',
  },
  successTitle: {
    fontSize: 24,
    fontWeight: '700',
    color: '#333333',
    marginBottom: 8,
    textAlign: 'center',
  },
  successMessage: {
    fontSize: 16,
    color: '#666666',
    marginBottom: 24,
    textAlign: 'center',
  },
  successButton: {
    backgroundColor: ACCENT_BLUE,
    paddingVertical: 12,
    paddingHorizontal: 32,
    borderRadius: 6,
    minWidth: 120,
  },
  successButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '600',
    textAlign: 'center',
  },
});

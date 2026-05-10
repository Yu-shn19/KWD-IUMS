import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  ScrollView,
  Alert,
  ActivityIndicator,
  FlatList,
  Modal,
  RefreshControl,
  TextInput,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Print from 'expo-print';
import { Asset } from 'expo-asset';
import * as FileSystem from 'expo-file-system';
import { readerDownloadedReadingsAPI, routesAPI } from './services/api';
import { tokenStorage, userStorage, routesStorage, printerStorage, receiptLogoStorage, receiptFormatStorage } from './services/storage';
import { isSupported as btSupported, printReceiptEscPos } from './services/bluetoothPrinter';
import PrinterSelector from './components/PrinterSelector';

export default function RetrieveZone({ onBack, userData }) {
  const readerId = userData?.id ?? userData?.reader_id ?? null;
  const [zones, setZones] = useState([]);
  const [readingDates, setReadingDates] = useState([]);
  const [selectedZone, setSelectedZone] = useState(null);
  const [selectedDate, setSelectedDate] = useState(null);
  const [list, setList] = useState([]);
  const [loadingFilters, setLoadingFilters] = useState(true);
  const [loadingList, setLoadingList] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [showZonePicker, setShowZonePicker] = useState(false);
  const [showDatePicker, setShowDatePicker] = useState(false);
  const [filtersMessage, setFiltersMessage] = useState(null);
  const [assignedSchedules, setAssignedSchedules] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [editingItem, setEditingItem] = useState(null);
  const [editCurrentReading, setEditCurrentReading] = useState('');
  const [showEditModal, setShowEditModal] = useState(false);
  const [savingEdit, setSavingEdit] = useState(false);
  const [calendarMonth, setCalendarMonth] = useState(() => new Date());
  const allZonesRef = useRef([]);
  const [showPrinterSelector, setShowPrinterSelector] = useState(false);
  const [pendingReceiptData, setPendingReceiptData] = useState(null);

  const isRouteNotFound = (err) => {
    const msg = (err?.message || '').toLowerCase();
    return msg.includes('could not be found') || msg.includes('404') || msg.includes('not found');
  };

  const getDateStr = (item) => {
    const d = item.bill_date ?? item.reading_date ?? item.due_date ?? item.reading_date_date ?? item.dueDate ?? null;
    if (!d) return null;
    if (typeof d === 'string') return d.split('T')[0].substring(0, 10);
    if (d && typeof d === 'object' && d.getMonth) return d.toISOString().split('T')[0];
    return String(d).substring(0, 10);
  };

  const getZoneStr = (item) => {
    const z = item.zone ?? item.zone_name ?? item.zone_id ?? '';
    return z != null && z !== '' ? String(z).trim() : null;
  };

  const getConsumerName = (item) => {
    return (item.account_name ?? item.name ?? item.consumer_name ?? item.accountName ?? '').toString().trim().toLowerCase();
  };

  const filteredList = searchQuery.trim() === ''
    ? list
    : list.filter((item) => getConsumerName(item).includes(searchQuery.trim().toLowerCase()));

  const loadFilters = useCallback(async () => {
    setFiltersMessage(null);
    setAssignedSchedules([]);
    if (!readerId) {
      setLoadingFilters(false);
      return;
    }
    try {
      const token = await tokenStorage.getToken();
      // Primary: get ALL zones and ALL reading dates (including previous days) from downloaded_readings
      const { zones: z, reading_dates: d } = await readerDownloadedReadingsAPI.getFilters(readerId, token);
      const zonesFromApi = Array.isArray(z) ? z : [];
      const datesFromApi = Array.isArray(d) ? d.sort().reverse() : [];
      if (zonesFromApi.length > 0 || datesFromApi.length > 0) {
        setZones(zonesFromApi.length > 0 ? zonesFromApi : ['—']);
        allZonesRef.current = zonesFromApi.length > 0 ? zonesFromApi : [];
        setReadingDates(datesFromApi.length > 0 ? datesFromApi : ['—']);
        setAssignedSchedules([]);
        setFiltersMessage('Data from downloaded_readings (arrears from meter reading schedules).');
        setLoadingFilters(false);
        return;
      }

      // Fallback: use assigned schedules from getRoutes (current month if that's all the API returns)
      const response = await routesAPI.getRoutes({ reader_id: readerId }, token);
      const list = Array.isArray(response?.schedules) ? response.schedules : Array.isArray(response?.data) ? response.data : [];

      if (list.length > 0) {
        const zoneSet = new Set();
        const dateSet = new Set();
        list.forEach((item) => {
          const zone = getZoneStr(item);
          if (zone) zoneSet.add(zone);
          const date = getDateStr(item);
          if (date) dateSet.add(date);
        });
        let zonesFromRoutes = [...zoneSet].sort();
        let datesFromRoutes = [...dateSet].sort().reverse();
        if (zonesFromRoutes.length === 0) zonesFromRoutes = ['—'];
        if (datesFromRoutes.length === 0) datesFromRoutes = ['—'];
        setZones(zonesFromRoutes);
        allZonesRef.current = zonesFromRoutes.filter((z) => z !== '—');
        setReadingDates(datesFromRoutes);
        setAssignedSchedules(list);
        setFiltersMessage('Showing zones and dates from your assigned routes.');
        setLoadingFilters(false);
        return;
      }

      setZones(['—']);
      setReadingDates(['—']);
    } catch (error) {
      console.error('RetrieveZone loadFilters:', error);
      setZones([]);
      setReadingDates([]);
      setAssignedSchedules([]);
      try {
        const token = await tokenStorage.getToken();
        const response = await routesAPI.getRoutes({ reader_id: readerId }, token);
        const list = Array.isArray(response?.schedules) ? response.schedules : Array.isArray(response?.data) ? response.data : [];
        if (list.length === 0) {
          const cached = await routesStorage.getRoutes();
          const cachedList = Array.isArray(cached) ? cached : [];
          if (cachedList.length > 0) {
            const zoneSet = new Set();
            const dateSet = new Set();
            cachedList.forEach((item) => {
              const zone = getZoneStr(item);
              if (zone) zoneSet.add(zone);
              const date = getDateStr(item);
              if (date) dateSet.add(date);
            });
            let zonesFromRoutes = [...zoneSet].sort();
            let datesFromRoutes = [...dateSet].sort().reverse();
            if (zonesFromRoutes.length === 0) zonesFromRoutes = ['—'];
            if (datesFromRoutes.length === 0) datesFromRoutes = ['—'];
            setZones(zonesFromRoutes);
            allZonesRef.current = zonesFromRoutes.filter((z) => z !== '—');
            setReadingDates(datesFromRoutes);
            setAssignedSchedules(cachedList);
            setFiltersMessage('Showing all zones and dates from your assigned routes (cached).');
          } else {
            if (isRouteNotFound(error)) {
              setFiltersMessage('Retrieve Zone is not set up on the server yet. Contact your admin to add the reader API (downloaded_readings).');
            } else {
              Alert.alert('Error', error?.message || 'Failed to load zones and dates.');
            }
          }
        } else {
          const zoneSet = new Set();
          const dateSet = new Set();
          list.forEach((item) => {
            const zone = getZoneStr(item);
            if (zone) zoneSet.add(zone);
            const date = getDateStr(item);
            if (date) dateSet.add(date);
          });
          let zonesFromRoutes = [...zoneSet].sort();
          let datesFromRoutes = [...dateSet].sort().reverse();
          if (zonesFromRoutes.length === 0) zonesFromRoutes = ['—'];
          if (datesFromRoutes.length === 0) datesFromRoutes = ['—'];
          setZones(zonesFromRoutes);
          allZonesRef.current = zonesFromRoutes.filter((z) => z !== '—');
          setReadingDates(datesFromRoutes);
          setAssignedSchedules(list);
          setFiltersMessage('Showing zones and dates from your assigned routes.');
        }
      } catch (fallbackError) {
        console.error('RetrieveZone fallback getRoutes:', fallbackError);
        setZones([]);
        setReadingDates([]);
        setAssignedSchedules([]);
        if (isRouteNotFound(error)) {
          setFiltersMessage('Retrieve Zone is not set up on the server yet. Contact your admin to add the reader API (downloaded_readings).');
        } else {
          Alert.alert('Error', error?.message || 'Failed to load zones and dates.');
        }
      }
    } finally {
      setLoadingFilters(false);
    }
  }, [readerId]);

  const loadList = useCallback(async () => {
    if (!readerId || !selectedZone || !selectedDate) {
      setList([]);
      return;
    }
    if (assignedSchedules.length > 0) {
      const filtered = assignedSchedules.filter((item) => {
        const zone = getZoneStr(item);
        const date = getDateStr(item);
        const zoneMatch = selectedZone === '—' || zone === selectedZone;
        const dateMatch = selectedDate === '—' || date === selectedDate;
        return zoneMatch && dateMatch;
      });
      setList(filtered);
      return;
    }
    setLoadingList(true);
    try {
      const token = await tokenStorage.getToken();
      const { data } = await readerDownloadedReadingsAPI.getList(
        readerId,
        selectedZone,
        selectedDate,
        token
      );
      setList(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('RetrieveZone loadList:', error);
      setList([]);
      if (!isRouteNotFound(error)) {
        Alert.alert('Error', error?.message || 'Failed to load readings.');
      }
    } finally {
      setLoadingList(false);
    }
  }, [readerId, selectedZone, selectedDate, assignedSchedules]);

  useEffect(() => {
    loadFilters();
  }, [loadFilters]);

  // When user selects a date, load zones that were assigned on that date (from downloaded_readings)
  useEffect(() => {
    if (!readerId || !selectedDate) {
      if (!selectedDate && allZonesRef.current.length > 0) {
        setZones(allZonesRef.current);
      }
      return;
    }
    let cancelled = false;
    const loadZonesForDate = async () => {
      try {
        const token = await tokenStorage.getToken();
        const { zones: zonesForDate } = await readerDownloadedReadingsAPI.getFilters(readerId, token, selectedDate);
        if (!cancelled && Array.isArray(zonesForDate)) {
          setZones(zonesForDate.length > 0 ? zonesForDate : ['—']);
          setSelectedZone((prev) => {
            if (!prev) return prev;
            return zonesForDate.includes(prev) ? prev : null;
          });
        }
      } catch (err) {
        if (!cancelled) {
          setZones(allZonesRef.current.length > 0 ? allZonesRef.current : ['—']);
        }
      }
    };
    loadZonesForDate();
    return () => { cancelled = true; };
  }, [readerId, selectedDate]);

  useEffect(() => {
    loadList();
  }, [loadList]);

  const onRefresh = async () => {
    setRefreshing(true);
    await loadFilters();
    if (selectedZone && selectedDate) await loadList();
    setRefreshing(false);
  };

  const openZonePicker = () => setShowZonePicker(true);
  const openDatePicker = () => {
    if (selectedDate) {
      const [y, m] = selectedDate.split('-').map(Number);
      setCalendarMonth(new Date(y, (m || 1) - 1, 1));
    } else {
      setCalendarMonth(new Date());
    }
    setShowDatePicker(true);
  };

  const selectZone = (zone) => {
    setSelectedZone(zone);
    setShowZonePicker(false);
  };

  const selectDate = (dateStr) => {
    setSelectedDate(dateStr);
    setShowDatePicker(false);
  };

  const formatCalendarDate = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  const getCalendarDays = () => {
    const year = calendarMonth.getFullYear();
    const month = calendarMonth.getMonth();
    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);
    const startDay = first.getDay();
    const daysInMonth = last.getDate();
    const readingDatesSet = new Set(readingDates.map((d) => String(d).substring(0, 10)));
    const rows = [];
    let row = [];
    for (let i = 0; i < startDay; i++) row.push({ key: `empty-${i}`, empty: true });
    for (let day = 1; day <= daysInMonth; day++) {
      const d = new Date(year, month, day);
      const dateStr = formatCalendarDate(d);
      row.push({
        key: dateStr,
        dateStr,
        day,
        hasData: readingDatesSet.has(dateStr),
        empty: false,
      });
      if (row.length === 7) {
        rows.push(row);
        row = [];
      }
    }
    if (row.length) {
      while (row.length < 7) row.push({ key: `empty-end-${row.length}`, empty: true });
      rows.push(row);
    }
    return rows;
  };

  const prevCalendarMonth = () => {
    setCalendarMonth((d) => new Date(d.getFullYear(), d.getMonth() - 1, 1));
  };

  const nextCalendarMonth = () => {
    setCalendarMonth((d) => new Date(d.getFullYear(), d.getMonth() + 1, 1));
  };

  const calendarMonthLabel = `${calendarMonth.toLocaleString('default', { month: 'long' })} ${calendarMonth.getFullYear()}`;

  // Allow editing current reading whenever a zone, date, and list are selected (any date, not only latest)
  const canEdit = Boolean(selectedZone && selectedDate && list.length > 0);

  const openEditModal = (item) => {
    const prev = item.previous_reading ?? item.previousReading ?? 0;
    const curr = item.current_reading ?? item.reading ?? '';
    setEditingItem(item);
    setEditCurrentReading(String(curr !== '' && curr != null ? curr : ''));
    setShowEditModal(true);
  };

  const closeEditModal = () => {
    setShowEditModal(false);
    setEditingItem(null);
    setEditCurrentReading('');
  };

  // Build receipt data in same shape and format as Read and Bill (generateReceipt + buildEscPosReceipt)
  const buildReceiptDataFromItem = (item) => {
    const account = item.account_number ?? item.account_no ?? item.accountNumber ?? '—';
    const name = item.account_name ?? item.name ?? item.consumer_name ?? '—';
    const zone = item.zone ?? selectedZone ?? '081';
    const readingDateRaw = item.bill_date ?? item.reading_date ?? item.reading_date_date ?? selectedDate ?? '';
    const parseDate = (d) => {
      if (!d) return null;
      const str = typeof d === 'string' ? d.split('T')[0].substring(0, 10) : '';
      if (!str || str === '—') return null;
      return new Date(str + 'T12:00:00');
    };
    const formatDateLocale = (date) => {
      if (!date || !(date instanceof Date) || isNaN(date.getTime())) return '—';
      return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    };
    const readingDateParsed = parseDate(readingDateRaw);
    const readingDate = formatDateLocale(readingDateParsed || new Date());
    let dueDateFormatted;
    const dueDateRaw = item.due_date ?? item.dueDate ?? null;
    if (dueDateRaw) {
      const dueParsed = parseDate(dueDateRaw);
      dueDateFormatted = formatDateLocale(dueParsed);
    } else {
      const due = readingDateParsed ? new Date(readingDateParsed) : new Date();
      due.setDate(due.getDate() + 14);
      dueDateFormatted = formatDateLocale(due);
    }
    const prevReading = Number(item.previous_reading ?? item.previousReading ?? 0);
    const currReading = Number(item.current_reading ?? item.reading ?? 0);
    const consumption = item.consumption != null ? Number(item.consumption) : (currReading >= prevReading ? currReading - prevReading : 0);
    // Same rule as Read and Bill: high consumption when current > previous AND consumption >= 10
    const isHighConsumption = currReading > prevReading && consumption >= 10;
    const currentBillNum = item.current_bill != null ? Number(item.current_bill) : 0;
    const meterMaintenanceCharge = 20.00;
    const totalCurrent = currentBillNum > 0 ? currentBillNum : meterMaintenanceCharge;
    const currentBillOnly = totalCurrent >= meterMaintenanceCharge ? totalCurrent - meterMaintenanceCharge : 0;
    const arrearsNum = Math.max(0, parseFloat(item.arrears ?? 0));
    const others = 0.00;
    const totalBillNum = totalCurrent + arrearsNum + others;
    const surchargeNum = parseFloat((currentBillOnly * 0.10).toFixed(2));
    const totalWithSurchargeNum = totalBillNum + surchargeNum;
    const readerName = userData?.name || userData?.full_name || userData?.username || 'Unknown Reader';
    return {
      periodCovered: `${readingDate} / ${dueDateFormatted}`,
      zone: String(zone),
      consumerType: item.category || 'Residential',
      sequence: item.sedr_number ?? item.sequence ?? '2982',
      accountNumber: String(account),
      customer: {
        name: name || 'Unknown Customer',
        address: item.address || 'No Address',
        meterNumber: item.meter_number ?? item.meterNumber ?? 'N/A',
      },
      readingDate,
      dueDate: dueDateFormatted,
      readings: {
        current: currReading,
        previous: prevReading,
        consumption,
        isHighConsumption,
      },
      billing: {
        currentBill: currentBillOnly.toFixed(2),
        meterMaintenanceCharge: meterMaintenanceCharge.toFixed(2),
        totalCurrent: totalCurrent.toFixed(2),
        arrears: arrearsNum.toFixed(2),
        others: others.toFixed(2),
        totalBill: totalBillNum.toFixed(2),
        surcharge: surchargeNum.toFixed(2),
        totalWithSurcharge: totalWithSurchargeNum.toFixed(2),
      },
      meterReader: readerName,
    };
  };

  const printBill = async (item) => {
    if (!item) return;
    try {
      const receiptData = buildReceiptDataFromItem(item);

      if (btSupported()) {
        const savedPrinter = await printerStorage.getPrinter();
        if (savedPrinter && savedPrinter.inner_mac_address) {
          const ok = await printReceiptEscPos(receiptData, savedPrinter);
          if (!ok) {
            Alert.alert(
              'Print Failed',
              'Could not print to the saved printer. Would you like to select a different printer?',
              [
                { text: 'Cancel', style: 'cancel' },
                { text: 'Select Printer', onPress: () => { setPendingReceiptData(receiptData); setShowPrinterSelector(true); } },
              ]
            );
          }
        } else {
          setPendingReceiptData(receiptData);
          setShowPrinterSelector(true);
        }
      } else {
        Alert.alert(
          'Bluetooth Printing Not Ready',
          'Install and rebuild to enable thermal printer:\n1) npm i react-native-thermal-receipt-printer\n2) npx expo prebuild, then make a dev build\n3) Open the app in that build and try again.'
        );
        // Fallback: system print (HTML) – same format as Read and Bill, use logo from Edit Receipt Format
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
        const rd = receiptData;
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

      <div class="row">Period Covered: ${rd.periodCovered}</div>
      <div class="row">Zone : ${rd.zone} &nbsp;&nbsp;&nbsp;&nbsp; Consumer type: ${rd.consumerType}</div>
      <div class="row">Sequence : ${rd.sequence}</div>
      <div class="row">Acct No. : ${rd.accountNumber}</div>
      <div class="row">Name: ${rd.customer.name}</div>
      <div class="row">Address : ${rd.customer.address}</div>
      <div class="row">Meter Number/Size : ${rd.customer.meterNumber}</div>

      <div class="sep"></div>

      <div class="row">Reading Date :${rd.readingDate}</div>
      <div class="row">Due Date :${rd.dueDate}</div>

      <div class="sep"></div>

      <div class="row">Present Reading : ${rd.readings.current}</div>
      <div class="row">Previous Reading : ${rd.readings.previous}</div>
      <div class="row">Cubic Meter Used : ${rd.readings.consumption} High Consumption</div>

      <div class="sep"></div>

      <div class="row">Current Bill : ${rd.billing.currentBill}</div>
      <div class="row">Meter Maintenance Charge : ${rd.billing.meterMaintenanceCharge}</div>
      <div class="row">TOTAL CURRENT : ${rd.billing.totalCurrent}</div>


      <div class="row">Arrears : ${rd.billing.arrears}</div>
      <div class="row">Others : ${rd.billing.others}</div>

      <div class="sep"></div>

      <div class="total">TOTAL BILL: ${rd.billing.totalBill}</div>
      
      <div class="row">IF UNPAID AT HWD OFFICE</div>
      <div class="row">After: ${rd.dueDate}</div>
      <div class="row">Surcharge : ${rd.billing.surcharge}</div>
      <div class="total">TOTAL WITH SURCHARGE : ${rd.billing.totalWithSurcharge}</div>

      <div class="sep"></div>

      <div class="row">Notice:</div>
      <div class="row">1. Failure to pay on the specified date of Disconnection Date, we will be constrained to cut off your services connection, disconnection of your water service.</div>
      <div class="row">2. Please disregard the Notice of Disconnection if account has been paid in full.</div>
      <div class="row">3. If service is discontinued, total amount due plus P200.00 reconnection fee.</div>

      <div class="sep"></div>

      <div class="row">Meter Reader : ${rd.meterReader}</div>
      <div class="account">${rd.accountNumber}</div>
    </div>
  </body>
</html>`;
        await Print.printAsync({ html });
      }
    } catch (e) {
      console.error('RetrieveZone print error', e);
      Alert.alert('Print Error', e?.message || 'Unable to print.');
    }
  };

  const saveEditedReading = async () => {
    if (!editingItem || !readerId) return;
    const prev = Number(editingItem.previous_reading ?? editingItem.previousReading ?? 0);
    const curr = parseInt(editCurrentReading.trim(), 10);
    if (isNaN(curr) || curr < prev) {
      Alert.alert('Invalid reading', `Current reading must be a number greater than or equal to previous reading (${prev}).`);
      return;
    }
    setSavingEdit(true);
    try {
      const token = await tokenStorage.getToken();
      const readingDate = selectedDate || editingItem.bill_date || editingItem.reading_date;
      const dateStr = typeof readingDate === 'string' ? readingDate : (readingDate && readingDate.split) ? readingDate : new Date().toISOString().split('T')[0];
      await readerDownloadedReadingsAPI.updateReading(readerId, editingItem.id || editingItem.schedule_id, curr, dateStr, token);
      Alert.alert('Saved', 'Reading updated successfully.');
      closeEditModal();
      loadList();
    } catch (err) {
      console.error('RetrieveZone save reading:', err);
      Alert.alert('Error', err?.message || 'Failed to update reading.');
    } finally {
      setSavingEdit(false);
    }
  };

  const renderReadingItem = ({ item, index }) => {
    const account = item.account_number ?? item.account_no ?? item.accountNumber ?? '—';
    const name = item.account_name ?? item.name ?? item.consumer_name ?? '—';
    const billDate = item.bill_date ?? '—';
    const previousReading = item.previous_reading ?? '—';
    const currentReading = item.current_reading ?? item.reading ?? '—';
    const zone = item.zone ?? selectedZone ?? '—';
    const isEditable = canEdit && (item.id != null || item.schedule_id != null);
    const CardWrapper = isEditable ? TouchableOpacity : View;
    const cardProps = isEditable ? { onPress: () => openEditModal(item), activeOpacity: 0.7 } : {};
    return (
      <CardWrapper style={styles.card} {...cardProps}>
        <View style={styles.cardRow}>
          <Text style={styles.cardLabel}>Account</Text>
          <Text style={styles.cardValue}>{account}</Text>
        </View>
        <View style={styles.cardRow}>
          <Text style={styles.cardLabel}>Name</Text>
          <Text style={styles.cardValue}>{name}</Text>
        </View>
        <View style={styles.cardRow}>
          <Text style={styles.cardLabel}>Bill date</Text>
          <Text style={styles.cardValue}>{billDate}</Text>
        </View>
        <View style={styles.cardRow}>
          <Text style={styles.cardLabel}>Previous reading</Text>
          <Text style={styles.cardValue}>{previousReading}</Text>
        </View>
        <View style={styles.cardRow}>
          <Text style={styles.cardLabel}>Current reading</Text>
          <Text style={styles.cardValue}>{currentReading}</Text>
        </View>
        <View style={styles.cardRow}>
          <Text style={styles.cardLabel}>Zone</Text>
          <Text style={styles.cardValue}>{zone}</Text>
        </View>
        {isEditable && (
          <View style={styles.cardEditHint}>
            <Ionicons name="create-outline" size={14} color="#2196F3" />
            <Text style={styles.cardEditHintText}>Tap to adjust reading</Text>
          </View>
        )}
        <TouchableOpacity
          style={styles.cardPrintBtn}
          onPress={() => printBill(item)}
          activeOpacity={0.7}
        >
          <Ionicons name="print-outline" size={18} color="#2196F3" />
          <Text style={styles.cardPrintBtnText}>Print bill</Text>
        </TouchableOpacity>
      </CardWrapper>
    );
  };

  if (!readerId) {
    return (
      <View style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton} onPress={onBack}>
            <Text style={styles.backButtonText}>← Back</Text>
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Retrieve Zone</Text>
        </View>
        <View style={styles.emptyState}>
          <Text style={styles.emptyStateText}>Reader account not found. Please log in again.</Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={onBack}>
          <Text style={styles.backButtonText}>← Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Retrieve Zone</Text>
        <Text style={styles.headerSubtitle}>Downloaded readings by zone and date</Text>
      </View>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.scrollContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={['#2196F3']} />
        }
      >
        {/* Filters */}
        <View style={styles.filters}>
          <Text style={styles.filterLabel}>Select zone</Text>
          {loadingFilters ? (
            <ActivityIndicator size="small" color="#2196F3" style={styles.filterLoader} />
          ) : (
            <TouchableOpacity style={styles.pickerButton} onPress={openZonePicker}>
              <Text style={styles.pickerButtonText} numberOfLines={1}>
                {selectedZone ?? 'Choose zone'}
              </Text>
              <Ionicons name="chevron-down" size={20} color="#666" />
            </TouchableOpacity>
          )}

          <Text style={[styles.filterLabel, { marginTop: 16 }]}>Reading date</Text>
          {loadingFilters ? null : (
            <TouchableOpacity style={styles.pickerButton} onPress={openDatePicker}>
              <Text style={styles.pickerButtonText} numberOfLines={1}>
                {selectedDate ?? 'Choose date'}
              </Text>
              <Ionicons name="calendar-outline" size={20} color="#666" />
            </TouchableOpacity>
          )}
          {filtersMessage ? (
            <View style={styles.filtersMessageBox}>
              <Ionicons name="information-circle-outline" size={20} color="#2196F3" style={styles.filtersMessageIcon} />
              <Text style={styles.filtersMessageText}>{filtersMessage}</Text>
            </View>
          ) : null}
        </View>

        {/* List */}
        {selectedZone && selectedDate && (
          <View style={styles.listSection}>
            <Text style={styles.listSectionTitle}>
              Readings ({filteredList.length}{searchQuery.trim() ? ` of ${list.length}` : ''})
            </Text>
            {canEdit && (
              <View style={styles.editHintBox}>
                <Ionicons name="information-circle-outline" size={18} color="#1565C0" />
                <Text style={styles.editHintBoxText}>Tap a consumer to edit current reading.</Text>
              </View>
            )}
            {list.length > 0 && (
              <View style={styles.searchBarContainer}>
                <Ionicons name="search-outline" size={20} color="#666" style={styles.searchIcon} />
                <TextInput
                  style={styles.searchInput}
                  placeholder="Search consumer name"
                  placeholderTextColor="#999"
                  value={searchQuery}
                  onChangeText={setSearchQuery}
                  autoCapitalize="none"
                  autoCorrect={false}
                />
                {searchQuery.length > 0 && (
                  <TouchableOpacity
                    style={styles.searchClear}
                    onPress={() => setSearchQuery('')}
                    hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                  >
                    <Ionicons name="close-circle" size={20} color="#666" />
                  </TouchableOpacity>
                )}
              </View>
            )}
            {loadingList ? (
              <ActivityIndicator size="large" color="#2196F3" style={styles.listLoader} />
            ) : list.length === 0 ? (
              <Text style={styles.emptyListText}>No downloaded readings for this zone and date.</Text>
            ) : filteredList.length === 0 ? (
              <Text style={styles.emptyListText}>No consumers match "{searchQuery.trim()}".</Text>
            ) : (
              <FlatList
                data={filteredList}
                keyExtractor={(item, idx) => String(item.id ?? item.schedule_id ?? idx)}
                renderItem={renderReadingItem}
                scrollEnabled={false}
                listKey="retrieve-zone-list"
              />
            )}
          </View>
        )}
      </ScrollView>

      {/* Edit Reading Modal (latest month only) */}
      <Modal visible={showEditModal} transparent animationType="slide">
        <View style={styles.editModalOverlay}>
          <View style={styles.editModalContent}>
            <Text style={styles.modalTitle}>Adjust Reading</Text>
            {editingItem && (
              <>
                <View style={styles.editModalRow}>
                  <Text style={styles.editModalLabel}>Account</Text>
                  <Text style={styles.editModalValue}>{editingItem.account_number ?? editingItem.account_no ?? '—'}</Text>
                </View>
                <View style={styles.editModalRow}>
                  <Text style={styles.editModalLabel}>Name</Text>
                  <Text style={styles.editModalValue} numberOfLines={2}>{editingItem.account_name ?? editingItem.name ?? '—'}</Text>
                </View>
                <View style={styles.editModalRow}>
                  <Text style={styles.editModalLabel}>Previous reading</Text>
                  <Text style={styles.editModalValue}>{editingItem.previous_reading ?? editingItem.previousReading ?? '—'}</Text>
                </View>
                <View style={styles.editModalField}>
                  <Text style={styles.editModalLabel}>Current reading</Text>
                  <TextInput
                    style={styles.editModalInput}
                    value={editCurrentReading}
                    onChangeText={setEditCurrentReading}
                    keyboardType="numeric"
                    placeholder="Enter current reading"
                    placeholderTextColor="#999"
                  />
                </View>
                <View style={styles.editModalActions}>
                  <TouchableOpacity style={styles.editModalCancelBtn} onPress={closeEditModal} disabled={savingEdit}>
                    <Text style={styles.editModalCancelText}>Cancel</Text>
                  </TouchableOpacity>
                  <TouchableOpacity style={[styles.editModalSaveBtn, savingEdit && styles.editModalSaveBtnDisabled]} onPress={saveEditedReading} disabled={savingEdit}>
                    {savingEdit ? <ActivityIndicator size="small" color="#fff" /> : <Text style={styles.editModalSaveText}>Save</Text>}
                  </TouchableOpacity>
                </View>
              </>
            )}
          </View>
        </View>
      </Modal>

      {/* Zone Picker Modal */}
      <Modal visible={showZonePicker} transparent animationType="slide">
        <TouchableOpacity
          style={styles.modalOverlay}
          activeOpacity={1}
          onPress={() => setShowZonePicker(false)}
        >
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Select zone</Text>
            <ScrollView style={styles.modalScroll}>
              {zones.length === 0 ? (
                <Text style={styles.modalEmpty}>No zones available</Text>
              ) : (
                zones.map((zone) => (
                  <TouchableOpacity
                    key={zone}
                    style={styles.modalOption}
                    onPress={() => selectZone(zone)}
                  >
                    <Text style={styles.modalOptionText}>{zone}</Text>
                    {selectedZone === zone && (
                      <Ionicons name="checkmark-circle" size={22} color="#2196F3" />
                    )}
                  </TouchableOpacity>
                ))
              )}
            </ScrollView>
            <TouchableOpacity style={styles.modalCancel} onPress={() => setShowZonePicker(false)}>
              <Text style={styles.modalCancelText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </TouchableOpacity>
      </Modal>

      {/* Date Picker Modal - Calendar */}
      <Modal visible={showDatePicker} transparent animationType="slide">
        <TouchableOpacity
          style={styles.modalOverlay}
          activeOpacity={1}
          onPress={() => setShowDatePicker(false)}
        >
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Select reading date</Text>
            <View style={styles.calendarHeader}>
              <TouchableOpacity onPress={prevCalendarMonth} style={styles.calendarNav}>
                <Ionicons name="chevron-back" size={24} color="#2196F3" />
              </TouchableOpacity>
              <Text style={styles.calendarMonthLabel}>{calendarMonthLabel}</Text>
              <TouchableOpacity onPress={nextCalendarMonth} style={styles.calendarNav}>
                <Ionicons name="chevron-forward" size={24} color="#2196F3" />
              </TouchableOpacity>
            </View>
            <View style={styles.calendarWeekRow}>
              {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((w) => (
                <Text key={w} style={styles.calendarWeekDay}>{w}</Text>
              ))}
            </View>
            <ScrollView style={styles.calendarScroll} showsVerticalScrollIndicator={false}>
              {getCalendarDays().map((row, rowIdx) => (
                <View key={rowIdx} style={styles.calendarDayRow}>
                  {row.map((cell) =>
                    cell.empty ? (
                      <View key={cell.key} style={styles.calendarDayCell} />
                    ) : (
                      <TouchableOpacity
                        key={cell.key}
                        style={[
                          styles.calendarDayCell,
                          cell.hasData && styles.calendarDayHasData,
                          selectedDate === cell.dateStr && styles.calendarDaySelected,
                        ]}
                        onPress={() => selectDate(cell.dateStr)}
                      >
                        <Text
                          style={[
                            styles.calendarDayText,
                            selectedDate === cell.dateStr && styles.calendarDayTextSelected,
                          ]}
                        >
                          {cell.day}
                        </Text>
                      </TouchableOpacity>
                    )
                  )}
                </View>
              ))}
            </ScrollView>
            <TouchableOpacity style={styles.modalCancel} onPress={() => setShowDatePicker(false)}>
              <Text style={styles.modalCancelText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </TouchableOpacity>
      </Modal>

      {/* Printer Selector for thermal receipt (same as Read and Bill) */}
      <PrinterSelector
        visible={showPrinterSelector}
        onSelect={async (printer) => {
          setShowPrinterSelector(false);
          if (pendingReceiptData) {
            try {
              await printerStorage.savePrinter(printer);
              const ok = await printReceiptEscPos(pendingReceiptData, printer);
              if (!ok) {
                Alert.alert('Print Failed', 'Could not print to the selected printer. Please try again.');
              }
            } catch (e) {
              Alert.alert('Print Error', e?.message || 'Failed to print bill.');
            }
            setPendingReceiptData(null);
          }
        }}
        onCancel={() => {
          setShowPrinterSelector(false);
          setPendingReceiptData(null);
        }}
      />
    </View>
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
    paddingBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  backButton: {
    marginBottom: 10,
  },
  backButtonText: {
    fontSize: 16,
    color: '#2196F3',
    fontWeight: '600',
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 4,
  },
  headerSubtitle: {
    fontSize: 14,
    color: '#666',
  },
  scroll: {
    flex: 1,
  },
  scrollContent: {
    padding: 20,
    paddingBottom: 40,
  },
  filters: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 16,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  filterLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
    marginBottom: 8,
  },
  filterLoader: {
    marginVertical: 8,
  },
  pickerButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    backgroundColor: '#fafafa',
  },
  pickerButtonText: {
    fontSize: 16,
    color: '#333',
    flex: 1,
  },
  filtersMessageBox: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: '#E3F2FD',
    borderRadius: 8,
    padding: 12,
    marginTop: 16,
  },
  filtersMessageIcon: {
    marginRight: 8,
    marginTop: 2,
  },
  filtersMessageText: {
    flex: 1,
    fontSize: 14,
    color: '#1565C0',
    lineHeight: 20,
  },
  listSection: {
    marginTop: 8,
  },
  listSectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 12,
  },
  searchBarContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fafafa',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    paddingHorizontal: 12,
    marginBottom: 16,
    minHeight: 44,
  },
  searchIcon: {
    marginRight: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 16,
    color: '#333',
    paddingVertical: 10,
  },
  searchClear: {
    padding: 4,
  },
  listLoader: {
    marginVertical: 24,
  },
  emptyListText: {
    fontSize: 15,
    color: '#666',
    textAlign: 'center',
    marginVertical: 24,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  emptyStateText: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
  },
  card: {
    backgroundColor: 'white',
    borderRadius: 10,
    padding: 14,
    marginBottom: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.08,
    shadowRadius: 2,
    elevation: 2,
  },
  cardRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  cardLabel: {
    fontSize: 13,
    color: '#666',
    marginRight: 8,
  },
  cardValue: {
    fontSize: 14,
    color: '#333',
    fontWeight: '500',
    flex: 1,
    textAlign: 'right',
  },
  cardEditHint: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 8,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#eee',
    gap: 6,
  },
  cardEditHintText: {
    fontSize: 12,
    color: '#2196F3',
    fontWeight: '600',
  },
  cardPrintBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 10,
    paddingVertical: 10,
    paddingHorizontal: 14,
    backgroundColor: '#E3F2FD',
    borderRadius: 8,
    gap: 6,
  },
  cardPrintBtnText: {
    fontSize: 14,
    color: '#2196F3',
    fontWeight: '600',
  },
  editHintBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#E3F2FD',
    borderRadius: 8,
    padding: 10,
    marginBottom: 12,
    gap: 8,
  },
  editHintBoxText: {
    flex: 1,
    fontSize: 13,
    color: '#1565C0',
    fontWeight: '500',
  },
  editModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    padding: 20,
  },
  editModalContent: {
    backgroundColor: 'white',
    marginHorizontal: 20,
    borderRadius: 12,
    padding: 20,
    maxWidth: 400,
    alignSelf: 'center',
    width: '100%',
  },
  editModalRow: {
    marginBottom: 10,
  },
  editModalLabel: {
    fontSize: 12,
    color: '#666',
    marginBottom: 2,
  },
  editModalValue: {
    fontSize: 15,
    color: '#333',
    fontWeight: '500',
  },
  editModalField: {
    marginBottom: 16,
  },
  editModalInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    backgroundColor: '#fafafa',
    marginTop: 4,
  },
  editModalActions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 8,
  },
  editModalCancelBtn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: '#f5f5f5',
    alignItems: 'center',
  },
  editModalCancelText: {
    fontSize: 16,
    color: '#666',
    fontWeight: '600',
  },
  editModalSaveBtn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: '#2196F3',
    alignItems: 'center',
  },
  editModalSaveBtnDisabled: {
    backgroundColor: '#90CAF9',
  },
  editModalSaveText: {
    fontSize: 16,
    color: '#fff',
    fontWeight: '600',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: 'white',
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    maxHeight: '70%',
    paddingBottom: 24,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    textAlign: 'center',
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  modalScroll: {
    maxHeight: 320,
  },
  calendarHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  calendarNav: {
    padding: 8,
  },
  calendarMonthLabel: {
    fontSize: 16,
    fontWeight: '700',
    color: '#333',
  },
  calendarWeekRow: {
    flexDirection: 'row',
    paddingHorizontal: 8,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  calendarWeekDay: {
    flex: 1,
    textAlign: 'center',
    fontSize: 12,
    fontWeight: '600',
    color: '#666',
  },
  calendarScroll: {
    maxHeight: 280,
    paddingHorizontal: 8,
    paddingTop: 8,
  },
  calendarDayRow: {
    flexDirection: 'row',
    marginBottom: 4,
  },
  calendarDayCell: {
    flex: 1,
    aspectRatio: 1,
    maxWidth: 40,
    maxHeight: 40,
    justifyContent: 'center',
    alignItems: 'center',
    alignSelf: 'center',
    borderRadius: 20,
    marginHorizontal: 2,
  },
  calendarDayHasData: {
    backgroundColor: '#E3F2FD',
  },
  calendarDaySelected: {
    backgroundColor: '#2196F3',
  },
  calendarDayText: {
    fontSize: 14,
    color: '#333',
    fontWeight: '500',
  },
  calendarDayTextSelected: {
    color: '#fff',
    fontWeight: '700',
  },
  modalEmpty: {
    fontSize: 15,
    color: '#666',
    textAlign: 'center',
    paddingVertical: 24,
  },
  modalOption: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 14,
    paddingHorizontal: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  modalOptionText: {
    fontSize: 16,
    color: '#333',
  },
  modalCancel: {
    marginTop: 12,
    marginHorizontal: 20,
    paddingVertical: 14,
    borderRadius: 10,
    backgroundColor: '#f5f5f5',
    alignItems: 'center',
  },
  modalCancelText: {
    fontSize: 16,
    color: '#666',
    fontWeight: '600',
  },
});

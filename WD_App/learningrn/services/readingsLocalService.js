
import * as SQLite from 'expo-sqlite';
import { config } from '../constant';
import { getLocalDateYYYYMMDD } from '../utils/dateUtils';

const TABLE = 'read_and_bill';

let db = null;
let dbOpenPromise = null;

async function getDb() {
  if (db) return db;
  if (dbOpenPromise) return dbOpenPromise;
  dbOpenPromise = (async () => {
    try {
      
      const opts = { useNewConnection: true };
      const opened = await SQLite.openDatabaseAsync(config.dbName, opts);
      if (!opened) throw new Error('Database open returned null');
      db = opened;
      await db.execAsync(`
        CREATE TABLE IF NOT EXISTS ${TABLE} (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          storage_keys TEXT,
          schedule_id INTEGER,
          current_reading INTEGER,
          reading_date TEXT,
          reader_notes TEXT,
          reader_id INTEGER,
          consumption INTEGER,
          status TEXT,
          lastAttempt TEXT,
          error TEXT,
          retryCount INTEGER,
          customer TEXT
        )
      `);
      return db;
    } catch (e) {
      dbOpenPromise = null;
      throw e;
    }
  })();
  return dbOpenPromise;
}


export async function saveReadingToLocal(readingData) {
  const conn = await getDb();
  const now = new Date().toISOString();
  const readingDateStr = readingData.reading_date || getLocalDateYYYYMMDD();

  const scheduleId = readingData.schedule_id != null ? Number(readingData.schedule_id) : null;
  const currentReading = readingData.current_reading != null ? Number(readingData.current_reading) : 0;
  const readerId = readingData.reader_id != null ? Number(readingData.reader_id) : null;
  const consumption = readingData.consumption != null ? Number(readingData.consumption) : 0;
  let customerJson = '{}';
  try {
    customerJson = JSON.stringify(readingData.customer || {});
  } catch (_) {
    customerJson = '{}';
  }
  const result = await conn.runAsync(
    `INSERT INTO ${TABLE} (storage_keys, schedule_id, current_reading, reading_date, reader_notes, reader_id, consumption, status, lastAttempt, error, retryCount, customer)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      'pending_readings',
      scheduleId,
      currentReading,
      readingDateStr,
      readingData.reader_notes || '',
      readerId,
      consumption,
      'pending',
      now,
      null,
      0,
      customerJson,
    ]
  );
  const id = result.lastInsertRowId;
  console.log('✅ Reading saved to SQLite first (local):', id);
  return { id };
}


export async function getPendingReadings() {
  const conn = await getDb();
  const rows = await conn.getAllAsync(
    `SELECT id, schedule_id, current_reading, reading_date, reader_notes, reader_id, consumption, status, lastAttempt, error, retryCount, customer
     FROM ${TABLE}
     WHERE (status = ? OR status = ?)
     ORDER BY id ASC`,
    ['pending', 'failed']
  );
  return rows.map((r) => ({
    id: r.id,
    data: {
      schedule_id: r.schedule_id,
      current_reading: r.current_reading,
      reading_date: r.reading_date,
      reader_notes: r.reader_notes || '',
      reader_id: r.reader_id,
      consumption: r.consumption,
      customer: r.customer ? JSON.parse(r.customer) : {},
    },
    status: r.status,
    retryCount: r.retryCount || 0,
  }));
}

export async function getAllReadingsForExport() {
  const conn = await getDb();
  const rows = await conn.getAllAsync(
    `SELECT id, storage_keys, schedule_id, current_reading, reading_date, reader_notes, reader_id, consumption, status, lastAttempt, error, retryCount, customer
     FROM ${TABLE}
     ORDER BY id DESC`
  );

  return rows.map((r) => {
    let customer = {};
    try {
      customer = r.customer ? JSON.parse(r.customer) : {};
    } catch (_) {
      customer = {};
    }

    return {
      id: r.id,
      storage_keys: r.storage_keys,
      schedule_id: r.schedule_id,
      current_reading: r.current_reading,
      reading_date: r.reading_date,
      reader_notes: r.reader_notes || '',
      reader_id: r.reader_id,
      consumption: r.consumption,
      status: r.status,
      lastAttempt: r.lastAttempt,
      error: r.error,
      retryCount: r.retryCount || 0,
      customer,
    };
  });
}

export async function markSynced(id) {
  const conn = await getDb();
  await conn.runAsync(
    `UPDATE ${TABLE} SET status = ?, lastAttempt = ?, error = NULL WHERE id = ?`,
    ['synced', new Date().toISOString(), id]
  );
  console.log('Reading marked synced in SQLite:', id);
}

export async function markFailed(id, errorMessage) {
  const conn = await getDb();
  const now = new Date().toISOString();
  await conn.runAsync(
    `UPDATE ${TABLE} SET status = ?, lastAttempt = ?, error = ?, retryCount = COALESCE(retryCount, 0) + 1 WHERE id = ?`,
    ['failed', now, errorMessage || 'Upload failed', id]
  );
  console.log('Reading marked failed in SQLite:', id, errorMessage);
}


export async function getPendingCount() {
  const conn = await getDb();
  const rows = await conn.getAllAsync(
    `SELECT COUNT(*) as c FROM ${TABLE}
     WHERE (status = ? OR status = ?)`,
    ['pending', 'failed']
  );
  return (rows[0] && rows[0].c) || 0;
}

export async function getPendingIdByScheduleId(scheduleId) {
  if (scheduleId == null) return null;
  const conn = await getDb();
  const sid = Number(scheduleId);
  const rows = await conn.getAllAsync(
    `SELECT id FROM ${TABLE}
     WHERE schedule_id = ? AND (status = ? OR status = ?)
     ORDER BY id DESC LIMIT 1`,
    [sid, 'pending', 'failed']
  );
  return (rows[0] && rows[0].id != null) ? rows[0].id : null;
}

export async function updatePendingReading(id, readingData) {
  const conn = await getDb();
  const now = new Date().toISOString();
  const readingDateStr = readingData.reading_date || getLocalDateYYYYMMDD();
  const scheduleId = readingData.schedule_id != null ? Number(readingData.schedule_id) : null;
  const currentReading = readingData.current_reading != null ? Number(readingData.current_reading) : 0;
  const readerId = readingData.reader_id != null ? Number(readingData.reader_id) : null;
  const consumption = readingData.consumption != null ? Number(readingData.consumption) : 0;
  let customerJson = '{}';
  try {
    customerJson = JSON.stringify(readingData.customer || {});
  } catch (_) {
    customerJson = '{}';
  }
  await conn.runAsync(
    `UPDATE ${TABLE} SET current_reading = ?, reading_date = ?, reader_notes = ?, reader_id = ?, consumption = ?, lastAttempt = ?, customer = ?, error = NULL, retryCount = 0, status = ?
     WHERE id = ?`,
    [
      currentReading,
      readingDateStr,
      readingData.reader_notes || '',
      readerId,
      consumption,
      now,
      customerJson,
      'pending',
      id,
    ]
  );
  console.log('✅ Pending reading updated in SQLite:', id);
  return { id };
}

export default {
  saveReadingToLocal,
  getPendingReadings,
  getAllReadingsForExport,
  markSynced,
  markFailed,
  getPendingCount,
  getPendingIdByScheduleId,
  updatePendingReading,
};

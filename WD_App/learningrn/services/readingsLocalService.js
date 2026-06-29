
import * as SQLite from 'expo-sqlite';
import { config } from '../constant';
import { getLocalDateYYYYMMDD } from '../utils/dateUtils';

const TABLE = 'read_and_bill';
/** Local-only rows waiting for server upload; stays this way after failed sync (not `pending`). */
export const STATUS_SAVED_OFFLINE = 'saved_offline';
const FAILED_RETRY_BASE_DELAY_MS = 5000;
const FAILED_RETRY_MAX_DELAY_MS = 5 * 60 * 1000;

let db = null;
let dbOpenPromise = null;

async function ensureTable(conn) {
  await conn.execAsync(`
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
  await conn.runAsync(
    `UPDATE ${TABLE} SET status = ? WHERE status IN ('pending', 'failed')`,
    [STATUS_SAVED_OFFLINE]
  );
}

async function getDb() {
  if (db) return db;
  if (dbOpenPromise) return dbOpenPromise;
  dbOpenPromise = (async () => {
    try {
      
      const opts = { useNewConnection: true };
      const opened = await SQLite.openDatabaseAsync(config.dbName, opts);
      if (!opened) throw new Error('Database open returned null');
      db = opened;
      await ensureTable(db);
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
  await ensureTable(conn);
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
      STATUS_SAVED_OFFLINE,
      now,
      null,
      0,
      customerJson,
    ]
  );
  const id = result.lastInsertRowId;
  console.log('✅ Reading saved to SQLite (saved offline):', id);
  return { id };
}


export async function getPendingReadings() {
  const conn = await getDb();
  await ensureTable(conn);
  const rows = await conn.getAllAsync(
    `SELECT id, schedule_id, current_reading, reading_date, reader_notes, reader_id, consumption, status, lastAttempt, error, retryCount, customer
     FROM ${TABLE}
     WHERE status = ?
     ORDER BY id ASC`,
    [STATUS_SAVED_OFFLINE]
  );
  const nowMs = Date.now();
  const shouldIncludeForSync = (r) => {
    const err = r.error != null && String(r.error).trim() !== '';
    const retryCount = Number(r.retryCount || 0);
    if (!err && retryCount === 0) return true;
    const backoffMs = Math.min(
      FAILED_RETRY_MAX_DELAY_MS,
      FAILED_RETRY_BASE_DELAY_MS * Math.pow(2, Math.max(0, retryCount))
    );
    const lastAttemptMs = r.lastAttempt ? new Date(r.lastAttempt).getTime() : 0;
    if (!Number.isFinite(lastAttemptMs) || lastAttemptMs <= 0) return true;
    return (nowMs - lastAttemptMs) >= backoffMs;
  };

  return rows.filter(shouldIncludeForSync).map(mapRowToReadingQueueItem);
}

function mapRowToReadingQueueItem(r) {
  let customer = {};
  try {
    customer = r.customer ? JSON.parse(r.customer) : {};
  } catch (_) {
    customer = {};
  }
  return {
    id: r.id,
    lastAttempt: r.lastAttempt,
    data: {
      schedule_id: r.schedule_id,
      current_reading: r.current_reading,
      reading_date: r.reading_date,
      reader_notes: r.reader_notes || '',
      reader_id: r.reader_id,
      consumption: r.consumption,
      customer,
    },
    status: r.status,
    retryCount: r.retryCount || 0,
  };
}

/**
 * All locally unsynced readings for UI / route merge — no retry backoff.
 * Keeps list showing "saved offline" even while a row waits to sync again after a failure.
 */
export async function getUnsyncedReadingsForUIMerge() {
  const conn = await getDb();
  await ensureTable(conn);
  const rows = await conn.getAllAsync(
    `SELECT id, schedule_id, current_reading, reading_date, reader_notes, reader_id, consumption, status, lastAttempt, error, retryCount, customer
     FROM ${TABLE}
     WHERE (status = ? OR status = 'syncing')
     ORDER BY id ASC`,
    [STATUS_SAVED_OFFLINE]
  );
  return rows.map(mapRowToReadingQueueItem);
}

export async function getAllReadingsForExport() {
  const conn = await getDb();
  await ensureTable(conn);
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
  await ensureTable(conn);
  await conn.runAsync(
    `UPDATE ${TABLE} SET status = ?, lastAttempt = ?, error = NULL WHERE id = ?`,
    ['synced', new Date().toISOString(), id]
  );
  console.log('Reading marked synced in SQLite:', id);
}

export async function markSyncing(id) {
  const conn = await getDb();
  await ensureTable(conn);
  await conn.runAsync(
    `UPDATE ${TABLE} SET status = ?, lastAttempt = ?, error = NULL WHERE id = ?`,
    ['syncing', new Date().toISOString(), id]
  );
}

export async function markFailed(id, errorMessage) {
  const conn = await getDb();
  await ensureTable(conn);
  const now = new Date().toISOString();
  await conn.runAsync(
    `UPDATE ${TABLE} SET status = ?, lastAttempt = ?, error = ?, retryCount = COALESCE(retryCount, 0) + 1 WHERE id = ?`,
    [STATUS_SAVED_OFFLINE, now, errorMessage || 'Upload failed', id]
  );
  console.log('Reading stays saved offline after failed sync (will retry):', id, errorMessage);
}

export async function resetStuckSyncingRows() {
  const conn = await getDb();
  await ensureTable(conn);
  const now = new Date().toISOString();
  await conn.runAsync(
    `UPDATE ${TABLE}
     SET status = ?, lastAttempt = ?, error = COALESCE(error, ?)
     WHERE status = ?`,
    [STATUS_SAVED_OFFLINE, now, 'Sync interrupted. Will retry automatically.', 'syncing']
  );
}


export async function getPendingCount() {
  const conn = await getDb();
  await ensureTable(conn);
  const rows = await conn.getAllAsync(
    `SELECT COUNT(*) as c FROM ${TABLE}
     WHERE (status = ? OR status = ?)`,
    [STATUS_SAVED_OFFLINE, 'syncing']
  );
  return (rows[0] && rows[0].c) || 0;
}

export async function getPendingIdByScheduleId(scheduleId) {
  if (scheduleId == null) return null;
  const conn = await getDb();
  await ensureTable(conn);
  const sid = Number(scheduleId);
  const rows = await conn.getAllAsync(
    `SELECT id FROM ${TABLE}
     WHERE schedule_id = ? AND (status = ? OR status = ?)
     ORDER BY id DESC LIMIT 1`,
    [sid, STATUS_SAVED_OFFLINE, 'syncing']
  );
  return (rows[0] && rows[0].id != null) ? rows[0].id : null;
}

export async function updatePendingReading(id, readingData) {
  const conn = await getDb();
  await ensureTable(conn);
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
      STATUS_SAVED_OFFLINE,
      id,
    ]
  );
  console.log('✅ Reading updated in SQLite (saved offline):', id);
  return { id };
}

export async function deleteAllPendingReadings() {
  const conn = await getDb();
  await ensureTable(conn);
  await conn.runAsync(
    `DELETE FROM ${TABLE} WHERE status IN (?, 'syncing', 'pending', 'failed')`,
    [STATUS_SAVED_OFFLINE]
  );
}

export async function markPending(id) {
  const conn = await getDb();
  await ensureTable(conn);
  const now = new Date().toISOString();
  await conn.runAsync(
    `UPDATE ${TABLE} SET status = ?, lastAttempt = ?, error = NULL WHERE id = ?`,
    [STATUS_SAVED_OFFLINE, now, id]
  );
}

export default {
  saveReadingToLocal,
  getPendingReadings,
  getUnsyncedReadingsForUIMerge,
  getAllReadingsForExport,
  markSyncing,
  markSynced,
  markFailed,
  markPending,
  resetStuckSyncingRows,
  deleteAllPendingReadings,
  getPendingCount,
  getPendingIdByScheduleId,
  updatePendingReading,
  STATUS_SAVED_OFFLINE,
};

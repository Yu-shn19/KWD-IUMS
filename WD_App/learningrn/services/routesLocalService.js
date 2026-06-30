/**
 * SQLite-backed cache for assigned routes / assignments (replaces AsyncStorage routes_list).
 * Buckets: reader meter routes vs disconnector assignments so they do not overwrite each other.
 */
import * as SQLite from 'expo-sqlite';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { config } from '../constant';

const TABLE = 'routes_cache';
const LEGACY_ROUTES_KEY = 'routes_list';
const MIGRATION_FLAG_KEY = 'routes_sqlite_migrated_v1';

export const ROUTES_BUCKET_READER = 'reader';
export const ROUTES_BUCKET_DISCONNECTOR = 'disconnector';

let db = null;
let dbOpenPromise = null;
let migrationPromise = null;

async function getDb() {
  if (db) return db;
  if (dbOpenPromise) return dbOpenPromise;
  dbOpenPromise = (async () => {
    const opts = { useNewConnection: true };
    const opened = await SQLite.openDatabaseAsync(config.dbName, opts);
    if (!opened) throw new Error('Database open returned null');
    db = opened;
    await db.execAsync(`
      CREATE TABLE IF NOT EXISTS ${TABLE} (
        bucket TEXT PRIMARY KEY NOT NULL,
        payload TEXT NOT NULL,
        updated_at TEXT
      )
    `);
    return db;
  })().catch((e) => {
    dbOpenPromise = null;
    throw e;
  });
  return dbOpenPromise;
}

/**
 * One-time copy from legacy AsyncStorage `routes_list` into SQLite buckets, then remove legacy key.
 */
async function migrateFromAsyncStorageOnce() {
  if (migrationPromise) return migrationPromise;
  migrationPromise = (async () => {
    try {
      const done = await AsyncStorage.getItem(MIGRATION_FLAG_KEY);
      if (done === '1') return;

      const conn = await getDb();
      const countRows = await conn.getAllAsync(`SELECT COUNT(*) as c FROM ${TABLE}`);
      const hasAnyBucket = (countRows[0] && countRows[0].c > 0) || false;

      const raw = await AsyncStorage.getItem(LEGACY_ROUTES_KEY);

      // Import legacy AsyncStorage only when SQLite has no rows yet (avoid clobbering newer SQLite data)
      if (!hasAnyBucket && raw != null && raw !== '') {
        let arr = [];
        try {
          const parsed = JSON.parse(raw);
          arr = Array.isArray(parsed) ? parsed : [];
        } catch (_) {
          arr = [];
        }
        const now = new Date().toISOString();
        const json = JSON.stringify(arr);
        await conn.runAsync(
          `INSERT OR REPLACE INTO ${TABLE} (bucket, payload, updated_at) VALUES (?, ?, ?)`,
          [ROUTES_BUCKET_READER, json, now]
        );
        await conn.runAsync(
          `INSERT OR REPLACE INTO ${TABLE} (bucket, payload, updated_at) VALUES (?, ?, ?)`,
          [ROUTES_BUCKET_DISCONNECTOR, json, now]
        );
      }

      if (raw != null) {
        await AsyncStorage.removeItem(LEGACY_ROUTES_KEY);
      }
      await AsyncStorage.setItem(MIGRATION_FLAG_KEY, '1');
    } catch (e) {
      console.error('routes SQLite migration:', e);
    }
  })();
  return migrationPromise;
}

export async function getRoutes(bucket = ROUTES_BUCKET_READER) {
  await migrateFromAsyncStorageOnce();
  const conn = await getDb();
  const rows = await conn.getAllAsync(
    `SELECT payload FROM ${TABLE} WHERE bucket = ? LIMIT 1`,
    [bucket]
  );
  if (!rows || rows.length === 0) return [];
  try {
    const parsed = JSON.parse(rows[0].payload);
    return Array.isArray(parsed) ? parsed : [];
  } catch (_) {
    return [];
  }
}

export async function saveRoutes(bucket, routes) {
  await migrateFromAsyncStorageOnce();
  const conn = await getDb();
  const json = JSON.stringify(Array.isArray(routes) ? routes : []);
  const now = new Date().toISOString();
  await conn.runAsync(
    `INSERT OR REPLACE INTO ${TABLE} (bucket, payload, updated_at) VALUES (?, ?, ?)`,
    [bucket, json, now]
  );
  return true;
}

export async function clearRoutes(bucket = ROUTES_BUCKET_READER) {
  await migrateFromAsyncStorageOnce();
  const conn = await getDb();
  await conn.runAsync(`DELETE FROM ${TABLE} WHERE bucket = ?`, [bucket]);
  return true;
}

export default {
  getRoutes,
  saveRoutes,
  clearRoutes,
  ROUTES_BUCKET_READER,
  ROUTES_BUCKET_DISCONNECTOR,
};

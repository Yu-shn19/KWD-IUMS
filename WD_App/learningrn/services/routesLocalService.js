/**
 * SQLite-backed cache for assigned routes / assignments (replaces AsyncStorage routes_list).
 * Buckets: reader meter routes vs disconnector assignments so they do not overwrite each other.
 */
import AsyncStorage from '@react-native-async-storage/async-storage';
import { withSharedDb } from './sqliteDb';

const TABLE = 'routes_cache';
const LEGACY_ROUTES_KEY = 'routes_list';
const MIGRATION_FLAG_KEY = 'routes_sqlite_migrated_v1';

export const ROUTES_BUCKET_READER = 'reader';
export const ROUTES_BUCKET_DISCONNECTOR = 'disconnector';

let ensureTablePromise = null;
let migrationPromise = null;

async function ensureRoutesTable(conn) {
  if (!ensureTablePromise) {
    ensureTablePromise = (async () => {
      await conn.execAsync(`
        CREATE TABLE IF NOT EXISTS ${TABLE} (
          bucket TEXT PRIMARY KEY NOT NULL,
          payload TEXT NOT NULL,
          updated_at TEXT
        )
      `);
    })().catch((e) => {
      ensureTablePromise = null;
      throw e;
    });
  }
  return ensureTablePromise;
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

      await withSharedDb(async (conn) => {
        await ensureRoutesTable(conn);
        const countRows = await conn.getAllAsync(`SELECT COUNT(*) as c FROM ${TABLE}`);
        const hasAnyBucket = (countRows[0] && countRows[0].c > 0) || false;

        const raw = await AsyncStorage.getItem(LEGACY_ROUTES_KEY);

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
      });
    } catch (e) {
      console.error('routes SQLite migration:', e);
      migrationPromise = null;
    }
  })();
  return migrationPromise;
}

export async function getRoutes(bucket = ROUTES_BUCKET_READER) {
  await migrateFromAsyncStorageOnce();
  return withSharedDb(async (conn) => {
    await ensureRoutesTable(conn);
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
  });
}

export async function saveRoutes(bucket, routes) {
  await migrateFromAsyncStorageOnce();
  return withSharedDb(async (conn) => {
    await ensureRoutesTable(conn);
    const json = JSON.stringify(Array.isArray(routes) ? routes : []);
    const now = new Date().toISOString();
    await conn.runAsync(
      `INSERT OR REPLACE INTO ${TABLE} (bucket, payload, updated_at) VALUES (?, ?, ?)`,
      [bucket, json, now]
    );
    return true;
  });
}

export async function clearRoutes(bucket = ROUTES_BUCKET_READER) {
  await migrateFromAsyncStorageOnce();
  return withSharedDb(async (conn) => {
    await ensureRoutesTable(conn);
    await conn.runAsync(`DELETE FROM ${TABLE} WHERE bucket = ?`, [bucket]);
    return true;
  });
}

export default {
  getRoutes,
  saveRoutes,
  clearRoutes,
  ROUTES_BUCKET_READER,
  ROUTES_BUCKET_DISCONNECTOR,
};

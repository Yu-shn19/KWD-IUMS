/**
 * Single shared SQLite connection with a serialized operation queue.
 * Concurrent getAllAsync/runAsync/execAsync on one expo-sqlite connection
 * causes: "Cannot use shared object that was already released".
 */
import * as SQLite from 'expo-sqlite';
import { config } from '../constant';

let db = null;
let dbOpenPromise = null;
let queue = Promise.resolve();

export async function getSharedDb() {
  if (db) return db;
  if (dbOpenPromise) return dbOpenPromise;

  dbOpenPromise = (async () => {
    const opened = await SQLite.openDatabaseAsync(config.dbName);
    if (!opened) throw new Error('Database open returned null');
    try {
      await opened.execAsync('PRAGMA journal_mode = WAL;');
    } catch (_) {
      // WAL may be unavailable on some platforms; ignore.
    }
    db = opened;
    return db;
  })().catch((e) => {
    dbOpenPromise = null;
    db = null;
    throw e;
  });

  return dbOpenPromise;
}

/**
 * Run a DB callback exclusively (FIFO). All services must use this.
 */
export function withSharedDb(fn) {
  const run = queue.then(async () => {
    const conn = await getSharedDb();
    return fn(conn);
  });
  // Keep queue alive even if this op fails
  queue = run.catch(() => {});
  return run;
}

export async function resetSharedDb() {
  queue = Promise.resolve();
  const old = db;
  db = null;
  dbOpenPromise = null;
  if (old) {
    try {
      await old.closeAsync();
    } catch (_) {}
  }
}

export default { getSharedDb, withSharedDb, resetSharedDb };

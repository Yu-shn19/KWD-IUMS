/**
 * Single shared SQLite connection for the app.
 * Opening the same DB with useNewConnection from multiple services causes "database is locked".
 */
import * as SQLite from 'expo-sqlite';
import { config } from '../constant';

let db = null;
let dbOpenPromise = null;

export async function getSharedDb() {
  if (db) return db;
  if (dbOpenPromise) return dbOpenPromise;

  dbOpenPromise = (async () => {
    const opened = await SQLite.openDatabaseAsync(config.dbName);
    if (!opened) throw new Error('Database open returned null');
    db = opened;
    return db;
  })().catch((e) => {
    dbOpenPromise = null;
    db = null;
    throw e;
  });

  return dbOpenPromise;
}

export default { getSharedDb };

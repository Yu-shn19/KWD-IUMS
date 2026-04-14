import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SQLite from 'expo-sqlite';
import { config } from '../constant';


const c = config;

// const readingDummyData = {
//     schedule_id: 1,
//     current_reading: 100,
//     reading_date: new Date().toISOString().split('T')[0],
//     reader_notes: '',
//     reader_id: 1,
//     consumption: 50,
//     customer: {
//         id: 1,
//         sedrNumber: 1,
//         accountname: 'Genobiagon',
//         accountnumber: '05-12-419',
//         category: '12',
//         rateCode: null,
//         zone: '12',
//         address: 'Test street',
//         meterNumber: '240607194',
//         status: 'Assigned',
//         lastReading: '3',
//         currentReading: '5',
//         estimatedReading: 0,
//         consumption: '2',
//         billMonth: '2025-12-01',
//         dueDate: '2025-12-16',
//         arrears: '0',
//         readerId: readerId || c.reader_id || c.readerId,
//     }
// }

class DbAsyncStorage {
    constructor() {}
    /**
     * @params keys - string
     */
    async get(keys, locationName) {
        const data = await AsyncStorage.getItem(keys);
        return data;
    }

    async set(keys, data, locationName) {
        await AsyncStorage.setItem(keys, JSON.stringify(data));
    }

    async deleteAll(keys, locationName) {
        await AsyncStorage.removeItem(keys);
    }
}


class DbSqlite {
    dbConnection = null;

    /**
     * 
     * @param {string[]} createTables 
     */
     constructor() {
        SQLite.openDatabaseAsync(c.dbName)
        .then(() => {
            for (const location in c.locations) {
                this.dbConnection.execAsync(c.locations[location].createTables);
            }

            console.log("Database Create Table function success"); 
        })
        .catch((e) => {
            throw new Error("Problem on opening database (sqlite) \n" + e);
        })
    }


    async get(keys, locationName) {
        const allRows = await this.dbConnection.getAllAsync(`SELECT * FROM ${locationName}`);
        const filtered = allRows.filter(row => row.storage_keys === keys);
        
        return filtered;
    }

    async set(keys, datas, locationName) {
        // await this.dbConnection.runAsync(`DELETE FROM ${readAndBill.name} WHERE storage_keys = ?`, [keys]);

        await this.deleteAll(keys, locationName);

        for (const data of datas) {
            await this.dbConnection.runAsync(
                `INSERT INTO ${locationName} (storage_keys, schedule_id, current_reading, reading_date, reader_notes, reader_id, consumption, status, lastAttempt, error, retryCount, customer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
                [keys, data.schedule_id, data.current_reading, data.reading_date, data.reader_notes, data.reader_id, data.consumption, data.status, data.lastAttempt, data.error, data.retryCount, JSON.stringify(data.customer)]
            );
        }
    }

    async deleteAll(keys, locationName) {
        await this.dbConnection.runAsync(`DELETE FROM ${locationName} WHERE storage_keys = ?`, [keys]);
    }
}


class dbService {
    useDbStorage = null; // sqlite;

    /**
     * 
     * @param {string} storage - "async-storage" or "sqlite"
     * 
     */
    constructor(storage) {
        this.setDB(storage);
    }

    setDB(selectedStorage) {
        if (selectedStorage === 'async-storage') {
            this.useDbStorage = new DbAsyncStorage();
        } else if (selectedStorage === 'sqlite') {
            this.useDbStorage = new DbSqlite();
        } else {
            throw new Error("storage not found");
        }
    }

    async get(keys, locationName) {
        return await this.useDbStorage.get(keys, locationName);
    }

    async set(keys, data, locationName) {
        await this.useDbStorage.set(keys, data, locationName);
    }

    async deleteAll(keys, locationName) {
        await this.useDbStorage.deleteAll(keys, locationName);
    }
}


// export default dbService;

export default new dbService(c.storage.ASYNC_STORAGE);
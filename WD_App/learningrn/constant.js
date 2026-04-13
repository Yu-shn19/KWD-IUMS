const config = {
    dbName: 'wd-database',
    storage: {
        ASYNC_STORAGE: 'async-storage',
        SQLITE: 'sqlite'
    },
    locations: { 
      readAndBill:  {
        name: 'read_and_bill',
        createTables:
            `CREATE TABLE IF NOT EXISTS read_and_bill (
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
            )`
      }
    }
}

export {
    config
}
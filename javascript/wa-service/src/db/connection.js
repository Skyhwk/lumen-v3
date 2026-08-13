const mysql = require('mysql2/promise');
const { loadEnv } = require('../config/env');

let pool = null;

function getPool() {
    if (!pool) {
        const { db } = loadEnv();
        pool = mysql.createPool({
            host: db.host,
            port: db.port,
            user: db.user,
            password: db.password,
            database: db.database,
            waitForConnections: true,
            connectionLimit: 10,
            timezone: '+00:00',
        });
    }
    return pool;
}

async function pingDatabase() {
    const connection = await getPool().getConnection();
    try {
        await connection.ping();
        return true;
    } finally {
        connection.release();
    }
}

module.exports = { getPool, pingDatabase };

require('dotenv').config({ path: require('path').join(__dirname, '../.env') });
const mysql = require('mysql2/promise');

(async () => {
    const pool = mysql.createPool({
        host: process.env.DB_HOST,
        user: process.env.DB_USERNAME,
        password: process.env.DB_PASSWORD,
        database: process.env.DB_DATABASE,
        timezone: '+00:00',
    });

    const [rows] = await pool.execute(
        `SELECT m.wa_message_id, m.type, m.content, m.sender_push_name, m.timestamp,
                m.raw_message IS NOT NULL AS has_raw, c.name
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.jid LIKE '%@g.us'
           AND (m.sender_push_name LIKE '%Wulan%' OR m.content LIKE '%Wulan%')
         ORDER BY m.timestamp DESC
         LIMIT 10`,
    );

    for (const row of rows) {
        console.log(
            row.timestamp, '|', row.name, '|', row.sender_push_name,
            '| type=', row.type, '| content=', JSON.stringify((row.content || '').slice(0, 60)),
            '| raw=', row.has_raw,
        );
    }

    const [recent] = await pool.execute(
        `SELECT m.wa_message_id, m.type, m.content, m.sender_push_name, m.timestamp, c.name
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.jid LIKE '%@g.us'
           AND m.timestamp >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR)
         ORDER BY m.timestamp DESC
         LIMIT 15`,
    );
    console.log('\nrecent group messages:');
    for (const row of recent) {
        console.log(
            row.timestamp?.toISOString?.()?.slice(11, 16), '|', row.sender_push_name || '?',
            '|', JSON.stringify((row.content || `[empty ${row.type}]`).slice(0, 50)),
        );
    }

    await pool.end();
})();

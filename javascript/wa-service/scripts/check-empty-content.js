require('dotenv').config({ path: require('path').join(__dirname, '../.env') });
const mysql = require('mysql2/promise');
const { parseBaileysMessage } = require('../src/utils/messageParser');

(async () => {
    const pool = mysql.createPool({
        host: process.env.DB_HOST,
        user: process.env.DB_USERNAME,
        password: process.env.DB_PASSWORD,
        database: process.env.DB_DATABASE,
        timezone: '+00:00',
    });

    const [rows] = await pool.execute(
        `SELECT m.wa_message_id, m.type, m.content, m.sender_push_name, m.raw_message, c.name, c.jid
         FROM wa_messages m
         JOIN wa_chats c ON c.id = m.chat_id
         WHERE c.jid LIKE '%@g.us'
           AND m.type = 'text'
           AND (m.content IS NULL OR m.content = '')
         ORDER BY m.timestamp DESC
         LIMIT 5`,
    );

    console.log('empty text messages:', rows.length);
    for (const row of rows) {
        console.log('\n---', row.name, '|', row.sender_push_name, '|', row.wa_message_id);
        if (row.raw_message) {
            try {
                const raw = JSON.parse(row.raw_message);
                const keys = Object.keys(raw.message || {});
                console.log('raw message keys:', keys);
                const reparsed = parseBaileysMessage(raw);
                console.log('reparsed content:', JSON.stringify(reparsed?.content));
                console.log('sample:', JSON.stringify(raw.message)?.slice(0, 400));
            } catch (e) {
                console.log('raw parse error:', e.message);
            }
        } else {
            console.log('no raw_message stored');
        }
    }

    await pool.end();
})();

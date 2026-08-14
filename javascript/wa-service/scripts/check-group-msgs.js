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

    try {
        const [grp] = await pool.execute(
            `SELECT COUNT(*) as c FROM wa_messages m
             JOIN wa_chats c ON c.id = m.chat_id
             WHERE c.jid LIKE '%@g.us'`,
        );
        console.log('group messages total:', grp[0].c);

        const [recent] = await pool.execute(
            `SELECT COUNT(*) as c FROM wa_messages m
             JOIN wa_chats c ON c.id = m.chat_id
             WHERE c.jid LIKE '%@g.us'
               AND m.timestamp >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY)`,
        );
        console.log('group messages last 3 days:', recent[0].c);

        const [last] = await pool.execute(
            `SELECT c.jid, c.name, m.content, m.timestamp, m.from_me, m.wa_message_id
             FROM wa_messages m
             JOIN wa_chats c ON c.id = m.chat_id
             WHERE c.jid LIKE '%@g.us'
             ORDER BY m.timestamp DESC
             LIMIT 8`,
        );
        console.log('recent group messages:');
        for (const row of last) {
            console.log(`  ${row.timestamp} | ${row.name || row.jid} | ${row.from_me ? 'me' : 'them'} | ${(row.content || '').slice(0, 40)}`);
        }

        const { getGroupMessageCutoff } = require('../src/utils/groupRetentionUtils');

        function toMysqlDatetime(value) {
            const date = value instanceof Date ? value : new Date(value || Date.now());
            return date.toISOString().slice(0, 19).replace('T', ' ');
        }

        const cutoff = toMysqlDatetime(getGroupMessageCutoff());
        console.log('\nretention cutoff (UTC mysql):', cutoff);

        const [chats] = await pool.execute(
            `SELECT jid, name, last_message, last_message_at, unread_count
             FROM wa_chats WHERE jid LIKE '%@g.us'
             ORDER BY last_message_at DESC LIMIT 8`,
        );
        console.log('\ngroup chats vs retained messages:');
        for (const row of chats) {
            const [cnt] = await pool.execute(
                `SELECT COUNT(*) as total,
                        SUM(CASE WHEN m.timestamp >= ? THEN 1 ELSE 0 END) as retained
                 FROM wa_messages m
                 JOIN wa_chats c ON c.id = m.chat_id
                 WHERE c.jid = ?`,
                [cutoff, row.jid],
            );
            console.log(
                `  ${row.name} | last=${row.last_message_at?.toISOString?.() || row.last_message_at}`
                + ` | unread=${row.unread_count}`
                + ` | msgs=${cnt[0].total}/${cnt[0].retained} retained`
                + ` | preview=${(row.last_message || '').slice(0, 30)}`,
            );
        }
    } catch (error) {
        console.error('error:', error.message);
    }

    await pool.end();
})();

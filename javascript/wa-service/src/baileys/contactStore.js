/**
 * In-memory contact store — pola yang sama dengan @ookamiiixd/baileys-store.
 * Mengumpulkan kontak dari contacts.upsert, contacts.update, dan messaging-history.set
 * agar sync manual / flush ke DB bisa mengambil seluruh kontak perangkat.
 */
function mergeContactRecord(existing = {}, incoming = {}) {
    const id = incoming.id || existing.id;
    if (!id) return null;

    const merged = { ...existing, ...incoming, id };

    // Jangan timpa field bernilai dengan undefined (bug urutan event Baileys #899)
    for (const field of ['name', 'notify', 'verifiedName', 'status', 'imgUrl']) {
        if (incoming[field] === undefined && existing[field] !== undefined) {
            merged[field] = existing[field];
        }
    }

    if (incoming.lid === undefined && existing.lid) merged.lid = existing.lid;
    if (incoming.jid === undefined && existing.jid) merged.jid = existing.jid;

    return merged;
}

function createContactStore() {
    const contacts = Object.create(null);

    function upsertMany(list = []) {
        for (const raw of list) {
            if (!raw?.id) continue;
            if (raw.id.endsWith('@g.us') || raw.id.endsWith('@broadcast')) continue;
            contacts[raw.id] = mergeContactRecord(contacts[raw.id], raw);
        }
    }

    function updateMany(list = []) {
        for (const raw of list) {
            if (!raw?.id) continue;
            contacts[raw.id] = mergeContactRecord(contacts[raw.id], raw);
        }
    }

    function bind(ev) {
        ev.on('contacts.upsert', upsertMany);
        ev.on('contacts.update', updateMany);
        ev.on('messaging-history.set', ({ contacts: historyContacts = [] }) => {
            if (historyContacts.length) upsertMany(historyContacts);
        });
    }

    function getAll() {
        return Object.values(contacts);
    }

    function size() {
        return Object.keys(contacts).length;
    }

    function clear() {
        for (const key of Object.keys(contacts)) {
            delete contacts[key];
        }
    }

    return {
        contacts,
        bind,
        upsertMany,
        updateMany,
        getAll,
        size,
        clear,
    };
}

module.exports = { createContactStore, mergeContactRecord };

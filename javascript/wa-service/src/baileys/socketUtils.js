async function closeSocket(sock) {
    if (!sock) return;
    try {
        sock.ev.removeAllListeners('connection.update');
        sock.ev.removeAllListeners('creds.update');
    } catch {
        // ignore
    }
    try {
        sock.end(undefined);
    } catch {
        // ignore
    }
}

module.exports = { closeSocket };

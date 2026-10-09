(() => {
    const log = document.getElementById('chat-messages');
    if (!log) return;
    const status = document.getElementById('chat-refresh-status');
    const empty = document.getElementById('chat-empty');
    let lastId = Math.max(0, ...Array.from(log.querySelectorAll('[data-message-id]'), item => Number(item.dataset.messageId)));
    let stopped = false;
    log.scrollTop = log.scrollHeight;
    async function refresh() {
        if (stopped) return;
        try {
            const url = new URL('get_messages.php', window.location.href);
            url.searchParams.set('request_id', log.dataset.requestId);
            url.searchParams.set('after_id', String(lastId));
            const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
            if (response.redirected || response.status === 403) {
                stopped = true;
                status.textContent = 'Chat access has ended. Sign in again or check the request status.';
                document.querySelector('#chat-composer button').disabled = true;
                return;
            }
            if (!response.ok) throw new Error('Refresh failed');
            const data = await response.json();
            const nearBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 90;
            for (const message of data.messages) {
                if (message.id <= lastId) continue;
                const bubble = document.createElement('article');
                bubble.className = `chat-bubble ${message.mine ? 'mine' : 'theirs'}`;
                bubble.dataset.messageId = String(message.id);
                const text = document.createElement('p');
                text.textContent = message.message;
                const time = document.createElement('time');
                time.textContent = message.created_at;
                bubble.append(text, time);
                log.append(bubble);
                lastId = message.id;
                empty.hidden = true;
            }
            if (nearBottom) log.scrollTop = log.scrollHeight;
            status.textContent = 'New messages refresh every 4 seconds.';
        } catch {
            status.textContent = 'Connection interrupted. Retrying automatically...';
        } finally {
            if (!stopped) window.setTimeout(refresh, 4000);
        }
    }
    document.getElementById('chat-composer').addEventListener('submit', event => {
        if (event.currentTarget.checkValidity()) event.currentTarget.querySelector('button').disabled = true;
    });
    refresh();
})();

"""Localhost integration checks. Creates and removes only disposable test records."""
import http.cookiejar
import json
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

BASE = 'http://localhost/book_exchange/'
MYSQL = r'C:\xampp\mysql\bin\mysql.exe'
ROOT = Path(__file__).resolve().parent.parent
prefix = 'chatcheck_' + secrets.token_hex(6)
ids = []
def sql(query):
    return subprocess.check_output([MYSQL, '-u', 'root', '-N', '-B', 'book_exchange', '-e', query], text=True, encoding='utf-8').strip()
def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c, page, data=None):
    try:
        r = c.open(BASE + page, None if data is None else urllib.parse.urlencode(data).encode())
        body = r.read().decode()
        assert 'Fatal error' not in body and 'Warning:' not in body, body[:250]
        return r.status, body, r.url
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode(), error.url
def csrf(html):
    return re.search(r'name="csrf_token" value="([a-f0-9]+)"', html)[1]
def submission(html):
    return re.search(r'name="submission_token" value="([a-f0-9]+)"', html)[1]
def endpoint(c, rid, after=0):
    return call(c, f'get_messages.php?request_id={rid}&after_id={after}')
def request(c, book):
    _, h, _ = call(c, f'exchange_request.php?book_id={book}')
    _, h, _ = call(c, f'exchange_request.php?book_id={book}', dict(csrf_token=csrf(h), message=prefix+' request'))
    assert 'sent successfully' in h
def deny(c, rid):
    code, h, _ = call(c, f'exchange_chat.php?request_id={rid}'); assert code == 403 and 'available only after' in h
    code, h, _ = endpoint(c, rid); assert code == 403 and 'messages' not in json.loads(h)
    _, h, _ = call(c, 'edit_profile.php')
    code, _, _ = call(c, f'exchange_chat.php?request_id={rid}', dict(csrf_token=csrf(h), submission_token=secrets.token_hex(16), message='Unauthorized message'))
    assert code == 403

original_counts = sql('SELECT (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM books), (SELECT COUNT(*) FROM exchange_requests), (SELECT COUNT(*) FROM exchange_messages)')
try:
    clients = [client(), client(), client()]
    for i, c in enumerate(clients):
        email = f'{prefix}_{i}@example.test'
        _, h, _ = call(c, 'register.php')
        code, h, url = call(c, 'register.php', dict(csrf_token=csrf(h), name=f'{prefix} User {i}', email=email, password='ChatTest123!', confirm_password='ChatTest123!', age='23', phone='', address=prefix+' PRIVATE ADDRESS'))
        assert url.endswith('login.php')
        ids.append(int(sql(f"SELECT id FROM users WHERE email='{email}'")))
        _, h, _ = call(c, 'login.php')
        _, h, url = call(c, 'login.php', dict(csrf_token=csrf(h), email=email, password='ChatTest123!'))
        assert url.endswith('dashboard.php')
    owner, requester, outsider = clients
    books = []
    for i in range(2):
        _, h, _ = call(owner, 'add_book.php')
        _, h, url = call(owner, 'add_book.php', dict(csrf_token=csrf(h), title=f'{prefix} Book {i}', author='Chat test', category='Fiction', description='', condition_status='Good'))
        assert url.endswith('my_books.php')
        books.append(int(sql(f"SELECT id FROM books WHERE title='{prefix} Book {i}' AND user_id={ids[0]}")))
        request(requester, books[i])
    rid = int(sql(f'SELECT id FROM exchange_requests WHERE book_id={books[0]} AND requester_id={ids[1]}'))
    rejected = int(sql(f'SELECT id FROM exchange_requests WHERE book_id={books[1]} AND requester_id={ids[1]}'))
    for c in clients: deny(c, rid)
    for c, page in [(owner, 'received_requests.php'), (requester, 'my_requests.php')]:
        _, h, _ = call(c, page); assert f'exchange_chat.php?request_id={rid}' not in h
    print('PASS Pending requests: no chat links, no page/read/send access')
    _, h, _ = call(owner, 'edit_profile.php')
    _, h, _ = call(owner, 'update_request.php', dict(csrf_token=csrf(h), request_id=rid, action='accept'))
    assert 'Chat is now available.' in h
    assert sql(f'SELECT exchange_status FROM books WHERE id={books[0]}') == 'Available'
    for c, page in [(owner, 'received_requests.php'), (requester, 'my_requests.php')]:
        _, h, _ = call(c, page); assert f'exchange_chat.php?request_id={rid}' in h
    for c, other in [(owner, 1), (requester, 0)]:
        code, h, _ = call(c, f'exchange_chat.php?request_id={rid}')
        assert code == 200 and f'With: <strong>{prefix} User {other}</strong>' in h
        assert prefix+' PRIVATE ADDRESS' not in h
        assert 'Start your exchange conversation' in h
    print('PASS Accepted chat links for both participants, correct partner, no address disclosure, unchanged book status')
    deny(outsider, rid)
    _, h, _ = call(outsider, 'exchange_chats.php'); assert f'exchange_chat.php?request_id={rid}' not in h
    for invalid in ['0', '-1', '99999999', 'abc', '1%20OR%201=1']:
        code, _, _ = call(owner, f'exchange_chat.php?request_id={invalid}'); assert code == 403
    print('PASS third-party and forged request ID access denied for page, reads and sends')
    _, h, _ = call(owner, f'exchange_chat.php?request_id={rid}')
    payload = dict(csrf_token=csrf(h), submission_token=submission(h), message='Where should I give the book?', sender_id=ids[2], owner_id=ids[2], requester_id=ids[2])
    _, h, url = call(owner, f'exchange_chat.php?request_id={rid}', payload); assert url.endswith(f'request_id={rid}')
    call(owner, f'exchange_chat.php?request_id={rid}', payload)
    assert sql(f'SELECT COUNT(*) FROM exchange_messages WHERE request_id={rid}') == '1'
    assert sql(f'SELECT sender_id FROM exchange_messages WHERE request_id={rid}') == str(ids[0])
    _, h, _ = endpoint(requester, rid); messages = json.loads(h)['messages']; assert messages[0]['message'] == payload['message'] and not messages[0]['mine']
    first_id = messages[0]['id']
    _, h, _ = call(requester, f'exchange_chat.php?request_id={rid}')
    attack = '<script>alert("xss")</script> We can meet near the college. 📚'
    call(requester, f'exchange_chat.php?request_id={rid}', dict(csrf_token=csrf(h), submission_token=submission(h), message=attack))
    _, h, _ = endpoint(owner, rid, first_id); messages = json.loads(h)['messages']; assert len(messages)==1 and messages[0]['message']==attack and not messages[0]['mine']
    _, h, _ = call(owner, f'exchange_chat.php?request_id={rid}'); assert '<script>alert(' not in h and '&lt;script&gt;' in h
    _, h, _ = call(requester, 'exchange_chats.php'); assert f'exchange_chat.php?request_id={rid}' in h and '&lt;script&gt;' in h
    print('PASS two-way messaging, forged sender ignored, submission replay deduplicated, incremental polling, Unicode and escaped HTML')
    for message in ['', '   ', 'a'*2001]:
        _, h, _ = call(owner, f'exchange_chat.php?request_id={rid}')
        code, _, _ = call(owner, f'exchange_chat.php?request_id={rid}', dict(csrf_token=csrf(h), submission_token=submission(h), message=message)); assert code == 422
    code, _, _ = call(owner, f'exchange_chat.php?request_id={rid}', dict(message='Missing CSRF')); assert code==403
    _, h, _ = call(owner, 'edit_profile.php')
    call(owner, 'update_request.php', dict(csrf_token=csrf(h), request_id=rejected, action='reject'))
    for c in clients: deny(c, rejected)
    _, h, _ = call(owner, 'edit_profile.php')
    call(owner, 'update_request.php', dict(csrf_token=csrf(h), request_id=rejected, action='accept'))
    assert sql(f'SELECT status FROM exchange_requests WHERE id={rejected}') == 'Rejected'
    call(owner, 'update_request.php', dict(csrf_token=csrf(h), request_id=rid, action='reject'))
    assert sql(f'SELECT status FROM exchange_requests WHERE id={rid}') == 'Accepted'
    print('PASS empty/oversized message and CSRF validation, Rejected chat denial, terminal request states')
    anon = client()
    for page in ['exchange_chat.php','exchange_chats.php','get_messages.php']:
        _, _, url = call(anon, page); assert url.endswith('login.php')
    print('PASS logged-out protection on all chat routes')
    import os
    if os.environ.get('CHAT_VISUAL_CHECK') == '1':
        import tempfile
        _, html, _ = call(owner, f'exchange_chat.php?request_id={rid}')
        html = html.replace('<head>', '<head><base href="http://localhost/book_exchange/">').replace('<script src="assets/chat.js" defer></script>', '')
        with tempfile.TemporaryDirectory(prefix='book-chat-preview-') as preview:
            preview = Path(preview)
            snapshot = preview/'chat.html'
            snapshot.write_text(html, encoding='utf-8')
            subprocess.run(['node', str(ROOT/'tests/preview_chat.mjs'), str(snapshot), str(preview/'chrome-profile'), str(ROOT/'tests')], timeout=45, check=True)
        print('Captured desktop/mobile chat layout using disposable test conversation')

finally:
    if ids:
        ids_sql = ','.join(map(str, ids))
        sql(f'DELETE FROM exchange_requests WHERE owner_id IN ({ids_sql}) OR requester_id IN ({ids_sql}); DELETE FROM books WHERE user_id IN ({ids_sql}); DELETE FROM users WHERE id IN ({ids_sql});')
    assert sql('SELECT (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM books), (SELECT COUNT(*) FROM exchange_requests), (SELECT COUNT(*) FROM exchange_messages)') == original_counts
    print('Removed only temporary chat test records; original record counts unchanged')

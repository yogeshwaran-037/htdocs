# Accepted exchange chats

Private chat is linked to an exchange request. The owner and requester can open it only while that request is `Accepted`. Pending and Rejected requests never allow chat reads or sends, including direct URLs and polling endpoints.

## Files created

- `exchange_chat.php`: private conversation, header, information card, validated POST messages and redirect after sending.
- `exchange_chats.php`: current user's accepted exchanges and last-message previews.
- `get_messages.php`: authorized incremental JSON polling.
- `includes/chat.php`: shared participant/status checks and prepared message queries.
- `assets/chat.css`, `assets/chat.js`: responsive bubbles and refresh every four seconds.
- `schema_chat.sql`: new `exchange_messages` table, foreign keys, message-order index and unique submission token to prevent duplicate retries.
- `tests/verify_chat.py`, `tests/preview_chat.mjs`: integration checks and optional desktop/mobile layout verification.
- `tests/chat-desktop.png`, `tests/chat-mobile.png`: previews using disposable test conversations.
- `CHAT_TESTING.md`: this guide.

## Files modified

- `my_requests.php`: selects request ID and shows Open Exchange Chat only for Accepted rows.
- `received_requests.php`: accepted chat link, pending explanation and rejected explanation; accept/reject remain pending-only.
- `update_request.php`: success message announces chat availability. Acceptance keeps book status unchanged, rejects competing pending requests and prevents accepting another request for an already accepted book. Existing owner authorization, locking and Pending-only state changes are retained.
- `includes/sidebar.php`: Exchange Chats navigation and current-page highlight.
- `profile.php`: describes accepted exchange activity rather than claiming a completed handover.
- `tests/verify_project.py`, `TESTING.md`: update the previous acceptance expectations to preserve the book status.

`config/db.php`, authentication, My Books authorization and existing application data were preserved. Temporary editing scripts were removed.

## Database

Inspected existing tables first. No message table existed, so `exchange_messages` was created. Existing tables were not altered or reset. Each message references its request and its sender. Deleting a request cascades to its messages; the application's existing book-deletion guard continues to retain books with request history.

Do not rerun migrations on the current database; the table is already installed.

## Security and behavior

- Every page/read/send checks Accepted status and session user membership in owner/requester. Third users receive a generic denial without book, participant or message details.
- Sender identity always comes from `$_SESSION['user_id']`; posted owner/requester/sender fields are ignored.
- All queries use prepared statements. Sending also locks the accepted request, validates CSRF, rejects blank messages and limits text to 2,000 UTF-8 characters.
- Submission tokens and a database unique key prevent duplicate retries. Successful sending redirects with HTTP 303.
- Server-rendered messages are escaped; polling inserts message text using `textContent`.
- Both participants use the same `request_id`. The other person's name/profile is computed relative to the current session.
- No profile address or contact details are fetched into the conversation. Users can voluntarily type an address, meeting location or phone number.
- Chat does not complete an exchange or change the book's status. No completion button or confirmation workflow was added. Books marked Exchanged before this change retain that existing status.

## Manual test: Thangapandi and Yogesh

1. In normal Chrome, log in as Thangapandi (owner). In Chrome Incognito, log in as Yogesh (requester). Choose an available book belonging to Thangapandi; use a disposable book if existing requests would prevent testing acceptance.
2. Yogesh opens Browse Books, the chosen Book Details, and Send Exchange Request. Submit a message.
3. While Pending, neither user's request row should show Open Exchange Chat. Directly opening `exchange_chat.php?request_id=ID` or `get_messages.php?request_id=ID` must deny access.
4. Thangapandi opens Received Requests and accepts Yogesh's request. The success message must say: "Exchange request accepted. Chat is now available." Book status must stay unchanged.
5. Thangapandi's Received Requests and Yogesh's My Requests should now show Open Exchange Chat. Both can also find it under the Exchange Chats sidebar item.
6. Open the chat in both windows. Check the book title, Accepted status, both participant links and the correct other person's name. An empty chat should invite you to start the conversation.
7. Thangapandi sends "Where should I give the book?" Yogesh should see it automatically within about four seconds. Yogesh replies "We can meet near the college." Thangapandi should see the reply without a page reload. Your own messages appear on the right; the other person's appear on the left.
8. Test a blank message, more than 2,000 characters, and text containing HTML. Blank/oversized text must be refused; HTML must display as ordinary text. Refreshing after sending must not duplicate the message.
9. In a third separate browser profile, log in as another user. Paste the same chat URL and polling URL. Both must deny access. The chat must not appear in that user's Exchange Chats list. A forged POST to the same chat must also be denied.
10. Use another book/request to test Reject. It must show Request rejected, no chat button, and deny direct chat/poll/send access. Trying to accept the already rejected request or reject the already accepted request must leave its status unchanged.
11. Log out, then open any chat route: it must redirect to login. Check the Menu/sidebar, messages and Send form at mobile width.

## Validation

All PHP files passed syntax validation; `assets/chat.js` passed Node syntax checking. Localhost tests passed Pending/Rejected denial, participant-only access, forged IDs and sender fields, two-way messaging, polling cursors, duplicate retries, CSRF, length validation, Unicode/HTML escaping, unchanged book status and terminal request states. Existing ownership, login, profile, request and upload regression checks also passed.

Desktop (1,440 px) and mobile (390 px) layouts were rendered in headless Chrome and checked for horizontal overflow. These previews use test conversations and are protected by the tests folder's existing Apache access rule.

Repeat functional checks with:

```powershell
python -X utf8 C:\xampp\htdocs\book_exchange\tests\verify_chat.py
python -X utf8 C:\xampp\htdocs\book_exchange\tests\verify_project.py
```

Optional Chrome layout checks:

```powershell
$env:CHAT_VISUAL_CHECK = '1'
python -X utf8 C:\xampp\htdocs\book_exchange\tests\verify_chat.py
```

Tests create disposable accounts, books, requests and messages, then remove only those test records. Original record counts are checked afterward.

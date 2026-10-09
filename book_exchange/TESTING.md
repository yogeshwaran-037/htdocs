# Book Exchange Easy: changes and verification

Open http://localhost/book_exchange/login.php with Apache and MySQL running in XAMPP.

## Changed existing files

- `add_book.php`: optional verified JPG/PNG/WebP upload, random filename, 2 MB limit, existing `book_image` column, session ownership, CSRF, validation, shared navigation.
- `my_books.php`: owner-filtered image cards and POST deletion with CSRF.
- `edit_book.php`: preserves owner-filtered SELECT/UPDATE; adds CSRF, input validation and shared UI.
- `delete_book.php`: POST only; transactional ownership check and owner-filtered DELETE. Books with any exchange requests are retained to preserve history.
- `browse_books.php`: includes all users' available books; qualified `books.created_at`; images and exchange links for other owners.
- `book_details.php`: images and shared UI; public owner profile links retained.
- `exchange_request.php`: CSRF and transactional book locking around duplicate detection and request creation; self requests remain forbidden.
- `update_request.php`: owner authorization, locking and atomic acceptance; accepted requests enable private chat without changing the book status, competing pending requests become `Rejected`.
- `dashboard.php`: book statistics, request counts and quick actions.
- `my_requests.php`, `received_requests.php`: book images, both participants and profile links, protected decision forms.
- `profile.php`: full collection with images; public profile and accepted exchange history retained.
- `edit_profile.php`: session-only profile editing retained; CSRF and shared UI.
- `login.php`, `register.php`, `logout.php`: shared session handling; form CSRF; shared styling. Password hashing/verification and login session regeneration retained.
- `config/db.php`: UTF-8 connection and generic connection failure response.
- `includes/layout.php`, `includes/sidebar.php`: shared theme, current page highlighting and mobile menu; duplicate page navbar markup removed.

## New files

`includes/security.php`, `assets/app.css`, `assets/book-placeholder.svg`, `uploads/.htaccess`, `schema_ownership.sql`, `tests/verify_project.py`, `tests/.htaccess`, and this document. Upload folders: `uploads/books/` and `uploads/profile/`.

## Database

Inspected the live `users`, `books`, `categories` and `exchange_requests` tables before editing. Existing `books.user_id` and `books.book_image` were reused. No tables or columns added, and existing application records were preserved.

The following ALTER was already applied after checking that no books had invalid user IDs:

```sql
ALTER TABLE books ADD CONSTRAINT books_user_fk
FOREIGN KEY (user_id) REFERENCES users(id);
```

`schema_ownership.sql` records this change. Do not rerun it on this database; the constraint already exists.

## Manual tests with User 1 and User 2

1. Use separate Chrome profiles, or normal Chrome for User 1 and an incognito session for User 2. Tabs in the same profile share PHP login cookies. Multiple incognito windows also share their incognito session; they do not provide separate accounts.
2. Register/login both accounts. User 1 adds Book A; User 2 adds Book B. Optionally upload an image for A and leave B without one.
3. User 1's My Books must show A only. User 2's must show B only. Browse Books must show both while they are available. A shows its image and B shows the book placeholder.
4. Each owner can edit their own book. Save an edit and refresh the other account's Browse Books to see it.
5. Record B's ID from its details URL. While logged in as User 1, open `edit_book.php?id=B_ID`. Permission must be refused. Submitting an edit with B's ID must also leave B unchanged.
6. Open `delete_book.php?id=B_ID` as User 1. It must refuse GET deletion. To test a forged POST, use developer tools on User 1's My Books: change A's Delete form hidden `id` to B's ID, keep the CSRF token, and submit. Expect HTTP 403 and B to remain. Repeat in the other direction.
7. Delete an owner's disposable book with no requests using its normal Delete button. It should disappear. Books with requests are retained with an explanatory message to preserve exchange history.
8. As User 2, open A's details and its owner's profile, then send an exchange message. My Requests must show A, the message, both participants, profile links, date and Pending status. Duplicate pending requests must be refused.
9. As User 1, Received Requests must identify User 2 and link to their public profile. Accept it: both accounts must see Accepted, and A must keep its existing book status. Both users can open the private exchange chat. If another account had a pending request for A, it becomes Rejected.
10. Use a new available book to test Reject. The request becomes Rejected while the book stays Available. A requester must not be able to accept/reject by forging a request ID in `update_request.php`.
11. Owners must have no Send Exchange Request link for their own books. Opening their own `exchange_request.php?book_id=...` directly must refuse the request.
12. Test valid JPG/PNG/WebP, a renamed non-image and an image above 2 MB. Only valid images within the limit should be stored. Edit another public profile's URL; edit_profile.php must still edit only the current account.
13. Log out and directly open dashboard.php, my_books.php and other protected pages. Each must redirect to login.php. Test navigation at desktop and narrow mobile widths using the Menu button.

## Automated validation

All PHP files passed `C:\xampp\php\php.exe -l`. Localhost integration checks passed registration/login, two-account isolation, shared browsing, unauthorized edits/deletes, CSRF, POST-only deletion, owner edits/deletion, own-book request prevention, request decisions, competing rejection, history retention, public profiles, protected redirects, real PNG upload/display, and invalid/oversized upload rejection.

To repeat (creates disposable test accounts and removes only their rows and uploaded image afterward):

```powershell
python -X utf8 C:\xampp\htdocs\book_exchange\tests\verify_project.py
```

Automated checks verify HTTP behavior and database state. Desktop/mobile appearance should also be checked in your browser.

import urllib.request, urllib.parse, urllib.error, http.cookiejar, re, subprocess, time, json, base64
from pathlib import Path
base='http://localhost/book_exchange/'
mysql=r'C:\xampp\mysql\bin\mysql.exe'
def sql(q):
 return subprocess.check_output([mysql,'-u','root','-N','-B','book_exchange','-e',q],text=True).strip()
def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(c,page,data=None):
 try:
  r=c.open(base+page,None if data is None else urllib.parse.urlencode(data).encode()); html=r.read().decode(); assert 'Fatal error' not in html and 'Warning:' not in html, html[:300]; return r.status,html,r.url
 except urllib.error.HTTPError as e: return e.code,e.read().decode(),e.url
def token(html): return re.search(r'name="csrf_token" value="([a-f0-9]+)"',html)[1]
prefix='codexcheck'+str(int(time.time()))
clients=[client(),client(),client()]; ids=[]; images=[]
try:
 for i,c in enumerate(clients):
  email=f'{prefix}{i}@example.test'; _,h,_=call(c,'register.php')
  code,h,url=call(c,'register.php',dict(csrf_token=token(h),name=f'{prefix} User {i}',email=email,password='Testpass123!',confirm_password='Testpass123!',age='22',phone='',address=''))
  assert url.endswith('login.php'),h
  ids.append(int(sql(f"SELECT id FROM users WHERE email='{email}'")))
  _,h,_=call(c,'login.php'); code,h,url=call(c,'login.php',dict(csrf_token=token(h),email=email,password='Testpass123!')); assert url.endswith('dashboard.php')
 print('PASS registration and independent login sessions')
 books=[]
 for i,c in enumerate(clients[:2]):
  _,h,_=call(c,'add_book.php'); code,h,url=call(c,'add_book.php',dict(csrf_token=token(h),title=f'{prefix} Book {i}',author='Test author',category='Fiction',condition_status='Good',description='Integration test'))
  assert url.endswith('my_books.php'); books.append(int(sql(f'SELECT id FROM books WHERE user_id={ids[i]}')))
 for i,c in enumerate(clients[:2]):
  _,h,_=call(c,'my_books.php'); assert f'{prefix} Book {i}' in h and f'{prefix} Book {1-i}' not in h
  _,h,_=call(c,'browse_books.php'); assert all(f'{prefix} Book {j}' in h for j in range(2))
  _,h,_=call(c,f'edit_book.php?id={books[1-i]}'); assert 'do not have permission' in h
  _,h,_=call(c,'my_books.php'); t=token(h)
  code,_,_=call(c,'delete_book.php',dict(csrf_token=t,id=books[1-i])); assert code==403
  code,_,_=call(c,f'delete_book.php?id={books[1-i]}'); assert code==405
  code,_,_=call(c,'delete_book.php',dict(id=books[i])); assert code==403
 print('PASS ownership filtering, shared browsing, unauthorized edit/delete, CSRF and GET protection')
 _,h,_=call(clients[0],f'edit_book.php?id={books[0]}'); code,h,url=call(clients[0],f'edit_book.php?id={books[0]}',dict(csrf_token=token(h),title=f'{prefix} Book 0 edited',author='Test author',category='Fiction',description='Updated',condition_status='Good')); assert url.endswith('my_books.php')
 _,h,_=call(clients[0],f'exchange_request.php?book_id={books[0]}'); assert 'your own book' in h
 for c in clients[1:]:
  _,h,_=call(c,f'exchange_request.php?book_id={books[0]}'); _,h,_=call(c,f'exchange_request.php?book_id={books[0]}',dict(csrf_token=token(h),message='Test exchange')); assert 'sent successfully' in h
 requests=[int(x) for x in sql(f'SELECT id FROM exchange_requests WHERE book_id={books[0]} ORDER BY id').splitlines()]
 _,h,_=call(clients[1],'my_books.php'); code,_,_=call(clients[1],'update_request.php',dict(csrf_token=token(h),request_id=requests[0],action='accept')); assert code==403
 _,h,_=call(clients[0],'received_requests.php'); assert all(f'{prefix} User {i}' in h for i in [1,2])
 _,h,_=call(clients[0],'update_request.php',dict(csrf_token=token(h),request_id=requests[0],action='accept'))
 assert sql(f'SELECT status FROM exchange_requests WHERE id={requests[0]}')=='Accepted'
 assert sql(f'SELECT status FROM exchange_requests WHERE id={requests[1]}')=='Rejected'
 assert sql(f'SELECT exchange_status FROM books WHERE id={books[0]}')=='Available'
 _,h,_=call(clients[0],f'profile.php?id={ids[1]}'); assert f'{prefix} User 1' in h
 _,h,_=call(clients[1],'my_requests.php'); assert 'Accepted' in h
 _,h,_=call(clients[0],'my_books.php'); call(clients[0],'delete_book.php',dict(csrf_token=token(h),id=books[0])); assert sql(f'SELECT COUNT(*) FROM books WHERE id={books[0]}')=='1'
 _,h,_=call(clients[1],'my_books.php'); call(clients[1],'delete_book.php',dict(csrf_token=token(h),id=books[1])); assert sql(f'SELECT COUNT(*) FROM books WHERE id={books[1]}')=='0'
 print('PASS owner edit/delete, self-request prevention, request visibility, unauthorized acceptance, atomic acceptance, competing rejection, history preservation and public profile')
 c=client()
 for page in ['dashboard.php','my_books.php','browse_books.php','add_book.php','profile.php','edit_profile.php','received_requests.php','my_requests.php','edit_book.php','exchange_request.php']:
  _,_,url=call(c,page); assert url.endswith('login.php'),page
 print('PASS protected page redirects')
 # Exercise multipart upload validation using a real tiny PNG and invalid files.
 def multipart(c, fields, filename, content):
  boundary='CodexUploadBoundary123'
  parts=[]
  for key,value in fields.items():
   parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
  parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="book_image"; filename="{filename}"\r\nContent-Type: image/png\r\n\r\n'.encode()+content+b'\r\n')
  parts.append(f'--{boundary}--\r\n'.encode())
  req=urllib.request.Request(base+'add_book.php',data=b''.join(parts),headers={'Content-Type':f'multipart/form-data; boundary={boundary}'})
  r=c.open(req); return r.read().decode()
 _,h,_=call(clients[1],'add_book.php')
 fields=dict(csrf_token=token(h),title=prefix+' Image book',author='Upload test',category='Fiction',description='',condition_status='Good')
 png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
 h=multipart(clients[1],fields,'cover.png',png); assert 'added successfully' in h
 image=sql(f'SELECT book_image FROM books WHERE user_id={ids[1]}'); images.append(image)
 assert image.startswith('uploads/books/') and Path(__file__).parent.parent.joinpath(image).is_file()
 imageid=int(sql(f'SELECT id FROM books WHERE user_id={ids[1]}'))
 for page in ['my_books.php','browse_books.php',f'book_details.php?id={imageid}']:
  _,h,_=call(clients[1],page); assert image in h
 h=multipart(clients[1],fields,'fake.png',b'<?php echo 1; ?>'); assert 'must be JPG' in h
 h=multipart(clients[1],fields,'large.png',png+b'0'*(2*1024*1024)); assert 'smaller than 2 MB' in h
 _,h,_=call(clients[0],f'exchange_request.php?book_id={imageid}'); call(clients[0],f'exchange_request.php?book_id={imageid}',dict(csrf_token=token(h),message='Please reject'))
 rid=int(sql(f'SELECT id FROM exchange_requests WHERE book_id={imageid}'))
 _,h,_=call(clients[1],'received_requests.php'); call(clients[1],'update_request.php',dict(csrf_token=token(h),request_id=rid,action='reject'))
 assert sql(f'SELECT status FROM exchange_requests WHERE id={rid}')=='Rejected'
 assert sql(f'SELECT exchange_status FROM books WHERE id={imageid}')=='Available'
 print('PASS real PNG upload and display, invalid/oversized upload rejection and owner request rejection')
finally:
 if ids:
  idlist=','.join(map(str,ids)); sql(f'DELETE FROM exchange_requests WHERE requester_id IN ({idlist}) OR owner_id IN ({idlist}); DELETE FROM books WHERE user_id IN ({idlist}); DELETE FROM users WHERE id IN ({idlist});')
 for image in images:
  Path(__file__).parent.parent.joinpath(image).unlink(missing_ok=True)
 print('Removed only temporary integration test records')
